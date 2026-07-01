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

/** Surcharges d'édition par slug : [slug => ['nom','secteur','travaux']]. */
function lfi_nct_groupes_overrides() {
    $o = get_option('lfi_nct_ga_overrides', []);
    return is_array($o) ? $o : [];
}

/** GA archivés (masqués des listes actives) : [slug => 1]. */
function lfi_nct_groupes_archived() {
    $a = get_option('lfi_nct_ga_archived', []);
    return is_array($a) ? $a : [];
}

/** Config géographique d'un GA (pour géocoder/centrer la carte au bon endroit). */
function lfi_nct_ga_geo($slug) {
    $slug = (string) $slug;
    $default = ['ville' => 'Nantes', 'cp' => '44200', 'centre' => [47.1933, -1.5380], 'hint' => 'Clos Toreau, Nantes'];
    if ($slug === '' || $slug === 'clos-toreau') return $default;
    foreach (lfi_nct_groupes(true) as $g) {
        if ($g['slug'] !== $slug) continue;
        $centre = (isset($g['centre']) && is_array($g['centre']) && count($g['centre']) === 2)
            ? [(float) $g['centre'][0], (float) $g['centre'][1]] : $default['centre'];
        return [
            'ville'  => $g['ville'] ?? 'Nantes',
            'cp'     => $g['cp'] ?? '',
            'centre' => $centre,
            'hint'   => $g['geo_hint'] ?? ($g['secteur'] ?? ''),
        ];
    }
    return $default;
}

/** Config géo correspondant au GA d'un utilisateur (auteur d'une enquête). */
function lfi_nct_geo_for_user($uid) {
    $slug = $uid ? (string) get_user_meta((int) $uid, 'lfi_nct_ga', true) : '';
    return lfi_nct_ga_geo($slug);
}

/** Nom affichable d'un GA à partir de son slug ('' / clos-toreau = espace home). */
function lfi_nct_ga_nom($slug) {
    $slug = (string) $slug;
    if ($slug === '' || $slug === 'clos-toreau') return 'LFI Nantes Sud Clos Toreau';
    foreach (lfi_nct_groupes(true) as $g) {
        if ($g['slug'] === $slug) return $g['nom'];
    }
    return 'LFI Nantes Sud Clos Toreau';
}

/**
 * Liste des GA déclarés : ceux du dépôt (content/groupes.php) PLUS ceux créés
 * depuis l'application (option lfi_nct_ga_custom). Les slugs restent uniques :
 * un GA du dépôt n'est jamais écrasé par un GA personnalisé du même slug.
 *
 * Les surcharges d'édition (renommage, secteur, travaux) et l'état « archivé »
 * sont appliqués à la volée. Par défaut, $include_archived = false : les GA
 * archivés sont exclus des listes actives (switcher, cartes, stats…).
 */
