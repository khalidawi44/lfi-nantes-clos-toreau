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

    /* Enregistrement du binôme paritaire d'admins (femme + homme) par GA. */
    if ($is_super && !empty($_POST['lfi_ga_admins_save']) && check_admin_referer('lfi_ga_admins_save')) {
        $admins = function_exists('lfi_nct_ga_admins') ? lfi_nct_ga_admins() : [];
        $pivots = function_exists('lfi_nct_ga_pivots') ? lfi_nct_ga_pivots() : [];
        foreach ($groupes as $g) {
            if (!empty($g['actuel'])) continue;
            $slug = $g['slug'];
            $f = (int) ($_POST['adminf'][$slug] ?? 0);
            $h = (int) ($_POST['adminh'][$slug] ?? 0);
            $admins[$slug] = ['f' => $f, 'h' => $h];
            /* Compte pivot (= coffre des données du GA) : l'animatrice si
               renseignée, sinon l'animateur. */
            $piv = $f ?: $h;
            if ($piv) $pivots[$slug] = $piv; else unset($pivots[$slug]);
            /* Les deux admins sont rattachés au GA → ils partagent ses données. */
            foreach ([$f, $h] as $uid) { if ($uid) update_user_meta($uid, 'lfi_nct_ga', $slug); }
        }
        update_option('lfi_nct_ga_admins', $admins, false);
        update_option('lfi_nct_ga_pivots', $pivots, false);
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
        if (function_exists('lfi_nct_ga_admin_pair')) {
            $pair = lfi_nct_ga_admin_pair($g['slug']);
            $names = [];
            if ($pair['f'] && ($uf = get_userdata($pair['f']))) $names[] = '👩 ' . $uf->display_name;
            if ($pair['h'] && ($uh = get_userdata($pair['h']))) $names[] = '👨 ' . $uh->display_name;
            if ($names) echo '<span class="meta-chip">' . esc_html(implode(' · ', $names)) . '</span>';
        }
        echo '</div>';
        if (!empty($g['ap_url'])) {
            echo '<a class="btn-ghost" style="margin-top:6px;display:inline-block;padding:4px 10px;font-size:.85em" href="' . esc_url($g['ap_url']) . '" target="_blank" rel="noopener">Voir sur Action Populaire →</a>';
        }
        echo '</li>';
    }
    echo '</ul>';

    /* --- Binôme paritaire d'admins par GA (super-admin) --- */
    if ($is_super) {
        $users  = get_users(['orderby' => 'display_name', 'number' => 500]);
        $opts   = function ($cur) use ($users) {
            $h = '<option value="0">— aucun —</option>';
            foreach ($users as $u) {
                $h .= '<option value="' . (int) $u->ID . '" ' . selected($cur, $u->ID, false) . '>' . esc_html($u->display_name . ' (' . $u->user_login . ')') . '</option>';
            }
            return $h;
        };
        echo '<h3 style="margin:22px 0 6px">👥 Admins du GA — binôme paritaire</h3>';
        echo '<div class="lfi-app-help"><small><strong>2 admins par GA</strong> : une <strong>animatrice</strong> et un <strong>animateur</strong>. Tous deux gèrent l\'espace de leur GA et partagent ses données. Crée d\'abord leurs comptes dans <a href="' . esc_url(lfi_nct_app_url('comptes-ga')) . '">Comptes GA</a> (sans accès WordPress), puis désigne-les ici. Le « coffre » de données du GA est rattaché à l\'animatrice (ou à l\'animateur si l\'animatrice n\'est pas renseignée).</small></div>';
        echo '<form method="post" class="lfi-app-form">';
        wp_nonce_field('lfi_ga_admins_save');
        echo '<input type="hidden" name="lfi_ga_admins_save" value="1">';
        foreach ($groupes as $g) {
            if (!empty($g['actuel'])) continue;
            $pair = function_exists('lfi_nct_ga_admin_pair') ? lfi_nct_ga_admin_pair($g['slug']) : ['f' => 0, 'h' => 0];
            echo '<div style="border:1px solid #eee;border-radius:8px;padding:10px;margin:8px 0">';
            echo '<div style="font-weight:700;margin-bottom:4px">' . esc_html($g['nom']) . '</div>';
            echo '<label>👩 Animatrice (femme)<select name="adminf[' . esc_attr($g['slug']) . ']">' . $opts($pair['f']) . '</select></label>';
            echo '<label>👨 Animateur (homme)<select name="adminh[' . esc_attr($g['slug']) . ']">' . $opts($pair['h']) . '</select></label>';
            echo '</div>';
        }
        echo '<button type="submit" class="btn-primary">💾 Enregistrer les binômes</button>';
        echo '</form>';
    }

    echo '<div class="lfi-app-help"><small>✅ Le cloisonnement marche par <strong>compte pivot</strong> (chaque GA voit ses données, toi tu vois tout et tu bascules avec le sélecteur « 👁 Espace affiché » de l\'accueil). ✅ Personne n\'a accès à WordPress : les comptes GA sont <strong>redirigés vers l\'app</strong>. Reste à créer les comptes des autres GA et à les affecter ci-dessus.</small></div>';

    lfi_nct_app_screen_close();
}
