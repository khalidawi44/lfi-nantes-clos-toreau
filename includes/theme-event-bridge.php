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
    // ORDRE = PRIORITÉ. Si plusieurs CPT existent, on prend le PREMIER trouvé.
    // → on cherche d'abord les CPT du thème AG Starter (Alliance Groupe) qui
    //   alimentent le calendrier de la home, puis les noms génériques, puis
    //   les plugins tiers (TEC, MEC) en dernier.
    return apply_filters('lfi_nct_theme_event_cpt_candidates', [
        'ag_evenement', 'ag_evenements', 'ag_event', 'ag_events',
        'mobilisation', 'mobilisations',
        'evenement', 'evenements',
        'event', 'events',
        'agenda',
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

    // Conversion DÉFINITIVE en MySQL datetime AVANT wp_insert/update_post.
    // Le _lfi_evt_date_debut est au format HTML datetime-local (Y-m-d\TH:i),
    // WordPress veut Y-m-d H:i:s sinon ça part en NOW() silencieusement.
    $ts_debut         = $date_debut ? strtotime($date_debut) : 0;
    $date_debut_mysql = $ts_debut ? date('Y-m-d H:i:s', $ts_debut) : '';
    $ts_fin           = $date_fin ? strtotime($date_fin) : 0;
    $date_fin_mysql   = $ts_fin ? date('Y-m-d H:i:s', $ts_fin) : '';

    $args = [
        'post_type'    => $cpt,
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_content' => $content,
        'post_excerpt' => $excerpt,
    ];
    // post_date = date de l'événement (utile si le thème trie par post_date asc)
    if ($date_debut_mysql) {
        $args['post_date']     = $date_debut_mysql;
        $args['post_date_gmt'] = get_gmt_from_date($date_debut_mysql);
    }
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

    // ($ts_debut, $date_debut_mysql, etc. déjà calculés plus haut pour le post_date)
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
/* Diagnostic admin : liste tous les CPT publics et leur nombre d'items
   pour qu'on sache quel CPT alimente le calendrier du thème.           */
/* ------------------------------------------------------------------ */

add_action('admin_menu', 'lfi_nct_event_bridge_diag_menu', 50);
function lfi_nct_event_bridge_diag_menu() {
    add_submenu_page(
        'lfi-nct-responses',
        'Diag CPT événements',
        '🔍 Diag CPT',
        'manage_options',
        'lfi-nct-cpt-diag',
        'lfi_nct_event_bridge_diag_page'
    );
}

function lfi_nct_event_bridge_diag_page() {
    if (!current_user_can('manage_options')) return;
    $detected = lfi_nct_detect_theme_event_cpt();

    // Bouton « Sync maintenant »
    $sync_notice = '';
    if (!empty($_POST['lfi_nct_sync_now']) && check_admin_referer('lfi_nct_sync_now')) {
        $events = get_posts([
            'post_type'      => LFI_NCT_EVT_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => 200,
        ]);
        $count_done = 0; $count_fail = 0;
        foreach ($events as $p) {
            try {
                lfi_nct_mirror_event_to_theme_cpt($p->ID, $p, true);
                $count_done++;
            } catch (Exception $e) { $count_fail++; }
        }
        do_action('litespeed_purge_all');
        if (function_exists('wp_cache_flush')) wp_cache_flush();
        $sync_notice = sprintf('%d événement(s) re-mirroirés dans %s. Cache purgé.', $count_done, $detected ?: '(rien)');
    }

    $all = get_post_types(['_builtin' => false], 'objects');
    ?>
    <div class="wrap">
        <h1>🔍 Diagnostic CPT événements</h1>
        <?php if ($sync_notice): ?>
            <div class="notice notice-success is-dismissible"><p><strong>✅ <?php echo esc_html($sync_notice); ?></strong></p></div>
        <?php endif; ?>
        <p>CPT détecté pour le miroir : <strong><?php echo $detected !== '' ? esc_html($detected) : '<em>aucun</em>'; ?></strong></p>
        <p>Candidats parcourus (dans l'ordre) : <code><?php echo esc_html(implode(', ', lfi_nct_theme_event_cpt_candidates())); ?></code></p>

        <form method="post" style="background:#fff3f5;padding:14px 18px;border-radius:4px;border-left:4px solid #c8102e;margin:16px 0">
            <?php wp_nonce_field('lfi_nct_sync_now'); ?>
            <p style="margin:0 0 .6em"><strong>🔄 Forcer la synchro des événements maintenant</strong></p>
            <p class="description" style="margin:0 0 .8em">Pour chaque événement de mon CPT <code>lfi_evenement</code>, je re-crée ou mets à jour son miroir dans <code><?php echo esc_html($detected ?: '?'); ?></code> avec les bonnes méta de date (au format MySQL) et de lieu. Utile après modif de la logique ou si le calendrier de la home n'affiche pas un événement.</p>
            <button type="submit" name="lfi_nct_sync_now" value="1" class="button button-primary">🔄 Sync maintenant</button>
        </form>
        <h2>Tous les CPT non-builtin enregistrés sur ce site</h2>
        <table class="wp-list-table widefat striped">
            <thead><tr><th>Nom CPT (slug)</th><th>Label</th><th>Public</th><th>Nb publiés</th><th>3 plus récents</th></tr></thead>
            <tbody>
            <?php foreach ($all as $cpt): ?>
                <?php
                $count = wp_count_posts($cpt->name);
                $recent = get_posts(['post_type' => $cpt->name, 'numberposts' => 3, 'post_status' => 'any']);
                $rec_str = [];
                foreach ($recent as $r) $rec_str[] = '#' . $r->ID . ' ' . wp_trim_words($r->post_title, 4);
                ?>
                <tr<?php echo $cpt->name === $detected ? ' style="background:#d4edda"' : ''; ?>>
                    <td><strong><?php echo esc_html($cpt->name); ?></strong></td>
                    <td><?php echo esc_html($cpt->label); ?></td>
                    <td><?php echo $cpt->public ? 'Oui' : 'Non'; ?></td>
                    <td><?php echo (int) ($count->publish ?? 0); ?></td>
                    <td><?php echo esc_html(implode(' · ', $rec_str)); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">La ligne verte = CPT actuellement utilisé par le bridge. Si ce n'est pas le bon, dis-moi quel CPT alimente le calendrier sur la home et j'adapte.</p>
    </div>
    <?php
}

/* ------------------------------------------------------------------ */
/* Auto-healing : à chaque init, miroir les lfi_evenement non encore
   mirroirés OU dont le miroir a disparu. Évite la nécessité d'un flag
   d'idempotence : auto-cohérent en permanence. Limité à 50 par requête
   pour ne pas exploser le temps de chargement.                          */
/* ------------------------------------------------------------------ */

// Self-healing UNIQUEMENT côté admin (admin_init), pas sur chaque page front.
// Ça évite les requêtes get_posts en boucle qui peuvent surcharger ou perturber
// les sessions sur les hits anonymes. Le mirror reste live via save_post.
add_action('admin_init', 'lfi_nct_theme_mirror_missing_events');
function lfi_nct_theme_mirror_missing_events() {
    if (!current_user_can('manage_options')) return;
    if (lfi_nct_detect_theme_event_cpt() === '') return;

    $resync_version = 'v0.20.7_tec_custom_tables';
    $last_resync    = get_option('lfi_nct_mirror_resync_version');
    $force_resync   = ($last_resync !== $resync_version);

    // Throttle : pas plus d'une fois par 5 min sauf force_resync
    $last_run = (int) get_option('lfi_nct_mirror_last_run_ts', 0);
    if (!$force_resync && (time() - $last_run) < 300) return;
    update_option('lfi_nct_mirror_last_run_ts', time(), false);

    $posts = get_posts([
        'post_type'      => LFI_NCT_EVT_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'no_found_rows'  => true,
    ]);
    foreach ($posts as $p) {
        if (!$force_resync) {
            $mirror_id = (int) get_post_meta($p->ID, LFI_NCT_THEME_MIRROR_META, true);
            if ($mirror_id && get_post($mirror_id)) continue;
        }
        lfi_nct_mirror_event_to_theme_cpt($p->ID, $p, true);
    }

    if ($force_resync) {
        update_option('lfi_nct_mirror_resync_version', $resync_version, false);
    }
}
