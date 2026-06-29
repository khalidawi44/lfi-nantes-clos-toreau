<?php
/**
 * App GA — fonctionnalités « pro » :
 *  - Photos envoyées par les locataires (médiathèque privée, liée user)
 *  - Dossier par locataire (admin)
 *  - Signatures email configurables (Collectif, Fabrice, signataires perso)
 *  - En-tête email LFI Nantes Sud Clos Toreau intégrée aux envois
 */
if (!defined('ABSPATH')) exit;

/* ============================================================== *
 *  Signatures email — stockées dans une option WP                  *
 * ============================================================== */

function lfi_nct_signatures_defaults() {
    return [
        'collectif' => [
            'nom'  => 'Le Collectif',
            'role' => 'Groupe d\'Action LFI Nantes Sud Clos Toreau',
            'tel'  => '',
            'web'  => 'lfi-nantes-clostoreau.fr',
        ],
    ];
}

function lfi_nct_signatures() {
    $sigs = get_option('lfi_nct_signatures', null);
    if (!is_array($sigs)) {
        $sigs = lfi_nct_signatures_defaults();
        update_option('lfi_nct_signatures', $sigs, false);
    }
    return $sigs;
}

function lfi_nct_render_signature_html($key) {
    $sigs = lfi_nct_signatures();
    if (!isset($sigs[$key])) return '';
    $s = $sigs[$key];
    $html  = '<table cellpadding="0" cellspacing="0" border="0" style="margin-top:18px;font-family:Arial,sans-serif">';
    $html .= '<tr><td style="border-left:4px solid #c8102e;padding:8px 14px">';
    $html .= '<div style="font-weight:700;color:#c8102e;font-size:1em">' . esc_html($s['nom']) . '</div>';
    if (!empty($s['role'])) $html .= '<div style="font-size:.92em;color:#222">' . esc_html($s['role']) . '</div>';
    if (!empty($s['tel']) || !empty($s['web'])) {
        $html .= '<div style="font-size:.85em;color:#666;margin-top:4px">';
        if (!empty($s['tel'])) $html .= '📞 ' . esc_html($s['tel']);
        if (!empty($s['tel']) && !empty($s['web'])) $html .= ' · ';
        if (!empty($s['web'])) $html .= '🌐 ' . esc_html($s['web']);
        $html .= '</div>';
    }
    $html .= '</td></tr></table>';
    return $html;
}

function lfi_nct_render_signature_text($key) {
    $sigs = lfi_nct_signatures();
    if (!isset($sigs[$key])) return '';
    $s = $sigs[$key];
    $t = "— " . $s['nom'];
    if (!empty($s['role'])) $t .= "\n" . $s['role'];
    if (!empty($s['web']))  $t .= "\n" . $s['web'];
    return $t;
}

/**
 * Enveloppe HTML standard d'un email du GA : en-tête rouge LFI +
 * salutation + corps + bouton « Installer l'app » + bloc événement
 * optionnel + signature + footer RGPD.
 */
function lfi_nct_email_wrap_html($prenom, $body_html, $event_html = '', $signature_key = 'collectif', $rgpd_text = '') {
    $sig_html  = lfi_nct_render_signature_html($signature_key);
    $app_url   = esc_url(home_url('/app/'));
    $install_url = esc_url(home_url('/app/?vue=installer'));
    if ($rgpd_text === '') {
        $rgpd_text = 'Vous recevez cet email du Groupe d\'Action LFI Nantes Sud Clos Toreau.';
    }

    /* Bloc « Installer l'app » prêt à coller en tête d'email */
    $install_block = ''
        . '<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px;width:100%">'
        . '<tr><td style="background:#fff8e6;border-left:4px solid #c8102e;border-radius:6px;padding:14px 18px">'
        .   '<div style="font-weight:700;color:#c8102e;font-size:1em;margin-bottom:4px">📲 L\'app du GA sur votre téléphone</div>'
        .   '<div style="font-size:.92em;color:#444;margin-bottom:10px">Modèles de lettre, vos droits, envoi de photos, conseils juridiques — tout est dans l\'app. Installez-la en un geste.</div>'
        .   '<a href="' . $install_url . '" style="display:inline-block;background:#c8102e;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:700;font-size:1em">📥 Installer l\'app sur mon téléphone</a>'
        .   '<div style="font-size:.78em;color:#777;margin-top:8px">ou ouvrir directement : <a href="' . $app_url . '" style="color:#c8102e">' . esc_html($app_url) . '</a></div>'
        . '</td></tr></table>';

    return ''
        . '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;background:#fff">'
        . '<div style="background:linear-gradient(135deg,#c8102e,#a30b25);color:#fff;padding:18px 22px">'
        .   '<div style="font-size:11px;letter-spacing:.8px;opacity:.85">LA FRANCE INSOUMISE · NANTES SUD</div>'
        .   '<div style="font-size:22px;font-weight:900;margin-top:2px">Φ Clos Toreau</div>'
        . '</div>'
        . '<div style="padding:22px 22px 6px;color:#1a1a1a;line-height:1.5">'
        .   '<p style="margin:0 0 12px">Bonjour ' . esc_html($prenom ?: '') . ',</p>'
        .   $body_html
        .   $install_block
        .   $event_html
        .   $sig_html
        . '</div>'
        . '<div style="background:#f8f8f8;color:#777;font-size:11px;padding:14px 22px;border-top:1px solid #eee">'
        .   esc_html($rgpd_text)
        . '</div>'
        . '</div>';
}

/* ============================================================== *
 *  Photos envoyées par les locataires                              *
 *  - Endpoint /app/?vue=envoyer-photo (locataire connecté)         *
 *  - Stockées en attachment privé, lien via post_meta              *
 * ============================================================== */

function lfi_nct_app_view_envoyer_photo() {
    if (!lfi_nct_user_role_tenant()) {
        echo '<div class="lfi-app"><div class="lfi-app-error">Page réservée aux locataires suivis.</div></div>';
        return;
    }
    $user = wp_get_current_user();

    $err = null;
    if (!empty($_POST['lfi_app_photo_upload']) && check_admin_referer('lfi_app_photo_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $piece = sanitize_text_field(wp_unslash($_POST['piece'] ?? ''));
        $note  = sanitize_textarea_field(wp_unslash($_POST['note'] ?? ''));

        if (empty($_FILES['photo']['tmp_name'])) {
            $err = 'Choisis une photo à envoyer.';
        } else {
            $file = $_FILES['photo'];
            /* Limite à 8 Mo + image uniquement */
            if ($file['size'] > 8 * 1024 * 1024) {
                $err = 'Fichier trop volumineux (max 8 Mo).';
            } else {
                $mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : $file['type'];
                if (strpos($mime, 'image/') !== 0) {
                    $err = 'Seules les images sont acceptées (JPEG, PNG, HEIC).';
                } else {
                    $upload = wp_handle_upload($file, ['test_form' => false]);
                    if (!empty($upload['error'])) {
                        $err = 'Erreur upload : ' . $upload['error'];
                    } else {
                        $att_id = wp_insert_attachment([
                            'post_mime_type' => $upload['type'],
                            'post_title'     => sprintf('Photo %s — %s', $piece ?: 'logement', $user->display_name),
                            'post_content'   => $note,
                            'post_status'    => 'private',
                            'post_author'    => $user->ID,
                        ], $upload['file']);
                        if (is_wp_error($att_id) || !$att_id) {
                            $err = 'Échec d\'enregistrement de la photo.';
                        } else {
                            update_post_meta($att_id, '_lfi_tenant_user_id', $user->ID);
                            update_post_meta($att_id, '_lfi_tenant_piece', $piece);
                            update_post_meta($att_id, '_lfi_tenant_note', $note);
                            $meta = wp_generate_attachment_metadata($att_id, $upload['file']);
                            wp_update_attachment_metadata($att_id, $meta);
                            wp_safe_redirect(lfi_nct_app_url('envoyer-photo', ['uploaded' => 1]));
                            exit;
                        }
                    }
                }
            }
        }
    }

    /* Suppression d'une photo par son propriétaire */
    if (!empty($_POST['lfi_app_photo_del']) && check_admin_referer('lfi_app_photo_del')) {
        $att_id = (int) $_POST['att_id'];
        $owner  = (int) get_post_meta($att_id, '_lfi_tenant_user_id', true);
        if ($att_id && $owner === $user->ID) {
            wp_delete_attachment($att_id, true);
            wp_safe_redirect(lfi_nct_app_url('envoyer-photo', ['deleted' => 1]));
            exit;
        }
    }

    $photos = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'any',
        'posts_per_page' => 50,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [['key' => '_lfi_tenant_user_id', 'value' => $user->ID]],
    ]);

    lfi_nct_app_screen_open('📷 Envoyer une photo', 'Documenter votre logement en images');

    if (!empty($_GET['uploaded'])) lfi_nct_app_flash('✅ Photo enregistrée. Le GA y a accès dans votre dossier.');
    if (!empty($_GET['deleted']))  lfi_nct_app_flash('🗑 Photo supprimée.');
    if ($err) lfi_nct_app_flash('❌ ' . $err, 'err');

    echo '<div class="lfi-app-help">📸 Documentez les problèmes : moisissures, fuites, dégâts, parties communes. Ces photos servent de preuve dans les démarches juridiques. Vos photos restent <strong>privées</strong> : seul le GA y a accès, jamais le public.</div>';

    echo '<form method="post" enctype="multipart/form-data" class="lfi-app-form">';
    wp_nonce_field('lfi_app_photo_upload');
    echo '<input type="hidden" name="lfi_app_photo_upload" value="1">';

    echo '<label>📍 De quelle pièce s\'agit-il ?<select name="piece" required>';
    foreach (['Cuisine','Salle de bain','WC','Chambre','Salon','Couloir','Entrée','Balcon','Cave','Garage','Parties communes','Cage d\'escalier','Ascenseur','Extérieur immeuble','Autre'] as $p) {
        echo '<option value="' . esc_attr($p) . '">' . esc_html($p) . '</option>';
    }
    echo '</select></label>';

    echo '<label>📝 Que montre la photo ? (description)<textarea name="note" rows="3" placeholder="Ex : moisissures sur le plafond depuis 6 mois, mur sud de la cuisine"></textarea></label>';

    echo '<label>📂 La photo<input type="file" name="photo" accept="image/*" required></label>';

    echo '<button type="submit" class="btn-primary big">📤 Envoyer la photo</button>';
    echo '</form>';

    echo '<h3 style="margin:24px 0 10px">📷 Mes photos envoyées (' . count($photos) . ')</h3>';
    if (empty($photos)) {
        echo '<div class="lfi-app-empty">Aucune photo encore. Commencez par la pièce la plus problématique.</div>';
    } else {
        echo '<div class="lfi-tenant-gallery">';
        foreach ($photos as $p) {
            $url = wp_get_attachment_image_url($p->ID, 'medium') ?: wp_get_attachment_url($p->ID);
            $piece = (string) get_post_meta($p->ID, '_lfi_tenant_piece', true);
            $note  = (string) get_post_meta($p->ID, '_lfi_tenant_note', true);
            echo '<div class="lfi-tenant-photo">';
            echo '<a href="' . esc_url(wp_get_attachment_url($p->ID)) . '" target="_blank">';
            echo '<img src="' . esc_url($url) . '" alt="" loading="lazy">';
            echo '</a>';
            echo '<div class="info">';
            if ($piece) echo '<div class="piece">📍 ' . esc_html($piece) . '</div>';
            if ($note)  echo '<div class="note">' . esc_html($note) . '</div>';
            echo '<div class="when">' . esc_html(wp_date('j M Y', strtotime($p->post_date))) . '</div>';
            echo '</div>';
            echo '<form method="post" class="row-actions" onsubmit="return confirm(\'Supprimer cette photo ?\');">';
            wp_nonce_field('lfi_app_photo_del');
            echo '<input type="hidden" name="lfi_app_photo_del" value="1">';
            echo '<input type="hidden" name="att_id" value="' . (int) $p->ID . '">';
            echo '<button type="submit" class="btn-del">🗑 Supprimer</button>';
            echo '</form>';
            echo '</div>';
        }
        echo '</div>';
    }

    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  ADMIN : Dossiers locataires                                     *
 * ============================================================== */

