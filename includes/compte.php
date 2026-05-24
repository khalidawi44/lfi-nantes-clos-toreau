<?php
/**
 * Espace adhérent : connexion en façade (sans wp-login.php) + entrées de menu.
 *
 * - Shortcode [lfi_nct_compte] pour la page /mon-compte : formulaire de
 *   connexion quand on est déconnecté (+ lien d'inscription Action Populaire),
 *   tableau de bord + déconnexion quand on est connecté.
 * - Entrées de menu ajoutées automatiquement au menu principal (sans wp-admin) :
 *   « 📅 Prendre rendez-vous » et « Espace adhérent » / « M'inscrire / Me connecter ».
 * - « Rejoindre LFI » est masqué quand l'utilisateur est connecté.
 */
if (!defined('ABSPATH')) exit;

// URL du groupe sur Action Populaire (pour « Rejoindre le groupe »).
if (!defined('LFI_NCT_ACTION_POPULAIRE_URL')) {
    define('LFI_NCT_ACTION_POPULAIRE_URL', 'https://actionpopulaire.fr/groupes/3f07362c-8238-4a63-9b0c-4128e9ec6ede/');
}

// URL « Trouver un groupe près de chez moi » sur Action Populaire.
if (!defined('LFI_NCT_AP_CARTE_URL')) {
    define('LFI_NCT_AP_CARTE_URL', 'https://actionpopulaire.fr/groupes/');
}

// Telegram : lien d'invitation au groupe + lien du fil « rendez-vous » (membres).
if (!defined('LFI_NCT_TELEGRAM_INVITE')) {
    define('LFI_NCT_TELEGRAM_INVITE', 'https://t.me/+bt8Wm58ejXxlYWRk');
}
if (!defined('LFI_NCT_TELEGRAM_RDV')) {
    define('LFI_NCT_TELEGRAM_RDV', 'https://t.me/c/3045636337/37');
}

/**
 * Traite la connexion en façade avant tout affichage (pour pouvoir rediriger).
 * Passe par wp_signon() : la connexion ne dépend pas de wp-login.php et reste
 * couverte par Wordfence (qui se branche sur le filtre "authenticate").
 */
add_action('template_redirect', 'lfi_nct_handle_login');
function lfi_nct_handle_login() {
    if (empty($_POST['lfi_nct_login_nonce'])) return;
    if (is_user_logged_in()) return;

    if (!wp_verify_nonce($_POST['lfi_nct_login_nonce'], 'lfi_nct_login')) {
        $GLOBALS['lfi_nct_login_error'] = 'Session expirée, recharge la page et réessaie.';
        return;
    }

    $creds = [
        'user_login'    => sanitize_text_field(wp_unslash($_POST['lfi_nct_user'] ?? '')),
        'user_password' => (string) ($_POST['lfi_nct_pass'] ?? ''),
        'remember'      => !empty($_POST['lfi_nct_remember']),
    ];

    $user = wp_signon($creds, is_ssl());
    if (is_wp_error($user)) {
        $GLOBALS['lfi_nct_login_error'] = 'Identifiant ou mot de passe incorrect.';
        return;
    }

    $redirect = wp_get_referer();
    if (!$redirect) $redirect = home_url('/mon-compte/');
    wp_safe_redirect($redirect);
    exit;
}

/**
 * Affiche le formulaire / tableau de bord à la suite du contenu de la page
 * « mon-compte », sans avoir à insérer le shortcode à la main.
 * Le contenu existant de la page est conservé.
 */
add_filter('the_content', 'lfi_nct_inject_compte_form', 20);
function lfi_nct_inject_compte_form($content) {
    if (is_admin() || !in_the_loop() || !is_main_query() || !is_page()) return $content;
    $post = get_post();
    if (!$post || $post->post_name !== 'mon-compte') return $content;
    if (has_shortcode($post->post_content, 'lfi_nct_compte')) return $content;
    return $content . do_shortcode('[lfi_nct_compte]');
}

/**
 * Encart « Prendre rendez-vous sur Telegram » injecté en haut de la page
 * /rendez-vous. Le contenu existant (dont Cal.com) est conservé en dessous.
 */
add_filter('the_content', 'lfi_nct_inject_telegram_rdv', 15);
function lfi_nct_inject_telegram_rdv($content) {
    if (is_admin() || !in_the_loop() || !is_main_query() || !is_page()) return $content;
    $post = get_post();
    if (!$post || $post->post_name !== 'rendez-vous') return $content;
    return lfi_nct_telegram_rdv_box() . $content;
}

