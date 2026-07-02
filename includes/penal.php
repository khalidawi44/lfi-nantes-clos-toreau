<?php
/**
 * VOLET PÉNAL — analyse & désamorçage.
 *
 * RÈGLE (posée par Fabrice) : dans CHAQUE réponse à un courrier reçu, on
 * analyse aussi le volet pénal. Si l'interlocuteur (bailleur, institution)
 * cherche à INTIMIDER un·e locataire, ou tente un CONTOURNEMENT ILLÉGAL —
 * dans la procédure ou dans les propos — on l'intègre dans l'e-mail pour
 * DÉSAMORCER sa tentative (rappel factuel + cadre légal), fermement mais
 * sans injure ni menace de poursuite personnelle.
 *
 * Ligne de conduite (Me Gouache) : on RAPPELLE le cadre légal (y compris
 * pénal) à titre d'information — on n'engage PAS d'acte judiciaire ici ;
 * le judiciaire passe par l'avocat. Le but est de couper court à la
 * manœuvre, pas de porter plainte dans l'e-mail.
 *
 * v1 : heuristique par signaux (gratuit, sans clé). L'interface (flags +
 * paragraphe) ne changera pas quand on branchera l'IA.
 */
if (!defined('ABSPATH')) exit;

/**
 * Scanne un texte reçu et renvoie les signaux « pénal » détectés.
 *
 * @param string $text Le message reçu (bailleur / institution).
 * @return array Liste de flags : [ ['code'=>..,'label'=>..,'parade'=>..], .. ]
 */
function lfi_nct_penal_scan($text) {
    $t = ' ' . mb_strtolower((string) $text) . ' ';
    $has = function ($needles) use ($t) {
        foreach ((array) $needles as $n) if (mb_strpos($t, ' ' . $n, 0) !== false || mb_strpos($t, $n) !== false) return true;
        return false;
    };
    $flags = [];

    /* 1) INTIMIDATION du locataire (menace de sanction pour le faire taire). */
    if ($has(['expulsion', 'résiliation du bail', 'resiliation du bail', 'congé', 'conge ', 'huissier', 'commissaire de justice', 'poursuites', 'vous vous exposez', 'à vos torts', 'a vos torts', 'à vos frais', 'a vos frais', 'trouble de voisinage', 'mise en demeure de quitter'])) {
        $flags[] = [
            'code'   => 'intimidation',
            'label'  => 'Tentative d\'intimidation du locataire',
            'parade' => "Je relève une formulation qui s'apparente à une pression sur la personne accompagnée alors qu'elle ne fait qu'exercer ses droits. Toute mesure de rétorsion ou pression liée à un signalement d'insalubrité est prohibée ; je la consigne. Un locataire de bonne foi qui signale un logement non décent ne peut en être pénalisé.",
        ];
    }

    /* 2) CONTOURNEMENT : requalifier en « charge locative » des désordres
          structurels pour échapper à l'obligation de décence. */
    if ($has(['charge locative', 'incombe au locataire', 'entretien courant', 'à la charge du locataire', 'a la charge du locataire', 'assurance habitation', 'contrat d\'entretien', 'contrats d\'entretien'])) {
        $flags[] = [
            'code'   => 'requalif_charge_locative',
            'label'  => 'Report abusif sur le locataire (charge locative)',
            'parade' => "Je conteste le report de désordres structurels sur la charge locative : un défaut affectant la structure, l'étanchéité, les équipements collectifs ou la salubrité relève de l'obligation de délivrance d'un logement décent (art. 1719 du Code civil, art. 6 de la loi du 6 juillet 1989, décret n° 2002-120). Le décret « réparations locatives » n° 87-712 ne couvre pas ces désordres.",
        ];
    }

    /* 3) CONTOURNEMENT : nier l'existence d'un signalement / d'une saisine. */
    if ($has(['pas fait l\'objet d\'un signalement', 'aucun signalement', 'n\'avons pas de constat', 'n\'a pas signalé', 'n a pas signale', 'absence de confirmation', 'en l\'absence de confirmation'])) {
        $flags[] = [
            'code'   => 'deni_signalement',
            'label'  => 'Négation d\'un signalement / d\'une saisine existante',
            'parade' => "Je rectifie : le signalement existe et le Service d'Hygiène de Nantes Métropole est saisi. Un bailleur informé d'une situation d'insalubrité qui s'abstient d'agir engage sa responsabilité (art. L.1337-4 du Code de la santé publique ; art. 225-14 du Code pénal sur la soumission à des conditions d'hébergement indignes).",
        ];
    }

    /* 4) CONTOURNEMENT : tenter de joindre le locataire directement / obtenir
          ses coordonnées, en contournant l'interlocuteur unique. */
    if ($has(['coordonnées de la locataire', 'coordonnees de la locataire', 'coordonnées du locataire', 'contacter directement', 'joindre directement', 'numéro de la locataire', 'numero de la locataire', 'ses coordonnées', 'ses coordonnees'])) {
        $flags[] = [
            'code'   => 'contournement_interlocuteur',
            'label'  => 'Tentative de contourner l\'interlocuteur unique',
            'parade' => "Je suis l'interlocuteur unique de la personne accompagnée, qui m'a mandaté. Ses coordonnées ne seront pas communiquées (RGPD) ; tout contact et tout accès au logement se font par mon intermédiaire et en ma présence. Merci de passer exclusivement par moi.",
        ];
    }

    /* 5) CONTOURNEMENT : conditionner l'intervention à une démarche indue
          (assurance du locataire pour une origine bailleur/indéterminée). */
    if ($has(['via son assurance', 'via votre assurance', 'déclarer à votre assurance', 'declarer a votre assurance', 'origine inconnue', 'origine indéterminée', 'origine indeterminee'])) {
        $flags[] = [
            'code'   => 'report_assurance',
            'label'  => 'Renvoi indu vers l\'assurance du locataire',
            'parade' => "Un désordre d'origine indéterminée et antérieure ne peut être imputé au locataire ni renvoyé à son assurance : la recherche de fuite et la remise en état relèvent du bailleur au titre de son obligation d'entretien (art. 1719 et 1720 du Code civil).",
        ];
    }

    return $flags;
}

/**
 * Construit le paragraphe « désamorçage pénal » à insérer dans une réponse,
 * à partir des flags détectés. Renvoie '' si rien à signaler.
 *
 * @param string $text Le message reçu.
 * @return string Bloc de texte prêt à insérer (ou '').
 */
function lfi_nct_penal_paragraphe($text) {
    $flags = lfi_nct_penal_scan($text);
    if (empty($flags)) return '';
    $lines = [];
    foreach ($flags as $f) $lines[] = '- ' . $f['parade'];
    return "Sur le cadre légal (rappel, sans engager à ce stade d'action judiciaire) :\n" . implode("\n", $lines);
}

/**
 * Libellés courts des flags pénaux (pour affichage « lecture pénale »).
 * @return string[] labels
 */
function lfi_nct_penal_labels($text) {
    $out = [];
    foreach (lfi_nct_penal_scan($text) as $f) $out[] = $f['label'];
    return $out;
}
