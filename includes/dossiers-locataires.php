<?php
/**
 * Module Dossiers juridiques locataires
 *
 * Pour chaque locataire qui le demande, on monte un dossier complet :
 *
 *   - Constatations détaillées du membre du GA (date, durée, observations)
 *   - Certificat médical éventuel (médecin, pathologie, lien humidité/santé)
 *   - Demandes formulées (travaux urgents, relogement d'urgence, expertise…)
 *
 * À partir de quoi l'app génère, prêtes à imprimer en LRAR, 4 lettres types :
 *
 *   1. Mise en demeure travaux urgents (NMH)
 *   2. Demande de relogement d'urgence pour raison médicale (NMH)
 *   3. Saisine SCHS Nantes (insalubrité)
 *   4. Saisine ARS Pays de la Loire (risque sanitaire)
 *
 * STRICTEMENT PER-USER : chaque membre du GA voit uniquement les dossiers
 * qu'il a montés (owner_user_id, jamais partagé).
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_DOSSIER_DBVER_KEY = 'lfi_nct_dossier_db_ver';
const LFI_NCT_DOSSIER_DBVER_VAL = '3';

/* ============================================================== *
 *  DB Setup                                                        *
 * ============================================================== */
add_action('init', 'lfi_nct_dossier_db_setup', 8);
function lfi_nct_dossier_db_setup() {
    if (get_option(LFI_NCT_DOSSIER_DBVER_KEY) === LFI_NCT_DOSSIER_DBVER_VAL) return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
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
        tenant_email VARCHAR(150) DEFAULT '',
        visite_date DATE DEFAULT NULL,
        visite_duree VARCHAR(40) DEFAULT '',
        constatations TEXT,
        certificat_medecin VARCHAR(200) DEFAULT '',
        certificat_pathologie TEXT,
        certificat_date DATE DEFAULT NULL,
        demandes TEXT,
        visite_heures DECIMAL(5,2) DEFAULT 0,
        facture_intervention_id BIGINT UNSIGNED DEFAULT NULL,
        statut VARCHAR(20) DEFAULT 'ouvert',
        lrar_travaux_date DATE DEFAULT NULL,
        lrar_relogement_date DATE DEFAULT NULL,
        schs_date DATE DEFAULT NULL,
        ars_date DATE DEFAULT NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        nmh_urgence VARCHAR(20) DEFAULT '',
        PRIMARY KEY (id),
        KEY owner_user_id (owner_user_id),
        KEY tenant_user_id (tenant_user_id)
    ) $charset;");

    /* Chronomètre NMH : niveau d'urgence qui fixe le délai légal après la mise
       en demeure (urgent 8j / bailleur 1 mois / autre 2 mois). Ajout explicite. */
    $t2 = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    if (!$wpdb->get_var("SHOW COLUMNS FROM $t2 LIKE 'nmh_urgence'")) {
        $wpdb->query("ALTER TABLE $t2 ADD COLUMN nmh_urgence VARCHAR(20) DEFAULT ''");
    }

    update_option(LFI_NCT_DOSSIER_DBVER_KEY, LFI_NCT_DOSSIER_DBVER_VAL, false);
}

/* ============================================================== *
 *  Helpers                                                         *
 * ============================================================== */
function lfi_nct_dossier_owner_id() {
    return function_exists('lfi_nct_brigade_owner_id') ? lfi_nct_brigade_owner_id() : (int) get_current_user_id();
}

/* ============================================================== *
 *  Dropdown locataires existants — affiché en haut du formulaire    *
 *                                                                   *
 *  Permet de PICK un locataire déjà enregistré et de remplir auto   *
 *  toutes ses infos (adresse, étage, tel, email, problème enquête). *
 * ============================================================== */
function lfi_nct_dossier_pick_tenant($r) {
    if (!defined('LFI_NCT_ROLE_TENANT')) return;
    global $wpdb;

    $tenants = get_users([
        'role' => LFI_NCT_ROLE_TENANT,
        'fields' => ['ID', 'display_name', 'user_login'],
        'number' => 500,
        'orderby' => 'display_name',
        'order' => 'ASC',
    ]);
    if (empty($tenants)) return;

    /* Construit l'URL de rechargement avec ?tenant_uid=X pour utiliser
       le pré-remplissage déjà existant dans le formulaire. */
    $base_url = lfi_nct_app_url('dossier-juridique-add');
    $current_uid = (int) ($r->tenant_user_id ?? 0);

    echo '<div style="background:#fff;border:2px solid #c8102e;border-radius:10px;padding:12px 14px;margin:0 0 18px">';
    echo '<div style="font-weight:800;color:#c8102e;margin-bottom:6px">🔗 Lier à un compte locataire enregistré</div>';
    echo '<div style="font-size:.88em;color:#444;margin-bottom:8px">Sélectionne un locataire déjà recensé → nom, adresse, étage, téléphone, problème de l\'enquête se remplissent automatiquement.</div>';

    echo '<select onchange="if(this.value){window.location.href=this.value;}" style="width:100%;padding:10px;border:1.5px solid #ddd;border-radius:8px;font-size:1em;background:#fff">';
    echo '<option value="">— Saisie manuelle (locataire pas encore enregistré) —</option>';

    /* On groupe en deux optgroups : avec enquête (info riche) / sans */
    $with_enq = [];
    $without_enq = [];
    foreach ($tenants as $u) {
        $rid = (int) get_user_meta($u->ID, 'lfi_nct_response_id', true);
        if ($rid) $with_enq[]    = [$u, $rid];
        else      $without_enq[] = [$u, 0];
    }

    if (!empty($with_enq)) {
        echo '<optgroup label="✓ Avec enquête liée (infos complètes)">';
        foreach ($with_enq as $pair) {
            list($u, $rid) = $pair;
            $resp = $wpdb->get_row($wpdb->prepare("SELECT adresse, etage FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid));
            $label = $u->display_name ?: $u->user_login;
            if ($resp) {
                if ($resp->adresse) $label .= ' · ' . $resp->adresse;
                if ($resp->etage)   $label .= ' (ét. ' . $resp->etage . ')';
            }
            $url = add_query_arg('tenant_uid', $u->ID, $base_url);
            $sel = ($current_uid === (int) $u->ID) ? 'selected' : '';
            echo '<option value="' . esc_url($url) . '" ' . $sel . '>' . esc_html($label) . '</option>';
        }
        echo '</optgroup>';
    }
    if (!empty($without_enq)) {
        echo '<optgroup label="⚠ Sans enquête liée">';
        foreach ($without_enq as $pair) {
            list($u, $rid) = $pair;
            $label = $u->display_name ?: $u->user_login;
            $url = add_query_arg('tenant_uid', $u->ID, $base_url);
            $sel = ($current_uid === (int) $u->ID) ? 'selected' : '';
            echo '<option value="' . esc_url($url) . '" ' . $sel . '>' . esc_html($label) . '</option>';
        }
        echo '</optgroup>';
    }
    echo '</select>';

    /* Si déjà lié, affiche un récap visuel + lien vers le compte */
    if ($current_uid) {
        $u = get_userdata($current_uid);
        if ($u) {
            $tel = (string) get_user_meta($current_uid, 'lfi_nct_tel', true);
            $rid = (int) get_user_meta($current_uid, 'lfi_nct_response_id', true);
            $problem_html = '';
            if ($rid) {
                $resp = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid));
                if ($resp && function_exists('lfi_nct_app_enq_problem')) {
                    $p = lfi_nct_app_enq_problem($resp);
                    if ($p) {
                        $main = $p['main'];
                        $problem_html = '<span style="background:#fff3f5;color:#a30b25;padding:3px 8px;border-radius:4px;font-size:.85em">' . $main[0] . ' ' . esc_html($main[1]);
                        if ($p['gravite']) $problem_html .= ' · ' . (int) $p['gravite'] . '/10';
                        $problem_html .= '</span>';
                    }
                }
            }

            echo '<div style="margin-top:10px;padding:10px;background:#e8f5ea;border-radius:8px;font-size:.92em;line-height:1.5">';
            echo '✅ <strong>Lié au compte : ' . esc_html($u->display_name ?: $u->user_login) . '</strong> ';
            if ($problem_html) echo $problem_html;
            echo '<div style="margin-top:6px;color:#444;font-size:.9em">';
            if ($u->user_email) echo '📧 ' . esc_html($u->user_email) . ' &nbsp; ';
            if ($tel)            echo '📞 ' . esc_html($tel);
            echo '</div>';
            echo '</div>';
        }
    }

    echo '</div>';
}

function lfi_nct_dossier_get($id) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $owner = (int) lfi_nct_dossier_owner_id();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d AND owner_user_id = %d", (int) $id, $owner));
}

/* ============================================================== *
 *  Trouve le dossier juridique EXISTANT d'un locataire             *
 *                                                                  *
 *  Matching robuste (identique au suivi) : tenant_user_id OU nom   *
 *  OU adresse canonique. Renvoie la ligne la plus récente, ou null *
 *  si aucun dossier n'existe encore pour ce locataire.             *
 * ============================================================== */
function lfi_nct_dossier_find_for_tenant($uid) {
    global $wpdb;
    $uid = (int) $uid;
    if (!$uid) return null;
    $u = get_userdata($uid);
    if (!$u) return null;

    $owner = (int) lfi_nct_dossier_owner_id();
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';

    /* 1) Cas le plus simple et le plus fiable : lien direct par compte. */
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $t WHERE owner_user_id = %d AND tenant_user_id = %d ORDER BY updated_at DESC LIMIT 1",
        $owner, $uid
    ));
    if ($row) return $row;

    /* 2) Fallback : dossier saisi à la main (pas encore lié au compte).
          On matche par nom OU adresse canonique, comme le suivi. */
    $nom = trim($u->last_name . ' ' . $u->first_name);
    if ($nom === '') $nom = (string) $u->display_name;
    $adr = '';
    $rid = (int) get_user_meta($uid, 'lfi_nct_response_id', true);
    if ($rid) {
        $resp = $wpdb->get_row($wpdb->prepare("SELECT adresse FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid));
        if ($resp) $adr = (string) $resp->adresse;
    }
    $adr_key = ($adr && function_exists('lfi_nct_address_canonical_key')) ? lfi_nct_address_canonical_key($adr) : '';

    $all = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $t WHERE owner_user_id = %d ORDER BY updated_at DESC LIMIT 300", $owner
    )) ?: [];
    $na = strtolower(trim(preg_replace('/\s+/', ' ', $nom)));
    foreach ($all as $d) {
        $d_nom = trim($d->tenant_prenom . ' ' . $d->tenant_nom);
        $nb = strtolower(trim(preg_replace('/\s+/', ' ', $d_nom)));
        if ($na !== '' && $nb !== '') {
            if ($na === $nb) return $d;
            if (strlen($na) >= 4 && (strpos($nb, $na) !== false || strpos($na, $nb) !== false)) return $d;
        }
        if ($adr_key && function_exists('lfi_nct_address_canonical_key')) {
            if (lfi_nct_address_canonical_key($d->tenant_adresse) === $adr_key) return $d;
        }
    }
    return null;
}

/* URL « intelligente » du dossier juridique d'un locataire :
   - si un dossier existe déjà → on l'OUVRE (édition / lettres) ;
   - sinon → on ouvre le formulaire de création pré-rempli.
   C'est cette URL qu'il faut utiliser sur tous les boutons « Dossier
   juridique » des fiches locataires, pour ne plus jamais recréer un
   dossier en double ni tomber sur une page vide. */
function lfi_nct_dossier_url_for_tenant($uid, $extra = []) {
    $uid = (int) $uid;
    $existing = $uid ? lfi_nct_dossier_find_for_tenant($uid) : null;
    if ($existing) {
        return lfi_nct_app_url('dossier-juridique-edit', array_merge(['id' => (int) $existing->id], $extra));
    }
    $args = $uid ? array_merge(['tenant_uid' => $uid], $extra) : $extra;
    return lfi_nct_app_url('dossier-juridique-add', $args);
}

/* Tarif horaire pour une visite/constat (= tarif brigade par défaut) */
function lfi_nct_visite_tarif_horaire() {
    return function_exists('lfi_nct_fact_tarif_defaut') ? lfi_nct_fact_tarif_defaut() : 40.00;
}

/* Crée une intervention facturable pour le TEMPS DE VISITE (constat).
   Facturé à l'heure (tarif brigade). Retourne l'ID intervention ou null. */
function lfi_nct_dossier_creer_facture_visite($dossier, $heures, $owner) {
    global $wpdb;
    if ($heures <= 0) return null;
    $tarif = lfi_nct_visite_tarif_horaire();
    $total = round($heures * $tarif, 2);
    $ti = $wpdb->prefix . 'lfi_nct_interventions';
    $h_lbl = rtrim(rtrim(number_format($heures, 2, ',', ' '), '0'), ',');
    $wpdb->insert($ti, [
        'owner_user_id' => $owner,
        'tenant_user_id'=> $dossier->tenant_user_id ?: null,
        'tenant_prenom' => $dossier->tenant_prenom ?: '',
        'tenant_nom'    => $dossier->tenant_nom ?: '',
        'tenant_adresse'=> $dossier->tenant_adresse ?: '',
        'tenant_etage'  => $dossier->tenant_etage ?: '',
        'tenant_appartement' => $dossier->tenant_appartement ?: '',
        'bailleur'      => 'Nantes Métropole Habitat',
        'date_intervention' => $dossier->visite_date ?: current_time('Y-m-d'),
        'type_travaux'  => 'Constat et rapport de visite du logement',
        'type_travaux_key' => '',
        'description'   => 'Visite contradictoire du logement, constat détaillé des désordres et rédaction du rapport de visite (dossier juridique #' . $dossier->id . '). Durée : ' . $h_lbl . ' h.',
        'tarif_mode'    => 'horaire',
        'prix_tache'    => 0,
        'duree_heures'  => $heures,
        'tarif_horaire' => $tarif,
        'cout_materiaux'=> 0,
        'total_ht'      => $total,
        'statut'        => 'realise',
        'notes'         => 'Généré automatiquement depuis le dossier juridique #' . $dossier->id . ' (visite facturée ' . number_format($tarif, 2, ',', ' ') . ' €/h).',
    ]);
    return (int) $wpdb->insert_id;
}

/* Étiquettes lisibles pour les codes de demandes */
function lfi_nct_dossier_demandes_labels() {
    return [
        'travaux_urgents'    => 'Travaux d\'urgence pour mettre fin aux désordres',
        'relogement_urgent'  => 'Relogement d\'urgence (raison médicale ou sanitaire)',
        'expertise'          => 'Expertise contradictoire du logement',
        'indemnisation'      => 'Indemnisation du trouble de jouissance',
        'reduction_loyer'    => 'Réduction de loyer (logement non décent)',
    ];
}

/* ============================================================== *
 *  VUE : Liste des dossiers                                        *
 * ============================================================== */
function lfi_nct_app_view_dossiers_juridiques() {
    if (!lfi_nct_app_guard_brigade()) return;
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $owner = (int) lfi_nct_dossier_owner_id();

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $t WHERE owner_user_id = %d ORDER BY updated_at DESC LIMIT 200", $owner
    )) ?: [];

    lfi_nct_app_screen_open('📁 Dossiers juridiques locataires', count($rows) . ' dossier(s)');

    echo '<div class="lfi-app-help">';
    echo '<strong>Un dossier juridique par locataire qui le demande.</strong> Tu y consignes tes constats de visite, le certificat médical éventuel, et tu choisis les demandes (travaux urgents, relogement médical, etc.). L\'app génère pour toi les 4 lettres types — mise en demeure NMH, demande de relogement, saisine SCHS, saisine ARS — prêtes à imprimer en LRAR.';
    echo '</div>';

    echo '<div class="lfi-app-bulk-row">';
    echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dossier-juridique-add')) . '">+ Nouveau dossier locataire</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-synthese')) . '">📂 Fiches de synthèse (gérées par Claude)</a>';
    echo '</div>';

    if (empty($rows)) {
        echo '<div class="lfi-app-empty">Aucun dossier ouvert pour le moment.</div>';
        lfi_nct_app_screen_close();
        return;
    }

    $statuts = [
        'ouvert'    => ['📂', 'Ouvert',        '#bd8600'],
        'envoyes'   => ['📨', 'Courriers envoyés', '#c8102e'],
        'abouti'    => ['✓',  'Abouti',        '#186a3b'],
        'abandonne' => ['✕',  'Abandonné',     '#777'],
    ];

    echo '<ul class="lfi-app-list">';
    foreach ($rows as $r) {
        $name = trim($r->tenant_prenom . ' ' . $r->tenant_nom) ?: '(anonyme)';
        $lbl = $statuts[$r->statut] ?? ['?', $r->statut, '#888'];
        $demandes = json_decode($r->demandes ?? '[]', true);
        if (!is_array($demandes)) $demandes = [];

        echo '<li class="lfi-app-card">';
        echo '<div class="head"><div class="who">📁 ' . esc_html($name) . '</div>';
        echo '<div class="badge" style="background:' . esc_attr($lbl[2]) . ';color:#fff">' . $lbl[0] . ' ' . esc_html($lbl[1]) . '</div>';
        echo '</div>';
        echo '<div class="meta">';
        if ($r->tenant_adresse) echo '<span class="meta-chip">📍 ' . esc_html(trim($r->tenant_adresse . ($r->tenant_etage ? ' · ét. ' . $r->tenant_etage : ''))) . '</span>';
        if ($r->visite_date)     echo '<span class="meta-chip">🗓 Visite ' . esc_html(wp_date('j M Y', strtotime($r->visite_date))) . '</span>';
        if (in_array('relogement_urgent', $demandes, true)) echo '<span class="meta-chip" style="background:#fff3f5;color:#a30b25">🏥 Relogement urgent</span>';
        if (in_array('travaux_urgents', $demandes, true))   echo '<span class="meta-chip" style="background:#fff8e6;color:#bd8600">🔧 Travaux urgents</span>';
        if ($r->lrar_travaux_date)   echo '<span class="meta-chip">📨 LRAR travaux : ' . esc_html(wp_date('j M', strtotime($r->lrar_travaux_date))) . '</span>';
        if ($r->lrar_relogement_date) echo '<span class="meta-chip">📨 LRAR relogement : ' . esc_html(wp_date('j M', strtotime($r->lrar_relogement_date))) . '</span>';
        if ($r->schs_date)            echo '<span class="meta-chip">🏥 SCHS : ' . esc_html(wp_date('j M', strtotime($r->schs_date))) . '</span>';
        if ($r->ars_date)             echo '<span class="meta-chip">🏛 ARS : ' . esc_html(wp_date('j M', strtotime($r->ars_date))) . '</span>';
        echo '</div>';
        echo '<div class="row-actions">';
        echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dossier-juridique-edit', ['id' => $r->id])) . '">📂 Ouvrir / Générer lettres</a>';
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';

    /* === ANNUAIRE : accès direct au dossier de CHAQUE locataire enregistré === */
    lfi_nct_dossiers_render_tenant_directory();

    lfi_nct_app_screen_close();
}

/* Liste tous les locataires enregistrés avec un accès direct à leur dossier
   juridique (ouvre l'existant ou en démarre un). Permet de retrouver le
   dossier de n'importe quel locataire — Mme Fadiga comprise — en un clic. */
function lfi_nct_dossiers_render_tenant_directory() {
    if (!defined('LFI_NCT_ROLE_TENANT')) return;
    $tenants = get_users([
        'role'    => LFI_NCT_ROLE_TENANT,
        'fields'  => ['ID', 'display_name', 'user_login', 'user_email'],
        'number'  => 500,
        'orderby' => 'display_name',
        'order'   => 'ASC',
    ]);
    if (empty($tenants)) return;

    echo '<h3 style="margin:26px 0 6px;color:#c8102e">👥 Tous les locataires enregistrés</h3>';
    echo '<div class="lfi-app-help">Accède au dossier juridique de n\'importe quel locataire. Si un dossier existe déjà, le bouton l\'ouvre ; sinon il en démarre un, pré-rempli.</div>';
    echo '<ul class="lfi-app-list">';
    foreach ($tenants as $u) {
        $existing = lfi_nct_dossier_find_for_tenant($u->ID);
        $name = $u->display_name ?: $u->user_login;
        echo '<li class="lfi-app-card">';
        echo '<div class="head"><div class="who">👤 ' . esc_html($name) . '</div>';
        if ($existing) {
            echo '<div class="badge" style="background:#186a3b;color:#fff">✓ Dossier #' . (int) $existing->id . '</div>';
        } else {
            echo '<div class="badge" style="background:#eee;color:#777">aucun dossier</div>';
        }
        echo '</div>';
        echo '<div class="row-actions">';
        if ($existing) {
            echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dossier-juridique-edit', ['id' => (int) $existing->id])) . '">📂 Ouvrir le dossier</a>';
        } else {
            echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-juridique-add', ['tenant_uid' => $u->ID])) . '">+ Démarrer un dossier</a>';
        }
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => $u->ID])) . '">📁 Profil complet</a>';
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';
}

/* ============================================================== *
 *  Formulaire création / édition                                   *
 * ============================================================== */
function lfi_nct_app_view_dossier_juridique_add() {
    if (!lfi_nct_app_guard_brigade()) return;
    lfi_nct_app_dossier_juridique_form(null);
}
function lfi_nct_app_view_dossier_juridique_edit() {
    if (!lfi_nct_app_guard_brigade()) return;
    $row = lfi_nct_dossier_get((int) ($_GET['id'] ?? 0));
    if (!$row) {
        lfi_nct_app_screen_open('Dossier introuvable');
        echo '<div class="lfi-app-empty"><a href="' . esc_url(lfi_nct_app_url('dossiers-juridiques')) . '">← Retour</a></div>';
        lfi_nct_app_screen_close(false);
        return;
    }
    lfi_nct_app_dossier_juridique_form($row);
}

/* ============================================================== *
 *  CHRONOMÈTRE NMH — délai légal + alerte + étape 4 (SCHS)         *
 * ============================================================== */
function lfi_nct_nmh_urgence_options() {
    return [
        'urgent'   => ['label' => 'Urgent — santé / sécurité (plus de chauffage ou d\'eau chaude, insalubrité, danger)', 'add' => '+8 days',   'court' => '8 jours'],
        'bailleur' => ['label' => 'Réparation à la charge du bailleur',                                                  'add' => '+1 month',  'court' => '1 mois'],
        'autre'    => ['label' => 'Autre situation',                                                                     'add' => '+2 months', 'court' => '2 mois'],
    ];
}
function lfi_nct_nmh_urgence_get($u) {
    $o = lfi_nct_nmh_urgence_options();
    return $o[$u] ?? $o['bailleur'];
}
/** Date limite = date d'envoi de la mise en demeure + délai selon l'urgence. */
function lfi_nct_nmh_deadline($courrier_date, $urgence) {
    if (empty($courrier_date)) return '';
    $u = lfi_nct_nmh_urgence_get($urgence);
    return wp_date('Y-m-d', strtotime($courrier_date . ' ' . $u['add']));
}
/* ============================================================== *
 *  NOTE POUR L'AVOCAT — document de présentation / demande de     *
 *  conseil, rempli automatiquement depuis les VRAIES données du   *
 *  dossier (aucune donnée inventée). Imprimable / PDF.            *
 * ============================================================== */