function lfi_nct_groupes($include_archived = false) {
    $list      = function_exists('lfi_nct_content_load') ? lfi_nct_content_load('groupes.php') : [];
    $overrides = lfi_nct_groupes_overrides();
    $archived  = lfi_nct_groupes_archived();
    $out  = [];
    $seen = [];

    $apply = function ($g, $custom) use ($overrides, $archived) {
        $slug = $g['slug'];
        if (isset($overrides[$slug]) && is_array($overrides[$slug])) {
            foreach (['nom', 'secteur', 'travaux'] as $k) {
                if (array_key_exists($k, $overrides[$slug])) $g[$k] = $overrides[$slug][$k];
            }
        }
        $g['ap_url']   = !empty($g['uuid']) ? 'https://actionpopulaire.fr/groupes/' . $g['uuid'] . '/' : '';
        $g['custom']   = $custom;
        $g['archived'] = !empty($archived[$slug]);
        return $g;
    };

    foreach ((array) $list as $g) {
        if (!is_array($g) || empty($g['slug']) || empty($g['nom'])) continue;
        $g = $apply($g, false);
        if (!$include_archived && !empty($g['archived'])) { $seen[$g['slug']] = true; continue; }
        $out[] = $g;
        $seen[$g['slug']] = true;
    }
    foreach (lfi_nct_groupes_custom() as $g) {
        if (!is_array($g) || empty($g['slug']) || empty($g['nom'])) continue;
        if (!empty($seen[$g['slug']])) continue;
        $g = $apply($g, true);
        if (!$include_archived && !empty($g['archived'])) { $seen[$g['slug']] = true; continue; }
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
    $is_super = function_exists('lfi_nct_super_admin') && lfi_nct_super_admin();
    /* Le super-admin voit aussi les GA archivés (pour pouvoir les réactiver). */
    $groupes = lfi_nct_groupes($is_super);

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

    /* ---------------------------------------------------------------- *
     *  MODIFIER / ARCHIVER / SUPPRIMER un groupe d'action (super-admin). *
     * ---------------------------------------------------------------- */
    if ($is_super && !empty($_POST['lfi_ga_edit']) && check_admin_referer('lfi_ga_edit')) {
        $slug   = sanitize_title(wp_unslash($_POST['edit_slug'] ?? ''));
        $action = sanitize_key($_POST['edit_action'] ?? 'save');
        /* On ne touche jamais au GA « actuel » (Clos Toreau, ce site). */
        $is_actuel = false;
        foreach ($groupes as $g) { if ($g['slug'] === $slug && !empty($g['actuel'])) $is_actuel = true; }

        if ($slug !== '' && !$is_actuel) {
            if ($action === 'save') {
                $overrides = lfi_nct_groupes_overrides();
                $overrides[$slug] = [
                    'nom'     => sanitize_text_field(wp_unslash($_POST['edit_nom'] ?? '')),
                    'secteur' => sanitize_text_field(wp_unslash($_POST['edit_secteur'] ?? '')),
                    'travaux' => !empty($_POST['edit_travaux']) ? 1 : 0,
                ];
                if ($overrides[$slug]['nom'] === '') unset($overrides[$slug]['nom']); // garde le nom d'origine si vidé
                update_option('lfi_nct_ga_overrides', $overrides, false);
                wp_safe_redirect(lfi_nct_app_url('groupes', ['saved' => 1]));
                exit;
            }
            if ($action === 'archive' || $action === 'unarchive') {
                $arch = lfi_nct_groupes_archived();
                if ($action === 'archive') $arch[$slug] = 1; else unset($arch[$slug]);
                update_option('lfi_nct_ga_archived', $arch, false);
                wp_safe_redirect(lfi_nct_app_url('groupes', ['saved' => 1]));
                exit;
            }
            if ($action === 'delete') {
                /* Suppression réservée aux GA créés dans l'app (jamais ceux du dépôt). */
                $custom = lfi_nct_groupes_custom();
                if (isset($custom[$slug])) {
                    unset($custom[$slug]);
                    update_option('lfi_nct_ga_custom', $custom, false);
                    $admins = function_exists('lfi_nct_ga_admins') ? lfi_nct_ga_admins() : [];
                    $pivots = function_exists('lfi_nct_ga_pivots') ? lfi_nct_ga_pivots() : [];
                    unset($admins[$slug], $pivots[$slug]);
                    update_option('lfi_nct_ga_admins', $admins, false);
                    update_option('lfi_nct_ga_pivots', $pivots, false);
                    $ov = lfi_nct_groupes_overrides(); unset($ov[$slug]); update_option('lfi_nct_ga_overrides', $ov, false);
                    $ar = lfi_nct_groupes_archived();  unset($ar[$slug]); update_option('lfi_nct_ga_archived', $ar, false);
                    /* Les comptes restent (on ne supprime pas d'utilisateurs), mais
                       on les détache du GA pour ne pas garder un rattachement orphelin. */
                    $members = get_users(['meta_key' => 'lfi_nct_ga', 'meta_value' => $slug, 'fields' => ['ID'], 'number' => 1000]);
                    foreach ((array) $members as $m) { delete_user_meta((int) (is_object($m) ? $m->ID : $m), 'lfi_nct_ga'); }
                }
                wp_safe_redirect(lfi_nct_app_url('groupes', ['deleted_ga' => 1]));
                exit;
            }
        }
        wp_safe_redirect(lfi_nct_app_url('groupes'));
        exit;
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
    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Responsables du GA enregistrés.');

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
        echo '<div class="lfi-app-help" style="margin:0 0 8px"><small>Renseigne au moins un·e responsable. <strong>Ce sont eux qui géreront ce GA</strong> (ajouter des membres, nommer d\'autres admins). Leur espace démarre vide et cloisonné.</small></div>';
        echo '<button type="submit" class="btn-primary" style="background:#186a3b;border-color:#155f34">✅ Créer le groupe + ses responsables</button>';
        echo '</form>';
        echo '</details>';
    }

    if (!empty($_GET['deleted_ga'])) lfi_nct_app_flash('🗑 Groupe d\'action supprimé.');

    echo '<ul class="lfi-app-list">';
    foreach ($groupes as $g) {
        $is_archived = !empty($g['archived']);
        $border = !empty($g['actuel']) ? '#c8102e' : ($is_archived ? '#999' : '#186a3b');
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . $border . ($is_archived ? ';opacity:.7' : '') . '">';
        $tag = !empty($g['actuel']) ? ' <span style="font-size:.8em;color:#c8102e">(ce site)</span>' : (!empty($g['custom']) ? ' <span style="font-size:.8em;color:#186a3b">(créé ici)</span>' : '');
        if ($is_archived) $tag .= ' <span style="font-size:.8em;color:#999">(archivé)</span>';
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

        /* --- Modifier / archiver / supprimer (super-admin, hors « ce site ») --- */
        if ($is_super && empty($g['actuel'])) {
            $sl = esc_attr($g['slug']);
            echo '<details class="lfi-app-collapse" style="margin-top:8px">';
            echo '<summary style="cursor:pointer;color:#0066a3;font-size:.9em">✏️ Modifier / archiver</summary>';
            echo '<form method="post" class="lfi-app-form" style="margin-top:6px">';
            wp_nonce_field('lfi_ga_edit');
            echo '<input type="hidden" name="lfi_ga_edit" value="1">';
            echo '<input type="hidden" name="edit_slug" value="' . $sl . '">';
            echo '<label>Nom<input type="text" name="edit_nom" value="' . esc_attr($g['nom']) . '"></label>';
            echo '<label>Secteur<input type="text" name="edit_secteur" value="' . esc_attr($g['secteur'] ?? '') . '"></label>';
            echo '<label class="lfi-app-checkbox-row" style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="edit_travaux" value="1" ' . checked(!empty($g['travaux']), true, false) . '> 🔧 Volet travaux</label>';
            echo '<button type="submit" name="edit_action" value="save" class="btn-primary">💾 Enregistrer</button>';
            if ($is_archived) {
                echo '<button type="submit" name="edit_action" value="unarchive" class="btn-ghost" style="margin-top:6px">♻️ Réactiver</button>';
            } else {
                echo '<button type="submit" name="edit_action" value="archive" class="btn-ghost" style="margin-top:6px" onclick="return confirm(\'Archiver ce GA ? Il disparaîtra des listes actives (réversible).\');">📦 Archiver</button>';
            }
            if (!empty($g['custom'])) {
                echo '<button type="submit" name="edit_action" value="delete" class="btn-del" style="margin-top:6px" onclick="return confirm(\'Supprimer définitivement ce GA créé dans l\\\'app ? Les comptes restent mais sont détachés. Action irréversible.\');">🗑 Supprimer</button>';
            }
            echo '</form>';
            echo '</details>';
        }

        echo '</li>';
    }
    echo '</ul>';

    /* --- Désigner les admins de chaque GA, PARMI SES PROPRES MEMBRES --- */
    if ($is_super) {
        /* Options = uniquement les membres de CE GA (jamais ceux d'un autre GA
           ni des locataires). Si le GA n'a pas encore de membre, on invite à les
           créer d'abord (via « ➕ Créer un groupe d'action » ou ses Comptes). */
        $opts_for = function ($slug, $cur) {
            $members = get_users([
                'meta_key' => 'lfi_nct_ga', 'meta_value' => $slug,
                'orderby' => 'display_name', 'number' => 500,
            ]);
            $h = '<option value="0">— aucun —</option>';
            foreach ($members as $u) {
                if (defined('LFI_NCT_ROLE_TENANT') && in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) continue;
                $h .= '<option value="' . (int) $u->ID . '" ' . selected($cur, $u->ID, false) . '>' . esc_html($u->display_name . ' (' . $u->user_login . ')') . '</option>';
            }
            return [$h, count($members)];
        };
        echo '<h3 style="margin:22px 0 6px">👥 Admins de chaque GA</h3>';
        echo '<div class="lfi-app-help"><small>Tu désignes ici les <strong>responsables (admins)</strong> d\'un GA <strong>parmi les membres de ce GA</strong>. Ensuite, <strong>ce sont eux qui gèrent leur groupe</strong> : ils ajoutent des membres et peuvent nommer d\'autres admins depuis leurs <em>Comptes</em>. Pour créer un GA et ses premiers responsables d\'un coup, utilise « ➕ Créer un groupe d\'action » plus haut.</small></div>';
        echo '<form method="post" class="lfi-app-form">';
        wp_nonce_field('lfi_ga_admins_save');
        echo '<input type="hidden" name="lfi_ga_admins_save" value="1">';
        foreach ($groupes as $g) {
            if (!empty($g['actuel'])) continue;
            $pair = function_exists('lfi_nct_ga_admin_pair') ? lfi_nct_ga_admin_pair($g['slug']) : ['f' => 0, 'h' => 0];
            list($opt_f, $nb_members) = $opts_for($g['slug'], $pair['f']);
            list($opt_h)              = $opts_for($g['slug'], $pair['h']);
            echo '<div style="border:1px solid #eee;border-radius:8px;padding:10px;margin:8px 0">';
            echo '<div style="font-weight:700;margin-bottom:4px">' . esc_html($g['nom']) . '</div>';
            if ($nb_members === 0) {
                echo '<div class="lfi-app-help" style="margin:4px 0"><small>Aucun membre dans ce GA pour l\'instant. Crée d\'abord ses comptes (« ➕ Créer un groupe d\'action » ou via ses Comptes), puis désigne les admins ici.</small></div>';
            }
            echo '<label>1er·e responsable<select name="adminf[' . esc_attr($g['slug']) . ']">' . $opt_f . '</select></label>';
            echo '<label>2e responsable (facultatif)<select name="adminh[' . esc_attr($g['slug']) . ']">' . $opt_h . '</select></label>';
            echo '</div>';
        }
        echo '<button type="submit" class="btn-primary">💾 Enregistrer les responsables</button>';
        echo '</form>';
    }

    echo '<div class="lfi-app-help"><small>✅ Chaque GA ne voit que <strong>ses</strong> données ; toi seul vois tout et tu bascules avec le sélecteur « 👁 Espace affiché » de l\'accueil. ✅ Personne n\'a accès à WordPress : les comptes des GA sont <strong>redirigés vers l\'app</strong>.</small></div>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  TABLEAU CUMULÉ — chiffres par GA (toi seul, super-admin).      *
 *  Le foyer « avec problème » = réponse dont problemes_presence   *
 *  vaut « oui » (recherche sur le JSON de la réponse).            *
 * ============================================================== */
function lfi_nct_ga_overview_rows() {
    global $wpdb;
    $resp = $wpdb->prefix . 'lfi_nct_responses';
    $mem  = $wpdb->prefix . 'lfi_nct_membres';
    $groupes = lfi_nct_groupes();

    /* Les enquêtes sont désormais cloisonnées par la colonne `ga` de la table
       des réponses (et non plus par l'identifiant du·de la militant·e). On
       compte donc chaque GA par son tag `ga`. */
    $others = [];
    foreach ($groupes as $g) {
        if (!empty($g['actuel'])) continue;
        $others[$g['slug']] = ['g' => $g];
    }

    $like_prob = "data LIKE '%\"problemes_presence\":\"oui\"%'";

    $count_slug = function ($slug) use ($wpdb, $resp, $like_prob) {
        return [
            'enq'  => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $resp WHERE deleted_at IS NULL AND ga = %s", $slug)),
            'prob' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $resp WHERE deleted_at IS NULL AND ga = %s AND $like_prob", $slug)),
        ];
    };

    $rows = [];

    /* Ligne « Mon espace » (Clos Toreau) = enquêtes du Clos Toreau (ga vide/historique). */
    $home_where = " WHERE deleted_at IS NULL AND (ga = '' OR ga = 'clos-toreau' OR ga IS NULL)";
    $home_enq  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $resp" . $home_where);
    $home_prob = (int) $wpdb->get_var("SELECT COUNT(*) FROM $resp" . $home_where . ' AND ' . $like_prob);
    $home_adh  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $mem WHERE ga = '' OR ga = 'clos-toreau'");
    $rows[] = [
        'nom' => 'Mon espace — Clos Toreau', 'home' => true, 'slug' => 'clos-toreau',
        'enq' => $home_enq, 'prob' => $home_prob, 'adh' => $home_adh,
        'binome' => '', 'pivot' => true, 'custom' => false,
    ];

    foreach ($others as $slug => $o) {
        $c   = $count_slug($slug);
        $adh = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $mem WHERE ga = %s", $slug));
        $names = [];
        if (function_exists('lfi_nct_ga_admin_pair')) {
            $pair = lfi_nct_ga_admin_pair($slug);
            if ($pair['f'] && ($uf = get_userdata($pair['f']))) $names[] = '👩 ' . $uf->display_name;
            if ($pair['h'] && ($uh = get_userdata($pair['h']))) $names[] = '👨 ' . $uh->display_name;
        }
        $rows[] = [
            'nom' => $o['g']['nom'], 'home' => false, 'slug' => $slug,
            'enq' => $c['enq'], 'prob' => $c['prob'], 'adh' => $adh,
            'binome' => implode(' · ', $names),
            'pivot' => function_exists('lfi_nct_ga_pivot_uid') ? (bool) lfi_nct_ga_pivot_uid($slug) : false,
            'custom' => !empty($o['g']['custom']),
        ];
    }
    return $rows;
}

/* ============================================================== *
 *  VUE : ESPACE « AUTRES GROUPES D'ACTION » (super-admin)         *
 *  Tout le pilotage multi-GA regroupé en un seul endroit.         *
 * ============================================================== */
function lfi_nct_app_view_reseau_ga() {
    if (!function_exists('lfi_nct_super_admin') || !lfi_nct_super_admin()) {
        wp_safe_redirect(lfi_nct_app_url());
        exit;
    }
    $groupes = lfi_nct_groupes();
    $autres  = array_values(array_filter($groupes, function ($g) { return empty($g['actuel']); }));

    lfi_nct_app_screen_open('🌐 Autres groupes d\'action', 'Le réseau des GA, regroupé — ' . count($autres) . ' autre(s) GA');

    echo '<div class="lfi-app-help" style="background:#eef4ff;border-left:4px solid #0066a3">Ton <strong>espace de pilotage de tous les autres groupes d\'action</strong> (distincts du tien, Clos Toreau). Chaque GA est <strong>cloisonné</strong> : tu es le seul à voir l\'ensemble et à additionner les chiffres. Bascule dans l\'espace d\'un GA avec le sélecteur ci-dessous, puis reviens sur « Mon espace ».</div>';

    /* Sélecteur de bascule (voir comme un GA). */
    if (function_exists('lfi_nct_render_ga_switcher')) lfi_nct_render_ga_switcher();

    /* --- Outils regroupés --- */
    $tiles = [
        ['🌐', 'Carte cumulée du réseau', 'Tous les GA sur une carte 3D',   lfi_nct_app_url('reseau-carte')],
        ['🗺️', 'Annuaire & créer un GA',  'Liste, création, binôme',        lfi_nct_app_url('groupes')],
        ['➕', 'Créer un groupe d\'action','Nom + 2 responsables',           lfi_nct_app_url('groupes')],
        ['🪪', 'Comptes du GA affiché',    'Créer · importer · reset',       lfi_nct_app_url('comptes-ga')],
        ['👥', 'Adhérents du GA affiché',  'Liste cloisonnée',               lfi_nct_app_url('membres')],
        ['🗺', 'Carte du GA affiché',      'Signalements (cloisonnés)',      lfi_nct_app_url('carte')],
        ['📊', 'Stats du GA affiché',      'Compteurs (cloisonnés)',         lfi_nct_app_url('stats-enquete')],
    ];
    echo '<h3 style="margin:18px 0 8px;font-size:.9em;color:#666;text-transform:uppercase;letter-spacing:1px">🧰 Outils (sur l\'espace affiché)</h3>';
    echo '<div class="lfi-app-grid">';
    foreach ($tiles as $t) {
        echo '<a class="lfi-app-tile" href="' . esc_url($t[3]) . '">';
        echo '<div class="ico">' . $t[0] . '</div>';
        echo '<div class="tit">' . esc_html($t[1]) . '</div>';
        echo '<div class="sub">' . esc_html($t[2]) . '</div>';
        echo '</a>';
    }
    echo '</div>';

    /* --- Chaque GA : binôme + chiffres + entrer dans l'espace --- */
    echo '<h3 style="margin:24px 0 8px;font-size:.9em;color:#666;text-transform:uppercase;letter-spacing:1px">🏘️ Les groupes d\'action</h3>';
    $rows = lfi_nct_ga_overview_rows();
    /* On retire la ligne « Mon espace » des cartes (elle est dans le tableau cumulé). */
    echo '<ul class="lfi-app-list">';
    foreach ($rows as $r) {
        if (!empty($r['home'])) continue;
        $border = '#186a3b';
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . $border . '">';
        echo '<div class="com"><strong>' . esc_html($r['nom']) . '</strong>' . (!empty($r['custom']) ? ' <span style="font-size:.8em;color:#186a3b">(créé ici)</span>' : '') . '</div>';
        if ($r['binome']) echo '<div class="meta" style="font-size:.88em;color:#444">' . esc_html($r['binome']) . '</div>';
        else echo '<div class="meta" style="font-size:.85em;color:#c8102e">⚠️ Binôme à désigner</div>';
        /* Liens directs vers les téléphones des responsables enregistrés. */
        if (function_exists('lfi_nct_ga_admin_uids')) {
            $tel_chips = '';
            foreach (lfi_nct_ga_admin_uids($r['slug']) as $auid) {
                $u = get_userdata($auid);
                $tel = (string) get_user_meta($auid, 'lfi_nct_tel', true);
                if ($u && $tel) {
                    $tel_chips .= '<a class="meta-chip" href="tel:' . esc_attr(preg_replace('/[^\d+]/', '', $tel)) . '">📞 ' . esc_html($u->display_name) . '</a> ';
                }
            }
            if ($tel_chips) echo '<div class="meta" style="margin-top:4px;display:flex;gap:6px;flex-wrap:wrap">' . $tel_chips . '</div>';
        }
        echo '<div class="meta" style="font-size:.85em;margin-top:4px;display:flex;gap:6px;flex-wrap:wrap">';
        echo '<span class="meta-chip">🏠 ' . (int) $r['enq'] . ' enquête(s)</span>';
        echo '<span class="meta-chip">⚠️ ' . (int) $r['prob'] . ' avec problème</span>';
        echo '<span class="meta-chip">👥 ' . (int) $r['adh'] . ' adhérent(s)</span>';
        echo '<span class="meta-chip">' . ($r['pivot'] ? '🔒 cloisonné' : '⚙️ responsable à désigner') . '</span>';
        echo '</div>';
        echo '<div class="row-actions" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">';
        echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('voir-ga', ['ga' => $r['slug']])) . '">👁 Entrer dans cet espace</a>';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('voir-ga', ['ga' => $r['slug'], 'then' => 'comptes-ga'])) . '">🪪 Comptes &amp; membres</a>';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('groupes')) . '">⚙️ Configurer</a>';
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';

    /* --- Tableau cumulé (toutes les colonnes additionnées) --- */
    echo '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin:24px 0 8px">';
    echo '<h3 style="margin:0;font-size:.9em;color:#666;text-transform:uppercase;letter-spacing:1px">📊 Statistiques cumulées</h3>';
    echo '<a class="btn-ghost" style="padding:6px 12px" href="' . esc_url(lfi_nct_app_url('reseau-ga-pdf')) . '" target="_blank" rel="noopener">📄 Export PDF</a>';
    echo '</div>';
    $t_enq = 0; $t_prob = 0; $t_adh = 0;
    foreach ($rows as $r) { $t_enq += (int) $r['enq']; $t_prob += (int) $r['prob']; $t_adh += (int) $r['adh']; }
    echo '<div style="overflow-x:auto">';
    echo '<table class="lfi-app-table" style="width:100%;border-collapse:collapse;font-size:.9em">';
    echo '<thead><tr style="text-align:left;border-bottom:2px solid #ddd">';
    echo '<th style="padding:6px 8px">Groupe d\'action</th><th style="padding:6px 8px">Enquêtes</th><th style="padding:6px 8px">Avec problème</th><th style="padding:6px 8px">Adhérents</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $bg = !empty($r['home']) ? 'background:#fff5f6' : '';
        echo '<tr style="border-bottom:1px solid #eee;' . $bg . '">';
        echo '<td style="padding:6px 8px"><strong>' . esc_html($r['nom']) . '</strong></td>';
        echo '<td style="padding:6px 8px">' . (int) $r['enq'] . '</td>';
        echo '<td style="padding:6px 8px">' . (int) $r['prob'] . '</td>';
        echo '<td style="padding:6px 8px">' . (int) $r['adh'] . '</td>';
        echo '</tr>';
    }
    echo '<tr style="border-top:2px solid #333;font-weight:800">';
    echo '<td style="padding:6px 8px">TOTAL réseau</td>';
    echo '<td style="padding:6px 8px">' . (int) $t_enq . '</td>';
    echo '<td style="padding:6px 8px">' . (int) $t_prob . '</td>';
    echo '<td style="padding:6px 8px">' . (int) $t_adh . '</td>';
    echo '</tr>';
    echo '</tbody></table></div>';
    echo '<div class="lfi-app-help"><small>Les chiffres « Mon espace » regroupent tout ce qui n\'appartient à aucun autre GA (ton Clos Toreau). Chaque autre GA n\'affiche que ses propres données.</small></div>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE PDF : tableau cumulé du réseau, imprimable (→ PDF iPhone). *
 * ============================================================== */
