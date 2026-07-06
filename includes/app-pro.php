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

/** Détermine et mémorise la DATE DE PRISE DE VUE d'une photo (EXIF), pour le
 *  classement chronologique. Ordre de fiabilité : EXIF « created_timestamp » →
 *  date de fichier → date d'upload. Renvoie le timestamp unix. */
function lfi_nct_store_capture_ts($att_id, $file = '') {
    $att_id = (int) $att_id; $ts = 0;
    $meta = wp_get_attachment_metadata($att_id);
    if (is_array($meta) && !empty($meta['image_meta']['created_timestamp'])) {
        $ts = (int) $meta['image_meta']['created_timestamp'];
    }
    if (!$ts && $file && @file_exists($file)) $ts = (int) @filemtime($file);
    if (!$ts) { $p = get_post($att_id); if ($p) $ts = (int) get_post_time('U', true, $p); }
    if (!$ts) $ts = (int) current_time('timestamp');
    update_post_meta($att_id, '_lfi_capture_ts', $ts);
    return $ts;
}

/* HEAL (une fois) : renseigne _lfi_capture_ts (date de prise de vue) sur toutes
   les photos de locataires déjà envoyées, pour le tri chronologique. */
add_action('init', 'lfi_nct_heal_capture_ts', 19);
function lfi_nct_heal_capture_ts() {
    if (get_option('lfi_nct_heal_capture_ts_v1')) return;
    $ids = get_posts([
        'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => 3000, 'fields' => 'ids',
        'meta_query' => [
            ['key' => '_lfi_tenant_user_id', 'compare' => 'EXISTS'],
            ['key' => '_lfi_capture_ts', 'compare' => 'NOT EXISTS'],
        ],
    ]);
    foreach ((array) $ids as $aid) lfi_nct_store_capture_ts((int) $aid, get_attached_file((int) $aid));
    update_option('lfi_nct_heal_capture_ts_v1', 1, false);
}

/** Récupère les photos d'un locataire, CLASSÉES par date de prise de vue (chrono). */
function lfi_nct_tenant_photos_chrono($uid, $limit = 200) {
    return get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'any',
        'posts_per_page' => (int) $limit,
        'meta_key'       => '_lfi_capture_ts',
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC',
        'meta_query'     => [['key' => '_lfi_tenant_user_id', 'value' => (int) $uid]],
    ]);
}

/** Date de prise de vue lisible d'une photo (ou date d'upload en repli). */
function lfi_nct_photo_capture_label($att_id) {
    $ts = (int) get_post_meta($att_id, '_lfi_capture_ts', true);
    if (!$ts) $ts = (int) get_post_time('U', true, get_post($att_id));
    return $ts ? wp_date('j M Y · H:i', $ts) : '';
}

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

        /* PLUSIEURS photos d'un coup (name="photo[]"). On accepte aussi l'ancien
           format à une seule photo par sécurité. */
        $names = $_FILES['photo']['name'] ?? null;
        if (empty($names) || (is_array($names) && !array_filter($names))) {
            $err = 'Choisis au moins une photo à envoyer.';
        } else {
            $list = is_array($names) ? array_keys($names) : [0];
            $done = 0; $skipped = 0;
            foreach ($list as $i) {
                $file = is_array($names)
                    ? ['name' => $_FILES['photo']['name'][$i], 'type' => $_FILES['photo']['type'][$i], 'tmp_name' => $_FILES['photo']['tmp_name'][$i], 'error' => $_FILES['photo']['error'][$i], 'size' => $_FILES['photo']['size'][$i]]
                    : $_FILES['photo'];
                if (empty($file['tmp_name']) || (int) $file['error'] !== 0) { continue; }
                if ($file['size'] > 15 * 1024 * 1024) { $skipped++; continue; }
                $mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : $file['type'];
                if (strpos((string) $mime, 'image/') !== 0) { $skipped++; continue; }
                $upload = wp_handle_upload($file, ['test_form' => false]);
                if (!empty($upload['error'])) { $skipped++; continue; }
                $att_id = wp_insert_attachment([
                    'post_mime_type' => $upload['type'],
                    'post_title'     => sprintf('Photo %s — %s', $piece ?: 'logement', $user->display_name),
                    'post_content'   => $note,
                    'post_status'    => 'private',
                    'post_author'    => $user->ID,
                ], $upload['file']);
                if (is_wp_error($att_id) || !$att_id) { $skipped++; continue; }
                update_post_meta($att_id, '_lfi_tenant_user_id', $user->ID);
                update_post_meta($att_id, '_lfi_tenant_piece', $piece);
                update_post_meta($att_id, '_lfi_tenant_note', $note);
                wp_update_attachment_metadata($att_id, wp_generate_attachment_metadata($att_id, $upload['file']));
                lfi_nct_store_capture_ts($att_id, $upload['file']); /* date de prise de vue (EXIF) */
                $done++;
            }
            if ($done > 0) { wp_safe_redirect(lfi_nct_app_url('envoyer-photo', ['uploaded' => $done] + ($skipped ? ['skip' => $skipped] : []))); exit; }
            $err = 'Aucune photo envoyée (' . $skipped . ' ignorée·s : trop lourdes ou pas des images).';
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

    $photos = function_exists('lfi_nct_tenant_photos_chrono')
        ? lfi_nct_tenant_photos_chrono($user->ID, 100)
        : get_posts(['post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => 50, 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => [['key' => '_lfi_tenant_user_id', 'value' => $user->ID]]]);

    lfi_nct_app_screen_open('📷 Envoyer une photo', 'Documenter votre logement en images');

    if (!empty($_GET['uploaded'])) lfi_nct_app_flash('✅ ' . (int) $_GET['uploaded'] . ' photo(s) enregistrée(s). Le GA y a accès dans votre dossier.' . (!empty($_GET['skip']) ? ' (' . (int) $_GET['skip'] . ' ignorée·s.)' : ''));
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

    echo '<label>📂 Les photos (tu peux en choisir <strong>plusieurs à la fois</strong>)<input type="file" name="photo[]" accept="image/*" multiple required></label>';

    echo '<button type="submit" class="btn-primary big">📤 Envoyer les photos</button>';
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
            echo '<div class="when">📅 ' . esc_html(lfi_nct_photo_capture_label($p->ID)) . '</div>';
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

    /* + TOUTE personne ayant un DOSSIER dans le périmètre, même si son rôle
       principal n'est pas « locataire » (multi-casquette : un membre du GA qui
       est AUSSI locataire, comme Fabrice Doucet, doit apparaître ici). */
    $by_id = [];
    foreach ($tenants as $u) $by_id[(int) $u->ID] = $u;
    $td = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $drows = $wpdb->get_results("SELECT DISTINCT tenant_user_id FROM $td WHERE tenant_user_id > 0") ?: [];
    foreach ($drows as $r) {
        $duid = (int) $r->tenant_user_id;
        if (!$duid || isset($by_id[$duid])) continue;
        if (function_exists('lfi_nct_uid_in_scope') && !lfi_nct_uid_in_scope($duid)) continue; /* cloisonnement */
        $uu = get_userdata($duid);
        if ($uu) $by_id[$duid] = $uu;
    }
    $tenants = array_values($by_id);
    usort($tenants, function ($a, $b) { return strcasecmp((string) $a->display_name, (string) $b->display_name); });

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

/* ============================================================== *
 *  CHRONOLOGIE STRUCTURÉE du dossier (par locataire) — une vraie   *
 *  timeline, PAS des notes. S'auto-alimente quand le dossier évolue *
 *  (emails reçus/envoyés) et reste éditable/triable à la main.      *
 * ============================================================== */
function lfi_nct_chrono_get($uid) {
    $c = get_user_meta((int) $uid, 'lfi_nct_chrono', true);
    return is_array($c) ? $c : [];
}
function lfi_nct_chrono_norm_date($s) {
    $s = trim((string) $s);
    if (preg_match('#(\d{1,2})/(\d{1,2})/(\d{4})#', $s, $m)) return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    if (preg_match('#(\d{4})-(\d{2})-(\d{2})#', $s, $m)) return $m[1] . '-' . $m[2] . '-' . $m[3];
    if (preg_match('#\b(20\d{2})\b#', $s, $m)) return $m[1] . '-00-00';
    return '';
}
function lfi_nct_chrono_save($uid, $list) {
    usort($list, function ($a, $b) {
        $c = strcmp((string) ($a['d'] ?? ''), (string) ($b['d'] ?? ''));
        return $c !== 0 ? $c : strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
    });
    update_user_meta((int) $uid, 'lfi_nct_chrono', array_values($list));
}
/** Ajoute une entrée APRÈS vérification (rationnel + pas de doublon). */
function lfi_nct_chrono_add($uid, $label, $txt, $auto = false) {
    $txt = trim((string) $txt); if ($txt === '') return false;
    /* 1) RATIONNEL : au moins 5 caractères ET une lettre (on refuse « ). », un
       simple numéro « 003859 », les fragments « Erreur d'extraction »…). */
    if (mb_strlen($txt) < 5 || !preg_match('/\p{L}/u', $txt)) return false;
    if (preg_match('/erreur d\'?extraction|non retenu/iu', $txt)) return false;
    $label = trim((string) $label);
    $nd  = lfi_nct_chrono_norm_date($label);
    $pfx = mb_strtolower(mb_substr(trim(preg_replace('/\s+/u', ' ', $txt)), 0, 45));
    $list = lfi_nct_chrono_get($uid);
    /* 2) PAS DE DOUBLON : texte identique, OU même jour + même début de phrase. */
    foreach ($list as $e) {
        if (mb_strtolower(trim((string) ($e['txt'] ?? ''))) === mb_strtolower($txt)) return false;
        if ($nd !== '' && (string) ($e['d'] ?? '') === $nd
            && mb_strtolower(mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($e['txt'] ?? ''))), 0, 45)) === $pfx) return false;
    }
    $list[] = ['id' => (int) (round(microtime(true) * 1000) % 1000000000), 'd' => $nd, 'label' => $label, 'txt' => $txt, 'auto' => $auto ? 1 : 0];
    lfi_nct_chrono_save($uid, $list);
    return true;
}
/** Entrée auto « email » dans la chronologie — ACTIVÉ par défaut : c'est le bon
 *  système pour les locataires (les emails arrivés dans la boîte se classent tout
 *  seuls dans la timeline du bon dossier). Les doublons sont évités en amont
 *  (dédup + vérification dans lfi_nct_chrono_add, import .md qui remplace).
 *  Débrayable si besoin via l'option lfi_nct_chrono_from_email = '0'. */
function lfi_nct_chrono_add_email($uid, $sens, $qui, $objet, $date = '') {
    if (!$uid) return;
    if (get_option('lfi_nct_chrono_from_email', '1') !== '1') return;
    $who = function_exists('lfi_nct_interlocuteur') ? lfi_nct_interlocuteur($qui) : ['ico' => '✉️', 'label' => ''];
    $lab = $date ?: wp_date('Y-m-d');
    $ico = ($sens === 'recu') ? '📥 Email reçu de' : '📤 Email envoyé à';
    $txt = $ico . ' ' . trim($who['ico'] . ' ' . ($who['label'] ?: 'interlocuteur')) . ($objet !== '' ? ' — « ' . mb_substr((string) $objet, 0, 70) . ' »' : '');
    lfi_nct_chrono_add($uid, $lab, $txt, true);
}
/** Options « rattacher à un événement » (id => libellé) pour un locataire. */
function lfi_nct_chrono_link_options($uid) {
    $opts = [];
    foreach (lfi_nct_chrono_get($uid) as $e) {
        $id = (int) ($e['id'] ?? 0); if (!$id) continue;
        $lbl = trim((string) ($e['label'] ?? '')) . ' — ' . mb_substr((string) ($e['txt'] ?? ''), 0, 45);
        $opts[$id] = trim($lbl, ' —');
    }
    return $opts;
}

/** Pièces rattachées à un événement chrono donné. */
function lfi_nct_chrono_event_pieces($uid, $chrono_id) {
    return get_posts([
        'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => 50, 'orderby' => 'date', 'order' => 'ASC',
        'meta_query' => [
            ['key' => '_lfi_tenant_user_id', 'value' => (int) $uid],
            ['key' => '_lfi_chrono_id', 'value' => (int) $chrono_id],
        ],
    ]);
}

/** Auto-rattache les pièces à l'événement dont la DATE = leur date de prise de
 *  vue (jour exact). Renvoie le nombre rattaché. */
function lfi_nct_pieces_autolink_by_date($uid) {
    $uid = (int) $uid; if (!$uid) return 0;
    $events = [];
    foreach (lfi_nct_chrono_get($uid) as $e) {
        $d = (string) ($e['d'] ?? ''); /* AAAA-MM-JJ (ou AAAA-00-00) */
        if ($d !== '' && substr($d, 5) !== '00-00') $events[substr($d, 0, 10)] = (int) ($e['id'] ?? 0);
    }
    if (!$events) return 0;
    $pieces = get_posts(['post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => 300, 'fields' => 'ids',
        'meta_query' => [['key' => '_lfi_tenant_user_id', 'value' => $uid]]]);
    $n = 0;
    foreach ((array) $pieces as $aid) {
        if (get_post_meta($aid, '_lfi_chrono_id', true)) continue; /* déjà rattachée */
        $ts = (int) get_post_meta($aid, '_lfi_capture_ts', true); if (!$ts) $ts = (int) get_post_time('U', true, $aid);
        $day = $ts ? wp_date('Y-m-d', $ts) : '';
        if ($day !== '' && isset($events[$day])) { update_post_meta($aid, '_lfi_chrono_id', $events[$day]); $n++; }
    }
    return $n;
}

/** Rend la section « 📅 Chronologie » dans le dossier (triée, éditable). */
function lfi_nct_dossier_render_chrono($u) {
    $list = lfi_nct_chrono_get($u->ID);
    echo '<div class="lfi-app-card" style="border-left:4px solid #0b3d91;margin-bottom:12px" id="dossier-chrono">';
    echo '<div class="head"><div class="who">📅 Chronologie du dossier</div><div class="badge" style="background:#0b3d91;color:#fff">' . count($list) . '</div></div>';
    if (empty($list)) {
        echo '<div class="com" style="font-size:.9em;color:#777">Aucune entrée. Ajoute les dates clés ci-dessous — et chaque email classé viendra s\'ajouter tout seul.</div>';
    } else {
        echo '<div style="display:flex;flex-direction:column;gap:6px;margin-top:6px">';
        foreach ($list as $e) {
            $lab = (string) ($e['label'] ?? '');
            $eid = (int) ($e['id'] ?? 0);
            $etxt = (string) ($e['txt'] ?? '');
            echo '<div style="border-left:3px solid ' . (!empty($e['auto']) ? '#0066a3' : '#0b3d91') . ';background:#f6f8fc;border-radius:6px;padding:6px 9px">';
            echo '<div style="display:flex;gap:8px;align-items:flex-start">';
            echo '<input type="checkbox" form="lfi-chrono-bulk" name="chrono_sel[]" value="' . $eid . '" title="Sélectionner" style="margin-top:2px;width:16px;height:16px;flex:0 0 auto">';
            echo '<div style="font-weight:800;color:#0b3d91;font-size:.82em;white-space:nowrap;min-width:78px">' . esc_html($lab ?: '—') . '</div>';
            echo '<div style="flex:1;font-size:.88em;color:#333">' . esc_html($etxt) . (!empty($e['auto']) ? ' <span style="color:#0066a3;font-size:.85em">· auto</span>' : '') . '</div>';
            echo '<form method="post" onsubmit="return confirm(\'Retirer cette ligne ?\')" style="margin:0">' . wp_nonce_field('lfi_chrono', '_wpnonce', true, false) . '<input type="hidden" name="lfi_chrono_del" value="' . $eid . '"><button type="submit" class="btn-ghost" style="font-size:.72em;padding:2px 6px">🗑</button></form>';
            echo '</div>';
            /* 📎 Pièces rattachées à CET événement (clic → agrandir ; ✕ pour détacher). */
            $ep = $eid ? lfi_nct_chrono_event_pieces($u->ID, $eid) : [];
            if ($ep) {
                echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;padding-left:26px">';
                foreach ($ep as $pp) {
                    $mime = (string) get_post_mime_type($pp->ID);
                    $purl = wp_get_attachment_url($pp->ID);
                    $th = (strpos($mime, 'image/') === 0)
                        ? wp_get_attachment_image($pp->ID, [64, 64], true, ['style' => 'width:52px;height:52px;object-fit:cover;border-radius:6px;display:block'])
                        : '<div style="width:52px;height:52px;border-radius:6px;background:#eef;display:flex;align-items:center;justify-content:center">' . (strpos($mime, 'pdf') !== false ? '📄' : '📎') . '</div>';
                    echo '<div style="position:relative"><a href="' . esc_url($purl) . '" target="_blank" rel="noopener" style="display:block">' . $th . '</a>';
                    echo '<form method="post" style="position:absolute;top:-6px;right:-6px;margin:0">' . wp_nonce_field('lfi_app_piece_link', '_wpnonce', true, false)
                       . '<input type="hidden" name="lfi_app_piece_link" value="1"><input type="hidden" name="att_id" value="' . (int) $pp->ID . '"><input type="hidden" name="chrono_id" value="0">'
                       . '<button type="submit" title="Détacher de l\'événement" style="width:18px;height:18px;border-radius:50%;border:none;background:#c8102e;color:#fff;font-size:.7em;line-height:1;cursor:pointer">✕</button></form>';
                    echo '</div>';
                }
                echo '</div>';
            }
            /* ✏️ Modifier (date + texte), y compris pour une ligne importée. */
            echo '<details style="margin-top:4px"><summary style="cursor:pointer;color:#0b3d91;font-size:.78em;font-weight:600">✏️ Modifier</summary>';
            echo '<form method="post" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">' . wp_nonce_field('lfi_chrono', '_wpnonce', true, false);
            echo '<input type="hidden" name="lfi_chrono_edit" value="' . $eid . '">';
            echo '<input type="text" name="chrono_date" value="' . esc_attr($lab) . '" placeholder="date" style="width:130px;padding:6px;border:1px solid #ccc;border-radius:6px;font-size:.85em">';
            echo '<input type="text" name="chrono_txt" value="' . esc_attr($etxt) . '" style="flex:1;min-width:160px;padding:6px;border:1px solid #ccc;border-radius:6px;font-size:.85em">';
            echo '<button type="submit" class="btn-primary" style="background:#0b3d91;font-size:.82em">💾 Enregistrer</button>';
            echo '</form></details>';
            echo '</div>';
        }
        echo '</div>';
    }
    /* 🧹 Tri/suppression multiple (cases reliées par form=) + tout effacer. */
    if (!empty($list)) {
        echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">';
        echo '<form id="lfi-chrono-bulk" method="post" onsubmit="return confirm(\'Supprimer les lignes cochées ?\')" style="margin:0">' . wp_nonce_field('lfi_chrono', '_wpnonce', true, false) . '<input type="hidden" name="lfi_chrono_bulk_del" value="1"><button type="submit" class="btn-ghost" style="font-size:.8em;border-color:#c8102e;color:#c8102e">🗑 Supprimer la sélection</button></form>';
        echo '<form method="post" onsubmit="return confirm(\'Tout effacer la chronologie ?\')" style="margin:0">' . wp_nonce_field('lfi_chrono', '_wpnonce', true, false) . '<input type="hidden" name="lfi_chrono_reset" value="1"><button type="submit" class="btn-ghost" style="font-size:.8em;color:#c8102e">🧹 Tout effacer</button></form>';
        echo '<form method="post" title="Rattache chaque pièce à l\'événement de même date" style="margin:0">' . wp_nonce_field('lfi_app_pieces_autolink', '_wpnonce', true, false) . '<input type="hidden" name="lfi_app_pieces_autolink" value="1"><button type="submit" class="btn-ghost" style="font-size:.8em;color:#186a3b;border-color:#a9d5b6">🔗 Rattacher les pièces par date</button></form>';
        echo '<form method="post" title="Retire les doublons (chronologie + pièces)" style="margin:0">' . wp_nonce_field('lfi_app_dossier_dedupe', '_wpnonce', true, false) . '<input type="hidden" name="lfi_app_dossier_dedupe" value="1"><button type="submit" class="btn-ghost" style="font-size:.8em;color:#8a6d1f;border-color:#e6d29a">🧹 Nettoyer les doublons</button></form>';
        echo '</div>';
        if (isset($_GET['autolinked'])) echo '<div style="font-size:.82em;color:#186a3b;margin-top:4px">🔗 ' . (int) $_GET['autolinked'] . ' pièce(s) rattachée(s) à leur événement.</div>';
        if (isset($_GET['deduped_c']) || isset($_GET['deduped_p'])) echo '<div style="font-size:.82em;color:#8a6d1f;margin-top:4px">🧹 ' . (int) ($_GET['deduped_c'] ?? 0) . ' doublon(s) de chronologie et ' . (int) ($_GET['deduped_p'] ?? 0) . ' pièce(s) en double retiré(s).</div>';
    }
    echo '<details style="margin-top:8px"><summary style="cursor:pointer;color:#0b3d91;font-weight:700;font-size:.9em">➕ Ajouter une date</summary>';
    echo '<form method="post" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">' . wp_nonce_field('lfi_chrono', '_wpnonce', true, false);
    echo '<input type="text" name="chrono_date" placeholder="ex. 20/08/2025 ou 2020" style="width:150px;padding:7px;border:1px solid #ccc;border-radius:6px">';
    echo '<input type="text" name="chrono_txt" placeholder="Événement…" style="flex:1;min-width:160px;padding:7px;border:1px solid #ccc;border-radius:6px">';
    echo '<button type="submit" name="lfi_chrono_add" value="1" class="btn-primary" style="background:#0b3d91">Ajouter</button>';
    echo '</form></details>';
    echo '</div>';
}

