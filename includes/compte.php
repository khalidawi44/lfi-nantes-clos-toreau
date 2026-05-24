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

// URL du groupe sur Action Populaire (pour le bouton « M'inscrire »).
// Laisser vide masque le bouton.
if (!defined('LFI_NCT_ACTION_POPULAIRE_URL')) {
    define('LFI_NCT_ACTION_POPULAIRE_URL', 'https://actionpopulaire.fr/groupes/3f07362c-8238-4a63-9b0c-4128e9ec6ede/');
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
 * Construit les entrées « Prendre rendez-vous » + « Espace adhérent ».
 * $style : 'classic' (menus wp_nav_menu) ou 'block' (bloc de navigation FSE).
 */
function lfi_nct_menu_extra_items($style = 'classic') {
    $rdv_url    = esc_url(home_url('/rendez-vous/'));
    $compte_url = esc_url(home_url('/mon-compte/'));
    $rdv_label  = esc_html('📅 Prendre rendez-vous');
    $compte_label = esc_html(is_user_logged_in() ? 'Espace adhérent' : "M'inscrire / Me connecter");

    if ($style === 'block') {
        $tpl = '<li class="wp-block-navigation-item wp-block-navigation-link %3$s"><a class="wp-block-navigation-item__content" href="%1$s"><span class="wp-block-navigation-item__label">%2$s</span></a></li>';
    } else {
        $tpl = '<li class="menu-item %3$s"><a href="%1$s">%2$s</a></li>';
    }

    return sprintf($tpl, $rdv_url, $rdv_label, 'lfi-menu-rdv')
         . sprintf($tpl, $compte_url, $compte_label, 'lfi-menu-compte');
}

/**
 * Cas thème classique : ajoute au premier menu rendu qui n'est pas un menu
 * pied-de-page / réseaux sociaux, peu importe comment le thème le nomme.
 */
add_filter('wp_nav_menu_items', 'lfi_nct_append_menu_items', 10, 2);
function lfi_nct_append_menu_items($items_html, $args) {
    if (lfi_nct_menu_extra_added()) return $items_html;

    $loc = (is_object($args) && !empty($args->theme_location)) ? strtolower($args->theme_location) : '';
    if ($loc !== '' && preg_match('/(footer|social|bottom|pied|legal|mentions)/', $loc)) {
        return $items_html;
    }

    lfi_nct_menu_extra_added(true);
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
 * Masque « Rejoindre LFI » (par titre ou via la classe « lfi-hide-when-logged-in »)
 * quand l'utilisateur est connecté.
 */
add_filter('wp_nav_menu_objects', 'lfi_nct_hide_join_when_logged_in', 10, 2);
function lfi_nct_hide_join_when_logged_in($items, $args) {
    if (!is_user_logged_in()) return $items;
    $kept = [];
    foreach ($items as $item) {
        $classes = (array) $item->classes;
        $title = strtolower(wp_strip_all_tags($item->title));
        if (in_array('lfi-hide-when-logged-in', $classes, true) || strpos($title, 'rejoindre') !== false) {
            continue;
        }
        $kept[] = $item;
    }
    return $kept;
}
