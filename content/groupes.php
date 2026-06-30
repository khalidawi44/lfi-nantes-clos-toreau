<?php
/**
 * ANNUAIRE DES GROUPES D'ACTION (réseau multi-GA).
 *
 * Première brique du déploiement « une app par groupe d'action » sur le même
 * site : on liste ici les GA. Chacun pourra, à terme, avoir son espace cloisonné
 * (Phase 2) et ses modules. Pour l'instant cet annuaire documente le réseau.
 *
 * Champs : slug (identifiant court), nom, secteur, uuid (Action Populaire),
 * travaux (volet travaux activé ?), referent (qui le porte), actuel (le GA de
 * ce site).
 */
if (!defined('ABSPATH')) exit;

return [
    [
        'slug'     => 'clos-toreau',
        'nom'      => 'GA LFI Nantes Sud – Clos Toreau',
        'secteur'  => 'Nantes Sud / Clos Toreau',
        'uuid'     => '',
        'travaux'  => true,
        'referent' => 'Fabrice Doucet',
        'actuel'   => true,
    ],
    [
        'slug'     => 'port-boyer',
        'nom'      => 'GA Port-Boyer – Beaujoire – Halvêque',
        'secteur'  => 'Nantes : Beaujoire, Halvêque, Port-Boyer, Saint-Joseph de Porterie',
        'uuid'     => 'ac7b7ac7-aefc-4975-8259-969d769e3311',
        'travaux'  => true,
        'referent' => '',
        'actuel'   => false,
    ],
    [
        'slug'     => 'nantes-nord',
        'nom'      => 'GA Nantes Nord',
        'secteur'  => 'Nantes Nord (44300)',
        'uuid'     => 'bde42d1b-793d-40b1-bfff-3ff4b84b8e8b',
        'travaux'  => true,
        'referent' => '',
        'actuel'   => false,
    ],
    [
        'slug'     => 'reze',
        'nom'      => 'Groupe d\'action de Rezé',
        'secteur'  => 'Rezé (44400)',
        'uuid'     => '76d00d51-260c-4c55-86df-f9ac98b55339',
        'travaux'  => true,
        'referent' => '',
        'actuel'   => false,
    ],
    [
        'slug'     => 'saint-sebastien',
        'nom'      => 'Groupe d\'action de Saint-Sébastien-sur-Loire',
        'secteur'  => 'Saint-Sébastien-sur-Loire (44)',
        'uuid'     => '72e32f3f-da85-40ad-b55a-2a6f38c5e0fe',
        'travaux'  => true,
        'referent' => '',
        'actuel'   => false,
    ],
];
