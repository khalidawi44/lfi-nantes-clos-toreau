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
        $subject_raw = (string) imap_utf8((string) ($o->subject ?? ''));
        /* Clé anti-doublon PARTAGÉE avec le chemin « inbox » (push Apps Script) :
           Message-ID si présent, sinon empreinte de contenu (from+objet+jour).
           Un même email pêché en boucle (non ouvert dans Gmail) ne compte qu'une
           fois — et ne double pas non plus s'il est aussi poussé par Apps Script. */
        $mid = (function_exists('lfi_nct_inbox_dedup_key'))
            ? lfi_nct_inbox_dedup_key((string) ($o->from ?? ''), $subject_raw, (string) ($o->date ?? ''), '', (string) ($o->message_id ?? ''))
            : (string) ($o->message_id ?? ('uid' . $uid));
        if (in_array($mid, $seen, true)) continue;
        if (function_exists('lfi_nct_inbox_seen_mark') && lfi_nct_inbox_seen_mark($mid)) { $seen[] = $mid; continue; }
        $from = strtolower((string) ($o->from ?? ''));
        $to   = strtolower((string) ($o->to ?? ''));
        $cc   = strtolower((string) ($o->cc ?? ''));
        $hay  = $from . ' ' . $to . ' ' . $cc;
        $is_central = ((int) $box['referent'] === 0); /* la boîte collectrice dédiée */

        /* Liste noire (« boîte noire ») : expéditeur banni → on ignore et on
           marque vu pour ne plus jamais le réévaluer. */
        if (function_exists('lfi_nct_inbox_is_blocklisted') && function_exists('lfi_nct_inbox_emails')
            && lfi_nct_inbox_is_blocklisted(lfi_nct_inbox_emails($from))) { $seen[] = $mid; continue; }

        /* On capte : une réponse d'un expéditeur suivi (NMH/institution/avocat,
           en From OU To/Cc → les deux sens), OU un email impliquant un MEMBRE du
           GA (ex. Fabrice Doucet qui envoie) — pas seulement les réponses reçues. */
        $match = false;
        foreach ($senders as $s) if (strpos($hay, $s) !== false) { $match = true; break; }
        if (!$match && function_exists('lfi_nct_inbox_is_member_email') && function_exists('lfi_nct_inbox_emails')) {
            foreach (array_merge(lfi_nct_inbox_emails($from), lfi_nct_inbox_emails($to), lfi_nct_inbox_emails($cc)) as $a) {
                if (lfi_nct_inbox_is_member_email($a)) { $match = true; break; }
            }
        }
        /* Boîte collectrice : on ne JETTE rien — un email inconnu part en « à
           rattacher » (jamais perdu). Une boîte membre reste filtrée. */
        if (!$match && !$is_central) continue;

        $out['traites']++;
        $seen[] = $mid;

        $subject = (string) imap_utf8((string) ($o->subject ?? ''));
        $body = lfi_nct_mailcheck_body($mbox, $uid);
        /* Une boîte membre ne rattache QUE les dossiers dont il/elle est référent. */
        $dossier = lfi_nct_mailcheck_match_dossier($subject, $body, (int) $box['referent'], $from);
        if ($dossier) {
            lfi_nct_mailcheck_prepare_reply($dossier, $o, $subject, $body);
            /* PIÈCES JOINTES → rangées dans le dossier du locataire. */
            lfi_nct_mailcheck_import_attachments($mbox, $uid, (int) ($dossier->tenant_user_id ?? 0));
            $out['prepares']++;
        } elseif (function_exists('lfi_nct_inbox_unmatched')) {
            /* Aucun dossier trouvé → file « à rattacher » : l'email n'est PAS
               perdu, il remonte sur l'accueil et tu le ranges toi-même (le robot
               apprend l'adresse pour la prochaine fois). */
            $q = lfi_nct_inbox_unmatched();
            $dup = false;
            foreach ($q as $e) if ($mid !== '' && (($e['dedup'] ?? '') === $mid || ($e['message_id'] ?? '') === $mid)) { $dup = true; break; }
            if (!$dup) {
                /* On importe QUAND MÊME les pièces jointes (en attente) — une
                   photo ne doit JAMAIS être perdue parce que le locataire n'est
                   pas encore reconnu. Elles suivront l'email quand tu le rangeras. */
                $att_ids = lfi_nct_mailcheck_import_attachments($mbox, $uid, 0);
                $q[] = [
                    'id'         => (int) round(microtime(true) * 1000) + ($uid % 1000),
                    'from'       => (string) ($o->from ?? ''),
                    'to'         => (string) ($o->to ?? ''),
                    'cc'         => (string) ($o->cc ?? ''),
                    'objet'      => $subject,
                    'body'       => mb_substr($body, 0, 12000),
                    'message_id' => (string) ($o->message_id ?? ''),
                    'dedup'      => $mid,
                    'date'       => wp_date('Y-m-d H:i'),
                    'extrait'    => mb_substr($body, 0, 200),
                    'att_ids'    => array_map('intval', (array) $att_ids),
                    'src'        => 'mailcheck',
                ];
                lfi_nct_inbox_unmatched_save($q);
                $out['unmatched'] = ($out['unmatched'] ?? 0) + 1;
                $out['pieces'] = ($out['pieces'] ?? 0) + count($att_ids);
            }
        }
    }
    @imap_close($mbox);
    return $out;
}

