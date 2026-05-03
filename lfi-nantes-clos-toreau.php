<?php
/**
 * Plugin Name: LFI Nantes Clos Toreau — Outils du GA
 * Description: Outils numériques du Groupe d'Action LFI Nantes Sud Clos Toreau (formulaire enquête logement HLM, modules futurs).
 * Version: 0.1.0
 * Author: Khalid Awi (LFI Nantes Sud Clos Toreau)
 * License: GPL v2 or later
 * Text Domain: lfi-nct
 */

if (!defined('ABSPATH')) exit;

define('LFI_NCT_VERSION', '0.1.0');
define('LFI_NCT_PATH', plugin_dir_path(__FILE__));
define('LFI_NCT_URL', plugin_dir_url(__FILE__));

require_once LFI_NCT_PATH . 'includes/db.php';
require_once LFI_NCT_PATH . 'includes/form-render.php';
require_once LFI_NCT_PATH . 'includes/form-handler.php';
require_once LFI_NCT_PATH . 'includes/admin.php';

register_activation_hook(__FILE__, 'lfi_nct_activate');
function lfi_nct_activate() {
    lfi_nct_create_table();
}

add_action('wp_enqueue_scripts', 'lfi_nct_enqueue_assets');
function lfi_nct_enqueue_assets() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'lfi_nct_survey')) {
        wp_enqueue_style('lfi-nct-css', LFI_NCT_URL . 'assets/css/form.css', [], LFI_NCT_VERSION);
        wp_enqueue_script('lfi-nct-js', LFI_NCT_URL . 'assets/js/form.js', [], LFI_NCT_VERSION, true);
    }
}

add_shortcode('lfi_nct_survey', 'lfi_nct_survey_shortcode');
function lfi_nct_survey_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="lfi-warn"><p>Vous devez être connecté en tant que militant LFI Clos Toreau pour accéder à ce formulaire.</p><p><a href="' . esc_url(wp_login_url(get_permalink())) . '">Se connecter</a></p></div>';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lfi_nct_submit'])) {
        $result = lfi_nct_handle_submission();
        if ($result === true) {
            return '<div class="lfi-success"><h2>Merci !</h2><p>L\'enquête a été enregistrée. Tu peux en saisir une nouvelle ci-dessous.</p></div>' . lfi_nct_render_form();
        } else {
            return '<div class="lfi-error"><strong>Erreur :</strong> ' . esc_html($result) . '</div>' . lfi_nct_render_form();
        }
    }
    return lfi_nct_render_form();
}