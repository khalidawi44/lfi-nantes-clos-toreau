<?php
/**
 * MÉTHODE — VOLET « AIDE SOCIALE À L'ENFANCE / CONSEIL DÉPARTEMENTAL »
 *
 * ⚠️ VOLET STRICTEMENT SÉPARÉ du logement. Même si on retrouve le même NOM
 * (ex. Gourdien), un dossier ASE ne se mélange JAMAIS avec un dossier logement :
 * autre adversaire (Conseil départemental / juge des enfants), autres pièces,
 * autre stratégie. On le range à part (slug « <nom>-ase », champ 'volet').
 *
 * CE FICHIER EST UN CADRE (framework) : il capture la méthodologie qui a
 * FONCTIONNÉ (dossier Doucet classé), à compléter avec les pièces réelles et
 * les conclusions exactes de la juge que Fabrice fournira. Rien n'est inventé :
 * ce qui n'est pas encore documenté est marqué « à verser ».
 *
 * La stratégie juridique relève d'un AVOCAT spécialisé (droit des mineurs /
 * famille). Boussole du volet : l'INTÉRÊT DE L'ENFANT, toujours.
 */
if (!defined('ABSPATH')) exit;

return [
    'volet'   => 'Aide sociale à l\'enfance — Conseil départemental / juge des enfants',
    'statut'  => 'CADRE méthodologique — à compléter avec les pièces réelles',
    'boussole'=> "L'intérêt de l'enfant d'abord. On ne mélange jamais ce volet avec le logement.",

    /* --- Le problème identifié --- */
    'probleme' => [
        "Déni de justice / accès effectif à la justice : souvent, on n'a pas réellement accès à la justice (à démontrer, pièces à l'appui).",
        "Une mesure judiciaire (ex. mesure éducative) est imposée, mais le SERVICE chargé de l'exécuter est désorganisé au point de la rendre inapplicable.",
    ],

    /* --- La méthodologie qui a FONCTIONNÉ (généralisée, à confirmer par les pièces) --- */
    'methode' => [
        "1. DOCUMENTER l'instabilité du service : lister, DATÉE, la succession des intervenants (dans le dossier Doucet : ~12 intervenants) — chaque changement = rupture de suivi.",
        "2. RELEVER les incohérences : contradictions entre rapports, informations perdues d'un intervenant à l'autre, engagements non tenus, rendez-vous manqués CÔTÉ SERVICE.",
        "3. DÉMONTRER que la mesure est DE FAIT INEXÉCUTABLE du fait de cette désorganisation — ce n'est pas la famille qui fait obstacle, c'est le service qui ne peut pas mettre en œuvre la mesure.",
        "4. EN TIRER la conséquence juridique : le juge ne peut maintenir une mesure inapplicable → demande de mainlevée / classement. (Résultat obtenu : la juge a classé le dossier Doucet, concluant que la désorganisation du service rendait la mesure impossible à mettre en œuvre.)",
        "5. SOULEVER, le cas échéant, le déni de justice / le défaut d'accès effectif à la justice.",
    ],

    /* --- Pièces types à réunir (bordereau à constituer) --- */
    'pieces_types' => [
        "Chronologie datée de TOUS les intervenants (nom/fonction/période) — la pièce maîtresse.",
        "Copie de la décision imposant la mesure + copie de la décision de classement (conclusions de la juge) — À VERSER.",
        "Rapports successifs du service (pour en relever les contradictions).",
        "Courriers/emails échangés (engagements non tenus, RDV manqués côté service).",
        "Toute pièce établissant le défaut d'accès effectif à la justice.",
    ],

    /* --- Garde-fous --- */
    'garde_fous' => [
        "Séparation absolue d'avec le volet logement (adversaire, pièces et stratégie différents).",
        "La stratégie de plaidoirie relève d'un avocat spécialisé (mineurs / famille) — le cadre l'oriente, il décide.",
        "Boussole : l'intérêt de l'enfant. On ne met jamais un enfant en difficulté pour gagner un point de procédure.",
        "Confidentialité renforcée (données d'enfants) — RGPD strict, accès très restreint.",
    ],

    /* --- À faire ensemble (prochaine étape) --- */
    'a_faire' => [
        "Fabrice fournit : les conclusions exactes de la juge (classement) + le dossier pièce par pièce (dont le dossier Gourdien-ASE à archiver, non terminé).",
        "On construit alors, par personne, une fiche ASE séparée (slug « <nom>-ase », champ 'volet') sans jamais toucher au volet logement.",
        "On formalise la méthode en un mode opératoire réutilisable pour d'autres familles.",
    ],
];
