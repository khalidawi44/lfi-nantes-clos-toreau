<?php
/**
 * Module Facturation — brigade travaux d'urgence Fabrice Doucet
 * auto-entrepreneur, facture émise à Nantes Métropole Habitat.
 *
 * - Suivi des interventions chez les locataires (date, durée, matériaux,
 *   type de travaux, statut)
 * - Génération de factures conformes au régime micro-entrepreneur
 *   (N° SIRET, mention « TVA non applicable, art. 293 B du CGI »,
 *   pénalités de retard, escompte, IBAN)
 * - Numérotation séquentielle inviolable (FA-AAAA-NNNN)
 * - Statuts : planifié → réalisé → facturé → payé
 *
 * Accès uniquement à l'admin (current_user_can('manage_options')).
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_FACT_DBVER_KEY = 'lfi_nct_fact_db_ver';
const LFI_NCT_FACT_DBVER_VAL = '4';

/* ============================================================== *
 *  DB Setup + paramètres par défaut                                *
 * ============================================================== */
add_action('init', 'lfi_nct_facturation_db_setup', 6);
function lfi_nct_facturation_db_setup() {
    if (get_option(LFI_NCT_FACT_DBVER_KEY) === LFI_NCT_FACT_DBVER_VAL) return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $t = $wpdb->prefix . 'lfi_nct_interventions';
    dbDelta("CREATE TABLE $t (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        owner_user_id BIGINT UNSIGNED DEFAULT NULL,
        tenant_user_id BIGINT UNSIGNED DEFAULT NULL,
        tenant_prenom VARCHAR(120) DEFAULT '',
        tenant_nom VARCHAR(120) DEFAULT '',
        tenant_adresse VARCHAR(255) DEFAULT '',
        tenant_etage VARCHAR(50) DEFAULT '',
        tenant_appartement VARCHAR(50) DEFAULT '',
        tenant_tel VARCHAR(40) DEFAULT '',
        bailleur VARCHAR(120) DEFAULT 'Nantes Métropole Habitat',
        date_intervention DATE DEFAULT NULL,
        duree_heures DECIMAL(5,2) DEFAULT 0,
        type_travaux VARCHAR(120) DEFAULT '',
        type_travaux_key VARCHAR(80) DEFAULT '',
        categorie_travaux VARCHAR(20) DEFAULT '',
        description TEXT,
        tarif_mode VARCHAR(10) DEFAULT 'tache',
        prix_tache DECIMAL(10,2) DEFAULT 0,
        tarif_horaire DECIMAL(8,2) DEFAULT 40.00,
        cout_materiaux DECIMAL(10,2) DEFAULT 0,
        total_ht DECIMAL(10,2) DEFAULT 0,
        statut VARCHAR(20) DEFAULT 'planifie',
        facture_numero VARCHAR(40) DEFAULT NULL,
        facture_date DATE DEFAULT NULL,
        paye_date DATE DEFAULT NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY owner_user_id (owner_user_id),
        KEY tenant_user_id (tenant_user_id),
        KEY statut (statut),
        KEY date_intervention (date_intervention),
        KEY facture_numero (facture_numero)
    ) $charset;");

    /* Migration : les interventions existantes sans owner sont attribuées
       au premier admin (compte historique de Fabrice). */
    $admins = get_users(['role' => 'administrator', 'fields' => ['ID'], 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
    if (!empty($admins)) {
        $admin_id = (int) $admins[0]->ID;
        $wpdb->query("UPDATE $t SET owner_user_id = $admin_id WHERE owner_user_id IS NULL");

        /* Migration : si des paramètres globaux existaient (v1/v2), on les
           bascule vers les user_meta du premier admin pour ne rien perdre. */
        $g_presta = get_option('lfi_nct_fact_prestataire');
        if (is_array($g_presta) && !empty($g_presta['nom']) && !get_user_meta($admin_id, 'lfi_nct_fact_prestataire', true)) {
            update_user_meta($admin_id, 'lfi_nct_fact_prestataire', $g_presta);
        }
        $g_bailleur = get_option('lfi_nct_fact_bailleur');
        if (is_array($g_bailleur) && !get_user_meta($admin_id, 'lfi_nct_fact_bailleur', true)) {
            update_user_meta($admin_id, 'lfi_nct_fact_bailleur', $g_bailleur);
        }
        foreach (['tarif_defaut', 'invoice_prefix', 'invoice_counter', 'delai_paiement'] as $k) {
            $val = get_option('lfi_nct_fact_' . $k);
            if ($val !== false && get_user_meta($admin_id, 'lfi_nct_fact_' . $k, true) === '') {
                update_user_meta($admin_id, 'lfi_nct_fact_' . $k, $val);
            }
        }
    }

    update_option(LFI_NCT_FACT_DBVER_KEY, LFI_NCT_FACT_DBVER_VAL, false);
}

/* ============================================================== *
 *  Helpers PER-USER — chaque membre a SES propres paramètres        *
 *                                                                   *
 *  Stockage en user_meta, jamais en options globales. Aucun         *
 *  mélange possible entre Fabrice, les membres du GA, etc.          *
 * ============================================================== */
function lfi_nct_fact_owner_id($uid = null) {
    if ($uid) return (int) $uid;
    return function_exists('lfi_nct_brigade_owner_id') ? lfi_nct_brigade_owner_id() : (int) get_current_user_id();
}

function lfi_nct_fact_prestataire($uid = null) {
    $uid = lfi_nct_fact_owner_id($uid);
    $data = $uid ? get_user_meta($uid, 'lfi_nct_fact_prestataire', true) : '';
    if (is_array($data) && !empty($data)) return $data;
    $u = $uid ? get_userdata($uid) : null;
    return [
        'nom'         => $u ? trim($u->first_name . ' ' . $u->last_name) ?: $u->display_name : '',
        'adresse'     => '',
        'cp_ville'    => '',
        'siret'       => '',
        'ape'         => '',
        'email'       => $u ? $u->user_email : '',
        'tel'         => '',
        'iban'        => '',
        'bic'         => '',
        'mention_tva' => 'TVA non applicable, art. 293 B du CGI',
    ];
}

function lfi_nct_fact_bailleur($uid = null) {
    $uid = lfi_nct_fact_owner_id($uid);
    $data = $uid ? get_user_meta($uid, 'lfi_nct_fact_bailleur', true) : '';
    /* Défauts incluant l'agence sectorielle Clos Toreau / Nantes Sud */
    $defaults = [
        'nom'                  => 'Nantes Métropole Habitat',
        'adresse'              => '8 rue de la Tour d\'Auvergne',
        'cp_ville'             => '44000 Nantes',
        'siret'                => '',
        'email'                => '',
        'agence_nom'           => 'Agence Goudy',
        'agence_secteur'       => 'Clos Toreau / Nantes Sud',
        'agence_contact'       => 'M. Yvonnic Morineau',
        'agence_email'         => 'yvonnic.morineau@nmh.fr',
        'agence_adresse'       => '',
        'agence_tel'           => '',
    ];
    if (is_array($data) && !empty($data)) {
        /* Fusion : on garde ce que l'utilisateur a renseigné, on complète
           le reste avec les défauts (notamment la fiche agence Goudy
           si l'utilisateur n'avait pas encore les champs). */
        return array_merge($defaults, $data);
    }
    return $defaults;
}

function lfi_nct_fact_tarif_defaut($uid = null) {
    $uid = lfi_nct_fact_owner_id($uid);
    $val = $uid ? get_user_meta($uid, 'lfi_nct_fact_tarif_defaut', true) : '';
    return (float) ($val !== '' ? $val : 40.00);
}

function lfi_nct_fact_delai($uid = null) {
    $uid = lfi_nct_fact_owner_id($uid);
    $val = $uid ? get_user_meta($uid, 'lfi_nct_fact_delai_paiement', true) : '';
    return (int) ($val !== '' ? $val : 30);
}

function lfi_nct_fact_invoice_prefix_default($uid) {
    $u = $uid ? get_userdata($uid) : null;
    $initials = '';
    if ($u) {
        $initials = strtoupper(substr($u->first_name ?: $u->display_name, 0, 1) . substr($u->last_name, 0, 1));
        if (strlen($initials) < 2) $initials = strtoupper(substr($u->user_login, 0, 2));
    }
    return 'FA-' . ($initials ?: 'XX') . '-' . date('Y') . '-';
}

function lfi_nct_fact_next_invoice_number($uid = null) {
    $uid = lfi_nct_fact_owner_id($uid);
    if (!$uid) return 'FA-' . date('Y') . '-' . str_pad('1', 4, '0', STR_PAD_LEFT);

    $prefix = (string) get_user_meta($uid, 'lfi_nct_fact_invoice_prefix', true);
    if (!$prefix) $prefix = lfi_nct_fact_invoice_prefix_default($uid);

    /* Reset annuel automatique sur tout préfixe contenant -AAAA- */
    if (preg_match('/^(.+?-)(\d{4})(-)$/', $prefix, $m) && (int) $m[2] !== (int) date('Y')) {
        $prefix = $m[1] . date('Y') . '-';
        update_user_meta($uid, 'lfi_nct_fact_invoice_prefix', $prefix);
        update_user_meta($uid, 'lfi_nct_fact_invoice_counter', 0);
    }

    $counter = (int) get_user_meta($uid, 'lfi_nct_fact_invoice_counter', true) + 1;
    update_user_meta($uid, 'lfi_nct_fact_invoice_counter', $counter);
    return $prefix . str_pad((string) $counter, 4, '0', STR_PAD_LEFT);
}

function lfi_nct_fact_format_eur($n) {
    return number_format((float) $n, 2, ',', ' ') . ' €';
}

function lfi_nct_fact_recalc_total($duree, $tarif, $materiaux) {
    return round(((float) $duree * (float) $tarif) + (float) $materiaux, 2);
}

/* Total selon le mode choisi : tâche (prix forfaitaire + matériaux) OU
   horaire (durée × tarif + matériaux). Le mode tâche est privilégié car
   c'est un prix de marché objectivement défendable. */
function lfi_nct_fact_total_smart($mode, $prix_tache, $duree, $tarif, $materiaux) {
    if ($mode === 'horaire') {
        return round(((float) $duree * (float) $tarif) + (float) $materiaux, 2);
    }
    return round(((float) $prix_tache) + (float) $materiaux, 2);
}

/* ============================================================== *
 *  VUE : Liste des interventions                                   *
 * ============================================================== */
function lfi_nct_app_view_interventions() {
    if (!lfi_nct_can_use_brigade()) return;
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_interventions';
    $owner = (int) lfi_nct_fact_owner_id();
    $owner_clause = $wpdb->prepare('owner_user_id = %d', $owner);

    /* Action : génération de facture pour une intervention */
    if (!empty($_POST['lfi_app_fact_create']) && check_admin_referer('lfi_app_fact_create')) {
        $ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));
        if ($ids) {
            /* Sécurité : on ne facture QUE ses propres interventions */
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $args = array_merge($ids, [$owner]);
            $own_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $t WHERE id IN ($placeholders) AND owner_user_id = %d", ...$args));
            if (!empty($own_ids)) {
                $num = lfi_nct_fact_next_invoice_number();
                $today = current_time('Y-m-d');
                foreach ($own_ids as $id) {
                    $wpdb->update($t, [
                        'statut'         => 'facture',
                        'facture_numero' => $num,
                        'facture_date'   => $today,
                    ], ['id' => (int) $id, 'owner_user_id' => $owner]);
                }
                wp_safe_redirect(lfi_nct_app_url('facture', ['numero' => $num]));
                exit;
            }
        }
    }

    /* Action : marquer comme payée */
    if (!empty($_POST['lfi_app_fact_paye']) && check_admin_referer('lfi_app_fact_paye')) {
        $num = sanitize_text_field(wp_unslash($_POST['numero'] ?? ''));
        if ($num) {
            $wpdb->update($t, ['statut' => 'paye', 'paye_date' => current_time('Y-m-d')], ['facture_numero' => $num, 'owner_user_id' => $owner]);
            wp_safe_redirect(lfi_nct_app_url('interventions', ['paye' => 1]));
            exit;
        }
    }

    /* Filtre statut — TOUJOURS borné à owner_user_id */
    $f = isset($_GET['f']) ? sanitize_key($_GET['f']) : 'all';
    $statut_clause = $f === 'all' ? '1=1' : $wpdb->prepare('statut = %s', $f);
    $rows = $wpdb->get_results("SELECT * FROM $t WHERE $owner_clause AND $statut_clause ORDER BY date_intervention DESC, id DESC LIMIT 200") ?: [];

    /* Compteurs par statut (owner uniquement) */
    $counts = [];
    foreach ($wpdb->get_results("SELECT statut, COUNT(*) AS n FROM $t WHERE $owner_clause GROUP BY statut") ?: [] as $r) {
        $counts[$r->statut] = (int) $r->n;
    }
    $totals = $wpdb->get_row("SELECT
        COALESCE(SUM(CASE WHEN statut='facture' THEN total_ht ELSE 0 END), 0) AS a_recouvrer,
        COALESCE(SUM(CASE WHEN statut='paye' THEN total_ht ELSE 0 END), 0) AS encaisse,
        COALESCE(SUM(CASE WHEN statut IN ('realise', 'facture', 'paye') THEN total_ht ELSE 0 END), 0) AS ca_total
    FROM $t WHERE $owner_clause");

    lfi_nct_app_screen_open('🔧 Brigade travaux', count($rows) . ' intervention(s) · CA ' . lfi_nct_fact_format_eur($totals->ca_total ?? 0));

    if (!empty($_GET['paye']))     lfi_nct_app_flash('💰 Facture marquée comme payée.');
    if (!empty($_GET['annule']))   lfi_nct_app_flash('✕ Intervention annulée. Tu peux maintenant en créer une nouvelle avec le bon prix.');
    if (!empty($_GET['supprime'])) lfi_nct_app_flash('🗑 Intervention supprimée.');

    /* Bandeau résumé */
    echo '<div class="lfi-app-stats-grid">';
    echo '<div class="stat"><div class="ico">📋</div><div class="n">' . array_sum($counts) . '</div><div class="l">Total</div></div>';
    echo '<div class="stat"><div class="ico">💵</div><div class="n">' . lfi_nct_fact_format_eur($totals->a_recouvrer ?? 0) . '</div><div class="l">À encaisser</div></div>';
    echo '<div class="stat"><div class="ico">✅</div><div class="n">' . lfi_nct_fact_format_eur($totals->encaisse ?? 0) . '</div><div class="l">Encaissé</div></div>';
    echo '<div class="stat"><div class="ico">📈</div><div class="n">' . lfi_nct_fact_format_eur($totals->ca_total ?? 0) . '</div><div class="l">CA total</div></div>';
    echo '</div>';

    /* Filtres en chips */
    $filters = [
        'all'      => 'Tout (' . array_sum($counts) . ')',
        'planifie' => '📅 Planifié (' . ($counts['planifie'] ?? 0) . ')',
        'realise'  => '✓ Réalisé (' . ($counts['realise']  ?? 0) . ')',
        'facture'  => '🧾 Facturé (' . ($counts['facture']  ?? 0) . ')',
        'paye'     => '💰 Payé (' . ($counts['paye']     ?? 0) . ')',
    ];
    echo '<div class="lfi-app-filter-chips">';
    foreach ($filters as $k => $label) {
        $cls = $f === $k ? 'on' : '';
        echo '<a class="fc ' . esc_attr($cls) . '" href="' . esc_url(lfi_nct_app_url('interventions', ['f' => $k])) . '">' . esc_html($label) . '</a>';
    }
    echo '</div>';

    /* Bouton + Nouvelle intervention */
    echo '<div class="lfi-app-bulk-row">';
    echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('intervention-add')) . '">+ Nouvelle intervention</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('facturation-params')) . '">⚙️ Paramètres</a>';
    echo '</div>';

    if (empty($rows)) {
        echo '<div class="lfi-app-empty">Aucune intervention pour ce filtre.</div>';
        lfi_nct_app_screen_close();
        return;
    }

    /* Form bulk de génération de facture pour les "réalisées" */
    $can_bulk = $f === 'realise';
    if ($can_bulk) {
        echo '<form method="post" id="lfi-bulk-fact">';
        wp_nonce_field('lfi_app_fact_create');
        echo '<input type="hidden" name="lfi_app_fact_create" value="1">';
    }

    echo '<ul class="lfi-app-list">';
    foreach ($rows as $r) {
        $name = trim($r->tenant_prenom . ' ' . $r->tenant_nom) ?: '(anonyme)';
        $statut_lbl = [
            'planifie' => ['📅', 'Planifié',  '#bd8600'],
            'realise'  => ['✓',  'Réalisé',  '#1a7f37'],
            'facture'  => ['🧾', 'Facturé',  '#c8102e'],
            'paye'     => ['💰', 'Payé',     '#186a3b'],
            'annule'   => ['✕',  'Annulé',   '#777'],
        ][$r->statut] ?? ['?', $r->statut, '#888'];

        echo '<li class="lfi-app-card">';
        echo '<div class="head">';
        if ($can_bulk) echo '<label style="margin-right:8px"><input type="checkbox" name="ids[]" value="' . (int) $r->id . '"></label>';
        echo '<div class="who">' . esc_html($name) . '</div>';
        echo '<div class="badge" style="background:' . esc_attr($statut_lbl[2]) . ';color:#fff">' . $statut_lbl[0] . ' ' . esc_html($statut_lbl[1]) . '</div>';
        echo '</div>';

        echo '<div class="meta">';
        if ($r->date_intervention)  echo '<span class="meta-chip">🗓 ' . esc_html(wp_date('j M Y', strtotime($r->date_intervention))) . '</span>';
        if ($r->type_travaux)        echo '<span class="meta-chip">🔧 ' . esc_html($r->type_travaux) . '</span>';
        if ($r->duree_heures > 0)    echo '<span class="meta-chip">⏱ ' . number_format($r->duree_heures, 1, ',', ' ') . ' h</span>';
        echo '<span class="meta-chip"><strong>' . lfi_nct_fact_format_eur($r->total_ht) . '</strong></span>';
        if ($r->tenant_adresse)      echo '<span class="meta-chip">📍 ' . esc_html(trim($r->tenant_adresse . ($r->tenant_etage ? ' · ét. ' . $r->tenant_etage : ''))) . '</span>';
        echo '</div>';

        if ($r->description) {
            echo '<div class="com">' . esc_html(mb_substr($r->description, 0, 200)) . (mb_strlen($r->description) > 200 ? '…' : '') . '</div>';
        }

        if ($r->facture_numero) {
            echo '<div class="meta"><a class="meta-chip act" href="' . esc_url(lfi_nct_app_url('facture', ['numero' => $r->facture_numero])) . '">🧾 Voir facture ' . esc_html($r->facture_numero) . '</a></div>';
        }

        echo '<div class="row-actions">';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('intervention-edit', ['id' => $r->id])) . '">✏️ Éditer</a>';
        if ($r->statut === 'facture') {
            echo '<form method="post" style="display:inline;margin:0">';
            wp_nonce_field('lfi_app_fact_paye');
            echo '<input type="hidden" name="lfi_app_fact_paye" value="1">';
            echo '<input type="hidden" name="numero" value="' . esc_attr($r->facture_numero) . '">';
            echo '<button type="submit" class="btn-primary">💰 Marquer payée</button>';
            echo '</form>';
        }
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';

    if ($can_bulk) {
        echo '<div class="lfi-app-form" style="position:sticky;bottom:0;margin-top:14px">';
        echo '<button type="submit" class="btn-primary big">🧾 Générer une facture pour les interventions cochées</button>';
        echo '<div class="lfi-app-help"><small>Un nouveau N° de facture sera attribué. Les interventions cochées passeront au statut « facturé ».</small></div>';
        echo '</div></form>';
    }

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE : Ajout / édition d'intervention                            *
 * ============================================================== */
function lfi_nct_app_view_intervention_add() {
    if (!lfi_nct_can_use_brigade()) return;
    lfi_nct_app_intervention_form(null);
}
function lfi_nct_app_view_intervention_edit() {
    if (!lfi_nct_can_use_brigade()) return;
    global $wpdb;
    $id = (int) ($_GET['id'] ?? 0);
    $t = $wpdb->prefix . 'lfi_nct_interventions';
    $owner = (int) lfi_nct_fact_owner_id();
    $row = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d AND owner_user_id = %d", $id, $owner)) : null;
    if (!$row) {
        lfi_nct_app_screen_open('Intervention introuvable');
        echo '<div class="lfi-app-empty"><a href="' . esc_url(lfi_nct_app_url('interventions')) . '">← Retour à la liste</a></div>';
        lfi_nct_app_screen_close(false);
        return;
    }
    lfi_nct_app_intervention_form($row);
}

function lfi_nct_app_intervention_form($row) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_interventions';
    $is_edit = !empty($row);
    $err = null;

    /* Handler ANNULATION rapide : 1 clic, sans repasser par le formulaire */
    if ($is_edit && !empty($_POST['lfi_app_intervention_cancel']) && check_admin_referer('lfi_app_intervention_cancel')) {
        $owner = (int) lfi_nct_fact_owner_id();
        $update = ['statut' => 'annule'];
        /* Si une facture avait été générée, on libère le numéro pour que le
           recouvrement éventuellement ouvert sur cette facture ne pollue
           plus le dashboard. */
        if (!empty($row->facture_numero)) {
            /* Supprime aussi un éventuel recouvrement ouvert sur cette facture */
            $tr = $wpdb->prefix . 'lfi_nct_recouvrements';
            $wpdb->delete($tr, ['facture_numero' => $row->facture_numero, 'owner_user_id' => $owner]);
        }
        $wpdb->update($t, $update, ['id' => $row->id, 'owner_user_id' => $owner]);
        wp_safe_redirect(lfi_nct_app_url('interventions', ['annule' => 1]));
        exit;
    }

    /* Handler SUPPRESSION définitive */
    if ($is_edit && !empty($_POST['lfi_app_intervention_delete']) && check_admin_referer('lfi_app_intervention_delete')) {
        $owner = (int) lfi_nct_fact_owner_id();
        if (!empty($row->facture_numero)) {
            $tr = $wpdb->prefix . 'lfi_nct_recouvrements';
            $wpdb->delete($tr, ['facture_numero' => $row->facture_numero, 'owner_user_id' => $owner]);
        }
        $wpdb->delete($t, ['id' => $row->id, 'owner_user_id' => $owner]);
        wp_safe_redirect(lfi_nct_app_url('interventions', ['supprime' => 1]));
        exit;
    }

    /* POST handler */
    if (!empty($_POST['lfi_app_intervention_save']) && check_admin_referer('lfi_app_intervention_save')) {
        $data = [
            'tenant_user_id'    => ((int) ($_POST['tenant_user_id'] ?? 0)) ?: null,
            'tenant_prenom'     => sanitize_text_field(wp_unslash($_POST['tenant_prenom'] ?? '')),
            'tenant_nom'        => sanitize_text_field(wp_unslash($_POST['tenant_nom'] ?? '')),
            'tenant_adresse'    => sanitize_text_field(wp_unslash($_POST['tenant_adresse'] ?? '')),
            'tenant_etage'      => sanitize_text_field(wp_unslash($_POST['tenant_etage'] ?? '')),
            'tenant_appartement'=> sanitize_text_field(wp_unslash($_POST['tenant_appartement'] ?? '')),
            'tenant_tel'        => sanitize_text_field(wp_unslash($_POST['tenant_tel'] ?? '')),
            'bailleur'          => sanitize_text_field(wp_unslash($_POST['bailleur'] ?? 'Nantes Métropole Habitat')),
            'date_intervention' => sanitize_text_field(wp_unslash($_POST['date_intervention'] ?? '')) ?: null,
            'duree_heures'      => (float) ($_POST['duree_heures'] ?? 0),
            'type_travaux_key'  => sanitize_key($_POST['type_travaux_key'] ?? ''),
            'type_travaux'      => sanitize_text_field(wp_unslash($_POST['type_travaux'] ?? '')),
            'description'       => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
            'tarif_mode'        => (in_array(($_POST['tarif_mode'] ?? 'tache'), ['tache', 'horaire'], true) ? $_POST['tarif_mode'] : 'tache'),
            'prix_tache'        => (float) ($_POST['prix_tache'] ?? 0),
            'tarif_horaire'     => (float) ($_POST['tarif_horaire'] ?? lfi_nct_fact_tarif_defaut()),
            'cout_materiaux'    => (float) ($_POST['cout_materiaux'] ?? 0),
            'statut'            => sanitize_key($_POST['statut'] ?? 'planifie'),
            'notes'             => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
        ];
        $data['total_ht'] = lfi_nct_fact_total_smart(
            $data['tarif_mode'], $data['prix_tache'],
            $data['duree_heures'], $data['tarif_horaire'], $data['cout_materiaux']
        );

        /* Classification automatique de la catégorie à partir du type_key. */
        if (function_exists('lfi_nct_travaux_classify')) {
            $classif = lfi_nct_travaux_classify($data['type_travaux_key']);
            $data['categorie_travaux'] = $classif ? $classif['cat_key'] : '';
            if ($classif && empty($data['type_travaux'])) $data['type_travaux'] = $classif['type_label'];
        }

        /* Si on a un user_id, récupère les infos de l'enquête pour pré-remplir l'adresse */
        if ($data['tenant_user_id'] && empty($data['tenant_adresse'])) {
            $resp_id = (int) get_user_meta($data['tenant_user_id'], 'lfi_nct_response_id', true);
            if ($resp_id) {
                $resp = $wpdb->get_row($wpdb->prepare("SELECT adresse, etage FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $resp_id));
                if ($resp) {
                    $data['tenant_adresse'] = $resp->adresse ?: $data['tenant_adresse'];
                    $data['tenant_etage']   = $resp->etage   ?: $data['tenant_etage'];
                }
            }
        }

        if ($is_edit) {
            $owner = (int) lfi_nct_fact_owner_id();
            $wpdb->update($t, $data, ['id' => $row->id, 'owner_user_id' => $owner]);
            wp_safe_redirect(lfi_nct_app_url('intervention-edit', ['id' => $row->id, 'saved' => 1]));
        } else {
            /* Estampille du créateur = owner immuable */
            $data['owner_user_id'] = (int) lfi_nct_fact_owner_id();
            $wpdb->insert($t, $data);
            $new_id = (int) $wpdb->insert_id;
            wp_safe_redirect(lfi_nct_app_url('intervention-edit', ['id' => $new_id, 'created' => 1]));
        }
        exit;
    }

    /* Préselection depuis paramètres URL bruts (raccourci depuis dossier juridique) */
    if (!$is_edit && !$row) {
        $row = (object) [];
        foreach (['tenant_prenom', 'tenant_nom', 'tenant_adresse', 'tenant_etage', 'tenant_appartement', 'tenant_tel'] as $f) {
            if (!empty($_GET[$f])) $row->$f = sanitize_text_field(wp_unslash($_GET[$f]));
        }
        if (empty((array) $row)) $row = null;
    }

    /* Préselection depuis ?tenant_uid=X — auto-fill agressif depuis l'enquête */
    if (!$is_edit && !empty($_GET['tenant_uid'])) {
        $tuid = (int) $_GET['tenant_uid'];
        $u = get_userdata($tuid);
        if ($u) {
            $row = (object) [
                'tenant_user_id' => $tuid,
                'tenant_prenom'  => $u->first_name ?: $u->display_name,
                'tenant_nom'     => $u->last_name,
                'tenant_tel'     => (string) get_user_meta($tuid, 'lfi_nct_tel', true),
            ];
            $resp_id = (int) get_user_meta($tuid, 'lfi_nct_response_id', true);
            if ($resp_id) {
                $resp = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $resp_id));
                if ($resp) {
                    $row->tenant_adresse = $resp->adresse;
                    $row->tenant_etage   = $resp->etage;
                    /* Auto-construction d'une description depuis l'enquête */
                    $data_resp = json_decode($resp->data ?? '', true);
                    if (is_array($data_resp)) {
                        $types = (array) ($data_resp['problemes_types'] ?? []);
                        $autre = trim((string) ($data_resp['problemes_types_autre'] ?? ''));
                        $duree = trim((string) ($data_resp['problemes_duree'] ?? ''));
                        $gravite = (int) ($data_resp['problemes_gravite'] ?? 0);
                        $parts = [];
                        if ($types)   $parts[] = 'Problèmes déclarés dans l\'enquête : ' . implode(', ', $types) . '.';
                        if ($autre)   $parts[] = 'Précisions : ' . $autre . '.';
                        if ($duree)   $parts[] = 'Durée du problème : ' . $duree . '.';
                        if ($gravite) $parts[] = 'Gravité signalée : ' . $gravite . '/10.';
                        if ($parts)   $row->description = implode(' ', $parts);
                    }
                }
            }
        }
    }

    /* Defaults */
    $r = $row ?: (object) [
        'tenant_user_id'    => '',
        'tenant_prenom'     => '',
        'tenant_nom'        => '',
        'tenant_adresse'    => '',
        'tenant_etage'      => '',
        'tenant_appartement'=> '',
        'tenant_tel'        => '',
        'bailleur'          => lfi_nct_fact_bailleur()['nom'] ?? 'Nantes Métropole Habitat',
        'date_intervention' => current_time('Y-m-d'),
        'duree_heures'      => '',
        'type_travaux'      => '',
        'tarif_mode'        => 'tache',
        'prix_tache'        => '',
        'description'       => '',
        'tarif_horaire'     => lfi_nct_fact_tarif_defaut(),
        'cout_materiaux'    => '',
        'statut'            => 'planifie',
        'notes'             => '',
    ];

    /* Liste des locataires pour la dropdown */
    $tenants = get_users(['role' => LFI_NCT_ROLE_TENANT, 'fields' => ['ID', 'display_name'], 'number' => 200, 'orderby' => 'display_name', 'order' => 'ASC']);

    lfi_nct_app_screen_open($is_edit ? '✏️ Éditer l\'intervention #' . (int) $row->id : '+ Nouvelle intervention', 'Brigade travaux Fabrice Doucet');

    if (!empty($_GET['saved']))   lfi_nct_app_flash('✅ Intervention enregistrée.');
    if (!empty($_GET['created'])) lfi_nct_app_flash('✅ Intervention créée.');

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_intervention_save');
    echo '<input type="hidden" name="lfi_app_intervention_save" value="1">';

    /* Locataire */
    echo '<h3 style="margin:0">👤 Locataire</h3>';
    echo '<label>Compte locataire (optionnel)<select name="tenant_user_id" onchange="if(this.value){location.href=\'' . esc_url(lfi_nct_app_url('intervention-add')) . '&tenant_uid=\'+this.value;}">';
    echo '<option value="">— Saisie manuelle —</option>';
    foreach ($tenants as $u) {
        $sel = (int) $r->tenant_user_id === $u->ID ? 'selected' : '';
        echo '<option value="' . (int) $u->ID . '" ' . $sel . '>' . esc_html($u->display_name) . '</option>';
    }
    echo '</select></label>';
    echo '<label>Prénom<input type="text" name="tenant_prenom" value="' . esc_attr($r->tenant_prenom) . '"></label>';
    echo '<label>Nom<input type="text" name="tenant_nom" value="' . esc_attr($r->tenant_nom) . '"></label>';
    echo '<label>Téléphone<input type="tel" name="tenant_tel" value="' . esc_attr($r->tenant_tel) . '"></label>';
    echo '<label>Adresse<input type="text" name="tenant_adresse" value="' . esc_attr($r->tenant_adresse) . '"></label>';
    echo '<label>Étage<input type="text" name="tenant_etage" value="' . esc_attr($r->tenant_etage) . '"></label>';
    echo '<label>N° appartement<input type="text" name="tenant_appartement" value="' . esc_attr($r->tenant_appartement) . '"></label>';

    /* Travaux */
    echo '<h3 style="margin:18px 0 0">🔧 Travaux</h3>';
    echo '<label>Bailleur à facturer<input type="text" name="bailleur" value="' . esc_attr($r->bailleur) . '" required></label>';
    echo '<label>Date d\'intervention<input type="date" name="date_intervention" value="' . esc_attr($r->date_intervention) . '" required></label>';

    /* Catalogue classifié : bailleur / gris / locataire */
    $current_key = (string) ($r->type_travaux_key ?? '');
    $current_type = (string) $r->type_travaux;
    if (function_exists('lfi_nct_travaux_catalogue')) {
        echo '<label><strong>Nature des travaux (classifiés selon la loi)</strong><select name="type_travaux_key" id="lfi-type-key" required>';
        echo '<option value="">— Choisir le type exact —</option>';
        foreach (lfi_nct_travaux_catalogue() as $cat_key => $cat) {
            echo '<optgroup label="' . esc_attr($cat['label']) . '">';
            foreach ($cat['types'] as $tkey => $tlabel) {
                echo '<option value="' . esc_attr($tkey) . '" data-cat="' . esc_attr($cat_key) . '" ' . selected($current_key, $tkey, false) . '>' . esc_html($tlabel) . '</option>';
            }
            echo '</optgroup>';
        }
        echo '</select></label>';

        /* Bandeau d'alerte selon catégorie */
        echo '<div id="lfi-cat-banner" style="display:none;padding:12px 14px;border-radius:8px;margin:8px 0;font-size:.9em;line-height:1.5"></div>';
    }
    /* Champ libre conservé pour compatibilité (titre de la prestation sur facture) */
    echo '<label>Libellé court pour la facture (auto-rempli)<input type="text" name="type_travaux" id="lfi-type-label" value="' . esc_attr($current_type) . '" placeholder="Ex : Moisissures + repose placo BA13 hydro"></label>';

    echo '<label>Description détaillée (ce qui sera repris dans la facture)<textarea name="description" id="lfi-desc-interv" rows="4" placeholder="Ex : Démontage et évacuation des plaques de plâtre infestées de moisissures sur 4 m² au mur de la cuisine, ponçage du support, rebouchage à l\'enduit, repose de placo BA13 hydro, enduit de finition.">' . esc_textarea($r->description) . '</textarea></label>';
    echo '<div class="lfi-voice-zone" data-target="lfi-desc-interv" data-label="Dicter la description"></div>';

    /* === MODE DE FACTURATION === */
    $current_mode = (string) ($r->tarif_mode ?? 'tache');
    echo '<h3 style="margin:18px 0 0">💶 Facturation</h3>';

    echo '<div style="background:#e8f5ea;border-left:4px solid #186a3b;padding:10px 14px;border-radius:6px;margin:6px 0;font-size:.9em">';
    echo '✅ <strong>Tarif à la tâche (recommandé).</strong> Plus défendable au tribunal qu\'un tarif horaire — c\'est un prix de marché objectif, basé sur ce que facturent les entreprises pro (Daleco, Solitec, Rentokil…). Le juge ne peut pas le contester.';
    echo '</div>';

    echo '<div style="display:flex;gap:14px;margin:8px 0">';
    echo '<label style="flex:1;background:#fff;padding:12px;border-radius:8px;border:2px solid ' . ($current_mode === 'tache' ? '#c8102e' : '#ddd') . ';cursor:pointer">';
    echo '<input type="radio" name="tarif_mode" value="tache" ' . checked($current_mode, 'tache', false) . ' onchange="lfi_toggle_tarif_mode()"> <strong>📦 À la tâche (forfait)</strong>';
    echo '<div style="font-size:.85em;color:#666;margin-top:4px">Prix marché — recommandé</div>';
    echo '</label>';
    echo '<label style="flex:1;background:#fff;padding:12px;border-radius:8px;border:2px solid ' . ($current_mode === 'horaire' ? '#c8102e' : '#ddd') . ';cursor:pointer">';
    echo '<input type="radio" name="tarif_mode" value="horaire" ' . checked($current_mode, 'horaire', false) . ' onchange="lfi_toggle_tarif_mode()"> <strong>⏱ À l\'heure</strong>';
    echo '<div style="font-size:.85em;color:#666;margin-top:4px">Pour les cas inhabituels</div>';
    echo '</label>';
    echo '</div>';

    /* Bloc TÂCHE : prix forfaitaire + zone suggestion marché auto-rendue par JS */
    echo '<div id="lfi-bloc-tache" style="display:' . ($current_mode === 'tache' ? 'block' : 'none') . '">';
    echo '<div id="lfi-suggestion-marche" style="display:none;background:#fff8e6;border-left:4px solid #bd8600;padding:12px 14px;border-radius:6px;margin:8px 0;font-size:.9em;line-height:1.5"></div>';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
    echo '<label>Prix forfait HT (€)<input type="number" name="prix_tache" id="lfi-prix-tache" value="' . esc_attr($r->prix_tache) . '" step="1" min="0" placeholder="ex: 220"></label>';
    echo '<label>Matériaux séparés (€)<input type="number" name="cout_materiaux" id="lfi-cout-mat" value="' . esc_attr($r->cout_materiaux) . '" step="0.01" min="0" placeholder="0"></label>';
    echo '</div>';
    echo '<div class="lfi-app-help"><small>Le forfait inclut généralement la main d\'œuvre. Ne re-déclare des matériaux séparés que s\'ils ne sont pas inclus dans le forfait.</small></div>';
    echo '</div>';

    /* Bloc HORAIRE — affiché si mode horaire */
    echo '<div id="lfi-bloc-horaire" style="display:' . ($current_mode === 'horaire' ? 'block' : 'none') . '">';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">';
    echo '<label>Durée (h)<input type="number" name="duree_heures" id="lfi-duree" value="' . esc_attr($r->duree_heures) . '" step="0.25" min="0"></label>';
    echo '<label>Tarif horaire (€)<input type="number" name="tarif_horaire" id="lfi-tarif-h" value="' . esc_attr($r->tarif_horaire) . '" step="0.50" min="0"></label>';
    echo '<label>Matériaux (€)<input type="number" name="cout_materiaux" id="lfi-cout-mat-h" value="' . esc_attr($r->cout_materiaux) . '" step="0.01" min="0"></label>';
    echo '</div>';
    echo '</div>';

    $preview_total = lfi_nct_fact_total_smart($current_mode, (float) $r->prix_tache, (float) $r->duree_heures, (float) $r->tarif_horaire, (float) $r->cout_materiaux);
    echo '<div class="lfi-app-help"><strong>Total HT : <span id="lfi-total-preview">' . lfi_nct_fact_format_eur($preview_total) . '</span></strong> <small>(TVA non applicable, art. 293 B du CGI)</small></div>';

    /* === DÉTECTION PROBLÈME D'IMMEUBLE / DE RUE — cartouche tribunal ===
       Affichage et injection 100 % anonymes (RGPD). Comptes exprimés en
       formule approximative ("plusieurs", "au moins une demi-douzaine")
       et mise en avant du cluster d'étages CONSÉCUTIFS (argument le plus
       fort : ça prouve un défaut vertical d'immeuble — colonne d'aération,
       canalisation, etc.). Seuil mini : 2 locataires concernés. */
    $collectif = [
        'meme_immeuble' => [], 'immeubles_voisins' => [], 'numeros_voisins' => [],
        'rue_label' => '', 'cluster_meme' => [], 'cluster_all' => [],
        'approx_meme' => '', 'approx_voisins' => '', 'approx_total' => '',
        'cluster_meme_lbl' => '', 'cluster_all_lbl' => '',
    ];
    if (!empty($r->tenant_adresse) && !empty($r->type_travaux_key) && function_exists('lfi_nct_rec_collective_signal')) {
        $collectif = lfi_nct_rec_collective_signal($r->tenant_adresse, $r->type_travaux_key);
    }
    $n_meme    = count($collectif['meme_immeuble']);
    $n_voisin  = count($collectif['immeubles_voisins']);
    $n_total   = $n_meme + $n_voisin;
    $nums_v    = $collectif['numeros_voisins'];
    $rue_label = (string) $collectif['rue_label'];
    $cluster_meme_lbl = (string) $collectif['cluster_meme_lbl'];
    $cluster_all_lbl  = (string) $collectif['cluster_all_lbl'];

    if ($n_total >= 2) {
        echo '<div style="background:#fff3f5;border:2px solid #c8102e;border-radius:10px;padding:14px 16px;margin:14px 0">';
        echo '<div style="font-size:1.05em;font-weight:800;color:#c8102e;margin-bottom:8px">🎯 CARTOUCHE TRIBUNAL — défaut structurel détecté</div>';
        echo '<div style="font-size:.9em;color:#444;line-height:1.5;margin-bottom:8px">';
        echo 'L\'enquête révèle plusieurs logements touchés par le même type de problème. Argument juridique massif : ce n\'est plus du cas isolé, c\'est un défaut structurel à la charge exclusive du bailleur (art. 6 loi 89-462, décret 2002-120).';
        echo '</div>';

        /* Mise en avant : cluster d'étages consécutifs (LE plus fort) */
        if (!empty($cluster_meme_lbl)) {
            echo '<div style="background:#c8102e;color:#fff;border-radius:8px;padding:10px 14px;margin:8px 0">';
            echo '<div style="font-size:.8em;text-transform:uppercase;letter-spacing:.5px;font-weight:700;opacity:.9">⚡ Cluster vertical détecté — preuve la plus forte</div>';
            echo '<div style="font-size:1.05em;margin-top:4px"><strong>Plusieurs locataires sur ' . esc_html($cluster_meme_lbl) . '</strong> du même immeuble signalent ce problème.</div>';
            echo '<div style="font-size:.85em;opacity:.9;margin-top:4px">Étages contigus → défaut vertical (colonne d\'aération, canalisation, infiltration de toiture descendante). Très difficile à contester par le bailleur.</div>';
            echo '</div>';
        } elseif (!empty($cluster_all_lbl)) {
            echo '<div style="background:#fff8e6;border-left:4px solid #bd8600;border-radius:8px;padding:10px 14px;margin:8px 0">';
            echo '<div style="font-size:.8em;text-transform:uppercase;letter-spacing:.5px;font-weight:700;color:#bd8600">⚡ Étages contigus (immeubles confondus)</div>';
            echo '<div style="font-size:1em;margin-top:4px">Plusieurs locataires sur ' . esc_html($cluster_all_lbl) . ' signalent le même problème — défaut d\'ensemble immobilier.</div>';
            echo '</div>';
        }

        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:10px 0">';

        /* Bloc immeuble — count approximatif */
        echo '<div style="background:#fff;border-radius:8px;padding:10px 12px;border:1px solid #f3c4cc">';
        echo '<div style="font-size:.8em;color:#c8102e;text-transform:uppercase;letter-spacing:.5px;font-weight:700">🏢 Même immeuble</div>';
        echo '<div style="font-size:1em;color:#c8102e;margin:4px 0;font-weight:700">' . esc_html(ucfirst((string) $collectif['approx_meme'])) . '</div>';
        echo '<div style="font-size:.8em;color:#666">concernés</div>';
        echo '</div>';

        /* Bloc voisins — count approximatif + numéros d'immeubles */
        echo '<div style="background:#fff;border-radius:8px;padding:10px 12px;border:1px solid #f3c4cc">';
        echo '<div style="font-size:.8em;color:#c8102e;text-transform:uppercase;letter-spacing:.5px;font-weight:700">🏘 Immeubles voisins</div>';
        echo '<div style="font-size:1em;color:#c8102e;margin:4px 0;font-weight:700">' . esc_html(ucfirst((string) $collectif['approx_voisins'])) . '</div>';
        echo '<div style="font-size:.8em;color:#666">';
        if (!empty($nums_v)) echo 'n° ' . esc_html(implode(', ', $nums_v));
        if ($rue_label) echo ' · ' . esc_html($rue_label);
        echo '</div>';
        echo '</div>';

        echo '</div>';

        echo '<div style="margin-top:10px">';
        $args_json = wp_json_encode([
            'approx_meme'      => $collectif['approx_meme'],
            'approx_voisins'   => $collectif['approx_voisins'],
            'approx_total'     => $collectif['approx_total'],
            'cluster_meme_lbl' => $cluster_meme_lbl,
            'cluster_all_lbl'  => $cluster_all_lbl,
            'has_meme'         => $n_meme >= 1,
            'has_voisin'       => $n_voisin >= 1,
            'nums_voisins'     => $nums_v,
            'rue_label'        => $rue_label,
        ]);
        echo '<button type="button" class="btn-ghost" onclick=\'lfi_inject_motif_collectif(' . $args_json . ')\'>📋 Ajouter cet argument à la description</button>';
        echo '</div>';

        echo '<div style="font-size:.8em;color:#888;margin-top:10px;font-style:italic">🔒 Tout est anonyme : pas de noms, pas de chiffres exacts, formules approximatives uniquement (RGPD + sécurité juridique).</div>';
        echo '</div>';
    }

    echo '<label>Statut<select name="statut">';
    foreach (['planifie' => '📅 Planifié', 'realise' => '✓ Réalisé', 'annule' => '✕ Annulé'] as $k => $lbl) {
        echo '<option value="' . esc_attr($k) . '" ' . selected($r->statut, $k, false) . '>' . esc_html($lbl) . '</option>';
    }
    echo '</select></label>';

    echo '<label>Notes privées (ne figure pas sur la facture)<textarea name="notes" id="lfi-notes-interv" rows="2">' . esc_textarea($r->notes) . '</textarea></label>';
    echo '<div class="lfi-voice-zone" data-target="lfi-notes-interv" data-label="Dicter mes notes"></div>';

    echo '<button type="submit" class="btn-primary big">' . ($is_edit ? '💾 Enregistrer' : '+ Créer l\'intervention') . '</button>';
    echo '</form>';

    /* Boutons rapides : annuler / supprimer — disponibles uniquement en édition */
    if ($is_edit) {
        $facture_warn = !empty($row->facture_numero)
            ? ' Cette intervention a une facture (' . esc_html($row->facture_numero) . '). L\'annulation supprime aussi le recouvrement éventuellement ouvert.'
            : '';
        echo '<div style="margin-top:24px;padding-top:18px;border-top:2px dashed #eee">';
        echo '<h3 style="margin:0 0 8px;color:#a30b25">⚠ Annuler ou supprimer</h3>';
        echo '<div class="lfi-app-help" style="background:#fff3f5;border-left:4px solid #a30b25">';
        echo 'Tu peux annuler cette intervention (elle reste en historique avec le statut « ✕ Annulé ») ou la supprimer définitivement.' . $facture_warn;
        echo '</div>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">';

        echo '<form method="post" style="margin:0" onsubmit="return confirm(\'Annuler cette intervention ? Elle restera visible avec le statut « Annulé ».\')">';
        wp_nonce_field('lfi_app_intervention_cancel');
        echo '<input type="hidden" name="lfi_app_intervention_cancel" value="1">';
        echo '<button type="submit" style="background:#fff;color:#a30b25;border:2px solid #a30b25;padding:10px 16px;border-radius:8px;font-weight:700;cursor:pointer">✕ Annuler l\'intervention</button>';
        echo '</form>';

        echo '<form method="post" style="margin:0" onsubmit="return confirm(\'SUPPRIMER DÉFINITIVEMENT cette intervention ? Cette action est irréversible.\')">';
        wp_nonce_field('lfi_app_intervention_delete');
        echo '<input type="hidden" name="lfi_app_intervention_delete" value="1">';
        echo '<button type="submit" style="background:#a30b25;color:#fff;border:0;padding:10px 16px;border-radius:8px;font-weight:700;cursor:pointer">🗑 Supprimer définitivement</button>';
        echo '</form>';

        echo '</div>';
        echo '</div>';
    }

    /* Catalogue des tarifs marché (JSON injecté pour le JS) */
    $tarifs_json = function_exists('lfi_nct_tarif_taches_catalogue')
        ? wp_json_encode(lfi_nct_tarif_taches_catalogue())
        : '{}';
    ?>
    <script>
    var LFI_TARIFS = <?php echo $tarifs_json; ?>;

    function lfi_eur(n) {
        return (Number(n) || 0).toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+,)/g, '$1 ') + ' €';
    }

    function lfi_refresh_total() {
        var mode = (document.querySelector('input[name=tarif_mode]:checked') || {value: 'tache'}).value;
        var m, total;
        if (mode === 'horaire') {
            var d = parseFloat(document.getElementById('lfi-duree')?.value) || 0;
            var t = parseFloat(document.getElementById('lfi-tarif-h')?.value) || 0;
            m = parseFloat(document.getElementById('lfi-cout-mat-h')?.value) || 0;
            total = (d * t) + m;
        } else {
            var p = parseFloat(document.getElementById('lfi-prix-tache')?.value) || 0;
            m = parseFloat(document.getElementById('lfi-cout-mat')?.value) || 0;
            total = p + m;
        }
        var el = document.getElementById('lfi-total-preview');
        if (el) el.textContent = lfi_eur(total);
    }

    function lfi_toggle_tarif_mode() {
        var mode = (document.querySelector('input[name=tarif_mode]:checked') || {value: 'tache'}).value;
        document.getElementById('lfi-bloc-tache').style.display    = (mode === 'tache') ? 'block' : 'none';
        document.getElementById('lfi-bloc-horaire').style.display  = (mode === 'horaire') ? 'block' : 'none';
        document.querySelectorAll('input[name=tarif_mode]').forEach(function (r) {
            r.closest('label').style.borderColor = r.checked ? '#c8102e' : '#ddd';
        });
        lfi_refresh_total();
    }

    function lfi_apply_suggestion(prix) {
        var el = document.getElementById('lfi-prix-tache');
        if (el) { el.value = prix; lfi_refresh_total(); }
    }

    function lfi_render_suggestion(typeKey) {
        var box = document.getElementById('lfi-suggestion-marche');
        if (!box) return;
        var t = LFI_TARIFS[typeKey];
        if (!t) { box.style.display = 'none'; return; }
        box.style.display = 'block';
        box.innerHTML =
            '<div style="font-weight:800;color:#bd8600;margin-bottom:6px">💰 Prix marché 2026 — ' + (t.unite || '') + '</div>' +
            '<div style="margin:6px 0">' +
                '<button type="button" onclick="lfi_apply_suggestion(' + t.bas + ')" style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:6px 10px;margin-right:6px;cursor:pointer">Bas : <strong>' + t.bas + ' €</strong></button>' +
                '<button type="button" onclick="lfi_apply_suggestion(' + t.juste + ')" style="background:#186a3b;color:#fff;border:0;border-radius:6px;padding:6px 12px;margin-right:6px;cursor:pointer;font-weight:700">✓ Juste : ' + t.juste + ' €</button>' +
                '<button type="button" onclick="lfi_apply_suggestion(' + t.haut + ')" style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:6px 10px;cursor:pointer">Haut : <strong>' + t.haut + ' €</strong></button>' +
            '</div>' +
            '<div style="font-size:.85em;color:#666;line-height:1.4">' + (t.detail || '') + '</div>' +
            '<div style="font-size:.8em;color:#888;margin-top:4px"><em>Sources : ' + (t.source || '') + '</em></div>';
    }

    function lfi_inject_motif_collectif(args) {
        var d = document.querySelector('[name=description]');
        if (!d || !args) return;
        /* Texte STRICTEMENT anonyme et APPROXIMATIF :
            - aucun nom de locataire (RGPD)
            - aucun chiffre exact (formule type "plusieurs", "au moins X")
            - mise en avant du cluster d'étages consécutifs si présent
              (= la cartouche tribunal la plus solide). */
        var parts = [];
        parts.push('IMPORTANT — défaut structurel d\'ensemble immobilier établi par l\'enquête de terrain du Groupe d\'Action :');

        if (args.cluster_meme_lbl) {
            parts.push('• Cluster vertical avéré : plusieurs locataires sur ' + args.cluster_meme_lbl + ' du même immeuble signalent le même type de problème. La contiguïté des étages atteints établit l\'existence d\'un défaut vertical (colonne d\'aération, canalisation, infiltration descendante, etc.) qui ne peut relever que du bâti.');
        } else if (args.has_meme) {
            parts.push('• Au sein du même immeuble : ' + args.approx_meme + ' signalent le même type de problème.');
        }

        if (args.has_voisin) {
            var loc = '• Dans les immeubles voisins';
            if (args.nums_voisins && args.nums_voisins.length) {
                loc += ' (n° ' + args.nums_voisins.join(', ');
                if (args.rue_label) loc += ' ' + args.rue_label;
                loc += ')';
            } else if (args.rue_label) {
                loc += ' de la même rue (' + args.rue_label + ')';
            }
            loc += ' : ' + args.approx_voisins + ' signalent un problème similaire.';
            parts.push(loc);
        }

        if (args.cluster_all_lbl && !args.cluster_meme_lbl) {
            parts.push('• L\'ensemble forme un cluster cohérent (' + args.cluster_all_lbl + ') indiquant un défaut d\'ensemble immobilier et non un cas isolé.');
        }

        parts.push('Le caractère répété et géographiquement groupé des signalements exclut la cause locataire isolée et établit un manquement du bailleur à son obligation de délivrer et d\'entretenir un logement décent (art. 6 loi 89-462 ; art. 1719 et 1724 CC ; décret n° 2002-120 du 30 janvier 2002). Données anonymisées en compteurs approximatifs ; détails individuels disponibles sur réquisition judiciaire (article 145 CPC).');

        var phrase = parts.join('\n');
        d.value = (d.value ? d.value + '\n\n' : '') + phrase;
    }

    (function () {
        ['lfi-duree', 'lfi-tarif-h', 'lfi-cout-mat-h', 'lfi-prix-tache', 'lfi-cout-mat'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', lfi_refresh_total);
        });

        var BANNERS = {
            bailleur:  { bg: '#e8f5ea', bd: '#186a3b', ico: '✅',
                         msg: '<strong>OBLIGATION BAILLEUR — facturer NMH est légitime.</strong> Articles 1719 / 1724 CC, loi 89-462 art. 6, décret 2002-120 (décence). Lance le recouvrement sereinement.' },
            gris:      { bg: '#fff8e6', bd: '#bd8600', ico: '⚠',
                         msg: '<strong>ZONE GRISE — appréciation du juge.</strong> Soigne le motif d\'urgence : photos datées, copies des signalements préalables du locataire au bailleur. Sans ça, le tribunal peut te débouter.' },
            locataire: { bg: '#fff3f5', bd: '#a30b25', ico: '🚫',
                         msg: '<strong>RÉPARATION LOCATIVE (décret 87-712) — NE PAS facturer NMH.</strong> Ces travaux sont à la charge du locataire. Tu peux les facturer au locataire (qui est ton client), mais le recouvrement contre NMH sera REFUSÉ.' },
        };
        var sel = document.getElementById('lfi-type-key');
        var lbl = document.getElementById('lfi-type-label');
        var ban = document.getElementById('lfi-cat-banner');
        function updateAll() {
            if (!sel) return;
            var opt = sel.options[sel.selectedIndex];
            var cat = opt ? opt.getAttribute('data-cat') : '';
            if (lbl && opt && !lbl.value) lbl.value = opt.text;
            if (ban) {
                if (BANNERS[cat]) {
                    var b = BANNERS[cat];
                    ban.style.display = 'block';
                    ban.style.background = b.bg;
                    ban.style.borderLeft = '4px solid ' + b.bd;
                    ban.innerHTML = b.ico + ' ' + b.msg;
                } else {
                    ban.style.display = 'none';
                }
            }
            lfi_render_suggestion(sel.value);
        }
        if (sel) {
            sel.addEventListener('change', function () {
                if (lbl) lbl.value = sel.options[sel.selectedIndex].text;
                updateAll();
            });
            updateAll();
        }
    })();
    </script>
    <?php

    /* === REGROUPEMENT PAR LOCATAIRE — autres interventions + dossiers === */
    if ($is_edit && (!empty($r->tenant_nom) || !empty($r->tenant_adresse))) {
        $owner = (int) lfi_nct_fact_owner_id();
        $ti = $wpdb->prefix . 'lfi_nct_interventions';
        $td = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
        $name_clause = $r->tenant_nom ? $wpdb->prepare('LOWER(tenant_nom) = LOWER(%s)', $r->tenant_nom) : '0';
        $adr_clause  = $r->tenant_adresse ? $wpdb->prepare('LOWER(tenant_adresse) = LOWER(%s)', $r->tenant_adresse) : '0';

        $other_interv = $wpdb->get_results($wpdb->prepare(
            "SELECT id, date_intervention, type_travaux, total_ht, statut FROM $ti
             WHERE owner_user_id = %d AND id != %d AND ($name_clause OR $adr_clause)
             ORDER BY date_intervention DESC LIMIT 20",
            $owner, (int) $row->id
        )) ?: [];

        $other_dossiers = $wpdb->get_results($wpdb->prepare(
            "SELECT id, visite_date, statut FROM $td
             WHERE owner_user_id = %d AND ($name_clause OR $adr_clause)
             ORDER BY updated_at DESC LIMIT 20",
            $owner
        )) ?: [];

        if ($other_interv || $other_dossiers) {
            echo '<h3 style="margin:24px 0 8px;color:#c8102e">🔗 Tout ce qui concerne ce locataire</h3>';
            if ($other_dossiers) {
                echo '<h4 style="margin:10px 0 4px">📁 Dossiers juridiques</h4><ul class="lfi-app-list">';
                foreach ($other_dossiers as $d) {
                    echo '<li class="lfi-app-card">';
                    echo '<div class="head"><div class="who">📁 Dossier #' . (int) $d->id . '</div>';
                    echo '<div class="badge">' . esc_html($d->statut) . '</div></div>';
                    echo '<div class="row-actions"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-juridique-edit', ['id' => $d->id])) . '">Ouvrir →</a></div>';
                    echo '</li>';
                }
                echo '</ul>';
            }
            if ($other_interv) {
                echo '<h4 style="margin:10px 0 4px">🔧 Autres interventions</h4><ul class="lfi-app-list">';
                foreach ($other_interv as $i) {
                    echo '<li class="lfi-app-card">';
                    echo '<div class="head"><div class="who">🔧 ' . esc_html($i->type_travaux ?: '(sans type)') . '</div>';
                    echo '<div class="badge">' . esc_html($i->statut) . '</div></div>';
                    echo '<div class="row-actions"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('intervention-edit', ['id' => $i->id])) . '">Ouvrir →</a></div>';
                    echo '</li>';
                }
                echo '</ul>';
            }
        }

        $shortcut_args = [
            'tenant_prenom' => $r->tenant_prenom, 'tenant_nom' => $r->tenant_nom,
            'tenant_adresse'=> $r->tenant_adresse, 'tenant_etage' => $r->tenant_etage,
            'tenant_appartement' => $r->tenant_appartement, 'tenant_tel' => $r->tenant_tel,
        ];
        echo '<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-juridique-add', $shortcut_args)) . '">+ Ouvrir un dossier juridique pour ce locataire</a>';
        echo '</div>';
    }

    /* Bouton voice helper partagé */
    if (function_exists('lfi_nct_render_voice_helper')) lfi_nct_render_voice_helper();

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE : Facture (imprimable / PDF via print)                      *
 * ============================================================== */
