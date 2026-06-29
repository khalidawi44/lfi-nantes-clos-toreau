<?php
/**
 * ANALYSES D'EMAILS NMH — rédigées par Claude Code
 *
 * À chaque fois qu'on analyse ensemble un email de Nantes Métropole
 * Habitat (M. Morineau, etc.), Claude écrit ici la retranscription de la
 * discussion + l'analyse (manquements juridiques + professionnalisme).
 *
 * Si 'dossier_id' correspond à un dossier juridique existant, le document
 * « 📑 Discussion + analyse » de ce dossier affiche automatiquement ce
 * contenu (au lieu de la base) — prêt à imprimer / PDF.
 */
if (!defined('ABSPATH')) exit;

return [

    'fadiga' => [
        'titre'      => 'Mme Fadiga — réponse de l\'Agence Goudy (M. Morineau, NMH)',
        'dossier_id' => 1,
        'date'       => '2026-06-29',
        'emails'     => [
            [
                'sens'  => 'envoye',
                'to'    => 'yvonnic.morineau@nmh.fr (Agence Goudy — NMH)',
                'date'  => '2026-06 (signalement urgent)',
                'objet' => 'Signalement urgent — logement de Mme Fadiga (Clos Toreau)',
                'corps' => "Signalement urgent transmis via le site, listant les désordres affectant le logement de Mme Fadiga (humidité / moisissures, et désordres techniques : électricité, fuite d'eau, volet roulant), avec demande de prise en charge.",
            ],
            [
                'sens'  => 'recu',
                'de'    => 'yvonnic.morineau@nmh.fr (Agence Goudy — Nantes Métropole Habitat)',
                'date'  => '2026-06',
                'objet' => 'Réponse de NMH',
                'corps' => <<<'MAIL'
Bonjour,

Ces faits n’ont pas fait l’objet d’un signalement auprès de NMH à ce jour par la locataire ou le service d’hygiène de la ville de Nantes.

Je reviens d’un contrôle de l’immeuble en lien avec le rétablissement de l’eau chaude collectif réalisé ce matin. La locataire était présente mais elle n’a pas signalé de problème technique ou en lien avec des nuisibles hormis une demande relative à l’amélioration du sol au 24/06/26 en cours de traitement.

Aussi, sans avoir pu avoir accès au logement mais en l’absence de confirmation par la locataire des dysfonctionnements signalés, veuillez nous apporter des éléments complémentaires pour instruire votre demande.

Dans cette attente certaines demandes relèvent d’une charge locative ou des contrats d’entretien pour cette locataire présente depuis 10 ans et dont le constat d’état des lieux d’entrée ne fait pas mention de ces désordres.

Électricité – c’est une charge locative. Il appartient au locataire d’en assurer la maintenance jusqu’au remplacement de l’équipement défectueux.

Fuite d’eau – s’il y a des dégâts en lien avec des infiltrations, nous n’avons pas de constat dégât des eaux de parvenu, il est nécessaire d’en faire un via son assurance habitation. Si l’origine semble inconnue, il appartient au locataire dans un premier temps un contrôle et la maintenance des joints d’étanchéité des appareils sanitaires et de contacter l’entreprise de maintenance des robinetteries pour un contrôle des équipements (n° affiché dans le hall).

Volet roulant – les volets de la résidence sont électrifiés, compte tenu des fortes chaleurs il peut y avoir une contrainte sur la motorisation qui se déconnecte – Dans un premier temps j’invite la locataire à déconnecter/relancer le différentiel correspondant au niveau du tableau électrique et de revenir vers nous si le volet reste bloqué.

Cordialement
MAIL,
            ],
        ],
        'analyse'    => <<<'TXT'
ANALYSE DE LA RÉPONSE DE NANTES MÉTROPOLE HABITAT (Agence Goudy — M. Morineau)

I. CONTEXTE
Cet email RÉPOND à notre signalement urgent transmis via le site. NMH y oppose pourtant une « absence de signalement » et renvoie la quasi-totalité des désordres à la charge de la locataire.

II. SUR LE FOND — RÉPONSE POINT PAR POINT

1) « Ces faits n’ont pas fait l’objet d’un signalement » — contredit par les faits.
Le présent email de NMH fait SUITE à notre signalement écrit : il en est lui-même la preuve, datée. L’affirmation est donc factuellement inexacte. Au surplus, l’obligation d’entretien et de délivrance d’un logement décent (art. 1719 et 1720 du Code civil ; décret n° 2002-120) est PERMANENTE et ne dépend d’aucun signalement préalable.

2) « La locataire n’a rien signalé lors de mon passage » — non pertinent.
Le passage de NMH portait sur le rétablissement de l’EAU CHAUDE COLLECTIVE, non sur l’état du logement. L’absence de remarque orale lors d’une visite technique sans rapport ne vaut pas absence de désordre ; le signalement écrit fait foi. NMH reconnaît d’ailleurs une demande en cours (amélioration du sol, 24/06/26) : la démarche de la locataire est établie.

