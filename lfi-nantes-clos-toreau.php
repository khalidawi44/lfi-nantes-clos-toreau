<?php
/**
 * Plugin Name: LFI Nantes Clos Toreau — Outils du GA
 * Description: Outils numériques du Groupe d'Action LFI Nantes Sud Clos Toreau (formulaire enquête logement HLM, modules futurs).
 * Version: 0.70.0
 * Author: Khalid Awi (LFI Nantes Sud Clos Toreau)
 * License: GPL v2 or later
 * Text Domain: lfi-nct
 */

if (!defined('ABSPATH')) exit;

define('LFI_NCT_VERSION', '0.70.0');
define('LFI_NCT_PATH', plugin_dir_path(__FILE__));
define('LFI_NCT_URL', plugin_dir_url(__FILE__));

require_once LFI_NCT_PATH . 'includes/db.php';
require_once LFI_NCT_PATH . 'includes/form-render.php';
require_once LFI_NCT_PATH . 'includes/form-handler.php';
require_once LFI_NCT_PATH . 'includes/gravity.php';
require_once LFI_NCT_PATH . 'includes/admin.php';
require_once LFI_NCT_PATH . 'includes/stats.php';
require_once LFI_NCT_PATH . 'includes/compte.php';
require_once LFI_NCT_PATH . 'includes/rdv.php';
require_once LFI_NCT_PATH . 'includes/arpege.php';
require_once LFI_NCT_PATH . 'includes/map.php';
require_once LFI_NCT_PATH . 'includes/event-reunion-confluences-20260626.php';
require_once LFI_NCT_PATH . 'includes/event-conference-municipales-20260708.php';
require_once LFI_NCT_PATH . 'includes/membres.php';
require_once LFI_NCT_PATH . 'includes/sms.php';
require_once LFI_NCT_PATH . 'includes/events.php';
require_once LFI_NCT_PATH . 'includes/news-seed.php';
require_once LFI_NCT_PATH . 'includes/email-blast.php';
require_once LFI_NCT_PATH . 'includes/maintenance.php';
require_once LFI_NCT_PATH . 'includes/hide-theme-promo.php';
require_once LFI_NCT_PATH . 'includes/social-share.php';
require_once LFI_NCT_PATH . 'includes/app.php';
require_once LFI_NCT_PATH . 'includes/app-roles.php';
require_once LFI_NCT_PATH . 'includes/app-pro.php';
require_once LFI_NCT_PATH . 'includes/facturation.php';
require_once LFI_NCT_PATH . 'includes/recouvrement.php';
require_once LFI_NCT_PATH . 'includes/dossiers-locataires.php';
require_once LFI_NCT_PATH . 'includes/appels-nmh.php';
require_once LFI_NCT_PATH . 'includes/tutoriels.php';
require_once LFI_NCT_PATH . 'includes/agenda.php';
require_once LFI_NCT_PATH . 'includes/outils-scientifiques.php';
require_once LFI_NCT_PATH . 'includes/inscription.php';
require_once LFI_NCT_PATH . 'includes/site-navbar.php';
require_once LFI_NCT_PATH . 'includes/admin-dashboard.php';
require_once LFI_NCT_PATH . 'includes/admin-comptes.php';

register_activation_hook(__FILE__, 'lfi_nct_activate');
function lfi_nct_activate() {
    lfi_nct_create_table();
}

/**
 * Purge automatiquement les caches dès que la version du plugin change
 * (= dès qu'Hostinger Git Tool a tiré un nouveau commit). Sur la première
 * requête après le déploiement, on détecte la nouvelle version et on vide
 * LiteSpeed Cache (et d'autres caches connus s'ils sont présents).
 */
add_action('init', 'lfi_nct_purge_cache_on_upgrade', 5);
function lfi_nct_purge_cache_on_upgrade() {
    $installed = (string) get_option('lfi_nct_installed_version', '');
    if ($installed === LFI_NCT_VERSION) return;

    // On note la nouvelle version AVANT de purger pour ne jamais boucler.
    update_option('lfi_nct_installed_version', LFI_NCT_VERSION, false);

    // LiteSpeed Cache (le cache utilisé sur le site)
    do_action('litespeed_purge_all');
    // Filets de sécurité si d'autres caches sont actifs
    if (function_exists('wp_cache_clear_cache')) wp_cache_clear_cache(); // WP Super Cache
    if (function_exists('w3tc_flush_all'))       w3tc_flush_all();       // W3 Total Cache
    if (function_exists('rocket_clean_domain'))  rocket_clean_domain();  // WP Rocket
    if (function_exists('wp_cache_flush'))       wp_cache_flush();       // Cache objet
}

