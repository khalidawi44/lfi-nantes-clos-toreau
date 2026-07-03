<?php
/**
 * ESPACE PARTENAIRE — élu·e allié·e (ex. William Aucant).
 *
 * Un compte SÉPARÉ, cloisonné, pour un·e élu·e partenaire :
 *   - PAS d'accès WordPress, PAS de résultats d'enquête, PAS de données
 *     locataires ni de dossiers individuels (comme tout le monde) ;
 *   - un « espace partagé privilégié » : un dossier co-géré (il peut ajouter /
 *     retirer des éléments) entre lui et l'admin (Fabrice) ;
 *   - une « ligne directe » : une messagerie 1-à-1 privée avec Fabrice ;
 *   - quelques « billes » soigneusement choisies (argumentaire public), SANS
 *     dévoiler la stratégie interne.
 *
 * Rien de sensible n'est exposé : l'espace ne montre QUE ce qu'on y dépose
 * volontairement. Tout est privé entre le·la partenaire et l'admin.
 */
if (!defined('ABSPATH')) exit;

if (!defined('LFI_NCT_ROLE_PARTNER')) define('LFI_NCT_ROLE_PARTNER', 'lfi_nct_partenaire');

/* -------------------------------------------------------------- *
 *  Rôle                                                           *
 * -------------------------------------------------------------- */
add_action('init', 'lfi_nct_partner_ensure_role', 11);
function lfi_nct_partner_ensure_role() {
    if (!get_role(LFI_NCT_ROLE_PARTNER)) {
        add_role(LFI_NCT_ROLE_PARTNER, 'Élu·e partenaire LFI', ['read' => true]);
    }
}

function lfi_nct_user_role_partner($uid = 0) {
    $u = $uid ? get_user_by('id', $uid) : wp_get_current_user();
    return $u && in_array(LFI_NCT_ROLE_PARTNER, (array) $u->roles, true);
}

/** Email de l'admin (Fabrice) pour les notifications de la ligne directe. */
function lfi_nct_partner_admin_email() {
    return sanitize_email((string) get_option('admin_email'));
}

/* -------------------------------------------------------------- *
 *  Stockage : dossier partagé + fil de discussion (par partenaire) *
 * -------------------------------------------------------------- */
function lfi_nct_partner_dossier($uid) {
    $all = get_option('lfi_nct_partner_dossier', []);
    return (is_array($all) && !empty($all[$uid]) && is_array($all[$uid])) ? $all[$uid] : [];
}
function lfi_nct_partner_dossier_save($uid, $items) {
    $all = get_option('lfi_nct_partner_dossier', []);
    if (!is_array($all)) $all = [];
    $all[$uid] = array_values($items);
    update_option('lfi_nct_partner_dossier', $all, false);
}
function lfi_nct_partner_thread($uid) {
    $all = get_option('lfi_nct_partner_thread', []);
    return (is_array($all) && !empty($all[$uid]) && is_array($all[$uid])) ? $all[$uid] : [];
}
function lfi_nct_partner_thread_save($uid, $msgs) {
    $all = get_option('lfi_nct_partner_thread', []);
    if (!is_array($all)) $all = [];
    $all[$uid] = array_values($msgs);
    update_option('lfi_nct_partner_thread', $all, false);
}
/** Prochain id d'élément (monotone, sans Date/random). */
function lfi_nct_partner_next_id($items) {
    $max = 0;
    foreach ($items as $it) { $id = (int) ($it['id'] ?? 0); if ($id > $max) $max = $id; }
    return $max + 1;
}

/* -------------------------------------------------------------- *
 *  Actions communes (ajout/suppression dossier, message)         *
 *  $ctx_uid = le partenaire concerné ; $is_admin = point de vue.  *
 * -------------------------------------------------------------- */
