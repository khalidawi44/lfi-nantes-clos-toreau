<?php
/**
 * Conférence « Municipales 2026 : la géographie sociale du vote à Nantes »
 * Mercredi 8 juillet 2026 · 18h30
 * Pôle associatif Désiré Colombe — salle Flora Tristan
 * 8 rue Arsène Leloup, 44100 Nantes
 *
 * - Auto-publication de l'événement dans le CPT du thème (ag_evenement)
 *   ou du plugin (lfi_evenement) au prochain hit, avec image de couverture
 *   importée dans la médiathèque.
 * - Auto-publication d'un article LFI qui explique le thème de la conf
 *   et incite à venir.
 *
 * Le force-create est idempotent : il s'arrête dès qu'il a trouvé / créé
 * les deux posts (marqueur lfi_nct_event_municipales_done).
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_CONF_MUNI_SLUG_EVT  = 'conference-municipales-2026-jean-riviere';
const LFI_NCT_CONF_MUNI_SLUG_NEWS = 'municipales-2026-geographie-sociale-vote-nantes';
const LFI_NCT_CONF_MUNI_FLAG      = 'lfi_nct_event_municipales_done';
const LFI_NCT_CONF_MUNI_DATE      = '2026-07-08';
const LFI_NCT_CONF_MUNI_TIME      = '18h30';
const LFI_NCT_CONF_MUNI_END       = '20h30';
const LFI_NCT_CONF_MUNI_PLACE     = 'Pôle associatif Désiré Colombe — salle Flora Tristan';
const LFI_NCT_CONF_MUNI_ADDRESS   = '8 rue Arsène Leloup, 44100 Nantes';
const LFI_NCT_CONF_MUNI_CITY      = 'Nantes';
const LFI_NCT_CONF_MUNI_TITLE     = 'Municipales 2026 : la géographie sociale du vote à Nantes';

/* ============================================================== *
 *  Au prochain init, créer l'événement + l'article s'ils n'existent
 *  pas déjà dans la base.
 * ============================================================== */
add_action('init', 'lfi_nct_event_municipales_create', 60);
function lfi_nct_event_municipales_create() {
    if (get_option(LFI_NCT_CONF_MUNI_FLAG) === '1') return;

    /* Choisit le CPT actif : thème en priorité, fallback plugin */
    $cpt = post_type_exists('ag_evenement') ? 'ag_evenement'
         : (post_type_exists('lfi_evenement') ? 'lfi_evenement' : null);

    $event_id = null;
    if ($cpt) {
        $existing = get_page_by_path(LFI_NCT_CONF_MUNI_SLUG_EVT, OBJECT, $cpt);
        if ($existing) {
            $event_id = (int) $existing->ID;
        } else {
            $content = lfi_nct_event_municipales_content_html();
            $event_id = wp_insert_post([
                'post_type'    => $cpt,
                'post_status'  => 'publish',
                'post_title'   => LFI_NCT_CONF_MUNI_TITLE,
                'post_name'    => LFI_NCT_CONF_MUNI_SLUG_EVT,
                'post_content' => $content,
                'post_excerpt' => 'Conférence-formation avec Jean Rivière (IGARUN / Nantes Université) sur la géographie sociale du vote à Nantes — un décryptage des dernières municipales, données et cartes à l\'appui.',
                'post_author'  => 1,
                'comment_status'=> 'closed',
                'ping_status'   => 'closed',
            ], true);

            if (!is_wp_error($event_id) && $event_id) {
                /* Méta du thème AG Starter (et fallback plugin) */
                update_post_meta($event_id, '_ag_event_date',  LFI_NCT_CONF_MUNI_DATE);
                update_post_meta($event_id, '_ag_event_time',  LFI_NCT_CONF_MUNI_TIME);
                update_post_meta($event_id, '_ag_event_end',   LFI_NCT_CONF_MUNI_END);
                update_post_meta($event_id, '_ag_event_place', LFI_NCT_CONF_MUNI_PLACE);
                update_post_meta($event_id, '_ag_event_city',  LFI_NCT_CONF_MUNI_CITY);
                update_post_meta($event_id, '_ag_event_address', LFI_NCT_CONF_MUNI_ADDRESS);
                /* Marqueur LFI pour ne pas être confondu avec un démo du thème */
                update_post_meta($event_id, '_lfi_evt_origin_id', 'conference-municipales-20260708');
                update_post_meta($event_id, '_lfi_evt_internal', 1);

                /* Image de couverture depuis l'asset du plugin */
                $att_id = lfi_nct_event_municipales_import_cover($event_id);
                if ($att_id) set_post_thumbnail($event_id, $att_id);
            } else {
                $event_id = null;
            }
        }
    }

    /* Article qui explique le thème */
    $news_existing = get_page_by_path(LFI_NCT_CONF_MUNI_SLUG_NEWS, OBJECT, 'post');
    $news_id = null;
    if ($news_existing) {
        $news_id = (int) $news_existing->ID;
    } else {
        $news_id = wp_insert_post([
            'post_type'    => 'post',
            'post_status'  => 'publish',
            'post_title'   => 'Municipales 2026 à Nantes : qui a voté quoi, et où ?',
            'post_name'    => LFI_NCT_CONF_MUNI_SLUG_NEWS,
            'post_content' => lfi_nct_event_municipales_article_html($event_id),
            'post_excerpt' => 'Le 8 juillet, le Groupe d\'Action LFI invite Jean Rivière (IGARUN) pour une conférence-discussion sur la géographie sociale du vote à Nantes. Décryptage des dernières municipales, cartes et données à l\'appui.',
            'post_author'  => 1,
            'comment_status'=> 'closed',
            'ping_status'   => 'closed',
        ], true);
        if (is_wp_error($news_id)) $news_id = null;
        if ($news_id) {
            update_post_meta($news_id, '_lfi_news_origin', 'conference-municipales-20260708');
            $att_id = $event_id ? get_post_thumbnail_id($event_id) : lfi_nct_event_municipales_import_cover($news_id);
            if ($att_id) set_post_thumbnail($news_id, $att_id);
            /* Catégorise comme « formation » si la catégorie existe */
            wp_set_post_categories($news_id, [1], true); // 1 = Uncategorized par défaut
        }
    }

    /* Si les deux sont en place, on pose le flag pour ne plus relancer */
    if ($event_id && $news_id) {
        update_option(LFI_NCT_CONF_MUNI_FLAG, '1', false);
    }
}