function lfi_nct_app_view_facture() {
    if (!lfi_nct_can_use_brigade()) return;
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_interventions';

    $num = isset($_GET['numero']) ? sanitize_text_field(wp_unslash($_GET['numero'])) : '';
    if (!$num) {
        lfi_nct_app_screen_open('Facture introuvable');
        echo '<div class="lfi-app-empty"><a href="' . esc_url(lfi_nct_app_url('interventions')) . '">← Retour aux interventions</a></div>';
        lfi_nct_app_screen_close(false);
        return;
    }

    $owner = (int) lfi_nct_fact_owner_id();
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE facture_numero = %s AND owner_user_id = %d ORDER BY date_intervention ASC", $num, $owner)) ?: [];
    if (empty($rows)) {
        lfi_nct_app_screen_open('Facture ' . $num . ' introuvable');
        echo '<div class="lfi-app-empty"><a href="' . esc_url(lfi_nct_app_url('interventions')) . '">← Retour</a></div>';
        lfi_nct_app_screen_close(false);
        return;
    }

    $facture_date = $rows[0]->facture_date;
    $delai = lfi_nct_fact_delai();
    $echeance = wp_date('Y-m-d', strtotime($facture_date . ' +' . $delai . ' days'));

    $total_ht_global = 0;
    foreach ($rows as $r) $total_ht_global += (float) $r->total_ht;

    $presta = lfi_nct_fact_prestataire();
    $bailleur = lfi_nct_fact_bailleur();

    lfi_nct_app_screen_open('🧾 Facture ' . $num);

    echo '<div class="lfi-app-help no-print">📄 Cette facture est prête à imprimer ou à exporter en PDF (via la fonction « Imprimer » de ton navigateur > « Enregistrer au format PDF »).</div>';

    echo '<div class="row-actions no-print" style="margin-bottom:14px">';
    echo '<button type="button" class="btn-primary" onclick="window.print()">🖨 Imprimer / Exporter PDF</button>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('interventions')) . '">← Retour</a>';
    echo '</div>';

    echo '<div class="lfi-facture">';

    /* En-tête */
    echo '<div class="fact-header">';
    echo '<div class="fact-prestataire">';
    echo '<div class="big">' . esc_html($presta['nom'] ?? 'Fabrice Doucet') . '</div>';
    if (!empty($presta['adresse']))  echo '<div>' . esc_html($presta['adresse']) . '</div>';
    if (!empty($presta['cp_ville'])) echo '<div>' . esc_html($presta['cp_ville']) . '</div>';
    if (!empty($presta['tel']))      echo '<div>📞 ' . esc_html($presta['tel']) . '</div>';
    if (!empty($presta['email']))    echo '<div>✉ ' . esc_html($presta['email']) . '</div>';
    if (!empty($presta['siret']))    echo '<div>SIRET : ' . esc_html($presta['siret']) . '</div>';
    if (!empty($presta['ape']))      echo '<div>APE : ' . esc_html($presta['ape']) . '</div>';
    echo '</div>';
    echo '<div class="fact-titre">';
    echo '<div class="big">FACTURE</div>';
    echo '<div>N° <strong>' . esc_html($num) . '</strong></div>';
    echo '<div>Date : ' . esc_html(wp_date('j F Y', strtotime($facture_date))) . '</div>';
    echo '<div>Échéance : ' . esc_html(wp_date('j F Y', strtotime($echeance))) . '</div>';
    echo '</div>';
    echo '</div>';

    /* Client */
    echo '<div class="fact-client">';
    echo '<div class="lab">Facturé à :</div>';
    echo '<div class="big">' . esc_html($bailleur['nom'] ?? 'Nantes Métropole Habitat') . '</div>';
    if (!empty($bailleur['adresse']))  echo '<div>' . esc_html($bailleur['adresse']) . '</div>';
    if (!empty($bailleur['cp_ville'])) echo '<div>' . esc_html($bailleur['cp_ville']) . '</div>';
    if (!empty($bailleur['siret']))    echo '<div>SIRET : ' . esc_html($bailleur['siret']) . '</div>';
    echo '</div>';

    /* Tableau des prestations */
    echo '<table class="fact-table">';
    echo '<thead><tr><th>Date</th><th>Locataire / Logement</th><th>Prestation</th><th class="num">Qté</th><th class="num">PU HT</th><th class="num">Total HT</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $logement = trim($r->tenant_prenom . ' ' . $r->tenant_nom);
        if ($r->tenant_adresse) $logement .= "\n" . $r->tenant_adresse;
        if ($r->tenant_etage)    $logement .= ' · ét. ' . $r->tenant_etage;
        if ($r->tenant_appartement) $logement .= ' · appt ' . $r->tenant_appartement;

        /* Affichage selon mode : tâche (forfait) ou horaire */
        $is_horaire = (($r->tarif_mode ?? 'tache') === 'horaire');
        if ($is_horaire) {
            $qte_str  = number_format($r->duree_heures, 2, ',', ' ') . ' h';
            $pu_str   = lfi_nct_fact_format_eur($r->tarif_horaire);
            $main_ht  = (float) $r->duree_heures * (float) $r->tarif_horaire;
        } else {
            $qte_str  = '1';
            $pu_str   = lfi_nct_fact_format_eur($r->prix_tache);
            $main_ht  = (float) $r->prix_tache;
        }

        echo '<tr>';
        echo '<td>' . esc_html(wp_date('d/m/Y', strtotime($r->date_intervention))) . '</td>';
        echo '<td>' . nl2br(esc_html($logement)) . '</td>';
        echo '<td><strong>' . esc_html($r->type_travaux) . '</strong>';
        if ($r->description) echo '<br><small>' . nl2br(esc_html($r->description)) . '</small>';
        if (!$is_horaire) echo '<br><small style="color:#666"><em>Prestation forfaitaire — prix marché 2026</em></small>';
        echo '</td>';
        echo '<td class="num">' . esc_html($qte_str) . '</td>';
        echo '<td class="num">' . $pu_str . '</td>';
        echo '<td class="num">' . lfi_nct_fact_format_eur($main_ht) . '</td>';
        echo '</tr>';
        if ((float) $r->cout_materiaux > 0) {
            echo '<tr class="materiaux"><td></td><td></td><td colspan="3"><small>↳ Matériaux et fournitures</small></td>';
            echo '<td class="num">' . lfi_nct_fact_format_eur($r->cout_materiaux) . '</td></tr>';
        }
    }
    echo '</tbody>';
    echo '<tfoot><tr><td colspan="5" class="num"><strong>Total HT</strong></td><td class="num"><strong>' . lfi_nct_fact_format_eur($total_ht_global) . '</strong></td></tr></tfoot>';
    echo '</table>';

    /* Mentions légales */
    echo '<div class="fact-mentions">';
    echo '<div><strong>' . esc_html($presta['mention_tva'] ?? 'TVA non applicable, art. 293 B du CGI') . '</strong></div>';
    echo '<div style="margin-top:10px"><strong>Conditions de paiement :</strong> ' . (int) $delai . ' jours à réception de la facture.</div>';
    echo '<div><strong>Pénalités de retard :</strong> trois fois le taux d\'intérêt légal en vigueur (article L.441-10 du Code de commerce).</div>';
    echo '<div><strong>Indemnité forfaitaire pour frais de recouvrement :</strong> 40 € (décret n° 2012-1115 du 2 octobre 2012).</div>';
    echo '<div style="margin-top:10px"><strong>Pas d\'escompte pour paiement anticipé.</strong></div>';
    if (!empty($presta['iban'])) {
        echo '<div style="margin-top:14px;background:#f8f8f8;padding:10px;border-radius:6px"><strong>Coordonnées bancaires</strong><br>';
        echo 'Bénéficiaire : ' . esc_html($presta['nom']) . '<br>';
        echo 'IBAN : <code>' . esc_html($presta['iban']) . '</code>';
        if (!empty($presta['bic'])) echo '<br>BIC : <code>' . esc_html($presta['bic']) . '</code>';
        echo '</div>';
    } else {
        echo '<div style="margin-top:14px;color:#a30b25"><em>⚠ Renseigne tes coordonnées bancaires dans <a href="' . esc_url(lfi_nct_app_url('facturation-params')) . '">⚙️ Paramètres</a> pour qu\'elles apparaissent sur les prochaines factures.</em></div>';
    }
    echo '</div>';

    echo '</div>';

    /* CSS print + facture */
    ?>
    <style>
    .lfi-facture {
        background: #fff; padding: 22px; border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,.08);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        color: #1a1a1a; line-height: 1.45;
    }
    .lfi-facture .fact-header {
        display: flex; justify-content: space-between; gap: 20px;
        padding-bottom: 18px; border-bottom: 3px solid #c8102e;
        margin-bottom: 18px;
    }
    .lfi-facture .fact-prestataire .big { font-size: 1.2em; font-weight: 800; color: #c8102e; margin-bottom: 4px; }
    .lfi-facture .fact-prestataire div { font-size: .9em; }
    .lfi-facture .fact-titre { text-align: right; }
    .lfi-facture .fact-titre .big { font-size: 2em; font-weight: 900; letter-spacing: 1px; color: #c8102e; }
    .lfi-facture .fact-titre div { font-size: .92em; margin-top: 2px; }
    .lfi-facture .fact-client {
        background: #f8f8f8; padding: 14px; border-radius: 6px;
        margin-bottom: 18px; font-size: .92em;
    }
    .lfi-facture .fact-client .lab { font-size: .8em; color: #888; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
    .lfi-facture .fact-client .big { font-weight: 700; font-size: 1.05em; margin-bottom: 2px; }
    .lfi-facture .fact-table { width: 100%; border-collapse: collapse; margin: 18px 0; font-size: .88em; }
    .lfi-facture .fact-table th { background: #c8102e; color: #fff; padding: 8px 6px; text-align: left; font-weight: 700; }
    .lfi-facture .fact-table th.num, .lfi-facture .fact-table td.num { text-align: right; }
    .lfi-facture .fact-table td { padding: 8px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
    .lfi-facture .fact-table tr.materiaux td { background: #fafafa; color: #666; }
    .lfi-facture .fact-table tfoot td { background: #fff8e6; padding: 12px 6px; font-size: 1.05em; border-bottom: 0; }
    .lfi-facture .fact-mentions { font-size: .8em; color: #555; line-height: 1.5; margin-top: 18px; padding-top: 14px; border-top: 1px solid #eee; }
    @media print {
        .lfi-app-navbar, .lfi-quickbar, .no-print, .row-actions { display: none !important; }
        body { background: #fff !important; }
        .lfi-facture { box-shadow: none; border: 0; }
        .lfi-app, .lfi-app-screen, .lfi-app-screen-body { padding: 0 !important; margin: 0 !important; }
    }
    </style>
    <?php

    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  VUE : Paramètres facturation (prestataire + bailleur + tarif)   *
 * ============================================================== */
function lfi_nct_app_view_facturation_params() {
    if (!lfi_nct_can_use_brigade()) return;

    $uid = (int) lfi_nct_fact_owner_id();

    if (!empty($_POST['lfi_app_fact_params']) && check_admin_referer('lfi_app_fact_params')) {
        $presta = lfi_nct_fact_prestataire($uid);
        foreach (['nom', 'adresse', 'cp_ville', 'siret', 'ape', 'email', 'tel', 'iban', 'bic', 'mention_tva'] as $k) {
            $presta[$k] = sanitize_text_field(wp_unslash($_POST['presta_' . $k] ?? ''));
        }
        update_user_meta($uid, 'lfi_nct_fact_prestataire', $presta);

        $bailleur = lfi_nct_fact_bailleur($uid);
        foreach (['nom', 'adresse', 'cp_ville', 'siret', 'email',
                  'agence_nom', 'agence_secteur', 'agence_contact',
                  'agence_email', 'agence_adresse', 'agence_tel'] as $k) {
            $bailleur[$k] = sanitize_text_field(wp_unslash($_POST['bailleur_' . $k] ?? ''));
        }
        update_user_meta($uid, 'lfi_nct_fact_bailleur', $bailleur);

        update_user_meta($uid, 'lfi_nct_fact_tarif_defaut', (float) ($_POST['tarif_defaut'] ?? 40.00));
        update_user_meta($uid, 'lfi_nct_fact_delai_paiement', max(1, (int) ($_POST['delai_paiement'] ?? 30)));
        $prefix_in = sanitize_text_field(wp_unslash($_POST['invoice_prefix'] ?? ''));
        if ($prefix_in !== '') update_user_meta($uid, 'lfi_nct_fact_invoice_prefix', $prefix_in);

        wp_safe_redirect(lfi_nct_app_url('facturation-params', ['saved' => 1]));
        exit;
    }

    $presta = lfi_nct_fact_prestataire($uid);
    $bailleur = lfi_nct_fact_bailleur($uid);
    $tarif = lfi_nct_fact_tarif_defaut($uid);
    $delai = lfi_nct_fact_delai($uid);
    $prefix = (string) get_user_meta($uid, 'lfi_nct_fact_invoice_prefix', true);
    if (!$prefix) $prefix = lfi_nct_fact_invoice_prefix_default($uid);
    $counter = (int) get_user_meta($uid, 'lfi_nct_fact_invoice_counter', true);

    lfi_nct_app_screen_open('⚙️ Paramètres facturation', 'TES paramètres — privés, jamais partagés');

    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Paramètres enregistrés.');

    echo '<div class="lfi-app-help" style="background:#e8f5ea;border-left:4px solid #186a3b">';
    echo '🔒 <strong>Ces données ne sont visibles QUE par toi.</strong> Ton IBAN, ton SIRET, ton tarif, ton compteur de facture — chacun a les siens. Quand tu crées une facture, elle est numérotée dans TA série (avec tes initiales) et ne se mélange jamais avec celles des autres membres de la brigade.';
    echo '</div>';

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_fact_params');
    echo '<input type="hidden" name="lfi_app_fact_params" value="1">';

    echo '<h3 style="margin:0">👤 Prestataire (auto-entrepreneur)</h3>';
    echo '<label>Nom complet<input type="text" name="presta_nom" value="' . esc_attr($presta['nom'] ?? '') . '" required></label>';
    echo '<label>Adresse<input type="text" name="presta_adresse" value="' . esc_attr($presta['adresse'] ?? '') . '"></label>';
    echo '<label>Code postal et ville<input type="text" name="presta_cp_ville" value="' . esc_attr($presta['cp_ville'] ?? '') . '" placeholder="44200 Nantes"></label>';
    echo '<label>N° SIRET (optionnel, si déclaré)<input type="text" name="presta_siret" value="' . esc_attr($presta['siret'] ?? '') . '" placeholder="laisse vide si pas encore déclaré"></label>';
    echo '<label>Code APE (optionnel)<input type="text" name="presta_ape" value="' . esc_attr($presta['ape'] ?? '') . '" placeholder="4334Z (Plâtrerie) — facultatif"></label>';
    echo '<div class="lfi-app-help"><small>Tu peux faire les travaux et facturer avant de déclarer ton activité. SIRET / APE sont optionnels et apparaîtront sur la facture seulement s\'ils sont renseignés. Tu pourras compléter ces champs après ta déclaration.</small></div>';
    echo '<label>Téléphone<input type="tel" name="presta_tel" value="' . esc_attr($presta['tel'] ?? '') . '"></label>';
    echo '<label>Email<input type="email" name="presta_email" value="' . esc_attr($presta['email'] ?? '') . '"></label>';
    echo '<label>IBAN (pour règlement)<input type="text" name="presta_iban" value="' . esc_attr($presta['iban'] ?? '') . '" placeholder="FR76..."></label>';
    echo '<label>BIC<input type="text" name="presta_bic" value="' . esc_attr($presta['bic'] ?? '') . '"></label>';
    echo '<label>Mention TVA<input type="text" name="presta_mention_tva" value="' . esc_attr($presta['mention_tva'] ?? 'TVA non applicable, art. 293 B du CGI') . '"></label>';

    echo '<h3 style="margin:18px 0 0">🏢 Bailleur — direction générale</h3>';
    echo '<label>Nom du bailleur<input type="text" name="bailleur_nom" value="' . esc_attr($bailleur['nom'] ?? 'Nantes Métropole Habitat') . '" required></label>';
    echo '<label>Adresse siège<input type="text" name="bailleur_adresse" value="' . esc_attr($bailleur['adresse'] ?? '') . '"></label>';
    echo '<label>Code postal et ville<input type="text" name="bailleur_cp_ville" value="' . esc_attr($bailleur['cp_ville'] ?? '') . '"></label>';
    echo '<label>N° SIRET<input type="text" name="bailleur_siret" value="' . esc_attr($bailleur['siret'] ?? '') . '"></label>';
    echo '<label>Email contact général<input type="email" name="bailleur_email" value="' . esc_attr($bailleur['email'] ?? '') . '"></label>';

    /* === Agence locale Clos Toreau / Nantes Sud === */
    echo '<h3 style="margin:18px 0 0">🏘 Agence locale (responsable de secteur)</h3>';
    echo '<div class="lfi-app-help"><small>Pour le Clos Toreau / Nantes Sud, c\'est l\'<strong>Agence Goudy</strong>. Le responsable est <strong>M. Yvonnic Morineau</strong> (<code>yvonnic.morineau@nmh.fr</code>). Ces infos seront automatiquement reprises en « copie pour information » sur toutes les lettres LRAR pour que le bon interlocuteur soit averti en temps réel.</small></div>';
    echo '<label>Nom de l\'agence<input type="text" name="bailleur_agence_nom" value="' . esc_attr($bailleur['agence_nom'] ?? 'Agence Goudy') . '" placeholder="Agence Goudy"></label>';
    echo '<label>Secteur géré<input type="text" name="bailleur_agence_secteur" value="' . esc_attr($bailleur['agence_secteur'] ?? '') . '" placeholder="Clos Toreau / Nantes Sud"></label>';
    echo '<label>Responsable<input type="text" name="bailleur_agence_contact" value="' . esc_attr($bailleur['agence_contact'] ?? '') . '" placeholder="M. Yvonnic Morineau"></label>';
    echo '<label>Email du responsable<input type="email" name="bailleur_agence_email" value="' . esc_attr($bailleur['agence_email'] ?? '') . '" placeholder="yvonnic.morineau@nmh.fr"></label>';
    echo '<label>Adresse agence<input type="text" name="bailleur_agence_adresse" value="' . esc_attr($bailleur['agence_adresse'] ?? '') . '" placeholder="ex: 6 rue Goudy, 44200 Nantes"></label>';
    echo '<label>Téléphone agence<input type="tel" name="bailleur_agence_tel" value="' . esc_attr($bailleur['agence_tel'] ?? '') . '"></label>';

    echo '<h3 style="margin:18px 0 0">💶 Tarification</h3>';
    echo '<label>Tarif horaire par défaut (€ HT)<input type="number" name="tarif_defaut" value="' . esc_attr($tarif) . '" step="0.50" min="0" required></label>';
    echo '<label>Délai de paiement (jours)<input type="number" name="delai_paiement" value="' . esc_attr($delai) . '" min="1" max="180" required></label>';

    echo '<h3 style="margin:18px 0 0">🧾 Numérotation des factures</h3>';
    echo '<label>Préfixe (sera concaténé avec NNNN)<input type="text" name="invoice_prefix" value="' . esc_attr($prefix) . '" required></label>';
    echo '<div class="lfi-app-help">Compteur actuel : <strong>' . $counter . '</strong>. Prochaine facture sera <code>' . esc_html($prefix . str_pad((string) ($counter + 1), 4, '0', STR_PAD_LEFT)) . '</code>.</div>';
    echo '<div class="lfi-app-help"><small>⚠ Le compteur ne peut PAS être remis à zéro en cours d\'année : c\'est une obligation légale (la numérotation doit être chronologique et continue).</small></div>';

    echo '<button type="submit" class="btn-primary big">💾 Enregistrer les paramètres</button>';
    echo '</form>';

    lfi_nct_app_screen_close();
}
