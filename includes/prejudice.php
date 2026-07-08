<?php
/**
 * CALCULATEUR DE PRÉJUDICE — punaises de lit (bailleur social).
 *
 * Reprend la méthode du dossier pilote DOUCET c/ NMH (voir
 * docs/METHODE-PREJUDICE-PUNAISES.md) : 10 postes, sortie DOUBLE
 * (amiable = postes 1–5 ; fond = les 10 postes en fourchette min–max),
 * sources citées, pièces manquantes signalées.
 *
 * ⚠️ Aide au chiffrage : tous les montants sont des ORDRES DE GRANDEUR
 * ajustables, à valider avec l'avocat. Ce n'est pas un avis juridique.
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_prej_can() {
    return current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
}

/** Sources documentaires à citer dans le rapport. */
function lfi_nct_prej_sources() {
    return [
        'scientifiques' => [
            'ANSES, avis/rapport sur les punaises de lit (2019–2020) : 890 €/traitement, 11 % foyers/5 ans, 83 M€/an.',
            'INSERM — impact psychologique des infestations (anxiété, insomnie, ESPT).',
            'Rapport parlementaire, mission d\'information Assemblée nationale, novembre 2023.',
            'Muséum d\'Histoire Naturelle de Nantes (expertise entomologique locale si disponible).',
        ],
        'juridiques' => [
            'Loi n° 89-462 du 6 juillet 1989, art. 6 (logement décent).',
            'Code civil, art. 1719, 1720, 1721, 1143 (violence économique), 1231-1, 1240.',
            'Décret n° 2002-120 du 30 janvier 2002 (décence).',
            'Code de la santé publique, R.1331-14 et s. ; Règlement Sanitaire Départemental.',
        ],
        'jurisprudence' => [
            'Cass. 3e civ., 11 mai 2011, n° 10-30.328 (obligation de délivrance).',
            'Cass. 3e civ., 8 juin 2017, n° 16-16.958 (entretien).',
            'TJ Bobigny, 15 mars 2022 — 45 000 € (famille de 4, 2 ans).',
            'CA Paris, 12 janvier 2023 — 120 000 € (enfant handicapé).',
            'TJ Marseille, 2024 — relogement + 80 000 € (5 ans).',
        ],
        'referentiels' => [
            'Référentiel Mornet (édition récente) ; nomenclature Dintilhac ; tables CA compétente ; ONIAM.',
        ],
    ];
}

/**
 * Calcule les 10 postes. Renvoie ['postes'=>[...], 'amiable'=>, 'fond_min'=>, 'fond_max'=>].
 * Chaque poste : ['num','titre','amiable','fond_min','fond_max','formule','note'].
 */
