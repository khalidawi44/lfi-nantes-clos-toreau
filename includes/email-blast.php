<?php
/**
 * Module Email Blast — envoi d'emails HTML brandés LFI aux membres.
 *
 * - Page admin pour composer un email (sujet, body, événement à lier)
 * - Template HTML responsive avec branding LFI : bandeau rouge en
 *   tête, card événement, GROS bouton « ✓ Je participe » qui pointe
 *   sur la page d'événement, footer avec lien de désabonnement
 * - Sélection des destinataires (membres GA) avec filtre par statut
 * - Envoi via wp_mail() en batch (throttle pour ne pas se faire jeter
 *   par Hostinger SMTP)
 * - Log de tous les envois en base avec compteurs (sent, opened, clicked)
 * - Stats par campagne : nombre envoyé, taux d'ouverture (pixel
 *   tracker), taux de clic CTA, RSVPs résultants
 *
 * Hooks : sms_event_vars() est réutilisé pour les variables d'événement,
 * donc {{prenom}}, {{event_titre}}, {{event_date}}, etc. fonctionnent
 * pareil dans les emails et SMS.
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_EMAIL_DBVER     = 'lfi_nct_email_db_ver';
const LFI_NCT_EMAIL_CAMP_TBL  = 'lfi_nct_email_campaigns';
const LFI_NCT_EMAIL_LOG_TBL   = 'lfi_nct_email_log';

/* ------------------------------------------------------------------ */
/* DB                                                                  */
/* ------------------------------------------------------------------ */

add_action('init', 'lfi_nct_email_db_setup', 5);
function lfi_nct_email_db_setup() {
    if (get_option(LFI_NCT_EMAIL_DBVER) === '1') return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $camp_table = $wpdb->prefix . LFI_NCT_EMAIL_CAMP_TBL;
    dbDelta("CREATE TABLE $camp_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        nom VARCHAR(200) DEFAULT '',
        sujet VARCHAR(300) DEFAULT '',
        body LONGTEXT,
        event_id BIGINT(20) UNSIGNED DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        sent_at DATETIME NULL,
        sent_by BIGINT(20) UNSIGNED DEFAULT NULL,
        recipients_count INT UNSIGNED DEFAULT 0,
        opened_count INT UNSIGNED DEFAULT 0,
        clicked_count INT UNSIGNED DEFAULT 0,
        PRIMARY KEY (id),
        KEY event_id (event_id),
        KEY sent_at (sent_at)
    ) $charset;");

    $log_table = $wpdb->prefix . LFI_NCT_EMAIL_LOG_TBL;
    dbDelta("CREATE TABLE $log_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT(20) UNSIGNED NOT NULL,
        membre_id BIGINT(20) UNSIGNED DEFAULT NULL,
        email VARCHAR(190) DEFAULT '',
        prenom VARCHAR(120) DEFAULT '',
        token VARCHAR(64) DEFAULT '',
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        opened_at DATETIME NULL,
        clicked_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY campaign_id (campaign_id),
        KEY membre_id (membre_id),
        KEY token (token)
    ) $charset;");
    update_option(LFI_NCT_EMAIL_DBVER, '1', false);
}

/* ------------------------------------------------------------------ */
/* Template HTML email                                                   */
/* ------------------------------------------------------------------ */

/**
 * Génère le HTML d'un email branding LFI, avec le sujet/body fournis,
 * personnalisé pour un membre et optionnellement lié à un événement.
 */
