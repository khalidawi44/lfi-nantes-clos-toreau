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

    // ——— Modèle (à remplir quand on analyse un vrai email) ———
    // 'fadiga' => [
    //     'titre'      => 'Mme Fadiga — réponse de M. Morineau',
    //     'dossier_id' => 1,
    //     'date'       => '2026-06-29',
    //     'emails'     => [
    //         [
    //             'sens'  => 'recu',
    //             'de'    => 'yvonnic.morineau@nmh.fr',
    //             'date'  => '2026-06-29 09:19',
    //             'objet' => 'Re : logement de Mme Fadiga',
    //             'corps' => "Colle ici le texte de l'email reçu…",
    //         ],
    //     ],
    //     'analyse'    => "Analyse rédigée par Claude : manquements juridiques + professionnalisme…",
    // ],

];
