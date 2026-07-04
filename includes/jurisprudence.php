<?php
/**
 * JURISPRUDENCE — recherche Judilibre via le relais Alliance Groupe.
 *
 * Le plugin appelle le relais (ag/v1/judilibre), jamais Judilibre/PISTE en
 * direct : aucune clé, aucun compte côté LFI (le relais gère l'OAuth).
 * On n'affiche QUE ce que l'API renvoie — jamais de décision inventée — et
 * on cite toujours la source officielle (courdecassation.fr).
 *
 * Objectif : par dossier locataire, retrouver de vraies décisions à citer
 * dans le stratège et le calculateur de préjudice.
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_JURIS_ENDPOINT = 'https://alliancegroupe-inc.com/wp-json/ag/v1/judilibre';
const LFI_NCT_JURIS_DECISION = 'https://alliancegroupe-inc.com/wp-json/ag/v1/judilibre-decision';

function lfi_nct_juris_can() {
    return current_user_can('manage_options')
        || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga())
        || (function_exists('lfi_nct_user_role_avocat') && lfi_nct_user_role_avocat());
}

/** Lien public officiel d'une décision. */
function lfi_nct_juris_source_url($id) {
    return 'https://www.courdecassation.fr/decision/' . rawurlencode((string) $id);
}

/**
 * Recherche. Renvoie l'enveloppe judilibre (results, total, page…) ou ['error'=>…].
 * Cache 6 h côté plugin (le relais limite à 120 req/h).
 */
function lfi_nct_juris_search($args) {
    $q = trim((string) ($args['q'] ?? ''));
    if ($q === '') return ['error' => 'q_vide'];
    $body = ['q' => $q];
    foreach (['jur', 'sort', 'order', 'ymin', 'ymax', 'solution', 'theme', 'pub', 'page', 'page_size', 'chamber', 'formation'] as $k) {
        if (isset($args[$k]) && $args[$k] !== '') $body[$k] = $args[$k];
    }
    $ckey = 'lfi_juris_' . md5(wp_json_encode($body));
    $cached = get_transient($ckey);
    if ($cached !== false) return $cached;

    $resp = wp_remote_post(LFI_NCT_JURIS_ENDPOINT, ['timeout' => 25, 'body' => $body]);
    if (is_wp_error($resp)) return ['error' => 'connexion : ' . $resp->get_error_message()];
    $code = (int) wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code === 429) return ['error' => 'rate_limit'];
    if (!is_array($json)) return ['error' => 'reponse_invalide'];
    if (!empty($json['error'])) return ['error' => (string) $json['error']];
    if (empty($json['judilibre'])) return ['error' => 'reponse_invalide'];

    $out = $json['judilibre'];
    set_transient($ckey, $out, 6 * HOUR_IN_SECONDS);
    return $out;
}

/** Construit une requête pertinente à partir des constatations d'un dossier. */
function lfi_nct_juris_query_for_dossier($row) {
    $c = function_exists('mb_strtolower') ? mb_strtolower((string) $row->constatations) : strtolower((string) $row->constatations);
    $map = [
        'punaise' => 'punaises de lit', 'moisiss' => 'moisissures', 'humidit' => 'humidité',
        'chauff' => 'chauffage', 'plomb' => 'plomb saturnisme', 'cafard' => 'cafards',
        'blatte' => 'blattes', 'fuite' => 'infiltration eau', 'infiltrat' => 'infiltration',
        'électri' => 'installation électrique', 'electri' => 'installation électrique', 'amiante' => 'amiante',
    ];
    $terms = [];
    foreach ($map as $k => $v) if (strpos($c, $k) !== false && !in_array($v, $terms, true)) $terms[] = $v;
    $base = $terms ? implode(' ', $terms) : 'logement non décent';
    return $base . ' logement bailleur social';
}

/* ============================================================== *
 *  VUE : Recherche de jurisprudence                              *
 * ============================================================== */
