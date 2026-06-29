<?php
/**
 * Calculateur d'AIDE JURIDICTIONNELLE.
 *
 * Permet de savoir À L'AVANCE si un·e locataire a droit à l'aide
 * juridictionnelle (totale, partielle 55 % ou 25 %, ou aucune), à partir
 * de son revenu fiscal de référence (RFR), de la composition du foyer et
 * de son patrimoine.
 *
 * ⚠️ Résultat INDICATIF. Le barème évolue chaque année : il est isolé dans
 * lfi_nct_aj_bareme() pour être mis à jour en un seul endroit. Toujours
 * confirmer avec le simulateur officiel (justice.fr).
 */
if (!defined('ABSPATH')) exit;

/**
 * Barème de l'aide juridictionnelle (année de référence ci-dessous).
 * Source : barème issu de la réforme 2021 (RFR), plafonds revalorisés.
 * À ACTUALISER chaque année si besoin.
 */
function lfi_nct_aj_bareme() {
    return [
        'annee'             => 2024,
        'rfr_totale'        => 12271, // RFR annuel : aide TOTALE (100 %) si ≤
        'rfr_partielle55'   => 14510, // aide PARTIELLE 55 % si ≤
        'rfr_partielle25'   => 18404, // aide PARTIELLE 25 % si ≤
        'pct_maj_2premiers' => 0.18,   // majoration des plafonds par pers. à charge (1re et 2e)
        'pct_maj_suivants'  => 0.1137, // majoration à partir de la 3e personne
        'patri_mobilier_max'   => 12271, // épargne / valeurs : refus si >
        'patri_immobilier_max' => 37087, // biens immobiliers hors résidence principale : refus si >
        'simulateur'        => 'https://www.justice.fr/simulateurs/aide-juridictionnelle',
    ];
}

/**
 * Évalue le droit à l'AJ.
 * @return array niveau ('totale'|'partielle55'|'partielle25'|'aucune'),
 *               taux (100/55/25/0), libellé, plafonds majorés, blocage patrimoine.
 */
function lfi_nct_aj_evaluer($rfr, $nb_charge, $patri_mob = 0, $patri_immo = 0) {
    $b = lfi_nct_aj_bareme();
    $rfr = max(0, (float) $rfr);
    $nb_charge = max(0, (int) $nb_charge);

    /* Majoration des plafonds selon le nombre de personnes à charge. */
    $maj_unit_2 = round($b['pct_maj_2premiers'] * $b['rfr_totale']);
    $maj_unit_3 = round($b['pct_maj_suivants']  * $b['rfr_totale']);
    $maj = ($nb_charge <= 2)
        ? $nb_charge * $maj_unit_2
        : (2 * $maj_unit_2) + (($nb_charge - 2) * $maj_unit_3);

    $seuil_totale = $b['rfr_totale']      + $maj;
    $seuil_55     = $b['rfr_partielle55'] + $maj;
    $seuil_25     = $b['rfr_partielle25'] + $maj;
    $seuil_mob    = $b['patri_mobilier_max']   + $maj;
    $seuil_immo   = $b['patri_immobilier_max'] + $maj;

    $blocage_mob  = ((float) $patri_mob)  > $seuil_mob;
    $blocage_immo = ((float) $patri_immo) > $seuil_immo;

    if ($blocage_mob || $blocage_immo) {
        $niveau = 'aucune'; $taux = 0;
    } elseif ($rfr <= $seuil_totale) {
        $niveau = 'totale'; $taux = 100;
    } elseif ($rfr <= $seuil_55) {
        $niveau = 'partielle55'; $taux = 55;
    } elseif ($rfr <= $seuil_25) {
        $niveau = 'partielle25'; $taux = 25;
    } else {
        $niveau = 'aucune'; $taux = 0;
    }

    $libelles = [
        'totale'      => 'Aide juridictionnelle TOTALE (100 % pris en charge)',
        'partielle55' => 'Aide juridictionnelle PARTIELLE — 55 % pris en charge',
        'partielle25' => 'Aide juridictionnelle PARTIELLE — 25 % pris en charge',
        'aucune'      => 'Pas d\'aide juridictionnelle (au-dessus des plafonds)',
    ];

    return [
        'niveau'        => $niveau,
        'taux'          => $taux,
        'libelle'       => $libelles[$niveau],
        'seuil_totale'  => $seuil_totale,
        'seuil_55'      => $seuil_55,
        'seuil_25'      => $seuil_25,
        'seuil_mob'     => $seuil_mob,
        'seuil_immo'    => $seuil_immo,
        'maj'           => $maj,
        'blocage_mob'   => $blocage_mob,
        'blocage_immo'  => $blocage_immo,
        'bareme'        => $b,
    ];
}

function lfi_nct_aj_eur($n) { return number_format((float) $n, 0, ',', ' ') . ' €'; }