/**
 * Reconstruction COMPLÈTE du dossier de Fabrice (idempotent) : rôle locataire,
 * enquête #6 (restaurée si en corbeille / recréée si disparue), dossier
 * juridique garanti, mandat coché (président), chronologie punaises 2020→2026
 * injectée. Appelée par le bouton ET par l'auto-déploiement (aucun clic requis).
 */
function lfi_nct_fabrice_reconstruct($u) {
    global $wpdb;
    $uid = (int) $u->ID;
    if (defined('LFI_NCT_ROLE_TENANT') && !in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) $u->add_role(LFI_NCT_ROLE_TENANT);
    /* Rattacher au GA Clos Toreau : sinon la recherche/liste cloisonnée par GA
       l'exclut → « dossier locataire Doucet » ne le trouve pas. */
    if ((string) get_user_meta($uid, 'lfi_nct_ga', true) === '' && function_exists('lfi_nct_creation_ga')) {
        $cga = lfi_nct_creation_ga(); if ($cga) update_user_meta($uid, 'lfi_nct_ga', $cga);
    }

    /* Enquête #6 : restaurer (corbeille) ou recréer (disparue), reliée à Fabrice. */
    $t_resp = $wpdb->prefix . 'lfi_nct_responses';
    $rid = (int) get_user_meta($uid, 'lfi_nct_response_id', true);
    $r = $rid ? $wpdb->get_row($wpdb->prepare("SELECT id, deleted_at FROM $t_resp WHERE id = %d", $rid)) : null;
    if ($rid && $r && !empty($r->deleted_at)) {
        $wpdb->query($wpdb->prepare("UPDATE $t_resp SET deleted_at = NULL WHERE id = %d", $rid));
    } elseif ($rid && !$r) {
        $data = wp_json_encode(['problemes_types' => ['insectes'], 'problemes_types_autre' => 'cafards, punaises de lit', 'problemes_gravite' => 8, 'problemes_recurrent' => 'permanent'], JSON_UNESCAPED_UNICODE);
        $wpdb->insert($t_resp, [
            'id' => $rid, 'militant_user_id' => $uid, 'militant_login' => (string) $u->user_login,
            'submitted_at' => current_time('mysql'), 'adresse' => '14 rue de Saint-Jean-de-Luz', 'etage' => 'Apt 88',
            'contact_prenom' => $u->first_name ?: 'Fabrice', 'contact_nom' => $u->last_name ?: 'Doucet',
            'contact_tel' => (string) get_user_meta($uid, 'lfi_nct_tel', true), 'contact_email' => (string) $u->user_email,
            'contact_recontact' => 1, 'data' => $data, 'ga' => (string) get_user_meta($uid, 'lfi_nct_ga', true),
        ]);
    }

    /* Dossier juridique garanti + mandat (président). */
    $dj = function_exists('lfi_nct_dossier_ensure_for_tenant') ? lfi_nct_dossier_ensure_for_tenant($uid) : null;
    if ($dj && function_exists('lfi_nct_dossier_mandat_set')) lfi_nct_dossier_mandat_set((int) $dj->id, 1);

    /* Chronologie 2020→2026 (dédupliquée à l'ajout). */
    $chrono = [
        ['2020', "Première infestation de l'étage ; traitement partiel (interventions 03 et 17/09/2020) ; participation de 80 € imposée au locataire."],
        ['17/07/2025', "Expertise entomologique de M. François Meurgey (Muséum de Nantes) : confirmation punaises de lit."],
        ['31/07/2025', "Constat SCHS (base du PV)."],
        ['20/08/2025', "PV SCHS (réf. JL.FM.20082025) : présence actuelle de punaises de lit, immeuble 14 rue Saint-Jean-de-Luz."],
        ['21/08/2025', "PV du voisin M. Kaba (apt 87) : infestation mitoyenne."],
        ['12/06/2025 et 08/08/2025', "2 mises en demeure de la Mairie à NMH, restées non exécutées."],
        ['17/09/2025', "Ordonnance de référés (demande rejetée) ; orientation Conseil d'État."],
        ['14/12/2025', "Indemnité « réparations locatives »."],
        ['23/03/2026', "Traitement préventif de contrôle : constat de blattes (garantie 6 mois) — victoire partielle."],
        ['10/05/2026', "Piqûre de Souleyman (fils) ; troubles du sommeil persistants."],
        ['11/05/2026', "Appel NMH (dossier 2026-33305) ; bon de commande évoqué pour l'apt 87 ; intervention partielle."],
        ['19/05/2026', "Aide juridictionnelle totale (BAJ TJ Nantes, N-44109-2026-003859) ; avocate désignée Me Julie Supiot (Barreau de Nantes, 06 67 93 26 18)."],
        ['26/05/2026', "Piqûres (Fabrice + fils Souleyman)."],
        ['27/05 et 03/06/2026', "Interventions/visites « SIHS punaises »."],
        ['06/06/2026', "Email à NMH ; NMH relance le prestataire (Sapiens) pour un nouveau RDV."],
        ['08/06/2026', "Départ du logement (inhabitable)."],
        ['09/06/2026', "Réponse de NMH reçue."],
        ['11/06/2026', "Punaise vivante photographiée (IMG_1258) ; PJ de mise en demeure ; demande n°3."],
        ['12/06/2026', "Réponse NMH (« nous prenons note… néanmoins concernant les interventions de désinsectisation… »)."],
        ['16/06/2026', "Huissier : « désignés pour la délivrance d'actes, pas pour la réalisation d'un constat »."],
        ['01/07/2026', "Point avec Maître Julie Supiot (avocate désignée, aide juridictionnelle) ; dépôt de la modification des statuts de l'association."],
    ];
    if (function_exists('lfi_nct_chrono_add')) foreach ($chrono as $c) lfi_nct_chrono_add($uid, $c[0], $c[1], false);
}

/**
 * VIDER TOUT le dossier d'un locataire : pièces (photos/PDF/documents),
 * chronologie, historique des emails (reçus/envoyés/brouillons) et notes du GA.
 * Garde le compte + l'enquête liée (données de terrain) — on vide le DOSSIER.
 * @return array Compteurs.
 */
function lfi_nct_dossier_wipe_all($uid) {
    global $wpdb;
    $uid = (int) $uid; if (!$uid) return ['pieces' => 0, 'chrono' => 0, 'dossiers' => 0];
    $rep = ['pieces' => lfi_nct_dossier_purge_pieces($uid), 'chrono' => 0, 'dossiers' => 0];
    if (function_exists('lfi_nct_chrono_get')) { $rep['chrono'] = count(lfi_nct_chrono_get($uid)); lfi_nct_chrono_save($uid, []); }
    delete_user_meta($uid, 'lfi_nct_admin_notes');
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id, notes FROM $t WHERE tenant_user_id = %d", $uid)) ?: [];
    foreach ($rows as $r) {
        $n = json_decode((string) $r->notes, true); if (!is_array($n)) $n = [];
        unset($n['email_recu'], $n['email_log'], $n['replies'], $n['inbox_seen']);
        $wpdb->update($t, ['notes' => wp_json_encode($n), 'updated_at' => current_time('mysql')], ['id' => (int) $r->id]);
        $rep['dossiers']++;
    }
    return $rep;
}

/**
 * Extrait une ARCHIVE ZIP dans le dossier d'un locataire : chaque image / PDF de
 * l'archive devient une pièce (rangée, catégorisée, étape auto). Renvoie le
 * nombre de pièces créées. Gère les zips avec sous-dossiers.
 */
function lfi_nct_dossier_import_zip($zip_path, $uid) {
    $uid = (int) $uid; if (!$uid || !$zip_path || !file_exists($zip_path)) return 0;
    if (!class_exists('ZipArchive')) return 0; /* extension zip absente */
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $za = new ZipArchive();
    if ($za->open($zip_path) !== true) return 0;
    $up = wp_upload_dir(); if (!empty($up['error'])) { $za->close(); return 0; }
    $ok_ext = ['jpg', 'jpeg', 'png', 'heic', 'heif', 'webp', 'gif', 'pdf'];
    $n = 0;
    for ($i = 0; $i < $za->numFiles; $i++) {
        $name = (string) $za->getNameIndex($i);
        if ($name === '' || substr($name, -1) === '/') continue;         /* dossier */
        $base = basename($name);
        if ($base === '' || $base[0] === '.' || strpos($name, '__MACOSX') !== false) continue; /* fichiers cachés / macOS */
        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        if (!in_array($ext, $ok_ext, true)) continue;
        $data = $za->getFromIndex($i);
        if ($data === false || $data === '' || strlen($data) > 20 * 1024 * 1024) continue;
        $safe = wp_unique_filename($up['path'], sanitize_file_name($base) ?: ('piece.' . $ext));
        $path = trailingslashit($up['path']) . $safe;
        if (@file_put_contents($path, $data) === false) continue;
        $ft  = wp_check_filetype($safe);
        $att = wp_insert_attachment(['post_mime_type' => $ft['type'] ?: 'application/octet-stream', 'post_title' => 'Pièce (zip) — ' . $base, 'post_status' => 'private', 'post_author' => (int) get_current_user_id()], $path);
        if (is_wp_error($att) || !$att) { @unlink($path); continue; }
        wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $path));
        update_post_meta($att, '_lfi_tenant_user_id', $uid);
        update_post_meta($att, '_lfi_tenant_piece', 'Pièce importée (zip)');
        if (function_exists('lfi_nct_piece_categorize')) {
            $cat = lfi_nct_piece_categorize($base, (string) ($ft['type'] ?? ''));
            update_post_meta($att, '_lfi_piece_cat', $cat['cat']);
            if (function_exists('lfi_nct_piece_autostep')) {
                $sk = lfi_nct_piece_autostep($uid, $cat['cat']);
                if ($sk !== '') update_post_meta($att, '_lfi_step', $sk);
            }
        }
        if (function_exists('lfi_nct_store_capture_ts')) lfi_nct_store_capture_ts($att, $path);
        $n++;
    }
    $za->close();
    return $n;
}

/** Normalise un texte de chronologie (minuscules, sans accents/ponctuation). */
function lfi_nct_chrono_norm_txt($t) {
    $t = mb_strtolower(trim((string) $t));
    if (function_exists('remove_accents')) $t = remove_accents($t);
    return preg_replace('/[^a-z0-9]/', '', $t);
}

/**
 * Retire les DOUBLONS de chronologie — y compris le MÊME événement écrit
 * différemment (ex. « Expertise M » tronqué vs « Expertise entomologique de
 * M. Meurgey »). On garde la version la PLUS COMPLÈTE.
 * Règle : même jour ET (texte identique OU l'un contenu dans l'autre OU même
 * début sur 12 caractères). Sans date, on dédUplique le texte exact seulement.
 */
function lfi_nct_chrono_dedupe($uid) {
    $list = lfi_nct_chrono_get($uid);
    /* On traite d'abord les entrées les plus LONGUES (pour garder la complète). */
    usort($list, function ($a, $b) {
        $c = strcmp((string) ($a['d'] ?? ''), (string) ($b['d'] ?? ''));
        return $c !== 0 ? $c : (mb_strlen((string) ($b['txt'] ?? '')) - mb_strlen((string) ($a['txt'] ?? '')));
    });
    $kept = []; $removed = 0;
    foreach ($list as $e) {
        $d = (string) ($e['d'] ?? '');
        $t = lfi_nct_chrono_norm_txt($e['txt'] ?? '');
        if ($t === '') { $kept[] = $e; continue; }
        $dup = false;
        foreach ($kept as $k) {
            $kt = lfi_nct_chrono_norm_txt($k['txt'] ?? '');
            $kd = (string) ($k['d'] ?? '');
            if ($kt === $t) { $dup = true; break; } /* texte identique (toute date) */
            /* Même jour connu → variantes du même événement. */
            if ($d !== '' && $d === $kd && substr($d, 5) !== '00-00') {
                if (strpos($kt, $t) !== false || strpos($t, $kt) !== false || mb_substr($kt, 0, 12) === mb_substr($t, 0, 12)) { $dup = true; break; }
            }
        }
        if ($dup) { $removed++; continue; }
        $kept[] = $e;
    }
    if ($removed) lfi_nct_chrono_save($uid, $kept);
    return $removed;
}

/** Retire les PIÈCES en double (même nom de fichier + même taille). */
function lfi_nct_pieces_dedupe($uid) {
    $atts = get_posts(['post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => 400, 'orderby' => 'date', 'order' => 'ASC', 'fields' => 'ids',
        'meta_query' => [['key' => '_lfi_tenant_user_id', 'value' => (int) $uid]]]);
    $seen = []; $removed = 0;
    foreach ((array) $atts as $aid) {
        $f = get_attached_file($aid); $sz = ($f && file_exists($f)) ? (int) filesize($f) : 0;
        $key = strtolower(basename((string) $f)) . '|' . $sz;
        if (isset($seen[$key])) { if (wp_delete_attachment((int) $aid, true)) $removed++; continue; }
        $seen[$key] = 1;
    }
    return $removed;
}

/** Supprime TOUTES les pièces (attachments) d'un locataire. Renvoie le nombre. */
function lfi_nct_dossier_purge_pieces($uid) {
    $uid = (int) $uid; if (!$uid) return 0;
    $atts = get_posts([
        'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids',
        'meta_query' => [['key' => '_lfi_tenant_user_id', 'value' => $uid]],
    ]);
    $n = 0;
    foreach ((array) $atts as $aid) { if (wp_delete_attachment((int) $aid, true)) $n++; }
    return $n;
}

/** Section « 📊 Synthèse & chiffrage du préjudice » (importée du .md, éditable). */
function lfi_nct_dossier_render_synthese($u) {
    $syn = (string) get_user_meta($u->ID, 'lfi_nct_dossier_synthese', true);
    $open = ($syn !== '') ? ' open' : '';
    echo '<details class="lfi-app-card" style="border:2px solid #0b3d91;background:#f4f8ff;margin-bottom:12px" id="synthese"' . $open . '>';
    echo '<summary style="cursor:pointer;font-weight:800;color:#0b3d91">📊 Synthèse & chiffrage du préjudice</summary>';
    if (!empty($_GET['syn_saved'])) echo '<div style="background:#eef7ee;border-left:4px solid #186a3b;border-radius:8px;padding:8px 10px;margin:6px 0;color:#186a3b;font-weight:700">✅ Synthèse enregistrée.</div>';
    /* 📇 Interlocuteurs & références (bailleur, hygiène, aide jurid., avocate). */
    $inter = (string) get_user_meta($u->ID, 'lfi_nct_dossier_interlocuteurs', true);
    if ($inter !== '') {
        echo '<div style="background:#fff;border:1px solid #d6e2f0;border-radius:8px;padding:9px 11px;margin:6px 0"><div style="font-weight:700;color:#0b3d91;font-size:.9em;margin-bottom:3px">📇 Interlocuteurs & références</div><div style="white-space:pre-wrap;font-size:.88em;color:#333">' . esc_html($inter) . '</div></div>';
    }
    echo '<div class="com" style="font-size:.86em;color:#555">Le robot en sort le <strong>chiffrage du préjudice</strong> (postes, montants, justification) et le contexte à partir du <code>.md</code>. Tu peux corriger ici.</div>';
    if ($syn !== '') {
        echo '<div style="background:#fff;border:1px solid #d6e2f0;border-radius:8px;padding:10px;margin:8px 0;white-space:pre-wrap;font-size:.9em">' . esc_html($syn) . '</div>';
    } else {
        echo '<div class="lfi-app-empty" style="font-size:.9em;margin:8px 0">Rien pour l\'instant — importe un <code>.md</code> qui contient le calcul du préjudice, ou saisis-le ci-dessous.</div>';
    }
    echo '<form method="post">' . wp_nonce_field('lfi_app_synthese', '_wpnonce', true, false);
    echo '<textarea name="synthese" rows="8" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px;font-size:.9em">' . esc_textarea($syn) . '</textarea>';
    echo '<button type="submit" name="lfi_app_synthese_save" value="1" class="btn-primary" style="background:#0b3d91;margin-top:6px">💾 Enregistrer la synthèse</button></form>';
    echo '</details>';
}