/* Importe l'image de l'asset du plugin dans la médiathèque WP. */
function lfi_nct_event_municipales_import_cover($parent_post_id) {
    $asset = LFI_NCT_PATH . 'assets/img/conference-municipales-20260708.png';
    if (!file_exists($asset)) return 0;

    /* Si déjà importée (par filename), réutilise */
    $existing = get_posts([
        'post_type'   => 'attachment',
        'meta_key'    => '_lfi_evt_cover_key',
        'meta_value'  => 'conference-municipales-20260708',
        'numberposts' => 1,
        'fields'      => 'ids',
    ]);
    if (!empty($existing)) return (int) $existing[0];

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $upload = wp_upload_dir();
    if (!empty($upload['error'])) return 0;

    $filename = 'conference-municipales-20260708.png';
    $target   = trailingslashit($upload['path']) . wp_unique_filename($upload['path'], $filename);
    if (!@copy($asset, $target)) return 0;

    $att = [
        'guid'           => trailingslashit($upload['url']) . basename($target),
        'post_mime_type' => 'image/png',
        'post_title'     => 'Affiche — Conférence Municipales 2026, Jean Rivière',
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];
    $att_id = wp_insert_attachment($att, $target, $parent_post_id);
    if (is_wp_error($att_id) || !$att_id) return 0;

    $meta = wp_generate_attachment_metadata($att_id, $target);
    wp_update_attachment_metadata($att_id, $meta);
    update_post_meta($att_id, '_lfi_evt_cover_key', 'conference-municipales-20260708');
    return $att_id;
}

/* Corps HTML de l'événement (compatible Gutenberg) */
function lfi_nct_event_municipales_content_html() {
    ob_start(); ?>
<p><strong>Formation–discussion pour décrypter, carte et données à l'appui, les résultats des dernières municipales à Nantes.</strong></p>

<p>On a tous une intuition sur ce qui s'est joué dans les urnes en mars. Et si on confrontait nos impressions aux chiffres pour Nantes ?</p>

<h3>Avec Jean Rivière</h3>
<p>Maître de conférences en géographie à l'IGARUN (Nantes Université). Ses recherches portent sur la <strong>géographie sociale et politique du vote</strong> : comment les recompositions sociologiques des espaces résidentiels — des centres urbains aux mondes ruraux — et les politiques d'urbanisation façonnent les comportements électoraux et les inégalités.</p>

<h3>📅 Quand &amp; où ?</h3>
<ul>
  <li><strong>Mercredi 8 juillet 2026 · 18h30</strong></li>
  <li>Pôle associatif Désiré Colombe — salle Flora Tristan</li>
  <li>8 rue Arsène Leloup, 44100 Nantes</li>
</ul>

<p><em>Conférence interne LFI 44, ouverte aux sympathisant·es. Entrée libre, sans inscription.</em></p>

<p>À mercredi 8 juillet ! ✊</p>
<?php
    return ob_get_clean();
}

