<?php
/**
 * Content store — « contenu géré par le code »
 *
 * Idée : certains contenus (événements, analyses d'emails NMH…) sont
 * écrits dans des fichiers du dépôt, dans le dossier /content/, par
 * Claude Code à la demande. À chaque push, ils s'affichent sur le site.
 * Le but : tout piloter depuis la conversation, sans formulaire WordPress.
 *
 * Chaque fichier de /content/ renvoie un tableau PHP. On le charge de
 * façon défensive (jamais de page blanche si un fichier manque ou casse).
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_content_dir() {
    return LFI_NCT_PATH . 'content/';
}

/**
 * Charge un fichier de contenu (ex : 'evenements.php') et renvoie le
 * tableau qu'il retourne, ou [] si absent/illisible. Jamais d'erreur fatale.
 */
function lfi_nct_content_load($file) {
    $path = lfi_nct_content_dir() . ltrim($file, '/');
    if (!is_file($path) || !is_readable($path)) return [];
    try {
        $data = include $path;
        return is_array($data) ? $data : [];
    } catch (\Throwable $e) {
        if (function_exists('error_log')) error_log('[LFI content] ' . $file . ' : ' . $e->getMessage());
        return [];
    }
}

/* ============================================================== *
 *  ÉVÉNEMENTS                                                      *
 * ============================================================== */

/** Tous les événements déclarés dans content/evenements.php */
function lfi_nct_content_events() {
    $events = lfi_nct_content_load('evenements.php');
    /* Normalisation + tri par date croissante */
    $events = array_values(array_filter($events, function ($e) {
        return is_array($e) && !empty($e['titre']) && !empty($e['date']);
    }));
    usort($events, function ($a, $b) {
        return strcmp(($a['date'] ?? '') . ($a['heure'] ?? ''), ($b['date'] ?? '') . ($b['heure'] ?? ''));
    });
    return $events;
}

/** Événements à venir (date >= aujourd'hui) */
function lfi_nct_content_events_a_venir() {
    $today = current_time('Y-m-d');
    return array_values(array_filter(lfi_nct_content_events(), function ($e) use ($today) {
        return ($e['date'] ?? '') >= $today;
    }));
}

/**
 * Shortcode [lfi_nct_evenements] — liste publique des événements à venir.
 * À placer une seule fois sur une page WordPress ; ensuite, il suffit
 * d'éditer content/evenements.php (ici, avec Claude) et de pousser.
 *
 * Attribut : [lfi_nct_evenements passes="1"] pour inclure aussi les
 * événements passés.
 */
