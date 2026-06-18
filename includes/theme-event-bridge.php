<?php
/**
 * Pont entre le CPT lfi_evenement (plugin) et le CPT du thème
 * (mobilisation / evenement / etc.) qui alimente la section
 * « Mobilisations à venir » et son calendrier sur le front.
 *
 * Deux directions :
 *   1. À la création/modif d'un lfi_evenement → on miroir dans le CPT du thème
 *      (avec tous les meta keys connus pour la date et le lieu).
 *   2. Sur les requêtes front du CPT du thème → on cache les événements passés
 *      via pre_get_posts en faisant une OR sur tous les meta keys de date connus.
 */
if (!defined('ABSPATH')) exit;

/* ------------------------------------------------------------------ */
/* Détection                                                            */
/* ------------------------------------------------------------------ */

/**
 * Liste des CPT susceptibles d'être utilisés par le thème pour les événements.
 */
function lfi_nct_theme_event_cpt_candidates() {
    return apply_filters('lfi_nct_theme_event_cpt_candidates', [
        'mobilisation', 'mobilisations',
        'evenement', 'evenements',
        'event', 'events',
        'ag_event', 'ag_evenement', 'ag_events',
        'tribe_events', 'mec-events',
    ]);
}

/**
 * Trouve le premier CPT enregistré parmi nos candidats. Renvoie '' si rien.
 */
function lfi_nct_detect_theme_event_cpt() {
    static $cached = null;
    if ($cached !== null) return $cached;
    foreach (lfi_nct_theme_event_cpt_candidates() as $c) {
        if ($c === LFI_NCT_EVT_CPT) continue; // évite de se mirrorer soi-même
        if (post_type_exists($c)) { $cached = $c; return $cached; }
    }
    $cached = '';
    return $cached;
}

/**
 * Liste des meta_key utilisés par les thèmes/plugins connus pour stocker la date début.
 */
function lfi_nct_theme_event_date_keys() {
    return apply_filters('lfi_nct_theme_event_date_keys', [
        'event_date', 'date_evenement', '_event_start_date', 'start_date',
        'event_start_date', '_EventStartDate', 'mec_event_date',
    ]);
}

function lfi_nct_theme_event_location_keys() {
    return apply_filters('lfi_nct_theme_event_location_keys', [
        'event_location', 'lieu', 'location', '_event_location',
        '_EventVenue', 'mec_event_location',
    ]);
}

/* ------------------------------------------------------------------ */
/* Miroir : lfi_evenement → CPT du thème                                */
/* ------------------------------------------------------------------ */

const LFI_NCT_THEME_MIRROR_META = '_lfi_evt_theme_mirror_id';

add_action('save_post_' . LFI_NCT_EVT_CPT, 'lfi_nct_mirror_event_to_theme_cpt', 20, 3);
function lfi_nct_mirror_event_to_theme_cpt($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if ($post->post_status !== 'publish') return;
    if (wp_is_post_revision($post_id)) return;

    $cpt = lfi_nct_detect_theme_event_cpt();
    if ($cpt === '') return;

    $date_debut = get_post_meta($post_id, '_lfi_evt_date_debut', true);
    $date_fin   = get_post_meta($post_id, '_lfi_evt_date_fin',   true);
    $lieu       = get_post_meta($post_id, '_lfi_evt_lieu',       true);
    $adresse    = get_post_meta($post_id, '_lfi_evt_adresse',    true);
    if (!$date_debut) return;

    $mirror_id = (int) get_post_meta($post_id, LFI_NCT_THEME_MIRROR_META, true);
    $exists    = $mirror_id ? get_post($mirror_id) : null;

    $title   = get_the_title($post);
    $content = $post->post_content;
    $excerpt = $post->post_excerpt;

    $args = [
        'post_type'    => $cpt,
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_content' => $content,
        'post_excerpt' => $excerpt,
        'post_date'    => $date_debut, // post_date = date de l'événement (utile si thème trie là-dessus)
    ];
    if ($exists && $exists->post_type === $cpt) {
        $args['ID'] = $mirror_id;
        $new_id = wp_update_post($args, true);
    } else {
        $new_id = wp_insert_post($args, true);
        if (!is_wp_error($new_id) && $new_id) {
            update_post_meta($post_id, LFI_NCT_THEME_MIRROR_META, (int) $new_id);
        }
    }
    if (is_wp_error($new_id) || !$new_id) return;

    // Image à la une recopiée
    $thumb_id = get_post_thumbnail_id($post_id);
    if ($thumb_id) set_post_thumbnail($new_id, $thumb_id);

    // Couvre tous les meta_keys de date / lieu utilisés par les thèmes connus
    foreach (lfi_nct_theme_event_date_keys() as $k) {
        update_post_meta($new_id, $k, $date_debut);
    }
    if ($date_fin) {
        foreach (['event_end_date', '_event_end_date', '_EventEndDate'] as $k) {
            update_post_meta($new_id, $k, $date_fin);
        }
    }
    $loc_full = trim(($lieu ? $lieu : '') . ($adresse ? (($lieu ? ' — ' : '') . $adresse) : ''));
    if ($loc_full !== '') {
        foreach (lfi_nct_theme_event_location_keys() as $k) {
            update_post_meta($new_id, $k, $loc_full);
        }
    }
    foreach (['event_time', 'heure', '_event_time', '_EventStartTime'] as $k) {
        update_post_meta($new_id, $k, date('H:i', strtotime($date_debut)));
    }

    // Pointe l'original pour ouvrir la page du plugin au clic depuis le calendrier
    update_post_meta($new_id, '_lfi_evt_origin_id', $post_id);
}

