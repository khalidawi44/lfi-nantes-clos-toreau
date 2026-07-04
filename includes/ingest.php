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
    register_rest_route('lfi-nct/v1', '/dossier-assign', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_assign',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/dossier-reply-delete', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_reply_delete',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/member-create', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_member_create',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/member-password', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_member_password',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/member-role', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_member_role',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/activity-log', [
        'methods'             => 'GET',
        'callback'            => 'lfi_nct_ingest_rest_activity_log',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/journal-add', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_journal_add',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/page-set', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_page_set',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/member-news-add', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_member_news_add',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/frais-add', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_frais_add',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/frais-list', [
        'methods'             => 'GET',
        'callback'            => 'lfi_nct_ingest_rest_frais_list',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/interventions-list', [
        'methods'             => 'GET',
        'callback'            => 'lfi_nct_ingest_rest_interventions_list',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/intervention-reclass', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_intervention_reclass',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/member-mailbox-set', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_ingest_rest_member_mailbox_set',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
    register_rest_route('lfi-nct/v1', '/membres-ga', [
        'methods'             => 'GET',
        'callback'            => 'lfi_nct_ingest_rest_membres_ga',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
});

/** Liste des comptes du GA (pour affecter un référent). */
function lfi_nct_ingest_rest_membres_ga($request) {
    $role = defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : 'lfi_nct_ga_member';
    /* On filtre par rôle côté serveur (role__in) : indispensable, car avec
       'fields' restreint les rôles ne sont PAS chargés et le filtre échouait
       → la liste revenait toujours vide. */
    $users = get_users(['role__in' => ['administrator', 'lfi_nct_ga', $role], 'number' => 200]);
    $out = [];
    foreach ($users as $u) {
        $out[] = ['id' => (int) $u->ID, 'nom' => $u->display_name, 'email' => $u->user_email, 'roles' => array_values((array) $u->roles)];
    }
    return new WP_REST_Response(['ok' => true, 'membres' => $out], 200);
}

/**
 * Change le RÔLE d'un compte (membre du GA ↔ locataire) et, en option, le lie
 * à un dossier locataire. Réservé à la clé d'intégration.
 * Params : user_id|email, role ('tenant'|'ga'), dossier_id (option), remove_mailbox (option).
 */
function lfi_nct_ingest_rest_member_role($request) {
    global $wpdb;
    $uid   = (int) $request->get_param('user_id');
    $email = sanitize_email((string) $request->get_param('email'));
    if (!$uid && $email && ($u = get_user_by('email', $email))) $uid = (int) $u->ID;
    $user = $uid ? get_userdata($uid) : null;
    if (!$user) return new WP_REST_Response(['ok' => false, 'error' => 'compte_introuvable'], 400);

    $role = sanitize_key((string) $request->get_param('role'));
    $tenant_role = defined('LFI_NCT_ROLE_TENANT') ? LFI_NCT_ROLE_TENANT : 'lfi_nct_tenant';
    $ga_role     = defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : 'lfi_nct_ga_member';
    $target = ($role === 'tenant') ? $tenant_role : (($role === 'ga') ? $ga_role : '');
    if ($target === '' || !get_role($target)) return new WP_REST_Response(['ok' => false, 'error' => 'role_invalide'], 400);

    /* set_role remplace tous les rôles : le compte devient UNIQUEMENT ce rôle
       (on ne touche pas à un vrai administrateur par sécurité). */
    if (in_array('administrator', (array) $user->roles, true)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'compte_admin_protege'], 400);
    }
    $user->set_role($target);

    /* Lien au dossier locataire (tenant_user_id). */
    $did = (int) $request->get_param('dossier_id');
    $linked = 0;
    if ($did) {
        $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
        if ($wpdb->get_row($wpdb->prepare("SELECT id FROM $t WHERE id = %d", $did))) {
            $wpdb->update($t, ['tenant_user_id' => $uid], ['id' => $did]);
            $linked = $did;
        }
    }

    /* Retrait éventuel de sa boîte du check multi-boîtes (plus un membre). */
    $removed_box = false;
    if ($request->get_param('remove_mailbox')) {
        $boxes = get_option('lfi_nct_member_mailboxes', []);
        if (is_array($boxes) && isset($boxes[(string) $uid])) {
            unset($boxes[(string) $uid]);
            update_option('lfi_nct_member_mailboxes', $boxes, false);
            $removed_box = true;
        }
    }

    return new WP_REST_Response([
        'ok'        => true,
        'user_id'   => $uid,
        'email'     => $user->user_email,
        'role'      => $target,
        'dossier'   => $linked,
        'boite_retiree' => $removed_box,
    ], 200);
}

