<?php
/**
 * INGESTION DE DOCUMENTS À DISTANCE.
 *
 * Objectif : Fabrice envoie un document (email reçu, pièce PDF…) à Claude
 * dans le chat, et Claude le classe DIRECTEMENT dans le bon dossier
 * locataire sur le site — sans que Fabrice ait quoi que ce soit à faire
 * sur WordPress (pas de copier-coller, pas d'ajout de pièce jointe).
 *
 * Comment : un point d'entrée REST sécurisé par une CLÉ dédiée (pas le mot
 * de passe WordPress). Claude appelle ce point d'entrée avec la clé et le
 * contenu ; le plugin écrit dans le dossier (correspondance + pièce jointe).
 *
 * Confidentialité (RGPD) : les pièces jointes sont stockées dans un dossier
 * PROTÉGÉ (non accessible publiquement) et ne sont servies qu'via une route
 * authentifiée, cloisonnée au GA. Rien ne transite par Git.
 */
if (!defined('ABSPATH')) exit;

/** Clé d'intégration (générée une fois, stockée en base). */
function lfi_nct_ingest_key() {
    $k = (string) get_option('lfi_nct_ingest_key', '');
    if ($k === '') {
        $k = wp_generate_password(40, false, false);
        update_option('lfi_nct_ingest_key', $k, false);
    }
    return $k;
}

/** Régénère la clé (invalide l'ancienne). */
function lfi_nct_ingest_key_regenerate() {
    $k = wp_generate_password(40, false, false);
    update_option('lfi_nct_ingest_key', $k, false);
    return $k;
}

/** Dossier de stockage protégé des pièces jointes. */
function lfi_nct_ingest_pieces_dir() {
    $up  = wp_upload_dir();
    $dir = trailingslashit($up['basedir']) . 'lfi-nct-pieces';
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
        /* Blocage de l'accès web direct (Apache + LiteSpeed). */
        @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

/* ============================================================== *
 *  ROUTE REST : POST /wp-json/lfi-nct/v1/dossier-piece            *
 * ============================================================== */
add_action('rest_api_init', function () {
    register_rest_route('lfi-nct/v1', '/dossier-piece', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_handle',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/dossiers', [
        'methods'             => 'GET',
        'callback'            => 'lfi_nct_ingest_rest_list',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/evenement', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_event',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/dossier-create', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_dossier_create',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/evenement-update', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_event_update',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/dossier-reply-set', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_reply_set',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
});

/** Dépose une RÉPONSE PROPOSÉE (psy+architecte) dans un dossier. */
function lfi_nct_ingest_rest_reply_set($request) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $id = (int) $request->get_param('dossier_id');
    $row = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id)) : null;
    if (!$row) return new WP_REST_Response(['ok' => false, 'error' => 'dossier_introuvable'], 404);
    $to      = sanitize_text_field((string) $request->get_param('to'));
    $subject = sanitize_text_field((string) $request->get_param('subject'));
    $body    = sanitize_textarea_field(wp_check_invalid_utf8((string) $request->get_param('body')));
    if ($to === '' || $body === '') return new WP_REST_Response(['ok' => false, 'error' => 'to_ou_body_vide'], 400);
    $notes = json_decode($row->notes ?? '', true);
    if (!is_array($notes)) $notes = [];
    $notes['replies'] = isset($notes['replies']) && is_array($notes['replies']) ? $notes['replies'] : [];
    $notes['replies'][] = [
        'to'      => $to,
        'subject' => $subject,
        'body'    => $body,
        'objet'   => sanitize_text_field((string) $request->get_param('ref')), /* à quel email on répond */
        'date'    => wp_date('Y-m-d'),
        'src'     => 'claude',
    ];
    $wpdb->update($t, ['notes' => wp_json_encode($notes), 'updated_at' => current_time('mysql')], ['id' => $id]);
    return new WP_REST_Response(['ok' => true, 'count' => count($notes['replies'])], 200);
}

