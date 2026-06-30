<?php
/**
 * PARCOURS GUIDÉ de création d'un dossier locataire (assistant pas-à-pas).
 *
 * Étape 1 : identité + logement → enregistre, crée le dossier.
 * Étape 2 : situation (objectif, désordres, urgence sanitaire, situation éco
 *           pour l'aide juridictionnelle).
 * Étape 3 : PLAN D'ACTION généré automatiquement selon les réponses (les
 *           « étapes types » que l'utilisateur n'a pas à saisir lui-même),
 *           avec un bouton vers chaque outil (courriers, AJ, préfecture…).
 */
if (!defined('ABSPATH')) exit;

/** Catalogue des désordres (parcours guidé). */
function lfi_nct_wizard_desordres() {
    return [
        'humidite'        => 'Humidité / moisissures',
        'nuisibles'       => 'Nuisibles (cafards, punaises, rongeurs)',
        'chauffage'       => 'Chauffage défaillant / eau chaude',
        'electricite'     => 'Électricité',
        'fuite'           => "Fuite d'eau / dégât des eaux",
        'volet'           => 'Volet / menuiserie / ventilation',
        'parties_communes'=> 'Parties communes / ascenseur',
    ];
}

/** Catalogue des objectifs principaux. */
function lfi_nct_wizard_objectifs() {
    return [
        'relogement'    => '🏠 Être relogé·e (déménager)',
        'travaux'       => '🔧 Obtenir des travaux (rester dans le logement)',
        'indemnisation' => '⚖️ Être indemnisé·e (préjudices)',
        'constat'       => '🏛️ Faire constater l\'insalubrité',
    ];
}

/** Lit le bloc « wizard » stocké dans les notes JSON du dossier. */
function lfi_nct_wizard_data($row) {
    $logs = json_decode($row->notes ?? '', true);
    return (is_array($logs) && !empty($logs['wizard']) && is_array($logs['wizard'])) ? $logs['wizard'] : [];
}

/**
 * Génère le PLAN D'ACTION (étapes types) selon les réponses.
 * Chaque étape : [icône, titre, détail, url].
 */
function lfi_nct_wizard_plan($w, $id) {
    $obj = $w['objectif'] ?? '';
    $urg = !empty($w['urgence']);
    $des = (array) ($w['desordres'] ?? []);
    $insalubre = $urg || array_intersect($des, ['humidite', 'nuisibles', 'fuite']);

    $L = function ($letter) use ($id) { return lfi_nct_app_url('dossier-send-email', ['id' => $id, 'letter' => $letter]); };
    $plan = [];

    if ($urg || $obj === 'relogement') {
        $plan[] = ['🆘', 'Demande de relogement (LRAR)', 'Courrier au bailleur — relogement prioritaire' . ($urg ? ' (urgence médicale)' : ''), $L('lrar_relogement')];
    }
    if ($obj === 'travaux' || ($des && $obj !== 'relogement')) {
        $plan[] = ['🔧', 'Mise en demeure de travaux (LRAR)', 'Courrier au bailleur — travaux urgents sous délai', $L('lrar_travaux')];
    }
    if ($insalubre || $obj === 'constat') {
        $plan[] = ['🏛️', 'Saisine du service d\'hygiène (SCHS)', 'Signalement d\'insalubrité — Ville de Nantes (M. Lejeune)', $L('schs')];
    }
    if ($urg) {
        $plan[] = ['⚕️', 'Saisine de l\'ARS', 'Risque sanitaire — occupant vulnérable', $L('ars')];
        $plan[] = ['📄', 'Préparer un recours DALO', 'Si pas de relogement adapté sous 1 mois', lfi_nct_app_url('dossier-juridique-edit', ['id' => $id])];
    }
    $plan[] = ['🏛️', 'Informer la préfecture', 'Déléguée du Préfet — appui institutionnel', lfi_nct_app_url('prefecture-email', ['type' => 'prefecture'])];
    $plan[] = ['⚖️', 'Vérifier l\'aide juridictionnelle', ($w['aj_libelle'] ?? 'Calcul selon les revenus'), lfi_nct_app_url('aj-calcul')];
    if ($obj === 'indemnisation' || $urg) {
        $plan[] = ['📑', 'Synthèse pour l\'avocat', 'Réunir les preuves et transmettre le dossier', lfi_nct_app_url('doc-strategie-avocats')];
    }
    $plan[] = ['🪪', 'Faire adhérer le/la locataire', 'Mandat d\'accompagnement de l\'association', lfi_nct_app_url('dossier-doc-adhesion', ['id' => $id])];

    return $plan;
}

