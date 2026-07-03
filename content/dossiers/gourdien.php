<?php
/**
 * DOSSIER LOCATAIRE — Mme GOURDIEN  (fiche maître, gérée par le code)
 *
 * Fichier de référence du dossier de Mme Gwenaëlle Gourdien. Mise à jour : on
 * édite ce fichier et on pousse sur GitHub → la fiche « dossier-synthese?slug=
 * gourdien » et le dossier juridique lié s'actualisent automatiquement.
 *
 * Lié au dossier juridique #5 (base WordPress) via 'dossier_id' — à confirmer
 * si l'ID réel diffère (la fiche reste accessible par son slug dans tous les cas).
 *
 * MÉCANIQUE : cette fiche est le produit du pipeline (architecte + psychologue
 * + pénal + bot préjudice), pas d'une saisie manuelle. La BOUSSOLE est
 * explicite : 1) l'URGENCE (faire cesser l'infestation) — en voie de
 * résolution ; 2) ENSUITE le PRÉJUDICE, à l'AMIABLE d'abord (gardé en réserve,
 * jamais proposé à ce jour, pour ne pas braquer le bailleur).
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
    'anciennete'  => 'Locataire depuis avril 2022 (≈ 4 ans) — infestation de blattes depuis l\'entrée dans les lieux',
    'bailleur'        => 'Nantes Métropole Habitat — Pôle Grand Sud',
    'bailleur_contact'=> 'M. Yvonnic MORINEAU — Responsable Patrimoine — yvonnic.morineau@nmh.fr — Service Relation Client : 02 40 67 07 37',
    'medical'     => 'À recueillir si effets sanitaires (le prurit / stress lié à une infestation lourde est documenté — cf. HCSP).',
    'rdv'         => '',

    /* --- Objectif (ce que vise le dossier) --- */
    'objectif_locataire' => "D'ABORD : en finir avec l'infestation de blattes (traitement COMPLET et durable). ENSUITE : être indemnisée pour les ~4 années subies.",
    'objectifs_ga' => [
        "Verrouiller par écrit le traitement complet (protocole adapté, calendrier, apparts voisins) — l'urgence.",
        "Ouvrir le volet PRÉJUDICE à l'amiable : indemnisation du trouble de jouissance sur ~4 ans + préjudice moral.",
    ],

    /* --- Les deux volets (la boussole) --- */
    'volets' => [
        ['nom' => 'Urgence — faire cesser l\'infestation', 'statut' => 'EN VOIE DE RÉSOLUTION',
         'detail' => 'NMH a relancé le prestataire (Sapiens) pour un nouveau rendez-vous à domicile (réponse du 09/06/2026). Reste à VERROUILLER : protocole complet (pas le préventif allégé), calendrier écrit, et traitement des apparts voisins (dont le 88, blattes constatées et photographiées le 19/05/2026) pour éviter la réinfestation.'],
        ['nom' => 'Préjudice — réparation', 'statut' => 'À OUVRIR (amiable) — gardé en réserve',
         'detail' => 'Jamais proposé au bailleur à ce jour (volontairement). On l\'ouvre APRÈS avoir sécurisé l\'urgence, à l\'amiable d\'abord : trouble de jouissance sur ~4  ans + préjudice moral. Judiciaire seulement en dernier recours (avocat = Fabrice).'],
    ],

    /* --- Désordre principal + position de NMH + notre observation --- */
    'desordres' => [
        ['nom' => 'Infestation de blattes (depuis avril 2022, ≈ 4 ans)',
         'nmh' => 'Reconnaît une intervention le 23/03/2026 (traitement préventif de contrôle) ayant CONSTATÉ la présence de blattes ; garantie 6 mois ; relance du prestataire en cours.',
         'obs' => 'Le traitement « allégé » est insuffisant : le propre technicien de NMH a recommandé par écrit un protocole COMPLET et étalé. Risque de réinfestation depuis les logements mitoyens (apt 88).'],
    ],

    /* --- La conversation avec M. MORINEAU (NMH) --- */
    'email_envoye' => [
        'objet' => 'Apt 93 — 14 rue Saint-Jean-de-Luz — Mme GOURDIEN — Mise en demeure de traitement complet de l\'infestation de blattes (4 ans, depuis avril 2022) — art. 1719 C. civ. et L.1331-22 CSP',
        'a'     => 'Nantes Métropole Habitat — M. Yvonnic Morineau (yvonnic.morineau@nmh.fr) · CC : nantessudclostoreau / fabrice.doucet44@gmail.com',
        'date'  => '2026-06-06 23:06',
        'corps' => <<<'MAIL'
Mise en demeure (résumé fidèle). Mme Gourdien rappelle l'infestation de blattes subie depuis son entrée en avril 2022, malgré signalements répétés.

I. SITUATION CONSTATÉE PAR LE TECHNICIEN DE NMH — infestation d'ampleur très importante, incompatible avec le traitement allégé mis en œuvre ; le technicien a lui-même recommandé un protocole COMPLET et étalé (recommandations écrites disponibles).

II. CADRE JURIDIQUE — art. 1719 C. civ. (jouissance paisible) ; art. 6 loi du 6 juillet 1989 (logement décent, exempt de nuisibles) ; décret n° 2002-120 art. 2 ; art. L.1331-22 CSP ; loi ELAN ; Cass. 3e civ. 8 juillet 2009 n° 08-12.116 (la persistance d'une infestation non traitée engage la responsabilité du bailleur).

III. DEMANDES (sous huitaine) : 1) une nouvelle intervention par une entreprise spécialisée pour un protocole COMPLET conforme aux recommandations du technicien ; 2) le détail du protocole + le calendrier des passages ; 3) l'inclusion des apparts voisins (dont le 88, blattes constatées et photographiées le 19/05/2026) ; 4) la communication des rapports d'intervention antérieurs et de la copie de la commande.

