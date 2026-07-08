<?php
/**
 * SIGNALER UN PROBLÈME — retour des utilisateurs (bug / manque / dossier faux…).
 *
 * Un bouton PERSISTANT sur toutes les pages de l'app permet à n'importe quel
 * utilisateur (membre, locataire, admin) de signaler un souci : un bouton qui
 * ne marche pas, un dossier incomplet, quelque chose qui manque… Champ LIBRE
 * (ils expliquent avec leurs mots) + une petite catégorie pour aiguiller.
 * Les signalements arrivent à l'admin (bandeau discret + écran dédié).
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_feedback_cats() {
    return [
        'bouton'    => '🔘 Un bouton ne marche pas',
        'dossier'   => '🗂 Mon dossier / mes infos sont incomplets ou faux',
        'manque'    => '➕ Il manque quelque chose',
        'affichage' => '🖥 Un problème d\'affichage',
        'autre'     => '💬 Autre',
    ];
}

/* ---- Stockage (option) --------------------------------------- */
function lfi_nct_feedback_add($cat, $texte, $url = '') {
    $texte = trim(sanitize_textarea_field((string) $texte));
    if ($texte === '') return false;
    $cats = lfi_nct_feedback_cats();
    $cat = isset($cats[$cat]) ? $cat : 'autre';
    $uid = get_current_user_id();
    $u = $uid ? get_userdata($uid) : null;
    $role = 'visiteur';
    if ($u) {
        if (in_array('administrator', (array) $u->roles, true)) $role = 'admin';
        elseif (defined('LFI_NCT_ROLE_GA') && in_array(LFI_NCT_ROLE_GA, (array) $u->roles, true)) $role = 'membre GA';
        elseif (defined('LFI_NCT_ROLE_TENANT') && in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) $role = 'locataire';
        else $role = 'utilisateur';
    }
    $l = get_option('lfi_nct_bug_reports', []);
    if (!is_array($l)) $l = [];
    $l[] = [
        'id'    => (int) round(microtime(true) * 1000),
        'uid'   => $uid,
        'nom'   => $u ? ($u->display_name ?: $u->user_login) : 'Anonyme',
        'role'  => $role,
        'cat'   => $cat,
        'texte' => mb_substr($texte, 0, 3000),
        'url'   => esc_url_raw((string) $url),
        'ga'    => function_exists('lfi_nct_creation_ga') ? (string) lfi_nct_creation_ga() : '',
        'date'  => current_time('mysql'),
    ];
    if (count($l) > 500) $l = array_slice($l, -500);
    update_option('lfi_nct_bug_reports', $l, false);
    return true;
}
function lfi_nct_feedback_pending() {
    $l = get_option('lfi_nct_bug_reports', []);
    if (!is_array($l)) return [];
    return array_values(array_filter($l, function ($e) { return empty($e['done']); }));
}
function lfi_nct_feedback_done($id) {
    $l = get_option('lfi_nct_bug_reports', []);
    if (!is_array($l)) return;
    foreach ($l as $i => $e) if ((int) ($e['id'] ?? 0) === (int) $id) $l[$i]['done'] = 1;
    update_option('lfi_nct_bug_reports', $l, false);
}

/* ---- Bouton persistant (toutes les pages de l'app) ----------- */
function lfi_nct_app_render_feedback_button() {
    if (!is_user_logged_in()) return;
    $url = function_exists('lfi_nct_app_url') ? lfi_nct_app_url('signaler-bug') : '#';
    echo '<a href="' . esc_url($url) . '" title="Signaler un problème" '
       . 'style="position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:9997;'
       . 'background:#fff;color:#c8102e;border:2px solid #c8102e;border-radius:999px;'
       . 'padding:7px 14px;font-weight:800;font-size:.82em;text-decoration:none;'
       . 'box-shadow:0 3px 10px rgba(0,0,0,.18);font-family:-apple-system,Segoe UI,Roboto,sans-serif">'
       . '🐞 Signaler un souci</a>';
}

/* ---- Bandeau admin (nouveaux signalements) ------------------- */
function lfi_nct_feedback_admin_notice() {
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can) return;
    $n = count(lfi_nct_feedback_pending());
    if ($n < 1) return;
    echo '<a href="' . esc_url(lfi_nct_app_url('bug-reports')) . '" style="display:flex;align-items:center;gap:8px;margin:0 0 12px;padding:9px 13px;background:#fdeef0;border:1px solid #c8102e;border-radius:10px;text-decoration:none;color:#c8102e;font-weight:800">'
       . '<span style="font-size:1.1em">🐞</span><span>' . (int) $n . ' signalement' . ($n > 1 ? 's' : '') . ' d\'utilisateur à lire</span>'
       . '<span style="margin-left:auto;font-size:.85em;opacity:.8">Voir →</span></a>';
}