/** Affecte un dossier à un référent (membre du GA). */
function lfi_nct_ingest_rest_assign($request) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $id  = (int) $request->get_param('dossier_id');
    $ref = (int) $request->get_param('referent_user_id');
    if (!$id || !$wpdb->get_row($wpdb->prepare("SELECT id FROM $t WHERE id = %d", $id))) {
        return new WP_REST_Response(['ok' => false, 'error' => 'dossier_introuvable'], 404);
    }
    if ($ref && !get_userdata($ref)) return new WP_REST_Response(['ok' => false, 'error' => 'membre_introuvable'], 400);
    $wpdb->update($t, ['referent_user_id' => $ref ?: null], ['id' => $id]);
    return new WP_REST_Response(['ok' => true, 'dossier_id' => $id, 'referent_user_id' => $ref], 200);
}

/** Crée (ou retrouve) un compte membre du GA. */
function lfi_nct_ingest_rest_member_create($request) {
    $email = sanitize_email((string) $request->get_param('email'));
    $nom   = sanitize_text_field((string) $request->get_param('nom'));
    if (!is_email($email)) return new WP_REST_Response(['ok' => false, 'error' => 'email_invalide'], 400);
    $role = defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : 'lfi_nct_ga_member';

    $existing = get_user_by('email', $email);
    if ($existing) {
        $uid = (int) $existing->ID;
        if (get_role($role) && !in_array($role, (array) $existing->roles, true)) $existing->add_role($role);
        $existant = true;
    } else {
        $base  = $nom !== '' ? $nom : current(explode('@', $email));
        $login = sanitize_user(sanitize_title($base) . '_' . wp_generate_password(4, false, false), true);
        $uid = wp_insert_user([
            'user_login'   => $login,
            'user_pass'    => wp_generate_password(24),
            'user_email'   => $email,
            'display_name' => $nom !== '' ? $nom : $login,
            'role'         => get_role($role) ? $role : 'subscriber',
        ]);
        if (is_wp_error($uid)) return new WP_REST_Response(['ok' => false, 'error' => $uid->get_error_message()], 500);
        $uid = (int) $uid;
        $cga = function_exists('lfi_nct_creation_ga') ? lfi_nct_creation_ga() : '';
        if ($cga) update_user_meta($uid, 'lfi_nct_ga', $cga);
        $existant = false;
    }
    return new WP_REST_Response(['ok' => true, 'user_id' => $uid, 'email' => $email, 'existant' => $existant], 200);
}

/**
 * Définit le mot de passe (et éventuellement le nom / le rôle) d'un membre.
 * Renvoie le mot de passe défini pour pouvoir le transmettre. Réservé à la clé.
 */
function lfi_nct_ingest_rest_member_password($request) {
    $uid   = (int) $request->get_param('user_id');
    $email = sanitize_email((string) $request->get_param('email'));
    if (!$uid && $email && ($u = get_user_by('email', $email))) $uid = (int) $u->ID;
    $user = $uid ? get_userdata($uid) : null;
    if (!$user) return new WP_REST_Response(['ok' => false, 'error' => 'membre_introuvable'], 400);

    $pw = (string) $request->get_param('password');
    if ($pw === '') $pw = wp_generate_password(12, false); /* lisible, sans symboles */
    $upd = ['ID' => $uid, 'user_pass' => $pw];
    $name = sanitize_text_field((string) $request->get_param('display_name'));
    if ($name !== '') $upd['display_name'] = $name;
    $res = wp_update_user($upd);
    if (is_wp_error($res)) return new WP_REST_Response(['ok' => false, 'error' => $res->get_error_message()], 500);

    /* S'assure du rôle membre du GA. */
    $role = defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : 'lfi_nct_ga_member';
    if (get_role($role) && !in_array($role, (array) $user->roles, true)) $user->add_role($role);

    return new WP_REST_Response([
        'ok'       => true,
        'user_id'  => $uid,
        'login'    => $user->user_login,
        'email'    => $user->user_email,
        'password' => $pw,
    ], 200);
}

/**
 * Enregistre la BOÎTE EMAIL d'un membre (email + mot de passe d'application)
 * pour le check permanent multi-boîtes. Le secret est stocké en option serveur.
 */
