<?php
/**
 * BOUSSOLE — L'ESPACE LOCATAIRE (self-service, mais canalisé)
 *
 * Principe directeur pour construire l'interface des locataires suivis. À
 * respecter dans TOUT ce qu'on développe pour eux. (Note de référence, à
 * transformer en fonctionnalités.)
 */
if (!defined('ABSPATH')) exit;

return [
    'but' => "Le locataire s'empare de SON dossier et fait les choses lui-même — ça le responsabilise ET ça décharge le gestionnaire du suivi.",

    'regles' => [
        "CANAL UNIQUE : tout contact avec le bailleur (NMH) passe PAR l'app. Jamais le Gmail perso seul, jamais un SMS externe, jamais un appel non tracé — car ça ne laisse AUCUNE preuve.",
        "TOUJOURS UNE TRACE : chaque message envoyé (au bailleur, au service d'hygiène…) est automatiquement VERSÉ AU DOSSIER, daté, pour pouvoir être remis à l'avocat.",
        "CLOISONNEMENT : le locataire ne voit QUE son dossier. Aucune donnée d'un autre locataire, aucune donnée interne du GA (RGPD strict).",
        "LES PIÈCES VIENNENT D'EUX : le locataire dépose lui-même ses justificatifs (photos, quittances, courriers reçus) dans SON dossier → le gestionnaire n'a plus à les réclamer.",
        "ON GUIDE, ON NE LÂCHE PAS LA BRIDE : on invite et on explique (« voici ce qu'on attend de vous »), mais on ne donne pas de liberté qui pousse vers des outils extérieurs contre-productifs.",
    ],

    'a_construire' => [
        "SMS d'invitation guidé : quand on envoie un SMS à un locataire, un message prêt avec SON lien + explication (« votre espace, déposez vos pièces ici »).",
        "« Déposer mes pièces » : upload direct dans SON dossier (photos/documents) → arrive classé chez le gestionnaire.",
        "« Contacter NMH depuis l'app » : le locataire écrit son message dans l'app → il part (ou est prêt à partir) ET une copie est versée au dossier automatiquement. Jamais en dehors.",
        "Encart « ce qu'on attend de vous » : ce qui manque au dossier, à compléter par le locataire.",
        "Signaler un souci : bouton déjà en place, présent aussi pour les locataires.",
    ],

    'garde_fou' => "Priorité inchangée : sortir les gens de leurs conditions d'abord. L'espace locataire sert cette cause — il ne complique jamais la vie de la personne, il la rend actrice.",
];