IV. À DÉFAUT — saisine du SCHS de Nantes (constat d'insalubrité), du Tribunal judiciaire en référé (astreinte + indemnisation des préjudices : jouissance, moral, sanitaire), du Défenseur des droits et de l'ANCOLS, et action conjointe avec d'autres locataires affectés.
MAIL,
        'note' => "Envoyé le 6 juin 2026 par Mme Gourdien (Clos Toreau en copie). Texte intégral versé au dossier.",
    ],
    'email_recu' => [
        'de'    => 'yvonnic.morineau@nmh.fr — Yvonnic MORINEAU, Responsable Patrimoine, Pôle Grand Sud (NMH)',
        'date'  => '2026-06-09 08:54',
        'corps' => <<<'MAIL'
Bonjour

Nous prenons acte de votre demande ci-dessous.

Pour rappel depuis votre entrée dans les lieux en 2022, vous nous avez sollicité une seule fois au 23/10/25 pour ce sujet qui a fait l'objet d'une commande de traitement au 11/11/25 à l'entreprise Sapian puis d'une annulation au 31/12/25 faute de retour de votre part pour convenir d'un rendez-vous.

L'entreprise a cependant pu intervenir au 23/03/26 dans le cadre d'un traitement préventif de contrôle et a constaté la présence de blatte et a appliqué le traitement en conséquence. Cette prestation étant garantie 6 mois nous relançons le prestataire afin qu'il conviennes d'un nouveau rendez-vous à votre domicile.

Cordialement
Yvonnic MORINEAU — Responsable Patrimoine — Pôle Grand Sud — Direction Maintenance Proximité
MAIL,
    ],

    /* --- Suivi (fait) / à faire --- */
    'timeline' => [
        ['date' => '2022-04', 'fait' => 'Entrée dans les lieux — infestation de blattes présente dès l\'origine'],
        ['date' => '2025-10-23', 'fait' => 'Signalement à NMH (selon NMH, une sollicitation ; l\'historique de la locataire en documente davantage)'],
        ['date' => '2025-11-11', 'fait' => 'NMH commande un traitement à l\'entreprise Sapiens'],
        ['date' => '2025-12-31', 'fait' => 'Annulation de la commande (NMH invoque un défaut de RDV côté locataire)'],
        ['date' => '2026-03-23', 'fait' => 'Intervention Sapiens : présence de blattes CONSTATÉE, traitement appliqué (garanti 6 mois) — le traitement allégé s\'avère insuffisant'],
        ['date' => '2026-05-19', 'fait' => 'Blattes constatées et photographiées (horodatage) à l\'appartement voisin 88'],
        ['date' => '2026-06-06', 'fait' => 'Mise en demeure envoyée à NMH (protocole complet + apparts voisins + rapports)'],
        ['date' => '2026-06-09', 'fait' => '✅ VICTOIRE (partielle) : NMH relance le prestataire (Sapiens) pour un nouveau RDV à domicile'],
    ],
    'prochaines_etapes' => [
        'VOLET URGENCE — verrouiller par écrit (cette semaine) : acter la relance de Sapiens, MAIS exiger le protocole COMPLET (pas le préventif allégé) conforme aux recommandations écrites du technicien, le calendrier des passages, l\'inclusion des apparts voisins (88), et la communication des rapports + de la commande. Ton factuel, sans agressivité — l\'engagement est acquis, on le cadre.',
        'VOLET PRÉJUDICE — à ouvrir À L\'AMIABLE une fois le traitement engagé (gardé en réserve jusqu\'ici) : proposer une indemnisation du trouble de jouissance sur ~4  ans + préjudice moral. On ne parle argent qu\'APRÈS l\'urgence, pour ne pas braquer.',
        'Faire adhérer Mme Gourdien à l\'association + mandat écrit avant tout courrier en son nom.',
        'À défaut d\'accord amiable sur le préjudice : SCHS de Nantes, puis Tribunal judiciaire (avocat = Fabrice, lui seul).',
    ],

    /* --- Chiffrage du préjudice (bot — AMIABLE, à l'ouverture) --- */
    'prejudice' => [
        'note' => "Chiffrage indicatif À L'AMIABLE (ouverture de négociation), produit par le bot préjudice. BLATTES → PAS de mise en sacs ni lavage 60-90 °C (règle réservée aux punaises de lit) : ici protection/éviction + remplacement d'éventuels effets contaminés sur justificatifs. À parfaire avec le loyer réel de Mme Gourdien.",
        'postes' => [
            "Trouble de jouissance (≈ 4 ans / 48 mois) — réduction de 15 à 20 % du loyer sur la période. Base loyer HLM moyen du quartier ≈ 330 €/mois (à remplacer par le loyer réel) → environ 2 400 à 3 200 €.",
            "Préjudice moral (infestation lourde subie pendant 4 ans, stress, prurit, atteinte à la dignité du logement) — forfait amiable ≈ 500 à 1 000 €.",
            "Remplacement / protection d'effets éventuellement contaminés — sur justificatifs (poste modeste, à documenter).",
            "Frais d'accompagnement engagés par l'association — selon le total « frais » du dossier.",
        ],
        'fourchette_amiable' => 'de l\'ordre de 3 000 à 4 500 € (ouverture amiable, négociable) — à confirmer avec le loyer réel et les justificatifs.',
    ],

    'pieces' => [
        'Mise en demeure du 06/06/2026 (envoyée) + réponse de NMH du 09/06/2026 (reçue)',
        'Recommandations écrites du technicien NMH (protocole complet) — à demander formellement',
        'Rapports d\'intervention Sapiens + copie de la commande — à obtenir de NMH',
        'Photographie horodatée du 19/05/2026 (apt voisin 88)',
    ],
];
