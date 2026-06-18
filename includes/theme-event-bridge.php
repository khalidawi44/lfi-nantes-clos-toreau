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
    // Conversion en formats MySQL DATETIME et UTC, attendus par The Events Calendar et autres
    $ts_debut         = strtotime($date_debut);
    $date_debut_mysql = $ts_debut ? date('Y-m-d H:i:s', $ts_debut) : '';
    $ts_fin           = $date_fin ? strtotime($date_fin) : 0;
    $date_fin_mysql   = $ts_fin ? date('Y-m-d H:i:s', $ts_fin) : '';

    foreach (lfi_nct_theme_event_date_keys() as $k) {
        update_post_meta($new_id, $k, $date_debut_mysql ?: $date_debut);
    }
    if ($date_fin_mysql) {
        foreach (['event_end_date', '_event_end_date', '_EventEndDate'] as $k) {
            update_post_meta($new_id, $k, $date_fin_mysql);
        }
    }
    foreach (['event_time', 'heure', '_event_time', '_EventStartTime'] as $k) {
        update_post_meta($new_id, $k, $ts_debut ? date('H:i', $ts_debut) : '');
    }

    // === Cas particulier The Events Calendar (CPT tribe_events) ===
    if ($cpt === 'tribe_events' && $date_debut_mysql) {
        update_post_meta($new_id, '_EventStartDate', $date_debut_mysql);
        if ($date_fin_mysql) update_post_meta($new_id, '_EventEndDate', $date_fin_mysql);
        update_post_meta($new_id, '_EventAllDay',      'no');
        update_post_meta($new_id, '_EventTimezone',    wp_timezone_string());
        update_post_meta($new_id, '_EventOrigin',      'plugin');
        update_post_meta($new_id, '_EventShowMap',     'no');
        update_post_meta($new_id, '_EventShowMapLink', 'no');

        try {
            $tz = wp_timezone();
            $dt_debut_utc = new DateTime($date_debut_mysql, $tz);
            $dt_debut_utc->setTimezone(new DateTimeZone('UTC'));
            update_post_meta($new_id, '_EventStartDateUTC', $dt_debut_utc->format('Y-m-d H:i:s'));
            if ($date_fin_mysql) {
                $dt_fin_utc = new DateTime($date_fin_mysql, $tz);
                $dt_fin_utc->setTimezone(new DateTimeZone('UTC'));
                update_post_meta($new_id, '_EventEndDateUTC', $dt_fin_utc->format('Y-m-d H:i:s'));
                $duration = $ts_fin - $ts_debut;
                if ($duration > 0) update_post_meta($new_id, '_EventDuration', $duration);
            }
        } catch (Exception $e) { /* ignore */ }

        // Venue : TEC l'attend comme un post lié au type tribe_venue
        if ($lieu) {
            $venue_id = lfi_nct_tec_find_or_create_venue($lieu, $adresse);
            if ($venue_id) update_post_meta($new_id, '_EventVenueID', $venue_id);
        }

        // CRITIQUE : re-déclenche save_post_tribe_events maintenant que TOUTES les méta sont posées,
        // pour que TEC puisse remplir ses tables custom tec_events / tec_occurrences. Sans ça
        // l'événement existe en post mais n'apparaît dans aucune vue front (les vues TEC lisent
        // les tables custom, pas wp_postmeta). Lock anti-réentrance.
        static $tec_resaving = false;
        if (!$tec_resaving) {
            $tec_resaving = true;
            wp_update_post(['ID' => $new_id]);
            if (function_exists('tribe_update_event')) {
                @tribe_update_event($new_id, [
                    'EventStartDate' => $date_debut_mysql,
                    'EventEndDate'   => $date_fin_mysql ?: $date_debut_mysql,
                    'EventAllDay'    => 'no',
                ]);
            }
            $tec_resaving = false;
        }

        // Purge LiteSpeed pour que la home reflète immédiatement le nouvel événement
        do_action('litespeed_purge_all');
    } else {
        // Cas générique : stockage en texte libre dans tous les meta keys de lieu connus
        $loc_full = trim(($lieu ? $lieu : '') . ($adresse ? (($lieu ? ' — ' : '') . $adresse) : ''));
        if ($loc_full !== '') {
            foreach (lfi_nct_theme_event_location_keys() as $k) {
                update_post_meta($new_id, $k, $loc_full);
            }
        }
    }

    // Pointe l'original pour ouvrir la page du plugin au clic depuis le calendrier
    update_post_meta($new_id, '_lfi_evt_origin_id', $post_id);
}

/**
 * Trouve un tribe_venue par titre, ou le crée avec parsing simple de l'adresse.
 */
function lfi_nct_tec_find_or_create_venue($name, $address) {
    if (!post_type_exists('tribe_venue')) return 0;
    $existing = get_posts([
        'post_type'      => 'tribe_venue',
        'title'          => $name,
        'posts_per_page' => 1,
        'post_status'    => 'publish',
    ]);
    if (!empty($existing)) return (int) $existing[0]->ID;

    $venue_id = wp_insert_post([
        'post_type'    => 'tribe_venue',
        'post_status'  => 'publish',
        'post_title'   => $name,
        'post_content' => $address ?: '',
    ], true);
    if (is_wp_error($venue_id) || !$venue_id) return 0;

    if ($address) {
        update_post_meta($venue_id, '_VenueAddress', $address);
        // Parse simple "12 rue X, 44200 Nantes"
        if (preg_match('/(\d{5})\s+([\p{L}\s\-]+)/u', $address, $m)) {
            update_post_meta($venue_id, '_VenueZip',  $m[1]);
            update_post_meta($venue_id, '_VenueCity', trim($m[2]));
        }
        update_post_meta($venue_id, '_VenueCountry', 'France');
    }
    return (int) $venue_id;
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
/* Auto-healing : à chaque init, miroir les lfi_evenement non encore
   mirroirés OU dont le miroir a disparu. Évite la nécessité d'un flag
   d'idempotence : auto-cohérent en permanence. Limité à 50 par requête
   pour ne pas exploser le temps de chargement.                          */
/* ------------------------------------------------------------------ */

add_action('init', 'lfi_nct_theme_mirror_missing_events', 35);
function lfi_nct_theme_mirror_missing_events() {
    if (lfi_nct_detect_theme_event_cpt() === '') return;

    // Force un resync complet à chaque nouvelle version qui change la logique TEC.
    // Bump cette constante pour rebalayer tout (ex: nouvelle méta TEC ajoutée).
    $resync_version = 'v0.20.5_tec_custom_tables';
    $last_resync    = get_option('lfi_nct_mirror_resync_version');
    $force_resync   = ($last_resync !== $resync_version);

    $posts = get_posts([
        'post_type'      => LFI_NCT_EVT_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'no_found_rows'  => true,
    ]);
    foreach ($posts as $p) {
        if (!$force_resync) {
            $mirror_id = (int) get_post_meta($p->ID, LFI_NCT_THEME_MIRROR_META, true);
            if ($mirror_id && get_post($mirror_id)) continue; // déjà mirroiré, on saute
        }
        lfi_nct_mirror_event_to_theme_cpt($p->ID, $p, true);
    }

    if ($force_resync) {
        update_option('lfi_nct_mirror_resync_version', $resync_version, false);
    }
}
