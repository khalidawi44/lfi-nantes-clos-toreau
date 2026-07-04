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
    /* Admin du GA — données nominatives sensibles (RGPD), strictement
       cloisonnées : chaque GA ne voit QUE ses propres locataires. */
    if (!(function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'))) return;
    global $wpdb;

    $tenant_args = [
        'role'    => LFI_NCT_ROLE_TENANT,
        'fields'  => ['ID', 'user_login', 'display_name', 'user_email'],
        'number'  => 500,
        'orderby' => 'display_name',
        'order'   => 'ASC',
    ];
    if (function_exists('lfi_nct_users_ga_query')) $tenant_args = lfi_nct_users_ga_query($tenant_args);
    $tenants = get_users($tenant_args);

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
    if (!(function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'))) {
        lfi_nct_app_screen_open('📂 Dossier locataire');
        echo '<div class="lfi-app-empty">Le profil complet d\'un locataire (avec ses données d\'enquête) est réservé aux administrateurs du groupe.<br><br><a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dossiers-juridiques')) . '">📁 Voir les dossiers juridiques</a></div>';
        lfi_nct_app_screen_close(false);
        return;
    }
    global $wpdb;

    $uid = (int) ($_GET['uid'] ?? 0);
    $u = $uid ? get_userdata($uid) : null;
    /* Cloisonnement : on n'ouvre que les locataires de SON GA. */
    $in_scope = !function_exists('lfi_nct_uid_in_scope') || lfi_nct_uid_in_scope($uid);
    if (!$u || !$in_scope || !in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) {
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

    /* 🏆 Clore une BATAILLE → coupe + réussite anonyme + célébration du GA.
       ⚡ urgence : le dossier reste OUVERT (la 2e bataille continue).
       💶 indemnisation : le dossier est réellement clos.
       Une coupe par bataille, une famille par locataire (cf. victoires.php). */
    if (!empty($_POST['lfi_dossier_win']) && check_admin_referer('lfi_dossier_win')) {
        $bataille = (($_POST['bataille'] ?? '') === 'indemnisation') ? 'indemnisation' : 'urgence';
        if (function_exists('lfi_nct_victoire_record')) {
            lfi_nct_victoire_record($u->ID, $bataille, 0, 'manuel');
        }
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'won' => $bataille])); exit;
    }
    /* Annuler une coupe (fausse détection auto). */
    if (!empty($_POST['lfi_dossier_win_annuler']) && check_admin_referer('lfi_dossier_win')) {
        $bataille = (($_POST['bataille'] ?? '') === 'indemnisation') ? 'indemnisation' : 'urgence';
        if (function_exists('lfi_nct_victoire_annuler')) lfi_nct_victoire_annuler($u->ID, $bataille);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'unwon' => 1])); exit;
    }

    /* Partage de l'espace avec le locataire : génère le lien magique (sur clic,
       usage unique) → à envoyer par SMS. Le locataire se connecte, choisit son
       mot de passe (onboarding) puis complète sa fiche / dépose ses pièces. */
    $share_link = '';
    $do_share = (!empty($_POST['lfi_share_tenant']) && check_admin_referer('lfi_share_tenant'))
             || !empty($_GET['autoshare']); /* arrivée depuis « Inviter par SMS » */
    if ($do_share) {
        $share_link = (function_exists('lfi_nct_login_link'))
            ? lfi_nct_login_link((int) $u->ID, function_exists('lfi_nct_app_page_url') ? lfi_nct_app_page_url() : home_url('/app/'))
            : (function_exists('lfi_nct_app_page_url') ? lfi_nct_app_page_url() : home_url('/app/'));
    }

    /* Parcours de suivi : ajout / coche / suppression d'étapes */
    if (!empty($_POST['lfi_app_dossier_step']) && check_admin_referer('lfi_app_dossier_step')) {
        $steps = get_user_meta($u->ID, 'lfi_nct_suivi_steps', true);
        if (!is_array($steps)) $steps = [];
        $action = sanitize_key($_POST['step_action'] ?? '');
        if ($action === 'add') {
            $txt = sanitize_text_field(wp_unslash($_POST['step_text'] ?? ''));
            $echeance = sanitize_text_field(wp_unslash($_POST['step_echeance'] ?? ''));
            if ($txt !== '') {
                $steps[] = ['text' => $txt, 'done' => false, 'echeance' => $echeance, 'created' => current_time('Y-m-d')];
            }
        } elseif ($action === 'toggle') {
            $idx = (int) ($_POST['step_idx'] ?? -1);
            if (isset($steps[$idx])) $steps[$idx]['done'] = !$steps[$idx]['done'];
        } elseif ($action === 'batch') {
            /* Coche/décoche PLUSIEURS étapes d'un coup (cases cochées = faites). */
            $checked = array_map('intval', (array) ($_POST['step_done'] ?? []));
            foreach ($steps as $i => $s) { $steps[$i]['done'] = in_array($i, $checked, true); }
        } elseif ($action === 'del') {
            $idx = (int) ($_POST['step_idx'] ?? -1);
            if (isset($steps[$idx])) { array_splice($steps, $idx, 1); }
        } elseif ($action === 'autofill') {
            /* Génère le parcours-type (n'ajoute que les étapes manquantes). */
            $existing = array_map(function ($s) { return $s['text'] ?? ''; }, $steps);
            foreach (lfi_nct_dossier_parcours_template() as $tpl) {
                if (!in_array($tpl['text'], $existing, true)) {
                    $steps[] = ['text' => $tpl['text'], 'who' => $tpl['who'], 'auto' => !empty($tpl['auto']), 'done' => false, 'echeance' => '', 'created' => current_time('Y-m-d')];
                }
            }
        }
        update_user_meta($u->ID, 'lfi_nct_suivi_steps', array_values($steps));
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'step_saved' => 1]));
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

    /* Bouton retour — revient à la page précédente sans repasser par le menu */
    echo '<div style="margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap">';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('comptes', ['tab' => 'locataires'])) . '">← Tous les locataires</a>';
    echo '<a class="btn-ghost" href="#" onclick="if(history.length>1){history.back();return false;}">↩ Page précédente</a>';
    echo '<a class="btn-primary" style="background:#6a1b9a" href="' . esc_url(lfi_nct_app_url('dossier-avocat', ['uid' => $u->ID])) . '" target="_blank">⚖️ Note pour l\'avocat (PDF)</a>';
    echo '</div>';

    if (!empty($_GET['notes_saved'])) lfi_nct_app_flash('Notes enregistrées.');
    if (!empty($_GET['step_saved']))  lfi_nct_app_flash('✅ Parcours de suivi mis à jour.');
    if (!empty($_GET['won']))  lfi_nct_app_flash('🏆 Coupe posée ! Une réussite ANONYME est prête dans « 🏆 Réussites » — relis-la et publie-la (aucun nom n\'y figure). Les membres du GA verront la victoire à l\'ouverture de l\'app.');
    if (!empty($_GET['unwon'])) lfi_nct_app_flash('Coupe annulée.');
    if (!empty($_GET['avocat_ok'])) lfi_nct_app_flash('⚖️ Dossier confié à l\'avocat·e. Il/elle le voit dans son espace (note + pièces + ligne directe).');

    /* ===== LES DEUX BATAILLES + la demande du locataire (EN HAUT) ===== */
    lfi_nct_dossier_render_batailles($u, $row);

    /* Profil + actions */
    echo '<div class="lfi-app-card">';
    echo '<div class="head"><div class="who">👤 Profil</div><div class="badge">@' . esc_html($u->user_login) . '</div></div>';
    echo '<div class="meta">';
    if ($tel) echo '<a class="meta-chip" href="tel:' . esc_attr($tel) . '">📞 ' . esc_html($tel) . '</a>';
    if ($u->user_email) echo '<a class="meta-chip" href="mailto:' . esc_attr($u->user_email) . '">✉️ ' . esc_html($u->user_email) . '</a>';
    if ($row && $row->adresse) echo '<span class="meta-chip">📍 ' . esc_html(trim($row->adresse . ($row->etage ? ' · ét. ' . $row->etage : ''))) . '</span>';
    echo '</div>';
    $sms_blocked = ($tel && function_exists('lfi_nct_sms_is_blocked')) ? lfi_nct_sms_is_blocked($tel) : false;
    echo '<div class="row-actions">';
    if ($tel && !$sms_blocked) echo '<a class="btn-primary" href="sms:' . esc_attr(preg_replace('/[^\d+]/', '', $tel)) . '">📱 SMS direct</a>';
    if ($tel &&  $sms_blocked) echo '<span class="btn-ghost" style="opacity:.6;cursor:not-allowed" title="A demandé à ne plus recevoir de SMS">🚫 SMS refusés</span>';
    if ($tel) echo '<a class="btn-ghost" href="tel:' . esc_attr(preg_replace('/[^\d+]/', '', $tel)) . '">📞 Appeler</a>';
    if ($u->user_email) echo '<a class="btn-ghost" href="mailto:' . esc_attr($u->user_email) . '">✉️ Email perso</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('comptes', ['tab' => 'locataires', 'open' => $u->ID])) . '">✏️ Éditer la fiche</a>';
    if ($tel && function_exists('lfi_nct_sms_block_toggle_link')) {
        $lbl = $sms_blocked ? '↩ Réautoriser les SMS' : '🚫 Ne plus lui envoyer de SMS';
        echo '<a class="btn-ghost" style="font-size:.85em" href="' . esc_url(lfi_nct_sms_block_toggle_link($tel, $u->display_name)) . '">' . $lbl . '</a>';
    }
    echo '</div>';
    echo '</div>';

    /* ===== Partager l'espace avec le locataire (le fait entrer dans l'app) ===== */
    echo '<div class="lfi-app-card" style="border:2px solid #0066a3;background:#f2f8fd;margin-top:12px">';
    echo '<div class="head"><div class="who">🔗 Partager l\'espace avec le locataire</div></div>';
    $mail_t = sanitize_email((string) $u->user_email);
    $has_mail = ($mail_t !== '' && is_email($mail_t) && stripos($mail_t, '@tenant.') === false && stripos($mail_t, '@partenaire.') === false);
    echo '<div class="com" style="font-size:.92em">Envoie-lui son <strong>espace personnel</strong> : il se connecte en 1 clic, choisit son mot de passe, puis <strong>complète sa fiche</strong>, <strong>dépose ses pièces</strong> et ses <strong>photos</strong>. Tout reste dans son dossier.</div>';
    echo '<div class="meta" style="margin-top:4px">';
    if ($tel) echo '<span class="meta-chip">📞 ' . esc_html($tel) . '</span>';
    if ($has_mail) echo '<span class="meta-chip">✉️ ' . esc_html($mail_t) . '</span>';
    if (!$tel && !$has_mail) echo '<span class="meta-chip" style="color:#c8102e">⚠️ aucun moyen de contact enregistré</span>';
    echo '</div>';
    if ($share_link !== '') {
        $prenom_t = $u->first_name ?: ($row && $row->contact_prenom ? $row->contact_prenom : '');
        $moi = wp_get_current_user();
        $moi_nom = $moi->display_name ?: $moi->user_login;
        $intro = ($prenom_t ? 'Bonjour ' . $prenom_t . ', ' : 'Bonjour, ')
                  . "c'est " . $moi_nom . " du Groupe d'Action La France Insoumise Nantes Sud – Clos Toreau. "
                  . "Comme convenu, voici votre espace personnel pour suivre votre logement (gratuit, confidentiel). "
                  . "Connexion directe (rien à taper) : " . $share_link
                  . " — vous choisirez votre mot de passe, puis vous serez guidé·e pas à pas pour compléter votre profil, votre dossier et envoyer vos photos. On prend rendez-vous pour venir vous voir. On est là pour vous accompagner.";
        echo '<div class="lfi-app-help" style="margin-top:8px;background:#eef7ee;border-left:4px solid #186a3b"><small>✅ Lien généré (usage unique). Envoie-le. <strong>Ne régénère pas</strong> après l\'envoi (ça l\'invalide).</small></div>';
        echo '<div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">';
        if ($tel) echo '<a class="btn-primary" style="background:#0066a3" href="sms:' . esc_attr(preg_replace('/[^\d+]/', '', $tel)) . '?body=' . rawurlencode($intro) . '">📲 Envoyer par SMS</a>';
        if ($has_mail) {
            $subj = 'Votre espace personnel LFI — suivi de votre logement';
            echo '<a class="btn-primary" style="background:#186a3b" href="mailto:' . esc_attr($mail_t) . '?subject=' . rawurlencode($subj) . '&body=' . rawurlencode($intro) . '">✉️ Envoyer par email</a>';
        }
        echo '</div>';
        echo '<textarea readonly onclick="this.select()" style="width:100%;height:90px;margin-top:6px;font-size:.8em;padding:6px;border:1px solid #ccc;border-radius:8px">' . esc_textarea($intro) . '</textarea>';
        if (!$tel && !$has_mail) echo '<div class="lfi-app-help" style="margin-top:4px"><small>⚠️ Ni numéro ni email — copie le message et transmets-le comme tu peux.</small></div>';
        /* Après l'envoi : marquer l'étape « partager l'espace » faite → l'action
           disparaît du tableau de bord et l'étape suivante apparaît. */
        $steps = get_user_meta($u->ID, 'lfi_nct_suivi_steps', true);
        $sidx = -1;
        if (is_array($steps)) foreach ($steps as $i => $s) { if (empty($s['done']) && stripos((string) ($s['text'] ?? ''), 'partager l\'espace') !== false) { $sidx = $i; break; } }
        if ($sidx >= 0) {
            $du = wp_nonce_url(admin_url('admin-post.php?action=lfi_nct_tenant_step_done&uid=' . (int) $u->ID . '&idx=' . $sidx), 'lfi_nct_tstep_' . (int) $u->ID . '_' . $sidx);
            echo '<div style="margin-top:8px"><a class="btn-primary" style="background:#186a3b" href="' . esc_url($du) . '">✅ SMS envoyé — passer à l\'étape suivante</a></div>';
        }
    } else {
        echo '<form method="post" style="margin-top:8px">';
        wp_nonce_field('lfi_share_tenant');
        echo '<input type="hidden" name="lfi_share_tenant" value="1">';
        $lbl = ($tel && $has_mail) ? '🔗 Générer le lien (SMS + email)' : ($has_mail ? '🔗 Générer le lien + l\'email' : '🔗 Générer le lien + le SMS');
        echo '<button type="submit" class="btn-primary" style="background:#0066a3">' . esc_html($lbl) . '</button></form>';
        echo '<div class="lfi-app-help" style="margin-top:4px"><small>Le lien connecte ' . esc_html($u->display_name) . ' d\'un seul clic, sans identifiant (usage unique, 14 jours).</small></div>';
    }
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

    /* Photos prises pendant l'enquête (horodatées) — stockées dans le JSON de
       la réponse. Strictement internes. */
    if ($row && $row->data) {
        $rdata = json_decode($row->data, true);
        $enq_photos = is_array($rdata) ? (array) ($rdata['photos'] ?? []) : [];
        if ($enq_photos) {
            echo '<div class="lfi-app-card" style="margin-top:14px">';
            echo '<div class="head"><div class="who">📸 Photos de l\'enquête</div><div class="badge">' . count($enq_photos) . ' · horodatées</div></div>';
            echo '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px">';
            foreach ($enq_photos as $ph) {
                $pid = (int) ($ph['id'] ?? 0);
                if (!$pid) continue;
                $thumb = wp_get_attachment_image_url($pid, 'medium');
                $full  = wp_get_attachment_url($pid);
                if (!$thumb) continue;
                echo '<a href="' . esc_url($full) . '" target="_blank" rel="noopener" style="text-decoration:none">';
                echo '<img src="' . esc_url($thumb) . '" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:8px;border:1px solid #ccc">';
                echo '<span style="display:block;font-size:.72em;color:#666;text-align:center;margin-top:2px">' . esc_html($ph['date'] ?? '') . '</span>';
                echo '</a>';
            }
            echo '</div></div>';
        }
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
    /* Dossier juridique : OUVRE le dossier existant s'il y en a un,
       sinon ouvre le formulaire de création pré-rempli. */
    $dossier_existant = function_exists('lfi_nct_dossier_find_for_tenant') ? lfi_nct_dossier_find_for_tenant($u->ID) : null;
    if ($dossier_existant) {
        $dossier_url = lfi_nct_app_url('dossier-juridique-edit', ['id' => (int) $dossier_existant->id]);
        $dossier_lbl = 'Ouvrir le dossier';
    } else {
        $dossier_url = lfi_nct_app_url('dossier-juridique-add', $shortcut);
        $dossier_lbl = 'Dossier juridique';
    }
    $actions = [
        ['📁', $dossier_lbl,        $dossier_url],
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

    /* === PARCOURS DE SUIVI — checklist d'actions à mener === */
    lfi_nct_dossier_render_parcours($u);

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
 *  Parcours de suivi — checklist d'étapes (numérotée, cochable)    *
 * ============================================================== */
/* ============================================================== *
 *  FIL D'ACTIONS UNIFIÉ (tableau de bord admin) — la prochaine     *
 *  étape de CHAQUE locataire suivi, cochable en un clic.           *
 * ============================================================== */

/** Handler « ✓ Fait » : coche l'étape et passe à la suivante. */
add_action('admin_post_lfi_nct_tenant_step_done', 'lfi_nct_tenant_step_done_handler');
function lfi_nct_tenant_step_done_handler() {
    if (!is_user_logged_in()) wp_die('non');
    $uid = (int) ($_GET['uid'] ?? 0);
    $idx = (int) ($_GET['idx'] ?? -1);
    $can = current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
    $in_scope = !function_exists('lfi_nct_uid_in_scope') || lfi_nct_uid_in_scope($uid);
    if ($uid && $idx >= 0 && $can && $in_scope && check_admin_referer('lfi_nct_tstep_' . $uid . '_' . $idx)) {
        $steps = get_user_meta($uid, 'lfi_nct_suivi_steps', true);
        if (is_array($steps) && isset($steps[$idx])) {
            $steps[$idx]['done'] = true;
            update_user_meta($uid, 'lfi_nct_suivi_steps', array_values($steps));
        }
    }
    wp_safe_redirect(lfi_nct_app_url('', ['stepok' => 1])); exit;
}

/** Fil « Mes actions locataires » sur le tableau de bord : la 1re étape non faite
 *  de chaque dossier suivi, avec « ✓ Fait » (avance) et « Ouvrir le dossier ». */
function lfi_nct_render_home_tenant_actions() {
    $can = current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
    if (!$can) return;
    $args = ['role' => defined('LFI_NCT_ROLE_TENANT') ? LFI_NCT_ROLE_TENANT : 'lfi_nct_tenant', 'number' => 300, 'fields' => ['ID', 'display_name']];
    if (function_exists('lfi_nct_users_ga_query')) $args = lfi_nct_users_ga_query($args);
    $users = get_users($args);
    $mine = [];   /* étapes où c'est à MOI d'agir */
    $waiting = []; /* en attente du locataire */
    foreach ($users as $u) {
        $steps = get_user_meta($u->ID, 'lfi_nct_suivi_steps', true);
        if (!is_array($steps) || empty($steps)) continue;

        /* Auto-avance : une étape « locataire » avec auto se coche dès qu'il a
           fait sa part (fiche + objectif). On persiste pour ne pas recalculer. */
        $changed = false;
        foreach ($steps as $i => $s) {
            if (!empty($s['skipped'])) continue; /* étape rendue inutile : on l'ignore */
            if (empty($s['done']) && ($s['who'] ?? 'admin') === 'tenant' && !empty($s['auto'])) {
                if (lfi_nct_tenant_part_done((int) $u->ID)) { $steps[$i]['done'] = true; $changed = true; }
                break; /* on ne teste que la 1re non faite */
            }
            if (empty($s['done'])) break;
        }
        if ($changed) update_user_meta($u->ID, 'lfi_nct_suivi_steps', array_values($steps));

        /* Prochaine étape À FAIRE = 1re non cochée ET non « inutile ». */
        $idx = -1;
        foreach ($steps as $i => $s) { if (empty($s['done']) && empty($s['skipped'])) { $idx = $i; break; } }
        if ($idx < 0) continue; /* tout est fait */
        $done_n = 0; foreach ($steps as $s) if (!empty($s['done'])) $done_n++;
        $who = $steps[$idx]['who'] ?? 'admin';
        $row = ['uid' => (int) $u->ID, 'name' => $u->display_name, 'idx' => $idx, 'text' => (string) $steps[$idx]['text'], 'total' => count($steps), 'donen' => $done_n];
        if ($who === 'tenant') $waiting[] = $row; else $mine[] = $row;
    }
    if (empty($mine) && empty($waiting)) return;

    if (!empty($mine)) {
        echo '<div class="lfi-app-card" style="border:2px solid #0066a3;border-radius:14px;padding:12px;margin:0 0 14px">';
        echo '<div style="font-weight:900;color:#0066a3;margin-bottom:6px">📋 Mes actions à faire (' . count($mine) . ')</div>';
        echo '<div style="display:flex;flex-direction:column;gap:8px">';
        foreach ($mine as $it) {
            $done_url = wp_nonce_url(admin_url('admin-post.php?action=lfi_nct_tenant_step_done&uid=' . $it['uid'] . '&idx=' . $it['idx']), 'lfi_nct_tstep_' . $it['uid'] . '_' . $it['idx']);
            $open_url = lfi_nct_app_url('dossier', ['uid' => $it['uid']]);
            echo '<div style="background:#f2f8fd;border:1px solid #cfe0f5;border-radius:10px;padding:10px 12px">';
            echo '<div style="font-weight:700">' . esc_html($it['name']) . ' <span style="font-size:.78em;color:#888;font-weight:400">· étape ' . ($it['donen'] + 1) . '/' . $it['total'] . '</span></div>';
            echo '<div style="font-size:.9em;color:#333;margin:2px 0 6px">👉 ' . esc_html($it['text']) . '</div>';
            echo '<div style="display:flex;gap:6px;flex-wrap:wrap">';
            echo '<a class="btn-primary" style="background:#186a3b;padding:6px 12px;font-size:.85em" href="' . esc_url($done_url) . '">✓ Fait</a>';
            echo '<a class="btn-ghost" style="padding:6px 12px;font-size:.85em" href="' . esc_url($open_url) . '">📂 Ouvrir le dossier</a>';
            echo '</div></div>';
        }
        echo '</div></div>';
    }
    if (!empty($waiting)) {
        echo '<div class="lfi-app-card" style="border:1px solid #e6c65a;background:#fffbf0;border-radius:14px;padding:12px;margin:0 0 14px">';
        echo '<div style="font-weight:800;color:#b8860b;margin-bottom:6px">⏳ En attente du locataire (' . count($waiting) . ')</div>';
        echo '<div class="lfi-app-help" style="margin:0 0 6px"><small>Ces personnes ont leur espace : elles remplissent leur dossier. Rien à faire de ton côté — ça avancera tout seul. Tu peux relancer si ça traîne.</small></div>';
        echo '<div style="display:flex;flex-direction:column;gap:6px">';
        foreach ($waiting as $it) {
            $open_url = lfi_nct_app_url('dossier', ['uid' => $it['uid']]);
            echo '<a href="' . esc_url($open_url) . '" style="text-decoration:none;color:inherit;display:flex;justify-content:space-between;align-items:center;background:#fff;border:1px solid #eee;border-radius:8px;padding:8px 10px">';
            echo '<span><strong>' . esc_html($it['name']) . '</strong> <span style="font-size:.82em;color:#888">— ' . esc_html($it['text']) . '</span></span>';
            echo '<span style="color:#b8860b;font-weight:700;white-space:nowrap">Relancer →</span></a>';
        }
        echo '</div></div>';
    }
}

/** Le parcours-type d'un dossier locataire : la personne s'empare d'abord de sa
 *  fiche, puis on va à l'amiable (urgence d'abord), puis juridique si besoin. */
function lfi_nct_dossier_parcours_template() {
    /* who = 'admin' (à TOI de faire) ou 'tenant' (au LOCATAIRE de faire).
       auto = l'étape se coche toute seule quand le locataire a fait sa part. */
    return [
        ['who' => 'admin',  'text' => "Envoyer le SMS d'invitation au locataire (lien de l'app)"],
        ['who' => 'tenant', 'text' => "Le locataire s'empare de son dossier (fiche, objectif, photos)", 'auto' => 1],
        ['who' => 'admin',  'text' => "Prendre contact et visiter l'appartement (constat, photos)"],
        ['who' => 'admin',  'text' => "Faire signer l'adhésion à l'association (mandat)"],
        ['who' => 'admin',  'text' => "Chiffrer le préjudice avec la personne (selon ses pièces)"],
        ['who' => 'admin',  'text' => "Écrire à NMH : mise en demeure travaux (mandat requis)"],
        ['who' => 'admin',  'text' => "Appeler NMH puis relancer (1re, 2e relance)"],
        ['who' => 'admin',  'text' => "Amiable : négocier travaux / relogement / indemnisation"],
        ['who' => 'admin',  'text' => "Si échec : saisir le SCHS (insalubrité) / l'ARS"],
        ['who' => 'admin',  'text' => "Préparer l'assignation au Tribunal Judiciaire"],
    ];
}

/** Volet INDEMNISATION / juridique : la 2e bataille, lancée quand l'urgence est
 *  gagnée. Réparer le préjudice — amiable d'abord, puis judiciaire. */
function lfi_nct_dossier_indemnisation_steps() {
    return [
        ['who' => 'admin', 'text' => "💶 Chiffrer le préjudice subi (trouble de jouissance, frais engagés, santé)"],
        ['who' => 'admin', 'text' => "💶 Écrire à NMH : demande d'indemnisation amiable (mandat requis)"],
        ['who' => 'admin', 'text' => "💶 Relancer NMH sur l'indemnisation (1re, 2e relance)"],
        ['who' => 'admin', 'text' => "💶 Si échec amiable : saisir la Commission Départementale de Conciliation"],
        ['who' => 'admin', 'text' => "💶 Consulter l'avocat partenaire (Me Valet / Me Goache)"],
        ['who' => 'admin', 'text' => "💶 Préparer l'assignation au Tribunal Judiciaire (indemnisation)"],
    ];
}
/* NETTOYAGE (une fois) : les parcours créés avant le modèle « propriétaire par
   étape » (toi / locataire) sont ré-initialisés avec le nouveau modèle cohérent. */
add_action('init', 'lfi_nct_heal_parcours_who', 17);
function lfi_nct_heal_parcours_who() {
    if (get_option('lfi_nct_heal_parcours_who_v1')) return;
    if (!defined('LFI_NCT_ROLE_TENANT')) return;
    $users = get_users(['role' => LFI_NCT_ROLE_TENANT, 'number' => 2000, 'fields' => ['ID']]);
    foreach ($users as $u) {
        $steps = get_user_meta($u->ID, 'lfi_nct_suivi_steps', true);
        if (!is_array($steps) || empty($steps)) continue;
        $needs = false; foreach ($steps as $s) { if (!isset($s['who'])) { $needs = true; break; } }
        if (!$needs) continue;
        $new = [];
        foreach (lfi_nct_dossier_parcours_template() as $tpl) {
            $new[] = ['text' => $tpl['text'], 'who' => $tpl['who'], 'auto' => !empty($tpl['auto']), 'done' => false, 'echeance' => '', 'created' => current_time('Y-m-d')];
        }
        update_user_meta($u->ID, 'lfi_nct_suivi_steps', $new);
    }
    update_option('lfi_nct_heal_parcours_who_v1', 1, false);
}

/** Déploie (idempotent) les étapes du volet indemnisation dès que l'urgence est
 *  gagnée. Rattrape les dossiers dont l'urgence a été close avant le grafting
 *  automatique. */
function lfi_nct_ensure_indemnisation_steps($uid) {
    if (function_exists('lfi_nct_victoire_won') && !lfi_nct_victoire_won($uid, 'urgence')) return;
    if (!function_exists('lfi_nct_dossier_indemnisation_steps')) return;
    $steps = get_user_meta($uid, 'lfi_nct_suivi_steps', true);
    if (!is_array($steps)) $steps = [];
    $existing = array_map(function ($s) { return $s['text'] ?? ''; }, $steps);
    $changed = false;
    foreach (lfi_nct_dossier_indemnisation_steps() as $tpl) {
        if (!in_array($tpl['text'], $existing, true)) {
            $steps[] = ['text' => $tpl['text'], 'who' => $tpl['who'], 'done' => false, 'echeance' => '', 'created' => current_time('Y-m-d')];
            $existing[] = $tpl['text'];
            $changed = true;
        }
    }
    /* RATTRAPAGE : marquer « inutiles » les étapes urgence restées non cochées
       (dossiers gagnés avant cette logique, ex. Gwenaëlle). Le marqueur
       « GAGNÉE » et les étapes 💶 indemnisation sont préservés. */
    $marker = '⚡ Volet urgence : bataille GAGNÉE 🏆';
    foreach ($steps as $i => $s) {
        $t = (string) ($s['text'] ?? '');
        if ($t === $marker) continue;
        if (mb_strpos($t, '💶') !== false) continue;
        if (empty($s['done']) && empty($s['skipped'])) { $steps[$i]['skipped'] = true; $changed = true; }
    }
    if ($changed) update_user_meta($uid, 'lfi_nct_suivi_steps', array_values($steps));

    /* Backfill des stats « rapidité » si absentes (victoire posée avant). */
    $ust = get_user_meta($uid, 'lfi_nct_urgence_stats', true);
    if (!is_array($ust) || !isset($ust['days'])) {
        $won = (string) get_user_meta($uid, 'lfi_nct_urgence_won', true);
        if ($won !== '') {
            $dates = [];
            $done_at_win = 0; $total_urg = 0;
            foreach ($steps as $s) {
                $t = (string) ($s['text'] ?? '');
                if ($t === $marker || mb_strpos($t, '💶') !== false) continue;
                if (!empty($s['created'])) $dates[] = $s['created'];
                $total_urg++;
                if (!empty($s['done'])) $done_at_win++;
            }
            $started = $dates ? min($dates) : substr($won, 0, 10);
            $days = max(0, (int) round((strtotime(substr($won, 0, 10)) - strtotime($started)) / 86400));
            update_user_meta($uid, 'lfi_nct_urgence_stats', [
                'started_at' => $started, 'won_at' => substr($won, 0, 10), 'days' => $days,
                'done_at_win' => $done_at_win, 'total_at_win' => $total_urg,
            ]);
            /* On enrichit aussi la coupe existante pour le classement. */
            if (function_exists('lfi_nct_victoires_all') && function_exists('lfi_nct_victoires_save')) {
                $vl = lfi_nct_victoires_all(); $vchg = false;
                foreach ($vl as $k => $v) {
                    if ((int) ($v['tenant_uid'] ?? 0) === (int) $uid && ($v['bataille'] ?? '') === 'urgence' && !isset($v['days'])) {
                        $vl[$k]['days'] = $days; $vl[$k]['won_at_step'] = $done_at_win; $vchg = true;
                    }
                }
                if ($vchg) lfi_nct_victoires_save($vl);
            }
        }
    }
}

/* HEAL (une fois) : tous les dossiers dont l'urgence est déjà gagnée reçoivent
   les étapes du volet indemnisation. */
add_action('init', 'lfi_nct_heal_deploy_indemnisation', 18);
function lfi_nct_heal_deploy_indemnisation() {
    if (get_option('lfi_nct_heal_deploy_indemn_v1')) return;
    if (!defined('LFI_NCT_ROLE_TENANT')) return;
    $users = get_users(['role' => LFI_NCT_ROLE_TENANT, 'number' => 2000, 'fields' => ['ID']]);
    foreach ($users as $uid) {
        if (get_user_meta($uid, 'lfi_nct_urgence_won', true)) {
            lfi_nct_ensure_indemnisation_steps((int) $uid);
        }
    }
    update_option('lfi_nct_heal_deploy_indemn_v1', 1, false);
}

/** Le locataire a-t-il fait sa part (fiche + objectif) ? */
function lfi_nct_tenant_part_done($uid) {
    global $wpdb;
    $u = get_userdata($uid); if (!$u) return false;
    $rid = function_exists('lfi_nct_user_tenant_response_id') ? lfi_nct_user_tenant_response_id($uid) : 0;
    $resp = $rid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid)) : null;
    if (!function_exists('lfi_nct_tenant_steps_state')) return false;
    $st = lfi_nct_tenant_steps_state($u, $resp);
    return !empty($st['profil']) && !empty($st['objectif']);
}

