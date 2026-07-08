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

        /* CE QUI A ÉTÉ FAIT — timeline (ordre chronologique). */
        'timeline' => [
            ['date' => '2026-06-28', 'fait' => 'Visite et constat du logement par le Groupe d’Action', 'detail' => 'Constat des désordres : moisissures / humidité, et désordres techniques (électricité, fuite d’eau, volet roulant). Photographies datées.'],
            ['date' => '2026-06-28', 'fait' => 'Certificat médical recueilli', 'detail' => 'Pièce attestant l’impact sanitaire sur un occupant — versée au dossier.'],
            ['date' => '2026-06-28', 'fait' => 'Ouverture du dossier juridique #1', 'detail' => 'Centralisation des pièces et du suivi.'],
            ['date' => '2026-06', 'fait' => 'Signalement urgent envoyé à NMH (Agence Goudy, M. Morineau)', 'detail' => 'Démarche amiable : signalement écrit des désordres via le site.'],
            ['date' => '2026-06', 'fait' => 'Réponse de NMH reçue', 'detail' => 'Réponse d’esquive (voir correspondance ci-dessous) : « pas de signalement », renvoi des désordres au locataire, silence sur les moisissures et la santé.'],
            ['date' => '2026-06-29', 'fait' => 'Analyse de la réponse de NMH réalisée', 'detail' => 'Réfutation point par point (manquements juridiques + professionnalisme).'],
        ],

        /* CE QU’IL RESTE À FAIRE — stratégie, priorité à l’amiable. */
        'prochaines_etapes' => [
            ['etape' => 'Demander une visite contradictoire', 'echeance' => 'sous 8 jours', 'detail' => 'Par email puis, à défaut de réponse, par LRAR. En présence de la locataire et de l’association. NMH invoque un défaut d’accès : c’est à lui d’organiser la visite.'],
            ['etape' => 'Faire adhérer la locataire à l’association + mandat écrit', 'echeance' => 'avant tout courrier en son nom', 'detail' => 'Condition pour agir et écrire au nom de la locataire (loi du 6 juillet 1989 ; art. 63-66 loi 71-1130). Générer le bulletin d’adhésion depuis le dossier.'],
            ['etape' => 'Envoyer la mise en demeure LRAR (réponse argumentée)', 'echeance' => 'semaine en cours', 'detail' => 'Utiliser le document « 📨 Réponse argumentée à NMH » : réfutation point par point + exiger une RECHERCHE DE FUITE et un DIAGNOSTIC électricité/volet à la charge de NMH, un calendrier ferme de travaux, et une réponse écrite sur les moisissures + la demande de relogement.'],
            ['etape' => 'À défaut de réponse satisfaisante : saisir le SCHS de Nantes et l’ARS', 'echeance' => 'sous 15 jours', 'detail' => 'Documents « 🏥 SCHS » et « 🏛 ARS » du dossier. Une insalubrité confirmée peut emporter un arrêté préfectoral et l’obligation de relogement (L.521-3-1 CCH).'],
            ['etape' => 'Consulter les avocats (Me Vallée — Cabinet 333, et Me Gouache)', 'echeance' => 'en parallèle', 'detail' => 'Voir la « Note pour les avocats » ci-dessous : volet civil/logement (référé-expertise art. 145 CPC, travaux + dommages-intérêts + réduction de loyer art. 20-1 loi 89-462) et volet pénal/habitat indigne (art. 225-14 C. pén., L.1337-4 CSP). Éligibilité à l’aide juridictionnelle.'],
            ['etape' => 'Documenter en continu', 'echeance' => 'permanent', 'detail' => 'Photos datées, relevés d’humidité, et — de façon anonymisée — les cas similaires dans l’immeuble (pour étayer un défaut collectif du bâti).'],
        ],

        /* NOTE POUR LES AVOCATS — synthèse de ce qu’on attend d’eux. */
        'note_avocats' => <<<'AVO'