/* ============================================================== *
 *  VUE : formulaire de signalement (tout utilisateur connecté)   *
 * ============================================================== */
function lfi_nct_app_view_signaler_bug() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    if (!empty($_POST['lfi_fb_send']) && check_admin_referer('lfi_fb_send')) {
        lfi_nct_feedback_add($_POST['cat'] ?? 'autre', $_POST['texte'] ?? '', $_POST['ctx_url'] ?? '');
        wp_safe_redirect(lfi_nct_app_url('signaler-bug', ['ok' => 1]));
        exit;
    }
    lfi_nct_app_screen_open('🐞 Signaler un problème', 'Dis-nous ce qui a bugué ou ce qui manque — on corrige');
    if (!empty($_GET['ok'])) {
        lfi_nct_app_flash('✅ Merci ! C\'est bien envoyé. On regarde ça.');
        echo '<div class="lfi-app-card" style="border-left:4px solid #186a3b"><div class="com">Ton signalement est arrivé. Tu peux en envoyer un autre si besoin, ou revenir à l\'accueil.</div></div>';
    }
    echo '<div class="lfi-app-help">Un bouton qui ne marche pas ? Ton dossier incomplet ? Un truc qui manque ou qui s\'affiche mal ? Explique-le simplement — c\'est toi qui es le mieux placé pour le voir.</div>';
    $cats = lfi_nct_feedback_cats();
    echo '<form method="post" class="lfi-app-card" style="border-left:4px solid #c8102e">';
    echo wp_nonce_field('lfi_fb_send', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_fb_send" value="1">';
    echo '<input type="hidden" name="ctx_url" id="lfi-fb-ctx" value="">';
    echo '<div style="margin:6px 0"><label>De quoi s\'agit-il ?<br><select name="cat" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px">';
    foreach ($cats as $k => $lbl) echo '<option value="' . esc_attr($k) . '">' . esc_html($lbl) . '</option>';
    echo '</select></label></div>';
    echo '<div style="margin:6px 0"><label>Explique avec tes mots<br><textarea name="texte" rows="5" required placeholder="ex : le bouton « À envoyer » ne s\'ouvre pas ; ou : il manque ma dernière photo dans mon dossier…" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px"></textarea></label></div>';
    echo '<div style="margin-top:8px"><button type="submit" class="btn-primary" style="background:#c8102e">📨 Envoyer mon signalement</button></div>';
    echo '</form>';
    /* Contexte : la page d'où l'on vient (aide au diagnostic). */
    echo '<script>(function(){var f=document.getElementById("lfi-fb-ctx");if(f)f.value=document.referrer||"";})();</script>';
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE ADMIN : liste des signalements                            *
 * ============================================================== */
function lfi_nct_app_view_bug_reports() {
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    if (!empty($_POST['lfi_fb_done']) && check_admin_referer('lfi_fb_done')) {
        lfi_nct_feedback_done((int) $_POST['lfi_fb_done']);
        wp_safe_redirect(lfi_nct_app_url('bug-reports', ['ok' => 1]));
        exit;
    }
    lfi_nct_app_screen_open('🐞 Signalements des utilisateurs', 'Bugs & manques remontés par les gens');
    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Marqué comme traité.');
    $cats = lfi_nct_feedback_cats();
    $pending = lfi_nct_feedback_pending();
    if (empty($pending)) {
        echo '<div class="lfi-app-help">Aucun signalement en attente. Le bouton « 🐞 Signaler un souci » est présent sur toutes les pages pour tes utilisateurs.</div>';
        lfi_nct_app_screen_close();
        return;
    }
    echo '<ul class="lfi-app-list">';
    foreach (array_reverse($pending) as $e) {
        echo '<li class="lfi-app-card" style="border-left:4px solid #c8102e">';
        echo '<div class="head"><div class="who">' . esc_html($cats[$e['cat'] ?? 'autre'] ?? 'Signalement') . '</div>';
        echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html(wp_date('j M · H:i', strtotime($e['date'] ?? ''))) . '</div></div>';
        echo '<div class="com" style="white-space:pre-wrap">' . esc_html($e['texte'] ?? '') . '</div>';
        echo '<div class="com" style="font-size:.82em;color:#888">' . esc_html($e['nom'] ?? '') . ' · ' . esc_html($e['role'] ?? '') . ($e['url'] ? ' · <a href="' . esc_url($e['url']) . '">page concernée</a>' : '') . '</div>';
        echo '<form method="post" style="margin-top:6px">' . wp_nonce_field('lfi_fb_done', '_wpnonce', true, false)
           . '<input type="hidden" name="lfi_fb_done" value="' . (int) $e['id'] . '">'
           . '<button type="submit" class="btn-ghost" style="padding:6px 10px;font-size:.82em">✓ Traité</button></form>';
        echo '</li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}
