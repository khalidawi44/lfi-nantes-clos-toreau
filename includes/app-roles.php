<?php
/**
 * App GA — rôles, comptes, ajout témoignage, dashboards locataires.
 *
 * 3 rôles :
 *  - administrator (toi)          : accès complet
 *  - lfi_nct_ga_member            : événements + adhérents + SMS/email aux adhérents
 *                                   PAS d'accès aux enquêtes ni aux contacts locataires
 *  - lfi_nct_tenant               : son dashboard perso (lié à son enquête)
 *                                   modèles de lettres, droits, conseils, config notifs
 *
 * Les rôles non-admin :
 *  - sont redirigés vers /app/ s'ils tombent sur /wp-admin/
 *  - n'ont pas la barre WP en haut
 *  - voient un dashboard adapté à leur rôle
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_ROLE_GA     = 'lfi_nct_ga_member';
const LFI_NCT_ROLE_TENANT = 'lfi_nct_tenant';

/* ============================================================== *
 *  Rues canoniques du Clos Toreau + auto-correction orthographique *
 * ============================================================== */

/**
 * Liste de rues du quartier (thème pays basque) que l'app suggère
 * par défaut. La liste s'étoffe automatiquement avec les adresses
 * déjà saisies dans les enquêtes (cf. lfi_nct_known_addresses()).
 */
function lfi_nct_clos_toreau_streets() {
    $streets = [
        "rue d'Ascain",
        "rue de Biarritz",
        "rue d'Hendaye",
        "rue de Saint-Jean-de-Luz",
        "place du Pays Basque",
    ];
    return apply_filters('lfi_nct_clos_toreau_streets', $streets);
}

/**
 * Corrige les fautes d'orthographe connues sur une adresse
 * et ré-applique une capitalisation propre.
 */
function lfi_nct_normalize_address($input) {
    $input = trim((string) $input);
    if ($input === '') return '';

    /* 1) Mappe les fautes connues vers le nom propre canonique.
          On capture aussi le « d' » ou « d » optionnel devant pour ne pas
          le doubler ensuite. */
    $corrections = [
        // Saint-Jean-de-Luz et variantes (Luse / Luz / sans tirets)
        '/\bsaint[- ]?jean[- ]?de[- ]?lu(?:se|z|s)\b/iu' => 'Saint-Jean-de-Luz',
        '/\bst[- ]?jean[- ]?de[- ]?lu(?:se|z|s)\b/iu'    => 'Saint-Jean-de-Luz',
        // Hendaye et variantes
        "/\b(?:d['’\s])?dandaille?\b/iu"                  => "d'Hendaye",
        "/\b(?:d['’\s])?endaille?\b/iu"                   => "d'Hendaye",
        "/\b(?:d['’\s])?hendaille?\b/iu"                  => "d'Hendaye",
        "/\b(?:d['’\s])?dendaye\b/iu"                     => "d'Hendaye",
        // Ascain et variantes (Asquin)
        "/\b(?:d['’\s])?asquin\b/iu"                      => "d'Ascain",
        // Biarritz
        '/\bbiarit(?:z|s)\b/iu'  => 'Biarritz',
        '/\bbiarritze\b/iu'      => 'Biarritz',
        '/\bbiarits\b/iu'        => 'Biarritz',
        // Pays Basque
        '/\bpays[- ]?basque\b/iu' => 'Pays Basque',
    ];
    foreach ($corrections as $pat => $rep) {
        $input = preg_replace($pat, $rep, $input);
    }

    /* 2) Met les types de voie en minuscules */
    $input = preg_replace_callback(
        '/\b(rue|place|avenue|boulevard|impasse|all[ée]+e|chemin|passage|quai|square)\b/iu',
        function ($m) { return mb_strtolower($m[1]); },
        $input
    );

    /* 3) Title-case sur les noms propres canoniques (corrige les ALL CAPS) */
    $proper_nouns = [
        'Hendaye', 'Biarritz', 'Ascain', 'Saint-Jean-de-Luz',
        'Bayonne', 'Pau', 'Dax', 'Anglet', 'Hasparren',
        'Pays Basque', 'Pays-Basque',
    ];
    foreach ($proper_nouns as $name) {
        $input = preg_replace('/\b' . preg_quote($name, '/') . '\b/iu', $name, $input);
    }

    /* 4) « D'HENDAYE », « d hendaye », « d ascain » → « d'Hendaye » / « d'Ascain »
          - flag /i pour matcher quelle que soit la casse
          - on lowercase la lettre d'élision, on uppercase la première du nom propre */
    $input = preg_replace_callback(
        "/\b([dlnst])[ '’]([a-zà-ÿ])/iu",
        function ($m) { return mb_strtolower($m[1]) . "'" . mb_strtoupper($m[2]); },
        $input
    );

    /* 5) Pour les rues qui devraient avoir « d' » mais où l'utilisateur a
          tapé juste « rue Hendaye » : on injecte la liaison correcte. */
    $input = preg_replace(
        '/\brue\s+(Hendaye|Ascain|Anglet|Hasparren)\b/u',
        "rue d'$1",
        $input
    );

    /* 6) Espaces multiples → simple */
    $input = preg_replace('/\s+/u', ' ', trim($input));

    return $input;
}

/**
 * Clé canonique d'une adresse — sert à regrouper les variantes
 * orthographiques d'une même rue : « rue Saint-Jean-de-Luz », « rue st
 * jean de luse », « Rue de Saint-Jean de Luz » → tous la même clé.
 *
 * On normalise d'abord, puis on retire numéro + type de voie + article,
 * on lowercase et on garde seulement [a-z0-9].
 */
function lfi_nct_address_canonical_key($adr) {
    $adr = lfi_nct_normalize_address((string) $adr);
    $key = remove_accents($adr);
    $key = mb_strtolower($key);
    // Retire « 12 », « 12bis », « 14 ter », etc. en tête
    $key = preg_replace('/^\s*\d+\s*(bis|ter|quater)?\s*/iu', '', $key);
    // Retire le type de voie + article éventuel
    // Ordre crucial : « de\s+la » > « de » > « d['] » pour ne pas matcher
    // juste « d » dans « de saint » et laisser un « e » orphelin.
    $key = preg_replace(
        "/^(rue|place|avenue|boulevard|impasse|allee|chemin|passage|quai|square)(\s+(de\s+la|de\s+l['’]|des|du|de|d['’]|la|le|les|l['’]))?\s*/iu",
        '',
        $key
    );
    // Garde seulement alphanum
    $key = preg_replace('/[^a-z0-9]+/u', '', $key);
    return $key;
}

/**
 * Forme affichable canonique d'une adresse (normalize + numéro conservé).
 * Utile dans les listes (« top des adresses ») pour avoir un libellé propre.
 */
function lfi_nct_address_canonical_display($adr) {
    return lfi_nct_normalize_address((string) $adr);
}

/**
 * Datalist combinant rues canoniques + adresses déjà saisies.
 */
function lfi_nct_streets_datalist($id = 'lfi-nct-known-streets') {
    $items = [];
    foreach (lfi_nct_clos_toreau_streets() as $s) $items[$s] = true;
    if (function_exists('lfi_nct_known_addresses')) {
        foreach (lfi_nct_known_addresses() as $a) $items[$a] = true;
    }
    $list = array_keys($items);
    sort($list, SORT_NATURAL | SORT_FLAG_CASE);
    $out = '<datalist id="' . esc_attr($id) . '">';
    foreach ($list as $a) {
        $out .= '<option value="' . esc_attr($a) . '">';
    }
    return $out . '</datalist>';
}

/* ============================================================== *
 *  Types de problèmes : base + appris automatiquement              *
 * ============================================================== */

function lfi_nct_problem_types_base() {
    return [
        'degats_eaux'      => '💧 Dégâts des eaux / fuites',
        'humidite'         => '🌫 Humidité / moisissures',
        'insectes'         => '🐜 Nuisibles (cafards, rats, punaises)',
        'chauffage'        => '🥶 Chauffage défaillant',
        'electricite'      => '⚡ Électricité',
        'ascenseur'        => '🛗 Ascenseur en panne',
        'parties_communes' => '🚪 Parties communes dégradées',
        'bruit'            => '🔊 Nuisances sonores',
        'securite'         => '🚨 Insécurité',
    ];
}

function lfi_nct_problem_types_custom() {
    $opt = get_option('lfi_nct_custom_problem_labels', []);
    return is_array($opt) ? $opt : [];
}

function lfi_nct_problem_types_all() {
    return array_merge(lfi_nct_problem_types_base(), lfi_nct_problem_types_custom());
}

/**
 * Slug stable pour une étiquette libre (clé de la checkbox).
 */
function lfi_nct_problem_slug($label) {
    $label = trim($label);
    if ($label === '') return '';
    $slug = sanitize_title($label);
    $slug = str_replace('-', '_', $slug);
    if (strlen($slug) > 40) $slug = substr($slug, 0, 40);
    return 'custom_' . $slug;
}

/**
 * Ajoute (si nouvelle) une étiquette de problème personnalisée à
 * l'option WordPress. Renvoie le slug attribué.
 */
function lfi_nct_learn_custom_problem($label) {
    $label = trim((string) $label);
    if ($label === '' || mb_strlen($label) > 80) return '';
    /* Préfixe par un emoji neutre si l'utilisateur n'en a pas mis */
    if (!preg_match('/^\p{So}|^\p{S}|^[^\w\s]/u', $label)) {
        $display = '🏠 ' . ucfirst($label);
    } else {
        $display = $label;
    }
    $slug = lfi_nct_problem_slug($label);
    if (!$slug) return '';

    $custom = lfi_nct_problem_types_custom();
    /* Ne pas dupliquer (même slug ou label identique en lowercase) */
    foreach ($custom as $k => $existing) {
        if ($k === $slug) return $k;
        if (mb_strtolower(trim($existing)) === mb_strtolower($display)) return $k;
    }
    /* Idem : ne pas dupliquer un label déjà dans la base */
    foreach (lfi_nct_problem_types_base() as $existing) {
        if (mb_strtolower(trim($existing)) === mb_strtolower($display)) return null;
    }
    $custom[$slug] = $display;
    update_option('lfi_nct_custom_problem_labels', $custom, false);
    return $slug;
}

/**
 * Supprime une étiquette personnalisée (utilitaire admin).
 */
function lfi_nct_forget_custom_problem($slug) {
    $custom = lfi_nct_problem_types_custom();
    if (isset($custom[$slug])) {
        unset($custom[$slug]);
        update_option('lfi_nct_custom_problem_labels', $custom, false);
        return true;
    }
    return false;
}

/* ============================================================== *
 *  Création des rôles                                              *
 * ============================================================== */
add_action('init', 'lfi_nct_setup_roles', 4);
function lfi_nct_setup_roles() {
    if (get_option('lfi_nct_roles_v') === '2') return;
    if (!get_role(LFI_NCT_ROLE_GA)) {
        add_role(LFI_NCT_ROLE_GA, 'Membre du GA LFI', ['read' => true]);
    }
    if (!get_role(LFI_NCT_ROLE_TENANT)) {
        add_role(LFI_NCT_ROLE_TENANT, 'Locataire suivi par le GA', ['read' => true]);
    }
    update_option('lfi_nct_roles_v', '2', false);
}

/* ============================================================== *
 *  Helpers de rôle                                                  *
 * ============================================================== */
function lfi_nct_user_role_ga() {
    if (!is_user_logged_in()) return false;
    $u = wp_get_current_user();
    return in_array(LFI_NCT_ROLE_GA, (array) $u->roles, true);
}
function lfi_nct_user_role_tenant() {
    if (!is_user_logged_in()) return false;
    $u = wp_get_current_user();
    return in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true);
}
function lfi_nct_user_tenant_response_id($user_id = 0) {
    if (!$user_id) $user_id = get_current_user_id();
    return (int) get_user_meta($user_id, 'lfi_nct_response_id', true);
}

/* ============================================================== *
 *  Bloque wp-admin pour les non-admins : redirection vers /app/   *
 * ============================================================== */
add_action('admin_init', 'lfi_nct_block_admin_for_non_admins', 1);
function lfi_nct_block_admin_for_non_admins() {
    if (!is_user_logged_in())                                  return;
    if (defined('DOING_AJAX') && DOING_AJAX)                   return;
    if (current_user_can('manage_options'))                    return;
    if (current_user_can('edit_others_posts'))                 return;
    $u = wp_get_current_user();
    $roles = (array) $u->roles;
    if (in_array(LFI_NCT_ROLE_GA, $roles, true) ||
        in_array(LFI_NCT_ROLE_TENANT, $roles, true)) {
        wp_safe_redirect(home_url('/app/'));
        exit;
    }
}

