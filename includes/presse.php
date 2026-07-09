<?php
/**
 * ESPACE PRESSE — communiqués de presse + annuaire des contacts médias.
 *
 *  Deux briques :
 *   1) COMMUNIQUÉS : un type de contenu PUBLIC (CPT « lfi_presse ») pensé pour
 *      le référencement. Chaque communiqué a sa VRAIE page indexable
 *      (/communique/<slug>) avec balises SEO complètes : <title>, meta
 *      description, Open Graph / Twitter, lien canonique, et surtout un bloc
 *      JSON-LD « NewsArticle » (schema.org) qui indique aux moteurs et à
 *      Google Actualités qu'il s'agit d'un communiqué de presse daté. Les
 *      pages sont automatiquement dans le sitemap de WordPress.
 *   2) ANNUAIRE PRESSE : une liste ÉDITABLE de contacts médias / réseaux
 *      (Ouest-France, Presse Océan, Télénantes, AFP, référents…). Pour chaque
 *      contact : site, email, X/Twitter, Instagram, Facebook, téléphone.
 *      L'admin diffuse un communiqué en 1 clic (email Gmail pré-rempli + copie
 *      du texte + ouverture des réseaux).
 *
 *  RÈGLE DU PROJET — NE RIEN INVENTER : aucun email ni aucun identifiant de
 *  réseau social n'est pré-rempli d'office. On amorce seulement les NOMS des
 *  médias et leurs sites officiels (faits publics vérifiables). Tout le reste
 *  est « à compléter » par l'admin après vérification.
 */
if (!defined('ABSPATH')) exit;

if (!defined('LFI_NCT_PRESSE_CPT')) define('LFI_NCT_PRESSE_CPT', 'lfi_presse');

/* ============================================================== *
 *  1) CPT COMMUNIQUÉS + SEO                                        *
 * ============================================================== */
add_action('init', 'lfi_nct_presse_register_cpt', 11);
function lfi_nct_presse_register_cpt() {
    if (post_type_exists(LFI_NCT_PRESSE_CPT)) return;
    register_post_type(LFI_NCT_PRESSE_CPT, [
        'labels' => [
            'name'          => 'Communiqués de presse',
            'singular_name' => 'Communiqué de presse',
            'menu_name'     => '📰 Communiqués',
            'add_new'       => 'Nouveau communiqué',
            'add_new_item'  => 'Nouveau communiqué',
            'edit_item'     => 'Modifier le communiqué',
        ],
        'public'          => true,
        'show_in_menu'    => true,
        'show_in_rest'    => true,
        'menu_icon'       => 'dashicons-megaphone',
        'menu_position'   => 27,
        'has_archive'     => 'communiques',
        'rewrite'         => ['slug' => 'communique', 'with_front' => false],
        'supports'        => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
        'capability_type' => 'post',
    ]);
    /* Flush unique après enregistrement (une seule fois par version). */
    if (get_option('lfi_nct_presse_rewrite_v') !== '1') {
        flush_rewrite_rules(false);
        update_option('lfi_nct_presse_rewrite_v', '1', false);
    }
}

/** Émetteur / signature d'un communiqué (méta), avec repli sur le nom du site. */
function lfi_nct_presse_emetteur($post_id = 0) {
    $v = trim((string) get_post_meta($post_id, '_lfi_presse_emetteur', true));
    return $v !== '' ? $v : get_bloginfo('name');
}

/** Description courte propre (excerpt ou début du corps), pour meta/description. */
function lfi_nct_presse_desc($post) {
    $ex = trim((string) $post->post_excerpt);
    if ($ex === '') $ex = wp_strip_all_tags($post->post_content);
    $ex = trim(preg_replace('/\s+/', ' ', $ex));
    return mb_substr($ex, 0, 300);
}