function lfi_nct_ingest_rest_member_mailbox_set($request) {
    $uid   = (int) $request->get_param('user_id');
    $email = sanitize_email((string) $request->get_param('email'));
    if (!$uid && $email && ($u = get_user_by('email', $email))) $uid = (int) $u->ID;
    if (!$uid || !get_userdata($uid)) return new WP_REST_Response(['ok' => false, 'error' => 'membre_introuvable'], 400);

    $box_email = sanitize_email((string) $request->get_param('gmail_user'));
    if ($box_email === '') $box_email = $email;
    if ($box_email === '') { $u = get_userdata($uid); $box_email = $u ? $u->user_email : ''; }
    $app_pw = preg_replace('/\s+/', '', (string) $request->get_param('gmail_app_pw'));
    $en = $request->get_param('enabled');
    $enabled = ($en === null) ? 1 : (int) (bool) $en;

    $boxes = get_option('lfi_nct_member_mailboxes', []);
    if (!is_array($boxes)) $boxes = [];
    /* On garde l'ancien mot de passe si aucun n'est fourni (mise à jour partielle). */
    if ($app_pw === '' && isset($boxes[(string) $uid]['app_pw'])) $app_pw = (string) $boxes[(string) $uid]['app_pw'];
    if ($box_email === '' || $app_pw === '') return new WP_REST_Response(['ok' => false, 'error' => 'email_ou_mdp_manquant'], 400);

    $boxes[(string) $uid] = ['user_id' => $uid, 'email' => $box_email, 'app_pw' => $app_pw, 'enabled' => $enabled];
    update_option('lfi_nct_member_mailboxes', $boxes, false);
    return new WP_REST_Response(['ok' => true, 'user_id' => $uid, 'email' => $box_email, 'enabled' => $enabled, 'boites' => count($boxes)], 200);
}

/** Traduit un user-agent en libellé d'appareil court et lisible. */
function lfi_nct_ua_device($ua) {
    $ua = (string) $ua;
    if ($ua === '') return 'Appareil inconnu';
    $os = 'Autre';
    if (preg_match('/iPhone/i', $ua)) $os = 'iPhone';
    elseif (preg_match('/iPad/i', $ua)) $os = 'iPad';
    elseif (preg_match('/Android/i', $ua)) $os = 'Android';
    elseif (preg_match('/Windows/i', $ua)) $os = 'Windows';
    elseif (preg_match('/Macintosh|Mac OS/i', $ua)) $os = 'Mac';
    elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';
    $br = '';
    if (preg_match('/Edg\//i', $ua)) $br = 'Edge';
    elseif (preg_match('/CriOS|Chrome/i', $ua)) $br = 'Chrome';
    elseif (preg_match('/FxiOS|Firefox/i', $ua)) $br = 'Firefox';
    elseif (preg_match('/Safari/i', $ua)) $br = 'Safari';
    return trim($os . ($br ? ' · ' . $br : ''));
}

/**
 * Journal de connexion : quels comptes se sont connectés, depuis quels
 * appareils, quand et d'où. Réservé à la clé d'intégration.
 * Param option : days (fenêtre, défaut 60), limit (défaut 500).
 */
function lfi_nct_ingest_rest_activity_log($request) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_activity';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t)) !== $t) {
        return new WP_REST_Response(['ok' => false, 'error' => 'table_absente'], 404);
    }
    $days  = max(1, min(365, (int) ($request->get_param('days') ?: 60)));
    $limit = max(1, min(2000, (int) ($request->get_param('limit') ?: 500)));
    $since = wp_date('Y-m-d H:i:s', current_time('timestamp') - $days * DAY_IN_SECONDS);
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, event, ip, ua, created_at FROM $t WHERE created_at >= %s ORDER BY created_at DESC LIMIT %d",
        $since, $limit
    )) ?: [];

    /* Regroupé par compte : appareils distincts, dernière activité, nb. */
    $byuser = [];
    foreach ($rows as $r) {
        $uid = (int) $r->user_id;
        if (!isset($byuser[$uid])) {
            $u = get_userdata($uid);
            $byuser[$uid] = [
                'user_id'    => $uid,
                'nom'        => $u ? ($u->display_name ?: $u->user_login) : ('#' . $uid),
                'email'      => $u ? $u->user_email : '',
                'connexions' => 0,
                'derniere'   => (string) $r->created_at,
                'appareils'  => [],
                'ips'        => [],
            ];
        }
        $byuser[$uid]['connexions']++;
        $dev = lfi_nct_ua_device($r->ua);
        if (!isset($byuser[$uid]['appareils'][$dev])) $byuser[$uid]['appareils'][$dev] = 0;
        $byuser[$uid]['appareils'][$dev]++;
        if ($r->ip) $byuser[$uid]['ips'][(string) $r->ip] = true;
    }
    $out = [];
    foreach ($byuser as $u) {
        $appareils = [];
        foreach ($u['appareils'] as $dev => $n) $appareils[] = ['appareil' => $dev, 'fois' => $n];
        $out[] = [
            'user_id'    => $u['user_id'],
            'nom'        => $u['nom'],
            'email'      => $u['email'],
            'connexions' => $u['connexions'],
            'derniere'   => $u['derniere'],
            'appareils'  => $appareils,
            'ips'        => array_keys($u['ips']),
        ];
    }
    /* Tri par dernière activité décroissante. */
    usort($out, function ($a, $b) { return strcmp($b['derniere'], $a['derniere']); });
    return new WP_REST_Response(['ok' => true, 'fenetre_jours' => $days, 'comptes' => count($out), 'log' => $out], 200);
}

