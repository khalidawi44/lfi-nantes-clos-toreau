<?php
/**
 * LISTE NOIRE SMS — droit de s'opposer aux SMS (opt-out).
 *
 * Quand une personne dit explicitement « je ne veux plus recevoir de SMS », on
 * l'ajoute ici. Son numéro est alors EXCLU de tous les envois (envois de masse
 * aux répondant·es, aux locataires, aux membres) et les boutons « SMS » de son
 * dossier sont désactivés. Respect du RGPD (droit d'opposition, art. 21).
 *
 * Stockage : option lfi_nct_sms_blocklist = [ ['key','tel','name','by','date'] ].
 */
if (!defined('ABSPATH')) exit;

/** Normalise un numéro FR en clé canonique (+33XXXXXXXXX) pour comparer. */
function lfi_nct_tel_key($tel) {
    $t = preg_replace('/[^\d+]/', '', (string) $tel);
    if ($t === '') return '';
    if (strpos($t, '0033') === 0)                 $t = '+33' . substr($t, 4);
    if ($t !== '' && $t[0] !== '+' && strpos($t, '33') === 0 && strlen($t) === 11) $t = '+' . $t;
    if ($t !== '' && $t[0] === '0' && strlen($t) === 10) $t = '+33' . substr($t, 1);
    return $t;
}

function lfi_nct_sms_blocklist() {
    $l = get_option('lfi_nct_sms_blocklist', []);
    return is_array($l) ? $l : [];
}
function lfi_nct_sms_blocklist_save($l) {
    update_option('lfi_nct_sms_blocklist', array_values($l), false);
}

/** Ce numéro est-il en liste noire SMS ? */
function lfi_nct_sms_is_blocked($tel) {
    $k = lfi_nct_tel_key($tel);
    if ($k === '') return false;
    foreach (lfi_nct_sms_blocklist() as $e) if (($e['key'] ?? '') === $k) return true;
    return false;
}

/** Ajoute un numéro à la liste noire (idempotent). */
function lfi_nct_sms_block_add($tel, $name = '', $by = '') {
    $k = lfi_nct_tel_key($tel);
    if ($k === '') return false;
    $l = lfi_nct_sms_blocklist();
    foreach ($l as $e) if (($e['key'] ?? '') === $k) return true; /* déjà présent */
    $l[] = ['key' => $k, 'tel' => (string) $tel, 'name' => (string) $name, 'by' => (string) $by, 'date' => current_time('mysql')];
    lfi_nct_sms_blocklist_save($l);
    return true;
}

/* -------------------------------------------------------------- *
 *  Lien STOP par NUMÉRO (signé) : à mettre au bout des SMS pour    *
 *  que n'importe qui puisse se désinscrire depuis un SMS.         *
 * -------------------------------------------------------------- */
/** Jeton STOP signé encodant le numéro (récupérable côté handler). */
function lfi_nct_stop_token($tel) {
    $tel = preg_replace('/[^\d+]/', '', (string) $tel);
    if ($tel === '') return '';
    $sig = substr(hash_hmac('sha256', $tel, wp_salt('nonce')), 0, 12);
    return rtrim(strtr(base64_encode($tel), '+/', '-_'), '=') . '.' . $sig;
}
/** Décode un jeton STOP → numéro, ou '' si signature invalide. */
function lfi_nct_stop_token_decode($token) {
    $token = (string) $token;
    if (strpos($token, '.') === false) return '';
    list($b, $sig) = explode('.', $token, 2);
    $tel = base64_decode(strtr($b, '-_', '+/'));
    if ($tel === false || $tel === '') return '';
    if (!hash_equals(substr(hash_hmac('sha256', $tel, wp_salt('nonce')), 0, 12), (string) $sig)) return '';
    return $tel;
}
/** Lien court « ne plus me contacter » pour un numéro : …/stop/<jeton>. */
function lfi_nct_stop_link($tel) {
    $tk = lfi_nct_stop_token($tel);
    return $tk === '' ? '' : home_url('/stop/' . $tk);
}
/** Ligne à ajouter au bout d'un SMS (vide si pas de numéro). */
function lfi_nct_sms_stop_line($tel) {
    $l = lfi_nct_stop_link($tel);
    return $l === '' ? '' : "\nSTOP (ne plus me contacter) : " . $l;
}

/** Retire un numéro de la liste noire. */
function lfi_nct_sms_block_remove($tel) {
    $k = lfi_nct_tel_key($tel);
    $l = array_values(array_filter(lfi_nct_sms_blocklist(), function ($e) use ($k) {
        return ($e['key'] ?? '') !== $k;
    }));
    lfi_nct_sms_blocklist_save($l);
}

/* -------------------------------------------------------------- *
 *  SEED : Axel a demandé explicitement à ne plus recevoir de SMS.  *
 * -------------------------------------------------------------- */
add_action('init', 'lfi_nct_sms_blocklist_seed', 20);
function lfi_nct_sms_blocklist_seed() {
    if (get_option('lfi_nct_sms_blocklist_seed_axel')) return;
    lfi_nct_sms_block_add('+33760962978', 'Axel', 'demande explicite');
    update_option('lfi_nct_sms_blocklist_seed_axel', 1, false);
}

/* -------------------------------------------------------------- *
 *  Bouton réutilisable : (dé)bloquer un numéro depuis un dossier.  *
 * -------------------------------------------------------------- */
