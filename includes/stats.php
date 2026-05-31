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

    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");

    $stats = [
        'total' => $total,
        'insectes_oui' => 0, 'humidite_oui' => 0, 'thermique_insuffisant' => 0,
        'appoint_utilise' => 0, 'signale_nmh' => 0, 'interet_collectif' => 0, 'recontact' => 0,
        'insectes_repartition' => ['oui' => 0, 'non' => 0],
        'humidite_repartition' => ['oui_visible' => 0, 'oui_ressentie' => 0, 'oui_suspicion' => 0, 'non' => 0],
        'thermique_type' => [], 'thermique_adequation' => [], 'thermique_appoint' => [],
        'demarches_signale' => [], 'demarches_collectif' => [],
        'top_immeubles' => [],
        'gravity' => ['leger' => 0, 'preoccupant' => 0, 'grave' => 0, 'critique' => 0],
    ];

    if ($total === 0) return $stats;

    $responses = $wpdb->get_results("SELECT data, contact_recontact FROM $table");
    foreach ($responses as $r) {
        $data = json_decode($r->data, true);
        if (!is_array($data)) continue;

        // Gravité automatique
        list($glvl) = lfi_nct_gravity_level(lfi_nct_gravity_score($data));
        if (isset($stats['gravity'][$glvl])) $stats['gravity'][$glvl]++;

        $ins = $data['insectes_presence'] ?? '';
        if ($ins === 'oui') { $stats['insectes_oui']++; $stats['insectes_repartition']['oui']++; }
        elseif ($ins === 'non') { $stats['insectes_repartition']['non']++; }

        $hum = $data['humidite_presence'] ?? '';
        if (isset($stats['humidite_repartition'][$hum])) $stats['humidite_repartition'][$hum]++;
        if (in_array($hum, ['oui_visible', 'oui_ressentie', 'oui_suspicion'], true)) $stats['humidite_oui']++;

        $type = $data['thermique_type'] ?? '';
        if ($type) $stats['thermique_type'][$type] = ($stats['thermique_type'][$type] ?? 0) + 1;

        $adeq = $data['thermique_adequation'] ?? '';
        if ($adeq) $stats['thermique_adequation'][$adeq] = ($stats['thermique_adequation'][$adeq] ?? 0) + 1;
        if (in_array($adeq, ['partiel', 'non_18', 'non_16', 'non_panne'], true)) $stats['thermique_insuffisant']++;

        $appoint = $data['thermique_appoint'] ?? '';
        if ($appoint) $stats['thermique_appoint'][$appoint] = ($stats['thermique_appoint'][$appoint] ?? 0) + 1;
        if (in_array($appoint, ['oui_permanent', 'oui_quotidien', 'oui_ponctuel', 'oui_passe'], true)) $stats['appoint_utilise']++;

        $signale = $data['demarches_signale'] ?? '';
        if ($signale) $stats['demarches_signale'][$signale] = ($stats['demarches_signale'][$signale] ?? 0) + 1;
        if (in_array($signale, ['oui', 'partiel'], true)) $stats['signale_nmh']++;

        $collectif = $data['demarches_collectif'] ?? '';
        if ($collectif) $stats['demarches_collectif'][$collectif] = ($stats['demarches_collectif'][$collectif] ?? 0) + 1;
        if (in_array($collectif, ['oui', 'a_voir'], true)) $stats['interet_collectif']++;

        if ((int) $r->contact_recontact === 1) $stats['recontact']++;
    }

    $top = $wpdb->get_results("SELECT adresse, COUNT(*) as nb FROM $table GROUP BY adresse ORDER BY nb DESC LIMIT 10");
    $stats['top_immeubles'] = $top ?: [];

    return $stats;
}

/**
 * Récupère les réponses filtrées selon un critère.
 */
