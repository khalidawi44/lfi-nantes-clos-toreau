<?php
/**
 * INSCRIPTIONS AUX ÉVÉNEMENTS — QR code, flyer imprimable, diffusion SMS.
 *
 * S'appuie sur le système d'événements existant (CPT + table
 * wp_lfi_nct_event_rsvp de includes/events.php). Ajoute, pour TOUS les GA :
 *   - une page « flyer » imprimable et personnalisable (texte + image),
 *     avec un QR code qui mène à la page d'inscription de l'événement ;
 *   - une diffusion par SMS du lien d'inscription (membres actifs + locataires
 *     qui ont accepté d'être suivis / recontactés) ;
 *   - une vue des inscrit·es par événement.
 *
 * Inscriptions closes automatiquement quand l'événement est passé (géré dans
 * events.php via le drapeau is_past).
 */
if (!defined('ABSPATH')) exit;

/** URL publique d'inscription = page de l'événement (formulaire « Je participe »). */
function lfi_nct_event_inscription_url($event_id) {
    return get_permalink((int) $event_id);
}

/** URL d'une image QR (service public) encodant $url. */
function lfi_nct_qr_url($url, $size = 320) {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . (int) $size . 'x' . (int) $size
         . '&margin=8&data=' . rawurlencode($url);
}

/** Peut administrer (flyer/SMS/inscrits) : admin du GA ou super-admin. */
function lfi_nct_evt_can_admin() {
    return function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options');
}

/* ============================================================== *
 *  FLYER imprimable + QR (route publique « flyer »)               *
 * ============================================================== */