function lfi_nct_telegram_rdv_box() {
    $invite = esc_url(LFI_NCT_TELEGRAM_INVITE);
    $rdv    = esc_url(LFI_NCT_TELEGRAM_RDV);
    ob_start(); ?>
    <div class="lfi-compte lfi-tg-rdv">
        <h2>📅 Prendre rendez-vous sur Telegram</h2>
        <p>Les rendez-vous se prennent dans notre groupe Telegram. Rejoignez le groupe, puis allez dans le fil « Rendez-vous » pour réserver un créneau avec un·e bénévole.</p>
        <p><a class="lfi-btn lfi-btn-tg lfi-popup" href="<?php echo $invite; ?>" target="_blank" rel="noopener">Rejoindre le groupe Telegram</a></p>
        <p class="lfi-help">Déjà membre du groupe ? <a class="lfi-popup" href="<?php echo $rdv; ?>" target="_blank" rel="noopener">Accéder au fil rendez-vous</a>.</p>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('lfi_nct_compte', 'lfi_nct_compte_shortcode');
function lfi_nct_compte_shortcode() {
    if (is_user_logged_in()) {
        $u = wp_get_current_user();
        ob_start(); ?>
        <div class="lfi-compte lfi-compte-in">
            <h2>Espace adhérent</h2>
            <p>Bonjour <strong><?php echo esc_html($u->display_name); ?></strong>.</p>
            <p class="lfi-compte-email"><?php echo esc_html($u->user_email); ?></p>
            <p><a class="lfi-btn" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Se déconnecter</a></p>
        </div>
        <?php
        return ob_get_clean();
    }

    $error = $GLOBALS['lfi_nct_login_error'] ?? '';
    $ap_url = LFI_NCT_ACTION_POPULAIRE_URL;
    ob_start(); ?>
    <div class="lfi-compte lfi-compte-out">
        <h2>Me connecter</h2>
        <?php if ($error): ?>
            <div class="lfi-error"><?php echo esc_html($error); ?></div>
        <?php endif; ?>
        <form method="post" class="lfi-login-form">
            <?php wp_nonce_field('lfi_nct_login', 'lfi_nct_login_nonce'); ?>
            <label class="lfi-field">
                <span class="lfi-label">Identifiant ou email</span>
                <input type="text" name="lfi_nct_user" autocomplete="username" required>
            </label>
            <label class="lfi-field">
                <span class="lfi-label">Mot de passe</span>
                <input type="password" name="lfi_nct_pass" autocomplete="current-password" required>
            </label>
            <label class="lfi-remember"><input type="checkbox" name="lfi_nct_remember" value="1"> Se souvenir de moi</label>
            <button type="submit" class="lfi-btn">Me connecter</button>
        </form>

        <div class="lfi-inscription">
            <h3>Pas encore adhérent ?</h3>
            <p>L'inscription se fait sur Action Populaire, dans notre groupe.</p>
            <?php if ($ap_url !== ''): ?>
                <a class="lfi-btn lfi-btn-ap lfi-popup" href="<?php echo esc_url($ap_url); ?>" target="_blank" rel="noopener">M'inscrire sur Action Populaire</a>
            <?php else: ?>
                <p class="lfi-help">(Lien Action Populaire à configurer.)</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Garde-fou pour n'ajouter les entrées qu'une seule fois par page,
 * que le menu soit classique (wp_nav_menu) ou en blocs (FSE).
 */
function lfi_nct_menu_extra_added($set = false) {
    static $done = false;
    if ($set) $done = true;
    return $done;
}

/**
 * Construit les entrées de menu : « Prendre rendez-vous » (à plat) + un menu
 * déroulant « Rejoindre » (déconnecté) / « Espace adhérent » (connecté).
 * $style : 'classic' (wp_nav_menu) ou 'block' (navigation FSE, version à plat).
 */
function lfi_nct_menu_extra_items($style = 'classic') {
    $compte_url = esc_url(home_url('/mon-compte/'));
    $rdv_url    = esc_url(home_url('/rendez-vous/'));
    $logged_in  = is_user_logged_in();

    if ($logged_in) {
        $parent_label = 'Espace adhérent';
        $parent_url   = $compte_url;
        $subs = [
            ['Mon compte', $compte_url, false],
            ['Se déconnecter', esc_url(wp_logout_url(home_url('/'))), false],
        ];
    } else {
        $parent_label = 'Rejoindre';
        $parent_url   = esc_url(home_url('/adherer/'));
        $subs = [
            ['Rejoindre le groupe', esc_url(LFI_NCT_ACTION_POPULAIRE_URL), true],
            ['Trouver mon groupe près de chez moi', esc_url(LFI_NCT_AP_CARTE_URL), true],
            ["Connexion / S'inscrire", $compte_url, false],
        ];
    }

    if ($style === 'block') {
        // Thème en blocs (fallback) : entrées à plat.
        $rdv = '<li class="wp-block-navigation-item wp-block-navigation-link lfi-menu-rdv"><a class="wp-block-navigation-item__content" href="' . $rdv_url . '"><span class="wp-block-navigation-item__label">' . esc_html('📅 Prendre rendez-vous') . '</span></a></li>';
        $par = '<li class="wp-block-navigation-item wp-block-navigation-link"><a class="wp-block-navigation-item__content" href="' . $parent_url . '"><span class="wp-block-navigation-item__label">' . esc_html($parent_label) . '</span></a></li>';
        return $rdv . $par;
    }

    $sub_html = '';
    foreach ($subs as $s) {
        $ext = $s[2] ? ' target="_blank" rel="noopener"' : '';
        $sub_html .= '<li class="menu-item"><a href="' . $s[1] . '"' . $ext . '>' . esc_html($s[0]) . '</a></li>';
    }

    $rdv = '<li class="menu-item lfi-menu-rdv"><a href="' . $rdv_url . '">' . esc_html('📅 Prendre rendez-vous') . '</a></li>';
    $dropdown = '<li class="menu-item menu-item-has-children lfi-menu-dropdown">'
        . '<a href="' . $parent_url . '">' . esc_html($parent_label) . ' ▾</a>'
        . '<ul class="sub-menu">' . $sub_html . '</ul></li>';

    return $rdv . $dropdown;
}

/**
 * Cas thème classique : ajoute à tous les menus rendus qui ne sont pas des
 * menus pied-de-page / réseaux sociaux (pour atteindre l'en-tête à coup sûr,
 * même si le thème rend plusieurs menus, ex. desktop + mobile).
 */
add_filter('wp_nav_menu_items', 'lfi_nct_append_menu_items', 10, 2);
function lfi_nct_append_menu_items($items_html, $args) {
    $loc = (is_object($args) && !empty($args->theme_location)) ? strtolower($args->theme_location) : '';
    if ($loc !== '' && preg_match('/(footer|social|bottom|pied|legal|mentions)/', $loc)) {
        return $items_html;
    }
    return $items_html . lfi_nct_menu_extra_items('classic');
}

/**
 * Cas thème en blocs (FSE) : injecte les entrées dans le bloc de navigation.
 */
add_filter('render_block', 'lfi_nct_inject_nav_block', 10, 2);
function lfi_nct_inject_nav_block($block_content, $block) {
    if (empty($block['blockName']) || $block['blockName'] !== 'core/navigation') return $block_content;
    if (lfi_nct_menu_extra_added()) return $block_content;

    lfi_nct_menu_extra_added(true);
    $items = lfi_nct_menu_extra_items('block');

    $pos = strripos($block_content, '</ul>');
    if ($pos !== false) {
        return substr($block_content, 0, $pos) . $items . substr($block_content, $pos);
    }
    return $block_content . $items;
}

/**
 * Supprime les entrées « Rejoindre LFI » / « Rejoindre le groupe » d'origine
 * (le doublon) : elles sont remplacées par le menu déroulant « Rejoindre ».
 * Respecte aussi la classe « lfi-hide-when-logged-in ».
 */
add_filter('wp_nav_menu_objects', 'lfi_nct_hide_redundant_join', 10, 2);
function lfi_nct_hide_redundant_join($items, $args) {
    $logged_in = is_user_logged_in();
    $kept = [];
    foreach ($items as $item) {
        $classes = (array) $item->classes;
        $title = strtolower(wp_strip_all_tags($item->title));
        if (strpos($title, 'rejoindre') !== false) {
            continue;
        }
        if ($logged_in && in_array('lfi-hide-when-logged-in', $classes, true)) {
            continue;
        }
        $kept[] = $item;
    }
    return $kept;
}

/**
 * Styles du menu déroulant (chargés sur tout le site, car le menu est partout).
 * Le sous-menu s'ouvre au survol, indépendamment du thème.
 */
add_action('wp_head', 'lfi_nct_menu_dropdown_css', 20);
function lfi_nct_menu_dropdown_css() {
    echo '<style id="lfi-nct-menu-css">'
        . '.lfi-menu-dropdown{position:relative}'
        . '.lfi-menu-dropdown>.sub-menu{display:none;position:absolute;top:100%;left:0;z-index:99999;min-width:250px;margin:0;padding:.4em 0;list-style:none;background:#2d0a2e;box-shadow:0 8px 24px rgba(0,0,0,.3);border-radius:0 0 6px 6px}'
        . '.lfi-menu-dropdown:hover>.sub-menu,.lfi-menu-dropdown:focus-within>.sub-menu{display:block}'
        . '.lfi-menu-dropdown>.sub-menu>li{display:block;margin:0;float:none}'
        . '.lfi-menu-dropdown>.sub-menu>li>a{display:block;padding:.55em 1.3em;color:#fff;white-space:nowrap;text-decoration:none;font-size:.95em}'
        . '.lfi-menu-dropdown>.sub-menu>li>a:hover{background:rgba(255,255,255,.14)}'
        . '</style>' . "\n";
}