function lfi_nct_app_view_reseau_ga_pdf() {
    if (!function_exists('lfi_nct_super_admin') || !lfi_nct_super_admin()) {
        wp_safe_redirect(lfi_nct_app_url());
        exit;
    }
    $rows  = lfi_nct_ga_overview_rows();
    $t_enq = 0; $t_prob = 0; $t_adh = 0;
    foreach ($rows as $r) { $t_enq += (int) $r['enq']; $t_prob += (int) $r['prob']; $t_adh += (int) $r['adh']; }

    lfi_nct_app_screen_open('📄 Export — réseau des GA', 'Statistiques cumulées');
    if (function_exists('lfi_nct_rec_doc_styles')) lfi_nct_rec_doc_styles();
    ?>
    <style>
    .lfi-rec-doc table.detail th { border-bottom: 2px solid #c8102e; padding: 6px 8px; text-align: left; font-size: .9em; }
    .lfi-rec-doc table.detail td.num { text-align: right; }
    </style>
    <?php
    echo '<div class="lfi-rec-doc">';
    echo '<div class="expediteur"><strong>Association Union des Quartiers Libres</strong><br>Réseau des Groupes d\'Action — Nantes</div>';
    echo '<div class="lieu-date">À Nantes, le ' . esc_html(wp_date('j F Y')) . '</div>';
    echo '<h1>Statistiques cumulées du réseau des groupes d\'action</h1>';
    echo '<p>État récapitulatif des chiffres par groupe d\'action : nombre d\'enquêtes réalisées, foyers déclarant au moins un problème, et adhérents. Document interne.</p>';

    echo '<table class="detail">';
    echo '<thead><tr><th>Groupe d\'action</th><th>Enquêtes</th><th>Avec problème</th><th>Adhérents</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td>' . esc_html($r['nom']) . (!empty($r['binome']) ? '<br><span style="font-size:.85em;color:#555">' . esc_html($r['binome']) . '</span>' : '') . '</td>';
        echo '<td class="num">' . (int) $r['enq'] . '</td>';
        echo '<td class="num">' . (int) $r['prob'] . '</td>';
        echo '<td class="num">' . (int) $r['adh'] . '</td>';
        echo '</tr>';
    }
    echo '<tr class="total"><td>TOTAL réseau</td><td class="num">' . (int) $t_enq . '</td><td class="num">' . (int) $t_prob . '</td><td class="num">' . (int) $t_adh . '</td></tr>';
    echo '</tbody></table>';

    echo '<p class="pj">Données agrégées par l\'association Union des Quartiers Libres. La ligne « Mon espace — Clos Toreau » regroupe tout ce qui n\'appartient à aucun autre groupe d\'action.</p>';
    echo '</div>'; // .lfi-rec-doc

    lfi_nct_app_screen_close(false);
}