/* ============================================================== *
 *  VUE : parcours guidé (3 étapes)                                *
 * ============================================================== */
function lfi_nct_app_view_dossier_wizard() {
    if (!lfi_nct_app_guard_brigade()) return;
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $owner = (int) lfi_nct_dossier_owner_id();

    /* --- ÉTAPE 1 : créer le dossier (identité + logement) --- */
    if (!empty($_POST['lfi_wizard_step1']) && check_admin_referer('lfi_wizard_step1')) {
        $data = [
            'owner_user_id'      => $owner,
            'tenant_prenom'      => sanitize_text_field(wp_unslash($_POST['tenant_prenom'] ?? '')),
            'tenant_nom'         => sanitize_text_field(wp_unslash($_POST['tenant_nom'] ?? '')),
            'tenant_adresse'     => sanitize_text_field(wp_unslash($_POST['tenant_adresse'] ?? '')),
            'tenant_etage'       => sanitize_text_field(wp_unslash($_POST['tenant_etage'] ?? '')),
            'tenant_appartement' => sanitize_text_field(wp_unslash($_POST['tenant_appartement'] ?? '')),
            'tenant_tel'         => sanitize_text_field(wp_unslash($_POST['tenant_tel'] ?? '')),
            'tenant_email'       => sanitize_email(wp_unslash($_POST['tenant_email'] ?? '')),
            'statut'             => 'ouvert',
        ];
        $wpdb->insert($t, $data);
        $new_id = (int) $wpdb->insert_id;
        wp_safe_redirect(lfi_nct_app_url('dossier-wizard', ['id' => $new_id, 'step' => 2]));
        exit;
    }

    /* --- ÉTAPE 2 : situation → enregistre + calcule l'AJ --- */
    if (!empty($_POST['lfi_wizard_step2']) && check_admin_referer('lfi_wizard_step2')) {
        $id = (int) ($_POST['id'] ?? 0);
        $row = lfi_nct_dossier_get($id);
        if ($row) {
            $objectif = sanitize_key($_POST['objectif'] ?? '');
            $desordres = array_values(array_intersect(array_keys(lfi_nct_wizard_desordres()), (array) ($_POST['desordres'] ?? [])));
            $urgence = !empty($_POST['urgence']);
            $rfr = (float) ($_POST['aj_rfr'] ?? 0);
            $nbc = (int) ($_POST['aj_nbcharge'] ?? 0);

            $aj = function_exists('lfi_nct_aj_evaluer') ? lfi_nct_aj_evaluer($rfr, $nbc) : ['libelle' => '', 'niveau' => '', 'taux' => 0];

            /* Constatations lisibles depuis les désordres + texte libre. */
            $labels = lfi_nct_wizard_desordres();
            $cons_list = array_map(function ($k) use ($labels) { return $labels[$k]; }, $desordres);
            $cons_txt = trim(($cons_list ? implode(', ', $cons_list) . '.' : '') . "\n" . sanitize_textarea_field(wp_unslash($_POST['constat_libre'] ?? '')));

            /* Demandes (clés cohérentes avec le reste de l'app). */
            $map = ['relogement' => 'relogement_urgent', 'travaux' => 'travaux_urgents', 'indemnisation' => 'indemnisation', 'constat' => 'constat_insalubrite'];
            $demandes = isset($map[$objectif]) ? [$map[$objectif]] : [];
            if ($urgence) $demandes[] = 'urgence_sanitaire';

            $wizard = [
                'objectif'  => $objectif,
                'desordres' => $desordres,
                'urgence'   => $urgence,
                'aj_rfr'    => $rfr,
                'aj_nbcharge' => $nbc,
                'aj_niveau' => $aj['niveau'],
                'aj_taux'   => $aj['taux'],
                'aj_libelle'=> $aj['libelle'],
            ];

            $logs = json_decode($row->notes ?? '', true);
            if (!is_array($logs)) $logs = ['__notes' => $row->notes ?? ''];
            $logs['wizard'] = $wizard;

            $upd = [
                'constatations'        => $cons_txt,
                'certificat_medecin'   => sanitize_text_field(wp_unslash($_POST['certificat_medecin'] ?? '')),
                'certificat_pathologie'=> sanitize_textarea_field(wp_unslash($_POST['certificat_pathologie'] ?? '')),
                'demandes'             => wp_json_encode(array_values(array_unique($demandes))),
                'notes'                => wp_json_encode($logs, JSON_UNESCAPED_UNICODE),
            ];
            $wpdb->update($t, $upd, ['id' => $id, 'owner_user_id' => $owner]);
        }
        wp_safe_redirect(lfi_nct_app_url('dossier-wizard', ['id' => $id, 'step' => 3]));
        exit;
    }

    $id   = (int) ($_GET['id'] ?? 0);
    $step = (int) ($_GET['step'] ?? 1);
    $row  = $id ? lfi_nct_dossier_get($id) : null;
    if ($step > 1 && !$row) { $step = 1; }

    /* Fil d'étapes */
    $steps_lbl = [1 => 'Locataire', 2 => 'Situation', 3 => 'Plan d\'action'];
    $progress = function () use ($steps_lbl, $step) {
        $h = '<div style="display:flex;gap:6px;margin:0 0 14px">';
        foreach ($steps_lbl as $n => $lbl) {
            $on = $n <= $step;
            $h .= '<div style="flex:1;text-align:center;font-size:.8em;padding:6px 4px;border-radius:6px;background:' . ($on ? '#186a3b' : '#eee') . ';color:' . ($on ? '#fff' : '#888') . '">' . $n . '. ' . esc_html($lbl) . '</div>';
        }
        $h .= '</div>';
        return $h;
    };

    /* =================== ÉTAPE 1 =================== */
    if ($step === 1) {
        lfi_nct_app_screen_open('🧭 Nouveau dossier — étape 1/3', 'Le/la locataire et son logement');
        echo $progress();
        echo '<form method="post" class="lfi-app-form">';
        wp_nonce_field('lfi_wizard_step1');
        echo '<input type="hidden" name="lfi_wizard_step1" value="1">';
        echo '<label>Prénom<input type="text" name="tenant_prenom"></label>';
        echo '<label>Nom<input type="text" name="tenant_nom" required></label>';
        echo '<label>Adresse du logement<input type="text" name="tenant_adresse" placeholder="ex : 8 rue de Saint-Jean-de-Luz, 44200 Nantes"></label>';
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">';
        echo '<label>Étage<input type="text" name="tenant_etage"></label>';
        echo '<label>Appartement<input type="text" name="tenant_appartement"></label>';
        echo '</div>';
        echo '<label>Téléphone<input type="tel" name="tenant_tel"></label>';
        echo '<label>Email<input type="email" name="tenant_email"></label>';
        echo '<button type="submit" class="btn-primary big">Enregistrer et continuer →</button>';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossiers-juridiques')) . '">← Annuler</a>';
        echo '</form>';
        lfi_nct_render_voice_helper();
        lfi_nct_app_screen_close();
        return;
    }

    /* =================== ÉTAPE 2 =================== */
    if ($step === 2) {
        $tenant_full = trim($row->tenant_prenom . ' ' . $row->tenant_nom);
        lfi_nct_app_screen_open('🧭 Étape 2/3 — Situation', $tenant_full ?: ('Dossier #' . $id));
        echo $progress();
        echo '<form method="post" class="lfi-app-form">';
        wp_nonce_field('lfi_wizard_step2');
        echo '<input type="hidden" name="lfi_wizard_step2" value="1"><input type="hidden" name="id" value="' . (int) $id . '">';

        echo '<fieldset style="border:1px solid #ddd;border-radius:8px;padding:10px;margin:8px 0"><legend style="font-weight:700">🎯 Objectif principal</legend>';
        foreach (lfi_nct_wizard_objectifs() as $k => $lbl) {
            echo '<label style="display:flex;align-items:center;gap:8px;margin:4px 0;font-weight:400"><input type="radio" name="objectif" value="' . esc_attr($k) . '"' . ($k === 'relogement' ? ' checked' : '') . '> ' . esc_html($lbl) . '</label>';
        }
        echo '</fieldset>';

        echo '<fieldset style="border:1px solid #ddd;border-radius:8px;padding:10px;margin:8px 0"><legend style="font-weight:700">🏚️ Désordres constatés</legend>';
        foreach (lfi_nct_wizard_desordres() as $k => $lbl) {
            echo '<label style="display:flex;align-items:center;gap:8px;margin:4px 0;font-weight:400"><input type="checkbox" name="desordres[]" value="' . esc_attr($k) . '"> ' . esc_html($lbl) . '</label>';
        }
        echo '<label>Précisions (optionnel)<textarea name="constat_libre" rows="2" placeholder="Détails, localisation, ancienneté…"></textarea></label>';
        echo '</fieldset>';

        echo '<fieldset style="border:1px solid #ddd;border-radius:8px;padding:10px;margin:8px 0"><legend style="font-weight:700">⚕️ Urgence sanitaire</legend>';
        echo '<label style="display:flex;align-items:center;gap:8px;margin:4px 0;font-weight:400"><input type="checkbox" name="urgence" value="1"> Personne vulnérable / santé affectée (certificat médical)</label>';
        echo '<label>Médecin (certificat)<input type="text" name="certificat_medecin" placeholder="ex : Dr …"></label>';
        echo '<label>Pathologie / impact santé<textarea name="certificat_pathologie" rows="2"></textarea></label>';
        echo '</fieldset>';

        echo '<fieldset style="border:1px solid #ddd;border-radius:8px;padding:10px;margin:8px 0"><legend style="font-weight:700">💶 Situation économique (aide juridictionnelle)</legend>';
        echo '<label>Revenu fiscal de référence (RFR) annuel<input type="number" name="aj_rfr" min="0" step="1" placeholder="ex : 12000"></label>';
        echo '<label>Nombre de personnes à charge<input type="number" name="aj_nbcharge" min="0" step="1" value="0"></label>';
        echo '<div class="lfi-app-help"><small>Le calcul de l\'aide juridictionnelle s\'affiche à l\'étape suivante.</small></div>';
        echo '</fieldset>';

        echo '<button type="submit" class="btn-primary big">Voir le plan d\'action →</button>';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-juridique-edit', ['id' => $id])) . '">Passer / éditer manuellement</a>';
        echo '</form>';
        lfi_nct_render_voice_helper();
        lfi_nct_app_screen_close();
        return;
    }

    /* =================== ÉTAPE 3 : PLAN =================== */
    $tenant_full = trim($row->tenant_prenom . ' ' . $row->tenant_nom);
    $w = lfi_nct_wizard_data($row);
    lfi_nct_app_screen_open('🧭 Étape 3/3 — Plan d\'action', $tenant_full ?: ('Dossier #' . $id));
    echo $progress();

    /* Rappel objectif + AJ */
    $objs = lfi_nct_wizard_objectifs();
    echo '<div class="lfi-app-card" style="background:#fff;border-radius:10px;padding:14px 16px;margin:6px 0">';
    if (!empty($w['objectif']) && isset($objs[$w['objectif']])) echo '<div><strong>Objectif :</strong> ' . esc_html($objs[$w['objectif']]) . '</div>';
    if (!empty($w['aj_libelle'])) echo '<div style="margin-top:4px"><strong>Aide juridictionnelle :</strong> ' . esc_html($w['aj_libelle']) . '</div>';
    echo '</div>';

    echo '<div class="lfi-app-help" style="background:#e8f5ea;border-left:4px solid #186a3b">Voici les <strong>étapes recommandées</strong> d\'après la situation. Clique sur chacune pour ouvrir l\'outil pré-rempli. Tu peux les faire dans l\'ordre.</div>';

    $plan = lfi_nct_wizard_plan($w, $id);
    echo '<ol class="lfi-app-list" style="counter-reset:none">';
    foreach ($plan as $p) {
        echo '<li class="lfi-app-card" style="border-left:4px solid #186a3b;margin-bottom:8px">';
        echo '<div class="com"><strong>' . $p[0] . ' ' . esc_html($p[1]) . '</strong></div>';
        echo '<div class="meta" style="color:#555;font-size:.9em">' . esc_html($p[2]) . '</div>';
        echo '<a class="btn-primary" style="margin-top:6px;display:inline-block;padding:5px 12px;font-size:.9em" href="' . esc_url($p[3]) . '">Ouvrir →</a>';
        echo '</li>';
    }
    echo '</ol>';

    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">';
    echo '<a class="btn-primary big" href="' . esc_url(lfi_nct_app_url('dossier-juridique-edit', ['id' => $id])) . '">✓ Terminer — ouvrir le dossier</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-wizard', ['id' => $id, 'step' => 2])) . '">← Modifier les réponses</a>';
    echo '</div>';

    lfi_nct_app_screen_close();
}
