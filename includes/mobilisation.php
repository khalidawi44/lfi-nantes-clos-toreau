<?php
/**
 * MOBILISATION / SE COORDONNER — à la Action Populaire.
 *
 * Remplace le calendrier « dispos » abstrait (qui flottait dans le vide). Ici,
 * chaque action a une RAISON claire, prise dans une liste de suggestions :
 *   · un ÉVÉNEMENT de l'agenda  → « Tractage pour la kermesse du 14 juillet »,
 *     « Tractage pour la réunion du 27 novembre »… ;
 *   · une CAMPAGNE  → « Présidentielle 2027 », « Municipales »…
 *
 * Sur chaque action, on pose des CRÉNEAUX concrets (jour + moment + type +
 * point de RDV). On peut en proposer PLUSIEURS d'un coup (plusieurs jours /
 * plusieurs moments). Chacun clique « 🙋 Je participe » sur les créneaux qui
 * l'arrangent, ou « ➕ Je propose d'autres dates ». Pas d'option négative :
 * on reste dans le positif, on se coordonne, c'est tout.
 *
 * Tout membre connecté peut créer une action, un créneau, s'y inscrire. Les
 * admins peuvent supprimer n'importe quel créneau. Aucune donnée locataire.
 */
if (!defined('ABSPATH')) exit;

define('LFI_NCT_MOBI_DBVER', 'lfi_nct_mobi_dbver');

add_action('init', 'lfi_nct_mobi_db_setup', 6);
function lfi_nct_mobi_db_setup() {
    if (get_option(LFI_NCT_MOBI_DBVER) === '2') return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t = $wpdb->prefix . 'lfi_nct_mobilisation';
    dbDelta("CREATE TABLE $t (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id BIGINT(20) UNSIGNED DEFAULT 0,
        theme VARCHAR(80) DEFAULT '',
        ga VARCHAR(60) DEFAULT '',
        created_by BIGINT(20) UNSIGNED DEFAULT 0,
        date_creneau DATE DEFAULT NULL,
        creneau VARCHAR(20) DEFAULT 'aprem',
        type VARCHAR(30) DEFAULT 'tractage',
        lieu VARCHAR(200) DEFAULT '',
        note VARCHAR(255) DEFAULT '',
        participants TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY event_id (event_id),
        KEY theme (theme),
        KEY ga (ga),
        KEY date_creneau (date_creneau)
    ) $charset;");
    update_option(LFI_NCT_MOBI_DBVER, '2', false);
}

/* ---- Réutilise les types/créneaux/helpers de coordination.php ------ */
function lfi_nct_mobi_types()    { return function_exists('lfi_nct_coord_action_types') ? lfi_nct_coord_action_types() : ['tractage' => ['📄', 'Tractage']]; }
function lfi_nct_mobi_creneaux() { return function_exists('lfi_nct_coord_creneaux') ? lfi_nct_coord_creneaux() : ['aprem' => 'Après-midi']; }
function lfi_nct_mobi_uname($uid){ return function_exists('lfi_nct_coord_user_name') ? lfi_nct_coord_user_name($uid) : 'Membre'; }
function lfi_nct_mobi_can()      { return is_user_logged_in(); }
function lfi_nct_mobi_can_mod()  { return current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()); }
function lfi_nct_mobi_ga()       { return function_exists('lfi_nct_creation_ga') ? (string) lfi_nct_creation_ga() : ''; }
function lfi_nct_mobi_scope_clause($col = 'ga') {
    global $wpdb;
    $slug = function_exists('lfi_nct_scope_ga_slug') ? (string) lfi_nct_scope_ga_slug() : '';
    if ($slug === '' || $slug === 'clos-toreau') return " AND ($col = '' OR $col = 'clos-toreau')";
    return $wpdb->prepare(" AND $col = %s", $slug);
}

/** Registre des campagnes (thèmes non liés à un événement daté). */
function lfi_nct_mobi_theme_registry() {
    $presets = [
        'presidentielle-2027' => ['🇫🇷', 'Présidentielle 2027'],
        'campagne-locale'     => ['🚩', 'Campagne locale / municipales'],
        'logement-nmh'        => ['🏠', 'Bataille du logement (NMH)'],
    ];
    $custom = get_option('lfi_nct_mobi_themes', []);
    if (is_array($custom)) foreach ($custom as $slug => $m) {
        if (!isset($presets[$slug]) && is_array($m)) $presets[$slug] = [$m[0] ?? '🚩', $m[1] ?? $slug];
    }
    return $presets;
}
/** Crée (ou retrouve) une campagne à partir d'un libellé. Renvoie le slug. */
function lfi_nct_mobi_theme_add($label) {
    $label = sanitize_text_field((string) $label);
    if ($label === '') return '';
    $slug = sanitize_title($label);
    if ($slug === '') return '';
    $reg = lfi_nct_mobi_theme_registry();
    if (isset($reg[$slug])) return $slug;
    $custom = get_option('lfi_nct_mobi_themes', []);
    if (!is_array($custom)) $custom = [];
    $custom[$slug] = ['🚩', $label];
    update_option('lfi_nct_mobi_themes', $custom, false);
    return $slug;
}
function lfi_nct_mobi_theme_label($slug) {
    $reg = lfi_nct_mobi_theme_registry();
    return isset($reg[$slug]) ? $reg[$slug] : ['🚩', ucfirst(str_replace('-', ' ', $slug))];
}

/** Liste des participants d'un créneau (array d'uids). */
function lfi_nct_mobi_parts($row) {
    $l = json_decode((string) $row->participants, true);
    return is_array($l) ? array_values(array_unique(array_map('intval', $l))) : [];
}

/* -------------------------------------------------------------- *
 *  NOTIFICATION ADMIN : « une nouvelle proposition à examiner »   *
 *  Petit indicateur discret, non intrusif. Marqué vu à l'ouverture *
 *  de l'écran de coordination.                                     *
 * -------------------------------------------------------------- */

/** Nombre de créneaux plus récents que le dernier vu par l'admin (hors les siens). */
function lfi_nct_mobi_admin_new_count() {
    if (!lfi_nct_mobi_can_mod()) return 0;
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_mobilisation';
    $uid  = get_current_user_id();
    $seen = (int) get_user_meta($uid, 'lfi_nct_mobi_seen_id', true);
    $n = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $t WHERE id > %d AND created_by <> %d" . lfi_nct_mobi_scope_clause(),
        $seen, $uid));
    return $n;
}

/** Marque tous les créneaux actuels comme « vus » par l'admin courant. */
function lfi_nct_mobi_mark_seen() {
    if (!lfi_nct_mobi_can_mod()) return;
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_mobilisation';
    $max = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $t");
    update_user_meta(get_current_user_id(), 'lfi_nct_mobi_seen_id', $max);
}

/**
 * Bandeau discret (1 ligne) affiché sur le tableau de bord admin quand des
 * membres ont proposé de nouveaux créneaux. À examiner → écran coordination.
 */
function lfi_nct_mobi_admin_notice() {
    $n = lfi_nct_mobi_admin_new_count();
    if ($n < 1) return;
    $url = lfi_nct_app_url('mobilisation');
    echo '<a href="' . esc_url($url) . '" style="display:flex;align-items:center;gap:8px;margin:0 0 12px;padding:9px 13px;background:#eef7ee;border:1px solid #186a3b;border-radius:10px;text-decoration:none;color:#186a3b;font-weight:700">'
       . '<span style="font-size:1.1em">🔔</span><span>' . (int) $n . ' nouvelle' . ($n > 1 ? 's' : '') . ' proposition' . ($n > 1 ? 's' : '') . ' de créneau à examiner</span>'
       . '<span style="margin-left:auto;font-size:.85em;opacity:.8">Voir →</span></a>';
}

/** Téléphones des MEMBRES DU GA (comptes WP du groupe d'action), scopés. */
function lfi_nct_mobi_member_phones() {
    $ga = function_exists('lfi_nct_scope_ga_slug') ? (string) lfi_nct_scope_ga_slug() : '';
    $role = defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : '';
    if ($role === '') return [];
    $users = get_users(['role' => $role, 'number' => 500, 'fields' => ['ID']]);
    $phones = [];
    foreach ((array) $users as $u) {
        $uga = (string) get_user_meta($u->ID, 'lfi_nct_ga', true);
        if ($ga !== '' && $ga !== 'clos-toreau') { if ($uga !== $ga) continue; }
        else { if (!in_array($uga, ['', 'clos-toreau'], true)) continue; }
        $tel = trim((string) get_user_meta($u->ID, 'lfi_nct_tel', true));
        if ($tel !== '') $phones[] = $tel;
    }
    return array_values(array_unique($phones));
}

/** Construit un lien « sms: » (iPhone) : destinataires + corps pré-rempli. */
function lfi_nct_mobi_sms_link($body, $recipients = []) {
    $nums = [];
    foreach ((array) $recipients as $r) { $n = preg_replace('/[^\d+]/', '', (string) $r); if ($n !== '') $nums[] = $n; }
    $addr = implode(',', array_values(array_unique($nums)));
    /* iOS : sms:NUM1,NUM2&body=... ouvre Messages avec destinataires + texte. */
    return 'sms:' . $addr . '&body=' . rawurlencode($body);
}

/** Seuil de « je participe » à partir duquel une action est ACTÉE (défaut 4). */
function lfi_nct_mobi_vote_threshold() {
    $n = (int) get_option('lfi_nct_mobi_vote_threshold', 4);
    return $n > 0 ? $n : 4;
}

/** Suivi « email déjà envoyé » par créneau + phase (vote / invit) — anti-renvoi. */
function lfi_nct_mobi_email_sent($cid) {
    $o = get_option('lfi_nct_mobi_email_sent', []);
    return (is_array($o) && !empty($o[$cid]) && is_array($o[$cid])) ? $o[$cid] : [];
}
function lfi_nct_mobi_email_mark($cid, $phase) {
    $o = get_option('lfi_nct_mobi_email_sent', []);
    if (!is_array($o)) $o = [];
    $m = (isset($o[$cid]) && is_array($o[$cid])) ? $o[$cid] : [];
    $m[$phase] = current_time('mysql');
    $o[$cid] = $m;
    update_option('lfi_nct_mobi_email_sent', $o, false);
}

/** Emails des MEMBRES DU GA (comptes WP du groupe), scopés. */
function lfi_nct_mobi_member_emails() {
    $ga = function_exists('lfi_nct_scope_ga_slug') ? (string) lfi_nct_scope_ga_slug() : '';
    $role = defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : '';
    if ($role === '') return [];
    $users = get_users(['role' => $role, 'number' => 500]);
    $emails = [];
    foreach ((array) $users as $u) {
        $uga = (string) get_user_meta($u->ID, 'lfi_nct_ga', true);
        if ($ga !== '' && $ga !== 'clos-toreau') { if ($uga !== $ga) continue; }
        else { if (!in_array($uga, ['', 'clos-toreau'], true)) continue; }
        $e = sanitize_email((string) $u->user_email);
        if ($e !== '' && is_email($e)) $emails[] = $e;
    }
    return array_values(array_unique($emails));
}