function lfi_nct_app_view_dossiers() {
    /* Admin EXCLUSIVEMENT — données nominatives sensibles (RGPD).
       Les GA peuvent FAIRE PASSER une enquête (via le formulaire public)
       mais n'ont JAMAIS accès aux résultats individuels. */
    if (!current_user_can('manage_options')) return;
    global $wpdb;

    $tenants = get_users([
        'role'    => LFI_NCT_ROLE_TENANT,
        'fields'  => ['ID', 'user_login', 'display_name', 'user_email'],
        'number'  => 100,
        'orderby' => 'display_name',
        'order'   => 'ASC',
    ]);

    lfi_nct_app_screen_open('🗂 Dossiers locataires', count($tenants) . ' locataire(s) suivi(s)');

    if (empty($tenants)) {
        echo '<div class="lfi-app-empty">Aucun locataire avec compte. Crée des comptes via <a href="' . esc_url(lfi_nct_app_url('comptes')) . '">🪪 Comptes</a>.</div>';
        lfi_nct_app_screen_close(false);
        return;
    }

    echo '<ul class="lfi-app-list">';
    foreach ($tenants as $u) {
        $rid = (int) get_user_meta($u->ID, 'lfi_nct_response_id', true);
        $tel = (string) get_user_meta($u->ID, 'lfi_nct_tel', true);
        $row = $rid ? $wpdb->get_row($wpdb->prepare("SELECT contact_tel, contact_email, adresse, data FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid)) : null;
        $problem = $row ? lfi_nct_app_enq_problem($row) : null;
        $nb_photos = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_lfi_tenant_user_id' AND meta_value = %d",
            $u->ID
        ));

        echo '<li class="lfi-app-card">';
        echo '<div class="head"><div class="who">' . esc_html($u->display_name) . '</div><div class="badge">Locataire</div></div>';
        if ($row && $row->adresse) echo '<div class="meta"><span class="meta-chip">📍 ' . esc_html($row->adresse) . '</span></div>';
        if ($problem) {
            echo '<div class="meta">';
            foreach (array_slice($problem['chips'], 0, 3) as $ch) {
                echo '<span class="meta-chip">' . $ch[0] . ' ' . esc_html($ch[1]) . '</span>';
            }
            if (count($problem['chips']) > 3) echo '<span class="meta-chip">+' . (count($problem['chips']) - 3) . '</span>';
            echo '</div>';
        }
        echo '<div class="meta">';
        if ($tel) echo '<a class="meta-chip" href="tel:' . esc_attr($tel) . '">📞 ' . esc_html($tel) . '</a>';
        if ($u->user_email) echo '<a class="meta-chip" href="mailto:' . esc_attr($u->user_email) . '">✉️ ' . esc_html($u->user_email) . '</a>';
        echo '<span class="meta-chip">📷 ' . $nb_photos . '</span>';
        echo '</div>';
        echo '<div class="row-actions">';
        echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => $u->ID])) . '">📂 Ouvrir le dossier</a>';
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';

    lfi_nct_app_screen_close();
}