function lfi_nct_get_filtered_responses($filter) {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';

    // Filtres SQL purs (colonnes structurées)
    if ($filter === 'recontact') {
        return $wpdb->get_results("SELECT * FROM $table WHERE contact_recontact = 1 ORDER BY submitted_at DESC");
    }

    // Filtres sur le champ JSON 'data' : on récupère tout puis on filtre en PHP
    $all = $wpdb->get_results("SELECT * FROM $table ORDER BY submitted_at DESC");
    $filtered = [];

    foreach ($all as $r) {
        $data = json_decode($r->data, true);
        if (!is_array($data)) continue;

        $match = false;
        switch ($filter) {
            case 'humidite_oui':
                $match = in_array($data['humidite_presence'] ?? '', ['oui_visible', 'oui_ressentie', 'oui_suspicion'], true);
                break;
            case 'insectes_oui':
                $match = ($data['insectes_presence'] ?? '') === 'oui';
                break;
            case 'thermique_insuffisant':
                $match = in_array($data['thermique_adequation'] ?? '', ['partiel', 'non_18', 'non_16', 'non_panne'], true);
                break;
            case 'appoint_utilise':
                $match = in_array($data['thermique_appoint'] ?? '', ['oui_permanent', 'oui_quotidien', 'oui_ponctuel', 'oui_passe'], true);
                break;
            case 'signale_nmh':
                $match = in_array($data['demarches_signale'] ?? '', ['oui', 'partiel'], true);
                break;
            case 'interet_collectif':
                $match = in_array($data['demarches_collectif'] ?? '', ['oui', 'a_voir'], true);
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
        'humidite_oui' => 'Humidité présente',
        'insectes_oui' => 'Insectes / nuisibles présents',
        'thermique_insuffisant' => 'Chauffage NMH insuffisant',
        'appoint_utilise' => 'Utilisent un appoint personnel',
        'signale_nmh' => 'Ont signalé à NMH',
        'interet_collectif' => 'Intéressés par dossier collectif',
        'recontact' => 'Souhaitent être recontactés',
        'gravite_preoccupant' => 'Cas au moins préoccupants',
        'gravite_grave' => 'Cas graves ou critiques',
        'gravite_critique' => 'Cas critiques',
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

    $chart_insectes = lfi_nct_chart_data($stats['insectes_repartition'], ['oui' => 'Présence', 'non' => 'Aucun']);
    $chart_humidite = lfi_nct_chart_data($stats['humidite_repartition'], lfi_nct_value_labels()['humidite_presence']);
    $chart_thermique_type = lfi_nct_chart_data($stats['thermique_type'], lfi_nct_value_labels()['thermique_type']);
    $chart_thermique_adeq = lfi_nct_chart_data($stats['thermique_adequation'], lfi_nct_value_labels()['thermique_adequation']);
    $chart_appoint = lfi_nct_chart_data($stats['thermique_appoint'], lfi_nct_value_labels()['thermique_appoint']);
    $chart_signale = lfi_nct_chart_data($stats['demarches_signale'], lfi_nct_value_labels()['demarches_signale']);
    $chart_collectif = lfi_nct_chart_data($stats['demarches_collectif'], lfi_nct_value_labels()['demarches_collectif']);
    ?>
    <div class="wrap lfi-stats">
        <h1>📊 LFI Clos Toreau — Statistiques de l'enquête <?php echo lfi_nct_print_button('Imprimer les statistiques'); ?></h1>

        <?php if ($total === 0): ?>
            <div class="notice notice-info"><p>Aucune réponse enregistrée pour l'instant.</p></div>
        <?php else: ?>

        <p style="font-size:1.1em;margin-bottom:1.5em;">Basé sur <strong><?php echo $total; ?></strong> réponse<?php echo $total > 1 ? 's' : ''; ?>. <em>Cliquez sur une card pour voir le détail.</em></p>

        <h2 style="margin-top:0">Gravité automatique</h2>
        <p class="description" style="margin-top:-.5em;margin-bottom:1em">Score calculé à partir des indicateurs : insectes, humidité, chauffage NMH, appoint perso, infiltrations.</p>
        <div class="lfi-stats-cards">
            <a class="lfi-stats-card" style="background:#1a7f37;color:#fff" href="<?php echo esc_url($url('gravite_preoccupant')); ?>" title="Niveau léger (score 0-2)">
                <div class="nb"><?php echo $stats['gravity']['leger']; ?></div>
                <div class="label">🟢 Léger</div>
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

        <h2 style="margin-top:2em">Indicateurs détaillés</h2>
        <div class="lfi-stats-cards">
            <div class="lfi-stats-card lfi-card-static">
                <div class="nb"><?php echo $total; ?></div>
                <div class="label">Réponses totales</div>
            </div>
            <a class="lfi-stats-card" href="<?php echo esc_url($url('humidite_oui')); ?>">
                <div class="nb"><?php echo $pct($stats['humidite_oui'], $total); ?>%</div>
                <div class="label">Logements avec humidité</div>
                <div class="abs"><?php echo $stats['humidite_oui']; ?> / <?php echo $total; ?></div>
            </a>
            <a class="lfi-stats-card" href="<?php echo esc_url($url('insectes_oui')); ?>">
                <div class="nb"><?php echo $pct($stats['insectes_oui'], $total); ?>%</div>
                <div class="label">Logements avec nuisibles</div>
                <div class="abs"><?php echo $stats['insectes_oui']; ?> / <?php echo $total; ?></div>
            </a>
            <a class="lfi-stats-card" href="<?php echo esc_url($url('thermique_insuffisant')); ?>">
                <div class="nb"><?php echo $pct($stats['thermique_insuffisant'], $total); ?>%</div>
                <div class="label">Chauffage NMH insuffisant</div>
                <div class="abs"><?php echo $stats['thermique_insuffisant']; ?> / <?php echo $total; ?></div>
            </a>
            <a class="lfi-stats-card" href="<?php echo esc_url($url('appoint_utilise')); ?>">
                <div class="nb"><?php echo $pct($stats['appoint_utilise'], $total); ?>%</div>
                <div class="label">Utilisent un appoint perso</div>
                <div class="abs"><?php echo $stats['appoint_utilise']; ?> / <?php echo $total; ?></div>
            </a>
            <a class="lfi-stats-card" href="<?php echo esc_url($url('signale_nmh')); ?>">
                <div class="nb"><?php echo $pct($stats['signale_nmh'], $total); ?>%</div>
                <div class="label">Ont signalé à NMH</div>
                <div class="abs"><?php echo $stats['signale_nmh']; ?> / <?php echo $total; ?></div>
            </a>
            <a class="lfi-stats-card" href="<?php echo esc_url($url('interet_collectif')); ?>">
                <div class="nb"><?php echo $pct($stats['interet_collectif'], $total); ?>%</div>
                <div class="label">Intéressés par dossier collectif</div>
                <div class="abs"><?php echo $stats['interet_collectif']; ?> / <?php echo $total; ?></div>
            </a>
            <a class="lfi-stats-card" href="<?php echo esc_url($url('recontact')); ?>">
                <div class="nb"><?php echo $pct($stats['recontact'], $total); ?>%</div>
                <div class="label">Souhaitent être recontactés</div>
                <div class="abs"><?php echo $stats['recontact']; ?> / <?php echo $total; ?></div>
            </a>
        </div>

        <div class="lfi-stats-charts">
            <div class="lfi-chart-box"><h3>Présence d'humidité</h3><canvas id="chart-humidite"></canvas></div>
            <div class="lfi-chart-box"><h3>Présence d'insectes</h3><canvas id="chart-insectes"></canvas></div>
            <div class="lfi-chart-box"><h3>Adéquation chauffage NMH</h3><canvas id="chart-thermique-adeq"></canvas></div>
            <div class="lfi-chart-box"><h3>Utilisation appoint perso</h3><canvas id="chart-appoint"></canvas></div>
            <div class="lfi-chart-box"><h3>Type de chauffage</h3><canvas id="chart-thermique-type"></canvas></div>
            <div class="lfi-chart-box"><h3>Signalement à NMH</h3><canvas id="chart-signale"></canvas></div>
            <div class="lfi-chart-box"><h3>Intérêt dossier collectif</h3><canvas id="chart-collectif"></canvas></div>
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
        makePie('chart-humidite', <?php echo wp_json_encode($chart_humidite['labels']); ?>, <?php echo wp_json_encode($chart_humidite['data']); ?>);
        makePie('chart-insectes', <?php echo wp_json_encode($chart_insectes['labels']); ?>, <?php echo wp_json_encode($chart_insectes['data']); ?>);
        makePie('chart-thermique-adeq', <?php echo wp_json_encode($chart_thermique_adeq['labels']); ?>, <?php echo wp_json_encode($chart_thermique_adeq['data']); ?>);
        makePie('chart-appoint', <?php echo wp_json_encode($chart_appoint['labels']); ?>, <?php echo wp_json_encode($chart_appoint['data']); ?>);
        makePie('chart-thermique-type', <?php echo wp_json_encode($chart_thermique_type['labels']); ?>, <?php echo wp_json_encode($chart_thermique_type['data']); ?>);
        makePie('chart-signale', <?php echo wp_json_encode($chart_signale['labels']); ?>, <?php echo wp_json_encode($chart_signale['data']); ?>);
        makePie('chart-collectif', <?php echo wp_json_encode($chart_collectif['labels']); ?>, <?php echo wp_json_encode($chart_collectif['data']); ?>);
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
    </style>
    <?php
}
