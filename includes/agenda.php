<?php
/**
 * Module Agenda — deux espaces distincts :
 *
 *  1. Agenda GA (admin) : tous les RDV, visites, appels, interventions
 *     facturables. Vue d'ensemble pour la brigade Fabrice.
 *
 *  2. Agenda personnel locataire : SES propres rendez-vous avec le GA
 *     uniquement. JAMAIS les événements publics du GA (réunions internes,
 *     conférences politiques, etc.) — ceux-ci restent dans le CPT
 *     ag_evenement et l'agenda public du site.
 *
 * Les interventions facturables (lfi_nct_interventions) apparaissent aussi
 * dans l'agenda du locataire concerné (date programmée = visite chez lui).
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_AGENDA_DBVER_KEY = 'lfi_nct_agenda_db_ver';
const LFI_NCT_AGENDA_DBVER_VAL = '2';

/* ============================================================== *
 *  DB Setup                                                         *
 * ============================================================== */
add_action('init', 'lfi_nct_agenda_db_setup', 7);
function lfi_nct_agenda_db_setup() {
    if (get_option(LFI_NCT_AGENDA_DBVER_KEY) === LFI_NCT_AGENDA_DBVER_VAL) return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $t = $wpdb->prefix . 'lfi_nct_rdv';
    dbDelta("CREATE TABLE $t (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        owner_user_id BIGINT UNSIGNED DEFAULT NULL,
        tenant_user_id BIGINT UNSIGNED DEFAULT NULL,
        tenant_name VARCHAR(120) DEFAULT '',
        tenant_tel VARCHAR(40) DEFAULT '',
        tenant_adresse VARCHAR(255) DEFAULT '',
        date_rdv DATE DEFAULT NULL,
        heure_debut TIME DEFAULT NULL,
        heure_fin TIME DEFAULT NULL,
        type VARCHAR(30) DEFAULT 'rdv',
        lieu VARCHAR(120) DEFAULT '',
        description TEXT,
        statut VARCHAR(20) DEFAULT 'planifie',
        intervention_id BIGINT UNSIGNED DEFAULT NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY owner_user_id (owner_user_id),
        KEY tenant_user_id (tenant_user_id),
        KEY date_rdv (date_rdv),
        KEY statut (statut)
    ) $charset;");

    /* MULTI-GA : colonne propriétaire pour cloisonner l'agenda par GA. */
    $has_owner = $wpdb->get_var("SHOW COLUMNS FROM $t LIKE 'owner_user_id'");
    if (!$has_owner) {
        $wpdb->query("ALTER TABLE $t ADD COLUMN owner_user_id BIGINT UNSIGNED DEFAULT NULL AFTER id");
        $wpdb->query("ALTER TABLE $t ADD INDEX owner_user_id (owner_user_id)");
    }

    update_option(LFI_NCT_AGENDA_DBVER_KEY, LFI_NCT_AGENDA_DBVER_VAL, false);
}

/* ============================================================== *
 *  Helpers                                                          *
 * ============================================================== */

function lfi_nct_agenda_types() {
    return [
        'rdv'                 => '💬 RDV simple',
        'visite_chez_tenant'  => '🏠 Visite chez le locataire',
        'visite_au_local'     => '🏛 RDV au local du GA',
        'appel_telephonique'  => '📞 Appel téléphonique',
        'reunion_collective'  => '👥 Réunion collective logement',
        'intervention_travaux'=> '🔧 Intervention travaux',
        'demarche_admin'      => '📋 Démarche administrative',
    ];
}

function lfi_nct_agenda_statuts() {
    return [
        'planifie' => ['📅', 'Planifié', '#bd8600'],
        'confirme' => ['✓',  'Confirmé', '#1a7f37'],
        'realise'  => ['✅', 'Réalisé',  '#186a3b'],
        'annule'   => ['✕',  'Annulé',   '#777'],
        'reporte'  => ['↪',  'Reporté',  '#aa6c00'],
    ];
}

/**
 * Récupère les RDV à venir pour un locataire donné, mergés avec
 * les interventions facturables programmées chez lui.
 */