/** Lien de composition Gmail pré-rempli (ouvre Gmail avec la réponse). */
function lfi_nct_gmail_compose_url($to, $subject, $body) {
    return 'https://mail.google.com/mail/?view=cm&fs=1'
        . '&to=' . rawurlencode($to)
        . '&su=' . rawurlencode($subject)
        . '&body=' . rawurlencode($body);
}

/** Rendu de la section « Réponses à envoyer » (validées → bouton Gmail). */
function lfi_nct_render_dossier_replies($row) {
    $notes   = json_decode($row->notes ?? '', true);
    $replies = (is_array($notes) && !empty($notes['replies'])) ? $notes['replies'] : [];
    echo '<h3 id="sec-reponses" style="margin:22px 0 6px;color:#c8102e">✉️ Réponses à envoyer (prêtes)</h3>';
    if (empty($replies)) {
        echo '<div class="lfi-app-help">Quand un email arrive, le psy et l\'architecte préparent ici une réponse. Tu la relis, puis tu cliques « Ouvrir dans Gmail » : Gmail s\'ouvre avec la réponse pré-remplie, tu n\'as plus qu\'à envoyer.</div>';
        return;
    }
    echo '<ul class="lfi-app-list">';
    foreach (array_reverse($replies, true) as $i => $r) {
        $to  = (string) ($r['to'] ?? '');
        $sub = (string) ($r['subject'] ?? '');
        $bod = (string) ($r['body'] ?? '');
        $url = lfi_nct_gmail_compose_url($to, $sub, $bod);
        echo '<li class="lfi-app-card" style="border-left:4px solid #186a3b">';
        echo '<div class="head"><div class="who">✉️ Réponse prête</div>';
        echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html($r['date'] ?? '') . '</div></div>';
        echo '<div class="meta">';
        if ($to)  echo '<span class="meta-chip">À : ' . esc_html($to) . '</span>';
        if (!empty($r['objet'])) echo '<span class="meta-chip">↩︎ ' . esc_html($r['objet']) . '</span>';
        echo '</div>';
        if ($sub) echo '<div class="com"><strong>Objet :</strong> ' . esc_html($sub) . '</div>';
        echo '<details style="margin:6px 0"><summary style="cursor:pointer;color:#0066a3">📖 Lire la réponse</summary>'
           . '<div class="com" style="white-space:pre-wrap;background:#f7f7f7;border-radius:6px;padding:10px;margin-top:6px">' . esc_html($bod) . '</div></details>';
        echo '<div class="row-actions" style="margin-top:8px"><a class="btn-primary" style="background:#186a3b" href="' . esc_url($url) . '" target="_blank" rel="noopener">✅ Valider et ouvrir dans Gmail</a></div>';
        echo '<div class="lfi-app-help" style="margin-top:4px"><small>Gmail s\'ouvre avec la réponse déjà écrite — vérifie et appuie sur Envoyer.</small></div>';
        echo '</li>';
    }
    echo '</ul>';
}

/** Met à jour un événement existant (ex : attacher le lien Action Populaire). */
function lfi_nct_ingest_rest_event_update($request) {
    $id = (int) $request->get_param('id');
    if (!$id || !get_post($id)) return new WP_REST_Response(['ok' => false, 'error' => 'introuvable'], 404);
    $ap = esc_url_raw((string) $request->get_param('ap_url'));
    if ($ap) {
        update_post_meta($id, '_lfi_evt_url_ap', $ap);
        update_post_meta($id, '_lfi_evt_ap_url', $ap);
    }
    foreach (['date' => '_ag_event_date', 'heure' => '_ag_event_time', 'lieu' => '_ag_event_place', 'ville' => '_ag_event_city'] as $pk => $mk) {
        $val = $request->get_param($pk);
        if ($val !== null && $val !== '') update_post_meta($id, $mk, sanitize_text_field((string) $val));
    }
    return new WP_REST_Response(['ok' => true, 'id' => $id, 'url' => get_permalink($id)], 200);
}