/** Ajoute une ligne de frais d'accompagnement à un dossier (→ préjudice/avocat). */
function lfi_nct_ingest_rest_frais_add($request) {
    if (!function_exists('lfi_nct_frais_log')) return new WP_REST_Response(['ok' => false, 'error' => 'module_absent'], 404);
    $did  = (int) $request->get_param('dossier_id');
    if (!$did) return new WP_REST_Response(['ok' => false, 'error' => 'dossier_manquant'], 400);
    $type = sanitize_key((string) $request->get_param('type'));
    $desc = sanitize_text_field((string) $request->get_param('description'));
    $m    = $request->get_param('montant');
    $montant = ($m === null || $m === '') ? null : (float) $m;
    $id = lfi_nct_frais_log($did, $type ?: 'autre', $desc, $montant, 'rest');
    if (!$id) return new WP_REST_Response(['ok' => false, 'error' => 'insertion_impossible'], 500);
    return new WP_REST_Response(['ok' => true, 'id' => $id, 'total' => lfi_nct_frais_total($did)], 200);
}

/** Liste des frais d'accompagnement d'un dossier + total. */
function lfi_nct_ingest_rest_frais_list($request) {
    if (!function_exists('lfi_nct_frais_list')) return new WP_REST_Response(['ok' => false, 'error' => 'module_absent'], 404);
    $did = (int) $request->get_param('dossier_id');
    $rows = lfi_nct_frais_list($did);
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['id' => (int) $r->id, 'date' => $r->date_frais, 'type' => $r->type, 'description' => $r->description, 'montant' => (float) $r->montant, 'src' => $r->src];
    }
    return new WP_REST_Response(['ok' => true, 'frais' => $out, 'total' => lfi_nct_frais_total($did)], 200);
}

/** Liste des interventions (facturation travaux) — filtre option par nom locataire. */
function lfi_nct_ingest_rest_interventions_list($request) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_interventions';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t)) !== $t) return new WP_REST_Response(['ok' => false, 'error' => 'table_absente'], 404);
    $nom = sanitize_text_field((string) $request->get_param('tenant'));
    if ($nom !== '') {
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE tenant_nom LIKE %s OR tenant_prenom LIKE %s ORDER BY date_intervention DESC LIMIT 200", '%' . $wpdb->esc_like($nom) . '%', '%' . $wpdb->esc_like($nom) . '%')) ?: [];
    } else {
        $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY date_intervention DESC LIMIT 200") ?: [];
    }
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int) $r->id,
            'tenant' => trim($r->tenant_prenom . ' ' . $r->tenant_nom),
            'date' => $r->date_intervention,
            'type' => $r->type_travaux,
            'categorie' => $r->categorie_travaux,
            'total_ht' => (float) $r->total_ht,
            'statut' => $r->statut,
            'facture' => $r->facture_numero,
        ];
    }
    return new WP_REST_Response(['ok' => true, 'interventions' => $out], 200);
}

/**
 * Reclasse une intervention FACTURÉE en frais d'accompagnement (→ avocat) :
 * on annule son statut de facture et on crée une ligne de frais dans le dossier
 * du locataire. Sert à corriger des factures émises à tort à NMH.
 */
function lfi_nct_ingest_rest_intervention_reclass($request) {
    global $wpdb;
    $ti = $wpdb->prefix . 'lfi_nct_interventions';
    $iid = (int) $request->get_param('intervention_id');
    $r = $iid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $ti WHERE id = %d", $iid)) : null;
    if (!$r) return new WP_REST_Response(['ok' => false, 'error' => 'intervention_introuvable'], 404);

    /* Dossier cible : fourni, sinon retrouvé par nom du locataire. */
    $did = (int) $request->get_param('dossier_id');
    if (!$did) {
        $td = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
        $did = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $td WHERE tenant_nom = %s ORDER BY id ASC LIMIT 1", $r->tenant_nom));
    }
    if (!$did) return new WP_REST_Response(['ok' => false, 'error' => 'dossier_introuvable_pour_locataire'], 400);

    $type = sanitize_key((string) $request->get_param('type')) ?: 'visite';
    $m    = $request->get_param('montant');
    $montant = ($m === null || $m === '') ? (float) $r->total_ht : (float) $m;
    $desc = sanitize_text_field((string) $request->get_param('description'));
    if ($desc === '') $desc = 'Reclassement facture NMH → frais : ' . ($r->type_travaux ?: 'visite/constat') . ' du ' . $r->date_intervention;

    $fid = function_exists('lfi_nct_frais_log') ? lfi_nct_frais_log($did, $type, $desc, $montant, 'reclass') : 0;

    /* On annule la facture côté intervention (plus de facture directe NMH). */
    $wpdb->update($ti, [
        'statut'         => 'reclasse_frais',
        'facture_numero' => null,
        'facture_date'   => null,
    ], ['id' => $iid]);

    return new WP_REST_Response(['ok' => true, 'intervention_id' => $iid, 'dossier_id' => $did, 'frais_id' => $fid, 'montant' => $montant], 200);
}