function lfi_nct_agenda_rdvs_tenant($user_id, $limit = 10) {
    global $wpdb;
    $tr = $wpdb->prefix . 'lfi_nct_rdv';
    $ti = $wpdb->prefix . 'lfi_nct_interventions';

    $today = current_time('Y-m-d');
    $rdvs = $wpdb->get_results($wpdb->prepare(
        "SELECT id, date_rdv AS date, heure_debut AS heure, type, lieu, description, statut, NULL AS intervention_id, 'rdv' AS source
         FROM $tr
         WHERE tenant_user_id = %d AND date_rdv >= %s AND statut NOT IN ('annule')
         ORDER BY date_rdv ASC, heure_debut ASC LIMIT %d",
        $user_id, $today, $limit
    )) ?: [];

    $interventions = $wpdb->get_results($wpdb->prepare(
        "SELECT id AS intervention_id, date_intervention AS date, NULL AS heure, type_travaux AS type, tenant_adresse AS lieu, description, statut, id AS intervention_id, 'intervention' AS source
         FROM $ti
         WHERE tenant_user_id = %d AND date_intervention >= %s AND statut IN ('planifie', 'realise')
         ORDER BY date_intervention ASC LIMIT %d",
        $user_id, $today, $limit
    )) ?: [];

    $merged = array_merge($rdvs, $interventions);
    usort($merged, function ($a, $b) {
        $d = strcmp($a->date, $b->date);
        if ($d !== 0) return $d;
        return strcmp((string) ($a->heure ?? ''), (string) ($b->heure ?? ''));
    });
    return array_slice($merged, 0, $limit);
}

/* ============================================================== *
 *  ADMIN : Agenda complet (tous les RDV + interventions)           *
 * ============================================================== */
