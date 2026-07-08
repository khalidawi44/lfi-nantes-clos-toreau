<?php
/**
 * JOURNAL D'ACTIVITÉ / CONNEXIONS — pour le super-admin.
 *
 * Enregistre qui utilise l'app, quand et d'où : connexions (wp_login) et
 * ouvertures de l'app (1 fois par jour et par utilisateur). Fournit un
 * rapport par groupe d'action : GA actifs / dormants, dernière connexion,
 * nombre de connexions, et la liste récente (qui · quand · où).
 *
 * RGPD : données internes réservées au super-admin, purge automatique
 * au-delà d'un an. L'IP sert seulement à repérer l'activité (pas de
 * profilage).
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_ACT_DBVER = 'lfi_nct_activity_db_ver';

add_action('init', 'lfi_nct_activity_db_setup', 6);
function lfi_nct_activity_db_setup() {
    if (get_option(LFI_NCT_ACT_DBVER) === '1') return;
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_activity';
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE $t (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        ga VARCHAR(60) DEFAULT '',
        event VARCHAR(20) DEFAULT 'app',
        ip VARCHAR(45) DEFAULT '',
        ua VARCHAR(255) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY ga (ga),
        KEY created_at (created_at)
    ) $charset;");
    update_option(LFI_NCT_ACT_DBVER, '1', false);
}

/** IP de l'appelant (derrière proxy/LiteSpeed si présent). */
function lfi_nct_activity_ip() {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', (string) $_SERVER[$k])[0]);
            if ($ip !== '') return substr($ip, 0, 45);
        }
    }
    return '';
}

/** Enregistre un événement d'activité. */
function lfi_nct_activity_log($event, $user_id = 0) {
    global $wpdb;
    $user_id = $user_id ?: get_current_user_id();
    if (!$user_id) return;
    $ga = function_exists('lfi_nct_user_ga') ? (string) lfi_nct_user_ga($user_id) : '';
    $wpdb->insert($wpdb->prefix . 'lfi_nct_activity', [
        'user_id'    => (int) $user_id,
        'ga'         => $ga,
        'event'      => substr((string) $event, 0, 20),
        'ip'         => lfi_nct_activity_ip(),
        'ua'         => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'created_at' => current_time('mysql'),
    ]);
    update_user_meta($user_id, 'lfi_nct_last_seen', current_time('mysql'));
}

/** Connexion WordPress → log. */
add_action('wp_login', 'lfi_nct_activity_on_login', 10, 2);
function lfi_nct_activity_on_login($login, $user) {
    if ($user instanceof WP_User) lfi_nct_activity_log('login', $user->ID);
}

/** Usage de l'app : une entrée par jour et par utilisateur (anti-spam). */
function lfi_nct_activity_track_app() {
    if (!is_user_logged_in()) return;
    $uid = get_current_user_id();
    $today = current_time('Y-m-d');
    if (get_user_meta($uid, 'lfi_nct_activity_day', true) === $today) return;
    update_user_meta($uid, 'lfi_nct_activity_day', $today);
    lfi_nct_activity_log('app', $uid);
}

/** Ville approximative d'une IP (cache 7 j, best-effort, sans clé). */
function lfi_nct_activity_geo($ip) {
    $ip = trim((string) $ip);
    if ($ip === '' || $ip === '127.0.0.1') return '';
    $key = 'lfi_geo_' . md5($ip);
    $c = get_transient($key);
    if ($c !== false) return $c;
    $city = '';
    $resp = wp_remote_get('http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,city,regionName,country&lang=fr', ['timeout' => 3]);
    if (!is_wp_error($resp)) {
        $b = json_decode(wp_remote_retrieve_body($resp), true);
        if (is_array($b) && ($b['status'] ?? '') === 'success') {
            $city = trim(($b['city'] ?? '') . (!empty($b['regionName']) ? ' (' . $b['regionName'] . ')' : ''));
        }
    }
    set_transient($key, $city, 7 * DAY_IN_SECONDS);
    return $city;
}

