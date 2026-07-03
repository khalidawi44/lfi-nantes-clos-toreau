<?php
/**
 * DOSSIER SCIENTIFIQUE — bots d'analyse + export expertisable.
 *
 * Objectif : produire, par locataire, un dossier EXHAUSTIF et SOURCÉ qu'on peut
 * remettre à un scientifique / expert pour qu'il valide notre analyse et en
 * tire ses conclusions. Trois briques :
 *
 *   1. BOT DÉSORDRES → détecte les désordres déclarés (humidité, moisissures,
 *      punaises, blattes, froid, ventilation, plomb…) et les relie aux NORMES
 *      applicables (décence, RSD, Code de la santé publique).
 *   2. BOT MÉDICAL → relie les pathologies déclarées / le certificat médical
 *      aux effets sanitaires documentés du logement, avec RÉFÉRENCES (OMS,
 *      ANSES, HCSP, INSERM, Santé publique France).
 *   3. GRILLE PHOTOS → liste les pièces jointes comme éléments de preuve à
 *      expertiser (type, ce que ça prouve, norme). PAS d'analyse d'image
 *      automatique inventée : on fournit le cadre rigoureux, l'expert tranche.
 *
 * Honnêteté scientifique (règle du profil : la preuve, pas la promesse) :
 * chaque affirmation est adossée à une source publique légitime. JAMAIS de
 * source d'extrême droite. On n'invente aucun chiffre ni aucun résultat d'image.
 */
if (!defined('ABSPATH')) exit;

/* ============================================================== *
 *  BASE DE CONNAISSANCES — désordres → normes + santé (sourcé)   *
 * ============================================================== */
