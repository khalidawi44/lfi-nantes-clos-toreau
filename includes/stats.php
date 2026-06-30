<?php
if (!defined('ABSPATH')) exit;

/**
 * Dictionnaire de labels lisibles pour chaque value brute stockée en DB.
 */
function lfi_nct_value_labels() {
    return [
        'insectes_presence' => ['oui' => 'Oui', 'non' => 'Non'],
        'insectes_types' => [
            'cafards' => 'Cafards', 'punaises_lit' => 'Punaises de lit',
            'rongeurs' => 'Rongeurs', 'fourmis' => 'Fourmis', 'autres' => 'Autres',
        ],
        'insectes_depuis' => [
            'moins_6mois' => 'Moins de 6 mois', '6_12mois' => '6 à 12 mois', 'plus_1an' => "Plus d'un an",
        ],
        'humidite_presence' => [
            'oui_visible' => 'Oui, traces visibles',
            'oui_ressentie' => 'Oui, ressentie sans traces',
            'oui_suspicion' => 'Oui, suspicion',
            'non' => 'Non',
        ],
        'humidite_loc' => [
            'salon_mur_ext_bas' => 'Salon — mur ext bas', 'salon_mur_ext_haut' => 'Salon — mur ext haut',
            'salon_plafond' => 'Salon — plafond', 'salon_fenetres' => 'Salon — fenêtres',
            'salon_derriere_meubles' => 'Salon — derrière meubles',
            'chambre_mur_ext_bas' => 'Chambre — mur ext bas', 'chambre_mur_ext_haut' => 'Chambre — mur ext haut',
            'chambre_plafond' => 'Chambre — plafond', 'chambre_fenetres' => 'Chambre — fenêtres',
            'chambre_derriere_meubles' => 'Chambre — derrière meubles',
            'cuisine_sous_evier' => 'Cuisine — sous évier', 'cuisine_vmc' => 'Cuisine — VMC',
            'cuisine_plafond' => 'Cuisine — plafond', 'cuisine_derriere_meubles' => 'Cuisine — derrière meubles',
            'sdb_joints' => 'SDB — joints', 'sdb_plafond' => 'SDB — plafond',
            'sdb_baignoire' => 'SDB — baignoire', 'sdb_lavabo' => 'SDB — lavabo',
            'wc_separe' => 'WC séparés', 'couloir_plafond' => 'Couloir — plafond',
            'couloir_porte' => 'Couloir — porte palière', 'cave' => 'Cave/cellier',
            'combles' => 'Combles', 'garage' => 'Garage', 'autres' => 'Autres',
        ],
        'humidite_consequences' => [
            'moisissures_noires' => 'Moisissures noires', 'moisissures_vertes' => 'Moisissures vertes/blanches',
            'salpetre' => 'Salpêtre', 'peinture_cloque' => 'Peinture cloque',
            'papier_peint_decolle' => 'Papier peint décolle', 'platre_effrite' => 'Plâtre s\'effrite',
            'carrelage_descelle' => 'Carrelage descellé', 'parquet_gondole' => 'Parquet gondolé',
            'bois_pourri' => 'Bois pourri', 'odeur_renferme' => 'Odeur renfermé',
            'condensation_vitres' => 'Condensation vitres', 'mur_froid' => 'Mur froid',
            'linge_humide' => 'Linge humide', 'sante_respi' => 'Asthme/allergies',
            'sante_tete' => 'Maux de tête', 'degradation_biens' => 'Dégradation biens',
            'surconso_chauffage' => 'Surconso chauffage', 'autres' => 'Autres',
        ],
        'thermique_type' => [
            'sol_collectif_nmh' => 'Sol collectif NMH',
            'sol_avec_appoint' => 'Sol collectif + appoint perso',
            'individuel_gaz' => 'Individuel gaz', 'individuel_elec' => 'Individuel électrique',
            'aucun' => 'Aucun chauffage fonctionnel',
        ],
        'thermique_adequation' => [
            'oui_toujours' => 'Oui toujours', 'oui_limite' => 'Oui mais limite (19°C max)',
            'partiel' => 'Partiellement', 'non_18' => 'Non, 17-18°C max',
            'non_16' => 'Non, < 16°C', 'non_panne' => 'Non, en panne',
        ],
        'thermique_appoint' => [
            'oui_permanent' => 'Oui permanent', 'oui_quotidien' => 'Oui quotidien',
            'oui_ponctuel' => 'Oui ponctuel', 'oui_passe' => 'Eu, plus utilisé',
            'non' => 'Jamais',
        ],
        'thermique_appoint_types' => [
            'radiateur_convecteur' => 'Radiateur convecteur', 'radiateur_inertie' => 'Radiateur inertie',
            'soufflant' => 'Soufflant', 'gaz_portatif' => 'Gaz portatif',
            'petrole' => 'Pétrole', 'clim_reversible' => 'Clim réversible',
            'couvertures_chauffantes' => 'Couvertures chauffantes', 'bouillottes' => 'Bouillottes',
            'autres' => 'Autres',
        ],
        'thermique_appoint_cout' => [
            'moins_20' => '< 20 €/mois', '20_50' => '20-50 €/mois',
            '50_100' => '50-100 €/mois', '100_150' => '100-150 €/mois',
            'plus_150' => '> 150 €/mois', 'nsp' => 'Ne sait pas',
        ],
        'ete_confort' => [
            'confortable' => 'Confortable', 'trop_chaud_canicule' => 'Trop chaud canicule',
            'trop_chaud_souvent' => 'Trop chaud souvent', 'insupportable' => 'Insupportable',
        ],
        'thermique_infiltration' => [
            'oui_importantes' => 'Oui importantes', 'oui_moderees' => 'Oui modérées', 'non' => 'Non',
        ],
        'thermique_infiltration_origine' => [
            'fenetres' => 'Fenêtres', 'porte_entree' => 'Porte entrée',
            'portes_int' => 'Portes intérieures', 'volets' => 'Volets',
            'vmc' => 'VMC', 'prises' => 'Prises électriques',
            'coffrets_volets' => 'Coffrets volets', 'plinthes' => 'Plinthes',
            'fissures' => 'Fissures murs', 'cheminee' => 'Cheminée',
        ],
        'thermique_signale_nmh' => [
            'signale_resolu' => 'Signalé, résolu', 'signale_insuffisant' => 'Signalé, travaux insuffisants',
            'signale_pas_travaux' => 'Signalé, pas de travaux', 'signale_pas_reponse' => 'Signalé, pas de réponse',
            'non_signale' => 'Non signalé',
        ],
        'demarches_signale' => [
            'oui' => 'Oui', 'partiel' => 'Partiellement', 'non' => 'Non',
        ],
        'demarches_procedure' => [
            'oui_en_cours' => 'En cours', 'oui_passee' => 'Passée', 'non' => 'Non',
        ],
        'demarches_collectif' => [
            'oui' => 'Oui', 'a_voir' => 'À voir', 'non' => 'Non',
        ],
    ];
}

