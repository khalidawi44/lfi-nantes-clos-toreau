<?php
/**
 * DOSSIER LOCATAIRE — M. Fabrice DOUCET (apt 88)  — fiche maître (code)
 *
 * Cas RÉEL de punaises de lit, 14 rue Saint-Jean-de-Luz, 44200 Nantes.
 * Fiche produite par le pipeline (architecte + psychologue + pénal + bot
 * préjudice) à partir des pièces authentiques versées (proposition amiable
 * conjointe du 11/05/2026, décision d'AJ totale du 19/05/2026, etc.).
 *
 * BOUSSOLE : 1) URGENCE — faire cesser l'infestation : traitement OBTENU (NMH
 * est intervenu peu après l'appel du 11/05/2026), mais PARTIEL du fait de NMH
 * (pas de relogement pendant les opérations, mise en sacs supportée seul,
 * protocole/relogement demandés non accordés) ; 2) RÉPARATION — à l'amiable
 * d'abord (proposition transactionnelle en cours), puis judiciaire (AJ TOTALE
 * accordée → avocat désigné). Le dossier N'EST PAS clos.
 *
 * RÔLES : la STRATÉGIE de plaidoirie appartient à l'AVOCAT. Cette fiche
 * l'ORIENTE (faits, pistes, pièces classées) pour qu'il monte le dossier.
 * Lié au dossier juridique réel via 'dossier_id' — à confirmer.
 */
if (!defined('ABSPATH')) exit;