/* ============================================================== *
 *  LES DEUX BATAILLES — bandeau haut du dossier :                 *
 *  la demande du locataire + ⚡ urgence / 💶 indemnisation, avec   *
 *  les boutons pour clore chaque bataille (→ coupe + célébration). *
 * ============================================================== */
function lfi_nct_dossier_render_batailles($u, $row) {
    $obj_labels = [
        'travaux'       => '🔧 Que les travaux soient faits',
        'relogement'    => '🏠 Être relogé·e (déménager)',
        'indemnisation' => '💶 Être indemnisé·e pour le préjudice',
        'a_voir'        => '🤝 En discuter avec le GA',
    ];
    $rdata   = ($row && $row->data) ? json_decode($row->data, true) : [];
    $obj_key = is_array($rdata) ? (string) ($rdata['objectif'] ?? '') : '';

    $urg_won = function_exists('lfi_nct_victoire_won') ? lfi_nct_victoire_won($u->ID, 'urgence') : get_user_meta($u->ID, 'lfi_nct_urgence_won', true);
    $ind_won = function_exists('lfi_nct_victoire_won') ? lfi_nct_victoire_won($u->ID, 'indemnisation') : get_user_meta($u->ID, 'lfi_nct_indemn_won', true);

    echo '<div class="lfi-app-card" style="border:2px solid #c8102e;margin-bottom:14px">';
    echo '<div class="head"><div class="who">⚖️ Un dossier, deux batailles</div>';
    if ($urg_won && $ind_won) echo '<div class="badge" style="background:#186a3b;color:#fff">✅ Dossier gagné</div>';
    echo '</div>';

    /* La demande du locataire (son objectif) — tout en haut. */
    echo '<div style="margin:8px 0 12px;padding:10px 12px;background:#fff8f9;border-radius:8px">';
    echo '<div style="font-size:.76em;color:#999;font-weight:800;text-transform:uppercase;letter-spacing:.04em">🎯 La demande du locataire</div>';
    if ($obj_key && isset($obj_labels[$obj_key])) {
        echo '<div style="font-weight:700;margin-top:3px">' . esc_html($obj_labels[$obj_key]) . '</div>';
    } else {
        echo '<div style="margin-top:3px;color:#c8102e;font-weight:600">à définir avec le locataire — <a href="' . esc_url(lfi_nct_app_url('comptes', ['tab' => 'locataires', 'open' => $u->ID])) . '">renseigner l\'objectif</a></div>';
    }
    echo '</div>';

    /* Quand l'urgence est GAGNÉE, on déploie le volet indemnisation : on greffe
       ses étapes au parcours (idempotent — rattrape les dossiers gagnés avant
       le grafting automatique, comme Gwenaëlle). */
    if ($urg_won && function_exists('lfi_nct_ensure_indemnisation_steps')) {
        lfi_nct_ensure_indemnisation_steps($u->ID);
    }

    if (!$urg_won) {
        /* Les deux batailles côte à côte : urgence active, indemnisation à suivre. */
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">';
        echo '<div style="padding:12px;border-radius:10px;background:#fff3f5;border-left:5px solid #c8102e">';
        echo '<div style="font-weight:800;color:#c8102e">⚡ Urgence — en cours</div>';
        echo '<div style="font-size:.82em;color:#555;margin-top:3px">Faire cesser le danger : travaux, relogement d\'urgence, insalubrité.</div>';
        echo '<form method="post" style="margin-top:8px" onsubmit="return confirm(\'NMH a accédé à la demande (travaux lancés, relogement accordé…) ? On clôt le volet urgence : une COUPE est posée et le GA est prévenu. Le dossier reste ouvert pour l\\\'indemnisation.\')">' . wp_nonce_field('lfi_dossier_win', '_wpnonce', true, false) . '<input type="hidden" name="lfi_dossier_win" value="1"><input type="hidden" name="bataille" value="urgence"><button type="submit" class="btn-primary" style="background:#186a3b;width:100%;font-size:.86em">🏆 Bataille gagnée — clore l\'urgence</button></form>';
        echo '</div>';
        echo '<div style="padding:12px;border-radius:10px;background:#fff8e6;border-left:5px solid #bd8600">';
        echo '<div style="font-weight:800;color:#bd8600">💶 Indemnisation — à suivre</div>';
        echo '<div style="font-size:.82em;color:#555;margin-top:3px">Après l\'urgence : trouble de jouissance, préjudice. Se déploie automatiquement dès l\'urgence gagnée.</div>';
        echo '</div>';
        echo '</div>';
    } else {
        /* URGENCE GAGNÉE → repliée en « hamburger » ; INDEMNISATION déployée dessous. */
        echo '<details style="margin:4px 0 10px;background:#e8f5ea;border-radius:10px;border-left:5px solid #186a3b;overflow:hidden">';
        echo '<summary style="cursor:pointer;padding:10px 12px;font-weight:800;color:#186a3b;list-style:none;display:flex;justify-content:space-between;align-items:center"><span>⚡ Volet urgence — 🏆 gagné</span><span style="font-size:1.1em">▾</span></summary>';
        echo '<div style="padding:0 12px 12px"><div style="font-size:.85em;color:#555">Le danger a cessé. <span style="color:#888">' . esc_html(wp_date('j M Y', strtotime($urg_won))) . '</span></div>';
        /* 🏁 Championnat de rapidité : durée + étape de la victoire. */
        $ust = get_user_meta($u->ID, 'lfi_nct_urgence_stats', true);
        if (is_array($ust) && isset($ust['days'])) {
            $d = (int) $ust['days'];
            echo '<div style="margin-top:6px;padding:8px 10px;background:#eef7ee;border-radius:8px;font-size:.85em;color:#186a3b">🏁 <strong>Gagné en ' . $d . ' jour' . ($d > 1 ? 's' : '') . '</strong>';
            if (isset($ust['done_at_win'], $ust['total_at_win']) && (int) $ust['total_at_win'] > 0) {
                echo ' · à l\'étape ' . (int) $ust['done_at_win'] . '/' . (int) $ust['total_at_win'];
            }
            echo ' — <a href="' . esc_url(lfi_nct_app_url('victoires')) . '" style="color:#186a3b;text-decoration:underline">voir le classement</a></div>';
        }
        echo '<form method="post" style="margin-top:6px" onsubmit="return confirm(\'Annuler la coupe du volet urgence ?\')">' . wp_nonce_field('lfi_dossier_win', '_wpnonce', true, false) . '<input type="hidden" name="lfi_dossier_win_annuler" value="1"><input type="hidden" name="bataille" value="urgence"><button type="submit" class="btn-ghost" style="font-size:.78em;padding:3px 8px">↩ annuler la coupe</button></form>';
        echo '</div></details>';

        if ($ind_won) {
            echo '<div style="padding:14px;border-radius:12px;background:#e8f5ea;border:2px solid #186a3b">';
            echo '<div style="font-weight:900;color:#186a3b;font-size:1.05em">💶 Volet indemnisation — 🏆 gagné</div>';
            echo '<div style="font-size:.85em;color:#555;margin-top:3px">Préjudice réparé. <span style="color:#888">' . esc_html(wp_date('j M Y', strtotime($ind_won))) . '</span> — dossier clos.</div>';
            echo '<form method="post" style="margin-top:6px" onsubmit="return confirm(\'Annuler la coupe du volet indemnisation ?\')">' . wp_nonce_field('lfi_dossier_win', '_wpnonce', true, false) . '<input type="hidden" name="lfi_dossier_win_annuler" value="1"><input type="hidden" name="bataille" value="indemnisation"><button type="submit" class="btn-ghost" style="font-size:.78em;padding:3px 8px">↩ annuler la coupe</button></form>';
            echo '</div>';
        } else {
            echo '<div style="padding:14px;border-radius:12px;background:#fffdf5;border:2px solid #bd8600">';
            echo '<div style="font-weight:900;color:#bd8600;font-size:1.08em">💶 Volet indemnisation — déployé</div>';
            echo '<div style="font-size:.9em;color:#555;margin-top:4px">Le <strong>préjudice est déjà chiffré</strong> dans le dossier. Reste à <strong>réunir les preuves</strong>, puis à négocier l\'indemnisation à l\'amiable avec Nantes Métropole Habitat.</div>';
            lfi_nct_dossier_render_juridique_guidance($u, $row);
            $dj = function_exists('lfi_nct_dossier_find_for_tenant') ? lfi_nct_dossier_find_for_tenant($u->ID) : null;
            $dj_url = $dj
                ? lfi_nct_app_url('dossier-juridique-edit', ['id' => (int) $dj->id])
                : lfi_nct_app_url('dossier-juridique-add', ['tenant_uid' => $u->ID, 'tenant_nom' => $u->last_name ?: $u->display_name, 'tenant_prenom' => $u->first_name ?: '', 'tenant_adresse' => $row->adresse ?? '']);
            echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">';
            echo '<a class="btn-primary" style="background:#bd8600;flex:1;text-align:center;min-width:150px" href="' . esc_url($dj_url) . '">⚖️ ' . ($dj ? 'Ouvrir le volet juridique' : 'Lancer le volet juridique') . '</a>';
            if (function_exists('lfi_nct_architecte_can') && lfi_nct_architecte_can()) {
                echo '<a class="btn-ghost" style="flex:1;text-align:center;min-width:150px" href="' . esc_url(lfi_nct_app_url('architecte')) . '">🧠 Robot architecte</a>';
            }
            echo '</div>';
            echo '<form method="post" style="margin-top:8px" onsubmit="return confirm(\'Indemnisation obtenue (préjudice réparé) ? On pose la COUPE et on CLÔT le dossier. Le GA est prévenu.\')">' . wp_nonce_field('lfi_dossier_win', '_wpnonce', true, false) . '<input type="hidden" name="lfi_dossier_win" value="1"><input type="hidden" name="bataille" value="indemnisation"><button type="submit" class="btn-ghost" style="width:100%;font-size:.86em">🏆 Indemnisation obtenue — clore le dossier</button></form>';
            echo '</div>';
        }
    }

    echo '<div class="lfi-app-help" style="margin-top:8px"><small>Gagner l\'urgence ne ferme PAS le dossier : la coupe est posée, mais on continue sur l\'indemnisation. Chaque bataille = une coupe ; une famille reste comptée une seule fois.</small></div>';
    echo '</div>';
}