/* Quand on supprime un lfi_evenement → on supprime aussi son miroir */
add_action('before_delete_post', 'lfi_nct_delete_theme_mirror');
function lfi_nct_delete_theme_mirror($post_id) {
    if (get_post_type($post_id) !== LFI_NCT_EVT_CPT) return;
    $mirror_id = (int) get_post_meta($post_id, LFI_NCT_THEME_MIRROR_META, true);
    if ($mirror_id) {
        wp_delete_post($mirror_id, true);
    }
}

/* ------------------------------------------------------------------ */
/* Filtre : cache les événements passés du CPT du thème                 */
/* ------------------------------------------------------------------ */

add_action('pre_get_posts', 'lfi_nct_hide_past_theme_events', 20);
function lfi_nct_hide_past_theme_events($q) {
    if (is_admin() || !$q->is_main_query()) {
        // On laisse aussi les sous-requêtes "Mobilisations à venir" passer ce filtre.
        // pre_get_posts est appelé pour toutes les WP_Query. On filtre uniquement quand
        // le post_type matche un CPT thème connu.
    }
    $post_type = $q->get('post_type');
    if (empty($post_type)) return;
    if (is_array($post_type)) {
        $match = array_intersect($post_type, lfi_nct_theme_event_cpt_candidates());
        if (empty($match)) return;
    } else {
        if (!in_array($post_type, lfi_nct_theme_event_cpt_candidates(), true)) return;
    }
    // Ne touche pas si l'admin demande explicitement "all".
    if ($q->get('lfi_show_past') === '1') return;

    $now = current_time('Y-m-d H:i:s');
    $today = current_time('Y-m-d');
    $or = ['relation' => 'OR'];
    foreach (lfi_nct_theme_event_date_keys() as $k) {
        $or[] = [
            'key'     => $k,
            'value'   => $today,
            'compare' => '>=',
            'type'    => 'DATE',
        ];
        $or[] = [
            'key'     => $k,
            'value'   => $now,
            'compare' => '>=',
            'type'    => 'DATETIME',
        ];
    }
    $existing = $q->get('meta_query');
    if (!is_array($existing)) $existing = [];
    $existing[] = $or;
    $q->set('meta_query', $existing);
}

/* ------------------------------------------------------------------ */
/* Seed initial : miroir tous les lfi_evenement déjà créés              */
/* ------------------------------------------------------------------ */

const LFI_NCT_THEME_MIRROR_SEED_FLAG = 'lfi_nct_theme_mirror_seed_done';

add_action('init', 'lfi_nct_theme_mirror_existing_events', 35);
function lfi_nct_theme_mirror_existing_events() {
    if (get_option(LFI_NCT_THEME_MIRROR_SEED_FLAG) === 'done') return;
    if (lfi_nct_detect_theme_event_cpt() === '') return;
    $posts = get_posts([
        'post_type'      => LFI_NCT_EVT_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ]);
    foreach ($posts as $p) {
        lfi_nct_mirror_event_to_theme_cpt($p->ID, $p, true);
    }
    update_option(LFI_NCT_THEME_MIRROR_SEED_FLAG, 'done', false);
}