/** Crée un dossier locataire à distance (clé d'intégration). */
function lfi_nct_ingest_rest_dossier_create($request) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $prenom = sanitize_text_field((string) $request->get_param('prenom'));
    $nom    = sanitize_text_field((string) $request->get_param('nom'));
    if ($prenom === '' && $nom === '') return new WP_REST_Response(['ok' => false, 'error' => 'nom_manquant'], 400);
    /* Propriétaire = celui du GA « maison » (clos-toreau / super-admin). */
    $owner = function_exists('lfi_nct_ga_admin_owner') ? (int) lfi_nct_ga_admin_owner('clos-toreau') : 0;
    if (!$owner && function_exists('lfi_nct_dossier_owner_id')) $owner = (int) lfi_nct_dossier_owner_id();
    /* Anti-doublon simple. */
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $t WHERE owner_user_id = %d AND tenant_prenom = %s AND tenant_nom = %s LIMIT 1",
        $owner, $prenom, $nom));
    if ($exists) return new WP_REST_Response(['ok' => true, 'id' => (int) $exists, 'existant' => true], 200);
    $ok = $wpdb->insert($t, [
        'owner_user_id'  => $owner,
        'tenant_prenom'  => $prenom,
        'tenant_nom'     => $nom,
        'tenant_adresse' => sanitize_text_field((string) $request->get_param('adresse')),
        'tenant_tel'     => sanitize_text_field((string) $request->get_param('tel')),
        'tenant_email'   => sanitize_email((string) $request->get_param('email')),
        'constatations'  => sanitize_textarea_field((string) $request->get_param('constatations')),
        'statut'         => 'ouvert',
    ]);
    if (!$ok) return new WP_REST_Response(['ok' => false, 'error' => 'creation_impossible'], 500);
    return new WP_REST_Response(['ok' => true, 'id' => (int) $wpdb->insert_id], 200);
}

/** Crée un événement à distance (clé d'intégration). */
function lfi_nct_ingest_rest_event($request) {
    $title = sanitize_text_field((string) $request->get_param('titre'));
    if ($title === '') return new WP_REST_Response(['ok' => false, 'error' => 'titre_manquant'], 400);
    $cpt = post_type_exists('ag_evenement') ? 'ag_evenement' : (post_type_exists('lfi_evenement') ? 'lfi_evenement' : 'post');
    $pid = wp_insert_post([
        'post_type'    => $cpt,
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_content' => wp_kses_post((string) $request->get_param('description')),
    ], true);
    if (is_wp_error($pid) || !$pid) return new WP_REST_Response(['ok' => false, 'error' => 'creation_impossible'], 500);
    update_post_meta($pid, '_ag_event_date',  sanitize_text_field((string) $request->get_param('date')));
    update_post_meta($pid, '_ag_event_time',  sanitize_text_field((string) $request->get_param('heure')));
    update_post_meta($pid, '_ag_event_place', sanitize_text_field((string) $request->get_param('lieu')));
    update_post_meta($pid, '_ag_event_city',  sanitize_text_field((string) $request->get_param('ville')));
    /* Lien Action Populaire (l'inscription se fait TOUJOURS d'abord sur AP). */
    $ap = esc_url_raw((string) $request->get_param('ap_url'));
    if ($ap) {
        update_post_meta($pid, '_lfi_evt_url_ap', $ap); /* utilisé par la page single */
        update_post_meta($pid, '_lfi_evt_ap_url', $ap); /* utilisé par la liste de l'app */
    }
    $ga = sanitize_text_field((string) $request->get_param('ga'));
    update_post_meta($pid, '_lfi_evt_ga', $ga !== '' ? $ga : 'clos-toreau');
    update_post_meta($pid, '_lfi_evt_internal', 1);
    return new WP_REST_Response(['ok' => true, 'id' => (int) $pid, 'url' => get_permalink($pid)], 200);
}