/**
 * Crée ou met à jour une PAGE WordPress (par slug) avec un contenu (ex. un
 * shortcode), et peut la définir comme page d'accueil du site. RÉVERSIBLE :
 * l'ancien réglage d'accueil est sauvegardé (option lfi_nct_prev_front) et
 * l'ancienne page n'est jamais supprimée. Réservé à la clé d'intégration.
 */
function lfi_nct_ingest_rest_page_set($request) {
    $slug    = sanitize_title((string) $request->get_param('slug'));
    $title   = sanitize_text_field((string) $request->get_param('title'));
    $content = (string) $request->get_param('content');
    if ($slug === '') return new WP_REST_Response(['ok' => false, 'error' => 'slug_manquant'], 400);

    $existing = get_page_by_path($slug, OBJECT, 'page');
    $arr = [
        'post_title'   => $title !== '' ? $title : $slug,
        'post_name'    => $slug,
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_type'    => 'page',
    ];
    if ($existing) { $arr['ID'] = (int) $existing->ID; $pid = wp_update_post($arr, true); }
    else           { $pid = wp_insert_post($arr, true); }
    if (is_wp_error($pid)) return new WP_REST_Response(['ok' => false, 'error' => $pid->get_error_message()], 500);
    $pid = (int) $pid;

    $front = false;
    if ($request->get_param('set_front')) {
        /* Sauvegarde réversible de l'accueil actuel. */
        if (get_option('lfi_nct_prev_front', null) === null) {
            update_option('lfi_nct_prev_front', [
                'show_on_front' => get_option('show_on_front'),
                'page_on_front' => (int) get_option('page_on_front'),
            ], false);
        }
        update_option('show_on_front', 'page');
        update_option('page_on_front', $pid);
        $front = true;
    }
    return new WP_REST_Response([
        'ok'    => true,
        'id'    => $pid,
        'url'   => get_permalink($pid),
        'front' => $front,
    ], 200);
}

/** Ajoute une annonce « quoi de neuf » (pop-up affiché aux membres du GA). */
function lfi_nct_ingest_rest_member_news_add($request) {
    if (!function_exists('lfi_nct_news_add')) return new WP_REST_Response(['ok' => false, 'error' => 'module_absent'], 404);
    $titre = sanitize_text_field((string) $request->get_param('titre'));
    $corps = (string) $request->get_param('corps');
    if ($titre === '' && $corps === '') return new WP_REST_Response(['ok' => false, 'error' => 'vide'], 400);
    $id = lfi_nct_news_add($titre, $corps);
    return new WP_REST_Response(['ok' => true, 'id' => (int) $id], 200);
}

/** Ajoute une note / un rappel dans le Journal de bord (option : épinglé). */
function lfi_nct_ingest_rest_journal_add($request) {
    if (!function_exists('lfi_nct_journal_add')) return new WP_REST_Response(['ok' => false, 'error' => 'journal_absent'], 404);
    $titre = sanitize_text_field((string) $request->get_param('titre'));
    $corps = sanitize_textarea_field((string) $request->get_param('corps'));
    if ($titre === '' && $corps === '') return new WP_REST_Response(['ok' => false, 'error' => 'vide'], 400);
    $args = [
        'date_evt'  => sanitize_text_field((string) ($request->get_param('date') ?: wp_date('Y-m-d'))),
        'categorie' => sanitize_key((string) ($request->get_param('categorie') ?: 'general')),
        'titre'     => $titre,
        'corps'     => $corps,
        'pinned'    => (bool) $request->get_param('pinned'),
    ];
    $ga = $request->get_param('ga');
    if ($ga !== null && $ga !== '') $args['ga'] = sanitize_text_field((string) $ga);
    $id = (int) lfi_nct_journal_add($args);
    if (!$id) return new WP_REST_Response(['ok' => false, 'error' => 'insertion_impossible'], 500);
    return new WP_REST_Response(['ok' => true, 'id' => $id], 200);
}

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

/**
 * Supprime une (ou des) réponse(s) d'un dossier.
 * @param int      $dossier_id
 * @param int|null $index Index à supprimer (dans notes['replies']). Ignoré si $src fourni.
 * @param string   $src   Si fourni, supprime toutes les réponses de cette source (ex: 'mailcheck').
 * @return int Nombre de réponses restantes, ou -1 si dossier introuvable.
 */