function lfi_nct_email_render($body, $membre, $event_post = null, $tracking_token = '', $unsubscribe_token = '') {
    $vars_membre = [
        'prenom' => $membre->prenom ?: $membre->pseudo ?: 'camarade',
        'nom'    => $membre->nom,
        'pseudo' => $membre->pseudo,
        'statut' => $membre->statut,
    ];
    $vars_event = function_exists('lfi_nct_sms_event_vars')
        ? lfi_nct_sms_event_vars($event_post)
        : array_fill_keys(['event_titre','event_url','event_url_short','event_date','event_jour','event_heure','event_lieu','event_adresse','event_date_complete'], '');
    $vars = array_merge($vars_membre, $vars_event);

    // Substitution {{variable}} dans le body
    $body_resolved = $body;
    foreach ($vars as $k => $v) {
        $body_resolved = str_replace('{{' . $k . '}}', (string) $v, $body_resolved);
    }
    // Conversion newlines en <br> si body en texte simple
    if (strpos($body_resolved, '<p>') === false && strpos($body_resolved, '<br') === false) {
        $body_resolved = wpautop($body_resolved);
    }

    $site_url      = home_url('/');
    $site_name     = 'LFI Nantes Sud · Clos Toreau';
    $event_url     = $vars['event_url']   ?: $site_url;
    $event_date_c  = $vars['event_date_complete'];
    $event_titre   = $vars['event_titre'] ?: 'Prochain rendez-vous';
    $event_lieu    = trim(($vars['event_lieu'] ? $vars['event_lieu'] : '') . ($vars['event_adresse'] ? ($vars['event_lieu'] ? ' — ' : '') . $vars['event_adresse'] : ''));
    $unsubscribe_url = $unsubscribe_token ? home_url('/stop/' . $unsubscribe_token) : home_url('/');
    $logo_url = (defined('LFI_NCT_URL') ? LFI_NCT_URL : (home_url('/wp-content/plugins/lfi-nantes-clos-toreau/'))) . 'assets/img/logo-lfi.png';
    $pixel_url     = $tracking_token ? home_url('/?lfi_open=' . $tracking_token) : '';
    $cta_url       = $tracking_token ? home_url('/?lfi_click=' . $tracking_token . '&to=' . urlencode($event_url)) : $event_url;

    $event_card = '';
    if ($event_post) {
        $event_card = '
        <tr><td style="padding:0 30px 0">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fff3f5;border-left:6px solid #c8102e;border-radius:8px;margin:20px 0">
                <tr><td style="padding:20px">
                    <p style="margin:0;font-size:13px;color:#c8102e;font-weight:700;text-transform:uppercase;letter-spacing:1px">📍 Prochain rendez-vous</p>
                    <h2 style="margin:8px 0 12px;font-size:22px;color:#1a1a1a;font-family:Arial,sans-serif">' . esc_html($event_titre) . '</h2>
                    ' . ($event_date_c ? '<p style="margin:4px 0;font-size:16px;color:#333"><strong>📅 ' . esc_html($event_date_c) . '</strong></p>' : '') . '
                    ' . ($event_lieu ? '<p style="margin:4px 0;font-size:15px;color:#555">📌 ' . esc_html($event_lieu) . '</p>' : '') . '
                </td></tr>
            </table>
        </td></tr>';
    }

    $cta_button = '
    <tr><td style="padding:10px 30px 30px;text-align:center">
        <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto">
            <tr><td style="background:#c8102e;border-radius:8px;box-shadow:0 4px 12px rgba(200,16,46,.3)">
                <a href="' . esc_url($cta_url) . '" style="display:inline-block;padding:18px 42px;color:#ffffff;font-size:20px;font-weight:700;text-decoration:none;font-family:Arial,sans-serif;letter-spacing:.5px">
                    ✓ JE PARTICIPE
                </a>
            </td></tr>
        </table>
        <p style="margin:14px 0 0;font-size:13px;color:#888;font-family:Arial,sans-serif">
            Clique pour confirmer ta venue et avoir tous les détails 💪
        </p>
    </td></tr>';

    $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . esc_html($vars['event_titre'] ?: $site_name) . '</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#333">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f5f5;padding:24px 0">
<tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:600px">

        <!-- Bandeau rouge LFI (avec logo) -->
        <tr><td style="background:linear-gradient(135deg,#c8102e 0%,#7a0000 100%);padding:26px 30px 22px;text-align:center">
            <img src="' . esc_url($logo_url) . '" alt="La France Insoumise" width="72" height="72" style="display:inline-block;width:72px;height:72px;margin:0 auto 8px;border:0;outline:none">
            <p style="margin:0;color:#fff;font-size:11px;letter-spacing:3px;text-transform:uppercase;opacity:.9">LA FRANCE INSOUMISE</p>
            <h1 style="margin:6px 0 0;color:#fff;font-size:26px;font-weight:900;letter-spacing:.5px">Nantes Sud · Clos Toreau</h1>
            <p style="margin:8px 0 0;color:#fff;font-size:14px;opacity:.95">✊ Groupe d\'Action</p>
        </td></tr>

        <!-- Salutation perso -->
        <tr><td style="padding:30px 30px 0">
            <p style="margin:0;font-size:18px;color:#1a1a1a">Salut <strong>' . esc_html($vars['prenom']) . '</strong> 👋</p>
        </td></tr>

        <!-- Corps du message -->
        <tr><td style="padding:14px 30px;font-size:16px;line-height:1.6;color:#333">' . wp_kses_post($body_resolved) . '</td></tr>

        ' . $event_card . '

        ' . $cta_button . '

        <!-- Séparateur -->
        <tr><td style="padding:0 30px"><div style="border-top:1px solid #eee"></div></td></tr>

        <!-- Pied de page -->
        <tr><td style="padding:24px 30px;text-align:center;font-size:12px;color:#888">
            <p style="margin:0 0 8px">📍 LFI Nantes Sud · Groupe d\'Action Clos Toreau</p>
            <p style="margin:0 0 12px">
                <a href="' . esc_url($site_url) . '" style="color:#c8102e;text-decoration:none;font-weight:600">lfi-nantes-clostoreau.fr</a>
            </p>
            <p style="margin:0 0 10px">
                <a href="' . esc_url($unsubscribe_url) . '" style="display:inline-block;color:#c8102e;text-decoration:none;font-weight:700;font-size:13px;border:1px solid #e0b3ba;border-radius:20px;padding:8px 16px">🚫 Ne plus me contacter (emails et SMS)</a>
            </p>
            <p style="margin:0;font-size:11px;color:#aaa">
                Tu reçois ce message parce que tu es en lien avec le Groupe d\'Action.<br>
                En cliquant ci-dessus, tu es retiré·e de nos emails <strong>et</strong> de nos SMS — plus aucun message.
            </p>
        </td></tr>

    </table>

    ' . ($pixel_url ? '<img src="' . esc_url($pixel_url) . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px">' : '') . '
</td></tr>
</table>
</body>
</html>';

    return $html;
}

