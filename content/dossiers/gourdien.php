<?php
/**
 * DOSSIER LOCATAIRE — Mme Gwenaëlle GOURDIEN (apt 93) — fiche maître (code)
 *
 * Infestation de BLATTES depuis avril 2022, 14 rue Saint-Jean-de-Luz, 44200
 * Nantes. Fiche produite par le pipeline (architecte + psy + pénal + bot
 * préjudice) à partir des pièces authentiques (mise en demeure du 06/06/2026 +
 * réponse MORINEAU du 09/06/2026 ; proposition amiable conjointe du 11/05/2026).
 *
 * BOUSSOLE : 1) URGENCE — faire cesser l'infestation : traitement RELANCÉ
 * (Sapiens), aujourd'hui la locataire est sans blattes, MAIS le « Protocole
 * Curatif de Choc » préconisé par le propre prestataire de NMH (apts 83, 85,
 * 93) n'a jamais été intégralement appliqué → à verrouiller ; 2) RÉPARATION —
 * ouverte à l'amiable (proposition conjointe du 11/05/2026), chiffrage ANSES posé.
 * Lié au dossier juridique #5 (à confirmer).
 */
if (!defined('ABSPATH')) exit;

return [
    'slug'        => 'gourdien',
    'dossier_id'  => 5,
    'maj'         => '2026-07-03',
    'confidentiel'=> true,

    /* --- État civil & logement --- */
    'civilite'    => 'Mme',
    'prenom'      => 'Gwenaëlle',
    'nom'         => 'GOURDIEN',
    'adresse'     => '14 rue de Saint-Jean-de-Luz, 44200 Nantes',
    'etage'       => '',
    'appartement' => '93',
    'anciennete'  => 'Locataire depuis avril 2022 (≈ 4 ans). Infestation de blattes depuis l\'entrée dans les lieux — signalée à de multiples reprises par téléphone, sans solution durable.',
    'bailleur'        => 'Nantes Métropole Habitat — Pôle Grand Sud',
    'bailleur_contact'=> 'M. Yvonnic MORINEAU — Responsable Patrimoine — yvonnic.morineau@nmh.fr — 02 40 67 07 37',
    'medical'     => 'À recueillir si effets sanitaires.',
    'rdv'         => 'Co-signataire de la proposition amiable conjointe du 11/05/2026 (avec M. Doucet, apt 88).',

    /* --- Objectif --- */
    'objectif_locataire' => "D'ABORD : traitement COMPLET et durable des blattes (protocole préconisé par le prestataire de NMH lui-même). ENSUITE : indemnisation des ~4 années subies.",
    'objectifs_ga' => [
        "Verrouiller l'application intégrale du « Protocole Curatif de Choc » (apts 83, 85, 93).",
        "Faire aboutir la réparation à l'amiable (proposition conjointe du 11/05/2026), puis judiciaire si blocage.",
    ],

    /* --- Les volets (la boussole) --- */
    'volets' => [
        ['nom' => 'Urgence — faire cesser l\'infestation', 'statut' => '✅ RELANCÉE (à verrouiller)',
         'detail' => "Après la mise en demeure du 06/06/2026, NMH a relancé le prestataire (Sapiens) pour un nouveau RDV (réponse du 09/06/2026) ; la locataire est aujourd'hui sans blattes. MAIS le compte rendu du PROPRE technicien de NMH préconise un « Protocole Curatif de Choc » (deux pulvérisations dans les logements 83, 85 ET 93), à ce jour NON intégralement appliqué (interventions ponctuelles gels/appâts inefficaces). À verrouiller : application intégrale du protocole préconisé + logements mitoyens."],
        ['nom' => 'Réparation — indemnisation', 'statut' => '⏳ OUVERTE à l\'amiable',
         'detail' => "Le préjudice est posé dans la proposition conjointe du 11/05/2026 : trouble de jouissance sur ~4 ans (blattes), chiffrage ANSES. À défaut d'accord : judiciaire (avec M. Doucet ; l'AJ totale de M. Doucet arme le volet conjoint)."],
    ],

    /* --- Le désordre + le manquement central --- */
    'desordres' => [
        ['nom' => 'Infestation de blattes (apt 93) depuis avril 2022 (≈ 4 ans)',
         'nmh' => 'Interventions ponctuelles (gels/appâts) inefficaces ; relance de Sapiens annoncée le 09/06/2026.',
         'obs' => 'MANQUEMENT CENTRAL : NMH ignore les préconisations écrites de son propre prestataire — compte rendu « Préconisations Prioritaires (NMH) » recommandant un « Protocole Curatif de Choc » (2 pulvérisations dans les apts 83, 85 et 93). La locataire en détient une capture d\'écran (pièce n° 6) et en réclame l\'original. Non-respect de l\'obligation de délivrer un logement décent et exempt de nuisibles (art. 6 loi 1989 ; décret 2002-120 ; L.1331-22 CSP).'],
    ],

    /* --- Chiffrage du préjudice (méthode ANSES) --- */
    'prejudice' => [
        'note' => "Méthode ANSES (rapport n° 2021-SA-0147) : V = δ × (β/365) × VAV, VAV = 156 540 €. Dommage continu (avril 2022 → mai 2026). BLATTES → PAS de mise en sacs / lavage 60-90 °C (réservé aux punaises) : ici protection + remplacement d'éventuels effets sur justificatifs.",
        'postes' => [
            'ANSES — Mme Gourdien (blattes, 1 490 j, δ 0,05-0,10) : 31 947 à 63 894 €.',
            'Trouble de jouissance / préjudice moral compris dans le calcul ANSES.',
            'Remplacement / protection d\'effets éventuellement contaminés — sur justificatifs.',
        ],
        'fourchette_amiable' => 'Part Gourdien dans la proposition conjointe : fourchette basse ANSES ≈ 31 947 € (jusqu\'à 63 894 € au fond).',
    ],

    /* --- Chronologie --- */
    'timeline' => [
        ['date' => '2022-04', 'fait' => 'Entrée dans les lieux — infestation de blattes dès l\'origine'],
        ['date' => '2025-2026', 'fait' => 'Signalements téléphoniques répétés ; interventions ponctuelles (gels/appâts) inefficaces'],
        ['date' => '2026-05-11', 'fait' => 'Co-signataire de la proposition amiable transactionnelle conjointe (avec M. Doucet)'],
        ['date' => '2026-06-06', 'fait' => 'Mise en demeure individuelle envoyée à NMH (protocole complet + apparts voisins + rapports)'],
        ['date' => '2026-06-09', 'fait' => '✅ Réponse de NMH : relance du prestataire (Sapiens) — victoire partielle, à verrouiller'],
    ],
    'prochaines_etapes' => [
        ['etape' => 'VERROUILLER l\'urgence par écrit', 'echeance' => 'cette semaine', 'detail' => 'Exiger l\'application INTÉGRALE du « Protocole Curatif de Choc » préconisé par le prestataire de NMH (apts 83, 85, 93), le calendrier des passages, et la communication du compte rendu original + des rapports.'],
        ['etape' => 'RÉPARATION à l\'amiable', 'echeance' => 'suivi de la proposition du 11/05', 'detail' => 'Chiffrage ANSES posé (≈ 31 947 €). Escalade au-dessus de M. Morineau (DG / Président du CA) si nécessaire.'],
        ['etape' => 'Adhésion + mandat écrit', 'echeance' => 'avant tout courrier en son nom', 'detail' => 'Condition pour agir au nom de Mme Gourdien.'],
        ['etape' => 'À défaut : SCHS puis judiciaire', 'echeance' => 'si blocage', 'detail' => 'Volet conjoint avec M. Doucet (AJ totale). Avocat = Fabrice + avocat désigné, jamais les membres.'],
    ],

    'pieces' => [
        'Mise en demeure du 06/06/2026 (envoyée) + réponse NMH du 09/06/2026 (reçue) — verbatim',
        'Proposition amiable transactionnelle conjointe du 11/05/2026 (co-signée)',
        'Capture d\'écran du compte rendu « Préconisations Prioritaires (NMH) » — Protocole Curatif de Choc (pièce n° 6) — original à obtenir',
        'Historique des signalements et interventions (gels/appâts)',
    ],
];
