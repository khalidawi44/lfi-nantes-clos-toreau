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

/**
 * Boîtes à surveiller : la boîte CENTRALE (tous les dossiers) + une boîte par
 * MEMBRE (limitée à SES locataires attribués). Les identifiants (mots de passe
 * d'application) sont stockés en option côté serveur, jamais dans Git.
 */
function lfi_nct_mailcheck_boxes() {
    $boxes = [];
    $cu = (string) get_option('lfi_nct_gmail_user', '');
    $cp = str_replace(' ', '', (string) get_option('lfi_nct_gmail_app_pw', ''));
    if ($cu !== '' && $cp !== '') {
        $boxes[] = ['user' => $cu, 'pw' => $cp, 'referent' => 0, 'label' => 'centrale'];
    }
    $members = get_option('lfi_nct_member_mailboxes', []);
    if (is_array($members)) {
        foreach ($members as $m) {
            if (empty($m['enabled'])) continue;
            $u = (string) ($m['email'] ?? '');
            $p = str_replace(' ', '', (string) ($m['app_pw'] ?? ''));
            $ref = (int) ($m['user_id'] ?? 0);
            if ($u !== '' && $p !== '') {
                $boxes[] = ['user' => $u, 'pw' => $p, 'referent' => $ref, 'label' => $u];
            }
        }
    }
    return $boxes;
}

/** Le check lui-même (appelé par le cron, ou manuellement). Renvoie un rapport. */
function lfi_nct_mailcheck_do() {
    $rep = ['ok' => false, 'traites' => 0, 'prepares' => 0, 'boxes' => 0, 'msg' => ''];
    if (!function_exists('imap_open')) { $rep['msg'] = 'Extension PHP imap absente sur le serveur.'; lfi_nct_mailcheck_log($rep); return $rep; }
    $boxes = lfi_nct_mailcheck_boxes();
    if (empty($boxes)) { $rep['msg'] = 'Aucune boîte configurée.'; lfi_nct_mailcheck_log($rep); return $rep; }

    $seen = get_option('lfi_nct_mailcheck_seen', []);
    if (!is_array($seen)) $seen = [];
    $errors = [];
    foreach ($boxes as $box) {
        $r = lfi_nct_mailcheck_scan_box($box, $seen);
        $rep['traites']  += $r['traites'];
        $rep['prepares'] += $r['prepares'];
        $rep['boxes']++;
        if ($r['error'] !== '') $errors[] = $box['label'] . ' : ' . $r['error'];
    }

    /* On borne l'historique des vus. */
    if (count($seen) > 800) $seen = array_slice($seen, -800);
    update_option('lfi_nct_mailcheck_seen', $seen, false);
    $rep['ok']  = empty($errors);
    $rep['msg'] = $errors ? implode(' | ', $errors) : 'Terminé.';
    lfi_nct_mailcheck_log($rep);
    return $rep;
}

/** Scanne UNE boîte ; $seen est partagé (passé par référence) et mis à jour. */
function lfi_nct_mailcheck_scan_box($box, &$seen) {
    $out = ['traites' => 0, 'prepares' => 0, 'error' => ''];
    /* /novalidate-cert : contourne le bug SNI de certains clients PHP IMAP
       (la connexion reste chiffrée SSL). */
    $mbox = @imap_open('{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX', $box['user'], $box['pw'], 0, 1);
    if (!$mbox) { $out['error'] = 'connexion IMAP échouée : ' . imap_last_error(); return $out; }

    $since = date('d-M-Y', strtotime('-3 days'));
    $ids = @imap_search($mbox, 'UNSEEN SINCE "' . $since . '"', SE_UID) ?: [];
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

        $out['traites']++;
        $seen[] = $mid;

        $subject = (string) imap_utf8((string) ($o->subject ?? ''));
        $body = lfi_nct_mailcheck_body($mbox, $uid);
        /* Une boîte membre ne rattache QUE les dossiers dont il/elle est référent. */
        $dossier = lfi_nct_mailcheck_match_dossier($subject . ' ' . $body, (int) $box['referent']);
        if ($dossier) {
            lfi_nct_mailcheck_prepare_reply($dossier, $o, $subject, $body);
            $out['prepares']++;
        }
    }
    @imap_close($mbox);
    return $out;
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

/**
 * Trouve le dossier concerné (nom du locataire présent dans le texte).
 * $referent > 0 : on ne cherche que parmi les dossiers de ce membre.
 */
function lfi_nct_mailcheck_match_dossier($text, $referent = 0) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    if ($referent > 0) {
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE referent_user_id = %d ORDER BY updated_at DESC LIMIT 200", $referent)) ?: [];
    } else {
        $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY updated_at DESC LIMIT 200") ?: [];
    }
    $low = mb_strtolower($text);
    foreach ($rows as $r) {
        $nom = trim((string) $r->tenant_nom);
        if ($nom === '' || mb_strlen($nom) < 2) continue;
        /* Le nom doit apparaître comme MOT ENTIER (bornes des deux côtés) : évite
           qu'un nom court (« Ba », « Roy ») matche « bail », « Royan »… et classe
           le courrier dans le mauvais dossier. */
        if (preg_match('/(?<![\p{L}])' . preg_quote(mb_strtolower($nom), '/') . '(?![\p{L}])/u', $low)) return $r;
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
    /* Signataire = le référent du dossier (le membre qui gère), sinon Fabrice. */
    $ref_id = (int) ($row->referent_user_id ?? 0);
    $ref_u  = $ref_id ? get_userdata($ref_id) : null;
    $signataire = $ref_u ? ($ref_u->display_name ?: $ref_u->user_login) : 'Fabrice Doucet';
    /* Volet pénal (règle) : détecter intimidation / contournement illégal du
       message reçu et insérer un paragraphe de désamorçage dans la réponse. */
    $penal = function_exists('lfi_nct_penal_paragraphe') ? lfi_nct_penal_paragraphe($body) : '';
    $reply = "Madame, Monsieur,\n\n"
        . "En accompagnement de " . $nom . ", à sa demande et en qualité d'interlocuteur unique, je reviens vers vous.\n\n"
        . "[BROUILLON AUTOMATIQUE À RELIRE ET COMPLÉTER]\n"
        . "- Je rappelle que je suis l'interlocuteur unique de la personne accompagnée ; tout contact et tout accès au logement se font par mon intermédiaire et en ma présence.\n"
        . "- Sur le fond : un dysfonctionnement a été constaté et signalé. Il vous appartient d'intervenir/de constater ; je vous demande de me communiquer une date.\n\n"
        . ($penal !== '' ? $penal . "\n\n" : '')
        . "(Complétez ici les points précis selon le message reçu, puis envoyez.)\n\n"
        . "Cordialement,\n" . $signataire . "\nInterlocuteur unique de " . $nom . "\nGroupe d'Action La France Insoumise Nantes Sud – Clos Toreau\nAssociation Union des Quartiers Libres";

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
