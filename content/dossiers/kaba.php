<?php
/**
 * DOSSIER LOCATAIRE — KABA (apt 87) — fiche maître (code)
 *
 * Occupant de l'appartement n° 87, 14 rue Saint-Jean-de-Luz, 44200 Nantes
 * (désigné « M. KABA Kadiatou » par le PV du Service Intercommunal d'Hygiène du
 * 20/08/2025 — civilité à confirmer). Punaises de lit.
 *
 * Rôle DOUBLE dans l'affaire : (a) locataire à aider (punaises confirmées) ;
 * (b) élément CENTRAL du dossier de M. Doucet (apt 88) — l'apt 87 mitoyen est
 * la source de propagation, et NMH prétend l'avoir traité en 2020, ce que les
 * occupants contestent formellement.
 * Lié au dossier juridique réel via 'dossier_id' — à confirmer.
 */
if (!defined('ABSPATH')) exit;

return [
    'slug'        => 'kaba',
    'dossier_id'  => 0, // à relier
    'maj'         => '2026-07-03',
    'confidentiel'=> true,

    'civilite'    => '',
    'prenom'      => 'Kadiatou',
    'nom'         => 'KABA',
    'adresse'     => '14 rue Saint-Jean-de-Luz, 44200 Nantes',
    'etage'       => '',
    'appartement' => '87',
    'anciennete'  => 'Occupant·e de longue date (voisin·e de M. Doucet depuis 17 ans). Civilité à confirmer (le PV du SCHS désigne « M. KABA Kadiatou »).',
    'bailleur'        => 'Nantes Métropole Habitat',
    'bailleur_contact'=> 'NMH (interlocuteur à préciser).',
    'medical'     => 'À recueillir.',
    'rdv'         => '',

    'objectif_locataire' => "Obtenir un traitement EFFECTIF des punaises de lit (l'apt 87 est confirmé infesté par le SCHS).",
    'objectifs_ga' => [
        "Faire traiter réellement l'apt 87 (source de propagation vers l'apt 88).",
        "Recueillir, si l'occupant·e y consent, un témoignage écrit (art. 202 CPC) sur l'absence de traitement en 2020.",
    ],

    'volets' => [
        ['nom' => 'Urgence — traitement effectif', 'statut' => '⏳ À OBTENIR',
         'detail' => "Le PV du Service Intercommunal d'Hygiène du 20/08/2025 (constat du 31/07/2025, réf. JL.FM.20082025) certifie la présence ACTUELLE de punaises de lit dans l'apt 87 — ce qui démontre l'inefficacité (ou l'inexistence) de tout traitement passé. Traitement conforme à obtenir."],
        ['nom' => 'Le point litigieux — le « traitement 2020 »', 'statut' => '⚠️ CONTESTÉ',
         'detail' => "Lors de l'appel du 11/05/2026, l'agent NMH a affirmé qu'un bon de commande de traitement de l'apt 87 aurait été émis ET exécuté en 2020 (« pour elle, ça a été fait »). Or les occupant·es affirment de façon constante depuis 2020 n'avoir JAMAIS bénéficié d'aucun traitement. Le PV du SCHS (punaises actuelles) corrobore l'inefficacité/l'inexistence. Si NMH maintient une affirmation contredite par les pièces → risque art. 441-7 C. pén. (à l'appréciation du juge)."],
    ],

    'desordres' => [
        ['nom' => 'Punaises de lit (apt 87)',
         'nmh' => 'Prétend un traitement en 2020 (contesté) ; aucun traitement effectif documenté depuis.',
         'obs' => 'Source de propagation vers l\'apt 88 (M. Doucet). Présence actuelle certifiée par le SCHS (PV du 20/08/2025).'],
    ],

    'timeline' => [
        ['date' => '2020', 'fait' => 'Traitement de l\'apt 87 allégué par NMH — contesté par les occupant·es (jamais réalisé selon eux)'],
        ['date' => '2025-08-20', 'fait' => 'PV du Service Intercommunal d\'Hygiène (constat 31/07/2025) : punaises confirmées apt 87 (réf. JL.FM.20082025)'],
        ['date' => '2026-05-11', 'fait' => 'Appel NMH : l\'agent réaffirme le « traitement 2020 » de l\'apt 87 (à démentir par pièces/témoignage)'],
    ],
    'prochaines_etapes' => [
        ['etape' => 'Obtenir un traitement effectif de l\'apt 87', 'echeance' => 'court terme', 'detail' => 'S\'appuyer sur le PV du SCHS (présence actuelle certifiée). Interlocuteur unique, tout par écrit.'],
        ['etape' => 'Recueillir le témoignage (art. 202 CPC) — si consentement', 'echeance' => 'selon accord de l\'occupant·e', 'detail' => 'Sur l\'absence de traitement en 2020. Utile au dossier de M. Doucet (propagation) et au point pénal 441-7.'],
        ['etape' => 'Exiger de NMH les pièces du « traitement 2020 »', 'echeance' => 'demandé (48 h dans la proposition du 11/05)', 'detail' => 'Bon de commande, bon d\'intervention, prestataire, facture, compte rendu. Leur absence confirmerait l\'inexistence du traitement.'],
    ],

    'pieces' => [
        'PV du Service Intercommunal d\'Hygiène du 20/08/2025 (apt 87) — réf. JL.FM.20082025',
        'Témoignage écrit des occupant·es (art. 202 CPC) — à recueillir si consentement',
        'Demande de pièces « traitement 2020 » (incluse dans la proposition du 11/05/2026)',
    ],
];