/** Volet juridique déployé : les pièces à réunir + la stratégie amiable à
 *  destination de NMH (sans dévoiler tout le jeu). Guide d'orientation — le
 *  chiffrage du préjudice, lui, vit dans le dossier juridique. */
function lfi_nct_dossier_render_juridique_guidance($u, $row) {
    $pieces = [
        'Photos datées / horodatées de tous les désordres (avant / après)',
        'Constats et rapports officiels (SCHS, ARS, huissier si disponible)',
        'Certificats médicaux liés au logement (asthme, allergies, stress…)',
        'Tous les courriers et emails échangés avec NMH (avec accusés)',
        'Factures et justificatifs des frais engagés à cause du désordre',
        'Attestations de proches / voisins (témoignages sur le trouble)',
        'Quittances de loyer (loyer payé plein malgré le trouble de jouissance)',
    ];
    echo '<details style="margin-top:10px;background:#fff;border-radius:10px;border:1px solid #eee;overflow:hidden">';
    echo '<summary style="cursor:pointer;padding:10px 12px;font-weight:800;color:#bd8600;list-style:none;display:flex;justify-content:space-between;align-items:center"><span>📎 Les pièces / preuves à réunir</span><span>▾</span></summary>';
    echo '<ul style="margin:4px 0 10px;padding:0 16px 0 30px;font-size:.9em;color:#333;line-height:1.6">';
    foreach ($pieces as $p) echo '<li>' . esc_html($p) . '</li>';
    echo '</ul>';
    echo '</details>';

    echo '<details style="margin-top:8px;background:#fff;border-radius:10px;border:1px solid #eee;overflow:hidden">';
    echo '<summary style="cursor:pointer;padding:10px 12px;font-weight:800;color:#0066a3;list-style:none;display:flex;justify-content:space-between;align-items:center"><span>🎯 Stratégie amiable (ne pas dévoiler tout le jeu)</span><span>▾</span></summary>';
    echo '<div style="padding:0 12px 12px;font-size:.9em;color:#333;line-height:1.55">';
    echo '<p style="margin:6px 0">On adresse à Nantes Métropole Habitat une <strong>demande d\'indemnisation amiable chiffrée</strong>, en annonçant le <strong>montant global</strong> du préjudice et sa <strong>base légale</strong> (art. 1719 et 1721 du Code civil — obligation de délivrance et de jouissance paisible ; trouble de jouissance), <em>sans</em> communiquer le détail complet du calcul ni l\'intégralité des pièces.</p>';
    echo '<p style="margin:6px 0">On garde les preuves détaillées <strong>en réserve</strong> pour l\'éventuel contentieux : on montre qu\'on est solide et prêt, mais on ne livre pas tout le dossier d\'un coup.</p>';
    echo '<p style="margin:6px 0">On fixe un <strong>délai de réponse raisonnable</strong> (≈ 30 jours) et on rappelle qu\'à défaut d\'accord, on saisit la <strong>Commission Départementale de Conciliation</strong> puis le <strong>Tribunal Judiciaire de Nantes</strong>.</p>';
    echo '<p style="margin:6px 0;color:#666"><small>Le <strong>robot architecte</strong> t\'oriente pièce par pièce et sur la formulation à envoyer.</small></p>';
    echo '</div>';
    echo '</details>';

    /* Verser les pièces à transmettre (NMH / CDC / TJ). */
    if (function_exists('lfi_nct_justice_pieces_box')) lfi_nct_justice_pieces_box($u, true);

    /* Monter le dossier Commission de conciliation (bot justice). */
    echo '<div style="margin-top:10px"><a class="btn-primary" style="background:#6a1b9a;width:100%;display:block;text-align:center;box-sizing:border-box" href="' . esc_url(lfi_nct_app_url('justice-cdc', ['uid' => $u->ID])) . '">⚖️ Monter le dossier Commission de conciliation</a></div>';

    /* Confier ce dossier à un·e avocat·e (Me Valet / Me Goache). */
    if (function_exists('lfi_nct_avocat_assign_box')) lfi_nct_avocat_assign_box($u);
}

