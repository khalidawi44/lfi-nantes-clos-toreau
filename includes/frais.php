<?php
/**
 * FRAIS & TEMPS D'ACCOMPAGNEMENT — pour le dossier / l'avocat (PAS une facture).
 *
 * Cadre juridique (validé par l'analyse architecte + Judilibre) :
 *  - On ne facture PAS NMH pour les emails / visites / constats : NMH n'est pas
 *    notre client (risque de facture de complaisance). On les CAPITALISE comme
 *    « frais engagés + temps » dans le dossier du locataire, réclamés dans le
 *    cadre du PRÉJUDICE, via l'avocat (Me Gouache). Cass. 3e civ. 11-29.011,
 *    08-21.205 ; art. 1222, 1719 du Code civil.
 *  - Les TRAVAUX réellement réalisés restent facturés séparément (module
 *    facturation, art. 1222 CC).
 *
 * Chaque email/visite/constat/déplacement → une ligne horodatée, par dossier,
 * avec son fondement. Le total alimente le chiffrage du préjudice.
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_FRAIS_DBVER = 'lfi_nct_frais_dbver';

add_action('init', 'lfi_nct_frais_db_setup', 6);
function lfi_nct_frais_db_setup() {
    if (get_option(LFI_NCT_FRAIS_DBVER) === '1') return;
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_frais';
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE $t (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        owner_user_id BIGINT(20) UNSIGNED DEFAULT 0,
        dossier_id BIGINT(20) UNSIGNED DEFAULT 0,
        date_frais DATE DEFAULT NULL,
        type VARCHAR(24) DEFAULT 'autre',
        description VARCHAR(255) DEFAULT '',
        montant DECIMAL(10,2) DEFAULT 0,
        fondement VARCHAR(255) DEFAULT '',
        src VARCHAR(24) DEFAULT 'manuel',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY dossier_id (dossier_id)
    ) $charset;");
    update_option(LFI_NCT_FRAIS_DBVER, '1', false);
}

/** Types de frais + libellé + forfait par défaut (ordre de grandeur, € engagés/temps). */
function lfi_nct_frais_forfaits() {
    $def = [
        'courrier'    => ['📧 Courrier / email d\'accompagnement', 20.00],
        'visite'      => ['🏠 Visite / constat sur place',         50.00],
        'deplacement' => ['🚗 Déplacement',                        15.00],
        'appel'       => ['📞 Appel / démarche téléphonique',      10.00],
        'reunion'     => ['🤝 Réunion / RDV (institution, avocat)', 40.00],
        'autre'       => ['✳️ Autre frais engagé',                  0.00],
    ];
    $opt = get_option('lfi_nct_frais_forfaits', []);
    if (is_array($opt)) {
        foreach ($opt as $k => $v) {
            if (isset($def[$k]) && is_numeric($v)) $def[$k][1] = (float) $v;
        }
    }
    return $def;
}

/** Fondement juridique par défaut selon le type. */
function lfi_nct_frais_fondement($type) {
    switch ($type) {
        case 'courrier':
        case 'appel':
        case 'reunion':
            return 'Frais et temps d\'accompagnement engagés pour faire cesser le trouble ; réclamés au titre du préjudice (art. 1719, 1231-1 C. civ.).';
        case 'visite':
        case 'deplacement':
            return 'Frais de déplacement et de constat engagés du fait de la défaillance du bailleur (art. 1719, 1222 C. civ.).';
        default:
            return 'Frais engagés en lien avec les désordres imputables au bailleur — préjudice à réparer.';
    }
}

/**
 * Enregistre une ligne de frais d'accompagnement.
 * @return int id inséré, ou 0.
 */
function lfi_nct_frais_log($dossier_id, $type, $description = '', $montant = null, $src = 'manuel') {
    global $wpdb;
    $dossier_id = (int) $dossier_id;
    if (!$dossier_id) return 0;
    $forfaits = lfi_nct_frais_forfaits();
    $type = isset($forfaits[$type]) ? $type : 'autre';
    if ($montant === null || $montant === '') $montant = $forfaits[$type][1];
    $owner = function_exists('lfi_nct_dossier_owner_id') ? (int) lfi_nct_dossier_owner_id() : 0;
    $ok = $wpdb->insert($wpdb->prefix . 'lfi_nct_frais', [
        'owner_user_id' => $owner,
        'dossier_id'    => $dossier_id,
        'date_frais'    => wp_date('Y-m-d'),
        'type'          => $type,
        'description'   => mb_substr(sanitize_text_field((string) $description), 0, 255),
        'montant'       => (float) $montant,
        'fondement'     => lfi_nct_frais_fondement($type),
        'src'           => sanitize_key((string) $src),
    ]);
    return $ok ? (int) $wpdb->insert_id : 0;
}

/** Lignes de frais d'un dossier. */
function lfi_nct_frais_list($dossier_id) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_frais';
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE dossier_id = %d ORDER BY date_frais DESC, id DESC", (int) $dossier_id)) ?: [];
}

/** Total des frais d'un dossier. */
function lfi_nct_frais_total($dossier_id) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_frais';
    return (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(montant),0) FROM $t WHERE dossier_id = %d", (int) $dossier_id));
}

/** Formate en euros. */
function lfi_nct_frais_eur($n) {
    return number_format((float) $n, 2, ',', ' ') . ' €';
}

/**
 * Rendu de la section « Frais d'accompagnement » dans un dossier.
 * Cadre CLAIR : ce n'est pas une facture NMH, c'est à réclamer via l'avocat.
 */
function lfi_nct_frais_render($dossier_id) {
    $rows = lfi_nct_frais_list($dossier_id);
    $forfaits = lfi_nct_frais_forfaits();
    echo '<h3 style="margin:18px 0 6px;color:#4b2e83">💼 Frais &amp; temps d\'accompagnement (→ avocat)</h3>';
    echo '<div class="lfi-app-help"><small>⚖️ Ce n\'est <strong>pas</strong> une facture à NMH. Ce sont les <strong>frais engagés</strong> (déplacements, courriers, constats) et le temps passé, <strong>capitalisés pour être réclamés dans le préjudice</strong> du dossier, via l\'avocat. On ne facture jamais NMH directement pour ça.</small></div>';
    if (empty($rows)) {
        echo '<div class="lfi-app-help">Aucun frais enregistré pour l\'instant. Ils s\'ajoutent tout seuls à chaque courrier préparé, et tu peux en ajouter (visite, déplacement…).</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        foreach ($rows as $r) {
            $meta = $forfaits[$r->type] ?? ['✳️ Frais', 0];
            echo '<li class="lfi-app-card" style="border-left:4px solid #4b2e83">';
            echo '<div class="head"><div class="who">' . esc_html($meta[0]) . '</div>';
            echo '<div class="when" style="font-weight:700;color:#4b2e83">' . esc_html(lfi_nct_frais_eur($r->montant)) . '</div></div>';
            echo '<div class="meta"><span class="meta-chip">📅 ' . esc_html(wp_date('j M Y', strtotime($r->date_frais))) . '</span></div>';
            if (trim((string) $r->description) !== '') echo '<div class="com">' . esc_html($r->description) . '</div>';
            echo '<div class="com" style="font-size:.82em;color:#666">' . esc_html($r->fondement) . '</div>';
            echo '</li>';
        }
        echo '</ul>';
        echo '<div class="lfi-app-card" style="border:2px solid #4b2e83"><div class="com"><strong>Total à réclamer (via l\'avocat) : ' . esc_html(lfi_nct_frais_eur(lfi_nct_frais_total($dossier_id))) . '</strong></div></div>';
    }
}
