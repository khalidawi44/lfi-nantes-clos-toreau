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
        'adhesion_signature', /* traité à part (image base64, volumineux) */
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

    /* On ne garde pas le champ fichier brut dans le JSON */
    unset($data['enquete_photos']);

    /* === PHOTOS DU LOGEMENT (multiples, horodatées) ===
       Prises pendant la visite quand la personne nous invite à entrer.
       Chaque photo est rattachée à la médiathèque et stockée avec la
       date/heure d'enregistrement (horodatage serveur, fiable) dans le
       JSON de la réponse — pas de migration de schéma. Strictement interne. */
    $photos = [];
    if (!empty($_FILES['enquete_photos']) && !empty($_FILES['enquete_photos']['name'][0])) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $f     = $_FILES['enquete_photos'];
        $count = is_array($f['name']) ? count($f['name']) : 0;
        $stamp = current_time('mysql');
        for ($i = 0; $i < $count; $i++) {
            if (empty($f['name'][$i]) || !empty($f['error'][$i])) continue;
            $type = (string) ($f['type'][$i] ?? '');
            if (strpos($type, 'image/') !== 0) continue; // images uniquement
            $_FILES['lfi_nct_enquete_photo_one'] = [
                'name'     => $f['name'][$i],
                'type'     => $type,
                'tmp_name' => $f['tmp_name'][$i],
                'error'    => $f['error'][$i],
                'size'     => $f['size'][$i],
            ];
            $aid = media_handle_upload('lfi_nct_enquete_photo_one', 0);
            if (!is_wp_error($aid)) {
                $photos[] = ['id' => (int) $aid, 'date' => $stamp];
            }
        }
        unset($_FILES['lfi_nct_enquete_photo_one']);
    }
    if ($photos) {
        $data['photos'] = $photos;
        $data['photos_count'] = count($photos);
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
        /* Cloisonnement : l'enquête appartient au groupe d'action en cours de
           saisie (Clos Toreau = '' ; Rezé, Port-Boyer… = leur slug). Ainsi une
           enquête faite pour Rezé ne réapparaît jamais chez un autre GA. */
        'ga'                => (function_exists('lfi_nct_creation_ga') ? lfi_nct_creation_ga() : ''),
    ]);

    delete_transient('lfi_nct_known_addresses');

    if ($insert === false) {
        return 'Erreur DB : ' . esc_html($wpdb->last_error);
    }
    $rid = (int) $wpdb->insert_id;
    $GLOBALS['lfi_nct_last_submission_id'] = $rid;

    /* === ADHÉSION À L'ASSOCIATION (signature sur place) ===
       Si la personne accepte le suivi, elle devient adhérente gratuite de
       l'Union des Quartiers Libres et signe sur l'écran. On enregistre la fiche
       (date de naissance, adresse) + l'image de la signature, rattachées à la
       réponse. Le dossier juridique est créé juste après (mécanisme existant). */
    $sig     = (string) ($_POST['adhesion_signature'] ?? '');
    $consent = !empty($_POST['adhesion_consent']);
    if ($contact_recontact && ($consent || $sig !== '')) {
        $adh = [
            'signed'    => false,
            'date'      => current_time('mysql'),
            'consent'   => $consent,
            'naissance' => sanitize_text_field($_POST['adhesion_naissance'] ?? ''),
            'adresse'   => sanitize_text_field($_POST['adhesion_adresse'] ?? ''),
        ];
        if (strpos($sig, 'data:image/png;base64,') === 0) {
            $bin = base64_decode(substr($sig, 22), true);
            if ($bin !== false && strlen($bin) > 100 && strlen($bin) < 2 * 1024 * 1024) {
                $up = wp_upload_bits('adhesion-signature-' . $rid . '.png', null, $bin);
                if (empty($up['error']) && !empty($up['file'])) {
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    $aid = wp_insert_attachment([
                        'post_mime_type' => 'image/png',
                        'post_title'     => 'Signature adhésion enquête #' . $rid,
                        'post_status'    => 'inherit',
                    ], $up['file']);
                    if (!is_wp_error($aid)) {
                        @wp_update_attachment_metadata($aid, wp_generate_attachment_metadata($aid, $up['file']));
                        $adh['signature_id']  = (int) $aid;
                        $adh['signature_url'] = $up['url'];
                        $adh['signed']        = true;
                    }
                }
            }
        }
        $data['adhesion'] = $adh;
        /* Persiste la fiche d'adhésion dans la réponse (avant création dossier). */
        $wpdb->update($table, ['data' => wp_json_encode($data, JSON_UNESCAPED_UNICODE)], ['id' => $rid]);
    }

    /* AUTO : si la personne veut être recontactée et que c'est un·e militant·e
       CONNECTÉ·E qui saisit → on crée directement, POUR L'ÉQUIPE, le compte
       locataire + le dossier juridique liés à cette enquête. (Le militant simple
       n'y a pas accès : réservé aux admins.) */
    if ($contact_recontact && $militant_user_id
        && ($contact_prenom !== '' || $contact_nom !== '')
        && function_exists('lfi_nct_ep_ensure_tenant')) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $rid));
        if ($row) {
            $tenant_uid = lfi_nct_ep_ensure_tenant($row);
            if (function_exists('lfi_nct_ep_create_dossier')) {
                lfi_nct_ep_create_dossier($row, $tenant_uid, '', '');
            }
            $GLOBALS['lfi_nct_last_tenant_id'] = (int) $tenant_uid;
        }
    }
    return true;
}