/** Authentification : en-tête X-LFI-Key === clé d'intégration. */
function lfi_nct_ingest_rest_auth($request) {
    $expected = lfi_nct_ingest_key();
    $got = (string) $request->get_header('x_lfi_key');
    if ($got === '') $got = (string) $request->get_param('key');
    return $got !== '' && hash_equals($expected, $got);
}

/** Liste des dossiers (id + nom + adresse) pour aider Claude à cibler. */
function lfi_nct_ingest_rest_list($request) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $rows = $wpdb->get_results("SELECT id, tenant_prenom, tenant_nom, tenant_adresse, owner_user_id FROM $t ORDER BY updated_at DESC LIMIT 500") ?: [];
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'      => (int) $r->id,
            'nom'     => trim($r->tenant_prenom . ' ' . $r->tenant_nom),
            'adresse' => (string) $r->tenant_adresse,
            'owner'   => (int) $r->owner_user_id,
        ];
    }
    return new WP_REST_Response(['ok' => true, 'dossiers' => $out], 200);
}

/**
 * Classe un document dans un dossier.
 * Corps JSON attendu :
 *   dossier_id (int)  OU  tenant (string, recherche par nom)
 *   type       : 'correspondance_recue' | 'correspondance_envoyee' | 'piece'
 *   de, to, objet, date, corps  (texte de la correspondance)
 *   filename, pdf_base64        (pièce jointe optionnelle)
 */
function lfi_nct_ingest_rest_handle($request) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';

    $dossier_id = (int) $request->get_param('dossier_id');
    $tenant     = trim((string) $request->get_param('tenant'));

    $row = null;
    if ($dossier_id) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $dossier_id));
    } elseif ($tenant !== '') {
        $like = '%' . $wpdb->esc_like($tenant) . '%';
        $matches = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $t WHERE CONCAT(tenant_prenom,' ',tenant_nom) LIKE %s OR tenant_nom LIKE %s ORDER BY updated_at DESC LIMIT 10",
            $like, $like
        )) ?: [];
        if (count($matches) === 1) {
            $row = $matches[0];
        } elseif (count($matches) > 1) {
            $list = array_map(function ($m) {
                return ['id' => (int) $m->id, 'nom' => trim($m->tenant_prenom . ' ' . $m->tenant_nom), 'adresse' => (string) $m->tenant_adresse];
            }, $matches);
            return new WP_REST_Response(['ok' => false, 'error' => 'ambiguous', 'candidats' => $list], 409);
        }
    }
    if (!$row) {
        return new WP_REST_Response(['ok' => false, 'error' => 'dossier_introuvable'], 404);
    }

    $type  = sanitize_key((string) $request->get_param('type')) ?: 'correspondance_recue';
    $date  = sanitize_text_field((string) $request->get_param('date')) ?: wp_date('Y-m-d');
    $objet = sanitize_text_field((string) $request->get_param('objet'));
    $de    = sanitize_text_field((string) $request->get_param('de'));
    $to    = sanitize_text_field((string) $request->get_param('to'));
    $corps = (string) $request->get_param('corps');
    $corps = wp_check_invalid_utf8($corps);
    $corps = sanitize_textarea_field($corps);

    $notes = json_decode($row->notes ?? '', true);
    if (!is_array($notes)) $notes = [];

    $added = [];

    /* 1) Correspondance (texte). */
    if ($type === 'correspondance_recue' && ($corps !== '' || $objet !== '')) {
        $notes['email_recu'] = isset($notes['email_recu']) && is_array($notes['email_recu']) ? $notes['email_recu'] : [];
        $notes['email_recu'][] = ['date' => $date, 'de' => $de, 'objet' => $objet, 'corps' => $corps, 'src' => 'ingest'];
        $added[] = 'correspondance_recue';
    } elseif ($type === 'correspondance_envoyee' && ($corps !== '' || $objet !== '')) {
        $notes['email_log'] = isset($notes['email_log']) && is_array($notes['email_log']) ? $notes['email_log'] : [];
        $notes['email_log'][] = ['date' => $date, 'to' => $to, 'objet' => $objet, 'corps' => $corps, 'src' => 'ingest'];
        $added[] = 'correspondance_envoyee';
    }

    /* 2) Pièce jointe (PDF ou autre) — stockage protégé. */
    $b64  = (string) $request->get_param('pdf_base64');
    $fn   = sanitize_file_name((string) $request->get_param('filename'));
    if ($b64 !== '') {
        $bin = base64_decode($b64, true);
        if ($bin === false || strlen($bin) < 8) {
            return new WP_REST_Response(['ok' => false, 'error' => 'fichier_invalide'], 400);
        }
        if (strlen($bin) > 25 * 1024 * 1024) {
            return new WP_REST_Response(['ok' => false, 'error' => 'fichier_trop_gros'], 413);
        }
        if ($fn === '') $fn = 'piece-' . $date . '.pdf';
        $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'txt'];
        if (!in_array($ext, $allowed, true)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'type_non_autorise'], 415);
        }
        $dir = lfi_nct_ingest_pieces_dir() . '/' . (int) $row->id;
        if (!file_exists($dir)) wp_mkdir_p($dir);
        $hash = wp_generate_password(16, false, false);
        $path = $dir . '/' . $hash . '.' . $ext;
        if (@file_put_contents($path, $bin) === false) {
            return new WP_REST_Response(['ok' => false, 'error' => 'ecriture_impossible'], 500);
        }
        $notes['pieces'] = isset($notes['pieces']) && is_array($notes['pieces']) ? $notes['pieces'] : [];
        $notes['pieces'][] = [
            'id'    => $hash,
            'name'  => $fn,
            'ext'   => $ext,
            'date'  => $date,
            'objet' => $objet,
            'size'  => strlen($bin),
        ];
        $added[] = 'piece:' . $fn;
    }

    if (empty($added)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'rien_a_classer'], 400);
    }

    $wpdb->update($t, ['notes' => wp_json_encode($notes), 'updated_at' => current_time('mysql')], ['id' => (int) $row->id]);

    return new WP_REST_Response([
        'ok'       => true,
        'dossier'  => ['id' => (int) $row->id, 'nom' => trim($row->tenant_prenom . ' ' . $row->tenant_nom)],
        'classe'   => $added,
    ], 200);
}