À l’attention de :
• Maître Stéphane VALLÉE — Cabinet d’Avocats 333, 14 boulevard Gabriel Guist’hau, 44000 Nantes — Tél. 02 40 20 00 22 — contact@cabinet333.com
• Maître Maxime GOUACHE — Cabinet Poquet-Gouache, barreau de Nantes (droit pénal, exécution des peines, droit des étrangers)

OBJET : logement de Mme Fadiga — appui juridique envisagé (bailleur : Nantes Métropole Habitat).

1. CONTEXTE
Mme Fadiga est locataire d’un logement social géré par Nantes Métropole Habitat (Agence Goudy, M. Morineau). Le logement présente des désordres affectant la décence et la santé : moisissures et humidité, ainsi que des désordres techniques (électricité, fuite d’eau, volet roulant). Un certificat médical atteste de l’impact sanitaire sur un occupant. La démarche amiable est engagée (signalement écrit) ; la réponse de NMH esquive l’essentiel (voir analyse jointe).

2. CE QUI A DÉJÀ ÉTÉ FAIT
Constat de visite avec photographies datées ; recueil du certificat médical ; signalement écrit à NMH ; analyse réfutant point par point la réponse du bailleur. À venir : visite contradictoire, mise en demeure LRAR, puis saisine SCHS/ARS si nécessaire.

3. CE QUE NOUS ATTENDONS DE VOUS — DEUX VOLETS

A) VOLET CIVIL / LOGEMENT (plutôt Maître Vallée / Cabinet 333, à confirmer selon vos domaines) :
   a) Un avis sur la stratégie et le séquencement (amiable → référé → fond).
   b) Un RÉFÉRÉ-EXPERTISE (art. 145 du Code de procédure civile) pour faire constater par expert l’humidité/les moisissures, leur origine (bâti vs usage) et le lien avec la santé.
   c) Une action en EXÉCUTION FORCÉE des travaux (art. 1719-1720 C. civ. ; décret n° 2002-120 sur la décence), assortie de DOMMAGES-INTÉRÊTS pour trouble de jouissance (art. 1231-1 et 1240 C. civ.).
   d) Une demande de RÉDUCTION DE LOYER et d’injonction de travaux pour non-décence (art. 20-1 de la loi n° 89-462).
   e) L’articulation avec l’INSALUBRITÉ (SCHS/ARS, art. L.1331-22 et s. CSP) et l’obligation de RELOGEMENT (art. L.521-3-1 CCH).
   f) La faisabilité d’une ACTION COLLECTIVE (plusieurs locataires, même bailleur, désordres communs du bâti — art. 24-1 de la loi n° 89-462), si plusieurs ménages sont concernés.

B) VOLET PÉNAL / HABITAT INDIGNE (Maître Gouache, droit pénal) :
   a) L’opportunité d’agir sur le fondement de la SOUMISSION D’UNE PERSONNE VULNÉRABLE À DES CONDITIONS D’HÉBERGEMENT INDIGNES (art. 225-14 du Code pénal).
   b) Les SANCTIONS PÉNALES en cas de non-respect d’un arrêté d’insalubrité ou de mise en sécurité (art. L.1337-4 du Code de la santé publique), si un tel arrêté venait à être pris.
   c) L’opportunité d’un dépôt de plainte / d’une constitution de partie civile, et son articulation avec le volet civil.

4. QUESTIONS PRÉCISES
- Lequel d’entre vous (ou via le Cabinet 333) prend en charge le volet baux d’habitation / immobilier ?
- Mme Fadiga est-elle éligible à l’AIDE JURIDICTIONNELLE, et pouvez-vous intervenir à ce titre ?
- Quel est le levier le plus rapide pour obtenir les travaux (référé-injonction de faire / référé-expertise) ?
- Le volet pénal (habitat indigne) est-il opportun ici, et à quelles conditions ?
- Quels éléments de preuve sécuriser dès maintenant pour solidifier le dossier ?
- Vos modalités et honoraires pour une première consultation ?