return [
    'slug'        => 'doucet',
    'dossier_id'  => 0, // à relier au dossier juridique réel
    'maj'         => '2026-07-03',
    'confidentiel'=> true,

    /* --- État civil & logement --- */
    'civilite'    => 'M.',
    'prenom'      => 'Fabrice Jean',
    'nom'         => 'DOUCET',
    'adresse'     => '14 rue Saint-Jean-de-Luz, 44200 Nantes',
    'etage'       => '',
    'appartement' => '88',
    'anciennete'  => 'Locataire de longue date. Représentant légal de ses fils mineurs Souleyman (14 ans) et Ibrahim DOUCET-GUILLON (né le 16/05/2011 ; handicap reconnu MDPH ; endobrachyœsophage / Barrett précancéreux).',
    'bailleur'        => 'Nantes Métropole Habitat (OPH)',
    'bailleur_contact'=> 'Interlocuteur historique : M. Yvonnic MORINEAU (Responsable Patrimoine, Pôle Grand Sud). ⚠️ Compromis (courriels diffamatoires 6/12/13 mai 2025) → escalade AU-DESSUS de lui (voir « escalade »).',
    'medical'     => 'Ibrahim DOUCET-GUILLON : RGO sévère depuis la naissance (suivi CHU Nantes depuis 12/01/2012) évolué en endobrachyœsophage (Barrett) précancéreux, 2 cm (2021) → 6 cm (2024). Suivi par le Dr Dominique CALDARI, gastropédiatre (Hôpital Mère-Enfant, Nantes). Lettre d\'accompagnement du Dr CALDARI versée ; certificat détaillé attendu (RDV CHU 02/06/2026). Souleyman : troubles du sommeil persistants depuis la piqûre du 10/05/2026.',
    'rdv'         => 'AJ TOTALE accordée (BAJ Nantes, 19/05/2026, demande N-44109-2026-003859). Avocate désignée : Me Julie SUPIOT (Barreau de Nantes, 06 67 93 26 18 — SELARL LEBLANC SAGNIEZ LEROUX). AJ complétive pour auxiliaires (SUPIOT / huissier LEBLANC).',

    /* --- Objectif --- */
    'objectif_locataire' => "URGENCE : en finir avec les punaises de lit (traitement OBTENU, l'occupant est aujourd'hui sans punaises — mais traitement rendu PARTIEL par NMH). RÉPARATION : obtenir l'indemnisation des préjudices (proposition amiable ; puis judiciaire, AJ totale acquise).",
    'objectifs_ga' => [
        "Documenter que le traitement est resté PARTIEL du fait de NMH (pas de relogement, mise en sacs supportée seul).",
        "Faire aboutir la réparation : d'abord amiable (proposition du 11/05/2026), puis judiciaire avec l'avocat AJ.",
        "Tenir un dossier de pièces classé et prêt à remettre à l'avocat.",
    ],

    /* --- Les volets (la boussole) --- */
    'volets' => [
        ['nom' => 'Urgence — traitement des punaises de lit', 'statut' => '✅ OBTENU (mais PARTIEL du fait de NMH)',
         'detail' => "Après l'appel à NMH du 11/05/2026 (dossier 2026-33305), NMH est intervenu peu après (à confirmer : ~1 semaine, en 3 passages). L'occupant est aujourd'hui sans punaises. MAIS : (a) aucun relogement pendant les opérations, pourtant demandé ; (b) M. Doucet a dû assurer SEUL toute la logistique (mise en sacs des effets, regroupement des meubles) — ce qui n'était pas à sa charge et faisait partie de sa demande, jamais accordée ; (c) 2e passage manqué faute d'avoir pu être présent dans ces conditions. Le traitement est donc resté PARTIEL par la faute de NMH."],
        ['nom' => 'Réparation — indemnisation', 'statut' => '⏳ EN COURS (amiable → judiciaire, avocate désignée)',
         'detail' => "Amiable : proposition transactionnelle conjointe (avec Mme Gourdien) du 11/05/2026, délai 8 jours ; chiffrage DOUCET seul 151 388 € (amiable), 400 000–690 000 € au fond. Judiciaire : référé-expertise (art. 145 CPC) porté par Me Julie SUPIOT (avocate désignée, AJ totale) — assignation préparée. La STRATÉGIE de plaidoirie appartient à Me SUPIOT ; cette fiche l'oriente."],
        ['nom' => 'Pénal — réservé', 'statut' => '⚖️ RÉSERVÉ (Fabrice + avocat)',
         'detail' => "Diffamation (courriels MORINEAU 6/12/13 mai 2025) ; L.1337-4 CSP (mises en demeure de la Mairie des 12/06 et 08/08/2025 non exécutées) ; art. 441-7 C. pén. (traitement apt 87 allégué). Plainte / partie civile réservée. Judiciaire = Fabrice + avocat, jamais les membres."],
    ],

    /* --- Le désordre + refacturation illégale --- */
    'desordres' => [
        ['nom' => 'Punaises de lit (apt 88) — 2020, puis réapparition depuis nov. 2024',
         'nmh' => 'Interventions 2020 (03 + 17/09/2020) avec participation financière de 80 € imposée au locataire ; SAPIAN 06 + 14/10/2025 ; facturation de 118,51 € le 14/12/2025 (« indemnité réparations locatives ») ; intervention 2026 après l\'appel du 11/05.',
         'obs' => 'La refacturation de produits/frais de désinsectisation au locataire est contraire à la liste LIMITATIVE des charges récupérables (décret n° 87-713) et à l\'obligation de délivrer un logement décent et exempt de nuisibles (art. 6 loi 1989 ; décret 2002-120). → 118,51 € à ANNULER + 80 € à REMBOURSER.'],
    ],

    /* --- Chiffrage du préjudice (méthode ANSES — déjà posé dans la proposition) --- */
    'prejudice' => [
        'note' => "Méthode ANSES (rapport n° 2021-SA-0147, juillet 2023) : V = δ × (β/365) × VAV, VAV = 156 540 € (IPC INSEE juillet 2025). Dommage continu : la prescription ne court qu'à compter de la cessation (Cass. 2e civ., 12/05/2011, n° 10-17.683). Punaises → méthode DOUCET complète (mise en sacs, lavage 60-90 °C, remplacement literie).",
        'postes' => [
            'ANSES — M. Doucet (727 j, δ 0,10-0,20) : 31 180 à 62 359 €.',
            'ANSES — Souleyman (727 j, δ 0,10-0,20) : 31 180 à 62 359 €.',
            'ANSES — Ibrahim (727 j, δ 0,15-0,25 ; Barrett + MDPH) : 46 770 à 77 949 €.',
            'Literie mise au rebut : 457 € (lit Doucet 25/09/2023) + 239,97 € (lit Ibrahim 20/03/2025) + lit/matelas Souleyman à parfaire = min. 696,97 €.',
            'Temps perdu (~200 h / 18 mois, SMIC horaire chargé) : 5 000 €.',
            'Jouissance apt 88 (art. 1719 C. civ.) : 4 838 € (30 % × 6 mois 2020 ; 100 % × 1 mois mai 2025 ; 40 % × 17 mois).',
            'Annulation facturation 118,51 € (14/12/2025) + remboursement 80 € (2020).',
            'Ibrahim — aggravation Barrett (2→6 cm) : à parfaire selon expertise médicale.',
        ],
        'fourchette_amiable' => 'DOUCET seul — amiable : 151 388 € ; au fond : 400 000 à 690 000 € (méthode ANSES, fourchette haute, position du dossier). La proposition amiable CONJOINTE Doucet + Gourdien du 11/05/2026 était de 141 077 € (jusqu\'à 266 561 € au fond, tous bénéficiaires). Remboursements/annulations (118,51 € + 80 € + literie 696,97 €) acquis en droit.',
    ],

    /* --- Chronologie (bordereau) --- */
    'timeline' => [
        ['date' => '2020-09-03', 'fait' => '1re infestation de l\'étage — intervention désinsectisation apt 88 (+ 17/09/2020) ; 80 € de produits payés par M. Doucet'],
        ['date' => '2024-11', 'fait' => 'Réapparition des punaises de lit dans l\'apt 88'],
        ['date' => '2025-02-28', 'fait' => 'Mise en demeure (dont demande de relogement temporaire) — restée sans réponse'],
        ['date' => '2025-05', 'fait' => 'Hébergement contraint ~1 mois (famille), logement invivable — relogement NMH jamais accordé'],
        ['date' => '2025-05-06', 'fait' => 'Courriels diffamatoires de M. MORINEAU (6, 12 et 13 mai 2025) à l\'encontre de M. Doucet'],
        ['date' => '2025-06-12', 'fait' => 'Mises en demeure de la Mairie de Nantes (12/06 et 08/08/2025) — non exécutées par NMH'],
        ['date' => '2025-07-17', 'fait' => 'Qualification entomologique (F. Meurgey, Muséum de Nantes)'],
        ['date' => '2025-08-20', 'fait' => 'PV du Service Intercommunal d\'Hygiène (constat 31/07/2025) — punaises confirmées apt 87 (réf. JL.FM.20082025)'],
        ['date' => '2025-10-06', 'fait' => 'Interventions SAPIAN apt 88 (06 + 14/10/2025)'],
        ['date' => '2025-12-14', 'fait' => 'Facturation 118,51 € « indemnité réparations locatives » (à annuler)'],
        ['date' => '2026-05-10', 'fait' => 'Piqûres de Souleyman (punaise vivante filmée, horodatage Apple 18h53) — nouvelle preuve d\'infestation active'],
        ['date' => '2026-05-11', 'fait' => 'Appel à NMH (dossier 2026-33305, 16h07, 17 min) + envoi de la proposition amiable transactionnelle conjointe'],
        ['date' => '2026-05-19', 'fait' => '✅ AJ TOTALE accordée (BAJ Nantes) — demande N-44109-2026-003859'],
        ['date' => '2026-05', 'fait' => '✅ Intervention NMH après l\'appel (3 passages) — traitement obtenu mais PARTIEL (date exacte à confirmer)'],
    ],
    'prochaines_etapes' => [
        ['etape' => 'VERROUILLER l\'urgence', 'echeance' => 'court terme', 'detail' => 'Acter par écrit que le traitement a eu lieu MAIS partiellement du fait de NMH (pas de relogement, mise en sacs supportée seul, 2e passage manqué). Exiger un dernier passage complet + le protocole conforme (NF EN 16636, opérateur Certibiocide).'],
        ['etape' => 'RÉPARATION — amiable d\'abord', 'echeance' => 'suivi du délai de 8 j', 'detail' => 'Suivre la réponse à la proposition du 11/05/2026. Escalade au-dessus de M. Morineau (voir « escalade »).'],
        ['etape' => 'JUDICIAIRE — activer l\'AJ', 'echeance' => 'si échec amiable', 'detail' => 'AJ TOTALE acquise : prendre contact avec l\'avocat désigné (ou le bâtonnier). Lui REMETTRE le dossier de pièces classé + cette fiche. La stratégie de plaidoirie lui appartient ; on l\'oriente. Attention : caducité de l\'AJ si la juridiction n\'est pas saisie dans l\'année (art. 59 décret 2020-1717).'],
        ['etape' => 'Sécuriser les preuves', 'echeance' => 'permanent', 'detail' => 'Constat d\'huissier (état des logements + piqûres), expertise médicale (Barrett), vidéo horodatée conservée (iCloud).'],
    ],

    /* --- Architecte : à QUI adresser la réclamation --- */
    'escalade' => [
        'principe' => "La réclamation d'un tel montant NE se traite PAS avec M. Morineau (interlocuteur compromis : diffamation, conflit). On monte au-dessus, par écrit, interlocuteur unique.",
        'cibles' => [
            'Direction Générale de NMH — M. Marc PATAY (Directeur Général) : décideur sur une transaction de ce niveau.',
            'Président du Conseil d\'Administration de NMH — M. Thomas QUÉRO (également adjoint à l\'urbanisme, Ville de Nantes) : gouvernance / arbitrage.',
            'Nantes Métropole (tutelle de l\'OPH) : autorité de rattachement.',
            'En parallèle : Défenseur des droits (droits de l\'enfant, 2 mineurs dont 1 MDPH), ANCOLS (manquement d\'un OPH), CAF 44 (APL pour un logement non décent).',
        ],
        'note' => "Le référé et le fond passent par l'AVOCAT (AJ totale). L'amiable haut niveau (DG + Président CA) peut se faire en parallèle, sans jamais rien lâcher sur le préjudice.",
    ],

    /* --- Pièces (bordereau, à remettre à l'avocat) --- */
    'pieces' => [
        'Décision d\'AJ TOTALE du 19/05/2026 (BAJ Nantes) — N-44109-2026-003859',
        'Proposition amiable transactionnelle conjointe du 11/05/2026 (Doucet apt 88 + Gourdien apt 93)',
        'PV Service Intercommunal d\'Hygiène du 20/08/2025 (apt 87 et apt 88)',
        'Courriel entomologiste F. Meurgey (Muséum Nantes) du 17/07/2025',
        'Vidéo horodatée « Punaise_souley_10-05-26.mov » (iCloud, 18h53) + photos des piqûres (11/05/2026)',
        'Fil d\'emails NMH (fév. 2025 – mai 2026), dont courriels diffamatoires MORINEAU des 6/12/13 mai 2025',
        'Facture 118,51 € du 14/12/2025 + justificatif des 80 € (2020) + factures literie (457 € ; 239,97 €)',
        'Mises en demeure de la Mairie de Nantes des 12/06 et 08/08/2025',
        'Lettre d\'accompagnement du Dr CALDARI (Barrett d\'Ibrahim) + carnet de santé CHU',
        'Lettre au collège René Bernier (Souleyman) — pièce n° 7',
        /* --- Dossier juridique constitué (archive « DOUCET c/ NMH ») --- */
        'Synthèse stratégique pour Me SUPIOT (12/06/2026)',
        'Assignation en référé (v21) + Référé-expertise (art. 145 CPC) + Plan de bataille',
        'Dossier de pièces consolidé (bordereau) + récapitulatif chronologique Doucet + Gourdien',
        'Note de chiffrage ANSES + Méthode de chiffrage préjudice punaises (grille reproductible)',
        'Décision AJ complétive (auxiliaires SUPIOT / LEBLANC)',
        'Dossier MDPH Ibrahim (double usage) + notes d\'observation pour le Dr CALDARI',
        'Foyer KABA (apt 87) : présentation, attestation-type (art. 202 CPC), kit amiable',
        'Fiche appel ADIL 44 · Note multilingue réunion 26/06/2026',
    ],
];
