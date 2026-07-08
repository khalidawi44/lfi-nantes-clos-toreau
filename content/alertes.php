<?php
/**
 * ALERTES MANUELLES « À FAIRE » (affichées en haut de l'écran d'accueil).
 *
 * Quand tu me signales quelque chose (« j'ai reçu un email de NMH pour Mme X »),
 * j'ajoute ici une entrée qui apparaît tout de suite sur l'accueil avec un lien
 * qui t'envoie DIRECTEMENT au bon endroit. Une fois l'action faite, tu me le dis
 * et je retire l'entrée.
 *
 * Format d'une alerte :
 *   [
 *     'titre'     => 'Texte court de l'action à faire',
 *     'detail'    => 'Précision (optionnel)',
 *     'priorite'  => 'haute' | 'moyenne' | 'basse',
 *     // destination : soit 'route' (+ 'args' + 'anchor'), soit 'url' direct
 *     'route'     => 'dossier-juridique-edit',
 *     'args'      => ['id' => 1],
 *     'anchor'    => 'sec-emails',   // optionnel (ancre dans la page)
 *   ]
 *
 * Routes utiles :
 *   - dossier-juridique-edit (args id)        → le dossier (ancres : sec-emails, sec-analyse)
 *   - dossier-send-email (args id, letter)    → écrire un courrier (letter : schs, ars,
 *                                               lrar_travaux, lrar_relogement, reponse_nmh)
 *   - prefecture / prefecture-email           → volet préfecture
 *   - dossier-wizard                          → nouveau dossier guidé
 */
if (!defined('ABSPATH')) exit;

return [
    // (aucune alerte manuelle pour l'instant — j'en ajoute quand tu me signales quelque chose)
];