/** Section « 📄 Importer un dossier (.md) » + « 🗑 Vider les pièces ». */
function lfi_nct_dossier_render_import_md($u) {
    $ai = function_exists('lfi_nct_ai_enabled') && lfi_nct_ai_enabled();
    $md_open = (isset($_GET['md_chrono']) || isset($_GET['md_pieces']) || isset($_GET['pieces_purged'])) ? ' open' : '';
    echo '<details class="lfi-app-card" style="border:2px solid #4b2e83;background:#faf8ff;margin-bottom:12px" id="import-md"' . $md_open . '>';
    echo '<summary style="cursor:pointer;font-weight:800;color:#4b2e83">📄 Importer un dossier (.md) — le robot classe tout</summary>';

    if (isset($_GET['md_chrono']) || isset($_GET['md_pieces'])) {
        $av_msg = !empty($_GET['md_avocat']) ? ' · ⚖️ avocat·e « ' . esc_html(rawurldecode((string) $_GET['md_avocat'])) . ' » créé·e et rattaché·e' : '';
        echo '<div style="background:#eef7ee;border-left:4px solid #186a3b;border-radius:8px;padding:9px 11px;margin:6px 0"><strong style="color:#186a3b">✅ Import terminé</strong> — ' . (int) ($_GET['md_chrono'] ?? 0) . ' événement(s) ajouté(s) à la chronologie · ' . (int) ($_GET['md_pieces'] ?? 0) . ' pièce(s) rangée(s)' . $av_msg . '.</div>';
    }
    if (isset($_GET['pieces_purged'])) {
        echo '<div style="background:#fdeef0;border-left:4px solid #c8102e;border-radius:8px;padding:9px 11px;margin:6px 0"><strong style="color:#c8102e">🗑 ' . (int) $_GET['pieces_purged'] . ' pièce(s) supprimée(s).</strong></div>';
    }

    echo '<div class="com" style="font-size:.9em;color:#555">Tu rédiges le dossier (ici dans Claude) en <strong>Markdown</strong> avec les dates, puis tu déposes le fichier <code>.md</code> : le robot le <strong>décortique date par date</strong> et remplit la <strong>chronologie</strong>. ' . ($ai ? 'Analyse par <strong>IA Claude</strong>.' : '⚠️ Sans clé Claude : lecture basique des lignes datées.') . ' Tu peux joindre en même temps les <strong>photos / PDF</strong> → rangés comme pièces.</div>';

    echo '<form method="post" enctype="multipart/form-data" class="lfi-app-form" style="margin-top:8px">' . wp_nonce_field('lfi_app_md_import', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_app_md_import" value="1">';
    echo '<label>📄 Fichier du dossier (.md ou .txt)<input type="file" name="mdfile" accept=".md,.markdown,.txt,text/markdown,text/plain"></label>';
    echo '<label>… ou colle le texte du dossier ici<textarea name="md_paste" rows="5" placeholder="# Dossier…\n17/07/2025 : …\n20/08/2025 : …"></textarea></label>';
    echo '<label>📎 Photos / PDF à joindre (plusieurs possibles)<input type="file" name="pieces[]" accept="image/*,application/pdf" multiple></label>';
    echo '<label>🗜️ …ou une archive ZIP (photos + PDF en vrac) — le robot en sort tout et range<input type="file" name="zipfile" accept=".zip,application/zip,application/x-zip-compressed"></label>';
    echo '<label style="display:flex;gap:8px;align-items:center;margin-top:4px"><input type="checkbox" name="md_append" value="1"> <span>Ajouter à la chronologie existante (par défaut : <strong>on remplace</strong> — évite les doublons)</span></label>';
    echo '<button type="submit" class="btn-primary" style="background:#4b2e83">🤖 Importer et classer</button></form>';

    echo '<form method="post" onsubmit="return confirm(\'Supprimer TOUTES les pièces de ce dossier ? (photos, PDF, documents importés)\');" style="margin-top:8px">' . wp_nonce_field('lfi_app_pieces_purge', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_app_pieces_purge" value="1">';
    echo '<button type="submit" class="btn-ghost" style="font-size:.82em;color:#c8102e;border-color:#f0b6c1">🗑 Supprimer toutes les pièces de ce dossier</button></form>';

    /* 🗑 Vider TOUT le dossier (pièces + chronologie + emails + notes). */
    echo '<form method="post" onsubmit="return confirm(\'⚠️ VIDER TOUT le dossier ? Cela supprime les pièces/photos, la chronologie, tout l\\\'historique des emails et les notes. (Le compte et l\\\'enquête de terrain restent.)\');" style="margin-top:6px">' . wp_nonce_field('lfi_app_dossier_wipe', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_app_dossier_wipe" value="1">';
    echo '<button type="submit" class="btn-primary" style="background:#c8102e;font-size:.85em">🗑 Vider TOUT le dossier (photos + emails + chronologie)</button></form>';

    if (isset($_GET['wiped'])) echo '<div style="background:#fdeef0;border-left:4px solid #c8102e;border-radius:8px;padding:9px 11px;margin-top:8px"><strong style="color:#c8102e">🗑 Dossier vidé.</strong></div>';

    /* 📎 TOUTES les pièces du dossier, chacune SUPPRIMABLE (règle : tout ce qui
       entre doit pouvoir être supprimé depuis l'app — même sans étape). */
    $all_pieces = get_posts([
        'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => 200,
        'orderby' => 'date', 'order' => 'DESC',
        'meta_query' => [['key' => '_lfi_tenant_user_id', 'value' => (int) $u->ID]],
    ]);
    $chrono_opts = function_exists('lfi_nct_chrono_link_options') ? lfi_nct_chrono_link_options((int) $u->ID) : [];
    echo '<div style="margin-top:12px;border-top:1px solid #e2d7f5;padding-top:8px">';
    echo '<div style="font-weight:800;color:#4b2e83;margin-bottom:6px">📎 Toutes les pièces (' . count($all_pieces) . ') — triées par compartiment, chacune supprimable</div>';
    if (empty($all_pieces)) {
        echo '<div class="lfi-app-empty" style="font-size:.9em">Aucune pièce dans ce dossier.</div>';
    } else {
        /* COMPARTIMENTS : on ne mélange rien. Chaque pièce va dans SA catégorie. */
        $compart = [
            'photo'     => ['📷 Photos de preuve', '#186a3b'],
            'pv'        => ['📋 PV / constats (Hygiène)', '#0066a3'],
            'expertise' => ['🔬 Expertises', '#6a1b9a'],
            'courrier'  => ['✉️ Courriers', '#8a6d1f'],
            'medical'   => ['🩺 Certificats médicaux', '#c8102e'],
            'facture'   => ['💶 Factures / devis', '#0b3d91'],
            'doc'       => ['📄 Documents', '#555'],
            'document'  => ['📄 Documents', '#555'],
            'autre'     => ['📎 Autres pièces', '#777'],
        ];
        $buckets = [];
        foreach ($all_pieces as $p) {
            $cat = (string) get_post_meta($p->ID, '_lfi_piece_cat', true);
            if (!isset($compart[$cat])) $cat = 'autre';
            $buckets[$cat][] = $p;
        }
        /* Ordre d'affichage = ordre de $compart ; « document » fusionné dans « doc ». */
        $order = ['photo', 'pv', 'expertise', 'courrier', 'medical', 'facture', 'doc', 'autre'];
        foreach ($order as $cat) {
            $items = $buckets[$cat] ?? [];
            if ($cat === 'doc' && !empty($buckets['document'])) $items = array_merge($items, $buckets['document']);
            if (empty($items)) continue;
            list($lbl, $col) = $compart[$cat];
            echo '<div style="margin-top:10px"><div style="font-weight:700;color:' . $col . ';font-size:.9em;margin-bottom:4px;border-left:3px solid ' . $col . ';padding-left:6px">' . esc_html($lbl) . ' (' . count($items) . ')</div>';
            echo '<div style="display:flex;flex-wrap:wrap;gap:10px">';
            foreach ($items as $p) {
                $mime = (string) get_post_mime_type($p->ID);
                $url  = wp_get_attachment_url($p->ID);
                $fname = basename((string) get_attached_file($p->ID)) ?: get_the_title($p->ID);
                $is_pdf = (strpos($mime, 'pdf') !== false);
                $thumb = (strpos($mime, 'image/') === 0)
                    ? wp_get_attachment_image($p->ID, [96, 96], true, ['style' => 'width:80px;height:80px;object-fit:cover;border-radius:8px;display:block'])
                    : '<div style="width:80px;height:80px;border-radius:8px;background:' . ($is_pdf ? '#fde8e8' : '#efeaf7') . ';display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:1.5em">' . ($is_pdf ? '📄' : '📎') . '<span style="font-size:.42em;color:#a33;font-weight:700;margin-top:2px">OUVRIR</span></div>';
                $cur_link = (int) get_post_meta($p->ID, '_lfi_chrono_id', true);
                echo '<div style="width:96px;text-align:center">';
                /* Clic → ouvre l'ORIGINAL (photo en grand / PDF dans l'onglet). */
                echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" style="text-decoration:none;display:block">' . $thumb . '</a>';
                echo '<div style="font-size:.58em;color:#666;margin:1px 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' . esc_attr($fname) . '">' . esc_html($fname) . '</div>';
                if ($chrono_opts) {
                    echo '<form method="post" style="margin:2px 0 0">' . wp_nonce_field('lfi_app_piece_link', '_wpnonce', true, false)
                       . '<input type="hidden" name="lfi_app_piece_link" value="1"><input type="hidden" name="att_id" value="' . (int) $p->ID . '">'
                       . '<select name="chrono_id" onchange="this.form.submit()" style="width:100%;font-size:.62em;padding:2px;border:1px solid ' . ($cur_link ? '#186a3b' : '#ccc') . ';border-radius:5px">';
                    echo '<option value="0">' . ($cur_link ? '🔗 rattachée' : '— événement —') . '</option>';
                    foreach ($chrono_opts as $oid => $olbl) echo '<option value="' . (int) $oid . '" ' . selected($cur_link, $oid, false) . '>' . esc_html(mb_substr($olbl, 0, 32)) . '</option>';
                    echo '</select></form>';
                }
                echo '<form method="post" onsubmit="return confirm(\'Supprimer cette pièce ?\');" style="margin:2px 0 0">' . wp_nonce_field('lfi_app_step_piece', '_wpnonce', true, false)
                   . '<input type="hidden" name="lfi_app_step_piece_del" value="1"><input type="hidden" name="att_id" value="' . (int) $p->ID . '">'
                   . '<button type="submit" class="btn-ghost" style="font-size:.72em;padding:2px 8px;color:#c8102e;border-color:#f0b6c1">🗑 Suppr.</button></form>';
                echo '</div>';
            }
            echo '</div></div>';
        }
    }
    echo '</div>';
    echo '</details>';
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
    /* On ouvre si rôle locataire OU s'il a un DOSSIER (multi-casquette : Fabrice
       = membre ET locataire ne doit pas être bloqué ici). */
    $has_dossier = false;
    if ($uid) {
        $has_dossier = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}lfi_nct_dossiers_locataires WHERE tenant_user_id = %d", $uid)) > 0;
    }
    if (!$u || !$in_scope || (!in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true) && !$has_dossier)) {
        lfi_nct_app_screen_open('📂 Dossier locataire');
        echo '<div class="lfi-app-empty">Locataire introuvable. <a href="' . esc_url(lfi_nct_app_url('dossiers')) . '">← Retour à la liste</a></div>';
        lfi_nct_app_screen_close(false);
        return;
    }

    /* ===== ÉPISODES / dossiers d'incident : garantir + basculer + actions =====
       Chaque incident (ex. infestation 2020 / 2024 / 2025) est un dossier séparé
       avec son propre parcours. On bascule sur l'épisode demandé (les liens/forms
       portent ?ep=) AVANT tout traitement du parcours. */
    if (function_exists('lfi_nct_episodes_ensure')) lfi_nct_episodes_ensure($u->ID);
    $ep_req = isset($_REQUEST['ep']) ? (int) $_REQUEST['ep'] : 0;
    if ($ep_req && function_exists('lfi_nct_episode_switch')) lfi_nct_episode_switch($u->ID, $ep_req);
    /* Défaut : l'épisode ACTIF (persistant) → tous les redirects portent le bon ep. */
    if (!$ep_req && function_exists('lfi_nct_episode_active_id')) $ep_req = lfi_nct_episode_active_id($u->ID);

    if (!empty($_POST['lfi_app_episode']) && check_admin_referer('lfi_app_episode') && function_exists('lfi_nct_episode_create')) {
        $act = sanitize_key($_POST['ep_action'] ?? '');
        $eid = (int) ($_POST['ep_id'] ?? 0);
        $titre = sanitize_text_field(wp_unslash($_POST['ep_titre'] ?? ''));
        $type  = sanitize_key($_POST['ep_type'] ?? 'autre');
        if ($act === 'create') {
            $new = lfi_nct_episode_create($u->ID, $titre !== '' ? $titre : 'Nouvel incident', $type, '');
            wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'ep' => $new]) . '#parcours'); exit;
        } elseif ($act === 'switch' && $eid) {
            lfi_nct_episode_switch($u->ID, $eid);
            wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'ep' => $eid]) . '#parcours'); exit;
        } elseif ($act === 'rename' && $eid) {
            lfi_nct_episode_update($u->ID, $eid, ['titre' => $titre, 'type' => $type]);
            wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'ep' => $eid]) . '#parcours'); exit;
        } elseif ($act === 'close' && $eid) {
            lfi_nct_episode_set_clos_urgence($u->ID, $eid, true);
            wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'ep' => $eid]) . '#parcours'); exit;
        } elseif ($act === 'reopen' && $eid) {
            lfi_nct_episode_set_clos_urgence($u->ID, $eid, false);
            wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'ep' => $eid]) . '#parcours'); exit;
        } elseif ($act === 'prej_add' && $eid && function_exists('lfi_nct_episode_prej_add')) {
            lfi_nct_episode_prej_add($u->ID, $eid, wp_unslash($_POST['prej_label'] ?? ''), wp_unslash($_POST['prej_montant'] ?? ''), wp_unslash($_POST['prej_date'] ?? ''));
            wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'ep' => $eid]) . '#parcours'); exit;
        } elseif ($act === 'prej_del' && $eid && function_exists('lfi_nct_episode_prej_del')) {
            lfi_nct_episode_prej_del($u->ID, $eid, (int) ($_POST['prej_idx'] ?? -1));
            wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'ep' => $eid]) . '#parcours'); exit;
        } elseif ($act === 'grp_sep' && $eid) {
            /* Séparer : cet incident devient son PROPRE dossier juridique. */
            lfi_nct_episode_set_groupe($u->ID, $eid, $eid);
            wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'ep' => $eid]) . '#parcours'); exit;
        } elseif ($act === 'grp_link' && $eid) {
            /* Rattacher au même dossier juridique qu'un autre incident. */
            $to = (int) ($_POST['ep_group_to'] ?? 0);
            $grp = 0;
            if ($to) foreach (lfi_nct_episodes_get($u->ID) as $ge) if ((int) ($ge['id'] ?? 0) === $to) $grp = lfi_nct_episode_groupe($ge);
            if ($grp) lfi_nct_episode_set_groupe($u->ID, $eid, $grp);
            wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'ep' => $eid]) . '#parcours'); exit;
        } elseif ($act === 'delete' && $eid) {
            lfi_nct_episode_delete($u->ID, $eid);
            wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID]) . '#parcours'); exit;
        }
    }

    /* Mise à jour des notes admin */
    if (!empty($_POST['lfi_app_dossier_notes']) && check_admin_referer('lfi_app_dossier_notes')) {
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
        update_user_meta($u->ID, 'lfi_nct_admin_notes', $notes);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'notes_saved' => 1]));
        exit;
    }

    /* 📊 Enregistrer / corriger la SYNTHÈSE (chiffrage du préjudice). */
    if (isset($_POST['lfi_app_synthese_save']) && check_admin_referer('lfi_app_synthese')) {
        update_user_meta($u->ID, 'lfi_nct_dossier_synthese', wp_kses_post(wp_unslash($_POST['synthese'] ?? '')));
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'syn_saved' => 1]) . '#synthese'); exit;
    }

    /* 🔗 RATTACHER une pièce à un ÉVÉNEMENT daté de la chronologie (ou détacher). */
    if (!empty($_POST['lfi_app_piece_link']) && check_admin_referer('lfi_app_piece_link')) {
        $att = (int) ($_POST['att_id'] ?? 0);
        $cid = (int) ($_POST['chrono_id'] ?? 0);
        if ($att && (int) get_post_meta($att, '_lfi_tenant_user_id', true) === (int) $u->ID) {
            if ($cid) update_post_meta($att, '_lfi_chrono_id', $cid);
            else delete_post_meta($att, '_lfi_chrono_id');
        }
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID]) . '#dossier-chrono'); exit;
    }
    /* 🔗 AUTO-rattachement : chaque pièce rejoint l'événement dont la DATE
       correspond à sa date de prise de vue (_lfi_capture_ts). */
    if (!empty($_POST['lfi_app_pieces_autolink']) && check_admin_referer('lfi_app_pieces_autolink')) {
        $n = lfi_nct_pieces_autolink_by_date($u->ID);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'autolinked' => $n]) . '#dossier-chrono'); exit;
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
    /* 👻 Enquête FANTÔME liée au dossier : restaurer (si en corbeille) ou délier. */
    if (!empty($_POST['lfi_dossier_enq_restore']) && check_admin_referer('lfi_dossier_enq')) {
        $rid0 = (int) get_user_meta($u->ID, 'lfi_nct_response_id', true);
        if ($rid0) $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}lfi_nct_responses SET deleted_at = NULL WHERE id = %d AND deleted_at IS NOT NULL", $rid0));
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'enq_restored' => 1])); exit;
    }
    if (!empty($_POST['lfi_dossier_enq_unlink']) && check_admin_referer('lfi_dossier_enq')) {
        delete_user_meta($u->ID, 'lfi_nct_response_id');
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'enq_unlinked' => 1])); exit;
    }
    /* ♻️ RECRÉER l'enquête disparue (même numéro si libre), pré-remplie
       « nuisibles (cafards, punaises) », reliée à ce locataire. On ne met AUCUNE
       date ni gravité inventée : l'éditeur s'ouvre pour compléter. */
    if (!empty($_POST['lfi_dossier_enq_recreate']) && check_admin_referer('lfi_dossier_enq')) {
        $t_resp = $wpdb->prefix . 'lfi_nct_responses';
        $rid0   = (int) get_user_meta($u->ID, 'lfi_nct_response_id', true);
        $exists = $rid0 ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t_resp WHERE id = %d", $rid0)) : 0;
        $me     = wp_get_current_user();
        $data   = wp_json_encode([
            'problemes_types'       => ['insectes'],
            'problemes_types_autre' => 'cafards, punaises de lit',
            'problemes_gravite'     => 0,
            'problemes_recurrent'   => 'permanent',
        ], JSON_UNESCAPED_UNICODE);
        $fields = [
            'militant_user_id'  => (int) $me->ID,
            'militant_login'    => (string) $me->user_login,
            'submitted_at'      => current_time('mysql'),
            'adresse'           => (string) ($row->adresse ?? ''),
            'contact_prenom'    => $u->first_name ?: 'Fabrice',
            'contact_nom'       => $u->last_name ?: 'Doucet',
            'contact_tel'       => (string) $tel,
            'contact_email'     => (string) $u->user_email,
            'contact_recontact' => 1,
            'data'              => $data,
            'ga'                => (string) get_user_meta($u->ID, 'lfi_nct_ga', true),
        ];
        if ($rid0 && !$exists) { $fields['id'] = $rid0; $wpdb->insert($t_resp, $fields); $new_id = $rid0; }
        else { $wpdb->insert($t_resp, $fields); $new_id = (int) $wpdb->insert_id; }
        update_user_meta($u->ID, 'lfi_nct_response_id', $new_id);
        /* S'assurer qu'il est bien reconnu comme LOCATAIRE (sinon son dossier ne
           s'ouvre pas) — rôle AJOUTÉ, on ne retire jamais superadmin. */
        if (defined('LFI_NCT_ROLE_TENANT') && !in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) $u->add_role(LFI_NCT_ROLE_TENANT);
        wp_safe_redirect(lfi_nct_app_url('enquete-edit', ['id' => $new_id, 'recreated' => 1])); exit;
    }
    /* 🔧 RECONSTRUIRE le dossier (chronologie punaises injectée dans les NOTES,
       éditable) + MANDAT OK (président/adhérent). Uniquement le dossier Fabrice. */
    if (!empty($_POST['lfi_dossier_reconstruct']) && check_admin_referer('lfi_dossier_reco')) {
        $chrono = "CHRONOLOGIE — Fabrice DOUCET · apt 88, 14 rue de Saint-Jean-de-Luz, 44200 Nantes · punaises de lit (À RELIRE/CORRIGER)\n"
            . "2020 — Première infestation de l'étage ; traitement partiel (interventions 03 et 17/09/2020) ; participation de 80 € imposée au locataire.\n"
            . "17/07/2025 — Expertise entomologique de M. François Meurgey (Muséum de Nantes) : confirmation punaises de lit.\n"
            . "31/07/2025 — Constat SCHS (base du PV).\n"
            . "20/08/2025 — PV SCHS (réf. JL.FM.20082025) : présence actuelle de punaises de lit, immeuble 14 rue Saint-Jean-de-Luz.\n"
            . "21/08/2025 — PV du voisin M. Kaba (apt 87) : infestation mitoyenne.\n"
            . "12/06/2025 et 08/08/2025 — 2 mises en demeure de la Mairie à NMH, restées non exécutées.\n"
            . "17/09/2025 — Ordonnance de référés (demande rejetée) ; orientation Conseil d'État.\n"
            . "14/12/2025 — Indemnité « réparations locatives ».\n"
            . "23/03/2026 — Traitement préventif de contrôle : constat de blattes (garantie 6 mois) — victoire partielle.\n"
            . "10/05/2026 — Piqûre de Souleyman (fils) ; troubles du sommeil persistants.\n"
            . "11/05/2026 — Appel NMH (dossier 2026-33305) ; bon de commande évoqué pour l'apt 87 ; intervention partielle.\n"
            . "19/05/2026 — Aide juridictionnelle totale (BAJ TJ Nantes, N-44109-2026-003859) ; avocate désignée Me Julie Supiot (Barreau de Nantes, 06 67 93 26 18).\n"
            . "26/05/2026 — Piqûres (Fabrice + fils Souleyman).\n"
            . "27/05 et 03/06/2026 — Interventions/visites « SIHS punaises ».\n"
            . "06/06/2026 — Email à NMH ; NMH relance le prestataire (Sapiens) pour un nouveau RDV.\n"
            . "08/06/2026 — Départ du logement (inhabitable).\n"
            . "09/06/2026 — Réponse de NMH reçue.\n"
            . "11/06/2026 — Punaise vivante photographiée (IMG_1258) ; PJ de mise en demeure ; demande n°3.\n"
            . "12/06/2026 — Réponse NMH (« nous prenons note… néanmoins concernant les interventions de désinsectisation… »).\n"
            . "16/06/2026 — Huissier : « désignés pour la délivrance d'actes, pas pour la réalisation d'un constat ».\n"
            . "01/07/2026 — Point avec Maître Julie Supiot (avocate désignée, aide juridictionnelle) ; dépôt de la modification des statuts de l'association.";
        /* On alimente la CHRONOLOGIE structurée (pas les notes) : une entrée par
           ligne datée « DATE — événement ». */
        foreach (preg_split('/\r\n|\r|\n/', $chrono) as $ln) {
            $ln = trim($ln); if ($ln === '' || stripos($ln, 'CHRONOLOGIE — Fabrice') === 0) continue;
            $parts = preg_split('/\s+[—-]\s+/u', $ln, 2);
            if (count($parts) === 2) lfi_nct_chrono_add($u->ID, trim($parts[0]), trim($parts[1]), false);
            else lfi_nct_chrono_add($u->ID, '', $ln, false);
        }
        /* Dossier juridique garanti (sinon les emails n'ont nulle part où aller)
           + mandat OK (président/adhérent, pas de formulaire à signer). */
        $dj = function_exists('lfi_nct_dossier_ensure_for_tenant') ? lfi_nct_dossier_ensure_for_tenant($u->ID)
            : (function_exists('lfi_nct_dossier_find_for_tenant') ? lfi_nct_dossier_find_for_tenant($u->ID) : null);
        if ($dj && function_exists('lfi_nct_dossier_mandat_set')) lfi_nct_dossier_mandat_set((int) $dj->id, 1);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'reco' => 1])); exit;
    }
    /* Chronologie : ajout / retrait d'une ligne. */
    if (!empty($_POST['lfi_chrono_add']) && check_admin_referer('lfi_chrono')) {
        lfi_nct_chrono_add($u->ID, sanitize_text_field(wp_unslash($_POST['chrono_date'] ?? '')), sanitize_text_field(wp_unslash($_POST['chrono_txt'] ?? '')), false);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID]) . '#dossier-chrono'); exit;
    }
    if (isset($_POST['lfi_chrono_del']) && check_admin_referer('lfi_chrono')) {
        $cid = (int) $_POST['lfi_chrono_del'];
        $list = array_values(array_filter(lfi_nct_chrono_get($u->ID), function ($e) use ($cid) { return (int) ($e['id'] ?? 0) !== $cid; }));
        lfi_nct_chrono_save($u->ID, $list);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID]) . '#dossier-chrono'); exit;
    }
    if (!empty($_POST['lfi_chrono_bulk_del']) && check_admin_referer('lfi_chrono')) {
        $sel = array_map('intval', (array) ($_POST['chrono_sel'] ?? []));
        $list = array_values(array_filter(lfi_nct_chrono_get($u->ID), function ($e) use ($sel) { return !in_array((int) ($e['id'] ?? 0), $sel, true); }));
        lfi_nct_chrono_save($u->ID, $list);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID]) . '#dossier-chrono'); exit;
    }
    if (!empty($_POST['lfi_chrono_reset']) && check_admin_referer('lfi_chrono')) {
        lfi_nct_chrono_save($u->ID, []);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID]) . '#dossier-chrono'); exit;
    }
    /* ✏️ MODIFIER une ligne de chronologie (y compris importée). */
    if (!empty($_POST['lfi_chrono_edit']) && check_admin_referer('lfi_chrono')) {
        $cid = (int) $_POST['lfi_chrono_edit'];
        $nd  = sanitize_text_field(wp_unslash($_POST['chrono_date'] ?? ''));
        $nt  = sanitize_text_field(wp_unslash($_POST['chrono_txt'] ?? ''));
        $list = lfi_nct_chrono_get($u->ID);
        foreach ($list as $i => $e) {
            if ((int) ($e['id'] ?? 0) === $cid) {
                $list[$i]['label'] = $nd;
                $list[$i]['d'] = lfi_nct_chrono_norm_date($nd);
                if ($nt !== '') $list[$i]['txt'] = $nt;
                break;
            }
        }
        lfi_nct_chrono_save($u->ID, $list);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID]) . '#dossier-chrono'); exit;
    }

    /* 🗑 Supprimer TOUTES les pièces du dossier (repartir propre). */
    if (!empty($_POST['lfi_app_pieces_purge']) && check_admin_referer('lfi_app_pieces_purge')) {
        $n = lfi_nct_dossier_purge_pieces($u->ID);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'pieces_purged' => $n]) . '#import-md'); exit;
    }

    /* 🧹 NETTOYER LES DOUBLONS (chronologie + pièces) — un seul bouton. */
    if (!empty($_POST['lfi_app_dossier_dedupe']) && check_admin_referer('lfi_app_dossier_dedupe')) {
        $dc = lfi_nct_chrono_dedupe($u->ID);
        $dp = lfi_nct_pieces_dedupe($u->ID);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'deduped_c' => $dc, 'deduped_p' => $dp]) . '#dossier-chrono'); exit;
    }

    /* 🗑 VIDER TOUT le dossier (pièces + chronologie + emails + notes). */
    if (!empty($_POST['lfi_app_dossier_wipe']) && check_admin_referer('lfi_app_dossier_wipe')) {
        $rep = lfi_nct_dossier_wipe_all($u->ID);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'wiped' => (int) $rep['pieces'] + (int) $rep['chrono']]) . '#import-md'); exit;
    }

    /* 📄 IMPORT d'un dossier rédigé (.md) : le robot décortique la chronologie
       date par date, et range les photos/PDF joints comme pièces. */
    if (!empty($_POST['lfi_app_md_import']) && check_admin_referer('lfi_app_md_import')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $added_chrono = 0; $added_pieces = 0; $md_avocat = '';

        /* 1) Le texte : fichier .md/.txt téléversé OU zone de texte collée. */
        $md_text = '';
        if (!empty($_FILES['mdfile']['tmp_name']) && (int) $_FILES['mdfile']['size'] <= 3 * 1024 * 1024) {
            $md_text = (string) @file_get_contents($_FILES['mdfile']['tmp_name']);
        }
        if (trim($md_text) === '' && !empty($_POST['md_paste'])) $md_text = (string) wp_unslash($_POST['md_paste']);
        $md_text = wp_check_invalid_utf8($md_text, true);

        if (trim($md_text) !== '' && function_exists('lfi_nct_md_extract_chrono')) {
            /* REMPLACER (par défaut) : on vide la chronologie avant d'importer →
               jamais de doublon empilé. Décocher pour ajouter à l'existant. */
            if (empty($_POST['md_append'])) lfi_nct_chrono_save($u->ID, []);
            foreach (lfi_nct_md_extract_chrono($md_text) as $e) {
                $lab = trim((string) ($e['date'] ?? ''));
                $ev  = trim((string) ($e['event'] ?? ''));
                if ($ev === '') continue;
                if (lfi_nct_chrono_add($u->ID, $lab !== '' ? $lab : wp_date('Y-m-d'), $ev, true)) $added_chrono++;
            }
            /* SYNTHÈSE / chiffrage du préjudice (parties non datées du .md). */
            if (function_exists('lfi_nct_md_extract_synthese')) {
                $syn = lfi_nct_md_extract_synthese($md_text);
                if ($syn !== '') update_user_meta($u->ID, 'lfi_nct_dossier_synthese', wp_kses_post($syn));
            }
            /* ENTITÉS : avocat·e (créée + rattachée), bailleur, hygiène, aide jurid. */
            if (function_exists('lfi_nct_md_extract_entities')) {
                $ent = lfi_nct_md_extract_entities($md_text);
                $av  = is_array($ent) ? ($ent['avocat'] ?? null) : null;
                $anom = is_array($av) ? trim((string) ($av['nom'] ?? '')) : '';
                if ($anom !== '' && function_exists('lfi_nct_avocat_ensure') && function_exists('lfi_nct_avocat_assign_tenant')) {
                    $aid = lfi_nct_avocat_ensure($anom, (string) ($av['email'] ?? ''), (string) ($av['tel'] ?? ''), (string) ($av['barreau'] ?? ''));
                    if ($aid) { lfi_nct_avocat_assign_tenant($u->ID, $aid); $md_avocat = $anom; }
                }
                /* Bailleur / Hygiène / Aide juridictionnelle → bloc « Interlocuteurs
                   & références » du dossier (informatif, éditable). */
                $lines = [];
                $bl = is_array($ent) ? ($ent['bailleur'] ?? null) : null;
                if (is_array($bl) && trim((string) ($bl['nom'] ?? '')) !== '') {
                    $lines[] = '🏢 Bailleur : ' . trim(implode(' · ', array_filter([$bl['nom'] ?? '', $bl['contact'] ?? '', $bl['tel'] ?? '', $bl['email'] ?? '', ($bl['dossier'] ?? '') ? 'dossier ' . $bl['dossier'] : ''])));
                }
                $hy = is_array($ent) ? ($ent['hygiene'] ?? null) : null;
                if (is_array($hy) && trim((string) ($hy['service'] ?? '')) !== '') {
                    $lines[] = '🩺 Hygiène (SCHS) : ' . trim(implode(' · ', array_filter([$hy['service'] ?? '', $hy['contact'] ?? '', $hy['tel'] ?? '', $hy['email'] ?? '', ($hy['ref'] ?? '') ? 'réf. ' . $hy['ref'] : ''])));
                }
                $aj = is_array($ent) ? trim((string) ($ent['aide_juridictionnelle'] ?? '')) : '';
                if ($aj !== '') $lines[] = '⚖️ Aide juridictionnelle : ' . $aj;
                if ($anom !== '') $lines[] = '👩‍⚖️ Avocate : ' . $anom;
                if ($lines) update_user_meta($u->ID, 'lfi_nct_dossier_interlocuteurs', wp_kses_post(implode("\n", $lines)));
            }
            /* On garde le .md source comme pièce « document » (traçabilité). */
            $up = wp_upload_dir();
            if (empty($up['error'])) {
                $fname = wp_unique_filename($up['path'], 'dossier-' . $u->ID . '-' . wp_date('Ymd-His') . '.md');
                $fpath = trailingslashit($up['path']) . $fname;
                /* BOM UTF-8 → les visionneuses affichent les accents correctement
                   (plus de « Ã© » / « â€" »). */
                if (@file_put_contents($fpath, "\xEF\xBB\xBF" . $md_text) !== false) {
                    $att = wp_insert_attachment(['post_mime_type' => 'text/markdown', 'post_title' => 'Dossier importé (.md) — ' . $u->display_name, 'post_status' => 'private', 'post_author' => (int) get_current_user_id()], $fpath);
                    if (!is_wp_error($att) && $att) {
                        update_post_meta($att, '_lfi_tenant_user_id', $u->ID);
                        update_post_meta($att, '_lfi_piece_cat', 'document');
                        update_post_meta($att, '_lfi_tenant_piece', 'Document importé (.md)');
                    }
                }
            }
        }

        /* 2) Les pièces jointes (photos / PDF) → rangées + catégorisées + étape. */
        if (!empty($_FILES['pieces']) && is_array($_FILES['pieces']['name'])) {
            $cnt = count($_FILES['pieces']['name']);
            for ($i = 0; $i < $cnt; $i++) {
                if (empty($_FILES['pieces']['tmp_name'][$i]) || (int) $_FILES['pieces']['size'][$i] > 15 * 1024 * 1024) continue;
                $file = [
                    'name'     => $_FILES['pieces']['name'][$i],
                    'type'     => $_FILES['pieces']['type'][$i],
                    'tmp_name' => $_FILES['pieces']['tmp_name'][$i],
                    'error'    => $_FILES['pieces']['error'][$i],
                    'size'     => $_FILES['pieces']['size'][$i],
                ];
                $upload = wp_handle_upload($file, ['test_form' => false]);
                if (!empty($upload['error'])) continue;
                $att = wp_insert_attachment(['post_mime_type' => $upload['type'], 'post_title' => 'Pièce importée — ' . $u->display_name, 'post_status' => 'private', 'post_author' => (int) get_current_user_id()], $upload['file']);
                if (is_wp_error($att) || !$att) continue;
                update_post_meta($att, '_lfi_tenant_user_id', $u->ID);
                update_post_meta($att, '_lfi_tenant_piece', 'Pièce importée');
                wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $upload['file']));
                if (function_exists('lfi_nct_piece_categorize')) {
                    $cat = lfi_nct_piece_categorize((string) $file['name'], (string) $upload['type']);
                    update_post_meta($att, '_lfi_piece_cat', $cat['cat']);
                    if (function_exists('lfi_nct_piece_autostep')) {
                        $sk = lfi_nct_piece_autostep($u->ID, $cat['cat']);
                        if ($sk !== '') update_post_meta($att, '_lfi_step', $sk);
                    }
                }
                if (function_exists('lfi_nct_store_capture_ts')) lfi_nct_store_capture_ts($att, $upload['file']);
                $added_pieces++;
            }
        }

        /* 3) ARCHIVE ZIP : on en SORT toutes les photos/PDF → rangées comme
           pièces dans CE dossier (catégorisées + étape auto). */
        if (!empty($_FILES['zipfile']['tmp_name']) && (int) $_FILES['zipfile']['size'] <= 80 * 1024 * 1024) {
            $added_pieces += lfi_nct_dossier_import_zip($_FILES['zipfile']['tmp_name'], (int) $u->ID);
        }

        $args = ['uid' => $u->ID, 'md_chrono' => $added_chrono, 'md_pieces' => $added_pieces];
        if (!empty($md_avocat)) $args['md_avocat'] = rawurlencode($md_avocat);
        wp_safe_redirect(lfi_nct_app_url('dossier', $args) . '#import-md'); exit;
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

    /* 📎 Dépôt d'une pièce dans une ÉTAPE → auto-clôture de l'étape (le robot
       range par date, analyse, coche). Manuel toujours possible (rouvrir/retirer). */
    if (!empty($_POST['lfi_app_step_piece']) && check_admin_referer('lfi_app_step_piece')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $skey = sanitize_text_field(wp_unslash($_POST['step_key'] ?? ''));
        $sidx = (int) ($_POST['step_idx'] ?? -1);
        $ok = false;
        if (!empty($_FILES['piece']['tmp_name']) && $skey !== '' && (int) $_FILES['piece']['size'] <= 12 * 1024 * 1024) {
            $upload = wp_handle_upload($_FILES['piece'], ['test_form' => false]);
            if (empty($upload['error'])) {
                $att = wp_insert_attachment(['post_mime_type' => $upload['type'], 'post_title' => 'Pièce dossier — ' . $u->display_name, 'post_status' => 'private', 'post_author' => (int) get_current_user_id()], $upload['file']);
                if (!is_wp_error($att) && $att) {
                    update_post_meta($att, '_lfi_tenant_user_id', $u->ID);
                    update_post_meta($att, '_lfi_step', $skey);
                    $cat = lfi_nct_piece_categorize((string) ($_FILES['piece']['name'] ?? ''), (string) $upload['type']);
                    update_post_meta($att, '_lfi_piece_cat', $cat['cat']);
                    wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $upload['file']));
                    if (function_exists('lfi_nct_store_capture_ts')) lfi_nct_store_capture_ts($att, $upload['file']);
                    $ok = true;
                }
            }
        }
        if ($ok && $sidx >= 0) { /* le robot coche et clôt l'étape */
            $st = get_user_meta($u->ID, 'lfi_nct_suivi_steps', true);
            if (is_array($st) && isset($st[$sidx])) { $st[$sidx]['done'] = true; update_user_meta($u->ID, 'lfi_nct_suivi_steps', array_values($st)); }
            if (function_exists('lfi_nct_episode_save_active')) lfi_nct_episode_save_active($u->ID);
        }
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'ep' => $ep_req, ($ok ? 'piece_ok' : 'piece_err') => 1]) . '#parcours'); exit;
    }
    if (!empty($_POST['lfi_app_step_piece_del']) && check_admin_referer('lfi_app_step_piece')) {
        $att = (int) ($_POST['att_id'] ?? 0);
        if ($att && (int) get_post_meta($att, '_lfi_tenant_user_id', true) === (int) $u->ID) wp_delete_attachment($att, true);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'ep' => $ep_req]) . '#parcours'); exit;
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
        } elseif ($action === 'besoin_add') {
            /* Déclare ce qu'on attend du LOCATAIRE pour cette étape (pièce/info). */
            $idx = (int) ($_POST['step_idx'] ?? -1);
            $btype = sanitize_key($_POST['besoin_type'] ?? 'info');
            $blabel = sanitize_text_field(wp_unslash($_POST['besoin_label'] ?? ''));
            $types = function_exists('lfi_nct_suivi_besoin_types') ? lfi_nct_suivi_besoin_types() : [];
            if (!isset($types[$btype])) $btype = 'info';
            if ($blabel === '' && isset($types[$btype])) $blabel = $types[$btype][1];
            if (isset($steps[$idx]) && $blabel !== '') {
                if (empty($steps[$idx]['besoins']) || !is_array($steps[$idx]['besoins'])) $steps[$idx]['besoins'] = [];
                $steps[$idx]['besoins'][] = ['type' => $btype, 'label' => $blabel, 'done' => false];
            }
        } elseif ($action === 'besoin_del') {
            $idx = (int) ($_POST['step_idx'] ?? -1);
            $bidx = (int) ($_POST['besoin_idx'] ?? -1);
            if (isset($steps[$idx]['besoins'][$bidx])) { array_splice($steps[$idx]['besoins'], $bidx, 1); }
        } elseif ($action === 'explain') {
            /* Explication pédagogique sur-mesure (sinon texte auto côté locataire). */
            $idx = (int) ($_POST['step_idx'] ?? -1);
            if (isset($steps[$idx])) $steps[$idx]['explain'] = sanitize_textarea_field(wp_unslash($_POST['step_explain'] ?? ''));
        } elseif ($action === 'del') {
            $idx = (int) ($_POST['step_idx'] ?? -1);
            if (isset($steps[$idx])) { array_splice($steps, $idx, 1); }
        } elseif ($action === 'bulk_del') {
            /* Suppression MULTIPLE : on retire toutes les étapes cochées. */
            $rm = array_map('intval', (array) ($_POST['step_sel'] ?? []));
            rsort($rm); /* du plus grand index au plus petit pour ne pas décaler */
            foreach ($rm as $i) { if (isset($steps[$i])) array_splice($steps, $i, 1); }
        } elseif ($action === 'reset') {
            /* Tout effacer le parcours. */
            $steps = [];
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
        if (function_exists('lfi_nct_episode_save_active')) lfi_nct_episode_save_active($u->ID);
        wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $u->ID, 'ep' => $ep_req, 'step_saved' => 1]));
        exit;
    }

    $rid = (int) get_user_meta($u->ID, 'lfi_nct_response_id', true);
    $tel = (string) get_user_meta($u->ID, 'lfi_nct_tel', true);
    $admin_notes = (string) get_user_meta($u->ID, 'lfi_nct_admin_notes', true);
    $row = $rid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid)) : null;
    /* 👻 État de l'enquête liée : '' = ok · 'trashed' = en corbeille · 'missing' = disparue. */
    $enq_ghost = '';
    if ($rid) {
        if (!$row) $enq_ghost = 'missing';
        elseif (!empty($row->deleted_at)) $enq_ghost = 'trashed';
    }
    /* Une enquête en corbeille ne doit pas alimenter le dossier comme si elle
       était active. */
    $problem = ($row && $enq_ghost === '') ? lfi_nct_app_enq_problem($row) : null;

    /* Photos — CLASSÉES par date de prise de vue (chronologie), pas par upload. */
    $photos = function_exists('lfi_nct_tenant_photos_chrono')
        ? lfi_nct_tenant_photos_chrono($u->ID, 200)
        : get_posts(['post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => 100, 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => [['key' => '_lfi_tenant_user_id', 'value' => $u->ID]]]);

    /* Communications */
    $sms_log   = $tel ? $wpdb->get_results($wpdb->prepare(
        "SELECT sent_at, body_sent FROM {$wpdb->prefix}lfi_nct_sms_log WHERE tel = %s ORDER BY sent_at DESC LIMIT 20",
        $tel
    )) : [];
    $email_log = $u->user_email ? $wpdb->get_results($wpdb->prepare(
        "SELECT sent_at, opened_at FROM {$wpdb->prefix}lfi_nct_email_log WHERE email = %s ORDER BY sent_at DESC LIMIT 20",
        $u->user_email
    )) : [];

    lfi_nct_app_screen_open('📂 Dossier locataire', '');

    if (!empty($_GET['notes_saved'])) lfi_nct_app_flash('Notes enregistrées.');
    if (!empty($_GET['step_saved']))  lfi_nct_app_flash('✅ Parcours de suivi mis à jour.');
    if (!empty($_GET['piece_ok']))    lfi_nct_app_flash('📎 Pièce versée et rangée par date — l\'étape est cochée automatiquement.');
    if (!empty($_GET['piece_err']))   lfi_nct_app_flash('⚠️ Dépôt impossible (fichier manquant ou trop lourd — max 12 Mo, image ou PDF).', 'error');
    if (!empty($_GET['won']))  lfi_nct_app_flash('🏆 Coupe posée ! Une réussite ANONYME est prête dans « 🏆 Réussites » — relis-la et publie-la (aucun nom n\'y figure). Les membres du GA verront la victoire à l\'ouverture de l\'app.');
    if (!empty($_GET['unwon'])) lfi_nct_app_flash('Coupe annulée.');
    if (!empty($_GET['avocat_ok'])) lfi_nct_app_flash('⚖️ Dossier confié à l\'avocat·e. Il/elle le voit dans son espace (note + pièces + ligne directe).');
    if (!empty($_GET['enq_restored'])) lfi_nct_app_flash('♻️ Enquête restaurée depuis la corbeille — le dossier est de nouveau complet.');
    if (!empty($_GET['enq_unlinked'])) lfi_nct_app_flash('Enquête déliée du dossier.');
    if (!empty($_GET['reco'])) lfi_nct_app_flash('🔧 Dossier reconstruit : chronologie ajoutée dans la 📅 Chronologie (triée par date, éditable) + mandat coché (président). Pense à recréer/relier l\'enquête #6 si besoin.');

    /* ===== BANNIÈRE — nom en GROS + n° d'enquête + éditer fiche/enquête ===== */
    $sms_blocked = ($tel && function_exists('lfi_nct_sms_is_blocked')) ? lfi_nct_sms_is_blocked($tel) : false;
    $mail_ok = ($u->user_email && stripos($u->user_email, '@tenant.') === false && stripos($u->user_email, '@partenaire.') === false);
    $initiale = mb_strtoupper(mb_substr($u->display_name, 0, 1));
    $tel_clean = $tel ? preg_replace('/[^\d+]/', '', $tel) : '';
    echo '<div style="background:linear-gradient(135deg,#c8102e,#7d0a1c);color:#fff;border-radius:16px;padding:16px 16px 14px;margin-bottom:12px;box-shadow:0 3px 14px rgba(200,16,46,.22)">';
    echo   '<div style="display:flex;gap:13px;align-items:center">';
    echo     '<div style="width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:1.5em;font-weight:900;flex:0 0 auto">' . esc_html($initiale) . '</div>';
    echo     '<div style="flex:1;min-width:0">';
    echo       '<div style="font-size:1.45em;font-weight:900;line-height:1.12">' . esc_html($u->display_name) . '</div>';
    if ($rid && $enq_ghost === '') echo '<a href="' . esc_url(lfi_nct_app_url('enquete-edit', ['id' => $rid])) . '" style="display:inline-block;margin-top:5px;background:#fff;color:#7d0a1c;font-weight:800;font-size:.8em;padding:2px 10px;border-radius:20px;text-decoration:none">📋 Enquête #' . (int) $rid . ' · modifier</a>';
    elseif ($rid && $enq_ghost === 'trashed') echo '<span style="display:inline-block;margin-top:5px;background:#ffe08a;color:#6b4e00;font-weight:800;font-size:.8em;padding:2px 10px;border-radius:20px">🗑 Enquête #' . (int) $rid . ' en corbeille</span>';
    elseif ($rid && $enq_ghost === 'missing') echo '<span style="display:inline-block;margin-top:5px;background:rgba(255,255,255,.25);color:#fff;font-weight:800;font-size:.8em;padding:2px 10px;border-radius:20px">👻 Enquête #' . (int) $rid . ' introuvable</span>';
    else      echo '<a href="' . esc_url(lfi_nct_app_url('enquete')) . '" style="display:inline-block;margin-top:5px;background:rgba(255,255,255,.25);color:#fff;font-weight:700;font-size:.8em;padding:2px 10px;border-radius:20px;text-decoration:none">➕ Lier une enquête</a>';
    echo     '</div>';
    echo   '</div>';
    /* Coordonnées compactes. */
    echo   '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:11px;font-size:.85em">';
    if ($row && $row->adresse) echo '<span style="background:rgba(255,255,255,.16);padding:3px 9px;border-radius:14px">📍 ' . esc_html(trim($row->adresse . ($row->etage ? ' · ét. ' . $row->etage : ''))) . '</span>';
    if ($tel)     echo '<span style="background:rgba(255,255,255,.16);padding:3px 9px;border-radius:14px">📞 ' . esc_html($tel) . '</span>';
    if ($mail_ok) echo '<span style="background:rgba(255,255,255,.16);padding:3px 9px;border-radius:14px">✉️ ' . esc_html($u->user_email) . '</span>';
    echo   '</div>';
    /* Actions principales : MODIFIER FICHE / ENQUÊTE + contact direct. */
    $bw = 'background:#fff;color:#7d0a1c;text-decoration:none;font-weight:800;font-size:.85em;padding:7px 12px;border-radius:9px;display:inline-block';
    $bg = 'background:rgba(255,255,255,.16);color:#fff;text-decoration:none;font-weight:700;font-size:.85em;padding:7px 12px;border-radius:9px;display:inline-block';
    echo   '<div style="display:flex;flex-wrap:wrap;gap:7px;margin-top:12px">';
    echo     '<a style="' . $bw . '" href="' . esc_url(lfi_nct_app_url('comptes', ['tab' => 'locataires', 'open' => $u->ID])) . '">✏️ Modifier la fiche</a>';
    if ($rid) echo '<a style="' . $bw . '" href="' . esc_url(lfi_nct_app_url('enquete-edit', ['id' => $rid])) . '">📋 Modifier l\'enquête</a>';
    echo     '<a style="' . $bg . '" href="#dossier-photos">📎 Pièces & photos (' . count($photos) . ')</a>';
    if ($tel && !$sms_blocked) echo '<a style="' . $bg . '" href="sms:' . esc_attr($tel_clean) . '">📱 SMS</a>';
    if ($tel) echo '<a style="' . $bg . '" href="tel:' . esc_attr($tel_clean) . '">📞 Appeler</a>';
    if ($mail_ok) echo '<a style="' . $bg . '" href="mailto:' . esc_attr($u->user_email) . '">✉️ Email</a>';
    echo   '</div>';
    echo '</div>';

    /* Ligne secondaire discrète : retour + PDF avocat + (dé)blocage SMS. */
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px;font-size:.82em">';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('comptes', ['tab' => 'locataires'])) . '">← Tous les locataires</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-avocat', ['uid' => $u->ID])) . '" target="_blank">⚖️ Note avocat (PDF)</a>';
    if ($tel && function_exists('lfi_nct_sms_block_toggle_link')) {
        $lbl = $sms_blocked ? '↩ Réautoriser les SMS' : '🚫 Ne plus lui envoyer de SMS';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_sms_block_toggle_link($tel, $u->display_name)) . '">' . $lbl . '</a>';
    }
    echo '</div>';

    /* 👻 ALERTE ENQUÊTE FANTÔME — enquête liée disparue : proposer de restaurer. */
    if ($enq_ghost === 'trashed' || $enq_ghost === 'missing') {
        echo '<div class="lfi-app-card" style="border-left:4px solid #d39e00;background:#fff8e6;margin-bottom:12px">';
        if ($enq_ghost === 'trashed') {
            echo '<div class="head"><div class="who" style="color:#8a6d1f">🗑 L\'enquête #' . (int) $rid . ' est en corbeille</div></div>';
            echo '<div class="com" style="font-size:.92em">Ce dossier est lié à l\'enquête <strong>#' . (int) $rid . '</strong> qui a été mise à la corbeille. Restaure-la pour retrouver l\'adresse, les problèmes signalés et la gravité.</div>';
            echo '<form method="post" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">' . wp_nonce_field('lfi_dossier_enq', '_wpnonce', true, false);
            echo '<button type="submit" name="lfi_dossier_enq_restore" value="1" class="btn-primary" style="background:#186a3b">♻️ Restaurer l\'enquête #' . (int) $rid . '</button>';
            echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('enquetes-corbeille')) . '">🗑 Voir la corbeille</a>';
            echo '</form></div>';
        } else {
            echo '<div class="head"><div class="who" style="color:#8a6d1f">👻 Enquête #' . (int) $rid . ' introuvable</div></div>';
            echo '<div class="com" style="font-size:.92em">Ce dossier pointe vers l\'enquête <strong>#' . (int) $rid . '</strong> qui n\'existe plus. Tu peux la <strong>recréer avec le même numéro</strong> (pré-remplie « nuisibles : cafards, punaises »), puis compléter les dates et la gravité dans l\'éditeur.</div>';
            echo '<form method="post" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">' . wp_nonce_field('lfi_dossier_enq', '_wpnonce', true, false);
            echo '<button type="submit" name="lfi_dossier_enq_recreate" value="1" class="btn-primary" style="background:#186a3b">♻️ Recréer l\'enquête #' . (int) $rid . ' (nuisibles)</button>';
            echo '<button type="submit" name="lfi_dossier_enq_unlink" value="1" class="btn-ghost" onclick="return confirm(\'Délier l\\\'enquête #' . (int) $rid . ' de ce dossier ?\')">🔓 Délier</button>';
            echo '</form></div>';
        }
        echo '</div>';
    }

    /* 🔧 Reconstruction du dossier — UNIQUEMENT sur le dossier de Fabrice
       (président). Injecte la chronologie punaises dans les notes + mandat OK. */
    $is_fabrice = (stripos((string) $u->display_name, 'doucet') !== false)
        || (stripos((string) $u->user_email, 'doucet') !== false)
        || (stripos((string) $u->user_email, 'nantessudclostoreau') !== false);
    if ($is_fabrice) {
        echo '<details class="lfi-app-card" style="border-left:4px solid #6a1b9a;margin-bottom:12px"><summary style="cursor:pointer;font-weight:800;color:#6a1b9a">🔧 Reconstruire mon dossier (chronologie punaises + mandat président)</summary>';
        echo '<div class="com" style="font-size:.92em;margin-top:6px">Injecte la <strong>chronologie complète</strong> (2020 → 2026) dans tes 📝 <strong>Notes du GA</strong> (éditable, tu corriges ce que tu veux) et coche le <strong>mandat</strong> (tu es président·e de l\'association → pas de formulaire d\'adhésion à signer). N\'écrase rien : ajoute en tête des notes.</div>';
        echo '<form method="post" style="margin-top:8px">' . wp_nonce_field('lfi_dossier_reco', '_wpnonce', true, false) . '<input type="hidden" name="lfi_dossier_reconstruct" value="1"><button type="submit" class="btn-primary" style="background:#6a1b9a">🔧 Reconstruire maintenant</button></form>';
        echo '<div class="lfi-app-help" style="margin-top:6px"><small>⚠️ Relis la chronologie après coup — je n\'invente pas, mais vérifie chaque date dans ton vrai dossier.</small></div></details>';
    }

    /* ===== LES DEUX BATAILLES + la demande du locataire (EN HAUT) ===== */
    lfi_nct_dossier_render_batailles($u, $row);

    /* ===== CHRONOLOGIE (timeline structurée, auto-alimentée) ===== */
    lfi_nct_dossier_render_chrono($u);

    /* ===== SYNTHÈSE / chiffrage du préjudice (importé du .md) ===== */
    lfi_nct_dossier_render_synthese($u);

    /* ===== IMPORT .md (chronologie décortiquée par l'IA) + vider les pièces ===== */
    lfi_nct_dossier_render_import_md($u);

    /* ===== Partager l'espace avec le locataire (le fait entrer dans l'app) ===== */
    echo '<details class="lfi-app-card" style="border:2px solid #0066a3;background:#f2f8fd;margin-top:12px"' . ($share_link !== '' ? ' open' : '') . '>';
    echo '<summary style="cursor:pointer;font-weight:800;color:#0066a3;list-style:none">🔗 Partager l\'espace avec le locataire</summary>';
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
    echo '</details>';

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

    /* Photos — dans l'ordre CHRONOLOGIQUE (date de prise de vue). */
    echo '<h3 id="dossier-photos" style="margin:18px 0 8px;scroll-margin-top:70px">📷 Photos envoyées (' . count($photos) . ') <small style="font-weight:400;color:#888">· classées par date de prise de vue</small></h3>';
    if (empty($photos)) {
        echo '<div class="lfi-app-empty">Aucune photo encore envoyée.</div>';
    } else {
        echo '<div class="lfi-tenant-gallery">';
        $prev_day = '';
        foreach ($photos as $p) {
            $cap_ts = (int) get_post_meta($p->ID, '_lfi_capture_ts', true);
            $day = $cap_ts ? wp_date('Y-m-d', $cap_ts) : wp_date('Y-m-d', strtotime($p->post_date));
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
            echo '<div class="when">📅 ' . esc_html(lfi_nct_photo_capture_label($p->ID)) . '</div>';
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

/** 🆕 QUOI DE NEUF CÔTÉ LOCATAIRES — en tête de l'accueil : les emails importés
 *  récents, les photos/pièces reçues, et les emails « à rattacher ». Un « NEW »
 *  tant qu'on ne l'a pas vu (mémorisé par admin). */
function lfi_nct_render_home_locataire_news() {
    $can = current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
    if (!$can) return;
    global $wpdb;

    /* « Vu » : on retient l'instant de dernière consultation. */
    $seen = (int) get_user_meta(get_current_user_id(), 'lfi_nct_locnews_seen', true);

    $owner = function_exists('lfi_nct_fact_owner_id') ? (int) lfi_nct_fact_owner_id() : (int) get_current_user_id();
    $td = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id, tenant_user_id, tenant_prenom, tenant_nom, notes, updated_at FROM $td WHERE owner_user_id = %d ORDER BY updated_at DESC LIMIT 200", $owner)) ?: [];
    $events = [];
    foreach ($rows as $r) {
        $logs = json_decode($r->notes ?? '', true); if (!is_array($logs)) continue;
        $name = trim($r->tenant_prenom . ' ' . $r->tenant_nom) ?: 'Locataire';
        $uid  = (int) $r->tenant_user_id;
        foreach (['email_recu' => ['📥', 'Email reçu', '#0066a3'], 'email_log' => ['📤', 'Email envoyé', '#186a3b']] as $k => $m) {
            if (empty($logs[$k]) || !is_array($logs[$k])) continue;
            foreach ($logs[$k] as $e) {
                /* Imports AUTOMATIQUES (ce qu'on risque de rater) : la voie « Apps
                   Script » (src=inbox) ET la voie IMAP/pêche (src=mailcheck). */
                if (!in_array($e['src'] ?? '', ['inbox', 'mailcheck'], true)) continue;
                $ts = strtotime($e['date'] ?? '') ?: strtotime($r->updated_at);
                $events[] = ['t' => $ts, 'ico' => $m[0], 'lbl' => $m[1], 'col' => $m[2], 'name' => $name, 'objet' => (string) ($e['objet'] ?? ''), 'uid' => $uid];
            }
        }
    }
    /* Photos récentes de locataires (14 j). */
    $since = date('Y-m-d H:i:s', current_time('timestamp') - 14 * DAY_IN_SECONDS);
    $photos = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID, p.post_date, pm.meta_value AS uid FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_lfi_tenant_user_id'
         WHERE p.post_type='attachment' AND p.post_date >= %s ORDER BY p.post_date DESC LIMIT 30", $since)) ?: [];
    foreach ($photos as $ph) {
        $u = get_userdata((int) $ph->uid);
        if (!$u || (function_exists('lfi_nct_uid_in_scope') && !lfi_nct_uid_in_scope((int) $ph->uid))) continue;
        $events[] = ['t' => strtotime($ph->post_date), 'ico' => '📸', 'lbl' => 'Photo envoyée', 'col' => '#8a6d1f', 'name' => $u->display_name, 'objet' => '', 'uid' => (int) $ph->uid];
    }

    /* Anti-doublon d'AFFICHAGE : un même événement (même personne + même type +
       même objet + même jour) n'apparaît qu'une fois dans le fil. */
    $ev_seen = []; $events = array_values(array_filter($events, function ($e) use (&$ev_seen) {
        $k = (int) ($e['uid'] ?? 0) . '|' . ($e['lbl'] ?? '') . '|' . mb_strtolower((string) ($e['objet'] ?? '')) . '|' . wp_date('Y-m-d', (int) ($e['t'] ?? 0));
        if (isset($ev_seen[$k])) return false; $ev_seen[$k] = 1; return true;
    }));
    usort($events, function ($a, $b) { return $b['t'] - $a['t']; });
    $unmatched = function_exists('lfi_nct_inbox_unmatched') ? count(lfi_nct_inbox_unmatched()) : 0;
    $new_count = 0; foreach ($events as $e) { if ($e['t'] > $seen) $new_count++; }
    $new_total = $new_count + $unmatched;

    /* Compte-rendu d'une pêche manuelle (bandeau éphémère). */
    if (function_exists('lfi_nct_mailcheck_peche_flash')) echo lfi_nct_mailcheck_peche_flash();

    /* 🔔 ALERTE ÉPHÉMÈRE : dès qu'une nouveauté arrive, un bandeau saillant.
       Et le bouton « pêcher maintenant » reste TOUJOURS là pour synchroniser à
       la demande — le check automatique tourne en plus, tout seul. */
    $btn = function_exists('lfi_nct_mailcheck_run_button') ? lfi_nct_mailcheck_run_button('🎣 Pêcher les emails maintenant') : '';
    if ($new_total > 0) {
        /* Résumé COHÉRENT : on n'affiche que les parts non nulles (plus de
           « 0 email/photo » à côté d'emails visibles). */
        $bits = [];
        if ($new_count > 0) $bits[] = (int) $new_count . ' nouvel email/photo';
        if ($unmatched > 0) $bits[] = (int) $unmatched . ' à rattacher';
        $lead = ($new_count > 0) ? ($new_count . ' nouveauté' . ($new_count > 1 ? 's' : '')) : ($unmatched . ' à rattacher');
        echo '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;background:#fdeef0;border:2px solid #c8102e;border-radius:12px;padding:11px 14px;margin-bottom:12px">';
        echo '<div style="font-weight:800;color:#c8102e">🔔 ' . esc_html($lead) . ($bits ? ' <span style="font-weight:600;color:#a33">— ' . esc_html(implode(' · ', $bits)) . '</span>' : '') . '</div>';
        echo '<div>' . $btn . '</div></div>';
    } else {
        echo '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;background:#f4f7fb;border:1px solid #d6e2f0;border-radius:12px;padding:9px 14px;margin-bottom:12px">';
        echo '<div style="color:#456;font-weight:600">📬 Emails à jour</div><div>' . $btn . '</div></div>';
    }

    if (empty($events) && $unmatched === 0) return; /* le bandeau + le bouton ci-dessus restent affichés */

    $open = ($new_count > 0 || $unmatched > 0) ? ' open' : '';
    echo '<details class="lfi-app-card" style="border:2px solid #0066a3;background:#f2f8fd;margin-bottom:14px"' . $open . '>';
    echo '<summary style="cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:8px;font-weight:800;color:#0b3d91">';
    echo '<span>🆕 Quoi de neuf côté locataires</span>';
    $sumbadge = [];
    if ($new_count)  $sumbadge[] = (int) $new_count . ' nouveau' . ($new_count > 1 ? 'x' : '');
    if ($unmatched)  $sumbadge[] = (int) $unmatched . ' à rattacher';
    echo '<span class="badge" style="background:' . ($sumbadge ? '#c8102e' : '#8aa') . ';color:#fff">' . esc_html($sumbadge ? implode(' · ', $sumbadge) : 'à jour') . '</span>';
    echo '</summary>';

    if ($unmatched > 0) {
        echo '<a href="' . esc_url(lfi_nct_app_url('inbox-import')) . '" style="text-decoration:none;color:inherit;display:block;margin:6px 0"><div style="background:#fff3cd;border-radius:8px;padding:9px 11px;font-weight:700;color:#8a6d1f">🧩 ' . (int) $unmatched . ' email(s) à rattacher — dis au robot de qui il s\'agit →</div></a>';
    }

    if (!empty($events)) {
        echo '<div style="display:flex;flex-direction:column;gap:5px;margin-top:4px">';
        foreach (array_slice($events, 0, 8) as $e) {
            $is_new = $e['t'] > $seen;
            /* TOUT email (reçu OU envoyé) → écran épuré « Répondre » : on voit
               l'email en entier + le fil + la réponse. Seules les PHOTOS → dossier. */
            $is_mail = in_array($e['lbl'] ?? '', ['Email reçu', 'Email envoyé'], true);
            $url = $is_mail
                ? lfi_nct_app_url('repondre', ['uid' => $e['uid']])
                : lfi_nct_app_url('dossier', ['uid' => $e['uid']]);
            echo '<a href="' . esc_url($url) . '" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:9px;background:#fff;border:1px solid ' . ($is_new ? '#c8102e' : '#e0e0e0') . ';border-radius:8px;padding:8px 10px">';
            echo '<span style="font-size:1.15em">' . $e['ico'] . '</span>';
            echo '<span style="flex:1"><strong>' . esc_html($e['name']) . '</strong> <span style="color:#888;font-size:.9em">— ' . esc_html($e['lbl']) . ($e['objet'] ? ' · ' . esc_html(mb_substr($e['objet'], 0, 40)) : '') . '</span></span>';
            if ($is_new) echo '<span style="background:#c8102e;color:#fff;font-size:.64em;font-weight:800;padding:1px 6px;border-radius:8px">NEW</span>';
            echo '<span style="color:#0066a3;font-weight:800">→</span></a>';
        }
        echo '</div>';
        if (count($events) > 8) echo '<div style="font-size:.8em;color:#888;margin-top:4px">… et ' . (count($events) - 8) . ' autre(s) — ouvre chaque dossier pour tout voir.</div>';
    }

    /* Barre d'outils. Le lien « à rattacher » ne concerne QUE les emails NON
       reconnus (les emails ci-dessus, eux, sont déjà rangés dans leur dossier —
       clique dessus pour les ouvrir). */
    $reset_post = admin_url('admin-post.php');
    echo '<div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:center;margin-top:10px;border-top:1px solid #dce7f2;padding-top:8px">';
    echo '<a href="' . esc_url(lfi_nct_app_url('inbox-import')) . '" style="font-size:.84em;color:#8a6d1f;font-weight:700;text-decoration:none">🧩 Emails « à rattacher » →</a>';
    echo '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
    if (!empty($events)) echo '<a href="' . esc_url(lfi_nct_app_url('', ['locnews_seen' => 1])) . '" style="font-size:.82em;color:#666">✓ Tout marquer vu</a>';
    echo '<form method="post" action="' . esc_url($reset_post) . '" style="margin:0" onsubmit="return confirm(\'PURGER tous les emails importés (boîte + tous les dossiers) et leurs pièces jointes email ? Les enquêtes et chronologies reconstruites ne bougent pas.\');">'
        . wp_nonce_field('lfi_nct_emails_reset', '_wpnonce', true, false)
        . '<input type="hidden" name="action" value="lfi_nct_emails_reset">'
        . '<button type="submit" style="background:none;border:none;color:#c8102e;font-size:.82em;font-weight:700;cursor:pointer;padding:0">🧹 Purger tous les emails</button></form>';
    echo '</div></div>';
    echo '</details>';

    /* Marquer vu si demandé. */
    if (!empty($_GET['locnews_seen'])) update_user_meta(get_current_user_id(), 'lfi_nct_locnews_seen', current_time('timestamp'));
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
        $row = ['uid' => (int) $u->ID, 'name' => $u->display_name, 'idx' => $idx, 'text' => (string) $steps[$idx]['text'], 'total' => count($steps), 'donen' => $done_n, 'echeance' => (string) ($steps[$idx]['echeance'] ?? '')];
        if ($who === 'tenant') $waiting[] = $row; else $mine[] = $row;
    }
    if (empty($mine) && empty($waiting)) return;

    /* Tri « du plus urgent au moins urgent » : d'abord ceux qui ont une échéance
       (la plus proche / dépassée en tête), puis ceux sans échéance. */
    $urg = function ($a, $b) {
        $ea = trim((string) $a['echeance']); $eb = trim((string) $b['echeance']);
        if ($ea === '' && $eb === '') return 0;
        if ($ea === '') return 1;   /* sans échéance → après */
        if ($eb === '') return -1;
        return strtotime($ea) <=> strtotime($eb); /* échéance la plus proche d'abord */
    };
    usort($mine, $urg);
    usort($waiting, $urg);

    if (!empty($mine)) {
        /* Accordéon (ouvert) : la liste est BORNÉE dans un cadre déroulant — plus
           de « liste interminable » qui prend 18 écrans. */
        echo '<details open class="lfi-app-card" style="border:2px solid #0066a3;border-radius:14px;padding:0;margin:0 0 14px;overflow:hidden">';
        echo '<summary style="cursor:pointer;list-style:none;padding:12px 14px;font-weight:900;color:#0066a3;display:flex;justify-content:space-between;align-items:center"><span>📋 Mes actions à faire (' . count($mine) . ')</span><span style="font-size:1.1em">▾</span></summary>';
        echo '<div style="max-height:56vh;overflow:auto;padding:0 12px 12px;display:flex;flex-direction:column;gap:8px">';
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
        echo '</div></details>';
    }
    if (!empty($waiting)) {
        /* Accordéon REPLIÉ par défaut : secondaire (ça avance tout seul). */
        echo '<details class="lfi-app-card" style="border:1px solid #e6c65a;background:#fffbf0;border-radius:14px;padding:0;margin:0 0 14px;overflow:hidden">';
        echo '<summary style="cursor:pointer;list-style:none;padding:12px 14px;font-weight:800;color:#b8860b;display:flex;justify-content:space-between;align-items:center"><span>⏳ En attente du locataire (' . count($waiting) . ')</span><span style="font-size:1.1em">▾</span></summary>';
        echo '<div style="max-height:50vh;overflow:auto;padding:0 12px 12px">';
        echo '<div class="lfi-app-help" style="margin:0 0 6px"><small>Ces personnes ont leur espace : elles remplissent leur dossier. Rien à faire de ton côté — ça avancera tout seul. Tu peux relancer si ça traîne.</small></div>';
        echo '<div style="display:flex;flex-direction:column;gap:6px">';
        foreach ($waiting as $it) {
            $open_url = lfi_nct_app_url('dossier', ['uid' => $it['uid']]);
            echo '<a href="' . esc_url($open_url) . '" style="text-decoration:none;color:inherit;display:flex;justify-content:space-between;align-items:center;background:#fff;border:1px solid #eee;border-radius:8px;padding:8px 10px">';
            echo '<span><strong>' . esc_html($it['name']) . '</strong> <span style="font-size:.82em;color:#888">— ' . esc_html($it['text']) . '</span></span>';
            echo '<span style="color:#b8860b;font-weight:700;white-space:nowrap">Relancer →</span></a>';
        }
        echo '</div></div></details>';
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
    /* Objectif = relogement → l'étape « déménagement » ne passe pas que par NMH :
       demande unique + Action Logement + DALO (relogement d'urgence). */
    if ($obj_key === 'relogement') {
        if (function_exists('lfi_nct_relogement_ensure_steps')) lfi_nct_relogement_ensure_steps($u->ID);
        echo '<a class="btn-primary" style="background:#0066a3;display:block;text-align:center;margin-top:8px;box-sizing:border-box" href="' . esc_url(lfi_nct_app_url('relogement', ['uid' => $u->ID])) . '">🏠 Relogement / déménagement (demande unique + DALO urgence)</a>';
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

/** Clé stable d'une étape (pour rattacher ses pièces). */
function lfi_nct_step_key($text, $idx) {
    return 'st' . substr(md5((string) $text . '|' . (int) $idx), 0, 12);
}
/** Pièces (photos/PDF) versées dans une étape — triées par DATE (prise de vue). */
function lfi_nct_step_pieces($uid, $skey) {
    if ($skey === '') return [];
    $q = get_posts([
        'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => 50,
        'meta_query' => [
            ['key' => '_lfi_tenant_user_id', 'value' => (int) $uid],
            ['key' => '_lfi_step', 'value' => $skey],
        ],
    ]);
    usort($q, function ($a, $b) {
        $ta = (int) get_post_meta($a->ID, '_lfi_capture_ts', true) ?: strtotime($a->post_date);
        $tb = (int) get_post_meta($b->ID, '_lfi_capture_ts', true) ?: strtotime($b->post_date);
        return $ta <=> $tb;
    });
    return $q;
}

/** 🤖 ROBOT : analyse le nom/type d'une pièce → catégorie (PV, expertise,
 *  courrier, médical, facture, photo…). */
function lfi_nct_piece_categorize($filename, $mime = '', $context = '') {
    $h = mb_strtolower((string) $filename . ' ' . (string) $context);
    if (preg_match('/(pv|constat|schs|sihs|insalub|proc.s.verbal|hygi)/u', $h))                  return ['cat' => 'pv',        'label' => '📋 PV / constat'];
    if (preg_match('/(expert|meurgey|entomolog|mus.um)/u', $h))                                    return ['cat' => 'expertise', 'label' => '🔬 Expertise'];
    if (preg_match('/(mise.?en.?demeure|lrar|recommand|courrier|r.ponse|nmh|bailleur|morineau)/u', $h)) return ['cat' => 'courrier',  'label' => '✉️ Courrier'];
    if (preg_match('/(certificat|m.dic|ordonnance|docteur|cmi|sant.)/u', $h))                      return ['cat' => 'medical',   'label' => '🩺 Certificat médical'];
    if (preg_match('/(facture|devis|frais|note.?d.honoraire)/u', $h))                              return ['cat' => 'facture',   'label' => '💶 Facture / devis'];
    if (strpos((string) $mime, 'image/') === 0 || preg_match('/\.(jpg|jpeg|png|heic|heif|webp|gif)$/u', $h)) return ['cat' => 'photo', 'label' => '📷 Photo preuve'];
    if (strpos((string) $mime, 'pdf') !== false || preg_match('/\.pdf$/u', $h))                    return ['cat' => 'doc',       'label' => '📄 Document'];
    return ['cat' => 'autre', 'label' => '📎 Pièce'];
}
/** 🤖 ROBOT : trouve l'ÉTAPE du parcours qui correspond à une catégorie de pièce
 *  → range la pièce dans la bonne étape automatiquement. Renvoie la clé d'étape. */
function lfi_nct_piece_autostep($uid, $cat) {
    $map = [
        'pv'        => ['constat', 'schs', 'hygi', 'insalub', 'pv', 'référé', 'refere'],
        'expertise' => ['expert', 'constat', 'preuve'],
        'courrier'  => ['mise en demeure', 'relance', 'nmh', 'courrier', 'amiable', 'réponse', 'reponse'],
        'medical'   => ['certificat', 'médic', 'medic', 'santé', 'sante', 'préjudice', 'prejudice'],
        'photo'     => ['constitu', 'preuve', 'photo', 'pièce', 'piece', 'constat'],
        'facture'   => ['frais', 'préjudice', 'prejudice', 'chiffr', 'facture'],
    ];
    $keys = $map[$cat] ?? [];
    if (!$keys) return '';
    $steps = get_user_meta((int) $uid, 'lfi_nct_suivi_steps', true);
    if (!is_array($steps)) return '';
    foreach ($steps as $i => $s) {
        $t = mb_strtolower((string) ($s['text'] ?? ''));
        foreach ($keys as $k) if ($k !== '' && mb_strpos($t, $k) !== false) return lfi_nct_step_key($s['text'] ?? '', $i);
    }
    return '';
}

/* Barre des DOSSIERS D'INCIDENT (épisodes) : un même locataire peut avoir
 *  plusieurs troubles distincts (infestation 2020 / 2024 / 2025). Chacun a son
 *  parcours. On sélectionne l'épisode actif, on en crée un nouveau, on clôt son
 *  volet urgence (le juridique, lui, reste global). */
function lfi_nct_dossier_render_episodes_bar($u) {
    if (!function_exists('lfi_nct_episodes_get')) return;
    $uid = (int) $u->ID;
    $episodes = lfi_nct_episodes_get($uid);
    if (empty($episodes)) return;
    $active = function_exists('lfi_nct_episode_active_id') ? lfi_nct_episode_active_id($uid) : 0;
    $types  = function_exists('lfi_nct_episode_types') ? lfi_nct_episode_types() : [];
    $ap_nonce = wp_create_nonce('lfi_app_episode');

    echo '<details open style="margin:14px 0;background:#fff;border-radius:12px;border:1px solid #eee;overflow:hidden">';
    echo '<summary style="cursor:pointer;padding:12px 15px;font-weight:800;color:#0b3d91;list-style:none;display:flex;justify-content:space-between;align-items:center"><span>🗂️ Dossiers d\'incident <span style="background:#0b3d91;color:#fff;font-size:.7em;padding:1px 7px;border-radius:10px;vertical-align:middle">' . count($episodes) . '</span></span><span style="font-size:1.2em">▾</span></summary>';
    echo '<div style="padding:0 15px 14px">';
    echo '<div class="lfi-app-help" style="margin:6px 0"><small>Chaque trouble distinct = <strong>un dossier séparé et cloisonné</strong>, avec son propre parcours (urgence → amiable → clôture). Le dossier <strong>juridique reste global</strong> : on additionne le préjudice de tous pour une indemnité globale.</small></div>';

    /* Onglets des épisodes. */
    echo '<div style="display:flex;flex-wrap:wrap;gap:7px;margin:8px 0">';
    foreach ($episodes as $e) {
        $eid = (int) ($e['id'] ?? 0);
        $is_active = ($eid === $active);
        $ic = $types[$e['type'] ?? ''][0] ?? '📁';
        $clos = !empty($e['clos_urgence']);
        $pend = function_exists('lfi_nct_episode_besoins_pending') ? lfi_nct_episode_besoins_pending($e) : 0;
        $prog = function_exists('lfi_nct_episode_progress') ? lfi_nct_episode_progress($e) : ['done' => 0, 'total' => 0];
        echo '<form method="post" style="margin:0">' . wp_nonce_field('lfi_app_episode', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_app_episode" value="1"><input type="hidden" name="ep_action" value="switch"><input type="hidden" name="ep_id" value="' . $eid . '">';
        echo '<button type="submit" style="cursor:pointer;border-radius:20px;padding:6px 12px;font-size:.82em;font-weight:700;border:2px solid ' . ($is_active ? '#0b3d91' : '#dfe6f0') . ';background:' . ($is_active ? '#0b3d91' : '#fff') . ';color:' . ($is_active ? '#fff' : '#333') . '">';
        echo $ic . ' ' . esc_html($e['titre'] ?? 'Dossier') . ' <span style="opacity:.75">' . (int) $prog['done'] . '/' . (int) $prog['total'] . '</span>';
        if ($clos) echo ' ✅';
        if ($pend) echo ' <span style="color:' . ($is_active ? '#ffd' : '#c8102e') . '">⚠' . (int) $pend . '</span>';
        echo '</button></form>';
    }
    echo '</div>';

    /* Épisode actif : renommer + clore/rouvrir urgence + supprimer. */
    $cur = null; foreach ($episodes as $e) if ((int) ($e['id'] ?? 0) === $active) { $cur = $e; break; }
    if ($cur) {
        $clos = !empty($cur['clos_urgence']);
        echo '<div style="background:#f6f8fb;border:1px solid #dfe6f0;border-radius:10px;padding:10px 12px;margin-top:4px">';
        echo '<div style="font-size:.78em;color:#0b3d91;font-weight:800;margin-bottom:5px">✎ Dossier sélectionné</div>';
        echo '<form method="post" style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;margin:0">' . wp_nonce_field('lfi_app_episode', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_app_episode" value="1"><input type="hidden" name="ep_action" value="rename"><input type="hidden" name="ep_id" value="' . (int) $active . '">';
        echo '<input type="text" name="ep_titre" value="' . esc_attr($cur['titre'] ?? '') . '" style="font-size:.82em;flex:1;min-width:140px">';
        echo '<select name="ep_type" style="font-size:.8em">';
        foreach ($types as $tk => $tv) echo '<option value="' . esc_attr($tk) . '"' . (($cur['type'] ?? '') === $tk ? ' selected' : '') . '>' . $tv[0] . ' ' . esc_html($tv[1]) . '</option>';
        echo '</select>';
        echo '<button type="submit" class="btn-ghost" style="font-size:.8em">💾</button></form>';
        echo '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:7px">';
        /* Clore / rouvrir le volet urgence. */
        echo '<form method="post" style="margin:0">' . wp_nonce_field('lfi_app_episode', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_app_episode" value="1"><input type="hidden" name="ep_action" value="' . ($clos ? 'reopen' : 'close') . '"><input type="hidden" name="ep_id" value="' . (int) $active . '">';
        echo '<button type="submit" class="btn-ghost" style="font-size:.8em;' . ($clos ? '' : 'color:#186a3b;border-color:#186a3b') . '">' . ($clos ? '↩ Rouvrir l\'urgence' : '✅ Clore l\'urgence') . '</button></form>';
        echo '<form method="post" onsubmit="return confirm(\'Supprimer ce dossier d\\\'incident ? (les pièces déjà versées restent dans les pièces du locataire)\')" style="margin:0">' . wp_nonce_field('lfi_app_episode', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_app_episode" value="1"><input type="hidden" name="ep_action" value="delete"><input type="hidden" name="ep_id" value="' . (int) $active . '">';
        echo '<button type="submit" class="btn-ghost" style="font-size:.75em;color:#c8102e">🗑 Supprimer le dossier</button></form>';
        echo '</div>';
        if ($clos) echo '<div style="font-size:.78em;color:#186a3b;margin-top:5px">✅ Urgence close le ' . esc_html(wp_date('j M Y', strtotime($cur['clos_date'] ?: current_time('Y-m-d')))) . ' — le préjudice reste compté dans le dossier juridique.</div>';

        /* ⚖️ DOSSIER JURIDIQUE (lignée du trouble). Les incidents de MÊME nature
           sont regroupés (préjudice cumulé = indemnité globale) ; un trouble
           DIFFÉRENT se sépare. */
        $grp = function_exists('lfi_nct_episode_groupe') ? lfi_nct_episode_groupe($cur) : (int) $active;
        $glabel = function_exists('lfi_nct_episode_group_label') ? lfi_nct_episode_group_label($uid, $grp) : '';
        $gcount = function_exists('lfi_nct_episode_group_count') ? lfi_nct_episode_group_count($uid, $grp) : 1;
        echo '<div style="margin-top:8px;border-top:1px dashed #dfe6f0;padding-top:8px">';
        echo '<div style="font-size:.8em;font-weight:800;color:#6b3fa0">⚖️ Dossier juridique : ' . esc_html($glabel) . ($gcount > 1 ? ' <span style="background:#efe6fb;color:#6b3fa0;padding:0 6px;border-radius:8px">' . (int) $gcount . ' incidents cumulés</span>' : ' <span style="color:#999;font-weight:600">(seul)</span>') . '</div>';
        echo '<div style="font-size:.75em;color:#888;margin:2px 0 5px">Même trouble qui revient = même dossier juridique (préjudice cumulé). Trouble différent = à séparer.</div>';
        echo '<div style="display:flex;gap:6px;flex-wrap:wrap">';
        /* Rattacher au juridique d'un autre incident. */
        $autres = array_values(array_filter($episodes, function ($e) use ($active) { return (int) ($e['id'] ?? 0) !== $active; }));
        if ($autres) {
            echo '<form method="post" style="display:flex;gap:4px;align-items:center;margin:0">' . wp_nonce_field('lfi_app_episode', '_wpnonce', true, false);
            echo '<input type="hidden" name="lfi_app_episode" value="1"><input type="hidden" name="ep_action" value="grp_link"><input type="hidden" name="ep_id" value="' . (int) $active . '">';
            echo '<select name="ep_group_to" style="font-size:.76em;max-width:150px">';
            foreach ($autres as $ae) echo '<option value="' . (int) ($ae['id'] ?? 0) . '">' . esc_html($ae['titre'] ?? 'Dossier') . '</option>';
            echo '</select><button type="submit" class="btn-ghost" style="font-size:.76em">🔗 Même juridique</button></form>';
        }
        if ($gcount > 1) {
            echo '<form method="post" style="margin:0">' . wp_nonce_field('lfi_app_episode', '_wpnonce', true, false);
            echo '<input type="hidden" name="lfi_app_episode" value="1"><input type="hidden" name="ep_action" value="grp_sep"><input type="hidden" name="ep_id" value="' . (int) $active . '">';
            echo '<button type="submit" class="btn-ghost" style="font-size:.76em;color:#c8102e">✂️ Séparer (juridique distinct)</button></form>';
        }
        echo '</div></div>';

        /* 💶 PRÉJUDICE de cet incident (postes simples) + total. */
        $prej = (isset($cur['prejudice']) && is_array($cur['prejudice'])) ? $cur['prejudice'] : [];
        $eur = function_exists('lfi_nct_episode_eur') ? 'lfi_nct_episode_eur' : function ($v) { return number_format((float) $v, 0, ',', ' ') . ' €'; };
        echo '<div style="margin-top:8px;border-top:1px dashed #dfe6f0;padding-top:8px">';
        echo '<div style="font-size:.8em;font-weight:800;color:#186a3b">💶 Préjudice de cet incident' . (($t = lfi_nct_episode_prej_total($cur)) > 0 ? ' — <strong>' . $eur($t) . '</strong>' : '') . '</div>';
        if ($prej) {
            echo '<div style="margin:5px 0">';
            foreach ($prej as $pi => $p) {
                echo '<div style="display:flex;align-items:center;gap:6px;font-size:.82em;background:#f1f8f2;border-radius:6px;padding:3px 7px;margin-bottom:3px">';
                echo '<span style="flex:1">' . esc_html($p['label'] ?? '') . ($p['date'] ?? '' ? ' <span style="color:#888">· ' . esc_html($p['date']) . '</span>' : '') . '</span>';
                echo '<strong style="color:#186a3b">' . $eur($p['montant'] ?? 0) . '</strong>';
                echo '<form method="post" style="margin:0">' . wp_nonce_field('lfi_app_episode', '_wpnonce', true, false) . '<input type="hidden" name="lfi_app_episode" value="1"><input type="hidden" name="ep_action" value="prej_del"><input type="hidden" name="ep_id" value="' . (int) $active . '"><input type="hidden" name="prej_idx" value="' . (int) $pi . '"><button type="submit" class="btn-ghost" style="font-size:.7em;padding:0 5px;color:#c8102e">✕</button></form>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '<form method="post" style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;margin-top:3px">' . wp_nonce_field('lfi_app_episode', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_app_episode" value="1"><input type="hidden" name="ep_action" value="prej_add"><input type="hidden" name="ep_id" value="' . (int) $active . '">';
        echo '<input type="text" name="prej_label" placeholder="Poste (ex : nuits gâchées)" style="font-size:.78em;flex:1;min-width:120px">';
        echo '<input type="number" step="0.01" min="0" name="prej_montant" placeholder="€" style="font-size:.78em;width:80px">';
        echo '<button type="submit" class="btn-ghost" style="font-size:.78em">+ Ajouter</button></form>';
        echo '<div style="font-size:.72em;color:#888;margin-top:2px">Chiffrage détaillé (15 postes) : outil « 💶 Préjudice ». Ici = postes cumulés pour l\'indemnité globale.</div>';
        echo '</div>';

        /* ⚖️ INDEMNITÉ GLOBALE du dossier juridique = somme de tous les incidents groupés. */
        if ($gcount > 1 && function_exists('lfi_nct_episode_group_members')) {
            $gtot = lfi_nct_episode_group_prej_total($uid, $grp);
            echo '<div style="margin-top:8px;background:#f3eefb;border:1px solid #d9c9f0;border-radius:10px;padding:10px 12px">';
            echo '<div style="font-weight:900;color:#6b3fa0">⚖️ Indemnité globale demandée — <strong>' . $eur($gtot) . '</strong></div>';
            echo '<div style="font-size:.78em;color:#7a5f9a;margin-top:2px">Somme du préjudice de tous les incidents de ce dossier juridique (' . (int) $gcount . ') :</div>';
            echo '<div style="margin-top:4px">';
            foreach (lfi_nct_episode_group_members($uid, $grp) as $ge) {
                echo '<div style="display:flex;justify-content:space-between;font-size:.8em;color:#4a3a5f;padding:1px 0"><span>' . esc_html($ge['titre'] ?? 'Incident') . '</span><span><strong>' . $eur(lfi_nct_episode_prej_total($ge)) . '</strong></span></div>';
            }
            echo '</div></div>';
        }
    }

    /* Nouvel incident. */
    echo '<form method="post" style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;margin-top:8px;border-top:1px dashed #dfe6f0;padding-top:9px">' . wp_nonce_field('lfi_app_episode', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_app_episode" value="1"><input type="hidden" name="ep_action" value="create">';
    echo '<span style="font-size:.82em;font-weight:800;color:#c8102e">＋ Nouvel incident :</span>';
    echo '<select name="ep_type" style="font-size:.8em">';
    foreach ($types as $tk => $tv) echo '<option value="' . esc_attr($tk) . '">' . $tv[0] . ' ' . esc_html($tv[1]) . '</option>';
    echo '</select>';
    echo '<input type="text" name="ep_titre" placeholder="Titre (ex : Infestation 2025)" style="font-size:.82em;flex:1;min-width:130px">';
    echo '<button type="submit" class="btn-primary" style="font-size:.8em;background:#c8102e">Créer</button></form>';

    echo '</div></details>';
}

function lfi_nct_dossier_render_parcours($u) {
    /* Barre des dossiers d'incident (épisodes) : sélectionner / créer / clore. */
    if (function_exists('lfi_nct_dossier_render_episodes_bar')) lfi_nct_dossier_render_episodes_bar($u);

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
        if (function_exists('lfi_nct_episode_save_active')) lfi_nct_episode_save_active($u->ID);
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

    /* 📎 Pièces à demander au locataire (choisies par l'architecte + robot avocat).
       Bouton bien visible AVANT le parcours : on invite, on relance, on suit. */
    if (function_exists('lfi_nct_pieces_progress')) {
        $pp = lfi_nct_pieces_progress($u->ID);
        $pc = $pp['complete'];
        echo '<a href="' . esc_url(lfi_nct_app_url('pieces', ['uid' => $u->ID])) . '" style="text-decoration:none;color:inherit;display:block;margin:12px 0 0">';
        echo '<div style="padding:12px 14px;border-radius:12px;background:' . ($pc ? '#e8f5ea' : '#fff8e6') . ';border:2px solid ' . ($pc ? '#186a3b' : '#bd8600') . ';display:flex;align-items:center;gap:12px">';
        echo '<div style="font-size:1.6em">📎</div>';
        echo '<div style="flex:1"><div style="font-weight:800;color:' . ($pc ? '#186a3b' : '#bd8600') . '">Pièces à demander au locataire</div>';
        echo '<div style="font-size:.85em;color:#555">' . (int) $pp['received'] . '/' . (int) $pp['mandatory'] . ' obligatoires reçues' . ($pc ? ' · ✅ conciliation débloquée' : ' · inviter / relancer') . '</div></div>';
        echo '<div style="font-weight:800;color:' . ($pc ? '#186a3b' : '#bd8600') . '">→</div>';
        echo '</div></a>';
    }

    echo '<details open id="parcours" style="margin:16px 0;background:#fff;border-radius:12px;border:1px solid #eee;overflow:hidden;scroll-margin-top:70px">';
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

    /* Étapes = ACCORDÉONS. Chaque étape s'ouvre (hamburger) : on y dépose des
       pièces/photos → le robot les range par date et COCHE/CLÔT l'étape tout
       seul. On garde la main : rouvrir, retirer une pièce, supprimer l'étape. */
    if (empty($steps)) {
        echo '<div class="lfi-app-help" style="margin:6px 0">Aucune étape pour l\'instant. Clique « ✨ Générer le parcours automatique » ci-dessus, ou ajoute une action à la main.</div>';
    } else {
        echo '<div class="lfi-app-help" style="margin:4px 0"><small>Ouvre une étape → <strong>dépose tes preuves</strong> (photos punaises, PV, courriers…). Dès qu\'une pièce est versée, l\'étape se <strong>coche automatiquement</strong>. Tu peux rouvrir, retirer une pièce, ré-en déposer.</small></div>';
        foreach ($steps as $idx => $s) {
            if (!empty($s['skipped'])) {
                echo '<div style="padding:8px 10px;border-radius:8px;margin-bottom:5px;background:#f4f4f4;border-left:3px solid #bbb"><span style="text-decoration:line-through;color:#999">⏭️ ' . esc_html($s['text'] ?? '') . '</span> <span style="font-size:.78em;color:#aaa">— inutile (urgence gagnée avant)</span></div>';
                continue;
            }
            $done  = !empty($s['done']);
            $who   = $s['who'] ?? 'admin';
            $badge = $who === 'tenant' ? '<span style="background:#fff3cd;color:#8a6d1f;font-size:.66em;font-weight:700;padding:1px 6px;border-radius:8px">🏠 locataire</span>' : '<span style="background:#e7f0fb;color:#0066a3;font-size:.66em;font-weight:700;padding:1px 6px;border-radius:8px">👤 toi</span>';
            $skey  = lfi_nct_step_key($s['text'] ?? '', $idx);
            $pieces = function_exists('lfi_nct_step_pieces') ? lfi_nct_step_pieces($u->ID, $skey) : [];
            $np = count($pieces);
            $col = $done ? '#186a3b' : ($who === 'tenant' ? '#d39e00' : '#c8102e');
            echo '<details style="margin-bottom:6px;background:' . ($done ? '#f0f8f1' : '#fff') . ';border:1px solid #eee;border-left:4px solid ' . $col . ';border-radius:8px;overflow:hidden"' . ($done ? '' : ' open') . '>';
            echo '<summary style="cursor:pointer;list-style:none;padding:9px 11px;font-weight:600;color:' . ($done ? '#5a7a5f' : '#1a1a1a') . '">';
            echo '<input type="checkbox" form="lfi-step-bulk" name="step_sel[]" value="' . (int) $idx . '" onclick="event.stopPropagation()" title="Sélectionner pour suppression multiple" style="margin-right:7px;vertical-align:middle;width:17px;height:17px">';
            echo ($done ? '✅ ' : '📂 ') . esc_html($s['text']) . ' ' . $badge . ($np ? ' <span style="color:#0066a3;font-size:.8em;font-weight:700">· 📎 ' . $np . '</span>' : '') . '</summary>';
            echo '<div style="padding:2px 11px 11px">';
            if (!empty($s['echeance'])) {
                $late = (!$done && strtotime($s['echeance']) < strtotime(current_time('Y-m-d')));
                echo '<div style="font-size:.82em;color:' . ($late ? '#c8102e' : '#888') . ';margin-bottom:6px">' . ($late ? '⚠ en retard — ' : '🗓 ') . 'échéance ' . esc_html(wp_date('j M Y', strtotime($s['echeance']))) . '</div>';
            }
            /* Pièces versées (triées par date). */
            if ($pieces) {
                echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px">';
                foreach ($pieces as $p) {
                    $isimg = strpos((string) $p->post_mime_type, 'image/') === 0;
                    $th = $isimg ? (wp_get_attachment_image_url($p->ID, 'thumbnail') ?: wp_get_attachment_url($p->ID)) : '';
                    $lab = function_exists('lfi_nct_photo_capture_label') ? lfi_nct_photo_capture_label($p->ID) : '';
                    echo '<div style="text-align:center;width:78px">';
                    echo '<a href="' . esc_url(wp_get_attachment_url($p->ID)) . '" target="_blank" rel="noopener">';
                    echo $th ? '<img src="' . esc_url($th) . '" style="width:74px;height:74px;object-fit:cover;border-radius:6px;border:1px solid #ccc">' : '<div style="width:74px;height:74px;border-radius:6px;border:1px solid #ccc;display:flex;align-items:center;justify-content:center;font-size:1.6em;background:#f4f4f4">📄</div>';
                    echo '</a>';
                    echo '<div style="font-size:.62em;color:#888;line-height:1.1;margin-top:1px">' . esc_html($lab) . '</div>';
                    echo '<form method="post" onsubmit="return confirm(\'Retirer cette pièce ?\')" style="margin:0">' . wp_nonce_field('lfi_app_step_piece', '_wpnonce', true, false) . '<input type="hidden" name="lfi_app_step_piece_del" value="1"><input type="hidden" name="att_id" value="' . (int) $p->ID . '"><button type="submit" class="btn-ghost" style="font-size:.6em;padding:1px 5px">🗑</button></form>';
                    echo '</div>';
                }
                echo '</div>';
            }
            /* Dépôt d'une pièce → coche auto. */
            echo '<form method="post" enctype="multipart/form-data" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">' . wp_nonce_field('lfi_app_step_piece', '_wpnonce', true, false);
            echo '<input type="hidden" name="lfi_app_step_piece" value="1"><input type="hidden" name="step_key" value="' . esc_attr($skey) . '"><input type="hidden" name="step_idx" value="' . (int) $idx . '">';
            echo '<input type="file" name="piece" accept="image/*,application/pdf" capture="environment" required style="font-size:.8em;max-width:190px">';
            echo '<button type="submit" class="btn-primary" style="background:#0066a3;font-size:.8em">📎 Déposer' . ($done ? '' : ' + clore') . '</button>';
            echo '</form>';
            /* 📖 Explication pédagogique montrée au locataire (règle : on explique
               toujours ce qu'il doit faire). Vide = texte automatique. */
            $auto_ped = function_exists('lfi_nct_step_pedagogie') ? lfi_nct_step_pedagogie($s) : '';
            echo '<div style="margin-top:9px;border-top:1px dashed #e0e0e0;padding-top:8px">';
            echo '<div style="font-size:.8em;font-weight:800;color:#0b3d91">📖 Explication montrée au locataire</div>';
            echo '<form method="post" style="margin:4px 0 0">' . wp_nonce_field('lfi_app_dossier_step', '_wpnonce', true, false);
            echo '<input type="hidden" name="lfi_app_dossier_step" value="1"><input type="hidden" name="step_action" value="explain"><input type="hidden" name="step_idx" value="' . (int) $idx . '">';
            echo '<textarea name="step_explain" rows="2" placeholder="' . esc_attr($auto_ped) . '" style="width:100%;font-size:.82em;border:1px solid #ddd;border-radius:8px;padding:6px">' . esc_textarea((string) ($s['explain'] ?? '')) . '</textarea>';
            echo '<div style="font-size:.72em;color:#888;margin-top:2px">Laisse vide → le locataire voit le texte automatique ci-dessus. Écris pour personnaliser.</div>';
            echo '<button type="submit" class="btn-ghost" style="font-size:.78em;margin-top:3px">💾 Enregistrer l\'explication</button></form>';
            echo '</div>';

            /* 📌 Ce qu'on ATTEND DU LOCATAIRE pour cette étape. Le locataire le
               verra dans « Mon suivi » et pourra fournir chaque élément. */
            $besoins = (isset($s['besoins']) && is_array($s['besoins'])) ? $s['besoins'] : [];
            $btypes  = function_exists('lfi_nct_suivi_besoin_types') ? lfi_nct_suivi_besoin_types() : [];
            $pend_b  = function_exists('lfi_nct_suivi_besoins_pending') ? lfi_nct_suivi_besoins_pending($s) : 0;
            echo '<div style="margin-top:9px;border-top:1px dashed #e0e0e0;padding-top:8px">';
            echo '<div style="font-size:.8em;font-weight:800;color:#8a6d1f">📌 À demander au locataire' . ($pend_b ? ' <span style="background:#d39e00;color:#fff;padding:0 6px;border-radius:8px;font-size:.85em">' . (int) $pend_b . ' en attente</span>' : '') . '</div>';
            if ($besoins) {
                echo '<div style="display:flex;flex-direction:column;gap:3px;margin:5px 0">';
                foreach ($besoins as $bi => $b) {
                    $bt = (string) ($b['type'] ?? 'info'); $ti = $btypes[$bt] ?? ['•', ''];
                    $bdone = !empty($b['done']);
                    echo '<div style="display:flex;align-items:center;gap:6px;font-size:.82em;background:' . ($bdone ? '#f0f8f1' : '#fffaf0') . ';border-radius:6px;padding:3px 7px">';
                    echo '<span style="flex:1;color:' . ($bdone ? '#186a3b' : '#7a5f10') . '">' . $ti[0] . ' ' . esc_html($b['label'] ?? '') . ($bdone ? ' ✓' . (!empty($b['value']) ? ' — <strong>' . esc_html($b['value']) . '</strong>' : ' fourni') : '') . '</span>';
                    echo '<form method="post" style="margin:0">' . wp_nonce_field('lfi_app_dossier_step', '_wpnonce', true, false) . '<input type="hidden" name="lfi_app_dossier_step" value="1"><input type="hidden" name="step_action" value="besoin_del"><input type="hidden" name="step_idx" value="' . (int) $idx . '"><input type="hidden" name="besoin_idx" value="' . (int) $bi . '"><button type="submit" class="btn-ghost" style="font-size:.7em;padding:0 5px;color:#c8102e">✕</button></form>';
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '<form method="post" style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;margin-top:4px">' . wp_nonce_field('lfi_app_dossier_step', '_wpnonce', true, false);
            echo '<input type="hidden" name="lfi_app_dossier_step" value="1"><input type="hidden" name="step_action" value="besoin_add"><input type="hidden" name="step_idx" value="' . (int) $idx . '">';
            echo '<select name="besoin_type" style="font-size:.78em;max-width:130px">';
            foreach ($btypes as $tk => $tv) echo '<option value="' . esc_attr($tk) . '">' . $tv[0] . ' ' . esc_html($tv[1]) . '</option>';
            echo '</select>';
            echo '<input type="text" name="besoin_label" placeholder="Précision (ex : photos des punaises)" style="font-size:.78em;flex:1;min-width:120px">';
            echo '<button type="submit" class="btn-ghost" style="font-size:.78em">+ Demander</button>';
            echo '</form>';
            echo '</div>';

            /* Contrôle manuel : rouvrir / marquer faite + supprimer l'étape. */
            echo '<div style="display:flex;gap:6px;margin-top:7px">';
            echo '<form method="post" style="margin:0">' . wp_nonce_field('lfi_app_dossier_step', '_wpnonce', true, false) . '<input type="hidden" name="lfi_app_dossier_step" value="1"><input type="hidden" name="step_action" value="toggle"><input type="hidden" name="step_idx" value="' . (int) $idx . '"><button type="submit" class="btn-ghost" style="font-size:.78em">' . ($done ? '↩ Rouvrir' : '✅ Marquer faite') . '</button></form>';
            echo '<form method="post" onsubmit="return confirm(\'Supprimer cette étape ?\')" style="margin:0">' . wp_nonce_field('lfi_app_dossier_step', '_wpnonce', true, false) . '<input type="hidden" name="lfi_app_dossier_step" value="1"><input type="hidden" name="step_action" value="del"><input type="hidden" name="step_idx" value="' . (int) $idx . '"><button type="submit" class="btn-ghost" style="font-size:.72em;color:#c8102e">🗑 Étape</button></form>';
            echo '</div>';
            echo '</div></details>';
        }
        /* 🧹 Outils de tri/suppression : sélection MULTIPLE + tout effacer.
           (les cases dans les accordéons sont reliées à ce formulaire par form=) */
        echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">';
        echo '<form id="lfi-step-bulk" method="post" onsubmit="return confirm(\'Supprimer les étapes cochées ?\')" style="margin:0">' . wp_nonce_field('lfi_app_dossier_step', '_wpnonce', true, false) . '<input type="hidden" name="lfi_app_dossier_step" value="1"><input type="hidden" name="step_action" value="bulk_del"><button type="submit" class="btn-ghost" style="font-size:.82em;border-color:#c8102e;color:#c8102e">🗑 Supprimer la sélection</button></form>';
        echo '<form method="post" onsubmit="return confirm(\'Tout effacer le parcours ? (les étapes seulement — les pièces déjà versées restent)\')" style="margin:0">' . wp_nonce_field('lfi_app_dossier_step', '_wpnonce', true, false) . '<input type="hidden" name="lfi_app_dossier_step" value="1"><input type="hidden" name="step_action" value="reset"><button type="submit" class="btn-ghost" style="font-size:.82em;color:#c8102e">🧹 Tout effacer le parcours</button></form>';
        echo '</div>';
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

    /* CLOISONNEMENT STRICT (règle absolue) : un enregistrement n'appartient à ce
       locataire QUE si son tenant_user_id correspond EXACTEMENT. Aucune
       correspondance par nom ni par adresse — c'était la source de fuites entre
       dossiers (deux personnes au même immeuble, noms proches…). Un dossier ou
       une intervention sans tenant_user_id doit être RELIÉ au bon compte (via
       « lier / fusionner »), pas deviné à l'affichage. */
    $matches = function ($r_uid, $r_nom = '', $r_adr = '') use ($uid) {
        return ($uid && (int) $r_uid === $uid);
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
    /* CLOISONNEMENT STRICT : uniquement les interventions liées à CE compte. */
    $interv = array_values(array_filter($all, function ($i) use ($uid) {
        return ($uid && (int) $i->tenant_user_id === $uid);
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
        map.on('load', function () {
            addSurveyLayer(); loadBuildings();
            /* MARQUEURS DOM (pins) — 100 % fiables : ne peuvent PAS être masqués
               par l'ordre des couches, un immeuble 3D ou une police manquante.
               Chaque signalement = une épingle colorée par gravité + popup. */
            try {
                markers.forEach(function (m) {
                    if (typeof m.lng !== 'number' || typeof m.lat !== 'number') return;
                    new maplibregl.Marker({ color: m.gcolor || '#c8102e' })
                        .setLngLat([m.lng, m.lat])
                        .setPopup(new maplibregl.Popup({ offset: 14, closeButton: true }).setHTML(popupHtml(m)))
                        .addTo(map);
                });
            } catch (e) { if (window.console) console.warn('pins', e); }
        });

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
            'body' => "Bonjour {{prenom}},\n\nC'est {{moi}}, votre voisin·e du quartier (Groupe d'Action LFI Clos Toreau) qui suis votre dossier logement. J'aimerais qu'on se voie pour faire le point sur votre situation. Quel jour vous arrangerait dans les prochaines semaines ?\n\nVous pouvez me répondre directement par SMS.\n\nÀ bientôt,\n{{moi}}",
        ],
        'invitation_reunion' => [
            'nom'  => '📣 Inviter à une réunion publique',
            'body' => "Bonjour {{prenom}},\n\nC'est {{moi}}, votre voisin·e du GA LFI Clos Toreau. On organise une réunion publique : {{event_titre}} — {{event_jour}} {{event_date}} à {{event_heure}}, à {{event_lieu}}.\n\nVotre présence serait précieuse. Infos : {{event_url_short}}\n\n{{moi}}",
        ],
        'invitation_evenement' => [
            'nom'  => '📅 Inviter à un événement',
            'body' => "Bonjour {{prenom}},\n\nC'est {{moi}}, votre voisin·e du quartier. On organise {{event_titre}} le {{event_date}} à {{event_lieu}}. Vous y êtes le·la bienvenu·e.\n\nDétails : {{event_url_short}}\n\nÀ très vite !\n{{moi}}",
        ],
        'suivi_lettre' => [
            'nom'  => '✉️ Suivi d\'une démarche / lettre',
            'body' => "Bonjour {{prenom}},\n\nC'est {{moi}}, votre voisin·e qui suis votre dossier. Suite à notre échange sur votre logement, vous trouverez dans l'app un modèle de lettre pré-rempli à envoyer à votre bailleur.\n\nLien : https://lfi-nantes-clostoreau.fr/app/?vue=lettre\n\nN'hésitez pas à me solliciter.\n{{moi}}",
        ],
        'rappel_droits' => [
            'nom'  => '⚖️ Rappel sur vos droits',
            'body' => "Bonjour {{prenom}},\n\nC'est {{moi}}, votre voisin·e du GA. Petit rappel : les problèmes que vous nous avez signalés relèvent d'obligations légales du bailleur (loi du 6 juillet 1989, décret 2002-120).\n\nLa fiche complète de vos droits : https://lfi-nantes-clostoreau.fr/app/?vue=droits\n\n{{moi}}",
        ],
        'photo_request' => [
            'nom'  => '📷 Demander des photos du logement',
            'body' => "Bonjour {{prenom}},\n\nC'est {{moi}}, votre voisin·e qui suis votre dossier. Pour le faire avancer, pourriez-vous m'envoyer quelques photos des dégradations (moisissures, fuites, etc.) ?\n\nVous pouvez les déposer dans l'app, elles restent privées : https://lfi-nantes-clostoreau.fr/app/?vue=envoyer-photo\n\nMerci !\n{{moi}}",
        ],
        'libre' => [
            'nom'  => '✍️ Texte libre',
            'body' => "Bonjour {{prenom}},\n\nC'est {{moi}}, votre voisin·e du quartier, pour votre logement. ",
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
    /* {{moi}} = prénom du MEMBRE qui envoie (le référent) → « c'est [prénom],
       votre voisin·e ». Chaque membre personnalise avec SON prénom. */
    $me_u = wp_get_current_user();
    $moi  = $me_u ? ($me_u->first_name ?: $me_u->display_name) : '';
    if ($user && function_exists('lfi_nct_sms_render')) {
        $fake_membre = (object) [
            'id'     => $user->ID,
            'prenom' => $user->first_name ?: $user->display_name,
            'nom'    => $user->last_name,
            'pseudo' => '',
            'tel'    => $user_tel,
        ];
        $body = lfi_nct_sms_render($body, $fake_membre, array_merge($event_vars, ['moi' => $moi, 'voisin' => $moi]));
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
    /* On peut prévisualiser un·e locataire, un membre/admin de GA, MAIS AUSSI un·e
       ÉLU·E partenaire (Bompard, William Aucant…) et un·e avocat·e — sinon
       « Voir en tant que Bompard » renvoyait l'admin sur son propre écran. */
    $allow = [LFI_NCT_ROLE_TENANT, LFI_NCT_ROLE_GA];
    if (defined('LFI_NCT_ROLE_PARTNER')) $allow[] = LFI_NCT_ROLE_PARTNER;
    if (defined('LFI_NCT_ROLE_AVOCAT'))  $allow[] = LFI_NCT_ROLE_AVOCAT;
    if (!array_intersect($allow, $roles)) return null;
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

    /* --- On récupère tout le monde, puis on répartit PAR STRATE. --- */
    $role_ga     = defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : 'lfi_nct_ga_member';
    $role_te     = defined('LFI_NCT_ROLE_TENANT') ? LFI_NCT_ROLE_TENANT : 'lfi_nct_tenant';
    $role_pa     = defined('LFI_NCT_ROLE_PARTNER') ? LFI_NCT_ROLE_PARTNER : 'lfi_nct_partenaire';
    $partners = get_users(['role' => $role_pa, 'fields' => ['ID', 'display_name', 'user_login'], 'number' => 300, 'orderby' => 'display_name', 'order' => 'ASC']);
    $gas      = get_users(['role' => $role_ga, 'fields' => ['ID', 'display_name', 'user_login'], 'number' => 400, 'orderby' => 'display_name', 'order' => 'ASC']);
    $tenants  = get_users(['role' => $role_te, 'fields' => ['ID', 'display_name', 'user_login'], 'number' => 400, 'orderby' => 'display_name', 'order' => 'ASC']);

    /* Anti-doublon d'AFFICHAGE : un même nom n'apparaît qu'une fois (garde le
       compte le plus ancien = plus petit ID). */
    $dedup = function ($list) {
        $seen = []; $out = [];
        foreach ($list as $u) {
            $k = mb_strtolower(trim((string) $u->display_name));
            if ($k !== '' && isset($seen[$k])) continue;
            $seen[$k] = 1; $out[] = $u;
        }
        return $out;
    };
    /* Répartition FINE des PARTENAIRES par fonction : national / députés /
       conseillers municipaux / chargés de mission (com, sécurité, trésorier…). */
    $national = []; $deputes = []; $conseillers = []; $charges = [];
    foreach ($partners as $u) {
        if (get_user_meta($u->ID, 'lfi_nct_demo_national', true)) { $national[] = $u; continue; }
        $f = mb_strtolower((string) get_user_meta($u->ID, 'lfi_nct_elu_fonction', true));
        if (preg_match('/(d.put.|assembl.e nationale|s.nateur|euro?d.put.)/u', $f))                 $deputes[] = $u;
        elseif (preg_match('/(conseil.*municipal|municipal|maire|adjoint|conseiller|conseill.re|.lu)/u', $f)) $conseillers[] = $u;
        elseif (preg_match('/(charg.|responsable|communication|s.curit.|tr.sorier|secr.taire|coordinateur|coordination|r.f.rent|relations|parrainage|gestion)/u', $f)) $charges[] = $u;
        else $conseillers[] = $u; /* élu par défaut si fonction inconnue */
    }
    /* Gestionnaires de GA vs membres simples. */
    $ga_admins = []; $ga_membres = [];
    foreach ($gas as $u) {
        if ((string) get_user_meta($u->ID, 'lfi_nct_ga_role', true) === 'admin') $ga_admins[] = $u; else $ga_membres[] = $u;
    }

    lfi_nct_app_screen_open('👁 Voir en tant que…', 'Chaque strate, bien séparée');
    echo '<div class="lfi-app-help">Touche une strate pour la déplier, puis « Voir en tant que ». « × Sortir » pour revenir. Rien n\'est modifié.</div>';

    /* Chaque strate = un ACCORDÉON (replié). $show_fn = afficher la fonction. */
    $render = function ($titre, $couleur, $badge, $list, $show_fn = false) use ($dedup) {
        $list = $dedup($list);
        echo '<details class="lfi-app-card" style="border-left:4px solid ' . $couleur . ';margin-bottom:8px"><summary style="cursor:pointer;font-weight:800;color:' . $couleur . '">' . $titre . ' (' . count($list) . ')</summary>';
        if (empty($list)) { echo '<div class="lfi-app-empty" style="font-size:.9em;margin-top:6px">Personne pour l\'instant.</div>'; echo '</details>'; return; }
        echo '<ul class="lfi-app-list" style="margin-top:8px">';
        foreach ($list as $u) {
            $nm = $u->display_name ?: $u->user_login;
            $fn = $show_fn ? (string) get_user_meta($u->ID, 'lfi_nct_elu_fonction', true) : '';
            echo '<li class="lfi-app-card" style="padding:9px 12px"><div class="head"><div class="who">' . esc_html($nm) . '</div><div class="badge" style="background:' . $couleur . ';color:#fff">' . esc_html($badge) . '</div></div>';
            if ($fn !== '') echo '<div class="meta"><span class="meta-chip">💼 ' . esc_html($fn) . '</span></div>';
            echo '<div class="row-actions" style="margin-top:6px"><a class="btn-primary" href="' . esc_url(lfi_nct_app_url('preview-set', ['uid' => $u->ID])) . '">👁 Voir en tant que ' . esc_html($nm) . '</a></div></li>';
        }
        echo '</ul></details>';
    };

    $render('🏛️ National',                 '#c8102e', 'National',   $national,    true);
    $render('🏛️ Députés',                  '#7a0000', 'Député·e',  $deputes,     true);
    $render('🏢 Conseillers municipaux',    '#8a6d1f', 'Conseil municipal', $conseillers, true);
    $render('🎯 Chargés de mission',        '#0b6a6a', 'Chargé·e',  $charges,     true);
    $render('⭐ Gestionnaires de GA',        '#6a1b9a', 'Gestion GA', $ga_admins);
    $render('👥 Membres de GA',              '#0b3d91', 'Membre',     $ga_membres);
    $render('🏠 Locataires',                 '#186a3b', 'Locataire',  $tenants);

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
