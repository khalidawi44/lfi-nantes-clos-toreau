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
/** Ajoute un signalement horodaté. Renvoie l'id créé. */
function lfi_nct_degat_signal_add($uid, $piece, $desc) {
    $uid = (int) $uid; if (!$uid) return 0;
    $u = get_userdata($uid);
    $list = lfi_nct_degat_signals_get();
    $id = (int) (round(microtime(true) * 1000) % 1000000000);
    $list[] = [
        'id'    => $id,
        'uid'   => $uid,
        'name'  => $u ? ($u->display_name ?: $u->user_login) : ('#' . $uid),
        'ga'    => (string) get_user_meta($uid, 'lfi_nct_ga', true),
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
    echo '<div style="background:#fdeef0;border:2px solid #c8102e;border-radius:14px;padding:12px 14px;margin-bottom:12px">';
    echo '<div style="font-weight:900;color:#c8102e;margin-bottom:6px">🚨 ' . count($new) . ' nouveau' . (count($new) > 1 ? 'x' : '') . ' signalement' . (count($new) > 1 ? 's' : '') . ' de dégât</div>';
    foreach (array_slice($new, 0, 6) as $s) {
        $uid = (int) $s['uid'];
        $dossier = function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga() ? lfi_nct_app_url('dossier', ['uid' => $uid]) : lfi_nct_app_url('dossier', ['uid' => $uid]);
        $seen_url = wp_nonce_url($ap . '?action=lfi_nct_degat_seen&sid=' . (int) $s['id'] . '&to=' . rawurlencode($dossier), 'lfi_nct_degat_seen');
        echo '<a href="' . esc_url($seen_url) . '" style="display:block;text-decoration:none;color:inherit;background:#fff;border:1px solid #f0b6c1;border-radius:10px;padding:9px 11px;margin-bottom:6px">';
        echo '<div style="font-weight:800;color:#c8102e">📍 ' . esc_html($s['name']) . ($s['piece'] !== '' ? ' — ' . esc_html($s['piece']) : '') . ' <span style="float:right;color:#0066a3">Voir →</span></div>';
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
 *  Écran LOCATAIRE : « 📋 Où en est mon dossier » (étapes + dates) *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_tenant_suivi() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $uid = get_current_user_id();
    $steps = get_user_meta($uid, 'lfi_nct_suivi_steps', true);
    if (!is_array($steps)) $steps = [];

    lfi_nct_app_screen_open('📋 Où en est mon dossier', 'Ce qu\'on a déjà fait et ce qui suit');
    echo '<div class="lfi-app-help">Voici, étape par étape, ce que le Groupe d\'Action fait pour vous. <strong>✓ vert = déjà fait</strong> · les autres sont à venir. On avance ensemble.</div>';

    /* On ignore les étapes rendues inutiles. */
    $steps = array_values(array_filter($steps, function ($s) { return empty($s['skipped']); }));
    if (empty($steps)) {
        echo '<div class="lfi-app-empty">Votre suivi démarre. La personne du GA qui vous accompagne va préparer les étapes — revenez bientôt.</div>';
        lfi_nct_app_screen_close(); return;
    }
    $done = 0; foreach ($steps as $s) if (!empty($s['done'])) $done++;
    $total = count($steps);
    $pct = (int) round($done * 100 / max(1, $total));
    echo '<div class="lfi-app-card" style="border:2px solid #0b3d91;border-radius:14px;padding:14px">';
    echo '<div style="font-weight:900;color:#0b3d91">Avancement : ' . $done . ' / ' . $total . ' étapes</div>';
    echo '<div style="background:#eee;border-radius:10px;height:12px;margin:8px 0;overflow:hidden"><div style="width:' . $pct . '%;height:100%;background:#186a3b"></div></div>';
    echo '<div style="display:flex;flex-direction:column;gap:8px;margin-top:6px">';
    $n = 0; $next_shown = false;
    foreach ($steps as $s) {
        $n++;
        $ok = !empty($s['done']);
        $txt = (string) ($s['text'] ?? '');
        $ech = trim((string) ($s['echeance'] ?? ''));
        $is_next = (!$ok && !$next_shown);
        if ($is_next) $next_shown = true;
        $bg = $ok ? '#eef7ee' : ($is_next ? '#fff7e6' : '#fafafa');
        $bd = $ok ? '#a6d3a6' : ($is_next ? '#e6c98a' : '#eee');
        echo '<div style="display:flex;align-items:flex-start;gap:12px;background:' . $bg . ';border:1px solid ' . $bd . ';border-radius:12px;padding:11px 13px">';
        echo '<div style="width:28px;height:28px;border-radius:50%;flex:0 0 auto;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;background:' . ($ok ? '#186a3b' : ($is_next ? '#d39e00' : '#bbb')) . '">' . ($ok ? '✓' : $n) . '</div>';
        echo '<div style="flex:1"><div style="font-weight:700;color:#222">' . esc_html($txt) . '</div>';
        if ($ech !== '') echo '<div style="font-size:.85em;color:#0066a3;margin-top:2px">📅 ' . esc_html($ech) . '</div>';
        if ($is_next) echo '<div style="font-size:.82em;color:#d39e00;font-weight:700;margin-top:2px">⏳ Prochaine étape</div>';
        echo '</div>';
        echo '<div style="font-weight:800;color:' . ($ok ? '#186a3b' : '#999') . ';white-space:nowrap">' . ($ok ? 'Fait' : '') . '</div>';
        echo '</div>';
    }
    echo '</div></div>';
    echo '<div class="lfi-app-help" style="margin-top:8px"><small>🔒 Ce suivi est privé, réservé à vous et au GA qui vous accompagne.</small></div>';
    lfi_nct_app_screen_close();
}
