<?php
/**
 * CHECK PERMANENT DE LA BOÎTE EMAIL (sur le serveur, 24/7).
 *
 * Un wp-cron tourne toutes les ~4-5 h, indépendamment de toute session Claude :
 *   1. se connecte en IMAP à la boîte de l'association (mot de passe d'application) ;
 *   2. récupère les nouveaux emails des interlocuteurs qui comptent ;
 *   3. les rattache au bon dossier (par nom du locataire) ;
 *   4. prépare une réponse (posture psy + ligne de conduite) déposée dans le dossier
 *      → le bouton « Ouvrir dans Gmail » apparaît, prêt à relire/envoyer.
 *
 * Le mot de passe d'application est stocké en option (serveur), jamais dans Git.
 * Niveau A : réponses par gabarit (gratuit, permanent). Niveau B (plus tard) :
 * brancher l'IA Claude pour des réponses rédigées finement.
 */
if (!defined('ABSPATH')) exit;

/* Planning cron personnalisé : toutes les 4 h 30. */
add_filter('cron_schedules', 'lfi_nct_mailcheck_sched');
function lfi_nct_mailcheck_sched($s) {
    $s['lfi_nct_4h30'] = ['interval' => 16200, 'display' => 'Toutes les 4 h 30 (LFI mailcheck)'];
    return $s;
}

add_action('init', 'lfi_nct_mailcheck_cron_setup', 8);
function lfi_nct_mailcheck_cron_setup() {
    if (get_option('lfi_nct_mailcheck_enabled') !== '1') {
        $ts = wp_next_scheduled('lfi_nct_mailcheck_run');
        if ($ts) wp_unschedule_event($ts, 'lfi_nct_mailcheck_run');
        return;
    }
    if (!wp_next_scheduled('lfi_nct_mailcheck_run')) {
        wp_schedule_event(time() + 300, 'lfi_nct_4h30', 'lfi_nct_mailcheck_run');
    }
}
add_action('lfi_nct_mailcheck_run', 'lfi_nct_mailcheck_do');

/** Interlocuteurs dont les emails déclenchent une préparation de réponse. */
function lfi_nct_mailcheck_senders() {
    return apply_filters('lfi_nct_mailcheck_senders', [
        'nmh.fr', 'nantesmetropole.fr', 'loire-atlantique.gouv.fr', 'loire-atlantique.fr',
        'justice.fr', 'justice.gouv.fr', 'avocat',
    ]);
}

/** Le check lui-même (appelé par le cron, ou manuellement). Renvoie un rapport. */
function lfi_nct_mailcheck_do() {
    $rep = ['ok' => false, 'traites' => 0, 'prepares' => 0, 'msg' => ''];
    if (!function_exists('imap_open')) { $rep['msg'] = 'Extension PHP imap absente sur le serveur.'; lfi_nct_mailcheck_log($rep); return $rep; }
    $user = (string) get_option('lfi_nct_gmail_user', '');
    $pw   = str_replace(' ', '', (string) get_option('lfi_nct_gmail_app_pw', ''));
    if ($user === '' || $pw === '') { $rep['msg'] = 'Identifiants Gmail non configurés.'; lfi_nct_mailcheck_log($rep); return $rep; }

    $mbox = @imap_open('{imap.gmail.com:993/imap/ssl}INBOX', $user, $pw, 0, 1);
    if (!$mbox) { $rep['msg'] = 'Connexion IMAP échouée : ' . imap_last_error(); lfi_nct_mailcheck_log($rep); return $rep; }

    /* Messages non lus des 3 derniers jours. */
    $since = date('d-M-Y', strtotime('-3 days'));
    $ids = @imap_search($mbox, 'UNSEEN SINCE "' . $since . '"', SE_UID) ?: [];
    $seen = get_option('lfi_nct_mailcheck_seen', []);
    if (!is_array($seen)) $seen = [];
    $senders = lfi_nct_mailcheck_senders();

    foreach ($ids as $uid) {
        $ov = @imap_fetch_overview($mbox, $uid, FT_UID);
        if (!$ov || empty($ov[0])) continue;
        $o = $ov[0];
        $mid = (string) ($o->message_id ?? ('uid' . $uid));
        if (in_array($mid, $seen, true)) continue;
        $from = strtolower((string) ($o->from ?? ''));
        $match = false;
        foreach ($senders as $s) if (strpos($from, $s) !== false) { $match = true; break; }
        if (!$match) continue;

        $rep['traites']++;
        $seen[] = $mid;

        $subject = (string) imap_utf8((string) ($o->subject ?? ''));
        $body = lfi_nct_mailcheck_body($mbox, $uid);
        $dossier = lfi_nct_mailcheck_match_dossier($subject . ' ' . $body);
        if ($dossier) {
            lfi_nct_mailcheck_prepare_reply($dossier, $o, $subject, $body);
            $rep['prepares']++;
        }
    }
    @imap_close($mbox);

    /* On borne l'historique des vus. */
    if (count($seen) > 500) $seen = array_slice($seen, -500);
    update_option('lfi_nct_mailcheck_seen', $seen, false);
    $rep['ok'] = true;
    $rep['msg'] = 'Terminé.';
    lfi_nct_mailcheck_log($rep);
    return $rep;
}