/* ============================================================== *
 *  Téléchargement authentifié d'une pièce (route app, cloisonné) *
 * ============================================================== */
function lfi_nct_ingest_download() {
    $can = current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
    if (!$can) { status_header(403); exit('403'); }
    $id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $hash = isset($_GET['f'])  ? preg_replace('/[^A-Za-z0-9]/', '', (string) $_GET['f']) : '';
    /* Le dossier est chargé de façon cloisonnée (owner du GA courant). */
    $row  = $id && function_exists('lfi_nct_dossier_get') ? lfi_nct_dossier_get($id) : null;
    if (!$row || $hash === '') { status_header(404); exit('404'); }
    $notes = json_decode($row->notes ?? '', true);
    $pieces = (is_array($notes) && !empty($notes['pieces'])) ? $notes['pieces'] : [];
    $found = null;
    foreach ($pieces as $p) { if (($p['id'] ?? '') === $hash) { $found = $p; break; } }
    if (!$found) { status_header(404); exit('404'); }
    $ext  = preg_replace('/[^a-z0-9]/', '', strtolower($found['ext'] ?? 'pdf'));
    $path = lfi_nct_ingest_pieces_dir() . '/' . (int) $id . '/' . $hash . '.' . $ext;
    if (!file_exists($path)) { status_header(404); exit('404'); }
    $mimes = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'txt' => 'text/plain'];
    nocache_headers();
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . str_replace('"', '', (string) ($found['name'] ?? ('piece.' . $ext))) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

