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
    if (!current_user_can('manage_options')) return;

    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        lfi_nct_export_csv();
        exit;
    }

    // === Traitement des actions (suppression / restauration / purge) ===
    $notice = '';
    if (!empty($_POST['lfi_nct_action']) && !empty($_POST['lfi_nct_id'])) {
        $action = sanitize_key($_POST['lfi_nct_action']);
        $id     = (int) $_POST['lfi_nct_id'];
        if ($id > 0 && in_array($action, ['delete', 'restore', 'destroy'], true)
            && check_admin_referer('lfi_nct_' . $action . '_' . $id)) {
            global $wpdb;
            $table = $wpdb->prefix . 'lfi_nct_responses';
            if ($action === 'delete') {
                $wpdb->update($table, ['deleted_at' => current_time('mysql')], ['id' => $id]);
                $notice = 'success|🗑 Réponse n°' . $id . ' déplacée à la corbeille (récupérable).';
            } elseif ($action === 'restore') {
                $wpdb->update($table, ['deleted_at' => null], ['id' => $id]);
                $notice = 'success|♻️ Réponse n°' . $id . ' restaurée.';
            } elseif ($action === 'destroy') {
                // Sécurité supplémentaire : on ne supprime DÉFINITIVEMENT qu'une réponse déjà en corbeille.
                $row = $wpdb->get_row($wpdb->prepare("SELECT id, deleted_at FROM $table WHERE id = %d", $id));
                if ($row && $row->deleted_at !== null) {
                    $wpdb->delete($table, ['id' => $id]);
                    $notice = 'warning|❌ Réponse n°' . $id . ' supprimée définitivement.';
                } else {
                    $notice = 'error|Refusé : pour une suppression définitive, la réponse doit d\'abord être dans la corbeille.';
                }
            }
        }
    }

    $view = ($_GET['view'] ?? '') === 'corbeille' ? 'corbeille' : 'actives';
    $sort = sanitize_key($_GET['sort'] ?? 'date_desc');

    $count_actives   = lfi_nct_count_responses(false);
    $count_corbeille = lfi_nct_count_responses(true);
    $responses       = lfi_nct_get_responses(500, 0, $view === 'corbeille');

    // === Tri en PHP (gravité et types sont dérivés du JSON) ===
    $type_labels = [
        'degats_eaux' => 'eaux', 'humidite' => 'humidité', 'insectes' => 'nuisibles',
        'chauffage' => 'chauffage', 'electricite' => 'électr.', 'ascenseur' => 'ascenseur',
        'parties_communes' => 'parties communes', 'bruit' => 'bruit', 'securite' => 'sécurité',
        'autre' => 'autre',
    ];
    $rec_labels = ['permanent' => 'permanent', 'parfois' => 'régulier', 'ponctuel' => 'ponctuel'];

    list($sort_col, $sort_dir) = array_pad(explode('_', $sort, 2), 2, 'desc');
    $dir = $sort_dir === 'asc' ? 1 : -1;
    $cmp = function($a, $b) use ($sort_col, $dir) {
        $av = ''; $bv = '';
        switch ($sort_col) {
            case 'date':     $av = $a->submitted_at; $bv = $b->submitted_at; break;
            case 'adresse':  $av = mb_strtolower((string)$a->adresse); $bv = mb_strtolower((string)$b->adresse); break;
            case 'etage':    $av = (string)$a->etage; $bv = (string)$b->etage; break;
            case 'gravite':
                $av = lfi_nct_gravity_score(json_decode((string)$a->data, true));
                $bv = lfi_nct_gravity_score(json_decode((string)$b->data, true));
                break;
            case 'rdv':      $av = (int)$a->contact_recontact; $bv = (int)$b->contact_recontact; break;
            case 'problemes':
                $da = json_decode((string)$a->data, true);
                $db = json_decode((string)$b->data, true);
                $av = implode(',', (array)($da['problemes_types'] ?? []));
                $bv = implode(',', (array)($db['problemes_types'] ?? []));
                break;
            default:         $av = $a->submitted_at; $bv = $b->submitted_at;
        }
        if ($av == $bv) return 0;
        return ($av < $bv ? -1 : 1) * $dir;
    };
    usort($responses, $cmp);

    $sort_link = function($col, $label) use ($sort) {
        $cur_dir = (strpos($sort, $col . '_') === 0) ? substr($sort, strlen($col) + 1) : '';
        $next    = ($cur_dir === 'asc') ? 'desc' : 'asc';
        $arrow   = $cur_dir === 'asc' ? ' ↑' : ($cur_dir === 'desc' ? ' ↓' : '');
        $url     = add_query_arg(['sort' => $col . '_' . $next]);
        return '<a href="' . esc_url($url) . '">' . esc_html($label) . $arrow . '</a>';
    };
    ?>
    <div class="wrap">
        <h1>
            LFI Clos Toreau — Réponses Enquête Logement
            <?php echo lfi_nct_print_button('Imprimer toutes les réponses'); ?>
        </h1>

        <?php if ($notice): list($lvl, $msg) = explode('|', $notice, 2); ?>
            <div class="notice notice-<?php echo esc_attr($lvl); ?> is-dismissible"><p><?php echo esc_html($msg); ?></p></div>
        <?php endif; ?>

        <h2 class="nav-tab-wrapper" style="border-bottom:none">
            <a href="?page=lfi-nct-responses" class="nav-tab <?php echo $view === 'actives' ? 'nav-tab-active' : ''; ?>">📋 Actives (<?php echo $count_actives; ?>)</a>
            <a href="?page=lfi-nct-responses&view=corbeille" class="nav-tab <?php echo $view === 'corbeille' ? 'nav-tab-active' : ''; ?>">🗑 Corbeille (<?php echo $count_corbeille; ?>)</a>
        </h2>

        <p style="margin-top:1em">
            <a href="?page=lfi-nct-responses&export=csv" class="button button-primary">📥 Exporter en CSV</a>
            <span style="margin-left:1em;color:#666">Tri actuel : <strong><?php echo esc_html(str_replace('_', ' ', $sort)); ?></strong> · Clic sur l'en-tête d'une colonne pour trier.</span>
        </p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?php echo $sort_link('date', 'Reçu le'); ?></th>
                    <th><?php echo $sort_link('gravite', 'Gravité'); ?></th>
                    <th><?php echo $sort_link('adresse', 'Adresse'); ?></th>
                    <th><?php echo $sort_link('etage', 'Étage'); ?></th>
                    <th>Appt</th>
                    <th><?php echo $sort_link('problemes', 'Problèmes'); ?></th>
                    <th>Récurrence</th>
                    <th><?php echo $sort_link('rdv', 'Souhaite RDV'); ?></th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($responses)): ?>
                    <tr><td colspan="10"><em>Aucune réponse <?php echo $view === 'corbeille' ? 'dans la corbeille' : 'enregistrée'; ?>.</em></td></tr>
                <?php else: foreach ($responses as $r):
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
                    <td>
                        <?php if ($view === 'actives'): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('🗑 Mettre cette réponse à la corbeille ? Elle sera récupérable avec « Restaurer ».');">
                                <?php wp_nonce_field('lfi_nct_delete_' . $r->id); ?>
                                <input type="hidden" name="lfi_nct_action" value="delete">
                                <input type="hidden" name="lfi_nct_id" value="<?php echo (int)$r->id; ?>">
                                <button type="submit" class="button button-small">🗑 Corbeille</button>
                            </form>
                        <?php else: ?>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field('lfi_nct_restore_' . $r->id); ?>
                                <input type="hidden" name="lfi_nct_action" value="restore">
                                <input type="hidden" name="lfi_nct_id" value="<?php echo (int)$r->id; ?>">
                                <button type="submit" class="button button-small">♻️ Restaurer</button>
                            </form>
                            <form method="post" style="display:inline" onsubmit="return confirm('⚠️ SUPPRIMER DÉFINITIVEMENT la réponse n°<?php echo (int)$r->id; ?> ?\n\nCette action est IRRÉVERSIBLE. Tape OK pour confirmer.') && prompt('Tape SUPPRIMER pour confirmer la suppression définitive') === 'SUPPRIMER';">
                                <?php wp_nonce_field('lfi_nct_destroy_' . $r->id); ?>
                                <input type="hidden" name="lfi_nct_action" value="destroy">
                                <input type="hidden" name="lfi_nct_id" value="<?php echo (int)$r->id; ?>">
                                <button type="submit" class="button button-small" style="color:#a00">❌ Supprimer définitivement</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
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