5. PIÈCES À VOTRE DISPOSITION
Constat de visite + photographies datées ; certificat médical ; copie du signalement et de la réponse de NMH ; analyse juridique jointe.
AVO,
    ],

    'gourdien' => [
        'titre'      => 'Mme Gourdien — réponse de M. MORINEAU (NMH) : relance du traitement blattes',
        'dossier_id' => 5,
        'date'       => '2026-07-03',
        'emails'     => [
            [
                'sens'  => 'envoye',
                'to'    => 'yvonnic.morineau@nmh.fr (NMH — Pôle Grand Sud) · CC Clos Toreau',
                'date'  => '2026-06-06 23:06',
                'objet' => 'Apt 93 — 14 rue Saint-Jean-de-Luz — Mise en demeure de traitement complet de l\'infestation de blattes (4 ans)',
                'corps' => "Mise en demeure de Mme Gourdien : infestation de blattes depuis avril 2022. Le propre technicien de NMH a constaté une infestation « très importante », incompatible avec le traitement allégé, et a recommandé PAR ÉCRIT un protocole complet et étalé. Demandes sous huitaine : (1) intervention par une entreprise spécialisée pour un protocole COMPLET ; (2) détail du protocole + calendrier ; (3) inclusion des apparts voisins (dont le 88, blattes photographiées le 19/05/2026) ; (4) communication des rapports antérieurs et de la copie de la commande. À défaut : SCHS, Tribunal judiciaire en référé (astreinte + indemnisation), Défenseur des droits, ANCOLS. Fondements : art. 1719 C. civ., art. 6 loi 1989, décret 2002-120, L.1331-22 CSP, loi ELAN, Cass. 3e civ. 8 juillet 2009 n° 08-12.116.",
            ],
            [
                'sens'  => 'recu',
                'de'    => 'yvonnic.morineau@nmh.fr — Yvonnic MORINEAU, Responsable Patrimoine, Pôle Grand Sud (NMH)',
                'date'  => '2026-06-09 08:54',
                'objet' => 'Réponse de NMH — relance du prestataire',
                'corps' => <<<'MAIL'
Bonjour

Nous prenons acte de votre demande ci-dessous.

Pour rappel depuis votre entrée dans les lieux en 2022, vous nous avez sollicité une seule fois au 23/10/25 pour ce sujet qui a fait l'objet d'une commande de traitement au 11/11/25 à l'entreprise Sapian puis d'une annulation au 31/12/25 faute de retour de votre part pour convenir d'un rendez-vous.

L'entreprise a cependant pu intervenir au 23/03/26 dans le cadre d'un traitement préventif de contrôle et a constaté la présence de blatte et a appliqué le traitement en conséquence. Cette prestation étant garantie 6 mois nous relançons le prestataire afin qu'il conviennes d'un nouveau rendez-vous à votre domicile.

Cordialement
Yvonnic MORINEAU — Responsable Patrimoine — Pôle Grand Sud — Direction Maintenance Proximité
MAIL,
            ],
        ],
        'analyse'    => <<<'TXT'
ANALYSE DE LA RÉPONSE DE NMH (M. Yvonnic MORINEAU) — dossier Mme Gourdien

I. CE QUE DIT LA RÉPONSE — UNE AVANCÉE RÉELLE, MAIS PARTIELLE
M. MORINEAU prend acte de la mise en demeure et annonce RELANCER le prestataire (Sapiens) pour un nouveau rendez-vous à domicile, la prestation du 23/03/2026 étant garantie 6 mois. C'est une VICTOIRE PARTIELLE : le traitement va reprendre. Mais la réponse n'accorde explicitement NI le protocole complet demandé, NI le calendrier, NI le traitement des apparts voisins, NI la communication des rapports et de la commande, NI un mot sur le préjudice.

II. SUR LA FORME (lecture psychologique) — administratif, légèrement défensif
Le ton reste courtois mais minimise : « vous nous avez sollicité une seule fois » et « annulation […] faute de retour de votre part » reportent la responsabilité sur la locataire. Ce n'est ni de l'hostilité ni une manœuvre d'intimidation — inutile de sur-réagir. La bonne posture : ACTER l'engagement (la relance), rester factuel, et VERROUILLER par écrit ce qui manque.

III. SUR LE FOND — POINT PAR POINT
1) « Une seule sollicitation » — inopérant. L'obligation d'entretien et de délivrance d'un logement décent (art. 1719-1720 C. civ. ; décret 2002-120) est PERMANENTE et ne dépend pas du nombre de signalements. Surtout, NMH RECONNAÎT lui-même que son prestataire a constaté la présence de blattes : la carence est établie indépendamment du décompte des relances.
2) « Annulation faute de retour de votre part » (31/12/25) — à documenter et, au besoin, contester ; en tout état de cause, un bailleur diligent relance (ce que NMH fait enfin). L'essentiel : le traitement reprend.
3) « Traitement préventif de contrôle », « garanti 6 mois » — c'est précisément le traitement ALLÉGÉ que le propre technicien de NMH a jugé insuffisant. On n'accepte pas une simple reconduction du préventif : on exige le PROTOCOLE COMPLET recommandé par écrit par ce technicien.
4) SILENCES à lever, factuellement : protocole complet, calendrier des passages, apparts voisins (88 — réinfestation garantie sinon), rapports d'intervention + copie de la commande.