/** Envoie l'action à tous les membres du GA par EMAIL (fiable, un clic).
 *  $phase = 'vote' (proposition) ou 'invit' (action actée). Renvoie le nb envoyé. */
function lfi_nct_mobi_notify_members($row, $phase, $back_args, $types, $creneaux) {
    $members = lfi_nct_mobi_ga_members_full();
    if (empty($members)) return 0;
    $tmeta  = $types[$row->type] ?? ['✨', 'Action'];
    $when   = $row->date_creneau ? ucfirst(wp_date('l j M', strtotime($row->date_creneau))) : '';
    $moment = $creneaux[$row->creneau] ?? '';
    $lieu   = trim((string) $row->lieu);
    $lien   = lfi_nct_app_url('mobilisation', $back_args);
    if ($phase === 'invit') {
        $subject = '🎟️ Action confirmée : ' . $tmeta[1] . ' — ' . $when;
        $intro   = "C'est confirmé, on y va ! On compte sur toi. 👉 Clique le bouton pour voir les détails et confirmer ta présence — c'est ton clic qui te compte parmi nous.";
        $cta     = '🙋 Je viens (clique ici)';
        $color   = '#186a3b';
    } else {
        $subject = '🗳 Ton avis : on fait cette action ? ' . $tmeta[1] . ' — ' . $when;
        $intro   = "Le Groupe d'Action propose une action. Es-tu partant·e ? 👉 Clique le bouton et appuie sur « 🙋 Je participe ». C'est TON CLIC qui compte (inutile de répondre à cet email) — à partir de 4 « oui », on lance !";
        $cta     = '🗳 Je participe (clique ici)';
        $color   = '#c8102e';
    }
    /* En-tête / corps communs ; le bouton (lien magique) est PROPRE à chaque membre. */
    $head = '<div style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;max-width:520px;margin:auto">'
          . '<div style="background:' . $color . ';color:#fff;padding:16px 20px;border-radius:12px 12px 0 0;font-weight:800;font-size:1.1em">' . esc_html($subject) . '</div>'
          . '<div style="border:1px solid #eee;border-top:0;border-radius:0 0 12px 12px;padding:18px 20px">'
          . '<p>' . esc_html($intro) . '</p>'
          . '<div style="background:#f7f7f7;border-radius:10px;padding:12px 14px;margin:10px 0">'
          . '<strong>' . esc_html($tmeta[0] . ' ' . $tmeta[1]) . '</strong><br>🗓 ' . esc_html($when . ' · ' . $moment)
          . ($lieu ? '<br>📍 ' . esc_html($lieu) : '') . '</div>';
    $foot = '<div style="margin-top:14px;font-size:.82em;color:#888">Ce lien te connecte directement (rien à taper). Tu reçois cet email en tant que membre du Groupe d\'Action LFI Nantes Sud – Clos Toreau.</div>'
          . '</div></div>';
    add_filter('wp_mail_content_type', 'lfi_nct_agenda_html_ct');
    add_filter('wp_mail_from_name', 'lfi_nct_agenda_from_name');
    $sent = 0;
    foreach ($members as $m) {
        $e = $m['email'] ?? '';
        if ($e === '' || !is_email($e)) continue;
        /* Lien magique PERSONNEL : connexion 1 clic + atterrissage sur le créneau. */
        $mlien = (function_exists('lfi_nct_login_link') && !empty($m['uid']))
               ? lfi_nct_login_link((int) $m['uid'], $lien) : $lien;
        $btn = '<div style="margin-top:14px"><a href="' . esc_url($mlien) . '" style="display:inline-block;background:' . $color . ';color:#fff;text-decoration:none;padding:11px 18px;border-radius:10px;font-weight:800">' . esc_html($cta) . '</a></div>';
        $body = $head . $btn . $foot;
        if (wp_mail($e, $subject, $body)) $sent++;
    }
    remove_filter('wp_mail_content_type', 'lfi_nct_agenda_html_ct');
    remove_filter('wp_mail_from_name', 'lfi_nct_agenda_from_name');
    return $sent;
}

/**
 * Section « À venir — se mobiliser » à afficher EN BAS de l'accueil (console).
 * Les gens voient tout de suite les événements à venir + peuvent participer /
 * proposer un créneau, sans avoir à chercher. Esprit : faciliter la tâche.
 */
function lfi_nct_render_home_mobilisation() {
    if (!is_user_logged_in() || !function_exists('lfi_nct_upcoming_events')) return;
    $events = lfi_nct_upcoming_events(8);
    if (empty($events)) return;
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_mobilisation';

    /* On ne garde QUE les actions du GA (pas les événements généraux/externes). */
    $ga = [];
    foreach ($events as $e) {
        $d = function_exists('lfi_nct_event_data') ? lfi_nct_event_data($e) : null;
        if ($d && lfi_nct_evt_is_ga_action((int) $d['id'])) $ga[] = $d;
    }
    if (empty($ga)) return;
    $total = count($ga);
    $ga = array_slice($ga, 0, 3); /* compact : on n'empile pas — 3 max, le reste via « voir tout ». */

    echo '<div class="lfi-app-section" style="margin-top:20px">';
    echo '<div class="lfi-app-section-title" style="font-size:1.05em">📅 À VENIR — SE MOBILISER</div>';
    /* Lignes COMPACTES (une par événement) : titre + date + un seul bouton. */
    echo '<div style="display:flex;flex-direction:column;gap:6px">';
    foreach ($ga as $d) {
        $eid = (int) $d['id'];
        $npart = 0;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT participants FROM $t WHERE event_id = %d", $eid)) ?: [];
        foreach ($rows as $r) { $l = json_decode((string) $r->participants, true); if (is_array($l)) $npart += count($l); }
        $url = lfi_nct_app_url('mobilisation', ['ev' => $eid]);
        $sub = trim(($d['date_fr'] ?: '') . ($d['lieu'] ? ' · ' . $d['lieu'] : ''));
        echo '<a href="' . esc_url($url) . '" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #eee;border-left:4px solid #c8102e;border-radius:10px;padding:9px 12px">';
        echo '<div style="flex:1;min-width:0"><div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">📣 ' . esc_html($d['titre']) . '</div>';
        if ($sub !== '') echo '<div style="font-size:.82em;color:#777">' . esc_html($sub) . ($npart ? ' · ' . $npart . ' inscrit·e·s' : '') . '</div>';
        echo '</div><div style="background:#186a3b;color:#fff;border-radius:16px;padding:5px 11px;font-size:.82em;font-weight:700;white-space:nowrap">🙋 Participer</div></a>';
    }
    echo '</div>';
    $reste = $total > 3 ? ' (' . $total . ')' : '';
    echo '<div style="text-align:center;margin-top:8px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('mobilisation')) . '">🤝 Toutes les actions' . esc_html($reste) . ' →</a></div>';
    echo '</div>';
}

/* ============================================================== *
 *  VOTE : pop-up « on attend votre décision » (membres)          *
 * ============================================================== */
/** Tous les créneaux EN VOTE (à venir) que l'utilisateur n'a PAS encore votés.
 *  IGNORE l'écartement du pop-up (croix) : sert au bandeau persistant d'accueil,
 *  pour qu'un vote ne soit jamais « perdu » parce qu'on a fermé la fenêtre. */
function lfi_nct_mobi_open_votes_for_user() {
    if (!is_user_logged_in()) return [];
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_mobilisation';
    $uid = get_current_user_id();
    $seuil = lfi_nct_mobi_vote_threshold();
    $today = wp_date('Y-m-d');
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $t WHERE (date_creneau >= %s OR date_creneau IS NULL)" . lfi_nct_mobi_scope_clause() . " ORDER BY date_creneau ASC LIMIT 60",
        $today)) ?: [];
    $out = [];
    foreach ($rows as $r) {
        $list = lfi_nct_mobi_parts($r);
        if (count($list) >= $seuil) continue;            /* déjà actée */
        if (in_array((int) $uid, $list, true)) continue; /* déjà voté oui */
        $out[] = $r;
    }
    return $out;
}

/** Bandeau PERSISTANT d'accueil : « tu as N vote(s) en attente ».
 *  Toujours visible tant qu'il reste un vote non tranché — même si le pop-up
 *  a été fermé d'un clic sur la croix. C'est le filet de sécurité du vote. */
function lfi_nct_render_home_vote_banner() {
    $open = lfi_nct_mobi_open_votes_for_user();
    if (empty($open)) return;
    $n = count($open);
    $first = $open[0];
    $types = lfi_nct_mobi_types(); $creneaux = lfi_nct_mobi_creneaux();
    $tmeta = $types[$first->type] ?? ['✨', 'Action'];
    $when  = $first->date_creneau ? ucfirst(wp_date('l j M', strtotime($first->date_creneau))) : '';
    $moment = $creneaux[$first->creneau] ?? '';
    /* Le clic mène au créneau (via l'événement / la campagne). */
    $ev = (int) $first->event_id;
    $url = $ev > 0 ? lfi_nct_app_url('mobilisation', ['ev' => $ev])
                   : lfi_nct_app_url('mobilisation', ['theme' => (string) $first->theme]);
    echo '<a href="' . esc_url($url) . '" style="text-decoration:none;color:inherit;display:block">';
    echo '<div style="margin:0 0 14px;background:linear-gradient(135deg,#c8102e,#e0455e);color:#fff;border-radius:14px;padding:14px 16px;box-shadow:0 4px 14px rgba(200,16,46,.25);display:flex;align-items:center;gap:12px">';
    echo '<div style="font-size:1.9em;line-height:1">🗳</div>';
    echo '<div style="flex:1">';
    echo '<div style="font-weight:900;font-size:1.05em">' . ($n > 1 ? ('Tu as ' . $n . ' votes en attente') : 'On attend ta décision !') . '</div>';
    echo '<div style="font-size:.9em;opacity:.95;margin-top:2px">' . esc_html($tmeta[0] . ' ' . $tmeta[1] . ($when ? ' · ' . $when : '') . ($moment ? ' · ' . $moment : '')) . '</div>';
    echo '<div style="font-size:.82em;opacity:.9;margin-top:3px">👉 Appuie pour voir et voter — ton clic compte.</div>';
    echo '</div>';
    echo '<div style="background:rgba(255,255,255,.22);border-radius:20px;padding:6px 12px;font-weight:800;font-size:.85em;white-space:nowrap">Voter →</div>';
    echo '</div></a>';
}

