<?php
if (!defined('ABSPATH')) exit;

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
        PRIMARY KEY (id),
        KEY militant_user_id (militant_user_id),
        KEY submitted_at (submitted_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function lfi_nct_get_responses($limit = 100, $offset = 0) {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
        $limit, $offset
    ));
}

function lfi_nct_count_responses() {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
}