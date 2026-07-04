<?php
/**
 * BOT JUSTICE — monter les dossiers amiables & judiciaires.
 *
 * Deux briques :
 *  1) VERSER DES PIÈCES : joindre au dossier les documents à transmettre à
 *     Nantes Métropole Habitat, à la Commission de conciliation ou au tribunal
 *     (PDF, photos). Rangées par destination. Visibles par l'admin du GA et par
 *     l'avocat·e à qui le dossier est confié.
 *  2) COMMISSION DÉPARTEMENTALE DE CONCILIATION (CDC) : générer la saisine
 *     pré-remplie + la liste des pièces + les liens officiels (démarche en
 *     ligne 44, Histologe, service-public). Gratuit, paritaire, avis sous 2 mois.
 *
 * Sources publiques (procédure) :
 *  - service-public.gouv.fr/particuliers/vosdroits/F1216
 *  - loire-atlantique.gouv.fr — saisine en ligne depuis le 01/01/2025
 *  - secrétariat CDC 44 : 02 72 20 63 04
 * Aucun chiffre de préjudice n'est inventé : la lettre renvoie au dossier.
 */
if (!defined('ABSPATH')) exit;

/** Destinations possibles d'une pièce versée. */
function lfi_nct_justice_destinations() {
    return [
        'interne' => '🔒 Interne au GA',
        'nmh'     => '🏢 À transmettre à Nantes Métropole Habitat',
        'cdc'     => '⚖️ Pour la Commission de conciliation',
        'tj'      => '🏛️ Pour le Tribunal Judiciaire',
    ];
}

/** Pièces versées à un dossier (attachments taggés au locataire). */
function lfi_nct_justice_pieces($tenant_uid) {
    return get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'any',
        'posts_per_page' => 200,
        'orderby'        => 'date', 'order' => 'DESC',
        'meta_query'     => [
            ['key' => '_lfi_dossier_piece', 'value' => '1'],
            ['key' => '_lfi_tenant_user_id', 'value' => (int) $tenant_uid],
        ],
    ]);
}

