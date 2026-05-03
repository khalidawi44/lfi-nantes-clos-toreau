<?php
if (!defined('ABSPATH')) exit;

function lfi_nct_handle_submission() {
    if (!isset($_POST['lfi_nct_nonce']) || !wp_verify_nonce($_POST['lfi_nct_nonce'], 'lfi_nct_submit_nonce')) {
        return 'Erreur de sécurité : nonce invalide. Recharge la page.';
    }
    if (!is_user_logged_in()) {
        return 'Vous devez être connecté.';
    }

    $user = wp_get_current_user();

    $adresse = sanitize_text_field($_POST['adresse'] ?? '');
    $etage = sanitize_text_field($_POST['etage'] ?? '');
    $annee_arrivee = intval($_POST['annee_arrivee'] ?? 0);

    if (empty($adresse) || empty($etage) || $annee_arrivee < 1950 || $annee_arrivee > 2030) {
        return 'Section 1 (Logement) incomplète ou année invalide.';
    }

    $contact_recontact = !empty($_POST['contact_recontact']) ? 1 : 0;
    $contact_prenom = sanitize_text_field($_POST['contact_prenom'] ?? '');
    $contact_nom = sanitize_text_field($_POST['contact_nom'] ?? '');
    $contact_tel = sanitize_text_field($_POST['contact_tel'] ?? '');
    $contact_email = sanitize_email($_POST['contact_email'] ?? '');

    if ($contact_recontact && empty($contact_prenom)) {
        return 'Si la personne demande à être recontactée, son prénom est obligatoire.';
    }

    $structured_keys = ['lfi_nct_nonce', 'lfi_nct_submit', 'adresse', 'etage', 'annee_arrivee',
                        'contact_recontact', 'contact_prenom', 'contact_nom', 'contact_tel', 'contact_email', '_wp_http_referer'];
    $data = [];
    foreach ($_POST as $key => $value) {
        if (in_array($key, $structured_keys, true)) continue;
        if (is_array($value)) {
            $data[$key] = array_map('sanitize_text_field', $value);
        } else {
            $data[$key] = sanitize_textarea_field($value);
        }
    }

    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $insert = $wpdb->insert($table, [
        'militant_user_id' => $user->ID,
        'militant_login' => $user->user_login,
        'adresse' => $adresse,
        'etage' => $etage,
        'annee_arrivee' => $annee_arrivee,
        'data' => wp_json_encode($data, JSON_UNESCAPED_UNICODE),
        'contact_recontact' => $contact_recontact,
        'contact_prenom' => $contact_prenom,
        'contact_nom' => $contact_nom,
        'contact_tel' => $contact_tel,
        'contact_email' => $contact_email,
    ]);

    if ($insert === false) {
        return 'Erreur DB : ' . esc_html($wpdb->last_error);
    }
    return true;
}