function lfi_nct_label($field, $value) {
    $labels = lfi_nct_value_labels();
    return $labels[$field][$value] ?? $value;
}

/**
 * Calcule toutes les statistiques agrégées.
 */
function lfi_nct_compute_stats() {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $scope = function_exists('lfi_nct_responses_scope_clause') ? lfi_nct_responses_scope_clause() : '';
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE deleted_at IS NULL" . $scope);

    $stats = [
        'total' => $total,
        'problemes_oui' => 0,
        'recontact'     => 0,
        'gravite_sum'   => 0,
        'gravite_count' => 0,
        'gravite_moyenne' => 0,
        'types_repartition' => [
            'degats_eaux' => 0, 'humidite' => 0, 'insectes' => 0, 'chauffage' => 0,
            'electricite' => 0, 'ascenseur' => 0, 'parties_communes' => 0,
            'bruit' => 0, 'securite' => 0, 'autre' => 0,
        ],
        'duree_repartition' => [
            'moins_1_mois' => 0, '1_6_mois' => 0, '6_12_mois' => 0,
            '1_5_ans' => 0, 'plus_5_ans' => 0,
        ],
        'recurrent_repartition' => ['permanent' => 0, 'parfois' => 0, 'ponctuel' => 0],
        'eau_chaude' => [
            'avec_donnee'  => 0, // nb d'enquêtes ayant au moins un champ rempli
            'durees_max'   => [], // textes bruts saisis (« 3 semaines », etc.)
            'nb_par_an'    => [], // textes bruts saisis
        ],
        'gravity'      => ['leger' => 0, 'preoccupant' => 0, 'grave' => 0, 'critique' => 0],
        'top_immeubles' => [],
    ];

    if ($total === 0) return $stats;

    $responses = $wpdb->get_results("SELECT data, contact_recontact FROM $table WHERE deleted_at IS NULL" . $scope);
    foreach ($responses as $r) {
        $data = json_decode($r->data, true);
        if (!is_array($data)) continue;

        // Gravité (niveau qualitatif via gravity.php)
        list($glvl) = lfi_nct_gravity_level(lfi_nct_gravity_score($data));
        if (isset($stats['gravity'][$glvl])) $stats['gravity'][$glvl]++;

        // Logements avec problèmes
        if (($data['problemes_presence'] ?? '') === 'oui') {
            $stats['problemes_oui']++;
        }

        // Types de problèmes (multi-cases)
        foreach ((array) ($data['problemes_types'] ?? []) as $t) {
            if (isset($stats['types_repartition'][$t])) $stats['types_repartition'][$t]++;
        }

        // Durée
        $duree = $data['problemes_duree'] ?? '';
        if (isset($stats['duree_repartition'][$duree])) $stats['duree_repartition'][$duree]++;

        // Récurrence
        $rec = $data['problemes_recurrent'] ?? '';
        if (isset($stats['recurrent_repartition'][$rec])) $stats['recurrent_repartition'][$rec]++;

        // Gravité moyenne (sur l'échelle 1-10)
        $g = (int) ($data['problemes_gravite'] ?? 0);
        if ($g > 0) { $stats['gravite_sum'] += $g; $stats['gravite_count']++; }

        if ((int) $r->contact_recontact === 1) $stats['recontact']++;

        // Coupures d'eau chaude — on récolte les chiffres bruts (fréquence + durée max).
        // Le constat global (100 % touché·es) est affiché en bandeau, pas calculé.
        $ec_nb    = trim((string) ($data['eau_chaude_nb_par_an'] ?? ''));
        $ec_duree = trim((string) ($data['eau_chaude_duree_max'] ?? ''));
        $ec_cit   = trim((string) ($data['eau_chaude_citation']  ?? ''));
        if ($ec_nb !== '' || $ec_duree !== '' || $ec_cit !== '') {
            $stats['eau_chaude']['avec_donnee']++;
        }
        if ($ec_duree !== '') $stats['eau_chaude']['durees_max'][] = $ec_duree;
        if ($ec_nb    !== '') $stats['eau_chaude']['nb_par_an'][]   = $ec_nb;
    }

    if ($stats['gravite_count'] > 0) {
        $stats['gravite_moyenne'] = round($stats['gravite_sum'] / $stats['gravite_count'], 1);
    }

    $top = $wpdb->get_results("SELECT adresse, COUNT(*) as nb FROM $table WHERE deleted_at IS NULL" . $scope . " GROUP BY adresse ORDER BY nb DESC LIMIT 10");
    $stats['top_immeubles'] = $top ?: [];

    return $stats;
}