/** Le premier créneau EN VOTE (à venir) que l'utilisateur n'a ni voté ni écarté. */
function lfi_nct_mobi_pending_vote_for_user() {
    if (!is_user_logged_in()) return null;
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_mobilisation';
    $uid = get_current_user_id();
    $seuil = lfi_nct_mobi_vote_threshold();
    $today = wp_date('Y-m-d');
    $dismissed = array_map('intval', (array) get_user_meta($uid, 'lfi_nct_vote_dismissed', true));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $t WHERE (date_creneau >= %s OR date_creneau IS NULL)" . lfi_nct_mobi_scope_clause() . " ORDER BY date_creneau ASC LIMIT 60",
        $today)) ?: [];
    foreach ($rows as $r) {
        $list = lfi_nct_mobi_parts($r);
        if (count($list) >= $seuil) continue;          /* déjà actée */
        if (in_array((int) $uid, $list, true)) continue; /* déjà voté oui */
        if (in_array((int) $r->id, $dismissed, true)) continue; /* écarté */
        return $r;
    }
    return null;
}
/** HANDLER EN-APP (fiable) : « je participe » / « pas cette fois » depuis le
 *  pop-up de vote. Comme pour le mot de passe, on traite sur la PAGE DE L'APP
 *  (auth garantie) et non sur /wp-admin/admin-post.php (auth incertaine via
 *  lien magique) → c'est ce qui faisait que le vote n'était PAS compté.
 *  Priorité 1 = avant le rendu de la coquille (redirect propre). */
add_action('template_redirect', 'lfi_nct_mobi_vote_inapp', 1);
function lfi_nct_mobi_vote_inapp() {
    if (empty($_GET['lfi_vote'])) return;
    $app = function_exists('lfi_nct_app_url') ? lfi_nct_app_url() : home_url('/app/');
    if (!is_user_logged_in()) { wp_safe_redirect($app); exit; }
    $cid = isset($_GET['cid']) ? (int) $_GET['cid'] : 0;
    $act = sanitize_key(wp_unslash($_GET['lfi_vote']));
    $uid = get_current_user_id();
    $nonce = (string) ($_GET['_wpnonce'] ?? '');
    if ($cid && $act === 'participe' && wp_verify_nonce($nonce, 'lfi_nct_vote_' . $cid)) {
        global $wpdb; $t = $wpdb->prefix . 'lfi_nct_mobilisation';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $cid));
        if ($row) {
            $list = lfi_nct_mobi_parts($row);
            if (!in_array($uid, $list, true)) {
                $list[] = $uid;
                $wpdb->update($t, ['participants' => wp_json_encode(array_values(array_unique($list)))], ['id' => $cid]);
            }
        }
    } elseif ($cid && $act === 'skip' && wp_verify_nonce($nonce, 'lfi_nct_vote_skip_' . $cid)) {
        $d = array_map('intval', (array) get_user_meta($uid, 'lfi_nct_vote_dismissed', true));
        $d[] = $cid;
        update_user_meta($uid, 'lfi_nct_vote_dismissed', array_values(array_unique($d)));
    }
    wp_safe_redirect($app); exit;
}

/** Handler (legacy, conservé en secours) : « je participe » depuis le pop-up. */
add_action('admin_post_lfi_nct_vote', 'lfi_nct_mobi_vote_handler');
function lfi_nct_mobi_vote_handler() {
    $home = function_exists('lfi_nct_app_url') ? lfi_nct_app_url() : home_url('/app/');
    /* JAMAIS de page « lien expiré » : si non connecté ou nonce périmé, on
       renvoie simplement à l'accueil (pas de wp_die, pas de cul-de-sac). */
    if (!is_user_logged_in()) { wp_safe_redirect($home); exit; }
    $cid = isset($_GET['cid']) ? (int) $_GET['cid'] : 0;
    if ($cid && wp_verify_nonce((string) ($_GET['_wpnonce'] ?? ''), 'lfi_nct_vote_' . $cid)) {
        global $wpdb; $t = $wpdb->prefix . 'lfi_nct_mobilisation';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $cid));
        if ($row) {
            $list = lfi_nct_mobi_parts($row); $uid = get_current_user_id();
            if (!in_array($uid, $list, true)) { $list[] = $uid; $wpdb->update($t, ['participants' => wp_json_encode(array_values(array_unique($list)))], ['id' => $cid]); }
        }
    }
    wp_safe_redirect($home); exit;
}
/** Handler : « pas cette fois » (écarte le pop-up pour ce créneau). */
add_action('admin_post_lfi_nct_vote_skip', 'lfi_nct_mobi_vote_skip_handler');
function lfi_nct_mobi_vote_skip_handler() {
    $home = function_exists('lfi_nct_app_url') ? lfi_nct_app_url() : home_url('/app/');
    if (!is_user_logged_in()) { wp_safe_redirect($home); exit; }
    $cid = isset($_GET['cid']) ? (int) $_GET['cid'] : 0;
    if ($cid && wp_verify_nonce((string) ($_GET['_wpnonce'] ?? ''), 'lfi_nct_vote_skip_' . $cid)) {
        $uid = get_current_user_id();
        $d = array_map('intval', (array) get_user_meta($uid, 'lfi_nct_vote_dismissed', true));
        $d[] = $cid; update_user_meta($uid, 'lfi_nct_vote_dismissed', array_values(array_unique($d)));
    }
    wp_safe_redirect($home); exit;
}
/** Pop-up de vote — à appeler sur l'accueil (membre & admin). */
function lfi_nct_render_vote_popup() {
    /* MODE APERÇU : jamais ce pop-up — son nonce est créé pour la personne
       prévisualisée mais vérifié pour l'admin réel → « le lien a expiré » en
       boucle. (Même piège que la popup mot de passe.) */
    if (function_exists('lfi_nct_app_preview_uid_from_cookie') && lfi_nct_app_preview_uid_from_cookie()) return;
    /* JAMAIS par-dessus l'ONBOARDING (choisir le mot de passe) : ce pop-up a un
       z-index supérieur et intercepterait les taps → « Enregistrer » / « Plus
       tard » ne réagiraient pas. L'onboarding passe d'abord. */
    if (function_exists('lfi_nct_member_onb_needed') && lfi_nct_member_onb_needed()) return;
    if (function_exists('lfi_nct_onboarding_needed') && lfi_nct_onboarding_needed()) return;
    $r = lfi_nct_mobi_pending_vote_for_user();
    if (!$r) return;
    $types = lfi_nct_mobi_types(); $creneaux = lfi_nct_mobi_creneaux();
    $tmeta = $types[$r->type] ?? ['✨', 'Action'];
    $when  = $r->date_creneau ? ucfirst(wp_date('l j M', strtotime($r->date_creneau))) : '';
    $moment = $creneaux[$r->creneau] ?? '';
    $lieu  = trim((string) $r->lieu);
    $seuil = lfi_nct_mobi_vote_threshold();
    $nb    = count(lfi_nct_mobi_parts($r));
    /* On poste sur la PAGE DE L'APP (auth garantie) → le vote est bien compté. */
    $app = function_exists('lfi_nct_app_url') ? lfi_nct_app_url() : home_url('/app/');
    $yes = wp_nonce_url(add_query_arg(['lfi_vote' => 'participe', 'cid' => (int) $r->id], $app), 'lfi_nct_vote_' . (int) $r->id);
    $no  = wp_nonce_url(add_query_arg(['lfi_vote' => 'skip',      'cid' => (int) $r->id], $app), 'lfi_nct_vote_skip_' . (int) $r->id);
    ?>
    <div id="lfi-vote-ov" onclick="if(event.target===this)this.style.display='none'" style="position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:100002;display:flex;align-items:center;justify-content:center;padding:16px">
      <div style="position:relative;background:#fff;color:#1a1a1a;border-radius:18px;max-width:420px;width:100%;padding:22px 20px;box-shadow:0 16px 50px rgba(0,0,0,.35);font-family:-apple-system,'Segoe UI',Roboto,sans-serif;text-align:center">
        <button type="button" aria-label="Fermer" onclick="document.getElementById('lfi-vote-ov').style.display='none'" style="position:absolute;top:10px;right:10px;width:34px;height:34px;border:none;border-radius:50%;background:#f0f0f0;color:#555;font-size:1.1em;font-weight:800;cursor:pointer;line-height:1">✕</button>
        <div style="font-size:42px">🗳</div>
        <div style="font-weight:900;font-size:1.2em;color:#c8102e;margin-top:4px">On attend ta décision !</div>
        <div style="margin-top:10px;font-size:1.05em"><strong><?php echo esc_html($tmeta[0] . ' ' . $tmeta[1]); ?></strong><br><?php echo esc_html($when . ' · ' . $moment); ?><?php echo $lieu ? '<br>📍 ' . esc_html($lieu) : ''; ?></div>
        <div style="margin-top:6px;color:#666;font-size:.9em"><?php echo (int) $nb; ?>/<?php echo (int) $seuil; ?> — encore <?php echo max(0, $seuil - $nb); ?> pour lancer l'action</div>
        <div style="margin-top:16px;display:flex;gap:10px;justify-content:center">
          <a href="<?php echo esc_url($yes); ?>" style="background:#186a3b;color:#fff;text-decoration:none;font-weight:800;padding:12px 22px;border-radius:12px">🙋 Je participe</a>
          <a href="<?php echo esc_url($no); ?>" style="background:#eee;color:#555;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:12px">Pas cette fois</a>
        </div>
      </div>
    </div>
    <script>
    /* Nudge UNE fois par session pour ce créneau : ensuite on ne renvoie plus
       le pop-up en boucle (le bandeau persistant reste, lui, comme rappel). */
    (function(){ var k='lfiVote<?php echo (int) $r->id; ?>';
      try{ if(sessionStorage.getItem(k)){ var o=document.getElementById('lfi-vote-ov'); if(o) o.style.display='none'; }
           else { sessionStorage.setItem(k,'1'); } }catch(e){} })();
    </script>
    <?php
}

/* ---- Membres du GA (uid, nom, tel) + suivi « SMS envoyé » ---- */
function lfi_nct_mobi_ga_members_full() {
    $ga = function_exists('lfi_nct_scope_ga_slug') ? (string) lfi_nct_scope_ga_slug() : '';
    $role = defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : '';
    if ($role === '') return [];
    $users = get_users(['role' => $role, 'number' => 500, 'orderby' => 'display_name']);
    $out = [];
    foreach ((array) $users as $u) {
        $uga = (string) get_user_meta($u->ID, 'lfi_nct_ga', true);
        if ($ga !== '' && $ga !== 'clos-toreau') { if ($uga !== $ga) continue; }
        else { if (!in_array($uga, ['', 'clos-toreau'], true)) continue; }
        $out[] = ['uid' => (int) $u->ID, 'nom' => ($u->display_name ?: $u->user_login),
                  'email' => sanitize_email((string) $u->user_email),
                  'tel' => trim((string) get_user_meta($u->ID, 'lfi_nct_tel', true))];
    }
    return $out;
}
function lfi_nct_mobi_sms_sent_map($cid) {
    $o = get_option('lfi_nct_mobi_sms_sent', []);
    return (is_array($o) && !empty($o[$cid]) && is_array($o[$cid])) ? $o[$cid] : [];
}
function lfi_nct_mobi_sms_toggle($cid, $uid) {
    $o = get_option('lfi_nct_mobi_sms_sent', []);
    if (!is_array($o)) $o = [];
    $m = (isset($o[$cid]) && is_array($o[$cid])) ? $o[$cid] : [];
    $m[$uid] = empty($m[$uid]) ? 1 : 0;
    $o[$cid] = $m;
    update_option('lfi_nct_mobi_sms_sent', $o, false);
}