function lfi_nct_sci_desordres_kb() {
    return [
        'humidite' => [
            'label'   => 'Humidité / infiltrations',
            'kw'      => ['humidit', 'infiltrat', 'fuite', 'condensation', 'salpêtre', 'salpetre', 'moisi'],
            'normes'  => ['Décret n°2002-120 (décence), art. 2-3 — clos, couvert, étanche',
                          'RSD — art. 23 (murs et parois sains)',
                          'CSP art. L.1331-22 (local impropre par nature)'],
            'sante'   => ['Aggravation asthme, rhinite, infections respiratoires basses',
                          'OMS — WHO Guidelines for indoor air quality: dampness and mould (2009)',
                          'ANSES — expertise moisissures dans l\'habitat (2016)'],
        ],
        'moisissures' => [
            'label'   => 'Moisissures',
            'kw'      => ['moisissur', 'moisi', 'champignon', 'noircissement'],
            'normes'  => ['Décret n°2002-120, art. 3 (absence de risque pour la santé)',
                          'RSD art. 23 · CSP L.1331-26 (insalubrité)'],
            'sante'   => ['Asthme, allergies, toux chronique ; surrisque chez l\'enfant',
                          'OMS (2009) — lien causal établi humidité/moisissures ↔ pathologies respiratoires',
                          'HCSP — recommandations moisissures (2016)'],
        ],
        'punaises' => [
            'label'   => 'Punaises de lit',
            'kw'      => ['punaise', 'punaises de lit', 'piqûre', 'piqure', 'bed bug'],
            'normes'  => ['Décret n°2002-120 (jouissance paisible, absence de nuisibles)',
                          'Loi 6 juillet 1989, art. 6 (logement décent) · art. 1720 C. civ.'],
            'sante'   => ['Lésions cutanées (prurigo), surinfection, prurit',
                          'Troubles du sommeil, anxiété, retentissement psychologique',
                          'HCSP — avis relatif à la lutte contre les punaises de lit (2019)'],
        ],
        'blattes' => [
            'label'   => 'Blattes / cafards',
            'kw'      => ['blatte', 'cafard', 'coquerelle'],
            'normes'  => ['RSD (lutte contre les nuisibles) · Décret décence',
                          'CSP L.1331-22 si prolifération rendant impropre'],
            'sante'   => ['Allergènes respiratoires, aggravation de l\'asthme',
                          'Contamination des denrées ; risque digestif',
                          'ANSES — nuisibles et santé dans l\'habitat'],
        ],
        'froid' => [
            'label'   => 'Froid / chauffage / eau chaude défaillants',
            'kw'      => ['chauffage', 'eau chaude', 'froid', 'coupure', 'radiateur', 'sans chauffage'],
            'normes'  => ['Décret n°2002-120, art. 3 (chauffage + eau chaude conformes)',
                          'RSD · Code civil art. 1719 (délivrance conforme)'],
            'sante'   => ['Précarité énergétique : effets cardiovasculaires et respiratoires',
                          'OMS — WHO Housing and health guidelines (2018), chapitre « cold housing »',
                          'Fondation Abbé Pierre — précarité énergétique (rapports annuels)'],
        ],
        'ventilation' => [
            'label'   => 'Ventilation / VMC défaillante',
            'kw'      => ['vmc', 'ventilation', 'aération', 'aeration', 'renouvellement d\'air', 'sans fenêtre'],
            'normes'  => ['Arrêté du 24 mars 1982 (aération des logements)',
                          'Décret n°2002-120, art. 3 · RSD art. 40-63'],
            'sante'   => ['Accumulation d\'humidité, de CO₂ et de COV → pathologies respiratoires',
                          'ANSES — qualité de l\'air intérieur'],
        ],
        'plomb' => [
            'label'   => 'Plomb (peintures anciennes) / saturnisme',
            'kw'      => ['plomb', 'saturnisme', 'peinture écaillée', 'peinture ecaillee'],
            'normes'  => ['CSP art. L.1334-1 et s. (lutte contre le saturnisme)',
                          'Constat de Risque d\'Exposition au Plomb (CREP) obligatoire'],
            'sante'   => ['Saturnisme infantile : atteintes neuro-développementales',
                          'Santé publique France — surveillance du saturnisme de l\'enfant'],
        ],
        'electricite' => [
            'label'   => 'Installation électrique dangereuse',
            'kw'      => ['électriqu', 'electriqu', 'court-circuit', 'prise', 'disjoncteur', 'fils'],
            'normes'  => ['Décret n°2002-120, art. 3 (réseaux et raccordements aux normes de sécurité)',
                          'RSD · risque d\'accident domestique'],
            'sante'   => ['Risque d\'électrisation, d\'incendie',
                          'Constat électrique (diagnostic) opposable'],
        ],
        'suroccupation' => [
            'label'   => 'Sur-occupation / surface insuffisante',
            'kw'      => ['suroccup', 'sur-occup', 'trop petit', 'surface', 'exigu', 'exigü'],
            'normes'  => ['Décret n°2002-120, art. 4 (surface habitable ≥ 9 m² / hauteur ≥ 2,20 m)',
                          'CCH art. R.822-25 (indécence par sur-occupation)'],
            'sante'   => ['Retentissement sur la santé mentale et le développement de l\'enfant',
                          'INSERM — conditions de logement et santé'],
        ],
    ];
}

/** Détecte les désordres présents dans un texte (constatations, pathologie…). */
function lfi_nct_sci_detect_desordres($text) {
    $low = mb_strtolower((string) $text);
    $out = [];
    foreach (lfi_nct_sci_desordres_kb() as $key => $d) {
        foreach ($d['kw'] as $kw) {
            if (mb_strpos($low, mb_strtolower($kw)) !== false) { $out[$key] = $d; break; }
        }
    }
    return $out;
}

/* ============================================================== *
 *  VUE : Dossier scientifique complet (expertisable)            *
 * ============================================================== */