/**
 * Récupère les réponses filtrées selon un critère.
 */
function lfi_nct_get_filtered_responses($filter) {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';

    $scope = function_exists('lfi_nct_responses_scope_clause') ? lfi_nct_responses_scope_clause() : '';
    // Filtres SQL purs (colonnes structurées)
    if ($filter === 'recontact') {
        return $wpdb->get_results("SELECT * FROM $table WHERE contact_recontact = 1 AND deleted_at IS NULL" . $scope . " ORDER BY submitted_at DESC");
    }

    // Filtres sur le champ JSON 'data' : on récupère tout puis on filtre en PHP
    $all = $wpdb->get_results("SELECT * FROM $table WHERE deleted_at IS NULL" . $scope . " ORDER BY submitted_at DESC");
    $filtered = [];

    foreach ($all as $r) {
        $data = json_decode($r->data, true);
        if (!is_array($data)) continue;

        $match = false;
        switch ($filter) {
            case 'problemes_oui':
                $match = ($data['problemes_presence'] ?? '') === 'oui';
                break;
            case 'type_degats_eaux':
            case 'type_humidite':
            case 'type_insectes':
            case 'type_chauffage':
            case 'type_electricite':
            case 'type_ascenseur':
            case 'type_parties_communes':
            case 'type_bruit':
            case 'type_securite':
            case 'type_autre':
                $needle = substr($filter, 5);
                $match  = in_array($needle, (array) ($data['problemes_types'] ?? []), true);
                break;
            case 'gravite_preoccupant':
                $match = lfi_nct_gravity_at_least($data, 'preoccupant');
                break;
            case 'gravite_grave':
                $match = lfi_nct_gravity_at_least($data, 'grave');
                break;
            case 'gravite_critique':
                $match = lfi_nct_gravity_at_least($data, 'critique');
                break;
        }
        if ($match) $filtered[] = $r;
    }
    return $filtered;
}

function lfi_nct_filter_label($filter) {
    return [
        'problemes_oui'      => 'Logements avec problèmes',
        'recontact'          => 'Souhaitent être recontactés',
        'type_degats_eaux'   => 'Dégâts des eaux / infiltrations',
        'type_humidite'      => 'Humidité / moisissures',
        'type_insectes'      => 'Insectes / nuisibles',
        'type_chauffage'     => 'Chauffage insuffisant',
        'type_electricite'   => 'Problèmes électriques',
        'type_ascenseur'     => 'Ascenseur défaillant',
        'type_parties_communes' => 'Parties communes dégradées',
        'type_bruit'         => 'Nuisances sonores',
        'type_securite'      => 'Insécurité',
        'type_autre'         => 'Autres problèmes',
        'gravite_preoccupant' => 'Cas au moins préoccupants',
        'gravite_grave'      => 'Cas graves ou critiques',
        'gravite_critique'   => 'Cas critiques',
    ][$filter] ?? $filter;
}

/**
 * Helper : transforme tableau pour Chart.js
 */
