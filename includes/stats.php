<?php
if (!defined('ABSPATH')) exit;

/**
 * Calcule toutes les statistiques agrégées à partir des réponses.
 */
function lfi_nct_compute_stats() {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';

    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");

    $stats = [
        'total' => $total,
        'insectes_oui' => 0,
        'humidite_oui' => 0,
        'thermique_insuffisant' => 0,
        'appoint_utilise' => 0,
        'signale_nmh' => 0,
        'interet_collectif' => 0,
        'recontact' => 0,
        // Répartitions pour camemberts
        'insectes_repartition' => ['oui' => 0, 'non' => 0],
        'humidite_repartition' => ['oui_visible' => 0, 'oui_ressentie' => 0, 'oui_suspicion' => 0, 'non' => 0],
        'thermique_type' => [],
        'thermique_adequation' => [],
        'thermique_appoint' => [],
        'demarches_signale' => [],
        'demarches_collectif' => [],
        'top_immeubles' => [],
    ];

    if ($total === 0) {
        return $stats;
    }

    $responses = $wpdb->get_results("SELECT data, contact_recontact FROM $table");

    foreach ($responses as $r) {
        $data = json_decode($r->data, true);
        if (!is_array($data)) continue;

        // Insectes
        $ins = $data['insectes_presence'] ?? '';
        if ($ins === 'oui') {
            $stats['insectes_oui']++;
            $stats['insectes_repartition']['oui']++;
        } elseif ($ins === 'non') {
            $stats['insectes_repartition']['non']++;
        }

        // Humidité
        $hum = $data['humidite_presence'] ?? '';
        if (isset($stats['humidite_repartition'][$hum])) {
            $stats['humidite_repartition'][$hum]++;
        }
        if (in_array($hum, ['oui_visible', 'oui_ressentie', 'oui_suspicion'], true)) {
            $stats['humidite_oui']++;
        }

        // Thermique
        $type = $data['thermique_type'] ?? '';
        if ($type) $stats['thermique_type'][$type] = ($stats['thermique_type'][$type] ?? 0) + 1;

        $adeq = $data['thermique_adequation'] ?? '';
        if ($adeq) $stats['thermique_adequation'][$adeq] = ($stats['thermique_adequation'][$adeq] ?? 0) + 1;
        if (in_array($adeq, ['partiel', 'non_18', 'non_16', 'non_panne'], true)) {
            $stats['thermique_insuffisant']++;
        }

        $appoint = $data['thermique_appoint'] ?? '';
        if ($appoint) $stats['thermique_appoint'][$appoint] = ($stats['thermique_appoint'][$appoint] ?? 0) + 1;
        if (in_array($appoint, ['oui_permanent', 'oui_quotidien', 'oui_ponctuel', 'oui_passe'], true)) {
            $stats['appoint_utilise']++;
        }

        // Démarches
        $signale = $data['demarches_signale'] ?? '';
        if ($signale) $stats['demarches_signale'][$signale] = ($stats['demarches_signale'][$signale] ?? 0) + 1;
        if (in_array($signale, ['oui', 'partiel'], true)) {
            $stats['signale_nmh']++;
        }

        $collectif = $data['demarches_collectif'] ?? '';
        if ($collectif) $stats['demarches_collectif'][$collectif] = ($stats['demarches_collectif'][$collectif] ?? 0) + 1;
        if (in_array($collectif, ['oui', 'a_voir'], true)) {
            $stats['interet_collectif']++;
        }

        // Recontact (depuis colonne SQL dédiée)
        if ((int) $r->contact_recontact === 1) {
            $stats['recontact']++;
        }
    }

    // Top immeubles
    $top = $wpdb->get_results("SELECT adresse, COUNT(*) as nb FROM $table GROUP BY adresse ORDER BY nb DESC LIMIT 10");
    $stats['top_immeubles'] = $top ?: [];

    return $stats;
}

/**
 * Helper : transforme un tableau associatif en objet JSON-friendly pour Chart.js
 */
