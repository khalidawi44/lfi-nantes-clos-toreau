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
            if (!current_user_can('manage_options')) lfi_nct_partner_activity_mark($ctx_uid, 'dossier');
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
                lfi_nct_partner_activity_mark($ctx_uid, 'message');
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
 *  Alertes pour l'ADMIN : quand un·e élu·e dépose / écrit qqch.    *
 * -------------------------------------------------------------- */
function lfi_nct_partner_activity_mark($uid, $type) {
    $a = get_option('lfi_nct_partner_activity', []);
    if (!is_array($a)) $a = [];
    $cur = (isset($a[$uid]) && is_array($a[$uid])) ? $a[$uid] : ['msg' => 0, 'dossier' => 0, 'last' => ''];
    if ($type === 'message') $cur['msg'] = (int) ($cur['msg'] ?? 0) + 1;
    else                     $cur['dossier'] = (int) ($cur['dossier'] ?? 0) + 1;
    $cur['last'] = current_time('mysql');
    $a[$uid] = $cur;
    update_option('lfi_nct_partner_activity', $a, false);
}
function lfi_nct_partner_activity_clear($uid) {
    $a = get_option('lfi_nct_partner_activity', []);
    if (is_array($a) && isset($a[$uid])) { unset($a[$uid]); update_option('lfi_nct_partner_activity', $a, false); }
}
/** Notice ludique sur le tableau de bord admin, menant à l'espace du partenaire. */
function lfi_nct_partner_admin_notice() {
    if (!current_user_can('manage_options')) return;
    $a = get_option('lfi_nct_partner_activity', []);
    if (!is_array($a) || empty($a)) return;
    foreach ($a as $uid => $act) {
        $u = get_user_by('id', (int) $uid);
        if (!$u) continue;
        $msg = (int) ($act['msg'] ?? 0); $dos = (int) ($act['dossier'] ?? 0);
        if ($msg + $dos < 1) continue;
        $parts = [];
        if ($msg > 0) $parts[] = '💬 ' . $msg . ' message' . ($msg > 1 ? 's' : '');
        if ($dos > 0) $parts[] = '📁 ' . $dos . ' dépôt' . ($dos > 1 ? 's' : '') . ' au dossier';
        $url = lfi_nct_app_url('partenaire-espace', ['uid' => (int) $uid]);
        echo '<a href="' . esc_url($url) . '" style="text-decoration:none;color:inherit;display:block">';
        echo '<div style="margin:0 0 12px;background:linear-gradient(135deg,#4b2e83,#6f4bb0);color:#fff;border-radius:14px;padding:13px 16px;display:flex;align-items:center;gap:12px">';
        echo '<div style="font-size:1.7em">📨</div>';
        echo '<div style="flex:1"><div style="font-weight:900">' . esc_html($u->display_name) . ' t\'a fait signe !</div>';
        echo '<div style="font-size:.86em;opacity:.95">' . esc_html(implode(' · ', $parts)) . ' — clique pour voir et répondre</div></div>';
        echo '<div style="background:rgba(255,255,255,.22);border-radius:20px;padding:6px 12px;font-weight:800;font-size:.85em">Ouvrir →</div>';
        echo '</div></a>';
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

    echo '<div class="lfi-app-help" style="margin:0 0 14px">👋 Bienvenue dans ton espace privé. Tout est <strong>simple et rangé par thème</strong>. Tu as une <strong>ligne directe avec Fabrice</strong>, un <strong>dossier qu\'on partage</strong>, des <strong>assistants qui travaillent pour toi</strong>, et les <strong>contacts directs</strong> des responsables de GA. C\'est confidentiel, entre nous.</div>';

    /* 1) Thématiques prêtes à l'emploi (argumentaire public, rangé simplement). */
    echo '<h3 style="margin:8px 0 6px;color:#c8102e">📚 Tes dossiers thématiques</h3>';
    echo '<div class="lfi-app-help" style="margin-bottom:8px">Les infos utiles, mâchées, prêtes à ressortir en conseil ou face à la presse.</div>';
    echo '<div class="lfi-app-grid">';
    lfi_nct_partner_tile('💶', 'Dossier NMH', 'Comptes · gestion · argumentaires · rhétorique', lfi_nct_app_url('nmh'));
    if (function_exists('lfi_nct_reussites_count_published')) {
        lfi_nct_partner_tile('🏆', 'Nos victoires', lfi_nct_reussites_count_published() . ' familles aidées (anonyme)', lfi_nct_app_url('victoires'));
    }
    echo '</div>';

    /* 2) Assistants (robots). */
    echo '<h3 style="margin:20px 0 6px;color:#2c3e91">🤖 Tes assistants</h3>';
    echo '<div class="lfi-app-card" style="border-left:4px solid #2c3e91"><div class="com">Des <strong>robots travaillent pour toi</strong>. Tu leur poses une question (même à la voix) : « que répondre sur le logement social ? », « prépare-moi 3 arguments sur NMH »… ils te sortent une réponse claire, appuyée sur nos données. Tu peux vraiment t\'en emparer.</div>';
    echo '<div style="margin-top:8px"><a class="btn-primary" style="background:#2c3e91" href="' . esc_url(lfi_nct_app_url('aide')) . '">🤖 Poser une question à un assistant</a></div></div>';

    /* 3) Ligne directe + dossier partagé. */
    lfi_nct_partner_render_shared($uid, $back);

    /* 4) Les autres élu·es (contact direct réservé aux élu·es). */
    echo '<h3 style="margin:22px 0 6px;color:#4b2e83">🏛️ Les autres élu·es</h3>';
    echo '<div class="lfi-app-help">Tu peux les contacter directement (c\'est réservé aux élu·es et à Fabrice).</div>';
    lfi_nct_render_elus_directory(true);

    /* 5) Organigramme des responsables de GA (appel direct). */
    lfi_nct_partner_render_organigramme();

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
 *  Organigramme des responsables de GA (appel direct)            *
 *  William peut appeler directement l'admin d'un GA quand besoin. *
 * -------------------------------------------------------------- */
function lfi_nct_partner_render_organigramme() {
    if (!function_exists('lfi_nct_ga_admins')) return;
    $admins = lfi_nct_ga_admins();
    $lignes = [];
    foreach ((array) $admins as $slug => $pair) {
        $ga_nom = function_exists('lfi_nct_ga_nom') ? lfi_nct_ga_nom($slug) : $slug;
        foreach (['f', 'h'] as $k) {
            $auid = (int) ($pair[$k] ?? 0);
            if (!$auid) continue;
            $u = get_userdata($auid);
            if (!$u) continue;
            $tel = trim((string) get_user_meta($auid, 'lfi_nct_tel', true));
            $lignes[] = ['ga' => $ga_nom, 'nom' => $u->display_name ?: $u->user_login, 'tel' => $tel];
        }
    }
    echo '<h3 style="margin:22px 0 6px;color:#186a3b">📇 Responsables des groupes d\'action</h3>';
    echo '<div class="lfi-app-help">Les personnes qui pilotent chaque GA sur le terrain. Tu peux les <strong>appeler directement</strong> quand tu as besoin.</div>';
    if (empty($lignes)) {
        echo '<div class="lfi-app-help" style="color:#888">L\'annuaire se remplira à mesure que les GA nomment leurs responsables.</div>';
        return;
    }
    echo '<ul class="lfi-app-list">';
    foreach ($lignes as $l) {
        echo '<li class="lfi-app-card" style="border-left:4px solid #186a3b">';
        echo '<div class="head"><div class="who">' . esc_html($l['nom']) . '</div></div>';
        echo '<div class="meta"><span class="meta-chip">🏳️ ' . esc_html($l['ga']) . '</span></div>';
        if ($l['tel'] !== '') {
            $telclean = preg_replace('/[^\d+]/', '', $l['tel']);
            echo '<div style="margin-top:6px"><a class="btn-primary" style="background:#186a3b" href="tel:' . esc_attr($telclean) . '">📞 Appeler ' . esc_html($l['tel']) . '</a></div>';
        } else {
            echo '<div class="lfi-app-help" style="margin-top:4px"><small>Numéro non renseigné.</small></div>';
        }
        echo '</li>';
    }
    echo '</ul>';
}

/* Vue MEMBRE : « Nos élu·es — à qui s'adresser » (lecture seule, sans contact). */
function lfi_nct_app_view_elus_membre() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    lfi_nct_app_screen_open('🏛️ Nos élu·es', 'À qui s\'adresser selon le secteur');
    echo '<div class="lfi-app-help">Tu ne sais pas vers qui te tourner pour telle question ? Voici <strong>nos élu·es</strong> et leur secteur. Tu notes ta question, l\'équipe fait le relais avec la bonne personne.</div>';
    lfi_nct_render_elus_directory(false);
    $elus = get_users(['role' => LFI_NCT_ROLE_PARTNER, 'number' => 1]);
    if (empty($elus)) echo '<div class="lfi-app-empty">L\'organigramme se remplit — reviens bientôt.</div>';
    lfi_nct_app_screen_close();
}

/* -------------------------------------------------------------- *
 *  Casquettes / secteur d'un·e élu·e partenaire                   *
 *  Une même personne peut cumuler PLUSIEURS casquettes (ex.       *
 *  William : municipal + métropolitain) et rester par ailleurs    *
 *  locataire suivi·e. Séparation nette : LOCAL vs NATIONAL.       *
 * -------------------------------------------------------------- */
function lfi_nct_partner_casquettes_def() {
    return [
        /* clé            => [ico, libellé, échelon] */
        'municipal'     => ['🏛️', 'Conseiller·e municipal·e',     'local'],
        'metropolitain' => ['🌐', 'Conseiller·e métropolitain·e', 'local'],
        'departemental' => ['🏳️', 'Conseiller·e départemental·e', 'local'],
        'regional'      => ['🗺️', 'Conseiller·e régional·e',      'local'],
        'national'      => ['🇫🇷', 'Député·e (national)',          'national'],
        'europeen'      => ['🇪🇺', 'Député·e européen·ne',         'national'],
    ];
}
/** Les casquettes (plusieurs) d'un·e élu·e — rétro-compatible avec l'ancien niveau unique. */
function lfi_nct_partner_levels($uid) {
    $keys = array_keys(lfi_nct_partner_casquettes_def());
    $arr  = get_user_meta((int) $uid, 'lfi_nct_partner_levels', true);
    if (is_array($arr) && $arr) {
        $v = array_values(array_intersect($keys, $arr));
        if ($v) return $v;
    }
    $old = (string) get_user_meta((int) $uid, 'lfi_nct_partner_level', true); /* ancien champ unique */
    return in_array($old, $keys, true) ? [$old] : ['municipal'];
}
function lfi_nct_partner_levels_save($uid, $levels) {
    $keys  = array_keys(lfi_nct_partner_casquettes_def());
    $valid = array_values(array_intersect($keys, (array) $levels));
    if (!$valid) $valid = ['municipal'];
    update_user_meta((int) $uid, 'lfi_nct_partner_levels', $valid);
}
/** Compat : la casquette « principale » (première) — pour l'ancien code. */
function lfi_nct_partner_level($uid) {
    $l = lfi_nct_partner_levels($uid);
    return $l[0];
}
/** La personne est-elle AUSSI locataire suivie ? (autre casquette, cloisonnée). */
function lfi_nct_partner_is_also_tenant($uid) {
    $uid = (int) $uid;
    if (!$uid) return false;
    if (defined('LFI_NCT_ROLE_TENANT')) {
        $u = get_userdata($uid);
        if ($u && in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) return true;
    }
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE tenant_user_id = %d", $uid)) > 0;
}
function lfi_nct_partner_scope($uid) {
    return trim((string) get_user_meta((int) $uid, 'lfi_nct_partner_scope', true));
}
/** GA(s) dont la personne est ADMIN — lu directement dans la base (lien auto). */
function lfi_nct_person_ga_admin_of($uid) {
    $uid = (int) $uid; $out = [];
    if (function_exists('lfi_nct_ga_admins')) {
        foreach ((array) lfi_nct_ga_admins() as $slug => $pair) {
            foreach (['f', 'h'] as $k) if ((int) ($pair[$k] ?? 0) === $uid) {
                $out[$slug] = function_exists('lfi_nct_ga_nom') ? lfi_nct_ga_nom($slug) : $slug;
            }
        }
    }
    return $out;
}
/**
 * Casquettes « liées » détectées AUTOMATIQUEMENT pour une personne (même compte,
 * lecture base) : admin de GA, membre de GA, locataire. Renvoie [[ico,label,couleur],…].
 * Ne dépend jamais du nom — uniquement de l'identifiant de compte (cloisonnement).
 */
function lfi_nct_person_linked_chips($uid) {
    $uid = (int) $uid; $chips = [];
    $u = get_userdata($uid); if (!$u) return $chips;
    $roles = (array) $u->roles;
    foreach (lfi_nct_person_ga_admin_of($uid) as $nom) $chips[] = ['⭐', 'Admin du GA ' . $nom, '#bd8600'];
    if (defined('LFI_NCT_ROLE_GA') && in_array(LFI_NCT_ROLE_GA, $roles, true)) {
        $ga = (string) get_user_meta($uid, 'lfi_nct_ga', true);
        $nom = $ga;
        if ($ga && function_exists('lfi_nct_ga_nom')) { $n2 = lfi_nct_ga_nom($ga); if ($n2) $nom = $n2; }
        $chips[] = ['👥', 'Membre du GA' . ($nom ? ' ' . $nom : ''), '#4b2e83'];
    }
    if (user_can($uid, 'manage_options')) $chips[] = ['🛠️', 'Super-admin', '#c8102e'];
    if (function_exists('lfi_nct_partner_is_also_tenant') && lfi_nct_partner_is_also_tenant($uid)) $chips[] = ['🏠', 'Locataire (a un dossier)', '#186a3b'];
    return $chips;
}
/** Cases à cocher des casquettes (formulaires création / promotion / édition). */
function lfi_nct_partner_casquettes_checkboxes($selected = [], $name = 'casquettes') {
    $out = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 10px;margin:4px 0">';
    foreach (lfi_nct_partner_casquettes_def() as $k => $d) {
        $ck = in_array($k, (array) $selected, true) ? ' checked' : '';
        $out .= '<label style="display:flex;gap:6px;align-items:center;font-size:.92em"><input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr($k) . '"' . $ck . '> ' . $d[0] . ' ' . esc_html($d[1]) . '</label>';
    }
    $out .= '</div>';
    return $out;
}

/**
 * Annuaire des élu·es (organigramme « à qui s'adresser »).
 * $with_contact = true  → réservé aux ÉLU·ES et à l'ADMIN : téléphone + email.
 * $with_contact = false → pour les MEMBRES : juste qui fait quoi, AUCUN contact
 *   direct (ce sont les élu·es et Fabrice qui se contactent entre eux).
 */
function lfi_nct_render_elus_directory($with_contact = false) {
    $elus = get_users(['role' => LFI_NCT_ROLE_PARTNER, 'orderby' => 'display_name']);
    if (empty($elus)) return;
    $def = lfi_nct_partner_casquettes_def();
    /* Séparation nette des échelons : LOCAL (ville/métropole/dép./région) vs NATIONAL. */
    $echelons = [
        'local'    => ['🏙️ Échelon local', '#4b2e83'],
        'national' => ['🇫🇷 Échelon national', '#0066a3'],
    ];
    /* Une personne peut apparaître sous PLUSIEURS casquettes (une par casquette
       tenue) — mais on ne la répète pas dans le même échelon. */
    $grp = ['local' => [], 'national' => []];
    foreach ($elus as $u) {
        $seen = [];
        foreach (lfi_nct_partner_levels($u->ID) as $lvl) {
            $ech = $def[$lvl][2] ?? 'local';
            if (isset($seen[$ech])) continue;
            $seen[$ech] = 1;
            $grp[$ech][] = $u;
        }
    }

    foreach ($echelons as $ech => $meta) {
        if (empty($grp[$ech])) continue;
        echo '<h3 style="margin:16px 0 6px;color:' . esc_attr($meta[1]) . '">' . $meta[0] . '</h3>';
        echo '<ul class="lfi-app-list">';
        foreach ($grp[$ech] as $u) {
            $scope = lfi_nct_partner_scope($u->ID);
            /* Casquettes de CET échelon (chips). */
            $chips = '';
            foreach (lfi_nct_partner_levels($u->ID) as $lvl) {
                if (($def[$lvl][2] ?? '') !== $ech) continue;
                $chips .= '<span class="meta-chip">' . $def[$lvl][0] . ' ' . esc_html($def[$lvl][1]) . '</span>';
            }
            echo '<li class="lfi-app-card" style="border-left:4px solid ' . esc_attr($meta[1]) . '">';
            echo '<div class="head"><div class="who">' . esc_html($u->display_name) . '</div></div>';
            echo '<div class="meta">' . $chips;
            if ($scope !== '') echo '<span class="meta-chip">🎯 ' . esc_html($scope) . '</span>';
            if (lfi_nct_partner_is_also_tenant($u->ID)) echo '<span class="meta-chip" style="background:#eef7ee;color:#186a3b">🏠 aussi locataire</span>';
            echo '</div>';
            if ($with_contact) {
                $tel = trim((string) get_user_meta($u->ID, 'lfi_nct_tel', true));
                $tg  = trim((string) get_user_meta($u->ID, 'lfi_nct_telegram', true));
                echo '<div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">';
                /* Email = canal PRINCIPAL des élu·es (avant le téléphone). */
                if (is_email($u->user_email)) echo '<a class="btn-primary" style="background:' . esc_attr($meta[1]) . '" href="mailto:' . esc_attr($u->user_email) . '">✉️ Écrire</a>';
                if ($tel !== '') echo '<a class="btn-ghost" href="tel:' . esc_attr(preg_replace('/[^\d+]/', '', $tel)) . '">📞 Appeler</a>';
                if ($tg !== '') echo '<a class="btn-ghost" href="https://t.me/' . esc_attr(ltrim($tg, '@')) . '" target="_blank" rel="noopener">✈️ Telegram</a>';
                echo '</div>';
                if (is_email($u->user_email)) echo '<div style="font-size:.82em;color:#666;margin-top:3px">📧 ' . esc_html($u->user_email) . '</div>';
            }
            echo '</li>';
        }
        echo '</ul>';
    }
    if (!$with_contact) {
        echo '<div class="lfi-app-help" style="margin-top:6px"><small>Pour une question sur un secteur, note-la et passe par ton binôme d\'animation du GA — ce sont les élu·es et l\'équipe qui font le relais.</small></div>';
    }
}

/* -------------------------------------------------------------- *
 *  VUE : l'étude des comptes de NMH, rangée SIMPLEMENT par thème  *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_partenaire_nmh() {
    if (!lfi_nct_user_role_partner() && !current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    lfi_nct_app_screen_open('💶 Dossier NMH', 'Comptes, gestion, argumentaires & rhétorique — chiffres PUBLICS, sourcés.');
    echo '<div style="text-align:center;margin:4px 0 10px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('espace')) . '">← Mon espace</a></div>';
    echo '<div class="lfi-app-card" style="border:2px solid #186a3b;background:#eef7ee"><div class="com"><strong>🧭 Ce dossier n\'utilise QUE des données publiques</strong> (Chambre régionale des comptes + comptes certifiés). Il ne contient <strong>aucune donnée d\'enquête terrain</strong> — c\'est une règle absolue. Chaque thème est pliable ; tout est sourcé.</div></div>';

    $themes = [
        ['💰', 'L\'essentiel en une phrase',
         'Sur un loyer moyen de <strong>330 €/mois</strong>, seulement <strong>21 € (6 %)</strong> vont vraiment à l\'entretien du logement. Le reste part surtout aux <strong>banques</strong> (intérêts de la dette, 33 %), à l\'<strong>État</strong> (taxe foncière, 16 %) et au <strong>fonctionnement</strong> de NMH (22 %).'],
        ['📊', 'Les chiffres qui parlent',
         '<ul style="margin:0;padding-left:18px;line-height:1.9">'
         . '<li><strong>+23 000 logements</strong> (40 % du parc social de la métropole).</li>'
         . '<li><strong>~939 M€</strong> de dette · <strong>+64 M€</strong> de dette nette par an.</li>'
         . '<li>Résultat net : <strong>−90 % en 5 ans</strong> (9,1 M€ → 0,97 M€).</li>'
         . '<li>Gros entretien : <strong>21 €/mois/logement</strong> seulement.</li></ul>'],
        ['🏢', 'La gestion de NMH — argumentée',
         '<p>Une critique <strong>de la gestion</strong>, appuyée sur les documents officiels (jamais sur les personnes) :</p>'
         . '<ul style="margin:0;padding-left:18px;line-height:1.9">'
         . '<li><strong>Gouvernance :</strong> le président du CA est aussi adjoint à l\'urbanisme de la Ville de Nantes. La CRC pointe un <strong>risque de conflit d\'intérêts</strong> (recommandation n°2).</li>'
         . '<li><strong>Cap financier :</strong> résultat net divisé par 10 en 5 ans, dette qui progresse de +64 M€ nets/an. La <strong>charge de la dette absorbe un tiers du loyer</strong> quand l\'entretien en reçoit 6 %.</li>'
         . '<li><strong>Arbitrage :</strong> le modèle a basculé des aides à la pierre vers l\'emprunt ; le désengagement de l\'État (Réduction de Loyer de Solidarité) ampute l\'autofinancement (impact estimé ~−4,5 M€ sur la CAF 2024). Résultat : la dette grignote ce qui devrait aller au bâti.</li>'
         . '<li><strong>Constat CRC :</strong> la Chambre elle-même <strong>alerte sur l\'insuffisance des investissements dans le parc existant</strong>.</li></ul>'
         . '<p style="margin-top:6px"><strong>La ligne :</strong> on ne dit pas « ils sont incompétents », on dit « voici les chiffres de leurs propres comptes, est-ce le bon équilibre ? ».</p>'],
        ['🏛️', 'Ta question prête pour le conseil',
         '<em>À poser telle quelle, sans citer de loyer individuel ni l\'enquête :</em><blockquote style="border-left:3px solid #ccc;margin:8px 0;padding-left:12px;font-style:italic">« Le rapport de la Chambre régionale des comptes (ROD n°2025-134) relève que le gros entretien du parc représente environ 6 % du loyer, quand la charge de la dette en absorbe un tiers. Pour un quartier <strong>hors NPNRU</strong> comme Clos Toreau, pouvez-vous communiquer au conseil le <strong>Plan Pluriannuel de Travaux</strong> et le calendrier d\'investissement prévu ? »</blockquote><strong>Pourquoi ça marche :</strong> ce sont ses propres institutions qui répondent. Pas d\'accusation → pas de contre-attaque. L\'écart parle seul.'],
        ['🗣️', 'Boîte à rhétorique',
         '<p><strong>Le principe :</strong> montrer l\'<strong>écart entre le discours et les preuves</strong>, en t\'appuyant sur LEURS documents (CRC, comptes certifiés). Tu ne portes pas d\'accusation : tu poses une question que les faits ont déjà tranchée.</p>'
         . '<p><strong>Formules qui marchent :</strong></p>'
         . '<ul style="margin:0;padding-left:18px;line-height:1.9">'
         . '<li>« 6 % du loyer à l\'entretien, un tiers à la dette : est-ce le bon équilibre pour un bailleur social ? »</li>'
         . '<li>« Je ne mets personne en cause — je cite le rapport de la Chambre régionale des comptes. »</li>'
         . '<li>« Un quartier hors NPNRU, c\'est un quartier qui ne touche aucune subvention de rénovation quand d\'autres en reçoivent des centaines de millions. »</li></ul>'
         . '<p style="margin-top:6px"><strong>À éviter absolument :</strong> les adjectifs (« menteuse », « incompétent ») → ça se retourne contre nous. Un loyer individuel. Et <strong>jamais, jamais l\'enquête terrain</strong>.</p>'
         . '<p><strong>Contre-arguments &amp; réponses :</strong></p>'
         . '<ul style="margin:0;padding-left:18px;line-height:1.9">'
         . '<li>« C\'est la faute de l\'État. » → « En partie oui — la RLS ampute leurs moyens, raison de plus pour le porter au national. Mais le conseil d\'administration garde la main sur ses priorités d\'investissement. »</li>'
         . '<li>« Les comptes sont certifiés. » → « Justement : je m\'appuie dessus, pas contre. C\'est ce qui rend le constat incontestable. »</li>'
         . '<li>« Vous faites de la politique. » → « Je pose une question de gestion publique, chiffres à l\'appui. »</li></ul>'],
        ['⚖️', 'Les leviers d\'action',
         '<ul style="margin:0;padding-left:18px;line-height:1.9">'
         . '<li><strong>CADA :</strong> demander le Plan Pluriannuel de Travaux de Clos Toreau.</li>'
         . '<li><strong>Au CA de NMH :</strong> question écrite des représentants locataires (INDECOSA-CGT, CLCV).</li>'
         . '<li><strong>Presse :</strong> « 21 € d\'entretien sur 330 € » + quartier hors NPNRU = inégalité documentable.</li></ul>'],
        ['🛡️', 'Les garde-fous (à respecter)',
         '<strong>Ratios moyens uniquement</strong> — jamais un loyer individuel. · <strong>Jamais l\'enquête terrain</strong> (règle absolue). · <strong>Faits secs</strong>, pas d\'insulte (« menteuse » se retourne contre nous). · <strong>Aucun nom</strong> de locataire. On montre l\'écart entre le discours et les preuves, on n\'attaque pas les personnes.'],
        ['📚', 'Les sources',
         'Rapport CRC Pays de la Loire <strong>ROD n°2025-134</strong> (17 déc. 2025) · Comptes annuels NMH 2024 certifiés (CA du 26/06/2025) · Réponse officielle NMH au ROD (24 oct. 2025) · Convention NM–NMH 2023-2032.'],
    ];
    echo '<ul class="lfi-app-list">';
    foreach ($themes as $i => $t) {
        $open = $i === 0 ? ' open' : '';
        echo '<li class="lfi-app-card" style="border-left:4px solid #c8102e;padding:0">';
        echo '<details' . $open . '><summary style="cursor:pointer;font-weight:800;padding:12px 14px;list-style:none">' . $t[0] . ' ' . esc_html($t[1]) . '</summary>';
        echo '<div class="com" style="padding:0 14px 14px">' . wp_kses_post($t[2]) . '</div></details></li>';
    }
    echo '</ul>';
    echo '<div class="lfi-app-help" style="margin-top:8px">Besoin d\'un angle précis ou d\'un chiffre en plus ? <a href="' . esc_url(lfi_nct_app_url('espace') . '#ligne') . '">Demande-le à Fabrice sur ta ligne directe.</a></div>';
    lfi_nct_app_screen_close();
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
                lfi_nct_partner_levels_save($uid, $_POST['casquettes'] ?? []);
                wp_safe_redirect(lfi_nct_app_url('partenaire-espace', ['uid' => (int) $uid, 'cree' => 1])); exit;
            }
        }
        wp_safe_redirect(lfi_nct_app_url('partenaires', ['err' => 1])); exit;
    }

    /* Promouvoir un COMPTE EXISTANT en élu·e partenaire (ex. Irina, déjà admin
       du GA Dervallières) : on lui AJOUTE le rôle partenaire, sans lui retirer
       son rôle actuel — elle garde sa console de GA + gagne son espace élu·e. */
    if (!empty($_POST['lfi_partner_promote']) && check_admin_referer('lfi_partner_promote')) {
        $puid = (int) ($_POST['user_id'] ?? 0);
        $u = $puid ? get_user_by('id', $puid) : null;
        if ($u) {
            $u->add_role(LFI_NCT_ROLE_PARTNER);
            $tg = sanitize_text_field(wp_unslash($_POST['telegram'] ?? ''));
            if ($tg !== '') update_user_meta($puid, 'lfi_nct_telegram', $tg);
            lfi_nct_partner_levels_save($puid, $_POST['casquettes'] ?? []);
            wp_safe_redirect(lfi_nct_app_url('partenaire-espace', ['uid' => $puid, 'promu' => 1])); exit;
        }
        wp_safe_redirect(lfi_nct_app_url('partenaires', ['err' => 1])); exit;
    }

    /* Éditer les casquettes / secteur / téléphone d'un·e élu·e existant·e. */
    if (!empty($_POST['lfi_partner_edit']) && check_admin_referer('lfi_partner_edit')) {
        $euid = (int) ($_POST['user_id'] ?? 0);
        if ($euid && lfi_nct_user_role_partner($euid)) {
            lfi_nct_partner_levels_save($euid, $_POST['casquettes'] ?? []);
            update_user_meta($euid, 'lfi_nct_partner_scope', sanitize_text_field(wp_unslash($_POST['scope'] ?? '')));
            update_user_meta($euid, 'lfi_nct_tel', sanitize_text_field(wp_unslash($_POST['tel'] ?? '')));
            update_user_meta($euid, 'lfi_nct_telegram', sanitize_text_field(wp_unslash($_POST['telegram'] ?? '')));
            /* Email = canal PRINCIPAL pour un·e élu·e (souvent, avant le téléphone). */
            $pem = sanitize_email(wp_unslash($_POST['email'] ?? ''));
            if ($pem !== '' && is_email($pem)) { $own = email_exists($pem); if (!$own || (int) $own === $euid) wp_update_user(['ID' => $euid, 'user_email' => $pem]); }
        }
        wp_safe_redirect(lfi_nct_app_url('partenaires', ['edited' => 1])); exit;
    }

    lfi_nct_app_screen_open('🤝 Élu·es partenaires', 'Comptes privilégiés, cloisonnés — ligne directe + dossier partagé');
    if (!empty($_GET['err'])) lfi_nct_app_flash('⚠️ Opération impossible (email déjà utilisé, invalide, ou compte introuvable).', 'error');
    if (!empty($_GET['edited'])) lfi_nct_app_flash('✅ Casquettes mises à jour.');

    $partners = get_users(['role' => LFI_NCT_ROLE_PARTNER, 'orderby' => 'display_name']);
    if (!empty($partners)) {
        $def = lfi_nct_partner_casquettes_def();
        echo '<div class="lfi-app-help" style="background:#f6f2fc;border-left:4px solid #6f4bb0"><small>🔗 Chaque personne est affichée <strong>une seule fois</strong>, avec <strong>toutes ses casquettes</strong> détectées automatiquement (élu, admin de GA, membre, locataire) — même compte, lecture directe de la base. Groupé par échelon.</small></div>';
        /* Groupe par échelon (national > local > autre) — chaque personne une fois. */
        $groups = ['national' => [], 'local' => [], 'autre' => []];
        foreach ($partners as $p) {
            $ech = 'autre';
            foreach (lfi_nct_partner_levels($p->ID) as $lvl) {
                $e = $def[$lvl][2] ?? '';
                if ($e === 'national') { $ech = 'national'; break; }
                if ($e === 'local') $ech = 'local';
            }
            $groups[$ech][] = $p;
        }
        $headers = [
            'national' => ['🇫🇷 Échelon national', '#0066a3'],
            'local'    => ['🏙️ Échelon local — ville · métropole · département · région', '#4b2e83'],
            'autre'    => ['Autres', '#777'],
        ];
        foreach ($headers as $ek => $h) {
            if (empty($groups[$ek])) continue;
            echo '<h3 style="margin:14px 0 6px;color:' . esc_attr($h[1]) . '">' . esc_html($h[0]) . '</h3>';
            echo '<ul class="lfi-app-list">';
            foreach ($groups[$ek] as $p) {
                $tg     = get_user_meta($p->ID, 'lfi_nct_telegram', true);
                $thread = lfi_nct_partner_thread($p->ID);
                $levels = lfi_nct_partner_levels($p->ID);
                $scope  = lfi_nct_partner_scope($p->ID);
                $tel    = trim((string) get_user_meta($p->ID, 'lfi_nct_tel', true));
                echo '<li class="lfi-app-card" style="border-left:4px solid #4b2e83">';
                echo '<div class="head"><div class="who">' . esc_html($p->display_name) . '</div></div>';
                /* TOUTES les casquettes : élu (chaque niveau) + liées (auto). */
                echo '<div class="meta">';
                foreach ($levels as $lvl) if (isset($def[$lvl])) echo '<span class="meta-chip" style="background:#efe9fb;color:#4b2e83">' . $def[$lvl][0] . ' ' . esc_html($def[$lvl][1]) . '</span>';
                foreach (lfi_nct_person_linked_chips($p->ID) as $c) echo '<span class="meta-chip" style="background:#f4f1f9;color:' . esc_attr($c[2]) . '">' . $c[0] . ' ' . esc_html($c[1]) . '</span>';
                echo '</div>';
                echo '<div class="meta"><span class="meta-chip">' . esc_html($p->user_email) . '</span>' . ($tg ? '<span class="meta-chip">✈️ ' . esc_html($tg) . '</span>' : '') . '</div>';
                if (!empty($thread)) echo '<div class="com" style="font-size:.9em">💬 ' . count($thread) . ' message(s)</div>';
                echo '<div style="margin-top:8px"><a class="btn-primary" style="background:#4b2e83" href="' . esc_url(lfi_nct_app_url('partenaire-espace', ['uid' => $p->ID])) . '">Ouvrir l\'espace partagé →</a></div>';
                echo '<details style="margin-top:6px"><summary style="cursor:pointer;font-size:.85em;color:#4b2e83;font-weight:700">🎩 Casquettes &amp; secteur</summary>';
                echo '<form method="post" style="margin-top:6px">' . wp_nonce_field('lfi_partner_edit', '_wpnonce', true, false);
                echo '<input type="hidden" name="lfi_partner_edit" value="1"><input type="hidden" name="user_id" value="' . (int) $p->ID . '">';
                echo lfi_nct_partner_casquettes_checkboxes($levels);
                echo '<div style="margin:4px 0"><label style="font-size:.85em">📧 Email (canal principal)<br><input type="email" name="email" value="' . esc_attr($p->user_email) . '" placeholder="prenom.nom@assemblee-nationale.fr" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:8px"></label></div>';
                echo '<div style="margin:4px 0"><label style="font-size:.85em">📞 Téléphone (rare, optionnel)<br><input type="tel" name="tel" value="' . esc_attr($tel) . '" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:8px"></label></div>';
                echo '<div style="margin:4px 0"><label style="font-size:.85em">✈️ Telegram (optionnel)<br><input type="text" name="telegram" value="' . esc_attr((string) $tg) . '" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:8px"></label></div>';
                echo '<div style="margin:4px 0"><label style="font-size:.85em">Secteur / délégation (optionnel)<br><input type="text" name="scope" value="' . esc_attr($scope) . '" placeholder="ex. Logement, Clos Toreau…" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:8px"></label></div>';
                echo '<button type="submit" class="btn-ghost" style="font-size:.85em;margin-top:4px">💾 Enregistrer les casquettes</button></form></details>';
                echo '</li>';
            }
            echo '</ul>';
        }
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
    echo '<div style="margin:8px 0"><div style="font-weight:700;font-size:.9em;margin-bottom:2px">🎩 Casquette(s) — plusieurs possibles</div>' . lfi_nct_partner_casquettes_checkboxes(['municipal']) . '</div>';
    echo '<div style="margin-top:8px"><button type="submit" class="btn-primary" style="background:#186a3b">Créer le compte + obtenir le lien</button></div>';
    echo '</form></details>';

    /* Promouvoir un compte EXISTANT — admin de GA OU locataire (ex. William,
       locataire, qui est aussi conseiller municipal + métropolitain). */
    $candidats = get_users(['role__in' => array_filter([
        defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : '',
        defined('LFI_NCT_ROLE_TENANT') ? LFI_NCT_ROLE_TENANT : '',
    ]), 'orderby' => 'display_name', 'number' => 800]);
    $opts = '';
    foreach ($candidats as $c) {
        if (lfi_nct_user_role_partner($c->ID)) continue; /* déjà élu·e */
        $is_tenant = defined('LFI_NCT_ROLE_TENANT') && in_array(LFI_NCT_ROLE_TENANT, (array) $c->roles, true);
        $ga = (string) get_user_meta($c->ID, 'lfi_nct_ga', true);
        $mark = $is_tenant ? ' — 🏠 locataire' : ($ga ? ' — ' . $ga : '');
        $opts .= '<option value="' . (int) $c->ID . '">' . esc_html($c->display_name . $mark) . '</option>';
    }
    if ($opts !== '') {
        echo '<details class="lfi-app-card" style="border-left:4px solid #4b2e83"><summary style="cursor:pointer;font-weight:800;color:#4b2e83">🏛️ Promouvoir un compte existant en élu·e</summary>';
        echo '<div class="lfi-app-help" style="margin:6px 0"><small>Pour une personne <strong>déjà inscrite</strong> — une admin de GA <em>ou un·e locataire</em> qui est aussi élu·e. On lui <strong>ajoute</strong> son espace élu·e (casquette de plus) : elle garde son rôle et son dossier actuels. Le cloisonnement reste total entre ses casquettes.</small></div>';
        echo '<form method="post" style="margin-top:6px">' . wp_nonce_field('lfi_partner_promote', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_partner_promote" value="1">';
        echo '<div style="margin:6px 0"><label>Personne<br><select name="user_id" required style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px">' . $opts . '</select></label></div>';
        echo '<div style="margin:8px 0"><div style="font-weight:700;font-size:.9em;margin-bottom:2px">🎩 Casquette(s) — plusieurs possibles</div>' . lfi_nct_partner_casquettes_checkboxes(['municipal']) . '</div>';
        echo '<div style="margin:6px 0"><label>Telegram (optionnel)<br><input type="text" name="telegram" placeholder="@…" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px"></label></div>';
        echo '<div style="margin-top:8px"><button type="submit" class="btn-primary" style="background:#4b2e83">🏛️ Créer son espace élu·e</button></div>';
        echo '</form></details>';
    }

    lfi_nct_app_screen_close();
}

/* -------------------------------------------------------------- *
 *  ADMIN : espace partagé d'UN partenaire (co-gestion + réponse) *
 * -------------------------------------------------------------- */
/**
 * Message d'invitation d'un·e élu·e : explique l'outil, MONTRE le travail
 * accompli (résultats anonymes) et l'invite à DÉCOUVRIR (lien 1 clic → visite
 * guidée). Même contenu pour le copier-coller Telegram et pour l'email.
 */
function lfi_nct_partner_invite_message($p, $link) {
    $prenom = $p->first_name ?: $p->display_name;
    $bilan  = '';
    if (function_exists('lfi_nct_demo_stats')) {
        $st = lfi_nct_demo_stats();
        $bilan = "📊 Ce qu'on a DÉJÀ obtenu (données anonymes) : "
               . (int) ($st['publiees'] ?? 0) . " victoire(s), "
               . (int) ($st['foyers'] ?? 0) . " foyer(s) accompagné(s), "
               . (int) ($st['suivis'] ?? 0) . " locataire(s) qui demandent un suivi.\n\n";
    }
    return "Salut " . $prenom . ",\n\n"
        . "On a construit au Clos Toreau (Nantes Sud) un outil de terrain pour défendre les locataires : de la porte du locataire jusqu'au tribunal — enquête, dossier monté tout seul, argumentaire chiffré sur le bailleur, relogement, victoires. Ça tourne, c'est concret.\n\n"
        . $bilan
        . "Je t'ai créé un espace rien qu'à toi pour DÉCOUVRIR l'outil : une visite guidée en 3 min (ce qu'on fait, comment ça marche, et ce qu'on a gagné). Dedans aussi :\n"
        . "• une ligne directe avec moi ;\n"
        . "• un dossier qu'on partage ;\n"
        . "• l'étude des comptes du bailleur, prête à ressortir en conseil.\n\n"
        . "👉 Connexion en 1 clic, rien à taper : " . $link . "\n"
        . "(À la 1re ouverture, choisis ton mot de passe, puis ajoute l'appli à ton écran d'accueil.)\n\n"
        . "L'idée : que tu découvres, que tu t'en empares. On voit ensemble ce qu'on en fait — et dis-moi ce que toi tu en penses.\n"
        . "À très vite,\nFabrice";
}

function lfi_nct_app_view_partenaire_espace() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $uid = isset($_GET['uid']) ? (int) $_GET['uid'] : 0;
    $p = $uid ? get_user_by('id', $uid) : null;
    if (!$p || !lfi_nct_user_role_partner($uid)) { wp_safe_redirect(lfi_nct_app_url('partenaires')); exit; }
    $back = lfi_nct_app_url('partenaire-espace', ['uid' => $uid]);
    lfi_nct_partner_activity_clear($uid); /* l'admin consulte → on efface l'alerte */

    /* Envoi de l'invitation par EMAIL (même message, lien 1 clic inclus). */
    if (!empty($_POST['lfi_partner_send_invite']) && check_admin_referer('lfi_partner_send_invite')) {
        $sent = false;
        if (is_email($p->user_email) && strpos((string) $p->user_email, '@partenaire.example') === false) {
            $link = function_exists('lfi_nct_login_link') ? lfi_nct_login_link($uid, lfi_nct_app_url('espace')) : lfi_nct_app_url();
            $txt  = lfi_nct_partner_invite_message($p, $link);
            $html = str_replace(esc_html($link), '<a href="' . esc_url($link) . '" style="color:#4b2e83;font-weight:700">' . esc_html($link) . '</a>', nl2br(esc_html($txt)));
            $html = '<div style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:15px;color:#222;line-height:1.6">' . $html . '</div>';
            $sent = wp_mail($p->user_email, 'Découvre notre outil de défense des locataires 👋', $html, ['Content-Type: text/html; charset=UTF-8']);
        }
        wp_safe_redirect(add_query_arg($sent ? 'invsent' : 'invfail', 1, $back)); exit;
    }

    /* Mise à jour de l'email du partenaire (pour qu'il reçoive les réponses). */
    if (!empty($_POST['lfi_partner_email']) && check_admin_referer('lfi_partner_email')) {
        $em = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        if (is_email($em) && (email_exists($em) === false || (int) email_exists($em) === $uid)) {
            wp_update_user(['ID' => $uid, 'user_email' => $em]);
        }
        wp_safe_redirect(add_query_arg('mailok', 1, $back)); exit;
    }
    /* Niveau + secteur (pour l'organigramme « à qui s'adresser »). */
    if (!empty($_POST['lfi_partner_scope']) && check_admin_referer('lfi_partner_scope')) {
        $lvl = sanitize_key($_POST['level'] ?? 'municipal');
        if (!in_array($lvl, ['municipal', 'national', 'europeen'], true)) $lvl = 'municipal';
        update_user_meta($uid, 'lfi_nct_partner_level', $lvl);
        update_user_meta($uid, 'lfi_nct_partner_scope', sanitize_text_field(wp_unslash($_POST['scope'] ?? '')));
        $tel = sanitize_text_field(wp_unslash($_POST['tel'] ?? ''));
        if ($tel !== '') update_user_meta($uid, 'lfi_nct_tel', $tel);
        wp_safe_redirect(add_query_arg('scopeok', 1, $back)); exit;
    }
    /* Génère (ou régénère) le lien magique — sur clic explicite uniquement,
       car chaque génération invalide la précédente (usage unique). */
    $fresh_link = '';
    if (!empty($_POST['lfi_partner_genlink']) && check_admin_referer('lfi_partner_genlink')) {
        $fresh_link = function_exists('lfi_nct_login_link') ? lfi_nct_login_link($uid, lfi_nct_app_url('espace')) : lfi_nct_app_url();
    }

    lfi_nct_partner_handle_posts($uid, $back);

    lfi_nct_app_screen_open('🤝 ' . $p->display_name, 'Espace partagé — dossier co-géré + ligne directe');
    if (!empty($_GET['ok']))     lfi_nct_app_flash('✅ Ajouté au dossier partagé.');
    if (!empty($_GET['del']))    lfi_nct_app_flash('🗑 Élément retiré.');
    if (!empty($_GET['msg']))    lfi_nct_app_flash('✅ Message envoyé.');
    if (!empty($_GET['mailok'])) lfi_nct_app_flash('✅ Email mis à jour.');
    if (!empty($_GET['scopeok'])) lfi_nct_app_flash('✅ Niveau & secteur enregistrés.');
    if (!empty($_GET['cree']))   lfi_nct_app_flash('✅ Compte créé — génère son lien + le message Telegram ci-dessous.');
    if (!empty($_GET['promu']))  lfi_nct_app_flash('✅ Espace élu·e créé. Elle garde son rôle actuel + accède à « 🏛️ Mon espace élu·e ». Génère son lien ci-dessous si besoin.');
    if (!empty($_GET['invsent'])) lfi_nct_app_flash('✅ Email d\'invitation envoyé (avec le bilan + le lien de découverte).');
    if (!empty($_GET['invfail'])) lfi_nct_app_flash('⚠️ Envoi impossible (email provisoire ou invalide — mets son vrai email d\'abord).', 'error');

    /* ===== Présentation du travail accompli (à montrer / envoyer / PDF) ===== */
    echo '<div class="lfi-app-card" style="border:2px solid #186a3b;background:#f2fbf4">';
    echo '<div class="head"><div class="who">🎁 Lui montrer le travail accompli</div></div>';
    echo '<div style="font-size:.9em;color:#333;margin:4px 0 8px">Le but : <strong>expliquer et l\'inviter à découvrir l\'outil</strong>. Trois façons :</div>';
    echo '<div style="display:flex;flex-direction:column;gap:6px">';
    echo '<div>🔗 <strong>Son espace</strong> : le lien de connexion ci-dessous l\'amène direct sur la <strong>visite guidée</strong> (ce qu\'on fait + ce qu\'on a gagné).</div>';
    echo '<div>✉️ <strong>Par email</strong> : bouton « Envoyer l\'invitation par email » (le message inclut le bilan chiffré).</div>';
    echo '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">';
    echo '<a class="btn-ghost" style="color:#c8102e;border-color:#f0b6c1" href="' . esc_url(lfi_nct_app_url('kit-national')) . '" target="_blank" rel="noopener">📄 Présentation simple (PDF)</a>';
    echo '<a class="btn-primary" style="background:#4b2e83" href="' . esc_url(lfi_nct_app_url('kit-technique')) . '" target="_blank" rel="noopener">🔬 Présentation DÉTAILLÉE (mécanismes) — pour un profil technique</a>';
    echo '</div>';
    echo '<div class="lfi-app-help" style="margin-top:6px"><small>Pour <strong>Manuel Bompard</strong> (mathématicien) : la présentation <strong>détaillée</strong> explique les mécanismes, les invariants et les garanties (le cœur reste scellé). Ouvre-la → 🖨️ Enregistrer en PDF pour la joindre.</small></div>';
    echo '</div>';

    /* ===== Bloc « Message Telegram prêt à envoyer » ===== */
    $tg = get_user_meta($uid, 'lfi_nct_telegram', true);
    $is_placeholder_mail = (strpos((string) $p->user_email, '@partenaire.example') !== false);
    echo '<div class="lfi-app-card" style="border:2px solid #4b2e83;background:#faf7ff">';
    echo '<div class="head"><div class="who">📤 Message Telegram prêt à envoyer</div></div>';
    if ($is_placeholder_mail) {
        echo '<div class="lfi-app-help" style="background:#fff3cd;border-left:4px solid #d39e00"><small>⚠️ Son email est un <strong>provisoire</strong>. Le lien de connexion marche quand même, mais mets son <strong>vrai email</strong> ci-dessous pour qu\'il reçoive tes réponses.</small></div>';
    }
    /* Email éditable. */
    echo '<form method="post" style="margin:8px 0;display:flex;gap:6px;flex-wrap:wrap;align-items:center">' . wp_nonce_field('lfi_partner_email', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_partner_email" value="1">';
    echo '<input type="email" name="email" value="' . esc_attr($p->user_email) . '" style="flex:1;min-width:180px;padding:8px;border:1px solid #ccc;border-radius:8px" placeholder="email de ' . esc_attr($p->display_name) . '">';
    echo '<button type="submit" class="btn-ghost" style="padding:8px 12px">💾 Enregistrer l\'email</button></form>';

    if ($fresh_link !== '') {
        $message = lfi_nct_partner_invite_message($p, $fresh_link);
        echo '<div class="lfi-app-help" style="margin-top:6px;background:#f4fbf4;border-left:4px solid #186a3b"><small>✅ Lien généré' . ($tg ? ' pour <strong>' . esc_html($tg) . '</strong>' : '') . '. Copie tout le message ci-dessous et colle-le dans Telegram. <strong>Ne régénère pas</strong> après l\'envoi (ça invalide le lien).</small></div>';
        echo '<textarea readonly onclick="this.select()" style="width:100%;height:250px;margin-top:6px;font-size:.82em;padding:8px;border:1px solid #ccc;border-radius:8px">' . esc_textarea($message) . '</textarea>';
    } else {
        echo '<form method="post" style="margin-top:6px">' . wp_nonce_field('lfi_partner_genlink', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_partner_genlink" value="1">';
        echo '<button type="submit" class="btn-primary" style="background:#4b2e83">🔗 Générer le lien + le message Telegram</button></form>';
        echo '<div class="lfi-app-help" style="margin-top:4px"><small>Le lien connecte ' . esc_html($p->display_name) . ' d\'un seul clic, sans identifiant (usage unique, 14 jours).</small></div>';
    }
    /* Envoi direct par email (le message inclut le bilan + le lien de découverte). */
    if (is_email($p->user_email) && !$is_placeholder_mail) {
        echo '<form method="post" style="margin-top:8px">' . wp_nonce_field('lfi_partner_send_invite', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_partner_send_invite" value="1">';
        echo '<button type="submit" class="btn-primary" style="background:#0066a3" onclick="return confirm(\'Envoyer l\\\'invitation par email à ' . esc_js($p->user_email) . ' ? Un lien de connexion NEUF est généré (il remplace tout lien précédent).\')">✉️ Envoyer / renvoyer l\'invitation (lien neuf)</button></form>';
        echo '<div class="lfi-app-help" style="margin-top:4px"><small>Chaque envoi crée un <strong>nouveau lien qui remplace le précédent</strong> — clique ici si tu doutes que l\'ancien fonctionne encore.</small></div>';
    }
    echo '</div>';

    /* ===== Niveau + secteur (pour l'organigramme « à qui s'adresser ») ===== */
    $cur_lvl = lfi_nct_partner_level($uid);
    $cur_scope = lfi_nct_partner_scope($uid);
    $cur_tel = trim((string) get_user_meta($uid, 'lfi_nct_tel', true));
    echo '<div class="lfi-app-card" style="border-left:4px solid #0066a3">';
    echo '<div class="head"><div class="who">🎯 Niveau & secteur (organigramme)</div></div>';
    echo '<form method="post" style="margin-top:8px">' . wp_nonce_field('lfi_partner_scope', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_partner_scope" value="1">';
    echo '<div style="margin:6px 0"><label>Niveau<br><select name="level" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px">';
    foreach (['municipal' => '🏛️ Municipal', 'national' => '🇫🇷 National (député·e)', 'europeen' => '🇪🇺 Européen'] as $k => $lab) {
        echo '<option value="' . esc_attr($k) . '"' . selected($k, $cur_lvl, false) . '>' . esc_html($lab) . '</option>';
    }
    echo '</select></label></div>';
    echo '<div style="margin:6px 0"><label>Secteur / rôle (visible par les membres)<br><input type="text" name="scope" value="' . esc_attr($cur_scope) . '" maxlength="160" placeholder="ex : secteur Dervallières · logement / social" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px"></label></div>';
    echo '<div style="margin:6px 0"><label>Téléphone (visible seulement par les élu·es et toi)<br><input type="tel" name="tel" value="' . esc_attr($cur_tel) . '" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px"></label></div>';
    echo '<div style="margin-top:8px"><button type="submit" class="btn-ghost" style="color:#0066a3">💾 Enregistrer</button></div></form></div>';

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

    /* Cas d'un·e élu·e QUI EST AUSSI admin de GA (ex. Irina, admin du GA
       Dervallières) : on ne lui confisque pas sa console. On ne prend en main
       QUE la route « espace » (son espace élu·e) ; tout le reste passe à son
       tableau de bord d'admin de GA. */
    $also_admin = current_user_can('manage_options')
               || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
    if ($also_admin) {
        if ($vue === 'espace') { lfi_nct_app_view_partenaire_dashboard(); return true; }
        if ($vue === 'nmh')    { lfi_nct_app_view_partenaire_nmh();       return true; }
        return false; /* garde sa console d'admin pour tout le reste */
    }

    /* Élu·e « pur » (sans autre rôle) : espace dédié.
       ⚠️ RÈGLE ABSOLUE : on N'expose JAMAIS la vue « audit-nmh » complète à un·e
       élu·e — elle contient des chiffres de l'ENQUÊTE TERRAIN. L'élu·e n'a que la
       vue « nmh » (comptes publics + argumentaires, SANS aucune donnée d'enquête). */
    switch ($vue) {
        case 'nmh':        lfi_nct_app_view_partenaire_nmh(); break;
        case 'audit-nmh':  lfi_nct_app_view_partenaire_nmh(); break; /* redirige vers la vue sûre */
        case 'victoires':  lfi_nct_app_view_victoires();  break;
        case 'aide':       lfi_nct_app_view_aide();       break;
        case 'installer':  lfi_nct_app_view_installer();  break;
        case 'mon-profil': lfi_nct_app_view_mon_profil(); break;
        case 'espace':     /* fallthrough */
        default:           lfi_nct_app_view_partenaire_dashboard();
    }
    return true;
}

/* -------------------------------------------------------------- *
 *  SEED : crée le compte de William Aucant une fois, au déploiement. *
 *  Son interface est alors prête ; l'admin génère le lien Telegram   *
 *  depuis « 🤝 Élu·es partenaires ». Mot de passe choisi par lui à   *
 *  la 1re connexion (onboarding). Email provisoire → à compléter.    *
 * -------------------------------------------------------------- */
add_action('init', 'lfi_nct_partner_seed_william', 12);
function lfi_nct_partner_seed_william() {
    if (get_option('lfi_nct_partner_seed_william_done')) return;
    if (!get_role(LFI_NCT_ROLE_PARTNER)) return; /* rôle pas encore prêt */

    /* Déjà semé (marqueur) ? */
    $already = get_users(['meta_key' => 'lfi_nct_partner_seed', 'meta_value' => 'william', 'number' => 1, 'fields' => 'ID']);
    if (!empty($already)) { update_option('lfi_nct_partner_seed_william_done', 1, false); return; }

    $login = username_exists('william.aucant') ? 'william.aucant.lfi' : 'william.aucant';
    $email = 'william.aucant@partenaire.example'; /* provisoire — l'admin met le vrai */
    if (username_exists($login) || email_exists($email)) { update_option('lfi_nct_partner_seed_william_done', 1, false); return; }

    $uid = wp_insert_user([
        'user_login'   => $login,
        'user_email'   => $email,
        'user_pass'    => wp_generate_password(16),
        'display_name' => 'William Aucant',
        'first_name'   => 'William',
        'last_name'    => 'Aucant',
        'role'         => LFI_NCT_ROLE_PARTNER,
    ]);
    if (!is_wp_error($uid)) {
        update_user_meta($uid, 'lfi_nct_telegram', '@WilliamAucant');
        update_user_meta($uid, 'lfi_nct_partner_seed', 'william');
    }
    update_option('lfi_nct_partner_seed_william_done', 1, false);
}

/* -------------------------------------------------------------- *
 *  NETTOYAGE (une fois) : retire le mot auto-déposé jadis dans le    *
 *  dossier partagé de William. Le dossier partagé ne doit contenir   *
 *  QUE ce que William et Fabrice y mettent eux-mêmes — on n'y copie  *
 *  rien automatiquement.                                             *
 * -------------------------------------------------------------- */
add_action('init', 'lfi_nct_partner_cleanup_william_dossier', 14);
function lfi_nct_partner_cleanup_william_dossier() {
    if (get_option('lfi_nct_partner_cleanup_william_dossier_done')) return;
    $found = get_users(['meta_key' => 'lfi_nct_partner_seed', 'meta_value' => 'william', 'number' => 1, 'fields' => 'ID']);
    if (empty($found)) return; /* William pas encore créé — on retentera. */
    $uid = (int) $found[0];
    $items = lfi_nct_partner_dossier($uid);
    $clean = array_values(array_filter($items, function ($it) {
        $t = (string) ($it['titre'] ?? '');
        return !(($it['by'] ?? '') === 'Fabrice' && strpos($t, 'Dossier NMH') !== false);
    }));
    if (count($clean) !== count($items)) lfi_nct_partner_dossier_save($uid, $clean);
    update_option('lfi_nct_partner_cleanup_william_dossier_done', 1, false);
}

/* -------------------------------------------------------------- *
 *  SEED best-effort : Irina (élue municipale, déjà admin d'un GA). *
 *  On lui AJOUTE le rôle partenaire si on la trouve SANS ambiguïté  *
 *  (un seul compte dont le prénom/nom commence par « Irina »).      *
 *  Sinon on ne touche à rien : l'admin la promeut via l'UI.         *
 * -------------------------------------------------------------- */
add_action('init', 'lfi_nct_partner_seed_irina', 13);
function lfi_nct_partner_seed_irina() {
    if (get_option('lfi_nct_partner_seed_irina_done')) return;
    if (!get_role(LFI_NCT_ROLE_PARTNER)) return;

    $matches = get_users(['search' => 'Irina*', 'search_columns' => ['user_login', 'display_name', 'user_nicename'], 'number' => 5]);
    if (empty($matches)) {
        /* Recherche complémentaire sur le prénom (meta first_name). */
        $matches = get_users(['meta_key' => 'first_name', 'meta_value' => 'Irina', 'number' => 5]);
    }
    /* On n'agit QUE si exactement une candidate → aucune ambiguïté. */
    if (count($matches) === 1) {
        $u = $matches[0];
        if (!lfi_nct_user_role_partner($u->ID)) $u->add_role(LFI_NCT_ROLE_PARTNER);
        update_user_meta($u->ID, 'lfi_nct_partner_seed', 'irina');
        update_option('lfi_nct_partner_seed_irina_done', 1, false);
    }
    /* Si 0 ou plusieurs : on laisse le flag à 0 pour retenter, et l'admin
       peut promouvoir manuellement depuis « 🤝 Élu·es partenaires ». */
}
