<?php
/**
 * SIGNALEMENT DE DÉGÂTS (locataire ⇄ membre/admin du GA).
 *
 *  - Le LOCATAIRE signale un nouveau dégât (pièce + description + photos), tout
 *    est HORODATÉ, ajouté à sa chronologie et à ses pièces.
 *  - Le MEMBRE/ADMIN qui suit ce locataire voit un bandeau clair « 🚨 Nouveau
 *    signalement de dégât de … » sur son accueil → 1 clic vers le dossier.
 *  - Le LOCATAIRE a un écran « 📋 Où en est mon dossier » : les étapes cochées
 *    (déjà faites) + les prochaines, avec les dates importantes. Simple et clair.
 */
if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------- *
 *  Stockage des signalements (option globale, cloisonné par GA).  *
 * -------------------------------------------------------------- */
function lfi_nct_degat_signals_get() {
    $v = get_option('lfi_nct_degat_signals', []);
    return is_array($v) ? $v : [];
}
function lfi_nct_degat_signals_save($list) {
    update_option('lfi_nct_degat_signals', array_slice(array_values($list), -400), false);
}
/** Ajoute un signalement horodaté. Renvoie l'id créé.
 *  $kind : 'degat' (nouveau dégât) ou 'contrib' (le locataire a versé une pièce
 *  / une info demandée sur une étape de son parcours). */
function lfi_nct_degat_signal_add($uid, $piece, $desc, $kind = 'degat') {
    $uid = (int) $uid; if (!$uid) return 0;
    $u = get_userdata($uid);
    $list = lfi_nct_degat_signals_get();
    $id = (int) (round(microtime(true) * 1000) % 1000000000);
    $list[] = [
        'id'    => $id,
        'uid'   => $uid,
        'name'  => $u ? ($u->display_name ?: $u->user_login) : ('#' . $uid),
        'ga'    => (string) get_user_meta($uid, 'lfi_nct_ga', true),
        'kind'  => ($kind === 'contrib' ? 'contrib' : 'degat'),
        'piece' => (string) $piece,
        'desc'  => (string) $desc,
        'ts'    => current_time('mysql'),
        'seen'  => [],   /* uids d'admins/membres l'ayant vu */
    ];
    lfi_nct_degat_signals_save($list);
    return $id;
}

/** Signalements NON vus par l'utilisateur courant (admin/membre), dans SON GA. */
function lfi_nct_degat_signals_unseen() {
    $me = get_current_user_id(); if (!$me) return [];
    $out = [];
    foreach (lfi_nct_degat_signals_get() as $s) {
        $uid = (int) ($s['uid'] ?? 0); if (!$uid) continue;
        if (function_exists('lfi_nct_uid_in_scope') && !lfi_nct_uid_in_scope($uid)) continue; /* cloisonnement */
        if (in_array($me, (array) ($s['seen'] ?? []), true)) continue;
        $out[] = $s;
    }
    /* plus récents d'abord */
    usort($out, function ($a, $b) { return strcmp((string) ($b['ts'] ?? ''), (string) ($a['ts'] ?? '')); });
    return $out;
}

/* Marquer un (ou tous) signalement(s) comme vu. */
add_action('admin_post_lfi_nct_degat_seen', 'lfi_nct_degat_seen_handler');
function lfi_nct_degat_seen_handler() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    check_admin_referer('lfi_nct_degat_seen');
    $me = get_current_user_id();
    $sid = isset($_GET['sid']) ? (int) $_GET['sid'] : 0; /* 0 = tous */
    $list = lfi_nct_degat_signals_get();
    foreach ($list as $i => $s) {
        if ($sid && (int) ($s['id'] ?? 0) !== $sid) continue;
        $seen = (array) ($s['seen'] ?? []);
        if (!in_array($me, $seen, true)) { $seen[] = $me; $list[$i]['seen'] = $seen; }
    }
    lfi_nct_degat_signals_save($list);
    $back = isset($_GET['to']) ? esc_url_raw(wp_unslash($_GET['to'])) : lfi_nct_app_url();
    wp_safe_redirect($back); exit;
}

