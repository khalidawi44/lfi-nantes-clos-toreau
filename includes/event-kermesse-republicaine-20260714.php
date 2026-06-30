<?php
/**
 * Kermesse Républicaine — Nantes Sud
 * Mardi 14 juillet 2026 · 14h – 23h
 * Parc de la Crapaudine (Nantes Sud)
 *
 * Jeux et animations pour enfants, banquet à prix libre, concert,
 * diffusion de match. Avec les députés LFI Ségolène Amiot et Andy Kerbrat.
 *
 * Auto-publication idempotente dans le CPT du thème (ag_evenement) ou du
 * plugin (lfi_evenement), avec l'affiche importée en image de couverture.
 *
 * Source : https://actionpopulaire.fr/evenements/60576337-c7f9-45a7-b322-c84080173b0c/
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_KERMESSE_SLUG    = 'kermesse-republicaine-nantes-sud-2026';
const LFI_NCT_KERMESSE_FLAG    = 'lfi_nct_event_kermesse_2026_done';
const LFI_NCT_KERMESSE_DATE    = '2026-07-14';
const LFI_NCT_KERMESSE_TIME    = '14h';
const LFI_NCT_KERMESSE_END     = '23h';
const LFI_NCT_KERMESSE_PLACE   = 'Parc de la Crapaudine';
const LFI_NCT_KERMESSE_ADDRESS = 'Parc de la Crapaudine, 44200 Nantes (Nantes Sud)';
const LFI_NCT_KERMESSE_CITY    = 'Nantes';
const LFI_NCT_KERMESSE_TITLE   = 'Kermesse Républicaine — Nantes Sud';
const LFI_NCT_KERMESSE_AP_URL  = 'https://actionpopulaire.fr/evenements/60576337-c7f9-45a7-b322-c84080173b0c/';

/* ============================================================== *
 *  Au prochain init, créer l'événement s'il n'existe pas déjà.
 * ============================================================== */
add_action('init', 'lfi_nct_event_kermesse_create', 60);
function lfi_nct_event_kermesse_create() {
    if (get_option(LFI_NCT_KERMESSE_FLAG) === '1') return;

    /* Choisit le CPT actif : thème en priorité, fallback plugin */
    $cpt = post_type_exists('ag_evenement') ? 'ag_evenement'
         : (post_type_exists('lfi_evenement') ? 'lfi_evenement' : null);
    if (!$cpt) return;

    $existing = get_page_by_path(LFI_NCT_KERMESSE_SLUG, OBJECT, $cpt);
    if ($existing) {
        update_option(LFI_NCT_KERMESSE_FLAG, '1', false);
        return;
    }

    $event_id = wp_insert_post([
        'post_type'     => $cpt,
        'post_status'   => 'publish',
        'post_title'    => LFI_NCT_KERMESSE_TITLE,
        'post_name'     => LFI_NCT_KERMESSE_SLUG,
        'post_content'  => lfi_nct_event_kermesse_content_html(),
        'post_excerpt'  => 'Kermesse Républicaine le 14 juillet au Parc de la Crapaudine (Nantes Sud), de 14h à 23h : jeux et animations pour enfants, banquet à prix libre, concert, diffusion de match. Avec les députés LFI Ségolène Amiot et Andy Kerbrat.',
        'post_author'   => 1,
        'comment_status'=> 'closed',
        'ping_status'   => 'closed',
    ], true);

    if (is_wp_error($event_id) || !$event_id) return;

    /* Méta du thème AG Starter (et fallback plugin) */
    update_post_meta($event_id, '_ag_event_date',    LFI_NCT_KERMESSE_DATE);
    update_post_meta($event_id, '_ag_event_time',    LFI_NCT_KERMESSE_TIME);
    update_post_meta($event_id, '_ag_event_end',     LFI_NCT_KERMESSE_END);
    update_post_meta($event_id, '_ag_event_place',   LFI_NCT_KERMESSE_PLACE);
    update_post_meta($event_id, '_ag_event_city',    LFI_NCT_KERMESSE_CITY);
    update_post_meta($event_id, '_ag_event_address', LFI_NCT_KERMESSE_ADDRESS);
    /* Marqueur LFI + lien Action Populaire */
    update_post_meta($event_id, '_lfi_evt_origin_id', 'kermesse-republicaine-20260714');
    update_post_meta($event_id, '_lfi_evt_internal', 1);
    update_post_meta($event_id, '_lfi_evt_ap_url', LFI_NCT_KERMESSE_AP_URL);

    /* Image de couverture (l'affiche) depuis l'asset du plugin */
    $att_id = lfi_nct_event_kermesse_import_cover($event_id);
    if ($att_id) set_post_thumbnail($event_id, $att_id);

    update_option(LFI_NCT_KERMESSE_FLAG, '1', false);
}