/** Catégorie d'un événement : 'ga' (action du GA), 'deputes' (événement des
 *  député·es LFI, ex. kermesse Kerbrat/Amiot), 'externe' (hors LFI, ex. une
 *  conférence). Source : meta _lfi_evt_cat ; rétro-compat via _lfi_evt_ga_action. */
function lfi_nct_evt_cat($eid) {
    $c = (string) get_post_meta((int) $eid, '_lfi_evt_cat', true);
    if (in_array($c, ['ga', 'deputes', 'externe'], true)) return $c;
    /* Rétro-compat : ancien drapeau oui/non. */
    return get_post_meta((int) $eid, '_lfi_evt_ga_action', true) === '0' ? 'externe' : 'ga';
}
function lfi_nct_evt_cat_set($eid, $cat) {
    $cat = in_array($cat, ['ga', 'deputes', 'externe'], true) ? $cat : 'ga';
    update_post_meta((int) $eid, '_lfi_evt_cat', $cat);
    update_post_meta((int) $eid, '_lfi_evt_ga_action', $cat === 'ga' ? '1' : '0'); /* garde l'ancien en phase */
}
/** Compat : « est-ce une action du GA ? » (pour l'accueil, la home_mobilisation). */
function lfi_nct_evt_is_ga_action($eid) {
    return lfi_nct_evt_cat($eid) === 'ga';
}
/** Labels des catégories (pour le sélecteur admin). */
function lfi_nct_evt_cat_labels() {
    return ['ga' => '📣 Action du GA', 'deputes' => '🇫🇷 Événement des député·es', 'externe' => '🌐 Événement externe'];
}

/* ============================================================== *
 *  ROUTEUR : hub, tableau, ou SMS membre-par-membre              *
 * ============================================================== */