function lfi_nct_chart_data($repartition, $labels_map = []) {
    $labels = []; $data = [];
    foreach ($repartition as $key => $val) {
        $labels[] = $labels_map[$key] ?? $key;
        $data[] = $val;
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Page admin de statistiques (router : globale / filtrée / détail).
 */
function lfi_nct_stats_page() {
    $view = $_GET['view'] ?? '';
    $filter = $_GET['filter'] ?? '';

    if ($view !== '') {
        lfi_nct_render_response_detail((int) $view);
    } elseif ($filter !== '') {
        lfi_nct_render_filtered_view($filter);
    } else {
        lfi_nct_render_stats_overview();
    }
}

/**
 * Vue principale : cards + camemberts.
 */
function lfi_nct_render_stats_overview() {
    $stats = lfi_nct_compute_stats();
    $total = $stats['total'];
    $pct = function($n, $tot) { return $tot > 0 ? round($n / $tot * 100, 1) : 0; };
    $url = function($filter) { return admin_url('admin.php?page=lfi-nct-stats&filter=' . urlencode($filter)); };

    $chart_types = lfi_nct_chart_data($stats['types_repartition'], [
        'degats_eaux' => 'Dégâts des eaux', 'humidite' => 'Humidité', 'insectes' => 'Nuisibles',
        'chauffage' => 'Chauffage', 'electricite' => 'Électricité', 'ascenseur' => 'Ascenseur',
        'parties_communes' => 'Parties communes', 'bruit' => 'Bruit', 'securite' => 'Insécurité',
        'autre' => 'Autre',
    ]);
    $chart_duree = lfi_nct_chart_data($stats['duree_repartition'], [
        'moins_1_mois' => "<1 mois", '1_6_mois' => '1-6 mois', '6_12_mois' => '6-12 mois',
        '1_5_ans' => '>1 an', 'plus_5_ans' => '>5 ans',
    ]);
    $chart_recurrent = lfi_nct_chart_data($stats['recurrent_repartition'], [
        'permanent' => 'Permanent', 'parfois' => 'Régulier', 'ponctuel' => 'Ponctuel',
    ]);
    $chart_gravity = lfi_nct_chart_data($stats['gravity'], [
        'leger' => '🟢 Sans souci', 'preoccupant' => '🟡 Préoccupant',
        'grave' => '🔴 Grave', 'critique' => '🚨 Critique',
    ]);
    ?>
    <div class="wrap lfi-stats">
        <h1>📊 LFI Clos Toreau — Statistiques de l'enquête <?php echo lfi_nct_print_button('Imprimer les statistiques'); ?></h1>

        <?php if ($total === 0): ?>
            <div class="notice notice-info"><p>Aucune réponse enregistrée pour l'instant.</p></div>
        <?php else: ?>

        <p style="font-size:1.1em;margin-bottom:1.5em;">Basé sur <strong><?php echo $total; ?></strong> réponse<?php echo $total > 1 ? 's' : ''; ?>. <em>Cliquez sur une card pour voir le détail.</em></p>

        <h2 style="margin-top:0">Gravité ressentie</h2>
        <p class="description" style="margin-top:-.5em;margin-bottom:1em">Niveau déclaré par les habitant·es sur l'échelle 1-10 (moyenne : <strong><?php echo $stats['gravite_moyenne']; ?> / 10</strong>).</p>
        <div class="lfi-stats-cards">
            <a class="lfi-stats-card" style="background:#1a7f37;color:#fff" href="<?php echo esc_url($url('gravite_preoccupant')); ?>" title="Sans souci (pas de problème ou score 0)">
                <div class="nb"><?php echo $stats['gravity']['leger']; ?></div>
                <div class="label">🟢 Sans souci</div>
                <div class="abs"><?php echo $pct($stats['gravity']['leger'], $total); ?>%</div>
            </a>
            <a class="lfi-stats-card" style="background:#bd8600;color:#fff" href="<?php echo esc_url($url('gravite_preoccupant')); ?>">
                <div class="nb"><?php echo $stats['gravity']['preoccupant']; ?></div>
                <div class="label">🟡 Préoccupant</div>
                <div class="abs"><?php echo $pct($stats['gravity']['preoccupant'], $total); ?>%</div>
            </a>
            <a class="lfi-stats-card" style="background:#c8102e;color:#fff" href="<?php echo esc_url($url('gravite_grave')); ?>">
                <div class="nb"><?php echo $stats['gravity']['grave']; ?></div>
                <div class="label">🔴 Grave</div>
                <div class="abs"><?php echo $pct($stats['gravity']['grave'], $total); ?>%</div>
            </a>
            <a class="lfi-stats-card" style="background:#7a0000;color:#fff" href="<?php echo esc_url($url('gravite_critique')); ?>">
                <div class="nb"><?php echo $stats['gravity']['critique']; ?></div>
                <div class="label">🚨 Critique</div>
                <div class="abs"><?php echo $pct($stats['gravity']['critique'], $total); ?>%</div>
            </a>
        </div>

        <h2 style="margin-top:2em">Vue d'ensemble</h2>
        <div class="lfi-stats-cards">
            <div class="lfi-stats-card lfi-card-static">
                <div class="nb"><?php echo $total; ?></div>
                <div class="label">Réponses totales</div>
            </div>
            <a class="lfi-stats-card" href="<?php echo esc_url($url('problemes_oui')); ?>">
                <div class="nb"><?php echo $pct($stats['problemes_oui'], $total); ?>%</div>
                <div class="label">Logements avec problèmes</div>
                <div class="abs"><?php echo $stats['problemes_oui']; ?> / <?php echo $total; ?></div>
            </a>
            <a class="lfi-stats-card" href="<?php echo esc_url($url('recontact')); ?>">
                <div class="nb"><?php echo $pct($stats['recontact'], $total); ?>%</div>
                <div class="label">Souhaitent un RDV</div>
                <div class="abs"><?php echo $stats['recontact']; ?> / <?php echo $total; ?></div>
            </a>
            <div class="lfi-stats-card lfi-card-static">
                <div class="nb"><?php echo $stats['gravite_moyenne']; ?> /10</div>
                <div class="label">Gravité moyenne ressentie</div>
            </div>
        </div>

        <h2 style="margin-top:2em">🚿 Coupures d'eau chaude récurrentes</h2>
        <div class="lfi-fact-banner">
            <div class="lfi-fact-headline"><strong>100 %</strong> des locataires enquêté·es subissent les coupures d'eau chaude.</div>
            <ul class="lfi-fact-list">
                <li>Plus de <strong>10 coupures par an</strong></li>
                <li>Plus de <strong>10 jours cumulés</strong> sans eau chaude</li>
                <li>Durée d'une coupure variant de <strong>2 jours à 3 semaines consécutives</strong> selon les immeubles</li>
            </ul>
            <?php if (!empty($stats['eau_chaude']['durees_max']) || !empty($stats['eau_chaude']['nb_par_an'])): ?>
                <div class="lfi-fact-data">
                    <strong>Déclarations recueillies (<?php echo (int) $stats['eau_chaude']['avec_donnee']; ?> enquête<?php echo $stats['eau_chaude']['avec_donnee'] > 1 ? 's' : ''; ?>) :</strong>
                    <?php if (!empty($stats['eau_chaude']['durees_max'])): ?>
                        <div>Plus longue coupure subie :
                            <?php
                            $uniq = array_count_values($stats['eau_chaude']['durees_max']);
                            arsort($uniq);
                            $pairs = [];
                            foreach ($uniq as $val => $cnt) {
                                $pairs[] = esc_html($val) . ' ×' . $cnt;
                            }
                            echo implode(' · ', $pairs);
                            ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($stats['eau_chaude']['nb_par_an'])): ?>
                        <div>Coupures par an déclarées :
                            <?php
                            $uniq = array_count_values($stats['eau_chaude']['nb_par_an']);
                            arsort($uniq);
                            $pairs = [];
                            foreach ($uniq as $val => $cnt) {
                                $pairs[] = esc_html($val) . ' ×' . $cnt;
                            }
                            echo implode(' · ', $pairs);
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <h2 style="margin-top:2em">Types de problèmes (multi-cases)</h2>
        <div class="lfi-stats-cards">
            <?php
            $type_meta = [
                'degats_eaux' => ['💧 Dégâts des eaux', 'type_degats_eaux'],
                'humidite'    => ['🌫️ Humidité', 'type_humidite'],
                'insectes'    => ['🐜 Insectes / nuisibles', 'type_insectes'],
                'chauffage'   => ['🥶 Chauffage', 'type_chauffage'],
                'electricite' => ['⚡ Électricité', 'type_electricite'],
                'ascenseur'   => ['🛗 Ascenseur', 'type_ascenseur'],
                'parties_communes' => ['🚪 Parties communes', 'type_parties_communes'],
                'bruit'       => ['🔊 Bruit', 'type_bruit'],
                'securite'    => ['🚨 Insécurité', 'type_securite'],
                'autre'       => ['Autre', 'type_autre'],
            ];
            foreach ($type_meta as $k => $meta):
                $cnt = $stats['types_repartition'][$k] ?? 0;
                if ($cnt === 0) continue;
            ?>
                <a class="lfi-stats-card" href="<?php echo esc_url($url($meta[1])); ?>">
                    <div class="nb"><?php echo $pct($cnt, $total); ?>%</div>
                    <div class="label"><?php echo esc_html($meta[0]); ?></div>
                    <div class="abs"><?php echo $cnt; ?> / <?php echo $total; ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="lfi-stats-charts">
            <div class="lfi-chart-box"><h3>Répartition des types de problèmes</h3><canvas id="chart-types"></canvas></div>
            <div class="lfi-chart-box"><h3>Depuis combien de temps</h3><canvas id="chart-duree"></canvas></div>
            <div class="lfi-chart-box"><h3>Récurrence</h3><canvas id="chart-recurrent"></canvas></div>
            <div class="lfi-chart-box"><h3>Niveau de gravité</h3><canvas id="chart-gravity"></canvas></div>
        </div>

        <div class="lfi-stats-section">
            <h2>🏢 Top 10 immeubles enquêtés</h2>
            <table class="wp-list-table widefat striped">
                <thead><tr><th>Immeuble (adresse)</th><th>Nombre d'enquêtes</th></tr></thead>
                <tbody>
                <?php if (empty($stats['top_immeubles'])): ?>
                    <tr><td colspan="2"><em>Aucune donnée</em></td></tr>
                <?php else: foreach ($stats['top_immeubles'] as $im): ?>
                    <tr><td><?php echo esc_html($im->adresse); ?></td><td><strong><?php echo $im->nb; ?></strong></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </div>

    <?php lfi_nct_stats_styles(); ?>

    <?php if ($total > 0): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Chart === 'undefined') return;
        const palette = ['#c8102e', '#e74c3c', '#f39c12', '#f1c40f', '#2ecc71', '#3498db', '#9b59b6', '#34495e', '#95a5a6'];
        const opts = {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 12 } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? Math.round(ctx.parsed / total * 100) : 0;
                            return ctx.label + ' : ' + ctx.parsed + ' (' + pct + '%)';
                        }
                    }
                }
            }
        };
        function makePie(id, labels, data) {
            const el = document.getElementById(id);
            if (!el) return;
            new Chart(el, {
                type: 'pie',
                data: { labels: labels, datasets: [{ data: data, backgroundColor: palette }] },
                options: opts
            });
        }
        makePie('chart-types', <?php echo wp_json_encode($chart_types['labels']); ?>, <?php echo wp_json_encode($chart_types['data']); ?>);
        makePie('chart-duree', <?php echo wp_json_encode($chart_duree['labels']); ?>, <?php echo wp_json_encode($chart_duree['data']); ?>);
        makePie('chart-recurrent', <?php echo wp_json_encode($chart_recurrent['labels']); ?>, <?php echo wp_json_encode($chart_recurrent['data']); ?>);
        makePie('chart-gravity', <?php echo wp_json_encode($chart_gravity['labels']); ?>, <?php echo wp_json_encode($chart_gravity['data']); ?>);
    });
    </script>
    <?php endif; ?>
    <?php
}