function lfi_nct_dossier_render_parcours($u) {
    $steps = get_user_meta($u->ID, 'lfi_nct_suivi_steps', true);
    if (!is_array($steps)) $steps = [];

    /* AUTO : si aucune étape, on monte le parcours-type TOUT SEUL (plus besoin
       de cliquer « générer »). Si l'urgence est déjà gagnée, on greffe aussi le
       volet indemnisation. */
    if (empty($steps) && function_exists('lfi_nct_dossier_parcours_template')) {
        foreach (lfi_nct_dossier_parcours_template() as $tpl) {
            $steps[] = ['text' => $tpl['text'], 'who' => $tpl['who'], 'auto' => !empty($tpl['auto']), 'done' => false, 'echeance' => '', 'created' => current_time('Y-m-d')];
        }
        update_user_meta($u->ID, 'lfi_nct_suivi_steps', array_values($steps));
        if (function_exists('lfi_nct_ensure_indemnisation_steps')) lfi_nct_ensure_indemnisation_steps($u->ID);
        $steps = get_user_meta($u->ID, 'lfi_nct_suivi_steps', true);
        if (!is_array($steps)) $steps = [];
    }

    /* Suggestions rapides (dropdown) — le parcours type d'un dossier */
    $suggestions = [
        'Passer chez le locataire pour constater',
        'Faire signer l\'accord d\'accompagnement de la locataire',
        'Envoyer la mise en demeure travaux à NMH',
        'Appeler NMH (agence Goudy / M. Morineau)',
        'Attendre la réponse de M. Morineau (NMH)',
        '1re relance NMH',
        '2e relance NMH',
        'Saisir la Commission de Conciliation',
        'Saisir le SCHS (insalubrité)',
        'Saisir l\'ARS',
        'Préparer l\'assignation au Tribunal Judiciaire',
        'Envoyer le récapitulatif de facturation à NMH',
    ];

    echo '<details open style="margin:16px 0;background:#fff;border-radius:12px;border:1px solid #eee;overflow:hidden">';
    echo '<summary style="cursor:pointer;padding:14px 16px;font-weight:800;color:#c8102e;list-style:none;display:flex;justify-content:space-between;align-items:center">';
    echo '<span>🧭 Parcours de suivi';
    $todo = count(array_filter($steps, function ($s) { return empty($s['done']) && empty($s['skipped']); }));
    if ($todo) echo ' <span style="background:#c8102e;color:#fff;font-size:.7em;padding:2px 7px;border-radius:10px;vertical-align:middle">' . $todo . ' à faire</span>';
    echo '</span><span style="font-size:1.2em">▾</span>';
    echo '</summary>';
    echo '<div style="padding:0 16px 16px">';

    /* Génération automatique du parcours-type (le « quand je clique, ça se monte tout seul »). */
    echo '<form method="post" style="margin:8px 0">';
    wp_nonce_field('lfi_app_dossier_step');
    echo '<input type="hidden" name="lfi_app_dossier_step" value="1"><input type="hidden" name="step_action" value="autofill">';
    echo '<button type="submit" class="btn-primary" style="background:#186a3b;width:100%">✨ ' . (empty($steps) ? 'Générer le parcours automatique' : 'Compléter avec le parcours-type') . '</button>';
    echo '</form>';
    echo '<div class="lfi-app-help" style="margin:0 0 8px"><small>Le parcours-type démarre par « le locataire s\'empare de sa fiche » (partage de l\'espace, pièces, adhésion, objectif) puis va à l\'amiable avant le juridique. Tu peux tout modifier.</small></div>';

    /* Liste des étapes — UNE SEULE FORM : on coche PLUSIEURS cases puis on
       enregistre en une fois (plus de rechargement à chaque clic). */
    if (empty($steps)) {
        echo '<div class="lfi-app-help" style="margin:6px 0">Aucune étape pour l\'instant. Clique « ✨ Générer le parcours automatique » ci-dessus, ou ajoute une action à la main.</div>';
    } else {
        echo '<form method="post">';
        wp_nonce_field('lfi_app_dossier_step');
        echo '<input type="hidden" name="lfi_app_dossier_step" value="1"><input type="hidden" name="step_action" value="batch">';
        echo '<div class="lfi-app-help" style="margin:4px 0"><small>Coche tout ce qui est fait, <strong>puis</strong> « 💾 Enregistrer » — tu peux en cocher plusieurs d\'un coup.</small></div>';
        echo '<ol style="list-style:none;padding:0;margin:8px 0">';
        foreach ($steps as $idx => $s) {
            /* Étape rendue « inutile » (urgence gagnée avant qu'on la fasse) :
               affichée barrée, en gris, SANS case — elle ne compte plus. */
            if (!empty($s['skipped'])) {
                echo '<li style="display:flex;align-items:flex-start;gap:10px;padding:8px 10px;border-radius:8px;margin-bottom:5px;background:#f4f4f4;border-left:3px solid #bbb">';
                echo '<span style="width:22px;text-align:center;flex-shrink:0">⏭️</span>';
                echo '<div style="flex:1"><div style="text-decoration:line-through;color:#999;font-weight:500">' . esc_html($s['text'] ?? '') . '</div>';
                echo '<div style="font-size:.78em;color:#aaa;margin-top:1px">inutile — urgence gagnée avant</div></div>';
                echo '</li>';
                continue;
            }
            $done = !empty($s['done']);
            $who = $s['who'] ?? 'admin';
            $badge = $who === 'tenant' ? '<span style="background:#fff3cd;color:#8a6d1f;font-size:.68em;font-weight:700;padding:1px 6px;border-radius:8px;white-space:nowrap">🏠 locataire</span>' : '<span style="background:#e7f0fb;color:#0066a3;font-size:.68em;font-weight:700;padding:1px 6px;border-radius:8px;white-space:nowrap">👤 toi</span>';
            echo '<li style="display:flex;align-items:flex-start;gap:10px;padding:9px 10px;border-radius:8px;margin-bottom:5px;background:' . ($done ? '#e8f5ea' : '#fafafa') . ';border-left:3px solid ' . ($done ? '#186a3b' : ($who === 'tenant' ? '#d39e00' : '#c8102e')) . '">';
            echo '<input type="checkbox" name="step_done[]" value="' . $idx . '" ' . checked($done, true, false) . ' style="width:22px;height:22px;margin-top:2px;flex-shrink:0">';
            echo '<div style="flex:1"><div style="font-weight:600;' . ($done ? 'text-decoration:line-through;color:#888' : 'color:#1a1a1a') . '">' . esc_html($s['text']) . ' ' . $badge . '</div>';
            if (!empty($s['echeance'])) {
                $late = (!$done && strtotime($s['echeance']) < strtotime(current_time('Y-m-d')));
                echo '<div style="font-size:.82em;color:' . ($late ? '#c8102e' : '#888') . ';margin-top:2px">' . ($late ? '⚠ en retard — ' : '🗓 ') . 'échéance ' . esc_html(wp_date('j M Y', strtotime($s['echeance']))) . '</div>';
            }
            echo '</div></li>';
        }
        echo '</ol>';
        echo '<button type="submit" class="btn-primary" style="background:#186a3b;width:100%">💾 Enregistrer les étapes cochées</button>';
        echo '</form>';
    }

    /* Ajout d'une étape — avec dropdown de suggestions */
    echo '<form method="post" class="lfi-app-form" style="margin-top:10px;background:#f8f8f8;padding:12px;border-radius:8px">';
    wp_nonce_field('lfi_app_dossier_step');
    echo '<input type="hidden" name="lfi_app_dossier_step" value="1">';
    echo '<input type="hidden" name="step_action" value="add">';
    echo '<label style="margin:0">➕ Ajouter une étape';
    echo '<select onchange="if(this.value){document.getElementById(\'lfi-step-text\').value=this.value;}" style="margin-top:4px">';
    echo '<option value="">— Choisir dans la liste —</option>';
    foreach ($suggestions as $sg) echo '<option value="' . esc_attr($sg) . '">' . esc_html($sg) . '</option>';
    echo '</select></label>';
    echo '<input type="text" id="lfi-step-text" name="step_text" placeholder="… ou écris l\'action à mener" style="margin-top:6px">';
    echo '<div style="display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:6px;align-items:end">';
    echo '<label style="margin:0">Échéance / rappel (optionnel)<input type="date" name="step_echeance"></label>';
    echo '<button type="submit" class="btn-primary">Ajouter</button>';
    echo '</div>';
    echo '</form>';

    echo '</div>';
    echo '</details>';
}

