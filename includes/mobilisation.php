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
    $emails = lfi_nct_mobi_member_emails();
    if (empty($emails)) return 0;
    $tmeta  = $types[$row->type] ?? ['✨', 'Action'];
    $when   = $row->date_creneau ? ucfirst(wp_date('l j M', strtotime($row->date_creneau))) : '';
    $moment = $creneaux[$row->creneau] ?? '';
    $lieu   = trim((string) $row->lieu);
    $lien   = lfi_nct_app_url('mobilisation', $back_args);
    if ($phase === 'invit') {
        $subject = '🎟️ Action confirmée : ' . $tmeta[1] . ' — ' . $when;
        $intro   = "C'est confirmé ! On compte sur toi pour cette action.";
        $cta     = 'Voir les détails & m\'inscrire';
        $color   = '#186a3b';
    } else {
        $subject = '🗳 Nouvelle action proposée : ' . $tmeta[1] . ' — ' . $when;
        $intro   = "Une nouvelle action est proposée par le Groupe d'Action. Es-tu partant·e ?";
        $cta     = 'Voter « je participe »';
        $color   = '#c8102e';
    }
    $body = '<div style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;max-width:520px;margin:auto">'
          . '<div style="background:' . $color . ';color:#fff;padding:16px 20px;border-radius:12px 12px 0 0;font-weight:800;font-size:1.1em">' . esc_html($subject) . '</div>'
          . '<div style="border:1px solid #eee;border-top:0;border-radius:0 0 12px 12px;padding:18px 20px">'
          . '<p>' . esc_html($intro) . '</p>'
          . '<div style="background:#f7f7f7;border-radius:10px;padding:12px 14px;margin:10px 0">'
          . '<strong>' . esc_html($tmeta[0] . ' ' . $tmeta[1]) . '</strong><br>🗓 ' . esc_html($when . ' · ' . $moment)
          . ($lieu ? '<br>📍 ' . esc_html($lieu) : '') . '</div>'
          . '<div style="margin-top:14px"><a href="' . esc_url($lien) . '" style="display:inline-block;background:' . $color . ';color:#fff;text-decoration:none;padding:11px 18px;border-radius:10px;font-weight:800">' . esc_html($cta) . '</a></div>'
          . '<div style="margin-top:14px;font-size:.82em;color:#888">Tu reçois cet email en tant que membre du Groupe d\'Action LFI Nantes Sud – Clos Toreau.</div>'
          . '</div></div>';
    add_filter('wp_mail_content_type', 'lfi_nct_agenda_html_ct');
    add_filter('wp_mail_from_name', 'lfi_nct_agenda_from_name');
    $sent = 0;
    foreach ($emails as $e) if (wp_mail($e, $subject, $body)) $sent++;
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
    $events = lfi_nct_upcoming_events(6);
    if (empty($events)) return;
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_mobilisation';

    echo '<div class="lfi-app-section" style="margin-top:20px">';
    echo '<div class="lfi-app-section-title" style="font-size:1.05em">📅 À VENIR — SE MOBILISER</div>';
    echo '<div style="display:flex;flex-direction:column;gap:10px">';
    foreach ($events as $e) {
        $d = function_exists('lfi_nct_event_data') ? lfi_nct_event_data($e) : null;
        if (!$d) continue;
        $eid = (int) $d['id'];
        $rows = $wpdb->get_results($wpdb->prepare("SELECT participants FROM $t WHERE event_id = %d", $eid)) ?: [];
        $ncr = count($rows); $npart = 0;
        foreach ($rows as $r) { $l = json_decode((string) $r->participants, true); if (is_array($l)) $npart += count($l); }
        $url = lfi_nct_app_url('mobilisation', ['ev' => $eid]);
        echo '<div class="lfi-app-card" style="border-left:4px solid #c8102e">';
        echo '<div class="head"><div class="who">📣 ' . esc_html($d['titre']) . '</div></div>';
        echo '<div class="meta">';
        if ($d['date_complete']) echo '<span class="meta-chip">' . esc_html(ucfirst($d['date_complete'])) . '</span>';
        if ($d['lieu']) echo '<span class="meta-chip">📍 ' . esc_html($d['lieu']) . '</span>';
        echo '</div>';
        if ($ncr > 0) echo '<div class="com" style="font-size:.9em"><strong>' . $ncr . '</strong> créneau(x) · <strong>' . $npart . '</strong> participant(s)</div>';
        else echo '<div class="com" style="font-size:.9em;color:#888">Aucun créneau encore — lance-toi.</div>';
        echo '<div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">';
        echo '<a class="btn-primary" href="' . esc_url($url) . '">🙋 Voir & participer</a>';
        echo '<a class="btn-ghost" href="' . esc_url($url) . '">➕ Proposer un créneau</a>';
        echo '</div></div>';
    }
    echo '</div>';
    echo '<div style="text-align:center;margin-top:10px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('mobilisation')) . '">🤝 Toutes les actions & campagnes →</a></div>';
    echo '</div>';
}