function lfi_nct_reply_delete($dossier_id, $index = null, $src = '') {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", (int) $dossier_id));
    if (!$row) return -1;
    $notes = json_decode($row->notes ?? '', true);
    if (!is_array($notes) || empty($notes['replies']) || !is_array($notes['replies'])) return 0;
    if ($src !== '') {
        $notes['replies'] = array_values(array_filter($notes['replies'], function ($r) use ($src) {
            return (string) ($r['src'] ?? '') !== $src;
        }));
    } elseif ($index !== null && isset($notes['replies'][(int) $index])) {
        array_splice($notes['replies'], (int) $index, 1);
        $notes['replies'] = array_values($notes['replies']);
    }
    $wpdb->update($t, ['notes' => wp_json_encode($notes), 'updated_at' => current_time('mysql')], ['id' => (int) $dossier_id]);
    return count($notes['replies']);
}

/** REST : supprimer une réponse (par index) ou purger une source (src). */
function lfi_nct_ingest_rest_reply_delete($request) {
    $id  = (int) $request->get_param('dossier_id');
    $src = sanitize_key((string) $request->get_param('src'));
    $idx = $request->get_param('index');
    $idx = ($idx === null || $idx === '') ? null : (int) $idx;
    $left = lfi_nct_reply_delete($id, $idx, $src);
    if ($left === -1) return new WP_REST_Response(['ok' => false, 'error' => 'dossier_introuvable'], 404);
    return new WP_REST_Response(['ok' => true, 'restant' => $left], 200);
}

/** Petit formulaire « supprimer ce brouillon » (usage app, nonce + capacité). */
function lfi_nct_reply_del_form($dossier_id, $index, $back = '') {
    $out  = '<form method="post" style="display:inline" onsubmit="return confirm(\'Supprimer ce brouillon ?\');">';
    $out .= wp_nonce_field('lfi_reply_del', '_wpnonce', true, false);
    $out .= '<input type="hidden" name="lfi_reply_del" value="1">';
    $out .= '<input type="hidden" name="dossier_id" value="' . (int) $dossier_id . '">';
    $out .= '<input type="hidden" name="reply_index" value="' . (int) $index . '">';
    if ($back !== '') $out .= '<input type="hidden" name="back" value="' . esc_attr($back) . '">';
    $out .= '<button type="submit" class="btn-ghost" style="padding:4px 10px;font-size:.82em;color:#c8102e">🗑 Supprimer</button>';
    $out .= '</form>';
    return $out;
}

/**
 * Gros bouton « LIRE LA RÉPONSE » (déplié = texte intégral). Volontairement
 * grand et distinct du bouton d'envoi, pour qu'on ne puisse PAS envoyer sans
 * avoir lu (le bouton d'envoi est séparé par un trait en dessous).
 */
function lfi_nct_reply_read_button($body) {
    return '<details class="lfi-lire" style="margin:10px 0">'
        . '<summary style="cursor:pointer;list-style:none;display:block;background:#0066a3;color:#fff;font-weight:800;font-size:1.05em;padding:13px 14px;border-radius:10px;text-align:center">📖 LIRE LA RÉPONSE EN ENTIER (avant d\'envoyer)</summary>'
        . '<div class="com" style="white-space:pre-wrap;background:#f7f7f7;border-radius:6px;padding:12px;margin-top:8px;line-height:1.5">' . esc_html((string) $body) . '</div>'
        . '</details>';
}

/** Email principal (hub) mis en copie de chaque réponse pour tout centraliser. */
function lfi_nct_central_email() {
    return (string) get_option('lfi_nct_central_email', 'nantessudclostoreau@gmail.com');
}

/** Lien qui ouvre l'APPLICATION Gmail (mobile) avec la réponse en brouillon. */
function lfi_nct_gmail_compose_url($to, $subject, $body, $cc = '') {
    $url = 'googlegmail:///co?to=' . rawurlencode($to)
        . '&subject=' . rawurlencode($subject)
        . '&body=' . rawurlencode($body);
    if ($cc !== '') $url .= '&cc=' . rawurlencode($cc);
    return $url;
}

/** Repli : lien Gmail web (si l'appli n'est pas installée / sur ordinateur). */
function lfi_nct_gmail_compose_url_web($to, $subject, $body, $cc = '') {
    $url = 'https://mail.google.com/mail/?view=cm&fs=1'
        . '&to=' . rawurlencode($to)
        . '&su=' . rawurlencode($subject)
        . '&body=' . rawurlencode($body);
    if ($cc !== '') $url .= '&cc=' . rawurlencode($cc);
    return $url;
}

/* ============================================================== *
 *  ÉCRAN SIMPLE « À envoyer » : toutes les réponses prêtes,       *
 *  tous dossiers confondus, en un seul endroit.                   *
 * ============================================================== */
