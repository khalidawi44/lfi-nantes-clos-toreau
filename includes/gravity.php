<?php
/**
 * Score de gravité automatique par réponse.
 *
 * Calcule un score à partir des indicateurs principaux du formulaire
 * (insectes, humidité, chauffage, appoint, infiltrations) et range chaque
 * réponse dans 4 niveaux : léger / préoccupant / grave / critique.
 *
 * Pondérations centralisées ici — modifier les chiffres pour ajuster.
 */
if (!defined('ABSPATH')) exit;

/**
 * Calcule le score brut (entier ≥ 0) à partir des données JSON décodées.
 */
function lfi_nct_gravity_score($data) {
    if (!is_array($data)) return 0;
    $score = 0;

    // Insectes / nuisibles
    if (($data['insectes_presence'] ?? '') === 'oui') $score += 3;

    // Humidité
    switch ($data['humidite_presence'] ?? '') {
        case 'oui_visible':   $score += 3; break;
        case 'oui_ressentie': $score += 2; break;
        case 'oui_suspicion': $score += 1; break;
    }

    // Adéquation chauffage NMH
    switch ($data['thermique_adequation'] ?? '') {
        case 'non_panne': $score += 5; break;
        case 'non_16':    $score += 4; break;
        case 'non_18':    $score += 3; break;
        case 'partiel':   $score += 2; break;
    }

    // Chauffage d'appoint (dépendance)
    switch ($data['thermique_appoint'] ?? '') {
        case 'oui_permanent': $score += 3; break;
        case 'oui_quotidien': $score += 2; break;
        case 'oui_ponctuel':
        case 'oui_passe':     $score += 1; break;
    }

    // Infiltrations d'eau
    if (($data['thermique_infiltration'] ?? '') === 'oui') $score += 2;

    return $score;
}

/**
 * À partir d'un score, renvoie [clé, libellé, couleur].
 * Seuils ajustables ici.
 */
function lfi_nct_gravity_level($score) {
    if ($score >= 10) return ['critique',    '🚨 Critique',    '#7a0000'];
    if ($score >= 6)  return ['grave',       '🔴 Grave',       '#c8102e'];
    if ($score >= 3)  return ['preoccupant', '🟡 Préoccupant', '#bd8600'];
    return                ['leger',       '🟢 Léger',       '#1a7f37'];
}

/**
 * Petit badge HTML (à coller dans une cellule de tableau).
 */
function lfi_nct_gravity_badge_html($row) {
    $data = is_object($row) && isset($row->data) ? json_decode((string) $row->data, true) : (array) $row;
    $score = lfi_nct_gravity_score($data);
    list($key, $label) = lfi_nct_gravity_level($score);
    return '<span class="lfi-gravity-badge lfi-grav-' . esc_attr($key) . '" title="Score : ' . (int) $score . '">'
        . esc_html($label) . ' <small>(' . (int) $score . ')</small></span>';
}

/**
 * Répartition par niveau pour les stats.
 * @param array $rows  Lignes wpdb (avec ->data en JSON).
 * @return array  [leger => n, preoccupant => n, grave => n, critique => n]
 */
function lfi_nct_gravity_distribution($rows) {
    $dist = ['leger' => 0, 'preoccupant' => 0, 'grave' => 0, 'critique' => 0];
    foreach ($rows as $r) {
        $data = json_decode((string) $r->data, true);
        list($key) = lfi_nct_gravity_level(lfi_nct_gravity_score($data));
        if (isset($dist[$key])) $dist[$key]++;
    }
    return $dist;
}

/**
 * Vrai si la réponse atteint au moins un niveau donné (utilisé pour filtrer).
 */
function lfi_nct_gravity_at_least($data, $min_key) {
    $rank = ['leger' => 0, 'preoccupant' => 1, 'grave' => 2, 'critique' => 3];
    list($key) = lfi_nct_gravity_level(lfi_nct_gravity_score($data));
    return ($rank[$key] ?? 0) >= ($rank[$min_key] ?? 0);
}

/**
 * Styles du badge — injectés une seule fois en admin.
 */
add_action('admin_head', 'lfi_nct_gravity_badge_css');
function lfi_nct_gravity_badge_css() {
    $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
    if (!preg_match('/^lfi-nct-(responses|stats)$/', $page)) return;
    echo '<style id="lfi-nct-gravity-css">
    .lfi-gravity-badge { display: inline-block; padding: 2px 10px; border-radius: 12px;
        color: #fff; font-size: .85em; font-weight: 600; white-space: nowrap; }
    .lfi-gravity-badge small { font-weight: 400; opacity: .85; margin-left: 2px; }
    .lfi-grav-leger       { background: #1a7f37; }
    .lfi-grav-preoccupant { background: #bd8600; }
    .lfi-grav-grave       { background: #c8102e; }
    .lfi-grav-critique    { background: #7a0000; }
    </style>';
}