/* -------------------------------------------------------------- *
 *  Bandeau ADMIN/MEMBRE : nouveaux signalements → vers le dossier *
 * -------------------------------------------------------------- */
function lfi_nct_render_degat_admin_notice() {
    if (!is_user_logged_in()) return;
    $can = current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga())
        || (function_exists('lfi_nct_user_role_ga') && lfi_nct_user_role_ga());
    if (!$can) return;
    $new = lfi_nct_degat_signals_unseen();
    if (empty($new)) return;
    $ap = admin_url('admin-post.php');
    $nb_deg = count(array_filter($new, function ($s) { return ($s['kind'] ?? 'degat') !== 'contrib'; }));
    $nb_con = count($new) - $nb_deg;
    $titre = [];
    if ($nb_deg) $titre[] = '🚨 ' . $nb_deg . ' signalement' . ($nb_deg > 1 ? 's' : '') . ' de dégât';
    if ($nb_con) $titre[] = '📎 ' . $nb_con . ' pièce' . ($nb_con > 1 ? 's' : '') . ' / info reçue' . ($nb_con > 1 ? 's' : '');
    echo '<div style="background:#fdeef0;border:2px solid #c8102e;border-radius:14px;padding:12px 14px;margin-bottom:12px">';
    echo '<div style="font-weight:900;color:#c8102e;margin-bottom:6px">' . esc_html(implode(' · ', $titre)) . '</div>';
    foreach (array_slice($new, 0, 6) as $s) {
        $uid = (int) $s['uid'];
        $is_con = (($s['kind'] ?? 'degat') === 'contrib');
        $emo = $is_con ? '📎' : '📍';
        $dossier = lfi_nct_app_url('dossier', ['uid' => $uid]) . '#parcours';
        $seen_url = wp_nonce_url($ap . '?action=lfi_nct_degat_seen&sid=' . (int) $s['id'] . '&to=' . rawurlencode($dossier), 'lfi_nct_degat_seen');
        echo '<a href="' . esc_url($seen_url) . '" style="display:block;text-decoration:none;color:inherit;background:#fff;border:1px solid #f0b6c1;border-radius:10px;padding:9px 11px;margin-bottom:6px">';
        echo '<div style="font-weight:800;color:#c8102e">' . $emo . ' ' . esc_html($s['name']) . ($s['piece'] !== '' ? ' — ' . esc_html($s['piece']) : '') . ' <span style="float:right;color:#0066a3">Voir →</span></div>';
        if ($s['desc'] !== '') echo '<div style="font-size:.9em;color:#444;margin-top:2px">' . esc_html(mb_substr($s['desc'], 0, 120)) . '</div>';
        echo '<div style="font-size:.78em;color:#888;margin-top:2px">🕒 ' . esc_html(wp_date('j M Y · H:i', strtotime($s['ts']))) . '</div>';
        echo '</a>';
    }
    $seen_all = wp_nonce_url($ap . '?action=lfi_nct_degat_seen&sid=0&to=' . rawurlencode(lfi_nct_app_url()), 'lfi_nct_degat_seen');
    echo '<a href="' . esc_url($seen_all) . '" style="font-size:.82em;color:#666">✓ Tout marquer vu</a>';
    echo '</div>';
}