/* --- Balises SEO dans le <head> des pages de communiqué --- */
add_action('wp_head', 'lfi_nct_presse_seo_head', 3);
function lfi_nct_presse_seo_head() {
    if (!is_singular(LFI_NCT_PRESSE_CPT)) return;
    global $post;
    if (!$post) return;
    $title = get_the_title($post);
    $desc  = lfi_nct_presse_desc($post);
    $url   = get_permalink($post);
    $img   = get_the_post_thumbnail_url($post, 'large') ?: '';
    $site  = get_bloginfo('name');
    $emet  = lfi_nct_presse_emetteur($post->ID);
    $pub   = get_post_time('c', true, $post);
    $mod   = get_post_modified_time('c', true, $post);

    echo "\n<!-- LFI presse SEO -->\n";
    echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
    echo '<meta name="news_keywords" content="communiqué de presse, logement, Clos Toreau, Nantes, HLM, locataires">' . "\n";
    echo '<meta property="og:type" content="article">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr($site) . '">' . "\n";
    echo '<meta property="article:published_time" content="' . esc_attr($pub) . '">' . "\n";
    if ($img) {
        echo '<meta property="og:image" content="' . esc_url($img) . '">' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    } else {
        echo '<meta name="twitter:card" content="summary">' . "\n";
    }
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($desc) . '">' . "\n";

    /* JSON-LD NewsArticle : le signal FORT pour Google / Google Actualités. */
    $ld = [
        '@context' => 'https://schema.org',
        '@type'    => 'NewsArticle',
        'headline' => mb_substr($title, 0, 110),
        'datePublished' => $pub,
        'dateModified'  => $mod,
        'description'   => $desc,
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        'author'    => ['@type' => 'Organization', 'name' => $emet],
        'publisher' => [
            '@type' => 'Organization',
            'name'  => $site,
            'logo'  => ['@type' => 'ImageObject', 'url' => (function_exists('lfi_nct_presse_site_logo') ? lfi_nct_presse_site_logo() : '')],
        ],
        'articleBody' => wp_strip_all_tags($post->post_content),
    ];
    if ($img) $ld['image'] = [$img];
    echo '<script type="application/ld+json">' . wp_json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}

/** Logo du site pour le publisher JSON-LD (custom logo si dispo). */
function lfi_nct_presse_site_logo() {
    $id = (int) get_theme_mod('custom_logo');
    if ($id) { $u = wp_get_attachment_image_url($id, 'full'); if ($u) return $u; }
    $icon = get_site_icon_url();
    return $icon ?: (home_url('/'));
}

/* --- Rendu public : bandeau « COMMUNIQUÉ DE PRESSE » + émetteur/date --- */
add_filter('the_content', 'lfi_nct_presse_render_content', 8);
function lfi_nct_presse_render_content($content) {
    if (!is_singular(LFI_NCT_PRESSE_CPT) || !in_the_loop() || !is_main_query()) return $content;
    global $post;
    $emet = lfi_nct_presse_emetteur($post->ID);
    $date = get_the_date('j F Y', $post);
    $head  = '<div style="background:#c8102e;color:#fff;border-radius:10px;padding:14px 18px;margin:0 0 18px">';
    $head .= '<div style="font-size:.8em;letter-spacing:2px;font-weight:700;opacity:.9">COMMUNIQUÉ DE PRESSE</div>';
    $head .= '<div style="font-size:1.05em;font-weight:800;margin-top:2px">' . esc_html($emet) . '</div>';
    $head .= '<div style="font-size:.9em;opacity:.92">🗓 ' . esc_html($date) . '</div>';
    $head .= '</div>';
    return $head . $content;
}

/* ============================================================== *
 *  2) ANNUAIRE PRESSE (option éditable, cloisonnée par usage).     *
 * ============================================================== */
function lfi_nct_presse_contacts_get() {
    $v = get_option('lfi_nct_presse_contacts', null);
    if ($v === null) { $v = lfi_nct_presse_contacts_seed(); update_option('lfi_nct_presse_contacts', $v, false); }
    return is_array($v) ? $v : [];
}
function lfi_nct_presse_contacts_save($list) {
    update_option('lfi_nct_presse_contacts', array_values($list), false);
}
/** Catégories de l'annuaire (emoji, libellé). On SÉPARE les médias (à qui on
 *  envoie le communiqué), les responsables (à interpeller) et les soutiens
 *  (à relayer / remercier). */
function lfi_nct_presse_cats() {
    return [
        'media'       => ['📰', 'Médias — envoyer le communiqué'],
        'responsable' => ['🏛️', 'Responsables — à interpeller'],
        'soutien'     => ['✊', 'Soutiens — à relayer / remercier'],
    ];
}

/** Amorce : NOMS des médias + sites officiels UNIQUEMENT (faits publics).
 *  Emails / réseaux volontairement VIDES → à vérifier puis compléter. */