add_action('after_setup_theme', 'lfi_nct_hide_admin_bar_for_non_admins');
function lfi_nct_hide_admin_bar_for_non_admins() {
    if (!is_user_logged_in()) return;
    if (current_user_can('manage_options')) return;
    $u = wp_get_current_user();
    $roles = (array) $u->roles;
    if (in_array(LFI_NCT_ROLE_GA, $roles, true) ||
        in_array(LFI_NCT_ROLE_TENANT, $roles, true)) {
        show_admin_bar(false);
    }
}

/* Redirection post-login : non-admin → /app/ */
add_filter('login_redirect', 'lfi_nct_login_redirect_to_app', 10, 3);
function lfi_nct_login_redirect_to_app($redirect_to, $requested, $user) {
    if (!$user || is_wp_error($user)) return $redirect_to;
    $roles = (array) $user->roles;
    if (in_array(LFI_NCT_ROLE_GA, $roles, true) ||
        in_array(LFI_NCT_ROLE_TENANT, $roles, true)) {
        return home_url('/app/');
    }
    return $redirect_to;
}

/* ============================================================== *
 *  VERROUILLAGE TOTAL : aucun affichage WordPress visible          *
 *  pour les non-admins. Tout est aspiré dans /app/.               *
 * ============================================================== */

/**
 * 1) /wp-login.php en GET → /app/ (la page de connexion est dans l'app).
 *    Les POST (formulaire de login) restent acceptés normalement.
 *    Les actions de récupération de mot de passe restent accessibles
 *    pour pouvoir se servir des liens email reçus.
 */
add_action('login_init', 'lfi_nct_block_wp_login_display', 1);
function lfi_nct_block_wp_login_display() {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method !== 'GET') return; // les soumissions de formulaire passent

    $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
    /* On laisse passer les flux de récupération / confirmation / déconnexion */
    $allowed_actions = ['lostpassword', 'retrievepassword', 'rp', 'resetpass', 'postpass', 'logout', 'confirmaction'];
    if (in_array($action, $allowed_actions, true)) return;

    wp_safe_redirect(home_url('/app/'));
    exit;
}

/**
 * 2) Cage : non-admin connecté ne voit que /app/ (et un tout petit
 *    nombre de chemins critiques : login/logout, manifest PWA, RSVP
 *    de la réunion).
 */
function lfi_nct_non_admin_allowed_path($path) {
    $path = '/' . ltrim($path, '/');
    $prefixes = [
        '/app',                       // /app et /app/...
        '/wp-login.php',              // pour la déconnexion / récup mdp
        '/wp-admin/admin-ajax.php',   // AJAX standard si jamais
        '/lfi-app-',                  // manifest, SW, icônes
        '/reunion-26-juin-2026',      // la page RSVP critique
    ];
    foreach ($prefixes as $p) {
        if ($path === $p || strpos($path, $p . '/') === 0) return true;
        if ($p === '/lfi-app-' && strpos($path, $p) === 0) return true;
    }
    return false;
}

add_action('template_redirect', 'lfi_nct_cage_non_admin_in_app', 1);
function lfi_nct_cage_non_admin_in_app() {
    if (!is_user_logged_in()) return;
    if (current_user_can('manage_options')) return; // toi : libre
    $u = wp_get_current_user();
    $roles = (array) $u->roles;
    $is_caged = in_array(LFI_NCT_ROLE_GA, $roles, true) ||
                in_array(LFI_NCT_ROLE_TENANT, $roles, true);
    /* Par sécurité : tout utilisateur connecté sans capacité d'édition
       et sans rôle métier est aussi cagé (s'il a un compte WP par défaut
       il finit forcément dans l'app — jamais sur le front du thème). */
    if (!$is_caged && !current_user_can('edit_posts')) $is_caged = true;
    if (!$is_caged) return;

    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    if (lfi_nct_non_admin_allowed_path($path)) return;

    wp_safe_redirect(home_url('/app/'));
    exit;
}

/**
 * 3) API REST : retire l'endpoint /wp/v2/users pour les non-admins
 *    (sinon n'importe qui peut énumérer les comptes existants).
 */
add_filter('rest_endpoints', 'lfi_nct_strip_rest_users_for_non_admins');
function lfi_nct_strip_rest_users_for_non_admins($endpoints) {
    if (current_user_can('list_users')) return $endpoints;
    foreach (array_keys($endpoints) as $k) {
        if (strpos($k, '/wp/v2/users') === 0) unset($endpoints[$k]);
    }
    return $endpoints;
}

/**
 * 4) Bloque /?author=N et les archives auteur : énumération évitée.
 */
add_action('template_redirect', 'lfi_nct_block_author_enumeration', 2);
function lfi_nct_block_author_enumeration() {
    if (isset($_GET['author']) || is_author()) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
}

/**
 * 5) Désactive xmlrpc.php (vecteur d'attaque, et aucune utilité ici).
 */
add_filter('xmlrpc_enabled', '__return_false');

/**
 * 6) Aucune barre d'admin WP, JAMAIS, sauf pour l'admin lui-même.
 *    (déjà branché plus haut, on durcit ici)
 */
add_action('init', 'lfi_nct_force_hide_admin_bar', 99);
function lfi_nct_force_hide_admin_bar() {
    if (!is_user_logged_in()) return;
    if (current_user_can('manage_options')) return;
    show_admin_bar(false);
    add_filter('show_admin_bar', '__return_false', 99);
}

/**
 * 7) wp_loaded : si un non-admin tombe sur n'importe quelle requête
 *    admin (admin.php, edit.php, post-new.php...), on l'éjecte avant
 *    même que le tableau de bord ne tente de se rendre.
 *    (Complète la redirection admin_init déjà en place.)
 */
add_action('wp_loaded', 'lfi_nct_eject_non_admin_from_admin', 1);
function lfi_nct_eject_non_admin_from_admin() {
    if (!is_admin()) return;
    if (!is_user_logged_in()) return;
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (current_user_can('manage_options')) return;
    wp_safe_redirect(home_url('/app/'));
    exit;
}

/**
 * 8) Rebranding du formulaire wp-login.php (mot de passe oublié,
 *    récupération de compte) aux couleurs LFI Clos Toreau, pour que
 *    les non-admins ne voient jamais le « WordPress » d'origine.
 */
