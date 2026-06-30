<?php
/**
 * RÉSEAU DES GROUPES D'ACTION — annuaire (Phase 1 du multi-espaces).
 */
if (!defined('ABSPATH')) exit;

/** GA créés depuis l'application (option, dynamique) : [slug => def]. */
function lfi_nct_groupes_custom() {
    $c = get_option('lfi_nct_ga_custom', []);
    return is_array($c) ? $c : [];
}

/**
 * Liste des GA déclarés : ceux du dépôt (content/groupes.php) PLUS ceux créés
 * depuis l'application (option lfi_nct_ga_custom). Les slugs restent uniques :
 * un GA du dépôt n'est jamais écrasé par un GA personnalisé du même slug.
 */
function lfi_nct_groupes() {
    $list = function_exists('lfi_nct_content_load') ? lfi_nct_content_load('groupes.php') : [];
    $out  = [];
    $seen = [];
    foreach ((array) $list as $g) {
        if (!is_array($g) || empty($g['slug']) || empty($g['nom'])) continue;
        $g['ap_url'] = !empty($g['uuid']) ? 'https://actionpopulaire.fr/groupes/' . $g['uuid'] . '/' : '';
        $out[] = $g;
        $seen[$g['slug']] = true;
    }
    foreach (lfi_nct_groupes_custom() as $g) {
        if (!is_array($g) || empty($g['slug']) || empty($g['nom'])) continue;
        if (!empty($seen[$g['slug']])) continue;
        $g['ap_url'] = !empty($g['uuid']) ? 'https://actionpopulaire.fr/groupes/' . $g['uuid'] . '/' : '';
        $g['custom'] = true;
        $out[] = $g;
        $seen[$g['slug']] = true;
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

    /* ---------------------------------------------------------------- *
     *  CRÉATION d'un groupe d'action depuis l'app (super-admin).        *
     *  On crée le GA + ses 2 responsables (binôme paritaire). L'espace  *
     *  démarre VIDE et totalement cloisonné : aucune donnée d'un autre  *
     *  GA n'y apparaît (pivot + affectation des comptes au nouveau slug).*
     * ---------------------------------------------------------------- */
    $create_err = '';
    if ($is_super && !empty($_POST['lfi_ga_create']) && check_admin_referer('lfi_ga_create')) {
        $nom     = sanitize_text_field(wp_unslash($_POST['ga_nom'] ?? ''));
        $secteur = sanitize_text_field(wp_unslash($_POST['ga_secteur'] ?? ''));
        $travaux = !empty($_POST['ga_travaux']) ? 1 : 0;
        $slug    = $nom !== '' ? sanitize_title($nom) : '';

        if ($nom === '' || $slug === '') {
            $create_err = 'Indique le nom du groupe d\'action.';
        } else {
            foreach ($groupes as $g) {
                if ($g['slug'] === $slug) { $create_err = 'Un groupe d\'action porte déjà ce nom.'; break; }
            }
        }

        if ($create_err === '') {
            /* Crée un compte responsable à partir des champs préfixés. */
            $created_accounts = [];
            $make_resp = function ($prefix, $civ) use ($slug, &$created_accounts) {
                $prenom = sanitize_text_field(wp_unslash($_POST[$prefix . '_prenom'] ?? ''));
                $nomr   = sanitize_text_field(wp_unslash($_POST[$prefix . '_nom'] ?? ''));
                $tel    = sanitize_text_field(wp_unslash($_POST[$prefix . '_tel'] ?? ''));
                $email  = sanitize_email(wp_unslash($_POST[$prefix . '_email'] ?? ''));
                if ($prenom === '' && $nomr === '') return 0;
                $login = lfi_nct_app_make_username($prenom, $nomr);
                $pwd   = lfi_nct_app_make_password();
                $uid   = wp_insert_user([
                    'user_login'   => $login,
                    'user_pass'    => $pwd,
                    'user_email'   => function_exists('lfi_nct_app_clean_email') ? lfi_nct_app_clean_email($email) : $email,
                    'first_name'   => $prenom,
                    'last_name'    => $nomr,
                    'display_name' => trim($prenom . ' ' . $nomr) ?: $login,
                    'role'         => defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : 'subscriber',
                ]);
                if (is_wp_error($uid)) return 0;
                update_user_meta($uid, 'lfi_nct_ga', $slug);
                if ($tel) update_user_meta($uid, 'lfi_nct_tel', $tel);
                $created_accounts[] = [
                    'civ' => $civ, 'login' => $login, 'pwd' => $pwd,
                    'name' => trim($prenom . ' ' . $nomr) ?: $login, 'tel' => $tel,
                ];
                return (int) $uid;
            };
            $fuid = $make_resp('animf', '👩 Animatrice');
            $huid = $make_resp('animh', '👨 Animateur');

            if (!$fuid && !$huid) {
                $create_err = 'Indique au moins un·e responsable (prénom ou nom).';
            } else {
                /* 1) Enregistre le GA dans le store dynamique. */
                $custom = lfi_nct_groupes_custom();
                $custom[$slug] = ['slug' => $slug, 'nom' => $nom, 'secteur' => $secteur, 'travaux' => $travaux];
                update_option('lfi_nct_ga_custom', $custom, false);

                /* 2) Binôme paritaire d'admins. */
                $admins = function_exists('lfi_nct_ga_admins') ? lfi_nct_ga_admins() : [];
                $admins[$slug] = ['f' => $fuid, 'h' => $huid];
                update_option('lfi_nct_ga_admins', $admins, false);

                /* 3) Compte pivot = coffre des données du GA (animatrice sinon animateur). */
                $pivots = function_exists('lfi_nct_ga_pivots') ? lfi_nct_ga_pivots() : [];
                $pivots[$slug] = $fuid ?: $huid;
                update_option('lfi_nct_ga_pivots', $pivots, false);

                /* 4) Identifiants à transmettre — affichés une fois après redirection. */
                set_transient('lfi_nct_ga_created_' . get_current_user_id(),
                    ['nom' => $nom, 'slug' => $slug, 'accounts' => $created_accounts], 600);

                wp_safe_redirect(lfi_nct_app_url('groupes', ['created' => 1]));
                exit;
            }
        }
    }

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

    echo '<div class="lfi-app-help" style="background:#e8f0ff;border-left:4px solid #0066a3">Voici les groupes d\'action du réseau. Chacun a <strong>son espace</strong> dans la même application (mêmes outils, choisis par chaque GA), totalement <strong>cloisonné</strong> : un nouveau GA démarre <strong>vide</strong>, sans aucune donnée des autres. Toi seul vois l\'ensemble et peux additionner les chiffres.</div>';

    /* --- Identifiants du GA qui vient d'être créé (affichés une seule fois) --- */
    if ($is_super && !empty($_GET['created'])) {
        $info = get_transient('lfi_nct_ga_created_' . get_current_user_id());
        if ($info && !empty($info['accounts'])) {
            delete_transient('lfi_nct_ga_created_' . get_current_user_id());
            echo '<div class="lfi-app-card" style="border-left:4px solid #186a3b;background:#f3fbf4">';
            echo '<div class="com"><strong>✅ Groupe d\'action « ' . esc_html($info['nom']) .' » créé.</strong> Son espace est vide et cloisonné.</div>';
            echo '<div class="lfi-app-help" style="margin:8px 0"><strong>⚠️ Note ces identifiants maintenant</strong> — le mot de passe ne sera plus affiché. Transmets-les à chaque responsable (ils se connectent sur la même app, sans accès WordPress).</div>';
            foreach ($info['accounts'] as $a) {
                echo '<div style="border:1px solid #cfe8d4;border-radius:8px;padding:10px;margin:8px 0;background:#fff">';
                echo '<div style="font-weight:700">' . esc_html($a['civ']) . ' — ' . esc_html($a['name']) . '</div>';
                echo '<div style="font-family:monospace;font-size:.95em;margin-top:4px">👤 Identifiant : <strong>' . esc_html($a['login']) . '</strong></div>';
                echo '<div style="font-family:monospace;font-size:.95em">🔑 Mot de passe : <strong>' . esc_html($a['pwd']) . '</strong></div>';
                if (!empty($a['tel'])) echo '<div style="font-size:.9em;color:#555;margin-top:2px">📞 ' . esc_html($a['tel']) . '</div>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            lfi_nct_app_flash('✅ Groupe d\'action créé.');
        }
    }

    /* --- Formulaire de CRÉATION d'un groupe d'action (super-admin) --- */
    if ($is_super) {
        if ($create_err !== '') echo '<div class="lfi-app-error">' . esc_html($create_err) . '</div>';
        echo '<details class="lfi-app-collapse" style="border:1.5px solid #186a3b;border-radius:10px;padding:4px 10px;margin:10px 0"' . ($create_err !== '' ? ' open' : '') . '>';
        echo '<summary style="font-weight:800;color:#186a3b;cursor:pointer;padding:8px 0">➕ Créer un nouveau groupe d\'action</summary>';
        echo '<div class="lfi-app-help" style="margin:6px 0">Tu crées le GA <strong>et ses 2 responsables</strong> (binôme paritaire). Leur espace démarre <strong>totalement vide</strong> : à eux d\'ajouter leurs membres et leurs locataires. Aucune de tes données (ni celle d\'un autre GA) n\'y apparaît.</div>';
        echo '<form method="post" class="lfi-app-form">';
        wp_nonce_field('lfi_ga_create');
        echo '<input type="hidden" name="lfi_ga_create" value="1">';
        echo '<label>🏷️ Nom du groupe d\'action <span style="color:#c8102e">*</span><input type="text" name="ga_nom" required placeholder="Ex : GA Bottière-Pin Sec"></label>';
        echo '<label>📍 Secteur / quartier<input type="text" name="ga_secteur" placeholder="Ex : Nantes Est"></label>';
        echo '<label class="lfi-app-checkbox-row" style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="ga_travaux" value="1"> 🔧 Activer le volet « brigade travaux » pour ce GA</label>';

        echo '<div style="border:1px solid #eee;border-radius:8px;padding:10px;margin:10px 0">';
        echo '<div style="font-weight:700;margin-bottom:6px">👩 Responsable — animatrice</div>';
        echo '<label>Prénom<input type="text" name="animf_prenom" placeholder="Prénom"></label>';
        echo '<label>Nom<input type="text" name="animf_nom" placeholder="Nom"></label>';
        echo '<label>Téléphone<input type="tel" name="animf_tel" placeholder="06 12 34 56 78"></label>';
        echo '<label>Email<input type="email" name="animf_email" placeholder="exemple@email.fr"></label>';
        echo '</div>';

        echo '<div style="border:1px solid #eee;border-radius:8px;padding:10px;margin:10px 0">';
        echo '<div style="font-weight:700;margin-bottom:6px">👨 Responsable — animateur</div>';
        echo '<label>Prénom<input type="text" name="animh_prenom" placeholder="Prénom"></label>';
        echo '<label>Nom<input type="text" name="animh_nom" placeholder="Nom"></label>';
        echo '<label>Téléphone<input type="tel" name="animh_tel" placeholder="06 12 34 56 78"></label>';
        echo '<label>Email<input type="email" name="animh_email" placeholder="exemple@email.fr"></label>';
        echo '</div>';
        echo '<div class="lfi-app-help" style="margin:0 0 8px"><small>Renseigne au moins un·e responsable. Le « coffre » des données du GA est rattaché à l\'animatrice (ou à l\'animateur si l\'animatrice n\'est pas renseignée).</small></div>';
        echo '<button type="submit" class="btn-primary" style="background:#186a3b;border-color:#155f34">✅ Créer le groupe + ses responsables</button>';
        echo '</form>';
        echo '</details>';
    }

    echo '<ul class="lfi-app-list">';
    foreach ($groupes as $g) {
        $border = !empty($g['actuel']) ? '#c8102e' : '#186a3b';
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . $border . '">';
        $tag = !empty($g['actuel']) ? ' <span style="font-size:.8em;color:#c8102e">(ce site)</span>' : (!empty($g['custom']) ? ' <span style="font-size:.8em;color:#186a3b">(créé ici)</span>' : '');
        echo '<div class="com"><strong>' . esc_html($g['nom']) . '</strong>' . $tag . '</div>';
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
        /* Uniquement des comptes ÉLIGIBLES (membres GA + admins) — JAMAIS de
           locataires : un binôme ne se choisit pas parmi les locataires. */
        $roles_admin = ['administrator'];
        if (defined('LFI_NCT_ROLE_GA')) $roles_admin[] = LFI_NCT_ROLE_GA;
        $users  = get_users(['role__in' => $roles_admin, 'orderby' => 'display_name', 'number' => 500]);
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