function lfi_nct_presse_contacts_seed() {
    $mk = function ($nom, $fonction, $site, $cat = 'media') {
        return ['id' => (abs(crc32($nom . microtime(true))) % 900000000) + 100000000,
            'nom' => $nom, 'fonction' => $fonction, 'site' => $site, 'cat' => $cat,
            'email' => '', 'twitter' => '', 'instagram' => '', 'facebook' => '', 'tel' => '', 'note' => 'À vérifier / compléter'];
    };
    return [
        $mk('Ouest-France (Nantes)', 'Quotidien régional', 'https://www.ouest-france.fr/pays-de-la-loire/nantes-44000/', 'media'),
        $mk('Presse Océan',          'Quotidien local',    'https://www.presseocean.fr/', 'media'),
        $mk('Télénantes',            'Télévision locale',  'https://www.telenantes.com/', 'media'),
        $mk('France Bleu Loire Océan','Radio locale',      'https://www.francebleu.fr/loire-ocean', 'media'),
        $mk('Mediacités Nantes',     'Presse d\'enquête',  'https://www.mediacites.fr/nantes/', 'media'),
        $mk('AFP (bureau Ouest)',    'Agence de presse',   'https://www.afp.com/fr', 'media'),
        $mk('Marie Vitoux',          'Adjointe quartier Nantes Sud (à vérifier)', '', 'responsable'),
        $mk('Aïcha Bassal',          'Présidente du CA de NMH (à vérifier)', '', 'responsable'),
    ];
}

/* ============================================================== *
 *  VUE ADMIN : hub « Espace presse » (communiqués + annuaire).    *
 * ============================================================== */
function lfi_nct_app_view_presse() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    $posts = get_posts(['post_type' => LFI_NCT_PRESSE_CPT, 'post_status' => ['publish', 'draft'], 'numberposts' => 50, 'orderby' => 'date', 'order' => 'DESC']);

    lfi_nct_app_screen_open('📰 Espace presse', 'Communiqués + contacts médias');
    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Communiqué enregistré.');
    if (!empty($_GET['del'])) lfi_nct_app_flash('🗑 Communiqué supprimé.');

    echo '<div class="lfi-app-help">Rédigez un <strong>communiqué de presse</strong> : il obtient sa propre page publique <strong>optimisée pour Google</strong> (titre, description, données structurées « NewsArticle », partage réseaux). Puis <strong>diffusez-le</strong> aux médias depuis l\'annuaire.</div>';

    echo '<a href="' . esc_url(lfi_nct_app_url('presse-add')) . '" class="btn-primary big" style="background:#c8102e;text-decoration:none;display:block;text-align:center;margin-bottom:8px">✍️ Nouveau communiqué</a>';
    echo '<a href="' . esc_url(lfi_nct_app_url('presse-contacts')) . '" style="display:block;text-align:center;background:#0b3d91;color:#fff;font-weight:800;border-radius:10px;padding:11px;text-decoration:none;margin-bottom:14px">📇 Annuaire presse & réseaux</a>';

    echo '<h3 style="margin:6px 0 8px;color:#0b3d91">Mes communiqués</h3>';
    if (empty($posts)) {
        echo '<div class="lfi-app-empty">Aucun communiqué pour l\'instant. Créez le premier — vous en avez déjà un prêt 😉</div>';
    } else {
        echo '<div style="display:flex;flex-direction:column;gap:9px">';
        foreach ($posts as $p) {
            $pub = $p->post_status === 'publish';
            $url = get_permalink($p);
            echo '<div style="background:#fff;border:1px solid #eee;border-left:4px solid ' . ($pub ? '#186a3b' : '#d39e00') . ';border-radius:10px;padding:11px 13px">';
            echo '<div style="font-weight:800;color:#1a1a1a">' . esc_html(get_the_title($p) ?: '(sans titre)') . ' <span style="font-size:.7em;font-weight:700;color:#fff;background:' . ($pub ? '#186a3b' : '#d39e00') . ';padding:1px 7px;border-radius:9px;vertical-align:middle">' . ($pub ? 'publié' : 'brouillon') . '</span></div>';
            echo '<div style="font-size:.8em;color:#888;margin-top:2px">🗓 ' . esc_html(get_the_date('j M Y', $p)) . '</div>';
            echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">';
            echo '<a href="' . esc_url(lfi_nct_app_url('presse-edit', ['id' => $p->ID])) . '" style="background:#0b3d91;color:#fff;padding:6px 11px;border-radius:8px;text-decoration:none;font-weight:700;font-size:.85em">✏️ Modifier</a>';
            if ($pub) {
                echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" style="background:#186a3b;color:#fff;padding:6px 11px;border-radius:8px;text-decoration:none;font-weight:700;font-size:.85em">🌐 Voir la page</a>';
                echo '<a href="' . esc_url(lfi_nct_app_url('presse-diffuser', ['id' => $p->ID])) . '" style="background:#c8102e;color:#fff;padding:6px 11px;border-radius:8px;text-decoration:none;font-weight:700;font-size:.85em">📣 Diffuser</a>';
            }
            echo '</div></div>';
        }
        echo '</div>';
    }
    echo '<div class="lfi-app-help" style="margin-top:12px"><small>🔎 SEO : chaque communiqué publié est indexable par Google et présent dans le plan de site (sitemap). Pour accélérer, soumettez l\'URL dans Google Search Console.</small></div>';
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE ADMIN : créer / modifier un communiqué.                    *
 * ============================================================== */