function lfi_nct_app_view_dossier_avocat() {
    if (!(function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'))) {
        wp_safe_redirect(lfi_nct_app_url('dossiers'));
        exit;
    }
    global $wpdb;
    $uid = (int) ($_GET['uid'] ?? 0);
    $u   = $uid ? get_userdata($uid) : null;
    $in_scope = !function_exists('lfi_nct_uid_in_scope') || lfi_nct_uid_in_scope($uid);
    if (!$u || !$in_scope || !in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) {
        echo '<div class="lfi-app"><div class="lfi-app-error">Locataire introuvable.</div></div>';
        return;
    }

    /* Données réelles du dossier. */
    $d = function_exists('lfi_nct_dossier_find_for_tenant') ? lfi_nct_dossier_find_for_tenant($uid) : null;
    $ph = function ($v) { $v = trim((string) $v); return $v !== '' ? esc_html($v) : '<span style="color:#b00">[à préciser]</span>'; };

    /* Identité + logement. */
    $prenom  = $d->tenant_prenom ?? '';
    $nom     = $d->tenant_nom ?? '';
    $name    = trim($prenom . ' ' . $nom);
    if ($name === '') $name = $u->display_name ?: $u->user_login;
    $adresse = $d->tenant_adresse ?? '';
    $etage   = $d->tenant_etage ?? '';
    $appt    = $d->tenant_appartement ?? '';
    $logement = trim($adresse . ($etage ? ' · étage ' . $etage : '') . ($appt ? ' · appt ' . $appt : ''));
    $bailleur = function_exists('lfi_nct_fact_bailleur') ? (lfi_nct_fact_bailleur()['nom'] ?? '') : '';

    /* Enquête liée → problèmes + gravité. */
    $rid = (int) get_user_meta($uid, 'lfi_nct_response_id', true);
    $response = $rid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid)) : null;
    if (!$logement && $response) $logement = trim(($response->adresse ?? '') . ($response->etage ? ' · étage ' . $response->etage : ''));
    $problem = ($response && function_exists('lfi_nct_app_enq_problem')) ? lfi_nct_app_enq_problem($response) : null;
    $prob_txt = '';
    if ($problem && !empty($problem['chips'])) {
        $names = array_map(function ($c) { return $c[1]; }, $problem['chips']);
        $prob_txt = implode(', ', $names);
    }
    $gravite = $problem ? (int) $problem['gravite'] : 0;
    $recurrent = $problem ? (string) $problem['recurrent'] : '';

    /* Chronomètre NMH (délais légaux). */
    $urg      = $d->nmh_urgence ?? 'bailleur';
    $u_opt    = function_exists('lfi_nct_nmh_urgence_get') ? lfi_nct_nmh_urgence_get($urg) : ['court' => ''];
    $sent     = $d->lrar_travaux_date ?? '';
    $deadline = ($sent && function_exists('lfi_nct_nmh_deadline')) ? lfi_nct_nmh_deadline($sent, $urg) : '';
    $today    = current_time('Y-m-d');
    $passed   = $deadline && ($today > $deadline) && empty($d->schs_date);

    /* Constatations / demandes / certificat. */
    $constat = $d->constatations ?? '';
    $demandes = $d->demandes ?? '';
    $cert_med = $d->certificat_medecin ?? '';
    $cert_dat = $d->certificat_date ?? '';

    /* Stats du quartier (cloisonnées au GA — PAS le national). */
    $sc = function_exists('lfi_nct_responses_scope_clause') ? lfi_nct_responses_scope_clause() : '';
    $qrows = $wpdb->get_results("SELECT adresse, data FROM {$wpdb->prefix}lfi_nct_responses WHERE deleted_at IS NULL" . $sc) ?: [];
    $q_total = count($qrows); $q_prob = 0; $q_addr = []; $q_gsum = 0; $q_gn = 0;
    foreach ($qrows as $qr) {
        $qd = json_decode((string) $qr->data, true); if (!is_array($qd)) $qd = [];
        if (($qd['problemes_presence'] ?? '') === 'oui') $q_prob++;
        $g = (int) ($qd['problemes_gravite'] ?? 0); if ($g > 0) { $q_gsum += $g; $q_gn++; }
        $a = trim((string) $qr->adresse);
        if ($a !== '' && function_exists('lfi_nct_address_canonical_key')) { $k = lfi_nct_address_canonical_key($a); if ($k !== '') $q_addr[$k] = 1; }
    }
    $q_pct  = $q_total ? round($q_prob * 100 / $q_total) : 0;
    $q_gavg = $q_gn ? round($q_gsum / $q_gn, 1) : 0;
    $q_imm  = count($q_addr);
    $ga_nom = function_exists('lfi_nct_ga_nom') && function_exists('lfi_nct_scope_ga_slug') ? lfi_nct_ga_nom(lfi_nct_scope_ga_slug()) : '';
    $asso   = function_exists('lfi_nct_ga_entete_nom') ? lfi_nct_ga_entete_nom() : 'Association Union des Quartiers Libres';

    /* ---------- Rendu ---------- */
    echo '<div class="lfi-app">';
    echo '<div class="lfi-noprint" style="max-width:720px;margin:0 auto 12px;display:flex;gap:8px;flex-wrap:wrap">';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => $uid])) . '">← Dossier</a>';
    echo '<button type="button" class="btn-primary" onclick="window.print()">🖨 Imprimer / PDF</button>';
    echo '</div>';

    echo '<div class="lfi-avocat" style="max-width:720px;margin:0 auto;background:#fff;border:1px solid #eee;border-radius:12px;padding:26px 30px;color:#1a1a1a;line-height:1.55">';
    echo '<div style="text-align:center;border-bottom:3px solid #c8102e;padding-bottom:12px;margin-bottom:16px">';
    echo '<h1 style="color:#c8102e;margin:0;font-size:1.4em">Note de présentation &amp; demande de conseil</h1>';
    echo '<div style="color:#666;margin-top:4px">' . esc_html($asso) . ($ga_nom ? ' — ' . esc_html($ga_nom) : '') . '</div>';
    echo '<div style="color:#999;font-size:.85em;margin-top:2px">Édité le ' . esc_html(wp_date('j F Y')) . '</div>';
    echo '</div>';

    echo '<h2 style="color:#c8102e;font-size:1.1em">1. L\'objet de ma venue</h2>';
    echo '<p>Je viens <strong>chercher votre conseil et votre orientation</strong>. Nous avons construit un dispositif pour repérer, documenter l\'habitat indigne et <strong>accompagner</strong> les locataires ; nous avons besoin de vous pour <strong>valider le cadre juridique</strong> et savoir <strong>ce que vous pouvez engager</strong>. Nous accompagnons ; nous ne représentons pas — c\'est votre rôle.</p>';

    echo '<h2 style="color:#c8102e;font-size:1.1em;margin-top:16px">2. Ce que nous avons déjà mis en place</h2>';
    echo '<ul>';
    echo '<li><strong>Enquête de terrain porte-à-porte</strong> : chaque logement recensé, le problème resitué dans l\'immeuble et le quartier.</li>';
    echo '<li><strong>Dossier juridique par locataire</strong> : constatations, demandes, pièces, historique.</li>';
    echo '<li><strong>Photos horodatées</strong> du logement (preuve datée).</li>';
    echo '<li><strong>Suivi des délais légaux</strong> : mise en demeure → délai selon l\'urgence → escalade SCHS/ARS.</li>';
    echo '<li><strong>Signalement préfecture anonyme par bâtiment</strong> (jamais transmis au bailleur).</li>';
    echo '<li><strong>Volet « travaux »</strong> pour organiser interventions/constats par la suite.</li>';
    echo '</ul>';

    echo '<h2 style="color:#c8102e;font-size:1.1em;margin-top:16px">3. Le cas — ' . esc_html($name) . '</h2>';
    echo '<ul>';
    echo '<li>Logement : ' . $ph($logement) . ($bailleur ? ' — bailleur : ' . esc_html($bailleur) : '') . '</li>';
    echo '<li>Problèmes signalés : ' . ($prob_txt !== '' ? esc_html($prob_txt) : $ph('')) . ($gravite ? ' — gravité déclarée <strong>' . (int) $gravite . '/10</strong>' : '') . ($recurrent === 'permanent' ? ' (en permanence)' : '') . '</li>';
    echo '<li>Santé : ' . ($cert_med ? 'certificat médical ' . esc_html($cert_med) . ($cert_dat ? ' du ' . esc_html(wp_date('j M Y', strtotime($cert_dat))) : '') : $ph('')) . '</li>';
    if ($constat)  echo '<li>Constatations : ' . esc_html($constat) . '</li>';
    if ($demandes) echo '<li>Ce que la personne demande : ' . esc_html($demandes) . '</li>';
    echo '<li>Démarches : ' . ($sent ? 'mise en demeure envoyée le <strong>' . esc_html(wp_date('j M Y', strtotime($sent))) . '</strong>, délai légal ' . esc_html($u_opt['court']) . ($deadline ? ', échéance le <strong>' . esc_html(wp_date('j M Y', strtotime($deadline))) . '</strong>' : '') . ($passed ? ' — <strong style="color:#b00">délai dépassé</strong>' : ' — en cours') : $ph('') . ' (mise en demeure non encore envoyée)') . '</li>';
    echo '</ul>';

    echo '<h2 style="color:#c8102e;font-size:1.1em;margin-top:16px">4. La vue d\'ensemble (quartier)</h2>';
    echo '<p><strong>' . (int) $q_total . '</strong> logements enquêtés' . ($ga_nom ? ' sur ' . esc_html($ga_nom) : '') . ', <strong>' . (int) $q_pct . ' %</strong> avec au moins un problème, <strong>' . (int) $q_imm . '</strong> immeubles concernés, gravité moyenne <strong>' . esc_html(str_replace('.', ',', (string) $q_gavg)) . '/10</strong>. <em>Ces chiffres montrent que ce n\'est pas un cas isolé.</em></p>';

    echo '<h2 style="color:#c8102e;font-size:1.1em;margin-top:16px">5. Le parcours d\'accompagnement (à valider avec vous)</h2>';
    echo '<ol>';
    echo '<li><strong>Adhésion à l\'association</strong> (gratuite) pour agir aux côtés de la personne — est-ce le bon montage (art. 63‑66 loi 71‑1130) ?</li>';
    echo '<li><strong>Accompagnement</strong> : courriers au bailleur, constats, orientation.</li>';
    echo '<li><strong>Travaux / brigade</strong> par la suite si le bailleur n\'agit pas — dans quel cadre légal ?</li>';
    echo '</ol>';

    echo '<h2 style="color:#c8102e;font-size:1.1em;margin-top:16px">6. Ce que nous attendons — du plus urgent au plus large</h2>';
    echo '<ul>';
    echo '<li><strong>🔴 Le plus urgent (' . esc_html($name) . ', santé/sécurité)</strong> : quelle voie la plus rapide pour la protéger, et que faire dès aujourd\'hui ?</li>';
    echo '<li><strong>🟠 En général (immeuble / quartier)</strong> : plusieurs logements touchés — peut-on mutualiser (action groupée) ?</li>';
    echo '<li><strong>🟡 Municipal (élus locaux)</strong> : comment articuler le plaidoyer auprès des élu·es avec l\'action juridique sans la fragiliser ?</li>';
    echo '</ul>';

    echo '<h2 style="color:#c8102e;font-size:1.1em;margin-top:16px">7. Nos questions — conseil &amp; orientation</h2>';
    echo '<ul>';
    echo '<li>Quelle <strong>stratégie</strong> conseillez-vous, et dans <strong>quel ordre</strong> ?</li>';
    echo '<li>Nos <strong>preuves</strong> sont-elles exploitables ? Que <strong>compléter / sécuriser</strong> ?</li>';
    echo '<li>Le <strong>montage association → adhésion → courriers → travaux</strong> est-il solide ?</li>';
    echo '<li>Que <strong>pouvez-vous engager</strong>, et de quoi avez-vous <strong>besoin de notre part</strong> ?</li>';
    echo '<li>Quelles démarches <strong>faire / surtout ne pas faire</strong> pour ne pas nuire au dossier ?</li>';
    echo '</ul>';

    echo '<p style="margin-top:16px">Merci de votre écoute et de vos conseils.</p>';
    echo '<div style="margin-top:14px;border-top:1px solid #eee;padding-top:8px;font-size:.8em;color:#999">Document interne d\'accompagnement — données du locataire strictement confidentielles, non transmises au bailleur.</div>';
    echo '</div>'; // .lfi-avocat
    echo '</div>'; // .lfi-app

    echo '<style>@media print{body *{visibility:hidden!important}.lfi-avocat,.lfi-avocat *{visibility:visible!important}.lfi-avocat{position:absolute;left:0;top:0;width:100%;border:0;border-radius:0}.lfi-noprint{display:none!important}.lfi-public-install,.lfi-app-emergency{display:none!important}}</style>';
}

/** Rendu du panneau chronomètre dans la fiche dossier (admins). */
function lfi_nct_nmh_render_chrono($dossier) {
    $urg     = $dossier->nmh_urgence ?: 'bailleur';
    $u       = lfi_nct_nmh_urgence_get($urg);
    $sent    = $dossier->lrar_travaux_date ?: '';
    $deadline = lfi_nct_nmh_deadline($sent, $urg);
    $today   = current_time('Y-m-d');

    echo '<div style="border:1.5px solid #c8102e;border-radius:12px;padding:14px;margin:12px 0;background:#fff">';
    echo '<div style="font-weight:800;color:#c8102e;margin-bottom:6px">⏱ Chronomètre NMH</div>';

    /* 1) Choix du délai (urgence) */
    echo '<form method="post" style="margin:0 0 10px">';
    wp_nonce_field('lfi_dossier_nmh_urgence');
    echo '<input type="hidden" name="lfi_dossier_nmh_urgence" value="1">';
    echo '<label style="font-size:.9em">Délai légal selon l\'urgence<select name="nmh_urgence" onchange="this.form.submit()">';
    foreach (lfi_nct_nmh_urgence_options() as $k => $o) {
        echo '<option value="' . esc_attr($k) . '" ' . selected($urg, $k, false) . '>' . esc_html($o['label']) . ' → ' . esc_html($o['court']) . '</option>';
    }
    echo '</select></label>';
    echo '</form>';

    if (!$sent) {
        echo '<div class="lfi-app-help" style="margin:0">Le chrono démarre quand tu <strong>envoies la mise en demeure</strong>. Génère le courrier, envoie-le, puis clique « ✅ Marquer la mise en demeure comme envoyée » (dans les étapes ci-dessous). Le délai (' . esc_html($u['court']) . ') courra à partir de cette date.</div>';
        echo '</div>';
        return;
    }

    $days_left = (int) floor((strtotime($deadline) - strtotime($today)) / 86400);
    echo '<div style="font-size:.95em;line-height:1.6">';
    echo '📨 Mise en demeure envoyée le <strong>' . esc_html(wp_date('j M Y', strtotime($sent))) . '</strong><br>';
    echo '⚖️ Délai : <strong>' . esc_html($u['court']) . '</strong> → date limite <strong>' . esc_html(wp_date('j M Y', strtotime($deadline))) . '</strong><br>';
    if ($days_left > 0) {
        echo '<span style="color:#186a3b;font-weight:700">⏳ Il reste ' . $days_left . ' jour(s).</span> On attend, sans débat.';
        echo '</div></div>';
        return;
    }
    /* Délai dépassé → étape 4 : SCHS */
    echo '<span style="color:#c8102e;font-weight:800">⏰ DÉLAI DÉPASSÉ' . ($days_left < 0 ? ' depuis ' . abs($days_left) . ' jour(s)' : ' aujourd\'hui') . '.</span><br>';
    echo '<strong>➡️ Étape 4 : saisir le SCHS (Service Communal d\'Hygiène et de Santé).</strong>';
    echo '</div>';
    echo '<div class="row-actions" style="margin-top:8px">';
    echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dossier-doc-schs', ['id' => $dossier->id])) . '">🏥 Générer la saisine SCHS</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-send-email', ['id' => $dossier->id, 'letter' => 'schs'])) . '">✉️ Envoyer au SCHS</a>';
    echo '</div>';
    echo '</div>';
}