/* Importe l'affiche de l'asset du plugin dans la médiathèque WP. */
function lfi_nct_event_kermesse_import_cover($parent_post_id) {
    $asset = LFI_NCT_PATH . 'assets/img/kermesse-republicaine-20260714.jpg';
    if (!file_exists($asset)) return 0;

    /* Si déjà importée (par clé), réutilise */
    $existing = get_posts([
        'post_type'   => 'attachment',
        'meta_key'    => '_lfi_evt_cover_key',
        'meta_value'  => 'kermesse-republicaine-20260714',
        'numberposts' => 1,
        'fields'      => 'ids',
    ]);
    if (!empty($existing)) return (int) $existing[0];

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $upload = wp_upload_dir();
    if (!empty($upload['error'])) return 0;

    $filename = 'kermesse-republicaine-20260714.jpg';
    $target   = trailingslashit($upload['path']) . wp_unique_filename($upload['path'], $filename);
    if (!@copy($asset, $target)) return 0;

    $att = [
        'guid'           => trailingslashit($upload['url']) . basename($target),
        'post_mime_type' => 'image/jpeg',
        'post_title'     => 'Affiche — Kermesse Républicaine, Nantes Sud, 14 juillet 2026',
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];
    $att_id = wp_insert_attachment($att, $target, $parent_post_id);
    if (is_wp_error($att_id) || !$att_id) return 0;

    $meta = wp_generate_attachment_metadata($att_id, $target);
    wp_update_attachment_metadata($att_id, $meta);
    update_post_meta($att_id, '_lfi_evt_cover_key', 'kermesse-republicaine-20260714');
    return $att_id;
}

/* Corps HTML de l'événement (compatible Gutenberg) */
function lfi_nct_event_kermesse_content_html() {
    ob_start(); ?>
<p><strong>Venez fêter le 14 juillet en famille à Nantes Sud&nbsp;!</strong> Le Groupe d'Action LFI Nantes Sud Clos Toreau vous donne rendez-vous au <strong>Parc de la Crapaudine</strong> pour une grande <strong>Kermesse Républicaine</strong>, de 14h à 23h.</p>

<h3>🎉 Au programme</h3>
<ul>
  <li>🎈 <strong>Jeux et animations pour les enfants</strong></li>
  <li>🍽️ <strong>Banquet à prix libre</strong></li>
  <li>🎶 <strong>Concert</strong></li>
  <li>📺 <strong>Diffusion de match</strong></li>
</ul>

<p>Avec les député·es LFI <strong>Ségolène Amiot</strong> et <strong>Andy Kerbrat</strong>.</p>

<h3>📅 Quand &amp; où&nbsp;?</h3>
<ul>
  <li><strong>Mardi 14 juillet 2026 · 14h – 23h</strong></li>
  <li>Parc de la Crapaudine — Nantes Sud (44200 Nantes)</li>
</ul>

<p><em>Entrée libre, ouvert à toutes et tous. Amenez vos proches, vos voisin·es, le quartier&nbsp;!</em> ✊🌹</p>

<p style="margin-top:1.2em"><a href="<?php echo esc_url(LFI_NCT_KERMESSE_AP_URL); ?>" target="_blank" rel="noopener">S'inscrire / voir sur Action Populaire →</a></p>
<?php
    return ob_get_clean();
}