function lfi_nct_mailcheck_log($rep) {
    update_option('lfi_nct_mailcheck_last', array_merge($rep, ['at' => current_time('mysql')]), false);
}

/** Corps texte (plain) d'un message IMAP. */
function lfi_nct_mailcheck_body($mbox, $uid) {
    $body = @imap_fetchbody($mbox, $uid, '1.1', FT_UID | FT_PEEK);
    if (!$body) $body = @imap_fetchbody($mbox, $uid, '1', FT_UID | FT_PEEK);
    if (!$body) $body = @imap_body($mbox, $uid, FT_UID | FT_PEEK);
    $body = quoted_printable_decode((string) $body);
    return mb_substr(wp_strip_all_tags((string) $body), 0, 4000);
}

/** Trouve le dossier concerné (nom du locataire présent dans le texte). */
function lfi_nct_mailcheck_match_dossier($text) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY updated_at DESC LIMIT 200") ?: [];
    $low = mb_strtolower($text);
    foreach ($rows as $r) {
        $nom = trim((string) $r->tenant_nom);
        if ($nom !== '' && mb_strpos($low, mb_strtolower($nom)) !== false) return $r;
    }
    return null;
}

/** Prépare une réponse (gabarit + posture psy + ligne de conduite) dans le dossier. */
function lfi_nct_mailcheck_prepare_reply($row, $o, $subject, $body) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    /* Adresse de réponse = expéditeur. */
    $to = '';
    if (!empty($o->from) && preg_match('/[\w.\-+]+@[\w.\-]+/', (string) $o->from, $m)) $to = $m[0];

    $rep_subject = (stripos($subject, 'Re:') === 0) ? $subject : ('Re: ' . $subject);
    $posture = '';
    if (function_exists('lfi_nct_psy_analyse')) {
        $r = lfi_nct_psy_analyse($body, 'institution');
        $posture = $r['label'] . ' — ton conseillé : ' . $r['ton'];
    }
    $nom = trim($row->tenant_prenom . ' ' . $row->tenant_nom);
    $reply = "Madame, Monsieur,\n\n"
        . "En accompagnement de " . $nom . ", que je suis à sa demande en tant que président de l'association Union des Quartiers Libres, je reviens vers vous.\n\n"
        . "[BROUILLON AUTOMATIQUE À RELIRE ET COMPLÉTER]\n"
        . "- Lecture de votre message : " . $posture . "\n"
        . "- Je rappelle que je suis l'interlocuteur unique de la personne accompagnée ; tout contact et tout accès au logement se font par mon intermédiaire et en ma présence.\n"
        . "- Sur le fond : un dysfonctionnement a été constaté et signalé. Il vous appartient d'intervenir/de constater ; je vous demande de me communiquer une date.\n\n"
        . "(Complétez ici les points précis selon le message reçu, puis envoyez.)\n\n"
        . "Cordialement,\nFabrice Doucet\nPrésident — Association Union des Quartiers Libres\nGroupe d'Action La France Insoumise Nantes Sud – Clos Toreau";

    $notes = json_decode($row->notes ?? '', true);
    if (!is_array($notes)) $notes = [];
    /* On archive aussi l'email reçu. */
    $notes['email_recu'] = isset($notes['email_recu']) && is_array($notes['email_recu']) ? $notes['email_recu'] : [];
    $notes['email_recu'][] = ['date' => wp_date('Y-m-d'), 'de' => $to, 'objet' => $subject, 'corps' => mb_substr($body, 0, 2000), 'src' => 'mailcheck'];
    $notes['replies'] = isset($notes['replies']) && is_array($notes['replies']) ? $notes['replies'] : [];
    $notes['replies'][] = ['to' => $to, 'subject' => $rep_subject, 'body' => $reply, 'objet' => 'Auto : ' . mb_substr($subject, 0, 60), 'date' => wp_date('Y-m-d'), 'src' => 'mailcheck'];
    $wpdb->update($t, ['notes' => wp_json_encode($notes), 'updated_at' => current_time('mysql')], ['id' => (int) $row->id]);
}