function lfi_nct_app_view_dossier() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;

    $uid = (int) ($_GET['uid'] ?? 0);
    $u = $uid ? get_userdata($uid) : null;
    if (!$u || !in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) {
        lfi_nct_app_screen_open('📂 Dossier locataire');
        echo '<div class="lfi-app-empty">Locataire introuvable. <a href="' . esc_url(lfi_nct_app_url('dossiers')) . '">← Retour à la liste</a></div>';
        lfi_nct_app_screen_close(false);
        return;
    }

    /* Mise à jour des notes admin */
    if (!empty($_POST['lfi_app_dossier_notes']) && check_admin_referer('lfi_app_dossier_notes')) {
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
        update_user_meta($u->ID, 'lfi_nct_admin_notes', $notes);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'notes_saved' => 1]));
        exit;
    }

    $rid = (int) get_user_meta($u->ID, 'lfi_nct_response_id', true);
    $tel = (string) get_user_meta($u->ID, 'lfi_nct_tel', true);
    $admin_notes = (string) get_user_meta($u->ID, 'lfi_nct_admin_notes', true);
    $row = $rid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid)) : null;
    $problem = $row ? lfi_nct_app_enq_problem($row) : null;

    /* Photos */
    $photos = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'any',
        'posts_per_page' => 100,
        'orderby'        => 'date', 'order' => 'DESC',
        'meta_query'     => [['key' => '_lfi_tenant_user_id', 'value' => $u->ID]],
    ]);

    /* Communications */
    $sms_log   = $tel ? $wpdb->get_results($wpdb->prepare(
        "SELECT sent_at, body_sent FROM {$wpdb->prefix}lfi_nct_sms_log WHERE tel = %s ORDER BY sent_at DESC LIMIT 20",
        $tel
    )) : [];
    $email_log = $u->user_email ? $wpdb->get_results($wpdb->prepare(
        "SELECT sent_at, opened_at FROM {$wpdb->prefix}lfi_nct_email_log WHERE email = %s ORDER BY sent_at DESC LIMIT 20",
        $u->user_email
    )) : [];

    lfi_nct_app_screen_open('📂 ' . $u->display_name, $rid ? 'Enquête #' . $rid : 'Compte sans enquête liée');

    if (!empty($_GET['notes_saved'])) lfi_nct_app_flash('Notes enregistrées.');

    /* Profil + actions */
    echo '<div class="lfi-app-card">';
    echo '<div class="head"><div class="who">👤 Profil</div><div class="badge">@' . esc_html($u->user_login) . '</div></div>';
    echo '<div class="meta">';
    if ($tel) echo '<a class="meta-chip" href="tel:' . esc_attr($tel) . '">📞 ' . esc_html($tel) . '</a>';
    if ($u->user_email) echo '<a class="meta-chip" href="mailto:' . esc_attr($u->user_email) . '">✉️ ' . esc_html($u->user_email) . '</a>';
    if ($row && $row->adresse) echo '<span class="meta-chip">📍 ' . esc_html(trim($row->adresse . ($row->etage ? ' · ét. ' . $row->etage : ''))) . '</span>';
    echo '</div>';
    echo '<div class="row-actions">';
    if ($tel) echo '<a class="btn-primary" href="sms:' . esc_attr(preg_replace('/[^\d+]/', '', $tel)) . '">📱 SMS direct</a>';
    if ($tel) echo '<a class="btn-ghost" href="tel:' . esc_attr(preg_replace('/[^\d+]/', '', $tel)) . '">📞 Appeler</a>';
    if ($u->user_email) echo '<a class="btn-ghost" href="mailto:' . esc_attr($u->user_email) . '">✉️ Email perso</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('comptes', ['tab' => 'locataires', 'open' => $u->ID])) . '">✏️ Éditer la fiche</a>';
    echo '</div>';
    echo '</div>';

    /* Problèmes signalés */
    if ($problem) {
        echo '<div class="lfi-app-problem" style="margin-top:14px">';
        echo '<div class="prob-head">🏠 Problèmes signalés';
        if ($problem['gravite']) echo ' <span class="prob-grav g' . (int) $problem['gravite'] . '">gravité ' . (int) $problem['gravite'] . '/10</span>';
        echo '</div>';
        echo '<div class="prob-chips">';
        foreach ($problem['chips'] as $ch) echo '<span class="prob-chip">' . $ch[0] . ' ' . esc_html($ch[1]) . '</span>';
        echo '</div></div>';
    }

    /* === HUB D'ACTIONS — tout ce que je peux faire pour ce locataire === */
    $shortcut = [
        'tenant_uid'     => $u->ID,
        'tenant_prenom'  => $u->first_name ?: '',
        'tenant_nom'     => $u->last_name ?: $u->display_name,
        'tenant_adresse' => $row->adresse ?? '',
        'tenant_etage'   => $row->etage ?? '',
        'tenant_tel'     => $tel,
    ];
    echo '<div style="background:linear-gradient(135deg,#c8102e,#a30b25);color:#fff;border-radius:12px;padding:16px;margin:16px 0">';
    echo '<div style="font-weight:800;font-size:1.05em;margin-bottom:10px">⚡ Actions pour ce locataire</div>';
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px">';
    $actions = [
        ['📁', 'Dossier juridique', lfi_nct_app_url('dossier-juridique-add', $shortcut)],
        ['🔧', 'Intervention',      lfi_nct_app_url('intervention-add', $shortcut)],
        ['☎️', 'Appeler NMH',       lfi_nct_app_url('appel-nmh-add', ['tenant_uid' => $u->ID])],
        ['📅', 'RDV / agenda',      lfi_nct_app_url('rdv-add', ['tenant_uid' => $u->ID])],
    ];
    foreach ($actions as $a) {
        echo '<a href="' . esc_url($a[2]) . '" style="background:rgba(255,255,255,.16);color:#fff;text-decoration:none;padding:12px;border-radius:8px;text-align:center;font-weight:700;font-size:.92em;display:block">';
        echo '<div style="font-size:1.5em;line-height:1;margin-bottom:4px">' . $a[0] . '</div>' . esc_html($a[1]);
        echo '</a>';
    }
    echo '</div>';
    echo '</div>';

    /* === SUIVI : dossiers juridiques + interventions + recouvrements === */
    lfi_nct_dossier_render_suivi($u, $row);

    /* Photos */
    echo '<h3 style="margin:18px 0 8px">📷 Photos envoyées (' . count($photos) . ')</h3>';
    if (empty($photos)) {
        echo '<div class="lfi-app-empty">Aucune photo encore envoyée.</div>';
    } else {
        echo '<div class="lfi-tenant-gallery">';
        foreach ($photos as $p) {
            $url    = wp_get_attachment_image_url($p->ID, 'medium') ?: wp_get_attachment_url($p->ID);
            $piece  = (string) get_post_meta($p->ID, '_lfi_tenant_piece', true);
            $note   = (string) get_post_meta($p->ID, '_lfi_tenant_note', true);
            echo '<div class="lfi-tenant-photo">';
            echo '<a href="' . esc_url(wp_get_attachment_url($p->ID)) . '" target="_blank">';
            echo '<img src="' . esc_url($url) . '" alt="" loading="lazy">';
            echo '</a>';
            echo '<div class="info">';
            if ($piece) echo '<div class="piece">📍 ' . esc_html($piece) . '</div>';
            if ($note)  echo '<div class="note">' . esc_html($note) . '</div>';
            echo '<div class="when">' . esc_html(wp_date('j M Y', strtotime($p->post_date))) . '</div>';
            echo '</div></div>';
        }
        echo '</div>';
    }

    /* Notes admin */
    echo '<h3 style="margin:18px 0 8px">📝 Notes du GA (privées)</h3>';
    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_dossier_notes');
    echo '<input type="hidden" name="lfi_app_dossier_notes" value="1">';
    echo '<textarea name="notes" rows="5" placeholder="Suivi, historique des échanges, prochaines actions à mener…">' . esc_textarea($admin_notes) . '</textarea>';
    echo '<button type="submit" class="btn-primary">💾 Enregistrer les notes</button>';
    echo '</form>';

    /* Communications */
    if (!empty($sms_log)) {
        echo '<h3 style="margin:18px 0 8px">📱 SMS envoyés (' . count($sms_log) . ')</h3>';
        echo '<ul class="lfi-app-list">';
        foreach ($sms_log as $l) {
            echo '<li class="lfi-app-card">';
            echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html(wp_date('j M Y · H:i', strtotime($l->sent_at))) . '</div>';
            echo '<div style="margin-top:4px;font-size:.92em">' . esc_html($l->body_sent) . '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }
    if (!empty($email_log)) {
        echo '<h3 style="margin:18px 0 8px">✉️ Emails envoyés (' . count($email_log) . ')</h3>';
        echo '<ul class="lfi-app-list">';
        foreach ($email_log as $l) {
            echo '<li class="lfi-app-card">';
            echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html(wp_date('j M Y · H:i', strtotime($l->sent_at))) . ($l->opened_at ? ' · ✓ ouvert' : ' · pas encore ouvert') . '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  Suivi complet d'un locataire — agrège dossiers juridiques,      *
 *  interventions brigade et recouvrements liés à ce locataire.     *
 *  Matching : tenant_user_id OU (nom + adresse).                   *
 * ============================================================== */
function lfi_nct_dossier_render_suivi($u, $row) {
    global $wpdb;
    $uid  = (int) $u->ID;
    $nom  = $u->last_name ?: $u->display_name;
    $adr  = $row->adresse ?? '';

    /* Clauses de matching réutilisées */
    $name_clause = $nom ? $wpdb->prepare('LOWER(tenant_nom) = LOWER(%s)', $nom) : '0';
    $adr_clause  = $adr ? $wpdb->prepare('LOWER(tenant_adresse) = LOWER(%s)', $adr) : '0';
    $uid_clause  = $wpdb->prepare('tenant_user_id = %d', $uid);

    /* --- Dossiers juridiques --- */
    $td = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $dossiers = $wpdb->get_results("SELECT * FROM $td WHERE ($uid_clause OR $name_clause OR $adr_clause) ORDER BY updated_at DESC LIMIT 30") ?: [];

    /* --- Interventions brigade --- */
    $ti = $wpdb->prefix . 'lfi_nct_interventions';
    $interv = $wpdb->get_results("SELECT * FROM $ti WHERE ($uid_clause OR $name_clause OR $adr_clause) ORDER BY date_intervention DESC, id DESC LIMIT 30") ?: [];

    /* --- Recouvrements (via les n° de facture des interventions) --- */
    $recs = [];
    $facture_nums = array_filter(array_map(function ($i) { return $i->facture_numero; }, $interv));
    if (!empty($facture_nums)) {
        $tr = $wpdb->prefix . 'lfi_nct_recouvrements';
        $place = implode(',', array_fill(0, count($facture_nums), '%s'));
        $recs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tr WHERE facture_numero IN ($place) ORDER BY updated_at DESC", ...array_values($facture_nums))) ?: [];
    }

    /* --- Appels NMH liés à ce locataire --- */
    $appels = [];
    $ta = $wpdb->prefix . 'lfi_nct_appels_nmh';
    if ($wpdb->get_var("SHOW TABLES LIKE '$ta'") === $ta) {
        $appels = $wpdb->get_results($wpdb->prepare("SELECT * FROM $ta WHERE tenant_user_id = %d ORDER BY date_appel DESC LIMIT 30", $uid)) ?: [];
    }

    /* --- Emails envoyés au nom du GA (loggés dans les notes des dossiers juridiques) --- */
    $emails_envoyes = [];
    foreach ($dossiers as $d) {
        $logs = json_decode($d->notes ?? '', true);
        if (is_array($logs) && !empty($logs['email_log'])) {
            foreach ($logs['email_log'] as $el) {
                $el['dossier_id'] = $d->id;
                $emails_envoyes[] = $el;
            }
        }
    }

    echo '<h3 style="margin:20px 0 8px;color:#c8102e">📋 Suivi complet</h3>';

    if (empty($dossiers) && empty($interv) && empty($recs) && empty($appels)) {
        echo '<div class="lfi-app-empty">Aucun dossier juridique, intervention ou appel pour ce locataire pour le moment. Utilise les boutons « Actions » ci-dessus pour en créer.</div>';
        return;
    }

    /* Dossiers juridiques */
    if (!empty($dossiers)) {
        echo '<h4 style="margin:14px 0 6px">📁 Dossiers juridiques (' . count($dossiers) . ')</h4>';
        echo '<ul class="lfi-app-list">';
        foreach ($dossiers as $d) {
            $demandes = json_decode($d->demandes ?? '[]', true) ?: [];
            echo '<li class="lfi-app-card">';
            echo '<div class="head"><div class="who">📁 Dossier #' . (int) $d->id . '</div>';
            echo '<div class="badge">' . esc_html($d->statut) . '</div></div>';
            echo '<div class="meta">';
            if ($d->visite_date) echo '<span class="meta-chip">🗓 ' . esc_html(wp_date('j M Y', strtotime($d->visite_date))) . '</span>';
            if (in_array('relogement_urgent', $demandes, true)) echo '<span class="meta-chip" style="background:#fff3f5;color:#a30b25">🏥 Relogement urgent</span>';
            if (in_array('travaux_urgents', $demandes, true))   echo '<span class="meta-chip" style="background:#fff8e6;color:#bd8600">🔧 Travaux urgents</span>';
            /* Lettres envoyées */
            foreach (['lrar_travaux_date' => 'MED travaux', 'lrar_relogement_date' => 'Relogement', 'schs_date' => 'SCHS', 'ars_date' => 'ARS'] as $col => $lbl) {
                if (!empty($d->$col)) echo '<span class="meta-chip" style="background:#e8f5ea;color:#186a3b">✓ ' . esc_html($lbl) . '</span>';
            }
            echo '</div>';
            echo '<div class="row-actions"><a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dossier-juridique-edit', ['id' => $d->id])) . '">📂 Ouvrir / Lettres</a></div>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /* Interventions */
    if (!empty($interv)) {
        $total_facture = 0; $total_paye = 0;
        foreach ($interv as $i) {
            if (in_array($i->statut, ['facture', 'paye'], true)) $total_facture += (float) $i->total_ht;
            if ($i->statut === 'paye') $total_paye += (float) $i->total_ht;
        }
        echo '<h4 style="margin:14px 0 6px">🔧 Interventions brigade (' . count($interv) . ')';
        if ($total_facture > 0) echo ' <small style="color:#666;font-weight:400">· ' . number_format($total_facture, 2, ',', ' ') . ' € facturé</small>';
        echo '</h4>';
        echo '<ul class="lfi-app-list">';
        foreach ($interv as $i) {
            $st = [
                'planifie' => ['📅', '#bd8600'], 'realise' => ['✓', '#1a7f37'],
                'facture'  => ['🧾', '#c8102e'], 'paye' => ['💰', '#186a3b'],
                'annule'   => ['✕', '#777'],
            ][$i->statut] ?? ['?', '#888'];
            echo '<li class="lfi-app-card">';
            echo '<div class="head"><div class="who">🔧 ' . esc_html($i->type_travaux ?: '(sans type)') . '</div>';
            echo '<div class="badge" style="background:' . $st[1] . ';color:#fff">' . $st[0] . ' ' . esc_html($i->statut) . '</div></div>';
            echo '<div class="meta">';
            if ($i->date_intervention) echo '<span class="meta-chip">🗓 ' . esc_html(wp_date('j M Y', strtotime($i->date_intervention))) . '</span>';
            if ($i->total_ht > 0)      echo '<span class="meta-chip"><strong>' . number_format($i->total_ht, 2, ',', ' ') . ' €</strong></span>';
            if ($i->facture_numero)    echo '<span class="meta-chip">🧾 ' . esc_html($i->facture_numero) . '</span>';
            echo '</div>';
            echo '<div class="row-actions"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('intervention-edit', ['id' => $i->id])) . '">Ouvrir →</a></div>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /* Recouvrements */
    if (!empty($recs)) {
        echo '<h4 style="margin:14px 0 6px">⚖️ Recouvrements NMH (' . count($recs) . ')</h4>';
        echo '<ul class="lfi-app-list">';
        foreach ($recs as $rc) {
            echo '<li class="lfi-app-card">';
            echo '<div class="head"><div class="who">⚖️ ' . esc_html($rc->facture_numero) . '</div>';
            echo '<div class="badge">' . esc_html($rc->statut) . '</div></div>';
            echo '<div class="row-actions"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('recouvrement-dossier', ['id' => $rc->id])) . '">Ouvrir →</a></div>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /* Appels NMH */
    if (!empty($appels)) {
        $inc_labels = function_exists('lfi_nct_appel_incidents_labels') ? lfi_nct_appel_incidents_labels() : [];
        echo '<h4 style="margin:14px 0 6px">☎️ Appels à NMH (' . count($appels) . ')</h4>';
        echo '<ul class="lfi-app-list">';
        foreach ($appels as $ap) {
            $inc = json_decode($ap->incidents ?? '[]', true) ?: [];
            echo '<li class="lfi-app-card" style="border-left:4px solid ' . (!empty($inc) ? '#a30b25' : '#0066a3') . '">';
            echo '<div class="head"><div class="who">☎️ ' . esc_html($ap->date_appel ? wp_date('j M Y · H:i', strtotime($ap->date_appel)) : 'Appel') . '</div>';
            if (!empty($inc)) echo '<div class="badge" style="background:#a30b25;color:#fff">⚠ Incident</div>';
            echo '</div>';
            echo '<div class="meta">';
            if ($ap->duree_minutes > 0) echo '<span class="meta-chip">⏱ ' . esc_html(rtrim(rtrim(number_format($ap->duree_minutes, 2, ',', ' '), '0'), ',')) . ' min</span>';
            if ($ap->interlocuteur)     echo '<span class="meta-chip">👤 ' . esc_html($ap->interlocuteur) . '</span>';
            echo '</div>';
            if ($ap->objet) echo '<div class="com">' . esc_html($ap->objet) . '</div>';
            echo '<div class="row-actions">';
            echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('appel-nmh-edit', ['id' => $ap->id])) . '">Ouvrir →</a>';
            if (!empty($inc)) echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('appel-nmh-rapport', ['id' => $ap->id])) . '" target="_blank">📄 Rapport</a>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /* Emails envoyés au nom du GA */
    if (!empty($emails_envoyes)) {
        $email_lbls = [
            'lrar_travaux' => 'Mise en demeure travaux', 'lrar_relogement' => 'Relogement médical',
            'schs' => 'Saisine SCHS', 'ars' => 'Saisine ARS',
        ];
        echo '<h4 style="margin:14px 0 6px">📧 Emails envoyés à NMH (' . count($emails_envoyes) . ')</h4>';
        echo '<ul class="lfi-app-list">';
        foreach ($emails_envoyes as $el) {
            echo '<li class="lfi-app-card">';
            echo '<div class="head"><div class="who">📧 ' . esc_html($email_lbls[$el['letter']] ?? $el['letter']) . '</div>';
            echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html($el['date'] ?? '') . '</div></div>';
            echo '<div class="meta"><span class="meta-chip">→ ' . esc_html($el['to'] ?? '') . '</span></div>';
            echo '</li>';
        }
        echo '</ul>';
    }
}

/* ============================================================== *
 *  ADMIN : Signatures email — gestion                              *
 * ============================================================== */

function lfi_nct_app_view_signatures() {
    if (!current_user_can('manage_options')) return;

    if (!empty($_POST['lfi_app_sig_save']) && check_admin_referer('lfi_app_sig_save')) {
        $keys  = (array) ($_POST['sig_key']  ?? []);
        $noms  = (array) ($_POST['sig_nom']  ?? []);
        $roles = (array) ($_POST['sig_role'] ?? []);
        $tels  = (array) ($_POST['sig_tel']  ?? []);
        $webs  = (array) ($_POST['sig_web']  ?? []);
        $sigs = [];
        foreach ($keys as $i => $k) {
            $k = sanitize_key($k);
            if (!$k) continue;
            $nom = sanitize_text_field(wp_unslash($noms[$i] ?? ''));
            if ($nom === '') continue;
            $sigs[$k] = [
                'nom'  => $nom,
                'role' => sanitize_text_field(wp_unslash($roles[$i] ?? '')),
                'tel'  => sanitize_text_field(wp_unslash($tels[$i]  ?? '')),
                'web'  => sanitize_text_field(wp_unslash($webs[$i]  ?? '')),
            ];
        }
        /* + Ajout */
        $new_nom = sanitize_text_field(wp_unslash($_POST['new_nom'] ?? ''));
        if ($new_nom !== '') {
            $new_key = sanitize_key($_POST['new_key'] ?? '') ?: sanitize_key($new_nom);
            $sigs[$new_key] = [
                'nom'  => $new_nom,
                'role' => sanitize_text_field(wp_unslash($_POST['new_role'] ?? '')),
                'tel'  => sanitize_text_field(wp_unslash($_POST['new_tel']  ?? '')),
                'web'  => sanitize_text_field(wp_unslash($_POST['new_web']  ?? '')),
            ];
        }
        update_option('lfi_nct_signatures', $sigs, false);
        wp_safe_redirect(lfi_nct_app_url('signatures', ['saved' => 1]));
        exit;
    }

    $sigs = lfi_nct_signatures();

    lfi_nct_app_screen_open('✍️ Signatures', count($sigs) . ' signature(s) — utilisées dans les emails du GA');
    if (!empty($_GET['saved'])) lfi_nct_app_flash('Signatures enregistrées.');

    echo '<div class="lfi-app-help">Ces signatures s\'affichent en bas des emails que tu envoies aux adhérents ou aux locataires. Tu peux en avoir plusieurs (Le Collectif, Fabrice, etc.) et choisir laquelle utiliser à chaque envoi.</div>';

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_sig_save');
    echo '<input type="hidden" name="lfi_app_sig_save" value="1">';

    foreach ($sigs as $k => $s) {
        echo '<div class="lfi-app-card" style="margin-bottom:10px">';
        echo '<div class="head"><div class="who">' . esc_html($s['nom']) . '</div><div class="badge">' . esc_html($k) . '</div></div>';
        echo '<input type="hidden" name="sig_key[]" value="' . esc_attr($k) . '">';
        echo '<label>Nom affiché<input type="text" name="sig_nom[]" value="' . esc_attr($s['nom']) . '" required></label>';
        echo '<label>Rôle / fonction<input type="text" name="sig_role[]" value="' . esc_attr($s['role'] ?? '') . '" placeholder="Ex : animateur du GA"></label>';
        echo '<label>Téléphone (optionnel)<input type="text" name="sig_tel[]" value="' . esc_attr($s['tel'] ?? '') . '"></label>';
        echo '<label>Site web (optionnel)<input type="text" name="sig_web[]" value="' . esc_attr($s['web'] ?? '') . '" placeholder="lfi-nantes-clostoreau.fr"></label>';
        echo '<div class="lfi-app-help"><strong>Aperçu :</strong>' . lfi_nct_render_signature_html($k) . '</div>';
        echo '</div>';
    }

    echo '<h3 style="margin:18px 0 0">+ Ajouter une signature</h3>';
    echo '<label>Clé technique (identifiant court, ex : « fabrice »)<input type="text" name="new_key" placeholder="fabrice"></label>';
    echo '<label>Nom affiché<input type="text" name="new_nom" placeholder="Fabrice Untel"></label>';
    echo '<label>Rôle / fonction<input type="text" name="new_role" placeholder="Membre du Groupe d\'Action"></label>';
    echo '<label>Téléphone<input type="text" name="new_tel"></label>';
    echo '<label>Site web<input type="text" name="new_web"></label>';
    echo '<button type="submit" class="btn-primary big">💾 Tout enregistrer</button>';
    echo '</form>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  ADMIN : Carte 3D — MapLibre GL avec extrusion bâtiments OSM     *
 *  (même rendu que la page wp-admin /lfi-nct-map)                  *
 * ============================================================== */

function lfi_nct_app_view_carte() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';

    if (!empty($_POST['lfi_app_geocode']) && check_admin_referer('lfi_app_geocode')) {
        @set_time_limit(120);
        $batch = 25;
        if (function_exists('lfi_nct_geocode_pending')) {
            $res = lfi_nct_geocode_pending($batch);
            wp_safe_redirect(lfi_nct_app_url('carte', ['geo_traitees' => (int) $res['traitees'], 'geo_ok' => (int) $res['geocodees'], 'geo_remain' => (int) $res['restantes']]));
        } else {
            wp_safe_redirect(lfi_nct_app_url('carte', ['geo_err' => 1]));
        }
        exit;
    }

    $rows = $wpdb->get_results(
        "SELECT id, adresse, etage, data, lat, lng, submitted_at
         FROM $table
         WHERE deleted_at IS NULL AND lat IS NOT NULL AND lng IS NOT NULL
         ORDER BY submitted_at DESC LIMIT 500"
    ) ?: [];
    $pending = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $table
         WHERE lat IS NULL AND adresse IS NOT NULL AND adresse != ''
               AND deleted_at IS NULL"
    );

    /* Labels + niveaux de gravité (cohérents avec map.php) */
    $type_labels = [
        'degats_eaux' => '💧 Dégâts des eaux', 'humidite' => '🌫 Humidité', 'insectes' => '🐜 Nuisibles',
        'chauffage' => '🥶 Chauffage', 'electricite' => '⚡ Électricité', 'ascenseur' => '🛗 Ascenseur',
        'parties_communes' => '🚪 Parties communes', 'bruit' => '🔊 Bruit', 'securite' => '🚨 Insécurité',
        'autre' => '❗ Autre',
    ];
    $rec_labels = ['permanent' => 'En permanence', 'parfois' => 'Régulièrement', 'ponctuel' => 'Ponctuel'];

    $markers = [];
    foreach ($rows as $r) {
        $data = json_decode((string) $r->data, true);
        if (!is_array($data)) $data = [];
        $score = function_exists('lfi_nct_gravity_score') ? lfi_nct_gravity_score($data) : (int) ($data['problemes_gravite'] ?? 0);
        if (function_exists('lfi_nct_gravity_level')) {
            list($gkey, $glabel, $gcolor) = lfi_nct_gravity_level($score);
        } else {
            if      ($score >= 8) { $gkey='critique';    $glabel='Critique';    $gcolor='#7a0000'; }
            elseif  ($score >= 6) { $gkey='grave';       $glabel='Grave';       $gcolor='#c8102e'; }
            elseif  ($score >= 3) { $gkey='preoccupant'; $glabel='Préoccupant'; $gcolor='#bd8600'; }
            else                  { $gkey='leger';       $glabel='Sans souci';  $gcolor='#1a7f37'; }
        }
        $types_raw = (array) ($data['problemes_types'] ?? []);
        $types = array_map(function ($t) use ($type_labels) { return $type_labels[$t] ?? ucfirst($t); }, $types_raw);
        $markers[] = [
            'id'        => (int) $r->id,
            'lat'       => (float) $r->lat,
            'lng'       => (float) $r->lng,
            'adresse'   => (string) $r->adresse,
            'etage'     => (string) $r->etage,
            'appt'      => (string) ($data['appartement'] ?? ''),
            'date'      => wp_date('j M Y', strtotime($r->submitted_at)),
            'score'     => $score,
            'gkey'      => $gkey,
            'glabel'    => $glabel,
            'gcolor'    => $gcolor,
            'types'     => $types,
            'recurrent' => $rec_labels[$data['problemes_recurrent'] ?? ''] ?? '',
            'presence'  => $data['problemes_presence'] ?? '',
            'detail_url'=> lfi_nct_app_url('dossiers'),
        ];
    }

    $center_lat  = defined('LFI_NCT_MAP_CENTER_LAT')  ? (float) LFI_NCT_MAP_CENTER_LAT  : 47.1933;
    $center_lng  = defined('LFI_NCT_MAP_CENTER_LNG')  ? (float) LFI_NCT_MAP_CENTER_LNG  : -1.5380;
    $center_zoom = defined('LFI_NCT_MAP_CENTER_ZOOM') ? (int)   LFI_NCT_MAP_CENTER_ZOOM : 16;

    lfi_nct_app_screen_open('🗺 Carte 3D des signalements', count($rows) . ' enquête(s) géolocalisée(s)' . ($pending ? ' · ' . $pending . ' à géocoder' : ''));

    if (isset($_GET['geo_traitees'])) {
        lfi_nct_app_flash(sprintf('Géocodage : %d traité(s), %d réussi(s), %d restant(s).', (int) $_GET['geo_traitees'], (int) $_GET['geo_ok'], (int) $_GET['geo_remain']));
    }
    if (isset($_GET['geo_err'])) {
        lfi_nct_app_flash('La fonction de géocodage n\'est pas disponible.', 'err');
    }

    if ($pending > 0) {
        echo '<form method="post" style="margin:0 0 12px;text-align:center">';
        wp_nonce_field('lfi_app_geocode');
        echo '<input type="hidden" name="lfi_app_geocode" value="1">';
        echo '<button type="submit" class="btn-ghost">🌍 Géocoder ' . min(25, $pending) . ' adresse(s) en attente</button>';
        echo '</form>';
    }

    /* Légende du site (4 paliers) */
    echo '<div class="lfi-map-legend">';
    echo '<span><i style="background:#1a7f37"></i> 🟢 Sans souci</span>';
    echo '<span><i style="background:#bd8600"></i> 🟡 Préoccupant</span>';
    echo '<span><i style="background:#c8102e"></i> 🔴 Grave</span>';
    echo '<span><i style="background:#7a0000"></i> 🚨 Critique</span>';
    echo '</div>';

    echo '<div id="lfi-map" style="width:100%;height:65vh;min-height:420px;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.1);background:#f5f5f5"></div>';
    echo '<div class="lfi-app-help" style="margin-top:8px"><small>📱 Bouge à 2 doigts pour incliner la vue 3D. Pince pour zoomer. Touche un cube pour voir le détail.</small></div>';

    echo '<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">';
    echo '<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>';
    echo '<script src="https://unpkg.com/osmtogeojson@3.0.0-beta.5/osmtogeojson.js"></script>';
    ?>
    <script>
    (function () {
        var markers = <?php echo wp_json_encode($markers); ?>;
        var el = document.getElementById('lfi-map');
        if (!el || typeof maplibregl === 'undefined') {
            if (el) el.innerHTML = '<div style="padding:30px;text-align:center;color:#777">Carte 3D indisponible.</div>';
            return;
        }
        if (!markers.length) {
            el.innerHTML = '<div style="padding:30px;text-align:center;color:#777">Aucune adresse géocodée. Clique sur « Géocoder » au-dessus.</div>';
            return;
        }
        var center = [<?php echo (float) $center_lng; ?>, <?php echo (float) $center_lat; ?>];
        function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
        function parseFloor(s){ if(!s) return 0; var m=String(s).match(/(\d+)/); return m?parseInt(m[1],10):0; }
        function popupHtml(m){
            var unite = 'Étage ' + esc(m.etage) + (m.appt ? ' · Appt ' + esc(m.appt) : '');
            var html = '<h3>' + esc(m.adresse) + '</h3>'
                     + '<div>' + unite + '</div>'
                     + '<div style="margin:.4em 0"><span class="gravbadge" style="background:'+esc(m.gcolor)+';color:#fff;padding:2px 8px;border-radius:10px;font-weight:600;font-size:.85em">'
                     + esc(m.glabel) + (m.score ? ' (' + m.score + '/10)' : '') + '</span></div>';
            if (m.presence === 'non') html += '<div>✅ Aucun problème déclaré.</div>';
            else if (m.types && m.types.length) {
                html += '<div><strong>Problèmes :</strong><ul style="margin:.3em 0;padding-left:1.2em">';
                m.types.forEach(function(t){ html += '<li>' + esc(t) + '</li>'; });
                html += '</ul></div>';
                if (m.recurrent) html += '<div><strong>Récurrence :</strong> ' + esc(m.recurrent) + '</div>';
            }
            html += '<div style="margin-top:.5em;font-size:.85em;color:#666">' + esc(m.date) + '</div>';
            return html;
        }

        var map = new maplibregl.Map({
            container: 'lfi-map',
            style: {
                version: 8,
                sources: { osm: {
                    type: 'raster',
                    tiles: [
                        'https://a.tile.openstreetmap.org/{z}/{x}/{y}.png',
                        'https://b.tile.openstreetmap.org/{z}/{x}/{y}.png',
                        'https://c.tile.openstreetmap.org/{z}/{x}/{y}.png'
                    ],
                    tileSize: 256,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }},
                layers: [{ id: 'osm', type: 'raster', source: 'osm' }]
            },
            center: center,
            zoom: <?php echo (int) $center_zoom; ?>,
            pitch: 55, bearing: -15, maxPitch: 75, antialias: true,
        });
        map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }));
        map.addControl(new maplibregl.ScaleControl({ maxWidth: 100 }));

        var bounds = new maplibregl.LngLatBounds();
        var FLOOR_M = 3, CUBE_M = 4;
        var stackCounts = {};
        var feats = [];
        function squareAround(lng, lat, sizeM) {
            var dLat = sizeM / 111111;
            var dLng = sizeM / (111111 * Math.cos(lat * Math.PI / 180));
            return [[
                [lng - dLng/2, lat - dLat/2],
                [lng + dLng/2, lat - dLat/2],
                [lng + dLng/2, lat + dLat/2],
                [lng - dLng/2, lat + dLat/2],
                [lng - dLng/2, lat - dLat/2]
            ]];
        }
        markers.forEach(function (m, idx) {
            var floor = parseFloor(m.etage);
            var key = m.lat.toFixed(5) + '_' + m.lng.toFixed(5) + '_' + floor;
            var rank = (stackCounts[key] = (stackCounts[key] || 0) + 1) - 1;
            var jitterM = rank * 5;
            var dLng = jitterM / (111111 * Math.cos(m.lat * Math.PI / 180));
            var base = floor * FLOOR_M + 0.5;
            var top  = base + 2.5;
            feats.push({
                type: 'Feature', id: idx,
                geometry: { type: 'Polygon', coordinates: squareAround(m.lng + dLng, m.lat, CUBE_M) },
                properties: { fid: idx, base: base, height: top, color: m.gcolor, popup: popupHtml(m) }
            });
            bounds.extend([m.lng, m.lat]);
        });
        var surveysGJ = { type: 'FeatureCollection', features: feats };
        if (!bounds.isEmpty()) map.fitBounds(bounds, { padding: 60, maxZoom: 18, pitch: 55, bearing: -15 });

        function addSurveyLayer() {
            if (map.getSource('surveys')) return;
            map.addSource('surveys', { type: 'geojson', data: surveysGJ });
            map.addLayer({
                id: 'surveys-3d', type: 'fill-extrusion', source: 'surveys',
                paint: {
                    'fill-extrusion-color': ['get', 'color'],
                    'fill-extrusion-base': ['get', 'base'],
                    'fill-extrusion-height': ['get', 'height'],
                    'fill-extrusion-opacity': 0.95
                }
            });
            map.on('click', 'surveys-3d', function (e) {
                if (!e.features || !e.features.length) return;
                new maplibregl.Popup({ closeButton: true })
                    .setLngLat(e.lngLat)
                    .setHTML(e.features[0].properties.popup)
                    .addTo(map);
            });
        }
        function loadBuildings() {
            var b = map.getBounds();
            var s = b.getSouth(), w = b.getWest(), n = b.getNorth(), e = b.getEast();
            var pad = 0.001;
            s -= pad; w -= pad; n += pad; e += pad;
            var key = 'lfi_bldg_' + [s.toFixed(4), w.toFixed(4), n.toFixed(4), e.toFixed(4)].join('_');
            try { var cached = localStorage.getItem(key); if (cached) { addBuildingLayer(JSON.parse(cached)); return; } } catch (e2) {}
            var q = '[out:json][timeout:25];(way["building"](' + s + ',' + w + ',' + n + ',' + e + ');relation["building"](' + s + ',' + w + ',' + n + ',' + e + '););out body;>;out skel qt;';
            fetch('https://overpass-api.de/api/interpreter?data=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (typeof osmtogeojson === 'undefined') return;
                    var gj = osmtogeojson(data);
                    gj.features = (gj.features || []).filter(function (f) { return f.geometry && (f.geometry.type === 'Polygon' || f.geometry.type === 'MultiPolygon'); });
                    gj.features.forEach(function (f) {
                        var p = f.properties || {};
                        var lvls = parseFloat(p['building:levels']);
                        var h = parseFloat(p.height);
                        if (!isFinite(h)) h = isFinite(lvls) ? lvls * 3 : 9;
                        f.properties._h = Math.max(3, h);
                    });
                    try { localStorage.setItem(key, JSON.stringify(gj)); } catch (e2) {}
                    addBuildingLayer(gj);
                })
                .catch(function (err) { console.warn('Overpass buildings fetch failed:', err); });
        }
        function addBuildingLayer(gj) {
            if (!map.isStyleLoaded() || map.getSource('buildings')) return;
            map.addSource('buildings', { type: 'geojson', data: gj });
            var beforeId = map.getLayer('surveys-3d') ? 'surveys-3d' : undefined;
            map.addLayer({
                id: 'buildings-3d', type: 'fill-extrusion', source: 'buildings',
                paint: {
                    'fill-extrusion-color': '#9aa0a8',
                    'fill-extrusion-height': ['get', '_h'],
                    'fill-extrusion-base': 0,
                    'fill-extrusion-opacity': 0.55,
                }
            }, beforeId);
        }
        map.on('load', function () { addSurveyLayer(); loadBuildings(); });
    })();
    </script>
    <?php

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  ADMIN : Stats de l'enquête (problèmes, adresses, gravité)       *
 * ============================================================== */

function lfi_nct_app_view_stats_enquete_helper_stub() {} /* no-op marker, kept for compat */

function lfi_nct_app_view_stats_enquete() {
    /* Admin EXCLUSIVEMENT — agrégats sur données RGPD. */
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';

    $rows = $wpdb->get_results(
        "SELECT adresse, data FROM $table WHERE deleted_at IS NULL"
    ) ?: [];
    $total = count($rows);

    /* Agrégats — adresses groupées par clé canonique pour fusionner les
       variantes orthographiques (« rue Saint-Jean-de-Luz » et « rue st jean
       de luse » deviennent une seule entrée). */
    $problem_types  = [];   // slug => count
    $address_groups = [];   // clé canonique => ['n' => count, 'display' => libellé propre]
    $gravity_bucket = [0,0,0,0,0]; // [1-2, 3-4, 5-6, 7-8, 9-10]
    foreach ($rows as $r) {
        $data = json_decode((string) $r->data, true);
        if (!is_array($data)) $data = [];
        foreach ((array) ($data['problemes_types'] ?? []) as $t) {
            $problem_types[$t] = ($problem_types[$t] ?? 0) + 1;
        }
        $adr = trim((string) $r->adresse);
        if ($adr !== '') {
            $key = lfi_nct_address_canonical_key($adr);
            if ($key !== '') {
                if (!isset($address_groups[$key])) {
                    $address_groups[$key] = ['n' => 0, 'display' => lfi_nct_address_canonical_display($adr)];
                }
                $address_groups[$key]['n']++;
            }
        }
        $g = (int) ($data['problemes_gravite'] ?? 0);
        if      ($g >= 9) $gravity_bucket[4]++;
        elseif  ($g >= 7) $gravity_bucket[3]++;
        elseif  ($g >= 5) $gravity_bucket[2]++;
        elseif  ($g >= 3) $gravity_bucket[1]++;
        elseif  ($g >= 1) $gravity_bucket[0]++;
    }
    arsort($problem_types);
    /* Tri par count desc */
    uasort($address_groups, function ($a, $b) { return $b['n'] <=> $a['n']; });

    $labels_base = function_exists('lfi_nct_problem_types_all') ? lfi_nct_problem_types_all() : [];

    lfi_nct_app_screen_open('📊 Stats enquête', $total . ' réponse(s) au total');

    /* Bandeau résumé */
    echo '<div class="lfi-app-stats-grid">';
    echo '<div class="stat"><div class="ico">📋</div><div class="n">' . $total . '</div><div class="l">Réponses</div></div>';
    echo '<div class="stat"><div class="ico">🏠</div><div class="n">' . count($address_groups) . '</div><div class="l">Immeubles touchés</div></div>';
    echo '<div class="stat"><div class="ico">🚨</div><div class="n">' . ($gravity_bucket[3] + $gravity_bucket[4]) . '</div><div class="l">Cas graves (≥7)</div></div>';
    echo '<div class="stat"><div class="ico">📝</div><div class="n">' . count($problem_types) . '</div><div class="l">Types de problèmes</div></div>';
    echo '</div>';

    /* Top des problèmes signalés */
    echo '<h3 style="margin:18px 0 8px">🏠 Problèmes les plus signalés</h3>';
    if (empty($problem_types)) {
        echo '<div class="lfi-app-empty">Aucun problème encore signalé.</div>';
    } else {
        $max = max($problem_types);
        echo '<div class="lfi-app-bars">';
        foreach ($problem_types as $slug => $n) {
            $label = $labels_base[$slug] ?? $slug;
            $pct = $max ? round($n / $max * 100) : 0;
            echo '<div class="bar-row"><div class="bar-label">' . $label . '</div>';
            echo '<div class="bar-track"><div class="bar-fill" style="width:' . $pct . '%"></div><div class="bar-n">' . $n . '</div></div></div>';
        }
        echo '</div>';
    }

    /* Top des immeubles touchés (variantes orthographiques regroupées) */
    echo '<h3 style="margin:18px 0 8px">📍 Adresses les plus représentées</h3>';
    if (empty($address_groups)) {
        echo '<div class="lfi-app-empty">Aucune adresse renseignée.</div>';
    } else {
        $top_addresses = array_slice($address_groups, 0, 12, true);
        $max_a = 0;
        foreach ($top_addresses as $g) { if ($g['n'] > $max_a) $max_a = $g['n']; }
        echo '<div class="lfi-app-bars">';
        foreach ($top_addresses as $g) {
            $pct = $max_a ? round($g['n'] / $max_a * 100) : 0;
            echo '<div class="bar-row"><div class="bar-label">' . esc_html($g['display']) . '</div>';
            echo '<div class="bar-track"><div class="bar-fill" style="width:' . $pct . '%;background:#3a3a3a"></div><div class="bar-n">' . (int) $g['n'] . '</div></div></div>';
        }
        echo '</div>';
        echo '<div class="lfi-app-help"><small>Les variantes d\'orthographe sont regroupées automatiquement (« rue Saint-Jean-de-Luz » et « rue st jean de luse » comptent comme une seule rue).</small></div>';
    }

    /* Histogramme de gravité */
    echo '<h3 style="margin:18px 0 8px">⚖️ Gravité ressentie</h3>';
    $buckets = [
        ['1-2 faible',    '#9ccc65'],
        ['3-4 mineur',    '#fdd835'],
        ['5-6 moyen',     '#ffa500'],
        ['7-8 grave',     '#e8201e'],
        ['9-10 critique', '#a30b25'],
    ];
    $max_g = max($gravity_bucket) ?: 1;
    echo '<div class="lfi-app-bars">';
    foreach ($buckets as $i => $b) {
        $n = (int) $gravity_bucket[$i];
        $pct = round($n / $max_g * 100);
        echo '<div class="bar-row"><div class="bar-label">' . esc_html($b[0]) . '</div>';
        echo '<div class="bar-track"><div class="bar-fill" style="width:' . $pct . '%;background:' . esc_attr($b[1]) . '"></div><div class="bar-n">' . $n . '</div></div></div>';
    }
    echo '</div>';

    /* Lien vers la carte */
    echo '<div style="margin-top:18px">';
    echo '<a class="btn-primary big" href="' . esc_url(lfi_nct_app_url('carte')) . '">🗺 Voir la carte des signalements</a>';
    echo '</div>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  SMS aux LOCATAIRES — 7 modèles en VOUVOIEMENT                  *
 * ============================================================== */

function lfi_nct_tenant_sms_templates() {
    return [
        'rdv' => [
            'nom'  => '📞 Convenir d\'un rendez-vous',
            'body' => "Bonjour {{prenom}},\n\nLe Groupe d'Action LFI Nantes Sud Clos Toreau souhaiterait vous rencontrer pour faire le point sur votre situation logement. Quel jour vous arrangerait dans les prochaines semaines ?\n\nVous pouvez nous répondre directement par SMS.\n\nCordialement.",
        ],
        'invitation_reunion' => [
            'nom'  => '📣 Inviter à une réunion publique',
            'body' => "Bonjour {{prenom}},\n\nLe GA LFI Clos Toreau organise une réunion publique : {{event_titre}} — {{event_jour}} {{event_date}} à {{event_heure}}, à {{event_lieu}}.\n\nVotre présence serait précieuse. Toutes les infos : {{event_url_short}}",
        ],
        'invitation_evenement' => [
            'nom'  => '📅 Inviter à un événement',
            'body' => "Bonjour {{prenom}},\n\nNous organisons {{event_titre}} le {{event_date}} à {{event_lieu}}. Vous y êtes le·la bienvenu·e.\n\nDétails et inscription : {{event_url_short}}\n\nÀ très vite !",
        ],
        'suivi_lettre' => [
            'nom'  => '✉️ Suivi d\'une démarche / lettre',
            'body' => "Bonjour {{prenom}},\n\nSuite à notre échange concernant votre logement, vous trouverez dans l'app du GA un modèle de lettre pré-rempli à envoyer à votre bailleur.\n\nLien direct : https://lfi-nantes-clostoreau.fr/app/?vue=lettre\n\nN'hésitez pas à nous solliciter en cas de besoin.",
        ],
        'rappel_droits' => [
            'nom'  => '⚖️ Rappel sur vos droits',
            'body' => "Bonjour {{prenom}},\n\nPetit rappel : les problèmes de logement que vous nous avez signalés relèvent d'obligations légales du bailleur (loi du 6 juillet 1989, décret 2002-120).\n\nConsultez la fiche complète de vos droits dans l'app : https://lfi-nantes-clostoreau.fr/app/?vue=droits",
        ],
        'photo_request' => [
            'nom'  => '📷 Demander des photos du logement',
            'body' => "Bonjour {{prenom}},\n\nPour faire avancer votre dossier, pourriez-vous nous envoyer quelques photos des dégradations (moisissures, fuites, etc.) ?\n\nVous pouvez les déposer directement dans l'app, elles resteront privées : https://lfi-nantes-clostoreau.fr/app/?vue=envoyer-photo\n\nMerci.",
        ],
        'libre' => [
            'nom'  => '✍️ Texte libre',
            'body' => "Bonjour {{prenom}},\n\n",
        ],
    ];
}

function lfi_nct_app_view_sms_locataires() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $logt = $wpdb->prefix . 'lfi_nct_sms_log';

    if (!empty($_POST['lfi_app_sms_log']) && check_admin_referer('lfi_app_sms_log')) {
        $wpdb->insert($logt, [
            'template_id' => null,
            'membre_id'   => ((int) ($_POST['uid'] ?? 0)) ?: null,
            'tel'         => sanitize_text_field(wp_unslash($_POST['tel'] ?? '')),
            'body_sent'   => sanitize_textarea_field(wp_unslash($_POST['body'] ?? '')),
            'sent_by'     => get_current_user_id(),
        ]);
        wp_safe_redirect(lfi_nct_app_url('sms-locataires', ['logged' => 1, 'uid' => (int) $_POST['uid'], 'mode' => sanitize_key($_POST['mode'] ?? 'libre')]));
        exit;
    }

    /* Liste des locataires avec un tel */
    $tenants = get_users([
        'role'    => LFI_NCT_ROLE_TENANT,
        'fields'  => ['ID', 'user_login', 'display_name'],
        'number'  => 200,
        'orderby' => 'display_name', 'order' => 'ASC',
    ]);
    $tenants_with_tel = [];
    foreach ($tenants as $u) {
        $tel = (string) get_user_meta($u->ID, 'lfi_nct_tel', true);
        if ($tel) $tenants_with_tel[] = ['uid' => $u->ID, 'name' => $u->display_name, 'tel' => $tel];
    }

    $uid  = isset($_GET['uid'])  ? (int) $_GET['uid']  : 0;
    $mode = isset($_GET['mode']) ? sanitize_key($_GET['mode']) : 'libre';

    $user      = $uid ? get_userdata($uid) : null;
    $is_tenant = $user && in_array(LFI_NCT_ROLE_TENANT, (array) $user->roles, true);
    if (!$is_tenant) { $user = null; $uid = 0; }
    $user_tel  = $user ? (string) get_user_meta($user->ID, 'lfi_nct_tel', true) : '';

    /* Prochain événement pour les variables {{event_*}} */
    $event_id = isset($_GET['event']) ? (int) $_GET['event'] : 0;
    if (!$event_id && function_exists('lfi_nct_sms_upcoming_events')) {
        $upc = lfi_nct_sms_upcoming_events(1);
        if ($upc) $event_id = $upc[0]->ID;
    }
    $event_post = $event_id ? get_post($event_id) : null;
    $event_vars = function_exists('lfi_nct_sms_event_vars') ? lfi_nct_sms_event_vars($event_post) : [];

    /* Rendu du body avec variables */
    $templates = lfi_nct_tenant_sms_templates();
    $template  = $templates[$mode] ?? $templates['libre'];
    $body = $template['body'];
    if ($user && function_exists('lfi_nct_sms_render')) {
        $fake_membre = (object) [
            'id'     => $user->ID,
            'prenom' => $user->first_name ?: $user->display_name,
            'nom'    => $user->last_name,
            'pseudo' => '',
            'tel'    => $user_tel,
        ];
        $body = lfi_nct_sms_render($body, $fake_membre, $event_vars);
    }

    lfi_nct_app_screen_open('📱 SMS aux locataires', count($tenants_with_tel) . ' locataire(s) avec téléphone');

    if (!empty($_GET['logged'])) lfi_nct_app_flash('✅ SMS noté comme envoyé.');

    if (empty($tenants_with_tel)) {
        echo '<div class="lfi-app-empty">Aucun locataire avec téléphone enregistré. Crée des comptes via 🏠 Comptes Locataires.</div>';
        lfi_nct_app_screen_close();
        return;
    }

    /* Sélecteur destinataire + modèle */
    echo '<form method="get" class="lfi-app-form">';
    echo '<input type="hidden" name="vue" value="sms-locataires">';
    echo '<label>👤 Destinataire<select name="uid" required onchange="this.form.submit()">';
    echo '<option value="">— choisir un·e locataire —</option>';
    foreach ($tenants_with_tel as $t) {
        echo '<option value="' . (int) $t['uid'] . '" ' . selected($uid, $t['uid'], false) . '>' . esc_html($t['name'] . ' — ' . $t['tel']) . '</option>';
    }
    echo '</select></label>';
    if ($event_post) {
        echo '<div class="lfi-app-help">📅 Événement lié pour les variables <code>{{event_*}}</code> : <strong>' . esc_html(get_the_title($event_post)) . '</strong></div>';
    }
    echo '</form>';

    /* Onglets de mode */
    echo '<div class="lfi-app-modes" style="grid-template-columns:repeat(2,1fr)">';
    foreach ($templates as $k => $t) {
        $cls = $mode === $k ? 'on' : '';
        echo '<a class="md ' . esc_attr($cls) . '" href="' . esc_url(lfi_nct_app_url('sms-locataires', ['uid' => $uid, 'mode' => $k])) . '">' . esc_html($t['nom']) . '</a>';
    }
    echo '</div>';

    if (!$user) {
        echo '<div class="lfi-app-help">Choisis un·e destinataire au-dessus pour voir le message rendu.</div>';
        lfi_nct_app_screen_close();
        return;
    }

    /* Aperçu + textarea modifiable + lien sms: live */
    $tel_clean = preg_replace('/[^\d+]/', '', $user_tel);
    echo '<div class="lfi-app-card sms-preview">';
    echo '<div class="head">';
    echo '<div class="who">' . esc_html($user->display_name) . '</div>';
    echo '<div class="badge">' . esc_html($user_tel) . '</div>';
    echo '</div>';

    echo '<label for="ten-sms-body" style="display:block;margin-top:8px;font-size:.85em;color:#555">Texte du SMS — modifiable (vouvoiement par défaut)</label>';
    echo '<textarea id="ten-sms-body" rows="8" class="sms-body" data-tel="' . esc_attr($tel_clean) . '">' . esc_textarea($body) . '</textarea>';
    echo '<div class="lfi-app-help"><small><span id="ten-sms-count">' . mb_strlen($body) . '</span> caractère(s)</small></div>';

    echo '<div class="row-actions">';
    echo '<a id="ten-sms-link" class="btn-primary big" href="sms:' . esc_attr($tel_clean) . '">📲 Ouvrir mon SMS</a>';
    echo '<button type="button" class="btn-ghost" onclick="navigator.clipboard.writeText(document.getElementById(\'ten-sms-body\').value);this.textContent=\'✓ Copié\';">📋 Copier</button>';
    echo '</div>';

    echo '<form method="post" class="row-actions">';
    wp_nonce_field('lfi_app_sms_log');
    echo '<input type="hidden" name="lfi_app_sms_log" value="1">';
    echo '<input type="hidden" name="uid"  value="' . (int) $uid . '">';
    echo '<input type="hidden" name="tel"  value="' . esc_attr($user_tel) . '">';
    echo '<input type="hidden" name="body" id="ten-sms-body-h" value="' . esc_attr($body) . '">';
    echo '<input type="hidden" name="mode" value="' . esc_attr($mode) . '">';
    echo '<button type="submit" class="btn-ghost">✅ Marquer comme envoyé</button>';
    echo '</form>';
    echo '</div>';

    ?>
    <script>
    (function () {
        var ta = document.getElementById('ten-sms-body');
        var lnk = document.getElementById('ten-sms-link');
        var cnt = document.getElementById('ten-sms-count');
        var hid = document.getElementById('ten-sms-body-h');
        if (!ta || !lnk) return;
        var tel = ta.dataset.tel || '';
        function refresh() {
            var b = ta.value || '';
            lnk.href = 'sms:' + tel + '?body=' + encodeURIComponent(b);
            if (cnt) cnt.textContent = b.length;
            if (hid) hid.value = b;
        }
        ta.addEventListener('input', refresh);
        refresh();
    })();
    </script>
    <?php

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  Page « Mon profil » — locataire OU membre GA                   *
 *  Change email + change mot de passe, login en lecture seule     *
 * ============================================================== */

function lfi_nct_app_view_mon_profil() {
    if (!is_user_logged_in()) return;
    $user = wp_get_current_user();
    /* Réservée aux non-admins : un admin a wp-admin pour ça */
    if (current_user_can('manage_options')) {
        wp_safe_redirect(admin_url('profile.php'));
        exit;
    }

    $err = null; $ok = null;

    /* Changer le mot de passe */
    if (!empty($_POST['lfi_app_change_pwd']) && check_admin_referer('lfi_app_change_pwd')) {
        $current = (string) ($_POST['current_pwd'] ?? '');
        $new1    = (string) ($_POST['new_pwd']     ?? '');
        $new2    = (string) ($_POST['new_pwd_2']   ?? '');
        if (!wp_check_password($current, $user->user_pass, $user->ID)) {
            $err = 'Mot de passe actuel incorrect.';
        } elseif ($new1 !== $new2) {
            $err = 'Les deux nouveaux mots de passe ne correspondent pas.';
        } elseif (strlen($new1) < 8) {
            $err = 'Le nouveau mot de passe doit faire au moins 8 caractères.';
        } else {
            wp_set_password($new1, $user->ID);
            /* Re-loggue immédiatement pour ne pas se faire déconnecter */
            wp_clear_auth_cookie();
            wp_set_auth_cookie($user->ID, true);
            wp_safe_redirect(lfi_nct_app_url('mon-profil', ['pwd' => 1]));
            exit;
        }
    }

    /* Changer l'email */
    if (!empty($_POST['lfi_app_change_email']) && check_admin_referer('lfi_app_change_email')) {
        $email = sanitize_email(wp_unslash($_POST['new_email'] ?? ''));
        if (!is_email($email)) {
            $err = 'Adresse email invalide.';
        } elseif ($email === $user->user_email) {
            $err = 'Cette adresse est déjà la vôtre.';
        } elseif (email_exists($email)) {
            $err = 'Cette adresse est déjà utilisée par un autre compte.';
        } else {
            wp_update_user(['ID' => $user->ID, 'user_email' => $email]);
            wp_safe_redirect(lfi_nct_app_url('mon-profil', ['email' => 1]));
            exit;
        }
    }

    lfi_nct_app_screen_open('✏️ Mon profil', 'Modifier vos informations');

    if ($err) lfi_nct_app_flash('❌ ' . $err, 'err');
    if (!empty($_GET['pwd']))   lfi_nct_app_flash('🔑 Mot de passe modifié.');
    if (!empty($_GET['email'])) lfi_nct_app_flash('✉️ Email modifié.');

    echo '<div class="lfi-app-card">';
    echo '<div class="head"><div class="who">👤 Vos informations</div></div>';
    echo '<div class="meta">';
    echo '<span class="meta-chip">🪪 ' . esc_html($user->user_login) . '</span>';
    if ($user->user_email) echo '<span class="meta-chip">✉️ ' . esc_html($user->user_email) . '</span>';
    echo '</div>';
    echo '<div class="lfi-app-help"><small>L\'identifiant de connexion ne peut pas être modifié. Pour le changer, contactez le GA.</small></div>';
    echo '</div>';

    /* Form changer email */
    echo '<details class="lfi-app-collapse" style="margin-top:14px"><summary>✉️ Changer mon adresse email</summary>';
    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_change_email');
    echo '<input type="hidden" name="lfi_app_change_email" value="1">';
    echo '<label>Nouvelle adresse email<input type="email" name="new_email" required></label>';
    echo '<button type="submit" class="btn-primary">✓ Enregistrer</button>';
    echo '</form></details>';

    /* Form changer mot de passe */
    echo '<details class="lfi-app-collapse" open><summary>🔑 Changer mon mot de passe</summary>';
    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_change_pwd');
    echo '<input type="hidden" name="lfi_app_change_pwd" value="1">';
    echo '<label>Mot de passe actuel<input type="password" name="current_pwd" autocomplete="current-password" required></label>';
    echo '<label>Nouveau mot de passe (8 caractères min.)<input type="password" name="new_pwd" autocomplete="new-password" required minlength="8"></label>';
    echo '<label>Confirmer le nouveau mot de passe<input type="password" name="new_pwd_2" autocomplete="new-password" required minlength="8"></label>';
    echo '<button type="submit" class="btn-primary">✓ Enregistrer</button>';
    echo '</form></details>';

    /* Bloc « mot de passe oublié » */
    echo '<div class="lfi-app-help" style="margin-top:18px">';
    echo '🔓 <strong>Mot de passe oublié ?</strong><br>';
    echo 'Si vous perdez votre mot de passe, vous pouvez en demander un nouveau en cliquant sur « Mot de passe oublié » depuis l\'écran de connexion. Un email de réinitialisation vous sera envoyé automatiquement. Aucune validation manuelle n\'est nécessaire.';
    echo '</div>';

    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  MODE APERÇU : admin voit l'app comme un·e locataire ou GA       *
 *  - Cookie lfi_app_preview_uid posé par le picker                 *
 *  - Le shortcode l'applique via wp_set_current_user au début      *
 *  - Bannière rouge en haut avec bouton « Sortir »                 *
 * ============================================================== */

function lfi_nct_app_preview_uid_from_cookie() {
    return (int) ($_COOKIE['lfi_app_preview_uid'] ?? 0);
}

/**
 * Applique le mode aperçu : si l'utilisateur actuel est admin ET
 * un cookie lfi_app_preview_uid est posé, override $current_user
 * pour la durée du rendu de l'app. Renvoie l'user prévisualisé.
 */
function lfi_nct_app_preview_apply() {
    if (!is_user_logged_in()) return null;
    if (!user_can(get_current_user_id(), 'manage_options')) return null;
    $puid = lfi_nct_app_preview_uid_from_cookie();
    if ($puid <= 0) return null;
    $u = get_userdata($puid);
    if (!$u) return null;
    $roles = (array) $u->roles;
    if (!in_array(LFI_NCT_ROLE_TENANT, $roles, true) && !in_array(LFI_NCT_ROLE_GA, $roles, true)) {
        return null;
    }
    wp_set_current_user($puid);
    return $u;
}

function lfi_nct_app_render_preview_banner($previewed_user) {
    $exit_url = esc_url(lfi_nct_app_url('preview-exit'));
    echo '<div class="lfi-preview-banner">';
    echo '<div class="lab">👁 Aperçu en tant que <strong>' . esc_html($previewed_user->display_name) . '</strong> (' . esc_html($previewed_user->user_login) . ')</div>';
    echo '<a href="' . $exit_url . '" class="quit">✕ Sortir</a>';
    echo '</div>';
}

function lfi_nct_app_view_preview_picker() {
    if (!user_can(get_current_user_id(), 'manage_options') && !user_can(lfi_nct_app_preview_uid_from_cookie() ?: 0, 'manage_options')) {
        /* Si on est déjà en aperçu, l'admin réel l'est. Le cookie n'est posé
           que par lui. On accepte donc le rendu. */
    }
    /* On vérifie via le cookie ou l'user connecté réel */
    $real_admin_id = 0;
    if (is_user_logged_in()) {
        $u = wp_get_current_user();
        if (user_can($u->ID, 'manage_options')) $real_admin_id = $u->ID;
    }
    if (!$real_admin_id) return;

    $tenants = get_users([
        'role' => LFI_NCT_ROLE_TENANT,
        'fields' => ['ID', 'display_name', 'user_login'],
        'number' => 200,
        'orderby' => 'display_name', 'order' => 'ASC',
    ]);
    $gas = get_users([
        'role' => LFI_NCT_ROLE_GA,
        'fields' => ['ID', 'display_name', 'user_login'],
        'number' => 200,
        'orderby' => 'display_name', 'order' => 'ASC',
    ]);

    lfi_nct_app_screen_open('👁 Aperçu de l\'app', 'Voir ce que voient les autres utilisateurs');

    echo '<div class="lfi-app-help">Choisis un·e locataire ou un membre du GA pour visualiser exactement ce qu\'il·elle voit dans l\'app. Tu pourras cliquer dans l\'interface comme eux. Un bandeau rouge en haut te rappelle que tu es en mode aperçu. Touche « Sortir » pour revenir à la vue admin.</div>';

    echo '<h3 style="margin:18px 0 8px">🏠 Locataires (' . count($tenants) . ')</h3>';
    if (empty($tenants)) {
        echo '<div class="lfi-app-empty">Aucun compte locataire créé.</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        foreach ($tenants as $u) {
            echo '<li class="lfi-app-card">';
            echo '<div class="head"><div class="who">' . esc_html($u->display_name) . '</div><div class="badge">Locataire</div></div>';
            echo '<div class="meta"><span class="meta-chip">@' . esc_html($u->user_login) . '</span></div>';
            echo '<div class="row-actions">';
            echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('preview-set', ['uid' => $u->ID])) . '">👁 Voir comme ' . esc_html($u->display_name) . '</a>';
            echo '</div></li>';
        }
        echo '</ul>';
    }

    echo '<h3 style="margin:18px 0 8px">👥 Membres du GA (' . count($gas) . ')</h3>';
    if (empty($gas)) {
        echo '<div class="lfi-app-empty">Aucun compte GA créé.</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        foreach ($gas as $u) {
            echo '<li class="lfi-app-card">';
            echo '<div class="head"><div class="who">' . esc_html($u->display_name) . '</div><div class="badge">GA</div></div>';
            echo '<div class="meta"><span class="meta-chip">@' . esc_html($u->user_login) . '</span></div>';
            echo '<div class="row-actions">';
            echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('preview-set', ['uid' => $u->ID])) . '">👁 Voir comme ' . esc_html($u->display_name) . '</a>';
            echo '</div></li>';
        }
        echo '</ul>';
    }

    lfi_nct_app_screen_close();
}

function lfi_nct_app_view_preview_set() {
    if (!current_user_can('manage_options')) {
        wp_safe_redirect(lfi_nct_app_url());
        exit;
    }
    $uid = isset($_GET['uid']) ? (int) $_GET['uid'] : 0;
    $u = $uid ? get_userdata($uid) : null;
    if (!$u) {
        wp_safe_redirect(lfi_nct_app_url('preview'));
        exit;
    }
    $secure = is_ssl();
    setcookie('lfi_app_preview_uid', (string) $uid, time() + 8 * HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, $secure, true);
    wp_safe_redirect(home_url('/app/'));
    exit;
}

function lfi_nct_app_view_preview_exit() {
    $secure = is_ssl();
    setcookie('lfi_app_preview_uid', '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN, $secure, true);
    unset($_COOKIE['lfi_app_preview_uid']);
    wp_safe_redirect(lfi_nct_app_url('preview'));
    exit;
}

/* ============================================================== *
 *  Page « Installer l'app » + demandes de permissions              *
 *  Accessible à tout utilisateur connecté, et même non connecté    *
 *  (sert de landing page depuis les liens SMS/email).              *
 * ============================================================== */

function lfi_nct_app_view_installer() {
    /* Ne pas exiger une connexion : un email d'invitation peut amener
       quelqu'un ici avant son premier login. */
    lfi_nct_app_screen_open('📲 Installer l\'app', 'En un geste, sur votre téléphone');

    echo '<div class="lfi-app-help" style="margin-bottom:14px"><strong>L\'app du GA fonctionne mieux installée sur votre téléphone</strong> : icône directe sur l\'écran d\'accueil, plein écran sans la barre du navigateur, accès rapide à la caméra et aux photos pour vos signalements.</div>';

    /* Bouton d'install Android (s'active si beforeinstallprompt déclenché) */
    echo '<div id="lfi-install-android" style="display:none">';
    echo '<button type="button" id="lfi-install-android-btn" class="btn-primary big">📥 Installer l\'app maintenant (Android)</button>';
    echo '<div class="lfi-app-help" style="margin-top:6px"><small>Chrome détecte que cette app est installable. Touchez ce bouton.</small></div>';
    echo '</div>';

    /* Instructions iOS */
    echo '<details class="lfi-app-collapse" open><summary>📱 iPhone — installer en 30 secondes</summary>';
    echo '<div style="padding:14px 16px;background:#fff;border-top:1px solid #eee">';
    echo '<ol style="margin:0;padding-left:1.4em;line-height:1.7">';
    echo '<li>Ouvrez cette page dans <strong>Safari</strong> (pas Chrome, pas un autre navigateur)</li>';
    echo '<li>Touchez le bouton <strong>Partager</strong> en bas : <span style="display:inline-block;background:#007aff;color:#fff;padding:2px 8px;border-radius:4px">⬆</span></li>';
    echo '<li>Faites défiler et touchez <strong>« Sur l\'écran d\'accueil »</strong></li>';
    echo '<li>Touchez <strong>Ajouter</strong> en haut à droite</li>';
    echo '</ol>';
    echo '<div class="lfi-app-help" style="margin-top:10px"><small>L\'icône rouge « GA LFI » apparaît sur votre bureau. Touchez-la pour ouvrir l\'app, plein écran sans la barre Safari.</small></div>';
    echo '</div></details>';

    /* Instructions Android (générique) */
    echo '<details class="lfi-app-collapse"><summary>🤖 Android — installer en 30 secondes</summary>';
    echo '<div style="padding:14px 16px;background:#fff;border-top:1px solid #eee">';
    echo '<ol style="margin:0;padding-left:1.4em;line-height:1.7">';
    echo '<li>Ouvrez cette page dans <strong>Chrome</strong></li>';
    echo '<li>Touchez le menu <strong>⋮</strong> en haut à droite</li>';
    echo '<li>Touchez <strong>« Installer l\'application »</strong> (ou « Ajouter à l\'écran d\'accueil »)</li>';
    echo '<li>Confirmez avec <strong>Installer</strong></li>';
    echo '</ol>';
    echo '<div class="lfi-app-help" style="margin-top:10px"><small>L\'icône apparaît sur votre écran d\'accueil et dans le tiroir d\'apps. Touchez-la pour ouvrir l\'app, plein écran.</small></div>';
    echo '</div></details>';

    /* Section autorisations */
    echo '<h3 style="margin:24px 0 8px">🔐 Autoriser les fonctionnalités</h3>';
    echo '<div class="lfi-app-help" style="margin-bottom:12px">Pour vos signalements et vos démarches, l\'app a besoin de votre accord pour utiliser certaines fonctions de votre téléphone. Vous restez maître·sse : vous pouvez accorder ou refuser chacune, et changer d\'avis plus tard dans les réglages.</div>';

    echo '<div class="lfi-app-perms">';

    /* Caméra */
    echo '<div class="lfi-perm-card" id="perm-camera">';
    echo '<div class="head"><span class="ico">📷</span><div><strong>Caméra</strong><br><small>Pour prendre directement une photo des dégradations</small></div></div>';
    echo '<button type="button" class="btn-primary" data-perm="camera">Autoriser</button>';
    echo '<div class="status" data-status="camera">Pas encore demandé</div>';
    echo '</div>';

    /* Photos / fichiers */
    echo '<div class="lfi-perm-card" id="perm-files">';
    echo '<div class="head"><span class="ico">🖼</span><div><strong>Photos &amp; fichiers</strong><br><small>Pour envoyer une photo déjà prise depuis votre galerie</small></div></div>';
    echo '<button type="button" class="btn-primary" data-perm="files">Tester l\'accès</button>';
    echo '<div class="status" data-status="files">Pas encore demandé</div>';
    echo '</div>';

    /* Notifications */
    echo '<div class="lfi-perm-card" id="perm-notif">';
    echo '<div class="head"><span class="ico">🔔</span><div><strong>Notifications</strong><br><small>Pour recevoir les conseils du jour et les rappels d\'événement</small></div></div>';
    echo '<button type="button" class="btn-primary" data-perm="notif">Autoriser</button>';
    echo '<div class="status" data-status="notif">Pas encore demandé</div>';
    echo '</div>';

    /* Géoloc */
    echo '<div class="lfi-perm-card" id="perm-geo">';
    echo '<div class="head"><span class="ico">📍</span><div><strong>Géolocalisation</strong><br><small>Pour vous situer automatiquement lors d\'un signalement</small></div></div>';
    echo '<button type="button" class="btn-primary" data-perm="geo">Autoriser</button>';
    echo '<div class="status" data-status="geo">Pas encore demandé</div>';
    echo '</div>';

    echo '</div>';

    echo '<div class="lfi-app-help" style="margin-top:18px"><small>🔒 Aucune donnée n\'est collectée à votre insu. Toutes ces permissions sont gérées par le système de votre téléphone, jamais par nous. Vous pouvez les révoquer à tout moment dans les réglages de votre téléphone (Safari ou Chrome > Paramètres du site).</small></div>';

    /* JS qui gère beforeinstallprompt et les demandes de permission */
    ?>
    <script>
    (function () {
        /* ----- Install Android via beforeinstallprompt ----- */
        var deferredPrompt = null;
        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            deferredPrompt = e;
            var box = document.getElementById('lfi-install-android');
            if (box) box.style.display = 'block';
        });
        var btnAndroid = document.getElementById('lfi-install-android-btn');
        if (btnAndroid) {
            btnAndroid.addEventListener('click', function () {
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function (choice) {
                    var box = document.getElementById('lfi-install-android');
                    if (box) {
                        box.innerHTML = choice.outcome === 'accepted'
                            ? '<div class="lfi-app-flash ok">✅ App installée. Touchez l\'icône sur votre écran d\'accueil.</div>'
                            : '<div class="lfi-app-flash err">L\'installation a été annulée.</div>';
                    }
                    deferredPrompt = null;
                });
            });
        }

        /* ----- Permission helpers ----- */
        function setStatus(key, msg, ok) {
            var el = document.querySelector('[data-status="' + key + '"]');
            if (!el) return;
            el.textContent = msg;
            el.classList.remove('ok', 'err');
            el.classList.add(ok ? 'ok' : 'err');
        }
        async function askCamera() {
            try {
                var stream = await navigator.mediaDevices.getUserMedia({ video: true });
                stream.getTracks().forEach(function (t) { t.stop(); });
                setStatus('camera', '✅ Caméra autorisée', true);
            } catch (err) {
                setStatus('camera', '❌ Refusé ou indisponible : ' + (err && err.message ? err.message : 'erreur'), false);
            }
        }
        function askFiles() {
            var inp = document.createElement('input');
            inp.type = 'file';
            inp.accept = 'image/*';
            inp.style.display = 'none';
            document.body.appendChild(inp);
            inp.addEventListener('change', function () {
                setStatus('files', inp.files.length ? '✅ Accès photos OK (' + inp.files[0].name + ')' : '❌ Aucun fichier sélectionné', !!inp.files.length);
                document.body.removeChild(inp);
            }, { once: true });
            inp.click();
        }
        async function askNotif() {
            if (!('Notification' in window)) {
                setStatus('notif', '❌ Non supporté par ce navigateur', false);
                return;
            }
            try {
                var perm = await Notification.requestPermission();
                if (perm === 'granted') {
                    setStatus('notif', '✅ Notifications autorisées', true);
                    new Notification('GA LFI Clos Toreau', { body: 'Vos notifications sont actives.' });
                } else {
                    setStatus('notif', '❌ Refusé (' + perm + ')', false);
                }
            } catch (err) {
                setStatus('notif', '❌ Erreur : ' + err.message, false);
            }
        }
        function askGeo() {
            if (!navigator.geolocation) {
                setStatus('geo', '❌ Géolocalisation non supportée', false);
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    setStatus('geo', '✅ Position obtenue (' + pos.coords.latitude.toFixed(3) + ', ' + pos.coords.longitude.toFixed(3) + ')', true);
                },
                function (err) {
                    setStatus('geo', '❌ Refusé ou indisponible (' + err.message + ')', false);
                }
            );
        }
        var handlers = { camera: askCamera, files: askFiles, notif: askNotif, geo: askGeo };
        document.querySelectorAll('[data-perm]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.dataset.perm;
                if (handlers[key]) handlers[key]();
            });
        });
    })();
    </script>
    <?php

    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  CSS supplémentaires                                              *
 * ============================================================== */
