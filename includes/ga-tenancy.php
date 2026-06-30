<?php
/**
 * MULTI-GA — cloisonnement des données par groupe d'action.
 *
 * Principe : toutes les requêtes « brigade » passent par un IDENTIFIANT
 * PROPRIÉTAIRE unique (lfi_nct_brigade_owner_id). On le rend ici « conscient
 * du GA » : chaque GA a un compte PIVOT ; tous les membres d'un GA partagent
 * les données de ce pivot et ne voient QUE ça. Toi (super-admin) tu vois ton
 * espace et tu peux « voir comme » n'importe quel GA pour les piloter / agréger.
 *
 * SÉCURITÉ : tant qu'aucun pivot/affectation n'est configuré, le comportement
 * est STRICTEMENT identique à aujourd'hui (repli sur l'identifiant courant).
 */
if (!defined('ABSPATH')) exit;

/** Super-admin (toi) : voit tout, peut basculer de GA. */
function lfi_nct_super_admin() {
    return current_user_can('manage_options');
}

/** Pivots des GA : [slug => user_id]. */
function lfi_nct_ga_pivots() {
    $p = get_option('lfi_nct_ga_pivots', []);
    return is_array($p) ? $p : [];
}
function lfi_nct_ga_pivot_uid($slug) {
    $p = lfi_nct_ga_pivots();
    return isset($p[$slug]) ? (int) $p[$slug] : 0;
}

/** GA d'un utilisateur (membre) — user_meta lfi_nct_ga. */
function lfi_nct_user_ga($uid = null) {
    $uid = $uid ?: get_current_user_id();
    return (string) get_user_meta((int) $uid, 'lfi_nct_ga', true);
}

/** GA actuellement « regardé » par le super-admin ('' ou '__all__' = son espace). */
function lfi_nct_view_ga() {
    if (!lfi_nct_super_admin()) return '';
    return (string) get_user_meta(get_current_user_id(), 'lfi_nct_view_ga', true);
}

/**
 * Résout l'identifiant propriétaire en tenant compte du GA.
 * Repli systématique sur $base (= comportement actuel) si rien n'est configuré.
 */
function lfi_nct_ga_owner_resolve($base) {
    $base = (int) $base;
    if (lfi_nct_super_admin()) {
        $vg = lfi_nct_view_ga();
        if ($vg !== '' && $vg !== '__all__') {
            $p = lfi_nct_ga_pivot_uid($vg);
            if ($p) return $p;
        }
        return $base; // « tout » / non configuré → ton propre espace
    }
    $ga = lfi_nct_user_ga($base);
    if ($ga !== '') {
        $p = lfi_nct_ga_pivot_uid($ga);
        if ($p) return $p;
    }
    return $base;
}

/* ============================================================== *
 *  Bascule de GA (super-admin) : ?vue=voir-ga&ga=slug             *
 * ============================================================== */
function lfi_nct_app_view_voir_ga() {
    if (!lfi_nct_super_admin()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $ga = isset($_GET['ga']) ? sanitize_title(wp_unslash($_GET['ga'])) : '';
    update_user_meta(get_current_user_id(), 'lfi_nct_view_ga', $ga);
    wp_safe_redirect(lfi_nct_app_url());
    exit;
}

/** Sélecteur de GA en haut de l'accueil (super-admin uniquement). */
function lfi_nct_render_ga_switcher() {
    if (!lfi_nct_super_admin() || !function_exists('lfi_nct_groupes')) return;
    $groupes = lfi_nct_groupes();
    if (count($groupes) < 2) return; // un seul GA : inutile
    $cur = lfi_nct_view_ga();
    echo '<div class="lfi-app-gaswitch" style="background:#fff;border:1.5px solid #c8102e;border-radius:10px;padding:8px 12px;margin:10px 0;font-size:.92em">';
    echo '<label style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><span style="font-weight:700;color:#c8102e">👁 Espace affiché :</span>';
    echo '<select onchange="location.href=this.value" style="flex:1;min-width:180px">';
    $own = ($cur === '' || $cur === '__all__');
    echo '<option value="' . esc_url(lfi_nct_app_url('voir-ga', ['ga' => '__all__'])) . '"' . ($own ? ' selected' : '') . '>Mon espace (Clos Toreau)</option>';
    foreach ($groupes as $g) {
        if (!empty($g['actuel'])) continue;
        $sel = ($cur === $g['slug']) ? ' selected' : '';
        $piv = lfi_nct_ga_pivot_uid($g['slug']);
        $label = $g['nom'] . ($piv ? '' : ' — (pivot à configurer)');
        echo '<option value="' . esc_url(lfi_nct_app_url('voir-ga', ['ga' => $g['slug']])) . '"' . $sel . '>' . esc_html($label) . '</option>';
    }
    echo '</select></label>';
    if ($cur !== '' && $cur !== '__all__') {
        echo '<div style="margin-top:4px;color:#555;font-size:.85em">Tu regardes l\'espace d\'un autre GA. Repasse sur « Mon espace » pour revenir.</div>';
    }
    echo '</div>';
}