function lfi_nct_prej_compute($p) {
    $n = function ($k, $d = 0) use ($p) { return isset($p[$k]) ? (float) $p[$k] : $d; };
    $b = function ($k) use ($p) { return !empty($p[$k]); };

    $annees   = max(0, (int) $n('annees'));
    $membres  = max(1, (int) $n('membres'));
    $enf_min  = (int) $n('enfants_mineurs');
    $enf_mdph = (int) $n('enfants_mdph');
    $ald      = (int) $n('personnes_ald');
    $loyer    = $n('loyer');
    $coef     = $n('coef', 0.40);
    $scol     = (int) $n('enfants_scolarises');
    $courriels= (int) $n('courriels_diffamatoires');
    $engmnts  = (int) $n('engagements_contrainte');

    $postes = [];

    /* ---- Poste 1 : corporel (Mornet + Dintilhac). Sous-valeurs saisies, modulées. ---- */
    $souffrances = $n('p1_souffrances_cotation') * 5000;               /* /7 × ordre de grandeur */
    $dft_jours   = $n('p1_dft_jours', $annees * 365);
    $dft         = ($n('p1_dft_taux', 25) / 100) * $dft_jours * 27;     /* valeur/jour indicative */
    $agrement    = $n('p1_agrement');
    $esthetique  = $n('p1_esthetique_cotation') * 3000;
    $base1 = $souffrances + $dft + $agrement + $esthetique;
    $mod1 = 1.0;
    if ($b('photos')) $mod1 *= 1.2;
    if ($b('arrets_5j')) $mod1 *= 1.3;
    if ($ald > 0 || $enf_mdph > 0) $mod1 *= 1.5;
    if ($b('dermatose')) $mod1 *= 1.4;
    $postes[] = ['num' => 1, 'titre' => 'Préjudice corporel', 'amiable' => $base1,
        'fond_min' => $base1, 'fond_max' => $base1 * $mod1,
        'formule' => 'souffrances + DFT + agrément + esthétique, ×modulateurs (photos/arrêts/ALD-MDPH/dermatose)',
        'note' => 'Mornet + Dintilhac ; base non modulée en amiable.'];

    /* ---- Poste 2 : temps perdu ---- */
    $intensite = $n('p2_intensite', 1.0);
    $p2 = $annees * 833 * $intensite;
    $postes[] = ['num' => 2, 'titre' => 'Temps perdu & démarches', 'amiable' => $p2,
        'fond_min' => $p2, 'fond_max' => $p2, 'formule' => $annees . ' an(s) × 833 € × ' . $intensite, 'note' => ''];

    /* ---- Poste 3 : jouissance rétroactif ---- */
    $mois = $annees * 12;
    $p3 = $loyer * $mois * $coef;
    $p3_amiable = min($p3, 5000);
    $p3_fond    = min($p3, 50000);
    $postes[] = ['num' => 3, 'titre' => 'Jouissance rétroactif', 'amiable' => $p3_amiable,
        'fond_min' => $p3_amiable, 'fond_max' => $p3_fond,
        'formule' => number_format($loyer, 0, ',', ' ') . ' € × ' . $mois . ' mois × coef ' . $coef, 'note' => 'Plafond amiable 5 000 € ; fond ≤ 50 000 €.'];

    /* ---- Poste 4 : biens détruits ---- */
    $fact4 = $n('p4_factures');
    $bareme4 = 0;
    if ($fact4 <= 0) {
        $bareme4 = ($n('lits_adulte') * 450) + ($n('lits_enfant') * 250) + ($n('sommiers') * 200)
                 + ($n('matelas_simple') * 300) + ($n('matelas_double') * 500)
                 + ($membres * 100) + ($membres * 200);
        $dep = max(0.30, 1 - 0.10 * $annees);
        $bareme4 *= $dep;
    }
    $p4_amiable = $fact4;                        /* amiable = factures uniquement */
    $p4_fond    = $fact4 > 0 ? $fact4 : $bareme4;
    $postes[] = ['num' => 4, 'titre' => 'Literie & textiles détruits', 'amiable' => $p4_amiable,
        'fond_min' => $p4_amiable, 'fond_max' => $p4_fond,
        'formule' => $fact4 > 0 ? 'sur factures' : 'barème mobilier + textiles, dépréciation 10 %/an (plancher 30 %)', 'note' => ''];

    /* ---- Poste 5 : produits / frais annexes ---- */
    $fact5 = $n('p5_factures');
    $p5_amiable = $fact5;
    $p5_fond    = $fact5 + ($annees * 100);
    $postes[] = ['num' => 5, 'titre' => 'Produits & frais annexes', 'amiable' => $p5_amiable,
        'fond_min' => $p5_amiable, 'fond_max' => $p5_fond,
        'formule' => 'factures' . ($annees ? ' + 100 €/an (fond)' : ''), 'note' => ''];

    /* ---- Poste 6 : moral familial aggravé (fond) ---- */
    $m6 = 1.0;
    if ($enf_min > 0) $m6 *= 1.5;
    if ($enf_mdph > 0 || $ald > 0) $m6 *= 2.5;
    if ($coef >= 1.0) $m6 *= 1.8;
    $p6_min = $membres * 5000;
    $p6_max = $membres * 5000 * $m6;
    $postes[] = ['num' => 6, 'titre' => 'Moral familial aggravé', 'amiable' => 0,
        'fond_min' => $p6_min, 'fond_max' => $p6_max,
        'formule' => $membres . ' membres × 5 000 € × modulateurs (mineur/MDPH-ALD/départ)', 'note' => 'Réservé au fond.'];

    /* ---- Poste 7 : scolaire (fond) ---- */
    $p7_min = 0; $p7_max = 0;
    if ($scol > 0) {
        $m7min = 1.0; $m7max = 1.0;
        if ($b('arret_scolaire')) { $m7min *= 2; $m7max *= 2; }
        if ($b('decrochage')) { $m7min *= 3; $m7max *= 5; }
        if ($b('redoublement')) { $m7min *= 2; $m7max *= 2; }
        $p7_min = $scol * 5000 * $m7min;
        $p7_max = $scol * 5000 * $m7max;
    }
    $postes[] = ['num' => 7, 'titre' => 'Scolaire des enfants', 'amiable' => 0,
        'fond_min' => $p7_min, 'fond_max' => $p7_max,
        'formule' => $scol . ' enfant(s) × 5 000 € × arrêt/décrochage/redoublement', 'note' => 'Réservé au fond.'];

    /* ---- Poste 8 : médical spécifique (fond) ---- */
    $p8_min = 0; $p8_max = 0;
    if ($b('anxiete')) { $p8_min += 3000; $p8_max += 8000; }
    if ($b('dermatose')) { $p8_min += 5000; $p8_max += 15000; }
    if ($ald > 0 || $enf_mdph > 0) { $p8_min += 20000; $p8_max += 100000; }
    if ($b('precancereux')) { $p8_min += 50000; $p8_max += 100000; }
    $postes[] = ['num' => 8, 'titre' => 'Médical spécifique', 'amiable' => 0,
        'fond_min' => $p8_min, 'fond_max' => $p8_max,
        'formule' => 'anxiété/insomnie + dermatose + aggravation ALD-MDPH + précancéreux', 'note' => 'Réservé au fond, sur pièces médicales.'];

    /* ---- Poste 9 : diffamation (fond) ---- */
    $p9_min = 0; $p9_max = 0;
    if ($courriels > 0) {
        $m9 = 1.0;
        if ($b('diff_publique')) $m9 *= 2;
        if ($b('diff_recidive')) $m9 *= 1.5;
        $p9_min = $courriels * 5000;
        $p9_max = $courriels * 5000 * $m9;
    }
    $postes[] = ['num' => 9, 'titre' => 'Diffamation (le cas échéant)', 'amiable' => 0,
        'fond_min' => $p9_min, 'fond_max' => $p9_max,
        'formule' => $courriels . ' courriel(s) × 5 000 € × public/récidive', 'note' => 'Communauté d\'intérêts entre destinataires = non publique.'];

    /* ---- Poste 10 : contrainte à signature (fond) ---- */
    $p10_min = 0; $p10_max = 0;
    if ($engmnts > 0) {
        $p10_min = $engmnts * 20000;
        $p10_max = $engmnts * 20000 * ($b('contrainte_systemique') ? 2.5 : 1.0);
    }
    $postes[] = ['num' => 10, 'titre' => 'Contrainte à signature', 'amiable' => 0,
        'fond_min' => $p10_min, 'fond_max' => $p10_max,
        'formule' => $engmnts . ' engagement(s) × 20 000 € × systémique', 'note' => 'Art. 1143 C. civ. (violence économique).'];

    /* ---- Poste 11 : astreinte journalière (fond, pour forcer les travaux) ---- */
    $astr_jour  = $n('astreinte_jour');
    $astr_jours = $n('astreinte_jours');
    $p11 = $astr_jour * $astr_jours;
    $postes[] = ['num' => 11, 'titre' => 'Astreinte journalière', 'amiable' => 0,
        'fond_min' => 0, 'fond_max' => $p11,
        'formule' => $astr_jour ? (number_format($astr_jour, 0, ',', ' ') . ' €/jour × ' . (int) $astr_jours . ' jours') : 'non demandée',
        'note' => 'Levier pour contraindre le bailleur à exécuter (demandée au juge).'];

    /* ---- Poste 12 : Relogement & déménagement (AMIABLE — charge NMH) ---- */
    $relog = $n('relogement_cout');
    if ($relog <= 0) {
        $rj = (int) $n('relogement_jours');
        if ($rj > 0) $relog = $rj * 80; /* forfait hébergement 80 €/nuit à défaut de factures */
    }
    $demenag = $n('demenagement_cout');
    $relog_total = $relog + $demenag;
    $postes[] = ['num' => 12, 'titre' => 'Relogement & déménagement (charge NMH)', 'amiable' => $relog_total,
        'fond_min' => $relog_total, 'fond_max' => $relog_total,
        'formule' => 'relogement' . ($n('relogement_cout') > 0 ? ' (factures)' : ((int) $n('relogement_jours') ? ' (' . (int) $n('relogement_jours') . ' nuits × 80 €)' : '')) . ($demenag > 0 ? ' + déménagement ' . number_format($demenag, 0, ',', ' ') . ' €' : ''),
        'note' => 'À la charge du bailleur — art. L.521-3-1 du CCH. NMH supporte ET organise : relogement + déménagement (entreprise), transport des meubles et des effets personnels.'];

    /* ---- Poste 13 : Traitement des effets — ADAPTÉ AU NUISIBLE (AMIABLE) ----
       Punaises de lit : ensachage + lavage 60-90° + congélation de tout le linge.
       Blattes / autres : PAS d'ensachage → protection des denrées, éviction
       temporaire pendant l'intervention. On adapte à la situation du locataire. */
    $ttype = isset($p['traitement_type']) ? sanitize_key((string) $p['traitement_type']) : '';
    $is_punaises = ($ttype === 'punaises');
    $trait = $n('traitement_effets');
    if ($trait <= 0 && $b('traitement_fait')) {
        $trait = $is_punaises ? (150 + $membres * 120) : ($membres * 40);
    }
    $titre13 = $is_punaises
        ? 'Mise en sacs, lavage 60-90° & congélation (punaises)'
        : 'Sujétions du traitement (protection, éviction temporaire)';
    $note13 = $is_punaises
        ? 'Spécifique PUNAISES DE LIT : ensachage de tout le linge, lavage 60-90° / congélation. À la charge du bailleur.'
        : ($ttype === 'blattes'
            ? 'BLATTES / cafards : PAS d\'ensachage (inutile) — protection des denrées, éviction pendant le traitement, remise en état. À la charge du bailleur.'
            : 'Adapté au nuisible/désordre du logement : sujétions réellement imposées. À la charge du bailleur.');
    $postes[] = ['num' => 13, 'titre' => $titre13, 'amiable' => $trait,
        'fond_min' => $trait, 'fond_max' => $trait,
        'formule' => $n('traitement_effets') > 0 ? 'sur factures' : ($b('traitement_fait') ? ($is_punaises ? ('150 € + ' . $membres . ' × 120 €') : ($membres . ' × 40 €')) : 'non renseigné'),
        'note' => $note13];

    /* ---- Poste 14 : Remplacement des effets personnels dégradés (AMIABLE) ---- */
    $effets = $n('effets_remplacement');
    $postes[] = ['num' => 14, 'titre' => 'Remplacement des effets personnels dégradés', 'amiable' => $effets,
        'fond_min' => $effets, 'fond_max' => $effets * 1.2,
        'formule' => 'biens personnels cassés/dégradés du fait des désordres (sur estimation/factures)',
        'note' => 'Dégradation imputable au bailleur (art. 1719, 1231-1 C. civ.).'];

    /* ---- Poste 15 : Frais d'accompagnement engagés (AMIABLE) ---- */
    $frais = $n('frais_accompagnement');
    $postes[] = ['num' => 15, 'titre' => 'Frais d\'accompagnement engagés', 'amiable' => $frais,
        'fond_min' => $frais, 'fond_max' => $frais,
        'formule' => 'déplacements, constats, courriers, temps (cumul du dossier)',
        'note' => 'Frais engagés du fait de la défaillance du bailleur — réclamés au préjudice, jamais facturés à NMH.'];

    $amiable = 0; $fmin = 0; $fmax = 0;
    foreach ($postes as $po) { $amiable += $po['amiable']; $fmin += $po['fond_min']; $fmax += $po['fond_max']; }
    return ['postes' => $postes, 'amiable' => $amiable, 'fond_min' => $fmin, 'fond_max' => $fmax];
}