/**
 * Vue filtrée : tableau des réponses qui matchent le filtre.
 */
function lfi_nct_render_filtered_view($filter) {
    $responses = lfi_nct_get_filtered_responses($filter);
    $label = lfi_nct_filter_label($filter);
    $back = admin_url('admin.php?page=lfi-nct-stats');
    $is_recontact = ($filter === 'recontact');
    ?>
    <div class="wrap lfi-stats">
        <p><a href="<?php echo esc_url($back); ?>" class="button">← Retour aux statistiques</a></p>
        <h1>🔍 Filtre : <?php echo esc_html($label); ?> <?php echo lfi_nct_print_button('Imprimer cette sélection'); ?></h1>
        <p><strong><?php echo count($responses); ?></strong> réponse<?php echo count($responses) > 1 ? 's' : ''; ?> correspondante<?php echo count($responses) > 1 ? 's' : ''; ?>.</p>

        <?php if (empty($responses)): ?>
            <div class="notice notice-info"><p>Aucune réponse ne correspond à ce filtre.</p></div>
        <?php else: ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>ID</th><th>Date</th><th>Adresse</th><th>Étage</th>
                    <?php if ($is_recontact): ?>
                        <th>Prénom</th><th>Nom</th><th>Téléphone</th><th>Email</th><th>App.</th>
                    <?php endif; ?>
                    <th>Détail complet</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($responses as $r): ?>
                <tr>
                    <td>#<?php echo $r->id; ?></td>
                    <td><?php echo esc_html($r->submitted_at); ?></td>
                    <td><?php echo esc_html($r->adresse); ?></td>
                    <td><?php echo esc_html($r->etage); ?></td>
                    <?php if ($is_recontact): ?>
                        <td><?php echo esc_html($r->contact_prenom); ?></td>
                        <td><?php echo esc_html($r->contact_nom); ?></td>
                        <td><?php echo esc_html($r->contact_tel); ?></td>
                        <td><?php echo esc_html($r->contact_email); ?></td>
                        <td><?php
                            $data = json_decode($r->data, true);
                            echo esc_html($data['contact_appartement'] ?? '');
                        ?></td>
                    <?php endif; ?>
                    <td><a href="<?php echo esc_url(admin_url('admin.php?page=lfi-nct-stats&view=' . $r->id)); ?>" class="button button-small">Voir tout</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php lfi_nct_stats_styles(); ?>
    <?php
}