add_shortcode('lfi_nct_evenements', 'lfi_nct_evenements_shortcode');
function lfi_nct_evenements_shortcode($atts = []) {
    $atts = shortcode_atts(['passes' => '0', 'limite' => '0'], (array) $atts, 'lfi_nct_evenements');
    $events = !empty($atts['passes']) && $atts['passes'] !== '0'
        ? array_reverse(lfi_nct_content_events())
        : lfi_nct_content_events_a_venir();
    if ((int) $atts['limite'] > 0) $events = array_slice($events, 0, (int) $atts['limite']);

    ob_start();
    echo '<div class="lfi-evts">';
    echo '<style>
    .lfi-evts{max-width:760px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
    .lfi-evt{display:flex;gap:14px;background:#fff;border:1px solid #eee;border-left:5px solid #c8102e;border-radius:12px;padding:16px 18px;margin:0 0 14px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
    .lfi-evt-date{flex:0 0 auto;text-align:center;background:#c8102e;color:#fff;border-radius:10px;padding:10px 12px;min-width:64px;height:fit-content}
    .lfi-evt-date .j{font-size:1.6em;font-weight:800;line-height:1}
    .lfi-evt-date .m{font-size:.78em;text-transform:uppercase;letter-spacing:.04em}
    .lfi-evt-body{flex:1}
    .lfi-evt-body h3{margin:0 0 6px;color:#1a1a1a;font-size:1.15em}
    .lfi-evt-meta{color:#666;font-size:.9em;margin:0 0 8px;display:flex;gap:12px;flex-wrap:wrap}
    .lfi-evt-resume{color:#333;line-height:1.5}
    .lfi-evt-cta{display:inline-block;margin-top:10px;background:#c8102e;color:#fff;text-decoration:none;padding:8px 16px;border-radius:8px;font-weight:700}
    .lfi-evts-empty{text-align:center;color:#888;padding:24px;background:#fafafa;border-radius:12px}
    </style>';

    if (empty($events)) {
        echo '<div class="lfi-evts-empty">Aucun événement à venir pour le moment. Reviens bientôt !</div>';
    } else {
        foreach ($events as $e) {
            $ts = strtotime($e['date'] . ' ' . ($e['heure'] ?? ''));
            echo '<div class="lfi-evt">';
            echo '<div class="lfi-evt-date"><div class="j">' . esc_html(wp_date('j', $ts)) . '</div><div class="m">' . esc_html(wp_date('M', $ts)) . '</div></div>';
            echo '<div class="lfi-evt-body">';
            echo '<h3>' . esc_html($e['titre']) . '</h3>';
            echo '<div class="lfi-evt-meta">';
            echo '<span>🗓 ' . esc_html(wp_date('l j F Y', $ts)) . (!empty($e['heure']) ? ' · ' . esc_html($e['heure']) : '') . '</span>';
            if (!empty($e['lieu'])) echo '<span>📍 ' . esc_html($e['lieu']) . '</span>';
            echo '</div>';
            if (!empty($e['resume']))  echo '<div class="lfi-evt-resume">' . esc_html($e['resume']) . '</div>';
            if (!empty($e['details'])) echo '<div class="lfi-evt-resume" style="margin-top:6px;white-space:pre-line">' . esc_html($e['details']) . '</div>';
            if (!empty($e['lien']))    echo '<a class="lfi-evt-cta" href="' . esc_url($e['lien']) . '">S\'inscrire / en savoir plus</a>';
            echo '</div></div>';
        }
    }
    echo '</div>';
    return ob_get_clean();
}

/**
 * Crée automatiquement (une seule fois) la page publique « Événements »
 * avec le shortcode, pour que l'agenda s'affiche sans aucune manipulation
 * dans WordPress. Adresse : /evenements/.
 */
add_action('init', 'lfi_nct_ensure_evenements_page', 20);
function lfi_nct_ensure_evenements_page() {
    if (get_option('lfi_nct_evenements_page_done')) return;
    $existing = get_page_by_path('evenements');
    if (!$existing) {
        wp_insert_post([
            'post_title'   => 'Événements',
            'post_name'    => 'evenements',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[lfi_nct_evenements]',
        ]);
    }
    update_option('lfi_nct_evenements_page_done', 1, false);
}

/* ============================================================== *
 *  ANALYSES D'EMAILS NMH (gérées par le code)                     *
 * ============================================================== */

/** Toutes les analyses NMH déclarées dans content/analyses-nmh.php */
function lfi_nct_content_nmh() {
    return lfi_nct_content_load('analyses-nmh.php');
}

/**
 * Renvoie l'analyse NMH gérée par le code rattachée à un dossier (par
 * dossier_id), ou null. Permet au document « Discussion + analyse » de
 * lire un contenu que Claude a écrit dans le dépôt plutôt que la base.
 */
function lfi_nct_content_nmh_for_dossier($dossier_id) {
    $dossier_id = (int) $dossier_id;
    if (!$dossier_id) return null;
    foreach (lfi_nct_content_nmh() as $slug => $entry) {
        if (!is_array($entry)) continue;
        if ((int) ($entry['dossier_id'] ?? 0) === $dossier_id) {
            $entry['slug'] = is_string($slug) ? $slug : '';
            return $entry;
        }
    }
    return null;
}