function lfi_nct_app_view_a_envoyer() {
    $can = current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $owner = function_exists('lfi_nct_dossier_owner_id') ? (int) lfi_nct_dossier_owner_id() : 0;
    /* Un membre non super-admin ne voit que ses dossiers confiés. */
    $ref = (function_exists('lfi_nct_dossier_sees_all') && !lfi_nct_dossier_sees_all())
        ? $wpdb->prepare(' AND referent_user_id = %d', get_current_user_id()) : '';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE owner_user_id = %d" . $ref . " ORDER BY updated_at DESC LIMIT 200", $owner)) ?: [];

    lfi_nct_app_screen_open('📥 À envoyer', 'Tes réponses prêtes — relis et envoie');
    if (!empty($_GET['rdel']) && function_exists('lfi_nct_app_flash')) lfi_nct_app_flash('🗑 Brouillon supprimé.');
    if (!empty($_GET['ok']) && function_exists('lfi_nct_app_flash')) lfi_nct_app_flash('✅ Réponse générée — relis-la puis ouvre-la dans Gmail.');
    $central = lfi_nct_central_email();
    $n = 0;
    echo '<ul class="lfi-app-list">';
    foreach ($rows as $r) {
        $notes = json_decode($r->notes ?? '', true);
        $replies = (is_array($notes) && !empty($notes['replies'])) ? $notes['replies'] : [];
        if (empty($replies)) continue;
        $who = trim($r->tenant_prenom . ' ' . $r->tenant_nom) ?: ('Dossier #' . $r->id);
        foreach (array_reverse($replies, true) as $ri => $rep) {
            $to  = (string) ($rep['to'] ?? '');
            $sub = (string) ($rep['subject'] ?? '');
            $bod = (string) ($rep['body'] ?? '');
            if ($to === '' || $bod === '') continue;
            $n++;
            $cc = ($central && stripos($to, $central) === false) ? $central : '';
            $url    = lfi_nct_gmail_compose_url($to, $sub, $bod, $cc);
            $urlweb = lfi_nct_gmail_compose_url_web($to, $sub, $bod, $cc);
            echo '<li class="lfi-app-card" style="border-left:4px solid #186a3b">';
            echo '<div class="head"><div class="who">🗂 ' . esc_html($who) . '</div></div>';
            echo '<div class="meta"><span class="meta-chip">À : ' . esc_html($to) . '</span></div>';
            if ($sub) echo '<div class="com"><strong>' . esc_html($sub) . '</strong></div>';
            echo lfi_nct_reply_read_button($bod);
            echo '<div style="height:1px;background:#e0e0e0;margin:12px 0"></div>';
            echo '<div class="row-actions" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;align-items:center"><a class="btn-primary" style="background:#186a3b" href="' . esc_attr($url) . '">✅ Ouvrir dans l\'appli Gmail (brouillon)</a>' . lfi_nct_reply_del_form((int) $r->id, (int) $ri, lfi_nct_app_url('a-envoyer')) . '</div>';
            echo '<div class="lfi-app-help" style="margin-top:4px"><small>L\'appli Gmail s\'ouvre avec la réponse en brouillon, au bon destinataire — relis et appuie sur Envoyer. <a href="' . esc_url($urlweb) . '" target="_blank" rel="noopener">Sinon, ouvrir dans le navigateur</a>.</small></div>';
            echo '</li>';
        }
    }
    echo '</ul>';
    if ($n === 0) echo '<div class="lfi-app-card" style="border:2px solid #186a3b"><div class="com">✅ Rien à envoyer pour l\'instant. Quand une réponse est prête, elle apparaît ici.</div></div>';
    lfi_nct_app_screen_close();
}