function lfi_nct_app_dossier_juridique_form($row) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $is_edit = !empty($row);
    $owner = (int) lfi_nct_dossier_owner_id();

    /* Suppression rapide */
    if ($is_edit && !empty($_POST['lfi_dossier_delete']) && check_admin_referer('lfi_dossier_delete')) {
        $wpdb->delete($t, ['id' => $row->id, 'owner_user_id' => $owner]);
        wp_safe_redirect(lfi_nct_app_url('dossiers-juridiques', ['supprime' => 1]));
        exit;
    }

    /* Save */
    if (!empty($_POST['lfi_dossier_save']) && check_admin_referer('lfi_dossier_save')) {
        $demandes = array_keys(array_filter((array) ($_POST['demandes'] ?? [])));
        $data = [
            'tenant_user_id'       => ((int) ($_POST['tenant_user_id'] ?? 0)) ?: null,
            'tenant_prenom'        => sanitize_text_field(wp_unslash($_POST['tenant_prenom'] ?? '')),
            'tenant_nom'           => sanitize_text_field(wp_unslash($_POST['tenant_nom'] ?? '')),
            'tenant_adresse'       => sanitize_text_field(wp_unslash($_POST['tenant_adresse'] ?? '')),
            'tenant_etage'         => sanitize_text_field(wp_unslash($_POST['tenant_etage'] ?? '')),
            'tenant_appartement'   => sanitize_text_field(wp_unslash($_POST['tenant_appartement'] ?? '')),
            'tenant_tel'           => sanitize_text_field(wp_unslash($_POST['tenant_tel'] ?? '')),
            'tenant_email'         => sanitize_email(wp_unslash($_POST['tenant_email'] ?? '')),
            'visite_date'          => sanitize_text_field(wp_unslash($_POST['visite_date'] ?? '')) ?: null,
            'visite_duree'         => sanitize_text_field(wp_unslash($_POST['visite_duree'] ?? '')),
            'visite_heures'        => (float) ($_POST['visite_heures'] ?? 0),
            'constatations'        => sanitize_textarea_field(wp_unslash($_POST['constatations'] ?? '')),
            'certificat_medecin'   => sanitize_text_field(wp_unslash($_POST['certificat_medecin'] ?? '')),
            'certificat_pathologie'=> sanitize_textarea_field(wp_unslash($_POST['certificat_pathologie'] ?? '')),
            'certificat_date'      => sanitize_text_field(wp_unslash($_POST['certificat_date'] ?? '')) ?: null,
            'demandes'             => wp_json_encode($demandes),
            'statut'               => sanitize_key($_POST['statut'] ?? 'ouvert'),
        ];

        /* Notes : préserve les logs d'emails (envoyés/reçus) stockés en JSON
           dans le même champ. On ne réécrit QUE la partie texte libre. */
        $new_notes_txt = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
        if ($is_edit) {
            $existing = json_decode($row->notes ?? '', true);
            if (is_array($existing) && (isset($existing['email_log']) || isset($existing['email_recu']) || isset($existing['__notes']))) {
                $existing['__notes'] = $new_notes_txt;
                $data['notes'] = wp_json_encode($existing, JSON_UNESCAPED_UNICODE);
            } else {
                $data['notes'] = $new_notes_txt;
            }
        } else {
            $data['notes'] = $new_notes_txt;
        }

        if ($is_edit) {
            $wpdb->update($t, $data, ['id' => $row->id, 'owner_user_id' => $owner]);
            $saved_id = (int) $row->id;
        } else {
            $data['owner_user_id'] = $owner;
            $wpdb->insert($t, $data);
            $saved_id = (int) $wpdb->insert_id;
        }
        /* Enchaînement depuis une intervention planifiée : aller directement à
           l'email pré-rempli pour le service choisi. */
        $next_service = sanitize_key($_POST['next_service'] ?? '');
        if ($saved_id && in_array($next_service, ['schs', 'ars', 'lrar_travaux', 'lrar_relogement', 'reponse_nmh'], true)) {
            wp_safe_redirect(lfi_nct_app_url('dossier-send-email', ['id' => $saved_id, 'letter' => $next_service]));
            exit;
        }
        wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $saved_id, $is_edit ? 'saved' : 'created' => 1]));
        exit;
    }

    /* Marquer envoi LRAR/SCHS/ARS */
    foreach (['lrar_travaux', 'lrar_relogement', 'schs', 'ars'] as $etape) {
        if ($is_edit && !empty($_POST['lfi_dossier_mark_' . $etape]) && check_admin_referer('lfi_dossier_mark_' . $etape)) {
            $wpdb->update($t, [$etape . '_date' => current_time('Y-m-d')], ['id' => $row->id, 'owner_user_id' => $owner]);
            wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $row->id, 'marked' => $etape]));
            exit;
        }
    }

    /* Chronomètre NMH : choix du niveau d'urgence (= délai légal après la mise
       en demeure). Le chrono démarre à la date d'envoi de la mise en demeure. */
    if ($is_edit && !empty($_POST['lfi_dossier_nmh_urgence']) && check_admin_referer('lfi_dossier_nmh_urgence')) {
        $urg = sanitize_key($_POST['nmh_urgence'] ?? 'bailleur');
        if (!array_key_exists($urg, lfi_nct_nmh_urgence_options())) $urg = 'bailleur';
        $wpdb->update($t, ['nmh_urgence' => $urg], ['id' => $row->id, 'owner_user_id' => $owner]);
        wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $row->id, 'nmh_set' => 1]));
        exit;
    }

    /* Enregistrer un email REÇU (réponse de NMH / M. Morineau…) dans le dossier */
    if ($is_edit && !empty($_POST['lfi_dossier_email_recu']) && check_admin_referer('lfi_dossier_email_recu')) {
        $de    = sanitize_text_field(wp_unslash($_POST['email_de'] ?? ''));
        $objet = sanitize_text_field(wp_unslash($_POST['email_objet'] ?? ''));
        $corps = sanitize_textarea_field(wp_unslash($_POST['email_corps'] ?? ''));
        if ($corps !== '' || $objet !== '') {
            $logs = json_decode($row->notes ?? '', true);
            if (!is_array($logs)) $logs = ['__notes' => $row->notes ?? ''];
            $logs['email_recu'] = $logs['email_recu'] ?? [];
            $logs['email_recu'][] = [
                'de'    => $de,
                'objet' => $objet,
                'corps' => $corps,
                'date'  => current_time('Y-m-d H:i'),
            ];
            $wpdb->update($t, ['notes' => wp_json_encode($logs, JSON_UNESCAPED_UNICODE)], ['id' => $row->id, 'owner_user_id' => $owner]);
        }
        wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $row->id, 'email_recu_ok' => 1]));
        exit;
    }

    /* Supprimer une entrée de la correspondance (email envoyé OU reçu).
       Utile notamment pour retirer les faux « Envoyé » créés quand Gmail
       ne s'ouvrait pas et qu'on a cliqué plusieurs fois. */
    if ($is_edit && !empty($_POST['lfi_dossier_email_del']) && check_admin_referer('lfi_dossier_email_del')) {
        $sens = (($_POST['del_sens'] ?? '') === 'recu') ? 'recu' : 'envoye';
        $idx  = (int) ($_POST['del_idx'] ?? -1);
        $key  = ($sens === 'recu') ? 'email_recu' : 'email_log';
        $logs = json_decode($row->notes ?? '', true);
        if (is_array($logs) && isset($logs[$key]) && is_array($logs[$key]) && $idx >= 0 && isset($logs[$key][$idx])) {
            array_splice($logs[$key], $idx, 1);
            $wpdb->update($t, ['notes' => wp_json_encode($logs, JSON_UNESCAPED_UNICODE)], ['id' => $row->id, 'owner_user_id' => $owner]);
        }
        wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $row->id, 'email_del_ok' => 1]));
        exit;
    }

    /* Suppression MULTIPLE (cases à cocher) d'entrées de correspondance. */
    if ($is_edit && !empty($_POST['lfi_dossier_email_delmulti']) && check_admin_referer('lfi_dossier_email_del')) {
        $sel  = (array) ($_POST['del'] ?? []);
        $logs = json_decode($row->notes ?? '', true);
        if (is_array($logs) && $sel) {
            $rm = ['email_log' => [], 'email_recu' => []];
            foreach ($sel as $s) {
                list($sens, $idx) = array_pad(explode(':', (string) $s, 2), 2, '');
                $key = ($sens === 'recu') ? 'email_recu' : 'email_log';
                $rm[$key][] = (int) $idx;
            }
            foreach ($rm as $key => $idxs) {
                if (empty($idxs) || !isset($logs[$key]) || !is_array($logs[$key])) continue;
                rsort($idxs); // décroissant : les splices ne décalent pas les index suivants
                foreach ($idxs as $i) { if (isset($logs[$key][$i])) array_splice($logs[$key], $i, 1); }
                $logs[$key] = array_values($logs[$key]);
            }
            $wpdb->update($t, ['notes' => wp_json_encode($logs, JSON_UNESCAPED_UNICODE)], ['id' => $row->id, 'owner_user_id' => $owner]);
        }
        wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $row->id, 'email_del_ok' => 1]));
        exit;
    }

    /* Enregistrer / mettre à jour l'analyse juridique de la réponse NMH */
    if ($is_edit && isset($_POST['lfi_dossier_analyse_nmh']) && check_admin_referer('lfi_dossier_analyse_nmh')) {
        $analyse = sanitize_textarea_field(wp_unslash($_POST['analyse_nmh'] ?? ''));
        $logs = json_decode($row->notes ?? '', true);
        if (!is_array($logs)) $logs = ['__notes' => $row->notes ?? ''];
        if ($analyse === '') unset($logs['analyse_nmh']); else $logs['analyse_nmh'] = $analyse;
        $wpdb->update($t, ['notes' => wp_json_encode($logs, JSON_UNESCAPED_UNICODE)], ['id' => $row->id, 'owner_user_id' => $owner]);
        wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $row->id, 'analyse_ok' => 1]));
        exit;
    }

    /* Facturer la visite (temps de constat) — ANTI-DOUBLON */
    if ($is_edit && !empty($_POST['lfi_dossier_facturer_visite']) && check_admin_referer('lfi_dossier_facturer_visite')) {
        $heures = (float) ($_POST['visite_heures'] ?? 0);
        /* Garde-fou anti-doublon : si déjà facturé, on n'en recrée pas. */
        $deja = (int) ($row->facture_intervention_id ?? 0);
        $exists = $deja ? $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}lfi_nct_interventions WHERE id = %d", $deja)) : 0;
        if ($exists) {
            wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $row->id, 'deja_facture' => 1]));
            exit;
        }
        if ($heures > 0) {
            $iid = lfi_nct_dossier_creer_facture_visite($row, $heures, $owner);
            if ($iid) {
                $wpdb->update($t, ['visite_heures' => $heures, 'facture_intervention_id' => $iid], ['id' => $row->id, 'owner_user_id' => $owner]);
                wp_safe_redirect(lfi_nct_app_url('intervention-edit', ['id' => $iid, 'billed' => 1]));
                exit;
            }
        }
        wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $row->id]));
        exit;
    }

    /* Valeurs par défaut COMPLÈTES — garantissent que tous les champs
       existent toujours sur l'objet, même en création pré-remplie. Évite
       les propriétés indéfinies (cause possible de page blanche). */
    $defaults = [
        'id'=>0, 'tenant_user_id'=>'', 'tenant_prenom'=>'', 'tenant_nom'=>'', 'tenant_adresse'=>'',
        'tenant_etage'=>'', 'tenant_appartement'=>'', 'tenant_tel'=>'', 'tenant_email'=>'',
        'visite_date'=>current_time('Y-m-d'), 'visite_duree'=>'', 'visite_heures'=>0,
        'constatations'=>'', 'certificat_medecin'=>'',
        'certificat_pathologie'=>'', 'certificat_date'=>'',
        'demandes'=>'[]', 'statut'=>'ouvert', 'notes'=>'',
        'lrar_travaux_date'=>null, 'lrar_relogement_date'=>null, 'schs_date'=>null, 'ars_date'=>null,
        'facture_intervention_id'=>0,
    ];

    /* Pré-remplissage depuis paramètres URL (raccourci depuis autre fiche) */
    $prefill = [];
    if (!$is_edit) {
        foreach (['tenant_prenom', 'tenant_nom', 'tenant_adresse', 'tenant_etage', 'tenant_appartement', 'tenant_tel'] as $f) {
            if (!empty($_GET[$f])) $prefill[$f] = sanitize_text_field(wp_unslash($_GET[$f]));
        }
    }

    /* Pré-remplissage si nouveau + tenant_uid */
    if (!$is_edit && !empty($_GET['tenant_uid'])) {
        $tuid = (int) $_GET['tenant_uid'];
        $u = get_userdata($tuid);
        if ($u) {
            $prefill['tenant_user_id'] = $tuid;
            $prefill['tenant_prenom']  = $u->first_name ?: $u->display_name;
            $prefill['tenant_nom']     = $u->last_name;
            $prefill['tenant_email']   = $u->user_email;
            $tel = (string) get_user_meta($tuid, 'lfi_nct_tel', true);
            if ($tel !== '') $prefill['tenant_tel'] = $tel;
            $resp_id = (int) get_user_meta($tuid, 'lfi_nct_response_id', true);
            if ($resp_id) {
                $resp = $wpdb->get_row($wpdb->prepare("SELECT adresse, etage FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $resp_id));
                if ($resp) {
                    $prefill['tenant_adresse'] = $resp->adresse;
                    $prefill['tenant_etage']   = $resp->etage;
                }
            }
        }
    }

    /* Construit l'objet final : défauts + (ligne existante OU pré-remplissage). */
    $base = $is_edit && $row ? (array) $row : [];
    $r = (object) array_merge($defaults, $base, $prefill);

    $demandes_actives = json_decode($r->demandes ?? '[]', true);
    if (!is_array($demandes_actives)) $demandes_actives = [];

    lfi_nct_app_screen_open($is_edit ? '📁 Dossier #' . $row->id : '+ Nouveau dossier juridique', 'Constatations · certificat · demandes');

    /* Navigation claire entre dossier juridique et profil locataire */
    echo '<div style="margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap">';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossiers-juridiques')) . '">← Tous les dossiers</a>';
    if ($is_edit && !empty($row->tenant_user_id)) {
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => (int) $row->tenant_user_id])) . '">📂 Profil complet du locataire</a>';
    }
    echo '</div>';

    /* Sommaire — accès rapide aux sections du dossier (réduit le « fouillis »). */
    echo '<div style="display:flex;gap:6px;flex-wrap:wrap;margin:0 0 14px;padding:10px 12px;background:#fafafa;border:1px solid #eee;border-radius:10px;font-size:.85em">';
    echo '<strong style="width:100%;color:#c8102e;margin-bottom:2px">Aller à :</strong>';
    $somm = [['#sec-locataire', '👤 Locataire'], ['#sec-constat', '🔍 Constats']];
    if ($is_edit) {
        $somm[] = ['#sec-factu',   '🧾 Facturation'];
        $somm[] = ['#sec-lettres', '📄 Lettres'];
        $somm[] = ['#sec-emails',  '📧 Emails'];
        $somm[] = ['#sec-analyse', '📑 Analyse'];
    }
    foreach ($somm as $s) {
        echo '<a href="' . esc_attr($s[0]) . '" style="text-decoration:none;background:#fff;border:1px solid #ddd;color:#333;padding:5px 10px;border-radius:14px">' . esc_html($s[1]) . '</a>';
    }
    echo '</div>';

    if (!empty($_GET['saved']))      lfi_nct_app_flash('✅ Dossier enregistré.');
    if (!empty($_GET['created']))    lfi_nct_app_flash('✅ Dossier créé. Tu peux maintenant générer les lettres.');
    if (!empty($_GET['marked']))     lfi_nct_app_flash('📨 Étape marquée comme envoyée (date du jour).');
    if (!empty($_GET['email_sent']))     lfi_nct_app_flash('📧 Email envoyé au nom du Groupe d\'Action LFI.');
    if (!empty($_GET['gmail_open']))     lfi_nct_app_flash('📨 Email consigné dans le dossier. Termine l\'envoi dans l\'onglet Gmail qui vient de s\'ouvrir.');
    if (!empty($_GET['email_recu_ok']))  lfi_nct_app_flash('📥 Email reçu enregistré dans le dossier.');
    if (!empty($_GET['email_del_ok']))   lfi_nct_app_flash('🗑 Entrée de correspondance supprimée.');
    if (!empty($_GET['analyse_ok']))     lfi_nct_app_flash('📑 Analyse enregistrée dans le dossier.');
    if (!empty($_GET['deja_facture'])) lfi_nct_app_flash('⚠ Cette visite est déjà facturée — pas de doublon créé.', 'err');
    if (!empty($_GET['nmh_set']))      lfi_nct_app_flash('⏱ Délai NMH mis à jour.');

    /* Chronomètre NMH (fiche existante) : délai légal + alerte + étape 4. */
    if ($is_edit) lfi_nct_nmh_render_chrono($row);

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_dossier_save');
    echo '<input type="hidden" name="lfi_dossier_save" value="1">';
    /* Enchaînement depuis une intervention planifiée : on conserve le service à prévenir. */
    if (!empty($_GET['next_service'])) {
        echo '<input type="hidden" name="next_service" value="' . esc_attr(sanitize_key($_GET['next_service'])) . '">';
        echo '<div class="lfi-app-help" style="background:#e8f0ff;border-left:4px solid #0066a3"><small>📧 Après création du dossier, tu seras redirigé vers l\'email pré-rempli pour le service choisi.</small></div>';
    }

    /* Champ caché qui PRÉSERVE le lien au compte locataire — bug fix
       majeur : sans ça, la liaison se perdait à la sauvegarde. */
    echo '<input type="hidden" name="tenant_user_id" value="' . esc_attr((int) ($r->tenant_user_id ?? 0)) . '">';

    /* === SÉLECTEUR DE COMPTE LOCATAIRE EXISTANT === */
    if (function_exists('lfi_nct_dossier_pick_tenant')) lfi_nct_dossier_pick_tenant($r);

    /* === LOCATAIRE === */
    echo '<h3 id="sec-locataire" style="margin:0">👤 Locataire <small style="color:#666;font-weight:400">(modifiable à tout moment)</small></h3>';
    echo '<div class="lfi-app-help" style="background:#e8f5ea;border-left:4px solid #186a3b"><small>💡 Tu peux compléter ces infos plus tard (étage, N° de porte, téléphone, etc.) au fur et à mesure que tu les découvres. Seul le <strong>nom OU prénom</strong> et l\'<strong>adresse</strong> sont obligatoires.</small></div>';

    /* Datalists pour autocomplétion */
    if (function_exists('lfi_nct_streets_datalist')) echo lfi_nct_streets_datalist('lfi-streets-dossier');
    echo '<datalist id="lfi-etages"><option value="RDC"><option value="1"><option value="2"><option value="3"><option value="4"><option value="5"><option value="6"><option value="7"><option value="8"><option value="9"><option value="10"><option value="11"><option value="12"></datalist>';

    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
    echo '<label>Prénom OU civilité <small style="color:#888">(facultatif)</small><input type="text" name="tenant_prenom" value="' . esc_attr($r->tenant_prenom) . '" placeholder="ex: Mme"></label>';
    echo '<label>Nom<input type="text" name="tenant_nom" value="' . esc_attr($r->tenant_nom) . '" placeholder="ex: Fadila"></label>';
    echo '</div>';

    echo '<label>Adresse complète <span style="color:#c8102e">*</span><input type="text" name="tenant_adresse" id="lfi-adr-dossier" list="lfi-streets-dossier" value="' . esc_attr($r->tenant_adresse) . '" placeholder="8 rue de Saint-Jean-de-Luz, 44200 Nantes" required></label>';

    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
    echo '<label>Étage<input type="text" name="tenant_etage" list="lfi-etages" value="' . esc_attr($r->tenant_etage) . '" placeholder="ex: 8"></label>';
    echo '<label>N° porte / appartement<input type="text" name="tenant_appartement" value="' . esc_attr($r->tenant_appartement) . '" placeholder="ex: 130"></label>';
    echo '</div>';

    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
    echo '<label>Téléphone<input type="tel" name="tenant_tel" value="' . esc_attr($r->tenant_tel) . '"></label>';
    echo '<label>Email<input type="email" name="tenant_email" value="' . esc_attr($r->tenant_email) . '"></label>';
    echo '</div>';

    /* === VISITE / CONSTATATIONS === */
    echo '<h3 id="sec-constat" style="margin:18px 0 0">🔍 Constatations de visite</h3>';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">';
    echo '<label>Date de visite<input type="date" name="visite_date" value="' . esc_attr($r->visite_date) . '"></label>';
    echo '<label>Durée (texte)<input type="text" name="visite_duree" value="' . esc_attr($r->visite_duree) . '" placeholder="ex: 4 heures"></label>';
    echo '<label>Heures (à facturer)<input type="number" name="visite_heures" step="0.25" min="0" value="' . esc_attr($r->visite_heures ?? '') . '" placeholder="ex: 4"></label>';
    echo '</div>';
    $tarif_v = lfi_nct_visite_tarif_horaire();
    echo '<div class="lfi-app-help">💶 La visite est facturable à <strong>' . number_format($tarif_v, 2, ',', ' ') . ' €/h</strong> (temps de constat + rapport). Renseigne le nb d\'heures puis utilise le bouton « Facturer la visite » plus bas.</div>';
    echo '<label>Description détaillée des désordres observés<textarea name="constatations" id="lfi-constatations" rows="8" placeholder="Décris pièce par pièce : moisissures (couleur, surface, emplacement précis), fuites, infiltrations d\'air, humidité au toucher, taux ressenti, odeurs… Sois factuel et précis : ces constatations seront citées dans toutes les lettres.">' . esc_textarea($r->constatations) . '</textarea></label>';
    echo '<div class="lfi-voice-zone" data-target="lfi-constatations" data-label="Dicter mes constats sur place"></div>';

    /* === CERTIFICAT MÉDICAL === */
    echo '<h3 style="margin:18px 0 0">🏥 Certificat médical (si demande de relogement)</h3>';
    echo '<label>Médecin (titre + nom)<input type="text" name="certificat_medecin" value="' . esc_attr($r->certificat_medecin) . '" placeholder="Dr Aubeau, médecin généraliste, Nantes"></label>';
    echo '<label>Date du certificat<input type="date" name="certificat_date" value="' . esc_attr($r->certificat_date) . '"></label>';
    echo '<label>Pathologie constatée + lien avec l\'humidité<textarea name="certificat_pathologie" id="lfi-pathologie" rows="4" placeholder="Ex: Asthme sévère de la fille mineure (X ans), aggravation des crises depuis l\'emménagement. Le médecin certifie que la pathologie est probablement liée à l\'exposition prolongée à l\'humidité et aux moisissures, et préconise un relogement immédiat dans un logement sain.">' . esc_textarea($r->certificat_pathologie) . '</textarea></label>';
    echo '<div class="lfi-voice-zone" data-target="lfi-pathologie" data-label="Dicter la pathologie"></div>';
    echo '<div class="lfi-app-help"><small>📎 Pense à <strong>scanner le certificat</strong> et à le joindre aux LRAR. Ce champ ne sert qu\'à reprendre le contenu dans les lettres ; le certificat lui-même est ta pièce maîtresse.</small></div>';

    /* === DEMANDES === */
    echo '<h3 style="margin:18px 0 0">📋 Demandes à formuler</h3>';
    echo '<div style="display:grid;grid-template-columns:1fr;gap:6px;background:#fff;padding:10px;border-radius:8px">';
    foreach (lfi_nct_dossier_demandes_labels() as $k => $lbl) {
        $check = in_array($k, $demandes_actives, true) ? 'checked' : '';
        echo '<label style="display:flex;align-items:center;gap:8px;margin:0;padding:6px 0;cursor:pointer">';
        echo '<input type="checkbox" name="demandes[' . esc_attr($k) . ']" value="1" ' . $check . '>';
        echo '<span>' . esc_html($lbl) . '</span>';
        echo '</label>';
    }
    echo '</div>';

    /* === STATUT === */
    echo '<h3 style="margin:18px 0 0">📂 Statut + notes</h3>';
    echo '<label>Statut<select name="statut">';
    foreach (['ouvert' => '📂 Ouvert', 'envoyes' => '📨 Courriers envoyés', 'abouti' => '✓ Abouti', 'abandonne' => '✕ Abandonné'] as $k => $lbl) {
        echo '<option value="' . esc_attr($k) . '" ' . selected($r->statut, $k, false) . '>' . esc_html($lbl) . '</option>';
    }
    echo '</select></label>';
    if (($r->statut ?? '') === 'abouti') {
        echo '<div class="lfi-app-help" style="background:#e8f5ea;border-left:4px solid #186a3b">🏆 <strong>Dossier abouti !</strong> Tu peux en tirer une <strong>fiche réussite anonyme</strong> (méthode + leviers, sans aucun nom). <a class="btn-primary" style="margin-top:6px;display:inline-block" href="' . esc_url(lfi_nct_app_url('reussite-edit', ['from_dossier' => $r->id])) . '">Créer la fiche réussite</a></div>';
    }
    /* Notes : on ne montre PAS le JSON brut si des logs y sont stockés */
    $notes_raw = $r->notes ?? '';
    $notes_decoded = json_decode($notes_raw, true);
    $notes_display = (is_array($notes_decoded) && isset($notes_decoded['__notes'])) ? $notes_decoded['__notes'] : (is_array($notes_decoded) ? '' : $notes_raw);
    echo '<label>Notes internes (non publiées)<textarea name="notes" id="lfi-notes-dossier" rows="2">' . esc_textarea($notes_display) . '</textarea></label>';
    echo '<div class="lfi-voice-zone" data-target="lfi-notes-dossier" data-label="Dicter mes notes"></div>';

    echo '<button type="submit" class="btn-primary big">' . ($is_edit ? '💾 Enregistrer' : '+ Créer le dossier') . '</button>';
    echo '</form>';

    /* === REGROUPEMENT PAR LOCATAIRE — interventions + autres dossiers === */
    if ($is_edit && (!empty($r->tenant_nom) || !empty($r->tenant_adresse))) {
        global $wpdb;
        $owner = (int) lfi_nct_dossier_owner_id();
        $ti = $wpdb->prefix . 'lfi_nct_interventions';
        $td = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
        /* Match large : même nom OU même adresse (clé canonique) */
        $name_clause = $r->tenant_nom ? $wpdb->prepare('LOWER(tenant_nom) = LOWER(%s)', $r->tenant_nom) : '0';
        $adr_clause  = $r->tenant_adresse ? $wpdb->prepare('LOWER(tenant_adresse) = LOWER(%s)', $r->tenant_adresse) : '0';

        $other_interv = $wpdb->get_results($wpdb->prepare(
            "SELECT id, date_intervention, type_travaux, total_ht, statut FROM $ti
             WHERE owner_user_id = %d AND ($name_clause OR $adr_clause)
             ORDER BY date_intervention DESC LIMIT 20",
            $owner
        )) ?: [];

        $other_dossiers = $wpdb->get_results($wpdb->prepare(
            "SELECT id, visite_date, statut FROM $td
             WHERE owner_user_id = %d AND id != %d AND ($name_clause OR $adr_clause)
             ORDER BY updated_at DESC LIMIT 20",
            $owner, (int) $row->id
        )) ?: [];

        if ($other_interv || $other_dossiers) {
            echo '<h3 style="margin:24px 0 8px;color:#c8102e">🔗 Tout ce qui concerne ce locataire</h3>';
            echo '<div class="lfi-app-help">Toutes les interventions et autres dossiers que tu as déjà ouverts pour cette personne / cette adresse.</div>';

            if ($other_dossiers) {
                echo '<h4 style="margin:10px 0 4px">📁 Autres dossiers juridiques</h4>';
                echo '<ul class="lfi-app-list">';
                foreach ($other_dossiers as $d) {
                    echo '<li class="lfi-app-card">';
                    echo '<div class="head"><div class="who">📁 Dossier #' . (int) $d->id . '</div>';
                    echo '<div class="badge">' . esc_html($d->statut) . '</div></div>';
                    if ($d->visite_date) echo '<div class="meta"><span class="meta-chip">🗓 Visite ' . esc_html(wp_date('j M Y', strtotime($d->visite_date))) . '</span></div>';
                    echo '<div class="row-actions"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-juridique-edit', ['id' => $d->id])) . '">Ouvrir →</a></div>';
                    echo '</li>';
                }
                echo '</ul>';
            }

            if ($other_interv) {
                echo '<h4 style="margin:10px 0 4px">🔧 Interventions brigade</h4>';
                echo '<ul class="lfi-app-list">';
                foreach ($other_interv as $i) {
                    echo '<li class="lfi-app-card">';
                    echo '<div class="head"><div class="who">🔧 ' . esc_html($i->type_travaux ?: '(sans type)') . '</div>';
                    echo '<div class="badge">' . esc_html($i->statut) . '</div></div>';
                    echo '<div class="meta">';
                    if ($i->date_intervention) echo '<span class="meta-chip">🗓 ' . esc_html(wp_date('j M Y', strtotime($i->date_intervention))) . '</span>';
                    if ($i->total_ht > 0) echo '<span class="meta-chip">' . esc_html(number_format($i->total_ht, 2, ',', ' ')) . ' €</span>';
                    echo '</div>';
                    echo '<div class="row-actions"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('intervention-edit', ['id' => $i->id])) . '">Ouvrir →</a></div>';
                    echo '</li>';
                }
                echo '</ul>';
            }
        }

        /* Raccourcis création */
        echo '<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">';
        $shortcut_args = [
            'tenant_prenom' => $r->tenant_prenom,
            'tenant_nom'    => $r->tenant_nom,
            'tenant_adresse'=> $r->tenant_adresse,
            'tenant_etage'  => $r->tenant_etage,
            'tenant_appartement' => $r->tenant_appartement,
            'tenant_tel'    => $r->tenant_tel,
        ];
        if ($r->tenant_user_id) $shortcut_args['tenant_uid'] = $r->tenant_user_id;
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('intervention-add', $shortcut_args)) . '">+ Nouvelle intervention pour ce locataire</a>';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-juridique-add', $shortcut_args)) . '">+ Nouveau dossier pour ce locataire</a>';
        echo '</div>';
    }

    /* === RAPPORT DE VISITE + FACTURATION (après création) === */
    if ($is_edit) {
        echo '<h3 id="sec-factu" style="margin:24px 0 8px;color:#c8102e">📄 Rapport de visite & facturation</h3>';

        echo '<div class="lfi-app-list"><div class="lfi-app-card" style="border-left:4px solid #0066a3">';
        echo '<div class="head"><div class="who">📄 Rapport de visite (PDF)</div></div>';
        echo '<div class="com">Document officiel reprenant tes constatations, daté et signé, pour le dossier juridique. Imprimable / PDF.</div>';
        echo '<div class="row-actions">';
        echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dossier-doc-rapport-visite', ['id' => $row->id])) . '" target="_blank">📄 Ouvrir le rapport de visite</a>';
        echo '</div></div></div>';

        /* Bulletin d'adhésion association — la pièce qui légalise l'accompagnement */
        $asso = function_exists('lfi_nct_association') ? lfi_nct_association() : ['nom' => 'Union des quartiers libres'];
        echo '<div class="lfi-app-list"><div class="lfi-app-card" style="border-left:4px solid #7a0000">';
        echo '<div class="head"><div class="who">🎫 Bulletin d\'adhésion ' . esc_html($asso['nom']) . '</div></div>';
        echo '<div class="com">⚖️ <strong>Fais adhérer le locataire AVANT de l\'accompagner.</strong> C\'est ce qui rend légal l\'envoi de courriers en son nom par l\'association (art. 63-66 loi 71-1130). <strong>Adhésion gratuite.</strong></div>';
        echo '<div class="row-actions">';
        echo '<a class="btn-primary" style="background:#7a0000" href="' . esc_url(lfi_nct_app_url('dossier-doc-adhesion', ['id' => $row->id])) . '" target="_blank">🎫 Générer le bulletin d\'adhésion</a>';
        echo '</div></div></div>';

        /* Facturation de la visite — anti-doublon */
        $deja_iid = (int) ($row->facture_intervention_id ?? 0);
        $deja_ok = $deja_iid ? $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}lfi_nct_interventions WHERE id = %d", $deja_iid)) : 0;
        $tarif_v = lfi_nct_visite_tarif_horaire();
        $heures_v = (float) ($row->visite_heures ?? 0);

        echo '<div class="lfi-app-list"><div class="lfi-app-card" style="border-left:4px solid ' . ($deja_ok ? '#186a3b' : '#bd8600') . '">';
        echo '<div class="head"><div class="who">🧾 Facturer la visite</div>';
        if ($deja_ok) echo '<div class="badge" style="background:#186a3b;color:#fff">✓ Déjà facturée</div>';
        echo '</div>';
        if ($deja_ok) {
            echo '<div class="com">Cette visite a déjà été facturée (intervention #' . (int) $deja_iid . '). Pas de doublon possible.</div>';
            echo '<div class="row-actions"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('intervention-edit', ['id' => $deja_iid])) . '">Voir la facture →</a></div>';
        } else {
            $montant_v = $heures_v * $tarif_v;
            echo '<div class="com">Temps de constat + rédaction du rapport, facturé à ' . number_format($tarif_v, 2, ',', ' ') . ' €/h.</div>';
            echo '<div style="background:#fff8e6;border-left:4px solid #bd8600;padding:10px 12px;border-radius:6px;margin:8px 0;font-size:.85em;line-height:1.5">';
            echo '⚖️ <strong>Point juridique à connaître.</strong> Une <strong>visite seule</strong> facturée directement à NMH est contestable (« on n\'a rien commandé »). Deux façons de la rendre solide :<br>';
            echo '• <strong>L\'intégrer au devis des travaux</strong> (déplacement + diagnostic + réparation en une facture) — imparable ;<br>';
            echo '• OU la <strong>facturer au locataire</strong>, qui la réclamera ensuite à NMH comme préjudice au tribunal.<br>';
            echo 'La facture reste émise <strong>en ton nom d\'auto-entrepreneur</strong>, jamais au nom du GA. <a href="' . esc_url(lfi_nct_app_url('cadre-juridique')) . '">📖 Comprendre le cadre légal</a>.';
            echo '</div>';
            echo '<form method="post" class="lfi-app-form" style="margin-top:8px">';
            wp_nonce_field('lfi_dossier_facturer_visite');
            echo '<input type="hidden" name="lfi_dossier_facturer_visite" value="1">';
            echo '<div style="display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end">';
            echo '<label style="margin:0">Nombre d\'heures<input type="number" name="visite_heures" step="0.25" min="0" value="' . esc_attr($heures_v ?: '') . '" placeholder="ex: 4" required></label>';
            echo '<button type="submit" class="btn-primary">🧾 Facturer la visite</button>';
            echo '</div>';
            echo '<div class="lfi-app-help"><small>Ex : 4 h × ' . number_format($tarif_v, 0, ',', ' ') . ' € = ' . number_format(4 * $tarif_v, 0, ',', ' ') . ' €. La facture est créée dans la brigade, rattachée à ce locataire. Impossible de la créer deux fois.</small></div>';
            echo '</form>';
        }
        echo '</div></div>';

        echo '<h3 id="sec-lettres" style="margin:24px 0 8px;color:#c8102e">📄 Lettres à générer (LRAR)</h3>';
        echo '<div class="lfi-app-help">Clique sur une lettre pour l\'ouvrir (déjà pré-remplie avec tes constats). Bouton « Imprimer » en haut, puis envoi en recommandé avec accusé de réception.</div>';

        $lettres = [
            ['reponse_nmh',     '📨 RÉPONSE argumentée à un refus NMH',
             'Quand NMH esquive (« charge locative », « pas de signalement »…). Contre-argumente point par point + annonce SCHS/ARS.',
             'dossier-doc-reponse-nmh',     null],
            ['lrar_travaux',    '🔧 Mise en demeure — TRAVAUX URGENTS',
             'NMH doit réaliser les travaux sans délai. Cite art. 1719, 1724 CC + loi 89-462 + décret 2002-120.',
             'dossier-doc-lrar-travaux',    $row->lrar_travaux_date],
            ['lrar_relogement', '🏥 Demande de RELOGEMENT D\'URGENCE médicale',
             'NMH doit reloger immédiatement (certificat médical à l\'appui). Cite art. L.441-2-3 CCH + DALO + art. 1719 CC.',
             'dossier-doc-lrar-relogement', $row->lrar_relogement_date],
            ['schs',            '🏥 Saisine SCHS Nantes — insalubrité',
             'Service Communal d\'Hygiène et Santé : déclenche enquête + arrêté préfectoral si confirmé.',
             'dossier-doc-schs',            $row->schs_date],
        ];

        echo '<ul class="lfi-app-list">';
        foreach ($lettres as $L) {
            list($key, $titre, $desc, $route, $sent_date) = $L;
            $sent = !empty($sent_date);
            echo '<li class="lfi-app-card" style="border-left:4px solid ' . ($sent ? '#186a3b' : '#c8102e') . '">';
            echo '<div class="head"><div class="who">' . esc_html($titre) . '</div>';
            if ($sent) echo '<div class="badge" style="background:#186a3b;color:#fff">✓ Envoyée ' . esc_html(wp_date('j M Y', strtotime($sent_date))) . '</div>';
            echo '</div>';
            echo '<div class="com">' . esc_html($desc) . '</div>';
            echo '<div class="row-actions">';
            echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url($route, ['id' => $row->id])) . '" target="_blank">📄 Ouvrir / Imprimer</a>';
            echo '<a class="btn-primary" style="background:#0066a3" href="' . esc_url(lfi_nct_app_url('dossier-send-email', ['id' => $row->id, 'letter' => $key])) . '">📧 Envoyer par email</a>';
            if (!$sent) {
                echo '<form method="post" style="display:inline;margin:0">';
                wp_nonce_field('lfi_dossier_mark_' . $key);
                echo '<input type="hidden" name="lfi_dossier_mark_' . $key . '" value="1">';
                echo '<button type="submit" class="btn-ghost">📨 Marquer envoyée (LRAR papier)</button>';
                echo '</form>';
            }
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';

        /* === SUIVI DES EMAILS (envoyés + reçus) === */
        echo '<h3 id="sec-emails" style="margin:24px 0 8px;color:#c8102e">📧 Correspondance avec NMH</h3>';
        $logs = json_decode($row->notes ?? '', true);
        $sent = (is_array($logs) && !empty($logs['email_log'])) ? $logs['email_log'] : [];
        $recu = (is_array($logs) && !empty($logs['email_recu'])) ? $logs['email_recu'] : [];
        /* Fusion chronologique (on garde l'index source pour pouvoir supprimer) */
        $timeline = [];
        foreach ($sent as $i => $e) { $e['sens'] = 'envoye'; $e['_idx'] = $i; $timeline[] = $e; }
        foreach ($recu as $i => $e) { $e['sens'] = 'recu';   $e['_idx'] = $i; $timeline[] = $e; }
        usort($timeline, function ($a, $b) { return strcmp($a['date'] ?? '', $b['date'] ?? ''); });

        /* Relance : dernier message = un ENVOI sans réponse depuis 8 j+. */
        if (!empty($timeline)) {
            $last = end($timeline);
            if (($last['sens'] ?? '') === 'envoye') {
                $age = (int) floor(((int) current_time('timestamp') - strtotime($last['date'] ?? '')) / 86400);
                if ($age >= 8) {
                    $rl = sanitize_key($last['letter'] ?? '');
                    $url = lfi_nct_app_url('dossier-send-email', array_filter(['id' => $row->id, 'letter' => $rl, 'relance' => 1]));
                    echo '<div class="lfi-app-help" style="background:#fff3cd;border-left:4px solid #d39e00">⏰ <strong>Dernier courrier envoyé il y a ' . $age . ' jours, sans réponse.</strong>' . ($rl ? ' <a class="btn-primary" style="margin-top:6px;display:inline-block" href="' . esc_url($url) . '">Relancer</a>' : '') . '</div>';
                }
            }
        }

        if (empty($timeline)) {
            echo '<div class="lfi-app-help">Aucun email conservé pour l\'instant. Les emails que tu envoies (bouton « 📧 Envoyer par email ») sont archivés ici. Tu peux aussi coller un email REÇU ci-dessous.</div>';
        } else {
            /* Liste avec cases à cocher → suppression multiple en un clic. */
            echo '<form method="post" id="lfi-corr-form" onsubmit="return lfiNctCorrDel(this)">';
            wp_nonce_field('lfi_dossier_email_del');
            echo '<input type="hidden" name="lfi_dossier_email_delmulti" value="1">';
            echo '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:6px 0">';
            echo '<label style="display:flex;align-items:center;gap:6px;font-size:.9em;cursor:pointer"><input type="checkbox" onclick="lfiNctCorrAll(this)"> Tout sélectionner</label>';
            echo '<button type="submit" class="btn-ghost" style="color:#c8102e;border-color:#c8102e;padding:4px 12px;font-size:.85em">🗑 Supprimer la sélection</button>';
            echo '</div>';
            echo '<ul class="lfi-app-list">';
            foreach ($timeline as $e) {
                $is_recu = ($e['sens'] === 'recu');
                echo '<li class="lfi-app-card" style="border-left:4px solid ' . ($is_recu ? '#0066a3' : '#186a3b') . '">';
                echo '<div class="head" style="align-items:center">';
                echo '<label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" class="lfi-corr-cb" name="del[]" value="' . esc_attr($e['sens'] . ':' . (int) $e['_idx']) . '"> <span class="who">' . ($is_recu ? '📥 Reçu' : '📤 Envoyé') . '</span></label>';
                echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html($e['date'] ?? '') . '</div></div>';
                echo '<div class="meta">';
                if ($is_recu && !empty($e['de'])) echo '<span class="meta-chip">de ' . esc_html($e['de']) . '</span>';
                if (!$is_recu && !empty($e['to'])) echo '<span class="meta-chip">à ' . esc_html($e['to']) . '</span>';
                echo '</div>';
                if (!empty($e['objet'])) echo '<div class="com"><strong>' . esc_html($e['objet']) . '</strong></div>';
                if (!empty($e['corps'])) echo '<div class="com" style="white-space:pre-wrap">' . esc_html(mb_substr($e['corps'], 0, 600)) . (mb_strlen($e['corps']) > 600 ? '…' : '') . '</div>';
                echo '</li>';
            }
            echo '</ul>';
            echo '</form>';
            ?>
            <script>
            function lfiNctCorrAll(master){
                var cbs = document.querySelectorAll('#lfi-corr-form .lfi-corr-cb');
                for (var i=0;i<cbs.length;i++) cbs[i].checked = master.checked;
            }
            function lfiNctCorrDel(form){
                var n = form.querySelectorAll('.lfi-corr-cb:checked').length;
                if (n === 0) { alert('Coche au moins une entrée à supprimer.'); return false; }
                return confirm('Supprimer définitivement ' + n + ' entrée(s) ?');
            }
            </script>
            <?php
        }

        /* Formulaire : coller un email reçu */
        echo '<details style="margin-top:10px;background:#e8f0ff;border-radius:8px;padding:10px 14px">';
        echo '<summary style="cursor:pointer;font-weight:700;color:#0066a3">📥 Enregistrer un email reçu (ex : réponse de M. Morineau)</summary>';
        echo '<form method="post" class="lfi-app-form" style="margin-top:10px">';
        wp_nonce_field('lfi_dossier_email_recu');
        echo '<input type="hidden" name="lfi_dossier_email_recu" value="1">';
        echo '<label>De (expéditeur)<input type="text" name="email_de" placeholder="yvonnic.morineau@nmh.fr"></label>';
        echo '<label>Objet<input type="text" name="email_objet" placeholder="Re: Mme Fadila — relogement"></label>';
        echo '<label>Contenu de l\'email<textarea name="email_corps" rows="5" placeholder="Colle ici le texte de l\'email reçu…"></textarea></label>';
        echo '<button type="submit" class="btn-primary">📥 Enregistrer dans le dossier</button>';
        echo '</form>';
        echo '</details>';

        /* === CHIFFRAGE DU PRÉJUDICE === */
        echo '<h3 style="margin:22px 0 6px;color:#c8102e">💶 Chiffrage du préjudice</h3>';
        $prej = (is_array($logs) && !empty($logs['prejudice'])) ? $logs['prejudice'] : null;
        if ($prej) {
            echo '<div class="lfi-app-card" style="border-left:4px solid #0066a3"><div class="meta">';
            echo '<span class="meta-chip">🤝 Amiable : ' . number_format((float) $prej['amiable'], 0, ',', ' ') . ' €</span>';
            echo '<span class="meta-chip">⚖️ Fond : ' . number_format((float) $prej['fond_min'], 0, ',', ' ') . ' – ' . number_format((float) $prej['fond_max'], 0, ',', ' ') . ' €</span>';
            echo '<span class="meta-chip">' . esc_html($prej['date'] ?? '') . '</span></div></div>';
        }
        echo '<div class="row-actions" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap"><a class="btn-primary" href="' . esc_url(lfi_nct_app_url('prejudice', ['id' => (int) $row->id])) . '">💶 ' . ($prej ? 'Recalculer' : 'Chiffrer le préjudice') . '</a>';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('jurisprudence', ['id' => (int) $row->id])) . '">🔎 Jurisprudence</a></div>';

        /* === PIÈCES JOINTES (classées automatiquement par Claude) === */
        if (function_exists('lfi_nct_ingest_render_pieces')) {
            lfi_nct_ingest_render_pieces($row);
        }

        /* === ANALYSE de la réponse NMH + document à imprimer/PDF === */
        $analyse_val = (is_array($logs) && !empty($logs['analyse_nmh'])) ? (string) $logs['analyse_nmh'] : '';
        echo '<h3 id="sec-analyse" style="margin:22px 0 6px;color:#c8102e">📑 Analyse de la réponse de NMH</h3>';
        echo '<div class="lfi-app-help">Saisis (ou colle) ici l\'analyse du dossier : manquements juridiques de NMH et manque de professionnalisme dans la réponse. Le document à imprimer reprend la <strong>discussion complète</strong> (emails envoyés + reçus) suivie de cette analyse + une grille de référence des manquements.</div>';
        echo '<form method="post" class="lfi-app-form" style="margin-top:6px">';
        wp_nonce_field('lfi_dossier_analyse_nmh');
        echo '<input type="hidden" name="lfi_dossier_analyse_nmh" value="1">';
        echo '<label>Analyse (manquements juridiques + ton / professionnalisme)<textarea name="analyse_nmh" rows="6" placeholder="Ex : NMH oppose une absence de signalement, inopérante (art. 1719-1720 C. civ.). Silence total sur les moisissures et le certificat médical. Requalification abusive en charge locative. Ton expéditif, aucune proposition de visite…">' . esc_textarea($analyse_val) . '</textarea></label>';
        echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
        echo '<button type="submit" class="btn-primary">💾 Enregistrer l\'analyse</button>';
        echo '<a class="btn-primary" style="background:#0066a3" href="' . esc_url(lfi_nct_app_url('dossier-doc-analyse-nmh', ['id' => $row->id])) . '" target="_blank">📑 Ouvrir le document (discussion + analyse)</a>';
        echo '</div>';
        echo '</form>';

        /* Suppression définitive */
        echo '<form method="post" style="margin-top:24px" onsubmit="return confirm(\'Supprimer définitivement ce dossier ? Action irréversible.\')">';
        wp_nonce_field('lfi_dossier_delete');
        echo '<input type="hidden" name="lfi_dossier_delete" value="1">';
        echo '<button type="submit" style="background:#a30b25;color:#fff;border:0;padding:10px 16px;border-radius:8px;font-weight:700;cursor:pointer">🗑 Supprimer ce dossier</button>';
        echo '</form>';
    }

    /* Helper voice — injecté ici, partagé avec l'intervention */
    lfi_nct_render_voice_helper();

    /* Sections repliables (page longue) : ouvrir/fermer chaque bloc. */
    lfi_nct_render_section_accordion_js();

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  Helper voice partagé — boutons 🎤 sur champs texte             *
 *                                                                  *
 *  Utilise l'API Web Speech Recognition (Chrome, Edge, Safari iOS).*
 *  Cherche tous les éléments <div class="lfi-voice-zone"           *
 *  data-target="ID" data-label="..."> et y injecte un bouton.      *
 * ============================================================== */
