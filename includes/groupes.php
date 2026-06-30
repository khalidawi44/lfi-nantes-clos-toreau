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
    $is_super = function_exists('lfi_nct_super_admin') && lfi_nct_super_admin();

    /* Enregistrement des comptes PIVOT par GA (super-admin). */
    if ($is_super && !empty($_POST['lfi_ga_pivots_save']) && check_admin_referer('lfi_ga_pivots_save')) {
        $piv = [];
        foreach ($groupes as $g) {
            $uid = (int) ($_POST['pivot'][$g['slug']] ?? 0);
            if ($uid) $piv[$g['slug']] = $uid;
        }
        update_option('lfi_nct_ga_pivots', $piv, false);
        wp_safe_redirect(lfi_nct_app_url('groupes', ['saved' => 1]));
        exit;
    }

    lfi_nct_app_screen_open('🗺️ Groupes d\'action', 'Le réseau — ' . count($groupes) . ' GA');
    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Comptes pivots enregistrés.');

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

    /* --- Affectation des comptes PIVOT (super-admin) --- */
    if ($is_super) {
        $pivots = function_exists('lfi_nct_ga_pivots') ? lfi_nct_ga_pivots() : [];
        $users  = get_users(['orderby' => 'display_name', 'number' => 300]);
        echo '<h3 style="margin:22px 0 6px">🔐 Compte pivot par GA</h3>';
        echo '<div class="lfi-app-help"><small>Le <strong>compte pivot</strong> d\'un GA, c\'est le « coffre » où vivent SES données : tous les membres de ce GA partagent ce coffre et ne voient que lui. Crée d\'abord le compte du GA dans <a href="' . esc_url(lfi_nct_app_url('comptes-ga')) . '">Comptes GA</a>, puis désigne-le ici. Tant qu\'un GA n\'a pas de pivot, rien ne change.</small></div>';
        echo '<form method="post" class="lfi-app-form">';
        wp_nonce_field('lfi_ga_pivots_save');
        echo '<input type="hidden" name="lfi_ga_pivots_save" value="1">';
        foreach ($groupes as $g) {
            if (!empty($g['actuel'])) continue;
            $cur = (int) ($pivots[$g['slug']] ?? 0);
            echo '<label>' . esc_html($g['nom']) . '<select name="pivot[' . esc_attr($g['slug']) . ']">';
            echo '<option value="0">— aucun (pas encore configuré) —</option>';
            foreach ($users as $u) {
                echo '<option value="' . (int) $u->ID . '" ' . selected($cur, $u->ID, false) . '>' . esc_html($u->display_name . ' (' . $u->user_login . ')') . '</option>';
            }
            echo '</select></label>';
        }
        echo '<button type="submit" class="btn-primary">💾 Enregistrer les pivots</button>';
        echo '</form>';
    }

    echo '<div class="lfi-app-help"><small>✅ Le cloisonnement marche par <strong>compte pivot</strong> (chaque GA voit ses données, toi tu vois tout et tu bascules avec le sélecteur « 👁 Espace affiché » de l\'accueil). ✅ Personne n\'a accès à WordPress : les comptes GA sont <strong>redirigés vers l\'app</strong>. Reste à créer les comptes des autres GA et à les affecter ci-dessus.</small></div>';

    lfi_nct_app_screen_close();
}