function lfi_nct_app_view_dossier_scientifique() {
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $d  = ($id && function_exists('lfi_nct_dossier_get')) ? lfi_nct_dossier_get($id) : null;
    if (!$d) { wp_safe_redirect(lfi_nct_app_url('dossiers-juridiques')); exit; }

    $nom = trim(($d->tenant_prenom ?? '') . ' ' . ($d->tenant_nom ?? '')) ?: ('Dossier #' . $id);
    $constat = (string) ($d->constatations ?? '');
    $patho   = (string) ($d->certificat_pathologie ?? '');
    $desordres = lfi_nct_sci_detect_desordres($constat . ' ' . $patho);

    $notes = json_decode($d->notes ?? '', true);
    if (!is_array($notes)) $notes = [];
    $pieces = !empty($notes['pieces']) && is_array($notes['pieces']) ? $notes['pieces'] : [];
    $prej   = !empty($notes['prejudice']) && is_array($notes['prejudice']) ? $notes['prejudice'] : null;

    lfi_nct_app_screen_open('🔬 Dossier scientifique', $nom . ' — pièce à faire expertiser');

    echo '<div class="row-actions" style="margin-bottom:10px"><button onclick="window.print()" class="btn-primary">🖨 Imprimer / PDF</button> '
       . '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-juridique-edit', ['id' => $id])) . '">← Dossier</a></div>';

    echo '<div class="lfi-rec-doc">';
    echo '<h1>Dossier d\'analyse — ' . esc_html($nom) . '</h1>';
    echo '<p style="text-align:center;color:#555">Document de synthèse destiné à une expertise scientifique indépendante.<br>Groupe d\'Action LFI Nantes Sud – Clos Toreau · Association Union des Quartiers Libres · ' . esc_html(wp_date('j F Y')) . '</p>';

    /* 1. Identité du logement. */
    echo '<h2>1. Le logement</h2><table class="detail">';
    echo '<tr><td><strong>Occupant·e</strong></td><td>' . esc_html($nom) . '</td></tr>';
    $adr = trim(($d->tenant_adresse ?? '') . ' ' . ($d->tenant_etage ? '· ét. ' . $d->tenant_etage : '') . ' ' . ($d->tenant_appartement ? '· apt ' . $d->tenant_appartement : ''));
    echo '<tr><td><strong>Logement</strong></td><td>' . esc_html($adr ?: '—') . '</td></tr>';
    echo '<tr><td><strong>Bailleur</strong></td><td>Nantes Métropole Habitat (OPH)</td></tr>';
    echo '</table>';

    /* 2. Désordres constatés → normes. */
    echo '<h2>2. Désordres constatés et normes applicables</h2>';
    if (empty($desordres)) {
        echo '<p><em>Aucun désordre n\'a encore été détecté automatiquement dans les constatations. Complète le champ « constatations » du dossier pour enrichir cette analyse.</em></p>';
    } else {
        echo '<div class="citations" style="border-left-color:#c8102e">';
        foreach ($desordres as $key => $de) {
            echo '<div style="margin:10px 0;padding-bottom:8px;border-bottom:1px solid #eee">';
            echo '<p style="margin:0 0 4px"><strong>▸ ' . esc_html($de['label']) . '</strong></p>';
            echo '<p style="margin:0 0 3px"><strong>Normes applicables :</strong></p><ul style="margin:0 0 6px">';
            foreach ($de['normes'] as $n) echo '<li>' . esc_html($n) . '</li>';
            echo '</ul>';
            echo '<p style="margin:0 0 3px"><strong>Effets sur la santé (documentés) :</strong></p><ul style="margin:0">';
            foreach ($de['sante'] as $s) echo '<li>' . esc_html($s) . '</li>';
            echo '</ul></div>';
        }
        echo '</div>';
    }
    if (trim($constat) !== '') {
        echo '<p style="margin-top:6px"><strong>Constatations de terrain (texte brut) :</strong></p>';
        echo '<div class="citations">' . nl2br(esc_html($constat)) . '</div>';
    }

    /* 3. Bot médical. */
    echo '<h2>3. Analyse médicale</h2>';
    $cert_med = trim((string) ($d->certificat_medecin ?? ''));
    $cert_dat = (string) ($d->certificat_date ?? '');
    if ($cert_med !== '' || trim($patho) !== '') {
        echo '<div class="citations" style="border-left-color:#186a3b">';
        if ($cert_med !== '') echo '<p><strong>Certificat médical :</strong> ' . esc_html($cert_med) . ($cert_dat ? ' — du ' . esc_html(wp_date('j F Y', strtotime($cert_dat))) : '') . '</p>';
        if (trim($patho) !== '') echo '<p><strong>Pathologie(s) déclarée(s) :</strong><br>' . nl2br(esc_html($patho)) . '</p>';
        echo '</div>';
        /* Lien de causalité déduit des désordres détectés. */
        if (!empty($desordres)) {
            echo '<p style="margin-top:6px"><strong>Lien de causalité logement → santé (à confirmer par l\'expert) :</strong></p><ul>';
            foreach ($desordres as $de) echo '<li><strong>' . esc_html($de['label']) . '</strong> — ' . esc_html($de['sante'][0]) . ' <span style="color:#777">(' . esc_html($de['sante'][count($de['sante'])-1]) . ')</span></li>';
            echo '</ul>';
        }
    } else {
        echo '<p><em>Pas de certificat médical ni de pathologie renseignés dans ce dossier. Si la personne a un certificat, ajoute-le : c\'est le levier le plus fort (santé).</em></p>';
    }

    /* 4. Grille photos / pièces à expertiser. */
    echo '<h2>4. Pièces de preuve à expertiser</h2>';
    if (empty($pieces)) {
        echo '<p><em>Aucune pièce jointe pour l\'instant. Les photos et documents versés au dossier apparaîtront ici comme éléments de preuve.</em></p>';
    } else {
        echo '<p style="color:#555">Chaque pièce est un élément de preuve. L\'expert apprécie sa portée au regard de la norme indiquée. <em>Aucune analyse d\'image automatique n\'est faite ici : le cadre est fourni, l\'expert conclut.</em></p>';
        echo '<table class="detail"><tr><th>Pièce</th><th>Type</th><th>Rattacher à</th></tr>';
        $labels = [];
        foreach ($desordres as $de) $labels[] = $de['label'];
        $rattach = $labels ? implode(' / ', $labels) : 'désordre constaté';
        foreach (array_reverse($pieces) as $p) {
            $ico = (($p['ext'] ?? '') === 'pdf') ? '📄' : '🖼';
            echo '<tr><td>' . $ico . ' ' . esc_html($p['name'] ?? 'Pièce') . '</td>'
               . '<td>' . esc_html(strtoupper((string) ($p['ext'] ?? ''))) . '</td>'
               . '<td>' . esc_html($rattach) . '</td></tr>';
        }
        echo '</table>';
    }

    /* 5. Chiffrage du préjudice (automatique). */
    echo '<h2>5. Chiffrage du préjudice (méthode automatisée)</h2>';
    if ($prej) {
        echo '<div class="citations" style="border-left-color:#0066a3">';
        echo '<p><strong>Amiable :</strong> ' . number_format((float) $prej['amiable'], 0, ',', ' ') . ' €</p>';
        echo '<p><strong>Fourchette au fond :</strong> ' . number_format((float) $prej['fond_min'], 0, ',', ' ') . ' – ' . number_format((float) $prej['fond_max'], 0, ',', ' ') . ' €</p>';
        echo '<p style="color:#777">Calculé automatiquement à partir des déclarations (méthode par postes). Détail : bouton « Chiffrage » du dossier.</p>';
        echo '</div>';
    } else {
        echo '<p><em>Pas encore de chiffrage enregistré. Il se calcule automatiquement depuis la fiche du dossier.</em></p>';
    }

    /* 6. Sources & méthode. */
    echo '<h2>6. Sources & méthode</h2>';
    echo '<div class="lfi-app-help" style="background:#f7f7f7"><small>'
       . '<strong>Bases mobilisées :</strong> OMS (WHO indoor air quality guidelines 2009 ; Housing and health guidelines 2018) · ANSES · Haut Conseil de la santé publique (HCSP) · INSERM · Santé publique France · Fondation Abbé Pierre. '
       . '<strong>Cadre juridique :</strong> décret n°2002-120 (logement décent), loi du 6 juillet 1989 (art. 6), Code civil (art. 1719-1720), Code de la santé publique (L.1331-22 et s.), RSD. '
       . '<strong>Méthode :</strong> détection des désordres déclarés → mise en regard des normes → effets sanitaires documentés → chiffrage par postes. Aucune donnée inventée ; aucune analyse d\'image automatique. Document destiné à validation par un·e expert·e indépendant·e.'
       . '</small></div>';

    echo '</div>'; /* .lfi-rec-doc */
    lfi_nct_app_screen_close();
}