function lfi_nct_render_voice_helper() {
    /* DÉSACTIVÉ (v0.77) — la dictée vocale maison injectait ~290 lignes de
       <script> inline (regex, template strings) dans la page. Un
       post-traitement HTML/JS de la page (minifieur du thème / optimiseur)
       plantait dessus → PAGE BLANCHE sur les écrans qui l'utilisaient
       (dossier juridique, intervention…). On s'en passe : le micro 🎤 du
       clavier iPhone fait le même travail directement dans les champs.
       Les <div class="lfi-voice-zone"> restantes deviennent inertes (vides),
       sans aucun effet visible. */
    return;

    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
    ?>
    <style>
    .lfi-voice-wrap {
        background: #fff8e6; border-left: 4px solid #bd8600;
        padding: 10px 14px; border-radius: 8px; margin-top: 8px;
        font-size: .92em; line-height: 1.5;
    }
    .lfi-voice-wrap.unsupported { background: #f5f5f5; border-left-color: #999; }
    .lfi-voice-tip {
        background: #e8f0ff; border-left: 4px solid #2e7dd7;
        padding: 10px 14px; border-radius: 8px; margin-top: 6px;
        font-size: .88em; line-height: 1.5; color: #1a3a5c;
    }
    .lfi-voice-btn {
        background: #c8102e; color: #fff; border: 0;
        padding: 14px 18px; border-radius: 10px; font-weight: 800; cursor: pointer;
        font-size: 1em; display: inline-flex; align-items: center;
        gap: 8px; -webkit-appearance: none;
        box-shadow: 0 2px 6px rgba(200,16,46,.3);
        width: 100%; justify-content: center;
    }

    /* === DICTAPHONE PLEIN ÉCRAN === */
    .lfi-dict {
        position: fixed; inset: 0; z-index: 999999;
        background: linear-gradient(180deg, #1a1a1a, #2a0a0e);
        color: #fff;
        display: flex; flex-direction: column;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .lfi-dict-bar {
        padding: max(14px, env(safe-area-inset-top)) 14px 12px;
        display: flex; gap: 10px; align-items: center;
        background: rgba(0,0,0,.4); backdrop-filter: blur(8px);
        border-bottom: 1px solid rgba(255,255,255,.1);
    }
    .lfi-dict-bar button {
        flex: 1; padding: 14px 12px; border: 0; border-radius: 10px;
        font-weight: 800; font-size: 1em; cursor: pointer;
        -webkit-appearance: none;
    }
    .lfi-dict-use   { background: #186a3b; color: #fff; }
    .lfi-dict-cancel{ background: rgba(255,255,255,.15); color: #fff; }
    .lfi-dict-status {
        padding: 10px 16px; text-align: center;
        display: flex; align-items: center; justify-content: center; gap: 10px;
        font-size: .95em; color: #ffb;
    }
    .lfi-dict-dot {
        width: 14px; height: 14px; border-radius: 50%;
        background: #c8102e;
        animation: lfi-rec-pulse 1.2s infinite;
        box-shadow: 0 0 0 0 rgba(200,16,46,.7);
    }
    @keyframes lfi-rec-pulse {
        0%,100% { box-shadow: 0 0 0 0 rgba(200,16,46,.7); }
        50%     { box-shadow: 0 0 0 14px rgba(200,16,46,0); }
    }
    .lfi-dict-area {
        flex: 1; overflow-y: auto; padding: 18px 18px 28px;
        font-size: 1.25em; line-height: 1.5;
        color: #fff;
    }
    .lfi-dict-area .interim { color: #aaa; font-style: italic; }
    .lfi-dict-area .placeholder {
        color: #666; font-style: italic; font-size: .85em; text-align: center;
        margin-top: 30vh;
    }
    .lfi-dict-help {
        padding: 12px 16px; background: rgba(255,255,255,.05);
        font-size: .85em; color: #ccc; text-align: center;
        border-top: 1px solid rgba(255,255,255,.1);
    }
    </style>
    <script>
    (function () {
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        var ua = navigator.userAgent || '';
        var isiOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;

        /* ============================================================
           DICTAPHONE PLEIN ÉCRAN
           Ouvre un overlay sombre qui prend TOUTE la fenêtre. Le clavier
           ne peut plus le couvrir car aucun input n'est focusé.
           Le bouton "✓ Utiliser ce texte" est en HAUT, toujours visible.
           ============================================================ */
        function openDictaphone(targetField) {
            if (!SR) {
                alert('La dictée vocale du navigateur n\'est pas disponible. Utilise plutôt le micro 🎤 du clavier iPhone (à côté de la barre d\'espace).');
                return;
            }
            try { targetField.blur(); } catch (e) {}

            var overlay = document.createElement('div');
            overlay.className = 'lfi-dict';
            overlay.innerHTML = ''
                + '<div class="lfi-dict-bar">'
                +   '<button type="button" class="lfi-dict-cancel">✕ Annuler</button>'
                +   '<button type="button" class="lfi-dict-use">✓ Utiliser ce texte</button>'
                + '</div>'
                + '<div class="lfi-dict-status">'
                +   '<span class="lfi-dict-dot"></span>'
                +   '<span class="lfi-dict-label">J\'écoute… parle naturellement</span>'
                + '</div>'
                + '<div class="lfi-dict-area" tabindex="-1"></div>'
                + '<div class="lfi-dict-help">Tout ce que tu dis s\'ajoute au texte existant du champ. Appuie sur ✓ quand t\'as fini.</div>';
            document.body.appendChild(overlay);
            try { overlay.querySelector('.lfi-dict-area').focus({ preventScroll: true }); } catch (e) {}

            var area    = overlay.querySelector('.lfi-dict-area');
            var labelEl = overlay.querySelector('.lfi-dict-label');
            var useBtn  = overlay.querySelector('.lfi-dict-use');
            var cancelBtn = overlay.querySelector('.lfi-dict-cancel');

            /* État interne : on accumule le texte ICI, pas dans le field,
               pour ne pas perdre ce qui était déjà tapé. */
            var anchor = targetField.value || '';
            if (anchor && !/[\s\.\!\?]$/.test(anchor)) anchor += ' ';
            var newText = '';
            var recognition = null;
            var manualStop = false;
            var restartTimer = null;

            function paint(interim) {
                area.innerHTML = '';
                if (anchor) {
                    var a = document.createElement('span');
                    a.style.opacity = '.5';
                    a.textContent = anchor;
                    area.appendChild(a);
                }
                if (newText) {
                    var n = document.createElement('span');
                    n.textContent = newText;
                    area.appendChild(n);
                }
                if (interim) {
                    var i = document.createElement('span');
                    i.className = 'interim';
                    i.textContent = interim;
                    area.appendChild(i);
                }
                if (!anchor && !newText && !interim) {
                    area.innerHTML = '<div class="placeholder">Parle… ce que tu dis apparaîtra ici en grand.</div>';
                }
                area.scrollTop = area.scrollHeight;
            }
            paint('');

            function buildRecognition() {
                var r;
                try { r = new SR(); } catch (e) { return null; }
                r.continuous     = !isiOS;
                r.interimResults = true;
                try { r.lang = 'fr-FR'; } catch (e) {}
                r.onresult = function (event) {
                    var fin = '', interim = '';
                    for (var i = 0; i < event.results.length; i++) {
                        var t = event.results[i][0].transcript;
                        if (event.results[i].isFinal) fin += t + ' ';
                        else interim += t;
                    }
                    if (fin) newText += fin;
                    paint(interim);
                };
                r.onerror = function (e) {
                    var err = e && e.error ? e.error : 'inconnue';
                    if (err === 'not-allowed' || err === 'service-not-allowed') {
                        labelEl.textContent = '🔒 Micro bloqué — autorise dans Réglages Safari';
                        manualStop = true;
                    } else if (err === 'no-speech') {
                        labelEl.textContent = '🤫 J\'écoute toujours… parle un peu plus fort';
                    } else if (err === 'audio-capture') {
                        labelEl.textContent = '🎙 Pas de micro accessible';
                        manualStop = true;
                    } else if (err === 'network') {
                        labelEl.textContent = '📡 Erreur réseau, réessaie';
                    } else {
                        labelEl.textContent = 'Erreur : ' + err;
                    }
                };
                r.onend = function () {
                    if (manualStop) return;
                    /* iOS Safari coupe toutes les 2-5 secondes — on relance */
                    restartTimer = setTimeout(function () {
                        if (manualStop) return;
                        try { recognition.start(); } catch (e) {}
                    }, 80);
                };
                return r;
            }

            function startReco() {
                recognition = buildRecognition();
                if (!recognition) {
                    labelEl.textContent = 'Impossible de lancer le micro (API indisponible).';
                    return;
                }
                try {
                    recognition.start();
                    labelEl.textContent = 'J\'écoute… parle naturellement';
                } catch (e) {
                    labelEl.textContent = 'Démarrage impossible : ' + (e.message || e.name);
                }
            }
            startReco();

            function close(commit) {
                manualStop = true;
                if (restartTimer) clearTimeout(restartTimer);
                try { recognition && recognition.stop(); } catch (e) {}
                if (commit) {
                    targetField.value = anchor + newText;
                    /* Petit feedback visuel : on défile vers le champ */
                    try {
                        var ev = new Event('input', { bubbles: true });
                        targetField.dispatchEvent(ev);
                        targetField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } catch (e) {}
                }
                if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
            }

            useBtn.addEventListener('click', function () { close(true); });
            cancelBtn.addEventListener('click', function () {
                if (newText && !confirm('Annuler ? Le texte dicté sera perdu.')) return;
                close(false);
            });
        }

        /* === Pose des boutons + tip iPhone === */
        document.querySelectorAll('.lfi-voice-zone').forEach(function (zone) {
            var fieldId = zone.getAttribute('data-target');
            var label = zone.getAttribute('data-label') || 'Dicter';
            var field = document.getElementById(fieldId);
            if (!field) return;

            /* On déplace la zone AU-DESSUS du champ pour que le bouton
               reste cliquable quand le clavier iOS est ouvert. */
            if (field.parentNode && field.parentNode.parentNode) {
                var labelEl = field.closest('label');
                if (labelEl && labelEl.parentNode) {
                    labelEl.parentNode.insertBefore(zone, labelEl);
                }
            }

            /* Astuce iPhone — la dictée Apple du clavier marche mieux que
               tout ce qu'on peut faire en JavaScript. On la met en avant. */
            if (isiOS) {
                var tip = document.createElement('div');
                tip.className = 'lfi-voice-tip';
                tip.innerHTML = '🎤 <strong>Pour dicter</strong> : touche le champ ci-dessous → puis le micro 🎤 du clavier iPhone (en bas à droite, à côté de la barre d\'espace).';
                zone.appendChild(tip);
            } else if (SR) {
                /* Sur desktop/Android : bouton plein écran (utile sans clavier tactile) */
                var wrap = document.createElement('div');
                wrap.className = 'lfi-voice-wrap';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'lfi-voice-btn';
                btn.innerHTML = '🎤 ' + label + ' (plein écran)';
                wrap.appendChild(btn);
                zone.appendChild(wrap);
                btn.addEventListener('click', function () { openDictaphone(field); });
            }
        });

        /* ============================================================
           FIX BUG SAVE — Force le blur de tous les champs au moment
           du submit, pour committer la dictée iOS en cours.
           Sans ça, si l'utilisateur clique Save alors que la dictée
           native n'a pas encore terminé d'écrire dans le field, la
           valeur sérialisée est vide → rien n'est enregistré.
           ============================================================ */
        document.querySelectorAll('form.lfi-app-form').forEach(function (form) {
            form.addEventListener('submit', function () {
                if (document.activeElement && document.activeElement.blur) {
                    document.activeElement.blur();
                }
            }, true);
        });
    })();
    </script>
    <?php
}

/* ============================================================== *
 *  ENVOI EMAIL au nom du Groupe d'Action LFI                        *
 *                                                                   *
 *  Une lettre déjà imprimable peut aussi être envoyée directement   *
 *  par email à NMH + Agence Goudy (M. Morineau), avec :              *
 *   - Reply-To = email de l'admin (le destinataire répondra à toi)   *
 *   - From = "Groupe d'Action LFI Nantes Sud Clos Toreau <admin>"    *
 *   - Signature claire mentionnant le mandat du locataire            *
 *   - HTML = exactement la lettre, plus quelques mots d'intro         *
 * ============================================================== */
/** Adresse Gmail du Groupe d'Action (depuis laquelle on envoie les courriers). */
function lfi_nct_ga_gmail() {
    $opt = get_option('lfi_nct_ga_gmail', '');
    return $opt ?: 'nantessudclostoreau@gmail.com';
}

/** Adresse Gmail PERSO de Fabrice (pour « mon dossier à moi »). */
function lfi_nct_perso_gmail() {
    $opt = get_option('lfi_nct_perso_gmail', '');
    return $opt ?: 'fabrice.doucet44@gmail.com';
}

/**
 * Marqueur HTML + script partagé pour OUVRIR GMAIL de façon fiable depuis un
 * formulaire d'email (champs email_to / email_cc / email_subject / email_intro
 * / email_body). Sur iPhone, on ouvre directement l'APPLICATION Gmail via son
 * lien « googlegmail:// » (le lien web tombait sur la boîte de réception sans
 * fenêtre de rédaction). Liens de secours toujours proposés (app / web / autre
 * appli mail) pour ne jamais rester bloqué. L'envoi est journalisé en
 * arrière-plan (iframe cachée) via le champ caché indiqué par data-log-field.
 *
 * À placer UNE fois par formulaire. Le <script> n'est émis qu'une fois.
 */
function lfi_nct_render_gmail_opener($gmail_user, $signature, $log_field, $button_label = '📨 Ouvrir dans Gmail', $redirect_url = '') {
    echo '<input type="hidden" name="' . esc_attr($log_field) . '" value="">';
    echo '<iframe name="lfiLogFrame" id="lfiLogFrame" style="display:none" title="journal"></iframe>';
    echo '<button type="button" class="btn-primary big" onclick="lfiNctOpenGmail(this)" '
        . 'data-gmail-user="' . esc_attr($gmail_user) . '" '
        . 'data-gmail-sig="' . esc_attr($signature) . '" '
        . 'data-redirect="' . esc_attr($redirect_url) . '" '
        . 'data-log-field="' . esc_attr($log_field) . '">' . esc_html($button_label) . '</button>';
    echo '<div id="lfiGmailFallback" style="display:none;margin-top:8px" class="lfi-app-help">'
        . '✅ <strong>Email ajouté au dossier.</strong> Si Gmail ne s\'ouvre pas tout seul, appuie ici : '
        . '<a id="lfiGmailApp" href="#" style="font-weight:700">📱 app Gmail</a> · '
        . '<a id="lfiGmailWeb" href="#" target="_blank" rel="noopener" style="font-weight:700">🌐 Gmail web</a> · '
        . '<a id="lfiGmailMailto" href="#" style="font-weight:700">✉️ autre appli mail</a></div>';

    static $script_done = false;
    if ($script_done) return;
    $script_done = true;
    ?>
    <script>
    function lfiNctHtmlToPlain(html){
        var t = String(html||'');
        /* Saut de ligne AVANT les blocs (titres, paragraphes) pour bien
           séparer les sections, et APRÈS leur fermeture. */
        t = t.replace(/<\s*(h1|h2|h3|p|div|tr)[^>]*>/gi, "\n");
        t = t.replace(/<\s*br\s*\/?>/gi, "\n");
        t = t.replace(/<\s*li[^>]*>/gi, "\n• ");
        t = t.replace(/<\/\s*(p|h1|h2|h3|li|div|tr)\s*>/gi, "\n");
        var d = document.createElement('div');
        d.innerHTML = t;
        t = d.textContent || d.innerText || '';
        return t.replace(/[ \t]+\n/g, "\n").replace(/\n{3,}/g, "\n\n").trim();
    }
    function lfiNctOpenGmail(btn){
        var form = btn.form;
        var user = btn.getAttribute('data-gmail-user') || '';
        var sig  = btn.getAttribute('data-gmail-sig')  || '';
        var logf = btn.getAttribute('data-log-field')  || '';
        var to   = (form.email_to    && form.email_to.value)    || '';
        var cc   = (form.email_cc    && form.email_cc.value)    || '';
        var su   = (form.email_subject && form.email_subject.value) || '';
        var intro= (form.email_intro && form.email_intro.value) || '';
        var body = (form.email_body  && form.email_body.value)  || '';
        var plain = ((intro ? intro + "\n\n" : '') + lfiNctHtmlToPlain(body) + (sig || '')).trim();

        var webUrl = 'https://mail.google.com/mail/?view=cm&fs=1&tf=1'
            + (user ? '&authuser=' + encodeURIComponent(user) : '')
            + '&to=' + encodeURIComponent(to)
            + (cc ? '&cc=' + encodeURIComponent(cc) : '')
            + '&su=' + encodeURIComponent(su)
            + '&body=' + encodeURIComponent(plain);
        var appUrl = 'googlegmail:///co?to=' + encodeURIComponent(to)
            + (cc ? '&cc=' + encodeURIComponent(cc) : '')
            + '&subject=' + encodeURIComponent(su)
            + '&body=' + encodeURIComponent(plain);
        var mailto = 'mailto:' + encodeURIComponent(to)
            + '?' + (cc ? 'cc=' + encodeURIComponent(cc) + '&' : '')
            + 'subject=' + encodeURIComponent(su)
            + '&body=' + encodeURIComponent(plain);

        /* Journalise de façon FIABLE, même quand on bascule vers l'app Gmail
           (la page Safari se ferme). sendBeacon est conçu pour survivre à la
           navigation ; repli sur l'iframe cachée si indisponible. */
        if (logf) { var h = form.querySelector('input[name="' + logf + '"]'); if (h) h.value = '1'; }
        var logged = false;
        try {
            if (navigator.sendBeacon) {
                var fd = new FormData(form);
                if (logf) fd.set(logf, '1');
                logged = navigator.sendBeacon(window.location.href, fd);
            }
        } catch(e){}
        if (!logged) { try { form.target = 'lfiLogFrame'; form.submit(); form.target = '_self'; } catch(e){} }

        /* Prépare et affiche les liens de secours. */
        var fb = document.getElementById('lfiGmailFallback');
        var la = document.getElementById('lfiGmailApp'), lw = document.getElementById('lfiGmailWeb'), lm = document.getElementById('lfiGmailMailto');
        if (la) la.href = appUrl; if (lw) lw.href = webUrl; if (lm) lm.href = mailto;
        if (fb) fb.style.display = 'block';

        /* Page de confirmation à rejoindre après l'envoi (pour CONSTATER que
           l'email est bien consigné). */
        var redirect = btn.getAttribute('data-redirect') || '';

        /* Ouvre Gmail : l'APP sur iPhone (sinon on retombe sur le web). */
        var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        if (isIOS) {
            window.location.href = appUrl;
            var done = false;
            /* Quand l'utilisateur revient de Gmail (page de nouveau visible),
               on l'amène sur la page de confirmation. */
            if (redirect) {
                document.addEventListener('visibilitychange', function onv(){
                    if (!document.hidden && !done) { done = true; document.removeEventListener('visibilitychange', onv); window.location.href = redirect; }
                });
            }
            /* App Gmail absente : après un délai, on ouvre Gmail web. */
            setTimeout(function(){ if (!document.hidden && !done) { window.location.href = webUrl; } }, 1500);
        } else {
            window.open(webUrl, '_blank');
            if (redirect) window.location.href = redirect;
        }
    }
    </script>
    <?php
}

/** Convertit un corps HTML simple en texte brut (pour le compose Gmail). */
function lfi_nct_html_to_plain($html) {
    $t = (string) $html;
    $t = preg_replace('#<\s*br\s*/?>#i', "\n", $t);
    $t = preg_replace('#</\s*(p|h1|h2|h3|li|div|tr)\s*>#i', "\n", $t);
    $t = preg_replace('#<\s*li[^>]*>#i', "• ", $t);
    $t = wp_strip_all_tags($t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t]+\n/", "\n", $t);
    $t = preg_replace("/\n{3,}/", "\n\n", $t);
    return trim($t);
}

function lfi_nct_app_view_dossier_send_email() {
    if (!lfi_nct_app_guard_brigade()) return;
    global $wpdb;
    $id = (int) ($_GET['id'] ?? 0);
    $letter_key = sanitize_key($_GET['letter'] ?? '');
    $dossier = lfi_nct_dossier_get($id);
    if (!$dossier) { wp_die('Dossier introuvable'); }

    $allowed = ['reponse_nmh', 'lrar_travaux', 'lrar_relogement', 'schs', 'ars'];
    if (!in_array($letter_key, $allowed, true)) wp_die('Type de lettre inconnu');

    $bailleur = lfi_nct_fact_bailleur();
    $presta   = lfi_nct_fact_prestataire();
    $u        = wp_get_current_user();

    /* Sujet + destinataires par type de lettre */
    $defaults = lfi_nct_dossier_email_defaults($letter_key, $dossier, $bailleur);
    $tenant_full = trim($dossier->tenant_prenom . ' ' . $dossier->tenant_nom);

    /* Relance (lien « Relancer ») : préfixe l'objet et l'intro. */
    if (!empty($_GET['relance'])) {
        $defaults['subject'] = 'Relance — ' . ($defaults['subject'] ?? '');
        $defaults['intro']   = "Madame, Monsieur,\n\nSauf erreur de ma part, je n'ai pas eu de retour à mon précédent courrier. Je me permets de vous relancer ci-dessous.\n\n" . ($defaults['intro'] ?? '');
    }

    /* HANDLER POST : JOURNALISER l'envoi Gmail dans le dossier.
       La fenêtre de rédaction Gmail est ouverte côté navigateur (JS, nouvel
       onglet) au moment du clic ; ce handler ne fait QUE consigner l'email
       dans le dossier puis revenir sur la fiche. */
    if (!empty($_POST['lfi_send_gmail_log']) && check_admin_referer('lfi_dossier_email_send')) {
        $to      = sanitize_text_field(wp_unslash($_POST['email_to'] ?? ''));
        $cc      = sanitize_text_field(wp_unslash($_POST['email_cc'] ?? ''));

        /* Journalise dans le dossier — sauf si on vient de consigner le MÊME
           email (même destinataire + même lettre) il y a moins de 5 min :
           ça évite les doublons quand on reclique (ex. si Gmail tarde). */
        $logs = json_decode($dossier->notes ?? '', true);
        if (!is_array($logs)) $logs = ['__notes' => $dossier->notes ?? ''];
        $logs['email_log'] = $logs['email_log'] ?? [];
        $is_dup = false;
        $now_ts = (int) current_time('timestamp');
        foreach (array_reverse($logs['email_log']) as $prev) {
            $pt = isset($prev['date']) ? strtotime($prev['date']) : 0;
            if (!$pt || ($now_ts - $pt) > 300) break; // entrées plus anciennes : on s'arrête
            if (($prev['to'] ?? '') === $to && ($prev['letter'] ?? '') === $letter_key) { $is_dup = true; break; }
        }
        if (!$is_dup) {
            $logs['email_log'][] = ['letter' => $letter_key, 'to' => $to, 'cc' => $cc, 'date' => current_time('Y-m-d H:i'), 'via' => 'gmail'];
            $wpdb->update($wpdb->prefix . 'lfi_nct_dossiers_locataires',
                ['notes' => wp_json_encode($logs, JSON_UNESCAPED_UNICODE)],
                ['id' => $dossier->id, 'owner_user_id' => (int) lfi_nct_dossier_owner_id()]
            );
        }

        wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $dossier->id, 'gmail_open' => 1]));
        exit;
    }

    /* HANDLER POST : envoi via le site (wp_mail) */
    if (!empty($_POST['lfi_send_wpmail']) && check_admin_referer('lfi_dossier_email_send')) {
        $to        = sanitize_text_field(wp_unslash($_POST['email_to'] ?? ''));
        $cc        = sanitize_text_field(wp_unslash($_POST['email_cc'] ?? ''));
        $bcc_self  = !empty($_POST['email_bcc_self']);
        $subject   = sanitize_text_field(wp_unslash($_POST['email_subject'] ?? ''));
        $body_raw  = wp_kses_post(wp_unslash($_POST['email_body'] ?? ''));
        $intro     = sanitize_textarea_field(wp_unslash($_POST['email_intro'] ?? ''));

        /* Compose le mail HTML */
        $html  = '<div style="font-family:Georgia,serif;font-size:14px;line-height:1.5;color:#1a1a1a;max-width:720px">';
        if ($intro) $html .= '<p style="margin-bottom:20px">' . nl2br(esc_html($intro)) . '</p><hr style="margin:20px 0;border:0;border-top:1px solid #ccc">';
        $html .= $body_raw;
        $html .= '<hr style="margin:24px 0;border:0;border-top:1px solid #ccc">';
        $html .= '<p style="font-size:12px;color:#666">Ce courrier est établi avec l\'appui du <strong>Groupe d\'Action de la France Insoumise — Nantes Sud / Clos Toreau</strong>, à la demande et avec l\'accord de <strong>' . esc_html($tenant_full ?: 'la locataire') . '</strong>, dans le cadre de notre action d\'accompagnement des habitant·es (aide à la rédaction et à la transmission). Pour toute réponse, merci d\'utiliser l\'adresse en Reply-To.</p>';
        $html .= '</div>';

        /* Headers */
        $from_name = 'GA LFI Nantes Sud — Clos Toreau';
        $admin_email = $u->user_email ?: get_option('admin_email');
        $headers = [];
        $headers[] = 'From: ' . $from_name . ' <' . $admin_email . '>';
        $headers[] = 'Reply-To: ' . $admin_email;
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        if ($cc)       $headers[] = 'Cc: ' . $cc;
        if ($bcc_self) $headers[] = 'Bcc: ' . $admin_email;

        $sent = wp_mail($to, $subject, $html, $headers);
        if ($sent) {
            /* Marque la lettre comme envoyée par email — date dans la
               colonne <letter_key>_email_date (on stocke en notes pour
               éviter de bumper la version DB pour 4 colonnes). */
            $logs = json_decode($dossier->notes ?? '', true);
            if (!is_array($logs)) $logs = ['__notes' => $dossier->notes ?? ''];
            $logs['email_log'] = $logs['email_log'] ?? [];
            $logs['email_log'][] = [
                'letter' => $letter_key,
                'to'     => $to,
                'cc'     => $cc,
                'date'   => current_time('Y-m-d H:i'),
            ];
            $wpdb->update($wpdb->prefix . 'lfi_nct_dossiers_locataires',
                ['notes' => wp_json_encode($logs, JSON_UNESCAPED_UNICODE)],
                ['id' => $dossier->id, 'owner_user_id' => (int) lfi_nct_dossier_owner_id()]
            );
            wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $dossier->id, 'email_sent' => 1]));
            exit;
        } else {
            $send_error = 'Échec d\'envoi. Vérifie la configuration mail du site (LiteSpeed Mail ou SMTP).';
        }
    }

    /* Génère le HTML de la lettre EN MÉMOIRE pour le placer en valeur
       par défaut du textarea. */
    ob_start();
    $generators = [
        'lrar_travaux'    => 'lfi_nct_app_view_dossier_doc_lrar_travaux',
        'lrar_relogement' => 'lfi_nct_app_view_dossier_doc_lrar_relogement',
        'schs'            => 'lfi_nct_app_view_dossier_doc_schs',
        'ars'             => 'lfi_nct_app_view_dossier_doc_ars',
    ];
    /* On extrait juste le bloc .lfi-rec-doc du rendu — l'enrobage app
       n'est pas voulu dans un email. */
    /* Pour simplifier : on regénère le corps de la lettre via une
       fonction utilitaire à ne pas réécrire — on prend juste le
       texte brut pour la compose. */
    ob_end_clean();

    /* Corps de mail par défaut = court résumé + invitation à la
       réponse. La lettre formelle peut être jointe en PJ par l'user. */
    $default_body = lfi_nct_dossier_email_body_text($letter_key, $dossier, $bailleur, $tenant_full);

    /* ----------- RENDU ----------- */
    lfi_nct_app_screen_open('📧 Envoyer par email', $defaults['title'] . ' · dossier #' . $dossier->id);

    if (!empty($send_error)) echo '<div class="lfi-error"><strong>Erreur :</strong> ' . esc_html($send_error) . '</div>';

    echo '<div class="lfi-app-help" style="background:#e8f5ea;border-left:4px solid #186a3b">';
    echo '🤝 <strong>Tu aides ' . esc_html($tenant_full ?: 'la locataire') . ' à transmettre ce courrier.</strong> Le Groupe d\'Action <strong>accompagne</strong> (aide à la rédaction et à l\'envoi) — il ne représente pas juridiquement la locataire. Le destinataire pourra te répondre directement (Reply-To = ton email).';
    echo '</div>';

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_dossier_email_send');
    echo '<input type="hidden" name="lfi_dossier_email_send" value="1">';

    echo '<label>Destinataire(s) — séparer par virgule<input type="text" name="email_to" value="' . esc_attr($defaults['to']) . '" required></label>';
    echo '<label>Copie (CC) — optionnel<input type="text" name="email_cc" value="' . esc_attr($defaults['cc']) . '"></label>';

    echo '<label style="display:flex;align-items:center;gap:6px;margin:6px 0">';
    echo '<input type="checkbox" name="email_bcc_self" value="1" checked> Recevoir une copie cachée pour mon archive';
    echo '</label>';

    echo '<label>Objet<input type="text" name="email_subject" value="' . esc_attr($defaults['subject']) . '" required></label>';

    echo '<label>📝 Mot d\'intro personnel (avant la lettre)<textarea name="email_intro" rows="4" placeholder="Optionnel — ex: « Suite à notre visite ce matin chez Mme X, je vous fais parvenir formellement... »">' . esc_textarea($defaults['intro']) . '</textarea></label>';
    echo '<div class="lfi-voice-zone" data-target="lfi-email-intro" data-label="Dicter mon intro"></div>';

    echo '<label>Lettre / corps du mail (HTML autorisé)<textarea name="email_body" id="lfi-email-body" rows="14" required>' . esc_textarea($default_body) . '</textarea></label>';
    echo '<div class="lfi-app-help"><small>Tu peux modifier librement le texte. Les balises HTML simples (&lt;p&gt; &lt;strong&gt; &lt;br&gt; &lt;ul&gt; &lt;li&gt;) sont conservées.</small></div>';

    /* Bouton Gmail robuste (ouvre l'app Gmail sur iPhone) + journalisation.
       Signature personnalisée par GA (nom + responsable), avec repli sûr. */
    $ga_nom_courrier = function_exists('lfi_nct_ga_entete_nom') ? lfi_nct_ga_entete_nom() : 'Groupe d\'Action LFI Nantes Sud – Clos Toreau';
    $ga_resp = function_exists('lfi_nct_ga_perso') ? trim((string) lfi_nct_ga_perso()['responsable']) : '';
    $sig_qui = $ga_resp !== '' ? $ga_resp . ' — ' . $ga_nom_courrier : $ga_nom_courrier;
    $gmail_signature = "\n\n—\n" . $sig_qui . " / Union des Quartiers Libres\nCourrier établi avec notre appui, à la demande et avec l'accord de " . ($tenant_full ?: 'la locataire') . ".";
    lfi_nct_render_gmail_opener(lfi_nct_ga_gmail(), $gmail_signature, 'lfi_send_gmail_log', '📨 Ouvrir dans mon Gmail (' . lfi_nct_ga_gmail() . ')', lfi_nct_app_url('dossier-juridique-edit', ['id' => $dossier->id, 'gmail_open' => 1]));
    echo '<div class="lfi-app-help" style="background:#e8f0ff;border-left:4px solid #0066a3"><small>Sur iPhone, ça ouvre <strong>l\'application Gmail</strong> avec le message déjà rempli — tu n\'as plus qu\'à appuyer sur « Envoyer ». L\'email est <strong>aussitôt ajouté au dossier</strong>. (Si rien ne s\'ouvre, utilise les liens de secours qui apparaissent.)</small></div>';

    echo '<details style="margin-top:8px"><summary style="cursor:pointer;color:#666;font-size:.9em">Ou envoyer directement depuis le site (sans Gmail)</summary>';
    echo '<button type="submit" name="lfi_send_wpmail" value="1" class="btn-ghost" style="margin-top:8px">📧 Envoyer depuis le site (wp_mail)</button>';
    echo '<div class="lfi-app-help"><small>À n\'utiliser que si l\'envoi par mail du site est bien configuré.</small></div></details>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-juridique-edit', ['id' => $dossier->id])) . '">← Annuler</a>';

    echo '</form>';

    /* Voice helper */
    lfi_nct_render_voice_helper();

    lfi_nct_app_screen_close();
}

