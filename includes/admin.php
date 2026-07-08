<?php
if (!defined('ABSPATH')) exit;

/**
 * Page wp-admin « déménagée dans l'app ». Affiche un renvoi clair vers la
 * version de l'app (référence unique), sans supprimer l'ancien code : la
 * fonction appelante fait `return` juste après, donc rien n'est cassé et
 * c'est réversible.
 */
function lfi_nct_admin_app_landing($app_route, $title, $subtitle = '') {
    $url  = home_url('/app/?vue=' . rawurlencode($app_route));
    $home = home_url('/app/');
    echo '<div class="wrap">';
    echo '<h1>' . esc_html($title) . '</h1>';
    echo '<div style="max-width:680px;margin:18px 0;background:#fff;border:1px solid #e2e2e2;border-left:5px solid #c8102e;border-radius:10px;padding:22px 26px">';
    echo '<h2 style="margin:0 0 6px;color:#c8102e">👉 Cette page est désormais dans l\'app</h2>';
    if ($subtitle) echo '<p style="color:#555;margin:0 0 10px">' . esc_html($subtitle) . '</p>';
    echo '<p style="margin:0 0 14px">Tout se pilote au même endroit (et depuis ton téléphone) dans l\'app — c\'est la version de référence. Cette page WordPress est conservée mais n\'est plus utilisée.</p>';
    echo '<p><a class="button button-primary button-hero" href="' . esc_url($url) . '">🚀 Ouvrir dans l\'app</a> ';
    echo '<a class="button button-hero" href="' . esc_url($home) . '">🏠 Tableau de bord de l\'app</a></p>';
    echo '</div></div>';
}

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
    lfi_nct_admin_app_landing('enquetes', '🏠 Enquêtes logement', 'La liste des réponses — avec tri, édition, suppression et corbeille — est dans l\'app.');
    return;

    // === Sauvegarde des modifications (AVANT tout rendu, sinon l'edit form se ré-affiche) ===
    if (!empty($_POST['lfi_nct_edit_id'])) {
        $eid = (int) $_POST['lfi_nct_edit_id'];
        if ($eid > 0 && check_admin_referer('lfi_nct_edit_' . $eid)) {
            global $wpdb;
            $table = $wpdb->prefix . 'lfi_nct_responses';
            $current = $wpdb->get_row($wpdb->prepare("SELECT adresse, data FROM $table WHERE id = %d", $eid));
            $adresse_changee = false;
            if ($current) {
                $new_adr = sanitize_text_field(wp_unslash($_POST['adresse'] ?? ''));
                $update = [
                    'adresse'        => $new_adr,
                    'etage'          => sanitize_text_field(wp_unslash($_POST['etage'] ?? '')),
                    'contact_prenom' => sanitize_text_field(wp_unslash($_POST['contact_prenom'] ?? '')),
                    'contact_nom'    => sanitize_text_field(wp_unslash($_POST['contact_nom'] ?? '')),
                    'contact_tel'    => sanitize_text_field(wp_unslash($_POST['contact_tel'] ?? '')),
                    'contact_email'  => sanitize_email(wp_unslash($_POST['contact_email'] ?? '')),
                ];
                $adresse_changee = (trim((string) $current->adresse) !== trim($new_adr));
                if ($adresse_changee) {
                    $update['lat'] = null;
                    $update['lng'] = null;
                }
                $data = json_decode((string) $current->data, true);
                if (!is_array($data)) $data = [];
                $data['appartement'] = sanitize_text_field(wp_unslash($_POST['appartement'] ?? ''));
                $update['data'] = wp_json_encode($data, JSON_UNESCAPED_UNICODE);
                $wpdb->update($table, $update, ['id' => $eid]);
                delete_transient('lfi_nct_known_addresses');
            }
            // Redirection vers la liste (évite la double-soumission au refresh).
            wp_safe_redirect(add_query_arg([
                'edited'   => $eid,
                'addr_chg' => $adresse_changee ? 1 : 0,
            ], admin_url('admin.php?page=lfi-nct-responses')));
            exit;
        }
    }

    // === Édition d'une réponse (vue séparée) ===
    if (($_GET['action'] ?? '') === 'edit' && !empty($_GET['id'])) {
        lfi_nct_render_edit_form((int) $_GET['id']);
        return;
    }

    $notice = '';
    if (!empty($_GET['edited'])) {
        $eid_n = (int) $_GET['edited'];
        $notice = 'success|✏️ Réponse n°' . $eid_n . ' modifiée'
                . (!empty($_GET['addr_chg']) ? ' — ses coordonnées GPS seront recalculées au prochain géocodage.' : '.');
    }

    // === Traitement des actions (suppression / restauration / purge) ===
    if (!empty($_POST['lfi_nct_action']) && !empty($_POST['lfi_nct_id'])) {
        $action = sanitize_key($_POST['lfi_nct_action']);
        $id     = (int) $_POST['lfi_nct_id'];
        if ($id > 0 && in_array($action, ['delete', 'restore', 'destroy'], true)
            && check_admin_referer('lfi_nct_' . $action . '_' . $id)) {
            global $wpdb;
            $table = $wpdb->prefix . 'lfi_nct_responses';
            if ($action === 'delete') {
                $wpdb->update($table, ['deleted_at' => current_time('mysql')], ['id' => $id]);
                delete_transient('lfi_nct_known_addresses');
                $notice = 'success|🗑 Réponse n°' . $id . ' déplacée à la corbeille (récupérable).';
            } elseif ($action === 'restore') {
                $wpdb->update($table, ['deleted_at' => null], ['id' => $id]);
                delete_transient('lfi_nct_known_addresses');
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

        <table class="wp-list-table widefat striped" style="table-layout:auto">
            <thead>
                <tr>
                    <th>N°</th>
                    <th><?php echo $sort_link('date', 'Reçu le'); ?></th>
                    <th><?php echo $sort_link('gravite', 'Gravité'); ?></th>
                    <th><?php echo $sort_link('adresse', 'Adresse'); ?></th>
                    <th><?php echo $sort_link('etage', 'Étage'); ?></th>
                    <th>Appt</th>
                    <th><?php echo $sort_link('problemes', 'Problèmes'); ?></th>
                    <th>Durée</th>
                    <th>Récurrence</th>
                    <th><?php echo $sort_link('rdv', 'RDV'); ?></th>
                    <th>Contact</th>
                    <th>Téléphone</th>
                    <th>Email</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($responses)): ?>
                    <tr><td colspan="14"><em>Aucune réponse <?php echo $view === 'corbeille' ? 'dans la corbeille' : 'enregistrée'; ?>.</em></td></tr>
                <?php else: foreach ($responses as $idx => $r):
                    $rank = $idx + 1;
                    $data = $r->data ? json_decode($r->data, true) : [];
                    if (!is_array($data)) $data = [];
                    $appt = $data['appartement'] ?? '';
                    $types = array_map(function($t) use ($type_labels) { return $type_labels[$t] ?? $t; }, (array)($data['problemes_types'] ?? []));
                    $rec = $rec_labels[$data['problemes_recurrent'] ?? ''] ?? '—';
                    $presence = $data['problemes_presence'] ?? '';
                    $types_str = $presence === 'non' ? '—' : ($types ? implode(', ', $types) : '—');
                ?>
                <tr>
                    <td><strong><?php echo $rank; ?></strong> <span style="color:#999;font-size:.82em" title="Identifiant interne de la réponse">#<?php echo (int) $r->id; ?></span></td>
                    <td><?php echo esc_html($r->submitted_at); ?></td>
                    <td><?php echo lfi_nct_gravity_badge_html($r); ?></td>
                    <td><?php echo esc_html($r->adresse); ?></td>
                    <td><?php echo esc_html($r->etage); ?></td>
                    <td><?php echo esc_html($appt); ?></td>
                    <td><?php echo esc_html($types_str); ?></td>
                    <td><?php
                        $duree_labels_full = [
                            'moins_1_mois' => '<1 mois', '1_6_mois' => '1-6 mois',
                            '6_12_mois' => '6-12 mois', '1_5_ans' => '>1 an', 'plus_5_ans' => '>5 ans',
                        ];
                        $duree = $data['problemes_duree'] ?? '';
                        echo esc_html($duree_labels_full[$duree] ?? ($duree ?: '—'));
                    ?></td>
                    <td><?php echo esc_html($rec); ?></td>
                    <td><?php echo $r->contact_recontact ? '✅' : '—'; ?></td>
                    <td><?php echo esc_html(trim($r->contact_prenom . ' ' . $r->contact_nom)) ?: '—'; ?></td>
                    <td><?php echo $r->contact_tel ? '<a href="tel:' . esc_attr($r->contact_tel) . '">' . esc_html($r->contact_tel) . '</a>' : '—'; ?></td>
                    <td><?php echo $r->contact_email ? '<a href="mailto:' . esc_attr($r->contact_email) . '">' . esc_html($r->contact_email) . '</a>' : '—'; ?></td>
                    <td>
                        <?php if ($view === 'actives'): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=lfi-nct-stats&view=' . (int)$r->id)); ?>" class="button button-small" title="Voir tous les champs">👁 Détail</a>
                            <a href="?page=lfi-nct-responses&action=edit&id=<?php echo (int)$r->id; ?>" class="button button-small">✏️ Modifier</a>
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

/**
 * Déclenchement précoce de l'export CSV : on doit le faire AVANT que WordPress
 * n'envoie le header HTML de la page admin, sinon le téléchargement contient
 * du HTML mélangé au CSV (« <!DOCTYPE… <script> id="wordfence…" »).
 */
add_action('admin_init', 'lfi_nct_handle_csv_export', 1);
function lfi_nct_handle_csv_export() {
    if (!current_user_can('manage_options')) return;
    $page   = isset($_GET['page'])   ? (string) $_GET['page']   : '';
    $export = isset($_GET['export']) ? (string) $_GET['export'] : '';
    if ($page !== 'lfi-nct-responses' || $export !== 'csv') return;
    lfi_nct_export_csv();
    exit;
}

function lfi_nct_export_csv() {
    $responses = lfi_nct_get_responses(10000);
    // Filet de sécurité : on vide tout buffer ouvert par un plugin tiers (LiteSpeed, etc.)
    while (ob_get_level() > 0) { ob_end_clean(); }
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lfi-clos-toreau-export-' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'ID', 'Reçu le', 'Adresse', 'Étage', 'Appartement',
        'Problèmes ?', 'Types', 'Autre type', 'Durée', 'Récurrence', 'Gravité (1-10)',
        'Eau chaude — coupures/an', 'Eau chaude — plus longue coupure', 'Eau chaude — verbatim',
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
            $data['eau_chaude_nb_par_an'] ?? '',
            $data['eau_chaude_duree_max'] ?? '',
            $data['eau_chaude_citation']  ?? '',
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

/**
 * Récupère la liste des adresses déjà saisies (en cache 5 min) pour les
 * datalists d'autocomplétion — front-end ET édition admin. Ça normalise
 * naturellement la saisie : on tape, on choisit parmi les adresses déjà
 * utilisées au lieu de risquer des variations « 14 rue st jean » vs
 * « 14 rue Saint Jean de Luz ».
 */
function lfi_nct_known_addresses() {
    $cached = get_transient('lfi_nct_known_addresses');
    if (is_array($cached)) return $cached;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $rows = $wpdb->get_col(
        "SELECT DISTINCT adresse FROM $table
         WHERE deleted_at IS NULL AND adresse IS NOT NULL AND adresse != ''
         ORDER BY adresse"
    );
    $rows = $rows ?: [];
    set_transient('lfi_nct_known_addresses', $rows, 5 * MINUTE_IN_SECONDS);
    return $rows;
}

/**
 * Sort un <datalist id="..."> avec toutes les adresses connues, à coupler
 * avec un input list="...".
 */
function lfi_nct_addresses_datalist($id = 'lfi-known-addresses') {
    $out = '<datalist id="' . esc_attr($id) . '">';
    foreach (lfi_nct_known_addresses() as $a) {
        $out .= '<option value="' . esc_attr($a) . '">';
    }
    return $out . '</datalist>';
}

/**
 * Page « Modifier la réponse n°X ».
 */
function lfi_nct_render_edit_form($id) {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", (int) $id));
    if (!$r) {
        echo '<div class="wrap"><div class="notice notice-error"><p>Réponse introuvable.</p></div>'
           . '<p><a href="?page=lfi-nct-responses" class="button">← Retour</a></p></div>';
        return;
    }
    $data = $r->data ? json_decode($r->data, true) : [];
    if (!is_array($data)) $data = [];
    $appt = $data['appartement'] ?? '';
    ?>
    <div class="wrap">
        <h1>✏️ Modifier la réponse #<?php echo (int) $r->id; ?></h1>
        <p><a href="?page=lfi-nct-responses" class="button">← Retour à la liste</a></p>
        <p class="description">L'adresse propose une autocomplétion avec les adresses déjà saisies : choisissez-en une dans la liste pour rester cohérent (évite « 14 rue st jean » vs « 14 rue Saint Jean de Luz »).</p>

        <form method="post">
            <?php wp_nonce_field('lfi_nct_edit_' . (int) $r->id); ?>
            <input type="hidden" name="lfi_nct_edit_id" value="<?php echo (int) $r->id; ?>">

            <table class="form-table">
                <tr><th><label for="lfi-ed-adresse">Adresse</label></th>
                    <td>
                        <input type="text" id="lfi-ed-adresse" name="adresse" value="<?php echo esc_attr($r->adresse); ?>" list="lfi-known-addresses" class="regular-text" autocomplete="off">
                        <?php echo lfi_nct_addresses_datalist('lfi-known-addresses'); ?>
                    </td>
                </tr>
                <tr><th><label>Étage</label></th>
                    <td><input type="text" name="etage" value="<?php echo esc_attr($r->etage); ?>" class="regular-text"></td>
                </tr>
                <tr><th><label>Appartement</label></th>
                    <td><input type="text" name="appartement" value="<?php echo esc_attr($appt); ?>" class="regular-text"></td>
                </tr>
                <tr><th colspan="2"><h3 style="margin:1em 0 0">Contact</h3></th></tr>
                <tr><th><label>Prénom</label></th>
                    <td><input type="text" name="contact_prenom" value="<?php echo esc_attr($r->contact_prenom); ?>" class="regular-text"></td>
                </tr>
                <tr><th><label>Nom</label></th>
                    <td><input type="text" name="contact_nom" value="<?php echo esc_attr($r->contact_nom); ?>" class="regular-text"></td>
                </tr>
                <tr><th><label>Téléphone</label></th>
                    <td><input type="text" name="contact_tel" value="<?php echo esc_attr($r->contact_tel); ?>" class="regular-text"></td>
                </tr>
                <tr><th><label>Email</label></th>
                    <td><input type="email" name="contact_email" value="<?php echo esc_attr($r->contact_email); ?>" class="regular-text"></td>
                </tr>
            </table>

            <p>
                <button type="submit" class="button button-primary">💾 Enregistrer</button>
                <a href="?page=lfi-nct-responses" class="button">Annuler</a>
            </p>
            <p class="description">⚠️ Changer l'adresse remet ses coordonnées GPS à zéro : la carte sera recalculée au prochain géocodage.</p>
        </form>
    </div>
    <?php
}