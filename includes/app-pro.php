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
 * salutation + corps + bloc événement optionnel + signature + footer RGPD.
 */
function lfi_nct_email_wrap_html($prenom, $body_html, $event_html = '', $signature_key = 'collectif', $rgpd_text = '') {
    $sig_html = lfi_nct_render_signature_html($signature_key);
    if ($rgpd_text === '') {
        $rgpd_text = 'Vous recevez cet email du Groupe d\'Action LFI Nantes Sud Clos Toreau.';
    }
    return ''
        . '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;background:#fff">'
        . '<div style="background:linear-gradient(135deg,#c8102e,#a30b25);color:#fff;padding:18px 22px">'
        .   '<div style="font-size:11px;letter-spacing:.8px;opacity:.85">LA FRANCE INSOUMISE · NANTES SUD</div>'
        .   '<div style="font-size:22px;font-weight:900;margin-top:2px">Φ Clos Toreau</div>'
        . '</div>'
        . '<div style="padding:22px 22px 6px;color:#1a1a1a;line-height:1.5">'
        .   '<p style="margin:0 0 12px">Bonjour ' . esc_html($prenom ?: '') . ',</p>'
        .   $body_html
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
    if ($u->user_email) echo '<a class="btn-ghost" href="mailto:' . esc_attr($u->user_email) . '">✉️ Email</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('email')) . '">📨 Email blast</a>';
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
    </style>
    <?php
}