/* Construit les destinataires + sujet par type de lettre */
function lfi_nct_dossier_email_defaults($letter_key, $dossier, $bailleur) {
    $tenant_full = trim($dossier->tenant_prenom . ' ' . $dossier->tenant_nom);
    $logement = trim($dossier->tenant_adresse . ($dossier->tenant_etage ? ', ét. ' . $dossier->tenant_etage : ''));

    $to_nmh    = trim($bailleur['email'] ?? '');
    $cc_agence = trim($bailleur['agence_email'] ?? 'yvonnic.morineau@nmh.fr');

    /* La COPIE (CC) part TOUJOURS vers notre propre archive Gmail, jamais
       vers le bailleur : NMH/Morineau n'a pas à connaître nos correspondances
       (en particulier la saisine du service d'hygiène ou de l'ARS). Quand le
       courrier est ADRESSÉ au bailleur, l'agence est mise dans le « À »,
       pas en copie. */
    $ga_archive = lfi_nct_ga_gmail();

    /* Destinataires « bailleur » : on regroupe l'adresse générale NMH et
       l'agence (Morineau) dans le « À ». */
    $bailleur_to = trim(implode(', ', array_unique(array_filter([$to_nmh, $cc_agence]))));
    if ($bailleur_to === '') $bailleur_to = $cc_agence ?: $to_nmh;

    switch ($letter_key) {
        case 'reponse_nmh':
            return [
                'title'  => 'Réponse argumentée',
                'to'     => $bailleur_to,
                'cc'     => $ga_archive,
                'subject'=> 'RE: ' . ($tenant_full ?: 'logement') . ($logement ? ' — ' . $logement : ''),
                'intro'  => "Monsieur Morineau,\n\nNous accusons réception de votre message et y répondons point par point ci-dessous. La version papier suit par lettre recommandée.",
            ];
        case 'lrar_travaux':
            return [
                'title'  => 'Mise en demeure travaux urgents',
                'to'     => $bailleur_to,
                'cc'     => $ga_archive,
                'subject'=> 'Mise en demeure de travaux urgents — ' . ($tenant_full ?: 'logement') . ($logement ? ' · ' . $logement : ''),
                'intro'  => "Madame, Monsieur,\n\nJe vous fais parvenir formellement par la présente la mise en demeure ci-après concernant le logement de " . ($tenant_full ?: '[locataire]') . ". La version papier de ce courrier vous sera également adressée par lettre recommandée avec accusé de réception.",
            ];
        case 'lrar_relogement':
            return [
                'title'  => 'Demande de relogement d\'urgence médicale',
                'to'     => $bailleur_to,
                'cc'     => $ga_archive,
                'subject'=> '🆘 URGENT — Relogement médical de ' . ($tenant_full ?: 'locataire') . ($logement ? ' · ' . $logement : ''),
                'intro'  => "Madame, Monsieur,\n\nCompte tenu de l'urgence sanitaire attestée, je sollicite votre traitement prioritaire de la demande de relogement de " . ($tenant_full ?: '[locataire]') . " formalisée dans le courrier ci-dessous. La LRAR vous sera également remise.",
            ];
        case 'schs':
            return [
                'title'  => 'Saisine du service d\'hygiène — Nantes Métropole',
                'to'     => 'Julien.LEJEUNE@nantesmetropole.fr',
                'cc'     => $ga_archive,
                'subject'=> 'Signalement d\'insalubrité — ' . $logement,
                'intro'  => "Madame, Monsieur,\n\nJe vous saisis par la présente d'une situation d'insalubrité documentée. La LRAR papier suit, le présent email étant adressé en parallèle pour célérité.",
            ];
        case 'ars':
            return [
                'title'  => 'Saisine ARS Pays de la Loire',
                'to'     => 'ars-pdl-contact@ars.sante.fr',
                'cc'     => $ga_archive,
                'subject'=> 'Signalement d\'un risque sanitaire en logement social — ' . $logement,
                'intro'  => "Madame, Monsieur,\n\nJe vous saisis d'un risque sanitaire dans un logement social, documenté ci-après. La LRAR papier suit ; le présent email vise la célérité de prise en charge.",
            ];
    }
    return ['title' => '', 'to' => '', 'cc' => $ga_archive, 'subject' => '', 'intro' => ''];
}

/* Corps du mail (HTML court) pour chaque type — l'utilisateur peut éditer */
function lfi_nct_dossier_email_body_text($letter_key, $dossier, $bailleur, $tenant_full) {
    $logement = trim($dossier->tenant_adresse . ($dossier->tenant_etage ? ', étage ' . $dossier->tenant_etage : '') . ($dossier->tenant_appartement ? ', appt ' . $dossier->tenant_appartement : ''));
    $cons     = $dossier->constatations ?? '';
    $patho    = $dossier->certificat_pathologie ?? '';
    $medecin  = $dossier->certificat_medecin ?? '';

    $html  = '<p><strong>Objet : ' . esc_html(lfi_nct_dossier_email_defaults($letter_key, $dossier, $bailleur)['title']) . '</strong></p>';
    $html .= '<p>Logement concerné : <strong>' . esc_html($logement) . '</strong>';
    if ($tenant_full) $html .= ' — locataire : <strong>' . esc_html($tenant_full) . '</strong>';
    $html .= '</p>';

    if ($cons) {
        $html .= '<h3 style="color:#c8102e">Constatations établies sur place</h3>';
        $html .= '<p>' . nl2br(esc_html($cons)) . '</p>';
    }
    if ($patho && $medecin) {
        $html .= '<h3 style="color:#c8102e">Situation médicale (certificat ' . esc_html($medecin) . ')</h3>';
        $html .= '<p>' . nl2br(esc_html($patho)) . '</p>';
    }

    switch ($letter_key) {
        case 'reponse_nmh':
            $html .= '<h3 style="color:#c8102e">1. Absence prétendue de signalement</h3>';
            $html .= '<p>L\'obligation d\'entretien (art. 1719-1720 CC, décret 2002-120) est permanente et ne dépend d\'aucun signalement préalable. La présente correspondance constitue ce signalement formel et écrit.</p>';
            $html .= '<h3 style="color:#c8102e">2. Objet réel resté sans réponse</h3>';
            $html .= '<p>Votre message n\'évoque ni les moisissures/humidité constatées, ni le certificat médical, qui sont l\'objet même de notre demande de relogement. Ces désordres relèvent du bâti (ventilation, étanchéité) et non d\'une charge locative.</p>';
            $html .= '<h3 style="color:#c8102e">3. Visite contradictoire</h3>';
            $html .= '<p>Vous indiquez n\'avoir pas eu accès au logement : nous proposons une visite contradictoire en présence de la locataire et de l\'association. Merci de confirmer une date sous 8 jours.</p>';
            $html .= '<h3 style="color:#c8102e">4. « Charge locative »</h3>';
            $html .= '<p>Acte pris pour l\'entretien courant. En revanche l\'humidité structurelle, la VMC, le moteur du volet électrifié et les infiltrations du bâti restent à votre charge (art. 1719-1720 CC).</p>';
            $html .= '<h3 style="color:#c8102e">5. À défaut</h3>';
            $html .= '<p>Nous saisissons le SCHS de Nantes et l\'ARS aux fins de constat d\'insalubrité (art. L.1331-22 et s. CSP), susceptible d\'emporter votre obligation de relogement (art. L.521-3-1 CCH), et réservons la saisine de la juridiction compétente.</p>';
            break;
        case 'lrar_travaux':
            $dc = function_exists('lfi_nct_nmh_urgence_get') ? lfi_nct_nmh_urgence_get($dossier->nmh_urgence ?: 'bailleur')['court'] : '1 mois';
            $html .= '<h3 style="color:#c8102e">Demande</h3>';
            $html .= '<p>En application des articles 1719 et 1724 du Code civil, de l\'article 6 de la loi n° 89-462 et du décret 2002-120, je vous mets en demeure de procéder <strong>sans délai</strong> à une visite contradictoire du logement et de réaliser, <strong>sous ' . esc_html(strtoupper($dc)) . '</strong> à compter de la réception, l\'intégralité des travaux nécessaires à la remise en conformité avec les critères de décence.</p>';
            $html .= '<p>À défaut, je serai contraint(e) de saisir la Commission Départementale de Conciliation, puis le Tribunal Judiciaire de Nantes aux fins de condamnation sous astreinte, ainsi que le SCHS et l\'ARS pour constat d\'insalubrité.</p>';
            break;
        case 'lrar_relogement':
            $html .= '<h3 style="color:#c8102e">Demande</h3>';
            $html .= '<p>En application de l\'article L.521-3-1 du CCH, de la loi DALO (n° 2007-290) et de l\'article 1719 du Code civil, je sollicite l\'attribution d\'un logement décent et adapté à la situation médicale, <strong>dans un délai d\'UN (1) MOIS</strong>.</p>';
            $html .= '<p>À défaut, je saisirai la commission DALO, le SCHS aux fins d\'arrêté d\'insalubrité (qui emporte obligation de relogement à votre charge — art. L.521-3-1 CCH), et le cas échéant le Tribunal Judiciaire en référé (art. 835 CPC).</p>';
            break;
        case 'schs':
            $html .= '<h3 style="color:#c8102e">Demande</h3>';
            $html .= '<p>Conformément aux articles L.1331-22 et suivants du Code de la santé publique, je sollicite la diligence d\'une <strong>visite d\'enquête sur place par les agents assermentés du SCHS, dans les meilleurs délais</strong>, l\'établissement d\'un rapport circonstancié, et le cas échéant la saisine de Monsieur le Préfet aux fins d\'arrêté d\'insalubrité.</p>';
            $html .= '<p><strong>L\'urgence est caractérisée</strong> par la présence d\'un occupant vulnérable dont la santé est affectée (certificat médical) et par des désordres portant atteinte à la salubrité du logement. Je vous remercie de bien vouloir programmer une intervention au logement le plus rapidement possible.</p>';
            break;
        case 'ars':
            $html .= '<h3 style="color:#c8102e">Demande</h3>';
            $html .= '<p>Conformément aux articles L.1311-2 et L.1331-22 du Code de la santé publique, je sollicite, <strong>dans les meilleurs délais</strong>, l\'évaluation sanitaire du logement, le cas échéant la saisine du Préfet, l\'orientation médicale de l\'occupant exposé, et la coordination avec le SCHS et Nantes Métropole Habitat.</p>';
            $html .= '<p><strong>L\'urgence sanitaire est caractérisée</strong> (occupant vulnérable, santé affectée — certificat médical). Je vous remercie de programmer une intervention au logement le plus rapidement possible.</p>';
            break;
    }

    $html .= '<p style="margin-top:18px">Je reste à votre disposition pour toute information complémentaire.</p>';
    $html .= '<p>Salutations distinguées,</p>';
    return $html;
}

/* ============================================================== *
 *  Helpers communs aux lettres                                     *
 * ============================================================== */
function lfi_nct_dossier_doc_open($titre_doc) {
    $id = (int) ($_GET['id'] ?? 0);
    $dossier = lfi_nct_dossier_get($id);
    if (!$dossier) wp_die('Dossier introuvable');

    $presta = function_exists('lfi_nct_fact_prestataire') ? lfi_nct_fact_prestataire() : [];
    $bailleur = function_exists('lfi_nct_fact_bailleur') ? lfi_nct_fact_bailleur() : ['nom' => 'Nantes Métropole Habitat', 'adresse' => '8 rue de la Tour d\'Auvergne', 'cp_ville' => '44000 Nantes'];

    $tenant_full = trim($dossier->tenant_prenom . ' ' . $dossier->tenant_nom);
    $tenant_logement = trim($dossier->tenant_adresse . ($dossier->tenant_etage ? ' (étage ' . $dossier->tenant_etage . ')' : '') . ($dossier->tenant_appartement ? ' · appt ' . $dossier->tenant_appartement : ''));

    lfi_nct_app_screen_open($titre_doc, '#' . $dossier->id . ' · ' . $tenant_full);

    if (function_exists('lfi_nct_rec_doc_styles')) lfi_nct_rec_doc_styles();

    return compact('dossier', 'presta', 'bailleur', 'tenant_full', 'tenant_logement');
}

function lfi_nct_dossier_header_locataire($dossier, $presta) {
    echo '<div class="expediteur">';
    echo '<strong>' . esc_html(trim($dossier->tenant_prenom . ' ' . $dossier->tenant_nom)) . '</strong><br>';
    if ($dossier->tenant_adresse)  echo esc_html($dossier->tenant_adresse) . '<br>';
    if ($dossier->tenant_etage)    echo 'Étage ' . esc_html($dossier->tenant_etage);
    if ($dossier->tenant_appartement) echo ' · Appt ' . esc_html($dossier->tenant_appartement);
    if ($dossier->tenant_etage || $dossier->tenant_appartement) echo '<br>';
    if ($dossier->tenant_tel)      echo 'Tél. : ' . esc_html($dossier->tenant_tel) . '<br>';
    if ($dossier->tenant_email)    echo 'Mél. : ' . esc_html($dossier->tenant_email);
    echo '</div>';
}

function lfi_nct_dossier_header_destinataire_nmh($bailleur) {
    echo '<div class="destinataire">';
    echo '<strong>Monsieur le Directeur Général</strong><br>';
    echo '<strong>' . esc_html($bailleur['nom'] ?? 'Nantes Métropole Habitat') . '</strong><br>';
    if (!empty($bailleur['adresse']))  echo esc_html($bailleur['adresse']) . '<br>';
    if (!empty($bailleur['cp_ville'])) echo esc_html($bailleur['cp_ville']);
    echo '</div>';

    /* Bloc copie à l'agence sectorielle si configurée */
    if (!empty($bailleur['agence_nom']) || !empty($bailleur['agence_email'])) {
        echo '<div style="background:#f8f8f8;padding:10px 14px;border-left:3px solid #c8102e;margin:10px 0;font-size:.92em">';
        echo '<strong>Copie pour information :</strong><br>';
        if (!empty($bailleur['agence_contact'])) echo esc_html($bailleur['agence_contact']) . ', ';
        if (!empty($bailleur['agence_nom']))     echo esc_html($bailleur['agence_nom']);
        if (!empty($bailleur['agence_secteur'])) echo ' — responsable de secteur ' . esc_html($bailleur['agence_secteur']);
        if (!empty($bailleur['agence_email']))   echo '<br>Mél. : ' . esc_html($bailleur['agence_email']);
        if (!empty($bailleur['agence_adresse'])) echo ' · ' . esc_html($bailleur['agence_adresse']);
        if (!empty($bailleur['agence_tel']))     echo ' · Tél. ' . esc_html($bailleur['agence_tel']);
        echo '</div>';
    }
}

/* ============================================================== *
 *  ÉCRAN : Cadre juridique de la facturation                       *
 * ============================================================== */
/* ============================================================== *
 *  MONTAGE FINANCIER : faire payer NMH, financer les avocats       *
 * ============================================================== */
function lfi_nct_app_view_montage_financier() {
    if (!lfi_nct_app_guard_brigade()) return;
    lfi_nct_app_screen_open('💰 Montage financier', 'Faire payer NMH · financer les avocats — proprement');
    if (function_exists('lfi_nct_rec_doc_styles')) lfi_nct_rec_doc_styles();

    echo '<div class="lfi-app-help no-print" style="background:#fff8e6;border-left:4px solid #bd8600">';
    echo '⚠️ <strong>Ceci n\'est pas un avis comptable ni juridique.</strong> Le principe : faire supporter les coûts par NMH par des voies légales, en gardant les <strong>caisses strictement séparées</strong>. À faire valider par un <strong>expert-comptable</strong> et par les avocats (Me Vallée, Me Gouache).';
    echo '</div>';

    echo '<div class="lfi-rec-doc">';
    echo '<h1>Montage financier — faire payer Nantes Métropole Habitat et financer les avocats</h1>';

    echo '<h2>Le principe : 3 caisses séparées (ne jamais les mélanger)</h2>';
    echo '<div class="citations">';
    echo '<p><strong>1) 🧰 Ton auto-entreprise (toi).</strong> Tu factures à NMH les <strong>travaux et prestations techniques</strong> (réparations, constat, devis, déplacement) via la <strong>substitution de l\'art. 1222 du Code civil</strong> (après mise en demeure). C\'est ton <strong>revenu professionnel personnel</strong>, sur le compte de l\'auto-entreprise. Jamais mélangé avec l\'association.</p>';
    echo '<p><strong>2) 🏛 L\'association.</strong> Elle perçoit les <strong>cotisations</strong> des locataires accompagnés, les <strong>dons</strong>, et — lorsqu\'elle est <strong>partie à l\'action</strong> — l\'<strong>article 700 du CPC</strong> (remboursement des frais, dont avocat) + d\'éventuels <strong>dommages-intérêts pour préjudice à l\'intérêt collectif</strong>. Avec ces fonds, <strong>l\'association règle les avocats</strong> (ou avance leurs honoraires, récupérés ensuite sur NMH).</p>';
    echo '<p><strong>3) 👤 Le locataire.</strong> S\'il a droit à l\'<strong>aide juridictionnelle</strong>, l\'État paie l\'avocat (coût nul). Sinon, sur <strong>mandat écrit</strong>, l\'association peut prendre en charge / avancer les frais et les récupérer via la condamnation de NMH.</p>';
    echo '</div>';

    echo '<h2>Comment NMH finit par tout payer</h2>';
    echo '<div class="citations">';
    echo '<ul>';
    echo '<li><strong>Les travaux</strong> → tes <strong>factures d\'auto-entrepreneur</strong> (art. 1222 C. civ., après mise en demeure).</li>';
    echo '<li><strong>Les honoraires d\'avocat</strong> → <strong>article 700 du CPC</strong> : le juge condamne NMH (partie perdante) à rembourser les frais d\'avocat.</li>';
    echo '<li><strong>Les frais de procédure</strong> (dépens) → <strong>article 696 du CPC</strong>, à la charge de NMH.</li>';
    echo '<li><strong>Les dommages-intérêts</strong> → trouble de jouissance (art. 1231-1 et 1240 C. civ.) au locataire, et le cas échéant à l\'association (préjudice collectif).</li>';
    echo '</ul>';
    echo '</div>';

    echo '<h2>Le circuit de l\'argent</h2>';
    echo '<div class="citations">';
    echo '<p>NMH —— (travaux, art. 1222) ——▶ <strong>ton auto-entreprise</strong></p>';
    echo '<p>NMH —— (art. 700 + dépens + D.-I. collectif) ——▶ <strong>l\'association</strong> ——▶ paie les <strong>avocats</strong></p>';
    echo '<p>NMH —— (dommages-intérêts) ——▶ <strong>le locataire</strong></p>';
    echo '<p>État (aide juridictionnelle) ——▶ <strong>l\'avocat</strong> (si le locataire est éligible)</p>';
    echo '</div>';

    echo '<h2>Garde-fous — pour que ce soit solide et légal</h2>';
    echo '<div class="citations">';
    echo '<ul>';
    echo '<li><strong>Séparer strictement</strong> le compte de l\'auto-entreprise et celui de l\'association. Deux comptabilités distinctes.</li>';
    echo '<li>Tu ne « perçois » pas personnellement l\'argent de l\'association : <strong>c\'est l\'association qui encaisse</strong> ; en tant que président tu peux être <strong>remboursé de tes frais sur justificatifs</strong>, mais les fonds appartiennent à l\'association (sinon : gestion de fait / abus de confiance).</li>';
    echo '<li><strong>Conventions écrites</strong> : mandat locataire → association ; convention association ↔ avocats (qui paie quoi, avances, art. 700).</li>';
    echo '<li><strong>Honoraires de résultat</strong> : possibles en complément d\'honoraires de base, mais le « <strong>quota litis</strong> » pur (honoraires uniquement en % du gain) est interdit.</li>';
    echo '<li><strong>Activité économique de l\'association</strong> : si elle facture des prestations, surveiller le seuil de fiscalisation et tenir une comptabilité ; au besoin, garder la facturation de travaux côté auto-entreprise.</li>';
    echo '<li><strong>Faire valider</strong> le schéma par un expert-comptable et par les avocats.</li>';
    echo '</ul>';
    echo '</div>';

    echo '<p style="margin-top:16px">Fait à Nantes, le ' . esc_html(wp_date('j F Y')) . '.</p>';
    echo '</div>';

    lfi_nct_app_screen_close(false);
}

function lfi_nct_app_view_cadre_juridique() {
    if (!lfi_nct_app_guard_brigade()) return;
    if (function_exists('lfi_nct_travaux_guard') && !lfi_nct_travaux_guard()) return;
    lfi_nct_app_screen_open('⚖️ Cadre juridique de la facturation', 'Comment facturer NMH légalement');

    echo '<div class="lfi-app-help" style="background:#fff8e6;border-left:4px solid #bd8600">';
    echo '⚠️ <strong>Ceci n\'est pas un avis d\'avocat.</strong> Avant d\'engager des montants importants, fais valider ton montage par l\'<strong>ADIL 44</strong> (consultation juridique logement gratuite — 02 40 89 30 15) ou un avocat. Les règles ci-dessous sont le cadre général ; chaque cas a ses nuances.';
    echo '</div>';

    $blocks = [
        ['✅ Les TRAVAUX : facturables à NMH', '#186a3b',
         'Réparer ce que NMH aurait dû réparer (moisissures, VMC, plomberie…) est <strong>facturable au bailleur</strong> via le mécanisme de substitution :<br><br>'
         . '<strong>Article 1222 du Code civil</strong> : après mise en demeure, le locataire peut faire exécuter les travaux par un tiers, aux frais du bailleur.<br><br>'
         . 'La chaîne : (1) le <strong>locataire est ton client</strong> → (2) il signe le <strong>mandat/devis</strong> → (3) <strong>mise en demeure</strong> à NMH → (4) tu fais les travaux, tu factures → (5) <strong>subrogation</strong> (art. 1346 CC) → tu réclames à NMH → (6) tribunal si refus.'],

        ['⚠️ La VISITE seule : fragile', '#bd8600',
         'Facturer une « visite » directement à NMH est contestable : ils diront « on n\'a rien commandé ». Pour la rendre solide :<br><br>'
         . '• <strong>L\'intégrer au devis des travaux</strong> (déplacement + diagnostic + réparation en une seule facture) — c\'est ce que fait tout artisan, imparable ;<br>'
         . '• OU la <strong>facturer au locataire</strong>, qui la réclame ensuite à NMH comme <strong>préjudice</strong> (dommages-intérêts) devant le juge.'],

        ['✅ L\'AIDE AUX DÉMARCHES : via ton association', '#186a3b',
         'Rédiger des courriers ou conseiller <strong>contre rémunération</strong> serait réservé aux professions réglementées (loi n° 71-1130, art. 54). MAIS ton association <strong>' . esc_html((function_exists('lfi_nct_association') ? lfi_nct_association()['nom'] : 'Union des quartiers libres')) . '</strong> peut légalement <strong>assister ses membres</strong> (art. 63-66 loi 71-1130).<br><br>'
         . '<strong>La clé :</strong> fais <strong>adhérer le locataire</strong> à l\'association (adhésion gratuite) → ensuite l\'asso peut écrire en son nom, monter le dossier, et même agir en justice pour lui.<br><br>'
         . '<strong>3 conditions :</strong> (1) l\'objet des statuts doit couvrir le logement/cadre de vie ; (2) l\'argent de l\'asso ne va jamais dans ta poche (tu es bénévole) ; (3) transparence sur le fait que tu fais aussi les travaux (le locataire reste libre de choisir un autre artisan).'],

        ['❌ Au nom du « GA LFI » : non', '#a30b25',
         'Un groupe d\'action est un <strong>mouvement politique</strong>, il ne peut pas émettre de factures ni avoir d\'activité commerciale.<br><br>'
         . 'Toutes tes factures sont émises <strong>en ton nom d\'auto-entrepreneur</strong> (Fabrice Doucet). Le GA, c\'est seulement <strong>comment tu as rencontré</strong> le locataire — jamais l\'émetteur de la facture.'],
    ];

    foreach ($blocks as $b) {
        echo '<div style="background:#fff;border-left:4px solid ' . $b[1] . ';border-radius:10px;padding:14px 16px;margin:12px 0;box-shadow:0 1px 3px rgba(0,0,0,.05)">';
        echo '<div style="font-weight:800;color:' . $b[1] . ';font-size:1.05em;margin-bottom:6px">' . $b[0] . '</div>';
        echo '<div style="font-size:.92em;line-height:1.6;color:#333">' . $b[2] . '</div>';
        echo '</div>';
    }

    /* Bloc essentiel : ce que l'asso peut VRAIMENT faire en justice */
    echo '<div style="background:#fff;border:2px solid #c8102e;border-radius:10px;padding:14px 16px;margin:14px 0">';
    echo '<div style="font-weight:800;color:#c8102e;margin-bottom:8px">⚖️ Ester en justice : la nuance à connaître (vérifiée)</div>';
    echo '<div style="font-size:.92em;line-height:1.6;color:#333">';
    echo '<strong>1. Aider UN locataire (mandat écrit) → ✅ aucune ancienneté requise.</strong> L\'asso l\'accompagne, rédige ses courriers, monte son dossier ; le locataire reste la partie. Possible dès le 1er jour. <em>C\'est ton cas le plus fréquent.</em><br><br>';
    echo '<strong>2. Action CONJOINTE (plusieurs locataires, même bailleur — art. 24-1 loi 89-462) → conditions.</strong> L\'asso doit être <strong>affiliée</strong> à une organisation siégeant à la Commission Nationale de Concertation (CNL, CLCV, CGL…) ou <strong>agréée</strong>. Pas une question d\'âge, mais d\'agrément/affiliation.<br><br>';
    echo '<strong>3. Agir EN SON NOM pour l\'intérêt général (ex. partie civile au pénal) → là, le « 5 ans » s\'applique</strong> (asso régulièrement déclarée depuis au moins 5 ans, art. 2-1 et s. CPP et textes spéciaux).<br><br>';
    echo '💡 <strong>Le raccourci malin :</strong> <strong>affilie ton association à la CNL ou la CLCV</strong> (fédérations de locataires) → tu obtiens le standing pour les actions collectives <strong>tout de suite</strong>, sans attendre 5 ans.';
    echo '</div></div>';

    $asso_nom = function_exists('lfi_nct_association') ? lfi_nct_association()['nom'] : 'Union des quartiers libres';
    echo '<div style="background:#e8f5ea;border-radius:10px;padding:14px 16px;margin:14px 0">';
    echo '<div style="font-weight:800;color:#186a3b;margin-bottom:6px">📋 Ton montage à 2 étages</div>';
    echo '<div style="font-size:.92em;line-height:1.6">';
    echo '<strong>1. L\'association ' . esc_html($asso_nom) . '</strong> → le locataire adhère, puis elle l\'<strong>accompagne</strong> : aide à la rédaction des courriers, mise en demeure, montage du dossier, saisines. <em>(Accompagnement — pas de représentation juridique : c\'est l\'avocat qui représente et plaide.)</em> Bulletin d\'adhésion générable dans chaque dossier.<br>';
    echo '<strong>2. Toi, auto-entrepreneur</strong> → les travaux physiques, facturés à NMH via substitution (art. 1222 CC), avec mandat + mise en demeure. La visite/diagnostic s\'intègre au devis des travaux.<br><br>';
    echo 'Quand NMH refuse → chaîne de recouvrement (Conciliation → Tribunal → SCHS/ARS).';
    echo '</div></div>';

    echo '<div style="margin-top:16px"><a class="btn-ghost" href="#" onclick="if(history.length>1){history.back();return false;}">↩ Retour</a></div>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  IMPORT D'EMAIL — colle un email brut, l'app détecte tout         *
 *  (expéditeur, objet, date, LOCATAIRE concerné) et le range dans   *
 *  le bon dossier juridique.                                         *
 * ============================================================== */
function lfi_nct_email_parse($raw) {
    $out = ['de' => '', 'objet' => '', 'date' => '', 'corps' => trim($raw)];
    if (preg_match('/^(?:de|from)\s*:\s*(.+)$/im', $raw, $m)) $out['de'] = trim($m[1]);
    if (!$out['de'] && preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $raw, $m)) $out['de'] = $m[0];
    if (preg_match('/^(?:objet|subject)\s*:\s*(.+)$/im', $raw, $m)) $out['objet'] = trim($m[1]);
    if (preg_match('/^(?:date|envoyé|le)\s*:\s*(.+)$/im', $raw, $m)) $out['date'] = trim($m[1]);
    return $out;
}