/* ------------------------------------------------------------------ */
/* Tracking : open pixel, click redirect, désabonnement                 */
/* ------------------------------------------------------------------ */

add_action('init', 'lfi_nct_email_tracking_handlers');
function lfi_nct_email_tracking_handlers() {
    // Tracking pixel : marque le mail comme ouvert
    if (!empty($_GET['lfi_open'])) {
        global $wpdb;
        $log_table = $wpdb->prefix . LFI_NCT_EMAIL_LOG_TBL;
        $camp_table = $wpdb->prefix . LFI_NCT_EMAIL_CAMP_TBL;
        $token = sanitize_text_field($_GET['lfi_open']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, campaign_id, opened_at FROM $log_table WHERE token = %s LIMIT 1", $token));
        if ($row && !$row->opened_at) {
            $wpdb->update($log_table, ['opened_at' => current_time('mysql')], ['id' => $row->id]);
            $wpdb->query($wpdb->prepare("UPDATE $camp_table SET opened_count = opened_count + 1 WHERE id = %d", $row->campaign_id));
        }
        // Renvoie un pixel transparent 1x1
        header('Content-Type: image/gif');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }
    // Click sur le CTA : marque comme cliqué et redirige
    if (!empty($_GET['lfi_click']) && !empty($_GET['to'])) {
        global $wpdb;
        $log_table = $wpdb->prefix . LFI_NCT_EMAIL_LOG_TBL;
        $camp_table = $wpdb->prefix . LFI_NCT_EMAIL_CAMP_TBL;
        $token = sanitize_text_field($_GET['lfi_click']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, campaign_id, clicked_at FROM $log_table WHERE token = %s LIMIT 1", $token));
        if ($row && !$row->clicked_at) {
            $wpdb->update($log_table, ['clicked_at' => current_time('mysql')], ['id' => $row->id]);
            $wpdb->query($wpdb->prepare("UPDATE $camp_table SET clicked_count = clicked_count + 1 WHERE id = %d", $row->campaign_id));
        }
        wp_safe_redirect(esc_url_raw(wp_unslash($_GET['to'])), 302);
        exit;
    }
    // Désabonnement
    if (!empty($_GET['lfi_unsub'])) {
        global $wpdb;
        $mem_table = $wpdb->prefix . 'lfi_nct_membres';
        $token = sanitize_text_field($_GET['lfi_unsub']);
        /* On coupe les EMAILS **et** on inscrit en liste noire SMS : « ne plus me
           contacter » = plus rien du tout. */
        $m = $wpdb->get_row($wpdb->prepare("SELECT prenom, nom, tel FROM $mem_table WHERE unsubscribe_token = %s", $token));
        if ($m) {
            $wpdb->update($mem_table, ['abonne_emails' => 0], ['unsubscribe_token' => $token]);
            if (!empty($m->tel) && function_exists('lfi_nct_sms_block_add')) {
                lfi_nct_sms_block_add($m->tel, trim(($m->prenom ?? '') . ' ' . ($m->nom ?? '')), 'désinscription (lien email)');
            }
        } else {
            /* Pas un jeton membre → jeton STOP signé par NUMÉRO (venu d'un SMS). */
            $tel = function_exists('lfi_nct_stop_token_decode') ? lfi_nct_stop_token_decode($token) : '';
            if ($tel !== '' && function_exists('lfi_nct_sms_block_add')) {
                lfi_nct_sms_block_add($tel, '', 'désinscription (lien SMS)');
                /* Coupe aussi les emails du membre qui aurait ce numéro. */
                $wpdb->query($wpdb->prepare("UPDATE $mem_table SET abonne_emails = 0 WHERE REPLACE(REPLACE(REPLACE(tel,' ',''),'.',''),'-','') LIKE %s", '%' . $wpdb->esc_like(preg_replace('/[^\d]/', '', $tel)) . '%'));
            }
        }
        wp_die(
            '<div style="font-family:Arial;padding:40px;text-align:center;max-width:500px;margin:80px auto;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08)">
                <h1 style="color:#c8102e">✅ C\'est noté</h1>
                <p>Tu ne recevras <strong>plus aucun message</strong> du Groupe d\'Action — ni email, ni SMS.</p>
                <p>Tu peux revenir vers nous quand tu veux si tu changes d\'avis.</p>
                <p style="margin-top:30px"><a href="' . esc_url(home_url('/')) . '" style="color:#c8102e;font-weight:700">Retour au site</a></p>
            </div>',
            'Ne plus me contacter', ['response' => 200]
        );
    }
}

/* ------------------------------------------------------------------ */
/* Envoi                                                                */
/* ------------------------------------------------------------------ */

function lfi_nct_email_send_campaign($campaign_id, $recipients) {
    global $wpdb;
    $camp_table = $wpdb->prefix . LFI_NCT_EMAIL_CAMP_TBL;
    $log_table  = $wpdb->prefix . LFI_NCT_EMAIL_LOG_TBL;

    $camp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $camp_table WHERE id = %d", $campaign_id));
    if (!$camp) return ['ok' => false, 'sent' => 0, 'errors' => ['Campagne introuvable']];

    $event_post = $camp->event_id ? get_post($camp->event_id) : null;

    add_filter('wp_mail_content_type', function() { return 'text/html'; });
    add_filter('wp_mail_from',         function() { return get_option('admin_email'); });
    add_filter('wp_mail_from_name',    function() { return 'LFI Nantes Sud Clos Toreau'; });

    $sent_ok = 0; $errors = [];
    foreach ($recipients as $r) {
        if (empty($r->email) || !is_email($r->email)) continue;
        $token = bin2hex(random_bytes(20));
        $wpdb->insert($log_table, [
            'campaign_id' => $campaign_id,
            'membre_id'   => $r->id ?? null,
            'email'       => $r->email,
            'prenom'      => $r->prenom ?? '',
            'token'       => $token,
        ]);
        $html = lfi_nct_email_render($camp->body, $r, $event_post, $token, $r->unsubscribe_token ?? '');
        $subject_resolved = str_replace(['{{prenom}}','{{event_titre}}'], [$r->prenom ?: $r->pseudo ?: '', $event_post ? get_the_title($event_post) : ''], $camp->sujet);
        $ok = wp_mail($r->email, $subject_resolved, $html);
        if ($ok) $sent_ok++;
        else $errors[] = $r->email;
    }

    $wpdb->update($camp_table, [
        'sent_at'          => current_time('mysql'),
        'sent_by'          => get_current_user_id(),
        'recipients_count' => $sent_ok,
    ], ['id' => $campaign_id]);

    remove_all_filters('wp_mail_content_type');
    remove_all_filters('wp_mail_from');
    remove_all_filters('wp_mail_from_name');

    return ['ok' => true, 'sent' => $sent_ok, 'errors' => $errors];
}

/* ------------------------------------------------------------------ */
/* Admin                                                                */
/* ------------------------------------------------------------------ */

add_action('admin_menu', 'lfi_nct_email_admin_menu', 38);
function lfi_nct_email_admin_menu() {
    add_submenu_page(
        'lfi-nct-responses',
        'Envoi d\'emails groupés',
        '📧 Emails',
        'manage_options',
        'lfi-nct-email',
        'lfi_nct_email_router'
    );
}

function lfi_nct_email_router() {
    if (!current_user_can('manage_options')) return;
    if (function_exists('lfi_nct_admin_app_landing')) {
        lfi_nct_admin_app_landing('email', '✉️ Email', 'L\'envoi d\'emails (blast) est dans l\'app.');
        return;
    }
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'envoyer';
    ?>
    <div class="wrap">
        <h1>📧 Emails groupés aux membres du GA</h1>
        <nav class="nav-tab-wrapper">
            <a class="nav-tab <?php echo $tab === 'envoyer'    ? 'nav-tab-active' : ''; ?>" href="?page=lfi-nct-email&tab=envoyer">✉️ Composer & envoyer</a>
            <a class="nav-tab <?php echo $tab === 'campagnes'  ? 'nav-tab-active' : ''; ?>" href="?page=lfi-nct-email&tab=campagnes">📊 Campagnes & stats</a>
        </nav>
        <?php
        if ($tab === 'campagnes') lfi_nct_email_page_campagnes();
        else                       lfi_nct_email_page_envoyer();
        ?>
    </div>
    <?php
}

/* ----- Onglet : composer & envoyer ----- */

function lfi_nct_email_page_envoyer() {
    global $wpdb;
    $mem_table  = $wpdb->prefix . 'lfi_nct_membres';
    $camp_table = $wpdb->prefix . LFI_NCT_EMAIL_CAMP_TBL;

    if (!empty($_POST['lfi_nct_email_send']) && check_admin_referer('lfi_nct_email_send')) {
        $sujet    = sanitize_text_field(wp_unslash($_POST['sujet'] ?? ''));
        $body     = wp_kses_post(wp_unslash($_POST['body']    ?? ''));
        $event_id = (int) ($_POST['event_id'] ?? 0);
        $f_statut = sanitize_text_field(wp_unslash($_POST['f_statut'] ?? ''));
        $nom      = sanitize_text_field(wp_unslash($_POST['nom_camp'] ?? '')) ?: ('Campagne ' . current_time('Y-m-d H:i'));

        if ($sujet === '' || $body === '') {
            echo '<div class="notice notice-error"><p>Sujet et message sont obligatoires.</p></div>';
        } else {
            // Construit la liste de destinataires
            $where = ["email <> ''", "abonne_emails = 1", "jetable = 0"];
            $args = [];
            if ($f_statut !== '') { $where[] = 'statut = %s'; $args[] = $f_statut; }
            $sql = "SELECT * FROM $mem_table WHERE " . implode(' AND ', $where);
            $recipients = $args ? $wpdb->get_results($wpdb->prepare($sql, ...$args)) : $wpdb->get_results($sql);

            // Crée la campagne
            $wpdb->insert($camp_table, [
                'nom'      => $nom,
                'sujet'    => $sujet,
                'body'     => $body,
                'event_id' => $event_id ?: null,
            ]);
            $camp_id = (int) $wpdb->insert_id;

            // Envoi
            $res = lfi_nct_email_send_campaign($camp_id, $recipients);
            if ($res['ok']) {
                echo '<div class="notice notice-success is-dismissible"><p>✅ Campagne envoyée à <strong>' . (int) $res['sent'] . '</strong> destinataire(s).' . (!empty($res['errors']) ? ' Erreurs sur : ' . count($res['errors']) : '') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Échec : ' . esc_html(implode(', ', $res['errors'])) . '</p></div>';
            }
        }
    }

    $upcoming = function_exists('lfi_nct_upcoming_events') ? lfi_nct_upcoming_events(20) : [];
    $statuts  = $wpdb->get_col("SELECT DISTINCT statut FROM $mem_table WHERE statut <> '' ORDER BY statut");
    $total    = (int) $wpdb->get_var("SELECT COUNT(*) FROM $mem_table WHERE email <> '' AND abonne_emails = 1 AND jetable = 0");

    ?>
    <p style="margin-top:1em"><strong><?php echo $total; ?></strong> membre(s) joignable(s) par email au total.</p>

    <form method="post" style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);max-width:900px">
        <?php wp_nonce_field('lfi_nct_email_send'); ?>

        <table class="form-table">
            <tr>
                <th><label for="nom_camp">Nom interne de la campagne</label></th>
                <td><input type="text" name="nom_camp" id="nom_camp" class="regular-text" placeholder="Ex : Convocation réunion 26 juin"></td>
            </tr>
            <tr>
                <th><label for="event_id">Événement à lier</label></th>
                <td>
                    <select name="event_id" id="event_id" class="regular-text">
                        <option value="0">— aucun (email général) —</option>
                        <?php foreach ($upcoming as $e):
                            $d = function_exists('lfi_nct_event_data') ? lfi_nct_event_data($e) : null;
                            $label = get_the_title($e) . ($d && $d['date'] ? ' — ' . $d['date'] : '');
                            ?>
                            <option value="<?php echo (int) $e->ID; ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Si choisi, la card événement et le bouton « ✓ JE PARTICIPE » apparaissent dans l'email avec un lien direct vers la page d'inscription.</p>
                </td>
            </tr>
            <tr>
                <th><label for="f_statut">Filtre destinataires</label></th>
                <td>
                    <select name="f_statut" id="f_statut">
                        <option value="">— tous les membres abonnés (<?php echo $total; ?>) —</option>
                        <?php foreach ($statuts as $s) {
                            printf('<option value="%s">%s</option>', esc_attr($s), esc_html($s));
                        } ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="sujet">Objet</label></th>
                <td><input type="text" name="sujet" id="sujet" class="large-text" placeholder="Ex : Réunion {{event_titre}} - on compte sur toi !" required></td>
            </tr>
            <tr>
                <th><label for="body">Message</label></th>
                <td>
                    <?php
                    wp_editor('Salut {{prenom}} !

On se retrouve pour notre prochaine réunion du Groupe d\'Action LFI Nantes Sud Clos Toreau.

Quelques mots sur le programme et pourquoi c\'est important que tu sois là...

À très vite,
Le Groupe d\'Action', 'body', [
                        'textarea_name' => 'body',
                        'textarea_rows' => 10,
                        'media_buttons' => false,
                    ]);
                    ?>
                    <p class="description">
                        <strong>Variables :</strong> <code>{{prenom}}</code> · <code>{{nom}}</code> · <code>{{event_titre}}</code> · <code>{{event_date}}</code> · <code>{{event_jour}}</code> · <code>{{event_heure}}</code> · <code>{{event_lieu}}</code><br>
                        Le template HTML LFI (bandeau rouge, card événement, bouton « ✓ JE PARTICIPE » géant, footer) est ajouté automatiquement autour de ton message.
                    </p>
                </td>
            </tr>
        </table>

        <p>
            <button type="submit" name="lfi_nct_email_send" value="1" class="button button-primary" style="background:#c8102e;border-color:#a30b25;font-size:1.05em;padding:8px 24px;height:auto"
                    onclick="return confirm('Envoyer cet email à tous les destinataires sélectionnés ?');">
                🚀 Envoyer maintenant
            </button>
        </p>
    </form>
    <?php
}

/* ----- Onglet : campagnes & stats ----- */

function lfi_nct_email_page_campagnes() {
    global $wpdb;
    $camp_table = $wpdb->prefix . LFI_NCT_EMAIL_CAMP_TBL;
    $log_table  = $wpdb->prefix . LFI_NCT_EMAIL_LOG_TBL;
    $camps = $wpdb->get_results("SELECT * FROM $camp_table ORDER BY id DESC LIMIT 100");
    ?>
    <h2 style="margin-top:1em">Campagnes envoyées</h2>
    <table class="wp-list-table widefat striped">
        <thead><tr><th>#</th><th>Nom</th><th>Sujet</th><th>Événement</th><th>Envoyée le</th><th>Destinataires</th><th>Ouverts</th><th>Cliqués</th><th>Taux d'ouverture</th><th>Taux de clic</th></tr></thead>
        <tbody>
        <?php if (empty($camps)): ?>
            <tr><td colspan="10"><em>Aucune campagne envoyée pour l'instant.</em></td></tr>
        <?php else: foreach ($camps as $c):
            $event_title = $c->event_id ? get_the_title($c->event_id) : '—';
            $tx_open  = $c->recipients_count > 0 ? round($c->opened_count  / $c->recipients_count * 100, 1) : 0;
            $tx_click = $c->recipients_count > 0 ? round($c->clicked_count / $c->recipients_count * 100, 1) : 0;
        ?>
            <tr>
                <td>#<?php echo (int) $c->id; ?></td>
                <td><strong><?php echo esc_html($c->nom); ?></strong></td>
                <td><?php echo esc_html($c->sujet); ?></td>
                <td><?php echo esc_html($event_title); ?></td>
                <td><?php echo $c->sent_at ? esc_html($c->sent_at) : '<em>—</em>'; ?></td>
                <td style="text-align:center"><?php echo (int) $c->recipients_count; ?></td>
                <td style="text-align:center"><?php echo (int) $c->opened_count; ?></td>
                <td style="text-align:center"><?php echo (int) $c->clicked_count; ?></td>
                <td style="text-align:center"><strong style="color:<?php echo $tx_open >= 30 ? '#1a7f37' : ($tx_open >= 15 ? '#bd8600' : '#c8102e'); ?>"><?php echo $tx_open; ?>%</strong></td>
                <td style="text-align:center"><strong style="color:<?php echo $tx_click >= 10 ? '#1a7f37' : ($tx_click >= 3 ? '#bd8600' : '#c8102e'); ?>"><?php echo $tx_click; ?>%</strong></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <p class="description" style="margin-top:1em">
        Les taux d'ouverture sont mesurés via un pixel tracker (1×1 px transparent inséré dans l'email).
        Les taux de clic comptent les clics sur le bouton « JE PARTICIPE ».
    </p>
    <?php
}