function lfi_nct_app_view_agenda() {
    if (!lfi_nct_app_guard_brigade()) return;
    global $wpdb;
    $tr = $wpdb->prefix . 'lfi_nct_rdv';
    $ti = $wpdb->prefix . 'lfi_nct_interventions';

    /* Filtre période */
    $period = isset($_GET['p']) ? sanitize_key($_GET['p']) : 'upcoming';
    $today  = current_time('Y-m-d');

    /* Cloisonnement par propriétaire (GA) : sur l'espace d'un autre GA on ne
       voit JAMAIS ses propres RDV/interventions. */
    $own_rdv = function_exists('lfi_nct_owner_clause') ? lfi_nct_owner_clause('owner_user_id') : '';
    $own_int = function_exists('lfi_nct_owner_clause') ? lfi_nct_owner_clause('owner_user_id') : '';

    if ($period === 'past') {
        $where_rdv  = $wpdb->prepare("date_rdv < %s", $today) . $own_rdv;
        $where_int  = $wpdb->prepare("date_intervention < %s", $today) . $own_int;
        $order = 'DESC';
    } else {
        $where_rdv  = $wpdb->prepare("date_rdv >= %s AND statut NOT IN ('annule')", $today) . $own_rdv;
        $where_int  = $wpdb->prepare("date_intervention >= %s AND statut IN ('planifie', 'realise')", $today) . $own_int;
        $order = 'ASC';
    }

    $rdvs = $wpdb->get_results(
        "SELECT id, tenant_user_id, tenant_name, tenant_tel, tenant_adresse, date_rdv AS date,
                heure_debut AS heure, heure_fin, type, lieu, description, statut,
                NULL AS intervention_id, 'rdv' AS source
         FROM $tr WHERE $where_rdv
         ORDER BY date_rdv $order, heure_debut $order LIMIT 100"
    ) ?: [];

    $ints = $wpdb->get_results(
        "SELECT id AS intervention_id, tenant_user_id, CONCAT(tenant_prenom, ' ', tenant_nom) AS tenant_name,
                tenant_tel, tenant_adresse, date_intervention AS date,
                NULL AS heure, NULL AS heure_fin, type_travaux AS type,
                CONCAT('Chez ', tenant_prenom, ' ', tenant_nom) AS lieu,
                description, statut, id AS intervention_id, 'intervention' AS source
         FROM $ti WHERE $where_int
         ORDER BY date_intervention $order LIMIT 100"
    ) ?: [];

    $items = array_merge($rdvs, $ints);
    usort($items, function ($a, $b) use ($order) {
        $d = strcmp($a->date, $b->date);
        if ($d === 0) $d = strcmp((string) ($a->heure ?? ''), (string) ($b->heure ?? ''));
        return $order === 'ASC' ? $d : -$d;
    });

    $types_lbl = lfi_nct_agenda_types();
    $statuts_lbl = lfi_nct_agenda_statuts();

    lfi_nct_app_screen_open('📅 Agenda GA', count($items) . ' rendez-vous · ' . ($period === 'past' ? 'historique' : 'à venir'));

    /* Filtres */
    echo '<div class="lfi-app-filter-chips">';
    foreach (['upcoming' => '📅 À venir', 'past' => '📋 Passés'] as $k => $lbl) {
        $cls = $period === $k ? 'on' : '';
        echo '<a class="fc ' . esc_attr($cls) . '" href="' . esc_url(lfi_nct_app_url('agenda', ['p' => $k])) . '">' . esc_html($lbl) . '</a>';
    }
    echo '</div>';

    /* Bouton nouveau RDV */
    echo '<div class="lfi-app-bulk-row">';
    echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('rdv-add')) . '">+ Nouveau rendez-vous</a>';
    echo '</div>';

    if (empty($items)) {
        echo '<div class="lfi-app-empty">Aucun rendez-vous ' . ($period === 'past' ? 'passé' : 'à venir') . '.</div>';
        lfi_nct_app_screen_close();
        return;
    }

    /* Liste groupée par date */
    $current_date = '';
    echo '<ul class="lfi-app-list">';
    foreach ($items as $r) {
        if ($r->date !== $current_date) {
            $current_date = $r->date;
            $when = wp_date('l j F Y', strtotime($r->date));
            echo '<li class="lfi-agenda-day">' . esc_html(ucfirst($when)) . '</li>';
        }

        $statut = $statuts_lbl[$r->statut] ?? ['?', $r->statut, '#888'];
        $type_lbl = $types_lbl[$r->type] ?? $r->type;

        echo '<li class="lfi-app-card">';
        echo '<div class="head"><div class="who">';
        if ($r->heure) echo '<strong>' . esc_html(substr($r->heure, 0, 5)) . '</strong> · ';
        echo esc_html($r->tenant_name ?: '(sans locataire)');
        echo '</div>';
        echo '<div class="badge" style="background:' . esc_attr($statut[2]) . ';color:#fff">' . $statut[0] . ' ' . esc_html($statut[1]) . '</div>';
        echo '</div>';

        echo '<div class="meta">';
        echo '<span class="meta-chip">' . esc_html($type_lbl) . '</span>';
        if ($r->lieu) echo '<span class="meta-chip">📍 ' . esc_html($r->lieu) . '</span>';
        if ($r->tenant_tel) echo '<a class="meta-chip" href="tel:' . esc_attr($r->tenant_tel) . '">📞 ' . esc_html($r->tenant_tel) . '</a>';
        echo '</div>';

        if ($r->description) {
            echo '<div class="com">' . esc_html(mb_substr($r->description, 0, 150)) . (mb_strlen($r->description) > 150 ? '…' : '') . '</div>';
        }

        echo '<div class="row-actions">';
        if ($r->source === 'rdv') {
            echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('rdv-edit', ['id' => $r->id])) . '">✏️ Éditer</a>';
        } else {
            echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('intervention-edit', ['id' => $r->intervention_id])) . '">🔧 Voir intervention</a>';
        }
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  ADMIN : Création / édition d'un RDV                              *
 * ============================================================== */
function lfi_nct_app_view_rdv_add() {
    if (!lfi_nct_app_guard_brigade()) return;
    lfi_nct_agenda_rdv_form(null);
}
function lfi_nct_app_view_rdv_edit() {
    if (!lfi_nct_app_guard_brigade()) return;
    global $wpdb;
    $id = (int) ($_GET['id'] ?? 0);
    $own = function_exists('lfi_nct_owner_clause') ? lfi_nct_owner_clause('owner_user_id') : '';
    $row = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_rdv WHERE id = %d" . $own, $id)) : null;
    if (!$row) {
        lfi_nct_app_screen_open('RDV introuvable');
        echo '<div class="lfi-app-empty"><a href="' . esc_url(lfi_nct_app_url('agenda')) . '">← Retour à l\'agenda</a></div>';
        lfi_nct_app_screen_close(false);
        return;
    }
    lfi_nct_agenda_rdv_form($row);
}