function lfi_nct_partner_handle_posts($ctx_uid, $back_url) {
    $me = wp_get_current_user();
    $by = $me->display_name ?: $me->user_login;

    /* Ajout d'un élément au dossier partagé. */
    if (!empty($_POST['lfi_partner_item_add']) && check_admin_referer('lfi_partner_item_add')) {
        $titre = sanitize_text_field(wp_unslash($_POST['titre'] ?? ''));
        $note  = sanitize_textarea_field(wp_unslash($_POST['note'] ?? ''));
        $url   = esc_url_raw(wp_unslash($_POST['url'] ?? ''));
        if ($titre !== '') {
            $items = lfi_nct_partner_dossier($ctx_uid);
            $items[] = [
                'id' => lfi_nct_partner_next_id($items),
                'titre' => $titre, 'note' => $note, 'url' => $url,
                'by' => $by, 'date' => current_time('mysql'),
            ];
            lfi_nct_partner_dossier_save($ctx_uid, $items);
        }
        wp_safe_redirect(add_query_arg('ok', 1, $back_url)); exit;
    }
    /* Suppression d'un élément. */
    if (!empty($_POST['lfi_partner_item_del']) && check_admin_referer('lfi_partner_item_del')) {
        $id = (int) $_POST['lfi_partner_item_del'];
        $items = array_values(array_filter(lfi_nct_partner_dossier($ctx_uid), function ($it) use ($id) {
            return (int) ($it['id'] ?? 0) !== $id;
        }));
        lfi_nct_partner_dossier_save($ctx_uid, $items);
        wp_safe_redirect(add_query_arg('del', 1, $back_url)); exit;
    }
    /* Message sur la ligne directe. */
    if (!empty($_POST['lfi_partner_msg']) && check_admin_referer('lfi_partner_msg')) {
        $msg = sanitize_textarea_field(wp_unslash($_POST['msg'] ?? ''));
        if ($msg !== '') {
            $is_admin = current_user_can('manage_options');
            $msgs = lfi_nct_partner_thread($ctx_uid);
            $msgs[] = ['by' => $is_admin ? 'admin' : 'partner', 'name' => $by, 'msg' => $msg, 'date' => current_time('mysql')];
            lfi_nct_partner_thread_save($ctx_uid, $msgs);
            /* Notifie l'autre partie par email. */
            if ($is_admin) {
                $pu = get_user_by('id', $ctx_uid);
                if ($pu && is_email($pu->user_email)) {
                    $link = function_exists('lfi_nct_login_link') ? lfi_nct_login_link($ctx_uid, lfi_nct_app_url('espace')) : lfi_nct_app_url();
                    wp_mail($pu->user_email, 'Message de Fabrice (LFI Nantes Sud)',
                        "Bonjour,\n\nFabrice t'a écrit sur ta ligne directe :\n\n« " . $msg . " »\n\nRéponds directement dans ton espace : " . $link);
                }
            } else {
                $ae = lfi_nct_partner_admin_email();
                if (is_email($ae)) {
                    wp_mail($ae, '💬 Message partenaire : ' . $by,
                        $by . " t'a écrit sur sa ligne directe :\n\n« " . $msg . " »\n\nRéponds depuis : " . lfi_nct_app_url('partenaire-espace', ['uid' => $ctx_uid]));
                }
            }
        }
        wp_safe_redirect(add_query_arg('msg', 1, $back_url)); exit;
    }
}

/* -------------------------------------------------------------- *
 *  Bloc réutilisable : dossier partagé + ligne directe           *
 * -------------------------------------------------------------- */