function lfi_nct_app_view_mobilisation() {
    if (!lfi_nct_mobi_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    /* Création d'une campagne libre depuis le hub. */
    if (!empty($_POST['lfi_mobi_theme_add']) && check_admin_referer('lfi_mobi_theme_add')) {
        $slug = lfi_nct_mobi_theme_add($_POST['theme_label'] ?? '');
        if ($slug !== '') { wp_safe_redirect(lfi_nct_app_url('mobilisation', ['theme' => $slug])); exit; }
        wp_safe_redirect(lfi_nct_app_url('mobilisation')); exit;
    }

    /* Admin : classer un événement (action du GA / député·es / externe). */
    if (!empty($_POST['lfi_evt_cat']) && check_admin_referer('lfi_evt_cat') && lfi_nct_mobi_can_mod()) {
        $eid = (int) ($_POST['eid'] ?? 0);
        if ($eid) lfi_nct_evt_cat_set($eid, sanitize_key($_POST['lfi_evt_cat']));
        wp_safe_redirect(lfi_nct_app_url('mobilisation')); exit;
    }
    /* (Compat) ancien bouton bascule oui/non. */
    if (!empty($_POST['lfi_evt_toggle_ga']) && check_admin_referer('lfi_evt_toggle_ga') && lfi_nct_mobi_can_mod()) {
        $eid = (int) $_POST['lfi_evt_toggle_ga'];
        if ($eid) {
            $cur = lfi_nct_evt_is_ga_action($eid);
            lfi_nct_evt_cat_set($eid, $cur ? 'externe' : 'ga');
        }
        wp_safe_redirect(lfi_nct_app_url('mobilisation')); exit;
    }

    $ev    = isset($_GET['ev']) ? (int) $_GET['ev'] : 0;
    $theme = isset($_GET['theme']) ? sanitize_title($_GET['theme']) : '';
    $sms   = isset($_GET['sms']) ? (int) $_GET['sms'] : 0;
    if ($sms > 0)         { lfi_nct_app_view_mobilisation_sms($sms, $ev, $theme); return; }
    if ($ev > 0)          { lfi_nct_app_view_mobilisation_board(['event_id' => $ev]); return; }
    if ($theme !== '')    { lfi_nct_app_view_mobilisation_board(['theme' => $theme]); return; }
    lfi_nct_app_view_mobilisation_hub();
}

/* ============================================================== *
 *  VUE : SMS membre-par-membre (coche verte « envoyé »)          *
 * ============================================================== */
function lfi_nct_app_view_mobilisation_sms($cid, $ev = 0, $theme = '') {
    if (!lfi_nct_mobi_can_mod()) { wp_safe_redirect(lfi_nct_app_url('mobilisation')); exit; }
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_mobilisation';
    $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", (int) $cid));
    $back = $ev > 0 ? ['ev' => $ev] : ($theme !== '' ? ['theme' => $theme] : []);
    if (!$r) { wp_safe_redirect(lfi_nct_app_url('mobilisation', $back)); exit; }

    if (!empty($_POST['lfi_mobi_sms_toggle']) && check_admin_referer('lfi_mobi_sms_toggle')) {
        lfi_nct_mobi_sms_toggle((int) $cid, (int) $_POST['lfi_mobi_sms_toggle']);
        wp_safe_redirect(lfi_nct_app_url('mobilisation', $back + ['sms' => $cid]));
        exit;
    }

    $types = lfi_nct_mobi_types(); $creneaux = lfi_nct_mobi_creneaux();
    $tmeta = $types[$r->type] ?? ['✨', 'Action'];
    $when  = $r->date_creneau ? ucfirst(wp_date('l j M', strtotime($r->date_creneau))) : '';
    $lieu_txt = trim((string) $r->lieu) !== '' ? ' (RDV ' . $r->lieu . ')' : '';
    $lien = lfi_nct_app_url('mobilisation', $back);
    /* Modèle de SMS ; %LIEN% est remplacé par le lien magique PERSONNEL de chaque membre. */
    $body_tpl = 'LFI Clos Toreau 👋 On organise : ' . $tmeta[1] . ' ' . $when . ' ' . ($creneaux[$r->creneau] ?? '') . $lieu_txt . '. Tu es partant·e ?'
          . "\n👉 Clique le lien (tu es connecté·e direct), puis appuie sur « 🙋 Je participe ». C'est TON CLIC qui compte (pas la peine de répondre à ce SMS !) : %LIEN%";

    lfi_nct_app_screen_open('📲 SMS un par un', $tmeta[1] . ' — ' . $when);
    echo '<div style="text-align:center;margin:4px 0 10px"><a class="btn-ghost" href="' . esc_url($lien) . '">← Retour au créneau</a></div>';
    echo '<div class="lfi-app-help">Tape sur un membre → le SMS s\'ouvre pré-rempli. En revenant, marque-le <strong>✅ envoyé</strong>. Ça t\'évite d\'oublier qui tu as prévenu.</div>';

    $members = lfi_nct_mobi_ga_members_full();
    $sent = lfi_nct_mobi_sms_sent_map((int) $cid);
    $ndone = 0; foreach ($members as $m) if (!empty($sent[$m['uid']])) $ndone++;
    echo '<div class="lfi-app-card" style="border-left:4px solid #186a3b"><div class="com"><strong>' . $ndone . ' / ' . count($members) . '</strong> membres prévenus.</div></div>';

    echo '<ul class="lfi-app-list">';
    foreach ($members as $m) {
        $ok = !empty($sent[$m['uid']]);
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . ($ok ? '#186a3b' : '#ccc') . '">';
        echo '<div class="head"><div class="who">' . ($ok ? '✅ ' : '') . esc_html($m['nom']) . '</div></div>';
        echo '<div class="row-actions" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">';
        if ($m['tel'] !== '') {
            /* Lien magique PERSONNEL : ce membre se connecte d'un clic, sans identifiant. */
            $mlien = (function_exists('lfi_nct_login_link') && !empty($m['uid']))
                   ? lfi_nct_login_link((int) $m['uid'], $lien) : $lien;
            $body = str_replace('%LIEN%', $mlien, $body_tpl);
            if (function_exists('lfi_nct_sms_stop_line')) $body .= lfi_nct_sms_stop_line($m['tel']);
            echo '<a href="' . esc_attr(lfi_nct_mobi_sms_link($body, [$m['tel']])) . '" class="btn-primary" style="padding:7px 12px;font-size:.85em;background:#0066a3">📲 Envoyer le SMS</a>';
            echo '<form method="post" style="display:inline">' . wp_nonce_field('lfi_mobi_sms_toggle', '_wpnonce', true, false)
               . '<input type="hidden" name="lfi_mobi_sms_toggle" value="' . (int) $m['uid'] . '">'
               . '<button type="submit" class="btn-ghost" style="padding:7px 12px;font-size:.85em;color:' . ($ok ? '#c8102e' : '#186a3b') . '">' . ($ok ? '↩︎ Annuler' : '✅ Marquer envoyé') . '</button></form>';
        } else {
            echo '<span class="lfi-app-help" style="margin:0"><small>Pas de numéro enregistré pour ce membre.</small></span>';
        }
        echo '</div></li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  HUB : suggestions (agenda + campagnes) + créer une action     *
 * ============================================================== */
function lfi_nct_app_view_mobilisation_hub() {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_mobilisation';

    /* L'admin consulte : on marque les propositions comme vues. */
    lfi_nct_mobi_mark_seen();

    lfi_nct_app_screen_open('🤝 Se coordonner', 'Pourquoi on se mobilise ? Choisis une action — on cale les créneaux ensemble.');

    echo '<div class="lfi-app-help" style="margin-bottom:12px">Chaque action a une <strong>raison claire</strong> (un événement de l\'agenda, une campagne). Tu ouvres l\'action, tu vois les <strong>créneaux</strong> (jour + moment) et tu cliques <strong>« 🙋 Je participe »</strong> sur ceux qui t\'arrangent. Tu peux aussi <strong>proposer d\'autres dates</strong>.</div>';

    /* Agrège créneaux + participants + ACTÉES par clé (event:ID ou theme:slug). */
    $seuil = lfi_nct_mobi_vote_threshold();
    $agg = [];
    $all = $wpdb->get_results("SELECT event_id, theme, participants FROM $t WHERE 1=1" . lfi_nct_mobi_scope_clause()) ?: [];
    foreach ($all as $r) {
        $key = ((int) $r->event_id > 0) ? ('e' . (int) $r->event_id) : ('t' . $r->theme);
        if (!isset($agg[$key])) $agg[$key] = ['creneaux' => 0, 'parts' => 0, 'actees' => 0];
        $n = count(lfi_nct_mobi_parts($r));
        $agg[$key]['creneaux']++;
        $agg[$key]['parts'] += $n;
        if ($n >= $seuil) $agg[$key]['actees']++;
    }
    $can_mod = lfi_nct_mobi_can_mod();

    $events = function_exists('lfi_nct_upcoming_events') ? lfi_nct_upcoming_events(20) : [];
    $ga_evts = []; $dep_evts = []; $ext_evts = [];
    foreach ($events as $e) {
        $d = lfi_nct_event_data($e);
        if (!$d) continue;
        $eid = (int) $d['id'];
        $cat = lfi_nct_evt_cat($eid);
        $has_cr = (($agg['e' . $eid]['creneaux'] ?? 0) > 0);
        /* RÈGLE : dès qu'un événement porte un tractage (des créneaux), c'est une
           action du GA — même si l'événement lui-même est externe/des député·es
           (ex. tractage POUR la kermesse). Sinon on classe par catégorie. */
        if ($cat === 'ga' || $has_cr) $ga_evts[] = $d;
        elseif ($cat === 'deputes')   $dep_evts[] = $d;
        else                          $ext_evts[] = $d;
    }

    /* Résumé clair en une ligne : acté / en vote. */
    $tot_act = 0; $tot_vote = 0;
    foreach ($agg as $a) { $tot_act += $a['actees']; $tot_vote += max(0, $a['creneaux'] - $a['actees']); }
    echo '<div style="display:flex;gap:8px;margin:0 0 12px">';
    echo '<div style="flex:1;text-align:center;background:#eef7ee;border:1px solid #a6d3a6;border-radius:10px;padding:8px"><div style="font-weight:900;font-size:1.3em;color:#186a3b">' . (int) $tot_act . '</div><div style="font-size:.78em;color:#186a3b">✅ actée' . ($tot_act > 1 ? 's' : '') . '</div></div>';
    echo '<div style="flex:1;text-align:center;background:#fff7e6;border:1px solid #e6c65a;border-radius:10px;padding:8px"><div style="font-weight:900;font-size:1.3em;color:#b8860b">' . (int) $tot_vote . '</div><div style="font-size:.78em;color:#b8860b">🗳 en vote</div></div>';
    echo '</div>';

    if ($can_mod) {
        echo '<div class="lfi-app-help" style="background:#f0f4ff;border-left:4px solid #4b2e83;margin-bottom:10px"><small>💡 <strong>Quelqu\'un t\'a confirmé par SMS (comme Yves) ?</strong> Ouvre l\'action ci-dessous → onglet « ➕ Acter une présence » (en haut). Tu peux l\'ajouter même sans qu\'il ait cliqué.</small></div>';
    }

    /* ---- NOS ACTIONS DU GA (compact, avec statut clair + vote cliquable) ---- */
    echo '<h3 style="margin:14px 0 6px;color:#c8102e">📣 Nos actions du GA</h3>';
    if (empty($ga_evts)) {
        echo '<div class="lfi-app-help">Aucune action du GA à venir. ' . (($dep_evts || $ext_evts) ? 'Regarde les autres événements plus bas.' : 'Crée-en une ci-dessous.') . '</div>';
    } else {
        foreach ($ga_evts as $d) {
            $eid = (int) $d['id'];
            $a = $agg['e' . $eid] ?? ['creneaux' => 0, 'parts' => 0, 'actees' => 0];
            $camp = lfi_nct_evt_campagne($eid);
            $sous = trim(($d['date_fr'] ?: '') . ($d['lieu'] ? ' · ' . $d['lieu'] : ''));
            /* Si l'événement est en réalité des député·es/externe mais qu'on y
               tracte, on le dit clairement (« Tractage pour : … »). */
            $ico = (lfi_nct_evt_cat($eid) === 'ga') ? '📣' : '📣';
            $titre = (lfi_nct_evt_cat($eid) === 'ga') ? $d['titre'] : ('Tractage pour : ' . $d['titre']);
            lfi_nct_mobi_hub_row($ico, $titre, $sous, $a, lfi_nct_app_url('mobilisation', ['ev' => $eid]), $camp);
            if ($can_mod) echo lfi_nct_evt_cat_form($eid, lfi_nct_evt_cat($eid));
        }
    }

    /* ---- CAMPAGNES (compact, ménage : plus de grosses cartes vides) ---- */
    echo '<h3 style="margin:18px 0 6px;color:#c8102e">🚩 Nos campagnes</h3>';
    foreach (lfi_nct_mobi_theme_registry() as $slug => $m) {
        $a = $agg['t' . $slug] ?? ['creneaux' => 0, 'parts' => 0, 'actees' => 0];
        lfi_nct_mobi_hub_row($m[0], $m[1], 'Campagne de fond', $a, lfi_nct_app_url('mobilisation', ['theme' => $slug]), '');
    }

    /* ---- ÉVÉNEMENTS DES DÉPUTÉ·ES (LFI) — à relayer / y aller ---- */
    if (!empty($dep_evts)) {
        echo '<details class="lfi-app-card" style="border-left:4px solid #c8102e;margin-top:12px" open><summary style="cursor:pointer;font-weight:800;color:#c8102e">🇫🇷 Événements des député·es LFI (' . count($dep_evts) . ')</summary>';
        echo '<div class="lfi-app-help" style="margin:6px 0">Organisés par nos député·es (ex. Andy Kerbrat, Ségolène Amiot). On peut <strong>y aller / relayer</strong>, et si on veut, <strong>organiser un tractage</strong> dessus.</div>';
        echo lfi_nct_mobi_render_evt_list($dep_evts, $agg, $can_mod, '#c8102e', '🇫🇷');
        echo '</details>';
    }

    /* ---- ÉVÉNEMENTS EXTERNES (hors LFI) — replié ---- */
    if (!empty($ext_evts)) {
        echo '<details class="lfi-app-card" style="border-left:4px solid #0066a3;margin-top:12px"><summary style="cursor:pointer;font-weight:800;color:#0066a3">🌐 Événements externes (' . count($ext_evts) . ') — hors LFI, à relayer</summary>';
        echo '<div class="lfi-app-help" style="margin:6px 0">Événements qui ne sont pas de LFI (ex. une conférence ouverte à tou·te·s). Rangés à part, pas en priorité.</div>';
        echo lfi_nct_mobi_render_evt_list($ext_evts, $agg, $can_mod, '#0066a3', '🌐');
        echo '</details>';
    }

    /* --- Créer une campagne libre --- */
    echo '<details class="lfi-app-card" style="border-left:4px solid #4b2e83"><summary style="cursor:pointer;font-weight:800;color:#4b2e83">➕ Autre action / campagne</summary>';
    echo '<form method="post" style="margin-top:8px">';
    echo wp_nonce_field('lfi_mobi_theme_add', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_mobi_theme_add" value="1">';
    echo '<div style="margin:6px 0"><label>Nom de l\'action / campagne<br><input type="text" name="theme_label" required maxlength="80" placeholder="ex : Tractage marché de Vertou" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px"></label></div>';
    echo '<div class="lfi-app-help" style="margin:4px 0">Astuce : si c\'est lié à un événement (kermesse, réunion…), passe plutôt par la liste ci-dessus — c\'est déjà rattaché à la bonne date.</div>';
    echo '<div style="margin-top:8px"><button type="submit" class="btn-primary" style="background:#4b2e83">Créer et ouvrir</button></div>';
    echo '</form></details>';

    lfi_nct_app_screen_close();
}

/** Une carte du hub (titre, sous-titre, agrégat, url).
 *  $eid > 0 + $is_ga => affiche à l'admin le bouton « déplacer vers événements généraux ». */
function lfi_nct_mobi_hub_card($titre, $sous, $a, $url, $eid = 0, $is_ga = false) {
    $accent = ($a['creneaux'] > 0) ? '#186a3b' : '#0066a3';
    echo '<li class="lfi-app-card" style="border-left:4px solid ' . esc_attr($accent) . '">';
    echo '<div class="head"><div class="who">' . esc_html($titre) . '</div></div>';
    if ($sous !== '') echo '<div class="meta"><span class="meta-chip">' . esc_html($sous) . '</span></div>';
    if ($a['creneaux'] > 0) {
        echo '<div class="com" style="font-size:.9em"><strong>' . (int) $a['creneaux'] . '</strong> créneau(x) · <strong>' . (int) $a['parts'] . '</strong> participation(s)</div>';
    } else {
        echo '<div class="com" style="font-size:.9em;color:#888">Pas encore de créneau — <strong>lance le premier</strong>.</div>';
    }
    echo '<div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:center"><a class="btn-primary" style="background:' . esc_attr($accent) . '" href="' . esc_url($url) . '">🎟️ Organiser / participer</a>';
    if ($eid > 0) echo lfi_nct_evt_toggle_form((int) $eid, $is_ga);
    echo '</div>';
    echo '</li>';
}

/** Puce de statut CLAIRE : acté (vert) / en vote (orange) / à lancer (gris). */
function lfi_nct_mobi_status_chip($a) {
    $act = (int) ($a['actees'] ?? 0);
    $cre = (int) ($a['creneaux'] ?? 0);
    if ($act > 0)  return '<span style="background:#186a3b;color:#fff;font-weight:800;font-size:.72em;padding:3px 9px;border-radius:20px;white-space:nowrap">✅ ' . $act . ' ACTÉE' . ($act > 1 ? 'S' : '') . '</span>';
    if ($cre > 0)  return '<span style="background:#d39e00;color:#fff;font-weight:800;font-size:.72em;padding:3px 9px;border-radius:20px;white-space:nowrap">🗳 ' . $cre . ' EN VOTE</span>';
    return '<span style="background:#e8e8e8;color:#666;font-weight:700;font-size:.72em;padding:3px 9px;border-radius:20px;white-space:nowrap">○ À lancer</span>';
}

/** Ligne COMPACTE du hub : titre + date + puce de statut (+ chip campagne). */
function lfi_nct_mobi_hub_row($ico, $titre, $sous, $a, $url, $campagne = '') {
    $act = (int) ($a['actees'] ?? 0); $cre = (int) ($a['creneaux'] ?? 0);
    $accent = $act > 0 ? '#186a3b' : ($cre > 0 ? '#d39e00' : '#bbb');
    echo '<a href="' . esc_url($url) . '" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #eee;border-left:4px solid ' . $accent . ';border-radius:10px;padding:10px 12px;margin-bottom:6px">';
    echo '<div style="flex:1;min-width:0">';
    echo '<div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' . $ico . ' ' . esc_html($titre) . '</div>';
    if ($sous !== '') echo '<div style="font-size:.82em;color:#777;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' . esc_html($sous) . '</div>';
    echo '<div style="margin-top:4px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">' . lfi_nct_mobi_status_chip($a);
    if (($a['parts'] ?? 0) > 0) echo '<span style="font-size:.78em;color:#555">👥 ' . (int) $a['parts'] . '</span>';
    if ($campagne !== '') echo '<span style="background:#efe9fb;color:#4b2e83;font-size:.72em;font-weight:700;padding:2px 8px;border-radius:20px">🚩 ' . esc_html($campagne) . '</span>';
    echo '</div></div>';
    echo '<div style="color:#c8102e;font-weight:800;font-size:1.2em">›</div></a>';
}

/** Campagne rattachée à un événement (nom lisible), ou '' si aucune. */
function lfi_nct_evt_campagne($eid) {
    $slug = (string) get_post_meta((int) $eid, '_lfi_evt_campagne', true);
    if ($slug === '') return '';
    $reg = function_exists('lfi_nct_mobi_theme_registry') ? lfi_nct_mobi_theme_registry() : [];
    return isset($reg[$slug]) ? $reg[$slug][1] : '';
}

/** Sélecteur de catégorie (admin) : action du GA / député·es / externe. */
function lfi_nct_evt_cat_form($eid, $cur) {
    $out = '<form method="post" style="margin:4px 0 8px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">'
         . wp_nonce_field('lfi_evt_cat', '_wpnonce', true, false)
         . '<input type="hidden" name="eid" value="' . (int) $eid . '">'
         . '<span style="font-size:.78em;color:#888">Classer :</span>'
         . '<select name="lfi_evt_cat" onchange="this.form.submit()" style="padding:5px 8px;border:1px solid #ccc;border-radius:8px;font-size:.82em">';
    foreach (lfi_nct_evt_cat_labels() as $k => $lab) $out .= '<option value="' . esc_attr($k) . '"' . selected($k, $cur, false) . '>' . esc_html($lab) . '</option>';
    $out .= '</select></form>';
    return $out;
}

/** Liste compacte d'événements (député·es / externes) : voir/partager, tracter, classer. */
function lfi_nct_mobi_render_evt_list($evts, $agg, $can_mod, $accent, $ico) {
    ob_start();
    echo '<ul class="lfi-app-list">';
    foreach ($evts as $d) {
        $eid = (int) $d['id'];
        $a = $agg['e' . $eid] ?? ['creneaux' => 0, 'parts' => 0, 'actees' => 0];
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . esc_attr($accent) . '">';
        echo '<div class="head"><div class="who">' . $ico . ' ' . esc_html($d['titre']) . '</div></div>';
        echo '<div class="meta"><span class="meta-chip">' . esc_html(trim(ucfirst((string) $d['date_complete']) . ($d['lieu'] ? ' · 📍 ' . $d['lieu'] : ''))) . '</span>';
        if (($a['creneaux'] ?? 0) > 0) echo ' <span class="meta-chip">' . lfi_nct_mobi_status_chip($a) . '</span>';
        echo '</div>';
        echo '<div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">';
        if (!empty($d['url'])) echo '<a class="btn-ghost" href="' . esc_url($d['url']) . '" style="padding:6px 12px;font-size:.85em">👁 Voir / partager</a>';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('mobilisation', ['ev' => $eid])) . '" style="padding:6px 12px;font-size:.85em;color:#186a3b">📣 Organiser un tractage</a>';
        echo '</div>';
        if ($can_mod) echo lfi_nct_evt_cat_form($eid, lfi_nct_evt_cat($eid));
        echo '</li>';
    }
    echo '</ul>';
    return ob_get_clean();
}

/* ============================================================== *
 *  TABLEAU d'une action : créneaux + « Je participe »            *
 *  $ctx = ['event_id'=>int] ou ['theme'=>slug]                   *
 * ============================================================== */
function lfi_nct_app_view_mobilisation_board($ctx) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_mobilisation';
    $uid = get_current_user_id();
    $types = lfi_nct_mobi_types();
    $creneaux = lfi_nct_mobi_creneaux();

    $event_id = isset($ctx['event_id']) ? (int) $ctx['event_id'] : 0;
    $theme    = isset($ctx['theme']) ? sanitize_title($ctx['theme']) : '';

    /* Détermine le titre + le rappel de contexte. */
    $d = null;
    if ($event_id > 0) {
        $d = function_exists('lfi_nct_event_data') ? lfi_nct_event_data($event_id) : null;
        if (!$d) { wp_safe_redirect(lfi_nct_app_url('mobilisation')); exit; }
        $titre = $d['titre'];
    } else {
        $tl = lfi_nct_mobi_theme_label($theme);
        $titre = $tl[0] . ' ' . $tl[1];
    }
    /* Clé de rattachement pour l'URL de retour. */
    $back_args = $event_id > 0 ? ['ev' => $event_id] : ['theme' => $theme];

    /* --- Ajout d'un ou plusieurs créneaux --- */
    if (!empty($_POST['lfi_mobi_add']) && check_admin_referer('lfi_mobi_add')) {
        $dates = (array) ($_POST['d'] ?? []);
        $crs   = (array) ($_POST['c'] ?? []);
        $ty = sanitize_key($_POST['type'] ?? 'tractage');
        if (!isset($types[$ty])) $ty = 'tractage';
        $lieu = sanitize_text_field(wp_unslash($_POST['lieu'] ?? ''));
        $note = sanitize_text_field(wp_unslash($_POST['note'] ?? ''));
        $n = max(count($dates), count($crs));
        for ($i = 0; $i < $n; $i++) {
            $date = sanitize_text_field(wp_unslash($dates[$i] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
            $cr = sanitize_key($crs[$i] ?? 'aprem');
            if (!isset($creneaux[$cr])) $cr = 'aprem';
            $wpdb->insert($t, [
                'event_id'     => $event_id,
                'theme'        => $theme,
                'ga'           => lfi_nct_mobi_ga(),
                'created_by'   => $uid,
                'date_creneau' => $date,
                'creneau'      => $cr,
                'type'         => $ty,
                'lieu'         => $lieu,
                'note'         => $note,
                'participants' => wp_json_encode([$uid]), /* créateur inscrit d'office */
            ]);
        }
        /* Apprend le point de RDV pour le GA (prochaine fois : dans la liste). */
        if ($lieu !== '' && function_exists('lfi_nct_rdv_learn')) lfi_nct_rdv_learn($lieu, lfi_nct_mobi_ga());
        wp_safe_redirect(lfi_nct_app_url('mobilisation', $back_args + ['ok' => 1]));
        exit;
    }
    /* --- « Je participe » / « Je me retire » (toggle) --- */
    if (!empty($_POST['lfi_mobi_join']) && check_admin_referer('lfi_mobi_join')) {
        $cid = (int) $_POST['lfi_mobi_join'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $cid));
        if ($row) {
            $list = lfi_nct_mobi_parts($row);
            if (in_array($uid, $list, true)) $list = array_values(array_diff($list, [$uid]));
            else $list[] = $uid;
            $wpdb->update($t, ['participants' => wp_json_encode(array_values(array_unique($list)))], ['id' => $cid]);
        }
        wp_safe_redirect(lfi_nct_app_url('mobilisation', $back_args));
        exit;
    }
    /* --- Admin : ajouter manuellement un·e participant·e (confirmé hors app,
           ex. par SMS) — permet d'acter la présence d'un membre depuis la gestion. */
    if (!empty($_POST['lfi_mobi_addpart']) && check_admin_referer('lfi_mobi_addpart') && lfi_nct_mobi_can_mod()) {
        $cid  = (int) $_POST['lfi_mobi_addpart'];
        $muid = (int) ($_POST['member_uid'] ?? 0);
        if ($cid && $muid) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $cid));
            if ($row) {
                $list = lfi_nct_mobi_parts($row);
                if (!in_array($muid, $list, true)) {
                    $list[] = $muid;
                    $wpdb->update($t, ['participants' => wp_json_encode(array_values(array_unique($list)))], ['id' => $cid]);
                }
            }
        }
        wp_safe_redirect(lfi_nct_app_url('mobilisation', $back_args + ['added' => 1]));
        exit;
    }
    /* --- Acter une présence : choisir la personne ET le créneau (box du haut) --- */
    if (!empty($_POST['lfi_mobi_addpart_pick']) && check_admin_referer('lfi_mobi_addpart_pick') && lfi_nct_mobi_can_mod()) {
        $cid  = (int) ($_POST['creneau_id'] ?? 0);
        $muid = (int) ($_POST['member_uid'] ?? 0);
        if ($cid && $muid) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $cid));
            if ($row) {
                $list = lfi_nct_mobi_parts($row);
                if (!in_array($muid, $list, true)) {
                    $list[] = $muid;
                    $wpdb->update($t, ['participants' => wp_json_encode(array_values(array_unique($list)))], ['id' => $cid]);
                }
            }
        }
        wp_safe_redirect(lfi_nct_app_url('mobilisation', $back_args + ['added' => 1]));
        exit;
    }
    /* --- Rattacher l'événement à une campagne (admin) --- */
    if (!empty($_POST['lfi_evt_campagne']) && check_admin_referer('lfi_evt_campagne') && lfi_nct_mobi_can_mod() && $event_id > 0) {
        $slug = sanitize_title($_POST['campagne'] ?? '');
        $reg = lfi_nct_mobi_theme_registry();
        update_post_meta($event_id, '_lfi_evt_campagne', isset($reg[$slug]) ? $slug : '');
        wp_safe_redirect(lfi_nct_app_url('mobilisation', $back_args + ['campok' => 1]));
        exit;
    }
    /* --- Suppression d'un créneau (créateur ou modérateur) --- */
    if (!empty($_POST['lfi_mobi_del']) && check_admin_referer('lfi_mobi_del')) {
        $cid = (int) $_POST['lfi_mobi_del'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT created_by FROM $t WHERE id = %d", $cid));
        if ($row && ((int) $row->created_by === $uid || lfi_nct_mobi_can_mod())) {
            $wpdb->delete($t, ['id' => $cid]);
        }
        wp_safe_redirect(lfi_nct_app_url('mobilisation', $back_args + ['del' => 1]));
        exit;
    }
    /* --- Envoi groupé par EMAIL à tous les membres du GA (vote / invitation) --- */
    if (!empty($_POST['lfi_mobi_notify']) && check_admin_referer('lfi_mobi_notify') && lfi_nct_mobi_can_mod()) {
        $cid   = (int) $_POST['lfi_mobi_notify'];
        $phase = (($_POST['phase'] ?? 'vote') === 'invit') ? 'invit' : 'vote';
        /* Anti-renvoi : une fois envoyé, c'est acté — on ne renvoie pas en masse. */
        $emap = lfi_nct_mobi_email_sent($cid);
        if (!empty($emap[$phase])) {
            wp_safe_redirect(lfi_nct_app_url('mobilisation', $back_args + ['already' => 1]));
            exit;
        }
        $row2  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $cid));
        $sent  = $row2 ? lfi_nct_mobi_notify_members($row2, $phase, $back_args, $types, $creneaux) : 0;
        if ($sent > 0) lfi_nct_mobi_email_mark($cid, $phase);
        wp_safe_redirect(lfi_nct_app_url('mobilisation', $back_args + ['sent' => $sent]));
        exit;
    }

    lfi_nct_app_screen_open('🎟️ ' . $titre, 'Créneaux de mobilisation — clique « Je participe »');
    if (!empty($_GET['ok']))  lfi_nct_app_flash('✅ Créneau(x) ajouté(s) — tu y es inscrit·e.');
    if (!empty($_GET['del'])) lfi_nct_app_flash('🗑 Créneau supprimé.');
    if (!empty($_GET['added'])) lfi_nct_app_flash('✅ Participant·e ajouté·e au créneau (présence actée).');
    if (isset($_GET['sent'])) {
        $ns = (int) $_GET['sent'];
        if ($ns > 0) lfi_nct_app_flash('✅ Email BIEN ENVOYÉ à ' . $ns . ' membre(s) du GA.');
        else lfi_nct_app_flash('⚠️ Aucun email parti : soit les comptes membres n\'ont pas d\'email, soit l\'envoi serveur a échoué. Utilise le repli SMS en attendant.', 'error');
    }
    if (!empty($_GET['already'])) lfi_nct_app_flash('ℹ️ Déjà envoyé à tous les membres — c\'est acté, on ne renvoie pas (anti-spam).');
    if (!empty($_GET['campok'])) lfi_nct_app_flash('✅ Campagne rattachée.');

    echo '<div style="text-align:center;margin:4px 0 10px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('mobilisation')) . '">← Toutes les actions</a></div>';

    /* Rappel du contexte. */
    echo '<div class="lfi-app-card" style="border-left:4px solid #c8102e"><div class="com">';
    if ($d) {
        echo '📅 <strong>' . esc_html(ucfirst($d['date_complete'] ?: $d['date'])) . '</strong>';
        if ($d['heure_fin']) echo ' – ' . esc_html($d['heure_fin']);
        if ($d['lieu']) echo '<br>📍 ' . esc_html($d['lieu']) . ($d['adresse'] && $d['adresse'] !== $d['lieu'] ? ' — ' . esc_html($d['adresse']) : '');
        $camp = lfi_nct_evt_campagne($event_id);
        if ($camp !== '') echo '<br><span style="background:#efe9fb;color:#4b2e83;font-size:.8em;font-weight:700;padding:2px 8px;border-radius:20px">🚩 ' . esc_html($camp) . '</span>';
    } else {
        echo '🚩 <strong>Campagne</strong> — on pose des créneaux au fil de l\'eau, sans date imposée.';
    }
    echo '</div></div>';

    /* Admin : rattacher cet événement-action à une campagne (ex. le porte-à-porte
       du 11 = action du GA ET « Bataille du logement »). */
    if ($event_id > 0 && lfi_nct_mobi_can_mod()) {
        $cur = (string) get_post_meta($event_id, '_lfi_evt_campagne', true);
        echo '<details class="lfi-app-card" style="border-left:4px solid #4b2e83"><summary style="cursor:pointer;font-weight:700;color:#4b2e83;font-size:.9em">🚩 Rattacher à une campagne</summary>';
        echo '<form method="post" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">' . wp_nonce_field('lfi_evt_campagne', '_wpnonce', true, false);
        echo '<select name="campagne" style="flex:1;min-width:160px;padding:8px;border:1px solid #ccc;border-radius:8px"><option value="">— Aucune —</option>';
        foreach (lfi_nct_mobi_theme_registry() as $slug => $m) echo '<option value="' . esc_attr($slug) . '"' . selected($slug, $cur, false) . '>' . esc_html($m[1]) . '</option>';
        echo '</select><button type="submit" name="lfi_evt_campagne" value="1" class="btn-ghost" style="padding:8px 12px;color:#4b2e83">💾</button></form>';
        echo '<div class="lfi-app-help" style="margin-top:2px"><small>Une action peut être à la fois une action du GA <strong>et</strong> rattachée à une campagne de fond.</small></div></details>';
    }

    /* Admin : envoyer l'événement dans l'agenda des membres (invitation .ics). */
    if ($event_id > 0 && lfi_nct_mobi_can_mod() && function_exists('lfi_nct_app_view_agenda_invite')) {
        echo '<div style="text-align:center;margin:0 0 10px"><a class="btn-ghost" style="color:#186a3b" href="' . esc_url(lfi_nct_app_url('agenda-invite', ['ev' => $event_id])) . '">📅 Mettre dans l\'agenda des membres</a></div>';
    }

    /* Formulaire : proposer un ou PLUSIEURS créneaux d'un coup. */
    echo '<details class="lfi-app-card" style="border-left:4px solid #186a3b" open><summary style="cursor:pointer;font-weight:800;color:#186a3b">➕ Proposer des dates / créneaux</summary>';
    echo '<form method="post" style="margin-top:8px">';
    echo wp_nonce_field('lfi_mobi_add', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_mobi_add" value="1">';
    echo '<div style="margin:6px 0"><label>Action<br><select name="type" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px">';
    foreach ($types as $k => $meta) echo '<option value="' . esc_attr($k) . '"' . selected($k, 'tractage', false) . '>' . esc_html($meta[1]) . '</option>';
    echo '</select></label></div>';

    echo '<div style="font-weight:700;margin:8px 0 4px">Jours & moments <span style="font-weight:400;color:#888">(ajoute-en autant que tu veux)</span></div>';
    echo '<div id="lfi-mobi-rows">';
    echo lfi_nct_mobi_row_html($creneaux);
    echo '</div>';
    echo '<div style="margin:6px 0"><button type="button" id="lfi-mobi-addrow" class="btn-ghost" style="padding:6px 12px">➕ Ajouter un autre jour</button></div>';

    /* Point de RDV : pré-rempli avec le point central du GA + liste déroulante
       qui apprend (Clos Toreau = Place du Pays Basque). Le moins de saisie possible. */
    $rdv_primary = function_exists('lfi_nct_rdv_primary') ? lfi_nct_rdv_primary() : '';
    $rdv_dl = function_exists('lfi_nct_rdv_datalist') ? lfi_nct_rdv_datalist('lfi-rdv-pts') : '';
    echo '<div style="margin:6px 0"><label>Point de rendez-vous<br><input type="text" name="lieu" list="lfi-rdv-pts" value="' . esc_attr($rdv_primary) . '" maxlength="200" placeholder="ex : Place du Pays Basque" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px">' . $rdv_dl . '</label><div class="lfi-app-help" style="margin-top:2px"><small>Choisis dans la liste ou tape un nouveau point — il sera mémorisé pour la prochaine fois.</small></div></div>';
    echo '<div style="margin:6px 0"><label>Note (optionnel)<br><input type="text" name="note" maxlength="255" placeholder="ex : 500 tracts à distribuer" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px"></label></div>';
    echo '<div style="margin-top:8px"><button type="submit" class="btn-primary" style="background:#186a3b">Créer le(s) créneau(x)</button></div>';
    echo '</form></details>';

    /* Petit JS : dupliquer une ligne jour/moment. */
    $tpl = str_replace(["\n", "'"], ['', "\\'"], lfi_nct_mobi_row_html($creneaux));
    echo '<script>(function(){var b=document.getElementById("lfi-mobi-addrow"),c=document.getElementById("lfi-mobi-rows");if(b&&c){b.addEventListener("click",function(){var d=document.createElement("div");d.innerHTML=\'' . $tpl . '\';c.appendChild(d.firstChild||d);});}})();</script>';

    /* Liste des créneaux. */
    $where = $event_id > 0
        ? $wpdb->prepare("event_id = %d", $event_id)
        : $wpdb->prepare("event_id = 0 AND theme = %s", $theme);
    $rows = $wpdb->get_results("SELECT * FROM $t WHERE $where" . lfi_nct_mobi_scope_clause() . " ORDER BY date_creneau ASC, creneau ASC, id ASC") ?: [];

    /* ADMIN : box « Acter une présence » bien visible (ex. Yves confirmé par SMS).
       Choisir la personne + le créneau, sans qu'elle ait cliqué. */
    if (lfi_nct_mobi_can_mod() && !empty($rows)) {
        $members = lfi_nct_mobi_ga_members_full();
        $mopts = '';
        foreach ($members as $m) $mopts .= '<option value="' . (int) $m['uid'] . '">' . esc_html($m['nom']) . '</option>';
        $copts = '';
        foreach ($rows as $r) {
            $w = $r->date_creneau ? ucfirst(wp_date('j M', strtotime($r->date_creneau))) : 'sans date';
            $copts .= '<option value="' . (int) $r->id . '">' . esc_html($w . ' · ' . ($creneaux[$r->creneau] ?? $r->creneau)) . '</option>';
        }
        if ($mopts !== '') {
            echo '<details class="lfi-app-card" style="border:2px solid #186a3b;background:#f4fbf4;margin-top:12px" open><summary style="cursor:pointer;font-weight:800;color:#186a3b">➕ Acter une présence (confirmé par SMS, oral…)</summary>';
            echo '<div class="lfi-app-help" style="margin:6px 0"><small>Quelqu\'un t\'a dit « je viens » sans passer par l\'app (ex. Yves) ? Ajoute-le ici — il comptera dans le vote.</small></div>';
            echo '<form method="post" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">' . wp_nonce_field('lfi_mobi_addpart_pick', '_wpnonce', true, false);
            echo '<input type="hidden" name="lfi_mobi_addpart_pick" value="1">';
            echo '<select name="member_uid" required style="flex:1;min-width:130px;padding:8px;border:1px solid #ccc;border-radius:8px">' . $mopts . '</select>';
            echo '<span style="font-size:.85em;color:#666">sur</span>';
            echo '<select name="creneau_id" required style="flex:1;min-width:130px;padding:8px;border:1px solid #ccc;border-radius:8px">' . $copts . '</select>';
            echo '<button type="submit" class="btn-primary" style="background:#186a3b;padding:8px 14px">✅ Acter</button></form></details>';
        }
    }

    echo '<h3 style="margin:16px 0 6px;color:#186a3b">Les créneaux</h3>';
    if (empty($rows)) {
        echo '<div class="lfi-app-help">Aucun créneau pour l\'instant. Propose le premier ci-dessus — <strong>ensuite</strong> tu pourras acter une présence (comme Yves).</div>';
        lfi_nct_app_screen_close();
        return;
    }

    echo '<ul class="lfi-app-list">';
    foreach ($rows as $r) {
        $tmeta = $types[$r->type] ?? ['✨', 'Action'];
        $list = lfi_nct_mobi_parts($r);
        $i_am = in_array($uid, $list, true);
        $when = $r->date_creneau ? ucfirst(wp_date('l j M', strtotime($r->date_creneau))) : '';
        $accent = $i_am ? '#186a3b' : '#0066a3';
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . esc_attr($accent) . '">';
        echo '<div class="head"><div class="who">' . $tmeta[0] . ' ' . esc_html($when) . '</div>';
        echo '<div class="when" style="font-size:.8em;color:#666">' . esc_html($creneaux[$r->creneau] ?? $r->creneau) . '</div></div>';
        echo '<div class="meta"><span class="meta-chip">' . $tmeta[0] . ' ' . esc_html($tmeta[1]) . '</span>';
        if (trim((string) $r->lieu) !== '') echo '<span class="meta-chip">📍 ' . esc_html($r->lieu) . '</span>';
        echo '</div>';
        if (trim((string) $r->note) !== '') echo '<div class="com" style="color:#555">' . esc_html($r->note) . '</div>';

        $names = array_map('lfi_nct_mobi_uname', $list);
        echo '<div class="com" style="font-size:.9em"><strong>👥 ' . count($list) . ' participant·e·s</strong>' . (count($names) ? ' : ' . esc_html(implode(', ', $names)) : ' — sois le/la premier·e !') . '</div>';

        /* Vote → validation : à partir du seuil, l'action est ACTÉE. */
        $seuil = lfi_nct_mobi_vote_threshold();
        $actee = count($list) >= $seuil;
        if ($actee) {
            echo '<div style="margin-top:4px"><span style="background:#186a3b;color:#fff;font-weight:800;font-size:.75em;padding:3px 9px;border-radius:20px">✅ ACTION ACTÉE (' . count($list) . '/' . $seuil . ')</span></div>';
            /* Étape suivante pour l'admin : officialiser sur Action Populaire. */
            if (lfi_nct_mobi_can_mod()) {
                $ap_place = trim((string) $r->lieu) !== '' ? $r->lieu . ', Nantes' : 'Nantes';
                echo '<div class="lfi-app-card" style="border:2px solid #c8102e;background:#fff7f8;margin-top:6px">'
                   . '<div class="com"><strong>🎉 C\'est parti — officialise-la sur Action Populaire.</strong><br>'
                   . 'Crée l\'événement (titre : « ' . esc_html($tmeta[1]) . ' ' . esc_html($when) . ' » · lieu : ' . esc_html($ap_place) . ') pour qu\'il soit visible partout.</div>'
                   . '<div style="margin-top:6px"><a class="btn-primary" style="background:#c8102e" href="https://actionpopulaire.fr/creer/evenement/" target="_blank" rel="noopener">✊ Créer sur Action Populaire</a></div></div>';
            }
        } else {
            echo '<div style="margin-top:4px"><span style="background:#d39e00;color:#fff;font-weight:800;font-size:.75em;padding:3px 9px;border-radius:20px">🗳 EN VOTE (' . count($list) . '/' . $seuil . ') — encore ' . max(0, $seuil - count($list)) . ' pour lancer</span></div>';
        }

        echo '<div class="row-actions" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">';
        echo '<form method="post" style="display:inline">' . wp_nonce_field('lfi_mobi_join', '_wpnonce', true, false)
           . '<input type="hidden" name="lfi_mobi_join" value="' . (int) $r->id . '">'
           . '<button type="submit" class="btn-primary" style="background:' . ($i_am ? '#888' : '#186a3b') . '">' . ($i_am ? '✓ Je participe (me retirer)' : '🙋 Je participe') . '</button></form>';
        if ((int) $r->created_by === $uid || lfi_nct_mobi_can_mod()) {
            echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Supprimer ce créneau ?\');">' . wp_nonce_field('lfi_mobi_del', '_wpnonce', true, false)
               . '<input type="hidden" name="lfi_mobi_del" value="' . (int) $r->id . '">'
               . '<button type="submit" class="btn-ghost" style="padding:6px 10px;font-size:.82em;color:#c8102e">🗑 Supprimer</button></form>';
        }
        echo '</div>';

        /* Admin : acter manuellement la présence d'un membre (confirmé hors app,
           ex. Yves qui a répondu par SMS). On ne montre que les non-inscrits. */
        if (lfi_nct_mobi_can_mod()) {
            $all_m = lfi_nct_mobi_ga_members_full();
            $opts = '';
            foreach ($all_m as $m) {
                if (in_array((int) $m['uid'], $list, true)) continue; /* déjà participant */
                $opts .= '<option value="' . (int) $m['uid'] . '">' . esc_html($m['nom']) . '</option>';
            }
            if ($opts !== '') {
                echo '<form method="post" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">'
                   . wp_nonce_field('lfi_mobi_addpart', '_wpnonce', true, false)
                   . '<input type="hidden" name="lfi_mobi_addpart" value="' . (int) $r->id . '">'
                   . '<span style="font-size:.82em;color:#666">➕ Acter la présence de :</span>'
                   . '<select name="member_uid" style="padding:6px;border:1px solid #ccc;border-radius:8px;font-size:.85em">' . $opts . '</select>'
                   . '<button type="submit" class="btn-ghost" style="padding:6px 10px;font-size:.82em;color:#186a3b">✅ Ajouter</button></form>';
                echo '<div class="lfi-app-help" style="margin-top:2px"><small>Pour un membre qui a confirmé autrement (SMS, oral) — sa présence est comptée dans le vote.</small></div>';
            }
        }

        /* Admin : prévenir TOUS les membres du GA. Email = envoi de masse fiable
           (un clic, côté serveur). SMS = repli « copier les numéros » (iOS
           n'ouvre un lien sms: qu'au 1er destinataire → pas de vrai envoi groupé). */
        if (lfi_nct_mobi_can_mod()) {
            $emails = lfi_nct_mobi_member_emails();
            $phones = lfi_nct_mobi_member_phones();
            $nem = count($emails); $nph = count($phones);
            $lieu_txt = trim((string) $r->lieu) !== '' ? ' (RDV ' . $r->lieu . ')' : '';
            $lien = lfi_nct_app_url('mobilisation', $back_args);
            echo '<div style="margin-top:8px;padding-top:8px;border-top:1px dashed #eee">';
            echo '<div style="font-size:.82em;color:#666;margin-bottom:4px">📣 Prévenir <strong>tous les ' . $nem . ' membres du GA</strong> :</div>';
            echo '<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">';
            $emap = lfi_nct_mobi_email_sent((int) $r->id);
            $vote_style = $actee ? 'btn-ghost' : 'btn-primary';
            $inv_style  = $actee ? 'btn-primary' : 'btn-ghost';
            /* VOTE : une seule fois. */
            if (!empty($emap['vote'])) {
                echo '<span class="btn-ghost" style="padding:8px 12px;font-size:.85em;color:#186a3b;cursor:default">✅ Vote déjà envoyé</span>';
            } else {
                echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Envoyer le vote à tous les ' . $nem . ' membres ? (une seule fois)\');"><input type="hidden" name="lfi_mobi_notify" value="' . (int) $r->id . '"><input type="hidden" name="phase" value="vote">' . wp_nonce_field('lfi_mobi_notify', '_wpnonce', true, false)
                   . '<button type="submit" class="' . $vote_style . '" style="padding:8px 12px;font-size:.85em' . ($actee ? ';color:#0066a3' : ';background:#d39e00') . '">🗳 Proposer au vote (email × ' . $nem . ')</button></form>';
            }
            /* INVITATION : une seule fois. */
            if (!empty($emap['invit'])) {
                echo '<span class="btn-ghost" style="padding:8px 12px;font-size:.85em;color:#186a3b;cursor:default">✅ Invitation déjà envoyée</span>';
            } else {
                echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Envoyer l\\\'invitation à tous les ' . $nem . ' membres ? (une seule fois)\');"><input type="hidden" name="lfi_mobi_notify" value="' . (int) $r->id . '"><input type="hidden" name="phase" value="invit">' . wp_nonce_field('lfi_mobi_notify', '_wpnonce', true, false)
                   . '<button type="submit" class="' . $inv_style . '" style="padding:8px 12px;font-size:.85em' . ($actee ? ';background:#186a3b' : ';color:#186a3b') . '">🎟️ Inviter les ' . $nem . ' membres (email)</button></form>';
            }
            /* SMS un par un (suivi ✅) + partage Telegram (autres GA). */
            $tg = 'https://t.me/share/url?url=' . rawurlencode($lien) . '&text=' . rawurlencode('LFI — action de terrain : ' . $tmeta[1] . ' ' . $when . $lieu_txt . '. On se mobilise, rejoignez / répliquez chez vous 👇');
            echo '<a href="' . esc_url(lfi_nct_app_url('mobilisation', $back_args + ['sms' => (int) $r->id])) . '" class="btn-ghost" style="padding:8px 12px;font-size:.85em;color:#0066a3;margin-top:6px;display:inline-block">📲 SMS un par un (suivi ✅)</a> ';
            echo '<a href="' . esc_url($tg) . '" target="_blank" rel="noopener" class="btn-ghost" style="padding:8px 12px;font-size:.85em;color:#0088cc;margin-top:6px;display:inline-block">✈️ Partager sur Telegram</a>';
            echo '</div>';
            if ($nem === 0) echo '<div class="lfi-app-help" style="margin-top:4px"><small>Aucun email de membre trouvé — vérifie que les comptes des membres du GA ont bien un email.</small></div>';
            /* Repli SMS honnête : le texte + tous les numéros à copier (l'iPhone
               n'envoie pas à plusieurs via un lien). */
            if ($nph > 0) {
                $sms_txt = 'LFI Clos Toreau 👋 On organise : ' . $tmeta[1] . ' ' . $when . ' ' . ($creneaux[$r->creneau] ?? '') . $lieu_txt . '. Tu es partant·e ? 👉 Clique le lien et appuie sur « 🙋 Je participe » — c\'est ton clic qui compte (pas la peine de répondre à ce SMS) : ' . $lien;
                echo '<details style="margin-top:6px"><summary style="cursor:pointer;font-size:.82em;color:#0066a3">📲 Par SMS (copier le texte + les ' . $nph . ' numéros)</summary>';
                echo '<div class="lfi-app-help" style="margin-top:4px"><small>L\'iPhone n\'envoie un lien SMS qu\'au 1er numéro. Copie ce texte + ces numéros dans un groupe Messages :</small></div>';
                echo '<textarea readonly style="width:100%;height:56px;margin-top:4px;font-size:.8em;padding:6px;border:1px solid #ccc;border-radius:8px">' . esc_textarea($sms_txt) . '</textarea>';
                echo '<textarea readonly style="width:100%;height:56px;margin-top:4px;font-size:.8em;padding:6px;border:1px solid #ccc;border-radius:8px">' . esc_textarea(implode(', ', $phones)) . '</textarea>';
                echo '</details>';
            }
            echo '</div>';
        }
        echo '</li>';
    }
    echo '</ul>';

    lfi_nct_app_screen_close();
}

/** Une ligne « jour + moment » du formulaire multi-créneaux. */
function lfi_nct_mobi_row_html($creneaux) {
    $opts = '';
    foreach ($creneaux as $k => $lbl) $opts .= '<option value="' . esc_attr($k) . '"' . selected($k, 'aprem', false) . '>' . esc_html($lbl) . '</option>';
    return '<div class="lfi-mobi-row" style="display:flex;gap:6px;margin:4px 0">'
         . '<input type="date" name="d[]" style="flex:1;padding:9px;border:1px solid #ccc;border-radius:8px">'
         . '<select name="c[]" style="flex:1;padding:9px;border:1px solid #ccc;border-radius:8px">' . $opts . '</select>'
         . '</div>';
}
