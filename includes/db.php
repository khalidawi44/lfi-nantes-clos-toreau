<?php
if (!defined('ABSPATH')) exit;

/**
 * Crée / met à niveau la table des réponses.
 * dbDelta ajoute les colonnes manquantes sans toucher aux données.
 */
function lfi_nct_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        militant_user_id BIGINT UNSIGNED NOT NULL,
        militant_login VARCHAR(60) NOT NULL,
        submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        adresse VARCHAR(255) DEFAULT NULL,
        etage VARCHAR(50) DEFAULT NULL,
        annee_arrivee SMALLINT UNSIGNED DEFAULT NULL,
        data LONGTEXT DEFAULT NULL,
        contact_recontact TINYINT(1) DEFAULT 0,
        contact_prenom VARCHAR(100) DEFAULT NULL,
        contact_nom VARCHAR(100) DEFAULT NULL,
        contact_tel VARCHAR(20) DEFAULT NULL,
        contact_email VARCHAR(150) DEFAULT NULL,
        deleted_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY militant_user_id (militant_user_id),
        KEY submitted_at (submitted_at),
        KEY deleted_at (deleted_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Lance la migration si la version DB stockée est en retard. Joue à chaque
 * mise à niveau du plugin. dbDelta est idempotent (ne touche pas aux données).
 */
add_action('init', 'lfi_nct_maybe_upgrade_responses_table', 7);
function lfi_nct_maybe_upgrade_responses_table() {
    if (get_option('lfi_nct_responses_db_v') === '2') return;
    lfi_nct_create_table();
    update_option('lfi_nct_responses_db_v', '2', false);
}

/**
 * Liste des réponses. Par défaut, exclut les réponses mises à la corbeille.
 * @param int  $limit          Nombre max de lignes
 * @param int  $offset         Décalage
 * @param bool $include_deleted  Si vrai : seulement les réponses supprimées (corbeille)
 */
function lfi_nct_get_responses($limit = 100, $offset = 0, $include_deleted = false) {
    global $wpdb;
    $table  = $wpdb->prefix . 'lfi_nct_responses';
    $clause = $include_deleted ? 'WHERE deleted_at IS NOT NULL' : 'WHERE deleted_at IS NULL';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table $clause ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
        $limit, $offset
    ));
}

function lfi_nct_count_responses($include_deleted = false) {
    global $wpdb;
    $table  = $wpdb->prefix . 'lfi_nct_responses';
    $clause = $include_deleted ? 'WHERE deleted_at IS NOT NULL' : 'WHERE deleted_at IS NULL';
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table $clause");
}