/** Upload d'une ou plusieurs pièces (PDF / images), taggées destination. */
function lfi_nct_justice_handle_upload($tenant_uid) {
    if (empty($_FILES['justice_pieces']['name'][0])) return 0;
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    $dest = sanitize_key($_POST['piece_dest'] ?? 'interne');
    if (!array_key_exists($dest, lfi_nct_justice_destinations())) $dest = 'interne';
    $f = $_FILES['justice_pieces'];
    $count = is_array($f['name']) ? count($f['name']) : 0;
    $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic'];
    $done = 0;
    for ($i = 0; $i < $count; $i++) {
        if (empty($f['name'][$i]) || !empty($f['error'][$i])) continue;
        $type = (string) ($f['type'][$i] ?? '');
        if ($type && !in_array($type, $allowed, true) && strpos($type, 'image/') !== 0) continue;
        $_FILES['lfi_justice_one'] = ['name' => $f['name'][$i], 'type' => $type, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        $aid = media_handle_upload('lfi_justice_one', 0);
        if (!is_wp_error($aid)) {
            update_post_meta($aid, '_lfi_dossier_piece', '1');
            update_post_meta($aid, '_lfi_tenant_user_id', (int) $tenant_uid);
            update_post_meta($aid, '_lfi_dossier_piece_dest', $dest);
            $done++;
        }
    }
    unset($_FILES['lfi_justice_one']);
    return $done;
}

/** Boîte « verser des pièces » + liste, réutilisable sur le dossier. */
function lfi_nct_justice_pieces_box($u, $editable = true) {
    $dests  = lfi_nct_justice_destinations();
    $pieces = lfi_nct_justice_pieces($u->ID);
    echo '<details style="margin-top:10px;background:#fff;border-radius:10px;border:1px solid #eee;overflow:hidden">';
    echo '<summary style="cursor:pointer;padding:10px 12px;font-weight:800;color:#0066a3;list-style:none;display:flex;justify-content:space-between;align-items:center"><span>📎 Pièces à transmettre (' . count($pieces) . ')</span><span>▾</span></summary>';
    echo '<div style="padding:2px 12px 12px">';
    if ($editable) {
        echo '<form method="post" enctype="multipart/form-data" style="margin:6px 0;padding:10px;background:#f7fafd;border-radius:8px">';
        wp_nonce_field('lfi_justice_piece');
        echo '<input type="hidden" name="lfi_justice_piece" value="1">';
        echo '<label style="margin:0">Destination<select name="piece_dest" style="width:100%;margin-top:4px">';
        foreach ($dests as $k => $l) echo '<option value="' . esc_attr($k) . '">' . esc_html($l) . '</option>';
        echo '</select></label>';
        echo '<label style="margin:6px 0 0;display:block">Fichiers (PDF, photos)<input type="file" name="justice_pieces[]" accept="application/pdf,image/*" multiple style="margin-top:4px"></label>';
        echo '<button type="submit" class="btn-primary" style="background:#0066a3;margin-top:8px">📎 Verser au dossier</button>';
        echo '</form>';
    }
    if (empty($pieces)) {
        echo '<div class="lfi-app-help">Aucune pièce versée. Ajoute ici les documents à transmettre (bail, état des lieux, courriers, photos, certificats…).</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        foreach ($pieces as $p) {
            $dest = (string) get_post_meta($p->ID, '_lfi_dossier_piece_dest', true);
            $url  = wp_get_attachment_url($p->ID);
            $mime = get_post_mime_type($p->ID);
            $ico  = ($mime === 'application/pdf') ? '📄' : '🖼';
            echo '<li class="lfi-app-card" style="border-left:4px solid #0066a3">';
            echo '<div class="head"><div class="who">' . $ico . ' ' . esc_html(get_the_title($p->ID) ?: 'Pièce') . '</div>';
            if (isset($dests[$dest])) echo '<div class="badge">' . esc_html($dests[$dest]) . '</div>';
            echo '</div>';
            echo '<div class="row-actions" style="margin-top:6px"><a class="btn-primary" href="' . esc_url($url) . '" target="_blank" rel="noopener">📂 Ouvrir</a>';
            if ($editable) {
                $del = wp_nonce_url(admin_url('admin-post.php?action=lfi_nct_justice_piece_del&aid=' . (int) $p->ID . '&uid=' . (int) $u->ID), 'lfi_justice_del_' . (int) $p->ID);
                echo ' <a class="btn-ghost" href="' . esc_url($del) . '" onclick="return confirm(\'Retirer cette pièce ?\')">🗑 Retirer</a>';
            }
            echo '</div></li>';
        }
        echo '</ul>';
    }
    echo '</div></details>';
}

add_action('admin_post_lfi_nct_justice_piece_del', 'lfi_nct_justice_piece_del_handler');
function lfi_nct_justice_piece_del_handler() {
    $aid = (int) ($_GET['aid'] ?? 0);
    $uid = (int) ($_GET['uid'] ?? 0);
    if (!$aid || !check_admin_referer('lfi_justice_del_' . $aid)) wp_die('non');
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
    if ($can && (int) get_post_meta($aid, '_lfi_dossier_piece', true) && (!function_exists('lfi_nct_uid_in_scope') || lfi_nct_uid_in_scope($uid))) {
        wp_delete_attachment($aid, true);
    }
    wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $uid, 'piece_del' => 1]));
    exit;
}

/* ============================================================== *
 *  PIÈCES À DEMANDER AU LOCATAIRE — choisies par l'architecte +   *
 *  le robot avocat, suivies une par une, relançables. Quand TOUTES *
 *  les obligatoires sont reçues → on débloque la conciliation.     *
 * ============================================================== */

