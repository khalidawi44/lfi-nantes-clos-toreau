<?php
/**
 * Espace adhérent : connexion en façade (sans wp-login.php) + menu dynamique.
 *
 * - Shortcode [lfi_nct_compte] pour la page /mon-compte : formulaire de
 *   connexion quand on est déconnecté (+ lien d'inscription Action Populaire),
 *   tableau de bord + déconnexion quand on est connecté.
 * - Élément de menu dynamique : un item portant la classe CSS
 *   "lfi-espace-adherent" devient « M'inscrire / Me connecter » (déconnecté)
 *   ou « Espace adhérent » (connecté).
 */
if (!defined('ABSPATH')) exit;

// URL du groupe sur Action Populaire (pour le bouton « M'inscrire »).
// À renseigner — laisser vide masque le bouton.
if (!defined('LFI_NCT_ACTION_POPULAIRE_URL')) {
    define('LFI_NCT_ACTION_POPULAIRE_URL', '');
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
                <a class="lfi-btn lfi-btn-ap" href="<?php echo esc_url($ap_url); ?>" target="_blank" rel="noopener">M'inscrire sur Action Populaire</a>
            <?php else: ?>
                <p class="lfi-help">(Lien Action Populaire à configurer.)</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Libellé dynamique d'un élément de menu portant la classe « lfi-espace-adherent ».
 */
add_filter('wp_nav_menu_objects', 'lfi_nct_dynamic_account_menu', 10, 2);
function lfi_nct_dynamic_account_menu($items, $args) {
    foreach ($items as $item) {
        if (in_array('lfi-espace-adherent', (array) $item->classes, true)) {
            $item->title = is_user_logged_in() ? 'Espace adhérent' : "M'inscrire / Me connecter";
        }
    }
    return $items;
}