function lfi_nct_app_view_flyer() {
    $event_id = isset($_GET['event']) ? (int) $_GET['event'] : 0;
    $post = $event_id ? get_post($event_id) : null;
    if (!$post || !function_exists('lfi_nct_event_data')) {
        echo '<div class="lfi-app"><div class="lfi-app-error">Événement introuvable.</div></div>';
        return;
    }
    $data      = lfi_nct_event_data($post);
    $can_admin = is_user_logged_in() && lfi_nct_evt_can_admin();

    /* Personnalisation (admin) : texte + image du flyer. */
    if ($can_admin && !empty($_POST['lfi_flyer_save']) && check_admin_referer('lfi_flyer_' . $event_id)) {
        update_post_meta($event_id, '_lfi_evt_flyer_texte', sanitize_textarea_field(wp_unslash($_POST['flyer_texte'] ?? '')));
        if (!empty($_FILES['flyer_image']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $aid = media_handle_upload('flyer_image', $event_id);
            if (!is_wp_error($aid)) update_post_meta($event_id, '_lfi_evt_flyer_image', (int) $aid);
        }
        if (!empty($_POST['flyer_image_remove'])) delete_post_meta($event_id, '_lfi_evt_flyer_image');
        wp_safe_redirect(lfi_nct_app_url('flyer', ['event' => $event_id, 'saved' => 1]));
        exit;
    }

    $url     = lfi_nct_event_inscription_url($event_id);
    $qr      = lfi_nct_qr_url($url, 320);
    $couleur = function_exists('lfi_nct_ga_couleur') ? lfi_nct_ga_couleur() : '#c8102e';
    $logo    = function_exists('lfi_nct_ga_logo_url') ? lfi_nct_ga_logo_url() : '';
    $ga_nom  = function_exists('lfi_nct_ga_entete_nom') ? lfi_nct_ga_entete_nom() : 'Groupe d\'Action LFI Nantes Sud';
    $texte   = (string) get_post_meta($event_id, '_lfi_evt_flyer_texte', true);
    if ($texte === '') $texte = "Venez nombreux·ses ! Entrée libre. Scannez le QR code pour vous inscrire (ou juste pour nous prévenir).";
    $img_id  = (int) get_post_meta($event_id, '_lfi_evt_flyer_image', true);
    $img     = $img_id ? wp_get_attachment_image_url($img_id, 'large') : get_the_post_thumbnail_url($event_id, 'large');

    $when = '';
    if (!empty($data['date_complete'])) $when = $data['date_complete'];
    elseif (!empty($data['date']))      $when = $data['date'] . (!empty($data['heure_debut']) ? ' · ' . $data['heure_debut'] : '');

    echo '<div class="lfi-app">';

    /* Barre d'actions (masquée à l'impression). */
    echo '<div class="lfi-noprint" style="max-width:480px;margin:0 auto 12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('evenements')) . '">← Événements</a>';
    echo '<button type="button" class="btn-primary" onclick="window.print()">🖨 Imprimer / PDF</button>';
    echo '</div>';
    if (!empty($_GET['saved'])) echo '<div class="lfi-noprint" style="max-width:480px;margin:0 auto 10px" class="lfi-app-flash ok">✅ Flyer enregistré.</div>';

    /* ---- LE FLYER (imprimable) ---- */
    echo '<div class="lfi-flyer" style="max-width:480px;margin:0 auto;background:#fff;border:1px solid #eee;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)">';
    echo '<div style="background:' . esc_attr($couleur) . ';color:#fff;padding:16px 18px;text-align:center">';
    if ($logo) echo '<img src="' . esc_url($logo) . '" alt="" style="max-height:48px;margin-bottom:6px"><br>';
    echo '<div style="font-weight:800;letter-spacing:.5px;text-transform:uppercase;font-size:.95em">' . esc_html($ga_nom) . '</div>';
    echo '</div>';

    if ($img) echo '<img src="' . esc_url($img) . '" alt="" style="width:100%;max-height:220px;object-fit:cover">';

    echo '<div style="padding:20px 22px;text-align:center">';
    echo '<h1 style="color:' . esc_attr($couleur) . ';font-size:1.5em;margin:0 0 10px;line-height:1.2">' . esc_html(get_the_title($post)) . '</h1>';
    if ($when)                 echo '<div style="font-weight:700;font-size:1.05em;margin:2px 0">📅 ' . esc_html($when) . '</div>';
    if (!empty($data['lieu'])) echo '<div style="margin:2px 0">📍 ' . esc_html($data['lieu']) . (!empty($data['adresse']) && $data['adresse'] !== $data['lieu'] ? ' — ' . esc_html($data['adresse']) : '') . '</div>';
    echo '<p style="color:#333;margin:14px 0;line-height:1.5">' . nl2br(esc_html($texte)) . '</p>';

    echo '<div style="margin:16px 0 6px"><img src="' . esc_url($qr) . '" alt="QR code d\'inscription" style="width:200px;height:200px"></div>';
    echo '<div style="font-weight:700;color:' . esc_attr($couleur) . '">📲 Scanne pour t\'inscrire</div>';
    echo '<div style="font-size:.78em;color:#888;margin-top:4px;word-break:break-all">' . esc_html($url) . '</div>';
    echo '</div>';
    echo '</div>'; // .lfi-flyer

    /* ---- Personnalisation (admin, masquée à l'impression) ---- */
    if ($can_admin) {
        echo '<div class="lfi-noprint" style="max-width:480px;margin:16px auto 0">';
        echo '<details class="lfi-app-collapse"><summary>🎨 Personnaliser ce flyer</summary>';
        echo '<form method="post" enctype="multipart/form-data" class="lfi-app-form" style="margin-top:10px">';
        wp_nonce_field('lfi_flyer_' . $event_id);
        echo '<input type="hidden" name="lfi_flyer_save" value="1">';
        echo '<label>Texte du flyer<textarea name="flyer_texte" rows="4" placeholder="Message d\'invitation">' . esc_textarea($texte) . '</textarea></label>';
        if ($img) {
            echo '<div style="margin:4px 0"><img src="' . esc_url($img) . '" alt="" style="max-height:64px;border-radius:6px;border:1px solid #ddd"> ';
            echo '<label style="font-size:.9em"><input type="checkbox" name="flyer_image_remove" value="1"> retirer l\'image</label></div>';
        }
        echo '<label>Image / visuel (optionnel)<input type="file" name="flyer_image" accept="image/*"></label>';
        echo '<div class="lfi-app-help"><small>La date, l\'heure et le lieu sont repris automatiquement de l\'événement. Le QR code mène à la page d\'inscription.</small></div>';
        echo '<button type="submit" class="btn-primary">💾 Enregistrer le flyer</button>';
        echo '</form></details>';
        echo '<div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('event-sms', ['event' => $event_id])) . '">📱 Diffuser par SMS</a>';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('event-inscrits', ['event' => $event_id])) . '">📋 Voir les inscrit·es</a>';
        echo '</div>';
        echo '</div>';
    }

    echo '</div>'; // .lfi-app

    /* CSS d'impression : on ne garde que le flyer. */
    echo '<style>@media print{body *{visibility:hidden!important}.lfi-flyer,.lfi-flyer *{visibility:visible!important}.lfi-flyer{position:absolute;left:0;top:0;width:100%;box-shadow:none;border:0}.lfi-noprint{display:none!important}.lfi-public-install,.lfi-app-emergency{display:none!important}}</style>';
}

/* ============================================================== *
 *  DIFFUSION SMS du lien d'inscription (admin)                    *
 * ============================================================== */
function lfi_nct_app_view_event_sms() {
    if (!lfi_nct_evt_can_admin()) { wp_safe_redirect(lfi_nct_app_url('evenements')); exit; }
    global $wpdb;
    $event_id = isset($_GET['event']) ? (int) $_GET['event'] : 0;
    $post = $event_id ? get_post($event_id) : null;
    if (!$post || !function_exists('lfi_nct_event_data')) {
        lfi_nct_app_screen_open('📱 Diffuser par SMS');
        echo '<div class="lfi-app-empty">Événement introuvable.</div>';
        lfi_nct_app_screen_close();
        return;
    }
    $data = lfi_nct_event_data($post);
    $url  = lfi_nct_event_inscription_url($event_id);

    /* Message pré-rempli (modifiable), avec les infos de l'événement. */
    $when = !empty($data['date']) ? $data['date'] . (!empty($data['heure_debut']) ? ' à ' . $data['heure_debut'] : '') : '';
    $default = 'Bonjour {prenom}, ' . get_the_title($post)
             . ($when ? ' le ' . $when : '')
             . (!empty($data['lieu']) ? ' à ' . $data['lieu'] : '')
             . '. Inscris-toi ici : ' . $url;
    $msg = isset($_GET['msg']) ? sanitize_textarea_field(wp_unslash($_GET['msg'])) : $default;

    /* Destinataires cloisonnés au GA, mais JAMAIS mélangés : d'un côté les
       membres actifs du GA, de l'autre les locataires (répondant·es d'enquête
       ayant accepté le recontact). On choisit son public — on ne mélange pas. */
    $mem = $wpdb->prefix . 'lfi_nct_membres';
    $rep = $wpdb->prefix . 'lfi_nct_responses';
    $mem_clause = function_exists('lfi_nct_membres_ga_clause') ? lfi_nct_membres_ga_clause('ga') : '';
    $rep_clause = function_exists('lfi_nct_responses_scope_clause') ? lfi_nct_responses_scope_clause() : '';

    $membres = [];
    foreach (($wpdb->get_results("SELECT prenom, nom, tel FROM $mem WHERE tel <> ''" . $mem_clause . " ORDER BY prenom LIMIT 500") ?: []) as $r) {
        $membres[preg_replace('/[^\d+]/', '', $r->tel)] = ['prenom' => $r->prenom, 'nom' => $r->nom, 'tel' => $r->tel];
    }
    $locataires = [];
    foreach (($wpdb->get_results("SELECT contact_prenom AS prenom, contact_nom AS nom, contact_tel AS tel FROM $rep WHERE deleted_at IS NULL AND contact_recontact = 1 AND contact_tel <> ''" . $rep_clause . " ORDER BY submitted_at DESC LIMIT 500") ?: []) as $r) {
        $k = preg_replace('/[^\d+]/', '', $r->tel);
        if ($k !== '') $locataires[$k] = ['prenom' => $r->prenom, 'nom' => $r->nom, 'tel' => $r->tel];
    }

    /* Public choisi (par défaut : les membres du GA). Un seul public à la fois. */
    $aud = isset($_GET['aud']) ? sanitize_key($_GET['aud']) : 'membres';
    if (!in_array($aud, ['membres', 'locataires'], true)) $aud = 'membres';
    $recips = $aud === 'locataires' ? $locataires : $membres;

    lfi_nct_app_screen_open('📱 Diffuser par SMS', get_the_title($post));
    echo '<div class="lfi-app-help">Envoie le lien d\'inscription par SMS. Choisis d\'abord <strong>à qui</strong> tu écris — les <strong>membres du GA</strong> et les <strong>locataires</strong> ne sont jamais mélangés. Touche un destinataire : ton appli SMS s\'ouvre avec le message pré-rempli (<code>{prenom}</code> est remplacé).</div>';

    /* Sélecteur de public : membres du GA / locataires (jamais mélangés). */
    echo '<div class="lfi-app-filter-chips">';
    foreach ([
        'membres'    => '👥 Membres du GA (' . count($membres) . ')',
        'locataires' => '🏠 Locataires — recontact OK (' . count($locataires) . ')',
    ] as $k => $lbl) {
        $cls = $aud === $k ? 'on' : '';
        echo '<a class="fc ' . esc_attr($cls) . '" href="' . esc_url(lfi_nct_app_url('event-sms', ['event' => $event_id, 'aud' => $k, 'msg' => $msg])) . '">' . esc_html($lbl) . '</a>';
    }
    echo '</div>';

    /* Édition du message. */
    echo '<form method="get" class="lfi-app-form">';
    echo '<input type="hidden" name="vue" value="event-sms"><input type="hidden" name="event" value="' . (int) $event_id . '"><input type="hidden" name="aud" value="' . esc_attr($aud) . '">';
    echo '<label>Message (variable : {prenom})<textarea name="msg" rows="4">' . esc_textarea($msg) . '</textarea></label>';
    echo '<button type="submit" class="btn-ghost">↻ Mettre à jour le message</button>';
    echo '</form>';

    /* Code couleur fort par public : impossible de se tromper de destinataires.
       Membres du GA = violet ; Locataires = vert. */
    $is_loc  = ($aud === 'locataires');
    $accent  = $is_loc ? '#1e8a5a' : '#4b2e83';
    $accbg   = $is_loc ? '#e7f5ee' : '#efeaf7';
    $src_lbl = $is_loc ? '🏠 Locataire (recontact OK)' : '👥 Membre du GA';
    $pub_lbl = $is_loc ? '🏠 LOCATAIRES (recontact OK)' : '👥 MEMBRES DU GA';

    echo '<div style="margin-bottom:10px;padding:10px 14px;border-radius:10px;font-weight:800;'
       . 'background:' . $accbg . ';color:' . $accent . ';border-left:5px solid ' . $accent . '">'
       . 'Tu écris à : ' . $pub_lbl . ' — ' . count($recips) . ' destinataire(s)'
       . '<div style="font-weight:500;font-size:.82em;margin-top:3px;color:#555">Ce public uniquement. Les '
       . ($is_loc ? 'membres du GA' : 'locataires') . ' ne sont pas dans cette liste.</div></div>';

    if (empty($recips)) {
        echo '<div class="lfi-app-empty">Aucun ' . ($is_loc ? 'locataire' : 'membre') . ' avec téléphone dans ce GA.</div>';
        lfi_nct_app_screen_close();
        return;
    }

    echo '<ul class="lfi-app-list">';
    foreach ($recips as $tel_clean => $r) {
        $body = str_replace('{prenom}', $r['prenom'] ?: '', $msg);
        $sms  = 'sms:' . $tel_clean . '?body=' . rawurlencode($body);
        echo '<li class="lfi-app-card" style="border-left:5px solid ' . $accent . '">';
        echo '<div class="head"><div class="who">' . esc_html(trim($r['prenom'] . ' ' . $r['nom']) ?: $r['tel']) . '</div>';
        echo '<div class="when" style="color:' . $accent . ';font-weight:700">' . esc_html($src_lbl) . '</div></div>';
        echo '<div class="meta"><span class="meta-chip">📞 ' . esc_html($r['tel']) . '</span></div>';
        echo '<div class="row-actions" style="margin-top:6px"><a class="btn-primary" href="' . esc_url($sms) . '">📲 Ouvrir le SMS</a></div>';
        echo '</li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  Liste des inscrit·es d'un événement (admin)                    *
 * ============================================================== */
function lfi_nct_app_view_event_inscrits() {
    if (!lfi_nct_evt_can_admin()) { wp_safe_redirect(lfi_nct_app_url('evenements')); exit; }
    global $wpdb;
    $event_id = isset($_GET['event']) ? (int) $_GET['event'] : 0;
    $post = $event_id ? get_post($event_id) : null;
    if (!$post) {
        lfi_nct_app_screen_open('📋 Inscrit·es');
        echo '<div class="lfi-app-empty">Événement introuvable.</div>';
        lfi_nct_app_screen_close();
        return;
    }
    $table = $wpdb->prefix . LFI_NCT_EVT_RSVP_TABLE;
    $rows  = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE event_id = %d ORDER BY created_at DESC LIMIT 500", $event_id)) ?: [];
    $pers  = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(avec_qui),0) FROM $table WHERE event_id = %d", $event_id));

    lfi_nct_app_screen_open('📋 Inscrit·es — ' . get_the_title($post), count($rows) . ' inscription(s) · ' . $pers . ' personne(s)');
    echo '<div style="margin:0 0 10px;display:flex;gap:8px;flex-wrap:wrap">';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('flyer', ['event' => $event_id])) . '">🖨 Flyer + QR</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('event-sms', ['event' => $event_id])) . '">📱 Diffuser par SMS</a>';
    echo '</div>';

    if (empty($rows)) {
        echo '<div class="lfi-app-empty">Personne d\'inscrit pour l\'instant. Diffuse le flyer et le QR code !</div>';
        lfi_nct_app_screen_close();
        return;
    }
    echo '<ul class="lfi-app-list">';
    foreach ($rows as $r) {
        echo '<li class="lfi-app-card">';
        echo '<div class="head"><div class="who">' . esc_html(trim($r->prenom . ' ' . $r->nom) ?: '(sans nom)') . ($r->avec_qui > 1 ? ' <span style="color:#888;font-weight:400">+' . ((int) $r->avec_qui - 1) . '</span>' : '') . '</div>';
        echo '<div class="when">' . esc_html(wp_date('j M', strtotime($r->created_at))) . '</div></div>';
        echo '<div class="meta">';
        if ($r->tel)   { $tc = preg_replace('/[^\d+]/', '', $r->tel); echo '<a class="meta-chip" href="tel:' . esc_attr($tc) . '">📞 ' . esc_html($r->tel) . '</a>'; }
        if ($r->email) echo '<a class="meta-chip" href="mailto:' . esc_attr($r->email) . '">✉️ ' . esc_html($r->email) . '</a>';
        echo '</div>';
        if ($r->commentaire) echo '<div class="lfi-app-help" style="margin-top:6px">💬 ' . esc_html($r->commentaire) . '</div>';
        echo '</li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}
