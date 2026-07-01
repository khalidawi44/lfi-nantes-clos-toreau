<?php
/**
 * PERSONNALISATION PAR GROUPE D'ACTION.
 *
 * Chaque GA peut adapter, sans toucher au code :
 *  - l'en-tête des courriers / emails (nom du GA, coordonnées, responsable) ;
 *  - la liste des bailleurs sociaux (en ajouter / en enlever) ;
 *  - (le bailleur principal NMH + agence de secteur + téléphones restent dans
 *    « Paramètres facturation », déjà par GA).
 *
 * Tout est stocké dans une option, cloisonné par slug de GA. Défauts sûrs :
 * tant que rien n'est saisi, on retombe sur le nom du GA et les bailleurs
 * standard — donc aucun risque de « casser » l'existant.
 */
if (!defined('ABSPATH')) exit;

/** Réglages de personnalisation d'un GA (défauts sûrs). */
function lfi_nct_ga_perso($slug = null) {
    if ($slug === null) $slug = function_exists('lfi_nct_scope_ga_slug') ? lfi_nct_scope_ga_slug() : '';
    $key = ($slug === '' ? 'home' : $slug);
    $all = get_option('lfi_nct_ga_perso', []);
    $data = (is_array($all) && !empty($all[$key]) && is_array($all[$key])) ? $all[$key] : [];

    $ga_nom = function_exists('lfi_nct_ga_nom') ? lfi_nct_ga_nom($slug) : 'GA LFI Nantes Sud – Clos Toreau';
    $defaults = [
        'entete_nom'     => $ga_nom,
        'entete_adresse' => '',
        'entete_tel'     => '',
        'entete_email'   => '',
        'responsable'    => '',
        'bailleurs'      => [], // liste de noms de bailleurs propres au GA
    ];
    return array_merge($defaults, $data);
}

/** Enregistre les réglages d'un GA. */
function lfi_nct_ga_perso_save($slug, $data) {
    $key = ($slug === '' ? 'home' : $slug);
    $all = get_option('lfi_nct_ga_perso', []);
    if (!is_array($all)) $all = [];
    $all[$key] = $data;
    update_option('lfi_nct_ga_perso', $all, false);
}

/** Nom du GA à afficher sur les courriers (en-tête / signature). */
function lfi_nct_ga_entete_nom() {
    $p = lfi_nct_ga_perso();
    return $p['entete_nom'] ?: 'Groupe d\'Action LFI Nantes Sud – Clos Toreau';
}

/* ============================================================== *
 *  Bailleurs sociaux : liste standard + ceux ajoutés par le GA    *
 *  (filtre branché sur lfi_nct_bailleurs_sociaux via un hook).    *
 * ============================================================== */
add_filter('lfi_nct_bailleurs_sociaux_list', 'lfi_nct_ga_perso_merge_bailleurs');
function lfi_nct_ga_perso_merge_bailleurs($list) {
    $p = lfi_nct_ga_perso();
    $custom = array_filter(array_map('trim', (array) $p['bailleurs']));
    if ($custom) $list = array_values(array_unique(array_merge($custom, (array) $list)));
    return $list;
}

/* ============================================================== *
 *  VUE : Personnalisation du GA (admins)                          *
 * ============================================================== */
function lfi_nct_app_view_ga_params() {
    if (!(function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'))) return;

    $slug     = function_exists('lfi_nct_scope_ga_slug') ? lfi_nct_scope_ga_slug() : '';
    $ga_label = function_exists('lfi_nct_ga_nom') ? lfi_nct_ga_nom($slug) : 'Mon GA';

    if (!empty($_POST['lfi_ga_perso_save']) && check_admin_referer('lfi_ga_perso_save')) {
        /* Bailleurs : un par ligne dans le textarea. */
        $bail = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', (string) wp_unslash($_POST['bailleurs'] ?? '')))));
        $data = [
            'entete_nom'     => sanitize_text_field(wp_unslash($_POST['entete_nom'] ?? '')),
            'entete_adresse' => sanitize_text_field(wp_unslash($_POST['entete_adresse'] ?? '')),
            'entete_tel'     => sanitize_text_field(wp_unslash($_POST['entete_tel'] ?? '')),
            'entete_email'   => sanitize_email(wp_unslash($_POST['entete_email'] ?? '')),
            'responsable'    => sanitize_text_field(wp_unslash($_POST['responsable'] ?? '')),
            'bailleurs'      => array_map('sanitize_text_field', $bail),
        ];
        lfi_nct_ga_perso_save($slug, $data);
        wp_safe_redirect(lfi_nct_app_url('ga-params', ['saved' => 1]));
        exit;
    }

    $p = lfi_nct_ga_perso($slug);

    lfi_nct_app_screen_open('🎨 Personnalisation — ' . $ga_label, 'En-tête des courriers · bailleurs');
    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Personnalisation enregistrée.');

    echo '<div class="lfi-app-help">Ces réglages ne concernent que <strong>ton groupe d\'action</strong>. Ils personnalisent l\'en-tête de tes courriers/emails et ta liste de bailleurs. Tu peux laisser vide : on garde alors le nom du GA et les bailleurs standard (rien ne casse).</div>';

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_ga_perso_save');
    echo '<input type="hidden" name="lfi_ga_perso_save" value="1">';

    echo '<h3 style="margin:14px 0 6px">✉️ En-tête des courriers & emails</h3>';
    echo '<label>Nom affiché (ton GA)<input type="text" name="entete_nom" value="' . esc_attr($p['entete_nom']) . '" placeholder="Ex : Groupe d\'Action LFI Rezé"></label>';
    echo '<label>Responsable (nom qui signe l\'appui)<input type="text" name="responsable" value="' . esc_attr($p['responsable']) . '" placeholder="Ex : Prénom Nom"></label>';
    echo '<label>Adresse / coordonnées<input type="text" name="entete_adresse" value="' . esc_attr($p['entete_adresse']) . '" placeholder="Ex : local, rue, ville"></label>';
    echo '<label>Téléphone du GA<input type="tel" name="entete_tel" value="' . esc_attr($p['entete_tel']) . '" placeholder="06 …"></label>';
    echo '<label>Email du GA<input type="email" name="entete_email" value="' . esc_attr($p['entete_email']) . '" placeholder="ga@…"></label>';

    echo '<h3 style="margin:18px 0 6px">🏢 Bailleurs sociaux</h3>';
    echo '<div class="lfi-app-help" style="margin:0 0 8px"><small>Un bailleur par ligne. Ceux-ci s\'ajoutent à la liste standard (NMH, Atlantique Habitations…) dans les menus déroulants. Pour le <strong>bailleur principal + agence de secteur + téléphone/email</strong> utilisés dans les courriers, va dans <a href="' . esc_url(lfi_nct_app_url('facturation-params')) . '">⚙️ Paramètres facturation</a> (déjà propre à ton GA).</small></div>';
    echo '<label>Mes bailleurs (un par ligne)<textarea name="bailleurs" rows="4" placeholder="Ex :&#10;Atlantique Habitations&#10;Nantes Métropole Habitat">' . esc_textarea(implode("\n", (array) $p['bailleurs'])) . '</textarea></label>';

    echo '<button type="submit" class="btn-primary">💾 Enregistrer</button>';
    echo '</form>';

    lfi_nct_app_screen_close();
}