function lfi_nct_email_detect_tenant($texte) {
    if (!defined('LFI_NCT_ROLE_TENANT')) return [];
    global $wpdb;
    $texte_l = ' ' . strtolower(remove_accents($texte)) . ' ';
    $tenants = get_users(['role' => LFI_NCT_ROLE_TENANT, 'fields' => ['ID', 'display_name'], 'number' => 500]);
    $scores = [];
    foreach ($tenants as $u) {
        $score = 0;
        $full = get_userdata($u->ID);
        foreach ([$full->last_name, $full->first_name] as $part) {
            $p = strtolower(remove_accents(trim($part)));
            if (strlen($p) >= 3 && strpos($texte_l, ' ' . $p) !== false) $score += 3;
        }
        $rid = (int) get_user_meta($u->ID, 'lfi_nct_response_id', true);
        if ($rid) {
            $resp = $wpdb->get_row($wpdb->prepare("SELECT adresse FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid));
            if ($resp && $resp->adresse) {
                $rue = strtolower(remove_accents(preg_replace('/^\s*\d+\s*(bis|ter)?\s*/i', '', $resp->adresse)));
                $rue = trim(preg_replace('/^(rue|avenue|boulevard|impasse|place|allee|chemin)\s+(de\s+la\s+|de\s+l.|des\s+|du\s+|de\s+|d.|la\s+|le\s+|les\s+)?/i', '', $rue));
                if (strlen($rue) >= 5 && strpos($texte_l, $rue) !== false) $score += 2;
            }
        }
        if ($score > 0) $scores[$u->ID] = $score;
    }
    arsort($scores);
    return $scores;
}

function lfi_nct_app_view_email_import() {
    if (!lfi_nct_app_guard_brigade()) return;
    global $wpdb;
    $owner = (int) lfi_nct_dossier_owner_id();
    $td = $wpdb->prefix . 'lfi_nct_dossiers_locataires';

    if (!empty($_POST['lfi_email_import_save']) && check_admin_referer('lfi_email_import_save')) {
        $uid   = (int) ($_POST['tenant_uid'] ?? 0);
        $sens  = ($_POST['sens'] ?? 'recu') === 'envoye' ? 'envoye' : 'recu';
        $de    = sanitize_text_field(wp_unslash($_POST['email_de'] ?? ''));
        $objet = sanitize_text_field(wp_unslash($_POST['email_objet'] ?? ''));
        $corps = sanitize_textarea_field(wp_unslash($_POST['email_corps'] ?? ''));
        $u = $uid ? get_userdata($uid) : null;
        /* Cloisonnement : on n'importe que pour un locataire du GA courant. */
        $in_scope = !function_exists('lfi_nct_uid_in_scope') || lfi_nct_uid_in_scope($uid);
        if ($u && $in_scope && in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) {
            $dossier = $wpdb->get_row($wpdb->prepare("SELECT * FROM $td WHERE owner_user_id = %d AND tenant_user_id = %d ORDER BY id DESC LIMIT 1", $owner, $uid));
            if (!$dossier) {
                $resp = null;
                $rid = (int) get_user_meta($uid, 'lfi_nct_response_id', true);
                if ($rid) $resp = $wpdb->get_row($wpdb->prepare("SELECT adresse, etage FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid));
                $wpdb->insert($td, [
                    'owner_user_id' => $owner, 'tenant_user_id' => $uid,
                    'tenant_prenom' => $u->first_name, 'tenant_nom' => $u->last_name ?: $u->display_name,
                    'tenant_adresse'=> $resp->adresse ?? '', 'tenant_etage' => $resp->etage ?? '',
                    'tenant_email'  => $u->user_email, 'tenant_tel' => (string) get_user_meta($uid, 'lfi_nct_tel', true),
                    'statut' => 'ouvert',
                ]);
                $dossier = $wpdb->get_row($wpdb->prepare("SELECT * FROM $td WHERE id = %d", (int) $wpdb->insert_id));
            }
            $logs = json_decode($dossier->notes ?? '', true);
            if (!is_array($logs)) $logs = ['__notes' => $dossier->notes ?? ''];
            $key = ($sens === 'envoye') ? 'email_log' : 'email_recu';
            $logs[$key] = $logs[$key] ?? [];
            $entry = ['objet' => $objet, 'corps' => $corps, 'date' => current_time('Y-m-d H:i')];
            if ($sens === 'envoye') $entry['to'] = $de; else $entry['de'] = $de;
            $logs[$key][] = $entry;
            $wpdb->update($td, ['notes' => wp_json_encode($logs, JSON_UNESCAPED_UNICODE)], ['id' => $dossier->id, 'owner_user_id' => $owner]);
            wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $dossier->id, 'email_recu_ok' => 1]));
            exit;
        }
    }

    lfi_nct_app_screen_open('📥 Importer un email', 'Colle l\'email — l\'app détecte le locataire et range tout');

    $raw = isset($_POST['raw_email']) ? wp_unslash($_POST['raw_email']) : '';
    $analyzed = !empty($_POST['lfi_email_import_analyze']) && check_admin_referer('lfi_email_import_analyze');

    if (!$analyzed || trim($raw) === '') {
        echo '<div class="lfi-app-help">Colle l\'email reçu (ou envoyé). L\'app détecte automatiquement l\'expéditeur, l\'objet et le <strong>locataire concerné</strong> (par son nom ou son adresse), puis le range dans son dossier juridique.</div>';
        echo '<form method="post" class="lfi-app-form">';
        wp_nonce_field('lfi_email_import_analyze');
        echo '<input type="hidden" name="lfi_email_import_analyze" value="1">';
        echo '<label>Email complet (copier-coller)<textarea name="raw_email" rows="12" placeholder="De : yvonnic.morineau@nmh.fr&#10;Objet : Mme Fadila — relogement&#10;&#10;Bonjour, suite à votre courrier concernant Mme Fadila au 8 rue de Saint-Jean-de-Luz…" required>' . esc_textarea($raw) . '</textarea></label>';
        echo '<button type="submit" class="btn-primary big">🔎 Analyser l\'email</button>';
        echo '</form>';
        lfi_nct_app_screen_close();
        return;
    }

    $parsed = lfi_nct_email_parse($raw);
    $scores = lfi_nct_email_detect_tenant($raw);
    $best_uid = !empty($scores) ? (int) array_key_first($scores) : 0;

    echo '<div class="lfi-app-help" style="background:#e8f5ea;border-left:4px solid #186a3b">✅ <strong>Analyse terminée.</strong> Vérifie ce que l\'app a détecté, puis enregistre.</div>';
    if ($best_uid) {
        $bu = get_userdata($best_uid);
        echo '<div class="lfi-app-card"><div class="head"><div class="who">🎯 Locataire détecté</div><div class="badge" style="background:#186a3b;color:#fff">' . esc_html($bu->display_name) . '</div></div>';
        echo '<div class="com">Détecté dans le texte. Corrige ci-dessous si besoin.</div></div>';
    } else {
        echo '<div class="lfi-app-help" style="background:#fff8e6;border-left:4px solid #bd8600">⚠ Aucun locataire détecté. Choisis-le manuellement.</div>';
    }

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_email_import_save');
    echo '<input type="hidden" name="lfi_email_import_save" value="1">';
    $tenants = get_users(['role' => LFI_NCT_ROLE_TENANT, 'fields' => ['ID', 'display_name'], 'number' => 500, 'orderby' => 'display_name']);
    echo '<label>Locataire concerné<select name="tenant_uid" required><option value="">— choisir —</option>';
    foreach ($tenants as $tu) {
        $lbl = $tu->display_name . (isset($scores[$tu->ID]) ? ' ✓ (détecté)' : '');
        echo '<option value="' . (int) $tu->ID . '" ' . selected($best_uid, $tu->ID, false) . '>' . esc_html($lbl) . '</option>';
    }
    echo '</select></label>';
    echo '<label>Sens<select name="sens"><option value="recu">📥 Email REÇU (de NMH)</option><option value="envoye">📤 Email ENVOYÉ (à NMH)</option></select></label>';
    echo '<label>Expéditeur / destinataire<input type="text" name="email_de" value="' . esc_attr($parsed['de']) . '"></label>';
    echo '<label>Objet<input type="text" name="email_objet" value="' . esc_attr($parsed['objet']) . '"></label>';
    echo '<label>Contenu<textarea name="email_corps" rows="8">' . esc_textarea($parsed['corps']) . '</textarea></label>';
    echo '<button type="submit" class="btn-primary big">💾 Ranger dans le dossier du locataire</button>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('email-import')) . '">↩ Recommencer</a>';
    echo '</form>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  ESPACE ASSOCIATION — hub central (config, documents, factures)  *
 * ============================================================== */
function lfi_nct_app_view_association() {
    if (!lfi_nct_app_guard_brigade()) return;
    $asso = function_exists('lfi_nct_association') ? lfi_nct_association() : ['nom' => 'Union des Quartiers Libres'];
    $is_admin = current_user_can('manage_options');

    lfi_nct_app_screen_open('🏛 ' . ($asso['nom'] ?: 'Association'), 'Espace association — documents & gestion');

    /* Carte d'identité de l'asso */
    echo '<div class="lfi-app-card">';
    echo '<div class="head"><div class="who">📇 Identité</div></div>';
    echo '<div class="meta">';
    if (!empty($asso['rna']))       echo '<span class="meta-chip">RNA ' . esc_html($asso['rna']) . '</span>';
    if (!empty($asso['siege']))     echo '<span class="meta-chip">📍 ' . esc_html(trim($asso['siege'] . ' ' . ($asso['cp_ville'] ?? ''))) . '</span>';
    if (!empty($asso['president'])) echo '<span class="meta-chip">👤 ' . esc_html($asso['president']) . ' (prés.)</span>';
    if (!empty($asso['secretaire']))echo '<span class="meta-chip">✍️ ' . esc_html($asso['secretaire']) . ' (secr.)</span>';
    if (!empty($asso['email']))     echo '<span class="meta-chip">✉️ ' . esc_html($asso['email']) . '</span>';
    echo '</div>';
    if ($is_admin) {
        echo '<div class="row-actions"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('facturation-params')) . '">⚙️ Modifier les infos & signatures</a></div>';
    }
    echo '</div>';

    /* Tuiles de documents */
    echo '<h3 style="margin:18px 0 8px;color:#c8102e">📄 Documents de l\'association</h3>';
    echo '<div class="lfi-app-grid">';
    $tiles = [];
    if ($is_admin) {
        $tiles[] = ['📜', 'Modifier les statuts', 'Convocation + PV + statuts à jour, signés', lfi_nct_app_url('asso-statuts')];
    }
    $tiles[] = ['⚖️', 'Cadre juridique', 'Ce qui est facturable, comment, par qui', lfi_nct_app_url('cadre-juridique')];
    $tiles[] = ['💰', 'Montage financier', 'Faire payer NMH, financer les avocats', lfi_nct_app_url('montage-financier')];
    $tiles[] = ['🧮', 'Aide juridictionnelle', 'Savoir à l\'avance qui y a droit', lfi_nct_app_url('aj-calcul')];
    $tiles[] = ['🤝', 'Stratégie avocats', 'Note générale à envoyer aux cabinets', lfi_nct_app_url('doc-strategie-avocats')];
    $tiles[] = ['📁', 'Dossiers juridiques', 'Un dossier par locataire accompagné', lfi_nct_app_url('dossiers-juridiques')];
    $tiles[] = ['🔧', 'Interventions & factures', 'Brigade travaux, facturation NMH', lfi_nct_app_url('interventions')];
    foreach ($tiles as $t) {
        echo '<a class="lfi-app-tile" href="' . esc_url($t[3]) . '">';
        echo '<div class="ico">' . $t[0] . '</div>';
        echo '<div class="tit">' . esc_html($t[1]) . '</div>';
        echo '<div class="sub">' . esc_html($t[2]) . '</div>';
        echo '</a>';
    }
    echo '</div>';

    /* Rappel : bulletin d'adhésion se génère depuis chaque dossier locataire */
    echo '<div class="lfi-app-help" style="margin-top:14px">💡 Le <strong>bulletin d\'adhésion</strong> d\'un locataire se génère depuis SON dossier juridique (chaque locataire adhère individuellement). Ouvre un dossier dans 📁 Dossiers juridiques → bouton « 🎫 Bulletin d\'adhésion ».</div>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  DOSSIER DE MODIFICATION DES STATUTS (ester en justice + logement)*
 *  À déposer en préfecture. Imprimable / PDF.                       *
 * ============================================================== */
function lfi_nct_app_view_asso_statuts() {
    if (!(function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'))) {
        lfi_nct_app_screen_open('📜 Statuts');
        echo '<div class="lfi-app-empty">Cette page est réservée aux administrateurs du groupe. Si tu es en mode aperçu, reviens en mode admin. <a href="' . esc_url(lfi_nct_app_url('')) . '">← Accueil</a></div>';
        lfi_nct_app_screen_close(false);
        return;
    }
    $asso = function_exists('lfi_nct_association') ? lfi_nct_association() : ['nom' => 'Union des quartiers libres'];
    $nom = $asso['nom'] ?: 'Union des quartiers libres';
    $siege = trim(($asso['siege'] ?? '') . ' ' . ($asso['cp_ville'] ?? ''));
    $pres = $asso['president'] ?: '[Nom du président]';
    $secr = $asso['secretaire'] ?? '';
    $pres_adr = trim($asso['president_adresse'] ?? '') ?: ($siege ?: '14 rue de Saint-Jean-de-Luz, 44200 Nantes');
    $secr_adr = trim($asso['secretaire_adresse'] ?? '');
    /* Date de l'AGE / de signature : celle saisie, sinon la date du jour. */
    $age_date_disp = trim($asso['age_date'] ?? '') ?: wp_date('j F Y');
    /* IMPORTANT : version REDIMENSIONNÉE (medium) des signatures, jamais la
       pleine résolution — une photo de signature en plein format sature la
       mémoire de Safari iOS et fait disparaître la page. */
    $sig_url = function ($att_id) {
        if (empty($att_id)) return '';
        $u = wp_get_attachment_image_url((int) $att_id, 'medium');
        return $u ?: wp_get_attachment_url((int) $att_id);
    };
    $sig_pres_url = $sig_url($asso['sig_president'] ?? 0);
    $sig_secr_url = $sig_url($asso['sig_secretaire'] ?? 0);

    /* Helper de bloc signature : image manuscrite si dispo, sinon ligne vide */
    $sig_block = function ($role, $name, $url) {
        $h = '<div style="flex:1">';
        $h .= '<p><strong>' . esc_html($role) . '</strong>' . ($name ? '<br>' . esc_html($name) : '') . '</p>';
        if ($url) {
            $h .= '<img src="' . esc_url($url) . '" alt="signature" loading="lazy" decoding="async" style="max-height:70px;max-width:90%;margin-top:4px">';
        } else {
            $h .= '<div style="height:50px;border-bottom:1px solid #999;width:80%"></div>';
        }
        $h .= '</div>';
        return $h;
    };

    lfi_nct_app_screen_open('📜 Dossier statuts complet', 'Convocation + PV + statuts à jour');
    if (function_exists('lfi_nct_rec_doc_styles')) lfi_nct_rec_doc_styles();

    if (!$sig_pres_url || !$sig_secr_url) {
        echo '<div class="lfi-app-help no-print" style="background:#fff3f5;border-left:4px solid #c8102e">';
        echo '✍️ <strong>Signatures manquantes.</strong> Pour que la préfecture accepte (signatures manuscrites), téléverse les 2 signatures dans <a href="' . esc_url(lfi_nct_app_url('facturation-params')) . '">⚙️ Paramètres → Association</a>. Sans ça, le document s\'imprime avec des lignes vides à signer à la main.';
        echo '</div>';
    }

    /* Mode d'emploi (à l'écran, pas imprimé) */
    echo '<div class="lfi-app-help no-print" style="background:#e8f0ff;border-left:4px solid #0066a3">';
    echo '<strong>📋 Procédure (gratuite) — ce document contient la convocation, le PV ET les statuts complets à jour :</strong><br>';
    echo '1. <strong>Convoquer</strong> les membres à l\'AGE (1re page ci-dessous), en respectant le délai prévu par tes statuts (souvent 15 jours).<br>';
    echo '2. Tenir l\'<strong>Assemblée Générale Extraordinaire</strong> et voter les modifications.<br>';
    echo '3. Signer le <strong>PV</strong> (2e page) + mettre à jour le texte des statuts (articles fournis).<br>';
    echo '4. Déclarer la modification <strong>dans les 3 mois</strong> sur <strong>lecompteasso.associations.gouv.fr</strong> (ou Cerfa n° 13972*03 à la préfecture de Loire-Atlantique), en joignant le PV + les statuts mis à jour datés et signés.<br>';
    echo '<em>⚖️ Fais relire par l\'ADIL 44 ou Juris\'Asso avant dépôt. Ceci est un modèle, pas un avis d\'avocat.</em>';
    echo '</div>';

    echo '<div class="lfi-app-help no-print" style="background:#fff3f5;border-left:4px solid #c8102e">';
    echo '⚠️ <strong>Important sur la clause « ester en justice » (article 11).</strong> L\'inscrire dans les statuts est utile et légitime, mais ne suffit pas à tout débloquer :<br>';
    echo '• <strong>Accompagner UN locataire sur son mandat écrit</strong> → possible immédiatement, à tout âge de l\'asso ;<br>';
    echo '• <strong>Action collective</strong> (plusieurs locataires, même bailleur, art. 24-1 loi 89-462) → exige l\'<strong>affiliation</strong> à une fédération (CNL, CLCV…) ou l\'<strong>agrément</strong> ;<br>';
    echo '• <strong>Agir en son nom pour l\'intérêt général / partie civile</strong> → souvent <strong>5 ans</strong> d\'ancienneté requis (art. 2-1 et s. CPP).<br>';
    echo '<strong>Conseil :</strong> affilie l\'asso à la CNL ou la CLCV pour obtenir le standing collectif sans attendre.';
    echo '</div>';

    /* ============ DOC 1 : CONVOCATION À L'AGE ============ */
    echo '<div class="lfi-rec-doc">';

    echo '<h1>Convocation à l\'Assemblée Générale Extraordinaire</h1>';
    echo '<p style="text-align:center"><strong>' . esc_html($nom) . '</strong>';
    if (!empty($asso['rna'])) echo '<br>Association déclarée — n° RNA ' . esc_html($asso['rna']);
    if ($siege) echo '<br>Siège : ' . esc_html($siege);
    echo '</p>';

    echo '<div class="lieu-date">À Nantes, le ____ / ____ / 20____</div>';
    echo '<p class="objet">Objet : Convocation à l\'Assemblée Générale Extraordinaire</p>';

    echo '<p>Chère adhérente, cher adhérent,</p>';
    echo '<p>En ma qualité de président de l\'association <strong>' . esc_html($nom) . '</strong>, j\'ai l\'honneur de vous convoquer à l\'<strong>Assemblée Générale Extraordinaire</strong> qui se tiendra :</p>';

    echo '<table class="detail">';
    echo '<tr><td><strong>Date</strong></td><td><strong>' . esc_html($age_date_disp) . '</strong> à ____ h ____</td></tr>';
    echo '<tr><td><strong>Lieu</strong></td><td>' . esc_html($siege ?: '__________________________') . '</td></tr>';
    echo '</table>';

    echo '<h2>Ordre du jour</h2>';
    echo '<div class="citations"><ol>';
    echo '<li>Modification de l\'objet social (article 2) : défense des locataires, logement, lutte contre l\'habitat indigne ;</li>';
    echo '<li>Ajout d\'un article relatif aux moyens d\'action et à la <strong>capacité d\'ester en justice</strong> ;</li>';
    echo '<li>Adoption des statuts mis à jour ;</li>';
    echo '<li>Pouvoirs au président pour les formalités de déclaration en préfecture ;</li>';
    echo '<li>Questions diverses.</li>';
    echo '</ol></div>';

    echo '<p>Chaque membre peut se faire représenter par un autre membre muni d\'un <strong>pouvoir écrit</strong> (coupon ci-dessous). En cas d\'empêchement, merci de retourner votre pouvoir avant la réunion.</p>';
    echo '<p>Comptant sur votre présence, je vous prie d\'agréer mes salutations associatives.</p>';
    echo '<div class="signature">' . esc_html($pres) . '<br><em>Président</em></div>';

    echo '<div style="margin-top:26px;border:1px dashed #999;padding:14px;border-radius:8px">';
    echo '<p style="text-align:center;font-weight:700;margin-top:0">✂ — — — POUVOIR — — —</p>';
    echo '<p>Je soussigné(e) ____________________________, membre de l\'association ' . esc_html($nom) . ', donne pouvoir à ____________________________ pour me représenter et voter en mon nom à l\'Assemblée Générale Extraordinaire du ____ / ____ / 20____.</p>';
    echo '<p>Fait à ____________, le ____ / ____ / 20____. &nbsp; Signature : ____________</p>';
    echo '</div>';

    echo '</div>';

    /* Saut de page avant le PV */
    echo '<div style="page-break-before:always;height:1px"></div>';

    /* ============ DOC 2 : PV d'AGE ============ */
    echo '<div class="lfi-rec-doc">';

    echo '<h1>Procès-verbal d\'Assemblée Générale Extraordinaire</h1>';
    echo '<p style="text-align:center"><strong>' . esc_html($nom) . '</strong>';
    if (!empty($asso['rna'])) echo '<br>Association déclarée — n° RNA ' . esc_html($asso['rna']);
    if ($siege) echo '<br>Siège : ' . esc_html($siege);
    echo '</p>';

    echo '<p>Le <strong>' . esc_html($age_date_disp) . '</strong>, à ____ h ____, les membres de l\'association <strong>' . esc_html($nom) . '</strong> se sont réunis en Assemblée Générale Extraordinaire au siège de l\'association, sur convocation du président, à l\'effet de délibérer sur l\'ordre du jour suivant :</p>';
    echo '<ul><li>Modification de l\'objet social ;</li><li>Ajout d\'un article relatif aux moyens d\'action et à la capacité d\'ester en justice ;</li><li>Mise à jour des statuts ;</li><li>Pouvoirs pour les formalités de déclaration.</li></ul>';
    echo '<p>L\'assemblée, après en avoir délibéré, adopte les résolutions suivantes :</p>';

    echo '<h2>Première résolution — Modification de l\'objet (article 2)</h2>';
    echo '<p>L\'assemblée décide de <strong>compléter l\'article 2 (Objet)</strong> des statuts en y ajoutant, après les alinéas existants relatifs à la culture et à l\'édition, les deux alinéas suivants :</p>';
    echo '<div class="citations">';
    echo '<p>« — de défendre les intérêts matériels et moraux, individuels et collectifs, des habitants et des <strong>locataires</strong> des quartiers populaires, notamment en matière de <strong>logement</strong>, de conditions d\'habitat, de salubrité, de décence, de cadre de vie, de sécurité et d\'accès aux services publics ;</p>';
    echo '<p>— d\'<strong>informer, conseiller et accompagner</strong> les habitants et locataires dans leurs démarches amiables et contentieuses, y compris auprès des bailleurs, des administrations et des juridictions, et de <strong>lutter contre l\'habitat indigne</strong> et la non-décence. »</p>';
    echo '<p>Il est en outre ajouté la mention : « À ce titre, l\'association constitue une <strong>association de défense des intérêts des locataires</strong> au sens de la loi n° 89-462 du 6 juillet 1989. »</p>';
    echo '</div>';
    echo '<p><em>Adoptée à la majorité des deux tiers — ___ voix pour, ___ contre, ___ abstentions.</em></p>';

    echo '<h2>Deuxième résolution — Capacité d\'ester en justice (nouvel article 9)</h2>';
    echo '<p>L\'assemblée décide d\'insérer un nouvel <strong>article 9 « Moyens d\'action et capacité d\'ester en justice »</strong> (les articles suivants étant renumérotés en conséquence : Bureau → art. 10, Réunions → art. 11, Modification des statuts → art. 12, Dissolution → art. 13), ainsi rédigé :</p>';
    echo '<div class="citations">';
    echo '<p><strong>« Article 9 — Moyens d\'action et capacité d\'ester en justice.</strong> Pour la réalisation de son objet, l\'association peut notamment :</p>';
    echo '<ul>';
    echo '<li>assister et représenter ses membres dans leurs démarches amiables et contentieuses ;</li>';
    echo '<li><strong>agir en justice, tant en demande qu\'en défense</strong>, devant toutes juridictions, pour la défense de ses intérêts propres comme de l\'<strong>intérêt collectif</strong> entrant dans son objet ;</li>';
    echo '<li>agir en justice, sur <strong>mandat exprès et écrit</strong>, pour la défense des <strong>intérêts individuels de ses membres</strong>, notamment locataires, dans les conditions prévues par la loi du 6 juillet 1989 ;</li>';
    echo '<li>conclure toute convention, recevoir cotisations, dons et subventions concourant à son objet.</li>';
    echo '</ul>';
    echo '<p>Le <strong>président représente l\'association en justice</strong> et dans tous les actes de la vie civile ; il peut agir en justice au nom de l\'association après autorisation du bureau. »</p>';
    echo '</div>';
    echo '<p><em>Adoptée à la majorité des deux tiers — ___ voix pour, ___ contre, ___ abstentions.</em></p>';

    echo '<h2>Troisième résolution — Pouvoirs</h2>';
    echo '<p>L\'assemblée donne tous pouvoirs au président, <strong>' . esc_html($pres) . '</strong>, à l\'effet d\'accomplir les formalités de déclaration et de publication de la présente modification auprès de la préfecture de la Loire-Atlantique.</p>';
    echo '<p><em>Adoptée à l\'unanimité.</em></p>';

    echo '<p>L\'ordre du jour étant épuisé, la séance est levée à ____ h ____.</p>';

    echo '<div style="margin-top:40px;display:flex;gap:40px;justify-content:space-between">';
    echo $sig_block('Le Président', $pres, $sig_pres_url);
    echo $sig_block('Le Secrétaire', $secr, $sig_secr_url);
    echo '</div>';

    echo '<div class="pj"><strong>À joindre à la déclaration en préfecture :</strong> le présent procès-verbal daté et signé + un exemplaire des statuts mis à jour, daté et signé, portant la mention « Statuts modifiés par l\'AGE du ' . esc_html($age_date_disp) . ' ».</div>';

    echo '</div>';

    /* Saut de page avant les statuts complets */
    echo '<div style="page-break-before:always;height:1px"></div>';

    /* ============ DOC 3 : STATUTS COMPLETS À JOUR ============ */
    echo '<div class="lfi-rec-doc">';

    echo '<h1>Statuts de l\'association « ' . esc_html($nom) . ' »</h1>';
    echo '<p style="text-align:center;font-style:italic">Statuts mis à jour par l\'Assemblée Générale Extraordinaire du ' . esc_html($age_date_disp) . '</p>';

    echo '<h2>Article 1 — Dénomination</h2>';
    echo '<p>Il est fondé, entre les adhérent·es aux présents statuts, une association régie par la loi du 1<sup>er</sup> juillet 1901 et le décret du 16 août 1901, ayant pour titre : <strong>' . esc_html($nom) . '</strong>.</p>';

    echo '<h2>Article 2 — Objet</h2>';
    echo '<p>L\'association a pour objet :</p>';
    echo '<ul>';
    echo '<li>de produire de la culture alternative à l\'attention de la jeunesse de Nantes et de ses environs ;</li>';
    echo '<li>de créer, produire, éditer, diffuser et vendre des journaux, revues, livres, zines, brochures ou tout autre support écrit ou artistique ;</li>';
    echo '<li>de promouvoir l\'expression artistique, politique et sociale des habitants des quartiers populaires ;</li>';
    echo '<li>d\'organiser des ateliers d\'écriture, des résidences, des expositions ou des rencontres autour de la création et de l\'édition ;</li>';
    echo '<li>de favoriser l\'autonomie éditoriale, l\'autoproduction et la création collective ;</li>';
    echo '<li><strong>de défendre les intérêts matériels et moraux, individuels et collectifs, des habitants et des locataires des quartiers populaires, notamment en matière de logement, de conditions d\'habitat, de salubrité, de décence, de cadre de vie, de sécurité et d\'accès aux services publics ;</strong></li>';
    echo '<li><strong>d\'informer, conseiller et accompagner les habitants et locataires dans leurs démarches amiables et contentieuses, y compris auprès des bailleurs, des administrations et des juridictions, et de lutter contre l\'habitat indigne et la non-décence.</strong></li>';
    echo '</ul>';
    echo '<p><strong>À ce titre, l\'association constitue une association de défense des intérêts des locataires au sens de la loi n° 89-462 du 6 juillet 1989.</strong></p>';

    echo '<h2>Article 3 — Siège social</h2>';
    echo '<p>Le siège social est fixé au : <strong>' . esc_html($siege ?: '14 rue de Saint-Jean-de-Luz, 44200 Nantes') . '</strong>. Il pourra être transféré par simple décision du bureau.</p>';

    echo '<h2>Article 4 — Durée</h2>';
    echo '<p>La durée de l\'association est illimitée.</p>';

    echo '<h2>Article 5 — Membres</h2>';
    echo '<p>L\'association se compose de : membres fondateurs ; membres actifs (adhérents) ; membres d\'honneur (personnes soutenant moralement ou matériellement l\'association, sans droit de vote).</p>';

    echo '<h2>Article 6 — Admission</h2>';
    echo '<p>L\'adhésion est ouverte à toute personne physique ou morale partageant les valeurs de l\'association. L\'adhésion implique l\'acceptation sans réserve des présents statuts.</p>';

    echo '<h2>Article 7 — Radiation</h2>';
    echo '<p>La qualité de membre se perd par : la démission ; le décès ; l\'exclusion prononcée par le bureau pour motif grave ou contraire aux valeurs de l\'association.</p>';

    echo '<h2>Article 8 — Ressources</h2>';
    echo '<p>Les ressources de l\'association comprennent notamment : les cotisations des adhérents ; les dons autorisés par la loi ; les recettes provenant de la vente de ses publications ou de ses activités culturelles en lien avec l\'objet ; toute autre ressource conforme à la législation.</p>';

    echo '<h2>Article 9 — Moyens d\'action et capacité d\'ester en justice</h2>';
    echo '<p>Pour la réalisation de son objet, l\'association peut notamment :</p>';
    echo '<ul>';
    echo '<li>assister ses membres dans leurs démarches amiables et contentieuses ;</li>';
    echo '<li><strong>agir en justice, tant en demande qu\'en défense, devant toutes les juridictions</strong>, pour la défense de ses intérêts propres comme de l\'intérêt collectif entrant dans son objet ;</li>';
    echo '<li><strong>agir en justice, sur mandat exprès et écrit, pour la défense des intérêts individuels de ses membres</strong>, notamment locataires, dans les conditions prévues par la loi du 6 juillet 1989 ;</li>';
    echo '<li>conclure toute convention et recevoir cotisations, dons et subventions concourant à son objet.</li>';
    echo '</ul>';
    echo '<p><strong>Le président représente l\'association en justice et dans tous les actes de la vie civile</strong> ; il peut agir en justice au nom de l\'association après autorisation du bureau.</p>';

    echo '<h2>Article 10 — Bureau</h2>';
    echo '<p>L\'association est dirigée par un bureau composé au minimum de 2 personnes. Les membres du bureau sont élus à main levée (ou à bulletin secret si demandé) pour une durée illimitée. Le bureau actuel est composé de :</p>';
    echo '<ul>';
    echo '<li><strong>Président :</strong> ' . esc_html($pres) . ', domicilié au ' . esc_html($pres_adr) . ' ;</li>';
    echo '<li><strong>Secrétaire :</strong> ' . esc_html($secr ?: 'Gwenaëlle Gourdien') . ', domiciliée au ' . esc_html($secr_adr ?: $pres_adr) . '.</li>';
    echo '</ul>';
    echo '<p>Le bureau peut être élargi à d\'autres membres sur décision de l\'Assemblée Générale. Les fonctions des membres du bureau sont bénévoles.</p>';

    echo '<h2>Article 11 — Réunions</h2>';
    echo '<ul>';
    echo '<li>Une Assemblée Générale se tient au moins une fois par an.</li>';
    echo '<li>Elle approuve le rapport d\'activité, les comptes, oriente les projets et peut modifier les statuts.</li>';
    echo '<li>Les décisions se prennent à la majorité des membres présents ou représentés.</li>';
    echo '</ul>';

    echo '<h2>Article 12 — Modification des statuts</h2>';
    echo '<p>Les statuts ne peuvent être modifiés que par l\'Assemblée Générale convoquée à cet effet, à la majorité des deux tiers des membres présents.</p>';

    echo '<h2>Article 13 — Dissolution</h2>';
    echo '<p>En cas de dissolution, l\'Assemblée Générale désigne un ou plusieurs liquidateurs et attribue l\'actif à une structure poursuivant un but analogue, conformément à la loi.</p>';

    echo '<p style="margin-top:30px">Fait à Nantes, le <strong>' . esc_html($age_date_disp) . '</strong></p>';
    echo '<div style="margin-top:10px;display:flex;gap:40px;justify-content:space-between">';
    echo $sig_block('Le Président', $pres, $sig_pres_url);
    echo $sig_block('Le Secrétaire', $secr, $sig_secr_url);
    echo '</div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  BULLETIN D'ADHÉSION à l'association (clé de l'accompagnement)    *
 * ============================================================== */
function lfi_nct_app_view_dossier_doc_adhesion() {
    if (!lfi_nct_app_guard_brigade()) return;
    $ctx = lfi_nct_dossier_doc_open('🎫 Bulletin d\'adhésion');
    extract($ctx);
    $asso = function_exists('lfi_nct_association') ? lfi_nct_association() : ['nom' => 'Union des quartiers libres'];

    echo '<div class="lfi-rec-doc">';
    echo '<h1>Bulletin d\'adhésion</h1>';
    echo '<p style="text-align:center;font-weight:700;font-size:1.1em;margin-bottom:6px">' . esc_html($asso['nom']) . '</p>';
    if (!empty($asso['rna'])) echo '<p style="text-align:center;font-style:italic;margin-bottom:20px">Association loi 1901 — n° RNA ' . esc_html($asso['rna']) . '</p>';

    echo '<p>Je soussigné(e) <strong>' . esc_html($tenant_full ?: '________________________') . '</strong>,';
    if ($dossier->tenant_adresse) echo ' demeurant ' . esc_html($dossier->tenant_adresse) . ($dossier->tenant_etage ? ', étage ' . esc_html($dossier->tenant_etage) : '') . ',';
    echo ' déclare adhérer à l\'association <strong>' . esc_html($asso['nom']) . '</strong>';
    if (!empty($asso['objet'])) echo ', dont l\'objet est : <em>' . esc_html($asso['objet']) . '</em>';
    echo '.</p>';

    $cot = trim((string) ($asso['cotisation'] ?? ''));
    $cot_free = ($cot === '' || (float) $cot == 0);
    if ($cot_free) {
        echo '<p>L\'adhésion à l\'association est <strong>gratuite</strong>. Je reconnais avoir pris connaissance des statuts et de l\'objet de l\'association, et y adhérer librement.</p>';
    } else {
        echo '<p>Je verse à ce titre ma cotisation annuelle de <strong>' . esc_html($cot) . ' €</strong> et reconnais avoir pris connaissance des statuts de l\'association.</p>';
    }

    echo '<h2>Demande d\'accompagnement</h2>';
    echo '<p>En qualité de membre, je sollicite l\'assistance de l\'association dans mes démarches relatives aux désordres affectant mon logement (relations avec le bailleur, courriers, constitution du dossier, saisines administratives), conformément à l\'objet statutaire de l\'association et aux articles 63 à 66 de la loi n° 71-1130 du 31 décembre 1971.</p>';

    echo '<table class="detail">';
    echo '<tr><td>Téléphone</td><td>' . esc_html($dossier->tenant_tel ?: '________________') . '</td></tr>';
    echo '<tr><td>Email</td><td>' . esc_html($dossier->tenant_email ?: '________________') . '</td></tr>';
    echo '<tr><td>Date de naissance</td><td>____ / ____ / ________</td></tr>';
    echo '<tr><td>Date d\'adhésion</td><td><strong>' . esc_html(wp_date('j F Y')) . '</strong></td></tr>';
    echo '</table>';

    echo '<div style="margin-top:40px;display:flex;gap:40px;justify-content:space-between">';
    echo '<div style="flex:1"><p><strong>Le membre adhérent :</strong></p><p>Signature :</p><div style="height:50px;border-bottom:1px solid #999;width:80%"></div></div>';
    echo '<div style="flex:1"><p><strong>Pour l\'association, le/la président·e :</strong></p><p>' . esc_html($asso['president'] ?: '') . '</p><div style="height:50px;border-bottom:1px solid #999;width:80%"></div></div>';
    echo '</div>';

    echo '<div class="pj"><strong>Important :</strong> cette adhésion ouvre le droit à l\'accompagnement de l\'association. Les éventuels travaux matériels restent réalisés et facturés séparément par un intervenant professionnel choisi librement par l\'adhérent.</div>';

    echo '<div class="pj" style="font-size:.85em;margin-top:10px"><strong>Protection des données (RGPD).</strong> Les informations recueillies sur ce bulletin sont enregistrées par l\'association ' . esc_html($asso['nom']) . ' pour la seule gestion de votre adhésion et de votre accompagnement. Elles sont destinées au bureau de l\'association, conservées pour la durée de l\'adhésion, et ne sont jamais cédées à des tiers. Conformément au RGPD (règlement UE 2016/679) et à la loi « Informatique et Libertés », vous disposez d\'un droit d\'accès, de rectification et de suppression de vos données' . (!empty($asso['email']) ? ', en écrivant à ' . esc_html($asso['email']) : '') . '. <br>☐ J\'accepte que mes données soient traitées à ces fins. &nbsp;&nbsp; Signature : ____________________</div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  RAPPORT DE VISITE (PDF imprimable)                              *
 * ============================================================== */
function lfi_nct_app_view_dossier_doc_rapport_visite() {
    if (!lfi_nct_app_guard_brigade()) return;
    $ctx = lfi_nct_dossier_doc_open('📄 Rapport de visite');
    extract($ctx);

    echo '<div class="lfi-rec-doc">';

    echo '<h1>Rapport de visite de logement</h1>';
    echo '<p style="text-align:center;font-style:italic;margin-bottom:24px">Constat des désordres — Groupe d\'Action LFI Nantes Sud / Clos Toreau</p>';

    echo '<table class="detail">';
    echo '<tr><td><strong>Logement visité</strong></td><td>' . esc_html($tenant_logement ?: '—') . '</td></tr>';
    echo '<tr><td><strong>Locataire</strong></td><td>' . esc_html($tenant_full ?: '—') . '</td></tr>';
    echo '<tr><td><strong>Bailleur</strong></td><td>' . esc_html($bailleur['nom'] ?? 'Nantes Métropole Habitat') . '</td></tr>';
    if ($dossier->visite_date) echo '<tr><td><strong>Date de la visite</strong></td><td>' . esc_html(wp_date('j F Y', strtotime($dossier->visite_date))) . '</td></tr>';
    if ($dossier->visite_duree) echo '<tr><td><strong>Durée de la visite</strong></td><td>' . esc_html($dossier->visite_duree) . '</td></tr>';
    if (!empty($presta['nom'])) echo '<tr><td><strong>Constaté par</strong></td><td>' . esc_html($presta['nom']) . ', membre du Groupe d\'Action</td></tr>';
    echo '</table>';

    echo '<h2>Désordres constatés</h2>';
    if ($dossier->constatations) {
        echo '<p>' . nl2br(esc_html($dossier->constatations)) . '</p>';
    } else {
        echo '<p><em>[Constatations à compléter dans le dossier.]</em></p>';
    }

    if (!empty($dossier->certificat_medecin) && !empty($dossier->certificat_pathologie)) {
        echo '<h2>Élément médical porté à connaissance</h2>';
        echo '<p>Un certificat médical du <strong>' . esc_html($dossier->certificat_medecin) . '</strong>';
        if ($dossier->certificat_date) echo ' en date du ' . esc_html(wp_date('j F Y', strtotime($dossier->certificat_date)));
        echo ' atteste : </p>';
        echo '<div class="citations"><em>' . nl2br(esc_html($dossier->certificat_pathologie)) . '</em></div>';
    }

    echo '<h2>Conclusion</h2>';
    echo '<p>Les désordres constatés ci-dessus caractérisent un manquement du bailleur à son obligation de délivrer et d\'entretenir un logement décent (articles 1719 et 1724 du Code civil, article 6 de la loi n° 89-462, décret n° 2002-120). Le présent rapport est versé au dossier du locataire et peut être produit à l\'appui de toute démarche amiable ou contentieuse.</p>';

    echo '<p style="margin-top:30px">Fait à Nantes, le ' . esc_html(wp_date('j F Y')) . '.</p>';
    echo '<div class="signature">' . esc_html($presta['nom'] ?? 'Le Groupe d\'Action LFI') . '</div>';

    echo '<div class="pj"><strong>Pièces jointes :</strong> photographies datées des désordres' . (!empty($dossier->certificat_medecin) ? ', certificat médical' : '') . '.</div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  RÉPONSE ARGUMENTÉE à un refus / une esquive de NMH               *
 *  (contre les "charge locative", "pas de signalement", etc.)       *
 * ============================================================== */
function lfi_nct_app_view_dossier_doc_reponse_nmh() {
    if (!lfi_nct_app_guard_brigade()) return;
    $ctx = lfi_nct_dossier_doc_open('📨 Réponse argumentée à NMH');
    extract($ctx);
    $asso = function_exists('lfi_nct_association') ? lfi_nct_association() : ['nom' => 'Union des Quartiers Libres'];

    echo '<div class="lfi-rec-doc">';

    /* Expéditeur = association (avec mandat du locataire) */
    echo '<div class="expediteur">';
    echo '<strong>' . esc_html($asso['nom']) . '</strong><br>';
    echo '<em>Association de défense des locataires (loi du 6 juillet 1989)</em><br>';
    if (!empty($asso['siege']))  echo esc_html(trim($asso['siege'] . ' ' . ($asso['cp_ville'] ?? ''))) . '<br>';
    if (!empty($asso['email']))  echo 'Mél. : ' . esc_html($asso['email']) . '<br>';
    echo 'Courrier établi avec l\'accord et à la demande de ' . esc_html($tenant_full ?: 'la locataire') . '</div>';

    lfi_nct_dossier_header_destinataire_nmh($bailleur);

    echo '<p class="lrar">Lettre recommandée avec accusé de réception</p>';
    echo '<div class="lieu-date">À Nantes, le ' . esc_html(wp_date('j F Y')) . '</div>';

    echo '<p class="objet">Objet : Réponse — logement de ' . esc_html($tenant_full) . ', ' . esc_html($tenant_logement) . '</p>';

    echo '<p>Monsieur,</p>';
    echo '<p>Nous accusons réception de votre message et y répondons point par point.</p>';

    echo '<h2>1. Sur l\'absence prétendue de signalement</h2>';
    echo '<p>L\'obligation du bailleur de délivrer et d\'entretenir un logement décent (articles <strong>1719 et 1720 du Code civil</strong>, décret n° 2002-120) est <strong>permanente</strong> et ne dépend d\'aucun signalement préalable par un canal particulier. La présente lettre, ainsi que nos précédents courriers, <strong>constituent ce signalement formel et écrit</strong>. Nous relevons au demeurant que vos services confirment eux-mêmes une demande en cours du locataire : la démarche est donc établie.</p>';

    echo '<h2>2. Sur l\'objet réel de notre saisine, demeuré sans réponse</h2>';
    echo '<p>Votre réponse n\'évoque ni les <strong>moisissures et l\'humidité constatées</strong>, ni le <strong>certificat médical</strong>';
    if (!empty($dossier->certificat_medecin)) echo ' du ' . esc_html($dossier->certificat_medecin);
    echo ', qui sont l\'objet même de notre demande de relogement. Ces désordres relèvent du bâti (ventilation, étanchéité) et affectent la santé d\'un occupant, médicalement attestée.</p>';
    if (!empty($dossier->constatations)) {
        echo '<div class="citations"><strong>Rappel des constatations effectuées sur place</strong>';
        if ($dossier->visite_date) echo ' le ' . esc_html(wp_date('j F Y', strtotime($dossier->visite_date)));
        echo ' :<br>' . nl2br(esc_html(mb_substr($dossier->constatations, 0, 600))) . '</div>';
    }

    echo '<h2>3. Sur l\'accès au logement</h2>';
    echo '<p>Vous indiquez n\'avoir pas eu accès au logement. Nous vous proposons en conséquence une <strong>visite contradictoire</strong>, en présence de la locataire et de notre association, le <strong>____ / ____ / 20____</strong>. Merci de nous confirmer une date sous huit (8) jours.</p>';

    echo '<h2>4. Sur les qualifications de « charge locative »</h2>';
    echo '<p>Nous prenons acte de ce qui relève de l\'entretien courant (décret n° 87-712). En revanche, l\'<strong>humidité structurelle</strong>, la <strong>ventilation (VMC)</strong>, le <strong>moteur du volet roulant électrifié</strong> et toute <strong>infiltration relevant du bâti</strong> demeurent à votre charge (articles 1719 et 1720 du Code civil). Le constat d\'état des lieux d\'entrée, ancien, ne saurait exonérer le bailleur de son obligation continue d\'entretien.</p>';

    echo '<h2>5. À défaut de prise en charge effective</h2>';
    echo '<p>Compte tenu de l\'urgence sanitaire attestée, et faute de réponse satisfaisante, nous saisissons le <strong>Service Communal d\'Hygiène et Santé de la Ville de Nantes</strong> et l\'<strong>Agence Régionale de Santé des Pays de la Loire</strong> aux fins de constat d\'insalubrité (articles L.1331-22 et suivants du Code de la santé publique), démarche susceptible d\'emporter un arrêté préfectoral et, partant, votre <strong>obligation de relogement</strong> (article L.521-3-1 du Code de la construction et de l\'habitation). Nous nous réservons par ailleurs la saisine de la juridiction compétente.</p>';
    echo '<p>Nous réservons l\'ensemble des droits de la locataire.</p>';

    echo '<p>Dans l\'attente d\'une date de visite contradictoire, nous vous prions d\'agréer, Monsieur, l\'expression de nos salutations distinguées.</p>';

    echo '<div class="signature">Pour ' . esc_html($asso['nom']) . '<br>' . esc_html($asso['president'] ?: '') . ', président</div>';

    echo '<div class="pj"><strong>Pièces jointes :</strong> certificat médical' . (!empty($dossier->certificat_medecin) ? ' (' . esc_html($dossier->certificat_medecin) . ')' : '') . ' · photographies datées des désordres · accord écrit de la locataire.</div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  NOTE : Discussion (emails) + ANALYSE des manquements de NMH      *
 *  Document à imprimer / PDF : reprend la correspondance puis une   *
 *  analyse juridique + sur la forme (professionnalisme).            *
 * ============================================================== */
function lfi_nct_app_view_dossier_doc_analyse_nmh() {
    if (!lfi_nct_app_guard_brigade()) return;
    $ctx = lfi_nct_dossier_doc_open('📑 Discussion + analyse NMH');
    extract($ctx);
    $asso = function_exists('lfi_nct_association') ? lfi_nct_association() : ['nom' => 'Union des Quartiers Libres'];

    /* Correspondance archivée dans le dossier */
    $logs = json_decode($dossier->notes ?? '', true);
    $sent = (is_array($logs) && !empty($logs['email_log']))  ? $logs['email_log']  : [];
    $recu = (is_array($logs) && !empty($logs['email_recu'])) ? $logs['email_recu'] : [];
    $analyse_perso = (is_array($logs) && !empty($logs['analyse_nmh'])) ? (string) $logs['analyse_nmh'] : '';
    $timeline = [];
    foreach ($sent as $e) { $e['sens'] = 'envoye'; $timeline[] = $e; }
    foreach ($recu as $e) { $e['sens'] = 'recu';   $timeline[] = $e; }

    /* Contenu géré par le code (content/analyses-nmh.php) : si une analyse
       est rattachée à ce dossier, elle est PRIORITAIRE — c'est le contenu
       qu'on a rédigé ensemble ici, poussé via GitHub. */
    $from_code = function_exists('lfi_nct_content_nmh_for_dossier')
        ? lfi_nct_content_nmh_for_dossier($dossier->id) : null;
    if ($from_code) {
        if (!empty($from_code['emails']) && is_array($from_code['emails'])) {
            foreach ($from_code['emails'] as $e) {
                if (!is_array($e)) continue;
                $e['sens'] = ($e['sens'] ?? 'recu') === 'envoye' ? 'envoye' : 'recu';
                $timeline[] = $e;
            }
        }
        if (!empty($from_code['analyse'])) $analyse_perso = (string) $from_code['analyse'];
    }

    usort($timeline, function ($a, $b) { return strcmp($a['date'] ?? '', $b['date'] ?? ''); });

    /* Suivi (ce qui a été FAIT), stratégie (ce qu'il RESTE à faire) et note
       avocats — gérés par le code, jamais mélangés. */
    $suivi_fait   = ($from_code && !empty($from_code['timeline']) && is_array($from_code['timeline'])) ? $from_code['timeline'] : [];
    $etapes_todo  = ($from_code && !empty($from_code['prochaines_etapes']) && is_array($from_code['prochaines_etapes'])) ? $from_code['prochaines_etapes'] : [];
    $note_avocats = ($from_code && !empty($from_code['note_avocats'])) ? (string) $from_code['note_avocats'] : '';

    echo '<div class="lfi-rec-doc">';

    echo '<h1>Suivi & analyse du dossier — ' . esc_html($tenant_full ?: 'locataire') . '</h1>';
    echo '<p style="text-align:center"><strong>' . esc_html($asso['nom']) . '</strong> — association de défense des locataires (loi du 6 juillet 1989)</p>';
    echo '<table class="detail">';
    echo '<tr><td><strong>Locataire</strong></td><td>' . esc_html($tenant_full ?: '—') . '</td></tr>';
    echo '<tr><td><strong>Logement</strong></td><td>' . esc_html($tenant_logement ?: '—') . '</td></tr>';
    echo '<tr><td><strong>Dossier</strong></td><td>n° ' . (int) $dossier->id . '</td></tr>';
    echo '<tr><td><strong>Date de la note</strong></td><td>' . esc_html(wp_date('j F Y')) . '</td></tr>';
    echo '</table>';

    /* ===== CE QUI A ÉTÉ FAIT (timeline) — séparé de ce qu'il reste à faire ===== */
    if (!empty($suivi_fait)) {
        echo '<h2 style="border-left:5px solid #186a3b;padding-left:10px">🗓 Ce qui a été fait — timeline du dossier</h2>';
        echo '<div class="citations" style="border-left-color:#186a3b">';
        foreach ($suivi_fait as $t) {
            if (!is_array($t)) continue;
            echo '<p style="margin:4px 0">✅ ';
            if (!empty($t['date'])) echo '<strong>' . esc_html($t['date']) . '</strong> — ';
            echo esc_html($t['fait'] ?? '');
            if (!empty($t['detail'])) echo '<br><span style="color:#555">' . nl2br(esc_html($t['detail'])) . '</span>';
            echo '</p>';
        }
        echo '</div>';
    }

    /* ===== CE QU'IL RESTE À FAIRE (stratégie) ===== */
    if (!empty($etapes_todo)) {
        echo '<h2 style="border-left:5px solid #0066a3;padding-left:10px">➡️ Ce qu\'il reste à faire — stratégie (priorité à l\'amiable)</h2>';
        echo '<div class="citations" style="border-left-color:#0066a3">';
        $n = 0;
        foreach ($etapes_todo as $e) {
            if (!is_array($e)) continue;
            $n++;
            echo '<p style="margin:6px 0"><strong>' . $n . '. ' . esc_html($e['etape'] ?? '') . '</strong>';
            if (!empty($e['echeance'])) echo ' <span style="color:#0066a3">(' . esc_html($e['echeance']) . ')</span>';
            if (!empty($e['detail'])) echo '<br><span style="color:#555">' . nl2br(esc_html($e['detail'])) . '</span>';
            echo '</p>';
        }
        echo '</div>';
    }

    /* ---- 1. La discussion ---- */
    echo '<h2>1. Rappel de la correspondance</h2>';
    if (empty($timeline)) {
        echo '<p><em>Aucun email n\'est encore archivé dans ce dossier. Enregistre les échanges (envoyés et reçus) depuis la fiche du dossier pour qu\'ils apparaissent ici.</em></p>';
    } else {
        foreach ($timeline as $e) {
            $is_recu = (($e['sens'] ?? '') === 'recu');
            echo '<div class="citations" style="margin:8px 0">';
            echo '<strong>' . ($is_recu ? '📥 Reçu' : '📤 Envoyé') . '</strong>';
            if (!empty($e['date'])) echo ' — ' . esc_html($e['date']);
            if ($is_recu && !empty($e['de'])) echo ' — de ' . esc_html($e['de']);
            if (!$is_recu && !empty($e['to'])) echo ' — à ' . esc_html($e['to']);
            if (!empty($e['objet'])) echo '<br><strong>Objet :</strong> ' . esc_html($e['objet']);
            if (!empty($e['corps'])) echo '<br>' . nl2br(esc_html(mb_substr($e['corps'], 0, 1500)));
            echo '</div>';
        }
    }

    /* ---- 2. Analyse personnalisée (si saisie) ---- */
    if ($analyse_perso !== '') {
        echo '<h2>2. Analyse du dossier</h2>';
        echo '<div>' . nl2br(esc_html($analyse_perso)) . '</div>';
        echo '<h2>3. Grille de référence — manquements fréquents du bailleur</h2>';
    } else {
        echo '<h2>2. Analyse — manquements juridiques de NMH</h2>';
    }

    /* ---- Grille des manquements juridiques (toujours fournie) ---- */
    echo '<div class="citations">';
    echo '<p><strong>a) Obligation de délivrance d\'un logement décent — permanente.</strong> Le bailleur doit délivrer et maintenir le logement en état de servir à l\'usage prévu (articles <strong>1719 et 1720 du Code civil</strong>, décret n° 2002-120 sur la décence). Cette obligation ne dépend d\'<em>aucun</em> signalement préalable : opposer une « absence de signalement » est juridiquement inopérant.</p>';
    echo '<p><strong>b) Silence sur l\'objet réel de la demande.</strong> La réponse n\'aborde ni les <strong>moisissures / l\'humidité</strong> constatées, ni le <strong>certificat médical</strong> produit. Ne pas répondre au cœur de la demande caractérise un défaut de diligence du bailleur.</p>';
    echo '<p><strong>c) Requalification abusive en « charges locatives ».</strong> L\'humidité structurelle, la VMC, les infiltrations et les équipements relevant du bâti restent à la charge du bailleur (art. 1719-1720 C. civ.) ; ils ne peuvent être renvoyés à l\'entretien courant du décret n° 87-712.</p>';
    echo '<p><strong>d) Délais et traçabilité.</strong> L\'absence de réponse utile dans un délai raisonnable, et l\'absence de proposition concrète (visite contradictoire, calendrier de travaux), aggravent le trouble de jouissance et ouvrent droit à réparation (art. 1231-1 et 1240 du Code civil).</p>';
    echo '<p><strong>e) Conséquences.</strong> À défaut de prise en charge, saisine du <strong>SCHS de Nantes</strong> et de l\'<strong>ARS</strong> (art. L.1331-22 et s. du Code de la santé publique), pouvant emporter arrêté d\'insalubrité et <strong>obligation de relogement</strong> (art. L.521-3-1 du CCH).</p>';
    echo '</div>';

    /* ---- 3/4. Sur la forme : professionnalisme ---- */
    echo '<h2>' . ($analyse_perso !== '' ? '4' : '3') . '. Sur la forme — qualité et professionnalisme de la réponse</h2>';
    echo '<div class="citations">';
    echo '<p>Indépendamment du fond, la réponse appelle des réserves sur la forme, qui nuisent à la relation locataire-bailleur attendue d\'un organisme de logement social :</p>';
    echo '<ul>';
    echo '<li><strong>Esquive des points essentiels</strong> (santé, moisissures, certificat) au profit d\'arguments de procédure.</li>';
    echo '<li><strong>Report de responsabilité</strong> sur le locataire (prétendu défaut de signalement, prétendu défaut d\'accès) sans élément probant.</li>';
    echo '<li><strong>Absence de proposition concrète</strong> et datée (pas de visite, pas de calendrier de travaux).</li>';
    echo '<li><strong>Formulations imprécises ou expéditives</strong>, peu compatibles avec le devoir d\'information et de conseil d\'un bailleur social.</li>';
    echo '</ul>';
    echo '<p>Ces éléments sont versés au dossier comme témoignant d\'un traitement insuffisamment diligent de la situation.</p>';
    echo '</div>';

    /* ===== NOTE POUR LES AVOCATS ===== */
    if ($note_avocats !== '') {
        echo '<div style="page-break-before:always;height:1px"></div>';
        echo '<h2 style="border-left:5px solid #7a0000;padding-left:10px">⚖️ Note pour les avocats — ce que nous attendons d\'eux</h2>';
        echo '<div>' . nl2br(esc_html($note_avocats)) . '</div>';
    }

    echo '<div class="signature">Pour ' . esc_html($asso['nom']) . '<br>' . esc_html($asso['president'] ?: '') . ', président</div>';
    echo '<div class="pj"><strong>Annexes :</strong> copie des emails ci-dessus · constat de visite · certificat médical · photographies datées.</div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  LETTRE 1 : Mise en demeure travaux urgents                       *
 * ============================================================== */
function lfi_nct_app_view_dossier_doc_lrar_travaux() {
    if (!lfi_nct_app_guard_brigade()) return;
    $ctx = lfi_nct_dossier_doc_open('📨 Mise en demeure — travaux urgents');
    extract($ctx);

    echo '<div class="lfi-rec-doc">';

    lfi_nct_dossier_header_locataire($dossier, $presta);
    lfi_nct_dossier_header_destinataire_nmh($bailleur);

    echo '<p class="lrar">Lettre recommandée avec accusé de réception</p>';
    echo '<div class="lieu-date">À Nantes, le ' . esc_html(wp_date('j F Y')) . '</div>';

    echo '<p class="objet">Objet : Mise en demeure de procéder, dans les plus brefs délais, aux travaux indispensables à la décence du logement situé ' . esc_html($dossier->tenant_adresse) . ($dossier->tenant_etage ? ', étage ' . esc_html($dossier->tenant_etage) : '') . '.</p>';

    echo '<p>Monsieur le Directeur Général,</p>';

    echo '<p>J\'ai l\'honneur d\'attirer votre attention, en application des articles <strong>1719 et 1724 du Code civil</strong>, de l\'<strong>article 6 de la loi n° 89-462 du 6 juillet 1989</strong> et du <strong>décret n° 2002-120 du 30 janvier 2002</strong> relatif aux caractéristiques du logement décent, sur la situation alarmante de mon logement, dont vous êtes propriétaire-bailleur.</p>';

    /* Constatations détaillées */
    if (!empty($dossier->constatations)) {
        echo '<h2>Désordres constatés</h2>';
        if ($dossier->visite_date) {
            echo '<p><strong>Constatations établies le ' . esc_html(wp_date('j F Y', strtotime($dossier->visite_date))) . '</strong>';
            if ($dossier->visite_duree) echo ' (durée de visite : ' . esc_html($dossier->visite_duree) . ')';
            echo ', en présence d\'un membre du Groupe d\'Action LFI Nantes Sud Clos Toreau, intervenu à ma demande.</p>';
        }
        echo '<p>' . nl2br(esc_html($dossier->constatations)) . '</p>';
    }

    echo '<h2>Fondements juridiques de la mise en demeure</h2>';

    echo '<div class="citations">';
    echo '<p>Ces désordres caractérisent un <strong>manquement à votre obligation légale de délivrer un logement décent</strong> et de l\'entretenir en état de servir à l\'usage convenu.</p>';
    echo '<ul>';
    echo '<li><strong>Article 1719 du Code civil :</strong> « Le bailleur est obligé, par la nature du contrat, et sans qu\'il soit besoin d\'aucune stipulation particulière : 1° De délivrer au preneur la chose louée et, s\'il s\'agit de son habitation principale, un logement décent. 2° D\'entretenir cette chose en état de servir à l\'usage pour lequel elle a été louée. »</li>';
    echo '<li><strong>Article 1724 du Code civil :</strong> les réparations urgentes incombent au bailleur, sans pouvoir être différées.</li>';
    echo '<li><strong>Article 6 de la loi n° 89-462 :</strong> obligation d\'entretien continu et de remise en conformité aux normes de décence.</li>';
    echo '<li><strong>Décret n° 2002-120 du 30 janvier 2002 :</strong> caractéristiques d\'un logement décent — absence de risque manifeste pour la sécurité physique et la santé, étanchéité à l\'air et à l\'eau, ventilation suffisante.</li>';
    echo '</ul>';
    echo '</div>';

    /* Bloc certificat médical si présent */
    if (!empty($dossier->certificat_medecin) && !empty($dossier->certificat_pathologie)) {
        echo '<h2>Impact sanitaire avéré</h2>';
        echo '<p>Les désordres précités emportent <strong>des conséquences sanitaires directes</strong> sur les occupants du logement. Un certificat médical du <strong>' . esc_html($dossier->certificat_medecin) . '</strong>';
        if ($dossier->certificat_date) echo ', établi le ' . esc_html(wp_date('j F Y', strtotime($dossier->certificat_date)));
        echo ', atteste de la situation suivante :</p>';
        echo '<p><em>' . nl2br(esc_html($dossier->certificat_pathologie)) . '</em></p>';
        echo '<p>Le certificat médical est joint à la présente.</p>';
    }

    echo '<h2>Mise en demeure</h2>';

    echo '<p>En conséquence, et en application des <strong>articles 1217, 1226 et 1231-1 du Code civil</strong>, je vous mets formellement en demeure, par la présente lettre recommandée avec accusé de réception :</p>';

    $delai_court = function_exists('lfi_nct_nmh_urgence_get') ? lfi_nct_nmh_urgence_get($dossier->nmh_urgence ?: 'bailleur')['court'] : '1 mois';
    echo '<div class="citations">';
    echo '<ul>';
    echo '<li>de <strong>diligenter sans délai</strong> à compter de la réception des présentes une visite contradictoire du logement, en ma présence ;</li>';
    echo '<li>de <strong>réaliser, dans un délai maximal de ' . esc_html(strtoupper($delai_court)) . '</strong> à compter de la réception des présentes, l\'intégralité des travaux nécessaires à la remise en conformité du logement avec les critères de décence ;</li>';
    echo '<li>de <strong>m\'indiquer par écrit</strong>, dans le même délai, le calendrier précis et la nature des interventions prévues.</li>';
    echo '</ul>';
    echo '</div>';

    echo '<h2>Conséquences à défaut</h2>';

    echo '<p>À défaut de réponse satisfaisante et d\'engagement effectif des travaux dans les délais impartis, je me réserve le droit, sans nouvelle mise en demeure :</p>';
    echo '<ul>';
    echo '<li>de <strong>saisir la Commission Départementale de Conciliation</strong> de Loire-Atlantique (article 20 de la loi n° 89-462) ;</li>';
    echo '<li>de <strong>saisir le Tribunal Judiciaire de Nantes</strong> aux fins de voir prononcer votre condamnation à l\'exécution des travaux sous astreinte (article 20-1 de la loi n° 89-462), au paiement de dommages-intérêts pour trouble de jouissance et à une réduction de loyer ;</li>';
    echo '<li>de <strong>saisir le Service Communal d\'Hygiène et Santé</strong> de la Ville de Nantes et l\'<strong>Agence Régionale de Santé Pays de la Loire</strong> aux fins de constatation d\'insalubrité et d\'arrêté préfectoral ;</li>';
    echo '<li>de <strong>solliciter la suspension de mes versements de loyer</strong> par consignation à la Caisse des Dépôts et Consignations dans l\'attente de la régularisation ;</li>';
    if (in_array('relogement_urgent', json_decode($dossier->demandes ?? '[]', true) ?: [], true)) {
        echo '<li>de <strong>demander mon relogement d\'urgence</strong> dans un logement décent, en application de votre obligation de relogement (article L.521-3-1 du Code de la construction et de l\'habitation) et du droit au logement opposable (loi n° 2007-290 du 5 mars 2007).</li>';
    }
    echo '</ul>';

    echo '<p>Je vous prie de croire, Monsieur le Directeur Général, à l\'expression de mes salutations distinguées.</p>';

    echo '<div class="signature">' . esc_html(trim($dossier->tenant_prenom . ' ' . $dossier->tenant_nom)) . '</div>';

    echo '<div class="pj"><strong>Pièces jointes :</strong><br>';
    echo '— Photographies datées des désordres constatés<br>';
    echo '— Compte-rendu de visite du Groupe d\'Action LFI Nantes Sud Clos Toreau';
    if (!empty($dossier->certificat_medecin)) echo '<br>— Certificat médical du ' . esc_html($dossier->certificat_medecin);
    echo '</div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  LETTRE 2 : Demande de relogement d'urgence médicale              *
 * ============================================================== */
function lfi_nct_app_view_dossier_doc_lrar_relogement() {
    if (!lfi_nct_app_guard_brigade()) return;
    $ctx = lfi_nct_dossier_doc_open('🏥 Relogement d\'urgence médicale');
    extract($ctx);

    echo '<div class="lfi-rec-doc">';

    lfi_nct_dossier_header_locataire($dossier, $presta);
    lfi_nct_dossier_header_destinataire_nmh($bailleur);

    echo '<p class="lrar">Lettre recommandée avec accusé de réception</p>';
    echo '<div class="lieu-date">À Nantes, le ' . esc_html(wp_date('j F Y')) . '</div>';

    echo '<p class="objet">Objet : Demande de RELOGEMENT D\'URGENCE pour motif médical impérieux — logement situé ' . esc_html($dossier->tenant_adresse) . ($dossier->tenant_etage ? ', étage ' . esc_html($dossier->tenant_etage) : '') . '.</p>';

    echo '<p>Monsieur le Directeur Général,</p>';

    echo '<p>Par la présente, j\'ai l\'honneur de solliciter, en raison d\'un péril sanitaire imminent attesté médicalement, mon <strong>relogement immédiat</strong> dans un logement décent et salubre du parc social de Nantes Métropole Habitat.</p>';

    /* === I. La situation sanitaire === */
    echo '<h2>I — La situation sanitaire</h2>';

    if (!empty($dossier->certificat_medecin)) {
        echo '<p>Un certificat médical du <strong>' . esc_html($dossier->certificat_medecin) . '</strong>';
        if ($dossier->certificat_date) echo ', établi le ' . esc_html(wp_date('j F Y', strtotime($dossier->certificat_date)));
        echo ', dont copie est jointe à la présente, atteste de la situation suivante :</p>';
        echo '<div class="citations"><em>' . nl2br(esc_html($dossier->certificat_pathologie)) . '</em></div>';
        echo '<p>Le lien direct entre la pathologie constatée et les conditions d\'habitat (humidité, moisissures) est expressément établi par le praticien, qui préconise un changement de logement.</p>';
    } else {
        echo '<p><em>[Joindre le certificat médical du praticien et reprendre ici son contenu.]</em></p>';
    }

    /* === II. L'état du logement === */
    if (!empty($dossier->constatations)) {
        echo '<h2>II — L\'état actuel du logement</h2>';
        if ($dossier->visite_date) {
            echo '<p>Une visite contradictoire du logement a été effectuée le <strong>' . esc_html(wp_date('j F Y', strtotime($dossier->visite_date))) . '</strong>';
            if ($dossier->visite_duree) echo ' (durée : ' . esc_html($dossier->visite_duree) . ')';
            echo ' par un membre du Groupe d\'Action LFI Nantes Sud Clos Toreau, intervenu à ma demande. Les constatations suivantes ont été établies :</p>';
        }
        echo '<p>' . nl2br(esc_html($dossier->constatations)) . '</p>';
        echo '<p>Ces désordres sont notoirement de nature à <strong>aggraver la pathologie constatée</strong> par le médecin traitant. Aucun simple ravalement ou traitement ponctuel ne peut, dans les délais compatibles avec l\'état de santé de l\'occupant, faire cesser l\'exposition au risque.</p>';
    }

    /* === III. Fondements juridiques === */
    echo '<h2>III — Fondements juridiques</h2>';
    echo '<div class="citations">';
    echo '<ul>';
    echo '<li><strong>Article 1719 du Code civil</strong> : obligation de délivrer un logement décent et d\'en assurer la jouissance paisible ;</li>';
    echo '<li><strong>Article 6 de la loi n° 89-462 du 6 juillet 1989</strong> : obligation d\'entretien et de mise en conformité avec les critères de décence ;</li>';
    echo '<li><strong>Décret n° 2002-120 du 30 janvier 2002</strong> : critères du logement décent — absence de risque manifeste pour la santé ;</li>';
    echo '<li><strong>Article L.521-3-1 du Code de la construction et de l\'habitation</strong> : obligation de relogement à la charge du bailleur lorsque le logement présente un risque pour la santé ou la sécurité des occupants ;</li>';
    echo '<li><strong>Loi n° 2007-290 du 5 mars 2007</strong> instituant le droit au logement opposable (DALO) — possibilité de saisine de la commission de médiation à défaut de relogement par le bailleur ;</li>';
    echo '<li><strong>Article L.301-1 du Code de la construction et de l\'habitation</strong> : la politique du logement social doit garantir à toute personne le droit à un logement décent et indépendant.</li>';
    echo '</ul>';
    echo '</div>';

    /* === IV. Demande === */
    echo '<h2>IV — Demande</h2>';

    echo '<p>En conséquence, je vous prie de bien vouloir :</p>';
    echo '<div class="citations">';
    echo '<ul>';
    echo '<li>m\'attribuer, <strong>dans le délai d\'UN (1) MOIS</strong> à compter de la réception de la présente, un logement décent et adapté à la situation médicale, dans le parc social que vous gérez ;</li>';
    echo '<li>à défaut de proposition dans ce délai, <strong>solliciter mon classement prioritaire</strong> sur le contingent préfectoral en application de l\'article R.441-14-1 du CCH (situation médicale grave) ;</li>';
    echo '<li>me confirmer par écrit la procédure suivie et les démarches engagées de votre part.</li>';
    echo '</ul>';
    echo '</div>';

    /* === V. À défaut === */
    echo '<h2>V — Conséquences à défaut</h2>';

    echo '<p>À défaut de réponse positive dans le délai imparti, je me réserve le droit, sans nouvelle mise en demeure :</p>';
    echo '<ul>';
    echo '<li>de <strong>saisir la commission de médiation DALO</strong> de Loire-Atlantique aux fins de reconnaissance prioritaire et de relogement contraint ;</li>';
    echo '<li>de <strong>saisir le Service Communal d\'Hygiène et Santé</strong> de la Ville de Nantes et l\'<strong>Agence Régionale de Santé Pays de la Loire</strong> aux fins de constatation d\'insalubrité et d\'arrêté préfectoral d\'interdiction temporaire d\'habiter, qui emporterait obligation à votre charge de relogement (article L.521-3-1 CCH) ;</li>';
    echo '<li>de <strong>saisir le Tribunal Judiciaire de Nantes en référé</strong> aux fins de relogement provisoire sous astreinte (article 835 CPC) ;</li>';
    echo '<li>de saisir la Défenseure des droits si la situation perdurait ;</li>';
    echo '<li>d\'engager votre <strong>responsabilité civile et pénale</strong> en cas d\'aggravation de l\'état de santé de l\'occupant mineur (mise en danger d\'autrui — article 223-1 du Code pénal).</li>';
    echo '</ul>';

    echo '<p>Compte tenu de l\'urgence médicale attestée, je sollicite votre traitement diligent et bienveillant de cette demande, dans l\'esprit des missions de service public du logement social qui vous incombent.</p>';

    echo '<p>Je vous prie de croire, Monsieur le Directeur Général, à l\'expression de mes salutations distinguées.</p>';

    echo '<div class="signature">' . esc_html(trim($dossier->tenant_prenom . ' ' . $dossier->tenant_nom)) . '</div>';

    echo '<div class="pj"><strong>Pièces jointes :</strong><br>';
    if (!empty($dossier->certificat_medecin)) echo '— Certificat médical du ' . esc_html($dossier->certificat_medecin) . '<br>';
    echo '— Photographies datées des désordres<br>';
    echo '— Compte-rendu de visite du Groupe d\'Action LFI Nantes Sud Clos Toreau<br>';
    echo '— Le cas échéant : courriers de signalement préalable au bailleur</div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  LETTRE 3 : Saisine SCHS Nantes                                   *
 * ============================================================== */
function lfi_nct_app_view_dossier_doc_schs() {
    if (!lfi_nct_app_guard_brigade()) return;
    $ctx = lfi_nct_dossier_doc_open('🏥 Saisine SCHS Nantes');
    extract($ctx);

    echo '<div class="lfi-rec-doc">';

    lfi_nct_dossier_header_locataire($dossier, $presta);

    echo '<div class="destinataire">';
    echo '<strong>Service Communal d\'Hygiène et Santé (SCHS)<br>Ville de Nantes</strong><br>';
    echo 'Direction Santé Publique<br>';
    echo '1 rue de Bouillé<br>';
    echo '44000 NANTES';
    echo '</div>';

    echo '<p class="lrar">Lettre recommandée avec accusé de réception</p>';
    echo '<div class="lieu-date">À Nantes, le ' . esc_html(wp_date('j F Y')) . '</div>';

    echo '<p class="objet">Objet : Signalement d\'insalubrité — demande de visite d\'enquête en application des articles L.1331-22 et suivants du Code de la santé publique. Logement situé ' . esc_html($dossier->tenant_adresse) . ($dossier->tenant_etage ? ', étage ' . esc_html($dossier->tenant_etage) : '') . '.</p>';

    echo '<p>Madame, Monsieur,</p>';

    echo '<p>J\'ai l\'honneur de porter à votre connaissance la situation d\'insalubrité du logement que j\'occupe, propriété de Nantes Métropole Habitat, et de solliciter, en application des articles <strong>L.1331-22 et suivants du Code de la santé publique</strong>, la diligence d\'une enquête d\'insalubrité par les agents assermentés de votre service.</p>';

    echo '<h2>Désordres constatés</h2>';
    if ($dossier->visite_date) {
        echo '<p>Une visite contradictoire a été effectuée le <strong>' . esc_html(wp_date('j F Y', strtotime($dossier->visite_date))) . '</strong>';
        if ($dossier->visite_duree) echo ' (durée : ' . esc_html($dossier->visite_duree) . ')';
        echo ' par un membre du Groupe d\'Action LFI Nantes Sud Clos Toreau, intervenu à ma demande.</p>';
    }
    if (!empty($dossier->constatations)) {
        echo '<p>' . nl2br(esc_html($dossier->constatations)) . '</p>';
    }

    /* Bloc certificat médical si présent — décisif pour le SCHS */
    if (!empty($dossier->certificat_medecin) && !empty($dossier->certificat_pathologie)) {
        echo '<h2>Risque sanitaire avéré — certificat médical</h2>';
        echo '<p>Un certificat médical du <strong>' . esc_html($dossier->certificat_medecin) . '</strong>';
        if ($dossier->certificat_date) echo ' en date du ' . esc_html(wp_date('j F Y', strtotime($dossier->certificat_date)));
        echo ' atteste d\'un lien direct entre l\'état du logement et la santé d\'un occupant :</p>';
        echo '<div class="citations"><em>' . nl2br(esc_html($dossier->certificat_pathologie)) . '</em></div>';
        echo '<p>Le certificat médical est joint à la présente.</p>';
    }

    echo '<h2>Démarches engagées</h2>';

    echo '<p>Une mise en demeure a été (ou va être) adressée par lettre recommandée avec accusé de réception à Nantes Métropole Habitat. Compte tenu de la nature des désordres et du risque sanitaire associé, je sollicite parallèlement votre intervention.</p>';

    echo '<h2>Demandes</h2>';
    echo '<div class="citations">';
    echo '<ul>';
    echo '<li><strong>Diligence d\'une visite</strong> du logement par les agents assermentés du SCHS ;</li>';
    echo '<li><strong>Établissement d\'un rapport circonstancié</strong> sur les manquements aux critères du logement décent (décret n° 2002-120) et de salubrité (articles R.1331-1 et suivants CSP) ;</li>';
    echo '<li>Le cas échéant, <strong>saisine de Monsieur le Préfet de la Loire-Atlantique</strong> aux fins d\'arrêté de mise en sécurité ou d\'insalubrité (article L.1331-22 CSP), avec interdiction temporaire d\'habiter et obligation de relogement à la charge du bailleur (article L.521-3-1 CCH) ;</li>';
    echo '<li><strong>Transmission</strong> du présent signalement à l\'Agence Régionale de Santé Pays de la Loire pour suivi sanitaire des occupants.</li>';
    echo '</ul>';
    echo '</div>';

    echo '<p>Je me tiens à disposition de votre service pour toute visite, expertise contradictoire et transmission de pièces complémentaires (photographies datées, devis, signalements préalables).</p>';

    echo '<p>Je vous prie de croire, Madame, Monsieur, à l\'expression de ma considération distinguée.</p>';

    echo '<div class="signature">' . esc_html(trim($dossier->tenant_prenom . ' ' . $dossier->tenant_nom)) . '</div>';

    echo '<div class="pj"><strong>Pièces jointes :</strong><br>';
    if (!empty($dossier->certificat_medecin)) echo '— Certificat médical du ' . esc_html($dossier->certificat_medecin) . '<br>';
    echo '— Photographies datées des désordres<br>';
    echo '— Compte-rendu de visite du Groupe d\'Action LFI Nantes Sud Clos Toreau<br>';
    echo '— Copie des courriers échangés avec Nantes Métropole Habitat</div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  LETTRE 4 : Saisine ARS Pays de la Loire                          *
 * ============================================================== */
function lfi_nct_app_view_dossier_doc_ars() {
    if (!lfi_nct_app_guard_brigade()) return;
    $ctx = lfi_nct_dossier_doc_open('🏛 Saisine ARS Pays de la Loire');
    extract($ctx);

    echo '<div class="lfi-rec-doc">';

    lfi_nct_dossier_header_locataire($dossier, $presta);

    echo '<div class="destinataire">';
    echo '<strong>Madame la Directrice Générale<br>Agence Régionale de Santé Pays de la Loire</strong><br>';
    echo '17 boulevard Gaston Doumergue<br>';
    echo 'CS 56233<br>';
    echo '44262 Nantes Cedex 2';
    echo '</div>';

    echo '<p class="lrar">Lettre recommandée avec accusé de réception</p>';
    echo '<div class="lieu-date">À Nantes, le ' . esc_html(wp_date('j F Y')) . '</div>';

    echo '<p class="objet">Objet : Signalement d\'un risque sanitaire dans un logement social — demande d\'intervention en application de l\'article L.1311-2 du Code de la santé publique. Logement situé ' . esc_html($dossier->tenant_adresse) . ($dossier->tenant_etage ? ', étage ' . esc_html($dossier->tenant_etage) : '') . '.</p>';

    echo '<p>Madame la Directrice Générale,</p>';

    echo '<p>J\'ai l\'honneur de porter à la connaissance de vos services une situation de risque sanitaire actuel et avéré dans le logement social que j\'occupe, et de solliciter votre intervention dans le cadre de vos missions de prévention et de protection de la santé des populations.</p>';

    if (!empty($dossier->certificat_medecin) && !empty($dossier->certificat_pathologie)) {
        echo '<h2>I — Situation médicale documentée</h2>';
        echo '<p>Un certificat médical du <strong>' . esc_html($dossier->certificat_medecin) . '</strong>';
        if ($dossier->certificat_date) echo ' en date du ' . esc_html(wp_date('j F Y', strtotime($dossier->certificat_date)));
        echo ', joint à la présente, atteste de la situation suivante :</p>';
        echo '<div class="citations"><em>' . nl2br(esc_html($dossier->certificat_pathologie)) . '</em></div>';
        echo '<p>Le praticien établit un <strong>lien de causalité probable entre la pathologie et les conditions d\'habitat</strong> et préconise un relogement immédiat.</p>';
    }

    if (!empty($dossier->constatations)) {
        echo '<h2>II — État du logement</h2>';
        if ($dossier->visite_date) {
            echo '<p>Une visite a été conduite le <strong>' . esc_html(wp_date('j F Y', strtotime($dossier->visite_date))) . '</strong>';
            if ($dossier->visite_duree) echo ' (durée : ' . esc_html($dossier->visite_duree) . ')';
            echo ' par un membre du Groupe d\'Action LFI Nantes Sud Clos Toreau, à ma demande. Constatations :</p>';
        }
        echo '<p>' . nl2br(esc_html($dossier->constatations)) . '</p>';
    }

    echo '<h2>III — Fondements et demandes</h2>';
    echo '<div class="citations">';
    echo '<p>Conformément aux articles <strong>L.1311-2, L.1331-22 et suivants du Code de la santé publique</strong>, et au regard de la situation, je sollicite :</p>';
    echo '<ul>';
    echo '<li><strong>L\'évaluation sanitaire</strong> du logement par vos services compétents (cellule habitat indigne / unité environnement extérieur) ;</li>';
    echo '<li>Le cas échéant, la <strong>saisine de Monsieur le Préfet</strong> aux fins d\'arrêté préfectoral de mise en sécurité ou d\'insalubrité ;</li>';
    echo '<li>L\'<strong>orientation médicale</strong> de l\'occupant exposé vers les structures de suivi adaptées ;</li>';
    echo '<li>La <strong>coordination</strong> avec le Service Communal d\'Hygiène et Santé de la Ville de Nantes et Nantes Métropole Habitat aux fins de mise à l\'abri rapide.</li>';
    echo '</ul>';
    echo '</div>';

    echo '<p>Vu l\'urgence sanitaire, je vous prie de bien vouloir traiter ce signalement avec la diligence requise.</p>';

    echo '<p>Je vous prie de croire, Madame la Directrice Générale, à l\'expression de mes salutations respectueuses.</p>';

    echo '<div class="signature">' . esc_html(trim($dossier->tenant_prenom . ' ' . $dossier->tenant_nom)) . '</div>';

    echo '<div class="pj"><strong>Pièces jointes :</strong><br>';
    if (!empty($dossier->certificat_medecin)) echo '— Certificat médical du ' . esc_html($dossier->certificat_medecin) . '<br>';
    echo '— Photographies datées des désordres constatés<br>';
    echo '— Compte-rendu de visite du Groupe d\'Action LFI Nantes Sud Clos Toreau<br>';
    echo '— Copie des courriers adressés à Nantes Métropole Habitat et au SCHS</div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}