/* -------------------------------------------------------------- *
 *  Écran LOCATAIRE : « 🚨 Signaler un dégât »                     *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_tenant_signaler_degat() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $user = wp_get_current_user(); $uid = (int) $user->ID;

    if (!empty($_POST['lfi_degat_signal']) && check_admin_referer('lfi_degat_signal')) {
        $piece = sanitize_text_field(wp_unslash($_POST['piece'] ?? ''));
        $desc  = sanitize_textarea_field(wp_unslash($_POST['desc'] ?? ''));
        $nb_photos = 0;
        /* Photos (plusieurs), horodatées, rangées comme pièces du locataire. */
        if (!empty($_FILES['photo']['name']) && is_array($_FILES['photo']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $cnt = count($_FILES['photo']['name']);
            for ($i = 0; $i < $cnt; $i++) {
                if (empty($_FILES['photo']['tmp_name'][$i]) || (int) $_FILES['photo']['error'][$i] !== 0) continue;
                if ((int) $_FILES['photo']['size'][$i] > 15 * 1024 * 1024) continue;
                $f = ['name' => $_FILES['photo']['name'][$i], 'type' => $_FILES['photo']['type'][$i], 'tmp_name' => $_FILES['photo']['tmp_name'][$i], 'error' => $_FILES['photo']['error'][$i], 'size' => $_FILES['photo']['size'][$i]];
                $mime = function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : $f['type'];
                if (strpos((string) $mime, 'image/') !== 0) continue;
                $up = wp_handle_upload($f, ['test_form' => false]);
                if (!empty($up['error'])) continue;
                $att = wp_insert_attachment(['post_mime_type' => $up['type'], 'post_title' => 'Dégât ' . ($piece ?: 'logement') . ' — ' . $user->display_name, 'post_content' => $desc, 'post_status' => 'private', 'post_author' => $uid], $up['file']);
                if (is_wp_error($att) || !$att) continue;
                update_post_meta($att, '_lfi_tenant_user_id', $uid);
                update_post_meta($att, '_lfi_tenant_piece', $piece);
                update_post_meta($att, '_lfi_piece_cat', 'photo');
                wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $up['file']));
                if (function_exists('lfi_nct_store_capture_ts')) lfi_nct_store_capture_ts($att, $up['file']);
                $nb_photos++;
            }
        }
        /* Chronologie horodatée. */
        if (function_exists('lfi_nct_chrono_add')) {
            $txt = '🚨 Dégât signalé par le locataire' . ($piece !== '' ? ' — ' . $piece : '') . ($desc !== '' ? ' : ' . mb_substr($desc, 0, 120) : '') . ($nb_photos ? ' (' . $nb_photos . ' photo' . ($nb_photos > 1 ? 's' : '') . ')' : '');
            lfi_nct_chrono_add($uid, wp_date('d/m/Y'), $txt, true);
        }
        /* Alerte au membre/admin. */
        lfi_nct_degat_signal_add($uid, $piece, $desc);
        wp_safe_redirect(lfi_nct_app_url('signaler-degat', ['ok' => 1])); exit;
    }

    lfi_nct_app_screen_open('🚨 Signaler un dégât', 'Un nouveau problème ? Dites-le, c\'est horodaté');
    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Signalement envoyé et horodaté. La personne qui suit votre dossier est prévenue.');
    echo '<div class="lfi-app-help">Un nouveau dégât (fuite, moisissure, panne, nuisibles…) ? Décrivez-le et ajoutez des photos. Tout est <strong>daté automatiquement</strong> et transmis à la personne du GA qui suit votre dossier.</div>';

    echo '<form method="post" enctype="multipart/form-data" class="lfi-app-form">' . wp_nonce_field('lfi_degat_signal', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_degat_signal" value="1">';
    echo '<label>📍 Quelle pièce / quel endroit ?<select name="piece">';
    foreach (['Cuisine', 'Salle de bain', 'WC', 'Chambre', 'Salon', 'Couloir', 'Entrée', 'Balcon', 'Cave', 'Parties communes', 'Cage d\'escalier', 'Ascenseur', 'Extérieur immeuble', 'Autre'] as $p) {
        echo '<option value="' . esc_attr($p) . '">' . esc_html($p) . '</option>';
    }
    echo '</select></label>';
    echo '<label>📝 Que se passe-t-il ?<textarea name="desc" rows="4" placeholder="Ex : nouvelle fuite sous l\'évier depuis ce matin, l\'eau coule en continu."></textarea></label>';
    echo '<label>📷 Photos (plusieurs possibles)<input type="file" name="photo[]" accept="image/*" multiple></label>';
    echo '<button type="submit" class="btn-primary big" style="background:#c8102e">🚨 Envoyer le signalement</button></form>';

    lfi_nct_app_screen_close();
}

