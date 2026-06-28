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
const LFI_NCT_DOSSIER_DBVER_VAL = '1';

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
        statut VARCHAR(20) DEFAULT 'ouvert',
        lrar_travaux_date DATE DEFAULT NULL,
        lrar_relogement_date DATE DEFAULT NULL,
        schs_date DATE DEFAULT NULL,
        ars_date DATE DEFAULT NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY owner_user_id (owner_user_id),
        KEY tenant_user_id (tenant_user_id)
    ) $charset;");

    update_option(LFI_NCT_DOSSIER_DBVER_KEY, LFI_NCT_DOSSIER_DBVER_VAL, false);
}

/* ============================================================== *
 *  Helpers                                                         *
 * ============================================================== */
function lfi_nct_dossier_owner_id() {
    return function_exists('lfi_nct_brigade_owner_id') ? lfi_nct_brigade_owner_id() : (int) get_current_user_id();
}

function lfi_nct_dossier_get($id) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $owner = (int) lfi_nct_dossier_owner_id();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d AND owner_user_id = %d", (int) $id, $owner));
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
    if (!lfi_nct_can_use_brigade()) return;
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

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  Formulaire création / édition                                   *
 * ============================================================== */
function lfi_nct_app_view_dossier_juridique_add() {
    if (!lfi_nct_can_use_brigade()) return;
    lfi_nct_app_dossier_juridique_form(null);
}
function lfi_nct_app_view_dossier_juridique_edit() {
    if (!lfi_nct_can_use_brigade()) return;
    $row = lfi_nct_dossier_get((int) ($_GET['id'] ?? 0));
    if (!$row) {
        lfi_nct_app_screen_open('Dossier introuvable');
        echo '<div class="lfi-app-empty"><a href="' . esc_url(lfi_nct_app_url('dossiers-juridiques')) . '">← Retour</a></div>';
        lfi_nct_app_screen_close(false);
        return;
    }
    lfi_nct_app_dossier_juridique_form($row);
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
            'constatations'        => sanitize_textarea_field(wp_unslash($_POST['constatations'] ?? '')),
            'certificat_medecin'   => sanitize_text_field(wp_unslash($_POST['certificat_medecin'] ?? '')),
            'certificat_pathologie'=> sanitize_textarea_field(wp_unslash($_POST['certificat_pathologie'] ?? '')),
            'certificat_date'      => sanitize_text_field(wp_unslash($_POST['certificat_date'] ?? '')) ?: null,
            'demandes'             => wp_json_encode($demandes),
            'statut'               => sanitize_key($_POST['statut'] ?? 'ouvert'),
            'notes'                => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
        ];

        if ($is_edit) {
            $wpdb->update($t, $data, ['id' => $row->id, 'owner_user_id' => $owner]);
            wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $row->id, 'saved' => 1]));
        } else {
            $data['owner_user_id'] = $owner;
            $wpdb->insert($t, $data);
            $new_id = (int) $wpdb->insert_id;
            wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $new_id, 'created' => 1]));
        }
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

    /* Pré-remplissage si nouveau + tenant_uid */
    if (!$is_edit && !empty($_GET['tenant_uid'])) {
        $tuid = (int) $_GET['tenant_uid'];
        $u = get_userdata($tuid);
        if ($u) {
            $row = (object) [
                'tenant_user_id'   => $tuid,
                'tenant_prenom'    => $u->first_name ?: $u->display_name,
                'tenant_nom'       => $u->last_name,
                'tenant_email'     => $u->user_email,
                'tenant_tel'       => (string) get_user_meta($tuid, 'lfi_nct_tel', true),
            ];
            $resp_id = (int) get_user_meta($tuid, 'lfi_nct_response_id', true);
            if ($resp_id) {
                $resp = $wpdb->get_row($wpdb->prepare("SELECT adresse, etage FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $resp_id));
                if ($resp) {
                    $row->tenant_adresse = $resp->adresse;
                    $row->tenant_etage   = $resp->etage;
                }
            }
        }
    }

    $r = $row ?: (object) [
        'tenant_user_id'=>'', 'tenant_prenom'=>'', 'tenant_nom'=>'', 'tenant_adresse'=>'',
        'tenant_etage'=>'', 'tenant_appartement'=>'', 'tenant_tel'=>'', 'tenant_email'=>'',
        'visite_date'=>current_time('Y-m-d'), 'visite_duree'=>'',
        'constatations'=>'', 'certificat_medecin'=>'',
        'certificat_pathologie'=>'', 'certificat_date'=>'',
        'demandes'=>'[]', 'statut'=>'ouvert', 'notes'=>'',
    ];

    $demandes_actives = json_decode($r->demandes ?? '[]', true);
    if (!is_array($demandes_actives)) $demandes_actives = [];

    lfi_nct_app_screen_open($is_edit ? '📁 Dossier #' . $row->id : '+ Nouveau dossier juridique', 'Constatations · certificat · demandes');

    if (!empty($_GET['saved']))   lfi_nct_app_flash('✅ Dossier enregistré.');
    if (!empty($_GET['created'])) lfi_nct_app_flash('✅ Dossier créé. Tu peux maintenant générer les lettres.');
    if (!empty($_GET['marked']))  lfi_nct_app_flash('📨 Étape marquée comme envoyée (date du jour).');

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_dossier_save');
    echo '<input type="hidden" name="lfi_dossier_save" value="1">';

    /* === LOCATAIRE === */
    echo '<h3 style="margin:0">👤 Locataire</h3>';
    echo '<label>Prénom<input type="text" name="tenant_prenom" value="' . esc_attr($r->tenant_prenom) . '" required></label>';
    echo '<label>Nom<input type="text" name="tenant_nom" value="' . esc_attr($r->tenant_nom) . '"></label>';
    echo '<label>Adresse complète<input type="text" name="tenant_adresse" value="' . esc_attr($r->tenant_adresse) . '" placeholder="8 rue de Saint-Jean-de-Luz, 44200 Nantes" required></label>';
    echo '<label>Étage<input type="text" name="tenant_etage" value="' . esc_attr($r->tenant_etage) . '"></label>';
    echo '<label>N° appartement<input type="text" name="tenant_appartement" value="' . esc_attr($r->tenant_appartement) . '"></label>';
    echo '<label>Téléphone<input type="tel" name="tenant_tel" value="' . esc_attr($r->tenant_tel) . '"></label>';
    echo '<label>Email<input type="email" name="tenant_email" value="' . esc_attr($r->tenant_email) . '"></label>';

    /* === VISITE / CONSTATATIONS === */
    echo '<h3 style="margin:18px 0 0">🔍 Constatations de visite</h3>';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
    echo '<label>Date de visite<input type="date" name="visite_date" value="' . esc_attr($r->visite_date) . '"></label>';
    echo '<label>Durée<input type="text" name="visite_duree" value="' . esc_attr($r->visite_duree) . '" placeholder="ex: 4 heures"></label>';
    echo '</div>';
    echo '<label>Description détaillée des désordres observés<textarea name="constatations" rows="8" placeholder="Décris pièce par pièce : moisissures (couleur, surface, emplacement précis), fuites, infiltrations d\'air, humidité au toucher, taux ressenti, odeurs… Sois factuel et précis : ces constatations seront citées dans toutes les lettres.">' . esc_textarea($r->constatations) . '</textarea></label>';

    /* === CERTIFICAT MÉDICAL === */
    echo '<h3 style="margin:18px 0 0">🏥 Certificat médical (si demande de relogement)</h3>';
    echo '<label>Médecin (titre + nom)<input type="text" name="certificat_medecin" value="' . esc_attr($r->certificat_medecin) . '" placeholder="Dr Aubeau, médecin généraliste, Nantes"></label>';
    echo '<label>Date du certificat<input type="date" name="certificat_date" value="' . esc_attr($r->certificat_date) . '"></label>';
    echo '<label>Pathologie constatée + lien avec l\'humidité<textarea name="certificat_pathologie" rows="4" placeholder="Ex: Asthme sévère de la fille mineure (X ans), aggravation des crises depuis l\'emménagement. Le médecin certifie que la pathologie est probablement liée à l\'exposition prolongée à l\'humidité et aux moisissures, et préconise un relogement immédiat dans un logement sain.">' . esc_textarea($r->certificat_pathologie) . '</textarea></label>';
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
    echo '<label>Notes internes (non publiées)<textarea name="notes" rows="2">' . esc_textarea($r->notes) . '</textarea></label>';

    echo '<button type="submit" class="btn-primary big">' . ($is_edit ? '💾 Enregistrer' : '+ Créer le dossier') . '</button>';
    echo '</form>';

    /* === GÉNÉRATION DES LETTRES (uniquement après création) === */
    if ($is_edit) {
        echo '<h3 style="margin:24px 0 8px;color:#c8102e">📄 Lettres à générer (LRAR)</h3>';
        echo '<div class="lfi-app-help">Clique sur une lettre pour l\'ouvrir (déjà pré-remplie avec tes constats). Bouton « Imprimer » en haut, puis envoi en recommandé avec accusé de réception.</div>';

        $lettres = [
            ['lrar_travaux',    '🔧 Mise en demeure — TRAVAUX URGENTS',
             'NMH doit réaliser les travaux sans délai. Cite art. 1719, 1724 CC + loi 89-462 + décret 2002-120.',
             'dossier-doc-lrar-travaux',    $row->lrar_travaux_date],
            ['lrar_relogement', '🏥 Demande de RELOGEMENT D\'URGENCE médicale',
             'NMH doit reloger immédiatement (certificat médical à l\'appui). Cite art. L.441-2-3 CCH + DALO + art. 1719 CC.',
             'dossier-doc-lrar-relogement', $row->lrar_relogement_date],
            ['schs',            '🏥 Saisine SCHS Nantes — insalubrité',
             'Service Communal d\'Hygiène et Santé : déclenche enquête + arrêté préfectoral si confirmé.',
             'dossier-doc-schs',            $row->schs_date],
            ['ars',             '🏛 Saisine ARS Pays de la Loire',
             'Agence Régionale de Santé : risque sanitaire avéré, surtout si enfant + certificat médical.',
             'dossier-doc-ars',             $row->ars_date],
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
            echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url($route, ['id' => $row->id])) . '" target="_blank">📄 Ouvrir la lettre (imprimable)</a>';
            if (!$sent) {
                echo '<form method="post" style="display:inline;margin:0">';
                wp_nonce_field('lfi_dossier_mark_' . $key);
                echo '<input type="hidden" name="lfi_dossier_mark_' . $key . '" value="1">';
                echo '<button type="submit" class="btn-ghost">📨 Marquer envoyée</button>';
                echo '</form>';
            }
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';

        /* Suppression définitive */
        echo '<form method="post" style="margin-top:24px" onsubmit="return confirm(\'Supprimer définitivement ce dossier ? Action irréversible.\')">';
        wp_nonce_field('lfi_dossier_delete');
        echo '<input type="hidden" name="lfi_dossier_delete" value="1">';
        echo '<button type="submit" style="background:#a30b25;color:#fff;border:0;padding:10px 16px;border-radius:8px;font-weight:700;cursor:pointer">🗑 Supprimer ce dossier</button>';
        echo '</form>';
    }

    lfi_nct_app_screen_close();
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
}

/* ============================================================== *
 *  LETTRE 1 : Mise en demeure travaux urgents                       *
 * ============================================================== */
function lfi_nct_app_view_dossier_doc_lrar_travaux() {
    if (!lfi_nct_can_use_brigade()) return;
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

    echo '<div class="citations">';
    echo '<ul>';
    echo '<li>de <strong>diligenter sous QUINZE (15) JOURS</strong> à compter de la réception des présentes une visite contradictoire du logement, en ma présence ;</li>';
    echo '<li>de <strong>réaliser, dans un délai maximal d\'UN (1) MOIS</strong>, l\'intégralité des travaux nécessaires à la remise en conformité du logement avec les critères de décence ;</li>';
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
    if (!lfi_nct_can_use_brigade()) return;
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
    if (!lfi_nct_can_use_brigade()) return;
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
    if (!lfi_nct_can_use_brigade()) return;
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