function lfi_nct_partner_render_shared($ctx_uid, $back_url) {
    /* Dossier partagé. */
    echo '<h3 style="margin:18px 0 6px;color:#4b2e83">📁 Dossier partagé (privé)</h3>';
    echo '<div class="lfi-app-help">Un espace rien qu\'à nous deux. Tu peux <strong>ajouter</strong> et <strong>retirer</strong> ce que tu veux (notes, liens, points à voir ensemble). Fabrice voit la même chose.</div>';

    $items = lfi_nct_partner_dossier($ctx_uid);
    if (!empty($items)) {
        echo '<ul class="lfi-app-list">';
        foreach (array_reverse($items) as $it) {
            echo '<li class="lfi-app-card" style="border-left:4px solid #4b2e83">';
            echo '<div class="head"><div class="who">' . esc_html($it['titre'] ?? '') . '</div></div>';
            if (!empty($it['note'])) echo '<div class="com" style="color:#555">' . nl2br(esc_html($it['note'])) . '</div>';
            if (!empty($it['url'])) echo '<div style="margin-top:4px"><a href="' . esc_url($it['url']) . '" target="_blank" rel="noopener">🔗 ' . esc_html($it['url']) . '</a></div>';
            echo '<div class="lfi-app-help" style="margin-top:4px"><small>Ajouté par ' . esc_html($it['by'] ?? '') . '</small></div>';
            echo '<form method="post" style="margin-top:6px" onsubmit="return confirm(\'Retirer cet élément ?\');">' . wp_nonce_field('lfi_partner_item_del', '_wpnonce', true, false)
               . '<input type="hidden" name="lfi_partner_item_del" value="' . (int) ($it['id'] ?? 0) . '">'
               . '<button type="submit" class="btn-ghost" style="padding:5px 10px;font-size:.8em;color:#c8102e">🗑 Retirer</button></form>';
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<div class="lfi-app-help" style="color:#888">Rien pour l\'instant — ajoute le premier élément ci-dessous.</div>';
    }

    echo '<details class="lfi-app-card" style="border-left:4px solid #186a3b" open><summary style="cursor:pointer;font-weight:800;color:#186a3b">➕ Ajouter au dossier partagé</summary>';
    echo '<form method="post" style="margin-top:8px">' . wp_nonce_field('lfi_partner_item_add', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_partner_item_add" value="1">';
    echo '<div style="margin:6px 0"><label>Titre<br><input type="text" name="titre" required maxlength="160" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px" placeholder="ex : Point à voir sur le logement social"></label></div>';
    echo '<div style="margin:6px 0"><label>Note (optionnel)<br><textarea name="note" rows="3" maxlength="2000" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px"></textarea></label></div>';
    echo '<div style="margin:6px 0"><label>Lien (optionnel)<br><input type="url" name="url" maxlength="300" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px" placeholder="https://…"></label></div>';
    echo '<div style="margin-top:8px"><button type="submit" class="btn-primary" style="background:#4b2e83">Ajouter</button></div>';
    echo '</form></details>';

    /* Ligne directe. */
    echo '<h3 style="margin:22px 0 6px;color:#c8102e">📨 Ligne directe avec Fabrice</h3>';
    echo '<div class="lfi-app-help">Ici, tu écris <strong>directement à Fabrice</strong>, en privé. Il reçoit une alerte par email et te répond au même endroit.</div>';
    $msgs = lfi_nct_partner_thread($ctx_uid);
    if (!empty($msgs)) {
        echo '<div style="display:flex;flex-direction:column;gap:8px;margin:8px 0">';
        foreach ($msgs as $m) {
            $mine = current_user_can('manage_options') ? ($m['by'] === 'admin') : ($m['by'] === 'partner');
            $bg = $mine ? '#e8f5e9' : '#f0f0f5';
            $al = $mine ? 'margin-left:auto' : 'margin-right:auto';
            echo '<div style="max-width:82%;' . $al . ';background:' . $bg . ';border-radius:12px;padding:9px 12px">';
            echo '<div style="font-size:.75em;color:#888;margin-bottom:2px">' . esc_html($m['name'] ?? '') . ' · ' . esc_html(!empty($m['date']) ? wp_date('j M H:i', strtotime($m['date'])) : '') . '</div>';
            echo '<div>' . nl2br(esc_html($m['msg'] ?? '')) . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '<form method="post" style="margin-top:8px">' . wp_nonce_field('lfi_partner_msg', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_partner_msg" value="1">';
    echo '<textarea name="msg" rows="3" required maxlength="3000" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px" placeholder="Écris ton message…"></textarea>';
    echo '<div style="margin-top:6px"><button type="submit" class="btn-primary">Envoyer</button></div>';
    echo '</form>';
}

/* -------------------------------------------------------------- *
 *  VUE PARTENAIRE : son espace (dashboard)                        *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_partenaire_dashboard() {
    if (!lfi_nct_user_role_partner() && !current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $uid = get_current_user_id();
    $back = lfi_nct_app_url('espace');
    lfi_nct_partner_handle_posts($uid, $back);

    $me = wp_get_current_user();
    /* (L'overlay d'accueil « 1re connexion » est déjà rendu en amont, dans le
       routeur de l'app, pour toutes les vues — inutile de le refaire ici.) */

    echo '<div class="lfi-app">';
    echo '<div class="lfi-app-topbar"><div class="lfi-app-logo-mini">Φ</div><div>';
    echo '<div class="lfi-app-hi">Bonjour ' . esc_html($me->display_name ?: $me->user_login) . '</div>';
    echo '<div class="lfi-app-sub2">Espace partenaire · LFI Nantes Sud</div></div></div>';

    if (!empty($_GET['ok']))  lfi_nct_app_flash('✅ Ajouté au dossier partagé.');
    if (!empty($_GET['del'])) lfi_nct_app_flash('🗑 Élément retiré.');
    if (!empty($_GET['msg'])) lfi_nct_app_flash('✅ Message envoyé à Fabrice.');

    echo '<div class="lfi-app-help" style="margin:0 0 14px">👋 Bienvenue dans ton espace privé. Ici, tu as une <strong>ligne directe avec Fabrice</strong> et un <strong>dossier qu\'on partage tous les deux</strong>. C\'est confidentiel, entre nous.</div>';

    /* « Billes » : ce que Fabrice partage volontairement (argumentaire public). */
    echo '<h3 style="margin:8px 0 6px;color:#186a3b">🧰 Des billes pour toi</h3>';
    echo '<div class="lfi-app-grid">';
    lfi_nct_partner_tile('💶', 'Où va le loyer ?', 'L\'argumentaire chiffré sur le bailleur', lfi_nct_app_url('audit-nmh'));
    if (function_exists('lfi_nct_reussites_count_published')) {
        lfi_nct_partner_tile('🏆', 'Nos victoires', lfi_nct_reussites_count_published() . ' familles aidées (anonyme)', lfi_nct_app_url('victoires'));
    }
    lfi_nct_partner_tile('📲', 'Installer l\'app', 'La garder sur ton écran d\'accueil', lfi_nct_app_url('installer'));
    echo '</div>';

    lfi_nct_partner_render_shared($uid, $back);

    echo '<h3 style="margin:22px 0 8px;font-size:.9em;color:#666;text-transform:uppercase;letter-spacing:1px">⚙️ Mon compte</h3>';
    echo '<div class="lfi-app-grid">';
    lfi_nct_partner_tile('✏️', 'Mon profil', 'Email · mot de passe', lfi_nct_app_url('mon-profil'));
    lfi_nct_partner_tile('🚪', 'Se déconnecter', '', wp_logout_url(home_url('/')));
    echo '</div>';
    echo '</div>';
}
function lfi_nct_partner_tile($ico, $tit, $sub, $url) {
    echo '<a class="lfi-app-tile" href="' . esc_url($url) . '"><div class="ico">' . $ico . '</div><div class="tit">' . esc_html($tit) . '</div><div class="sub">' . esc_html($sub) . '</div></a>';
}

/* -------------------------------------------------------------- *
 *  ADMIN : liste des partenaires + création de compte            *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_partenaires() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    /* Création d'un compte partenaire. */
    if (!empty($_POST['lfi_partner_create']) && check_admin_referer('lfi_partner_create')) {
        $prenom = sanitize_text_field(wp_unslash($_POST['prenom'] ?? ''));
        $nom    = sanitize_text_field(wp_unslash($_POST['nom'] ?? ''));
        $email  = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $tg     = sanitize_text_field(wp_unslash($_POST['telegram'] ?? ''));
        $display = trim($prenom . ' ' . $nom);
        if ($display !== '' && is_email($email) && !email_exists($email)) {
            $login = sanitize_user(strtolower($prenom . '.' . $nom), true);
            if ($login === '' || username_exists($login)) $login = 'partenaire_' . wp_generate_password(5, false, false);
            $uid = wp_insert_user([
                'user_login'   => $login,
                'user_email'   => $email,
                'user_pass'    => wp_generate_password(16),
                'display_name' => $display,
                'first_name'   => $prenom,
                'last_name'    => $nom,
                'role'         => LFI_NCT_ROLE_PARTNER,
            ]);
            if (!is_wp_error($uid)) {
                if ($tg !== '') update_user_meta($uid, 'lfi_nct_telegram', $tg);
                wp_safe_redirect(lfi_nct_app_url('partenaire-espace', ['uid' => (int) $uid, 'cree' => 1])); exit;
            }
        }
        wp_safe_redirect(lfi_nct_app_url('partenaires', ['err' => 1])); exit;
    }

    lfi_nct_app_screen_open('🤝 Élu·es partenaires', 'Comptes privilégiés, cloisonnés — ligne directe + dossier partagé');
    if (!empty($_GET['err'])) lfi_nct_app_flash('⚠️ Création impossible (email déjà utilisé ou invalide).', 'error');

    $partners = get_users(['role' => LFI_NCT_ROLE_PARTNER, 'orderby' => 'display_name']);
    if (!empty($partners)) {
        echo '<ul class="lfi-app-list">';
        foreach ($partners as $p) {
            $tg = get_user_meta($p->ID, 'lfi_nct_telegram', true);
            $thread = lfi_nct_partner_thread($p->ID);
            echo '<li class="lfi-app-card" style="border-left:4px solid #4b2e83">';
            echo '<div class="head"><div class="who">' . esc_html($p->display_name) . '</div></div>';
            echo '<div class="meta"><span class="meta-chip">' . esc_html($p->user_email) . '</span>' . ($tg ? '<span class="meta-chip">✈️ ' . esc_html($tg) . '</span>' : '') . '</div>';
            if (!empty($thread)) echo '<div class="com" style="font-size:.9em">💬 ' . count($thread) . ' message(s)</div>';
            echo '<div style="margin-top:8px"><a class="btn-primary" style="background:#4b2e83" href="' . esc_url(lfi_nct_app_url('partenaire-espace', ['uid' => $p->ID])) . '">Ouvrir l\'espace partagé →</a></div>';
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<div class="lfi-app-empty">Aucun·e partenaire pour l\'instant.</div>';
    }

    echo '<details class="lfi-app-card" style="border-left:4px solid #186a3b"><summary style="cursor:pointer;font-weight:800;color:#186a3b">➕ Créer un compte partenaire</summary>';
    echo '<form method="post" style="margin-top:8px">' . wp_nonce_field('lfi_partner_create', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_partner_create" value="1">';
    echo '<div style="margin:6px 0"><label>Prénom<br><input type="text" name="prenom" required style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px"></label></div>';
    echo '<div style="margin:6px 0"><label>Nom<br><input type="text" name="nom" required style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px"></label></div>';
    echo '<div style="margin:6px 0"><label>Email<br><input type="email" name="email" required style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px"></label></div>';
    echo '<div style="margin:6px 0"><label>Telegram (optionnel)<br><input type="text" name="telegram" placeholder="@WilliamAucant" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px"></label></div>';
    echo '<div style="margin-top:8px"><button type="submit" class="btn-primary" style="background:#186a3b">Créer le compte + obtenir le lien</button></div>';
    echo '</form></details>';

    lfi_nct_app_screen_close();
}

/* -------------------------------------------------------------- *
 *  ADMIN : espace partagé d'UN partenaire (co-gestion + réponse) *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_partenaire_espace() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $uid = isset($_GET['uid']) ? (int) $_GET['uid'] : 0;
    $p = $uid ? get_user_by('id', $uid) : null;
    if (!$p || !lfi_nct_user_role_partner($uid)) { wp_safe_redirect(lfi_nct_app_url('partenaires')); exit; }
    $back = lfi_nct_app_url('partenaire-espace', ['uid' => $uid]);
    lfi_nct_partner_handle_posts($uid, $back);

    lfi_nct_app_screen_open('🤝 ' . $p->display_name, 'Espace partagé — dossier co-géré + ligne directe');
    if (!empty($_GET['ok']))  lfi_nct_app_flash('✅ Ajouté au dossier partagé.');
    if (!empty($_GET['del'])) lfi_nct_app_flash('🗑 Élément retiré.');
    if (!empty($_GET['msg'])) lfi_nct_app_flash('✅ Message envoyé.');

    /* Après création : le lien magique de connexion 1-clic + modèle Telegram. */
    if (!empty($_GET['cree'])) {
        $link = function_exists('lfi_nct_login_link') ? lfi_nct_login_link($uid, lfi_nct_app_url('espace')) : lfi_nct_app_url();
        $tg = get_user_meta($uid, 'lfi_nct_telegram', true);
        echo '<div class="lfi-app-card" style="border:2px solid #186a3b;background:#f4fbf4">';
        echo '<div class="com"><strong>✅ Compte créé.</strong> Voici son <strong>lien de connexion directe</strong> (1 clic, sans identifiant — usage unique, 14 jours). Colle-le dans ton message Telegram' . ($tg ? ' à <strong>' . esc_html($tg) . '</strong>' : '') . ' :</div>';
        echo '<textarea readonly style="width:100%;height:44px;margin-top:6px;font-size:.8em;padding:6px;border:1px solid #ccc;border-radius:8px">' . esc_textarea($link) . '</textarea>';
        echo '<div class="lfi-app-help" style="margin-top:6px"><small>À la première connexion, il choisira son propre mot de passe et pourra installer l\'app sur son écran d\'accueil.</small></div>';
        echo '</div>';
    }

    lfi_nct_partner_render_shared($uid, $back);
    lfi_nct_app_screen_close();
}

/* -------------------------------------------------------------- *
 *  Routage : dispatch des vues du·de la partenaire                *
 *  Appelé depuis lfi_nct_app_role_dispatch(). Renvoie true si géré. *
 * -------------------------------------------------------------- */
function lfi_nct_partner_dispatch() {
    if (!lfi_nct_user_role_partner()) return false;
    $vue = isset($_GET['vue']) ? sanitize_key($_GET['vue']) : '';
    switch ($vue) {
        case 'audit-nmh':  lfi_nct_app_view_audit_nmh();  break;
        case 'victoires':  lfi_nct_app_view_victoires();  break;
        case 'installer':  lfi_nct_app_view_installer();  break;
        case 'mon-profil': lfi_nct_app_view_mon_profil(); break;
        case 'espace':     /* fallthrough */
        default:           lfi_nct_app_view_partenaire_dashboard();
    }
    return true;
}
