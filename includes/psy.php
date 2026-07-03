<?php
/**
 * ROBOT PSY — analyse des mécanismes intellectuels & cognitifs.
 *
 * Rôle UNIQUE : lire un écrit (réponse d'une institution, d'un bailleur, d'un
 * locataire) et en produire un RAPPORT structuré — posture, mécanismes,
 * leviers, pièges à éviter, ton conseillé. Il ne décide de rien : il TRANSMET
 * son rapport au robot architecte (strategie / architecte), qui s'en sert pour
 * peaufiner la tactique.
 *
 * v1 : heuristique par signaux (gratuit, sans clé). v2 (plus tard) : brancher
 * l'IA Claude à la place du moteur heuristique — l'interface (rapport) ne change pas.
 */
if (!defined('ABSPATH')) exit;

/**
 * Analyse un texte et renvoie un rapport psy.
 * @param string $text  Le message à analyser.
 * @param string $type  'institution' | 'bailleur' | 'locataire'
 * @return array rapport : posture, label, couleur, mecanismes[], leviers[], a_eviter[], ton
 */
function lfi_nct_psy_analyse($text, $type = 'institution') {
    $t = ' ' . mb_strtolower((string) $text) . ' ';
    /* Match par DÉBUT DE MOT : évite qu'un mot-clé court soit reconnu au milieu
       d'un autre mot (ex. « normal » ne doit PAS matcher « anormal », sens inverse). */
    $has = function ($needles) use ($t) {
        foreach ((array) $needles as $n) {
            if (preg_match('/(?<![\p{L}])' . preg_quote($n, '/') . '/u', $t)) return true;
        }
        return false;
    };

    if ($type === 'locataire') {
        if ($has(['urgent', 'malade', 'enfant', 'bébé', 'peur', 'insupportable', 'danger', 'santé', 'hôpital'])) {
            return lfi_nct_psy_report('detresse', 'Détresse / urgence vécue', '#c8102e',
                ['Charge émotionnelle forte, sentiment d\'insécurité', 'Attente de protection immédiate'],
                ['Réassurer d\'abord (on est là, on agit)', 'Donner une première action concrète très vite', 'Valoriser la preuve (photos, certificat)'],
                ['Minimiser ou noyer sous la procédure', 'Promettre ce qu\'on ne tiendra pas'],
                'Empathie + concret : « voilà ce qu\'on fait, maintenant ».');
        }
        if ($has(['à quoi bon', 'a quoi bon', 'rien ne change', 'j\'abandonne', 'abandonne', 'fatigué', 'découragé', 'decourage', 'sert à rien', 'sert a rien'])) {
            return lfi_nct_psy_report('decouragement', 'Découragement / résignation', '#d39e00',
                ['Résignation apprise (« ça ne changera pas »)', 'Perte du sentiment de pouvoir agir'],
                ['Obtenir une petite victoire rapide et visible', 'Montrer qu\'on avance (dates, preuves)', 'Rendre acteur : une action simple à faire'],
                ['Discours abstrait ou lointain', 'Laisser le silence s\'installer'],
                'Soutien + redonner du pouvoir d\'agir, par étapes courtes.');
        }
        if ($has(['on a l\'habitude', 'habitude', 'c\'est comme ça', 'c est comme ca', 'toujours été', 'normal'])) {
            return lfi_nct_psy_report('normalisation', 'Normalisation (« c\'est comme ça »)', '#0066a3',
                ['Le désordre est vécu comme normal, sous-déclaré', 'Adaptation à l\'anormal'],
                ['Nommer l\'anormalité (« ce n\'est pas normal, c\'est illégal »)', 'Comparer / chiffrer pour objectiver', 'Recueillir quand même la trace'],
                ['Valider implicitement que « c\'est normal »'],
                'Recadrer avec des faits : ce n\'est pas une fatalité, c\'est un manquement.');
        }
        if ($has(['je veux', 'mes droits', 'je ne lâche', 'je ne lache', 'me battre', 'ensemble', 'voisins'])) {
            return lfi_nct_psy_report('combatif', 'Combatif / moteur', '#186a3b',
                ['Fort sentiment de justice, énergie d\'action'],
                ['En faire un relais collectif (mobiliser l\'immeuble)', 'Canaliser vers des actes utiles et cadrés'],
                ['Le laisser partir seul dans le judiciaire prématuré'],
                'Reconnaissance + cadre : « ton énergie, on la met où ça paie ».');
        }
        return lfi_nct_psy_report('neutre_loc', 'À qualifier', '#4b2e83',
            ['Signal encore faible'], ['Poser 2-3 questions ouvertes pour cerner le besoin'],
            ['Sur-interpréter'], 'Écoute active, reformulation.');
    }

    /* Institution / bailleur. */
    if ($has(['refus', 'rejet', 'conteste', 'infondé', 'infonde', 'pas de notre ressort', 'charge locative', 'incombe au locataire', 'non fondé', 'non fonde'])) {
        return lfi_nct_psy_report('defensif', 'Défensif / esquive', '#c8102e',
            ['Protection institutionnelle, évitement de responsabilité', 'Recadrage juridique à son avantage'],
            ['Contre-argumenter point par point, sur preuves', 'Monter d\'un cran (SCHS/ARS/préfecture)', 'Chercher des alliés, jouer le collectif'],
            ['Le frontal isolé sans preuve', 'Répondre à chaud, sur le ton'],
            'Ferme, documenté, factuel — jamais affectif.');
    }
    if ($has(['je ne retrouve pas', 'pouvez-vous', 'pouvez vous', 'sur quelle base', 'préciser', 'preciser', 'dans quelle catégorie', 'catégorie', 'categorie', 'méthode', 'methode', 'comment avez'])) {
        return lfi_nct_psy_report('scrutateur', 'Scrutateur / vérificateur', '#0066a3',
            ['Prudence administrative : veut des données défendables', 'Se couvre avant de s\'engager / de relayer'],
            ['Fournir une donnée carrée (méthode, échantillon)', 'Distinguer le mesuré du déclaratif', 'Rester transparent = gagner sa confiance'],
            ['Approximations, chiffres non étayés', 'Sur-vendre'],
            'Précis et transparent : on donne les bases, pas des slogans.');
    }
    if ($has(['le cas échéant', 'le cas echeant', 'je reviendrai', 'reviens vers vous', 'prendre connaissance', 'je vais étudier', 'je vais etudier', 'transmets', 'transmettre', 'nous étudions', 'nous etudions'])) {
        return lfi_nct_psy_report('temporisation', 'Temporisation polie', '#d39e00',
            ['Gestion du risque : éviter de s\'engager tout de suite', 'Politesse qui ménage toutes les issues'],
            ['Fixer une échéance douce (« sous X jours »)', 'Relancer poliment mais fermement', 'Garder l\'initiative, ne pas dépendre d\'elle'],
            ['Attendre passivement une réponse qui ne vient pas'],
            'Courtois mais daté : on remercie et on propose une prochaine étape.');
    }
    if ($has(['j\'alerte', 'j alerte', 'visite', 'nous allons', 'je programme', 'intervention', 'rendez-vous', 'nous interviendrons', 'je saisis'])) {
        return lfi_nct_psy_report('cooperatif', 'Coopératif / en action', '#186a3b',
            ['Veut montrer qu\'il agit, cherche une issue'],
            ['Capitaliser : demander du concret (date, écrit)', 'Acter par écrit ce qui est promis', 'Rester présent sans lâcher'],
            ['Prendre la coopération pour acquise'],
            'Reconnaissant + concret : « merci, concrètement quelle date ? ».');
    }
    return lfi_nct_psy_report('neutre', 'Posture à préciser', '#4b2e83',
        ['Signaux ambivalents'], ['Reformuler pour faire préciser l\'intention'],
        ['Conclure trop vite'], 'Neutre, on fait préciser.');
}

function lfi_nct_psy_report($posture, $label, $couleur, $mecanismes, $leviers, $a_eviter, $ton) {
    return compact('posture', 'label', 'couleur', 'mecanismes', 'leviers', 'a_eviter', 'ton');
}

/** Rend une carte lisible du rapport psy (réutilisable). */
function lfi_nct_psy_render_card($r, $titre = '🧠 Lecture psy') {
    echo '<div class="lfi-app-card" style="border-left:4px solid ' . esc_attr($r['couleur']) . '">';
    echo '<div class="head"><div class="who">' . esc_html($titre) . ' — ' . esc_html($r['label']) . '</div></div>';
    echo '<div class="com"><strong>Mécanismes :</strong> ' . esc_html(implode(' · ', $r['mecanismes'])) . '</div>';
    echo '<div class="com"><strong>Leviers :</strong> ' . esc_html(implode(' · ', $r['leviers'])) . '</div>';
    echo '<div class="com"><strong>À éviter :</strong> ' . esc_html(implode(' · ', $r['a_eviter'])) . '</div>';
    echo '<div class="com" style="color:#186a3b"><strong>Ton conseillé :</strong> ' . esc_html($r['ton']) . '</div>';
    echo '</div>';
}
