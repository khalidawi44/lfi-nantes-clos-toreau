<?php
/**
 * SIGNATURES ADAPTATIVES + LOGOS — la « casquette » change selon l'interlocuteur.
 *
 *  - Au BAILLEUR (NMH / M. Moreno) : c'est l'ASSOCIATION mandatée qui parle
 *    (Union des Quartiers Libres). JAMAIS « LFI » — le bailleur traite avec
 *    l'association qui agit au nom du locataire, pas avec un parti politique.
 *  - Au LOCATAIRE : fraternel, entre voisins — l'association.
 *  - À l'AVOCAT : confraternel — LFI + Union des Quartiers Libres (Fabrice Doucet).
 *
 * Logos : Union des Quartiers Libres (toujours) + LFI (uniquement quand c'est
 * légitime : avocat / interne). Réglables dans « 🎨 Logos & signatures ».
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_signataire_defaut() {
    return (string) get_option('lfi_nct_signataire', 'Fabrice Doucet');
}
function lfi_nct_logo_uql_url() {
    $def = defined('LFI_NCT_URL') ? LFI_NCT_URL . 'assets/img/logo-quartier-libre.jpg' : '';
    return (string) get_option('lfi_nct_logo_uql', $def);
}
function lfi_nct_logo_lfi_url() {
    $def = defined('LFI_NCT_URL') ? LFI_NCT_URL . 'assets/img/logo-lfi.png' : 'https://lfi-nantes-clostoreau.fr/wp-content/uploads/2026/05/cropped-logo_sanss_AP.png';
    return (string) get_option('lfi_nct_logo_lfi', $def);
}

/** Signature TEXTE adaptée à l'interlocuteur ($ctx = nmh|locataire|avocat|general). */
function lfi_nct_email_signature($ctx, $signataire = '', $person = '') {
    $signataire = $signataire !== '' ? $signataire : lfi_nct_signataire_defaut();
    switch ($ctx) {
        case 'nmh': /* au bailleur : l'ASSOCIATION mandatée, jamais LFI */
            $s = "\n\nCordialement,\n" . $signataire . "\n"
               . "Union des Quartiers Libres — association d'accompagnement des locataires";
            if ($person !== '') $s .= "\nAgissant au nom et pour le compte de " . $person . ", qui nous a mandatés.";
            return $s;
        case 'locataire': /* fraternel, entre voisins */
            return "\n\nOn est là pour vous, courage — à très vite.\n" . $signataire . "\n"
                 . "Union des Quartiers Libres · votre Groupe d'Action de quartier";
        case 'avocat': /* confraternel : LFI + association */
            return "\n\nBien cordialement,\n" . $signataire . "\n"
                 . "Groupe d'Action La France Insoumise Nantes Sud – Clos Toreau\n"
                 . "Union des Quartiers Libres";
        default:
            return "\n\nCordialement,\n" . $signataire . "\n"
                 . "Groupe d'Action LFI Nantes Sud – Clos Toreau · Union des Quartiers Libres";
    }
}

/** Faut-il montrer le logo LFI pour ce contexte ? (pas au bailleur ni au locataire). */
function lfi_nct_signature_show_lfi($ctx) {
    return !in_array($ctx, ['nmh', 'locataire'], true);
}

/** En-tête de logos (HTML) pour les documents imprimables / emails HTML. */
function lfi_nct_signature_logos_html($ctx, $align = 'left') {
    $uql = lfi_nct_logo_uql_url();
    $lfi = lfi_nct_logo_lfi_url();
    $imgs = '';
    if ($uql) $imgs .= '<img src="' . esc_url($uql) . '" alt="Union des Quartiers Libres" style="height:54px;width:auto;vertical-align:middle">';
    if (lfi_nct_signature_show_lfi($ctx) && $lfi) $imgs .= '<img src="' . esc_url($lfi) . '" alt="La France Insoumise" style="height:54px;width:auto;vertical-align:middle">';
    if ($imgs === '') return '';
    return '<div style="display:flex;gap:16px;align-items:center;justify-content:' . ($align === 'center' ? 'center' : 'flex-start') . ';flex-wrap:wrap;margin:0 0 14px">' . $imgs . '</div>';
}

/* ============================================================== *
 *  VUE ADMIN : régler les logos + le signataire                   *
 * ============================================================== */
function lfi_nct_app_view_signatures_cfg() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    if (!empty($_POST['lfi_sig_cfg']) && check_admin_referer('lfi_sig_cfg')) {
        update_option('lfi_nct_signataire', sanitize_text_field(wp_unslash($_POST['signataire'] ?? '')) ?: 'Fabrice Doucet', false);
        update_option('lfi_nct_logo_uql', esc_url_raw(wp_unslash($_POST['logo_uql'] ?? '')), false);
        update_option('lfi_nct_logo_lfi', esc_url_raw(wp_unslash($_POST['logo_lfi'] ?? '')), false);
        wp_safe_redirect(lfi_nct_app_url('signatures-cfg', ['ok' => 1])); exit;
    }
    lfi_nct_app_screen_open('🎨 Logos & signatures', 'La bonne casquette selon l\'interlocuteur');
    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Enregistré.');
    echo '<div class="lfi-app-help">Les emails et documents s\'adaptent tout seuls : <strong>au bailleur</strong> = l\'association seule (Union des Quartiers Libres, jamais LFI) ; <strong>au locataire</strong> = fraternel ; <strong>à l\'avocat</strong> = LFI + association. Renseigne ici tes logos.</div>';

    echo '<form method="post" class="lfi-app-form" style="background:#f8f8f8;padding:12px;border-radius:10px">' . wp_nonce_field('lfi_sig_cfg', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_sig_cfg" value="1">';
    echo '<label>Signataire (ton nom)<input type="text" name="signataire" value="' . esc_attr(lfi_nct_signataire_defaut()) . '"></label>';
    echo '<label>URL du logo « Union des Quartiers Libres »<input type="url" name="logo_uql" value="' . esc_attr(lfi_nct_logo_uql_url()) . '" placeholder="https://…/logo-uql.png"></label>';
    echo '<label>URL du logo « La France Insoumise »<input type="url" name="logo_lfi" value="' . esc_attr(lfi_nct_logo_lfi_url()) . '"></label>';
    echo '<div class="lfi-app-help"><small>Pour trouver l\'URL d\'un logo : dépose-le dans <strong>Médias</strong> (wp-admin), ouvre-le, et copie l\'« URL du fichier ».</small></div>';
    echo '<button type="submit" class="btn-primary" style="margin-top:6px">💾 Enregistrer</button></form>';

    /* Aperçu des 3 casquettes. */
    echo '<h3 style="margin:16px 0 6px">Aperçu</h3>';
    foreach (['avocat' => '⚖️ À un·e avocat·e', 'nmh' => '🏢 Au bailleur (NMH)', 'locataire' => '🏠 À un·e locataire'] as $ctx => $lbl) {
        echo '<div class="lfi-app-card"><div class="head"><div class="who">' . esc_html($lbl) . '</div></div>';
        echo lfi_nct_signature_logos_html($ctx);
        echo '<pre style="white-space:pre-wrap;font-family:inherit;background:#fafafa;border-radius:8px;padding:8px;margin:0;font-size:.9em">' . esc_html(trim(lfi_nct_email_signature($ctx, '', 'M./Mme X'))) . '</pre></div>';
    }
    lfi_nct_app_screen_close();
}