add_action('admin_post_lfi_nct_sms_block', 'lfi_nct_sms_block_handler');
function lfi_nct_sms_block_handler() {
    if (!is_user_logged_in() || !(function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'))) wp_die('non');
    $tel  = sanitize_text_field(wp_unslash($_GET['tel'] ?? ''));
    $name = sanitize_text_field(wp_unslash($_GET['name'] ?? ''));
    $act  = sanitize_key($_GET['act'] ?? 'add');
    if (!check_admin_referer('lfi_nct_sms_block')) wp_die('non');
    $me = wp_get_current_user();
    if ($act === 'remove') lfi_nct_sms_block_remove($tel);
    else                   lfi_nct_sms_block_add($tel, $name, $me->display_name ?: $me->user_login);
    $back = wp_get_referer() ?: lfi_nct_app_url('sms-blocklist');
    wp_safe_redirect($back);
    exit;
}

/** Lien prêt à l'emploi pour (dé)bloquer un numéro. */
function lfi_nct_sms_block_toggle_link($tel, $name = '') {
    $blocked = lfi_nct_sms_is_blocked($tel);
    $act = $blocked ? 'remove' : 'add';
    return wp_nonce_url(admin_url('admin-post.php?action=lfi_nct_sms_block&act=' . $act . '&tel=' . rawurlencode($tel) . '&name=' . rawurlencode($name)), 'lfi_nct_sms_block');
}

/* -------------------------------------------------------------- *
 *  VUE : gérer la liste noire SMS                                  *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_sms_blocklist() {
    if (!(function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'))) {
        wp_safe_redirect(lfi_nct_app_url()); exit;
    }
    if (!empty($_POST['lfi_sms_block_add']) && check_admin_referer('lfi_sms_block_add')) {
        $tel  = sanitize_text_field(wp_unslash($_POST['tel'] ?? ''));
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $me   = wp_get_current_user();
        if ($tel !== '') lfi_nct_sms_block_add($tel, $name, $me->display_name ?: $me->user_login);
        wp_safe_redirect(lfi_nct_app_url('sms-blocklist', ['added' => 1])); exit;
    }
    if (!empty($_POST['lfi_sms_block_del']) && check_admin_referer('lfi_sms_block_del')) {
        lfi_nct_sms_block_remove(sanitize_text_field(wp_unslash($_POST['tel'] ?? '')));
        wp_safe_redirect(lfi_nct_app_url('sms-blocklist', ['removed' => 1])); exit;
    }

    lfi_nct_app_screen_open('🚫 Liste noire SMS', 'Les personnes qui ne veulent plus de SMS');
    if (!empty($_GET['added']))   lfi_nct_app_flash('✅ Numéro ajouté à la liste noire — il ne recevra plus de SMS.');
    if (!empty($_GET['removed'])) lfi_nct_app_flash('Numéro retiré de la liste noire.');
    echo '<div class="lfi-app-help">Quand quelqu\'un dit explicitement « je ne veux plus de SMS », ajoute-le ici : son numéro est <strong>exclu de tous les envois</strong> et les boutons SMS de son dossier sont désactivés. C\'est son <strong>droit d\'opposition</strong> (RGPD).</div>';

    echo '<form method="post" class="lfi-app-form" style="background:#f8f8f8;padding:12px;border-radius:10px">';
    wp_nonce_field('lfi_sms_block_add');
    echo '<input type="hidden" name="lfi_sms_block_add" value="1">';
    echo '<label>Numéro de téléphone<input type="tel" name="tel" required placeholder="06 12 34 56 78 ou +33…"></label>';
    echo '<label>Nom (facultatif)<input type="text" name="name" placeholder="Ex : Axel"></label>';
    echo '<button type="submit" class="btn-primary" style="background:#c8102e">🚫 Ajouter à la liste noire</button>';
    echo '</form>';

    $list = lfi_nct_sms_blocklist();
    echo '<h3 style="margin:18px 0 8px">Liste noire (' . count($list) . ')</h3>';
    if (empty($list)) {
        echo '<div class="lfi-app-empty">Personne pour l\'instant.</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        /* plus récents d'abord */
        foreach (array_reverse($list) as $e) {
            echo '<li class="lfi-app-card" style="border-left:4px solid #c8102e">';
            echo '<div class="head"><div class="who">🚫 ' . esc_html(($e['name'] ?? '') !== '' ? $e['name'] : 'Sans nom') . '</div>';
            echo '<div class="badge">' . esc_html($e['tel'] ?? $e['key'] ?? '') . '</div></div>';
            $meta = [];
            if (!empty($e['by']))   $meta[] = 'ajouté par ' . $e['by'];
            if (!empty($e['date'])) $meta[] = wp_date('j M Y', strtotime($e['date']));
            if ($meta) echo '<div class="lfi-app-help" style="margin:2px 0 0"><small>' . esc_html(implode(' · ', $meta)) . '</small></div>';
            echo '<form method="post" style="margin-top:6px" onsubmit="return confirm(\'Réautoriser les SMS pour ce numéro ?\')">' . wp_nonce_field('lfi_sms_block_del', '_wpnonce', true, false);
            echo '<input type="hidden" name="lfi_sms_block_del" value="1"><input type="hidden" name="tel" value="' . esc_attr($e['tel'] ?? $e['key'] ?? '') . '">';
            echo '<button type="submit" class="btn-ghost" style="font-size:.82em">↩ Réautoriser les SMS</button></form>';
            echo '</li>';
        }
        echo '</ul>';
    }
    lfi_nct_app_screen_close();
}
