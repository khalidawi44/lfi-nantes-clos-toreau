<?php
if (!defined('ABSPATH')) exit;

function lfi_nct_handle_submission() {
    if (!isset($_POST['lfi_nct_nonce']) || !wp_verify_nonce($_POST['lfi_nct_nonce'], 'lfi_nct_submit_nonce')) {
        return 'Erreur de sécurité : nonce invalide. Recharge la page.';
    }

    $user = wp_get_current_user();
    $militant_user_id = $user->ID;
    $militant_login   = $user->ID ? $user->user_login : 'anonyme';

    $adresse_raw = sanitize_text_field($_POST['adresse'] ?? '');
    /* Auto-correction orthographique des rues du Clos Toreau */
    $adresse = function_exists('lfi_nct_normalize_address') ? lfi_nct_normalize_address($adresse_raw) : $adresse_raw;
    $etage   = sanitize_text_field($_POST['etage'] ?? '');
    if ($adresse === '' || $etage === '') {
        return "L'adresse et l'étage sont obligatoires.";
    }

    $presence = sanitize_text_field($_POST['problemes_presence'] ?? '');
    if (!in_array($presence, ['oui', 'non'], true)) {
        return 'Indiquez si le logement a des problèmes ou non.';
    }

    if ($presence === 'oui') {
        $gravite = (int) ($_POST['problemes_gravite'] ?? 0);
        if ($gravite < 1 || $gravite > 10) {
            return 'Évaluez la gravité du problème sur l\'échelle de 1 à 10.';
        }
    }

    $revenir = sanitize_text_field($_POST['revenir_ok'] ?? '');
    $contact_recontact = ($revenir === 'oui') ? 1 : 0;

    $contact_prenom = sanitize_text_field($_POST['contact_prenom'] ?? '');
    $contact_nom    = sanitize_text_field($_POST['contact_nom'] ?? '');
    $contact_tel    = sanitize_text_field($_POST['contact_tel'] ?? '');
    $contact_email  = sanitize_email($_POST['contact_email'] ?? '');

    if ($contact_recontact && $contact_tel === '' && $contact_email === '') {
        return 'Pour être recontacté·e, indiquez au moins un téléphone ou un email.';
    }

    $structured_keys = [
        'lfi_nct_nonce', 'lfi_nct_submit', 'adresse', 'etage',
        'contact_prenom', 'contact_nom', 'contact_tel', 'contact_email',
        '_wp_http_referer',
    ];
    $data = [];
    foreach ($_POST as $key => $value) {
        if (in_array($key, $structured_keys, true)) continue;
        if (is_array($value)) {
            $data[$key] = array_map('sanitize_text_field', $value);
        } else {
            $data[$key] = sanitize_textarea_field($value);
        }
    }
    /* Apprend automatiquement un nouveau type de problème si « Autre » a été précisé */
    if (function_exists('lfi_nct_learn_custom_problem')) {
        $autre_label = trim((string) ($data['problemes_types_autre'] ?? ''));
        $types_arr   = (array) ($data['problemes_types'] ?? []);
        if ($autre_label !== '' && (in_array('autre', $types_arr, true) || empty($types_arr))) {
            $new_slug = lfi_nct_learn_custom_problem($autre_label);
            if ($new_slug) {
                /* On l'ajoute aux types cochés et on retire le placeholder « autre » */
                if (!in_array($new_slug, $types_arr, true)) $types_arr[] = $new_slug;
                $types_arr = array_values(array_diff($types_arr, ['autre']));
                $data['problemes_types']       = $types_arr;
                $data['problemes_types_autre'] = '';
            }
        }
    }
    /* Trace si l'adresse a été corrigée */
    if (isset($adresse_raw) && $adresse_raw !== $adresse) {
        $data['adresse_brute'] = $adresse_raw;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $insert = $wpdb->insert($table, [
        'militant_user_id'  => $militant_user_id,
        'militant_login'    => $militant_login,
        'adresse'           => $adresse,
        'etage'             => $etage,
        'data'              => wp_json_encode($data, JSON_UNESCAPED_UNICODE),
        'contact_recontact' => $contact_recontact,
        'contact_prenom'    => $contact_prenom,
        'contact_nom'       => $contact_nom,
        'contact_tel'       => $contact_tel,
        'contact_email'     => $contact_email,
    ]);

    delete_transient('lfi_nct_known_addresses');

    if ($insert === false) {
        return 'Erreur DB : ' . esc_html($wpdb->last_error);
    }
    $GLOBALS['lfi_nct_last_submission_id'] = (int) $wpdb->insert_id;
    return true;
}