add_filter('login_headerurl',  function() { return home_url('/app/'); });
add_filter('login_headertext', function() { return 'GA LFI Nantes Sud Clos Toreau'; });
add_action('login_enqueue_scripts', 'lfi_nct_skin_wp_login');
function lfi_nct_skin_wp_login() {
    $manifest = esc_url(home_url('/?lfi_app=manifest&v=' . LFI_NCT_VERSION));
    ?>
    <link rel="manifest" href="<?php echo $manifest; ?>">
    <meta name="theme-color" content="#c8102e">
    <style>
    body.login { background: #f5f5f5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body.login #login { padding: 30px 14px; max-width: 420px; }
    body.login h1 a {
        background: none !important;
        background-image: none !important;
        width: 100% !important; height: auto !important;
        text-indent: 0 !important; color: #c8102e !important;
        font-size: 1.6em; font-weight: 800; letter-spacing: .5px;
        line-height: 1.2; text-decoration: none !important;
        padding: 0 0 14px;
    }
    body.login h1 a::before {
        content: "Φ"; display: block;
        font-size: 2.2em; line-height: 1; color: #c8102e;
        margin-bottom: 8px;
    }
    body.login form {
        background: #fff; border: 0; padding: 22px;
        border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,.06);
    }
    body.login label { color: #555; font-size: .9em; }
    body.login input[type=text], body.login input[type=password], body.login input[type=email] {
        font-size: 1.05em; padding: 12px 14px; border: 1.5px solid #ddd;
        border-radius: 10px; background: #fafafa; box-shadow: none;
    }
    body.login input:focus { border-color: #c8102e; background: #fff; box-shadow: none; outline: none; }
    .wp-core-ui .button-primary, body.login .button-primary, body.login #wp-submit {
        background: #c8102e !important; border-color: #a30b25 !important;
        color: #fff !important; text-shadow: none !important; box-shadow: none !important;
        padding: 12px 18px !important; height: auto !important; line-height: 1 !important;
        border-radius: 12px !important; font-weight: 700 !important; font-size: 1.05em !important;
    }
    .wp-core-ui .button-primary:hover { background: #a30b25 !important; }
    body.login #nav, body.login #backtoblog { text-align: center; padding: 14px 0 0; }
    body.login #nav a, body.login #backtoblog a { color: #c8102e !important; text-decoration: none; font-size: .92em; }
    /* Cache la mention "Powered by WordPress" / le logo en bas */
    body.login .privacy-policy-page-link, body.login #language-switcher { display: none !important; }
    </style>
    <?php
}

/**
 * 9) Force le message de réinitialisation mail à parler en vouvoiement
 *    et au nom du GA, pas de « WordPress ».
 */
add_filter('retrieve_password_message', 'lfi_nct_skin_password_reset_email', 10, 4);
function lfi_nct_skin_password_reset_email($message, $key, $user_login, $user_data) {
    $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');
    return "Bonjour,\n\n"
         . "Vous avez demandé une réinitialisation de votre mot de passe sur l'app du GA LFI Nantes Sud Clos Toreau.\n\n"
         . "Pour définir un nouveau mot de passe, cliquez sur ce lien (valable 24h) :\n\n"
         . $reset_url . "\n\n"
         . "Si vous n'êtes pas à l'origine de cette demande, ignorez simplement ce message — votre mot de passe actuel restera inchangé.\n\n"
         . "Identifiant concerné : " . $user_login . "\n\n"
         . "— Groupe d'Action LFI Nantes Sud Clos Toreau\n"
         . home_url('/app/');
}
add_filter('retrieve_password_title', function() { return '🔑 Réinitialisation de votre mot de passe — GA LFI Clos Toreau'; });
add_filter('wp_mail_from_name', function($name) {
    /* Pour les emails wp_lostpassword : signe « GA LFI » au lieu de l'URL du site */
    if (did_action('retrieve_password')) return 'GA LFI Nantes Sud Clos Toreau';
    return $name;
});

/* ============================================================== *
 *  Génération de mots de passe lisibles                            *
 *  - 10 caractères, sans 0/O/1/l/I pour SMS                       *
 * ============================================================== */
function lfi_nct_app_make_password() {
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $pwd = '';
    $bytes = random_bytes(10);
    for ($i = 0; $i < 10; $i++) {
        $pwd .= $alphabet[ord($bytes[$i]) % strlen($alphabet)];
    }
    /* On insère un tiret pour la lisibilité : abc-de-fghij */
    return substr($pwd, 0, 3) . '-' . substr($pwd, 3, 3) . '-' . substr($pwd, 6);
}

function lfi_nct_app_make_username($prenom, $nom, $base = '') {
    $base = $base ?: strtolower(remove_accents(trim($prenom . '.' . $nom, '.')));
    $base = preg_replace('/[^a-z0-9.]+/', '', $base) ?: 'user';
    $u = $base; $i = 1;
    while (username_exists($u)) {
        $u = $base . $i;
        $i++;
        if ($i > 1000) { $u = $base . '_' . wp_generate_password(4, false, false); break; }
    }
    return $u;
}

/* ============================================================== *
 *  Router : branche sur le rôle                                   *
 *  (appelé par lfi_nct_app_shortcode avant le switch de vues)    *
 * ============================================================== */
function lfi_nct_app_role_dispatch(&$handled) {
    if (lfi_nct_user_role_tenant()) {
        $vue = isset($_GET['vue']) ? sanitize_key($_GET['vue']) : '';
        switch ($vue) {
            case 'lettre':       lfi_nct_app_view_tenant_lettre();   break;
            case 'droits':       lfi_nct_app_view_tenant_droits();   break;
            case 'notifs':       lfi_nct_app_view_tenant_notifs();   break;
            case 'mon-enquete':  lfi_nct_app_view_tenant_enquete();  break;
            case 'envoyer-photo':lfi_nct_app_view_envoyer_photo();   break;
            case 'mon-profil':   lfi_nct_app_view_mon_profil();      break;
            case 'installer':    lfi_nct_app_view_installer();       break;
            case 'mes-rdv':      lfi_nct_app_view_mes_rdv();         break;
            default:             lfi_nct_app_view_tenant_dashboard();
        }
        $handled = true; return;
    }
    if (lfi_nct_user_role_ga()) {
        $vue = isset($_GET['vue']) ? sanitize_key($_GET['vue']) : '';
        switch ($vue) {
            case 'reunion':         lfi_nct_app_view_reunion();    break;
            case 'membres':         lfi_nct_app_view_membres();    break;
            case 'evenements':      lfi_nct_app_view_evenements(); break;
            case 'sms':             lfi_nct_app_view_sms();        break;
            case 'email':           lfi_nct_app_view_email();      break;
            case 'stats':           lfi_nct_app_view_stats();      break;
            case 'mon-profil':      lfi_nct_app_view_mon_profil(); break;
            case 'installer':       lfi_nct_app_view_installer();  break;
            default:                lfi_nct_app_view_ga_dashboard();
        }
        $handled = true; return;
    }
    /* Admin = comportement par défaut, géré dans app.php */
    $handled = false;
}

/* ============================================================== *
 *  Dashboard Membre du GA (rôle restreint)                         *
 * ============================================================== */
function lfi_nct_app_view_ga_dashboard() {
    $user = wp_get_current_user();
    $stats = lfi_nct_app_quick_stats();
    $tiles = [
        ['📲', 'Installer l\'app',          'iPhone / Android · permissions',      lfi_nct_app_url('installer')],
        ['📣', 'Inscrits réunion 26 juin', $stats['reunion'] . ' inscrit(s)',     lfi_nct_app_url('reunion')],
        ['📅', 'Événements',                $stats['events']  . ' événement(s)',   lfi_nct_app_url('evenements')],
        ['👥', 'Adhérents',                 $stats['membres'] . ' adhérent(s)',    lfi_nct_app_url('membres')],
        ['📱', 'Envoyer SMS aux adhérents', 'Modèles + envoi',                     lfi_nct_app_url('sms')],
        ['✉️', 'Email aux adhérents',       'Diffusion ciblée',                    lfi_nct_app_url('email')],
        ['📊', 'Statistiques',              'Vue d\'ensemble',                     lfi_nct_app_url('stats')],
        ['✏️', 'Mon profil',                'Email · mot de passe',                lfi_nct_app_url('mon-profil')],
        ['🚪', 'Se déconnecter',            '',                                    wp_logout_url(home_url('/'))],
    ];
    ?>
    <div class="lfi-app">
        <div class="lfi-app-topbar">
            <div class="lfi-app-logo-mini">Φ</div>
            <div>
                <div class="lfi-app-hi">Bonjour <?php echo esc_html($user->display_name ?: $user->user_login); ?></div>
                <div class="lfi-app-sub2">Membre du GA · console restreinte</div>
            </div>
        </div>

        <div class="lfi-app-help" style="margin:0 0 14px">
            👋 Tu es connecté·e comme membre du GA. Tu peux gérer les événements et écrire aux adhérents, mais pas accéder aux contacts des locataires (réservés aux admins, RGPD).
        </div>

        <div class="lfi-app-grid">
            <?php foreach ($tiles as $t): ?>
                <a class="lfi-app-tile" href="<?php echo esc_url($t[3]); ?>">
                    <div class="ico"><?php echo $t[0]; ?></div>
                    <div class="tit"><?php echo esc_html($t[1]); ?></div>
                    <div class="sub"><?php echo esc_html($t[2]); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/* ============================================================== *
 *  Dashboard locataire suivi (rôle tenant)                        *
 * ============================================================== */
function lfi_nct_app_view_tenant_dashboard() {
    $user = wp_get_current_user();
    $resp_id = lfi_nct_user_tenant_response_id($user->ID);
    $response = null;
    $problem = null;
    if ($resp_id) {
        global $wpdb;
        $response = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d",
            $resp_id
        ));
        if ($response) $problem = lfi_nct_app_enq_problem($response);
    }

    /* Bandeau notif quotidien/hebdo selon préférence */
    $tip_html = lfi_nct_app_tenant_maybe_tip_banner($user->ID);

    /* Mes prochains RDV (PERSONNELS — pas les événements publics du GA) */
    $next_rdv_html = '';
    if (function_exists('lfi_nct_agenda_rdvs_tenant')) {
        $rdvs = lfi_nct_agenda_rdvs_tenant($user->ID, 1);
        if (!empty($rdvs)) {
            $rdv = $rdvs[0];
            $types = function_exists('lfi_nct_agenda_types') ? lfi_nct_agenda_types() : [];
            $type_lbl = $types[$rdv->type] ?? $rdv->type;
            $when = wp_date('l j F', strtotime($rdv->date));
            if (!empty($rdv->heure)) $when .= ' à ' . substr($rdv->heure, 0, 5);
            $next_rdv_html  = '<a class="lfi-app-card lfi-tenant-event" href="' . esc_url(lfi_nct_app_url('mes-rdv')) . '">';
            $next_rdv_html .= '<div class="lab">📅 VOTRE PROCHAIN RENDEZ-VOUS</div>';
            $next_rdv_html .= '<div class="ti">' . esc_html(ucfirst($when)) . '</div>';
            $next_rdv_html .= '<div class="me">' . esc_html($type_lbl);
            if (!empty($rdv->lieu)) $next_rdv_html .= ' · ' . esc_html($rdv->lieu);
            $next_rdv_html .= '</div>';
            if (!empty($rdv->description)) {
                $next_rdv_html .= '<div class="me" style="margin-top:4px;font-size:.85em;opacity:.9">' . esc_html(mb_substr($rdv->description, 0, 80)) . '</div>';
            }
            $next_rdv_html .= '<div class="cta">Voir mon agenda →</div>';
            $next_rdv_html .= '</a>';
        }
    }

    $tiles = [
        ['📲', 'Installer l\'app',  'iPhone / Android · permissions', lfi_nct_app_url('installer')],
        ['📅', 'Mes rendez-vous',   'Agenda avec le GA',              lfi_nct_app_url('mes-rdv')],
        ['📷', 'Envoyer une photo', 'Documenter votre logement',      lfi_nct_app_url('envoyer-photo')],
        ['📝', 'Modèle de lettre',  'Pour Nantes Métropole Habitat',  lfi_nct_app_url('lettre')],
        ['⚖️', 'Mes droits',        'Lois et recours',                lfi_nct_app_url('droits')],
        ['🔔', 'Conseils du jour',  'Rappels quotidiens / hebdo',     lfi_nct_app_url('notifs')],
        ['🏠', 'Ma situation',      'Ma réponse à l\'enquête',        lfi_nct_app_url('mon-enquete')],
        ['✏️', 'Mon profil',        'Email · mot de passe',           lfi_nct_app_url('mon-profil')],
        ['🚪', 'Se déconnecter',    '',                                wp_logout_url(home_url('/'))],
    ];
    ?>
    <div class="lfi-app">
        <div class="lfi-app-topbar">
            <div class="lfi-app-logo-mini">Φ</div>
            <div>
                <div class="lfi-app-hi">Bonjour <?php echo esc_html($user->display_name ?: $user->user_login); ?></div>
                <div class="lfi-app-sub2">Suivi par le GA LFI · espace personnel</div>
            </div>
        </div>

        <?php if ($tip_html) echo $tip_html; ?>

        <?php if ($problem): ?>
            <div class="lfi-app-problem" style="margin-bottom:14px">
                <div class="prob-head">Vos problèmes signalés
                    <?php if ($problem['gravite']): ?><span class="prob-grav g<?php echo (int) $problem['gravite']; ?>">gravité <?php echo (int) $problem['gravite']; ?>/10</span><?php endif; ?>
                </div>
                <div class="prob-chips">
                    <?php foreach ($problem['chips'] as $ch): ?>
                        <span class="prob-chip"><?php echo $ch[0]; ?> <?php echo esc_html($ch[1]); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php echo $next_rdv_html; ?>

        <div class="lfi-app-grid" style="margin-top:14px">
            <?php foreach ($tiles as $t): ?>
                <a class="lfi-app-tile" href="<?php echo esc_url($t[3]); ?>">
                    <div class="ico"><?php echo $t[0]; ?></div>
                    <div class="tit"><?php echo esc_html($t[1]); ?></div>
                    <div class="sub"><?php echo esc_html($t[2]); ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="lfi-app-foot" style="margin-top:18px">
            <small>Cet espace est réservé à votre suivi. Aucune de vos informations ne sera transmise à un tiers sans votre accord explicite.</small>
        </div>
    </div>
    <?php
}

/* ============================================================== *
 *  Astuce du jour / semaine pour locataire                         *
 * ============================================================== */
function lfi_nct_app_tenant_tips() {
    return [
        "Le bailleur doit délivrer un logement décent (loi du 6 juillet 1989, art. 6). Si ce n'est pas le cas, vous pouvez le mettre en demeure.",
        "Un logement décent doit être étanche à l'air et à l'eau, et exempt de toute infiltration ou remontée d'humidité (décret n° 2002-120 du 30 janvier 2002, art. 2).",
        "Le chauffage doit permettre d'atteindre 18 °C dans les pièces principales en hiver (décret 2002-120, art. 3). En dessous, c'est un manquement.",
        "La fourniture d'eau chaude doit être continue. Des coupures fréquentes sont une atteinte à la décence et peuvent justifier une réduction de loyer.",
        "Les nuisibles (cafards, punaises, rats) sont à la charge du bailleur en logement social, au titre du Règlement Sanitaire Départemental.",
        "Avant le tribunal, vous pouvez saisir gratuitement la Commission départementale de conciliation (CDC) du logement.",
        "L'ADIL Loire-Atlantique vous donne un conseil juridique gratuit en matière de logement — cherchez « ADIL 44 » pour les coordonnées à jour.",
        "Pour un cas d'insalubrité, vous pouvez signaler à l'ARS Pays-de-la-Loire (Code de la santé publique, art. L. 1331-22 et suivants).",
        "Vous n'êtes pas obligé·e d'être seul·e face au bailleur : le GA LFI Nantes Sud Clos Toreau peut vous accompagner dans les démarches.",
        "Documentez chaque problème : photos datées, courriers, e-mails. C'est ce qui fera la différence en cas de procédure.",
    ];
}

function lfi_nct_app_tenant_maybe_tip_banner($user_id) {
    $freq = get_user_meta($user_id, 'lfi_nct_notif_freq', true) ?: 'weekly';
    if ($freq === 'never') return '';
    $last = (int) get_user_meta($user_id, 'lfi_nct_notif_last_seen', true);
    $now  = current_time('timestamp');
    $interval = $freq === 'daily' ? 86400 : 7 * 86400;
    if ($last && ($now - $last) < $interval) return '';

    /* Tip stable du jour : indice basé sur la date pour rester cohérent dans une journée */
    $tips = lfi_nct_app_tenant_tips();
    $idx = (int) wp_date('z') % count($tips);
    $tip = $tips[$idx];

    update_user_meta($user_id, 'lfi_nct_notif_last_seen', $now);

    return '<div class="lfi-tenant-tip"><div class="lab">💡 Conseil du ' . ($freq === 'daily' ? 'jour' : 'moment') . '</div><div class="tx">' . esc_html($tip) . '</div><div class="more"><a href="' . esc_url(lfi_nct_app_url('droits')) . '">Voir mes droits →</a></div></div>';
}

/* ============================================================== *
 *  Vue locataire : Mes droits                                      *
 * ============================================================== */
function lfi_nct_app_view_tenant_droits() {
    $user = wp_get_current_user();
    $resp_id = lfi_nct_user_tenant_response_id($user->ID);
    global $wpdb;
    $response = $resp_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $resp_id)) : null;
    $problem = $response ? lfi_nct_app_enq_problem($response) : null;
    $main_keys = [];
    if ($response) {
        $data = $response->data ? json_decode($response->data, true) : [];
        if (is_array($data)) $main_keys = (array) ($data['problemes_types'] ?? []);
    }

    lfi_nct_app_screen_open('⚖️ Mes droits', 'Qui paie quoi · rôles · loi · FAQ · recours');

    echo '<div class="lfi-app-help">⚠️ <strong>Information juridique générale, pas conseil personnalisé.</strong> Pour un conseil sur votre cas, contactez l\'ADIL Loire-Atlantique (gratuit) ou un·e avocat·e en droit du logement.</div>';

    /* Sommaire ancré */
    echo '<div class="lfi-droits-toc">';
    echo '<a href="#droits-qui-paie">💰 Qui paie quoi ?</a>';
    echo '<a href="#droits-roles">🔁 Rôles</a>';
    echo '<a href="#droits-textes">📜 Les 5 lois</a>';
    echo '<a href="#droits-faq">❓ FAQ</a>';
    echo '<a href="#droits-recours">📞 Recours</a>';
    echo '</div>';

    echo '<div class="lfi-droits">';

    /* === Qui paie quoi === */
    echo '<section id="droits-qui-paie"><h3>💰 Qui paie quoi ?</h3>';
    echo '<p>Référence : <strong>loi du 6 juillet 1989</strong> + <strong>décret n° 87-712 du 26 août 1987</strong> (liste des réparations à la charge du locataire).</p>';
    echo '<table class="lfi-droits-table"><thead><tr><th></th><th>Bailleur</th><th>Locataire</th></tr></thead><tbody>';
    foreach ([
        ['Gros entretien (toiture, ravalement, chaudière collective, ascenseur)', '✅', '—'],
        ['Mise aux normes (électricité, gaz, plomb, amiante)',                    '✅', '—'],
        ['Remplacement éléments vétustes (volets, robinets en fin de vie)',       '✅', '—'],
        ['Étanchéité, infiltrations',                                             '✅', '—'],
        ['Désinsectisation logement social (cafards, punaises, rats)',            '✅', '—'],
        ['Entretien courant (peinture, joints, ampoules)',                          '—', '✅'],
        ['Petites réparations (robinet flexible, débouchage évier)',                '—', '✅'],
        ['Entretien chaudière individuelle (révision annuelle)',                    '—', '✅'],
        ['Taxe foncière',                                                          '✅', '—'],
        ['Charges récupérables (chauffage collectif, eau, ascenseur)',              '—', '✅'],
        ['Assurance du bâti',                                                      '✅', '—'],
        ['Assurance habitation (responsabilité civile, dégâts des eaux)',           '—', '✅'],
    ] as $r) {
        echo '<tr><td>' . esc_html($r[0]) . '</td><td class="c">' . $r[1] . '</td><td class="c">' . $r[2] . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '</section>';

    /* === Rôles === */
    echo '<section id="droits-roles"><h3>🔁 Le rôle du locataire et du bailleur</h3>';
    echo '<div class="lfi-droits-roles">';
    echo '<div class="rcard"><div class="rh">🏠 Le locataire doit</div><ul>';
    echo '<li>Payer le loyer et les charges aux dates prévues</li>';
    echo '<li>User du logement en « bon père de famille » (entretien courant, ne pas dégrader)</li>';
    echo '<li>Souscrire une <strong>assurance habitation</strong> (obligation légale)</li>';
    echo '<li>Respecter le voisinage (bruit, parties communes)</li>';
    echo '<li>Permettre l\'accès au logement pour les travaux nécessaires (avec préavis raisonnable)</li>';
    echo '<li>Signaler rapidement les problèmes au bailleur (LRAR si grave)</li>';
    echo '<li>Restituer le logement en bon état (état des lieux de sortie)</li>';
    echo '</ul></div>';
    echo '<div class="rcard"><div class="rh">🔑 Le bailleur doit</div><ul>';
    echo '<li>Délivrer un logement <strong>décent</strong> (loi 1989 art. 6, décret 2002-120)</li>';
    echo '<li>Assurer la <strong>jouissance paisible</strong> du logement</li>';
    echo '<li>Entretenir le logement (gros entretien, travaux structurels)</li>';
    echo '<li>Effectuer les réparations non locatives</li>';
    echo '<li>Garantir la santé et la sécurité (insalubrité, électricité)</li>';
    echo '<li>Respecter les délais de préavis pour entrer (sauf urgence)</li>';
    echo '<li>Ne pas augmenter le loyer hors cadre légal (encadrement Nantes 2024)</li>';
    echo '<li>Rendre le dépôt de garantie sous 1 ou 2 mois selon état des lieux</li>';
    echo '</ul></div>';
    echo '</div></section>';

    /* === Que dit la loi === */
    echo '<section id="droits-textes"><h3>📜 Les 5 textes à connaître</h3>';
    echo '<ol class="lfi-droits-textes">';
    echo '<li><strong>Loi n° 89-462 du 6 juillet 1989</strong> — la base : rapports locatifs, obligations du bailleur (art. 6 : logement décent), recours du locataire (art. 20-1).</li>';
    echo '<li><strong>Décret n° 2002-120 du 30 janvier 2002</strong> — critères techniques de la décence : étanchéité (art. 2), chauffage 18 °C (art. 3), surface minimale, hauteur sous plafond ≥ 2,20 m.</li>';
    echo '<li><strong>Décret n° 87-712 du 26 août 1987</strong> — liste exhaustive des réparations à la charge du locataire (tout le reste = bailleur).</li>';
    echo '<li><strong>Code de la santé publique, art. L. 1331-22 et suivants</strong> — insalubrité, pouvoirs de l\'ARS et du préfet, arrêté d\'insalubrité.</li>';
    echo '<li><strong>Loi ELAN 2018 + arrêté local Nantes 2024</strong> — encadrement des loyers : indexation IRL plafonnée, traitement des punaises de lit à la charge du bailleur HLM.</li>';
    echo '</ol></section>';

    /* === FAQ === */
    echo '<section id="droits-faq"><h3>❓ FAQ — 10 questions fréquentes</h3>';
    $faqs = [
        [
            'q' => 'Mon bailleur ne fait pas les travaux promis. Que faire ?',
            'r' => 'Étape 1 : LRAR de mise en demeure avec liste précise des travaux + délai de 2 mois. Étape 2 : sans réponse, saisir la <strong>Commission départementale de conciliation (CDC)</strong> du logement (gratuit). Étape 3 : tribunal judiciaire de Nantes pour exécution forcée + dommages-intérêts.',
        ],
        [
            'q' => 'Le bailleur peut-il entrer chez moi sans prévenir ?',
            'r' => '<strong>Non.</strong> Sauf urgence (fuite d\'eau, incendie), il doit donner un <strong>préavis raisonnable</strong> (généralement 7 jours) et passer à une heure convenue. Vous pouvez refuser une visite mal planifiée.',
        ],
        [
            'q' => 'Peut-on m\'expulser pour impayés ?',
            'r' => 'Procédure très encadrée : 1) commandement de payer par huissier, 2) délai de 2 mois pour régulariser, 3) assignation tribunal, 4) jugement. Et surtout : <strong>trêve hivernale du 1er novembre au 31 mars</strong> — pas d\'expulsion possible pendant cette période.',
        ],
        [
            'q' => 'Mon loyer a augmenté de façon abusive, c\'est légal ?',
            'r' => 'À Nantes, l\'encadrement des loyers est en vigueur depuis 2024. Le bailleur ne peut indexer que sur l\'<strong>IRL (Indice de référence des loyers)</strong>, dans la limite du plafond légal. Une hausse hors cadre est <strong>nulle</strong> : LRAR de contestation + remboursement des trop-perçus.',
        ],
        [
            'q' => 'Mes voisins font du bruit, à qui s\'adresser ?',
            'r' => 'D\'abord essayer la médiation directe (un mot, un échange courtois). Sinon : signaler au bailleur (responsable de la jouissance paisible des locataires). En cas de <strong>tapage nocturne</strong> (22h-7h) : police (17 ou 112) — c\'est une contravention.',
        ],
        [
            'q' => 'La chaudière collective est en panne, qui doit la réparer ?',
            'r' => 'Le bailleur. C\'est de l\'entretien à sa charge. Délai raisonnable. Si la coupure dure plus de quelques jours, vous pouvez demander un <strong>remboursement du chauffage d\'appoint</strong> que vous avez dû acheter.',
        ],
        [
            'q' => 'Punaises de lit ou cafards, qui paie le traitement ?',
            'r' => '<strong>En logement social (HLM), c\'est le bailleur</strong> qui doit traiter, y compris à l\'intérieur du logement (loi ELAN 2018 + Règlement Sanitaire Départemental). En privé, c\'est plus discuté : la date d\'infestation est déterminante.',
        ],
        [
            'q' => 'Mon logement a 1m70 de hauteur sous plafond, c\'est légal ?',
            'r' => '<strong>Non.</strong> Le décret 2002-120 exige une hauteur sous plafond <strong>≥ 2,20 m</strong> OU un volume habitable <strong>≥ 20 m³</strong>. Si ce n\'est pas le cas, le logement n\'est pas décent : action en réduction de loyer voire en résolution du bail.',
        ],
        [
            'q' => 'Puis-je peindre les murs sans demander ?',
            'r' => 'Oui, dans des <strong>couleurs courantes</strong> (claires, neutres). Le bailleur peut exiger une remise en état lors de votre départ si vous avez choisi une couleur très marquée (rouge, noir, etc.). Conservez les pots de peinture pour les retouches.',
        ],
        [
            'q' => 'Le bailleur refuse de rendre le dépôt de garantie. Que faire ?',
            'r' => '<strong>Délai légal : 1 mois</strong> (sans dégradation) ou <strong>2 mois</strong> (avec dégradations à déduire). Au-delà, <strong>intérêts de retard de 10 % du loyer mensuel</strong> par mois. Étapes : LRAR avec calcul des sommes dues → commission de conciliation → tribunal judiciaire.',
        ],
    ];
    echo '<div class="lfi-droits-faq">';
    foreach ($faqs as $f) {
        echo '<details><summary>' . esc_html($f['q']) . '</summary><div class="ans">' . $f['r'] . '</div></details>';
    }
    echo '</div></section>';

    /* === Spécifique au signalement === */
    if ($main_keys) {
        echo '<section><h3>🏠 Spécifique à votre signalement</h3>';
        if (in_array('humidite', $main_keys, true)) {
            echo '<details open><summary>🌫 Humidité, moisissures</summary><div class="ans">';
            echo '<p>Article 2 du décret 2002-120 : <em>« étanche à l\'air et à l\'eau, et exempt de toute infiltration ou remontée d\'humidité »</em>. Démarches : LRAR + photos datées → 2 mois → CDC → tribunal judiciaire.</p>';
            echo '</div></details>';
        }
        if (in_array('chauffage', $main_keys, true)) {
            echo '<details open><summary>🥶 Chauffage</summary><div class="ans">';
            echo '<p>Article 3 du décret 2002-120 : 18 °C minimum en pièce principale en hiver. Démarches : relevés horodatés (photo du thermomètre avec date), LRAR au bailleur.</p>';
            echo '</div></details>';
        }
        if (in_array('degats_eaux', $main_keys, true)) {
            echo '<details open><summary>💧 Dégâts des eaux</summary><div class="ans">';
            echo '<p>Loi 1989 art. 6, c : obligation d\'entretien. Démarches : déclaration assurance habitation + LRAR au bailleur avec photos.</p>';
            echo '</div></details>';
        }
        if (in_array('insectes', $main_keys, true)) {
            echo '<details open><summary>🐜 Nuisibles</summary><div class="ans">';
            echo '<p>Loi ELAN 2018 + RSD : le bailleur HLM doit traiter. Démarches : LRAR au bailleur, puis SCHS mairie de Nantes si pas d\'action sous 1 mois.</p>';
            echo '</div></details>';
        }
        echo '<details open><summary>🚿 Eau chaude sanitaire (sujet du quartier)</summary><div class="ans">';
        echo '<p>La fourniture continue d\'eau chaude relève de la décence. Coupures répétées = manquement = réduction de loyer + dommages-intérêts. Le GA centralise les preuves pour une action collective.</p>';
        echo '</div></details>';
        echo '</section>';
    }

    /* === Recours === */
    echo '<section id="droits-recours"><h3>📞 Vos recours, dans l\'ordre</h3>';
    echo '<ol class="lfi-droits-recours">';
    echo '<li><strong>Mise en demeure</strong> du bailleur en LRAR. Délai 1 à 2 mois.</li>';
    echo '<li><strong>Commission départementale de conciliation (CDC)</strong> — gratuite, à saisir avant le tribunal.</li>';
    echo '<li><strong>Tribunal judiciaire de Nantes</strong> — exécution forcée, dommages-intérêts, réduction de loyer, voire résolution du bail.</li>';
    echo '<li><strong>ARS Pays-de-la-Loire</strong> et <strong>SCHS de la mairie</strong> — pour les cas d\'insalubrité (CSP art. L. 1331-22 et suivants).</li>';
    echo '<li><strong>ADIL Loire-Atlantique</strong> — conseil juridique <strong>gratuit</strong>. Cherchez « ADIL 44 » sur Internet pour les coordonnées à jour.</li>';
    echo '</ol></section>';

    /* === Collectif === */
    echo '<section><h3>👥 Vous n\'êtes pas seul·e</h3>';
    echo '<p>Le Groupe d\'Action LFI Nantes Sud Clos Toreau organise un suivi collectif des problèmes de logement HLM dans le quartier, et accompagne les locataires dans leurs démarches.</p>';
    if (function_exists('lfi_nct_sms_upcoming_events')) {
        $upc = lfi_nct_sms_upcoming_events(1);
        if ($upc) {
            echo '<p>Prochaine réunion publique : <a href="' . esc_url(get_permalink($upc[0])) . '"><strong>' . esc_html(get_the_title($upc[0])) . '</strong></a>.</p>';
        }
    }
    echo '</section>';

    echo '</div>';

    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  Vue locataire : Modèle de lettre pour Nantes Métropole Habitat *
 * ============================================================== */
function lfi_nct_app_view_tenant_lettre() {
    $user = wp_get_current_user();
    $resp_id = lfi_nct_user_tenant_response_id($user->ID);
    global $wpdb;
    $response = $resp_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $resp_id)) : null;
    if (!$response) {
        lfi_nct_app_screen_open('📝 Modèle de lettre');
        echo '<div class="lfi-app-empty">Aucune enquête liée à votre compte. Contactez le GA.</div>';
        lfi_nct_app_screen_close(false);
        return;
    }

    $data = $response->data ? json_decode($response->data, true) : [];
    if (!is_array($data)) $data = [];
    $problem = lfi_nct_app_enq_problem($response);

    /* On compose la lettre en français */
    $prenom = $response->contact_prenom ?: $user->display_name;
    $nom    = $response->contact_nom    ?: '';
    $adresse = $response->adresse ?: '';
    $etage   = $response->etage ?: '';

    $duree_lbl = [
        'moins_1_mois' => 'depuis moins d\'un mois',
        '1_6_mois'     => 'depuis un à six mois',
        '6_12_mois'    => 'depuis six à douze mois',
        '1_5_ans'      => 'depuis plus d\'un an',
        'plus_5_ans'   => 'depuis plus de cinq ans',
    ];
    $rec_lbl = [
        'permanent' => 'en permanence',
        'parfois'   => 'de manière récurrente',
        'ponctuel'  => 'de manière ponctuelle',
    ];
    $duree     = $duree_lbl[$data['problemes_duree'] ?? ''] ?? '';
    $recurrent = $rec_lbl[$data['problemes_recurrent'] ?? ''] ?? '';
    $gravite   = (int) ($data['problemes_gravite'] ?? 0);

    $phrase_problem = lfi_nct_app_enq_phrase($problem);

    $today = wp_date('j F Y');

    $lettre = "$prenom $nom\n";
    if ($adresse) $lettre .= "$adresse" . ($etage ? " — étage $etage" : '') . "\n";
    $lettre .= "Nantes\n\n";
    $lettre .= "Nantes Métropole Habitat\n";
    $lettre .= "[Coordonnées du bailleur / agence — à compléter]\n\n";
    $lettre .= "Lettre recommandée avec accusé de réception\n\n";
    $lettre .= "Nantes, le $today\n\n";
    $lettre .= "Objet : mise en demeure de remise en conformité au titre de la décence du logement\n\n";
    $lettre .= "Madame, Monsieur,\n\n";
    $lettre .= "Locataire du logement situé " . ($adresse ?: '[adresse]') . ($etage ? ', étage ' . $etage : '') . ", je vous signale par la présente la persistance de problèmes affectant la décence de mon logement";
    if ($phrase_problem) $lettre .= " : $phrase_problem";
    $lettre .= ".\n\n";
    if ($duree || $recurrent || $gravite) {
        $lettre .= "Cette situation perdure";
        if ($duree)     $lettre .= " $duree";
        if ($recurrent) $lettre .= ", $recurrent";
        if ($gravite)   $lettre .= ", avec une gravité que j'estime à $gravite/10";
        $lettre .= ".\n\n";
    }

    /* Bloc juridique selon le problème principal */
    $types = (array) ($data['problemes_types'] ?? []);
    if (in_array('humidite', $types, true) || in_array('degats_eaux', $types, true)) {
        $lettre .= "Pour rappel, l'article 2 du décret n° 2002-120 du 30 janvier 2002 dispose que le logement doit assurer le clos et le couvert et être « étanche à l'air et à l'eau, et exempt de toute infiltration ou remontée d'humidité ». Mon logement ne respecte pas cette obligation.\n\n";
    }
    if (in_array('chauffage', $types, true)) {
        $lettre .= "L'article 3 du décret n° 2002-120 du 30 janvier 2002 impose un dispositif de chauffage permettant de maintenir 18 °C dans les pièces principales. Ce n'est pas le cas dans mon logement.\n\n";
    }
    if (in_array('insectes', $types, true)) {
        $lettre .= "Le Règlement Sanitaire Départemental met à la charge du bailleur les mesures de désinsectisation et de dératisation en logement social. À ce jour, ces mesures n'ont pas été effectuées de manière satisfaisante.\n\n";
    }
    /* Eau chaude */
    $ec_nb    = trim((string) ($data['eau_chaude_nb_par_an'] ?? ''));
    $ec_duree = trim((string) ($data['eau_chaude_duree_max'] ?? ''));
    $ec_cit   = trim((string) ($data['eau_chaude_citation']  ?? ''));
    if ($ec_nb || $ec_duree) {
        $lettre .= "Par ailleurs, je subis des coupures d'eau chaude récurrentes";
        if ($ec_nb)    $lettre .= " (estimation : $ec_nb par an)";
        if ($ec_duree) $lettre .= ", avec une coupure maximale ayant duré $ec_duree";
        $lettre .= ". La fourniture continue d'eau chaude étant une condition essentielle de la décence du logement, l'absence de réponse durable de votre part constitue un manquement contractuel.\n\n";
    }
    if ($ec_cit) {
        $lettre .= "Pour mémoire : « $ec_cit ».\n\n";
    }

    $lettre .= "Je vous mets donc formellement en demeure de procéder, dans un délai de DEUX MOIS à compter de la réception de la présente, à l'ensemble des travaux et mesures nécessaires à la remise en conformité du logement, en application de l'article 6 de la loi n° 89-462 du 6 juillet 1989 tendant à améliorer les rapports locatifs.\n\n";
    $lettre .= "À défaut, je me réserve la possibilité de saisir la commission départementale de conciliation, le tribunal judiciaire de Nantes, ainsi que, le cas échéant, l'ARS Pays-de-la-Loire au titre des articles L. 1331-22 et suivants du Code de la santé publique.\n\n";
    $lettre .= "Je sollicite par ailleurs, en application de l'article 20-1 de la loi du 6 juillet 1989, la mise en œuvre des mesures de remise aux normes.\n\n";
    $lettre .= "Je vous prie de croire, Madame, Monsieur, en l'assurance de mes sentiments distingués.\n\n\n";
    $lettre .= "$prenom $nom\n";
    $lettre .= "[Signature]\n";

    lfi_nct_app_screen_open('📝 Modèle de lettre', 'Mise en demeure du bailleur — modèle à adapter');

    echo '<div class="lfi-app-help">⚠️ <strong>Modèle indicatif</strong> pré-rempli avec votre situation. Relisez, complétez l\'adresse du bailleur, et faites idéalement vérifier par l\'ADIL ou un·e avocat·e avant d\'envoyer. <strong>À envoyer en lettre recommandée avec accusé de réception.</strong></div>';

    echo '<textarea class="lfi-lettre-area" rows="24" readonly onclick="this.select()">' . esc_textarea($lettre) . '</textarea>';

    echo '<div class="row-actions" style="margin-top:10px">';
    echo '<button type="button" class="btn-primary big" onclick="
        var ta = document.querySelector(\'.lfi-lettre-area\');
        navigator.clipboard.writeText(ta.value).then(function(){ alert(\'Lettre copiée. Colle-la dans Mail, Word ou Google Docs.\'); });
    ">📋 Copier la lettre</button>';
    echo '<button type="button" class="btn-ghost" onclick="window.print()">🖨 Imprimer</button>';
    echo '</div>';

    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  Vue locataire : Mon enquête (read-only)                         *
 * ============================================================== */
function lfi_nct_app_view_tenant_enquete() {
    $user = wp_get_current_user();
    $resp_id = lfi_nct_user_tenant_response_id($user->ID);
    global $wpdb;
    $response = $resp_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $resp_id)) : null;

    lfi_nct_app_screen_open('🏠 Ma situation', 'Ce que vous avez déclaré');

    if (!$response) {
        echo '<div class="lfi-app-empty">Aucune enquête liée à votre compte.</div>';
        lfi_nct_app_screen_close(false);
        return;
    }
    $problem = lfi_nct_app_enq_problem($response);

    echo '<div class="lfi-app-card">';
    echo '<div class="head"><div class="who">' . esc_html(trim($response->contact_prenom . ' ' . $response->contact_nom) ?: 'Vous') . '</div>';
    echo '<div class="when">' . esc_html(wp_date('j M Y', strtotime($response->submitted_at))) . '</div>';
    echo '</div>';
    $adr = trim($response->adresse . ($response->etage ? ' — étage ' . $response->etage : ''));
    if ($adr) echo '<div class="meta"><span class="meta-chip">📍 ' . esc_html($adr) . '</span></div>';
    if ($problem) {
        echo '<div class="lfi-app-problem inline">';
        echo '<div class="prob-head">Problèmes signalés';
        if ($problem['gravite']) echo ' <span class="prob-grav g' . (int) $problem['gravite'] . '">gravité ' . (int) $problem['gravite'] . '/10</span>';
        echo '</div>';
        echo '<div class="prob-chips">';
        foreach ($problem['chips'] as $ch) echo '<span class="prob-chip">' . $ch[0] . ' ' . esc_html($ch[1]) . '</span>';
        echo '</div></div>';
    }
    echo '</div>';

    echo '<div class="lfi-app-help" style="margin-top:14px">Pour modifier votre situation, contactez directement le GA — il vous aidera à mettre à jour votre dossier.</div>';

    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  Vue locataire : Notifications config                            *
 * ============================================================== */
function lfi_nct_app_view_tenant_notifs() {
    $user = wp_get_current_user();
    if (!empty($_POST['lfi_app_notif_save']) && check_admin_referer('lfi_app_notif_save')) {
        $f = sanitize_key($_POST['freq'] ?? 'weekly');
        if (!in_array($f, ['never', 'weekly', 'daily'], true)) $f = 'weekly';
        update_user_meta($user->ID, 'lfi_nct_notif_freq', $f);
        delete_user_meta($user->ID, 'lfi_nct_notif_last_seen');
        wp_safe_redirect(lfi_nct_app_url('notifs', ['saved' => 1]));
        exit;
    }
    $current = get_user_meta($user->ID, 'lfi_nct_notif_freq', true) ?: 'weekly';

    lfi_nct_app_screen_open('🔔 Conseils du jour', 'Recevoir des rappels juridiques dans l\'app');

    if (!empty($_GET['saved'])) lfi_nct_app_flash('Préférences enregistrées.');

    echo '<div class="lfi-app-help">Ces conseils s\'affichent dans votre espace personnel à chaque ouverture, à la fréquence choisie. (Le vrai push iOS arrivera dans une prochaine version.)</div>';

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_notif_save');
    echo '<input type="hidden" name="lfi_app_notif_save" value="1">';

    foreach ([
        'never'  => ['Jamais',         'Aucun conseil affiché'],
        'weekly' => ['Une fois par semaine', 'Un conseil différent chaque semaine'],
        'daily'  => ['Tous les jours', 'Un conseil par jour'],
    ] as $k => $info) {
        $checked = $current === $k ? 'checked' : '';
        echo '<label class="lfi-app-checkbox-row" style="cursor:pointer">';
        echo '<input type="radio" name="freq" value="' . esc_attr($k) . '" ' . $checked . '>';
        echo '<span><strong>' . esc_html($info[0]) . '</strong><br><small style="color:#777">' . esc_html($info[1]) . '</small></span>';
        echo '</label>';
    }
    echo '<button type="submit" class="btn-primary big">✓ Enregistrer</button>';
    echo '</form>';

    echo '<div class="lfi-app-help" style="margin-top:18px">';
    echo '<strong>Aperçu d\'un conseil :</strong><br>';
    $tips = lfi_nct_app_tenant_tips();
    echo esc_html($tips[array_rand($tips)]);
    echo '</div>';

    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  ADMIN : créer des comptes (GA member, locataire)               *
 * ============================================================== */
/* Helper : email propre, vidé si déjà utilisé par un autre user */
function lfi_nct_app_clean_email($email) {
    $email = trim((string) $email);
    if (!is_email($email)) return '';
    if (email_exists($email)) return '';
    return $email;
}

/* Helper : affichage des credentials créés + bouton SMS
 * - Vouvoiement systématique pour tous les destinataires
 * - Grandes respirations entre login et mot de passe pour la lisibilité */
function lfi_nct_app_render_credentials_card($created, $screen_label = 'Compte créé') {
    $login = $created['login']; $pwd = $created['pwd']; $tel = $created['tel'] ?? '';
    $site_app = home_url('/app/');
    $sms_body = "Bonjour,\n\n"
              . "Vos accès à l'app du GA LFI Nantes Sud Clos Toreau :\n\n"
              . "📲 Installez l'app en ouvrant ce lien :\n"
              . $site_app . "\n\n"
              . "→ iPhone : ouvrez dans Safari puis Partager > Sur l'écran d'accueil\n"
              . "→ Android : ouvrez dans Chrome, un bouton « Installer » apparaît\n\n"
              . "🪪 Identifiant : " . $login . "\n\n"
              . "🔑 Mot de passe : " . $pwd . "\n\n"
              . "Conservez bien ces informations. Vous pourrez les modifier dans l'app, rubrique « Mon profil ».";
    $sms_url = $tel ? 'sms:' . preg_replace('/[^\d+]/', '', $tel) . '?body=' . rawurlencode($sms_body) : '';
    echo '<div class="lfi-app-flash ok">';
    echo '<strong>✅ ' . esc_html($screen_label) . '</strong><br>';
    echo '<table style="margin:10px 0;border-collapse:collapse;width:100%">';
    echo '<tr><td style="padding:6px 8px;vertical-align:top"><small>🌐 URL</small></td><td style="padding:6px 8px"><code>' . esc_html($site_app) . '</code></td></tr>';
    echo '<tr><td style="padding:6px 8px;vertical-align:top"><small>🪪 Identifiant</small></td><td style="padding:6px 8px"><code style="font-size:1.05em;background:#fff;padding:2px 6px;border-radius:4px;border:1px solid #ddd">' . esc_html($login) . '</code></td></tr>';
    echo '<tr><td style="padding:6px 8px;vertical-align:top"><small>🔑 Mot de passe</small></td><td style="padding:6px 8px"><code style="font-size:1.05em;background:#fff;padding:2px 6px;border-radius:4px;border:1px solid #ddd;letter-spacing:.05em">' . esc_html($pwd) . '</code></td></tr>';
    echo '</table>';
    echo '<div class="row-actions">';
    if ($sms_url) echo '<a class="btn-primary" href="' . esc_url($sms_url) . '">📱 SMS les identifiants</a>';
    echo '<button type="button" class="btn-ghost" onclick="navigator.clipboard.writeText(' . wp_json_encode($sms_body) . ');this.textContent=\'✓ Copié\';">📋 Copier le message</button>';
    echo '</div>';
    echo '<div style="margin-top:8px"><small>⚠️ Ce mot de passe ne sera plus affiché. Notez-le ou envoyez-le maintenant.</small></div>';
    echo '</div>';
}

/* ============================================================== *
 *  Backward compat : /app/?vue=comptes affiche la page GA          *
 *  (wp_safe_redirect ne marche pas depuis le shortcode car les     *
 *  headers sont déjà envoyés — on appelle directement la vue)      *
 * ============================================================== */
function lfi_nct_app_view_comptes() {
    if (!current_user_can('manage_options')) return;
    lfi_nct_app_view_comptes_ga();
}

/* ============================================================== *
 *  PAGE 1/2 : Comptes Membres du GA                                *
 *  - Import depuis wp_lfi_nct_membres                              *
 *  - Création manuelle d'un membre GA                              *
 *  - Liste + reset password                                        *
 * ============================================================== */
function lfi_nct_app_view_comptes_ga() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;

    $created     = null;
    $created_err = null;

    /* Création manuelle d'un membre GA */
    if (!empty($_POST['lfi_app_create_ga']) && check_admin_referer('lfi_app_create_ga')) {
        $prenom = sanitize_text_field(wp_unslash($_POST['prenom'] ?? ''));
        $nom    = sanitize_text_field(wp_unslash($_POST['nom']    ?? ''));
        $email  = sanitize_email(wp_unslash($_POST['email']       ?? ''));
        $tel    = sanitize_text_field(wp_unslash($_POST['tel']    ?? ''));
        if ($prenom === '' && $nom === '') {
            $created_err = 'Indique au moins un prénom ou un nom.';
        } else {
            $login = lfi_nct_app_make_username($prenom, $nom);
            $pwd   = lfi_nct_app_make_password();
            $uid   = wp_insert_user([
                'user_login' => $login, 'user_pass' => $pwd,
                'user_email' => lfi_nct_app_clean_email($email),
                'first_name' => $prenom, 'last_name' => $nom,
                'display_name' => trim($prenom . ' ' . $nom) ?: $login,
                'role' => LFI_NCT_ROLE_GA,
            ]);
            if (is_wp_error($uid)) {
                $created_err = 'Erreur création compte GA : ' . $uid->get_error_message();
            } else {
                if ($tel) update_user_meta($uid, 'lfi_nct_tel', $tel);
                $created = ['login' => $login, 'pwd' => $pwd, 'tel' => $tel];
            }
        }
    }

    /* Import membre adhérent → compte GA (par ligne) */
    if (!empty($_POST['lfi_app_import_membre']) && check_admin_referer('lfi_app_import_membre')) {
        $mid = (int) $_POST['membre_id'];
        $row = $mid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_membres WHERE id = %d", $mid)) : null;
        if (!$row) {
            $created_err = "Adhérent introuvable (#$mid).";
        } else {
            $prenom = (string) ($row->prenom ?: '');
            $nom    = (string) ($row->nom ?: $row->pseudo ?: '');
            $email  = (string) ($row->email ?: '');
            $tel    = (string) ($row->tel ?: '');
            $login  = lfi_nct_app_make_username($prenom, $nom);
            $pwd    = lfi_nct_app_make_password();
            $uid    = wp_insert_user([
                'user_login' => $login, 'user_pass' => $pwd,
                'user_email' => lfi_nct_app_clean_email($email),
                'first_name' => $prenom, 'last_name' => $nom,
                'display_name' => trim($prenom . ' ' . $nom) ?: $login,
                'role' => LFI_NCT_ROLE_GA,
            ]);
            if (is_wp_error($uid)) {
                $created_err = 'Erreur import : ' . $uid->get_error_message();
            } else {
                update_user_meta($uid, 'lfi_nct_membre_id', $mid);
                if ($tel) update_user_meta($uid, 'lfi_nct_tel', $tel);
                $created = ['login' => $login, 'pwd' => $pwd, 'tel' => $tel];
            }
        }
    }

    /* Import en masse */
    if (!empty($_POST['lfi_app_import_all_membres']) && check_admin_referer('lfi_app_import_all_membres')) {
        @set_time_limit(0);
        if (function_exists('wp_raise_memory_limit')) wp_raise_memory_limit('admin');
        if (function_exists('ignore_user_abort'))     ignore_user_abort(true);

        $CHUNK = 30;
        $existing_mids = $wpdb->get_col("SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'lfi_nct_membre_id'") ?: [];
        $existing_in = $existing_mids ? '(' . implode(',', array_map('intval', $existing_mids)) . ')' : '(0)';
        $to_import = $wpdb->get_results(
            "SELECT id, prenom, nom, pseudo, email, tel FROM {$wpdb->prefix}lfi_nct_membres
             WHERE jetable = 0 AND id NOT IN $existing_in
             ORDER BY prenom, nom LIMIT $CHUNK"
        ) ?: [];

        $batch = []; $skipped = 0;
        foreach ($to_import as $row) {
            $prenom = (string) ($row->prenom ?: '');
            $nom    = (string) ($row->nom ?: $row->pseudo ?: '');
            $email  = (string) ($row->email ?: '');
            $tel    = (string) ($row->tel ?: '');
            $login  = lfi_nct_app_make_username($prenom, $nom);
            $pwd    = lfi_nct_app_make_password();
            $uid    = wp_insert_user([
                'user_login' => $login, 'user_pass' => $pwd,
                'user_email' => lfi_nct_app_clean_email($email),
                'first_name' => $prenom, 'last_name' => $nom,
                'display_name' => trim($prenom . ' ' . $nom) ?: $login,
                'role' => LFI_NCT_ROLE_GA,
            ]);
            if (is_wp_error($uid)) { $skipped++; continue; }
            update_user_meta($uid, 'lfi_nct_membre_id', $row->id);
            if ($tel) update_user_meta($uid, 'lfi_nct_tel', $tel);
            $batch[] = ['login' => $login, 'pwd' => $pwd, 'tel' => $tel, 'name' => trim($prenom . ' ' . $nom) ?: $login];
        }
        if ($batch) set_transient('lfi_nct_pwd_batch_' . get_current_user_id(), $batch, 1800);

        $existing_mids2 = $wpdb->get_col("SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'lfi_nct_membre_id'") ?: [];
        $existing_in2 = $existing_mids2 ? '(' . implode(',', array_map('intval', $existing_mids2)) . ')' : '(0)';
        $remaining = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lfi_nct_membres WHERE jetable = 0 AND id NOT IN $existing_in2");

        wp_safe_redirect(lfi_nct_app_url('comptes-ga', ['batched' => count($batch), 'skipped' => $skipped, 'remaining' => $remaining]));
        exit;
    }

    /* Reset password */
    if (!empty($_POST['lfi_app_reset_pwd']) && check_admin_referer('lfi_app_reset_pwd')) {
        $uid = (int) $_POST['uid'];
        if ($uid && get_userdata($uid)) {
            $pwd = lfi_nct_app_make_password();
            wp_set_password($pwd, $uid);
            $u   = get_userdata($uid);
            $tel = (string) get_user_meta($uid, 'lfi_nct_tel', true);
            $created = ['login' => $u->user_login, 'pwd' => $pwd, 'tel' => $tel, 'reset' => true];
        }
    }

    /* Compteur */
    $count = lfi_nct_app_count_users_cached();
    $n_ga  = $count['avail_roles'][LFI_NCT_ROLE_GA] ?? 0;

    /* Adhérents non encore importés */
    $linked_mids = $wpdb->get_col("SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'lfi_nct_membre_id'") ?: [];
    $linked_in   = $linked_mids ? '(' . implode(',', array_map('intval', $linked_mids)) . ')' : '(0)';
    $unlinked_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lfi_nct_membres WHERE jetable = 0 AND id NOT IN $linked_in");
    $unlinked_membres = $wpdb->get_results(
        "SELECT id, prenom, nom, pseudo, email, tel, statut FROM {$wpdb->prefix}lfi_nct_membres
         WHERE jetable = 0 AND id NOT IN $linked_in
         ORDER BY prenom, nom LIMIT 30"
    ) ?: [];

    /* Liste des comptes GA */
    $users_ga = get_users([
        'role' => LFI_NCT_ROLE_GA,
        'fields' => ['ID', 'user_login', 'display_name', 'user_email'],
        'number' => 50, 'orderby' => 'registered', 'order' => 'DESC',
    ]);

    lfi_nct_app_screen_open('👥 Comptes Membres du GA', (int) $n_ga . ' compte(s) actif(s) · ' . $unlinked_total . ' adhérent(s) à importer');

    /* Flash erreur */
    if ($created_err) lfi_nct_app_flash('❌ ' . $created_err, 'err');

    /* Batch après import en masse */
    $batch = get_transient('lfi_nct_pwd_batch_' . get_current_user_id());
    if (!empty($_GET['batched']) && is_array($batch)) {
        delete_transient('lfi_nct_pwd_batch_' . get_current_user_id());
        $site_app = home_url('/app/');
        echo '<div class="lfi-app-flash ok"><strong>✅ ' . count($batch) . ' compte(s) créé(s).</strong> SMS les identifiants maintenant (mot de passe non ré-affiché après).</div>';
        echo '<ul class="lfi-app-list">';
        foreach ($batch as $b) {
            $sms_body  = "Salut ! Accès app GA LFI Nantes Sud Clos Toreau :\n$site_app\nIdentifiant : " . $b['login'] . "\nMot de passe : " . $b['pwd'];
            $tel_clean = preg_replace('/[^\d+]/', '', (string) ($b['tel'] ?? ''));
            $sms_url   = $tel_clean ? 'sms:' . $tel_clean . '?body=' . rawurlencode($sms_body) : '';
            echo '<li class="lfi-app-card">';
            echo '<div class="head"><div class="who">' . esc_html($b['name']) . '</div><div class="badge">nouveau</div></div>';
            echo '<div class="meta"><span class="meta-chip">@' . esc_html($b['login']) . '</span>';
            echo '<span class="meta-chip"><code>' . esc_html($b['pwd']) . '</code></span></div>';
            echo '<div class="row-actions">';
            if ($sms_url) echo '<a class="btn-primary" href="' . esc_url($sms_url) . '">📱 SMS</a>';
            echo '<button type="button" class="btn-ghost" onclick="navigator.clipboard.writeText(' . wp_json_encode($sms_body) . ');this.textContent=\'✓ Copié\';">📋 Copier</button>';
            echo '</div></li>';
        }
        echo '</ul>';
    }
    if (!empty($_GET['skipped'])) lfi_nct_app_flash('⚠ ' . (int) $_GET['skipped'] . ' adhérent(s) sauté(s) (email déjà utilisé).', 'err');
    if (isset($_GET['remaining']) && (int) $_GET['remaining'] > 0) {
        $rem = (int) $_GET['remaining'];
        echo '<div class="lfi-app-flash ok" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">';
        echo '<div><strong>📋 ' . $rem . ' adhérent(s) restent à importer.</strong></div>';
        echo '<form method="post" style="margin:0">';
        wp_nonce_field('lfi_app_import_all_membres');
        echo '<input type="hidden" name="lfi_app_import_all_membres" value="1">';
        echo '<button type="submit" class="btn-primary">⚡ Importer les ' . min(30, $rem) . ' suivants</button>';
        echo '</form></div>';
    }

    /* Credentials d'un nouveau compte créé */
    if ($created) {
        $label = !empty($created['reset']) ? 'Mot de passe réinitialisé' : 'Compte GA créé';
        lfi_nct_app_render_credentials_card($created, $label);
    }

    /* Section : Import adhérents existants */
    if ($unlinked_total > 0) {
        echo '<details class="lfi-app-collapse" open><summary>🔄 Importer des adhérents existants (' . $unlinked_total . ' sans compte)</summary>';
        echo '<div style="padding:14px 16px;background:#fff;border-top:1px solid #eee">';
        echo '<div class="lfi-app-help" style="margin-bottom:12px">Ces personnes sont déjà adhérent·es du GA (Action Populaire) mais n\'ont pas encore d\'accès à l\'app.</div>';
        echo '<form method="post" style="margin:0 0 14px;text-align:center">';
        wp_nonce_field('lfi_app_import_all_membres');
        echo '<input type="hidden" name="lfi_app_import_all_membres" value="1">';
        $next_n = min(30, $unlinked_total);
        echo '<button type="submit" class="btn-primary big" onclick="return confirm(\'Créer ' . $next_n . ' compte(s) ? Total restant : ' . $unlinked_total . '\');">⚡ Importer les ' . $next_n . ' suivants</button>';
        echo '</form>';
        echo '<div class="lfi-app-help"><small>Import par lots de 30 pour éviter les timeouts du serveur.</small></div>';
        if (!empty($unlinked_membres)) {
            echo '<div style="font-size:.85em;color:#777;margin:14px 0 8px">Ou un par un :</div>';
            echo '<ul class="lfi-app-list">';
            foreach ($unlinked_membres as $m) {
                $name = trim($m->prenom . ' ' . $m->nom) ?: ($m->pseudo ?: '#' . $m->id);
                echo '<li class="lfi-app-card">';
                echo '<div class="head"><div class="who">' . esc_html($name) . '</div>';
                if ($m->statut) echo '<div class="badge">' . esc_html($m->statut) . '</div>';
                echo '</div><div class="meta">';
                if ($m->email) echo '<span class="meta-chip">✉️ ' . esc_html($m->email) . '</span>';
                if ($m->tel)   echo '<span class="meta-chip">📞 ' . esc_html($m->tel) . '</span>';
                echo '</div><form method="post" class="row-actions">';
                wp_nonce_field('lfi_app_import_membre');
                echo '<input type="hidden" name="lfi_app_import_membre" value="1">';
                echo '<input type="hidden" name="membre_id" value="' . (int) $m->id . '">';
                echo '<button type="submit" class="btn-ghost">+ Créer compte</button>';
                echo '</form></li>';
            }
            echo '</ul>';
        }
        echo '</div></details>';
    }

    /* Section : Créer manuellement */
    echo '<details class="lfi-app-collapse"><summary>+ Créer un membre GA manuellement</summary>';
    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_create_ga');
    echo '<input type="hidden" name="lfi_app_create_ga" value="1">';
    echo '<label>Prénom<input type="text" name="prenom" required></label>';
    echo '<label>Nom<input type="text" name="nom"></label>';
    echo '<label>Email<input type="email" name="email"></label>';
    echo '<label>Téléphone<input type="tel" name="tel" placeholder="06 12 34 56 78"></label>';
    echo '<button type="submit" class="btn-primary">✓ Créer le compte</button>';
    echo '</form></details>';

    /* Liste des comptes GA existants */
    echo '<h3 style="margin-top:18px">📋 Membres du GA inscrits (' . (int) $n_ga . ')</h3>';
    if (empty($users_ga)) {
        echo '<div class="lfi-app-empty">Aucun membre GA pour l\'instant.</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        foreach ($users_ga as $u) {
            echo '<li class="lfi-app-card">';
            echo '<div class="head"><div class="who">' . esc_html($u->display_name) . '</div><div class="badge">GA</div></div>';
            echo '<div class="meta"><span class="meta-chip">@' . esc_html($u->user_login) . '</span>';
            if ($u->user_email) echo '<a class="meta-chip" href="mailto:' . esc_attr($u->user_email) . '">✉️ ' . esc_html($u->user_email) . '</a>';
            $tel = (string) get_user_meta($u->ID, 'lfi_nct_tel', true);
            if ($tel) echo '<a class="meta-chip" href="tel:' . esc_attr($tel) . '">📞 ' . esc_html($tel) . '</a>';
            echo '</div><form method="post" class="row-actions">';
            wp_nonce_field('lfi_app_reset_pwd');
            echo '<input type="hidden" name="lfi_app_reset_pwd" value="1">';
            echo '<input type="hidden" name="uid" value="' . (int) $u->ID . '">';
            echo '<button type="submit" class="btn-ghost">🔑 Réinitialiser mot de passe</button>';
            echo '</form></li>';
        }
        echo '</ul>';
        if ((int) $n_ga > count($users_ga)) {
            echo '<div class="lfi-app-help"><small>Affichage des 50 plus récents. ' . ((int) $n_ga - count($users_ga)) . ' autres en base.</small></div>';
        }
    }

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  PAGE 2/2 : Comptes Locataires suivis                            *
 *  - Création depuis une réponse d'enquête                         *
 *  - Création manuelle                                              *
 *  - Liste + dossier + reset password                              *
 * ============================================================== */
function lfi_nct_app_view_comptes_locataires() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;

    $created     = null;
    $created_err = null;

    /* Créer locataire depuis une réponse d'enquête */
    if (!empty($_POST['lfi_app_create_tenant']) && check_admin_referer('lfi_app_create_tenant')) {
        $resp_id = (int) ($_POST['response_id'] ?? 0);
        $row = $resp_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $resp_id)) : null;
        if (!$row) {
            $created_err = "Réponse d'enquête introuvable (#$resp_id).";
        } else {
            $prenom = (string) ($row->contact_prenom ?: '');
            $nom    = (string) ($row->contact_nom ?: '');
            $email  = (string) ($row->contact_email ?: '');
            $tel    = (string) ($row->contact_tel ?: '');
            if ($prenom === '' && $nom === '') {
                $created_err = "Cette enquête n'a pas de prénom/nom. Édite-la d'abord ou utilise le formulaire manuel.";
            } else {
                $login = lfi_nct_app_make_username($prenom, $nom);
                $pwd   = lfi_nct_app_make_password();
                $uid   = wp_insert_user([
                    'user_login' => $login, 'user_pass' => $pwd,
                    'user_email' => lfi_nct_app_clean_email($email),
                    'first_name' => $prenom, 'last_name' => $nom,
                    'display_name' => trim($prenom . ' ' . $nom) ?: $login,
                    'role' => LFI_NCT_ROLE_TENANT,
                ]);
                if (is_wp_error($uid)) {
                    $created_err = 'Erreur : ' . $uid->get_error_message();
                } else {
                    update_user_meta($uid, 'lfi_nct_response_id', $resp_id);
                    if ($tel) update_user_meta($uid, 'lfi_nct_tel', $tel);
                    $created = ['login' => $login, 'pwd' => $pwd, 'tel' => $tel];
                }
            }
        }
    }

    /* Créer locataire manuellement */
    if (!empty($_POST['lfi_app_create_tenant_manual']) && check_admin_referer('lfi_app_create_tenant_manual')) {
        $prenom = sanitize_text_field(wp_unslash($_POST['prenom'] ?? ''));
        $nom    = sanitize_text_field(wp_unslash($_POST['nom']    ?? ''));
        $email  = sanitize_email(wp_unslash($_POST['email']       ?? ''));
        $tel    = sanitize_text_field(wp_unslash($_POST['tel']    ?? ''));
        if ($prenom === '' && $nom === '') {
            $created_err = 'Indique au moins un prénom ou un nom.';
        } else {
            $login = lfi_nct_app_make_username($prenom, $nom);
            $pwd   = lfi_nct_app_make_password();
            $uid   = wp_insert_user([
                'user_login' => $login, 'user_pass' => $pwd,
                'user_email' => lfi_nct_app_clean_email($email),
                'first_name' => $prenom, 'last_name' => $nom,
                'display_name' => trim($prenom . ' ' . $nom) ?: $login,
                'role' => LFI_NCT_ROLE_TENANT,
            ]);
            if (is_wp_error($uid)) {
                $created_err = 'Erreur : ' . $uid->get_error_message();
            } else {
                if ($tel) update_user_meta($uid, 'lfi_nct_tel', $tel);
                $created = ['login' => $login, 'pwd' => $pwd, 'tel' => $tel];
            }
        }
    }

    /* Reset password */
    if (!empty($_POST['lfi_app_reset_pwd']) && check_admin_referer('lfi_app_reset_pwd')) {
        $uid = (int) $_POST['uid'];
        if ($uid && get_userdata($uid)) {
            $pwd = lfi_nct_app_make_password();
            wp_set_password($pwd, $uid);
            $u   = get_userdata($uid);
            $tel = (string) get_user_meta($uid, 'lfi_nct_tel', true);
            $created = ['login' => $u->user_login, 'pwd' => $pwd, 'tel' => $tel, 'reset' => true];
        }
    }

    /* Compteur */
    $count = lfi_nct_app_count_users_cached();
    $n_tenant = $count['avail_roles'][LFI_NCT_ROLE_TENANT] ?? 0;

    /* Répondant·es non liés */
    $linked_rids = $wpdb->get_col("SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'lfi_nct_response_id'") ?: [];
    $linked_in   = $linked_rids ? '(' . implode(',', array_map('intval', $linked_rids)) . ')' : '(0)';
    $unlinked_responses = $wpdb->get_results(
        "SELECT id, contact_prenom, contact_nom, contact_email, contact_tel, contact_recontact
         FROM {$wpdb->prefix}lfi_nct_responses
         WHERE deleted_at IS NULL
               AND id NOT IN $linked_in
               AND (contact_prenom <> '' OR contact_nom <> '')
         ORDER BY contact_recontact DESC, submitted_at DESC LIMIT 100"
    ) ?: [];

    /* Liste des comptes locataires */
    $users_tenant = get_users([
        'role' => LFI_NCT_ROLE_TENANT,
        'fields' => ['ID', 'user_login', 'display_name', 'user_email'],
        'number' => 50, 'orderby' => 'registered', 'order' => 'DESC',
    ]);

    lfi_nct_app_screen_open('🏠 Comptes Locataires', (int) $n_tenant . ' compte(s) actif(s) · ' . count($unlinked_responses) . ' enquête(s) sans compte');

    /* Flash erreur */
    if ($created_err) lfi_nct_app_flash('❌ ' . $created_err, 'err');

    /* Credentials nouveau compte */
    if ($created) {
        $label = !empty($created['reset']) ? 'Mot de passe réinitialisé' : 'Compte locataire créé';
        lfi_nct_app_render_credentials_card($created, $label);
    }

    /* Section : Créer depuis une enquête */
    echo '<details class="lfi-app-collapse" open><summary>+ Créer un compte depuis une enquête</summary>';
    echo '<div style="padding:14px 16px;background:#fff;border-top:1px solid #eee">';
    if (empty($unlinked_responses)) {
        echo '<div class="lfi-app-help">Aucune réponse d\'enquête en attente d\'un compte. Utilise le formulaire manuel ci-dessous.</div>';
    } else {
        echo '<form method="post" class="lfi-app-form" style="margin:0">';
        wp_nonce_field('lfi_app_create_tenant');
        echo '<input type="hidden" name="lfi_app_create_tenant" value="1">';
        echo '<label>Répondant·e à lier<select name="response_id" required>';
        echo '<option value="">— choisir —</option>';
        $consent_open = false; $no_consent_open = false;
        foreach ($unlinked_responses as $r) {
            $consents = (int) ($r->contact_recontact ?? 0) === 1;
            if ($consents && !$consent_open) {
                echo '<optgroup label="✓ Consentent au recontact">';
                $consent_open = true;
            } elseif (!$consents && !$no_consent_open) {
                if ($consent_open) { echo '</optgroup>'; $consent_open = false; }
                echo '<optgroup label="⚠ Sans consentement explicite — à confirmer verbalement">';
                $no_consent_open = true;
            }
            $lbl = trim(($r->contact_prenom ?? '') . ' ' . ($r->contact_nom ?? '')) ?: '#' . $r->id;
            $extras = array_filter([$r->contact_tel, $r->contact_email]);
            if ($extras) $lbl .= ' — ' . implode(' / ', $extras);
            echo '<option value="' . (int) $r->id . '">' . esc_html($lbl) . '</option>';
        }
        if ($consent_open || $no_consent_open) echo '</optgroup>';
        echo '</select></label>';
        echo '<div class="lfi-app-help"><small>Si l\'email du répondant·e est déjà utilisé par un autre compte WP, le compte sera créé sans email.</small></div>';
        echo '<button type="submit" class="btn-primary">✓ Créer le compte locataire</button>';
        echo '</form>';
    }
    echo '</div></details>';

    /* Section : Créer manuellement */
    echo '<details class="lfi-app-collapse"><summary>+ Créer un compte locataire manuellement (sans enquête)</summary>';
    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_create_tenant_manual');
    echo '<input type="hidden" name="lfi_app_create_tenant_manual" value="1">';
    echo '<div class="lfi-app-help">Pour les personnes que tu as rencontrées sans qu\'elles aient rempli l\'enquête.</div>';
    echo '<label>Prénom<input type="text" name="prenom" required></label>';
    echo '<label>Nom<input type="text" name="nom"></label>';
    echo '<label>Email<input type="email" name="email"></label>';
    echo '<label>Téléphone<input type="tel" name="tel" placeholder="06 12 34 56 78"></label>';
    echo '<button type="submit" class="btn-primary">✓ Créer le compte locataire</button>';
    echo '</form></details>';

    /* Liste des comptes locataires */
    echo '<h3 style="margin-top:18px">📋 Locataires suivis (' . (int) $n_tenant . ')</h3>';
    if (empty($users_tenant)) {
        echo '<div class="lfi-app-empty">Aucun compte locataire pour l\'instant.</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        foreach ($users_tenant as $u) {
            $rid = (int) get_user_meta($u->ID, 'lfi_nct_response_id', true);
            $tel = (string) get_user_meta($u->ID, 'lfi_nct_tel', true);
            echo '<li class="lfi-app-card">';
            echo '<div class="head"><div class="who">' . esc_html($u->display_name) . '</div><div class="badge">Locataire</div></div>';
            echo '<div class="meta"><span class="meta-chip">@' . esc_html($u->user_login) . '</span>';
            if ($rid) echo '<span class="meta-chip">enquête #' . $rid . '</span>';
            if ($u->user_email) echo '<a class="meta-chip" href="mailto:' . esc_attr($u->user_email) . '">✉️ ' . esc_html($u->user_email) . '</a>';
            if ($tel) echo '<a class="meta-chip" href="tel:' . esc_attr($tel) . '">📞 ' . esc_html($tel) . '</a>';
            echo '</div>';
            echo '<div class="row-actions">';
            echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => $u->ID])) . '">📂 Ouvrir le dossier</a>';
            echo '</div>';
            echo '<form method="post" class="row-actions">';
            wp_nonce_field('lfi_app_reset_pwd');
            echo '<input type="hidden" name="lfi_app_reset_pwd" value="1">';
            echo '<input type="hidden" name="uid" value="' . (int) $u->ID . '">';
            echo '<button type="submit" class="btn-ghost">🔑 Réinitialiser mot de passe</button>';
            echo '</form></li>';
        }
        echo '</ul>';
        if ((int) $n_tenant > count($users_tenant)) {
            echo '<div class="lfi-app-help"><small>Affichage des 50 plus récents. ' . ((int) $n_tenant - count($users_tenant)) . ' autres en base.</small></div>';
        }
    }

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  ADMIN : ajouter un témoignage manuellement à l'enquête          *
 * ============================================================== */
