<?php
/**
 * AGENDA SYNC — mettre les événements dans l'agenda des membres.
 *
 * Objectif (demandé) : quand un événement est créé, il arrive dans l'agenda
 * de chaque membre du GA, avec un RAPPEL et une notification.
 *
 * ⚠️ Réalité technique honnête : le mot de passe d'application Gmail qu'on
 * utilise pour LIRE les boîtes ne permet PAS d'ÉCRIRE dans Google Agenda.
 * L'insertion 100 % silencieuse exigerait un branchement OAuth (chaque membre
 * reconnecte son compte en autorisant l'agenda). En attendant, on fait le plus
 * fiable et immédiat, qui marche avec juste leur email :
 *
 *   → On envoie à chaque membre une INVITATION CALENDRIER (.ics) par email.
 *     Gmail la détecte et propose/ajoute l'événement à Google Agenda, avec le
 *     rappel embarqué (VALARM). Le membre reçoit une notification tout de suite.
 *
 * Garde-fous : on n'envoie QUE pour les nouveaux événements à venir, une seule
 * fois par événement, et jamais rétroactivement pour ceux déjà en base.
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_AGENDA_SENT_META = '_lfi_evt_ics_sent';
const LFI_NCT_AGENDA_BASELINE  = 'lfi_nct_agenda_baseline_done';

/* -------------------------------------------------------------- *
 *  Baseline : au premier chargement, on marque TOUS les événements *
 *  existants comme « déjà envoyés » → aucun email rétroactif.      *
 * -------------------------------------------------------------- */
add_action('init', 'lfi_nct_agenda_baseline', 60);
function lfi_nct_agenda_baseline() {
    if (get_option(LFI_NCT_AGENDA_BASELINE) === '1') return;
    if (!function_exists('lfi_nct_event_cpt')) return;
    $cpt = lfi_nct_event_cpt();
    if (!post_type_exists($cpt)) return;
    $ids = get_posts(['post_type' => $cpt, 'post_status' => 'any', 'numberposts' => 500, 'fields' => 'ids']);
    foreach ($ids as $id) {
        if (get_post_meta($id, LFI_NCT_AGENDA_SENT_META, true) === '') {
            update_post_meta($id, LFI_NCT_AGENDA_SENT_META, '1');
        }
    }
    update_option(LFI_NCT_AGENDA_BASELINE, '1', false);
}

/* -------------------------------------------------------------- *
 *  Déclencheur : un événement passe en « publish » (donc créé).   *
 * -------------------------------------------------------------- */
add_action('transition_post_status', 'lfi_nct_agenda_on_publish', 20, 3);
function lfi_nct_agenda_on_publish($new_status, $old_status, $post) {
    if ($new_status !== 'publish') return;
    if (!is_a($post, 'WP_Post')) return;
    if (!function_exists('lfi_nct_event_cpt') || $post->post_type !== lfi_nct_event_cpt()) return;
    if (get_post_meta($post->ID, LFI_NCT_AGENDA_SENT_META, true) === '1') return;

    /* On attend que les métas de date soient posées : on diffère d'un tick. */
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    /* Marque tout de suite pour éviter tout double-envoi concurrent. */
    update_post_meta($post->ID, LFI_NCT_AGENDA_SENT_META, '1');

    /* Envoi (best-effort ; ne bloque jamais l'enregistrement). */
    lfi_nct_agenda_send_invites($post->ID);
}

/**
 * Envoie l'invitation .ics à tous les membres abonnés. Renvoie le nb d'emails.
 * $force = renvoyer même si déjà marqué (bouton admin).
 */