/** Rendu de la section « Réponses à envoyer » (validées → bouton Gmail). */
function lfi_nct_render_dossier_replies($row) {
    $notes   = json_decode($row->notes ?? '', true);
    $replies = (is_array($notes) && !empty($notes['replies'])) ? $notes['replies'] : [];
    echo '<h3 id="sec-reponses" style="margin:22px 0 6px;color:#c8102e">✉️ Réponses à envoyer (prêtes)</h3>';

    if (!empty($_GET['nomandat']) && function_exists('lfi_nct_app_flash')) {
        lfi_nct_app_flash('🔒 Impossible d\'écrire à NMH : il faut d\'abord le mandat (adhésion signée au dossier).', 'error');
    }

    /* VERROU MANDAT : pas d'email à NMH tant que l'adhésion n'est pas signée.
       Aucun membre ne peut écrire à NMH sans mandat → le bouton n'apparaît pas. */
    $has_mandate = function_exists('lfi_nct_dossier_has_mandate') ? lfi_nct_dossier_has_mandate($row) : true;
    if (!$has_mandate) {
        $tuid = (int) ($row->tenant_user_id ?? 0);
        $toggle = wp_nonce_url(admin_url('admin-post.php?action=lfi_nct_mandat&id=' . (int) $row->id), 'lfi_nct_mandat_' . (int) $row->id);
        echo '<div class="lfi-app-card" style="border:2px solid #c8102e;background:#fff7f8">';
        echo '<div class="com"><strong>🔒 Étape 1 avant tout courrier : le mandat.</strong><br>On n\'écrit <strong>jamais</strong> à NMH au nom d\'un locataire sans son <strong>adhésion signée</strong> (c\'est ce qui nous autorise à agir pour lui). Tant qu\'elle n\'est pas au dossier, la génération d\'email est bloquée.</div>';
        echo '<div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">';
        if ($tuid) echo '<a class="btn-primary" style="background:#0066a3" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => $tuid])) . '">📲 Inviter le locataire (SMS + RDV pour finir le dossier)</a>';
        echo '<a class="btn-ghost" style="color:#186a3b" href="' . esc_url($toggle) . '">✅ J\'ai fait signer l\'adhésion (mandat obtenu)</a>';
        echo '</div>';
        echo '<div class="lfi-app-help" style="margin-top:6px"><small>Le parcours : envoie-lui son espace (il télécharge l\'app, remplit son dossier), prends RDV pour aller le voir et faire signer l\'adhésion. Ensuite seulement, tu pourras écrire à NMH.</small></div>';
        echo '</div>';
        /* On affiche quand même les réponses DÉJÀ générées (historique), sans bouton de génération. */
        if (empty($replies)) return;
    } else {
        /* Bouton self-service : le membre a vu le locataire → il génère l'email. */
        echo '<div style="margin:4px 0 10px"><a class="btn-primary" style="background:#186a3b" href="' . esc_url(lfi_nct_app_url('generer-reponse', ['id' => (int) $row->id])) . '">✍️ Générer une réponse (le locataire a décidé)</a></div>';
    }
    if (empty($replies)) {
        echo '<div class="lfi-app-help">Quand un email arrive, va voir le locataire, puis clique « Générer une réponse » : choisis ce qu\'il a décidé, l\'email complet à NMH se prépare ici. Tu le relis et tu l\'envoies depuis ta boîte.</div>';
        return;
    }
    echo '<ul class="lfi-app-list">';
    foreach (array_reverse($replies, true) as $i => $r) {
        $to  = (string) ($r['to'] ?? '');
        $sub = (string) ($r['subject'] ?? '');
        $bod = (string) ($r['body'] ?? '');
        /* On met l'email principal (hub) en copie : tout reste centralisé. */
        $central = lfi_nct_central_email();
        $cc = ($central && stripos($to, $central) === false) ? $central : '';
        $url    = lfi_nct_gmail_compose_url($to, $sub, $bod, $cc);
        $urlweb = lfi_nct_gmail_compose_url_web($to, $sub, $bod, $cc);
        echo '<li class="lfi-app-card" style="border-left:4px solid #186a3b">';
        echo '<div class="head"><div class="who">✉️ Réponse prête</div>';
        echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html($r['date'] ?? '') . '</div></div>';
        echo '<div class="meta">';
        if ($to)  echo '<span class="meta-chip">À : ' . esc_html($to) . '</span>';
        if (!empty($r['objet'])) echo '<span class="meta-chip">↩︎ ' . esc_html($r['objet']) . '</span>';
        echo '</div>';
        if ($sub) echo '<div class="com"><strong>Objet :</strong> ' . esc_html($sub) . '</div>';
        echo lfi_nct_reply_read_button($bod);
        echo '<div style="height:1px;background:#e0e0e0;margin:12px 0"></div>';
        echo '<div class="row-actions" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:center"><a class="btn-primary" style="background:#186a3b" href="' . esc_attr($url) . '">✅ Ouvrir dans l\'appli Gmail (brouillon)</a>' . lfi_nct_reply_del_form((int) $row->id, (int) $i, lfi_nct_app_url('dossier', ['uid' => (int) $row->tenant_user_id])) . '</div>';
        echo '<div class="lfi-app-help" style="margin-top:4px"><small>L\'appli Gmail s\'ouvre avec la réponse en brouillon, au bon destinataire — relis et appuie sur Envoyer. <a href="' . esc_url($urlweb) . '" target="_blank" rel="noopener">Sinon, ouvrir dans le navigateur</a>.</small></div>';
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

    /* 🏆 Détection auto : une correspondance reçue qui acte notre demande
       (relogement accordé, travaux programmés…) → coupe du volet urgence. */
    $victoire_auto = false;
    if ($type === 'correspondance_recue' && function_exists('lfi_nct_victoire_detect_from_email') && (int) $row->tenant_user_id) {
        $victoire_auto = (bool) lfi_nct_victoire_detect_from_email((int) $row->tenant_user_id, $objet, $corps, (int) $row->id);
    }

    return new WP_REST_Response([
        'ok'            => true,
        'dossier'       => ['id' => (int) $row->id, 'nom' => trim($row->tenant_prenom . ' ' . $row->tenant_nom)],
        'classe'        => $added,
        'victoire_auto' => $victoire_auto,
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