function lfi_nct_app_view_temoignage_add() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;

    if (!empty($_POST['lfi_app_temoignage_add']) && check_admin_referer('lfi_app_temoignage_add')) {
        $prenom    = sanitize_text_field(wp_unslash($_POST['prenom'] ?? ''));
        $nom       = sanitize_text_field(wp_unslash($_POST['nom'] ?? ''));
        $tel       = sanitize_text_field(wp_unslash($_POST['tel'] ?? ''));
        $email     = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $adresse_raw = sanitize_text_field(wp_unslash($_POST['adresse'] ?? ''));
        $adresse   = lfi_nct_normalize_address($adresse_raw); // auto-correction orthographique
        $etage     = sanitize_text_field(wp_unslash($_POST['etage'] ?? ''));
        $arrivee   = (int) ($_POST['annee_arrivee'] ?? 0);
        $recontact = !empty($_POST['contact_recontact']) ? 1 : 0;

        $problems_presence = !empty($_POST['problemes_types']) ? 'oui' : 'non';
        $types = array_map('sanitize_text_field', (array) ($_POST['problemes_types'] ?? []));
        $types_autre = sanitize_text_field(wp_unslash($_POST['problemes_types_autre'] ?? ''));

        /* Apprend le nouveau problème pour les prochains formulaires */
        if ($types_autre !== '' && (in_array('autre', $types, true) || !$types)) {
            $new_slug = lfi_nct_learn_custom_problem($types_autre);
            if ($new_slug) {
                /* On l'ajoute aussi aux types cochés pour cette réponse */
                $types[] = $new_slug;
                /* On vide le champ texte libre puisqu'il devient une checkbox réutilisable */
                $types_autre = '';
                /* Et on retire le tag « autre » s'il y était */
                $types = array_values(array_diff($types, ['autre']));
            }
        }

        $duree       = sanitize_text_field(wp_unslash($_POST['problemes_duree']     ?? ''));
        $recurrent   = sanitize_text_field(wp_unslash($_POST['problemes_recurrent'] ?? ''));
        $gravite     = (int) ($_POST['problemes_gravite'] ?? 0);
        $ec_nb       = sanitize_text_field(wp_unslash($_POST['eau_chaude_nb_par_an'] ?? ''));
        $ec_duree    = sanitize_text_field(wp_unslash($_POST['eau_chaude_duree_max'] ?? ''));
        $ec_cit      = sanitize_textarea_field(wp_unslash($_POST['eau_chaude_citation'] ?? ''));
        $notes       = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

        $data = [
            'saisi_par_admin'      => 1,
            'admin_user'           => wp_get_current_user()->user_login,
            'notes_admin'          => $notes,
            'adresse_brute'        => $adresse_raw !== $adresse ? $adresse_raw : null,
            'problemes_presence'   => $problems_presence,
            'problemes_types'      => $types,
            'problemes_types_autre'=> $types_autre,
            'problemes_duree'      => $duree,
            'problemes_recurrent'  => $recurrent,
            'problemes_gravite'    => $gravite,
            'eau_chaude_nb_par_an' => $ec_nb,
            'eau_chaude_duree_max' => $ec_duree,
            'eau_chaude_citation'  => $ec_cit,
        ];

        $u = wp_get_current_user();
        $ok = $wpdb->insert($wpdb->prefix . 'lfi_nct_responses', [
            'militant_user_id'  => $u->ID,
            'militant_login'    => $u->user_login,
            'submitted_at'      => current_time('mysql'),
            'adresse'           => $adresse,
            'etage'             => $etage,
            'annee_arrivee'     => $arrivee ?: null,
            'data'              => wp_json_encode($data, JSON_UNESCAPED_UNICODE),
            'contact_recontact' => $recontact,
            'contact_prenom'    => $prenom,
            'contact_nom'       => $nom,
            'contact_tel'       => $tel,
            'contact_email'     => $email,
        ]);
        if ($ok) {
            delete_transient('lfi_nct_known_addresses'); // refresh datalist
            wp_safe_redirect(lfi_nct_app_url('temoignage-add', ['added' => $wpdb->insert_id]));
            exit;
        }
    }

    lfi_nct_app_screen_open('+ Saisir une réponse d\'enquête', 'Pour les personnes rencontrées en porte-à-porte (intégrée à l\'enquête)');

    if (!empty($_GET['added'])) {
        $id = (int) $_GET['added'];
        lfi_nct_app_flash("✅ Réponse #$id ajoutée à l'enquête.");
    }

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_temoignage_add');
    echo '<input type="hidden" name="lfi_app_temoignage_add" value="1">';

    echo '<h3 style="margin:0">👤 Contact</h3>';
    echo '<label>Prénom<input type="text" name="prenom" required></label>';
    echo '<label>Nom<input type="text" name="nom"></label>';
    echo '<label>Téléphone<input type="tel" name="tel" placeholder="06 12 34 56 78"></label>';
    echo '<label>Email<input type="email" name="email" placeholder="@"></label>';

    /* Adresse avec datalist (rues canoniques + déjà saisies) + auto-correction au save */
    echo '<label>Adresse / immeuble<input type="text" name="adresse" list="lfi-nct-known-streets" autocomplete="off" placeholder="ex : 12 rue d\'Hendaye"></label>';
    echo lfi_nct_streets_datalist('lfi-nct-known-streets');
    echo '<div class="lfi-app-help"><small>💡 Tape pour voir les suggestions. L\'orthographe est corrigée automatiquement (ex : « Saint-Jean-de-Luse » → « Saint-Jean-de-Luz »).</small></div>';

    echo '<label>Étage<input type="text" name="etage"></label>';
    echo '<label>Année d\'arrivée dans le logement<input type="number" name="annee_arrivee" placeholder="ex : 2018" min="1950" max="' . date('Y') . '"></label>';
    echo '<label class="lfi-app-checkbox-row"><input type="checkbox" name="contact_recontact" value="1" checked> ✓ La personne accepte d\'être recontactée par le GA (RGPD)</label>';

    echo '<h3 style="margin:18px 0 0">🏠 Problèmes signalés</h3>';
    $types_lab = lfi_nct_problem_types_all();
    $custom_keys = array_keys(lfi_nct_problem_types_custom());
    echo '<div class="lfi-checkbox-grid">';
    foreach ($types_lab as $k => $l) {
        $tag = in_array($k, $custom_keys, true) ? ' <small style="opacity:.6;font-weight:400">(appris)</small>' : '';
        echo '<label class="lfi-app-checkbox-row" style="cursor:pointer"><input type="checkbox" name="problemes_types[]" value="' . esc_attr($k) . '"> <span>' . $l . $tag . '</span></label>';
    }
    echo '</div>';
    echo '<label>Autre problème (texte libre) — sera ajouté aux choix la prochaine fois<input type="text" name="problemes_types_autre" placeholder="Ex : « porte d\'entrée cassée »"></label>';

    echo '<label>Depuis combien de temps ?<select name="problemes_duree">';
    foreach (['' => '—', 'moins_1_mois'=>"< 1 mois", '1_6_mois'=>"1 à 6 mois", '6_12_mois'=>"6 à 12 mois", '1_5_ans'=>"> 1 an", 'plus_5_ans'=>"> 5 ans"] as $k => $l) {
        echo '<option value="' . esc_attr($k) . '">' . esc_html($l) . '</option>';
    }
    echo '</select></label>';

    echo '<label>Récurrence<select name="problemes_recurrent">';
    foreach (['' => '—', 'permanent'=>'En permanence', 'parfois'=>'Régulièrement', 'ponctuel'=>'Ponctuel'] as $k => $l) {
        echo '<option value="' . esc_attr($k) . '">' . esc_html($l) . '</option>';
    }
    echo '</select></label>';

    echo '<label>Gravité ressentie (1 = mineur, 10 = critique)<select name="problemes_gravite">';
    for ($i = 0; $i <= 10; $i++) {
        $sel = $i === 5 ? 'selected' : '';
        echo '<option value="' . $i . '" ' . $sel . '>' . ($i === 0 ? '—' : $i) . '</option>';
    }
    echo '</select></label>';

    echo '<h3 style="margin:18px 0 0">🚿 Eau chaude (le sujet du quartier)</h3>';
    echo '<label>Coupures par an (estimation)<input type="text" name="eau_chaude_nb_par_an" placeholder="ex : 10, « plus de 15 »"></label>';
    echo '<label>Plus longue coupure subie<input type="text" name="eau_chaude_duree_max" placeholder="ex : 3 semaines"></label>';
    echo '<label>Citation / verbatim<textarea name="eau_chaude_citation" rows="2" placeholder="Notez tel que dit"></textarea></label>';

    echo '<label>📝 Vos notes (admin)<textarea name="notes" rows="3" placeholder="Détails du porte-à-porte, contexte…"></textarea></label>';

    echo '<button type="submit" class="btn-primary big">✓ Enregistrer ce témoignage</button>';
    echo '</form>';

    lfi_nct_app_screen_close();
}
