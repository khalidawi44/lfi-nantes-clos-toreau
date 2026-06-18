<?php
/**
 * Seed one-shot — création de la Réunion publique du 26 juin 2026 dans le CPT
 * lfi_evenement, en s'appuyant sur la fondation Événements (v0.20.0).
 *
 * NB : on garde aussi la page /reunion-26-juin-2026/ (shortcode dédié) en place
 * pour ne pas casser le QR code du tract papier déjà en circulation. Les deux
 * coexistent : la page = URL collée sur le tract (compteur live, formulaire),
 * le CPT = présence dans /evenements/ et dans l'agenda automatique.
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_SEED_REUNION_CPT_FLAG = 'lfi_nct_seed_reunion_26juin_cpt';

add_action('init', 'lfi_nct_seed_reunion_26juin_cpt', 30);
function lfi_nct_seed_reunion_26juin_cpt() {
    if (get_option(LFI_NCT_SEED_REUNION_CPT_FLAG) === 'done') return;
    if (!post_type_exists(LFI_NCT_EVT_CPT)) return; // attend que le CPT soit enregistré

    // Évite la création si une entrée avec ce slug existe déjà
    $existing = get_page_by_path('votre-logement-votre-droit-reunion-26-juin-2026', OBJECT, LFI_NCT_EVT_CPT);
    if ($existing) {
        update_option(LFI_NCT_SEED_REUNION_CPT_FLAG, 'done', false);
        return;
    }

    $content = <<<HTML
<!-- wp:paragraph -->
<p>Depuis plusieurs mois, le Groupe d'Action LFI Nantes Sud Clos Toreau mène une <strong>enquête de voisinage sur l'insalubrité au Clos Toreau</strong> : humidité, moisissures, nuisibles, logements dégradés, coupures d'eau chaude à répétition. Des problèmes que <em>vous</em> subissez.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Il est temps de faire le point ensemble et de <strong>passer à l'action</strong>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Au programme</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol>
<li><strong>Résultats de l'enquête de voisinage</strong> — ce que nous avons constaté dans le quartier, chiffres et témoignages à l'appui.</li>
<li><strong>Vos droits et les recours possibles</strong> — quelles démarches peut-on engager ? On vous explique concrètement ce qui est possible.</li>
<li><strong>Questions / Réponses</strong> — partagez votre situation. Nous sommes là pour vous écouter et vous répondre.</li>
</ol>
<!-- /wp:list -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><strong>VENEZ, PARLEZ, ON VOUS ÉCOUTE.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><em>Réunion ouverte à toutes et tous · Entrée libre · Pas besoin de s'inscrire (mais ça nous aide à prévoir les chaises !)</em></p>
<!-- /wp:paragraph -->
HTML;

    $excerpt = "Le Groupe d'Action LFI Nantes Sud Clos Toreau présente les résultats de l'enquête de voisinage sur l'insalubrité au Clos Toreau et organise la suite avec les habitant·es.";

    $post_id = wp_insert_post([
        'post_type'    => LFI_NCT_EVT_CPT,
        'post_title'   => 'Votre logement, votre droit — Réunion publique',
        'post_name'    => 'votre-logement-votre-droit-reunion-26-juin-2026',
        'post_status'  => 'publish',
        'post_content' => $content,
        'post_excerpt' => $excerpt,
        'post_author'  => 1,
    ], true);

    if (is_wp_error($post_id) || !$post_id) return;

    update_post_meta($post_id, '_lfi_evt_date_debut', '2026-06-26T15:00');
    update_post_meta($post_id, '_lfi_evt_date_fin',   '2026-06-26T17:00');
    update_post_meta($post_id, '_lfi_evt_lieu',       'Salle de Diffusion — Confluences');
    update_post_meta($post_id, '_lfi_evt_adresse',    '4 place du Muguet, 44200 Nantes');
    update_post_meta($post_id, '_lfi_evt_capacite',   '80');
    update_post_meta($post_id, '_lfi_evt_rsvp_actif', '1');
    update_post_meta($post_id, '_lfi_evt_url_ap',     'https://actionpopulaire.fr/evenements/b9e423c3-a850-4d5b-8507-7a979b791299/');

    update_option(LFI_NCT_SEED_REUNION_CPT_FLAG, 'done', false);
}