function lfi_nct_agenda_send_invites($event_id, $force = false) {
    if (!function_exists('lfi_nct_event_data')) return 0;
    $d = lfi_nct_event_data($event_id);
    if (!$d || empty($d['date'])) return 0;
    if (!empty($d['is_past']) && !$force) return 0;

    $ics = lfi_nct_agenda_build_ics($d);
    if ($ics === '') return 0;

    /* Fichier .ics temporaire pour la pièce jointe. */
    $up = wp_upload_dir();
    $dir = trailingslashit($up['basedir']) . 'lfi-ics';
    if (!is_dir($dir)) wp_mkdir_p($dir);
    $file = $dir . '/evenement-' . (int) $event_id . '.ics';
    file_put_contents($file, $ics);

    $recipients = lfi_nct_agenda_member_emails();
    if (empty($recipients)) { @unlink($file); return 0; }

    $subject = '📅 Nouvel événement : ' . $d['titre'];
    $when = ucfirst((string) ($d['date_complete'] ?: $d['date']));
    $lieu = $d['lieu'] ? ($d['adresse'] && $d['adresse'] !== $d['lieu'] ? $d['lieu'] . ' — ' . $d['adresse'] : $d['lieu']) : '';
    $body = '<div style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;max-width:520px;margin:auto">'
          . '<div style="background:#c8102e;color:#fff;padding:16px 20px;border-radius:12px 12px 0 0;font-weight:800;font-size:1.1em">📅 Nouvel événement du GA</div>'
          . '<div style="border:1px solid #eee;border-top:0;border-radius:0 0 12px 12px;padding:18px 20px">'
          . '<div style="font-size:1.15em;font-weight:800;color:#1a1a1a">' . esc_html($d['titre']) . '</div>'
          . '<div style="margin-top:8px">🗓 <strong>' . esc_html($when) . '</strong></div>'
          . ($lieu ? '<div style="margin-top:4px">📍 ' . esc_html($lieu) . '</div>' : '')
          . '<div style="margin-top:14px;padding:12px 14px;background:#eef7ee;border-radius:10px">📎 <strong>Ajoute-le à ton agenda :</strong> ouvre la pièce jointe (fichier <em>.ics</em>). Sur Gmail / Google Agenda, il s\'ajoute en un tap, avec un <strong>rappel</strong> la veille et 2 h avant.</div>'
          . '<div style="margin-top:14px"><a href="' . esc_url($d['url']) . '" style="display:inline-block;background:#186a3b;color:#fff;text-decoration:none;padding:11px 18px;border-radius:10px;font-weight:800">Voir l\'événement</a></div>'
          . '<div style="margin-top:14px;font-size:.82em;color:#888">Tu reçois cet email en tant que membre du Groupe d\'Action LFI Nantes Sud Clos Toreau.</div>'
          . '</div></div>';

    add_filter('wp_mail_content_type', 'lfi_nct_agenda_html_ct');
    add_filter('wp_mail_from_name', 'lfi_nct_agenda_from_name');
    $sent = 0;
    foreach ($recipients as $email) {
        $ok = wp_mail($email, $subject, $body, [], [$file]);
        if ($ok) $sent++;
    }
    remove_filter('wp_mail_content_type', 'lfi_nct_agenda_html_ct');
    remove_filter('wp_mail_from_name', 'lfi_nct_agenda_from_name');

    @unlink($file);
    return $sent;
}
function lfi_nct_agenda_html_ct()   { return 'text/html'; }
function lfi_nct_agenda_from_name() { return 'LFI Nantes Sud Clos Toreau'; }

/** Emails des membres abonnés (même périmètre que l'outil Email blast). */
function lfi_nct_agenda_member_emails() {
    global $wpdb;
    $mem = $wpdb->prefix . 'lfi_nct_membres';
    if ($wpdb->get_var("SHOW TABLES LIKE '$mem'") !== $mem) return [];
    $rows = $wpdb->get_col("SELECT DISTINCT email FROM $mem WHERE email <> '' AND abonne_emails = 1 AND jetable = 0");
    $out = [];
    foreach ((array) $rows as $e) {
        $e = sanitize_email(trim((string) $e));
        if ($e !== '' && is_email($e)) $out[] = $e;
    }
    return array_values(array_unique($out));
}