/** Rendu de la section « Pièces jointes » dans un dossier. */
function lfi_nct_ingest_render_pieces($row) {
    $notes  = json_decode($row->notes ?? '', true);
    $pieces = (is_array($notes) && !empty($notes['pieces'])) ? $notes['pieces'] : [];
    echo '<h3 id="sec-pieces" style="margin:22px 0 6px;color:#c8102e">📎 Pièces jointes du dossier</h3>';
    if (empty($pieces)) {
        echo '<div class="lfi-app-help">Aucune pièce pour l\'instant. Les documents transmis à Claude (emails du service hygiène, courriers, PDF…) sont classés ici automatiquement.</div>';
        return;
    }
    echo '<ul class="lfi-app-list">';
    foreach (array_reverse($pieces) as $p) {
        $url = lfi_nct_app_url('dossier-piece-dl', ['id' => (int) $row->id, 'f' => (string) ($p['id'] ?? '')]);
        $ico = (($p['ext'] ?? '') === 'pdf') ? '📄' : '🖼';
        $ko  = isset($p['size']) ? ' · ' . size_format((int) $p['size']) : '';
        echo '<li class="lfi-app-card" style="border-left:4px solid #0066a3">';
        echo '<div class="head"><div class="who">' . $ico . ' ' . esc_html($p['name'] ?? 'Pièce') . '</div>';
        echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html($p['date'] ?? '') . $ko . '</div></div>';
        if (!empty($p['objet'])) echo '<div class="com">' . esc_html($p['objet']) . '</div>';
        echo '<div class="row-actions" style="margin-top:6px"><a class="btn-primary" href="' . esc_url($url) . '" target="_blank" rel="noopener">📂 Ouvrir</a></div>';
        echo '</li>';
    }
    echo '</ul>';
}

/* ============================================================== *
 *  Écran « Clé d'intégration » (super-admin) — à lire une fois    *
 * ============================================================== */
function lfi_nct_app_view_integration_key() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    if (!empty($_POST['lfi_ingest_regen']) && check_admin_referer('lfi_ingest_regen')) {
        lfi_nct_ingest_key_regenerate();
        wp_safe_redirect(lfi_nct_app_url('integration-key', ['regen' => 1]));
        exit;
    }

    $key = lfi_nct_ingest_key();
    $ep  = rest_url('lfi-nct/v1/dossier-piece');

    lfi_nct_app_screen_open('🔗 Clé d\'intégration', 'Permet à Claude de classer tes documents directement dans les dossiers');
    if (!empty($_GET['regen'])) lfi_nct_app_flash('🔑 Nouvelle clé générée. L\'ancienne ne fonctionne plus.');
    echo '<div class="lfi-app-help">Donne cette clé <strong>une seule fois</strong> à Claude. Ensuite, tu lui envoies simplement un document (email, PDF…) et il le classe tout seul dans le bon dossier — tu n\'as rien à faire ici.</div>';
    echo '<div class="lfi-app-card"><div class="head"><div class="who">🔑 Clé</div></div>';
    echo '<div class="meta" style="flex-direction:column;align-items:flex-start;gap:6px">';
    echo '<code style="user-select:all;word-break:break-all;background:#f5f5f5;padding:8px 10px;border-radius:6px;display:block;width:100%">' . esc_html($key) . '</code>';
    echo '<span class="meta-chip">Point d\'entrée : ' . esc_html($ep) . '</span>';
    echo '</div></div>';
    echo '<div class="lfi-app-help" style="background:#fff3cd;border-left:4px solid #d39e00;margin-top:10px"><small>⚠️ Cette clé donne le droit d\'ajouter des documents aux dossiers. Ne la publie nulle part. Si tu penses qu\'elle a fuité, régénère-la ci-dessous.</small></div>';
    echo '<form method="post" style="margin-top:10px" onsubmit="return confirm(\'Régénérer la clé ? Il faudra la redonner à Claude.\');">';
    wp_nonce_field('lfi_ingest_regen');
    echo '<input type="hidden" name="lfi_ingest_regen" value="1">';
    echo '<button type="submit" class="btn-ghost" style="color:#c8102e;border-color:#c8102e">🔄 Régénérer la clé</button>';
    echo '</form>';
    lfi_nct_app_screen_close();
}