function lfi_nct_app_view_presse_add() { lfi_nct_app_view_presse_edit(0); }
function lfi_nct_app_view_presse_edit($id = null) {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    if ($id === null) $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    /* Suppression. */
    if (!empty($_POST['lfi_presse_del']) && check_admin_referer('lfi_presse_edit')) {
        $pid = (int) ($_POST['post_id'] ?? 0);
        if ($pid && get_post_type($pid) === LFI_NCT_PRESSE_CPT) wp_trash_post($pid);
        wp_safe_redirect(lfi_nct_app_url('presse', ['del' => 1])); exit;
    }

    /* Enregistrement. */
    if (!empty($_POST['lfi_presse_save']) && check_admin_referer('lfi_presse_edit')) {
        $titre = sanitize_text_field(wp_unslash($_POST['titre'] ?? ''));
        $chapo = sanitize_textarea_field(wp_unslash($_POST['chapo'] ?? ''));
        $corps = wp_kses_post(wp_unslash($_POST['corps'] ?? ''));
        $emet  = sanitize_text_field(wp_unslash($_POST['emetteur'] ?? ''));
        $statut = (($_POST['statut'] ?? 'publish') === 'draft') ? 'draft' : 'publish';
        $pid   = (int) ($_POST['post_id'] ?? 0);
        if ($titre === '') $titre = 'Communiqué de presse';
        $data = ['post_type' => LFI_NCT_PRESSE_CPT, 'post_title' => $titre, 'post_content' => $corps, 'post_excerpt' => $chapo, 'post_status' => $statut];
        if ($pid && get_post_type($pid) === LFI_NCT_PRESSE_CPT) { $data['ID'] = $pid; $pid = wp_update_post($data); }
        else { $pid = wp_insert_post($data); }
        if ($pid && !is_wp_error($pid)) {
            update_post_meta($pid, '_lfi_presse_emetteur', $emet);
            /* Image mise en avant (facultative). */
            if (!empty($_FILES['image']['name']) && (int) ($_FILES['image']['error'] ?? 4) === 0) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $mime = function_exists('mime_content_type') ? mime_content_type($_FILES['image']['tmp_name']) : $_FILES['image']['type'];
                if (strpos((string) $mime, 'image/') === 0 && (int) $_FILES['image']['size'] <= 15 * 1024 * 1024) {
                    $up = wp_handle_upload($_FILES['image'], ['test_form' => false]);
                    if (empty($up['error'])) {
                        $att = wp_insert_attachment(['post_mime_type' => $up['type'], 'post_title' => $titre, 'post_status' => 'inherit'], $up['file'], $pid);
                        if (!is_wp_error($att) && $att) {
                            wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $up['file']));
                            set_post_thumbnail($pid, $att);
                        }
                    }
                }
            }
        }
        wp_safe_redirect(lfi_nct_app_url('presse', ['ok' => 1])); exit;
    }

    $post = $id ? get_post($id) : null;
    $is_edit = ($post && get_post_type($post) === LFI_NCT_PRESSE_CPT);
    $titre = $is_edit ? $post->post_title : '';
    $chapo = $is_edit ? $post->post_excerpt : '';
    $corps = $is_edit ? $post->post_content : '';
    $emet  = $is_edit ? (string) get_post_meta($post->ID, '_lfi_presse_emetteur', true) : '';
    $statut = $is_edit ? $post->post_status : 'publish';

    lfi_nct_app_screen_open($is_edit ? '✏️ Modifier le communiqué' : '✍️ Nouveau communiqué', 'Il obtient sa page publique optimisée Google');
    echo '<div style="margin-bottom:10px"><a href="' . esc_url(lfi_nct_app_url('presse')) . '" style="color:#0b3d91;font-weight:700;text-decoration:none">← Espace presse</a></div>';

    echo '<form method="post" enctype="multipart/form-data" class="lfi-app-form">' . wp_nonce_field('lfi_presse_edit', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_presse_save" value="1"><input type="hidden" name="post_id" value="' . (int) ($is_edit ? $post->ID : 0) . '">';
    echo '<label>Titre du communiqué<input type="text" name="titre" value="' . esc_attr($titre) . '" placeholder="Ex : Rats et fuites d\'eau au Clos Toreau : les locataires exigent l\'intervention de Nantes Métropole Habitat" required></label>';
    echo '<label>Chapô (résumé — sert de description Google, 1–2 phrases)<textarea name="chapo" rows="3" placeholder="Résumé percutant qui donne envie de lire.">' . esc_textarea($chapo) . '</textarea></label>';
    echo '<label>Corps du communiqué<textarea name="corps" rows="12" placeholder="Le contenu complet. Qui, quoi, où, quand, pourquoi. Terminez par une phrase de contact presse.">' . esc_textarea($corps) . '</textarea></label>';
    echo '<label>Émetteur / signature (qui publie ce communiqué)<input type="text" name="emetteur" value="' . esc_attr($emet) . '" placeholder="Ex : Union des Quartiers Libres — Clos Toreau, avec le soutien de La France Insoumise"></label>';
    echo '<label>Image (facultative — améliore le partage réseaux)<input type="file" name="image" accept="image/*"></label>';
    echo '<label>Statut<select name="statut"><option value="publish"' . selected($statut, 'publish', false) . '>Publier (visible + indexable)</option><option value="draft"' . selected($statut, 'draft', false) . '>Brouillon (non public)</option></select></label>';
    echo '<button type="submit" class="btn-primary big" style="background:#c8102e">💾 Enregistrer</button>';
    echo '</form>';

    if ($is_edit) {
        echo '<form method="post" onsubmit="return confirm(\'Supprimer ce communiqué ?\')" style="margin-top:10px">' . wp_nonce_field('lfi_presse_edit', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_presse_del" value="1"><input type="hidden" name="post_id" value="' . (int) $post->ID . '">';
        echo '<button type="submit" class="btn-ghost" style="color:#c8102e">🗑 Supprimer</button></form>';
    }
    echo '<div class="lfi-app-help" style="margin-top:12px"><small>💡 SEO : un titre clair avec les mots que les gens cherchent (« rats », « Clos Toreau », « Nantes Métropole Habitat »), un chapô qui résume, un corps riche. Google adore les communiqués datés et structurés.</small></div>';
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE ADMIN : diffuser un communiqué (annuaire → email/réseaux). *
 * ============================================================== */
function lfi_nct_app_view_presse_diffuser() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $post = $id ? get_post($id) : null;
    if (!$post || get_post_type($post) !== LFI_NCT_PRESSE_CPT) { wp_safe_redirect(lfi_nct_app_url('presse')); exit; }

    $titre = get_the_title($post);
    $url   = get_permalink($post);
    $desc  = lfi_nct_presse_desc($post);
    $emet  = lfi_nct_presse_emetteur($post->ID);
    $subject = 'Communiqué de presse — ' . $titre;
    $body = "Bonjour,\n\nVeuillez trouver ci-dessous notre communiqué de presse.\n\n" . $titre . "\n\n" . $desc . "\n\nÀ lire en intégralité : " . $url . "\n\nBien cordialement,\n" . $emet;

    lfi_nct_app_screen_open('📣 Diffuser à la presse', $titre);
    echo '<div style="margin-bottom:10px"><a href="' . esc_url(lfi_nct_app_url('presse')) . '" style="color:#0b3d91;font-weight:700;text-decoration:none">← Espace presse</a></div>';

    /* Copier le texte + le lien. */
    $full = $titre . "\n\n" . $desc . "\n\n" . $url;
    echo '<div class="lfi-app-help">Pour chaque média : <strong>✉️ email pré-rempli</strong> (Gmail), <strong>📋 copier</strong> le texte, ou ouvrir ses <strong>réseaux</strong> pour commenter/partager. Le lien du communiqué (SEO) est déjà dans le message.</div>';
    echo '<textarea id="lfi-presse-copytext" style="position:absolute;left:-9999px" aria-hidden="true">' . esc_textarea($full) . '</textarea>';
    echo '<div style="display:flex;gap:8px;margin-bottom:12px"><button type="button" onclick="lfiPresseCopy()" style="flex:1;background:#0b3d91;color:#fff;border:0;border-radius:10px;padding:11px;font-weight:800">📋 Copier titre + lien</button>';
    echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" style="flex:1;text-align:center;background:#186a3b;color:#fff;border-radius:10px;padding:11px;font-weight:800;text-decoration:none">🌐 Ouvrir la page</a></div>';

    $contacts = lfi_nct_presse_contacts_get();
    $cats = lfi_nct_presse_cats();
    $intro_cat = [
        'media'       => 'Envoie-leur le communiqué (email + réseaux).',
        'responsable' => 'Interpelle-les publiquement sur leurs publications (réseaux) ou par email.',
        'soutien'     => 'Remercie-les et relaie / partage sous leurs publications.',
    ];
    foreach ($cats as $ck => $cv) {
        $grp = array_values(array_filter($contacts, function ($c) use ($ck) { return (($c['cat'] ?? 'media')) === $ck; }));
        if (empty($grp)) continue;
        echo '<div style="font-weight:800;color:#444;margin:14px 0 4px">' . $cv[0] . ' ' . esc_html($cv[1]) . '</div>';
        echo '<div style="font-size:.82em;color:#777;margin-bottom:6px">' . esc_html($intro_cat[$ck] ?? '') . '</div>';
        echo '<div style="display:flex;flex-direction:column;gap:10px">';
        foreach ($grp as $c) {
            echo '<div style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px 13px">';
            echo '<div style="font-weight:800;color:#1a1a1a">' . esc_html($c['nom']) . '</div>';
            if (!empty($c['fonction'])) echo '<div style="font-size:.85em;color:#666">' . esc_html($c['fonction']) . '</div>';
            echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">';
            if (!empty($c['email'])) {
                echo lfi_nct_email_buttons_html($c['email'], $subject, $body, '✉️ Email');
            }
            foreach ([['twitter', '𝕏', '#111'], ['instagram', '📸 Insta', '#c13584'], ['facebook', 'f Facebook', '#1877f2'], ['site', '🌐 Site', '#0b3d91']] as $r) {
                if (!empty($c[$r[0]])) echo '<a href="' . esc_url($c[$r[0]]) . '" target="_blank" rel="noopener" style="background:' . $r[2] . ';color:#fff;padding:6px 11px;border-radius:8px;text-decoration:none;font-weight:700;font-size:.82em">' . $r[1] . '</a>';
            }
            if (!empty($c['tel'])) echo '<a href="tel:' . esc_attr(preg_replace('/\s+/', '', $c['tel'])) . '" style="background:#555;color:#fff;padding:6px 11px;border-radius:8px;text-decoration:none;font-weight:700;font-size:.82em">📞 ' . esc_html($c['tel']) . '</a>';
            if (empty($c['email']) && empty($c['twitter']) && empty($c['instagram']) && empty($c['facebook'])) echo '<span style="font-size:.8em;color:#c8102e;font-weight:700;align-self:center">à compléter dans l\'annuaire</span>';
            echo '</div></div>';
        }
        echo '</div>';
    }
    echo '<div style="margin-top:12px"><a href="' . esc_url(lfi_nct_app_url('presse-contacts')) . '" style="color:#0b3d91;font-weight:700;text-decoration:none">📇 Gérer / compléter l\'annuaire →</a></div>';
    echo '<script>function lfiPresseCopy(){var t=document.getElementById("lfi-presse-copytext");if(!t)return;t.style.left="0";t.select();try{document.execCommand("copy");}catch(e){}try{navigator.clipboard.writeText(t.value);}catch(e){}t.style.left="-9999px";alert("Titre + lien copiés ✔");}</script>';
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE ADMIN : gérer l'annuaire presse (ajout / édition / suppr). *
 * ============================================================== */
function lfi_nct_app_view_presse_contacts() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    if (!empty($_POST['lfi_presse_contact']) && check_admin_referer('lfi_presse_contact')) {
        $list = lfi_nct_presse_contacts_get();
        $cid = (int) ($_POST['cid'] ?? 0);
        $row = [
            'id'        => $cid ?: ((abs(crc32(microtime(true) . mt_rand())) % 900000000) + 100000000),
            'nom'       => sanitize_text_field(wp_unslash($_POST['nom'] ?? '')),
            'fonction'  => sanitize_text_field(wp_unslash($_POST['fonction'] ?? '')),
            'cat'       => (function () { $c = sanitize_key($_POST['cat'] ?? 'media'); return isset(lfi_nct_presse_cats()[$c]) ? $c : 'media'; })(),
            'site'      => esc_url_raw(wp_unslash($_POST['site'] ?? '')),
            'email'     => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'twitter'   => esc_url_raw(wp_unslash($_POST['twitter'] ?? '')),
            'instagram' => esc_url_raw(wp_unslash($_POST['instagram'] ?? '')),
            'facebook'  => esc_url_raw(wp_unslash($_POST['facebook'] ?? '')),
            'tel'       => sanitize_text_field(wp_unslash($_POST['tel'] ?? '')),
            'note'      => sanitize_text_field(wp_unslash($_POST['note'] ?? '')),
        ];
        if ($row['nom'] !== '') {
            $found = false;
            foreach ($list as $i => $c) { if ((int) ($c['id'] ?? 0) === $cid && $cid) { $list[$i] = $row; $found = true; break; } }
            if (!$found) $list[] = $row;
            lfi_nct_presse_contacts_save($list);
        }
        wp_safe_redirect(lfi_nct_app_url('presse-contacts', ['ok' => 1])); exit;
    }
    if (!empty($_POST['lfi_presse_contact_del']) && check_admin_referer('lfi_presse_contact')) {
        $cid = (int) ($_POST['cid'] ?? 0);
        $list = array_values(array_filter(lfi_nct_presse_contacts_get(), function ($c) use ($cid) { return (int) ($c['id'] ?? 0) !== $cid; }));
        lfi_nct_presse_contacts_save($list);
        wp_safe_redirect(lfi_nct_app_url('presse-contacts', ['del' => 1])); exit;
    }

    $edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
    $contacts = lfi_nct_presse_contacts_get();
    $editing = null;
    foreach ($contacts as $c) if ((int) ($c['id'] ?? 0) === $edit_id) $editing = $c;

    lfi_nct_app_screen_open('📇 Annuaire presse & réseaux', 'Médias · élus · comptes à interpeller');
    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Contact enregistré.');
    if (!empty($_GET['del'])) lfi_nct_app_flash('🗑 Contact supprimé.');
    echo '<div style="margin-bottom:10px"><a href="' . esc_url(lfi_nct_app_url('presse')) . '" style="color:#0b3d91;font-weight:700;text-decoration:none">← Espace presse</a></div>';
    echo '<div class="lfi-app-help">⚠️ <strong>Rien n\'est pré-rempli automatiquement</strong> (règle : on ne devine pas). Les médias sont amorcés avec leur <strong>nom + site officiel</strong> seulement — <strong>vérifiez puis complétez</strong> les emails et réseaux avant toute diffusion.</div>';

    /* Formulaire ajout / édition. */
    $f = $editing ?: ['id' => 0, 'nom' => '', 'fonction' => '', 'cat' => 'media', 'site' => '', 'email' => '', 'twitter' => '', 'instagram' => '', 'facebook' => '', 'tel' => '', 'note' => ''];
    $cats = lfi_nct_presse_cats();
    echo '<form method="post" class="lfi-app-form" style="background:#f6f8fb;border:1px solid #dfe6f0;border-radius:12px;padding:12px">' . wp_nonce_field('lfi_presse_contact', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_presse_contact" value="1"><input type="hidden" name="cid" value="' . (int) $f['id'] . '">';
    echo '<div style="font-weight:800;color:#0b3d91;margin-bottom:6px">' . ($editing ? '✏️ Modifier un contact' : '➕ Ajouter un contact') . '</div>';
    echo '<label>Nom<input type="text" name="nom" value="' . esc_attr($f['nom']) . '" required></label>';
    echo '<label>Catégorie<select name="cat">';
    foreach ($cats as $ck => $cv) echo '<option value="' . esc_attr($ck) . '"' . selected(($f['cat'] ?? 'media'), $ck, false) . '>' . $cv[0] . ' ' . esc_html($cv[1]) . '</option>';
    echo '</select></label>';
    echo '<label>Fonction / média<input type="text" name="fonction" value="' . esc_attr($f['fonction']) . '" placeholder="Ex : Journaliste — Ouest-France"></label>';
    echo '<label>Site web<input type="url" name="site" value="' . esc_attr($f['site']) . '" placeholder="https://…"></label>';
    echo '<label>Email<input type="email" name="email" value="' . esc_attr($f['email']) . '" placeholder="redaction@…"></label>';
    echo '<label>X / Twitter (lien)<input type="url" name="twitter" value="' . esc_attr($f['twitter']) . '" placeholder="https://x.com/…"></label>';
    echo '<label>Instagram (lien)<input type="url" name="instagram" value="' . esc_attr($f['instagram']) . '" placeholder="https://instagram.com/…"></label>';
    echo '<label>Facebook (lien)<input type="url" name="facebook" value="' . esc_attr($f['facebook']) . '" placeholder="https://facebook.com/…"></label>';
    echo '<label>Téléphone<input type="text" name="tel" value="' . esc_attr($f['tel']) . '"></label>';
    echo '<label>Note<input type="text" name="note" value="' . esc_attr($f['note']) . '" placeholder="À vérifier, contact direct…"></label>';
    echo '<button type="submit" class="btn-primary big" style="background:#0b3d91">💾 Enregistrer le contact</button>';
    if ($editing) echo '<a href="' . esc_url(lfi_nct_app_url('presse-contacts')) . '" style="display:block;text-align:center;margin-top:6px;color:#666">Annuler l\'édition</a>';
    echo '</form>';

    echo '<h3 style="margin:16px 0 8px;color:#0b3d91">Contacts (' . count($contacts) . ')</h3>';
    /* Groupé par catégorie : médias, responsables à interpeller, soutiens. */
    foreach ($cats as $ck => $cv) {
        $grp = array_values(array_filter($contacts, function ($c) use ($ck) { return (($c['cat'] ?? 'media')) === $ck; }));
        if (empty($grp)) continue;
        echo '<div style="font-weight:800;color:#444;margin:14px 0 6px;padding-bottom:4px;border-bottom:2px solid #eee">' . $cv[0] . ' ' . esc_html($cv[1]) . ' <span style="color:#999;font-weight:600">(' . count($grp) . ')</span></div>';
        echo '<div style="display:flex;flex-direction:column;gap:9px">';
        foreach ($grp as $c) {
            $incomplete = empty($c['email']) && empty($c['twitter']) && empty($c['instagram']) && empty($c['facebook']);
            echo '<div style="background:#fff;border:1px solid ' . ($incomplete ? '#e6c98a' : '#e6e6e6') . ';border-radius:11px;padding:11px 12px">';
            echo '<div style="font-weight:800;color:#1a1a1a">' . esc_html($c['nom']);
            if ($incomplete) echo ' <span style="font-size:.7em;font-weight:700;color:#8a6d1f;background:#fff3cd;padding:1px 7px;border-radius:9px">à compléter</span>';
            echo '</div>';
            if (!empty($c['fonction'])) echo '<div style="font-size:.85em;color:#666">' . esc_html($c['fonction']) . '</div>';
            $bits = [];
            if (!empty($c['email'])) $bits[] = '✉️ ' . esc_html($c['email']);
            if (!empty($c['site'])) $bits[] = '🌐 site';
            if (!empty($c['twitter'])) $bits[] = '𝕏';
            if (!empty($c['instagram'])) $bits[] = '📸';
            if (!empty($c['facebook'])) $bits[] = 'f';
            if (!empty($c['tel'])) $bits[] = '📞 ' . esc_html($c['tel']);
            if ($bits) echo '<div style="font-size:.82em;color:#0066a3;margin-top:3px">' . implode(' · ', $bits) . '</div>';
            echo '<div style="margin-top:7px;display:flex;gap:8px">';
            echo '<a href="' . esc_url(lfi_nct_app_url('presse-contacts', ['edit' => (int) $c['id']])) . '" style="color:#0b3d91;font-weight:700;text-decoration:none;font-size:.85em">✏️ Modifier</a>';
            echo '<form method="post" onsubmit="return confirm(\'Supprimer ce contact ?\')" style="margin:0">' . wp_nonce_field('lfi_presse_contact', '_wpnonce', true, false) . '<input type="hidden" name="lfi_presse_contact_del" value="1"><input type="hidden" name="cid" value="' . (int) $c['id'] . '"><button type="submit" class="btn-ghost" style="font-size:.85em;padding:0;color:#c8102e">🗑 Supprimer</button></form>';
            echo '</div></div>';
        }
        echo '</div>';
    }
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE PUBLIQUE (in-app) : liste des communiqués.                 *
 * ============================================================== */
function lfi_nct_app_view_communiques() {
    $posts = get_posts(['post_type' => LFI_NCT_PRESSE_CPT, 'post_status' => 'publish', 'numberposts' => 30, 'orderby' => 'date', 'order' => 'DESC']);
    lfi_nct_app_screen_open('📰 Communiqués de presse', 'Nos prises de position publiques');
    if (empty($posts)) {
        echo '<div class="lfi-app-empty">Aucun communiqué publié pour l\'instant.</div>';
    } else {
        echo '<div style="display:flex;flex-direction:column;gap:10px">';
        foreach ($posts as $p) {
            echo '<a href="' . esc_url(get_permalink($p)) . '" style="text-decoration:none;color:inherit;display:block;background:#fff;border:1px solid #e6e6e6;border-left:4px solid #c8102e;border-radius:11px;padding:12px 14px">';
            echo '<div style="font-size:.72em;letter-spacing:1.5px;font-weight:700;color:#c8102e">COMMUNIQUÉ · ' . esc_html(get_the_date('j M Y', $p)) . '</div>';
            echo '<div style="font-weight:800;color:#1a1a1a;margin-top:2px">' . esc_html(get_the_title($p)) . '</div>';
            $ex = lfi_nct_presse_desc($p);
            if ($ex) echo '<div style="font-size:.9em;color:#555;margin-top:3px">' . esc_html(mb_substr($ex, 0, 140)) . '…</div>';
            echo '</a>';
        }
        echo '</div>';
    }
    lfi_nct_app_screen_close();
}