/* ============================================================== *
 *  REST : configurer / déclencher (via la clé d'intégration)     *
 * ============================================================== */
add_action('rest_api_init', function () {
    register_rest_route('lfi-nct/v1', '/mailcheck-config', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_mailcheck_rest_config',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/mailcheck-run', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_mailcheck_rest_run',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
});
function lfi_nct_mailcheck_rest_config($request) {
    $user = sanitize_text_field((string) $request->get_param('gmail_user'));
    $pw   = (string) $request->get_param('app_pw');
    $en   = $request->get_param('enabled');
    if ($user !== '') update_option('lfi_nct_gmail_user', $user, false);
    if ($pw !== '' && $pw !== null) update_option('lfi_nct_gmail_app_pw', str_replace(' ', '', $pw), false);
    if ($en !== null) update_option('lfi_nct_mailcheck_enabled', $en ? '1' : '0', false);
    lfi_nct_mailcheck_cron_setup();
    return new WP_REST_Response([
        'ok'       => true,
        'user'     => (string) get_option('lfi_nct_gmail_user', ''),
        'pw_set'   => get_option('lfi_nct_gmail_app_pw', '') !== '',
        'enabled'  => get_option('lfi_nct_mailcheck_enabled') === '1',
        'imap'     => function_exists('imap_open'),
    ], 200);
}
function lfi_nct_mailcheck_rest_run($request) {
    $rep = lfi_nct_mailcheck_do();
    return new WP_REST_Response(['ok' => (bool) $rep['ok']] + $rep, 200);
}

/* ============================================================== *
 *  Écran d'état (super-admin)                                     *
 * ============================================================== */
function lfi_nct_app_view_mailcheck() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    lfi_nct_app_screen_open('📬 Check permanent des emails', 'Surveillance automatique de la boîte (serveur, 24/7)');
    $en   = get_option('lfi_nct_mailcheck_enabled') === '1';
    $user = (string) get_option('lfi_nct_gmail_user', '');
    $pw   = get_option('lfi_nct_gmail_app_pw', '') !== '';
    $imap = function_exists('imap_open');
    $last = get_option('lfi_nct_mailcheck_last', []);

    echo '<ul class="lfi-app-list">';
    echo '<li class="lfi-app-card" style="border-left:4px solid ' . ($en ? '#186a3b' : '#999') . '"><div class="head"><div class="who">' . ($en ? '🟢 Activé' : '⚪ Désactivé') . '</div></div><div class="com">Le check tourne toutes les 4 h 30 sur le serveur.</div></li>';
    echo '<li class="lfi-app-card"><div class="meta"><span class="meta-chip">Boîte : ' . esc_html($user ?: '—') . '</span><span class="meta-chip">Mot de passe : ' . ($pw ? '✅ enregistré' : '❌ manquant') . '</span><span class="meta-chip">IMAP serveur : ' . ($imap ? '✅' : '❌ absent') . '</span></div></li>';
    if ($last) {
        echo '<li class="lfi-app-card"><div class="head"><div class="who">Dernier passage</div></div><div class="com">' . esc_html($last['at'] ?? '') . ' — ' . esc_html($last['msg'] ?? '') . ' (' . (int) ($last['traites'] ?? 0) . ' mail(s) vus, ' . (int) ($last['prepares'] ?? 0) . ' réponse(s) préparée(s))</div></li>';
    }
    echo '</ul>';
    if (!$imap) echo '<div class="lfi-app-help" style="background:#fff3cd;border-left:4px solid #d39e00"><small>⚠️ L\'extension PHP <code>imap</code> n\'est pas active sur l\'hébergement. À activer chez Hostinger (ou on passera par l\'API Gmail).</small></div>';
    echo '<div class="lfi-app-help"><small>Les réponses préparées apparaissent dans chaque dossier concerné, avec le bouton « Ouvrir dans Gmail ». Ce sont des brouillons auto à relire.</small></div>';
    lfi_nct_app_screen_close();
}