/** Construit le contenu d'un fichier .ics (VEVENT + rappels). */
function lfi_nct_agenda_build_ics($d) {
    $date = $d['date'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return '';

    /* Heures : « 15h00 » → 150000. Fin = heure_fin ou +2h. */
    $to_hms = function ($h) {
        $h = preg_replace('/[^\d]/', '', str_replace('h', ':', (string) $h));
        if ($h === '') return '';
        $h = str_pad($h, 4, '0'); /* HHmm */
        $hh = substr($h, 0, 2); $mm = substr($h, 2, 2);
        return $hh . $mm . '00';
    };
    $start_hms = $to_hms($d['heure_debut']);
    $end_hms   = $to_hms($d['heure_fin']);
    $ymd = str_replace('-', '', $date);

    if ($start_hms !== '') {
        $dtstart = 'DTSTART:' . $ymd . 'T' . $start_hms;
        if ($end_hms === '') {
            /* +2h par défaut. */
            $ts = strtotime($date . ' ' . substr($start_hms, 0, 2) . ':' . substr($start_hms, 2, 2)) + 2 * 3600;
            $end_hms = date('His', $ts);
            $ymd_end = date('Ymd', $ts);
        } else {
            $ymd_end = $ymd;
        }
        $dtend = 'DTEND:' . $ymd_end . 'T' . $end_hms;
    } else {
        /* Journée entière. */
        $dtstart = 'DTSTART;VALUE=DATE:' . $ymd;
        $dtend   = 'DTEND;VALUE=DATE:' . date('Ymd', strtotime($date . ' +1 day'));
    }

    $uid   = 'lfi-evt-' . (int) $d['id'] . '@nantessudclostoreau';
    $stamp = gmdate('Ymd\THis\Z');
    $summary = lfi_nct_ics_escape($d['titre']);
    $loc = $d['adresse'] ?: $d['lieu'];
    $location = lfi_nct_ics_escape($loc);
    $desc = lfi_nct_ics_escape('Événement du Groupe d\'Action LFI Nantes Sud Clos Toreau. ' . $d['url']);

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//LFI Nantes Sud Clos Toreau//Agenda//FR',
        'CALSCALE:GREGORIAN',
        'METHOD:REQUEST',
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . $stamp,
        $dtstart,
        $dtend,
        'SUMMARY:' . $summary,
        'LOCATION:' . $location,
        'DESCRIPTION:' . $desc,
        'STATUS:CONFIRMED',
        /* Rappel la veille. */
        'BEGIN:VALARM', 'TRIGGER:-P1D', 'ACTION:DISPLAY', 'DESCRIPTION:Rappel : ' . $summary, 'END:VALARM',
        /* Rappel 2 h avant. */
        'BEGIN:VALARM', 'TRIGGER:-PT2H', 'ACTION:DISPLAY', 'DESCRIPTION:Rappel : ' . $summary, 'END:VALARM',
        'END:VEVENT',
        'END:VCALENDAR',
    ];
    return implode("\r\n", $lines) . "\r\n";
}

/** Échappe une valeur pour l'iCalendar (RFC 5545). */
function lfi_nct_ics_escape($s) {
    $s = (string) $s;
    $s = str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], $s);
    return $s;
}

/* -------------------------------------------------------------- *
 *  Vue admin : envoi manuel de l'invitation agenda pour un event  *
 *  (utile pour un événement déjà en base, ex. la kermesse).       *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_agenda_invite() {
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $ev = isset($_GET['ev']) ? (int) $_GET['ev'] : 0;
    $back = $ev ? lfi_nct_app_url('mobilisation', ['ev' => $ev]) : lfi_nct_app_url('evenements');

    if ($ev && !empty($_POST['lfi_agenda_send']) && check_admin_referer('lfi_agenda_send')) {
        $n = lfi_nct_agenda_send_invites($ev, true);
        wp_safe_redirect(add_query_arg('sent', (int) $n, lfi_nct_app_url('agenda-invite', ['ev' => $ev])));
        exit;
    }

    $d = ($ev && function_exists('lfi_nct_event_data')) ? lfi_nct_event_data($ev) : null;
    lfi_nct_app_screen_open('📅 Invitation agenda', 'Envoyer l\'événement dans l\'agenda des membres');
    if (isset($_GET['sent'])) lfi_nct_app_flash('✅ Invitation envoyée à ' . (int) $_GET['sent'] . ' membre(s).');

    if (!$d) {
        echo '<div class="lfi-app-help">Événement introuvable. <a href="' . esc_url(lfi_nct_app_url('evenements')) . '">Voir les événements</a>.</div>';
        lfi_nct_app_screen_close();
        return;
    }
    $nb = count(lfi_nct_agenda_member_emails());
    echo '<div class="lfi-app-card" style="border-left:4px solid #c8102e"><div class="com"><strong>' . esc_html($d['titre']) . '</strong><br>🗓 ' . esc_html(ucfirst((string) ($d['date_complete'] ?: $d['date']))) . ($d['lieu'] ? '<br>📍 ' . esc_html($d['lieu']) : '') . '</div></div>';
    echo '<div class="lfi-app-help">Chaque membre abonné (<strong>' . (int) $nb . '</strong>) reçoit un email avec une <strong>invitation calendrier</strong> : sur Gmail / Google Agenda, l\'événement s\'ajoute en un tap, avec un rappel la veille et 2 h avant.</div>';
    echo '<form method="post" style="margin-top:12px">' . wp_nonce_field('lfi_agenda_send', '_wpnonce', true, false)
       . '<input type="hidden" name="lfi_agenda_send" value="1">'
       . '<button type="submit" class="btn-primary" style="background:#186a3b">📅 Envoyer l\'invitation aux ' . (int) $nb . ' membre(s)</button></form>';
    echo '<div style="margin-top:10px"><a class="btn-ghost" href="' . esc_url($back) . '">← Retour</a></div>';
    lfi_nct_app_screen_close();
}