/* ============================================================== *
 *  ROUTEUR : hub, ou tableau d'une action (événement OU campagne) *
 * ============================================================== */
function lfi_nct_app_view_mobilisation() {
    if (!lfi_nct_mobi_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    /* Création d'une campagne libre depuis le hub. */
    if (!empty($_POST['lfi_mobi_theme_add']) && check_admin_referer('lfi_mobi_theme_add')) {
        $slug = lfi_nct_mobi_theme_add($_POST['theme_label'] ?? '');
        if ($slug !== '') { wp_safe_redirect(lfi_nct_app_url('mobilisation', ['theme' => $slug])); exit; }
        wp_safe_redirect(lfi_nct_app_url('mobilisation')); exit;
    }

    $ev    = isset($_GET['ev']) ? (int) $_GET['ev'] : 0;
    $theme = isset($_GET['theme']) ? sanitize_title($_GET['theme']) : '';
    if ($ev > 0)          { lfi_nct_app_view_mobilisation_board(['event_id' => $ev]); return; }
    if ($theme !== '')    { lfi_nct_app_view_mobilisation_board(['theme' => $theme]); return; }
    lfi_nct_app_view_mobilisation_hub();
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

    /* Agrège créneaux + participants par clé (event:ID ou theme:slug). */
    $agg = [];
    $all = $wpdb->get_results("SELECT event_id, theme, participants FROM $t WHERE 1=1" . lfi_nct_mobi_scope_clause()) ?: [];
    foreach ($all as $r) {
        $key = ((int) $r->event_id > 0) ? ('e' . (int) $r->event_id) : ('t' . $r->theme);
        if (!isset($agg[$key])) $agg[$key] = ['creneaux' => 0, 'parts' => 0];
        $agg[$key]['creneaux']++;
        $agg[$key]['parts'] += count(lfi_nct_mobi_parts($r));
    }

    /* --- Section AGENDA : un événement = une action possible --- */
    $events = function_exists('lfi_nct_upcoming_events') ? lfi_nct_upcoming_events(20) : [];
    echo '<h3 style="margin:14px 0 6px;color:#c8102e">📅 Pour nos événements</h3>';
    if (empty($events)) {
        echo '<div class="lfi-app-help">Aucun événement à venir dans l\'agenda pour l\'instant.</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        foreach ($events as $e) {
            $d = lfi_nct_event_data($e);
            if (!$d) continue;
            $eid = (int) $d['id'];
            $a = $agg['e' . $eid] ?? ['creneaux' => 0, 'parts' => 0];
            lfi_nct_mobi_hub_card(
                '📣 Tractage / collage pour : ' . $d['titre'],
                trim(ucfirst((string) $d['date_complete']) . ($d['lieu'] ? ' · 📍 ' . $d['lieu'] : '')),
                $a,
                lfi_nct_app_url('mobilisation', ['ev' => $eid])
            );
        }
        echo '</ul>';
    }

    /* --- Section CAMPAGNES --- */
    echo '<h3 style="margin:18px 0 6px;color:#c8102e">🚩 Pour nos campagnes</h3>';
    echo '<ul class="lfi-app-list">';
    foreach (lfi_nct_mobi_theme_registry() as $slug => $m) {
        $a = $agg['t' . $slug] ?? ['creneaux' => 0, 'parts' => 0];
        lfi_nct_mobi_hub_card($m[0] . ' ' . $m[1], 'Campagne', $a, lfi_nct_app_url('mobilisation', ['theme' => $slug]));
    }
    echo '</ul>';

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

/** Une carte du hub (titre, sous-titre, agrégat, url). */
function lfi_nct_mobi_hub_card($titre, $sous, $a, $url) {
    $accent = ($a['creneaux'] > 0) ? '#186a3b' : '#0066a3';
    echo '<li class="lfi-app-card" style="border-left:4px solid ' . esc_attr($accent) . '">';
    echo '<div class="head"><div class="who">' . esc_html($titre) . '</div></div>';
    if ($sous !== '') echo '<div class="meta"><span class="meta-chip">' . esc_html($sous) . '</span></div>';
    if ($a['creneaux'] > 0) {
        echo '<div class="com" style="font-size:.9em"><strong>' . (int) $a['creneaux'] . '</strong> créneau(x) · <strong>' . (int) $a['parts'] . '</strong> participation(s)</div>';
    } else {
        echo '<div class="com" style="font-size:.9em;color:#888">Pas encore de créneau — <strong>lance le premier</strong>.</div>';
    }
    echo '<div style="margin-top:8px"><a class="btn-primary" style="background:' . esc_attr($accent) . '" href="' . esc_url($url) . '">🎟️ Organiser / participer</a></div>';
    echo '</li>';
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
        $row2  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $cid));
        $sent  = $row2 ? lfi_nct_mobi_notify_members($row2, $phase, $back_args, $types, $creneaux) : 0;
        wp_safe_redirect(lfi_nct_app_url('mobilisation', $back_args + ['sent' => $sent]));
        exit;
    }

    lfi_nct_app_screen_open('🎟️ ' . $titre, 'Créneaux de mobilisation — clique « Je participe »');
    if (!empty($_GET['ok']))  lfi_nct_app_flash('✅ Créneau(x) ajouté(s) — tu y es inscrit·e.');
    if (!empty($_GET['del'])) lfi_nct_app_flash('🗑 Créneau supprimé.');
    if (isset($_GET['sent'])) lfi_nct_app_flash('📧 Email envoyé à ' . (int) $_GET['sent'] . ' membre(s) du GA.');

    echo '<div style="text-align:center;margin:4px 0 10px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('mobilisation')) . '">← Toutes les actions</a></div>';

    /* Rappel du contexte. */
    echo '<div class="lfi-app-card" style="border-left:4px solid #c8102e"><div class="com">';
    if ($d) {
        echo '📅 <strong>' . esc_html(ucfirst($d['date_complete'] ?: $d['date'])) . '</strong>';
        if ($d['heure_fin']) echo ' – ' . esc_html($d['heure_fin']);
        if ($d['lieu']) echo '<br>📍 ' . esc_html($d['lieu']) . ($d['adresse'] && $d['adresse'] !== $d['lieu'] ? ' — ' . esc_html($d['adresse']) : '');
    } else {
        echo '🚩 <strong>Campagne</strong> — on pose des créneaux au fil de l\'eau, sans date imposée.';
    }
    echo '</div></div>';

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

    echo '<h3 style="margin:16px 0 6px;color:#186a3b">Les créneaux</h3>';
    if (empty($rows)) {
        echo '<div class="lfi-app-help">Aucun créneau pour l\'instant. Propose le premier ci-dessus.</div>';
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
            $vote_style = $actee ? 'btn-ghost' : 'btn-primary';
            $inv_style  = $actee ? 'btn-primary' : 'btn-ghost';
            echo '<form method="post" style="display:inline"><input type="hidden" name="lfi_mobi_notify" value="' . (int) $r->id . '"><input type="hidden" name="phase" value="vote">' . wp_nonce_field('lfi_mobi_notify', '_wpnonce', true, false)
               . '<button type="submit" class="' . $vote_style . '" style="padding:8px 12px;font-size:.85em' . ($actee ? ';color:#0066a3' : ';background:#d39e00') . '">🗳 Proposer au vote (email × ' . $nem . ')</button></form>';
            echo '<form method="post" style="display:inline"><input type="hidden" name="lfi_mobi_notify" value="' . (int) $r->id . '"><input type="hidden" name="phase" value="invit">' . wp_nonce_field('lfi_mobi_notify', '_wpnonce', true, false)
               . '<button type="submit" class="' . $inv_style . '" style="padding:8px 12px;font-size:.85em' . ($actee ? ';background:#186a3b' : ';color:#186a3b') . '">🎟️ Inviter les ' . $nem . ' membres (email)</button></form>';
            echo '</div>';
            if ($nem === 0) echo '<div class="lfi-app-help" style="margin-top:4px"><small>Aucun email de membre trouvé — vérifie que les comptes des membres du GA ont bien un email.</small></div>';
            /* Repli SMS honnête : le texte + tous les numéros à copier (l'iPhone
               n'envoie pas à plusieurs via un lien). */
            if ($nph > 0) {
                $sms_txt = 'LFI Clos Toreau — ' . $tmeta[1] . ' ' . $when . ' ' . ($creneaux[$r->creneau] ?? '') . $lieu_txt . '. Réponds sur l\'app : ' . $lien;
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