/* ============================================================== *
 *  VUE : Journal d'activité / connexions (super-admin)            *
 * ============================================================== */
function lfi_nct_app_view_activite() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_activity';

    /* Purge > 1 an. */
    $wpdb->query($wpdb->prepare("DELETE FROM $t WHERE created_at < %s", wp_date('Y-m-d H:i:s', strtotime('-1 year'))));

    $days = isset($_GET['j']) ? max(1, min(365, (int) $_GET['j'])) : 30;
    $since = wp_date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

    lfi_nct_app_screen_open('📡 Activité & connexions', 'Qui utilise l\'app, quand et où — ' . $days . ' derniers jours');

    /* ============ DÉTAIL PAR PERSONNE (ex. « tout sur Bompard ») ============ */
    $uq = isset($_GET['u']) ? (int) $_GET['u'] : 0;
    $namefind = isset($_GET['who']) ? sanitize_text_field(wp_unslash($_GET['who'])) : '';
    if (!$uq && $namefind !== '') {
        $found = get_users(['search' => '*' . $namefind . '*', 'search_columns' => ['display_name', 'user_login', 'user_email'], 'number' => 1, 'fields' => 'ID']);
        if ($found) $uq = (int) (is_object($found[0]) ? $found[0]->ID : $found[0]);
    }
    if ($uq) {
        $pu = get_userdata($uq);
        $pname = $pu ? ($pu->display_name ?: $pu->user_login) : ('#' . $uq);
        echo '<a href="' . esc_url(lfi_nct_app_url('activite')) . '" style="font-size:.85em;color:#0066a3">← Retour au journal</a>';
        echo '<h3 style="margin:8px 0 6px">👤 ' . esc_html($pname) . ' — tout ce qui est tracké</h3>';
        $arows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE user_id = %d ORDER BY created_at DESC LIMIT 500", $uq)) ?: [];
        $nlog = 0; $napp = 0; $ips = []; $uas = [];
        foreach ($arows as $r) { if ($r->event === 'login') $nlog++; else $napp++; if ($r->ip) $ips[$r->ip] = 1; if ($r->ua) $uas[$r->ua] = 1; }
        $first = $arows ? end($arows)->created_at : ''; $lastc = $arows ? $arows[0]->created_at : '';
        echo '<ul class="lfi-app-list"><li class="lfi-app-card"><div class="meta">';
        echo '<span class="meta-chip">🔑 ' . $nlog . ' connexion(s)</span><span class="meta-chip">📱 ' . $napp . ' jour(s) d\'usage</span>';
        echo '<span class="meta-chip">🕒 1re : ' . ($first ? esc_html(wp_date('j M Y H:i', strtotime($first))) : '—') . '</span>';
        echo '<span class="meta-chip">🕒 dernière : ' . ($lastc ? esc_html(wp_date('j M Y H:i', strtotime($lastc))) : '—') . '</span>';
        echo '<span class="meta-chip">🌐 ' . count($ips) . ' IP · ' . count($uas) . ' appareil(s)</span>';
        echo '</div></li></ul>';
        if (empty($arows)) {
            echo '<div class="lfi-app-empty">Aucune activité enregistrée pour cette personne (elle ne s\'est peut-être jamais connectée à l\'app, ou avant la mise en place du journal).</div>';
        } else {
            echo '<ul class="lfi-app-list">';
            foreach ($arows as $r) {
                $ville = lfi_nct_activity_geo($r->ip);
                echo '<li class="lfi-app-card" style="padding:8px 12px"><div class="head"><div class="who">' . ($r->event === 'login' ? '🔑 Connexion' : '📱 Usage de l\'app') . '</div><div class="when">' . esc_html(wp_date('j M Y · H:i', strtotime($r->created_at))) . '</div></div><div class="meta">';
                if ($ville) echo '<span class="meta-chip">📍 ' . esc_html($ville) . '</span>';
                if ($r->ip) echo '<span class="meta-chip" style="color:#888">' . esc_html($r->ip) . '</span>';
                if ($r->ua) echo '<span class="meta-chip" style="color:#888;max-width:100%;overflow:hidden;text-overflow:ellipsis">' . esc_html(mb_substr($r->ua, 0, 60)) . '</span>';
                echo '</div></li>';
            }
            echo '</ul>';
        }
        echo '<div class="lfi-app-help"><small>ℹ️ L\'app enregistre les <strong>connexions</strong> et l\'<strong>usage quotidien</strong> (date, heure, IP, ville, appareil). Elle ne trace <strong>pas</strong> le détail des pages visitées ni le temps passé — cette donnée n\'existe pas.</small></div>';
        lfi_nct_app_screen_close();
        return;
    }

    /* Recherche d'une personne (JS → URL de l'app, robuste quel que soit le routage). */
    $act_base = esc_js(lfi_nct_app_url('activite'));
    echo '<div style="display:flex;gap:6px;margin-bottom:8px"><input type="text" id="lfiActWho" placeholder="🔎 Tout sur une personne (nom)…" style="flex:1;padding:8px;border:1px solid #ccc;border-radius:8px" onkeydown="if(event.key===\'Enter\'){event.preventDefault();document.getElementById(\'lfiActGo\').click();}"><button type="button" id="lfiActGo" class="btn-primary" onclick="var v=document.getElementById(\'lfiActWho\').value.trim();if(v)location.href=\'' . $act_base . '\'+(\'' . $act_base . '\'.indexOf(\'?\')>=0?\'&\':\'?\')+\'who=\'+encodeURIComponent(v);">Voir</button></div>';

    /* Sélecteur de période. */
    echo '<div class="lfi-app-filter-chips">';
    foreach ([7 => '7 j', 30 => '30 j', 90 => '90 j', 365 => '1 an'] as $k => $lab) {
        $cls = $days === $k ? 'on' : '';
        echo '<a class="fc ' . esc_attr($cls) . '" href="' . esc_url(lfi_nct_app_url('activite', ['j' => $k])) . '">' . esc_html($lab) . '</a>';
    }
    echo '</div>';

    /* --- Synthèse par GA --- */
    $gas = [['slug' => 'clos-toreau', 'nom' => 'Clos Toreau']];
    if (function_exists('lfi_nct_groupes')) {
        foreach (lfi_nct_groupes(true) as $g) {
            if (in_array(($g['slug'] ?? ''), ['', 'clos-toreau'], true)) continue;
            $gas[] = ['slug' => $g['slug'], 'nom' => $g['nom'] ?? $g['slug']];
        }
    }
    $role = defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : 'lfi_nct_ga_member';

    echo '<h3 style="margin:14px 0 6px">Par groupe d\'action</h3>';
    echo '<ul class="lfi-app-list">';
    foreach ($gas as $g) {
        $slug = $g['slug'];
        $ga_clause = ($slug === 'clos-toreau') ? "(ga = '' OR ga = 'clos-toreau' OR ga IS NULL)" : $wpdb->prepare('ga = %s', $slug);
        $conns   = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE $ga_clause AND created_at >= '" . esc_sql($since) . "'");
        $actifs  = (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $t WHERE $ga_clause AND created_at >= '" . esc_sql($since) . "'");
        $last    = $wpdb->get_var("SELECT MAX(created_at) FROM $t WHERE $ga_clause");
        /* Nb de comptes membres du GA. */
        if ($slug === 'clos-toreau') {
            $membres = (int) count(get_users(['role' => $role, 'fields' => 'ID', 'meta_query' => [['relation' => 'OR', ['key' => 'lfi_nct_ga', 'compare' => 'NOT EXISTS'], ['key' => 'lfi_nct_ga', 'value' => ''], ['key' => 'lfi_nct_ga', 'value' => 'clos-toreau']]]]));
        } else {
            $membres = (int) count(get_users(['role' => $role, 'fields' => 'ID', 'meta_key' => 'lfi_nct_ga', 'meta_value' => $slug]));
        }
        $dormant = (!$last || strtotime($last) < strtotime('-' . max(14, $days) . ' days'));
        echo '<li class="lfi-app-card">';
        echo '<div class="head"><div class="who">' . esc_html($g['nom']) . '</div><div class="when">' . ($dormant ? '<span style="color:#c8102e">😴 dormant</span>' : '<span style="color:#186a3b">✅ actif</span>') . '</div></div>';
        echo '<div class="meta">';
        echo '<span class="meta-chip">👤 ' . $actifs . '/' . $membres . ' actif·s</span>';
        echo '<span class="meta-chip">🔁 ' . $conns . ' connexion·s</span>';
        echo '<span class="meta-chip">🕒 ' . ($last ? esc_html(wp_date('j M à H:i', strtotime($last))) : 'jamais') . '</span>';
        echo '</div></li>';
    }
    echo '</ul>';

    /* --- Journal récent : qui · quand · où --- */
    $rows = $wpdb->get_results("SELECT * FROM $t WHERE created_at >= '" . esc_sql($since) . "' ORDER BY created_at DESC LIMIT 120") ?: [];
    echo '<h3 style="margin:16px 0 6px">Dernières connexions</h3>';
    if (empty($rows)) {
        echo '<div class="lfi-app-empty">Aucune connexion sur la période.</div>';
        lfi_nct_app_screen_close();
        return;
    }
    echo '<ul class="lfi-app-list">';
    foreach ($rows as $r) {
        $u = get_userdata($r->user_id);
        $name = $u ? ($u->display_name ?: $u->user_login) : ('#' . $r->user_id);
        /* Un·e élu·e partenaire n'appartient à AUCUN GA : on l'étiquette « Élu·e »,
           jamais « Clos Toreau » (empêche de le/la ranger dans un groupe d'action). */
        $is_partner = $u && defined('LFI_NCT_ROLE_PARTNER') && in_array(LFI_NCT_ROLE_PARTNER, (array) $u->roles, true) && !in_array(defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : '__', (array) $u->roles, true);
        if ($is_partner) {
            $ga_nom = 'Élu·e (hors GA)';
        } else {
            $ga_nom = ($r->ga === '' || $r->ga === 'clos-toreau') ? 'Clos Toreau' : (function_exists('lfi_nct_ga_nom') ? lfi_nct_ga_nom($r->ga) : $r->ga);
        }
        $ville = lfi_nct_activity_geo($r->ip);
        echo '<li class="lfi-app-card" style="padding:9px 12px">';
        echo '<div class="head"><div class="who"><a href="' . esc_url(lfi_nct_app_url('activite', ['u' => (int) $r->user_id])) . '" style="color:#0b3d91;text-decoration:none">' . esc_html($name) . ' →</a></div><div class="when">' . esc_html(wp_date('j M · H:i', strtotime($r->created_at))) . '</div></div>';
        echo '<div class="meta">';
        echo '<span class="meta-chip">🏳️ ' . esc_html($ga_nom) . '</span>';
        echo '<span class="meta-chip">' . ($r->event === 'login' ? '🔑 connexion' : '📱 usage') . '</span>';
        if ($ville) echo '<span class="meta-chip">📍 ' . esc_html($ville) . '</span>';
        if ($r->ip) echo '<span class="meta-chip" style="color:#888">' . esc_html($r->ip) . '</span>';
        echo '</div></li>';
    }
    echo '</ul>';
    echo '<div class="lfi-app-help" style="margin-top:8px"><small>🔒 Données internes (réservées à toi). Purge automatique au-delà d\'un an. La localisation est approximative (basée sur l\'IP).</small></div>';

    lfi_nct_app_screen_close();
}