function lfi_nct_mailcheck_log($rep) {
    update_option('lfi_nct_mailcheck_last', array_merge($rep, ['at' => current_time('mysql')]), false);
}

/**
 * REMISE À ZÉRO du pipeline d'import des emails — pour repartir propre avant une
 * relance de pêche. On efface UNIQUEMENT ce qui vient de l'import automatique :
 *   - la boîte de collecte (emails non rattachés) ;
 *   - la mémoire anti-doublon (IMAP + globale) → la pêche re-télécharge tout ;
 *   - dans CHAQUE dossier : les emails reçus/envoyés IMPORTÉS (src mailcheck/inbox)
 *     et les brouillons auto (replies src=mailcheck), + la mémoire inbox_seen ;
 *   - les PIÈCES JOINTES importées par email (tag « Pièce jointe email ») ;
 *   - les entrées de chronologie créées par un email (📥/📤).
 * On NE TOUCHE PAS : les enquêtes, mandats, chronologies reconstruites/saisies à
 * la main, les emails saisis manuellement, ni les brouillons rédigés par un membre.
 * @return array  Compteurs pour l'affichage.
 */
function lfi_nct_emails_full_reset() {
    global $wpdb;
    $rep = ['dossiers' => 0, 'emails' => 0, 'replies' => 0, 'pieces' => 0, 'chrono' => 0, 'boite' => 0];

    /* 1) Boîte de collecte + mémoires anti-doublon. */
    if (function_exists('lfi_nct_inbox_unmatched')) $rep['boite'] = count(lfi_nct_inbox_unmatched());
    update_option('lfi_nct_inbox_unmatched', [], false);
    update_option('lfi_nct_mailcheck_seen', [], false);
    update_option('lfi_nct_inbox_seen_global', [], false);

    /* 2) Chaque dossier : on retire les items IMPORTÉS de notes. */
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $rows = $wpdb->get_results("SELECT id, tenant_user_id, notes FROM $t");
    $imported = ['mailcheck', 'inbox'];
    foreach ((array) $rows as $r) {
        $notes = json_decode((string) $r->notes, true);
        if (!is_array($notes)) continue;
        $touched = false;
        foreach (['email_recu', 'email_log'] as $k) {
            if (!empty($notes[$k]) && is_array($notes[$k])) {
                $before = count($notes[$k]);
                $notes[$k] = array_values(array_filter($notes[$k], function ($e) use ($imported) {
                    return !in_array((string) ($e['src'] ?? ''), $imported, true);
                }));
                $rep['emails'] += $before - count($notes[$k]);
                if (count($notes[$k]) !== $before) $touched = true;
            }
        }
        if (!empty($notes['replies']) && is_array($notes['replies'])) {
            $before = count($notes['replies']);
            $notes['replies'] = array_values(array_filter($notes['replies'], function ($e) {
                return (string) ($e['src'] ?? '') !== 'mailcheck'; /* on garde les brouillons rédigés par un membre */
            }));
            $rep['replies'] += $before - count($notes['replies']);
            if (count($notes['replies']) !== $before) $touched = true;
        }
        if (isset($notes['inbox_seen'])) { unset($notes['inbox_seen']); $touched = true; }
        if ($touched) {
            $wpdb->update($t, ['notes' => wp_json_encode($notes), 'updated_at' => current_time('mysql')], ['id' => (int) $r->id]);
            $rep['dossiers']++;
        }

        /* 3) Chronologie : retirer UNIQUEMENT les entrées créées par un email. */
        $tuid = (int) ($r->tenant_user_id ?? 0);
        if ($tuid && function_exists('lfi_nct_chrono_get')) {
            $list = lfi_nct_chrono_get($tuid);
            if ($list) {
                $before = count($list);
                $list = array_values(array_filter($list, function ($e) {
                    $txt = (string) ($e['txt'] ?? '');
                    return !(strpos($txt, '📥 Email reçu') === 0 || strpos($txt, '📤 Email envoyé') === 0);
                }));
                if (count($list) !== $before) { lfi_nct_chrono_save($tuid, $list); $rep['chrono'] += $before - count($list); }
            }
        }
    }

    /* 4) Pièces jointes importées par email (tag distinctif). */
    $atts = get_posts([
        'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids',
        'meta_query' => [['key' => '_lfi_tenant_piece', 'value' => 'Pièce jointe email']],
    ]);
    foreach ((array) $atts as $aid) { if (wp_delete_attachment((int) $aid, true)) $rep['pieces']++; }

    update_option('lfi_nct_emails_reset_last', array_merge($rep, ['at' => current_time('mysql')]), false);
    return $rep;
}