3) « Pas d’accès au logement / pas de confirmation » — nous proposons une visite contradictoire.
NMH réclame des éléments complémentaires faute d’accès. Nous proposons une VISITE CONTRADICTOIRE, en présence de la locataire et de l’association, sous huit jours. Il appartient au bailleur d’organiser cette visite ; le défaut d’accès ne saurait justifier l’inaction.

4) « Locataire depuis 10 ans, état des lieux d’entrée sans mention » — inopérant.
L’ancienneté dans les lieux et un état des lieux d’entrée ancien n’exonèrent jamais le bailleur de son obligation CONTINUE d’entretien (art. 1719-1720 C. civ.). Les désordres apparus en cours de bail (vétusté, défauts du bâti) relèvent du bailleur, qu’ils figurent ou non à l’entrée.

5) Électricité — distinction à rétablir.
Le décret n° 87-712 ne met à la charge du locataire que le MENU entretien (ampoules, fusibles). Une INSTALLATION électrique défectueuse, vétuste ou non sécurisée relève du bailleur au titre de la décence (décret n° 2002-120 : installation électrique en bon état d’usage et de sécurité). NMH écrit lui-même « jusqu’au remplacement de l’équipement défectueux » : ce remplacement d’un équipement défectueux par vétusté incombe au bailleur, pas à la locataire.

6) Fuite d’eau — ne pas renvoyer à l’assurance de la locataire.
Si la fuite provient des canalisations, de l’étanchéité du bâti ou d’une infiltration, elle relève du bailleur (art. 1719-1720 C. civ.). Renvoyer d’emblée la locataire vers son assurance habitation et vers l’entreprise de robinetterie, alors que l’origine est « inconnue », fait porter au locataire la recherche de fuite d’un désordre potentiellement structurel. Nous demandons une RECHERCHE DE FUITE à la charge de NMH ; le seul entretien des joints (menu) ne pourra être retenu qu’après détermination contradictoire de l’origine réelle.

7) Volet roulant électrifié — défaut récurrent à réparer.
Un volet motorisé qui « se déconnecte » de façon récurrente est un équipement du logement à maintenir en état de fonctionnement (décence). Inviter la locataire à réenclencher le différentiel est un palliatif, non une réparation. Si le dysfonctionnement persiste avec la chaleur, c’est un défaut de motorisation à diagnostiquer et réparer par le bailleur.

8) SILENCE sur les moisissures / l’humidité (et le certificat médical) — manquement le plus grave.
La réponse n’aborde NI les moisissures et l’humidité signalées, NI — le cas échéant — le certificat médical et la demande de relogement, qui sont au cœur du dossier. Ce silence sur la dimension sanitaire est le manquement le plus grave : il met en jeu la santé d’un occupant, médicalement attestée.

III. SUR LA FORME — PROFESSIONNALISME
- Report systématique de la responsabilité sur la locataire (preuve à fournir, assurance à saisir, différentiel à réenclencher) ;
- Utilisation d’une visite technique sans rapport (eau chaude) pour insinuer l’absence de désordre ;
- Renvoi vers des tiers (assurance, entreprise de robinetterie) plutôt qu’un engagement d’inspection ;
- Aucune date de visite, aucun calendrier de travaux, aucune réponse sur la santé.

IV. CE QUE NOUS DEMANDONS
1) Une visite contradictoire sous huit jours ;
2) Une recherche de fuite et un diagnostic électricité / volet roulant à la charge de NMH ;
3) Une réponse écrite sur les moisissures, l’humidité et la demande de relogement au regard du certificat médical.
À défaut : saisine du Service Communal d’Hygiène et de Santé de Nantes et de l’ARS (art. L.1331-22 et s. du Code de la santé publique), pouvant emporter un arrêté d’insalubrité et l’obligation de relogement (art. L.521-3-1 du CCH), ainsi que la saisine de la juridiction compétente. Préjudice et trouble de jouissance réservés (art. 1231-1 et 1240 du Code civil).
TXT,
    ],

];