function lfi_nct_prej_eur($v) { return number_format((float) $v, 0, ',', ' ') . ' €'; }

/** Récupère les paramètres depuis $_GET (préremplissage / recalcul). */
function lfi_nct_prej_params_from_request() {
    $keys_num = ['annees', 'membres', 'enfants_mineurs', 'enfants_mdph', 'personnes_ald', 'loyer', 'coef',
        'enfants_scolarises', 'courriels_diffamatoires', 'engagements_contrainte',
        'p1_souffrances_cotation', 'p1_dft_taux', 'p1_dft_jours', 'p1_agrement', 'p1_esthetique_cotation',
        'p2_intensite', 'p4_factures', 'p5_factures', 'lits_adulte', 'lits_enfant', 'sommiers', 'matelas_simple', 'matelas_double',
        'astreinte_jour', 'astreinte_jours'];
    $keys_bool = ['photos', 'arrets_5j', 'dermatose', 'anxiete', 'precancereux', 'arret_scolaire', 'decrochage',
        'redoublement', 'diff_publique', 'diff_recidive', 'contrainte_systemique'];
    $p = [];
    foreach ($keys_num as $k) if (isset($_GET[$k]) && $_GET[$k] !== '') $p[$k] = (float) $_GET[$k];
    foreach ($keys_bool as $k) if (!empty($_GET[$k])) $p[$k] = 1;
    return $p;
}