add_action('wp_enqueue_scripts', 'lfi_nct_enqueue_assets');
function lfi_nct_enqueue_assets() {
    // Script « petite fenêtre » chargé partout : des liens .lfi-popup sont
    // présents dans le menu (présent sur toutes les pages).
    wp_enqueue_script('lfi-nct-compte-js', LFI_NCT_URL . 'assets/js/compte.js', [], LFI_NCT_VERSION, true);

    global $post;
    if (!is_a($post, 'WP_Post')) return;
    $has_survey = has_shortcode($post->post_content, 'lfi_nct_survey');
    $has_compte = lfi_nct_is_compte_page($post);
    $is_rdv     = ($post->post_name === 'rendez-vous');
    $is_arpege  = ($post->post_name === LFI_NCT_ARPEGE_SLUG)
                  || has_shortcode($post->post_content, 'lfi_nct_arpege');
    if ($has_survey || $has_compte || $is_rdv || $is_arpege) {
        wp_enqueue_style('lfi-nct-css', LFI_NCT_URL . 'assets/css/form.css', [], LFI_NCT_VERSION);
    }
    if ($has_survey) {
        wp_enqueue_script('lfi-nct-js', LFI_NCT_URL . 'assets/js/form.js', [], LFI_NCT_VERSION, true);
    }
}

/**
 * Vrai si la page est l'espace adhérent (slug « mon-compte » ou shortcode présent).
 */
function lfi_nct_is_compte_page($post) {
    return is_a($post, 'WP_Post') && (
        $post->post_name === 'mon-compte' ||
        has_shortcode($post->post_content, 'lfi_nct_compte')
    );
}

/**
 * Vrai si la page contient le formulaire d'enquête ou l'espace adhérent
 * (pages privées : non indexées et non mises en cache).
 */
function lfi_nct_is_private_page($post) {
    if (!is_a($post, 'WP_Post')) return false;
    return has_shortcode($post->post_content, 'lfi_nct_survey') || lfi_nct_is_compte_page($post);
}

/**
 * Privacy : noindex sur le formulaire d'enquête et l'espace adhérent.
 * Pages publiques/privées mais non indexées par les moteurs de recherche.
 */
add_action('wp_head', 'lfi_nct_noindex_survey_page', 1);
function lfi_nct_noindex_survey_page() {
    global $post;
    if (lfi_nct_is_private_page($post)) {
        echo '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet">' . "\n";
    }
}

/**
 * Empêche la mise en cache de ces pages.
 * Sans ça, le jeton de sécurité (nonce) du formulaire ou de la connexion
 * serait mis en cache et les envois/connexions échoueraient (« nonce invalide »).
 */
add_action('wp', 'lfi_nct_no_cache_survey_page');
function lfi_nct_no_cache_survey_page() {
    if (!is_singular()) return;
    $post = get_post();
    $is_rdv    = is_a($post, 'WP_Post') && $post->post_name === 'rendez-vous';
    $is_arpege = is_a($post, 'WP_Post') && $post->post_name === LFI_NCT_ARPEGE_SLUG;
    if (lfi_nct_is_private_page($post) || $is_rdv || $is_arpege) {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        do_action('litespeed_control_set_nocache', 'LFI : page avec nonce dynamique');
    }
}

add_shortcode('lfi_nct_survey', 'lfi_nct_survey_shortcode');
function lfi_nct_survey_shortcode() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lfi_nct_submit'])) {
        $result = lfi_nct_handle_submission();
        if ($result === true) {
            $id = $GLOBALS['lfi_nct_last_submission_id'] ?? 0;
            $summary = $id > 0 ? lfi_nct_render_submission_summary($id) : lfi_nct_render_form();
            return '<div class="lfi-success"><h2>Merci !</h2><p>L\'enquête a été enregistrée. Vous pouvez l\'imprimer ci-dessous pour garder une copie papier.</p></div>' . $summary;
        } else {
            return '<div class="lfi-error"><strong>Erreur :</strong> ' . esc_html($result) . '</div>' . lfi_nct_render_form();
        }
    }
    return lfi_nct_render_form();
}