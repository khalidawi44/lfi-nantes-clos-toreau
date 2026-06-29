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
 *
 * Format de chaque entrée (clé = identifiant court, ex : 'fadiga') :
 *   'titre'      => string
 *   'dossier_id' => int    (id du dossier juridique à relier, ou 0)
 *   'date'       => 'AAAA-MM-JJ'
 *   'emails'     => [ ['sens'=>'recu'|'envoye','de'=>'','to'=>'','date'=>'','objet'=>'','corps'=>"..."], ... ]
 *   'analyse'    => "Texte de l'analyse (multi-lignes)."
 */
if (!defined('ABSPATH')) exit;

return [

    'fadiga' => [
        'titre'      => 'Mme Fadiga — réponse de l\'Agence Goudy (M. Morineau, NMH)',
        'dossier_id' => 1,
        'date'       => '2026-06-29',
        'emails'     => [
            [
                'sens'  => 'recu',
                'de'    => 'yvonnic.morineau@nmh.fr (Agence Goudy — Nantes Métropole Habitat)',
                'date'  => '2026-06',
                'objet' => 'Réponse de NMH — situation du logement de Mme Fadiga',
                'corps' => "[SYNTHÈSE des points soulevés par NMH — à remplacer par le texte exact de l'email quand tu me le transmets en toutes lettres.]\n\n"
                    . "Dans sa réponse, l'Agence Goudy (M. Yvonnic Morineau) :\n"
                    . "1) oppose une absence de signalement préalable des désordres par la locataire ;\n"
                    . "2) indique ne pas avoir eu accès au logement / ne pas avoir de confirmation de la locataire ;\n"
                    . "3) renvoie une partie des désordres à l'entretien courant (« charges locatives » à la charge du locataire) ;\n"
                    . "4) n'apporte aucune réponse sur les moisissures et l'humidité ni sur le certificat médical et la demande de relogement.",
            ],
        ],
        'analyse'    => <<<TXT
ANALYSE DE LA RÉPONSE DE NANTES MÉTROPOLE HABITAT (Agence Goudy — M. Morineau)

I. SUR LE FOND — MANQUEMENTS JURIDIQUES

1) « Absence de signalement » : argument inopérant.
L'obligation du bailleur de délivrer puis de maintenir le logement en état de servir à l'usage d'habitation est PERMANENTE et d'ordre public (art. 1719 et 1720 du Code civil ; art. 6 de la loi n° 89-462 du 6 juillet 1989 ; décret n° 2002-120 sur le logement décent). Elle ne dépend d'aucun signalement préalable par un canal particulier. Au demeurant, nos courriers et la présente correspondance VALENT signalement formel et écrit, daté. NMH ne peut subordonner l'exécution de ses propres obligations à une formalité qu'aucun texte n'impose.

2) Silence sur l'objet réel de la demande : moisissures, humidité, santé.
La réponse n'aborde NI les moisissures et l'humidité constatées sur place, NI le certificat médical produit, NI la demande de relogement. Or ces désordres relèvent du bâti (ventilation/VMC, étanchéité, ponts thermiques) et affectent la santé d'un occupant, médicalement attestée. Ne pas répondre au cœur de la demande caractérise un défaut de diligence et entretient un trouble de jouissance qui s'aggrave (art. 1719 et 1720 C. civ.).

3) Requalification abusive en « charges locatives ».
Le décret n° 87-712 du 26 août 1987 met à la charge du locataire le seul ENTRETIEN COURANT et les menues réparations. Il ne transfère jamais au locataire les désordres tenant au BÂTI : humidité structurelle, défaut de ventilation (VMC), infiltrations, étanchéité, équipements relevant du logement décent. Ceux-ci demeurent à la charge du bailleur (art. 1719-1720 C. civ. ; décret n° 2002-120). Un état des lieux d'entrée ancien n'exonère pas le bailleur de son obligation CONTINUE d'entretien.

4) « Absence d'accès » au logement.
Si NMH invoque un défaut d'accès, la locataire et notre association proposent une VISITE CONTRADICTOIRE à une date à convenir sous huit jours. L'argument ne saurait justifier l'inaction : il appartient au bailleur d'organiser la visite qu'il dit nécessaire.

5) Délais, traçabilité et conséquences.
L'absence de réponse utile dans un délai raisonnable et l'absence de toute proposition concrète (date de visite, calendrier de travaux) ouvrent droit à réparation du préjudice et du trouble de jouissance (art. 1231-1 et 1240 du Code civil). À défaut de prise en charge effective, saisine du Service Communal d'Hygiène et de Santé de Nantes et de l'ARS (art. L.1331-22 et s. du Code de la santé publique), susceptible d'emporter un arrêté d'insalubrité et, partant, l'obligation de relogement (art. L.521-3-1 du Code de la construction et de l'habitation).

II. SUR LA FORME — QUALITÉ ET PROFESSIONNALISME DE LA RÉPONSE

Indépendamment du fond, la réponse appelle de sérieuses réserves, peu compatibles avec le devoir d'information et de conseil attendu d'un bailleur social :
- Esquive des points essentiels (santé, moisissures, certificat médical) au profit d'arguments de pure procédure ;
- Report de la responsabilité sur la locataire (prétendu défaut de signalement, prétendu défaut d'accès) sans le moindre élément probant ;
- Absence de toute proposition concrète et datée (ni visite, ni calendrier de travaux, ni interlocuteur dédié) ;
- Formulations expéditives et imprécises, qui ne traitent pas une situation mettant en jeu la santé d'un occupant.

CONCLUSION
La réponse de NMH est juridiquement inopérante sur l'essentiel et insuffisamment diligente sur la forme. Elle est versée au dossier comme témoignant d'un traitement inadéquat de la situation. Nous demandons : (i) une visite contradictoire sous huit jours ; (ii) un calendrier ferme de travaux sur les désordres du bâti ; (iii) l'examen de la demande de relogement au regard du certificat médical. À défaut, saisine du SCHS, de l'ARS et de la juridiction compétente.
TXT,
    ],

];
