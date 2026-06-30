<?php
/**
 * RÉSEAU DES GROUPES D'ACTION — annuaire (Phase 1 du multi-espaces).
 */
if (!defined('ABSPATH')) exit;

/** Liste des GA déclarés (content/groupes.php). */
function lfi_nct_groupes() {
    $list = function_exists('lfi_nct_content_load') ? lfi_nct_content_load('groupes.php') : [];
    $out = [];
    foreach ((array) $list as $g) {
        if (!is_array($g) || empty($g['slug']) || empty($g['nom'])) continue;
        $g['ap_url'] = !empty($g['uuid']) ? 'https://actionpopulaire.fr/groupes/' . $g['uuid'] . '/' : '';
        $out[] = $g;
    }
    return $out;
}

/* ============================================================== *
 *  VUE : annuaire des groupes d'action                            *
 * ============================================================== */
function lfi_nct_app_view_groupes() {
    if (!lfi_nct_app_guard_brigade()) return;
    $groupes = lfi_nct_groupes();

    lfi_nct_app_screen_open('🗺️ Groupes d\'action', 'Le réseau — ' . count($groupes) . ' GA');

    echo '<div class="lfi-app-help" style="background:#e8f0ff;border-left:4px solid #0066a3">Voici les groupes d\'action du réseau. À terme, chacun aura <strong>son espace</strong> dans la même application (mêmes outils, choisis par chaque GA), avec une <strong>vue d\'ensemble</strong> qui additionne les chiffres pour les statistiques. Cet annuaire est la première étape.</div>';

    echo '<ul class="lfi-app-list">';
    foreach ($groupes as $g) {
        $border = !empty($g['actuel']) ? '#c8102e' : '#186a3b';
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . $border . '">';
        echo '<div class="com"><strong>' . esc_html($g['nom']) . '</strong>' . (!empty($g['actuel']) ? ' <span style="font-size:.8em;color:#c8102e">(ce site)</span>' : '') . '</div>';
        echo '<div class="meta" style="color:#555;font-size:.9em">' . esc_html($g['secteur']) . '</div>';
        echo '<div class="meta" style="font-size:.85em;margin-top:4px">';
        echo '<span class="meta-chip">' . (!empty($g['travaux']) ? '🔧 travaux activé' : '— sans travaux') . '</span>';
        if (!empty($g['referent'])) echo '<span class="meta-chip">référent : ' . esc_html($g['referent']) . '</span>';
        echo '</div>';
        if (!empty($g['ap_url'])) {
            echo '<a class="btn-ghost" style="margin-top:6px;display:inline-block;padding:4px 10px;font-size:.85em" href="' . esc_url($g['ap_url']) . '" target="_blank" rel="noopener">Voir sur Action Populaire →</a>';
        }
        echo '</li>';
    }
    echo '</ul>';

    echo '<div class="lfi-app-help"><small>Pour avancer (espace propre + données cloisonnées + stats centralisées par GA), il me faut, pour chaque groupe : qui le porte (référent admin) et si le volet « travaux » est activé. Tu peux compléter <code>content/groupes.php</code> ou me le dire.</small></div>';

    lfi_nct_app_screen_close();
}