/**
 * Vue détail d'une réponse complète (toutes sections).
 */
function lfi_nct_render_response_detail($id) {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    $back_filter = $_GET['from'] ?? '';
    $back_url = $back_filter ? admin_url('admin.php?page=lfi-nct-stats&filter=' . urlencode($back_filter)) : admin_url('admin.php?page=lfi-nct-stats');

    if (!$r) {
        echo '<div class="wrap"><p><a href="' . esc_url($back_url) . '" class="button">← Retour</a></p><div class="notice notice-error"><p>Réponse introuvable.</p></div></div>';
        return;
    }

    $data = json_decode($r->data, true) ?: [];
    $L = lfi_nct_value_labels();
    $val = function($field) use ($data, $L) {
        $v = $data[$field] ?? '';
        return $v === '' ? '<em style="color:#999">non renseigné</em>' : esc_html($L[$field][$v] ?? $v);
    };
    $multi = function($field) use ($data, $L) {
        $vs = $data[$field] ?? [];
        if (!is_array($vs) || empty($vs)) return '<em style="color:#999">aucun</em>';
        $out = [];
        foreach ($vs as $v) $out[] = esc_html($L[$field][$v] ?? $v);
        return implode(', ', $out);
    };
    $text = function($field) use ($data) {
        $v = $data[$field] ?? '';
        return $v === '' ? '<em style="color:#999">non renseigné</em>' : nl2br(esc_html($v));
    };
    ?>
    <div class="wrap lfi-stats">
        <p><a href="<?php echo esc_url($back_url); ?>" class="button">← Retour</a></p>
        <h1>📄 Réponse #<?php echo $r->id; ?> — détail complet <?php echo lfi_nct_print_button('Imprimer ce formulaire rempli'); ?></h1>
        <p><strong>Soumise le :</strong> <?php echo esc_html($r->submitted_at); ?> par <strong><?php echo esc_html($r->militant_login); ?></strong></p>

        <div class="lfi-detail-section">
            <h2>Section 1 — Logement</h2>
            <ul>
                <li><strong>Adresse :</strong> <?php echo esc_html($r->adresse); ?></li>
                <li><strong>Bailleur :</strong> Nantes Métropole Habitat</li>
                <li><strong>Étage :</strong> <?php echo esc_html($r->etage); ?></li>
                <li><strong>Année d'arrivée :</strong> <?php echo esc_html($r->annee_arrivee); ?></li>
            </ul>
        </div>

        <div class="lfi-detail-section">
            <h2>Section 2 — Insectes / nuisibles</h2>
            <ul>
                <li><strong>Présence :</strong> <?php echo $val('insectes_presence'); ?></li>
                <li><strong>Types :</strong> <?php echo $multi('insectes_types'); ?>
                    <?php if (!empty($data['insectes_types_autres'])): ?> (Autres : <?php echo esc_html($data['insectes_types_autres']); ?>)<?php endif; ?>
                </li>
                <li><strong>Depuis quand :</strong> <?php echo $val('insectes_depuis'); ?></li>
                <li><strong>Gravité :</strong> <?php echo esc_html($data['insectes_gravite'] ?? '—'); ?> / 5</li>
            </ul>
        </div>

        <div class="lfi-detail-section">
            <h2>Section 3 — Humidité</h2>
            <ul>
                <li><strong>Présence :</strong> <?php echo $val('humidite_presence'); ?></li>
                <li><strong>Localisations :</strong> <?php echo $multi('humidite_loc'); ?>
                    <?php if (!empty($data['humidite_loc_autres'])): ?> (Autres : <?php echo esc_html($data['humidite_loc_autres']); ?>)<?php endif; ?>
                </li>
                <li><strong>Gravité :</strong> <?php echo esc_html($data['humidite_gravite'] ?? '—'); ?> / 5</li>
                <li><strong>Conséquences :</strong> <?php echo $multi('humidite_consequences'); ?>
                    <?php if (!empty($data['humidite_consequences_autres'])): ?> (Autres : <?php echo esc_html($data['humidite_consequences_autres']); ?>)<?php endif; ?>
                </li>
            </ul>
        </div>

        <div class="lfi-detail-section">
            <h2>Section 4 — Thermique</h2>
            <ul>
                <li><strong>Type chauffage :</strong> <?php echo $val('thermique_type'); ?></li>
                <li><strong>Adéquation NMH :</strong> <?php echo $val('thermique_adequation'); ?></li>
                <li><strong>Température mesurée :</strong> <?php echo isset($data['thermique_temperature']) && $data['thermique_temperature'] !== '' ? esc_html($data['thermique_temperature']) . '°C' : '<em style="color:#999">non mesurée</em>'; ?></li>
                <li><strong>Appoint perso :</strong> <?php echo $val('thermique_appoint'); ?></li>
                <li><strong>Types d'appoint :</strong> <?php echo $multi('thermique_appoint_types'); ?>
                    <?php if (!empty($data['thermique_appoint_types_autres'])): ?> (Autres : <?php echo esc_html($data['thermique_appoint_types_autres']); ?>)<?php endif; ?>
                </li>
                <li><strong>Surcoût appoint :</strong> <?php echo $val('thermique_appoint_cout'); ?></li>
                <li><strong>Confort été :</strong> <?php echo $val('ete_confort'); ?></li>
                <li><strong>Infiltration d'air :</strong> <?php echo $val('thermique_infiltration'); ?></li>
                <li><strong>Origines infiltration :</strong> <?php echo $multi('thermique_infiltration_origine'); ?></li>
                <li><strong>Isolation ressentie :</strong> <?php echo esc_html($data['thermique_isolation'] ?? '—'); ?> / 5</li>
                <li><strong>Signalement NMH thermique :</strong> <?php echo $val('thermique_signale_nmh'); ?></li>
            </ul>
        </div>

        <div class="lfi-detail-section">
            <h2>Section 6 — Démarches déjà entreprises</h2>
            <ul>
                <li><strong>Signalement à NMH :</strong> <?php echo $val('demarches_signale'); ?></li>
                <li><strong>Réponse de NMH :</strong> <?php echo $text('demarches_reponse_nmh'); ?></li>
                <li><strong>Procédure judiciaire :</strong> <?php echo $val('demarches_procedure'); ?></li>
                <li><strong>Précisions procédure :</strong> <?php echo $text('demarches_procedure_precisions'); ?></li>
                <li><strong>Intérêt dossier collectif :</strong> <?php echo $val('demarches_collectif'); ?></li>
            </ul>
        </div>

        <div class="lfi-detail-section">
            <h2>Section 7 — Demande du locataire</h2>
            <p><?php echo $text('demande_locataire'); ?></p>
        </div>

        <div class="lfi-detail-section">
            <h2>🚿 Coupures d'eau chaude récurrentes</h2>
            <ul>
                <li><strong>Coupures par an déclarées :</strong> <?php echo $text('eau_chaude_nb_par_an'); ?></li>
                <li><strong>Plus longue coupure subie :</strong> <?php echo $text('eau_chaude_duree_max'); ?></li>
                <li><strong>Déclaration verbatim :</strong> <?php echo $text('eau_chaude_citation'); ?></li>
            </ul>
        </div>

        <div class="lfi-detail-section lfi-detail-contact">
            <h2>Section 8 — Contact</h2>
            <?php if ((int) $r->contact_recontact === 1): ?>
                <ul>
                    <li><strong>Souhaite être recontacté·e :</strong> ✅ Oui</li>
                    <li><strong>Prénom :</strong> <?php echo esc_html($r->contact_prenom); ?></li>
                    <li><strong>Nom :</strong> <?php echo esc_html($r->contact_nom) ?: '<em style="color:#999">—</em>'; ?></li>
                    <li><strong>Téléphone :</strong> <?php echo esc_html($r->contact_tel) ?: '<em style="color:#999">—</em>'; ?></li>
                    <li><strong>Email :</strong> <?php echo esc_html($r->contact_email) ?: '<em style="color:#999">—</em>'; ?></li>
                    <li><strong>N° appartement :</strong> <?php echo esc_html($data['contact_appartement'] ?? '') ?: '<em style="color:#999">—</em>'; ?></li>
                </ul>
            <?php else: ?>
                <p><em>La personne enquêtée ne souhaite pas être recontactée.</em></p>
            <?php endif; ?>
        </div>
    </div>
    <?php lfi_nct_stats_styles(); ?>
    <?php
}

