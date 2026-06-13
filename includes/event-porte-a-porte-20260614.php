<?php
/**
 * Création unique de l'événement « Porte-à-porte au Clos Toreau » du 14 juin 2026.
 *
 * Détecte automatiquement le CPT « Événements » du thème AG Starter (ou
 * d'un autre plugin d'événements connu), insère l'événement, puis bloque la
 * récidive via un flag. Si aucun CPT d'événement n'est trouvé, l'événement
 * est créé en brouillon pour ne pas polluer le blog.
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_PORTE_A_PORTE_2026_06_14_FLAG = 'lfi_nct_event_porte_a_porte_20260614';

add_action('init', 'lfi_nct_create_porte_a_porte_event', 20);
function lfi_nct_create_porte_a_porte_event() {
    if (get_option(LFI_NCT_PORTE_A_PORTE_2026_06_14_FLAG) === 'done') return;

    $title    = 'Porte-à-porte au Clos Toreau';
    $event_dt = '2026-06-14 13:30:00';
    $location = 'Rue de Biarritz, Clos Toreau, Nantes Sud';
    $content  = "Action porte-à-porte du Groupe d'Action LFI Nantes Sud Clos Toreau.\n\n"
              . "📅 Dimanche 14 juin 2026 — rendez-vous à <strong>13h30</strong>.\n\n"
              . "📍 <strong>" . $location . "</strong>.\n\n"
              . "On va à la rencontre des habitant·es pour échanger sur le logement, "
              . "récolter les témoignages avec le formulaire d'enquête, et faire connaître "
              . "le groupe d'action. Apportez de l'eau, on prévoit les flyers et les formulaires.";

    // CPT « Événements » : on essaie les slugs courants.
    $candidates = [
        'evenement', 'evenements',
        'event', 'events',
        'ag_event', 'ag_evenement', 'ag_events',
        'tribe_events', 'mec-events',
        'mobilisation', 'mobilisations',
    ];
    $post_type   = 'post';
    $found_event = false;
    foreach ($candidates as $c) {
        if (post_type_exists($c)) {
            $post_type   = $c;
            $found_event = true;
            break;
        }
    }

    $post_id = wp_insert_post([
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => $found_event ? 'publish' : 'draft',
        'post_type'    => $post_type,
        'post_date'    => $event_dt,
        'post_author'  => 1,
    ], true);

    if (is_wp_error($post_id) || !$post_id) {
        // Échec : on retentera au prochain init.
        return;
    }

    // Remplit les meta-fields courants utilisés par les plugins d'événements.
    foreach (['event_date', 'date_evenement', '_event_start_date', 'start_date',
              'event_start_date', '_EventStartDate', 'mec_event_date'] as $k) {
        update_post_meta($post_id, $k, $event_dt);
    }
    foreach (['event_location', 'lieu', 'location', '_event_location',
              '_EventVenue', 'mec_event_location'] as $k) {
        update_post_meta($post_id, $k, $location);
    }
    foreach (['event_time', 'heure', '_event_time', '_EventStartTime'] as $k) {
        update_post_meta($post_id, $k, '13:30');
    }

    update_option(LFI_NCT_PORTE_A_PORTE_2026_06_14_FLAG, 'done', false);
}
