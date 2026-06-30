<?php
/**
 * DOSSIER LOCATAIRE — Mme FADIGA  (fiche maître, gérée par le code)
 *
 * C'est LE fichier de référence du dossier de Mme Fadiga. Pour le mettre à
 * jour : édite ce fichier (ici, dans une conversation dédiée à elle) et
 * pousse sur GitHub. Le site/app affiche automatiquement la fiche à jour
 * (route « dossier-synthese?slug=fadiga ») et le PDF se régénère à partir
 * des mêmes données.
 *
 * Lié au dossier juridique #1 (base WordPress) via 'dossier_id'.
 */
if (!defined('ABSPATH')) exit;

return [
    'slug'        => 'fadiga',
    'dossier_id'  => 1,
    'maj'         => '2026-06-29',
    'confidentiel'=> true,

    /* --- État civil & logement --- */
    'civilite'    => 'Mme',
    'prenom'      => '',          // à compléter
    'nom'         => 'FADIGA',
    'adresse'     => '8 rue de Saint-Jean-de-Luz, 44200 Nantes',
    'etage'       => '8',
    'appartement' => '130',
    'anciennete'  => 'Locataire depuis ~10 ans (selon NMH)',
    'bailleur'        => 'Nantes Métropole Habitat — Agence Goudy',
    'bailleur_contact'=> 'M. Yvonnic Morineau — yvonnic.morineau@nmh.fr',
    'medical'     => 'Certificat du Dr Aubeau — impact sanitaire sur un occupant (enfant)',
    'rdv'         => 'Me Maxime Gouache — mercredi 1er juillet 2026, 15 h 30 (4 rue des Racines, Nantes)',

    /* --- Objectif (ce que vise le dossier) --- */
    'objectif_locataire' => "Être RELOGÉE. Mme Fadiga demande à déménager — ni travaux, ni maintien dans le logement. Seul un relogement répond à sa situation.",
    'objectifs_ga' => [
        "Indemnisation de l'ensemble des préjudices subis (trouble de jouissance, préjudice moral et, surtout, préjudice de santé).",
        "Reconnaissance du lien de causalité entre l'humidité du logement et la grave maladie de sa fille (les moyens probatoires — expertise médicale et technique — relèvent de l'avocat).",
    ],

    /* --- Désordres + position de NMH + notre observation --- */
    'desordres' => [
        ['nom' => 'Humidité / moisissures',     'nmh' => 'Aucune mention — sujet ignoré.',                                            'obs' => 'Au cœur de la demande (avec la santé) ; relève du bâti (ventilation, étanchéité).'],
        ['nom' => 'Nuisibles — blattes / cafards','nmh' => '« n\'a pas signalé […] de nuisibles ».',                                    'obs' => 'Signalé par écrit ; en logement social les nuisibles relèvent du bailleur (Règlement Sanitaire Départemental).'],
        ['nom' => 'Fuite d\'eau',                'nmh' => 'Renvoie à l\'assurance de la locataire et à l\'entretien des joints.',       'obs' => 'Origine inconnue, possiblement structurelle ; la recherche de fuite peut incomber au bailleur.'],
        ['nom' => 'Électricité',                 'nmh' => '« charge locative […] jusqu\'au remplacement de l\'équipement défectueux ».', 'obs' => 'Le remplacement d\'un équipement défectueux par vétusté relève de la décence (bailleur).'],
        ['nom' => 'Volet roulant motorisé',      'nmh' => 'Invite à « déconnecter / relancer le différentiel ».',                       'obs' => 'Palliatif et non réparation ; équipement défaillant à diagnostiquer et réparer.'],
    ],

    /* --- La conversation avec M. Morineau --- */
    'email_envoye' => [
        'objet' => 'URGENT — Relogement médical de Mme Fadiga · 8 rue de Saint-Jean-de-Luz, étage 8',
        'a'     => 'Nantes Métropole Habitat — M. Yvonnic Morineau (yvonnic.morineau@nmh.fr)',
        'corps' => <<<'MAIL'
Madame, Monsieur,

Compte tenu de l'urgence sanitaire attestée, je sollicite votre traitement prioritaire de la demande de relogement de Mme Fadiga formalisée ci-dessous. La lettre recommandée vous sera également remise.

Logement concerné : 8 rue de Saint-Jean-de-Luz, 44200 Nantes, étage 8, appartement 130 — locataire : Mme Fadiga.

Constatations établies sur place : humidité et moisissures, présence de nuisibles (blattes), fuite d'eau, désordres électriques et volet roulant défaillant.

Situation médicale (certificat du Dr Aubeau) : l'état de santé d'un occupant (enfant) est affecté par les conditions du logement.

Demande : en application de l'article L.521-3-1 du Code de la construction et de l'habitation, de la loi DALO (n° 2007-290) et de l'article 1719 du Code civil, je sollicite l'attribution d'un logement décent et adapté à la situation médicale, dans un délai d'UN (1) MOIS. À défaut, je saisirai la commission DALO, le SCHS aux fins d'arrêté d'insalubrité (qui emporte obligation de relogement à votre charge — art. L.521-3-1 CCH), et le cas échéant le Tribunal Judiciaire en référé (art. 835 CPC).

Je reste à votre disposition pour toute information complémentaire. Salutations distinguées,
Fabrice Doucet — Union des Quartiers Libres / GA LFI Nantes Sud – Clos Toreau
MAIL,
        'note' => "Reconstitué d'après le modèle « Demande de relogement d'urgence médicale » envoyé depuis le dossier.",
    ],
    'email_recu' => [
        'de'    => 'yvonnic.morineau@nmh.fr (Agence Goudy — NMH)',
        'corps' => <<<'MAIL'
Bonjour,

Ces faits n'ont pas fait l'objet d'un signalement auprès de NMH à ce jour par la locataire ou le service d'hygiène de la ville de Nantes.

Je reviens d'un contrôle de l'immeuble en lien avec le rétablissement de l'eau chaude collectif réalisé ce matin. La locataire était présente mais elle n'a pas signalé de problème technique ou en lien avec des nuisibles hormis une demande relative à l'amélioration du sol au 24/06/26 en cours de traitement.

Aussi, sans avoir pu avoir accès au logement mais en l'absence de confirmation par la locataire des dysfonctionnements signalés, veuillez nous apporter des éléments complémentaires pour instruire votre demande.

Dans cette attente certaines demandes relèvent d'une charge locative ou des contrats d'entretien pour cette locataire présente depuis 10 ans et dont le constat d'état des lieux d'entrée ne fait pas mention de ces désordres.

Électricité – c'est une charge locative. Il appartient au locataire d'en assurer la maintenance jusqu'au remplacement de l'équipement défectueux.

Fuite d'eau – s'il y a des dégâts en lien avec des infiltrations, nous n'avons pas de constat dégât des eaux de parvenu, il est nécessaire d'en faire un via son assurance habitation. Si l'origine semble inconnue, il appartient au locataire dans un premier temps un contrôle et la maintenance des joints d'étanchéité des appareils sanitaires et de contacter l'entreprise de maintenance des robinetteries pour un contrôle des équipements (n° affiché dans le hall).

Volet roulant – les volets de la résidence sont électrifiés, compte tenu des fortes chaleurs il peut y avoir une contrainte sur la motorisation qui se déconnecte – Dans un premier temps j'invite la locataire à déconnecter/relancer le différentiel correspondant au niveau du tableau électrique et de revenir vers nous si le volet reste bloqué.

Cordialement
MAIL,
    ],

    /* --- L'enquête de voisinage (confidentiel — ne pas communiquer à NMH) --- */
    'enquete' => [
        'reponses'    => 30,
        'logements'   => '19 / 30 (63 %) avec problèmes',
        'gravite'     => '7 / 10 en moyenne',
        'eau_chaude'  => '100 % des immeubles touchés (plus de 10 coupures/an, plus de 10 jours cumulés, jusqu\'à 3 semaines)',
        'immeuble'    => '8 rue de Saint-Jean-de-Luz figure parmi les immeubles enquêtés',
        'problemes'   => [
            ['type' => 'Insectes / nuisibles (blattes, cafards)', 'n' => '8 / 30', 'pct' => '26,7 % — plus d\'1 sur 4'],
            ['type' => 'Chauffage défaillant',                    'n' => '8 / 30', 'pct' => '26,7 %'],
            ['type' => 'Dégâts des eaux / fuites',                'n' => '5 / 30', 'pct' => '16,7 %'],
            ['type' => 'Humidité / moisissures',                 'n' => '4 / 30', 'pct' => '13,3 %'],
            ['type' => 'Ascenseur · Parties communes',           'n' => '2 / 30 · 2 / 30', 'pct' => '6,7 %'],
            ['type' => 'Électricité',                            'n' => '1 / 30', 'pct' => '3,3 %'],
        ],
    ],

    /* --- Suivi (fait) / à faire --- */
    'timeline' => [
        ['date' => '2026-06-28', 'fait' => 'Visite et constat du logement + photos datées'],
        ['date' => '2026-06',    'fait' => 'Email de demande de relogement médical envoyé à NMH (M. Morineau)'],
        ['date' => '2026-06',    'fait' => 'Réponse d\'esquive de NMH reçue'],
        ['date' => '2026-07-01', 'fait' => 'Rendez-vous avec Me Gouache (dossier remis)'],
    ],
    'prochaines_etapes' => [
        'Faire signer à Mme Fadiga le mandat à l\'avocat (et l\'adhésion à l\'association).',
        'Réunir les preuves du lien humidité → santé (certificats, relevés d\'humidité, photos).',
        'Suivre les suites définies par l\'avocat (relogement + indemnisation).',
    ],

    'pieces' => [
        'Constat de visite + photographies datées des désordres',
        'Certificat médical (Dr Aubeau)',
        'Copie du signalement (relogement) et de la réponse de NMH',
        'Données de l\'enquête de voisinage (usage interne)',
    ],
];