/**
 * Importe les PIÈCES JOINTES (images / PDF) d'un email. Si $tenant_uid > 0, la
 * pièce est rangée dans le dossier du locataire (meta _lfi_tenant_user_id +
 * étape auto). Si $tenant_uid = 0 (email « à rattacher », locataire pas encore
 * connu), la pièce est quand même importée mais mise EN ATTENTE (_lfi_inbox_pending)
 * — elle sera rattachée au bon locataire quand tu rangeras l'email. On ne perd
 * JAMAIS une photo.
 * @return int[]  Les IDs des pièces créées.
 */
function lfi_nct_mailcheck_import_attachments($mbox, $uid, $tenant_uid) {
    $tenant_uid = (int) $tenant_uid;
    $struct = @imap_fetchstructure($mbox, $uid, FT_UID);
    if (!$struct) return [];
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $ids = [];
    $ok_ext = ['jpg', 'jpeg', 'png', 'heic', 'heif', 'webp', 'gif', 'pdf'];
    $walk = function ($parts, $prefix) use (&$walk, $mbox, $uid, $tenant_uid, &$ids, $ok_ext) {
        foreach ($parts as $i => $part) {
            $partno = ($prefix === '') ? (string) ($i + 1) : $prefix . '.' . ($i + 1);
            if (!empty($part->parts)) { $walk($part->parts, $partno); continue; }
            $filename = '';
            if (!empty($part->ifdparameters)) foreach ($part->dparameters as $d) { if (strtolower($d->attribute) === 'filename') $filename = $d->value; }
            if ($filename === '' && !empty($part->ifparameters)) foreach ($part->parameters as $p) { if (strtolower($p->attribute) === 'name') $filename = $p->value; }
            $is_att = (!empty($part->ifdisposition) && in_array(strtolower((string) $part->disposition), ['attachment', 'inline'], true)) || ((int) ($part->type ?? -1) === 5);
            if (!$is_att && $filename === '') continue;
            if ($filename === '') $filename = 'piece-' . $partno . '.' . strtolower((string) ($part->subtype ?? 'bin'));
            $filename = (string) imap_utf8($filename);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, $ok_ext, true)) continue;
            $raw = @imap_fetchbody($mbox, $uid, $partno, FT_UID | FT_PEEK);
            if ($raw === false || $raw === '') continue;
            $enc = (int) ($part->encoding ?? 0);
            if ($enc === 3) $raw = base64_decode($raw);
            elseif ($enc === 4) $raw = quoted_printable_decode($raw);
            if ($raw === '' || strlen($raw) > 15 * 1024 * 1024) continue;
            $up = wp_upload_dir();
            if (!empty($up['error'])) continue;
            $safe = wp_unique_filename($up['path'], sanitize_file_name($filename) ?: ('piece-' . $partno . '.' . $ext));
            $path = trailingslashit($up['path']) . $safe;
            if (file_put_contents($path, $raw) === false) continue;
            $ft  = wp_check_filetype($safe);
            $att = wp_insert_attachment(['post_mime_type' => $ft['type'] ?: 'application/octet-stream', 'post_title' => $safe, 'post_status' => 'private'], $path);
            if (is_wp_error($att) || !$att) { @unlink($path); continue; }
            wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $path));
            update_post_meta($att, '_lfi_tenant_user_id', $tenant_uid);
            update_post_meta($att, '_lfi_tenant_piece', 'Pièce jointe email');
            /* 🤖 Catégorie (toujours) + étape auto (seulement si on connaît le
               locataire). Sinon on met la pièce EN ATTENTE de rattachement. */
            if (function_exists('lfi_nct_piece_categorize')) {
                $cat = lfi_nct_piece_categorize($filename, (string) ($ft['type'] ?? ''));
                update_post_meta($att, '_lfi_piece_cat', $cat['cat']);
                if ($tenant_uid > 0 && function_exists('lfi_nct_piece_autostep')) {
                    $sk = lfi_nct_piece_autostep($tenant_uid, $cat['cat']);
                    if ($sk !== '') update_post_meta($att, '_lfi_step', $sk);
                }
            }
            if ($tenant_uid === 0) update_post_meta($att, '_lfi_inbox_pending', 1);
            if (function_exists('lfi_nct_store_capture_ts')) lfi_nct_store_capture_ts($att, $path);
            $ids[] = (int) $att;
        }
    };
    $parts = (!empty($struct->parts)) ? $struct->parts : [$struct];
    $walk($parts, '');
    return $ids;
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

    /* 1) Match par ADRESSE EMAIL (bien plus fiable que le nom) : une adresse
       présente dans l'email (from/objet/corps) qui correspond au tenant_email
       d'un dossier candidat → c'est lui. */
    $emails_in = [];
    if (preg_match_all('/[\w.\-+]+@[\w.\-]+\.[\w.\-]+/u', $from . ' ' . $subject . ' ' . $body, $mm)) {
        $emails_in = array_map('mb_strtolower', $mm[0]);
    }
    if ($emails_in) {
        foreach ($cands as $r) {
            $te = mb_strtolower(trim((string) ($r->tenant_email ?? '')));
            if ($te !== '' && in_array($te, $emails_in, true)) return $r;
        }
    }

    /* 2) On cherche le nom (mot entier) d'ABORD dans l'OBJET (le plus fiable), puis
       dans objet + corps. Bornes des deux côtés → évite « Ba » dans « bail ». */
    foreach ([$subject, $subject . ' ' . $body] as $txt) {
        $low = mb_strtolower((string) $txt);
        foreach ($cands as $r) {
            $nl = mb_strtolower(trim((string) $r->tenant_nom));
            if (preg_match('/(?<![\p{L}])' . preg_quote($nl, '/') . '(?![\p{L}])/u', $low)) return $r;
        }
    }
    /* REPLI : aucun AUTRE locataire nommé → si l'EXPÉDITEUR est lui-même un
       locataire avec son propre dossier logement (cas Fabrice : membre ET
       locataire apt 88), l'email concerne SON dossier → on classe chez lui. */
    if ($sender_uid) {
        foreach ($rows as $r) {
            if ((int) $r->tenant_user_id === $sender_uid) return $r;
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
    /* Auto-alimentation de la chronologie du dossier. */
    if (function_exists('lfi_nct_chrono_add_email')) {
        lfi_nct_chrono_add_email((int) ($row->tenant_user_id ?? 0), 'recu', $to, $subject, wp_date('Y-m-d'));
    }
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

/* Remise à zéro du pipeline d'import — bouton réutilisable (super-admin). */
add_action('admin_post_lfi_nct_emails_reset', 'lfi_nct_emails_reset_handler');
function lfi_nct_emails_reset_handler() {
    if (!current_user_can('manage_options')) wp_die('Non autorisé');
    check_admin_referer('lfi_nct_emails_reset');
    $rep = lfi_nct_emails_full_reset();
    set_transient('lfi_nct_reset_' . get_current_user_id(), $rep, 180);
    $back = wp_get_referer(); if (!$back) $back = lfi_nct_app_url('mailcheck');
    wp_safe_redirect(add_query_arg('reset', 1, remove_query_arg('reset', $back)));
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

    /* Enregistrer la clé API Claude (vraie IA) + le modèle + l'activation. */
    if (!empty($_POST['lfi_ai_cfg']) && check_admin_referer('lfi_ai_cfg')) {
        $k = trim((string) wp_unslash($_POST['claude_key'] ?? ''));
        /* On ne réécrit la clé que si l'utilisateur en a saisi une nouvelle
           (le champ affiche des points quand elle est déjà là → on ignore). */
        if ($k !== '' && strpos($k, '•') === false) update_option('lfi_nct_claude_api_key', $k, false);
        $mo = sanitize_text_field(wp_unslash($_POST['claude_model'] ?? 'claude-sonnet-5'));
        if (!array_key_exists($mo, lfi_nct_ai_models())) $mo = 'claude-sonnet-5';
        update_option('lfi_nct_claude_model', $mo, false);
        update_option('lfi_nct_claude_enabled', empty($_POST['claude_enabled']) ? '0' : '1', false);
        /* Test de connexion immédiat si demandé. */
        if (!empty($_POST['claude_test'])) {
            list($ok, $msg) = lfi_nct_ai_ping();
            set_transient('lfi_nct_ai_ping_' . get_current_user_id(), ['ok' => $ok, 'msg' => $msg], 120);
        }
        wp_safe_redirect(lfi_nct_app_url('mailcheck', ['aisaved' => 1]) . '#sec-ia'); exit;
    }

    lfi_nct_app_screen_open('📬 Check des emails', 'Automatique 24/7 + pêche à la demande');
    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Réglages enregistrés.');
    echo lfi_nct_mailcheck_peche_flash();

    $en   = get_option('lfi_nct_mailcheck_enabled') === '1';
    $user = (string) get_option('lfi_nct_gmail_user', '');
    $pw   = get_option('lfi_nct_gmail_app_pw', '') !== '';
    $imap = function_exists('imap_open');
    $last = get_option('lfi_nct_mailcheck_last', []);

    /* Flash remise à zéro. */
    if (!empty($_GET['reset'])) {
        $rr = get_transient('lfi_nct_reset_' . get_current_user_id());
        if (is_array($rr)) {
            delete_transient('lfi_nct_reset_' . get_current_user_id());
            lfi_nct_app_flash('🧹 Remise à zéro faite : ' . (int) $rr['emails'] . ' email(s) importé(s) retiré(s), ' . (int) $rr['replies'] . ' brouillon(s) auto, ' . (int) $rr['pieces'] . ' pièce(s) jointe(s), ' . (int) $rr['chrono'] . ' entrée(s) de chrono, boîte vidée. Tu peux relancer la pêche.');
        }
    }

    /* Pêche à la demande — le gros bouton, tout en haut. */
    echo '<div style="margin-bottom:12px">' . lfi_nct_mailcheck_run_button('🎣 Aller à la pêche maintenant') . '</div>';

    /* Remise à zéro du pipeline d'import (réutilisable, avec confirmation). */
    $reset_url = admin_url('admin-post.php');
    echo '<details style="margin-bottom:12px"><summary style="cursor:pointer;color:#c8102e;font-weight:600">🧹 Vider tous les emails importés (remise à zéro)</summary>'
        . '<div style="background:#fdeef0;border-left:4px solid #c8102e;border-radius:8px;padding:10px 12px;margin-top:8px">'
        . '<small>Efface la boîte de collecte, la mémoire anti-doublon et TOUS les emails + pièces jointes <strong>importés automatiquement</strong> dans les dossiers. Ne touche pas aux enquêtes, mandats, chronologies reconstruites/saisies à la main, ni aux brouillons que tu as rédigés. Ensuite tu relances la pêche pour tout reclasser proprement.</small>'
        . '<form method="post" action="' . esc_url($reset_url) . '" style="margin:8px 0 0" onsubmit="return confirm(\'Vider tous les emails importés et la boîte de collecte ? Les enquêtes et chronologies reconstruites ne bougent pas.\');">'
        . wp_nonce_field('lfi_nct_emails_reset', '_wpnonce', true, false)
        . '<input type="hidden" name="action" value="lfi_nct_emails_reset">'
        . '<button type="submit" class="btn-primary" style="background:#c8102e">🧹 Confirmer la remise à zéro</button></form></div></details>';

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

    /* ============================================================== *
     *  VRAIE IA CLAUDE — clé API + modèle                             *
     * ============================================================== */
    echo '<h3 id="sec-ia" style="margin:22px 0 6px">🤖 Intelligence artificielle Claude</h3>';
    if (!empty($_GET['aisaved'])) lfi_nct_app_flash('✅ Réglages IA enregistrés.');
    $ping = get_transient('lfi_nct_ai_ping_' . get_current_user_id());
    if (is_array($ping)) {
        delete_transient('lfi_nct_ai_ping_' . get_current_user_id());
        $c = !empty($ping['ok']) ? '#186a3b' : '#c8102e';
        $b = !empty($ping['ok']) ? '#eef7ee' : '#fdeef0';
        echo '<div style="background:' . $b . ';border-left:4px solid ' . $c . ';border-radius:10px;padding:10px 12px;margin-bottom:10px"><strong style="color:' . $c . '">' . (!empty($ping['ok']) ? '✅ ' : '⚠️ ') . esc_html($ping['msg']) . '</strong></div>';
    }

    $ai_on   = lfi_nct_ai_enabled();
    $ai_key  = lfi_nct_ai_key() !== '';
    $ai_mod  = lfi_nct_ai_model();
    $ai_err  = (string) get_option('lfi_nct_claude_last_error', '');
    $ai_use  = get_option('lfi_nct_claude_usage', []);

    echo '<div class="lfi-app-help">Sans clé, les robots tournent par <strong>mots-clés</strong> (basique). Avec ta clé Claude, la <strong>génération des réponses</strong> et le <strong>classement des emails</strong> passent en vraie IA. La clé reste sur TON serveur, jamais dans ce dépôt. C\'est ton compte Anthropic qui est facturé (~5 €/mois avec Sonnet pour ~400 emails).</div>';

    echo '<ul class="lfi-app-list">';
    echo '<li class="lfi-app-card" style="border-left:4px solid ' . ($ai_on ? '#186a3b' : '#999') . '"><div class="head"><div class="who">' . ($ai_on ? '🟢 IA Claude active' : ($ai_key ? '⚪ IA en pause' : '⚪ IA non configurée')) . '</div></div><div class="meta"><span class="meta-chip">Clé : ' . ($ai_key ? '✅ enregistrée' : '❌ manquante') . '</span><span class="meta-chip">Modèle : ' . esc_html($ai_mod) . '</span>';
    if (is_array($ai_use) && !empty($ai_use['calls'])) echo '<span class="meta-chip">Ce mois : ' . (int) $ai_use['calls'] . ' appel(s)</span>';
    echo '</div>';
    if ($ai_err !== '') echo '<div class="com" style="color:#c8102e">⚠️ Dernier souci : ' . esc_html($ai_err) . '</div>';
    echo '</li></ul>';

    echo '<form method="post" class="lfi-app-form" style="background:#f8f8f8;padding:12px;border-radius:10px" action="' . esc_url(lfi_nct_app_url('mailcheck')) . '#sec-ia">' . wp_nonce_field('lfi_ai_cfg', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_ai_cfg" value="1">';
    echo '<label>🔑 Clé API Claude (commence par <code>sk-ant-</code>)<input type="password" name="claude_key" autocomplete="off" value="" placeholder="' . ($ai_key ? '•••••••••••• (déjà enregistrée — laisser vide pour garder)' : 'sk-ant-...') . '"></label>';
    echo '<label>🧠 Modèle<select name="claude_model">';
    foreach (lfi_nct_ai_models() as $mk => $ml) echo '<option value="' . esc_attr($mk) . '" ' . selected($ai_mod, $mk, false) . '>' . esc_html($ml) . '</option>';
    echo '</select></label>';
    echo '<label style="display:flex;gap:8px;align-items:center;margin-top:4px"><input type="checkbox" name="claude_enabled" value="1" ' . checked(get_option('lfi_nct_claude_enabled', '1') === '1', true, false) . '> <span>Activer la vraie IA Claude</span></label>';
    echo '<label style="display:flex;gap:8px;align-items:center;margin-top:2px"><input type="checkbox" name="claude_test" value="1"> <span>Tester la connexion en enregistrant</span></label>';
    echo '<button type="submit" class="btn-primary">💾 Enregistrer la clé Claude</button></form>';
    echo '<div class="lfi-app-help"><small>La clé se crée sur <strong>console.anthropic.com → API Keys → Create Key</strong>. Pense à ajouter du crédit (<strong>Billing → Add credits</strong>) et, si tu veux, un plafond mensuel (<strong>Usage limits</strong>).</small></div>';

    lfi_nct_app_screen_close();
}
