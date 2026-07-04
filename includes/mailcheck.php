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
    $rep = ['ok' => false, 'traites' => 0, 'prepares' => 0, 'unmatched' => 0, 'boxes' => 0, 'msg' => ''];
    if (!function_exists('imap_open')) { $rep['msg'] = 'Extension PHP imap absente sur le serveur.'; lfi_nct_mailcheck_log($rep); return $rep; }
    $boxes = lfi_nct_mailcheck_boxes();
    if (empty($boxes)) { $rep['msg'] = 'Aucune boîte configurée.'; lfi_nct_mailcheck_log($rep); return $rep; }

    $seen = get_option('lfi_nct_mailcheck_seen', []);
    if (!is_array($seen)) $seen = [];
    $errors = [];
    foreach ($boxes as $box) {
        $r = lfi_nct_mailcheck_scan_box($box, $seen);
        $rep['traites']   += $r['traites'];
        $rep['prepares']  += $r['prepares'];
        $rep['unmatched'] += ($r['unmatched'] ?? 0);
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
        $dossier = lfi_nct_mailcheck_match_dossier($subject, $body, (int) $box['referent'], $from);
        if ($dossier) {
            lfi_nct_mailcheck_prepare_reply($dossier, $o, $subject, $body);
            $out['prepares']++;
        } elseif (function_exists('lfi_nct_inbox_unmatched')) {
            /* Aucun dossier trouvé → file « à rattacher » : l'email n'est PAS
               perdu, il remonte sur l'accueil et tu le ranges toi-même (le robot
               apprend l'adresse pour la prochaine fois). */
            $q = lfi_nct_inbox_unmatched();
            $dup = false;
            foreach ($q as $e) if ($mid !== '' && ($e['message_id'] ?? '') === $mid) { $dup = true; break; }
            if (!$dup) {
                $q[] = [
                    'id'         => (int) round(microtime(true) * 1000) + ($uid % 1000),
                    'from'       => (string) ($o->from ?? ''),
                    'to'         => (string) ($o->to ?? ''),
                    'cc'         => (string) ($o->cc ?? ''),
                    'objet'      => $subject,
                    'body'       => mb_substr($body, 0, 12000),
                    'message_id' => $mid,
                    'date'       => wp_date('Y-m-d H:i'),
                    'extrait'    => mb_substr($body, 0, 200),
                    'src'        => 'mailcheck',
                ];
                lfi_nct_inbox_unmatched_save($q);
                $out['unmatched'] = ($out['unmatched'] ?? 0) + 1;
            }
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
 * Retire l'HISTORIQUE CITÉ d'un email de réponse (le « > » et le bloc
 * « Le … a écrit : »). Sans ça, la réponse de NMH importée contenait AUSSI
 * notre propre message cité en dessous → on croyait que « notre réponse »
 * s'était importée au lieu de la sienne. On ne garde que le message NEUF.
 */
function lfi_nct_mailcheck_strip_quote($body) {
    $body = (string) $body;
    if ($body === '') return $body;
    /* Séparateurs de citation les plus courants (FR/EN, Gmail/Outlook). */
    $seps = [
        '/^\s*Le\s.+\sa\s.crit\s*:.*$/mu',              // Gmail FR : « Le 3 juil. 2026 …, X a écrit : »
        '/^\s*On\s.+\swrote:.*$/mu',                     // Gmail EN
        '/^\s*-{2,}\s*Message d\'origine\s*-{2,}.*$/miu', // Outlook FR
        '/^\s*-{2,}\s*Original Message\s*-{2,}.*$/miu',   // Outlook EN
        '/^\s*_{5,}\s*$/mu',                              // Outlook séparateur
        '/^\s*De\s*:.*$/mu',                             // en-tête Outlook FR (De : … Envoyé : …)
        '/^\s*From:.*$/mu',                              // en-tête Outlook EN
        '/^\s*>.*$/mu',                                  // lignes citées « > »
        '/^\s*Envoy.\sdepuis\smon\s.+$/miu',             // signatures mobiles
    ];
    $cut = mb_strlen($body);
    foreach ($seps as $re) {
        if (preg_match($re, $body, $m, PREG_OFFSET_CAPTURE)) {
            /* offset en octets → position caractère. */
            $pos = mb_strlen(substr($body, 0, $m[0][1]));
            if ($pos < $cut) $cut = $pos;
        }
    }
    $new = trim(mb_substr($body, 0, $cut));
    /* Garde-fou : si on a presque tout coupé, on garde l'original (mieux vaut
       trop que rien). */
    return (mb_strlen($new) >= 15) ? $new : trim($body);
}

/**
 * Trouve le dossier concerné (nom du locataire présent dans le texte).
 * $referent > 0 : on ne cherche que parmi les dossiers de ce membre.
 * $from : en-tête « De » de l'email — sert à ÉCARTER le dossier de l'EXPÉDITEUR
 *   (le membre qui envoie, ex. fabrice.doucet44, n'est pas le locataire concerné,
 *   même si un dossier porte exactement son nom). On cherche d'abord dans l'OBJET.
 */
function lfi_nct_mailcheck_match_dossier($subject, $body = '', $referent = 0, $from = '') {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    if ($referent > 0) {
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE referent_user_id = %d ORDER BY updated_at DESC LIMIT 200", $referent)) ?: [];
    } else {
        $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY updated_at DESC LIMIT 200") ?: [];
    }

    /* Identité de l'EXPÉDITEUR → à écarter des candidats. */
    $sender_uid = 0; $sender_nom = '';
    if ($from !== '') {
        if (preg_match('/[\w.\-+]+@[\w.\-]+/', $from, $m)) {
            $su = get_user_by('email', $m[0]);
            if ($su) { $sender_uid = (int) $su->ID; $sender_nom = mb_strtolower(trim((string) ($su->last_name ?: $su->display_name))); }
        }
        if ($sender_nom === '' && preg_match('/^\s*"?([^"<]+?)"?\s*</u', $from, $mm)) $sender_nom = mb_strtolower(trim($mm[1]));
    }

    /* Candidats = tous les dossiers SAUF celui de l'expéditeur (par compte OU par
       nom identique) : on ne classe jamais l'email dans le dossier de celui qui
       l'envoie — c'est le locataire nommé dans le message qui compte. */
    $cands = [];
    foreach ($rows as $r) {
        $nom = trim((string) $r->tenant_nom);
        if ($nom === '' || mb_strlen($nom) < 2) continue;
        if ($sender_uid && (int) $r->tenant_user_id === $sender_uid) continue;
        if ($sender_nom !== '' && mb_strtolower($nom) === $sender_nom) continue;
        $cands[] = $r;
    }

    /* On cherche le nom (mot entier) d'ABORD dans l'OBJET (le plus fiable), puis
       dans objet + corps. Bornes des deux côtés → évite « Ba » dans « bail ». */
    foreach ([$subject, $subject . ' ' . $body] as $txt) {
        $low = mb_strtolower((string) $txt);
        foreach ($cands as $r) {
            $nl = mb_strtolower(trim((string) $r->tenant_nom));
            if (preg_match('/(?<![\p{L}])' . preg_quote($nl, '/') . '(?![\p{L}])/u', $low)) return $r;
        }
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

    /* On ne garde QUE le message neuf de l'expéditeur (sans notre propre message
       cité en dessous) → l'email reçu affiché et l'analyse portent sur SA
       réponse, pas sur la nôtre. */
    $body_new = lfi_nct_mailcheck_strip_quote($body);

    $rep_subject = (stripos($subject, 'Re:') === 0) ? $subject : ('Re: ' . $subject);
    $posture = '';
    if (function_exists('lfi_nct_psy_analyse')) {
        $r = lfi_nct_psy_analyse($body_new, 'institution');
        $posture = $r['label'] . ' — ton conseillé : ' . $r['ton'];
    }
    $nom = trim($row->tenant_prenom . ' ' . $row->tenant_nom);
    /* Signataire = le référent du dossier (le membre qui gère), sinon Fabrice. */
    $ref_id = (int) ($row->referent_user_id ?? 0);
    $ref_u  = $ref_id ? get_userdata($ref_id) : null;
    $signataire = $ref_u ? ($ref_u->display_name ?: $ref_u->user_login) : 'Fabrice Doucet';
    /* Volet pénal (règle) : détecter intimidation / contournement illégal du
       message reçu et insérer un paragraphe de désamorçage dans la réponse. */
    $penal = function_exists('lfi_nct_penal_paragraphe') ? lfi_nct_penal_paragraphe($body_new) : '';
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
    $notes['email_recu'][] = ['date' => wp_date('Y-m-d'), 'de' => $to, 'objet' => $subject, 'corps' => mb_substr($body_new, 0, 2000), 'src' => 'mailcheck'];
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
/* Bouton « Aller à la pêche maintenant » — utilisable depuis N'IMPORTE quelle
   page (l'accueil, l'écran mailcheck). Lance le check à la demande et revient. */
add_action('admin_post_lfi_nct_mailcheck_run', 'lfi_nct_mailcheck_run_handler');
function lfi_nct_mailcheck_run_handler() {
    if (!current_user_can('manage_options')) wp_die('Non autorisé');
    check_admin_referer('lfi_nct_mailcheck_run');
    $rep = lfi_nct_mailcheck_do();
    set_transient('lfi_nct_peche_' . get_current_user_id(), $rep, 180);
    $back = wp_get_referer();
    if (!$back) $back = lfi_nct_app_url();
    wp_safe_redirect(add_query_arg('peche', 1, remove_query_arg('peche', $back)));
    exit;
}

/** Le petit bouton « pêche maintenant » (formulaire POST vers admin-post). */
function lfi_nct_mailcheck_run_button($label = '🎣 Aller à la pêche maintenant', $bg = '#0066a3') {
    if (!current_user_can('manage_options')) return '';
    $u = admin_url('admin-post.php');
    return '<form method="post" action="' . esc_url($u) . '" style="margin:0">'
        . wp_nonce_field('lfi_nct_mailcheck_run', '_wpnonce', true, false)
        . '<input type="hidden" name="action" value="lfi_nct_mailcheck_run">'
        . '<button type="submit" class="btn-primary" style="background:' . esc_attr($bg) . '">' . esc_html($label) . '</button></form>';
}

/** Rapport de la dernière pêche manuelle (transient éphémère) → HTML ou ''. */
function lfi_nct_mailcheck_peche_flash() {
    if (empty($_GET['peche'])) return '';
    $rep = get_transient('lfi_nct_peche_' . get_current_user_id());
    if (!is_array($rep)) return '';
    delete_transient('lfi_nct_peche_' . get_current_user_id());
    $ok  = !empty($rep['ok']);
    $col = $ok ? '#186a3b' : '#c8102e';
    $bg  = $ok ? '#eef7ee' : '#fdeef0';
    $txt = ($ok ? '✅ Pêche terminée' : '⚠️ Pêche : ' . esc_html($rep['msg'] ?? 'souci'));
    $det = (int) ($rep['traites'] ?? 0) . ' mail(s) vus · ' . (int) ($rep['prepares'] ?? 0) . ' réponse(s) préparée(s) · ' . (int) ($rep['unmatched'] ?? 0) . ' à rattacher · ' . (int) ($rep['boxes'] ?? 0) . ' boîte(s) lue(s)';
    return '<div style="background:' . $bg . ';border-left:4px solid ' . $col . ';border-radius:10px;padding:10px 12px;margin-bottom:12px"><strong style="color:' . $col . '">' . $txt . '</strong><div style="font-size:.9em;color:#444;margin-top:2px">' . $det . '</div></div>';
}

function lfi_nct_app_view_mailcheck() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    /* Enregistrer la boîte + le mot de passe d'application + l'activation. */
    if (!empty($_POST['lfi_mailcheck_cfg']) && check_admin_referer('lfi_mailcheck_cfg')) {
        update_option('lfi_nct_gmail_user', sanitize_email(wp_unslash($_POST['gmail_user'] ?? '')), false);
        $ppw = (string) wp_unslash($_POST['app_pw'] ?? '');
        if ($ppw !== '' && strpos($ppw, '•') === false) update_option('lfi_nct_gmail_app_pw', str_replace(' ', '', $ppw), false);
        update_option('lfi_nct_mailcheck_enabled', empty($_POST['enabled']) ? '0' : '1', false);
        if (function_exists('lfi_nct_mailcheck_cron_setup')) lfi_nct_mailcheck_cron_setup();
        wp_safe_redirect(lfi_nct_app_url('mailcheck', ['saved' => 1])); exit;
    }

    lfi_nct_app_screen_open('📬 Check des emails', 'Automatique 24/7 + pêche à la demande');
    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Réglages enregistrés.');
    echo lfi_nct_mailcheck_peche_flash();

    $en   = get_option('lfi_nct_mailcheck_enabled') === '1';
    $user = (string) get_option('lfi_nct_gmail_user', '');
    $pw   = get_option('lfi_nct_gmail_app_pw', '') !== '';
    $imap = function_exists('imap_open');
    $last = get_option('lfi_nct_mailcheck_last', []);

    /* Pêche à la demande — le gros bouton, tout en haut. */
    echo '<div style="margin-bottom:12px">' . lfi_nct_mailcheck_run_button('🎣 Aller à la pêche maintenant') . '</div>';

    echo '<ul class="lfi-app-list">';
    echo '<li class="lfi-app-card" style="border-left:4px solid ' . ($en ? '#186a3b' : '#999') . '"><div class="head"><div class="who">' . ($en ? '🟢 Surveillance auto activée' : '⚪ Surveillance auto désactivée') . '</div></div><div class="com">Le check tourne tout seul toutes les 4 h 30 sur le serveur — et tu peux pêcher à la main quand tu veux (bouton ci-dessus).</div></li>';
    echo '<li class="lfi-app-card"><div class="meta"><span class="meta-chip">Boîte : ' . esc_html($user ?: '—') . '</span><span class="meta-chip">Mot de passe : ' . ($pw ? '✅ enregistré' : '❌ manquant') . '</span><span class="meta-chip">IMAP serveur : ' . ($imap ? '✅' : '❌ absent') . '</span></div></li>';
    if ($last) {
        echo '<li class="lfi-app-card"><div class="head"><div class="who">Dernier passage</div></div><div class="com">' . esc_html($last['at'] ?? '') . ' — ' . esc_html($last['msg'] ?? '') . ' (' . (int) ($last['traites'] ?? 0) . ' mail(s) vus, ' . (int) ($last['prepares'] ?? 0) . ' réponse(s) préparée(s))</div></li>';
    }
    echo '</ul>';

    /* Réglages boîte (indispensable pour que la pêche marche). */
    echo '<h3 style="margin:14px 0 6px">⚙️ Réglages de la boîte</h3>';
    echo '<form method="post" class="lfi-app-form" style="background:#f8f8f8;padding:12px;border-radius:10px">' . wp_nonce_field('lfi_mailcheck_cfg', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_mailcheck_cfg" value="1">';
    echo '<label>📮 Boîte Gmail de l\'association<input type="email" name="gmail_user" value="' . esc_attr($user) . '" placeholder="nantessudclostoreau@gmail.com"></label>';
    echo '<label>🔑 Mot de passe d\'application Gmail (16 lettres)<input type="text" name="app_pw" autocomplete="off" value="" placeholder="' . ($pw ? '•••••••••••••••• (déjà enregistré — laisser vide pour garder)' : 'xxxx xxxx xxxx xxxx') . '"></label>';
    echo '<label style="display:flex;gap:8px;align-items:center;margin-top:4px"><input type="checkbox" name="enabled" value="1" ' . checked($en, true, false) . '> <span>Activer la surveillance automatique (toutes les 4 h 30)</span></label>';
    echo '<button type="submit" class="btn-primary">💾 Enregistrer</button></form>';
    echo '<div class="lfi-app-help"><small>Le mot de passe d\'application se crée dans le compte Google de la boîte : <strong>Gérer le compte → Sécurité → Validation en 2 étapes → Mots de passe des applications</strong>. Ce n\'est pas le mot de passe habituel.</small></div>';

    if (!$imap) echo '<div class="lfi-app-help" style="background:#fff3cd;border-left:4px solid #d39e00"><small>⚠️ L\'extension PHP <code>imap</code> n\'est pas active sur l\'hébergement : tant qu\'elle n\'est pas activée (chez Hostinger), la pêche IMAP ne peut pas lire la boîte. C\'est probablement pourquoi rien n\'est remonté.</small></div>';

    /* Ce que la pêche attrape / n'attrape pas — pour ne pas se tromper. */
    echo '<div class="lfi-app-help" style="background:#eef4fb;border-left:4px solid #0066a3"><small>ℹ️ Cette pêche surveille les <strong>réponses de NMH, des institutions et des avocats</strong> (elle prépare un brouillon dans le bon dossier). Un email que <em>tu</em> t\'envoies depuis ta propre boîte pour tester ne sera pas reconnu ici. Les <strong>pièces jointes / photos</strong> ne sont pas encore importées automatiquement (seul le texte l\'est).</small></div>';

    lfi_nct_app_screen_close();
}