/**
 * Styles partagés (cards + tableau + détail).
 */
function lfi_nct_stats_styles() {
    ?>
    <style>
        .lfi-stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin: 20px 0 30px;
        }
        .lfi-stats-card {
            display: block;
            text-decoration: none;
            color: inherit;
            background: white;
            padding: 20px;
            border-left: 4px solid #c8102e;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border-radius: 4px;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .lfi-stats-card:not(.lfi-card-static):hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(200,16,46,0.2);
            cursor: pointer;
        }
        .lfi-stats-card .nb { font-size: 2.2em; font-weight: bold; color: #c8102e; line-height: 1; }
        .lfi-stats-card .label { color: #555; font-size: 0.95em; margin-top: 6px; }
        .lfi-stats-card .abs { color: #999; font-size: 0.8em; margin-top: 4px; }
        /* Cartes Gravité : fond coloré → texte (nombres + libellés + %) en blanc */
        .lfi-stats-card[style*="color:#fff"] .nb,
        .lfi-stats-card[style*="color:#fff"] .label,
        .lfi-stats-card[style*="color:#fff"] .abs { color: #fff !important; }
        .lfi-stats-charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 24px;
            margin: 20px 0 40px;
        }
        .lfi-chart-box {
            background: white;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border-radius: 4px;
        }
        .lfi-chart-box h3 { margin: 0 0 16px; color: #c8102e; font-size: 1.05em; }
        .lfi-chart-box canvas { max-height: 300px; }
        .lfi-stats-section { margin-top: 30px; }

        .lfi-detail-section {
            background: white;
            padding: 16px 20px;
            margin: 16px 0;
            border-left: 4px solid #c8102e;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border-radius: 4px;
        }
        .lfi-detail-section h2 {
            color: #c8102e;
            margin-top: 0;
            font-size: 1.1em;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        .lfi-detail-section ul { margin: 0; padding-left: 0; list-style: none; }
        .lfi-detail-section li { padding: 6px 0; border-bottom: 1px dashed #eee; }
        .lfi-detail-section li:last-child { border-bottom: none; }
        .lfi-detail-contact {
            border-left-color: #2ecc71;
            background: #f0fdf4;
        }
        .lfi-detail-contact h2 { color: #2ecc71; }

        .lfi-fact-banner {
            background: #fff3f5;
            border-left: 6px solid #c8102e;
            padding: 18px 22px;
            border-radius: 6px;
            margin: 16px 0 28px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .lfi-fact-headline { font-size: 1.15em; margin-bottom: 8px; }
        .lfi-fact-headline strong { color: #c8102e; font-size: 1.4em; }
        .lfi-fact-list { margin: .3em 0 .6em 1.2em; padding: 0; }
        .lfi-fact-list li { padding: 2px 0; }
        .lfi-fact-data { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #c8102e; font-size: .95em; color: #555; }
        .lfi-fact-data > div { margin: 4px 0; }
    </style>
    <?php
}