IV. CONCLUSION — LA BOUSSOLE
Volet URGENCE : en voie de résolution — à verrouiller par écrit cette semaine (protocole complet + calendrier + voisins + rapports). Volet PRÉJUDICE : on l'ouvre ENSUITE, à l'amiable d'abord (jamais proposé à ce jour, gardé en réserve), pour ne pas braquer le bailleur pendant qu'il agit sur l'urgence. Judiciaire en dernier recours seulement.
TXT,

        'timeline' => [
            ['date' => '2022-04', 'fait' => 'Entrée dans les lieux — infestation de blattes dès l\'origine', 'detail' => 'Point de départ du trouble de jouissance.'],
            ['date' => '2025-11-11', 'fait' => 'NMH commande un traitement à Sapiens', 'detail' => 'Puis annulé le 31/12/2025 (NMH invoque un défaut de RDV côté locataire).'],
            ['date' => '2026-03-23', 'fait' => 'Intervention Sapiens : blattes constatées, traitement appliqué', 'detail' => 'Traitement préventif « allégé » — jugé insuffisant par le technicien lui-même.'],
            ['date' => '2026-06-06', 'fait' => 'Mise en demeure envoyée à NMH', 'detail' => 'Protocole complet + calendrier + apparts voisins + rapports.'],
            ['date' => '2026-06-09', 'fait' => '✅ Réponse de NMH : relance du prestataire (victoire partielle)', 'detail' => 'Le traitement va reprendre ; le reste est à verrouiller.'],
        ],
        'prochaines_etapes' => [
            ['etape' => 'VERROUILLER L\'URGENCE par écrit', 'echeance' => 'cette semaine', 'detail' => 'Réponse factuelle : remercier de la relance, MAIS exiger le protocole COMPLET (recommandé par le technicien NMH), le calendrier des passages, l\'inclusion des apparts voisins (88), et la communication des rapports + de la commande. Sans agressivité — l\'engagement est acquis, on le cadre.'],
            ['etape' => 'OUVRIR LE PRÉJUDICE à l\'amiable', 'echeance' => 'une fois le traitement engagé', 'detail' => 'Gardé en réserve jusqu\'ici. Proposer une indemnisation amiable du trouble de jouissance (~4 ans) + préjudice moral. On ne parle argent qu\'APRÈS l\'urgence, pour ne pas braquer. Chiffrage : voir la fiche (bot préjudice).'],
            ['etape' => 'Adhésion + mandat écrit', 'echeance' => 'avant tout courrier en son nom', 'detail' => 'Condition pour écrire au nom de Mme Gourdien.'],
            ['etape' => 'À défaut d\'accord amiable sur le préjudice : SCHS puis judiciaire', 'echeance' => 'si blocage', 'detail' => 'Avocat = Fabrice, lui seul. Jamais les membres.'],
        ],
    ],

];