function lfi_nct_app_view_jurisprudence() {
    if (!lfi_nct_juris_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    $did = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $is_avocat = !current_user_can('manage_options') && (!function_exists('lfi_nct_can_admin_ga') || !lfi_nct_can_admin_ga())
        && function_exists('lfi_nct_user_role_avocat') && lfi_nct_user_role_avocat();
    if ($is_avocat && $did) {
        /* L'avocat·e ne voit la jurisprudence QUE des dossiers qui lui sont confiés. */
        global $wpdb;
        $drow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_dossiers_locataires WHERE id = %d", $did));
        $ok = $drow && function_exists('lfi_nct_avocat_of_tenant') && (int) lfi_nct_avocat_of_tenant((int) $drow->tenant_user_id) === get_current_user_id();
        $dossier = $ok ? $drow : null;
        if (!$ok) $did = 0;
    } else {
        $dossier = ($did && function_exists('lfi_nct_dossier_get')) ? lfi_nct_dossier_get($did) : null;
    }

    $q = isset($_GET['q']) ? trim(sanitize_text_field(wp_unslash($_GET['q']))) : '';
    if ($q === '' && $dossier) $q = lfi_nct_juris_query_for_dossier($dossier);

    $jur  = isset($_GET['jur'])  ? sanitize_text_field(wp_unslash($_GET['jur']))  : '';
    $ymin = isset($_GET['ymin']) ? (int) $_GET['ymin'] : 0;
    $ymax = isset($_GET['ymax']) ? (int) $_GET['ymax'] : 0;
    $sol  = isset($_GET['solution']) ? sanitize_text_field(wp_unslash($_GET['solution'])) : '';
    $page = isset($_GET['page_n']) ? max(0, (int) $_GET['page_n']) : 0;

    lfi_nct_app_screen_open('🔎 Jurisprudence', $dossier ? ('Dossier : ' . trim($dossier->tenant_prenom . ' ' . $dossier->tenant_nom)) : 'Vraies décisions (Judilibre) — via le relais');
    echo '<div class="lfi-app-help">Décisions réelles de la Cour de cassation / cours d\'appel. <strong>Rien n\'est inventé</strong> : on n\'affiche que ce que la base renvoie, avec le lien officiel sous chaque décision.</div>';

    echo '<form method="get" class="lfi-app-form">';
    echo '<input type="hidden" name="vue" value="jurisprudence">';
    if ($did) echo '<input type="hidden" name="id" value="' . $did . '">';
    echo '<label>Recherche<input type="search" name="q" value="' . esc_attr($q) . '" placeholder="Ex : punaises de lit logement bailleur social"></label>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
    echo '<label style="flex:1;min-width:130px">Juridiction<select name="jur">';
    foreach (['' => 'Toutes', 'cc' => 'Cassation', 'ca' => 'Cour d\'appel', 'tj' => 'Tribunaux'] as $vv => $ll) {
        echo '<option value="' . esc_attr($vv) . '"' . selected($jur, $vv, false) . '>' . esc_html($ll) . '</option>';
    }
    echo '</select></label>';
    echo '<label style="flex:1;min-width:110px">Année min<input type="number" name="ymin" value="' . ($ymin ?: '') . '" placeholder="2015"></label>';
    echo '<label style="flex:1;min-width:110px">Année max<input type="number" name="ymax" value="' . ($ymax ?: '') . '" placeholder="2024"></label>';
    echo '<label style="flex:1;min-width:130px">Sens<select name="solution">';
    foreach (['' => 'Tous', 'cassation' => 'Cassation', 'rejet' => 'Rejet'] as $vv => $ll) {
        echo '<option value="' . esc_attr($vv) . '"' . selected($sol, $vv, false) . '>' . esc_html($ll) . '</option>';
    }
    echo '</select></label>';
    echo '</div>';
    echo '<button type="submit" class="btn-primary">🔎 Chercher</button>';
    echo '</form>';

    if ($q === '') { lfi_nct_app_screen_close(); return; }

    $res = lfi_nct_juris_search([
        'q' => $q, 'jur' => $jur, 'ymin' => $ymin ?: '', 'ymax' => $ymax ?: '',
        'solution' => $sol, 'page' => $page, 'page_size' => 10, 'pub' => 1,
    ]);

    if (!empty($res['error'])) {
        $msg = $res['error'] === 'rate_limit' ? 'Trop de requêtes cette heure-ci (limite du relais). Réessaie plus tard.'
             : ($res['error'] === 'q_vide' ? 'Entre un mot-clé.' : 'Souci de connexion au relais : ' . esc_html($res['error']));
        echo '<div class="lfi-app-empty">' . $msg . '</div>';
        lfi_nct_app_screen_close();
        return;
    }

    $total = (int) ($res['total'] ?? 0);
    $results = $res['results'] ?? [];
    echo '<div class="lfi-app-help" style="background:#f7f7f7"><strong>' . number_format($total, 0, ',', ' ') . '</strong> décision(s) — page ' . ($page + 1) . '.</div>';

    if (empty($results)) {
        echo '<div class="lfi-app-empty">Aucune décision pour cette recherche.</div>';
        lfi_nct_app_screen_close();
        return;
    }

    echo '<ul class="lfi-app-list">';
    foreach ($results as $d) {
        $id   = (string) ($d['id'] ?? '');
        $jud  = (string) ($d['jurisdiction'] ?? '');
        $cham = (string) ($d['chamber'] ?? '');
        $num  = (string) ($d['number'] ?? '');
        $date = (string) ($d['decision_date'] ?? '');
        $solu = (string) ($d['solution'] ?? '');
        $sum  = (string) ($d['summary'] ?? '');
        if ($sum === '' && !empty($d['highlights']['text'])) {
            $sum = wp_strip_all_tags(implode(' … ', (array) $d['highlights']['text']));
        }
        $sum = wp_strip_all_tags($sum);
        if (mb_strlen($sum) > 420) $sum = mb_substr($sum, 0, 420) . '…';
        $themes = !empty($d['themes']) ? implode(' · ', array_slice((array) $d['themes'], 0, 4)) : '';

        echo '<li class="lfi-app-card" style="border-left:4px solid #0066a3">';
        echo '<div class="head"><div class="who">' . esc_html(trim($jud . ($cham ? ' — ' . $cham : ''))) . '</div>';
        if ($solu) echo '<div class="badge" style="background:#0066a3;color:#fff">' . esc_html($solu) . '</div>';
        echo '</div>';
        echo '<div class="meta">';
        if ($num)  echo '<span class="meta-chip">n° ' . esc_html($num) . '</span>';
        if ($date) echo '<span class="meta-chip">📅 ' . esc_html($date) . '</span>';
        echo '</div>';
        if ($themes) echo '<div class="com" style="color:#666"><small>' . esc_html($themes) . '</small></div>';
        if ($sum) echo '<div class="com" style="margin-top:4px">' . esc_html($sum) . '</div>';
        if ($id) echo '<div class="row-actions" style="margin-top:6px"><a class="btn-primary" href="' . esc_url(lfi_nct_juris_source_url($id)) . '" target="_blank" rel="noopener">⚖️ Décision officielle</a></div>';
        echo '</li>';
    }
    echo '</ul>';

    /* Pagination. */
    $per = 10;
    echo '<div class="row-actions" style="margin-top:8px;display:flex;gap:8px">';
    if ($page > 0) {
        $prev = add_query_arg(array_merge($_GET, ['page_n' => $page - 1]), home_url('/' . LFI_NCT_APP_SLUG . '/'));
        echo '<a class="btn-ghost" href="' . esc_url($prev) . '">← Précédent</a>';
    }
    if (($page + 1) * $per < $total) {
        $next = add_query_arg(array_merge($_GET, ['page_n' => $page + 1]), home_url('/' . LFI_NCT_APP_SLUG . '/'));
        echo '<a class="btn-ghost" href="' . esc_url($next) . '">Suivant →</a>';
    }
    echo '</div>';

    echo '<div class="lfi-app-help" style="margin-top:8px"><small>Source : Judilibre (open data) via le relais Alliance Groupe. Toujours vérifier la décision officielle avant de la citer.</small></div>';
    lfi_nct_app_screen_close();
}
