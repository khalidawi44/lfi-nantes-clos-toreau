<?php
/**
 * ÉVÉNEMENTS DU GROUPE D'ACTION — géré par Claude Code
 *
 * Pour ajouter / modifier un événement : dis-le à Claude dans la
 * conversation (« ajoute un événement le 12 juillet… »). Claude édite ce
 * fichier et pousse ; l'événement apparaît sur le site (page contenant le
 * shortcode [lfi_nct_evenements]).
 *
 * Format de chaque événement :
 *   'titre'   => string  (obligatoire)
 *   'date'    => 'AAAA-MM-JJ'  (obligatoire)
 *   'heure'   => 'HH:MM'  (optionnel)
 *   'lieu'    => string   (optionnel)
 *   'resume'  => string   (1-2 phrases, optionnel)
 *   'details' => string   (texte long multi-lignes, optionnel)
 *   'lien'    => URL d'inscription (optionnel)
 */
if (!defined('ABSPATH')) exit;

return [

    [
        'titre'   => 'Conférence — Logement social & municipales 2026',
        'date'    => '2026-07-08',
        'heure'   => '19:00',
        'lieu'    => 'Quartier Clos Toreau, Nantes Sud',
        'resume'  => 'Débat public : le logement social au cœur des municipales. Venez poser vos questions.',
        'details' => '',
        'lien'    => '',
    ],

    [
        'titre'   => 'Porte-à-porte — à la rencontre des locataires',
        'date'    => '2026-07-11',
        'heure'   => '14:00',
        'lieu'    => 'Rendez-vous place du Pays Basque, Clos Toreau (Nantes Sud)',
        'resume'  => 'Action de porte-à-porte du Groupe d\'Action : on va à la rencontre des locataires pour recenser les problèmes de logement. RDV à 14h place du Pays Basque.',
        'details' => '',
        'lien'    => '',
    ],

    // ——— Modèle à copier pour un nouvel événement ———
    // [
    //     'titre'   => '',
    //     'date'    => '2026-00-00',
    //     'heure'   => '',
    //     'lieu'    => '',
    //     'resume'  => '',
    //     'details' => '',
    //     'lien'    => '',
    // ],

];