/* -------------------------------------------------------------- *
 *  BESOINS d'une étape : ce que le gestionnaire attend du         *
 *  locataire pour pouvoir traiter l'étape (pièces / info).        *
 * -------------------------------------------------------------- */
function lfi_nct_suivi_besoin_types() {
    return [
        'photo'    => ['📷', 'Des photos',              'file'],
        'document' => ['📄', 'Un document (PDF ou scan)', 'file'],
        'date'     => ['📅', 'Une date',                'date'],
        'montant'  => ['💶', 'Un montant (en €)',       'number'],
        'info'     => ['✍️', 'Une information',          'text'],
    ];
}
/** Nombre de besoins encore EN ATTENTE (non fournis) sur une étape. */
function lfi_nct_suivi_besoins_pending($step) {
    if (empty($step['besoins']) || !is_array($step['besoins'])) return 0;
    $n = 0; foreach ($step['besoins'] as $b) if (empty($b['done'])) $n++;
    return $n;
}
/** Verse des fichiers (photos/PDF) comme pièces du locataire, rattachées à une
 *  étape ($skey). Renvoie le nombre de pièces réellement enregistrées. */
function lfi_nct_suivi_attach_files($files, $uid, $skey, $piece_label, $desc, $only_images) {
    if (empty($files) || empty($files['name']) || !is_array($files['name'])) return 0;
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $u = get_userdata($uid);
    $n = 0; $cnt = count($files['name']);
    for ($i = 0; $i < $cnt; $i++) {
        if (empty($files['tmp_name'][$i]) || (int) $files['error'][$i] !== 0) continue;
        if ((int) $files['size'][$i] > 15 * 1024 * 1024) continue;
        $f = ['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
        $mime = function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : $f['type'];
        $is_img = (strpos((string) $mime, 'image/') === 0);
        $is_pdf = (strpos((string) $mime, 'application/pdf') === 0);
        if ($only_images && !$is_img) continue;
        if (!$only_images && !$is_img && !$is_pdf) continue;
        $up = wp_handle_upload($f, ['test_form' => false]);
        if (!empty($up['error'])) continue;
        $att = wp_insert_attachment(['post_mime_type' => $up['type'], 'post_title' => $piece_label . ' — ' . ($u ? $u->display_name : ''), 'post_content' => (string) $desc, 'post_status' => 'private', 'post_author' => $uid], $up['file']);
        if (is_wp_error($att) || !$att) continue;
        update_post_meta($att, '_lfi_tenant_user_id', $uid);
        update_post_meta($att, '_lfi_step', $skey);
        update_post_meta($att, '_lfi_tenant_piece', $piece_label);
        $cat = function_exists('lfi_nct_piece_categorize') ? lfi_nct_piece_categorize($f['name'], (string) $up['type']) : ['cat' => 'photo'];
        update_post_meta($att, '_lfi_piece_cat', ($is_img ? 'photo' : ($cat['cat'] ?? 'document')));
        wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $up['file']));
        if (function_exists('lfi_nct_store_capture_ts')) lfi_nct_store_capture_ts($att, $up['file']);
        $n++;
    }
    return $n;
}

