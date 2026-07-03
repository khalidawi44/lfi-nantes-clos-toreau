<?php
/**
 * VOLET « PROTECTION DE L'ENFANCE » (Aide Sociale à l'Enfance / Conseil départemental).
 *
 * ⚠️ STRICTEMENT SÉPARÉ du logement : autre adversaire (Conseil départemental /
 * juge des enfants), autres pièces, autre stratégie. On ne mélange JAMAIS.
 * Boussole : l'intérêt de l'enfant. Confidentialité renforcée (données de
 * mineurs) → accès réservé aux admins (GA + super-admin), jamais aux membres.
 *
 * Cette vue expose le CADRE méthodologique (content/methode-ase.php).
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_ase_can() {
    return current_user_can('manage_options')
        || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
}

function lfi_nct_app_view_ase() {
    if (!lfi_nct_ase_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    $f = LFI_NCT_PATH . 'content/methode-ase.php';
    $data = is_file($f) ? include $f : [];
    if (!is_array($data)) $data = [];

    lfi_nct_app_screen_open('👶 Protection de l\'enfance', 'Aide sociale à l\'enfance — volet séparé du logement');

    echo '<div class="lfi-app-card" style="border:2px solid #4b2e83;background:#faf7ff"><div class="com"><strong>🧭 Boussole :</strong> ' . esc_html($data['boussole'] ?? "L'intérêt de l'enfant d'abord. On ne mélange jamais ce volet avec le logement.") . '</div></div>';

    if (!empty($data['statut'])) {
        echo '<div class="lfi-app-help" style="margin-top:8px"><small>📌 Statut : ' . esc_html($data['statut']) . '</small></div>';
    }

    $section = function ($titre, $items, $accent) {
        if (empty($items)) return;
        echo '<h3 style="margin:18px 0 6px;color:' . esc_attr($accent) . '">' . esc_html($titre) . '</h3>';
        echo '<ul class="lfi-app-list">';
        foreach ((array) $items as $it) {
            echo '<li class="lfi-app-card" style="border-left:4px solid ' . esc_attr($accent) . '"><div class="com">' . wp_kses_post($it) . '</div></li>';
        }
        echo '</ul>';
    };

    $section('⚠️ Le problème identifié', $data['probleme'] ?? [], '#c8102e');
    $section('🛠️ La méthode qui a fonctionné', $data['methode'] ?? [], '#186a3b');
    $section('📎 Pièces types à réunir', $data['pieces_types'] ?? [], '#0066a3');
    $section('🛡️ Garde-fous', $data['garde_fous'] ?? [], '#8a6d1f');
    $section('➡️ À faire ensemble (prochaine étape)', $data['a_faire'] ?? [], '#4b2e83');

    echo '<div class="lfi-app-help" style="margin-top:12px;background:#fff3cd;border-left:4px solid #d39e00"><small><strong>Confidentialité renforcée :</strong> données de mineurs — RGPD strict, accès très restreint. La stratégie de plaidoirie relève d\'un <strong>avocat spécialisé</strong> (droit des mineurs / famille) : ce cadre l\'oriente, il décide.</small></div>';

    lfi_nct_app_screen_close();
}