/** Liste recommandée (architecte + robot avocat) selon les désordres du dossier. */
function lfi_nct_pieces_recommended($uid, $row = null) {
    global $wpdb;
    if ($row === null) {
        $rid = (int) get_user_meta($uid, 'lfi_nct_response_id', true);
        $row = $rid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid)) : null;
    }
    $problem = ($row && function_exists('lfi_nct_app_enq_problem')) ? lfi_nct_app_enq_problem($row) : null;
    $txt = $problem && !empty($problem['chips']) ? mb_strtolower(implode(' ', array_map(function ($c) { return $c[1]; }, $problem['chips']))) : '';

    $list = [
        ['label' => 'Copie du bail (contrat de location)',            'mandatory' => true],
        ['label' => 'Quittances / avis d\'échéance de loyer récents',  'mandatory' => true],
        ['label' => 'Pièce d\'identité',                               'mandatory' => true],
        ['label' => 'État des lieux d\'entrée',                        'mandatory' => true],
        ['label' => 'Photos datées des désordres',                    'mandatory' => true],
        ['label' => 'Courriers / emails échangés avec NMH',           'mandatory' => true],
        ['label' => 'Attestation d\'assurance habitation',            'mandatory' => false],
        ['label' => 'Attestations de voisins (témoignages)',          'mandatory' => false],
    ];
    if (preg_match('/moisiss|humidit|insecte|nuisible|sant|cafard|punaise|rat/u', $txt)) {
        $list[] = ['label' => 'Certificat médical (lien logement ↔ santé)', 'mandatory' => true];
    }
    if (preg_match('/insalub|nuisible|moisiss|humidit/u', $txt)) {
        $list[] = ['label' => 'Signalement SCHS / ARS / Histologe', 'mandatory' => false];
    }
    return $list;
}

function lfi_nct_pieces_get($uid) {
    $l = get_user_meta($uid, 'lfi_nct_pieces_requises', true);
    if (!is_array($l) || empty($l)) {
        $l = [];
        foreach (lfi_nct_pieces_recommended($uid) as $p) {
            $l[] = ['label' => $p['label'], 'mandatory' => !empty($p['mandatory']), 'status' => 'todo', 'date' => ''];
        }
    }
    return $l;
}
function lfi_nct_pieces_save($uid, $l) { update_user_meta((int) $uid, 'lfi_nct_pieces_requises', array_values($l)); }

/** Progression des pièces obligatoires. */
function lfi_nct_pieces_progress($uid) {
    $l = lfi_nct_pieces_get($uid);
    $mand = 0; $recv = 0; $miss = [];
    foreach ($l as $p) {
        if (empty($p['mandatory'])) continue;
        $mand++;
        if (($p['status'] ?? '') === 'received') $recv++;
        else $miss[] = $p['label'];
    }
    return ['mandatory' => $mand, 'received' => $recv, 'complete' => ($mand > 0 && $recv >= $mand), 'missing' => $miss];
}

