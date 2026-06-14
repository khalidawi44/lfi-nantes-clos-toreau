<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'lfi_nct_admin_menu');
function lfi_nct_admin_menu() {
    add_menu_page(
        'LFI Clos Toreau — Réponses',
        'LFI Clos Toreau',
        'manage_options',
        'lfi-nct-responses',
        'lfi_nct_admin_page',
        'dashicons-clipboard',
        25
    );
    add_submenu_page(
        'lfi-nct-responses',
        'LFI Clos Toreau — Réponses',
        'Réponses',
        'manage_options',
        'lfi-nct-responses',
        'lfi_nct_admin_page'
    );
    add_submenu_page(
        'lfi-nct-responses',
        'LFI Clos Toreau — Statistiques',
        '📊 Statistiques',
        'manage_options',
        'lfi-nct-stats',
        'lfi_nct_stats_page'
    );
}

function lfi_nct_admin_page() {
    if (isset($_GET['export']) && $_GET['export'] === 'csv' && current_user_can('manage_options')) {
        lfi_nct_export_csv();
        exit;
    }

    $count = lfi_nct_count_responses();
    $responses = lfi_nct_get_responses(50);
    ?>
    <div class="wrap">
        <h1>LFI Clos Toreau — Réponses Enquête Logement <?php echo lfi_nct_print_button('Imprimer toutes les réponses'); ?></h1>
        <p><strong><?php echo $count; ?></strong> réponse(s) enregistrée(s).</p>
        <p><a href="?page=lfi-nct-responses&export=csv" class="button button-primary">📥 Exporter en CSV</a></p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th><th>Reçu le</th><th>Gravité</th>
                    <th>Adresse</th><th>Étage</th><th>Appt</th>
                    <th>Problèmes</th><th>Récurrence</th><th>Souhaite RDV</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $type_labels = [
                    'degats_eaux' => 'eaux', 'humidite' => 'humidité', 'insectes' => 'nuisibles',
                    'chauffage' => 'chauffage', 'electricite' => 'électr.', 'ascenseur' => 'ascenseur',
                    'parties_communes' => 'parties communes', 'bruit' => 'bruit', 'securite' => 'sécurité',
                    'autre' => 'autre',
                ];
                $rec_labels = ['permanent' => 'permanent', 'parfois' => 'régulier', 'ponctuel' => 'ponctuel'];
                foreach ($responses as $r):
                    $data = $r->data ? json_decode($r->data, true) : [];
                    if (!is_array($data)) $data = [];
                    $appt = $data['appartement'] ?? '';
                    $types = array_map(function($t) use ($type_labels) { return $type_labels[$t] ?? $t; }, (array)($data['problemes_types'] ?? []));
                    $rec = $rec_labels[$data['problemes_recurrent'] ?? ''] ?? '—';
                    $presence = $data['problemes_presence'] ?? '';
                    $types_str = $presence === 'non' ? '—' : ($types ? implode(', ', $types) : '—');
                ?>
                <tr>
                    <td><?php echo (int) $r->id; ?></td>
                    <td><?php echo esc_html($r->submitted_at); ?></td>
                    <td><?php echo lfi_nct_gravity_badge_html($r); ?></td>
                    <td><?php echo esc_html($r->adresse); ?></td>
                    <td><?php echo esc_html($r->etage); ?></td>
                    <td><?php echo esc_html($appt); ?></td>
                    <td><?php echo esc_html($types_str); ?></td>
                    <td><?php echo esc_html($rec); ?></td>
                    <td><?php echo $r->contact_recontact ? '✅ ' . esc_html(trim($r->contact_prenom . ' ' . $r->contact_nom)) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function lfi_nct_export_csv() {
    $responses = lfi_nct_get_responses(10000);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lfi-clos-toreau-export-' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'ID', 'Reçu le', 'Adresse', 'Étage', 'Appartement',
        'Problèmes ?', 'Types', 'Autre type', 'Durée', 'Récurrence', 'Gravité (1-10)',
        'Souhaite RDV', 'Prénom', 'Nom', 'Tél', 'Email',
        'Données complètes (JSON)'
    ], ';');
    foreach ($responses as $r) {
        $data = $r->data ? json_decode($r->data, true) : [];
        if (!is_array($data)) $data = [];
        fputcsv($out, [
            $r->id, $r->submitted_at, $r->adresse, $r->etage, $data['appartement'] ?? '',
            $data['problemes_presence'] ?? '',
            implode(', ', (array)($data['problemes_types'] ?? [])),
            $data['problemes_types_autre'] ?? '',
            $data['problemes_duree'] ?? '',
            $data['problemes_recurrent'] ?? '',
            $data['problemes_gravite'] ?? '',
            $r->contact_recontact ? 'Oui' : 'Non',
            $r->contact_prenom, $r->contact_nom, $r->contact_tel, $r->contact_email,
            $r->data,
        ], ';');
    }
    fclose($out);
}

/**
 * Petit bouton « Imprimer » à inclure en haut des pages d'admin LFI.
 */
function lfi_nct_print_button($label = 'Imprimer cette page') {
    return '<button type="button" class="button lfi-print-hide" onclick="window.print()" style="margin-left:.5em;vertical-align:middle">🖨️ ' . esc_html($label) . '</button>';
}

/**
 * Feuille de style impression : masque tout le chrome de wp-admin pour
 * n'imprimer que le contenu de la page (.wrap), sur les pages LFI.
 */
add_action('admin_head', 'lfi_nct_admin_print_css');
function lfi_nct_admin_print_css() {
    $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
    if (!preg_match('/^lfi-nct-(responses|stats|rdv)$/', $page)) return;
    echo '<style id="lfi-nct-print-css">
    @media print {
        body * { visibility: hidden; }
        .wrap, .wrap * { visibility: visible; }
        .wrap { position: absolute; left: 0; top: 0; width: 100%; padding: 0 1em; }
        .button, .lfi-print-hide, #screen-meta, #screen-meta-links,
        .notice, .update-nag, .page-title-action { display: none !important; }
        a { color: #000; text-decoration: none; }
        .wp-list-table { font-size: 11px; }
        .wp-list-table th, .wp-list-table td { padding: 4px 6px !important; }
    }
    </style>';
}