/* ============================================================== *
 *  VUE : Calculateur                                             *
 * ============================================================== */
function lfi_nct_app_view_prejudice() {
    if (!lfi_nct_prej_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    $has = !empty($_GET['calc']);
    $p   = lfi_nct_prej_params_from_request();

    /* Dossier rattaché (préremplissage + « verser au dossier »). */
    $did = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $dossier = ($did && function_exists('lfi_nct_dossier_get')) ? lfi_nct_dossier_get($did) : null;

    /* Verser le chiffrage au dossier. */
    if ($dossier && !empty($_POST['lfi_prej_save']) && check_admin_referer('lfi_prej_save_' . $did)) {
        global $wpdb;
        $r = lfi_nct_prej_compute($p);
        $notes = json_decode($dossier->notes ?? '', true);
        if (!is_array($notes)) $notes = [];
        $notes['prejudice'] = [
            'date'     => wp_date('Y-m-d'),
            'amiable'  => round($r['amiable']),
            'fond_min' => round($r['fond_min']),
            'fond_max' => round($r['fond_max']),
            'params'   => $p,
        ];
        $wpdb->update($wpdb->prefix . 'lfi_nct_dossiers_locataires',
            ['notes' => wp_json_encode($notes), 'updated_at' => current_time('mysql')], ['id' => $did]);
        wp_safe_redirect(add_query_arg(array_merge($p, ['vue' => 'prejudice', 'calc' => 1, 'id' => $did, 'saved' => 1]), home_url('/' . LFI_NCT_APP_SLUG . '/')));
        exit;
    }

    /* Préremplissage depuis le dossier : d'abord le chiffrage déjà enregistré
       (rempli automatiquement), sinon quelques indices des constatations. */
    if ($dossier && !$has) {
        $dnotes = json_decode($dossier->notes ?? '', true);
        if (is_array($dnotes) && !empty($dnotes['prejudice']['params']) && is_array($dnotes['prejudice']['params'])) {
            $p = array_merge($dnotes['prejudice']['params'], $p);
            $has = true; /* on affiche directement le résultat pré-rempli */
        } else {
            $has_med = trim((string) ($dossier->certificat_medecin ?? '') . (string) ($dossier->certificat_pathologie ?? '')) !== '';
            if ($has_med) { $p['anxiete'] = 1; }
        }
    }

    $sub = $dossier ? ('Dossier : ' . trim($dossier->tenant_prenom . ' ' . $dossier->tenant_nom)) : 'Punaises de lit — méthode DOUCET c/ NMH · 11 postes';
    lfi_nct_app_screen_open('💶 Chiffrage du préjudice', $sub);
    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Chiffrage versé au dossier.');

    /* Rappel d'actualisation annuelle des barèmes. */
    $year = (int) wp_date('Y');
    $bareme_year = (int) get_option('lfi_nct_prej_bareme_year', 2026);
    if ($year > $bareme_year) {
        echo '<div class="lfi-app-help" style="background:#fff3cd;border-left:4px solid #d39e00"><small>📅 Les barèmes datent de ' . $bareme_year . '. Pense à les actualiser (Mornet + jurisprudence ' . $year . ').</small></div>';
    }
    echo '<div class="lfi-app-help">Sortie <strong>double</strong> : un plancher <strong>amiable</strong> (postes 1–5, solides) et une <strong>fourchette au fond</strong> (10 postes). Montants = ordres de grandeur <strong>à valider avec l\'avocat</strong>.</div>';

    /* Formulaire. */
    echo '<form method="get" class="lfi-app-form">';
    echo '<input type="hidden" name="vue" value="prejudice"><input type="hidden" name="calc" value="1">';
    if (!empty($_GET['id'])) echo '<input type="hidden" name="id" value="' . (int) $_GET['id'] . '">';
    $v = function ($k, $d = '') use ($p) { return isset($p[$k]) ? esc_attr($p[$k]) : $d; };
    $ck = function ($k) use ($p) { return !empty($p[$k]) ? 'checked' : ''; };

    echo '<h4 style="margin:10px 0 4px;color:#c8102e">Foyer & exposition</h4>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
    echo '<label style="flex:1;min-width:120px">Années d\'exposition<input type="number" name="annees" value="' . $v('annees', '0') . '" min="0" step="1"></label>';
    echo '<label style="flex:1;min-width:120px">Personnes au foyer<input type="number" name="membres" value="' . $v('membres', '1') . '" min="1" step="1"></label>';
    echo '<label style="flex:1;min-width:120px">Loyer mensuel (€)<input type="number" name="loyer" value="' . $v('loyer', '0') . '" min="0" step="1"></label>';
    echo '</div>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
    echo '<label style="flex:1;min-width:110px">Enfants mineurs<input type="number" name="enfants_mineurs" value="' . $v('enfants_mineurs', '0') . '" min="0"></label>';
    echo '<label style="flex:1;min-width:110px">Enfants MDPH<input type="number" name="enfants_mdph" value="' . $v('enfants_mdph', '0') . '" min="0"></label>';
    echo '<label style="flex:1;min-width:110px">Personnes ALD<input type="number" name="personnes_ald" value="' . $v('personnes_ald', '0') . '" min="0"></label>';
    echo '<label style="flex:1;min-width:130px">Enfants scolarisés<input type="number" name="enfants_scolarises" value="' . $v('enfants_scolarises', '0') . '" min="0"></label>';
    echo '</div>';
    echo '<label>Coefficient de dégradation (jouissance)<select name="coef">';
    foreach (['0.20' => '0,20 — gêne modérée', '0.40' => '0,40 — gêne sévère', '0.70' => '0,70 — gêne majeure', '1.00' => '1,00 — privation totale'] as $val => $lab) {
        $sel = ((string) $v('coef', '0.40') === $val) ? 'selected' : '';
        echo '<option value="' . $val . '" ' . $sel . '>' . esc_html($lab) . '</option>';
    }
    echo '</select></label>';

    echo '<h4 style="margin:12px 0 4px;color:#c8102e">Poste 1 — corporel (à ajuster avec l\'avocat)</h4>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
    echo '<label style="flex:1;min-width:120px">Souffrances (0–7)<input type="number" name="p1_souffrances_cotation" value="' . $v('p1_souffrances_cotation', '3') . '" min="0" max="7" step="0.5"></label>';
    echo '<label style="flex:1;min-width:120px">DFT taux (%)<input type="number" name="p1_dft_taux" value="' . $v('p1_dft_taux', '25') . '" min="0" max="100"></label>';
    echo '<label style="flex:1;min-width:120px">Agrément (€)<input type="number" name="p1_agrement" value="' . $v('p1_agrement', '0') . '" min="0"></label>';
    echo '<label style="flex:1;min-width:120px">Esthétique (0–7)<input type="number" name="p1_esthetique_cotation" value="' . $v('p1_esthetique_cotation', '0') . '" min="0" max="7" step="0.5"></label>';
    echo '</div>';

    echo '<h4 style="margin:12px 0 4px;color:#c8102e">Biens & frais (factures si dispo)</h4>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
    echo '<label style="flex:1;min-width:150px">Factures biens détruits (€)<input type="number" name="p4_factures" value="' . $v('p4_factures', '0') . '" min="0"></label>';
    echo '<label style="flex:1;min-width:150px">Factures produits/frais (€)<input type="number" name="p5_factures" value="' . $v('p5_factures', '0') . '" min="0"></label>';
    echo '</div>';

    /* Postes AMIABLE « à la charge du bailleur » : relogement, traitement des
       effets (mise en sacs/lavage), remplacement des effets, frais engagés. */
    $frais_def = ($dossier && function_exists('lfi_nct_frais_total')) ? (string) round(lfi_nct_frais_total((int) $dossier->id)) : '0';
    echo '<h4 style="margin:12px 0 4px;color:#186a3b">À la charge du bailleur (amiable) — adapté à la situation</h4>';
    $tt = $v('traitement_type', '');
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
    echo '<label style="flex:1;min-width:170px">Type de nuisible / traitement<select name="traitement_type">'
       . '<option value=""' . selected($tt, '', false) . '>— (aucun / autre désordre)</option>'
       . '<option value="punaises"' . selected($tt, 'punaises', false) . '>🛏️ Punaises de lit (ensachage + 60-90°)</option>'
       . '<option value="blattes"' . selected($tt, 'blattes', false) . '>🪳 Blattes / cafards (pas d\'ensachage)</option>'
       . '<option value="autre"' . selected($tt, 'autre', false) . '>Autre désordre</option>'
       . '</select></label>';
    echo '<label style="flex:1;min-width:150px">Relogement — coût (€)<input type="number" name="relogement_cout" value="' . $v('relogement_cout', '0') . '" min="0"></label>';
    echo '<label style="flex:1;min-width:130px">…ou nuits d\'hébergement<input type="number" name="relogement_jours" value="' . $v('relogement_jours', '0') . '" min="0"></label>';
    echo '<label style="flex:1;min-width:150px">Déménagement / entreprise (€)<input type="number" name="demenagement_cout" value="' . $v('demenagement_cout', '0') . '" min="0"></label>';
    echo '<label style="flex:1;min-width:150px">Traitement des effets (€)<input type="number" name="traitement_effets" value="' . $v('traitement_effets', '0') . '" min="0"></label>';
    echo '<label style="flex:1;min-width:170px">Remplacement effets perso (€)<input type="number" name="effets_remplacement" value="' . $v('effets_remplacement', '0') . '" min="0"></label>';
    echo '<label style="flex:1;min-width:150px">Frais d\'accompagnement (€)<input type="number" name="frais_accompagnement" value="' . $v('frais_accompagnement', $frais_def) . '" min="0"></label>';
    echo '</div>';
    echo '<label class="lfi-app-checkbox-row"><input type="checkbox" name="traitement_fait" value="1" ' . $ck('traitement_fait') . '> Traitement subi → forfait auto si montant vide (ensachage+90° pour punaises ; protection/éviction pour blattes)</label>';
    echo '<div class="lfi-app-help"><small>Ces postes sont <strong>à la charge du bailleur</strong> et entrent dans la demande <strong>amiable</strong>, <strong>adaptés à la situation du locataire</strong> : relogement <strong>+ déménagement</strong> (NMH supporte et organise, art. L.521-3-1 CCH), sujétions du traitement <strong>selon le nuisible</strong> (ensachage seulement pour les punaises), remplacement des effets dégradés, frais engagés. La demande n\'est jamais « que des travaux » : elle est aussi <strong>relogement + financière</strong>.</small></div>';

    echo '<h4 style="margin:12px 0 4px;color:#c8102e">Pièces & aggravations (cochez ce qui s\'applique)</h4>';
    foreach ([
        'photos' => 'Photos horodatées', 'arrets_5j' => 'Arrêts de travail > 5 j', 'dermatose' => 'Dermatose chronique attestée',
        'anxiete' => 'Anxiété / insomnie documentée', 'precancereux' => 'Pathologie précancéreuse aggravée',
        'arret_scolaire' => 'Arrêt scolaire justifié', 'decrochage' => 'Décrochage scolaire', 'redoublement' => 'Redoublement provoqué',
    ] as $k => $lab) {
        echo '<label class="lfi-app-checkbox-row"><input type="checkbox" name="' . $k . '" value="1" ' . $ck($k) . '> ' . esc_html($lab) . '</label>';
    }
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">';
    echo '<label style="flex:1;min-width:150px">Courriels diffamatoires<input type="number" name="courriels_diffamatoires" value="' . $v('courriels_diffamatoires', '0') . '" min="0"></label>';
    echo '<label style="flex:1;min-width:170px">Engagements sous contrainte<input type="number" name="engagements_contrainte" value="' . $v('engagements_contrainte', '0') . '" min="0"></label>';
    echo '</div>';
    echo '<label class="lfi-app-checkbox-row"><input type="checkbox" name="contrainte_systemique" value="1" ' . $ck('contrainte_systemique') . '> Contrainte systémique (plusieurs années)</label>';

    echo '<h4 style="margin:12px 0 4px;color:#c8102e">Astreinte (au fond — pour forcer les travaux)</h4>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
    echo '<label style="flex:1;min-width:140px">Astreinte (€/jour)<input type="number" name="astreinte_jour" value="' . $v('astreinte_jour', '0') . '" min="0"></label>';
    echo '<label style="flex:1;min-width:140px">Nombre de jours estimé<input type="number" name="astreinte_jours" value="' . $v('astreinte_jours', '0') . '" min="0"></label>';
    echo '</div>';

    echo '<button type="submit" class="btn-primary" style="margin-top:10px">💶 Calculer le chiffrage</button>';
    echo '</form>';

    if ($has) {
        $r = lfi_nct_prej_compute($p);
        echo '<div class="lfi-app-stats-grid" style="margin-top:14px">';
        echo '<div class="stat"><div class="ico">🤝</div><div class="n" style="font-size:1.1em">' . lfi_nct_prej_eur($r['amiable']) . '</div><div class="l">Amiable (postes 1–5)</div></div>';
        echo '<div class="stat"><div class="ico">⚖️</div><div class="n" style="font-size:1.1em">' . lfi_nct_prej_eur($r['fond_min']) . ' – ' . lfi_nct_prej_eur($r['fond_max']) . '</div><div class="l">Au fond (10 postes)</div></div>';
        echo '</div>';

        echo '<h3 style="margin:14px 0 6px;color:#c8102e">Détail par poste</h3><ul class="lfi-app-list">';
        foreach ($r['postes'] as $po) {
            echo '<li class="lfi-app-card"><div class="head"><div class="who">Poste ' . $po['num'] . ' — ' . esc_html($po['titre']) . '</div></div>';
            echo '<div class="meta"><span class="meta-chip">Amiable : ' . lfi_nct_prej_eur($po['amiable']) . '</span><span class="meta-chip">Fond : ' . lfi_nct_prej_eur($po['fond_min']) . ' – ' . lfi_nct_prej_eur($po['fond_max']) . '</span></div>';
            echo '<div class="com"><small>' . esc_html($po['formule']) . ($po['note'] ? ' · ' . esc_html($po['note']) : '') . '</small></div></li>';
        }
        echo '</ul>';

        /* Pièces manquantes qui augmenteraient le chiffrage. */
        $manque = [];
        if (empty($p['photos']))     $manque[] = 'Photos horodatées (EXIF) — poste 1 ×1,2';
        if (($p['p4_factures'] ?? 0) <= 0) $manque[] = 'Factures literie/textiles — solidifie le poste 4 en amiable';
        if (empty($p['dermatose']) && empty($p['anxiete'])) $manque[] = 'Certificat médical (piqûres, anxiété, insomnie) — postes 1 et 8';
        $manque[] = 'PV du SCHS / constat d\'huissier — pièce maîtresse';
        $manque[] = 'Expertise entomologique (Muséum) — ancre le caractère avéré';
        if (($p['personnes_ald'] ?? 0) <= 0 && ($p['enfants_mdph'] ?? 0) <= 0) $manque[] = 'ALD / MDPH d\'un membre — postes 1, 6, 8 (fort impact)';
        echo '<h3 style="margin:14px 0 6px;color:#c8102e">📎 Pièces à obtenir (elles augmentent le chiffrage)</h3><ul class="lfi-app-list">';
        foreach ($manque as $m) echo '<li class="lfi-app-card" style="padding:8px 12px"><div class="com">☐ ' . esc_html($m) . '</div></li>';
        echo '</ul>';

        $rep_url = add_query_arg(array_merge($p, ['vue' => 'prejudice-report', 'calc' => 1]), home_url('/' . LFI_NCT_APP_SLUG . '/'));
        echo '<div class="row-actions" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap"><a class="btn-primary" href="' . esc_url($rep_url) . '" target="_blank">📑 Rapport imprimable (avec sources)</a>';
        if ($dossier) {
            echo '<form method="post" style="display:inline;margin:0">';
            wp_nonce_field('lfi_prej_save_' . $did);
            echo '<input type="hidden" name="lfi_prej_save" value="1">';
            foreach ($p as $k => $val) echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($val) . '">';
            echo '<button type="submit" class="btn-primary" style="background:#0066a3">💾 Verser ce chiffrage au dossier</button>';
            echo '</form>';
        }
        echo '</div>';
        echo '<div class="lfi-app-help" style="margin-top:8px;background:#fff3cd;border-left:4px solid #d39e00"><small>⚠️ Ordres de grandeur ajustables. En amiable, ne mobilise que les postes solides. Au fond, l\'avocat sollicite le haut et négocie vers la moyenne.</small></div>';
    }

    lfi_nct_app_screen_close();
}

/* Rapport imprimable avec citations. */
function lfi_nct_app_view_prejudice_report() {
    if (!lfi_nct_prej_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $p = lfi_nct_prej_params_from_request();
    $r = lfi_nct_prej_compute($p);
    $src = lfi_nct_prej_sources();

    echo '<div class="lfi-app"><div style="max-width:820px;margin:0 auto;padding:16px">';
    echo '<div class="row-actions" style="margin-bottom:10px"><button onclick="window.print()" class="btn-primary">🖨 Imprimer / PDF</button> <a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('prejudice')) . '">← Retour</a></div>';
    echo '<div style="background:#fff;padding:24px;border:1px solid #ddd;border-radius:8px;line-height:1.55">';
    echo '<h1 style="color:#c8102e;font-size:1.5em;margin:0 0 2px">Chiffrage du préjudice — infestation de punaises de lit</h1>';
    echo '<div style="color:#666;font-size:.9em;margin-bottom:12px">Méthode DOUCET c/ NMH · établi le ' . esc_html(wp_date('j F Y')) . ' · ordres de grandeur à valider avec l\'avocat.</div>';

    echo '<div style="display:flex;gap:14px;flex-wrap:wrap;margin:8px 0 14px">';
    echo '<div style="flex:1;min-width:200px;border:2px solid #186a3b;border-radius:8px;padding:10px"><div style="font-weight:800;color:#186a3b">Chiffrage amiable</div><div style="font-size:1.4em;font-weight:800">' . lfi_nct_prej_eur($r['amiable']) . '</div><div style="font-size:.85em;color:#555">Postes 1 à 5, les plus solides.</div></div>';
    echo '<div style="flex:1;min-width:200px;border:2px solid #c8102e;border-radius:8px;padding:10px"><div style="font-weight:800;color:#c8102e">Fourchette au fond</div><div style="font-size:1.4em;font-weight:800">' . lfi_nct_prej_eur($r['fond_min']) . ' – ' . lfi_nct_prej_eur($r['fond_max']) . '</div><div style="font-size:.85em;color:#555">Les 10 postes mobilisés.</div></div>';
    echo '</div>';

    echo '<h2 style="font-size:1.15em;color:#0066a3">Détail poste par poste</h2>';
    foreach ($r['postes'] as $po) {
        echo '<div style="border:1px solid #e3e3e3;border-radius:8px;padding:10px 12px;margin:8px 0">';
        echo '<strong>Poste ' . $po['num'] . ' — ' . esc_html($po['titre']) . '</strong>';
        echo '<div>Amiable : <strong>' . lfi_nct_prej_eur($po['amiable']) . '</strong> · Fond : <strong>' . lfi_nct_prej_eur($po['fond_min']) . ' – ' . lfi_nct_prej_eur($po['fond_max']) . '</strong></div>';
        echo '<div style="font-size:.9em;color:#444">Formule : ' . esc_html($po['formule']) . ($po['note'] ? ' — ' . esc_html($po['note']) : '') . '</div>';
        echo '</div>';
    }

    echo '<h2 style="font-size:1.15em;color:#0066a3;margin-top:14px">Sources mobilisées</h2>';
    foreach (['scientifiques' => 'Sources scientifiques', 'juridiques' => 'Fondements juridiques', 'jurisprudence' => 'Jurisprudence', 'referentiels' => 'Référentiels d\'indemnisation'] as $k => $lab) {
        echo '<p style="margin:6px 0 2px"><strong>' . esc_html($lab) . '</strong></p><ul style="margin:0 0 6px 18px">';
        foreach ($src[$k] as $s) echo '<li style="font-size:.9em">' . esc_html($s) . '</li>';
        echo '</ul>';
    }
    echo '<p style="color:#666;font-size:.85em;margin-top:12px">Document d\'aide au chiffrage — pas un avis juridique. Chaque montant doit être documenté par des pièces et validé par l\'avocat.</p>';
    echo '</div></div></div>';
}

/* ============================================================== *
 *  REST : remplir le chiffrage d'un dossier à distance          *
 *  (clé d'intégration). Montants explicites OU calculés.         *
 * ============================================================== */
add_action('rest_api_init', function () {
    register_rest_route('lfi-nct/v1', '/prejudice-set', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_prej_rest_set',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
});
function lfi_nct_prej_rest_set($request) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $id = (int) $request->get_param('dossier_id');
    $row = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id)) : null;
    if (!$row) return new WP_REST_Response(['ok' => false, 'error' => 'dossier_introuvable'], 404);

    $keys = ['annees', 'membres', 'enfants_mineurs', 'enfants_mdph', 'personnes_ald', 'loyer', 'coef',
        'enfants_scolarises', 'courriels_diffamatoires', 'engagements_contrainte',
        'p1_souffrances_cotation', 'p1_dft_taux', 'p1_dft_jours', 'p1_agrement', 'p1_esthetique_cotation',
        'p2_intensite', 'p4_factures', 'p5_factures', 'astreinte_jour', 'astreinte_jours',
        'photos', 'arrets_5j', 'dermatose', 'anxiete', 'precancereux', 'arret_scolaire', 'decrochage',
        'redoublement', 'diff_publique', 'diff_recidive', 'contrainte_systemique'];
    $params = [];
    foreach ($keys as $k) { $v = $request->get_param($k); if ($v !== null && $v !== '') $params[$k] = $v; }

    $explicit = $request->get_param('amiable');
    if ($explicit !== null && $explicit !== '') {
        $prej = [
            'amiable'  => round((float) $request->get_param('amiable')),
            'fond_min' => round((float) $request->get_param('fond_min')),
            'fond_max' => round((float) $request->get_param('fond_max')),
        ];
    } else {
        $r = lfi_nct_prej_compute($params);
        $prej = ['amiable' => round($r['amiable']), 'fond_min' => round($r['fond_min']), 'fond_max' => round($r['fond_max'])];
    }

    $notes = json_decode($row->notes ?? '', true);
    if (!is_array($notes)) $notes = [];
    $notes['prejudice'] = array_merge($prej, [
        'date'   => wp_date('Y-m-d'),
        'params' => $params,
        'source' => sanitize_text_field((string) $request->get_param('source')),
        'note'   => sanitize_text_field((string) $request->get_param('note')),
    ]);
    $wpdb->update($t, ['notes' => wp_json_encode($notes), 'updated_at' => current_time('mysql')], ['id' => $id]);
    return new WP_REST_Response(['ok' => true, 'prejudice' => $notes['prejudice']], 200);
}
