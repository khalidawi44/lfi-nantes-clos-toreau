<?php
/**
 * DOSSIER LOCATAIRE — Fabrice DOUCET  (fiche maître, gérée par le code)
 *
 * Le dossier de Fabrice Doucet lui-même (locataire à Clos Toreau) — cas RÉEL
 * de punaises de lit. C'est aussi le « dossier-pilote » qui a permis de tester
 * la méthode (dite « méthode DOUCET » du chiffrage préjudice punaises).
 *
 * Lié au dossier juridique réel via 'dossier_id' — à confirmer (mettre l'ID
 * réel). La fiche reste accessible par son slug « doucet » dans tous les cas.
 *
 * BOUSSOLE : 1) URGENCE — faire cesser l'infestation (traitement Sapiens
 * obtenu, SANS frais cette fois) = GAGNÉE ; 2) RÉPARATION — récupérer les
 * sommes indûment facturées les fois précédentes + préjudice = EN COURS, à
 * l'amiable d'abord. Le dossier N'EST PAS clos : les réussites (urgence) et la
 * réparation sont des volets distincts.
 */
if (!defined('ABSPATH')) exit;

return [
    'slug'        => 'doucet',
    'dossier_id'  => 0, // à relier au dossier juridique réel (mettre l'ID)
    'maj'         => '2026-07-03',
    'confidentiel'=> true,

    /* --- État civil & logement --- */
    'civilite'    => 'M.',
    'prenom'      => 'Fabrice',
    'nom'         => 'DOUCET',
    'adresse'     => 'Clos Toreau, 44200 Nantes (adresse exacte à compléter)',
    'etage'       => '',
    'appartement' => '',
    'anciennete'  => 'Locataire à Clos Toreau — à préciser',
    'bailleur'        => 'Nantes Métropole Habitat',
    'bailleur_contact'=> 'Nantes Métropole Habitat (interlocuteur à préciser)',
    'medical'     => 'À recueillir si effets sanitaires (piqûres, prurit, troubles du sommeil — documentés par le HCSP pour les punaises de lit).',
    'rdv'         => '',

    /* --- Objectif --- */
    'objectif_locataire' => "URGENCE : traitement des punaises de lit — OBTENU (Sapiens), sans frais cette fois. RÉPARATION : récupérer les sommes indûment facturées les fois précédentes + réparation du préjudice.",
    'objectifs_ga' => [
        "Documenter la double victoire (traitement obtenu + abandon de la refacturation illégale des produits).",
        "Constituer le dossier juridique complet (pièces horodatées) pour la réparation — à suivre, sans clore le dossier.",
    ],

    /* --- Les deux volets (la boussole) --- */
    'volets' => [
        ['nom' => 'Urgence — traitement des punaises de lit', 'statut' => '✅ GAGNÉE',
         'detail' => 'Après un appel à NMH juste avant le rendez-vous, l\'entreprise Sapiens est intervenue et a traité l\'appartement dans l\'urgence. Point décisif : contrairement aux DEUX fois précédentes, aucun paiement des produits n\'a été exigé cette fois. Les arguments juridiques ont fait céder NMH sur sa pratique interne.'],
        ['nom' => 'Réparation — récupérer l\'indu + préjudice', 'statut' => '⏳ EN COURS (ne pas clore)',
         'detail' => 'Les fois précédentes, le coût des produits de traitement a été refacturé et intégré aux CHARGES — ce qui est illégal (la désinsectisation relève du bailleur ; la liste des charges récupérables du décret n° 87-713 est limitative et ne couvre pas ce poste). Montant exact à récupérer : à confirmer depuis la quittance / l\'email. Dossier juridique en constitution (pièces horodatées).'],
    ],

    /* --- Désordre principal --- */
    'desordres' => [
        ['nom' => 'Infestation de punaises de lit',
         'nmh' => 'A d\'abord refacturé les produits de traitement au locataire (fois précédentes) ; a cédé cette fois : traitement d\'urgence par Sapiens SANS frais.',
         'obs' => 'La refacturation des produits de désinsectisation dans les charges est contraire au décret n° 87-713 (charges récupérables limitatives) et à l\'obligation de délivrance d\'un logement décent, exempt de nuisibles (art. 6 loi 1989 ; décret 2002-120).'],
    ],

    /* --- La double victoire (à distinguer) --- */
    'victoire' => [
        'titre' => 'Double victoire (volet URGENCE)',
        'points' => [
            "1) Traitement obtenu dans l'urgence : Sapiens est venu traiter l'appartement (punaises de lit) après relance directe de NMH.",
            "2) Refacturation illégale abandonnée : pour la première fois, NMH n'a PAS exigé le paiement des produits — reconnaissance de fait que sa procédure interne ne tenait pas face aux arguments juridiques (règlement interne opposé à la loi et à la jurisprudence).",
        ],
        'portee' => "Cette victoire montre le système : une pratique interne (refacturer aux locataires des frais qui incombent au bailleur) cède dès qu'on lui oppose le cadre légal. C'est réplicable pour les autres locataires.",
    ],

    /* --- Suivi (fait) / à faire --- */
    'timeline' => [
        ['date' => '', 'fait' => 'Fois précédentes : traitements punaises AVEC refacturation des produits au locataire (intégrée aux charges) — montant à récupérer'],
        ['date' => '', 'fait' => 'Appel à NMH juste avant le rendez-vous + rappel du cadre légal'],
        ['date' => '', 'fait' => '✅ Intervention Sapiens : traitement d\'urgence des punaises de lit — SANS frais cette fois'],
        ['date' => '2026-07-03', 'fait' => 'Ouverture / consolidation du dossier juridique (pièces horodatées) pour la réparation'],
    ],
    'prochaines_etapes' => [
        'VOLET URGENCE : classer comme RÉUSSITE (traitement obtenu, sans frais) — sans clore le dossier global.',
        'VOLET RÉPARATION (à l\'amiable d\'abord) : réclamer le REMBOURSEMENT des sommes indûment facturées les fois précédentes (produits de désinsectisation intégrés aux charges) — montant exact à établir depuis la quittance / l\'email.',
        'Réunir les pièces horodatées : quittances/charges faisant apparaître la refacturation, historique des interventions, échanges NMH, dates.',
        'Chiffrer le préjudice punaises (méthode DOUCET : trouble de jouissance + effets + traitement des effets — mise en sacs / lavage 60-90 °C spécifiques aux punaises).',
        'Si refus amiable : SCHS puis judiciaire (avocat = Fabrice, lui seul).',
    ],

    /* --- Chiffrage (bot — à parfaire avec les pièces) --- */
    'prejudice' => [
        'note' => "Punaises de lit → méthode DOUCET complète (contrairement aux blattes) : mise en sacs, lavage 60-90 °C, traitement/éviction des effets. Montants à confirmer avec le loyer réel et les justificatifs.",
        'postes' => [
            "Remboursement des produits de traitement indûment facturés (fois précédentes) — MONTANT EXACT À CONFIRMER depuis la quittance / l'email (poste central, base : décret n° 87-713).",
            "Trouble de jouissance sur la période d'infestation — % du loyer × durée (à préciser).",
            "Traitement des effets (mise en sacs, lavage 60-90 °C, éventuels remplacements) — sur justificatifs.",
        ],
        'fourchette_amiable' => 'à établir une fois le montant refacturé et le loyer confirmés — le remboursement de l\'indu est acquis en droit.',
    ],

    'pieces' => [
        'Quittances / décomptes de charges faisant apparaître la refacturation des produits (fois précédentes) — À VERSER',
        'Historique des interventions punaises (dates, entreprise Sapiens)',
        'Échanges avec NMH (emails) — à relier depuis la boîte',
        'Photos horodatées des punaises / piqûres',
    ],
];