/* Corps HTML de l'article qui explique le thème de la conférence */
function lfi_nct_event_municipales_article_html($event_id) {
    $event_url = $event_id ? get_permalink($event_id) : '';
    ob_start(); ?>
<p><strong>Le 8 juillet, le Groupe d'Action LFI invite Jean Rivière, géographe à l'IGARUN (Nantes Université), pour une conférence-discussion sur la géographie sociale du vote à Nantes.</strong> Une lecture des dernières municipales appuyée sur la cartographie et les données quantitatives.</p>

<h2>Comprendre le vote n'est pas qu'une affaire de sondages</h2>
<p>Une élection ne se joue pas que sur les programmes ou les face-à-face télévisés. <strong>Le vote a une géographie</strong> : il varie d'un quartier à l'autre, d'un immeuble à l'autre parfois. Cette répartition n'est pas le fruit du hasard — elle reflète des trajectoires sociales, des politiques d'urbanisation, des recompositions résidentielles parfois lentes, parfois brutales.</p>

<p>Comprendre où sont nos électeur·ices et comment le vote se distribue dans Nantes, c'est se donner les moyens de penser une stratégie politique enracinée et adaptée aux réalités du terrain — à commencer par les quartiers populaires comme <strong>le Clos Toreau</strong>.</p>

<h2>Pourquoi cette conférence est précieuse pour notre GA</h2>
<p>Au Groupe d'Action LFI Nantes Sud Clos Toreau, nous menons l'enquête de voisinage sur le logement, nous faisons du porte-à-porte, nous mobilisons sur les coupures d'eau chaude. Ce travail de terrain est <strong>indissociable d'une analyse des dynamiques électorales</strong> du quartier et de la métropole.</p>

<p>Cette formation va nous permettre :</p>
<ul>
  <li><strong>De resituer notre travail au Clos Toreau</strong> dans la géographie sociale plus large de Nantes ;</li>
  <li>De <strong>repérer les zones où la mobilisation peut faire basculer un résultat</strong> en 2026 ;</li>
  <li>De <strong>confronter nos intuitions militantes</strong> aux données empiriques sur les comportements électoraux ;</li>
  <li>De <strong>nourrir nos arguments</strong> dans la perspective des municipales 2026.</li>
</ul>

<h2>Qui est Jean Rivière ?</h2>
<p>Jean Rivière est <strong>maître de conférences en géographie</strong> à l'Institut de Géographie et d'Aménagement Régional de l'Université de Nantes (IGARUN). Ses travaux portent depuis plus de dix ans sur les recompositions sociologiques des espaces résidentiels — des centres urbains gentrifiés aux espaces périurbains et ruraux — et leur influence sur les comportements politiques. Il a notamment publié sur la géographie du vote Macron, du vote Rassemblement National, et sur les espaces ouvriers qui basculent.</p>

<p>Ses analyses sont régulièrement reprises dans <em>Le Monde</em>, <em>Le Monde diplomatique</em>, <em>Mediapart</em>. Son intervention au GA sera <strong>centrée sur les données de Nantes</strong> et des dernières échéances électorales.</p>

<h2>📅 Pour ne rien rater</h2>
<ul>
  <li><strong>Mercredi 8 juillet 2026 · 18h30 — 20h30</strong></li>
  <li>Pôle associatif <strong>Désiré Colombe</strong> · salle <strong>Flora Tristan</strong></li>
  <li>8 rue Arsène Leloup, 44100 Nantes (tram L1 arrêt Médiathèque ou L2 arrêt Mendès-France)</li>
</ul>

<p><em>Conférence interne LFI 44 — ouverte aux sympathisant·es. Entrée libre, sans inscription préalable.</em></p>

<?php if ($event_url): ?>
<p style="margin-top:1.5em">
  <a href="<?php echo esc_url($event_url); ?>" style="display:inline-block;background:#c8102e;color:#fff;text-decoration:none;padding:14px 22px;border-radius:8px;font-weight:700">📅 Voir l'événement dans l'agenda →</a>
</p>
<?php endif; ?>

<p style="margin-top:1.5em;font-size:.92em;color:#555"><em>Partagez cet événement dans le quartier, autour de vous, dans vos réseaux. Plus on est nombreux·ses, plus la discussion sera riche.</em></p>
<?php
    return ob_get_clean();
}