/** Vue : gérer les pièces à demander + inviter/relancer + débloquer conciliation. */
function lfi_nct_app_view_pieces() {
    if (!(function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'))) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $uid = (int) ($_GET['uid'] ?? 0);
    $u = $uid ? get_userdata($uid) : null;
    $in_scope = !function_exists('lfi_nct_uid_in_scope') || lfi_nct_uid_in_scope($uid);
    if (!$u || !$in_scope || !in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) { wp_safe_redirect(lfi_nct_app_url('dossiers')); exit; }

    $list = lfi_nct_pieces_get($uid);
    /* Actions */
    if (!empty($_POST['lfi_pieces_action']) && check_admin_referer('lfi_pieces')) {
        $act = sanitize_key($_POST['lfi_pieces_action']);
        if ($act === 'toggle') {
            $i = (int) ($_POST['idx'] ?? -1);
            if (isset($list[$i])) {
                $list[$i]['status'] = (($list[$i]['status'] ?? '') === 'received') ? 'todo' : 'received';
                $list[$i]['date']   = $list[$i]['status'] === 'received' ? current_time('Y-m-d') : '';
            }
        } elseif ($act === 'add') {
            $lbl = sanitize_text_field(wp_unslash($_POST['label'] ?? ''));
            if ($lbl !== '') $list[] = ['label' => $lbl, 'mandatory' => !empty($_POST['mandatory']), 'status' => 'todo', 'date' => ''];
        } elseif ($act === 'del') {
            $i = (int) ($_POST['idx'] ?? -1);
            if (isset($list[$i])) array_splice($list, $i, 1);
        } elseif ($act === 'reset') {
            $list = [];
            foreach (lfi_nct_pieces_recommended($uid) as $p) $list[] = ['label' => $p['label'], 'mandatory' => !empty($p['mandatory']), 'status' => 'todo', 'date' => ''];
        } elseif ($act === 'mandatory') {
            $i = (int) ($_POST['idx'] ?? -1);
            if (isset($list[$i])) $list[$i]['mandatory'] = empty($list[$i]['mandatory']);
        }
        lfi_nct_pieces_save($uid, $list);
        wp_safe_redirect(lfi_nct_app_url('pieces', ['uid' => $uid, 'saved' => 1])); exit;
    }

    /* Invitation / relance : génère le lien + le message des pièces manquantes. */
    $invite = '';
    if ((!empty($_POST['lfi_pieces_invite']) && check_admin_referer('lfi_pieces_invite'))) {
        $prog = lfi_nct_pieces_progress($uid);
        $miss = $prog['missing'];
        $link = function_exists('lfi_nct_login_link') ? lfi_nct_login_link($uid, function_exists('lfi_nct_app_page_url') ? lfi_nct_app_page_url() : home_url('/app/')) : (function_exists('lfi_nct_app_page_url') ? lfi_nct_app_page_url() : home_url('/app/'));
        $prenom = $u->first_name ?: $u->display_name;
        $moi = wp_get_current_user(); $moi_nom = $moi->display_name ?: $moi->user_login;
        $liste = $miss ? ("\n- " . implode("\n- ", $miss)) : ' (toutes reçues, merci !)';
        $invite = "Bonjour " . $prenom . ", c'est " . $moi_nom . " du Groupe d'Action LFI Nantes Sud. Pour faire avancer votre dossier logement, pouvez-vous nous envoyer (une photo lisible suffit) :" . $liste . "\n\nTout se dépose ici, en 1 clic : " . $link . "\nMerci beaucoup, on avance ensemble.";
    }

    lfi_nct_app_screen_open('📎 Pièces à demander', $u->display_name);
    echo '<div style="margin-bottom:10px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => $uid])) . '">← Retour au dossier</a></div>';
    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Pièces mises à jour.');
    echo '<div class="lfi-app-help">Liste proposée par l\'<strong>architecte</strong> et le <strong>robot avocat</strong> selon le dossier. Coche ce qui est reçu ; invite le locataire à envoyer le reste ; relance au besoin. <strong>Quand toutes les pièces obligatoires sont reçues, on peut monter le dossier de conciliation.</strong></div>';

    $prog = lfi_nct_pieces_progress($uid);
    $pct = $prog['mandatory'] ? round($prog['received'] / $prog['mandatory'] * 100) : 0;
    echo '<div style="margin:10px 0;padding:10px 12px;background:#f7fafd;border-radius:10px">';
    echo '<div style="font-weight:800;color:#0066a3">Pièces obligatoires : ' . (int) $prog['received'] . ' / ' . (int) $prog['mandatory'] . '</div>';
    echo '<div style="height:10px;background:#e5e5e5;border-radius:6px;margin-top:6px;overflow:hidden"><div style="height:100%;width:' . (int) $pct . '%;background:' . ($prog['complete'] ? '#186a3b' : '#0066a3') . '"></div></div>';
    echo '</div>';

    /* Invitation / relance */
    echo '<form method="post" style="margin:8px 0">' . wp_nonce_field('lfi_pieces_invite', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_pieces_invite" value="1">';
    echo '<button type="submit" class="btn-primary" style="background:#0066a3;width:100%">📩 ' . ($prog['received'] ? 'Relancer le locataire (pièces manquantes)' : 'Inviter le locataire à envoyer ses pièces') . '</button></form>';
    if ($invite !== '') {
        $tel = (string) get_user_meta($uid, 'lfi_nct_tel', true);
        $mail = sanitize_email((string) $u->user_email);
        $has_mail = ($mail !== '' && is_email($mail) && stripos($mail, '@tenant.') === false);
        echo '<div style="display:flex;gap:6px;flex-wrap:wrap;margin:6px 0">';
        if ($tel) echo '<a class="btn-primary" style="background:#186a3b" href="sms:' . esc_attr(preg_replace('/[^\d+]/', '', $tel)) . '?body=' . rawurlencode($invite) . '">📲 Envoyer par SMS</a>';
        if ($has_mail) echo '<a class="btn-primary" style="background:#0066a3" href="mailto:' . esc_attr($mail) . '?subject=' . rawurlencode('Vos pièces pour le dossier logement') . '&body=' . rawurlencode($invite) . '">✉️ Par email</a>';
        echo '</div>';
        echo '<textarea readonly onclick="this.select()" style="width:100%;height:150px;font-size:.82em;padding:8px;border:1px solid #ccc;border-radius:8px">' . esc_textarea($invite) . '</textarea>';
    }

    /* Liste des pièces */
    echo '<h3 style="margin:16px 0 6px">📋 Les pièces</h3>';
    echo '<ul class="lfi-app-list">';
    foreach ($list as $i => $p) {
        $recv = (($p['status'] ?? '') === 'received');
        $mand = !empty($p['mandatory']);
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . ($recv ? '#186a3b' : ($mand ? '#c8102e' : '#bbb')) . '">';
        echo '<div style="display:flex;align-items:flex-start;gap:10px">';
        /* toggle reçu */
        echo '<form method="post" style="margin:0">' . wp_nonce_field('lfi_pieces', '_wpnonce', true, false) . '<input type="hidden" name="lfi_pieces_action" value="toggle"><input type="hidden" name="idx" value="' . $i . '">';
        echo '<button type="submit" title="Marquer reçu/à recevoir" style="width:26px;height:26px;border-radius:6px;border:2px solid ' . ($recv ? '#186a3b' : '#bbb') . ';background:' . ($recv ? '#186a3b' : '#fff') . ';color:#fff;cursor:pointer;font-weight:800">' . ($recv ? '✓' : '') . '</button></form>';
        echo '<div style="flex:1"><div style="font-weight:600;' . ($recv ? 'color:#186a3b' : '') . '">' . esc_html($p['label']) . ' ';
        echo $mand ? '<span style="background:#fdeaec;color:#c8102e;font-size:.66em;font-weight:800;padding:1px 6px;border-radius:8px">OBLIGATOIRE</span>' : '<span style="background:#eee;color:#888;font-size:.66em;padding:1px 6px;border-radius:8px">facultative</span>';
        echo '</div>';
        if ($recv && !empty($p['date'])) echo '<div style="font-size:.78em;color:#186a3b;margin-top:1px">✅ reçue le ' . esc_html(wp_date('j M Y', strtotime($p['date']))) . '</div>';
        echo '</div>';
        /* actions : (dé)obligatoire, retirer */
        echo '<div style="display:flex;flex-direction:column;gap:3px">';
        echo '<form method="post" style="margin:0">' . wp_nonce_field('lfi_pieces', '_wpnonce', true, false) . '<input type="hidden" name="lfi_pieces_action" value="mandatory"><input type="hidden" name="idx" value="' . $i . '"><button type="submit" class="btn-ghost" style="font-size:.72em;padding:2px 6px">' . ($mand ? '↓ facultative' : '↑ obligatoire') . '</button></form>';
        echo '<form method="post" style="margin:0" onsubmit="return confirm(\'Retirer cette pièce ?\')">' . wp_nonce_field('lfi_pieces', '_wpnonce', true, false) . '<input type="hidden" name="lfi_pieces_action" value="del"><input type="hidden" name="idx" value="' . $i . '"><button type="submit" class="btn-ghost" style="font-size:.72em;padding:2px 6px">🗑</button></form>';
        echo '</div>';
        echo '</div></li>';
    }
    echo '</ul>';

    /* Ajouter une pièce + réinitialiser */
    echo '<form method="post" class="lfi-app-form" style="background:#f8f8f8;padding:10px;border-radius:8px;margin-top:8px">' . wp_nonce_field('lfi_pieces', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_pieces_action" value="add">';
    echo '<label style="margin:0">➕ Ajouter une pièce<input type="text" name="label" placeholder="Ex : facture de dépannage" style="margin-top:4px"></label>';
    echo '<label class="lfi-app-checkbox-row" style="margin-top:6px"><input type="checkbox" name="mandatory" value="1"> Obligatoire</label>';
    echo '<button type="submit" class="btn-ghost" style="margin-top:6px">Ajouter</button></form>';
    echo '<form method="post" style="margin-top:6px" onsubmit="return confirm(\'Réinitialiser la liste recommandée ? (les statuts reçus seront perdus)\')">' . wp_nonce_field('lfi_pieces', '_wpnonce', true, false) . '<input type="hidden" name="lfi_pieces_action" value="reset"><button type="submit" class="btn-ghost" style="font-size:.82em">↺ Réinitialiser la liste recommandée</button></form>';

    /* Déblocage conciliation */
    echo '<div style="margin-top:16px;padding:14px;border-radius:12px;background:' . ($prog['complete'] ? '#e8f5ea' : '#f4f4f4') . ';border:2px solid ' . ($prog['complete'] ? '#186a3b' : '#ccc') . '">';
    if ($prog['complete']) {
        echo '<div style="font-weight:900;color:#186a3b">✅ Toutes les pièces obligatoires sont reçues !</div>';
        echo '<div style="font-size:.9em;color:#555;margin-top:3px">On peut monter le dossier de conciliation. Si l\'amiable échoue, on passe à l\'avocat.</div>';
        echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">';
        echo '<a class="btn-primary" style="background:#6a1b9a;flex:1;text-align:center;min-width:160px" href="' . esc_url(lfi_nct_app_url('justice-cdc', ['uid' => $uid])) . '">⚖️ Créer le dossier conciliation</a>';
        if (function_exists('lfi_nct_avocat_list') && !empty(lfi_nct_avocat_list())) {
            echo '<a class="btn-ghost" style="flex:1;text-align:center;min-width:160px" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => $uid])) . '">⚖️ Confier à un avocat (si échec)</a>';
        }
        echo '</div>';
    } else {
        $rem = max(0, (int) $prog['mandatory'] - (int) $prog['received']);
        echo '<div style="font-weight:800;color:#888">🔒 Dossier conciliation verrouillé</div>';
        echo '<div style="font-size:.9em;color:#777;margin-top:3px">Encore <strong>' . $rem . ' pièce(s) obligatoire(s)</strong> à recevoir avant de pouvoir monter la conciliation.</div>';
    }
    echo '</div>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE : Commission de conciliation — monter la saisine          *
 * ============================================================== */
function lfi_nct_app_view_justice_cdc() {
    global $wpdb;
    $uid = (int) ($_GET['uid'] ?? 0);
    $is_admin = function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options');
    $is_avocat = function_exists('lfi_nct_user_role_avocat') && lfi_nct_user_role_avocat()
        && function_exists('lfi_nct_avocat_of_tenant') && lfi_nct_avocat_of_tenant($uid) === get_current_user_id();
    if (!$is_admin && !$is_avocat) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $u = $uid ? get_userdata($uid) : null;
    if (!$u || !in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    /* Versement de pièces depuis cette page (admin). */
    if ($is_admin && !empty($_POST['lfi_justice_piece']) && check_admin_referer('lfi_justice_piece')) {
        $n = lfi_nct_justice_handle_upload($uid);
        wp_safe_redirect(lfi_nct_app_url('justice-cdc', ['uid' => $uid, 'up' => (int) $n])); exit;
    }

    $d  = function_exists('lfi_nct_dossier_find_for_tenant') ? lfi_nct_dossier_find_for_tenant($uid) : null;
    $rid = (int) get_user_meta($uid, 'lfi_nct_response_id', true);
    $resp = $rid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid)) : null;
    $problem = ($resp && function_exists('lfi_nct_app_enq_problem')) ? lfi_nct_app_enq_problem($resp) : null;

    $name = trim(($d->tenant_prenom ?? '') . ' ' . ($d->tenant_nom ?? '')) ?: $u->display_name;
    $adresse = $d->tenant_adresse ?? ($resp->adresse ?? '');
    $etage = $d->tenant_etage ?? ($resp->etage ?? '');
    $logement = trim($adresse . ($etage ? ' · étage ' . $etage : ''));
    $desordres = ($problem && !empty($problem['chips'])) ? implode(', ', array_map(function ($c) { return $c[1]; }, $problem['chips'])) : '';

    lfi_nct_app_screen_open('⚖️ Commission de conciliation', 'Monter la saisine — ' . $name);
    echo '<div style="margin-bottom:10px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => $uid])) . '">← Retour au dossier</a></div>';
    if (!empty($_GET['up'])) lfi_nct_app_flash('📎 ' . (int) $_GET['up'] . ' pièce(s) versée(s).');

    /* Comment ça marche */
    echo '<div class="lfi-app-card" style="border-left:4px solid #6a1b9a">';
    echo '<div class="head"><div class="who">ℹ️ La Commission départementale de conciliation (CDC)</div></div>';
    echo '<div class="com" style="line-height:1.55">';
    echo '<p style="margin:6px 0"><strong>Gratuite</strong>, paritaire (autant de représentants des bailleurs que des locataires). Elle cherche un <strong>accord amiable</strong> avant le juge et rend un <strong>avis sous 2 mois</strong>.</p>';
    echo '<p style="margin:6px 0"><strong>Compétente</strong> pour : réparations / entretien, <strong>décence du logement</strong> (décret 2002-120), charges, dépôt de garantie, état des lieux. Pour un logement <strong>social</strong> (NMH), la saisine est <em>facultative</em> mais utile : un désaccord acté ouvre la voie au Tribunal Judiciaire.</p>';
    echo '<p style="margin:6px 0;color:#a30b25"><strong>À noter :</strong> la <strong>non-décence / insalubrité</strong> se signale <em>aussi</em> sur Histologe (elle ne relève pas directement de la CDC). Le locataire continue de payer loyer et charges pendant la procédure.</p>';
    echo '</div>';
    echo '<div class="row-actions" style="margin-top:6px;flex-wrap:wrap">';
    echo '<a class="btn-primary" style="background:#6a1b9a" href="https://demarche.numerique.gouv.fr/commencer/la-commission-departementale-de-conciliation-44" target="_blank" rel="noopener">🖥️ Saisir en ligne (Loire-Atlantique)</a>';
    echo '<a class="btn-ghost" href="https://histologe.beta.gouv.fr/" target="_blank" rel="noopener">🏚️ Signaler sur Histologe</a>';
    echo '<a class="btn-ghost" href="https://www.service-public.gouv.fr/particuliers/vosdroits/F1216" target="_blank" rel="noopener">📖 Fiche service-public</a>';
    echo '</div>';
    echo '<div class="lfi-app-help" style="margin-top:6px"><small>Secrétariat CDC 44 : <strong>02 72 20 63 04</strong> (lun–ven 9h30–12h, mer 14h–16h). Saisine possible aussi par lettre recommandée AR.</small></div>';
    echo '</div>';

    /* Pièces à joindre (checklist) */
    echo '<h3 style="margin:16px 0 6px;color:#0066a3">📋 Pièces à joindre à la saisine</h3>';
    $checklist = [
        'Copie du bail (contrat de location)',
        'État des lieux d\'entrée (et de sortie le cas échéant)',
        'Quittances de loyer récentes',
        'Tous les courriers / emails échangés avec NMH (mises en demeure, réponses)',
        'Photos datées des désordres',
        'Certificats médicaux liés au logement (si applicable)',
        'Constats / signalements SCHS, ARS, Histologe (si applicable)',
    ];
    echo '<ul style="padding:0 0 0 4px;list-style:none;line-height:1.7">';
    foreach ($checklist as $c) echo '<li>☐ ' . esc_html($c) . '</li>';
    echo '</ul>';

    /* Pièces versées + upload */
    lfi_nct_justice_pieces_box($u, $is_admin);

    /* Lettre de saisine pré-remplie */
    $moi = wp_get_current_user();
    $moi_nom = $moi->display_name ?: $moi->user_login;
    $today = function_exists('wp_date') ? wp_date('j F Y') : date('d/m/Y');
    $lettre  = "Objet : Saisine de la Commission départementale de conciliation — litige locatif\n\n";
    $lettre .= "Madame, Monsieur,\n\n";
    $lettre .= "Je soussigné·e " . $name . ", locataire du logement situé " . ($logement ?: '[adresse]') . ", ";
    $lettre .= "bailleur Nantes Métropole Habitat, saisis la Commission départementale de conciliation de Loire-Atlantique.\n\n";
    $lettre .= "Exposé du litige :\n";
    $lettre .= "Le logement présente des désordres persistants" . ($desordres ? " : " . $desordres . "." : ".") . " ";
    $lettre .= "Malgré mes démarches auprès du bailleur, la situation n'a pas été réglée, ce qui porte atteinte à la décence du logement et à ma jouissance paisible des lieux (art. 6 de la loi du 6 juillet 1989 ; art. 1719 et 1721 du Code civil ; décret n° 2002-120).\n\n";
    $lettre .= "Demandes :\n";
    $lettre .= "- la réalisation des travaux nécessaires pour rendre le logement décent ;\n";
    $lettre .= "- la réparation du préjudice subi (trouble de jouissance), dont le détail figure dans mon dossier ;\n";
    $lettre .= "- toute mesure utile que la Commission jugera appropriée.\n\n";
    $lettre .= "Vous trouverez ci-joint les pièces justificatives.\n\n";
    $lettre .= "Je reste à votre disposition pour être entendu·e.\n\n";
    $lettre .= "Fait le " . $today . ".\n" . $name . "\n";
    echo '<h3 style="margin:16px 0 6px;color:#186a3b">✍️ Lettre de saisine (pré-remplie — à relire)</h3>';
    echo '<div class="lfi-app-help"><small>Relis et complète les crochets [ ]. Le montant du préjudice reste dans le dossier — on annonce le préjudice sans livrer tout le détail. Copie ce texte ou colle-le dans la démarche en ligne.</small></div>';
    echo '<textarea readonly onclick="this.select()" style="width:100%;height:320px;margin-top:6px;font-size:.85em;padding:10px;border:1px solid #ccc;border-radius:8px;line-height:1.5">' . esc_textarea($lettre) . '</textarea>';

    /* Jurisprudence à l'appui */
    if (function_exists('lfi_nct_juris_can') && lfi_nct_juris_can()) {
        echo '<div style="margin-top:10px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('jurisprudence', ['dossier' => $d ? (int) $d->id : 0])) . '">🔎 Jurisprudence à l\'appui (Judilibre)</a></div>';
    }

    lfi_nct_app_screen_close();
}