/* ============================================================== *
 *  VUE : calculateur d'aide juridictionnelle                      *
 * ============================================================== */
function lfi_nct_app_view_aj_calcul() {
    if (!lfi_nct_app_guard_brigade()) return;

    $rfr = isset($_GET['rfr']) ? (float) $_GET['rfr'] : null;
    $nb  = isset($_GET['nb'])  ? (int) $_GET['nb']  : 0;
    $pm  = isset($_GET['pm'])  ? (float) $_GET['pm'] : 0;
    $pi  = isset($_GET['pi'])  ? (float) $_GET['pi'] : 0;
    $done = isset($_GET['rfr']);

    lfi_nct_app_screen_open('⚖️ Aide juridictionnelle', 'Savoir à l\'avance si un locataire y a droit');
    $b = lfi_nct_aj_bareme();

    echo '<div class="lfi-app-help">Renseigne le <strong>revenu fiscal de référence</strong> (RFR — sur l\'avis d\'imposition), le nombre de <strong>personnes à charge</strong> et le patrimoine. L\'outil estime le droit à l\'aide juridictionnelle. <em>Résultat indicatif (barème ' . (int) $b['annee'] . ') — à confirmer sur le <a href="' . esc_url($b['simulateur']) . '" target="_blank">simulateur officiel</a>.</em></div>';

    echo '<form method="get" class="lfi-app-form">';
    echo '<input type="hidden" name="vue" value="aj-calcul">';
    echo '<label>Revenu fiscal de référence (RFR annuel, €)<input type="number" name="rfr" step="1" min="0" value="' . esc_attr($rfr ?? '') . '" placeholder="ex : 11000" required></label>';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">';
    echo '<label>Personnes à charge<input type="number" name="nb" step="1" min="0" value="' . esc_attr($nb) . '" placeholder="0"></label>';
    echo '<label>Épargne / mobilier (€)<input type="number" name="pm" step="1" min="0" value="' . esc_attr($pm ?: '') . '" placeholder="0"></label>';
    echo '<label>Immobilier hors résidence (€)<input type="number" name="pi" step="1" min="0" value="' . esc_attr($pi ?: '') . '" placeholder="0"></label>';
    echo '</div>';
    echo '<button type="submit" class="btn-primary big">Calculer le droit à l\'AJ</button>';
    echo '</form>';

    if ($done && $rfr !== null) {
        $r = lfi_nct_aj_evaluer($rfr, $nb, $pm, $pi);
        $couleur = $r['taux'] >= 100 ? '#186a3b' : ($r['taux'] > 0 ? '#bd8600' : '#a30b25');
        echo '<div style="background:#fff;border:2px solid ' . $couleur . ';border-radius:12px;padding:16px;margin:14px 0">';
        echo '<div style="font-size:1.15em;font-weight:800;color:' . $couleur . '">' . esc_html($r['libelle']) . '</div>';
        if ($r['blocage_mob'])  echo '<div style="color:#a30b25;margin-top:6px">⚠ Patrimoine mobilier supérieur au plafond (' . lfi_nct_aj_eur($r['seuil_mob']) . ') → AJ refusée même si les revenus sont éligibles.</div>';
        if ($r['blocage_immo']) echo '<div style="color:#a30b25;margin-top:6px">⚠ Patrimoine immobilier supérieur au plafond (' . lfi_nct_aj_eur($r['seuil_immo']) . ') → AJ refusée même si les revenus sont éligibles.</div>';
        echo '<div style="margin-top:10px;font-size:.9em;color:#444">Pour ce foyer (' . (int) $nb . ' pers. à charge), les plafonds de RFR sont :</div>';
        echo '<ul style="margin:6px 0 0;font-size:.9em;color:#444">';
        echo '<li>Aide totale (100 %) : RFR ≤ <strong>' . lfi_nct_aj_eur($r['seuil_totale']) . '</strong></li>';
        echo '<li>Aide partielle 55 % : ≤ <strong>' . lfi_nct_aj_eur($r['seuil_55']) . '</strong></li>';
        echo '<li>Aide partielle 25 % : ≤ <strong>' . lfi_nct_aj_eur($r['seuil_25']) . '</strong></li>';
        echo '</ul>';
        echo '<div style="margin-top:10px"><a class="btn-ghost" href="' . esc_url($b['simulateur']) . '" target="_blank">🔗 Vérifier sur le simulateur officiel</a></div>';
        echo '</div>';
    }

    echo '<div class="lfi-app-help" style="background:#e8f0ff;border-left:4px solid #0066a3"><small>💡 Le RFR est indiqué sur l\'avis d\'imposition. « Personnes à charge » = personnes du foyer fiscal autres que le demandeur (conjoint, enfants…). Le patrimoine immobilier exclut la résidence principale.</small></div>';

    lfi_nct_app_screen_close();
}