/* ============================================================== *
 *  Suivi complet d'un locataire — agrège dossiers juridiques,      *
 *  interventions brigade et recouvrements liés à ce locataire.     *
 *  Matching : tenant_user_id OU adresse canonique OU nom.          *
 * ============================================================== */
function lfi_nct_dossier_render_suivi($u, $row) {
    global $wpdb;
    $uid  = (int) $u->ID;
    $nom  = trim($u->last_name . ' ' . $u->first_name) ?: $u->display_name;
    $adr  = $row->adresse ?? '';
    $adr_key = ($adr && function_exists('lfi_nct_address_canonical_key')) ? lfi_nct_address_canonical_key($adr) : '';

    /* Helper de matching robuste : un enregistrement concerne ce locataire si
       son tenant_user_id == uid, OU si son adresse a la même clé canonique,
       OU si le nom correspond (insensible casse/espaces). Gère les fautes de
       frappe sur la rue (Saint-Jean-de-Luz orthographié différemment). */
    $matches = function ($r_uid, $r_nom, $r_adr) use ($uid, $nom, $adr_key) {
        if ((int) $r_uid === $uid && $uid) return true;
        if ($adr_key && function_exists('lfi_nct_address_canonical_key')) {
            if (lfi_nct_address_canonical_key($r_adr) === $adr_key && $adr_key !== '') return true;
        }
        if ($nom && $r_nom) {
            $a = strtolower(trim(preg_replace('/\s+/', ' ', $nom)));
            $b = strtolower(trim(preg_replace('/\s+/', ' ', $r_nom)));
            if ($a === $b) return true;
            /* Match partiel : le nom du locataire est contenu dans l'autre */
            if (strlen($a) >= 4 && (strpos($b, $a) !== false || strpos($a, $b) !== false)) return true;
        }
        return false;
    };

    $owner = function_exists('lfi_nct_fact_owner_id') ? (int) lfi_nct_fact_owner_id() : (int) get_current_user_id();

    /* --- Dossiers juridiques (fetch large par owner, filtre PHP robuste) --- */
    $td = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $all_d = $wpdb->get_results($wpdb->prepare("SELECT * FROM $td WHERE owner_user_id = %d ORDER BY updated_at DESC LIMIT 300", $owner)) ?: [];
    $dossiers = array_values(array_filter($all_d, function ($d) use ($matches) {
        return $matches($d->tenant_user_id, $d->tenant_nom, $d->tenant_adresse);
    }));

    /* --- Interventions brigade --- */
    $ti = $wpdb->prefix . 'lfi_nct_interventions';
    $all_i = $wpdb->get_results($wpdb->prepare("SELECT * FROM $ti WHERE owner_user_id = %d ORDER BY date_intervention DESC, id DESC LIMIT 500", $owner)) ?: [];
    $interv = array_values(array_filter($all_i, function ($i) use ($matches) {
        return $matches($i->tenant_user_id, $i->tenant_nom, $i->tenant_adresse);
    }));

    /* --- Recouvrements (via les n° de facture des interventions) --- */
    $recs = [];
    $facture_nums = array_filter(array_map(function ($i) { return $i->facture_numero; }, $interv));
    if (!empty($facture_nums)) {
        $facture_nums = array_values(array_unique($facture_nums));
        $tr = $wpdb->prefix . 'lfi_nct_recouvrements';
        $place = implode(',', array_fill(0, count($facture_nums), '%s'));
        /* Borné au propriétaire (défense en profondeur, en plus du n° de facture). */
        $args = array_merge($facture_nums, [$owner]);
        $recs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tr WHERE facture_numero IN ($place) AND owner_user_id = %d ORDER BY updated_at DESC", ...$args)) ?: [];
    }

    /* --- Appels NMH liés (uid OU nom) --- */
    $appels = [];
    $ta = $wpdb->prefix . 'lfi_nct_appels_nmh';
    if ($wpdb->get_var("SHOW TABLES LIKE '$ta'") === $ta) {
        $all_a = $wpdb->get_results($wpdb->prepare("SELECT * FROM $ta WHERE owner_user_id = %d ORDER BY date_appel DESC LIMIT 200", $owner)) ?: [];
        $appels = array_values(array_filter($all_a, function ($a) use ($matches) {
            return $matches($a->tenant_user_id, $a->tenant_label, '');
        }));
    }

    /* --- Correspondance email (envoyés + reçus), loggée dans les dossiers --- */
    $emails_envoyes = [];
    foreach ($dossiers as $d) {
        $logs = json_decode($d->notes ?? '', true);
        if (!is_array($logs)) continue;
        if (!empty($logs['email_log'])) {
            foreach ($logs['email_log'] as $el) { $el['dossier_id'] = $d->id; $el['sens'] = 'envoye'; $emails_envoyes[] = $el; }
        }
        if (!empty($logs['email_recu'])) {
            foreach ($logs['email_recu'] as $el) { $el['dossier_id'] = $d->id; $el['sens'] = 'recu'; $emails_envoyes[] = $el; }
        }
    }
    usort($emails_envoyes, function ($a, $b) { return strcmp($a['date'] ?? '', $b['date'] ?? ''); });

    /* === TOTAUX FINANCIERS === */
    $total_realise = 0; $total_facture = 0; $total_paye = 0;
    foreach ($interv as $i) {
        if ($i->statut === 'realise') $total_realise += (float) $i->total_ht;
        if (in_array($i->statut, ['facture', 'paye'], true)) $total_facture += (float) $i->total_ht;
        if ($i->statut === 'paye') $total_paye += (float) $i->total_ht;
    }
    $a_recouvrer = $total_facture - $total_paye;
    $du_total = $total_realise + $total_facture; /* tout ce qui n'est pas payé reste dû */

    if (empty($dossiers) && empty($interv) && empty($recs) && empty($appels)) {
        echo '<h3 style="margin:20px 0 8px;color:#c8102e">📋 Suivi complet</h3>';
        echo '<div class="lfi-app-empty">Aucun dossier juridique, intervention ou appel pour ce locataire pour le moment. Utilise les boutons « Actions » ci-dessus pour en créer.</div>';
        return;
    }

    /* Accordéon « Suivi complet » — replié par défaut (évite les listes
       interminables ; on l'ouvre quand on veut le détail). */
    $nb_suivi = count($dossiers) + count($interv) + count($recs) + count($appels);
    echo '<details style="margin:20px 0;background:#fff;border-radius:12px;border:1px solid #eee;overflow:hidden">';
    echo '<summary style="cursor:pointer;padding:14px 16px;font-weight:800;color:#c8102e;list-style:none;display:flex;justify-content:space-between;align-items:center">';
    echo '<span>📋 Suivi complet <span style="background:#c8102e;color:#fff;font-size:.7em;padding:2px 7px;border-radius:10px;vertical-align:middle">' . (int) $nb_suivi . '</span></span><span style="font-size:1.2em">▾</span></summary>';
    echo '<div style="padding:2px 16px 16px">';

    /* Bandeau totaux — toujours affiché s'il y a des interventions */
    if (!empty($interv)) {
        echo '<div class="lfi-app-stats-grid" style="margin-bottom:10px">';
        echo '<div class="stat"><div class="ico">🔧</div><div class="n">' . count($interv) . '</div><div class="l">Interventions</div></div>';
        echo '<div class="stat"><div class="ico">💵</div><div class="n">' . number_format($total_facture, 0, ',', ' ') . ' €</div><div class="l">Facturé NMH</div></div>';
        echo '<div class="stat"><div class="ico">⏳</div><div class="n">' . number_format($a_recouvrer, 0, ',', ' ') . ' €</div><div class="l">À recouvrer</div></div>';
        echo '<div class="stat"><div class="ico">✅</div><div class="n">' . number_format($total_paye, 0, ',', ' ') . ' €</div><div class="l">Payé</div></div>';
        echo '</div>';
        echo '<div style="margin-bottom:14px"><a class="btn-primary" style="background:#0066a3" href="' . esc_url(lfi_nct_app_url('dossier-recap-nmh', ['uid' => $uid])) . '" target="_blank">🧾 Récapitulatif complet à envoyer à NMH</a></div>';
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

    /* Correspondance email (envoyés + reçus) — timeline */
    if (!empty($emails_envoyes)) {
        $email_lbls = [
            'lrar_travaux' => 'Mise en demeure travaux', 'lrar_relogement' => 'Relogement médical',
            'schs' => 'Saisine SCHS', 'ars' => 'Saisine ARS', 'reponse_nmh' => 'Réponse argumentée',
        ];
        echo '<h4 style="margin:14px 0 6px">📧 Correspondance NMH (' . count($emails_envoyes) . ')</h4>';
        echo '<ul class="lfi-app-list">';
        foreach ($emails_envoyes as $el) {
            $is_recu = (($el['sens'] ?? '') === 'recu');
            echo '<li class="lfi-app-card" style="border-left:4px solid ' . ($is_recu ? '#0066a3' : '#186a3b') . '">';
            $titre = $is_recu ? ('📥 Reçu' . (!empty($el['objet']) ? ' — ' . $el['objet'] : '')) : ('📤 ' . ($email_lbls[$el['letter'] ?? ''] ?? ($el['objet'] ?? 'Email envoyé')));
            echo '<div class="head"><div class="who">' . esc_html($titre) . '</div>';
            echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html($el['date'] ?? '') . '</div></div>';
            echo '<div class="meta">';
            if ($is_recu && !empty($el['de'])) echo '<span class="meta-chip">de ' . esc_html($el['de']) . '</span>';
            if (!$is_recu && !empty($el['to'])) echo '<span class="meta-chip">→ ' . esc_html($el['to']) . '</span>';
            if (!empty($el['dossier_id'])) echo '<a class="meta-chip" href="' . esc_url(lfi_nct_app_url('dossier-juridique-edit', ['id' => $el['dossier_id']])) . '">📁 dossier</a>';
            echo '</div>';
            if ($is_recu && !empty($el['corps'])) echo '<div class="com" style="white-space:pre-wrap">' . esc_html(mb_substr($el['corps'], 0, 300)) . (mb_strlen($el['corps']) > 300 ? '…' : '') . '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }

    echo '</div>';      /* .padding */
    echo '</details>';  /* accordéon « Suivi complet » */
}

/* ============================================================== *
 *  Récapitulatif de facturation à envoyer à NMH (imprimable)       *
 *  Toutes les interventions d'un locataire + total dû.             *
 * ============================================================== */
function lfi_nct_app_view_dossier_recap_nmh() {
    if (!lfi_nct_app_guard_brigade('🧾 Récapitulatif NMH')) return;
    global $wpdb;
    $uid = (int) ($_GET['uid'] ?? 0);
    $u = $uid ? get_userdata($uid) : null;
    /* Cloisonnement : le locataire doit appartenir au GA courant ET avoir le
       rôle locataire (sinon fuite d'identité/adresse via ?uid= d'un autre GA). */
    $in_scope = !function_exists('lfi_nct_uid_in_scope') || lfi_nct_uid_in_scope($uid);
    if (!$u || !$in_scope || !in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) {
        wp_die('Locataire introuvable dans ce groupe d\'action.');
    }

    $rid = (int) get_user_meta($uid, 'lfi_nct_response_id', true);
    $resp = $rid ? $wpdb->get_row($wpdb->prepare("SELECT adresse, etage FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid)) : null;
    $adr_key = ($resp && $resp->adresse && function_exists('lfi_nct_address_canonical_key')) ? lfi_nct_address_canonical_key($resp->adresse) : '';
    $nom = trim($u->last_name . ' ' . $u->first_name) ?: $u->display_name;
    $owner = function_exists('lfi_nct_fact_owner_id') ? (int) lfi_nct_fact_owner_id() : (int) get_current_user_id();

    $ti = $wpdb->prefix . 'lfi_nct_interventions';
    $all = $wpdb->get_results($wpdb->prepare("SELECT * FROM $ti WHERE owner_user_id = %d AND statut != 'annule' ORDER BY date_intervention ASC", $owner)) ?: [];
    $interv = array_values(array_filter($all, function ($i) use ($uid, $nom, $adr_key) {
        if ((int) $i->tenant_user_id === $uid && $uid) return true;
        if ($adr_key && function_exists('lfi_nct_address_canonical_key') && lfi_nct_address_canonical_key($i->tenant_adresse) === $adr_key) return true;
        if ($nom && $i->tenant_nom && strtolower(trim($i->tenant_nom)) === strtolower(trim($nom))) return true;
        return false;
    }));

    $presta = function_exists('lfi_nct_fact_prestataire') ? lfi_nct_fact_prestataire() : [];
    $bailleur = function_exists('lfi_nct_fact_bailleur') ? lfi_nct_fact_bailleur() : ['nom' => 'Nantes Métropole Habitat'];

    lfi_nct_app_screen_open('🧾 Récapitulatif NMH', $u->display_name);
    if (function_exists('lfi_nct_rec_doc_styles')) lfi_nct_rec_doc_styles();

    echo '<div class="lfi-rec-doc">';
    echo '<h1>Récapitulatif des interventions<br><small>à la charge de ' . esc_html($bailleur['nom'] ?? 'Nantes Métropole Habitat') . '</small></h1>';

    echo '<div class="expediteur">';
    if (!empty($presta['nom'])) echo '<strong>' . esc_html($presta['nom']) . '</strong><br>';
    if (!empty($presta['adresse'])) echo esc_html($presta['adresse']) . '<br>';
    if (!empty($presta['cp_ville'])) echo esc_html($presta['cp_ville']);
    echo '</div>';

    echo '<div class="destinataire">';
    echo '<strong>' . esc_html($bailleur['nom'] ?? 'Nantes Métropole Habitat') . '</strong><br>';
    if (!empty($bailleur['agence_contact'])) echo esc_html($bailleur['agence_contact']) . '<br>';
    if (!empty($bailleur['agence_email'])) echo esc_html($bailleur['agence_email']);
    echo '</div>';

    echo '<div class="lieu-date">À Nantes, le ' . esc_html(wp_date('j F Y')) . '</div>';

    $logement = $resp && $resp->adresse ? $resp->adresse . ($resp->etage ? ', étage ' . $resp->etage : '') : '';
    echo '<p class="objet">Objet : Récapitulatif des interventions conservatoires réalisées au logement de ' . esc_html($u->display_name) . ($logement ? ' — ' . esc_html($logement) : '') . '</p>';

    if (empty($interv)) {
        echo '<p>Aucune intervention enregistrée pour ce locataire.</p>';
        echo '</div>';
        lfi_nct_app_screen_close(false);
        return;
    }

    echo '<table class="detail">';
    echo '<tr><td><strong>Date</strong></td><td><strong>Prestation</strong></td><td class="num"><strong>Montant HT</strong></td><td><strong>Statut</strong></td></tr>';
    $total = 0; $total_du = 0;
    foreach ($interv as $i) {
        $total += (float) $i->total_ht;
        if ($i->statut !== 'paye') $total_du += (float) $i->total_ht;
        echo '<tr>';
        echo '<td>' . esc_html($i->date_intervention ? wp_date('d/m/Y', strtotime($i->date_intervention)) : '—') . '</td>';
        echo '<td>' . esc_html($i->type_travaux);
        if ($i->description) echo '<br><small style="color:#666">' . esc_html(mb_substr($i->description, 0, 120)) . (mb_strlen($i->description) > 120 ? '…' : '') . '</small>';
        echo '</td>';
        echo '<td class="num">' . number_format($i->total_ht, 2, ',', ' ') . ' €</td>';
        echo '<td>' . esc_html($i->statut === 'paye' ? '✓ payé' : ($i->facture_numero ? 'facturé ' . $i->facture_numero : 'à facturer')) . '</td>';
        echo '</tr>';
    }
    echo '<tr class="total"><td colspan="2">TOTAL des interventions</td><td class="num">' . number_format($total, 2, ',', ' ') . ' €</td><td></td></tr>';
    echo '<tr class="total" style="background:#fff3f5"><td colspan="2"><strong>RESTE DÛ PAR NMH</strong></td><td class="num"><strong>' . number_format($total_du, 2, ',', ' ') . ' €</strong></td><td></td></tr>';
    echo '</table>';

    echo lfi_nct_legal_fondement_block($bailleur['nom'] ?? 'Nantes Métropole Habitat');
    echo '<p><strong>TVA non applicable, art. 293 B du CGI.</strong></p>';

    echo '<div class="signature">' . esc_html($presta['nom'] ?? '') . '</div>';
    echo '</div>';

    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  Bloc "Fondement juridique + conséquences" — imprimé sur les     *
 *  récaps et factures envoyées à NMH.                              *
 * ============================================================== */
function lfi_nct_legal_fondement_block($bailleur_nom) {
    $h  = '<h2>Fondement juridique de la créance</h2>';
    $h .= '<p>Les travaux conservatoires figurant ci-dessus ont été réalisés <strong>en remplacement du bailleur</strong>, défaillant à son obligation légale de délivrer et d\'entretenir un logement décent (<strong>articles 1719 et 1724 du Code civil</strong> ; <strong>article 6 de la loi n° 89-462</strong> ; <strong>décret n° 2002-120</strong>), après <strong>mise en demeure restée infructueuse</strong>, en application de l\'<strong>article 1222 du Code civil</strong> (exécution de l\'obligation par un tiers aux frais du débiteur).</p>';
    $h .= '<p>Le locataire concerné a expressément <strong>mandaté l\'intervenant</strong> et l\'a <strong>subrogé dans ses droits</strong> à l\'encontre du bailleur (<strong>article 1346 du Code civil</strong>). La présente créance est donc réclamée à ' . esc_html($bailleur_nom) . ' au nom et pour le compte du locataire subrogeant.</p>';
    $h .= '<h2>Conséquences à défaut de paiement</h2>';
    $h .= '<p>À défaut de règlement dans le délai imparti, la créance sera recouvrée par voie judiciaire : tentative de conciliation préalable devant la <strong>Commission Départementale de Conciliation</strong> (art. 20 loi n° 89-462), puis saisine du <strong>Tribunal Judiciaire de Nantes</strong>, avec demande de condamnation au principal, aux <strong>pénalités de retard</strong> (art. L.441-10 C. com.), à l\'<strong>indemnité forfaitaire de recouvrement de 40 €</strong> (décret 2012-1115), aux <strong>dommages-intérêts pour trouble de jouissance</strong> du locataire (art. 1719 CC) et aux <strong>frais irrépétibles</strong> (art. 700 CPC). Un signalement au <strong>SCHS</strong> et à l\'<strong>ARS</strong> pourra en outre être diligenté.</p>';
    return $h;
}

/* ============================================================== *
 *  ADMIN : Signatures email — gestion                              *
 * ============================================================== */

function lfi_nct_app_view_signatures() {
    if (!(function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'))) return;

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

function lfi_nct_app_view_carte($force_all = false) {
    /* Carte cumulée réseau ($force_all) = super-admin ; carte d'un GA = admin du GA. */
    $ok = $force_all ? current_user_can('manage_options')
                     : (function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'));
    if (!$ok) return;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';

    /* Cloisonnement : sur l'espace d'un GA, on ne montre que ses signalements.
       En mode « réseau » ($force_all), on montre TOUT (carte cumulée). */
    $scope = (!$force_all && function_exists('lfi_nct_responses_scope_clause'))
        ? lfi_nct_responses_scope_clause('militant_user_id') : '';

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
        "SELECT id, adresse, etage, data, lat, lng, submitted_at, ga
         FROM $table
         WHERE deleted_at IS NULL AND lat IS NOT NULL AND lng IS NOT NULL" . $scope . "
         ORDER BY submitted_at DESC LIMIT 500"
    ) ?: [];
    $pending = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $table
         WHERE lat IS NULL AND adresse IS NOT NULL AND adresse != ''
               AND deleted_at IS NULL" . $scope
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
        $ref = function_exists('lfi_nct_response_ref')
            ? lfi_nct_response_ref($r->id, function_exists('lfi_nct_response_ga_of') ? lfi_nct_response_ga_of($r) : '')
            : '';
        $markers[] = [
            'id'        => (int) $r->id,
            'ref'       => $ref,
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

    /* Centre la carte sur la zone du GA affiché (Vallet, Dervallières,
       Port-Boyer, Rezé…) en vue aérienne, sauf en mode réseau cumulé où l'on
       garde Clos Toreau comme point de départ. */
    if (!$force_all && function_exists('lfi_nct_ga_geo') && function_exists('lfi_nct_scope_ga_slug')) {
        $geo = lfi_nct_ga_geo(lfi_nct_scope_ga_slug());
        if (!empty($geo['centre'])) {
            $center_lat = (float) $geo['centre'][0];
            $center_lng = (float) $geo['centre'][1];
            $center_zoom = 15; // vue aérienne sur la ville/quartier du GA
        }
    }

    lfi_nct_app_screen_open($force_all ? '🌐 Carte cumulée du réseau' : '🗺 Carte 3D des signalements', count($rows) . ' enquête(s) géolocalisée(s)' . ($pending ? ' · ' . $pending . ' à géocoder' : ''));

    if ($force_all) {
        echo '<div class="lfi-app-help" style="background:#eef4ff;border-left:4px solid #0066a3"><strong>Carte cumulée de tout le réseau</strong> : tous les signalements de tous les groupes d\'action, sur une seule carte 3D. Vue réservée au super-admin.</div>';
    }

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

    /* Liste déroulante : aller directement à un signalement (vole vers le point). */
    if (!empty($markers)) {
        echo '<label style="display:block;margin:0 0 10px;font-size:.9em;color:#555">📍 Aller à un signalement';
        echo '<select id="lfi-map-goto" style="width:100%;padding:11px 12px;border:1.5px solid #ddd;border-radius:10px;margin-top:4px;font-size:1em;background:#fafafa">';
        echo '<option value="">— choisir une adresse (' . count($markers) . ') —</option>';
        foreach ($markers as $i => $mk) {
            $lbl = ($mk['ref'] !== '' ? $mk['ref'] . ' · ' : '')
                . $mk['adresse']
                . ($mk['etage'] !== '' ? ' · ét. ' . $mk['etage'] : '')
                . ' — ' . $mk['glabel'];
            echo '<option value="' . (int) $i . '">' . esc_html($lbl) . '</option>';
        }
        echo '</select></label>';
    }

    echo '<div id="lfi-map" style="width:100%;height:65vh;min-height:420px;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.1);background:#f5f5f5"></div>';
    echo '<div class="lfi-app-help" style="margin-top:8px"><small>📱 Bouge à 2 doigts pour incliner la vue 3D. Pince pour zoomer. Touche une balise colorée (ou choisis une adresse ci-dessus) pour voir le détail.</small></div>';

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
        /* Même sans aucune enquête géocodée, on affiche quand même la carte 3D
           du quartier (rues + immeubles) centrée sur le GA, avec un petit avis. */
        var center = [<?php echo (float) $center_lng; ?>, <?php echo (float) $center_lat; ?>];
        function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
        function parseFloor(s){ if(!s) return 0; var m=String(s).match(/(\d+)/); return m?parseInt(m[1],10):0; }
        function popupHtml(m){
            var unite = 'Étage ' + esc(m.etage) + (m.appt ? ' · Appt ' + esc(m.appt) : '');
            var reftag = m.ref ? '<span style="display:inline-block;background:#c8102e;color:#fff;font-weight:700;font-size:.75em;padding:2px 7px;border-radius:6px;margin-bottom:4px;letter-spacing:.5px">' + esc(m.ref) + '</span><br>' : '';
            var html = reftag + '<h3>' + esc(m.adresse) + '</h3>'
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
                glyphs: 'https://fonts.openmaptiles.org/{fontstack}/{range}.pbf',
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

        /* Avis discret quand il n'y a encore aucune enquête géolocalisée. */
        if (!markers.length) {
            el.style.position = 'relative';
            var note = document.createElement('div');
            note.style.cssText = 'position:absolute;z-index:5;left:8px;right:8px;top:8px;background:rgba(255,255,255,.93);border:1px solid #d8d8d8;border-radius:8px;padding:8px 10px;font-size:.82em;color:#555;text-align:center;pointer-events:none';
            note.innerHTML = 'Carte du quartier affichée. Aucune enquête géolocalisée pour l\'instant — clique sur « 🌍 Géocoder » au-dessus pour y placer tes enquêtes.';
            el.appendChild(note);
        }

        var bounds = new maplibregl.LngLatBounds();
        var FLOOR_M = 3, CUBE_M = 5;
        var stackCounts = {};
        var feats = [];   // cubes 3D au niveau de l'étage (détail en vue rapprochée)
        var pts = [];     // points (curseurs adaptatifs + regroupement par zone)
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
            /* Cube au niveau réel de l'étage : détail visible en vue rapprochée. */
            var base = floor * FLOOR_M + 0.4;
            var top  = base + 2.6;
            var pop  = popupHtml(m);
            feats.push({
                type: 'Feature', id: idx,
                geometry: { type: 'Polygon', coordinates: squareAround(m.lng + dLng, m.lat, CUBE_M) },
                properties: { fid: idx, base: base, height: top, color: m.gcolor, popup: pop }
            });
            pts.push({
                type: 'Feature', id: idx,
                geometry: { type: 'Point', coordinates: [m.lng, m.lat] },
                properties: { fid: idx, color: m.gcolor, popup: pop }
            });
            bounds.extend([m.lng, m.lat]);
        });
        var surveysGJ = { type: 'FeatureCollection', features: feats };
        var pointsGJ  = { type: 'FeatureCollection', features: pts };
        /* Dès qu'il y a au moins une enquête, on cadre dessus en vue AÉRIENNE
           (maxZoom 16) : on voit le point/la pastille sans être collé au sol. */
        if (!bounds.isEmpty()) map.fitBounds(bounds, { padding: 80, maxZoom: 16, pitch: 55, bearing: -15 });

        function openPopup(e) {
            if (!e.features || !e.features.length) return;
            var g = e.features[0].geometry;
            var c = (g && g.type === 'Point') ? g.coordinates.slice() : e.lngLat;
            new maplibregl.Popup({ closeButton: true, offset: 10 })
                .setLngLat(c).setHTML(e.features[0].properties.popup).addTo(map);
        }

        function addSurveyLayer() {
            if (map.getSource('surveys')) return;

            /* Cubes 3D au niveau de l'étage : n'apparaissent qu'en vue rapprochée
               (zoom ≥ 16), pour le détail « au ras du sol ». */
            map.addSource('surveys', { type: 'geojson', data: surveysGJ });
            map.addLayer({
                id: 'surveys-3d', type: 'fill-extrusion', source: 'surveys', minzoom: 16,
                paint: {
                    'fill-extrusion-color': ['get', 'color'],
                    'fill-extrusion-base': ['get', 'base'],
                    'fill-extrusion-height': ['get', 'height'],
                    'fill-extrusion-opacity': 0.95
                }
            });

            /* Curseurs adaptatifs + REGROUPEMENT par zone :
               - vue haute (dézoomée) : un gros curseur bien visible portant le
                 NOMBRE d'enquêtes, là où il y en a une ou plusieurs ;
               - vue basse (rapprochée) : les curseurs se séparent, petits,
                 colorés par gravité — tout le détail au clic. */
            map.addSource('survey-pts', {
                type: 'geojson', data: pointsGJ,
                cluster: true, clusterMaxZoom: 16, clusterRadius: 50
            });
            map.addLayer({
                id: 'clusters', type: 'circle', source: 'survey-pts',
                filter: ['has', 'point_count'],
                paint: {
                    'circle-color': '#c8102e',
                    'circle-opacity': 0.95,
                    /* Rayon en PIXELS écran (pitch-scale viewport) → toujours
                       visible, même en vue aérienne très inclinée et éloignée. */
                    'circle-radius': ['step', ['get', 'point_count'], 18, 5, 23, 15, 28, 40, 34],
                    'circle-stroke-width': 3,
                    'circle-stroke-color': '#fff',
                    'circle-pitch-scale': 'viewport',
                    'circle-pitch-alignment': 'viewport'
                }
            });
            map.addLayer({
                id: 'cluster-count', type: 'symbol', source: 'survey-pts',
                filter: ['has', 'point_count'],
                layout: {
                    'text-field': ['get', 'point_count_abbreviated'],
                    'text-font': ['Noto Sans Regular'],
                    'text-size': 15,
                    'text-allow-overlap': true,
                    'text-ignore-placement': true,
                    'text-pitch-alignment': 'viewport'
                },
                paint: { 'text-color': '#fff' }
            });
            map.addLayer({
                id: 'survey-pt', type: 'circle', source: 'survey-pts',
                filter: ['!', ['has', 'point_count']],
                paint: {
                    'circle-color': ['get', 'color'],
                    /* Curseur d'une enquête isolée : rayon écran constant, bien
                       visible de loin, plus petit une fois zoomé au sol. */
                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 10, 11, 14, 9, 17, 7, 20, 5],
                    'circle-stroke-width': 2,
                    'circle-stroke-color': '#fff',
                    'circle-pitch-scale': 'viewport',
                    'circle-pitch-alignment': 'viewport'
                }
            });

            /* Clic sur un groupe : on zoome pour l'éclater. */
            map.on('click', 'clusters', function (e) {
                var f = map.queryRenderedFeatures(e.point, { layers: ['clusters'] });
                if (!f.length) return;
                var cid = f[0].properties.cluster_id;
                var src = map.getSource('survey-pts');
                if (!src || !src.getClusterExpansionZoom) return;
                src.getClusterExpansionZoom(cid).then(function (z) {
                    map.easeTo({ center: f[0].geometry.coordinates, zoom: (z || 17) + 0.4 });
                }).catch(function () {
                    map.easeTo({ center: f[0].geometry.coordinates, zoom: 17.5 });
                });
            });
            /* Clic sur un curseur individuel (point ou cube) : fiche détaillée. */
            map.on('click', 'survey-pt', openPopup);
            map.on('click', 'surveys-3d', openPopup);
            ['clusters', 'survey-pt', 'surveys-3d'].forEach(function (id) {
                map.on('mouseenter', id, function () { map.getCanvas().style.cursor = 'pointer'; });
                map.on('mouseleave', id, function () { map.getCanvas().style.cursor = ''; });
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

        /* Liste déroulante « Aller à un signalement » → vole vers le point + popup. */
        var goto = document.getElementById('lfi-map-goto');
        if (goto) {
            goto.addEventListener('change', function () {
                var i = parseInt(this.value, 10);
                if (isNaN(i) || !markers[i]) return;
                var m = markers[i];
                map.flyTo({ center: [m.lng, m.lat], zoom: 18.5, pitch: 55, bearing: -15, essential: true });
                new maplibregl.Popup({ closeButton: true, offset: 12 })
                    .setLngLat([m.lng, m.lat])
                    .setHTML(popupHtml(m))
                    .addTo(map);
            });
        }
    })();
    </script>
    <?php

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  ADMIN : Stats de l'enquête (problèmes, adresses, gravité)       *
 * ============================================================== */

function lfi_nct_app_view_stats_enquete_helper_stub() {} /* no-op marker, kept for compat */

function lfi_nct_app_view_stats_enquete($force_all = false) {
    /* Admin du GA — agrégats sur données RGPD, cloisonnés par GA.
       En mode « réseau » ($force_all), agrégats de TOUS les GA (super-admin). */
    if ($force_all) {
        if (!current_user_can('manage_options')) return;
    } elseif (!(function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'))) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';

    $stat_scope = (!$force_all && function_exists('lfi_nct_responses_scope_clause'))
        ? lfi_nct_responses_scope_clause() : '';
    $rows = $wpdb->get_results(
        "SELECT adresse, data FROM $table WHERE deleted_at IS NULL" . $stat_scope
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

    lfi_nct_app_screen_open($force_all ? '🌐 Stats enquête — réseau' : '📊 Stats enquête', $total . ' réponse(s) au total' . ($force_all ? ' · tous les GA' : ''));
    if ($force_all) {
        echo '<div class="lfi-app-help" style="background:#eef4ff;border-left:4px solid #0066a3"><strong>Statistiques cumulées de tout le réseau</strong> : toutes les enquêtes de tous les groupes d\'action additionnées. Vue réservée au super-admin.</div>';
    }

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
    if (!(function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'))) return;
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

    /* Liste des locataires avec un tel — cloisonnée par GA. */
    $tenant_args = [
        'role'    => LFI_NCT_ROLE_TENANT,
        'fields'  => ['ID', 'user_login', 'display_name'],
        'number'  => 500,
        'orderby' => 'display_name', 'order' => 'ASC',
    ];
    if (function_exists('lfi_nct_users_ga_query')) $tenant_args = lfi_nct_users_ga_query($tenant_args);
    $tenants = get_users($tenant_args);
    $tenants_with_tel = [];
    foreach ($tenants as $u) {
        $tel = (string) get_user_meta($u->ID, 'lfi_nct_tel', true);
        /* Liste noire SMS : on n'affiche pas les locataires qui refusent les SMS. */
        if ($tel && function_exists('lfi_nct_sms_is_blocked') && lfi_nct_sms_is_blocked($tel)) continue;
        if ($tel) $tenants_with_tel[] = ['uid' => $u->ID, 'name' => $u->display_name, 'tel' => $tel];
    }

    $uid  = isset($_GET['uid'])  ? (int) $_GET['uid']  : 0;
    $mode = isset($_GET['mode']) ? sanitize_key($_GET['mode']) : 'libre';

    $user      = $uid ? get_userdata($uid) : null;
    /* Cloisonnement : locataire du GA courant uniquement (sinon fuite du
       nom/tél via ?uid= d'un locataire d'un autre GA). */
    $in_scope  = !function_exists('lfi_nct_uid_in_scope') || lfi_nct_uid_in_scope($uid);
    $is_tenant = $user && $in_scope && in_array(LFI_NCT_ROLE_TENANT, (array) $user->roles, true);
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

    /* Form changer email — pré-rempli avec l'adresse actuelle (pas besoin de
       tout retaper, on modifie juste). */
    echo '<details class="lfi-app-collapse" style="margin-top:14px" open><summary>✉️ Changer mon adresse email</summary>';
    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_change_email');
    echo '<input type="hidden" name="lfi_app_change_email" value="1">';
    echo '<label>Mon adresse email<input type="email" name="new_email" value="' . esc_attr($user->user_email) . '" autocomplete="email" required></label>';
    echo '<button type="submit" class="btn-primary">✓ Enregistrer</button>';
    echo '</form>';
    echo '<div class="lfi-app-help"><small>Votre adresse est enregistrée dans l\'app : vous n\'aurez plus à la retaper pour vous connecter.</small></div>';
    echo '</details>';

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

    /* Garde l'identifiant de connexion mémorisé synchro avec l'email : si la
       personne se connecte avec son email et le change ici, l'app retiendra le
       nouveau pour le pré-remplir au prochain accès (rien à retaper). */
    $cur_email = (string) $user->user_email;
    if ($cur_email !== '') {
        echo '<script>(function(){try{var cur=' . wp_json_encode($cur_email) . ';var s=localStorage.getItem("lfi_login_id");if(s&&s.indexOf("@")>-1&&cur)localStorage.setItem("lfi_login_id",cur);}catch(e){}})();</script>';
    }

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