function lfi_nct_chart_data($repartition, $labels_map = []) {
    $labels = [];
    $data = [];
    foreach ($repartition as $key => $val) {
        $labels[] = $labels_map[$key] ?? $key;
        $data[] = $val;
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Page admin de statistiques.
 */
function lfi_nct_stats_page() {
    $stats = lfi_nct_compute_stats();
    $total = $stats['total'];

    // Calculs de pourcentages (évite division par 0)
    $pct = function($n, $tot) { return $tot > 0 ? round($n / $tot * 100, 1) : 0; };

    // Préparation données chart
    $chart_insectes = lfi_nct_chart_data($stats['insectes_repartition'], [
        'oui' => 'Présence', 'non' => 'Aucun',
    ]);
    $chart_humidite = lfi_nct_chart_data($stats['humidite_repartition'], [
        'oui_visible' => 'Visible', 'oui_ressentie' => 'Ressentie', 'oui_suspicion' => 'Suspicion', 'non' => 'Aucune',
    ]);
    $chart_thermique_type = lfi_nct_chart_data($stats['thermique_type'], [
        'sol_collectif_nmh' => 'Sol collectif NMH',
        'sol_avec_appoint' => 'Sol + appoint perso',
        'individuel_gaz' => 'Individuel gaz',
        'individuel_elec' => 'Individuel élec',
        'aucun' => 'Aucun chauffage',
    ]);
    $chart_thermique_adeq = lfi_nct_chart_data($stats['thermique_adequation'], [
        'oui_toujours' => 'Toujours OK',
        'oui_limite' => 'Limite (19°C max)',
        'partiel' => 'Partiellement',
        'non_18' => '17-18°C max',
        'non_16' => '< 16°C',
        'non_panne' => 'En panne',
    ]);
    $chart_appoint = lfi_nct_chart_data($stats['thermique_appoint'], [
        'oui_permanent' => 'Permanent',
        'oui_quotidien' => 'Quotidien',
        'oui_ponctuel' => 'Ponctuel',
        'oui_passe' => 'Passé',
        'non' => 'Jamais',
    ]);
    $chart_signale = lfi_nct_chart_data($stats['demarches_signale'], [
        'oui' => 'Oui', 'partiel' => 'Partiel', 'non' => 'Non',
    ]);
    $chart_collectif = lfi_nct_chart_data($stats['demarches_collectif'], [
        'oui' => 'Oui', 'a_voir' => 'À voir', 'non' => 'Non',
    ]);
    ?>
    <div class="wrap lfi-stats">
        <h1>📊 LFI Clos Toreau — Statistiques de l'enquête</h1>

        <?php if ($total === 0): ?>
            <div class="notice notice-info"><p>Aucune réponse enregistrée pour l'instant. Les statistiques apparaîtront dès la première soumission.</p></div>
        <?php else: ?>

        <p style="font-size:1.1em;margin-bottom:1.5em;">Basé sur <strong><?php echo $total; ?></strong> réponse<?php echo $total > 1 ? 's' : ''; ?> collectée<?php echo $total > 1 ? 's' : ''; ?>.</p>

        <!-- Cards chiffres clés -->
        <div class="lfi-stats-cards">
            <div class="lfi-stats-card">
                <div class="nb"><?php echo $total; ?></div>
                <div class="label">Réponses totales</div>
            </div>
            <div class="lfi-stats-card">
                <div class="nb"><?php echo $pct($stats['humidite_oui'], $total); ?>%</div>
                <div class="label">Logements avec humidité</div>
                <div class="abs"><?php echo $stats['humidite_oui']; ?> / <?php echo $total; ?></div>
            </div>
            <div class="lfi-stats-card">
                <div class="nb"><?php echo $pct($stats['insectes_oui'], $total); ?>%</div>
                <div class="label">Logements avec nuisibles</div>
                <div class="abs"><?php echo $stats['insectes_oui']; ?> / <?php echo $total; ?></div>
            </div>
            <div class="lfi-stats-card">
                <div class="nb"><?php echo $pct($stats['thermique_insuffisant'], $total); ?>%</div>
                <div class="label">Chauffage NMH insuffisant</div>
                <div class="abs"><?php echo $stats['thermique_insuffisant']; ?> / <?php echo $total; ?></div>
            </div>
            <div class="lfi-stats-card">
                <div class="nb"><?php echo $pct($stats['appoint_utilise'], $total); ?>%</div>
                <div class="label">Utilisent un appoint perso</div>
                <div class="abs"><?php echo $stats['appoint_utilise']; ?> / <?php echo $total; ?></div>
            </div>
            <div class="lfi-stats-card">
                <div class="nb"><?php echo $pct($stats['signale_nmh'], $total); ?>%</div>
                <div class="label">Ont signalé à NMH</div>
                <div class="abs"><?php echo $stats['signale_nmh']; ?> / <?php echo $total; ?></div>
            </div>
            <div class="lfi-stats-card">
                <div class="nb"><?php echo $pct($stats['interet_collectif'], $total); ?>%</div>
                <div class="label">Intéressés par dossier collectif</div>
                <div class="abs"><?php echo $stats['interet_collectif']; ?> / <?php echo $total; ?></div>
            </div>
            <div class="lfi-stats-card">
                <div class="nb"><?php echo $pct($stats['recontact'], $total); ?>%</div>
                <div class="label">Souhaitent être recontactés</div>
                <div class="abs"><?php echo $stats['recontact']; ?> / <?php echo $total; ?></div>
            </div>
        </div>

        <!-- Camemberts -->
        <div class="lfi-stats-charts">
            <div class="lfi-chart-box">
                <h3>Présence d'humidité</h3>
                <canvas id="chart-humidite"></canvas>
            </div>
            <div class="lfi-chart-box">
                <h3>Présence d'insectes</h3>
                <canvas id="chart-insectes"></canvas>
            </div>
            <div class="lfi-chart-box">
                <h3>Adéquation chauffage NMH (sans appoint)</h3>
                <canvas id="chart-thermique-adeq"></canvas>
            </div>
            <div class="lfi-chart-box">
                <h3>Utilisation d'un appoint personnel</h3>
                <canvas id="chart-appoint"></canvas>
            </div>
            <div class="lfi-chart-box">
                <h3>Type de chauffage</h3>
                <canvas id="chart-thermique-type"></canvas>
            </div>
            <div class="lfi-chart-box">
                <h3>Signalement à NMH</h3>
                <canvas id="chart-signale"></canvas>
            </div>
            <div class="lfi-chart-box">
                <h3>Intérêt pour dossier collectif</h3>
                <canvas id="chart-collectif"></canvas>
            </div>
        </div>

        <!-- Top immeubles -->
        <div class="lfi-stats-section">
            <h2>🏢 Top 10 immeubles les plus enquêtés</h2>
            <table class="wp-list-table widefat striped">
                <thead><tr><th>Immeuble (adresse)</th><th>Nombre d'enquêtes</th></tr></thead>
                <tbody>
                <?php if (empty($stats['top_immeubles'])): ?>
                    <tr><td colspan="2"><em>Aucune donnée</em></td></tr>
                <?php else: ?>
                    <?php foreach ($stats['top_immeubles'] as $im): ?>
                    <tr>
                        <td><?php echo esc_html($im->adresse); ?></td>
                        <td><strong><?php echo $im->nb; ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </div>

    <style>
        .lfi-stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin: 20px 0 30px;
        }
        .lfi-stats-card {
            background: white;
            padding: 20px;
            border-left: 4px solid #c8102e;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border-radius: 4px;
        }
        .lfi-stats-card .nb {
            font-size: 2.2em;
            font-weight: bold;
            color: #c8102e;
            line-height: 1;
        }
        .lfi-stats-card .label {
            color: #555;
            font-size: 0.95em;
            margin-top: 6px;
        }
        .lfi-stats-card .abs {
            color: #999;
            font-size: 0.8em;
            margin-top: 4px;
        }
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
        .lfi-chart-box h3 {
            margin: 0 0 16px;
            color: #c8102e;
            font-size: 1.05em;
        }
        .lfi-chart-box canvas {
            max-height: 300px;
        }
        .lfi-stats-section { margin-top: 30px; }
    </style>

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
                            const val = ctx.parsed;
                            const pct = total > 0 ? Math.round(val / total * 100) : 0;
                            return ctx.label + ' : ' + val + ' (' + pct + '%)';
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