function lfi_nct_agenda_rdv_form($row) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_rdv';
    $is_edit = !empty($row);

    if (!empty($_POST['lfi_app_rdv_save']) && check_admin_referer('lfi_app_rdv_save')) {
        $data = [
            'tenant_user_id' => ((int) ($_POST['tenant_user_id'] ?? 0)) ?: null,
            'tenant_name'    => sanitize_text_field(wp_unslash($_POST['tenant_name'] ?? '')),
            'tenant_tel'     => sanitize_text_field(wp_unslash($_POST['tenant_tel'] ?? '')),
            'tenant_adresse' => sanitize_text_field(wp_unslash($_POST['tenant_adresse'] ?? '')),
            'date_rdv'       => sanitize_text_field(wp_unslash($_POST['date_rdv'] ?? '')) ?: null,
            'heure_debut'    => sanitize_text_field(wp_unslash($_POST['heure_debut'] ?? '')) ?: null,
            'heure_fin'      => sanitize_text_field(wp_unslash($_POST['heure_fin'] ?? '')) ?: null,
            'type'           => sanitize_key($_POST['type'] ?? 'rdv'),
            'lieu'           => sanitize_text_field(wp_unslash($_POST['lieu'] ?? '')),
            // (le point de RDV saisi est appris juste après l'enregistrement)
            'description'    => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
            'statut'         => sanitize_key($_POST['statut'] ?? 'planifie'),
            'notes'          => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
        ];

        /* Apprend le point de RDV pour le GA (dispo en liste la prochaine fois). */
        if (!empty($data['lieu']) && function_exists('lfi_nct_rdv_learn')) lfi_nct_rdv_learn($data['lieu']);

        /* Auto-complète depuis l'user_id si choisi */
        if ($data['tenant_user_id'] && !$data['tenant_name']) {
            $u = get_userdata($data['tenant_user_id']);
            if ($u) $data['tenant_name'] = $u->display_name;
        }
        if ($data['tenant_user_id'] && !$data['tenant_tel']) {
            $data['tenant_tel'] = (string) get_user_meta($data['tenant_user_id'], 'lfi_nct_tel', true);
        }

        if ($is_edit) {
            /* WHERE borné au propriétaire aussi (défense en profondeur : $row
               a déjà été chargé avec la clause owner). */
            $own_uid = function_exists('lfi_nct_brigade_owner_id') ? (int) lfi_nct_brigade_owner_id() : (int) get_current_user_id();
            $wpdb->update($t, $data, ['id' => $row->id, 'owner_user_id' => $own_uid]);
            wp_safe_redirect(lfi_nct_app_url('rdv-edit', ['id' => $row->id, 'saved' => 1]));
        } else {
            /* Rattache le RDV au propriétaire (GA) courant → cloisonnement agenda. */
            $data['owner_user_id'] = function_exists('lfi_nct_brigade_owner_id') ? lfi_nct_brigade_owner_id() : get_current_user_id();
            $wpdb->insert($t, $data);
            $new_id = (int) $wpdb->insert_id;
            wp_safe_redirect(lfi_nct_app_url('rdv-edit', ['id' => $new_id, 'created' => 1]));
        }
        exit;
    }

    /* Pré-sélection depuis ?tenant_uid=X */
    if (!$is_edit && !empty($_GET['tenant_uid'])) {
        $uid = (int) $_GET['tenant_uid'];
        $u = get_userdata($uid);
        if ($u) {
            $row = (object) [
                'tenant_user_id' => $uid,
                'tenant_name'    => $u->display_name,
                'tenant_tel'     => (string) get_user_meta($uid, 'lfi_nct_tel', true),
                'tenant_adresse' => '',
            ];
            $resp_id = (int) get_user_meta($uid, 'lfi_nct_response_id', true);
            if ($resp_id) {
                $resp = $wpdb->get_row($wpdb->prepare("SELECT adresse FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $resp_id));
                if ($resp) $row->tenant_adresse = $resp->adresse;
            }
        }
    }

    $r = $row ?: (object) [
        'tenant_user_id' => '', 'tenant_name' => '', 'tenant_tel' => '', 'tenant_adresse' => '',
        'date_rdv' => current_time('Y-m-d'), 'heure_debut' => '15:00', 'heure_fin' => '',
        'type' => 'visite_chez_tenant', 'lieu' => '', 'description' => '', 'statut' => 'planifie', 'notes' => '',
    ];

    $tenants = get_users([
        'role' => LFI_NCT_ROLE_TENANT, 'fields' => ['ID', 'display_name'],
        'number' => 200, 'orderby' => 'display_name', 'order' => 'ASC',
    ]);

    lfi_nct_app_screen_open($is_edit ? '✏️ Éditer le RDV' : '+ Nouveau rendez-vous', 'Agenda perso (entre toi et le locataire)');

    if (!empty($_GET['saved']))   lfi_nct_app_flash('✅ RDV enregistré.');
    if (!empty($_GET['created'])) lfi_nct_app_flash('✅ RDV créé.');

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_rdv_save');
    echo '<input type="hidden" name="lfi_app_rdv_save" value="1">';

    echo '<label>👤 Locataire (compte lié)<select name="tenant_user_id">';
    echo '<option value="">— saisir manuellement ci-dessous —</option>';
    foreach ($tenants as $u) {
        $sel = (int) $r->tenant_user_id === $u->ID ? 'selected' : '';
        echo '<option value="' . (int) $u->ID . '" ' . $sel . '>' . esc_html($u->display_name) . '</option>';
    }
    echo '</select></label>';
    echo '<label>Nom (si pas de compte)<input type="text" name="tenant_name" value="' . esc_attr($r->tenant_name) . '"></label>';
    echo '<label>Téléphone<input type="tel" name="tenant_tel" value="' . esc_attr($r->tenant_tel) . '"></label>';
    echo '<label>Adresse<input type="text" name="tenant_adresse" value="' . esc_attr($r->tenant_adresse) . '"></label>';

    echo '<h3 style="margin:18px 0 0">📅 Date et heure</h3>';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">';
    echo '<label>Date<input type="date" name="date_rdv" value="' . esc_attr($r->date_rdv) . '" required></label>';
    echo '<label>Heure début<input type="time" name="heure_debut" value="' . esc_attr($r->heure_debut) . '"></label>';
    echo '<label>Heure fin<input type="time" name="heure_fin" value="' . esc_attr($r->heure_fin) . '"></label>';
    echo '</div>';

    echo '<label>Type<select name="type">';
    foreach (lfi_nct_agenda_types() as $k => $lbl) {
        echo '<option value="' . esc_attr($k) . '" ' . selected($r->type, $k, false) . '>' . esc_html($lbl) . '</option>';
    }
    echo '</select></label>';

    $rdv_dl_ag = function_exists('lfi_nct_rdv_datalist') ? lfi_nct_rdv_datalist('lfi-rdv-pts-ag') : '';
    echo '<label>Lieu (si autre que chez la personne)<input type="text" name="lieu" list="lfi-rdv-pts-ag" value="' . esc_attr($r->lieu) . '" placeholder="Ex : Place du Pays Basque, local du GA — laisse vide si chez la personne">' . $rdv_dl_ag . '</label>';

    echo '<label>Description (vue par le locataire dans son agenda)<textarea name="description" rows="3" placeholder="Ex : Visite pour évaluer les moisissures dans la cuisine">' . esc_textarea($r->description) . '</textarea></label>';

    echo '<label>Statut<select name="statut">';
    foreach (lfi_nct_agenda_statuts() as $k => $info) {
        echo '<option value="' . esc_attr($k) . '" ' . selected($r->statut, $k, false) . '>' . $info[0] . ' ' . esc_html($info[1]) . '</option>';
    }
    echo '</select></label>';

    echo '<label>Notes privées (non visibles par le locataire)<textarea name="notes" rows="2">' . esc_textarea($r->notes) . '</textarea></label>';

    echo '<button type="submit" class="btn-primary big">' . ($is_edit ? '💾 Enregistrer' : '+ Créer le RDV') . '</button>';
    echo '</form>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  LOCATAIRE : Mon agenda perso (RDV + interventions)              *
 * ============================================================== */
function lfi_nct_app_view_mes_rdv() {
    if (!is_user_logged_in()) return;
    if (!function_exists('lfi_nct_user_role_tenant') || !lfi_nct_user_role_tenant()) {
        echo '<div class="lfi-app"><div class="lfi-app-error">Page réservée aux locataires suivis.</div></div>';
        return;
    }
    $user = wp_get_current_user();
    $items = lfi_nct_agenda_rdvs_tenant($user->ID, 20);

    /* Aussi le passé pour mémoire */
    global $wpdb;
    $past = $wpdb->get_results($wpdb->prepare(
        "SELECT id, date_rdv AS date, heure_debut AS heure, type, lieu, description, statut, 'rdv' AS source
         FROM {$wpdb->prefix}lfi_nct_rdv
         WHERE tenant_user_id = %d AND date_rdv < %s AND statut NOT IN ('annule')
         ORDER BY date_rdv DESC LIMIT 10",
        $user->ID, current_time('Y-m-d')
    )) ?: [];

    $types_lbl = lfi_nct_agenda_types();
    $statuts_lbl = lfi_nct_agenda_statuts();

    lfi_nct_app_screen_open('📅 Mes rendez-vous', 'Avec le Groupe d\'Action LFI');

    echo '<div class="lfi-app-help">Cet agenda contient uniquement <strong>vos rendez-vous personnels</strong> avec le Groupe d\'Action. Pour les événements publics, consultez le site.</div>';

    /* À venir */
    echo '<h3 style="margin:16px 0 8px">⏳ À venir (' . count($items) . ')</h3>';
    if (empty($items)) {
        echo '<div class="lfi-app-empty">Aucun rendez-vous prévu pour l\'instant. Le GA vous contactera prochainement.</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        $current_date = '';
        foreach ($items as $r) {
            if ($r->date !== $current_date) {
                $current_date = $r->date;
                $when = wp_date('l j F Y', strtotime($r->date));
                echo '<li class="lfi-agenda-day">' . esc_html(ucfirst($when)) . '</li>';
            }
            $statut = $statuts_lbl[$r->statut] ?? ['?', $r->statut, '#888'];
            $type_lbl = $types_lbl[$r->type] ?? $r->type;

            echo '<li class="lfi-app-card">';
            echo '<div class="head"><div class="who">';
            if ($r->heure) echo '<strong>' . esc_html(substr($r->heure, 0, 5)) . '</strong> · ';
            echo esc_html($type_lbl);
            echo '</div>';
            echo '<div class="badge" style="background:' . esc_attr($statut[2]) . ';color:#fff">' . $statut[0] . ' ' . esc_html($statut[1]) . '</div>';
            echo '</div>';
            if ($r->lieu) echo '<div class="meta"><span class="meta-chip">📍 ' . esc_html($r->lieu) . '</span></div>';
            if ($r->description) echo '<div class="com">' . esc_html($r->description) . '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /* Passé */
    if (!empty($past)) {
        echo '<h3 style="margin:18px 0 8px">📋 Rendez-vous passés (' . count($past) . ')</h3>';
        echo '<ul class="lfi-app-list">';
        foreach ($past as $r) {
            $type_lbl = $types_lbl[$r->type] ?? $r->type;
            echo '<li class="lfi-app-card" style="opacity:.85">';
            echo '<div class="head"><div class="who">' . esc_html(wp_date('j M Y', strtotime($r->date))) . ' · ' . esc_html($type_lbl) . '</div>';
            echo '<div class="badge">✓ Passé</div></div>';
            if ($r->description) echo '<div class="com">' . esc_html(mb_substr($r->description, 0, 100)) . '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }

    lfi_nct_app_screen_close(false);
}