add_action('wp_footer', 'lfi_nct_app_pro_styles', 200);
function lfi_nct_app_pro_styles() {
    global $post;
    if (!is_a($post, 'WP_Post') || $post->post_name !== 'app') return;
    ?>
    <style>
    /* Galerie photos locataire */
    .lfi-tenant-gallery {
        display: grid; grid-template-columns: 1fr; gap: 10px;
    }
    @media (min-width: 600px) {
        .lfi-tenant-gallery { grid-template-columns: 1fr 1fr; }
    }
    .lfi-tenant-photo {
        background: #fff; border-radius: 12px; overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .lfi-tenant-photo img { display: block; width: 100%; height: auto; }
    .lfi-tenant-photo .info { padding: 10px 12px; }
    .lfi-tenant-photo .piece { font-weight: 700; color: #c8102e; font-size: .9em; }
    .lfi-tenant-photo .note { font-size: .85em; color: #444; margin: 4px 0; line-height: 1.3; }
    .lfi-tenant-photo .when { font-size: .78em; color: #888; }
    .lfi-tenant-photo form.row-actions { padding: 0 12px 10px; margin: 0; }

    /* Champ file plus visible */
    .lfi-app-form input[type=file] {
        padding: 10px; border: 2px dashed #ccc; border-radius: 10px;
        background: #fafafa; cursor: pointer;
    }

    /* Cartes de permissions */
    .lfi-app-perms { display: flex; flex-direction: column; gap: 10px; }
    .lfi-perm-card { background: #fff; border-radius: 12px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    .lfi-perm-card .head { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 10px; }
    .lfi-perm-card .head .ico { font-size: 1.8em; line-height: 1; flex-shrink: 0; }
    .lfi-perm-card .head strong { font-size: 1em; color: #1a1a1a; }
    .lfi-perm-card .head small { color: #666; font-size: .82em; line-height: 1.4; }
    .lfi-perm-card .status {
        margin-top: 8px; padding: 8px 10px; border-radius: 6px;
        font-size: .82em; color: #777; background: #f5f5f5;
    }
    .lfi-perm-card .status.ok  { background: #e7f5ee; color: #186a3b; }
    .lfi-perm-card .status.err { background: #fff3f5; color: #a30b25; }
    .lfi-perm-card button { width: 100%; }

    /* Légende de la carte */
    .lfi-map-legend {
        display: flex; flex-wrap: wrap; gap: 8px;
        margin: 0 0 10px; font-size: .78em;
    }
    .lfi-map-legend span {
        display: inline-flex; align-items: center; gap: 5px;
        background: #fff; padding: 4px 10px; border-radius: 999px;
        box-shadow: 0 1px 2px rgba(0,0,0,.06);
    }
    .lfi-map-legend i {
        width: 12px; height: 12px; border-radius: 50%;
        border: 2px solid #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.1);
    }

    /* Barres horizontales des stats */
    .lfi-app-bars { display: flex; flex-direction: column; gap: 6px; }
    .lfi-app-bars .bar-row { background: #fff; padding: 8px 12px; border-radius: 10px; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
    .lfi-app-bars .bar-label { font-size: .85em; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
    .lfi-app-bars .bar-track {
        position: relative; background: #f0f0f0; border-radius: 6px;
        height: 22px; overflow: hidden;
    }
    .lfi-app-bars .bar-fill {
        height: 100%; background: #c8102e; border-radius: 6px;
        transition: width .4s ease;
    }
    .lfi-app-bars .bar-n {
        position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
        font-size: .8em; font-weight: 700; color: #1a1a1a;
        background: rgba(255,255,255,.8); padding: 0 6px; border-radius: 4px;
    }
    </style>
    <?php
}