/* -------------------------------------------------------------- *
 *  Écran LOCATAIRE : « 📋 Où en est mon dossier » (étapes + dates) *
 *  Chaque étape est CLIQUABLE : le locataire l'ouvre et « donne   *
 *  les choses » (photos, document, date, montant, info) que le    *
 *  gestionnaire attend pour la traiter.                           *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_tenant_suivi() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $uid  = get_current_user_id();

    /* --- Suppression d'une pièce versée par le locataire (tout est supprimable). --- */
    if (!empty($_POST['lfi_suivi_piece_del']) && check_admin_referer('lfi_suivi_contrib')) {
        $att = (int) ($_POST['att_id'] ?? 0);
        if ($att && (int) get_post_meta($att, '_lfi_tenant_user_id', true) === $uid) wp_delete_attachment($att, true);
        $e = isset($_POST['etape']) ? ['etape' => (int) $_POST['etape']] : [];
        wp_safe_redirect(lfi_nct_app_url('mon-suivi', $e)); exit;
    }

    /* --- Contribution du locataire sur une étape. --- */
    if (!empty($_POST['lfi_suivi_contrib']) && check_admin_referer('lfi_suivi_contrib')) {
        $steps = get_user_meta($uid, 'lfi_nct_suivi_steps', true); if (!is_array($steps)) $steps = [];
        $idx = (int) ($_POST['step_idx'] ?? -1);
        if (isset($steps[$idx])) {
            $step = $steps[$idx];
            $stxt = (string) ($step['text'] ?? '');
            $skey = function_exists('lfi_nct_step_key') ? lfi_nct_step_key($stxt, $idx) : ('st' . $idx);
            $types = lfi_nct_suivi_besoin_types();
            $besoins = (isset($step['besoins']) && is_array($step['besoins'])) ? $step['besoins'] : [];
            $done_labels = [];
            foreach ($besoins as $bi => $b) {
                if (!empty($b['done'])) continue;
                $type = (string) ($b['type'] ?? 'info');
                $blabel = (string) ($b['label'] ?? ($types[$type][1] ?? 'Élément'));
                if ($type === 'photo' || $type === 'document') {
                    $n = lfi_nct_suivi_attach_files($_FILES['besoin_file_' . $bi] ?? null, $uid, $skey, $blabel, $stxt, $type === 'photo');
                    if ($n > 0) { $besoins[$bi]['done'] = true; $besoins[$bi]['ts'] = current_time('mysql'); $done_labels[] = $blabel . ' (' . $n . ')'; }
                } else {
                    $val = sanitize_text_field(wp_unslash($_POST['besoin_val_' . $bi] ?? ''));
                    if ($val !== '') {
                        $besoins[$bi]['done'] = true; $besoins[$bi]['value'] = $val; $besoins[$bi]['ts'] = current_time('mysql');
                        if (function_exists('lfi_nct_chrono_add')) lfi_nct_chrono_add($uid, wp_date('d/m/Y'), '📝 ' . $stxt . ' — ' . $blabel . ' : ' . $val, true);
                        $done_labels[] = $blabel . ' : ' . $val;
                    }
                }
            }
            $steps[$idx]['besoins'] = $besoins;
            /* Photos libres + note : toujours possible, même sans besoin déclaré. */
            $free_n = lfi_nct_suivi_attach_files($_FILES['photo'] ?? null, $uid, $skey, 'Pièce — ' . $stxt, $stxt, true);
            $note = sanitize_textarea_field(wp_unslash($_POST['note'] ?? ''));
            if ($note !== '' && function_exists('lfi_nct_chrono_add')) lfi_nct_chrono_add($uid, wp_date('d/m/Y'), '📝 ' . $stxt . ' — ' . $note, true);
            update_user_meta($uid, 'lfi_nct_suivi_steps', array_values($steps));
            /* Alerte au gestionnaire (canal « contribution »). */
            $bits = $done_labels;
            if ($free_n) $bits[] = $free_n . ' photo' . ($free_n > 1 ? 's' : '');
            if ($note !== '') $bits[] = mb_substr($note, 0, 80);
            lfi_nct_degat_signal_add($uid, $stxt, $bits ? implode(' · ', $bits) : 'Contribution du locataire', 'contrib');
        }
        wp_safe_redirect(lfi_nct_app_url('mon-suivi', ['contrib_ok' => 1, 'etape' => $idx])); exit;
    }

    $steps = get_user_meta($uid, 'lfi_nct_suivi_steps', true);
    if (!is_array($steps)) $steps = [];

    /* =========================================================== *
     *  DÉTAIL D'UNE ÉTAPE (?etape=N) : « je donne les choses ».    *
     * =========================================================== */
    $sel = isset($_GET['etape']) ? (int) $_GET['etape'] : -1;
    if ($sel >= 0 && isset($steps[$sel]) && empty($steps[$sel]['skipped'])) {
        $step = $steps[$sel];
        $stxt = (string) ($step['text'] ?? '');
        $ok   = !empty($step['done']);
        $skey = function_exists('lfi_nct_step_key') ? lfi_nct_step_key($stxt, $sel) : ('st' . $sel);
        $types = lfi_nct_suivi_besoin_types();
        $besoins = (isset($step['besoins']) && is_array($step['besoins'])) ? $step['besoins'] : [];
        $pending = lfi_nct_suivi_besoins_pending($step);
        $pieces = function_exists('lfi_nct_step_pieces') ? lfi_nct_step_pieces($uid, $skey) : [];

        lfi_nct_app_screen_open('📋 ' . $stxt, 'Ce que vous pouvez apporter pour cette étape');
        if (!empty($_GET['contrib_ok'])) lfi_nct_app_flash('✅ Merci ! Vos éléments sont enregistrés et horodatés. La personne qui suit votre dossier est prévenue.');
        echo '<div style="margin-bottom:10px"><a href="' . esc_url(lfi_nct_app_url('mon-suivi')) . '" style="color:#0b3d91;font-weight:700;text-decoration:none">← Retour à mon suivi</a></div>';

        if (!empty($step['echeance'])) echo '<div class="lfi-app-help" style="margin-bottom:8px">📅 Échéance : <strong>' . esc_html(wp_date('j M Y', strtotime($step['echeance']))) . '</strong></div>';

        if ($pending > 0) {
            echo '<div style="background:#fff3cd;border:2px solid #d39e00;border-radius:12px;padding:12px 14px;margin-bottom:12px">';
            echo '<div style="font-weight:900;color:#8a6d1f">⚠️ Il manque des pièces</div>';
            echo '<div style="font-size:.92em;color:#6b5410;margin-top:3px">Pour que la personne qui gère votre dossier puisse traiter cette étape, il lui manque ' . (int) $pending . ' élément' . ($pending > 1 ? 's' : '') . '. Ajoutez-le' . ($pending > 1 ? 's' : '') . ' ci-dessous 👇</div>';
            echo '</div>';
        } elseif ($ok) {
            echo '<div class="lfi-app-help" style="background:#e8f5ea;border-color:#186a3b;color:#186a3b;margin-bottom:12px"><strong>✅ Cette étape est faite.</strong> Vous pouvez tout de même ajouter une pièce ou une précision ci-dessous.</div>';
        } else {
            echo '<div class="lfi-app-help" style="margin-bottom:12px">Rien n\'est demandé pour l\'instant sur cette étape. Vous pouvez quand même ajouter une photo ou une information utile.</div>';
        }

        echo '<form method="post" enctype="multipart/form-data" class="lfi-app-form">' . wp_nonce_field('lfi_suivi_contrib', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_suivi_contrib" value="1"><input type="hidden" name="step_idx" value="' . (int) $sel . '">';

        /* Besoins déclarés par le gestionnaire → un champ ciblé par besoin. */
        foreach ($besoins as $bi => $b) {
            $type = (string) ($b['type'] ?? 'info');
            $tinfo = $types[$type] ?? $types['info'];
            $blabel = (string) ($b['label'] ?? $tinfo[1]);
            $bdone = !empty($b['done']);
            echo '<div style="border:1px solid ' . ($bdone ? '#a6d3a6' : '#e6c98a') . ';background:' . ($bdone ? '#f0f8f1' : '#fffdf6') . ';border-radius:10px;padding:11px 12px;margin-bottom:8px">';
            echo '<div style="font-weight:800;color:' . ($bdone ? '#186a3b' : '#8a6d1f') . '">' . $tinfo[0] . ' ' . esc_html($blabel) . ($bdone ? ' <span style="font-size:.8em">✓ fourni</span>' : '') . '</div>';
            if (!empty($b['value'])) echo '<div style="font-size:.85em;color:#555;margin-top:2px">Votre réponse : <strong>' . esc_html($b['value']) . '</strong></div>';
            if (!$bdone) {
                if ($type === 'photo') echo '<input type="file" name="besoin_file_' . (int) $bi . '[]" accept="image/*" multiple style="margin-top:6px">';
                elseif ($type === 'document') echo '<input type="file" name="besoin_file_' . (int) $bi . '[]" accept="image/*,application/pdf" multiple style="margin-top:6px">';
                elseif ($type === 'date') echo '<input type="date" name="besoin_val_' . (int) $bi . '" style="margin-top:6px">';
                elseif ($type === 'montant') echo '<input type="number" step="0.01" min="0" name="besoin_val_' . (int) $bi . '" placeholder="Ex : 350" style="margin-top:6px">';
                else echo '<textarea name="besoin_val_' . (int) $bi . '" rows="2" placeholder="Votre réponse…" style="margin-top:6px"></textarea>';
            }
            echo '</div>';
        }

        /* Toujours : photos libres + note. */
        echo '<label style="margin-top:6px">📷 Ajouter des photos (facultatif, plusieurs possibles)<input type="file" name="photo[]" accept="image/*" multiple></label>';
        echo '<label>✍️ Ajouter une précision (facultatif)<textarea name="note" rows="3" placeholder="Ex : la fuite a repris hier soir."></textarea></label>';
        echo '<button type="submit" class="btn-primary big">✅ Envoyer à mon gestionnaire</button></form>';

        /* Pièces déjà versées sur cette étape — supprimables par le locataire. */
        if ($pieces) {
            echo '<h3 style="margin:16px 0 6px">📎 Ce que vous avez déjà déposé (' . count($pieces) . ')</h3>';
            echo '<div style="display:flex;flex-wrap:wrap;gap:8px">';
            foreach ($pieces as $p) {
                $isimg = strpos((string) $p->post_mime_type, 'image/') === 0;
                $th = $isimg ? (wp_get_attachment_image_url($p->ID, 'thumbnail') ?: wp_get_attachment_url($p->ID)) : '';
                echo '<div style="text-align:center;width:84px">';
                echo '<a href="' . esc_url(wp_get_attachment_url($p->ID)) . '" target="_blank" rel="noopener">';
                echo $th ? '<img src="' . esc_url($th) . '" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #ccc">' : '<div style="width:80px;height:80px;border-radius:8px;border:1px solid #ccc;display:flex;align-items:center;justify-content:center;font-size:1.8em;background:#f4f4f4">📄</div>';
                echo '</a>';
                echo '<form method="post" onsubmit="return confirm(\'Supprimer cette pièce ?\')" style="margin:2px 0 0">' . wp_nonce_field('lfi_suivi_contrib', '_wpnonce', true, false) . '<input type="hidden" name="lfi_suivi_piece_del" value="1"><input type="hidden" name="etape" value="' . (int) $sel . '"><input type="hidden" name="att_id" value="' . (int) $p->ID . '"><button type="submit" class="btn-ghost" style="font-size:.68em;padding:1px 6px;color:#c8102e">🗑 Retirer</button></form>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '<div class="lfi-app-help" style="margin-top:12px"><small>🔒 Privé : visible seulement par vous et le GA qui vous accompagne.</small></div>';
        lfi_nct_app_screen_close(); return;
    }

    /* =========================================================== *
     *  LISTE DES ÉTAPES (chaque étape est cliquable).             *
     * =========================================================== */
    lfi_nct_app_screen_open('📋 Où en est mon dossier', 'Ce qu\'on a déjà fait et ce qui suit');
    if (!empty($_GET['contrib_ok'])) lfi_nct_app_flash('✅ Merci ! Vos éléments sont bien enregistrés.');
    echo '<div class="lfi-app-help">Voici, étape par étape, ce que le Groupe d\'Action fait pour vous. <strong>✓ vert = déjà fait</strong>. <strong>Touchez une étape</strong> pour y ajouter ce qu\'on vous demande (photos, dates, montants…).</div>';

    /* Indices RÉELS conservés (on saute seulement les étapes rendues inutiles). */
    $visibles = [];
    foreach ($steps as $idx => $s) { if (empty($s['skipped'])) $visibles[$idx] = $s; }
    if (empty($visibles)) {
        echo '<div class="lfi-app-empty">Votre suivi démarre. La personne du GA qui vous accompagne va préparer les étapes — revenez bientôt.</div>';
        lfi_nct_app_screen_close(); return;
    }
    $done = 0; foreach ($visibles as $s) if (!empty($s['done'])) $done++;
    $total = count($visibles);
    $pct = (int) round($done * 100 / max(1, $total));
    echo '<div class="lfi-app-card" style="border:2px solid #0b3d91;border-radius:14px;padding:14px">';
    echo '<div style="font-weight:900;color:#0b3d91">Avancement : ' . $done . ' / ' . $total . ' étapes</div>';
    echo '<div style="background:#eee;border-radius:10px;height:12px;margin:8px 0;overflow:hidden"><div style="width:' . $pct . '%;height:100%;background:#186a3b"></div></div>';
    echo '<div style="display:flex;flex-direction:column;gap:8px;margin-top:6px">';
    $n = 0; $next_shown = false;
    foreach ($visibles as $idx => $s) {
        $n++;
        $ok = !empty($s['done']);
        $txt = (string) ($s['text'] ?? '');
        $ech = trim((string) ($s['echeance'] ?? ''));
        $is_next = (!$ok && !$next_shown);
        if ($is_next) $next_shown = true;
        $pending = lfi_nct_suivi_besoins_pending($s);
        $bg = $ok ? '#eef7ee' : ($is_next ? '#fff7e6' : '#fafafa');
        $bd = $pending ? '#d39e00' : ($ok ? '#a6d3a6' : ($is_next ? '#e6c98a' : '#eee'));
        $url = lfi_nct_app_url('mon-suivi', ['etape' => (int) $idx]);
        echo '<a href="' . esc_url($url) . '" style="text-decoration:none;color:inherit;display:flex;align-items:flex-start;gap:12px;background:' . $bg . ';border:1px solid ' . $bd . ';border-radius:12px;padding:11px 13px">';
        echo '<div style="width:28px;height:28px;border-radius:50%;flex:0 0 auto;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;background:' . ($ok ? '#186a3b' : ($is_next ? '#d39e00' : '#bbb')) . '">' . ($ok ? '✓' : $n) . '</div>';
        echo '<div style="flex:1"><div style="font-weight:700;color:#222">' . esc_html($txt) . '</div>';
        if ($ech !== '') echo '<div style="font-size:.85em;color:#0066a3;margin-top:2px">📅 ' . esc_html($ech) . '</div>';
        if ($pending) echo '<div style="font-size:.82em;color:#c8102e;font-weight:800;margin-top:3px">⚠️ Il manque ' . (int) $pending . ' pièce' . ($pending > 1 ? 's' : '') . ' — touchez pour ajouter</div>';
        elseif ($is_next) echo '<div style="font-size:.82em;color:#d39e00;font-weight:700;margin-top:2px">⏳ Prochaine étape · touchez pour agir</div>';
        echo '</div>';
        echo '<div style="font-weight:800;color:' . ($ok ? '#186a3b' : '#999') . ';white-space:nowrap">' . ($ok ? 'Fait' : '›') . '</div>';
        echo '</a>';
    }
    echo '</div></div>';
    echo '<div class="lfi-app-help" style="margin-top:8px"><small>🔒 Ce suivi est privé, réservé à vous et au GA qui vous accompagne.</small></div>';
    lfi_nct_app_screen_close();
}
