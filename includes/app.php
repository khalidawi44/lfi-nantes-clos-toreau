<?php
/**
 * App GA — PWA installable iPhone / Android (sans App Store)
 *
 * - Page publique /app/ qui sert de coquille à la PWA.
 * - Manifest JSON et Service Worker servis sous /lfi-app-manifest.json
 *   et /lfi-app-sw.js (à la racine, indispensable pour le scope SW).
 * - Icônes 192×192 et 512×512 générées à la volée avec GD (zéro fichier
 *   binaire à committer).
 * - Écran de connexion → tableau de bord avec tuiles vers toutes les
 *   pages d'admin du plugin. Le cookie WP fait la session.
 *
 * Installation côté utilisateur :
 *   - iPhone : Safari → ouvre /app/ → bouton « Partager » → « Sur l'écran
 *     d'accueil ».
 *   - Android : Chrome → ouvre /app/ → bandeau « Installer l'application »
 *     ou menu → « Installer ».
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_APP_SLUG = 'app';

/**
 * URL FIABLE du formulaire d'enquête : on retrouve la vraie page qui porte le
 * shortcode [lfi_nct_survey] quel que soit son slug (évite les liens 404 si la
 * page a été renommée). Repli sur le slug historique en dernier recours.
 * Résultat mis en cache 1h ; invalidé quand une page est enregistrée.
 */
function lfi_nct_survey_url() {
    $cached = get_transient('lfi_nct_survey_url');
    if ($cached) return $cached;

    $url = '';
    /* 1) Cherche une page/article publié contenant le shortcode enquête. */
    $q = new WP_Query([
        'post_type'      => ['page', 'post'],
        'post_status'    => 'publish',
        'posts_per_page' => 30,
        'fields'         => 'ids',
        's'              => 'lfi_nct_survey',
        'no_found_rows'  => true,
    ]);
    foreach ((array) $q->posts as $pid) {
        $p = get_post($pid);
        if ($p && has_shortcode((string) $p->post_content, 'lfi_nct_survey')) {
            $url = get_permalink($pid);
            break;
        }
    }
    /* 2) Sinon, la page au slug historique si elle existe. */
    if (!$url) {
        $by = get_page_by_path('enquete-logement-clos-toreau');
        if ($by) $url = get_permalink($by);
    }
    /* 3) Dernier recours : le slug historique en dur (JAMAIS un appel récursif —
       sinon récursion infinie → erreur fatale si aucune page n'est trouvée). */
    if (!$url) $url = home_url('/enquete-logement-clos-toreau/');

    set_transient('lfi_nct_survey_url', $url, HOUR_IN_SECONDS);
    return $url;
}
/* Invalide le cache quand une page/un article change (le slug a pu bouger). */
add_action('save_post', function () { delete_transient('lfi_nct_survey_url'); });

/**
 * ÉCRAN « Faire passer une enquête » INTÉGRÉ à l'app.
 * On affiche le formulaire DIRECTEMENT dans l'app (au lieu d'ouvrir une page
 * externe) : plus fiable en PWA installée — aucune navigation hors de l'app,
 * donc plus de bouton qui « n'ouvre pas ». Le formulaire poste sur l'URL
 * courante ; le shortcode gère la soumission et affiche le récapitulatif.
 */
function lfi_nct_app_view_enquete() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    lfi_nct_app_screen_open('📋 Faire passer une enquête', 'Porte-à-porte — saisis les réponses sur place');
    /* Le rendu standalone de l'app N'IMPRIME PAS wp_head : on injecte donc
       DIRECTEMENT le CSS et le JS du formulaire (sinon : formulaire non stylé
       + le bloc « problèmes » ne s'ouvre pas quand on coche « Oui »). */
    $v = LFI_NCT_VERSION;
    echo '<link rel="stylesheet" href="' . esc_url(LFI_NCT_URL . 'assets/css/form.css?v=' . $v) . '">';
    /* Fond clair : le formulaire est conçu pour un fond blanc, pas le thème
       sombre de l'app → lisible et net. */
    echo '<style>.lfi-inapp-survey{background:#fff;color:#1a1a1a;border-radius:12px;padding:14px;margin-top:6px}'
       . '.lfi-inapp-survey input,.lfi-inapp-survey select,.lfi-inapp-survey textarea{background:#fff;color:#1a1a1a}'
       . '.lfi-inapp-survey label,.lfi-inapp-survey h2,.lfi-inapp-survey h3,.lfi-inapp-survey legend,.lfi-inapp-survey p{color:#1a1a1a}</style>';
    echo '<div class="lfi-inapp-survey">';
    echo function_exists('lfi_nct_survey_shortcode') ? lfi_nct_survey_shortcode() : do_shortcode('[lfi_nct_survey]');
    echo '</div>';
    echo '<script src="' . esc_url(LFI_NCT_URL . 'assets/js/form.js?v=' . $v) . '"></script>';
    lfi_nct_app_screen_close(false);
}

/**
 * URL FIABLE de la page de l'app (là où vit le shortcode [lfi_nct_app]).
 * On ne se fie plus à un chemin fixe /app/ : si la page a un autre slug
 * (ex. « app-2 » parce que « app » était déjà pris), le lien du SMS pointait
 * vers un 404. On retrouve donc la VRAIE page publiée.
 */
function lfi_nct_app_page_url() {
    $cached = get_transient('lfi_nct_app_page_url');
    if ($cached) return $cached;

    $url = '';
    $p = get_page_by_path(LFI_NCT_APP_SLUG, OBJECT, 'page');
    if ($p && $p->post_status === 'publish') {
        $url = get_permalink($p);
    } else {
        $q = new WP_Query([
            'post_type' => 'page', 'post_status' => 'publish',
            'posts_per_page' => 10, 'fields' => 'ids', 's' => 'lfi_nct_app', 'no_found_rows' => true,
        ]);
        foreach ((array) $q->posts as $pid) {
            $pp = get_post($pid);
            if ($pp && has_shortcode((string) $pp->post_content, 'lfi_nct_app')) { $url = get_permalink($pid); break; }
        }
    }
    if (!$url) $url = home_url('/' . LFI_NCT_APP_SLUG . '/');

    set_transient('lfi_nct_app_page_url', $url, HOUR_IN_SECONDS);
    return $url;
}
add_action('save_post', function () { delete_transient('lfi_nct_app_page_url'); });

/**
 * URL FIABLE d'une page par son slug : renvoie son permalien si elle existe et
 * est publiée, sinon un repli sûr (par défaut l'accueil) — JAMAIS un 404.
 */
function lfi_nct_page_url($slug, $fallback = '') {
    $slug = trim((string) $slug, '/');
    if ($slug !== '') {
        $p = get_page_by_path($slug, OBJECT, ['page', 'post']);
        if ($p && $p->post_status === 'publish') return get_permalink($p);
    }
    return $fallback !== '' ? $fallback : home_url('/');
}

/* ============================================================== *
 *  Page WordPress /app/                                            *
 * ============================================================== */
add_action('init', 'lfi_nct_app_create_page', 30);
function lfi_nct_app_create_page() {
    /* AUTO-RÉPARATION : on vérifie à chaque fois que la page /app/ existe,
       est publiée et contient le shortcode. Si elle a été supprimée, dépubliée
       ou renommée, on la recrée → plus jamais de 404 sur toute l'app. */
    $existing = get_page_by_path(LFI_NCT_APP_SLUG, OBJECT, 'page');
    if ($existing) {
        $fix = [];
        /* La page DOIT être publiée et publique : sinon un membre déconnecté
           reçoit un 404 (WordPress cache les pages privées/brouillons aux
           visiteurs) alors que l'admin, lui, la voit. C'est LA cause du 404
           du lien de connexion. On la republie et on retire tout mot de passe. */
        if ($existing->post_status !== 'publish') $fix['post_status'] = 'publish';
        if ((string) $existing->post_password !== '') $fix['post_password'] = '';
        if (strpos((string) $existing->post_content, '[lfi_nct_app]') === false) {
            $fix['post_content'] = trim($existing->post_content . "\n[lfi_nct_app]");
        }
        if ($fix) {
            $fix['ID'] = $existing->ID;
            wp_update_post($fix);
            if (function_exists('do_action')) do_action('litespeed_purge_all');
            if (function_exists('wp_cache_flush')) wp_cache_flush();
        }
        return;
    }
    /* Page absente → (re)création + flush des règles pour que /app/ résolve. */
    $pid = wp_insert_post([
        'post_title'    => 'App du GA',
        'post_name'     => LFI_NCT_APP_SLUG,
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_content'  => '[lfi_nct_app]',
        'post_author'   => 1,
        'comment_status'=> 'closed',
        'ping_status'   => 'closed',
    ]);
    if ($pid && !is_wp_error($pid)) {
        update_option('lfi_nct_app_page_created', 'done', false);
        flush_rewrite_rules(false);
    }
}

/* ============================================================== *
 *  Endpoints racine : manifest, service worker, icônes             *
 * ============================================================== */
add_action('init', 'lfi_nct_app_rewrites', 5);
function lfi_nct_app_rewrites() {
    add_rewrite_rule('^lfi-app-manifest\.json$',     'index.php?lfi_app=manifest', 'top');
    add_rewrite_rule('^lfi-app-sw\.js$',             'index.php?lfi_app=sw',       'top');
    add_rewrite_rule('^lfi-app-icon-([0-9]+)\.png$', 'index.php?lfi_app=icon&size=$matches[1]', 'top');

    if (get_option('lfi_nct_app_rewrites_flushed') !== '2') {
        flush_rewrite_rules(false);
        update_option('lfi_nct_app_rewrites_flushed', '2', false);
    }
}
add_filter('query_vars', function ($v) { $v[] = 'lfi_app'; $v[] = 'size'; $v[] = 'mask'; return $v; });

add_action('template_redirect', 'lfi_nct_app_serve_endpoints');
function lfi_nct_app_serve_endpoints() {
    $ep = get_query_var('lfi_app');
    if (!$ep) return;

    if ($ep === 'manifest') {
        nocache_headers();
        header('Content-Type: application/manifest+json; charset=utf-8');
        $v = LFI_NCT_VERSION;
        echo wp_json_encode([
            'name'             => 'GA LFI Nantes Sud Clos Toreau',
            'short_name'       => 'GA LFI',
            'description'      => 'Console mobile du Groupe d\'Action.',
            'start_url'        => home_url('/' . LFI_NCT_APP_SLUG . '/'),
            'scope'            => home_url('/'),
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => '#c8102e',
            'theme_color'      => '#c8102e',
            'lang'             => 'fr',
            'icons'            => [
                /* Maskable = Android adaptive, le motif doit rester dans le safe-zone central */
                ['src' => lfi_nct_app_icon_url(192, 'maskable') . '&v=' . $v, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => lfi_nct_app_icon_url(512, 'maskable') . '&v=' . $v, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
                /* "any" = iOS/iPad/web — utilise tout l'espace, pas de marge interne */
                ['src' => lfi_nct_app_icon_url(180) . '&v=' . $v, 'sizes' => '180x180', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => lfi_nct_app_icon_url(192) . '&v=' . $v, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => lfi_nct_app_icon_url(512) . '&v=' . $v, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ],
            'shortcuts' => [
                ['name' => 'Inscrits réunion', 'url' => admin_url('admin.php?page=lfi-nct-reunion-rsvp')],
                ['name' => 'Enquêtes logement', 'url' => admin_url('admin.php?page=lfi-nct-responses')],
                ['name' => 'Événements',        'url' => admin_url('admin.php?page=lfi-nct-event-rsvp')],
                ['name' => 'Envoyer SMS',       'url' => admin_url('admin.php?page=lfi-nct-sms')],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($ep === 'sw') {
        nocache_headers();
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        $home   = home_url('/');
        $app    = home_url('/' . LFI_NCT_APP_SLUG . '/');
        $version = LFI_NCT_VERSION;
        echo "/* LFI GA Service Worker — v{$version} */\n";
        ?>
const CACHE = 'lfi-app-<?php echo esc_js($version); ?>';
const SHELL = ['<?php echo esc_url_raw($app); ?>'];

self.addEventListener('install', e => {
    e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).catch(() => {}));
    self.skipWaiting();
});
self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(k => k !== CACHE).map(k => caches.delete(k))
        )).then(() => self.clients.claim())
    );
});
self.addEventListener('fetch', e => {
    if (e.request.method !== 'GET') return;
    const url = new URL(e.request.url);
    /* Ne JAMAIS cacher l'admin ou le login : il faut toujours du frais */
    if (url.pathname.startsWith('/wp-admin/') ||
        url.pathname.startsWith('/wp-login.php') ||
        url.pathname.startsWith('/wp-json/')) return;
    /* Navigations vers une page HORS de l'app (ex. le formulaire d'enquête) :
       on NE médiatise PAS — le navigateur ouvre nativement. Évite les blocages
       de navigation en PWA installée (le bouton « Faire passer une enquête »). */
    var APP_PATH = new URL('<?php echo esc_url_raw($app); ?>').pathname;
    if (e.request.mode === 'navigate' && url.pathname.indexOf(APP_PATH) !== 0) return;
    e.respondWith(
        fetch(e.request)
            .then(r => {
                if (e.request.url.indexOf('<?php echo esc_url_raw($app); ?>') === 0) {
                    const clone = r.clone();
                    caches.open(CACHE).then(c => c.put(e.request, clone)).catch(() => {});
                }
                return r;
            })
            .catch(() => caches.match(e.request).then(r => r || caches.match('<?php echo esc_url_raw($app); ?>')))
    );
});
        <?php
        exit;
    }

    if ($ep === 'icon') {
        $size = max(48, min(1024, (int) get_query_var('size')));
        $mask = get_query_var('mask') === 'maskable';
        nocache_headers();
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        lfi_nct_app_render_icon($size, $mask);
        exit;
    }

    /* ============================================================== *
     *  DIAGNOSTIC : /?lfi_app=diag&vue=...&id=...                      *
     *  Rend une vue directement, SANS thème, SANS Service Worker,     *
     *  SANS bufferisation cachée, avec affichage COMPLET des erreurs. *
     *  Réservé à l'admin. Sert à identifier une page blanche.         *
     * ============================================================== */
    if ($ep === 'diag') {
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (!current_user_can('manage_options')) {
            echo 'Réservé à l\'administrateur. Connecte-toi puis reviens sur cette URL.';
            exit;
        }
        @ini_set('display_errors', '1');
        @ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
        @ini_set('memory_limit', '512M');
        if (function_exists('set_time_limit')) @set_time_limit(60);

        $vue = isset($_GET['vue']) ? sanitize_key($_GET['vue']) : '';
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<div style="font-family:-apple-system,sans-serif;padding:14px;max-width:900px;margin:auto">';
        echo '<h2 style="color:#c8102e">🔬 Diagnostic — vue « ' . esc_html($vue) . ' »</h2>';
        echo '<p style="color:#666">Mémoire départ : ' . size_format(memory_get_usage(true)) . ' · limite : ' . esc_html(ini_get('memory_limit')) . '</p><hr>';

        /* Map vue -> fonction (sous-ensemble utile au diag). */
        $map = [
            'dossier-juridique-edit' => 'lfi_nct_app_view_dossier_juridique_edit',
            'dossier-juridique-add'  => 'lfi_nct_app_view_dossier_juridique_add',
            'dossiers-juridiques'    => 'lfi_nct_app_view_dossiers_juridiques',
            'association'            => 'lfi_nct_app_view_association',
            'asso-statuts'           => 'lfi_nct_app_view_asso_statuts',
            'dossier'                => 'lfi_nct_app_view_dossier',
        ];
        $fn = $map[$vue] ?? '';
        if (!$fn || !function_exists($fn)) {
            echo '<p style="color:#c8102e">Vue inconnue ou fonction absente : ' . esc_html($vue) . ' → ' . esc_html($fn) . '</p>';
            exit;
        }
        echo str_repeat(' ', 4096); /* casse tout buffer LiteSpeed pour voir le flux */
        if (function_exists('flush')) { @ob_flush(); @flush(); }

        /* Petit lanceur d'étape : exécute un test, capture sortie/mémoire/temps/erreur. */
        $run = function ($titre, callable $cb) {
            echo '<h3 style="margin:18px 0 4px">▶️ ' . esc_html($titre) . '</h3>';
            if (function_exists('flush')) { @ob_flush(); @flush(); }
            $t0 = microtime(true);
            try {
                $len = (int) $cb();
                $dt = round((microtime(true) - $t0) * 1000);
                echo '<p style="color:#186a3b">✅ OK — ' . number_format($len) . ' octets · ' . $dt . ' ms · pic mémoire ' . size_format(memory_get_peak_usage(true)) . '</p>';
            } catch (\Throwable $e) {
                echo '<div style="background:#fff3f5;border:2px solid #c8102e;padding:12px;border-radius:8px">';
                echo '<strong style="color:#c8102e">❌ ' . esc_html(get_class($e)) . '</strong><br>';
                echo esc_html($e->getMessage()) . '<br><small>' . esc_html($e->getFile()) . ':' . (int) $e->getLine() . '</small>';
                echo '</div>';
            }
            if (function_exists('flush')) { @ob_flush(); @flush(); }
        };

        /* Étape 1 : la vue seule (déjà connue OK). */
        $run('1. Vue seule — ' . $fn . '()', function () use ($fn) {
            ob_start(); $fn(); return strlen((string) ob_get_clean());
        });

        /* Étape 2 : le shortcode complet [lfi_nct_app] (coquille + voix + SW + styles). */
        $run('2. Shortcode complet lfi_nct_app_shortcode()', function () {
            $out = function_exists('lfi_nct_app_shortcode') ? (string) lfi_nct_app_shortcode() : '';
            return strlen($out);
        });

        /* Étape 3 : filtres the_content (wpautop, wptexturize, do_shortcode…). */
        $run('3. Filtres the_content sur [lfi_nct_app]', function () {
            $out = apply_filters('the_content', '[lfi_nct_app]');
            return strlen((string) $out);
        });

        echo '<hr><p style="color:#666">Si les 3 étapes sont ✅, le souci est dans le <strong>thème</strong> ou <strong>LiteSpeed</strong> (post-traitement de la page), pas dans le plugin. Mémoire pic finale : ' . size_format(memory_get_peak_usage(true)) . ' · limite effective : ' . esc_html(ini_get('memory_limit')) . '</p>';
        echo '</div>';
        exit;
    }
}

/**
 * URL d'une icône qui marche TOUJOURS (sans dépendre des rewrite rules).
 * On passe par index.php?lfi_app=icon&size=N pour ne jamais être bloqué.
 */
function lfi_nct_app_icon_url($size, $variant = 'any') {
    $url = home_url('/?lfi_app=icon&size=' . (int) $size);
    if ($variant === 'maskable') $url .= '&mask=maskable';
    return $url;
}

function lfi_nct_app_render_icon($size, $maskable = false) {
    if (!function_exists('imagecreatetruecolor')) {
        echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        return;
    }
    $im = imagecreatetruecolor($size, $size);
    imagealphablending($im, true);
    imageantialias($im, true);

    $rouge       = imagecolorallocate($im, 200, 16, 46);   // #c8102e
    $rouge_clair = imagecolorallocate($im, 232, 32, 60);   // un peu plus clair pour le dégradé
    $rouge_fonce = imagecolorallocate($im, 154, 8,  34);   // ombre interne
    $blanc       = imagecolorallocate($im, 255, 255, 255);
    $jaune       = imagecolorallocate($im, 255, 212, 0);   // liseré

    /* Dégradé radial maison : carré rouge avec un coeur plus clair */
    imagefilledrectangle($im, 0, 0, $size, $size, $rouge);
    for ($i = 0; $i < 6; $i++) {
        $r = (int) ($size * (0.55 - $i * 0.07));
        if ($r <= 0) break;
        /* alpha doit rester entre 0 et 127 (transparent total = 127) */
        $a = min(127, 60 + $i * 12);
        $col = imagecolorallocatealpha($im, 232, 32, 60, $a);
        imagefilledellipse($im, (int) ($size / 2), (int) ($size * 0.42), $r * 2, $r * 2, $col);
    }

    /* Zone utile : si maskable, le motif central tient dans 60% (safe zone Android) */
    $scale = $maskable ? 0.60 : 0.78;

    $font_bold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
    if (function_exists('imagettftext') && file_exists($font_bold)) {
        /* Φ (phi) majuscule, symbole reconnaissable LFI */
        $glyph = 'Φ';
        $fs = (int) ($size * $scale * 0.78);
        $bbox = imagettfbbox($fs, 0, $font_bold, $glyph);
        $tw = $bbox[2] - $bbox[0];
        $th = $bbox[1] - $bbox[7];
        $x = (int) (($size - $tw) / 2) - $bbox[0];
        $y = (int) (($size + $th) / 2) - (int) ($size * 0.04);
        /* ombre légère pour la profondeur */
        imagettftext($im, $fs, 0, $x + max(1, (int) ($size * 0.008)), $y + max(1, (int) ($size * 0.008)), $rouge_fonce, $font_bold, $glyph);
        imagettftext($im, $fs, 0, $x, $y, $blanc, $font_bold, $glyph);

        /* Bandeau « GA » en bas */
        $fs2 = (int) ($size * $scale * 0.20);
        $bbox2 = imagettfbbox($fs2, 0, $font_bold, 'GA');
        $tw2 = $bbox2[2] - $bbox2[0];
        $x2 = (int) (($size - $tw2) / 2) - $bbox2[0];
        $y2 = (int) ($size * ($maskable ? 0.78 : 0.86));
        imagettftext($im, $fs2, 0, $x2, $y2, $blanc, $font_bold, 'GA');

        /* Liseré jaune en bas du badge */
        if (!$maskable) {
            $h = max(2, (int) ($size * 0.018));
            imagefilledrectangle($im, 0, $size - $h, $size, $size, $jaune);
        }
    } else {
        imagestring($im, 5, (int) (($size - 24) / 2), (int) (($size - 16) / 2), 'LFI', $blanc);
    }

    imagepng($im, null, 6);
    imagedestroy($im);
}

/* ============================================================== *
 *  Hooks de page : <head> dédié + pas de cache + no theme chrome  *
 * ============================================================== */
add_action('wp', 'lfi_nct_app_no_cache');
function lfi_nct_app_no_cache() {
    if (!is_singular()) return;
    $post = get_post();
    if (!$post || $post->post_name !== LFI_NCT_APP_SLUG) return;

    /* === Marge mémoire LARGE, posée TÔT (avant que le thème ne rende) ===
       Diagnostic : une vue de l'app peut faire grimper le pic mémoire d'un
       rendu complet (thème + navbar + plugins + vue) au-delà de la limite
       PHP de l'hébergeur → erreur fatale « mémoire épuisée » non
       rattrapable → page blanche. On relève la limite ici, sur le hook
       « wp », donc AVANT le rendu du thème (le bump fait dans le shortcode
       arrivait trop tard, après l'en-tête du thème). */
    if (function_exists('wp_raise_memory_limit')) wp_raise_memory_limit('lfi_nct_app');
    @ini_set('memory_limit', '512M');
    if (function_exists('set_time_limit')) @set_time_limit(120);

    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    if (!defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true);
    if (!defined('DONOTCACHEDB')) define('DONOTCACHEDB', true);
    do_action('litespeed_control_set_nocache', 'LFI App : page dynamique');
    /* DÉSACTIVE TOUTE l'optimisation LiteSpeed (minify/combine CSS-JS, lazyload…)
       sur la page de l'app : ces écrans contiennent de gros <script> inline
       (dictaphone, etc.) que l'optimiseur peut casser → page blanche. La page
       est dynamique et privée : aucun intérêt à l'optimiser. */
    do_action('litespeed_disable_all', 'LFI App : page dynamique lourde (scripts inline)');
    /* Envoie des headers no-cache aussi (au cas où LiteSpeed cache HTML
       au niveau serveur sans respecter DONOTCACHEPAGE). */
    nocache_headers();
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Vary: Cookie, Accept-Encoding');
        /* X-LiteSpeed-Cache-Control : directive spécifique LSCWP */
        header('X-LiteSpeed-Cache-Control: no-cache, no-vary');
    }
}

/* Template_redirect : gère les redirections AVANT que les headers soient
 * envoyés (les wp_safe_redirect depuis le shortcode échouent silencieusement
 * une fois que le thème a commencé à rendre le HTML). */
add_action('template_redirect', 'lfi_nct_app_handle_redirects', 1);
function lfi_nct_app_handle_redirects() {
    if (!is_singular()) return;
    $post = get_post();
    if (!$post || $post->post_name !== LFI_NCT_APP_SLUG) return;

    $vue = isset($_GET['vue']) ? sanitize_key($_GET['vue']) : '';

    /* Suppression d'un brouillon de réponse (écran « À envoyer » ou dossier). */
    if (!empty($_POST['lfi_reply_del']) && check_admin_referer('lfi_reply_del')) {
        $can = current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
        if ($can && function_exists('lfi_nct_reply_delete')) {
            $did = (int) ($_POST['dossier_id'] ?? 0);
            $idx = isset($_POST['reply_index']) && $_POST['reply_index'] !== '' ? (int) $_POST['reply_index'] : null;
            if ($did) lfi_nct_reply_delete($did, $idx);
        }
        $back = !empty($_POST['back']) ? esc_url_raw(wp_unslash($_POST['back'])) : lfi_nct_app_url('a-envoyer');
        wp_safe_redirect(add_query_arg('rdel', 1, $back));
        exit;
    }

    /* Redirige les routes d'inscription vers /app/ si user déjà connecté */
    if (is_user_logged_in() && in_array($vue, ['inscription', 'inscription-locataire', 'inscription-ga'], true)) {
        wp_safe_redirect(home_url('/app/'));
        exit;
    }

    /* Preview set : poser cookie + redirect home */
    if ($vue === 'preview-set' && current_user_can('manage_options')) {
        $uid = isset($_GET['uid']) ? (int) $_GET['uid'] : 0;
        if ($uid && get_userdata($uid)) {
            $secure = is_ssl();
            setcookie('lfi_app_preview_uid', (string) $uid, time() + 8 * HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, $secure, true);
        }
        wp_safe_redirect(home_url('/app/'));
        exit;
    }

    /* Preview exit : retirer cookie + retour picker */
    if ($vue === 'preview-exit') {
        $secure = is_ssl();
        setcookie('lfi_app_preview_uid', '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN, $secure, true);
        unset($_COOKIE['lfi_app_preview_uid']);
        wp_safe_redirect(home_url('/app/?vue=preview'));
        exit;
    }
}

add_action('wp_head', 'lfi_nct_app_head_meta', 1);
function lfi_nct_app_head_meta() {
    global $post;
    if (!is_a($post, 'WP_Post') || $post->post_name !== LFI_NCT_APP_SLUG) return;
    $manifest = esc_url(home_url('/?lfi_app=manifest&v=' . LFI_NCT_VERSION));
    $v        = LFI_NCT_VERSION;
    /* URLs d'icônes via query var = marchent même sans rewrite rules flushées */
    $ic180 = esc_url(lfi_nct_app_icon_url(180) . '&v=' . $v);
    $ic192 = esc_url(lfi_nct_app_icon_url(192) . '&v=' . $v);
    $ic152 = esc_url(lfi_nct_app_icon_url(152) . '&v=' . $v);
    $ic167 = esc_url(lfi_nct_app_icon_url(167) . '&v=' . $v);
    $ic512 = esc_url(lfi_nct_app_icon_url(512) . '&v=' . $v);
    ?>
    <link rel="manifest" href="<?php echo $manifest; ?>">
    <meta name="theme-color" content="#c8102e">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GA LFI">
    <!-- iOS : besoin de tailles précises sinon il prend une capture d'écran -->
    <link rel="apple-touch-icon"               href="<?php echo $ic180; ?>">
    <link rel="apple-touch-icon" sizes="152x152" href="<?php echo $ic152; ?>">
    <link rel="apple-touch-icon" sizes="167x167" href="<?php echo $ic167; ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $ic180; ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $ic192; ?>">
    <link rel="icon" type="image/png" sizes="512x512" href="<?php echo $ic512; ?>">
    <link rel="shortcut icon" href="<?php echo $ic192; ?>">
    <meta name="robots" content="noindex,nofollow,noarchive,nosnippet">
    <?php
}

/* ============================================================== *
 *  RENDU AUTONOME DE LA PAGE /app/ — SANS LE THÈME                 *
 *                                                                  *
 *  L'app est une PWA plein écran : elle n'a aucun besoin du thème  *
 *  (en-tête « France Insoumise », pied de page, CSS du thème…).    *
 *  Faire passer la page par le thème provoquait des conflits :     *
 *  en onglet navigateur (hors PWA installée), l'en-tête du thème   *
 *  s'affichait par-dessus et la mise en forme de l'app sautait     *
 *  (« écran blanc »). Le hiding du thème était en @media           *
 *  (display-mode: standalone), donc inactif dans un onglet.        *
 *                                                                  *
 *  On rend donc un document HTML COMPLET et autonome (exactement   *
 *  comme la page de diagnostic, qui s'affiche parfaitement), puis  *
 *  on sort : le thème n'est jamais invoqué.                        *
 * ============================================================== */
add_action('template_redirect', 'lfi_nct_app_render_standalone', 99);
function lfi_nct_app_render_standalone() {
    if (!is_singular()) return;
    $post = get_post();
    if (!$post || $post->post_name !== LFI_NCT_APP_SLUG) return;

    /* Marge mémoire (au cas où le hook wp n'aurait pas suffi) */
    if (function_exists('wp_raise_memory_limit')) wp_raise_memory_limit('lfi_nct_app');
    @ini_set('memory_limit', '512M');
    if (function_exists('set_time_limit')) @set_time_limit(120);

    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    nocache_headers();
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');

    /* Contenu de l'app (login OU router) — coquille auto-suffisante :
       le shortcode injecte lui-même ses styles, le Service Worker et le
       bouton d'urgence. */
    $content = do_shortcode('[lfi_nct_app]');
    $title   = get_the_title($post);
    ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
<?php lfi_nct_app_head_meta(); ?>
<title><?php echo esc_html($title ?: 'App du GA — LFI Nantes Sud Clos Toreau'); ?></title>
</head>
<body class="page-app lfi-standalone">
<?php echo $content; ?>
</body>
</html>
<?php
    exit;
}

/* ============================================================== *
 *  Shortcode [lfi_nct_app] — coquille de la PWA + router          *
 * ============================================================== */
add_shortcode('lfi_nct_app', 'lfi_nct_app_shortcode');
function lfi_nct_app_shortcode() {
    /* === Anti-page-blanche niveau « fatal » ===
       Le try/catch plus bas n'attrape PAS les erreurs fatales non
       rattrapables (mémoire épuisée, dépassement de temps, erreur de
       compilation). On augmente la marge mémoire/temps pour les écrans
       lourds, et on pose un garde-fou de shutdown qui, en cas de fatal
       pendant le rendu de l'app, affiche un message lisible (+ le détail
       pour l'admin) au lieu d'un écran vide. */
    if (function_exists('wp_raise_memory_limit')) wp_raise_memory_limit('lfi_nct_app');
    @ini_set('memory_limit', '512M');
    if (function_exists('set_time_limit')) @set_time_limit(60);

    if (!defined('LFI_NCT_APP_RENDERING')) define('LFI_NCT_APP_RENDERING', true);
    static $shutdown_guard = false;
    if (!$shutdown_guard) {
        $shutdown_guard = true;
        register_shutdown_function(function () {
            $e = error_get_last();
            if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
            /* On ne réagit qu'aux fatals survenus dans le code du plugin (rendu app). */
            if (empty($e['file']) || strpos($e['file'], 'lfi-nantes-clos-toreau') === false) return;
            if (function_exists('error_log')) {
                error_log('[LFI app] FATAL pendant le rendu : ' . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line']);
            }
            /* Nettoie tout buffer partiel pour ne pas afficher un demi-écran. */
            while (ob_get_level() > 0) { @ob_end_clean(); }
            $is_admin = function_exists('current_user_can') && current_user_can('manage_options');
            echo '<div style="max-width:640px;margin:24px auto;background:#fff;border-radius:12px;padding:22px;font-family:-apple-system,BlinkMacSystemFont,sans-serif;text-align:center;color:#1a1a1a;box-shadow:0 2px 14px rgba(0,0,0,.12)">';
            echo '<div style="font-size:2.4em">😕</div>';
            echo '<h2 style="color:#c8102e;margin:6px 0 8px">Cette page a rencontré un souci technique</h2>';
            echo '<p style="color:#555;line-height:1.5">Rien n\'est perdu. Reviens à l\'accueil et réessaie.</p>';
            if ($is_admin) {
                echo '<p style="font-size:.8em;color:#999;background:#f7f7f7;padding:10px;border-radius:6px;text-align:left;word-break:break-word">' . htmlspecialchars($e['message']) . '<br><small>' . htmlspecialchars(basename($e['file'])) . ':' . (int) $e['line'] . '</small></p>';
            }
            echo '<a href="' . esc_url(home_url('/app/')) . '" style="display:inline-block;margin-top:8px;background:#c8102e;color:#fff;padding:11px 20px;border-radius:8px;text-decoration:none;font-weight:700">🏠 Retour à l\'accueil</a>';
            echo '</div>';
        });
    }

    ob_start();

    /* Routes accessibles sans login : installer + flow d'inscription */
    $vue_public = isset($_GET['vue']) ? sanitize_key($_GET['vue']) : '';
    $public_routes = [
        'installer'              => 'lfi_nct_app_view_installer',
        'inscription'            => 'lfi_nct_app_view_inscription',
        'inscription-locataire'  => 'lfi_nct_app_view_inscription_locataire',
        'inscription-ga'         => 'lfi_nct_app_view_inscription_ga',
        'flyer'                  => 'lfi_nct_app_view_flyer',
        'aide'                   => 'lfi_nct_app_view_aide',
        'infos-cles'             => 'lfi_nct_app_view_infos_cles',
        'signaler-bug'           => 'lfi_nct_app_view_signaler_bug',
        'victoires'              => 'lfi_nct_app_view_victoires',
    ];
    if (isset($public_routes[$vue_public]) && function_exists($public_routes[$vue_public])) {
        call_user_func($public_routes[$vue_public]);
        lfi_nct_app_render_styles();
        lfi_nct_app_render_register_sw();
        if (is_user_logged_in()) lfi_nct_app_render_emergency_button();
        lfi_nct_app_render_assistant_button();
        if (function_exists('lfi_nct_app_render_feedback_button')) lfi_nct_app_render_feedback_button();
        return ob_get_clean();
    }

    /* Routes admin du mode aperçu : poser ou retirer le cookie */
    if ($vue_public === 'preview-set' && function_exists('lfi_nct_app_view_preview_set')) {
        lfi_nct_app_view_preview_set();
        return ob_get_clean();
    }
    if ($vue_public === 'preview-exit' && function_exists('lfi_nct_app_view_preview_exit')) {
        lfi_nct_app_view_preview_exit();
        return ob_get_clean();
    }

    /* Picker du mode aperçu (admin seulement, sans cookie posé) */
    if ($vue_public === 'preview' && function_exists('lfi_nct_app_view_preview_picker')) {
        if (current_user_can('manage_options') && !lfi_nct_app_preview_uid_from_cookie()) {
            lfi_nct_app_view_preview_picker();
            lfi_nct_app_render_styles();
            lfi_nct_app_render_register_sw();
            return ob_get_clean();
        }
    }

    /* Si le cookie de preview est posé et l'admin réel existe, on bascule */
    $previewed_user = function_exists('lfi_nct_app_preview_apply') ? lfi_nct_app_preview_apply() : null;
    if ($previewed_user) {
        lfi_nct_app_render_preview_banner($previewed_user);
    }

    try {
    if (!is_user_logged_in()) {
        lfi_nct_app_render_login();
    } else {
        /* Journalise l'usage de l'app (1 fois/jour/utilisateur). */
        if (function_exists('lfi_nct_activity_track_app')) lfi_nct_activity_track_app();
        /* Accueil « première connexion » membre/locataire (choisir son mot de
           passe + installer l'app) — la coquille app n'exécute pas wp_footer,
           donc on rend l'overlay ici, avant le routeur, sur toutes les vues. */
        if (function_exists('lfi_nct_member_onb_render')) lfi_nct_member_onb_render();
        $handled = false;
        if (function_exists('lfi_nct_app_role_dispatch')) {
            lfi_nct_app_role_dispatch($handled);
        }
        if (!$handled) {
            /* Routeur « admin » : ouvert au super-admin ET aux admins de GA
               (binôme + promus). Les données sont cloisonnées par GA dans
               chaque vue. Certaines routes restent réservées au super-admin. */
            $can_admin = function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options');
            if (!$can_admin) {
                echo '<div class="lfi-app"><div class="lfi-app-error">Console réservée. <a href="' . esc_url(wp_logout_url(home_url('/app/'))) . '">Se déconnecter</a>.</div></div>';
            } else {
                $vue = isset($_GET['vue']) ? sanitize_key($_GET['vue']) : '';
                /* Routes RÉSERVÉES au super-admin (réseau des GA, système). Un
                   admin de GA qui les vise est renvoyé vers son tableau de bord. */
                $super_only = [
                    'groupes', 'reseau-ga', 'reseau-carte', 'reseau-stats-enquete', 'reseau-ga-pdf', 'voir-ga',
                    'national', 'national-args', 'national-etudes', 'national-pdf', 'sauvegarde', 'suggestions', 'activite',
                    'modules-params', 'cache', 'preview', 'preview-set', 'preview-exit',
                    'strategie-municipale', 'strategie-nationale', 'geo-perimetres',
                    'partenaires', 'partenaire-espace',
                    'sante', 'sante-analyse', 'sante-propositions',
                ];
                if (in_array($vue, $super_only, true) && !current_user_can('manage_options')) {
                    wp_safe_redirect(lfi_nct_app_url());
                    exit;
                }
                switch ($vue) {
                    case 'reunion':         lfi_nct_app_view_reunion();         break;
                    case 'membres':         lfi_nct_app_view_membres();         break;
                    case 'evenements':      lfi_nct_app_view_evenements();      break;
                    case 'sms':             lfi_nct_app_view_sms();             break;
                    case 'email':           lfi_nct_app_view_email();           break;
                    case 'enquetes':        lfi_nct_app_view_enquetes();        break;
                    case 'enquete-edit':    lfi_nct_app_view_enquete_edit();    break;
                    case 'enquetes-corbeille': lfi_nct_app_view_enquetes_corbeille(); break;
                    case 'enquetes-sms':    lfi_nct_app_view_enquetes_sms();    break;
                    case 'enquetes-email':  lfi_nct_app_view_enquetes_email();  break;
                    case 'event-sms':       lfi_nct_app_view_event_sms();       break;
                    case 'event-inscrits':  lfi_nct_app_view_event_inscrits();  break;
                    case 'stats':           lfi_nct_app_view_stats();           break;
                    case 'cache':           lfi_nct_app_view_cache();           break;
                    case 'comptes':            lfi_nct_app_view_comptes();              break;
                    case 'comptes-ga':         lfi_nct_app_view_comptes_ga();           break;
                    case 'comptes-locataires': lfi_nct_app_view_comptes_locataires();   break;
                    case 'temoignage-add':     lfi_nct_app_view_temoignage_add();       break;
                    case 'enquete-photos':     lfi_nct_app_view_enquete_photos();       break;
                    case 'dossiers':        lfi_nct_app_view_dossiers();        break;
                    case 'dossier':         lfi_nct_app_view_dossier();         break;
                    case 'dossier-recap-nmh': lfi_nct_app_view_dossier_recap_nmh(); break;
                    case 'dossier-avocat':    lfi_nct_app_view_dossier_avocat();    break;
                    case 'signatures':      lfi_nct_app_view_signatures();      break;
                    case 'carte':           lfi_nct_app_view_carte();           break;
                    case 'stats-enquete':   lfi_nct_app_view_stats_enquete();   break;
                    case 'sms-locataires':  lfi_nct_app_view_sms_locataires();  break;
                    case 'mon-profil':      lfi_nct_app_view_mon_profil();      break;
                    case 'installer':       lfi_nct_app_view_installer();       break;
                    case 'partenaires':        lfi_nct_app_view_partenaires();        break;
                    case 'partenaire-espace':  lfi_nct_app_view_partenaire_espace();  break;
                    case 'nmh':                lfi_nct_app_view_partenaire_nmh();     break;
                    case 'ase':                lfi_nct_app_view_ase();                break;
                    case 'elus':               lfi_nct_app_view_elus_membre();        break;
                    case 'sante':              lfi_nct_app_view_sante();              break;
                    case 'sante-analyse':      lfi_nct_app_view_sante_analyse();      break;
                    case 'sante-propositions': lfi_nct_app_view_sante_propositions(); break;
                    case 'interventions':         lfi_nct_app_view_interventions();          break;
                    case 'intervention-add':      lfi_nct_app_view_intervention_add();       break;
                    case 'intervention-edit':     lfi_nct_app_view_intervention_edit();      break;
                    case 'facture':               lfi_nct_app_view_facture();                break;
                    case 'facturation-params':    lfi_nct_app_view_facturation_params();     break;
                    case 'recouvrements':              lfi_nct_app_view_recouvrements();                break;
                    case 'recouvrement-dossier':       lfi_nct_app_view_recouvrement_dossier();         break;
                    case 'recouvrement-doc-mandat':    lfi_nct_app_view_recouvrement_doc_mandat();      break;
                    case 'recouvrement-doc-med1':      lfi_nct_app_view_recouvrement_doc_med1();        break;
                    case 'recouvrement-doc-med2':      lfi_nct_app_view_recouvrement_doc_med2();        break;
                    case 'recouvrement-doc-cdc':       lfi_nct_app_view_recouvrement_doc_cdc();         break;
                    case 'recouvrement-doc-tj':        lfi_nct_app_view_recouvrement_doc_tj();          break;
                    case 'recouvrement-doc-schs':      lfi_nct_app_view_recouvrement_doc_schs();        break;
                    case 'dossiers-juridiques':        lfi_nct_app_view_dossiers_juridiques();          break;
                    case 'dossier-juridique-add':      lfi_nct_app_view_dossier_juridique_add();        break;
                    case 'dossier-juridique-edit':     lfi_nct_app_view_dossier_juridique_edit();       break;
                    case 'cadre-juridique':            lfi_nct_app_view_cadre_juridique();              break;
                    case 'montage-financier':          lfi_nct_app_view_montage_financier();            break;
                    case 'aj-calcul':                  lfi_nct_app_view_aj_calcul();                    break;
                    case 'doc-strategie-avocats':      lfi_nct_app_view_doc_strategie_avocats();        break;
                    case 'dossier-synthese':           lfi_nct_app_view_dossier_synthese();             break;
                    case 'association':                lfi_nct_app_view_association();                  break;
                    case 'email-import':               lfi_nct_app_view_email_import();                 break;
                    case 'asso-statuts':               lfi_nct_app_view_asso_statuts();                 break;
                    case 'dossier-doc-rapport-visite': lfi_nct_app_view_dossier_doc_rapport_visite();   break;
                    case 'dossier-doc-reponse-nmh':    lfi_nct_app_view_dossier_doc_reponse_nmh();      break;
                    case 'dossier-doc-analyse-nmh':    lfi_nct_app_view_dossier_doc_analyse_nmh();      break;
                    case 'dossier-doc-adhesion':       lfi_nct_app_view_dossier_doc_adhesion();         break;
                    case 'dossier-doc-lrar-travaux':   lfi_nct_app_view_dossier_doc_lrar_travaux();     break;
                    case 'dossier-doc-lrar-relogement':lfi_nct_app_view_dossier_doc_lrar_relogement();  break;
                    case 'dossier-doc-schs':           lfi_nct_app_view_dossier_doc_schs();             break;
                    case 'dossier-doc-ars':            lfi_nct_app_view_dossier_doc_ars();              break;
                    case 'dossier-send-email':         lfi_nct_app_view_dossier_send_email();           break;
                    case 'appels-nmh':                 lfi_nct_app_view_appels_nmh();                   break;
                    case 'appel-nmh-add':              lfi_nct_app_view_appel_nmh_add();                break;
                    case 'appel-nmh-edit':             lfi_nct_app_view_appel_nmh_edit();               break;
                    case 'appel-nmh-rapport':          lfi_nct_app_view_appel_nmh_rapport();            break;
                    case 'appel-guide':                lfi_nct_app_view_appel_guide();                  break;
                    case 'tutoriels':             lfi_nct_app_view_tutoriels();              break;
                    case 'tutoriel':              lfi_nct_app_view_tutoriel();               break;
                    case 'agenda':                lfi_nct_app_view_agenda();                 break;
                    case 'rdv-add':               lfi_nct_app_view_rdv_add();                break;
                    case 'rdv-edit':              lfi_nct_app_view_rdv_edit();               break;
                    case 'outils':                lfi_nct_app_view_outils();                 break;
                    case 'outil-sonometre':       lfi_nct_app_view_outil_sonometre();        break;
                    case 'outil-niveau':          lfi_nct_app_view_outil_niveau();           break;
                    case 'outil-boussole':        lfi_nct_app_view_outil_boussole();         break;
                    case 'outil-gps':             lfi_nct_app_view_outil_gps();              break;
                    case 'outil-photo-preuve':    lfi_nct_app_view_outil_photo_preuve();     break;
                    case 'outil-humidite':        lfi_nct_app_view_outil_humidite();         break;
                    case 'outil-regle':           lfi_nct_app_view_outil_regle();            break;
                    case 'ga-liste':              lfi_nct_app_view_ga_liste();               break;
                    case 'prefecture':            lfi_nct_app_view_prefecture();             break;
                    case 'prefecture-rapport':    lfi_nct_app_view_prefecture_rapport();     break;
                    case 'prefecture-email':      lfi_nct_app_view_prefecture_email();       break;
                    case 'reussites':             lfi_nct_app_view_reussites();              break;
                    case 'dossier-wizard':        lfi_nct_app_view_dossier_wizard();         break;
                    case 'modules-params':        lfi_nct_app_view_modules_params();         break;
                    case 'ga-params':             lfi_nct_app_view_ga_params();              break;
                    case 'evenement-add':         lfi_nct_app_view_evenement_add();          break;
                    case 'evenement-edit':        lfi_nct_app_view_evenement_edit();         break;
                    case 'integration-key':       lfi_nct_app_view_integration_key();        break;
                    case 'dossier-piece-dl':      lfi_nct_ingest_download();                 break;
                    case 'journal':               lfi_nct_app_view_journal();                break;
                    case 'journal-edit':          lfi_nct_app_view_journal_edit();           break;
                    case 'strategie':             lfi_nct_app_view_strategie();              break;
                    case 'architecte':            lfi_nct_app_view_architecte();             break;
                    case 'prejudice':             lfi_nct_app_view_prejudice();              break;
                    case 'dossier-scientifique':  lfi_nct_app_view_dossier_scientifique();   break;
                    case 'geo-contacts':          lfi_nct_app_view_geo_contacts();           break;
                    case 'enquete-doublons':      lfi_nct_app_view_enquete_doublons();       break;
                    case 'bug-reports':           lfi_nct_app_view_bug_reports();            break;
                    case 'geo-perimetres':        lfi_nct_app_view_geo_perimetres();         break;
                    case 'suggerer-outil':        lfi_nct_app_view_suggerer_outil();         break;
                    case 'prejudice-report':      lfi_nct_app_view_prejudice_report();       break;
                    case 'jurisprudence':         lfi_nct_app_view_jurisprudence();          break;
                    case 'mailcheck':             lfi_nct_app_view_mailcheck();              break;
                    case 'a-envoyer':             lfi_nct_app_view_a_envoyer();              break;
                    case 'enquete':               lfi_nct_app_view_enquete();                break;
                    case 'mobilisation':          lfi_nct_app_view_mobilisation();           break;
                    case 'agenda-invite':         lfi_nct_app_view_agenda_invite();          break;
                    case 'dispos':                lfi_nct_app_view_dispos();                 break;
                    case 'dispos-communes':       lfi_nct_app_view_dispos_communes();        break;
                    case 'propositions':          lfi_nct_app_view_propositions();           break;
                    case 'generer-reponse':       lfi_nct_app_view_generer_reponse();        break;
                    case 'rgpd':                  lfi_nct_app_view_rgpd();                   break;
                    case 'rgpd-registre':         lfi_nct_app_view_rgpd_registre();          break;
                    case 'rgpd-politique':        lfi_nct_app_view_rgpd_politique();         break;
                    case 'guide':                 lfi_nct_app_view_guide();                  break;
                    case 'groupes':               lfi_nct_app_view_groupes();                break;
                    case 'reseau-ga':             lfi_nct_app_view_reseau_ga();              break;
                    case 'reseau-carte':          lfi_nct_app_view_carte(true);              break;
                    case 'reseau-stats-enquete':  lfi_nct_app_view_stats_enquete(true);      break;
                    case 'audit-nmh':            lfi_nct_app_view_audit_nmh();              break;
                    case 'strategie-municipale':  lfi_nct_app_view_strategie_municipale();   break;
                    case 'strategie-nationale':   lfi_nct_app_view_strategie_nationale();    break;
                    case 'national':              lfi_nct_app_view_national();               break;
                    case 'national-args':         lfi_nct_app_view_national_args();          break;
                    case 'national-etudes':       lfi_nct_app_view_national_etudes();        break;
                    case 'national-pdf':          lfi_nct_app_view_national_pdf();            break;
                    case 'sauvegarde':            lfi_nct_app_view_sauvegarde();             break;
                    case 'suggestions':           lfi_nct_app_view_suggestions();            break;
                    case 'activite':              lfi_nct_app_view_activite();               break;
                    case 'assistant':             lfi_nct_app_view_assistant();              break;
                    case 'compta':                lfi_nct_app_view_compta();                 break;
                    case 'compta-relances':       lfi_nct_app_view_compta_relances();        break;
                    case 'compta-export':         lfi_nct_app_view_compta_export();          break;
                    case 'reseau-ga-pdf':         lfi_nct_app_view_reseau_ga_pdf();          break;
                    case 'voir-ga':               lfi_nct_app_view_voir_ga();                break;
                    case 'reussite-edit':         lfi_nct_app_view_reussite_edit();          break;
                    case 'reussite-article':      lfi_nct_app_view_reussite_article();       break;
                    default:                lfi_nct_app_render_dashboard();
                }
            }
        }
    }
    } catch (\Throwable $e) {
        /* FILET DE SÉCURITÉ : plus jamais de page blanche. Si une vue
           plante (fonction, DB, etc.), on affiche une erreur claire +
           un retour, au lieu d'un écran vide. */
        if (function_exists('error_log')) {
            error_log('[LFI app] Erreur de rendu vue "' . (isset($_GET['vue']) ? sanitize_key($_GET['vue']) : 'dashboard') . '" : ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
        echo '<div class="lfi-app"><div class="lfi-app-screen">';
        echo '<div style="background:#fff;border-radius:12px;padding:20px;margin:14px;text-align:center;color:#1a1a1a">';
        echo '<div style="font-size:2.4em;margin-bottom:8px">😕</div>';
        echo '<h2 style="color:#c8102e;margin:0 0 8px">Cette page a rencontré un souci</h2>';
        echo '<p style="color:#555;line-height:1.5">Pas de panique, rien n\'est perdu. Réessaie, ou reviens à l\'accueil.</p>';
        if (current_user_can('manage_options')) {
            echo '<p style="font-size:.8em;color:#999;background:#f7f7f7;padding:8px;border-radius:6px;text-align:left;word-break:break-word">' . esc_html($e->getMessage()) . '<br><small>' . esc_html(basename($e->getFile())) . ':' . (int) $e->getLine() . '</small></p>';
        }
        echo '<div style="margin-top:14px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap">';
        echo '<a href="' . esc_url(home_url('/app/')) . '" style="background:#c8102e;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:700">🏠 Accueil</a>';
        echo '<a href="#" onclick="location.reload();return false;" style="background:#fff;color:#c8102e;border:1.5px solid #c8102e;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:700">🔄 Réessayer</a>';
        echo '</div>';
        echo '</div></div></div>';
    }

    /* Rendu final protégé : ces 3 fonctions ne doivent jamais blanchir
       la page si l'une d'elles échoue. */
    try {
        lfi_nct_app_render_styles();
        lfi_nct_app_render_register_sw();
        if (is_user_logged_in()) lfi_nct_app_render_emergency_button();
        lfi_nct_app_render_assistant_button();
        if (function_exists('lfi_nct_app_render_feedback_button')) lfi_nct_app_render_feedback_button();
    } catch (\Throwable $e) {
        if (function_exists('error_log')) error_log('[LFI app] Erreur rendu final : ' . $e->getMessage());
    }

    return ob_get_clean();
}

/* Helpers réutilisés par les écrans natifs */
function lfi_nct_app_url($vue = '', $extra = []) {
    $base = home_url('/' . LFI_NCT_APP_SLUG . '/');
    $args = [];
    if ($vue) $args['vue'] = $vue;
    if ($extra) $args = array_merge($args, $extra);
    return $args ? add_query_arg($args, $base) : $base;
}

function lfi_nct_app_screen_open($title, $subtitle = '') {
    ?>
    <div class="lfi-app lfi-app-screen">
        <div class="lfi-app-navbar">
            <a class="lfi-app-back" href="<?php echo esc_url(lfi_nct_app_url()); ?>" onclick="if(window.history.length>1){history.back();return false;}" aria-label="Revenir en arrière">←</a>
            <div class="lfi-app-screen-title">
                <div class="t"><?php echo esc_html($title); ?></div>
                <?php if ($subtitle): ?><div class="s"><?php echo esc_html($subtitle); ?></div><?php endif; ?>
            </div>
            <a class="lfi-app-home" href="<?php echo esc_url(lfi_nct_app_url()); ?>" aria-label="Accueil">⌂</a>
        </div>
        <div class="lfi-app-screen-body">
    <?php
}

function lfi_nct_app_screen_close($more_tiles = true) {
    if ($more_tiles) {
        $can_admin = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
        $is_ga_member = function_exists('lfi_nct_user_role_ga') && lfi_nct_user_role_ga();
        if ($can_admin) {
            /* Admin : raccourcis complets (tuiles admin). */
            $tiles = lfi_nct_admin_get_tiles();
            echo '<div class="lfi-app-other-shortcuts"><div class="lab">Aller à un autre écran</div><div class="row">';
            foreach ($tiles as $t) {
                if (strpos($t[3], '/app/?vue=') === false && strpos($t[3], 'lfi-nct') === false) continue;
                $vue_url = $t[3];
                if (strpos($vue_url, lfi_nct_app_url()) !== 0 && strpos($vue_url, '?vue=') === false) continue;
                echo '<a class="chip" href="' . esc_url($vue_url) . '">' . $t[0] . ' ' . esc_html($t[1]) . '</a>';
            }
            echo '</div></div>';
        } elseif ($is_ga_member) {
            /* Membre simple : UNIQUEMENT ses raccourcis autorisés. Aucune donnée
               locataire (comptes, dossiers, SMS/email, stats, cache…). */
            $ml = [
                ['🤝', 'Se coordonner',            lfi_nct_app_url('mobilisation')],
                ['💡', 'Idées d\'actions',         lfi_nct_app_url('propositions')],
                ['📅', 'Événements',               lfi_nct_app_url('evenements')],
                ['📋', 'Faire passer une enquête', lfi_nct_app_url('enquete')],
                ['📸', 'Photos',                   lfi_nct_app_url('enquete-photos')],
                ['🤖', 'Aide',                     lfi_nct_app_url('aide')],
            ];
            echo '<div class="lfi-app-other-shortcuts"><div class="lab">Aller à un autre écran</div><div class="row">';
            foreach ($ml as $t) echo '<a class="chip" href="' . esc_url($t[2]) . '">' . $t[0] . ' ' . esc_html($t[1]) . '</a>';
            echo '</div></div>';
        }
        /* Locataire / autre : pas de raccourcis « admin ». */
    }
    echo '</div></div>';
}

function lfi_nct_app_flash($msg, $type = 'ok') {
    $class = $type === 'ok' ? 'lfi-app-flash ok' : 'lfi-app-flash err';
    echo '<div class="' . esc_attr($class) . '">' . esc_html($msg) . '</div>';
}

/**
 * Transforme les sections (titres h2/h3) de l'écran courant en blocs
 * REPLIABLES (accordéon), pour les pages trop longues/fouillis. Tout reste
 * accessible mais on ouvre/ferme chaque section d'un appui. Côté client :
 * regroupe le contenu situé entre deux titres et le rend pliable. La première
 * section est ouverte par défaut.
 */
function lfi_nct_render_section_accordion_js($open_first = true) {
    $of = $open_first ? 'true' : 'false';
    ?>
    <script>
    (function(){
        var bodies = document.querySelectorAll('.lfi-app-screen-body');
        var body = bodies[bodies.length - 1];
        if (!body || body.getAttribute('data-acc')) return;
        body.setAttribute('data-acc', '1');
        var kids = Array.prototype.slice.call(body.children);
        var groups = [], cur = null;
        kids.forEach(function(n){
            if (/^H[23]$/.test(n.tagName)) { cur = { head: n, items: [] }; groups.push(cur); }
            else if (cur) { cur.items.push(n); }
        });
        if (groups.length < 2) return;
        groups.forEach(function(g, i){
            var wrap = document.createElement('div');
            wrap.className = 'lfi-acc-body';
            g.items.forEach(function(it){ wrap.appendChild(it); });
            g.head.parentNode.insertBefore(wrap, g.head.nextSibling);
            g.head.style.cursor = 'pointer';
            g.head.style.userSelect = 'none';
            var open = (i === 0 && <?php echo $of; ?>);
            wrap.style.display = open ? '' : 'none';
            var ar = document.createElement('span');
            ar.textContent = '▾';
            ar.style.cssText = 'float:right;transition:transform .15s ease;' + (open ? '' : 'transform:rotate(-90deg);');
            g.head.insertBefore(ar, g.head.firstChild);
            g.head.addEventListener('click', function(e){
                if (e.target.closest('a,button,input,textarea,select,label')) return;
                var vis = wrap.style.display !== 'none';
                wrap.style.display = vis ? 'none' : '';
                ar.style.transform = vis ? 'rotate(-90deg)' : '';
            });
        });
    })();
    </script>
    <?php
}

function lfi_nct_app_render_login() {
    $redirect = home_url('/' . LFI_NCT_APP_SLUG . '/');
    ?>
    <div class="lfi-app">
        <div class="lfi-app-header">
            <div class="lfi-app-logo">Φ</div>
            <h1>GA LFI Nantes Sud Clos Toreau</h1>
            <p class="lfi-app-sub">Console mobile du Groupe d'Action</p>
        </div>

        <form class="lfi-app-login" action="<?php echo esc_url(wp_login_url($redirect)); ?>" method="post">
            <label>
                <span>Identifiant ou e-mail</span>
                <input type="text" name="log" id="lfi-login-id" autocomplete="username"
                       autocapitalize="none" autocorrect="off" spellcheck="false" required>
            </label>
            <label>
                <span>Mot de passe</span>
                <input type="password" name="pwd" autocomplete="current-password" required>
            </label>
            <label class="lfi-app-checkbox">
                <input type="checkbox" name="rememberme" value="forever" checked>
                <span>Rester connecté</span>
            </label>
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">
            <button type="submit">Se connecter</button>
            <div style="text-align:center;margin-top:8px">
                <a href="<?php echo esc_url(wp_lostpassword_url($redirect)); ?>" style="color:#c8102e;font-size:.9em;text-decoration:none">🔓 Mot de passe oublié ?</a>
            </div>
        </form>

        <div class="lfi-app-help" style="margin-top:18px;text-align:center;background:#fff3f5;border-left:4px solid #c8102e">
            <strong>Pas encore de compte ?</strong><br>
            <a href="<?php echo esc_url(lfi_nct_app_url('inscription')); ?>" style="display:inline-block;margin-top:8px;background:#c8102e;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:700">✍️ S'inscrire en quelques secondes</a>
        </div>

        <div class="lfi-app-install-hint">
            <strong>📱 Installer l'app sur le téléphone</strong>
            <div class="ios">iPhone : Safari → bouton « Partager » → <strong>Sur l'écran d'accueil</strong></div>
            <div class="android">Android : Chrome → menu ⋮ → <strong>Installer l'application</strong></div>
        </div>
    </div>
    <script>
    /* Mémorise l'identifiant/email de connexion dans l'app : pré-rempli au
       prochain accès pour ne pas avoir à le retaper. */
    (function () {
        try {
            var f = document.querySelector('.lfi-app-login');
            if (!f) return;
            var inp = document.getElementById('lfi-login-id');
            if (inp && !inp.value) {
                var saved = localStorage.getItem('lfi_login_id');
                if (saved) inp.value = saved;
            }
            f.addEventListener('submit', function () {
                try { if (inp && inp.value) localStorage.setItem('lfi_login_id', inp.value.trim()); } catch (e) {}
            });
        } catch (e) {}
    })();
    </script>
    <?php
}

function lfi_nct_app_render_dashboard() {
    /* Accueil première connexion (admin de GA) — traite l'envoi puis affichera
       le pop-up si besoin. Doit passer avant tout rendu (redirection). */
    if (function_exists('lfi_nct_onboarding_maybe_handle')) lfi_nct_onboarding_maybe_handle();

    $can_admin = function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options');
    if (!$can_admin) {
        echo '<div class="lfi-app"><div class="lfi-app-error">Cette console est réservée aux administrateurs du GA. <a href="' . esc_url(wp_logout_url(home_url('/' . LFI_NCT_APP_SLUG . '/'))) . '">Se déconnecter</a>.</div></div>';
        return;
    }

    $user = wp_get_current_user();
    $stats = lfi_nct_app_quick_stats();
    $sections = lfi_nct_admin_get_tiles_sections($stats);

    /* « Vrai » super-admin sur SON espace (pas en train de regarder un GA) :
       lui seul voit l'espace réseau et les outils système. Pour un admin de GA
       — et pour le super-admin quand il regarde un autre GA — on retire ces
       sections (création de GA, bascule, système avancé). */
    $is_super_home = current_user_can('manage_options')
        && (!function_exists('lfi_nct_scope_ga_slug') || lfi_nct_scope_ga_slug() === '');
    if (!$is_super_home) {
        /* Les volets municipal et national ne concernent que le super-admin. */
        unset($sections['🏛️ VOLET MUNICIPAL — élus locaux']);
        unset($sections['🇫🇷 VOLET NATIONAL — député·es']);
        /* Section système réduite aux outils utiles à un GA. */
        $sections['⚙️ SYSTÈME'] = [
            ['📖', 'Guide d\'utilisation', 'Tout l\'outil, pas à pas',            lfi_nct_app_url('guide')],
            ['📈', 'Statistiques',        'Les compteurs de mon GA',             lfi_nct_app_url('stats')],
            ['🔄', 'Synchroniser',        'Forcer la maj sur mes appareils',     admin_url('admin-post.php?action=lfi_nct_purge_all')],
            ['🚪', 'Se déconnecter',      '',                                    wp_logout_url(home_url('/'))],
        ];
    }
    /* Pop-up d'accueil au tout premier login d'un admin de GA. */
    if (function_exists('lfi_nct_onboarding_render')) lfi_nct_onboarding_render();
    ?>
    <div class="lfi-app">
        <div class="lfi-app-topbar">
            <div class="lfi-app-logo-mini">Φ</div>
            <div>
                <div class="lfi-app-hi">Bonjour <?php echo esc_html($user->display_name ?: $user->user_login); ?></div>
                <div class="lfi-app-sub2">Console GA · <?php echo esc_html(wp_date('l j M Y', current_time('timestamp'))); ?></div>
            </div>
        </div>

        <div class="lfi-app-quick">
            <?php $reunion_past = function_exists('lfi_nct_reunion_confluences_is_past') && lfi_nct_reunion_confluences_is_past(); ?>
            <?php if (!$reunion_past) : ?>
            <div class="q"><span class="n"><?php echo (int) $stats['reunion']; ?></span><span class="l">Inscrits 26 juin</span></div>
            <?php endif; ?>
            <div class="q"><span class="n"><?php echo (int) $stats['surveys']; ?></span><span class="l">Enquêtes</span></div>
            <div class="q"><span class="n"><?php echo (int) $stats['membres']; ?></span><span class="l">Membres actifs</span></div>
            <?php if (function_exists('lfi_nct_reussites_count_published')): ?>
            <a class="q" href="<?php echo esc_url(lfi_nct_app_url('victoires')); ?>" style="text-decoration:none;color:inherit"><span class="n">🏆 <?php echo (int) lfi_nct_reussites_count_published(); ?></span><span class="l">Victoires</span></a>
            <?php endif; ?>
        </div>

        <?php if (function_exists('lfi_nct_render_ga_switcher')) lfi_nct_render_ga_switcher(); ?>
        <?php if (function_exists('lfi_nct_user_role_partner') && lfi_nct_user_role_partner() && !current_user_can('manage_options')): ?>
            <a href="<?php echo esc_url(lfi_nct_app_url('espace')); ?>" style="text-decoration:none;color:inherit;display:block">
              <div style="margin:0 0 14px;background:linear-gradient(135deg,#4b2e83,#6f4bb0);color:#fff;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px">
                <div style="font-size:1.8em">🏛️</div>
                <div style="flex:1"><div style="font-weight:900">Mon espace élu·e</div><div style="font-size:.88em;opacity:.95">Ta ligne directe avec Fabrice + ton dossier partagé</div></div>
                <div style="background:rgba(255,255,255,.22);border-radius:20px;padding:6px 12px;font-weight:800;font-size:.85em">Ouvrir →</div>
              </div>
            </a>
        <?php endif; ?>
        <?php if (function_exists('lfi_nct_render_home_vote_banner')) lfi_nct_render_home_vote_banner(); ?>
        <?php if (function_exists('lfi_nct_partner_admin_notice')) lfi_nct_partner_admin_notice(); ?>
        <?php if (function_exists('lfi_nct_render_home_alerts')) lfi_nct_render_home_alerts(); ?>
        <?php if (function_exists('lfi_nct_render_vote_popup')) lfi_nct_render_vote_popup(); ?>
        <?php if (function_exists('lfi_nct_render_reussite_celebration')) lfi_nct_render_reussite_celebration(); ?>
        <?php if (function_exists('lfi_nct_geo_admin_notice')) lfi_nct_geo_admin_notice(); ?>
        <?php if (function_exists('lfi_nct_dup_admin_notice')) lfi_nct_dup_admin_notice(); ?>
        <?php if (function_exists('lfi_nct_feedback_admin_notice')) lfi_nct_feedback_admin_notice(); ?>
        <?php if (function_exists('lfi_nct_mobi_admin_notice')) lfi_nct_mobi_admin_notice(); ?>
        <?php if (function_exists('lfi_nct_architecte_render_panel')) lfi_nct_architecte_render_panel(); ?>

        <?php /* ============ L'ESSENTIEL : ce dont tu te sers tous les jours ============ */
        $essentiel = [
            ['📋', 'Faire passer une enquête', 'Porte-à-porte',        lfi_nct_app_url('enquete')],
            ['📥', 'À envoyer',   'Tes réponses prêtes',        lfi_nct_app_url('a-envoyer')],
            ['🗂', 'Dossiers',    'Le suivi des locataires',    lfi_nct_app_url('dossiers-juridiques')],
            ['📓', 'Journal',     'Ton suivi général',          lfi_nct_app_url('journal')],
            ['📅', 'Événements',  'Réunions & actions',         lfi_nct_app_url('evenements')],
        ]; ?>
        <div class="lfi-app-section">
            <div class="lfi-app-section-title" style="font-size:1.05em">⭐ L'ESSENTIEL</div>
            <div class="lfi-app-grid">
                <?php foreach ($essentiel as $t): ?>
                    <a class="lfi-app-tile" href="<?php echo esc_url($t[3]); ?>" style="border:2px solid #c8102e">
                        <div class="ico"><?php echo $t[0]; ?></div>
                        <div class="tit"><?php echo esc_html($t[1]); ?></div>
                        <div class="sub"><?php echo esc_html($t[2]); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (function_exists('lfi_nct_render_home_mobilisation')) lfi_nct_render_home_mobilisation(); ?>

        <?php /* ============ COORDINATION DU GA : se mobiliser sur les actions ============ */
        $coord = [
            ['🤝', 'Se coordonner',  'Tractage, campagnes — je participe', lfi_nct_app_url('mobilisation')],
            ['💡', 'Idées d\'actions','Proposer / soutenir une idée',   lfi_nct_app_url('propositions')],
        ]; ?>
        <div class="lfi-app-section">
            <div class="lfi-app-section-title" style="font-size:1.05em">🤝 COORDINATION</div>
            <div class="lfi-app-grid">
                <?php foreach ($coord as $t): ?>
                    <a class="lfi-app-tile" href="<?php echo esc_url($t[3]); ?>" style="border:2px solid #186a3b">
                        <div class="ico"><?php echo $t[0]; ?></div>
                        <div class="tit"><?php echo esc_html($t[1]); ?></div>
                        <div class="sub"><?php echo esc_html($t[2]); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <details class="lfi-app-advanced" style="margin-top:8px">
            <summary style="cursor:pointer;font-weight:700;color:#666;padding:10px 4px;font-size:1.02em">⚙️ Tous les autres outils (avancé)</summary>
            <?php foreach ($sections as $section_title => $tiles): ?>
                <div class="lfi-app-section">
                    <div class="lfi-app-section-title"><?php echo esc_html($section_title); ?></div>
                    <div class="lfi-app-grid">
                        <?php foreach ($tiles as $t): ?>
                            <a class="lfi-app-tile" href="<?php echo esc_url($t[3]); ?>">
                                <div class="ico"><?php echo $t[0]; ?></div>
                                <div class="tit"><?php echo esc_html($t[1]); ?></div>
                                <div class="sub"><?php echo esc_html($t[2]); ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </details>

        <div class="lfi-app-foot">
            <div>LFI Nantes Sud Clos Toreau · v<?php echo esc_html(LFI_NCT_VERSION); ?></div>
            <div class="lfi-app-install-hint mini">📱 Ajoute cette page à l'écran d'accueil pour l'avoir comme une app</div>
        </div>
    </div>
    <?php
}

function lfi_nct_app_quick_stats() {
    /* Cache transient 60s, AVEC une clé PAR GA affiché (sinon les chiffres
       d'un GA fuiraient sur un autre). Les compteurs sont cloisonnés : un
       autre GA voit SES chiffres (0 tant qu'il n'a rien). */
    $scope   = function_exists('lfi_nct_scope_ga_slug') ? lfi_nct_scope_ga_slug() : '';
    $is_home = ($scope === '' || $scope === 'clos-toreau');
    $ckey    = 'lfi_nct_app_quick_stats_' . ($scope !== '' ? $scope : 'home');
    $cached = get_transient($ckey);
    if (is_array($cached)) return $cached;
    global $wpdb;
    $resp_clause = function_exists('lfi_nct_responses_scope_clause') ? lfi_nct_responses_scope_clause() : '';
    $surveys = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lfi_nct_responses WHERE deleted_at IS NULL" . $resp_clause);
    /* Adhérents : cloisonnés par GA (chaque GA compte LES SIENS). */
    $mem_clause = function_exists('lfi_nct_membres_ga_clause') ? lfi_nct_membres_ga_clause('ga') : '';
    $membres = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lfi_nct_membres WHERE 1=1" . $mem_clause);
    /* Réunion du 26 juin = propre à Clos Toreau. */
    $reunion = $is_home ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lfi_nct_reunion_rsvp") : 0;
    /* Événements : cloisonnés par GA via le rattachement _lfi_evt_ga. */
    if ($is_home) {
        $events = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_lfi_evt_ga'
             WHERE p.post_status = 'publish' AND p.post_type IN ('ag_evenement','lfi_evenement')
                   AND (m.meta_value IS NULL OR m.meta_value = '' OR m.meta_value = 'clos-toreau')"
        );
    } else {
        $events = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_lfi_evt_ga'
             WHERE p.post_status = 'publish' AND p.post_type IN ('ag_evenement','lfi_evenement')
                   AND m.meta_value = %s",
            $scope
        ));
    }
    $stats = [
        'reunion' => max(0, $reunion),
        'surveys' => max(0, $surveys),
        'membres' => max(0, $membres),
        'events'  => max(0, $events),
    ];
    set_transient($ckey, $stats, 60);
    return $stats;
}

/* Cache 60s pour count_users() — appel super lourd sur grosses installs */
function lfi_nct_app_count_users_cached() {
    $cached = get_transient('lfi_nct_app_count_users');
    if (is_array($cached)) return $cached;
    $count = count_users();
    set_transient('lfi_nct_app_count_users', $count, 60);
    return $count;
}

function lfi_nct_app_render_styles() {
    ?>
    <style>
    .lfi-app * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
    .lfi-app {
        max-width: 480px; margin: 0 auto; padding: 14px 14px 60px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #1a1a1a;
    }
    .lfi-app-header { text-align: center; padding: 30px 10px 24px; }
    .lfi-app-logo {
        width: 88px; height: 88px; border-radius: 22px; background: #c8102e;
        color: #fff; font-weight: 700; font-size: 56px; line-height: 88px;
        margin: 0 auto 14px; box-shadow: 0 8px 24px rgba(200,16,46,.35);
    }
    .lfi-app-header h1 { font-size: 1.3em; margin: 0 0 4px; color: #c8102e; }
    .lfi-app-sub { color: #777; font-size: .95em; margin: 0; }

    .lfi-app-login {
        display: flex; flex-direction: column; gap: 14px;
        background: #fff; border-radius: 16px; padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,.06);
    }
    .lfi-app-login label { display: flex; flex-direction: column; gap: 4px; font-size: .9em; color: #555; }
    .lfi-app-login input[type=text], .lfi-app-login input[type=password] {
        font-size: 1.05em; padding: 14px 14px; border: 1.5px solid #ddd;
        border-radius: 10px; background: #fafafa;
    }
    .lfi-app-login input:focus { outline: none; border-color: #c8102e; background: #fff; }
    .lfi-app-checkbox { flex-direction: row !important; align-items: center; gap: 8px; }
    .lfi-app-checkbox input { width: 20px; height: 20px; }
    .lfi-app-login button {
        background: #c8102e; color: #fff; border: none; padding: 16px;
        border-radius: 12px; font-size: 1.1em; font-weight: 700; cursor: pointer;
        margin-top: 4px;
    }
    .lfi-app-login button:active { background: #a30b25; }

    .lfi-app-install-hint {
        margin-top: 22px; background: #fff3f5; border: 1px solid #ffd4dc;
        border-radius: 12px; padding: 12px 14px; font-size: .88em; color: #555;
    }
    .lfi-app-install-hint strong { color: #c8102e; display: block; margin-bottom: 6px; }
    .lfi-app-install-hint div { margin: 3px 0; }
    .lfi-app-install-hint.mini { margin-top: 8px; font-size: .8em; text-align: center; padding: 8px; }

    .lfi-app-topbar {
        display: flex; align-items: center; gap: 12px;
        background: #c8102e; color: #fff; border-radius: 16px;
        padding: 14px 16px; margin-bottom: 14px;
    }
    .lfi-app-logo-mini {
        width: 44px; height: 44px; border-radius: 11px; background: rgba(255,255,255,.18);
        font-size: 26px; line-height: 44px; text-align: center; font-weight: 700;
    }
    .lfi-app-hi { font-weight: 700; font-size: 1.05em; }
    .lfi-app-sub2 { font-size: .8em; opacity: .85; margin-top: 2px; }

    .lfi-app-quick {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 14px;
    }
    .lfi-app-quick .q {
        background: #fff; border-radius: 12px; padding: 12px 6px; text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,.05);
    }
    .lfi-app-quick .n { display: block; font-size: 1.7em; font-weight: 700; color: #c8102e; }
    .lfi-app-quick .l { display: block; font-size: .72em; color: #777; margin-top: 2px; }

    .lfi-app-grid {
        display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;
    }
    .lfi-app-tile {
        display: block; background: #fff; border-radius: 14px; padding: 16px 12px;
        color: #1a1a1a; text-decoration: none;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
        transition: transform .08s ease;
        min-height: 110px;
    }
    .lfi-app-tile:active { transform: scale(.97); background: #f8f8f8; }
    .lfi-app-tile .ico { font-size: 1.8em; margin-bottom: 6px; }
    .lfi-app-tile .tit { font-weight: 700; font-size: .95em; line-height: 1.2; }
    .lfi-app-tile .sub { color: #888; font-size: .78em; margin-top: 3px; }

    .lfi-app-foot {
        margin-top: 24px; text-align: center; color: #888; font-size: .8em;
    }

    .lfi-app-error {
        background: #fff3f5; border: 1px solid #f5b5c0; color: #a30b25;
        padding: 16px; border-radius: 12px; margin: 20px 0;
    }
    .lfi-app-error a { color: #c8102e; font-weight: 700; }

    /* Cache le chrome du thème quand on est en mode standalone (vraie app) */
    @media (display-mode: standalone) {
        body.page-app header, body.page-app footer,
        body.page-app .site-header, body.page-app .site-footer,
        body.page-app .ag-asso-header, body.page-app .ag-asso-footer { display: none !important; }
        body.page-app { padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom); }
    }

    /* =============== ÉCRANS NATIFS =============== */
    .lfi-app-screen { padding-top: 0 !important; padding-bottom: 80px; }

    .lfi-app-navbar {
        position: sticky; top: 0; z-index: 50;
        display: flex; align-items: center; gap: 8px;
        background: linear-gradient(180deg, #c8102e, #a30b25); color: #fff;
        padding: 12px 14px; margin: -14px -14px 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,.15);
    }
    .lfi-app-back, .lfi-app-home {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 42px; height: 42px; padding: 0 10px;
        background: rgba(255,255,255,.18); color: #fff;
        border-radius: 10px; text-decoration: none; font-size: 1.3em; font-weight: 700;
    }
    .lfi-app-back:active, .lfi-app-home:active { background: rgba(255,255,255,.3); }
    .lfi-app-screen-title { flex: 1; min-width: 0; }
    .lfi-app-screen-title .t { font-weight: 700; font-size: 1.05em; line-height: 1.2; }
    .lfi-app-screen-title .s { font-size: .78em; opacity: .9; margin-top: 2px; line-height: 1.2; }

    .lfi-app-screen-body { padding-bottom: 12px; }

    .lfi-app-flash {
        padding: 12px 14px; border-radius: 10px; margin: 0 0 12px;
        font-size: .92em;
    }
    .lfi-app-flash.ok  { background: #e7f5ee; border: 1px solid #b6e2c8; color: #186a3b; }
    .lfi-app-flash.err { background: #fff3f5; border: 1px solid #f5b5c0; color: #a30b25; }

    .lfi-app-empty {
        text-align: center; padding: 40px 20px; color: #777;
        background: #fff; border-radius: 14px; border: 1px dashed #ddd;
    }

    .lfi-app-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
    .lfi-app-card {
        background: #fff; border-radius: 14px; padding: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .lfi-app-card .head { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; margin-bottom: 6px; }
    .lfi-app-card .who { font-weight: 700; font-size: 1em; color: #1a1a1a; }
    .lfi-app-card .when { font-size: .8em; color: #888; flex-shrink: 0; }
    .lfi-app-card .badge {
        background: #f0eaff; color: #5a3eb0; padding: 2px 8px;
        border-radius: 999px; font-size: .72em; font-weight: 600;
    }
    .lfi-app-card .meta { display: flex; flex-wrap: wrap; gap: 6px; margin: 6px 0; }
    .meta-chip {
        display: inline-flex; align-items: center; gap: 4px;
        background: #f5f5f5; color: #444;
        padding: 6px 10px; border-radius: 8px; font-size: .82em;
        text-decoration: none;
    }
    .meta-chip:hover { background: #e9e9e9; color: #1a1a1a; }
    .meta-chip.nb { background: #fff3f5; color: #c8102e; font-weight: 700; }
    .meta-chip.act { background: #c8102e; color: #fff; font-weight: 700; }
    .meta-chip.act:hover { background: #a30b25; color: #fff; }
    .lfi-app-card .com { font-style: italic; color: #555; margin: 8px 0; padding-left: 10px; border-left: 3px solid #c8102e; font-size: .9em; }

    .lfi-app-card .row-actions, .lfi-app-card form.row-actions { margin: 8px 0 0; display: flex; gap: 6px; flex-wrap: wrap; }
    .btn-del {
        background: transparent; color: #c8102e; border: 1px solid #f5b5c0;
        padding: 8px 12px; border-radius: 8px; font-size: .82em; cursor: pointer;
        font-family: inherit;
    }
    .btn-del:active { background: #fff3f5; }
    .btn-primary {
        background: #c8102e; color: #fff; border: none;
        padding: 12px 16px; border-radius: 10px; font-size: .95em; font-weight: 700;
        cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;
        font-family: inherit;
    }
    .btn-primary.big { padding: 16px 20px; font-size: 1.05em; width: 100%; }
    .btn-primary:active { background: #a30b25; }
    .btn-ghost {
        background: #fff; color: #c8102e; border: 1.5px solid #c8102e;
        padding: 10px 14px; border-radius: 10px; font-size: .9em; font-weight: 700;
        cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;
        font-family: inherit;
    }
    .btn-ghost:active { background: #fff3f5; }

    /* Forms */
    .lfi-app-form { display: flex; flex-direction: column; gap: 12px; background: #fff; border-radius: 14px; padding: 16px; margin: 0 0 14px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    .lfi-app-form label { display: flex; flex-direction: column; gap: 4px; font-size: .85em; color: #555; }
    .lfi-app-form input[type=text], .lfi-app-form input[type=email], .lfi-app-form input[type=tel],
    .lfi-app-form input[type=search], .lfi-app-form select, .lfi-app-form textarea {
        padding: 12px 12px; border: 1.5px solid #ddd; border-radius: 10px; font-size: 1em;
        background: #fafafa; font-family: inherit;
    }
    .lfi-app-form input:focus, .lfi-app-form select:focus, .lfi-app-form textarea:focus {
        outline: none; border-color: #c8102e; background: #fff;
    }
    .lfi-app-form textarea { resize: vertical; min-height: 100px; }
    .lfi-app-help { font-size: .85em; color: #666; padding: 8px 10px; background: #fff8e6; border-left: 3px solid #ffd400; border-radius: 6px; }

    .lfi-app-searchbar { display: flex; gap: 6px; margin: 0 0 14px; }
    .lfi-app-searchbar input { flex: 1; padding: 12px; border: 1.5px solid #ddd; border-radius: 10px; font-size: 1em; }
    .lfi-app-searchbar button, .lfi-app-searchbar .clear {
        padding: 0 14px; background: #c8102e; color: #fff; border: none;
        border-radius: 10px; font-size: 1.1em; cursor: pointer; text-decoration: none;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .lfi-app-searchbar .clear { background: #ddd; color: #555; }

    .lfi-app-collapse { background: #fff; border-radius: 14px; margin: 0 0 14px; box-shadow: 0 1px 3px rgba(0,0,0,.06); overflow: hidden; }
    .lfi-app-collapse summary { padding: 14px 16px; font-weight: 700; color: #c8102e; cursor: pointer; user-select: none; }
    .lfi-app-collapse[open] summary { border-bottom: 1px solid #eee; }
    .lfi-app-collapse .lfi-app-form { margin: 0; box-shadow: none; }

    .sms-preview .sms-body {
        width: 100%; min-height: 130px; padding: 12px; border: 1px solid #ddd;
        border-radius: 10px; background: #f8f8f8; font-size: .95em;
        font-family: -apple-system, BlinkMacSystemFont, sans-serif;
        margin: 8px 0;
    }

    .lfi-app-stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 16px; }
    .lfi-app-stats-grid .stat { background: #fff; border-radius: 14px; padding: 18px 10px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    .lfi-app-stats-grid .ico { font-size: 1.8em; }
    .lfi-app-stats-grid .n { font-size: 2em; font-weight: 800; color: #c8102e; line-height: 1.1; margin: 4px 0; }
    .lfi-app-stats-grid .l { font-size: .8em; color: #777; }

    .lfi-app-other-shortcuts { margin: 22px 0 0; padding-top: 18px; border-top: 1px solid #eee; }
    .lfi-app-other-shortcuts .lab { font-size: .8em; color: #888; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
    .lfi-app-other-shortcuts .row { display: flex; flex-wrap: wrap; gap: 6px; }
    .lfi-app-other-shortcuts .chip {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 8px 12px; background: #fff; border: 1px solid #ddd;
        border-radius: 999px; text-decoration: none; color: #444; font-size: .82em;
    }
    .lfi-app-other-shortcuts .chip:active { background: #f5f5f5; }

    /* Filtres en chips (enquêtes) */
    .lfi-app-filter-chips { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 12px; }
    .lfi-app-filter-chips .fc {
        padding: 8px 12px; background: #fff; border: 1.5px solid #ddd;
        border-radius: 999px; text-decoration: none; color: #555; font-size: .82em; font-weight: 600;
    }
    .lfi-app-filter-chips .fc.on { background: #c8102e; color: #fff; border-color: #c8102e; }
    .lfi-app-filter-chips .fc:active { background: #f5f5f5; }
    .lfi-app-filter-chips .fc.on:active { background: #a30b25; }

    /* Bulk row : 2 boutons côte à côte au-dessus de la liste */
    .lfi-app-bulk-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 0 0 14px; }
    .lfi-app-bulk-row > * { padding: 12px; font-size: .85em; text-align: center; }

    /* Pager Précédent / Suivant */
    .lfi-app-pager { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }
    .lfi-app-pager .btn-ghost.dis { opacity: .4; cursor: not-allowed; text-align: center; padding: 10px 14px; }

    /* Checkbox + label en ligne dans les forms */
    .lfi-app-checkbox-row { flex-direction: row !important; align-items: flex-start; gap: 10px; padding: 10px; background: #f8f8f8; border-radius: 8px; cursor: pointer; }
    .lfi-app-checkbox-row input { width: 20px; height: 20px; margin-top: 2px; flex-shrink: 0; }

    /* Bloc problème(s) signalé(s) dans les cards enquête */
    .lfi-app-problem { background: #fff8e6; border-left: 3px solid #ffd400; border-radius: 6px; padding: 10px 12px; margin: 8px 0; }
    .lfi-app-problem.inline { margin: 10px 0; }
    .lfi-app-problem .prob-head { font-size: .78em; font-weight: 700; color: #886e00; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 6px; }
    .lfi-app-problem .prob-chips { display: flex; flex-wrap: wrap; gap: 4px; }
    .lfi-app-problem .prob-chip { background: #fff; border: 1px solid #ffe4a0; padding: 4px 9px; border-radius: 999px; font-size: .82em; color: #3a3a3a; }
    .lfi-app-problem .prob-grav {
        display: inline-block; font-size: .72em; font-weight: 700; padding: 1px 7px;
        border-radius: 999px; background: #ddd; color: #444; margin-left: 4px;
    }
    .lfi-app-problem .prob-grav.g7, .lfi-app-problem .prob-grav.g8 { background: #ffcc99; color: #8a3a00; }
    .lfi-app-problem .prob-grav.g9, .lfi-app-problem .prob-grav.g10 { background: #ffb3b3; color: #a30b25; }
    .lfi-app-problem .prob-recur { font-size: .72em; font-weight: 700; color: #c8102e; margin-left: 4px; }

    /* Onglets de modes pour SMS / email enquête */
    .lfi-app-modes { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; margin: 0 0 14px; }
    .lfi-app-modes .md {
        text-align: center; padding: 10px 8px; background: #fff;
        border: 1.5px solid #ddd; border-radius: 10px;
        text-decoration: none; color: #555; font-size: .82em; font-weight: 600;
    }
    .lfi-app-modes .md.on { background: #c8102e; color: #fff; border-color: #c8102e; }
    .lfi-app-modes .md:active { background: #f5f5f5; }
    .lfi-app-modes .md.on:active { background: #a30b25; }

    /* Textarea SMS éditable */
    .sms-preview textarea.sms-body {
        background: #f8f8f8; min-height: 130px;
    }
    .sms-preview textarea.sms-body:focus {
        background: #fff; border-color: #c8102e;
    }

    /* Carte « prochain événement » sur dashboard locataire */
    .lfi-tenant-event {
        display: block; padding: 16px; margin-bottom: 14px;
        background: linear-gradient(135deg, #c8102e, #a30b25); color: #fff;
        border-radius: 14px; text-decoration: none;
        box-shadow: 0 4px 12px rgba(200,16,46,.2);
    }
    .lfi-tenant-event:hover, .lfi-tenant-event:focus { color: #fff; }
    .lfi-tenant-event .lab { font-size: .72em; opacity: .85; letter-spacing: .5px; }
    .lfi-tenant-event .ti  { font-weight: 800; font-size: 1.1em; margin: 4px 0; line-height: 1.2; }
    .lfi-tenant-event .me  { font-size: .85em; opacity: .92; }
    .lfi-tenant-event .cta { margin-top: 10px; font-weight: 700; font-size: .9em; }

    /* Banner conseil du jour pour locataire */
    .lfi-tenant-tip {
        background: #fff8e6; border-left: 4px solid #ffd400;
        padding: 12px 14px; border-radius: 8px; margin-bottom: 14px;
    }
    .lfi-tenant-tip .lab { font-size: .75em; font-weight: 700; color: #886e00; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 4px; }
    .lfi-tenant-tip .tx { font-size: .92em; color: #1a1a1a; }
    .lfi-tenant-tip .more { margin-top: 6px; font-size: .82em; }
    .lfi-tenant-tip .more a { color: #c8102e; font-weight: 600; text-decoration: none; }

    /* Sections du dashboard admin */
    .lfi-app-section { margin: 6px 0 18px; }
    .lfi-app-section-title {
        font-size: .72em; font-weight: 800; letter-spacing: 1px;
        color: #888; margin: 16px 4px 6px; padding: 0 4px;
    }

    /* Bandeau « Aperçu en tant que » (mode preview) */
    .lfi-preview-banner {
        position: sticky; top: 0; z-index: 99997;
        background: linear-gradient(180deg, #ffd400, #f7c000);
        color: #1a1a1a;
        display: flex; justify-content: space-between; align-items: center;
        gap: 10px; padding: 10px 14px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        box-shadow: 0 2px 8px rgba(0,0,0,.15);
        font-size: .88em; line-height: 1.3;
        margin: -14px -14px 14px;
    }
    .lfi-preview-banner .lab { flex: 1; min-width: 0; }
    .lfi-preview-banner .quit {
        background: #1a1a1a; color: #ffd400;
        padding: 6px 12px; border-radius: 8px; text-decoration: none;
        font-weight: 700; flex-shrink: 0;
    }
    .lfi-preview-banner .quit:hover { background: #000; color: #ffd400; }

    /* Page Inscription — 2 gros choix */
    .lfi-inscription-choices { display: flex; flex-direction: column; gap: 12px; margin: 12px 0; }
    .lfi-inscription-choice {
        display: block; background: #fff; border-radius: 14px; padding: 22px 20px;
        text-decoration: none; color: #1a1a1a;
        box-shadow: 0 2px 10px rgba(0,0,0,.08);
        border: 2px solid transparent;
        transition: border-color .15s ease, transform .08s ease;
    }
    .lfi-inscription-choice:active { transform: scale(.98); }
    .lfi-inscription-choice:hover { border-color: #c8102e; color: #1a1a1a; }
    .lfi-inscription-choice .ico { font-size: 2.4em; line-height: 1; margin-bottom: 8px; }
    .lfi-inscription-choice .ti { font-size: 1.1em; font-weight: 800; color: #c8102e; margin-bottom: 6px; }
    .lfi-inscription-choice .sub { font-size: .9em; color: #555; line-height: 1.4; }

    /* Page Tutoriels — guides brigade (CSS global pour fiabilité) */
    .lfi-tutoriel { display: block; color: #1a1a1a; }
    .lfi-tutoriel section {
        display: block; background: #fff; color: #1a1a1a;
        border-radius: 12px; padding: 16px 18px; margin: 0 0 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .lfi-tutoriel section h3 { margin: 0 0 10px; font-size: 1.05em; color: #c8102e; font-weight: 700; }
    .lfi-tutoriel section p { margin: 8px 0; line-height: 1.5; font-size: .95em; color: #1a1a1a; }
    .lfi-tutoriel section ul, .lfi-tutoriel section ol { margin: 8px 0; padding-left: 1.4em; line-height: 1.6; font-size: .92em; color: #1a1a1a; }
    .lfi-tutoriel section li { margin-bottom: 6px; color: #1a1a1a; }
    .lfi-tutoriel section strong { color: #1a1a1a; font-weight: 700; }
    .lfi-tutoriel section em { color: #555; }
    .lfi-tutoriel section table {
        font-size: .85em; border-collapse: collapse;
        width: 100%; margin: 10px 0;
    }
    .lfi-tutoriel section table th { background: #c8102e; color: #fff; padding: 8px 6px; font-weight: 700; text-align: left; }
    .lfi-tutoriel section table td { border-bottom: 1px solid #eee; padding: 8px 6px; color: #1a1a1a; vertical-align: top; }
    .lfi-tutoriel section table tr:nth-child(even) td { background: #fafafa; }

    /* Page Droits — sections juridiques */
    .lfi-droits section { background: #fff; border-radius: 12px; padding: 14px 16px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    .lfi-droits section h3 { margin: 0 0 8px; font-size: 1em; color: #c8102e; }
    .lfi-droits section p { margin: 0 0 6px; line-height: 1.45; font-size: .92em; }
    .lfi-droits section ol { margin: 6px 0 0 18px; padding: 0; }
    .lfi-droits section ol li { margin-bottom: 4px; font-size: .92em; line-height: 1.4; }

    /* Sommaire de la page droits */
    .lfi-droits-toc { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 14px; }
    .lfi-droits-toc a {
        background: #fff; padding: 8px 12px; border-radius: 999px;
        text-decoration: none; font-size: .82em; font-weight: 600; color: #c8102e;
        border: 1px solid #ffd4dc;
    }
    .lfi-droits-toc a:active { background: #fff3f5; }

    /* Tableau qui paie quoi */
    .lfi-droits-table { width: 100%; border-collapse: collapse; margin: 6px 0 0; font-size: .85em; }
    .lfi-droits-table th { background: #c8102e; color: #fff; padding: 6px 8px; text-align: left; font-weight: 700; }
    .lfi-droits-table th:nth-child(2), .lfi-droits-table th:nth-child(3) { text-align: center; width: 60px; }
    .lfi-droits-table td { padding: 8px 10px; border-bottom: 1px solid #eee; line-height: 1.35; }
    .lfi-droits-table td.c { text-align: center; font-size: 1.2em; }
    .lfi-droits-table tr:nth-child(even) td { background: #fafafa; }

    /* Rôles : 2 cartes */
    .lfi-droits-roles { display: grid; grid-template-columns: 1fr; gap: 10px; }
    @media (min-width: 600px) { .lfi-droits-roles { grid-template-columns: 1fr 1fr; } }
    .lfi-droits-roles .rcard { background: #f8f8f8; border-radius: 10px; padding: 12px 14px; }
    .lfi-droits-roles .rh { font-weight: 800; color: #c8102e; margin-bottom: 8px; font-size: .95em; }
    .lfi-droits-roles ul { margin: 0; padding-left: 1.2em; font-size: .88em; line-height: 1.5; }

    /* Lois numérotées */
    .lfi-droits-textes { margin: 0; padding-left: 1.4em; }
    .lfi-droits-textes li { margin-bottom: 8px; font-size: .9em; line-height: 1.45; }

    /* FAQ : accordéon */
    .lfi-droits-faq details {
        background: #fff; border: 1px solid #eee; border-radius: 8px;
        padding: 10px 12px; margin-bottom: 6px;
    }
    .lfi-droits-faq summary {
        font-weight: 600; cursor: pointer; color: #1a1a1a; font-size: .92em; list-style: none;
    }
    .lfi-droits-faq summary::-webkit-details-marker { display: none; }
    .lfi-droits-faq summary::before { content: "▸ "; color: #c8102e; font-weight: 800; }
    .lfi-droits-faq details[open] summary::before { content: "▾ "; }
    .lfi-droits-faq .ans { margin-top: 8px; padding-top: 8px; border-top: 1px solid #f0f0f0; font-size: .88em; line-height: 1.5; color: #444; }

    /* Recours numérotés gros */
    .lfi-droits-recours { margin: 0; padding-left: 1.4em; }
    .lfi-droits-recours li { margin-bottom: 8px; font-size: .92em; line-height: 1.45; }

    /* Lettre area */
    .lfi-lettre-area {
        width: 100%; padding: 14px; border: 1.5px solid #ddd;
        border-radius: 10px; background: #fafafa;
        font-family: ui-monospace, Menlo, Consolas, monospace;
        font-size: .85em; line-height: 1.4; resize: vertical;
    }
    @media print {
        .lfi-quickbar, .lfi-app-navbar, .row-actions, .lfi-app-other-shortcuts { display: none !important; }
        .lfi-lettre-area { border: 0; background: #fff; }
    }

    /* Grille de checkboxes (témoignage) */
    .lfi-checkbox-grid { display: grid; grid-template-columns: 1fr; gap: 6px; }
    @media (min-width: 600px) { .lfi-checkbox-grid { grid-template-columns: 1fr 1fr; } }
    <?php
    /* Couleur d'accent personnalisée par GA (repli = rouge LFI, aucun effet). */
    if (function_exists('lfi_nct_ga_couleur')) {
        $gc = lfi_nct_ga_couleur();
        if ($gc !== '#c8102e') {
            echo '.lfi-app .btn-primary,.lfi-app-topbar,.lfi-app-logo,.lfi-app-logo-mini{background:' . esc_attr($gc) . ' !important;border-color:' . esc_attr($gc) . ' !important}';
            echo '.lfi-app-tile .tit,.lfi-app-section-title,.lfi-app-hi,.lfi-app-alertes>div:first-child{color:' . esc_attr($gc) . ' !important}';
            echo '.lfi-app-alertes,.lfi-app-gaswitch{border-color:' . esc_attr($gc) . ' !important}';
        }
    }
    ?>
    </style>
    <?php
    /* Logo du GA : affiché en haut de la console (si configuré). */
    if (function_exists('lfi_nct_ga_logo_url')) {
        $glogo = lfi_nct_ga_logo_url();
        if ($glogo) echo '<div style="text-align:center;margin:6px 0 0"><img src="' . esc_url($glogo) . '" alt="logo" style="max-height:56px"></div>';
    }

    /* GROS EN-TÊTE « Groupe d'Action … » : comme toutes les apps des GA
     * partagent la même URL /app/, on affiche bien en évidence, en haut de
     * chaque écran, sur quel groupe d'action on se trouve. Rien ne s'affiche
     * sur l'écran de connexion (utilisateur pas encore connecté). */
    if (is_user_logged_in()
        && function_exists('lfi_nct_scope_ga_slug')
        && (current_user_can('manage_options')
            || (function_exists('lfi_nct_user_role_ga') && lfi_nct_user_role_ga())
            || (function_exists('lfi_nct_is_ga_member') && lfi_nct_is_ga_member()))) {
        $ga_slug = lfi_nct_scope_ga_slug();
        $ga_name = function_exists('lfi_nct_ga_nom') ? lfi_nct_ga_nom($ga_slug) : '';
        /* Préfixe « Groupe d'Action » pour que ce soit limpide (sans doublon). */
        if ($ga_name !== '' && stripos($ga_name, 'groupe') === false) {
            $ga_name = 'Groupe d\'Action ' . $ga_name;
        }
        $gc = function_exists('lfi_nct_ga_couleur') ? lfi_nct_ga_couleur() : '#c8102e';
        if ($ga_name !== '') {
            echo '<div style="max-width:480px;margin:8px auto 12px;background:' . esc_attr($gc) . ';color:#fff;text-align:center;font-weight:800;font-size:1.08em;letter-spacing:.3px;padding:12px 14px;border-radius:12px;box-shadow:0 3px 10px rgba(0,0,0,.15);text-transform:uppercase">📍 ' . esc_html($ga_name) . '</div>';
        }
    }
}

/* Bouton fixe « 📲 Télécharger l'app » sur TOUT le site public
 * (pas dans l'app elle-même). Visible aux visiteur·euses pour qu'ils
 * sachent qu'ils peuvent installer l'app et s'inscrire en un clic. */
add_action('wp_body_open', 'lfi_nct_render_install_button_public', 5);
add_action('wp_footer',    'lfi_nct_render_install_button_public', 5);
function lfi_nct_render_install_button_public() {
    static $rendered = false;
    if ($rendered) return;
    /* Ne rend PAS dans wp-admin */
    if (is_admin()) return;
    /* Ne rend PAS sur les pages de l'app (déjà dedans) */
    if (is_singular()) {
        $post = get_post();
        if ($post && $post->post_name === LFI_NCT_APP_SLUG) return;
    }
    $rendered = true;
    ?>
    <a class="lfi-public-install" href="<?php echo esc_url(home_url('/app/')); ?>" aria-label="Télécharger l'application">
        <span class="ico">📲</span>
        <span class="lbl">Télécharger l'app</span>
    </a>
    <style>
    .lfi-public-install {
        position: fixed; bottom: 20px; left: 16px; z-index: 99990;
        background: linear-gradient(135deg, #c8102e, #a30b25); color: #fff;
        padding: 12px 18px; border-radius: 999px;
        text-decoration: none;
        box-shadow: 0 4px 14px rgba(200,16,46,.4);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-weight: 700; font-size: .92em;
        display: inline-flex; align-items: center; gap: 8px;
        transition: transform .12s ease;
    }
    .lfi-public-install:hover { color: #fff; transform: translateY(-2px); }
    .lfi-public-install:active { background: #a30b25; transform: scale(.98); }
    .lfi-public-install .ico { font-size: 1.2em; }
    .lfi-public-install .lbl { white-space: nowrap; }
    @media (max-width: 480px) {
        .lfi-public-install { padding: 12px; bottom: 16px; left: 12px; }
        .lfi-public-install .lbl { display: none; }
    }
    @media print { .lfi-public-install { display: none !important; } }
    </style>
    <?php
}

/* Bouton fixe « Appel d'urgence » sur toutes les pages de l'app.
 * Numéro de Fabrice : 06 23 52 60 74.
 * FAB en bas à droite, semi-transparent, ne masque pas le contenu. */
function lfi_nct_app_render_emergency_button() {
    ?>
    <a class="lfi-app-emergency" href="tel:+33623526074" aria-label="Appel d'urgence Fabrice">
        <span class="ico">🆘</span>
        <span class="lbl">Urgence</span>
    </a>
    <style>
    .lfi-app-emergency {
        position: fixed; bottom: 20px; right: 16px;
        z-index: 99990;
        background: #c8102e; color: #fff;
        padding: 14px 18px; border-radius: 999px;
        text-decoration: none;
        box-shadow: 0 4px 14px rgba(200,16,46,.4);
        font-family: -apple-system, BlinkMacSystemFont, sans-serif;
        font-weight: 700; font-size: .92em;
        display: inline-flex; align-items: center; gap: 8px;
        transition: transform .12s ease;
    }
    .lfi-app-emergency:hover, .lfi-app-emergency:focus {
        color: #fff; transform: scale(1.05);
    }
    .lfi-app-emergency:active { background: #a30b25; transform: scale(.98); }
    .lfi-app-emergency .ico { font-size: 1.2em; }
    .lfi-app-emergency .lbl { white-space: nowrap; }
    @media (max-width: 480px) {
        .lfi-app-emergency .lbl { display: none; }
        .lfi-app-emergency { padding: 14px; }
    }
    @media print {
        .lfi-app-emergency { display: none !important; }
    }
    </style>
    <?php
}

/* Bouton fixe « Assistant » (robot) + POPUP de discussion (chat).
 * Toujours visible, en bas à gauche (ne chevauche pas le bouton Urgence
 * en bas à droite). Un clic ouvre une fenêtre de discussion PAR-DESSUS la
 * page (aucun changement de page). Contextuel :
 *   - admin de GA / super-admin  → robot admin (dossiers, enquêtes, stats… cloisonné),
 *   - locataire / visiteur       → aide & contact (orientation + mise en relation).
 * Repli sans JavaScript : le bouton reste un lien vers la vue plein écran. */
function lfi_nct_app_render_assistant_button() {
    $is_admin = function_exists('lfi_nct_robot_can') && lfi_nct_robot_can();
    $href  = $is_admin ? lfi_nct_app_url('assistant') : lfi_nct_app_url('aide');
    $label = $is_admin ? 'Assistant' : 'Aide';
    $aria  = $is_admin ? 'Ouvrir l\'assistant du groupe d\'action' : 'Aide et contact — être accompagné·e';
    $titre = $is_admin ? '🤖 Assistant' : '🤖 On peut t\'aider';
    $ph    = $is_admin ? 'Ex : dossier locataire 27 · enquête RE01 · stats…' : 'Décris ton problème : moisissures, chauffage, loyer…';

    $chips   = function_exists('lfi_nct_robot_chips') ? lfi_nct_robot_chips($is_admin) : [];
    $welcome = function_exists('lfi_nct_robot_welcome_html') ? lfi_nct_robot_welcome_html($is_admin) : '';

    $cfg = [
        'ajax'    => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('lfi_nct_robot'),
        'admin'   => (bool) $is_admin,
        'welcome' => $welcome,
        'chips'   => $chips,
        'fallback'=> $href,
    ];
    ?>
    <a class="lfi-app-assistant" id="lfiRobotFab" href="<?php echo esc_url($href); ?>" aria-label="<?php echo esc_attr($aria); ?>" aria-haspopup="dialog">
        <span class="ico">🤖</span>
        <span class="lbl"><?php echo esc_html($label); ?></span>
    </a>

    <div class="lfi-robot-overlay" id="lfiRobotOverlay" hidden>
        <div class="lfi-robot-panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($titre); ?>">
            <div class="lfi-robot-head">
                <span class="t"><?php echo esc_html($titre); ?></span>
                <button type="button" class="lfi-robot-close" id="lfiRobotClose" aria-label="Fermer">✕</button>
            </div>
            <div class="lfi-robot-msgs" id="lfiRobotMsgs" aria-live="polite"></div>
            <div class="lfi-robot-chips" id="lfiRobotChips"></div>
            <form class="lfi-robot-input" id="lfiRobotForm">
                <input type="text" id="lfiRobotQ" autocomplete="off" placeholder="<?php echo esc_attr($ph); ?>" aria-label="Votre message">
                <button type="submit" aria-label="Envoyer">➤</button>
            </form>
        </div>
    </div>

    <style>
    .lfi-app-assistant {
        position: fixed; bottom: 20px; left: 16px;
        z-index: 99990;
        background: #4b2e83; color: #fff;
        padding: 14px 18px; border-radius: 999px;
        text-decoration: none;
        box-shadow: 0 4px 14px rgba(75,46,131,.4);
        font-family: -apple-system, BlinkMacSystemFont, sans-serif;
        font-weight: 700; font-size: .92em;
        display: inline-flex; align-items: center; gap: 8px;
        transition: transform .12s ease;
    }
    .lfi-app-assistant:hover, .lfi-app-assistant:focus { color: #fff; transform: scale(1.05); }
    .lfi-app-assistant:active { background: #3a2367; transform: scale(.98); }
    .lfi-app-assistant .ico { font-size: 1.2em; }
    .lfi-app-assistant .lbl { white-space: nowrap; }
    @media (max-width: 480px) {
        .lfi-app-assistant .lbl { display: none; }
        .lfi-app-assistant { padding: 14px; }
    }
    @media print { .lfi-app-assistant, .lfi-robot-overlay { display: none !important; } }

    /* --- Popup de discussion --- */
    .lfi-robot-overlay {
        position: fixed; inset: 0; z-index: 100000;
        background: rgba(20,12,40,.45);
        display: flex; align-items: flex-end; justify-content: flex-start;
        padding: 0; font-family: -apple-system, BlinkMacSystemFont, sans-serif;
        -webkit-backdrop-filter: blur(2px); backdrop-filter: blur(2px);
    }
    .lfi-robot-overlay[hidden] { display: none; }
    .lfi-robot-panel {
        width: 380px; max-width: calc(100vw - 24px);
        height: 560px; max-height: calc(100vh - 24px);
        margin: 12px; background: #fff; border-radius: 18px;
        box-shadow: 0 18px 50px rgba(0,0,0,.35);
        display: flex; flex-direction: column; overflow: hidden;
        animation: lfiRobotIn .18s ease;
    }
    @keyframes lfiRobotIn { from { opacity: 0; transform: translateY(14px) scale(.98); } to { opacity: 1; transform: none; } }
    .lfi-robot-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 14px; background: #4b2e83; color: #fff;
    }
    .lfi-robot-head .t { font-weight: 800; font-size: 1.02em; }
    .lfi-robot-close {
        background: rgba(255,255,255,.18); color: #fff; border: 0;
        width: 30px; height: 30px; border-radius: 999px; cursor: pointer;
        font-size: .95em; line-height: 1;
    }
    .lfi-robot-close:hover { background: rgba(255,255,255,.32); }
    .lfi-robot-msgs {
        flex: 1; overflow-y: auto; padding: 14px;
        background: #f4f2f8; display: flex; flex-direction: column; gap: 10px;
    }
    .lfi-robot-msg { max-width: 92%; }
    .lfi-robot-msg.user {
        align-self: flex-end; background: #4b2e83; color: #fff;
        padding: 9px 13px; border-radius: 16px 16px 4px 16px; font-size: .93em;
    }
    .lfi-robot-msg.bot {
        align-self: flex-start; background: #fff; color: #1c1c28;
        padding: 12px 13px; border-radius: 16px 16px 16px 4px;
        box-shadow: 0 1px 4px rgba(0,0,0,.08); font-size: .93em; line-height: 1.5;
    }
    .lfi-robot-msg.bot .lfi-app-card { box-shadow: none; border: 1px solid #ece8f4; margin: 0; }
    .lfi-robot-msg.bot a.btn-primary, .lfi-robot-msg.bot a.btn-ghost { display: inline-flex; }
    .lfi-robot-msg.typing { color: #7a7590; font-style: italic; }
    .lfi-robot-chips { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px 12px 0; background: #f4f2f8; }
    .lfi-robot-chips button {
        background: #efeaf7; color: #4b2e83; border: 1px solid #dcd2ee;
        padding: 6px 11px; border-radius: 999px; font-size: .82em; cursor: pointer;
        font-weight: 600;
    }
    .lfi-robot-chips button:hover { background: #e3d8f5; }
    .lfi-robot-input { display: flex; gap: 8px; padding: 10px 12px 12px; background: #f4f2f8; }
    .lfi-robot-input input {
        flex: 1; border: 1px solid #d6cfe6; border-radius: 999px;
        padding: 11px 15px; font-size: .95em; outline: none; background: #fff;
    }
    .lfi-robot-input input:focus { border-color: #4b2e83; }
    .lfi-robot-input button {
        background: #4b2e83; color: #fff; border: 0; border-radius: 999px;
        width: 44px; height: 44px; cursor: pointer; font-size: 1.05em; flex: 0 0 auto;
    }
    .lfi-robot-input button:hover { background: #3a2367; }
    @media (max-width: 480px) {
        .lfi-robot-overlay { align-items: stretch; justify-content: stretch; }
        .lfi-robot-panel { width: 100%; height: 100%; max-width: 100%; max-height: 100%; margin: 0; border-radius: 0; }
    }
    </style>

    <script>
    (function () {
        var cfg = <?php echo wp_json_encode($cfg); ?>;
        var fab = document.getElementById('lfiRobotFab');
        var ov  = document.getElementById('lfiRobotOverlay');
        if (!fab || !ov) return;
        var panel = ov.querySelector('.lfi-robot-panel');
        var msgs  = document.getElementById('lfiRobotMsgs');
        var chips = document.getElementById('lfiRobotChips');
        var form  = document.getElementById('lfiRobotForm');
        var input = document.getElementById('lfiRobotQ');
        var started = false, busy = false;

        function scrollDown() { msgs.scrollTop = msgs.scrollHeight; }

        function addBubble(cls, html) {
            var d = document.createElement('div');
            d.className = 'lfi-robot-msg ' + cls;
            if (cls === 'user') { d.textContent = html; } else { d.innerHTML = html; }
            msgs.appendChild(d); scrollDown();
            return d;
        }

        function renderChips() {
            chips.innerHTML = '';
            var list = cfg.chips || {};
            Object.keys(list).forEach(function (key) {
                var b = document.createElement('button');
                b.type = 'button';
                b.textContent = list[key];
                b.addEventListener('click', function () { send(key); });
                chips.appendChild(b);
            });
        }

        function open() {
            ov.hidden = false;
            if (!started) {
                started = true;
                if (cfg.welcome) addBubble('bot', cfg.welcome);
                renderChips();
            }
            setTimeout(function () { input.focus(); }, 60);
        }
        function close() { ov.hidden = true; }

        function send(q) {
            q = (q || '').trim();
            if (!q || busy) return;
            addBubble('user', q);
            input.value = '';
            busy = true;
            var typing = addBubble('bot typing', 'Un instant…');
            var body = 'action=lfi_nct_robot&nonce=' + encodeURIComponent(cfg.nonce) + '&q=' + encodeURIComponent(q);
            fetch(cfg.ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: body
            }).then(function (r) { return r.json(); }).then(function (j) {
                typing.remove();
                var html = (j && j.success && j.data && j.data.html) ? j.data.html
                         : '<div class="lfi-app-empty">Petit souci de connexion. Réessaie, ou contacte-nous directement.</div>';
                addBubble('bot', html);
            }).catch(function () {
                typing.remove();
                addBubble('bot', '<div class="lfi-app-empty">Petit souci de connexion. Réessaie, ou contacte-nous directement.</div>');
            }).finally(function () { busy = false; scrollDown(); });
        }

        fab.addEventListener('click', function (e) { e.preventDefault(); open(); });
        document.getElementById('lfiRobotClose').addEventListener('click', close);
        ov.addEventListener('click', function (e) { if (e.target === ov) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !ov.hidden) close(); });
        form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
    })();
    </script>
    <?php
}

/* Partage simplifié : un seul bouton « Partager » qui ouvre la feuille de
 * partage native du téléphone (un geste → toutes les apps). Repli : les
 * réseaux individuels dans un menu déroulant si le partage natif est absent. */
function lfi_nct_app_share_control($url, $title) {
    static $js_done = false;
    $u = rawurlencode($url);
    $t = rawurlencode($title);
    ob_start();
    echo '<span class="lfi-share" data-url="' . esc_attr($url) . '" data-title="' . esc_attr($title) . '">';
    echo '<button type="button" class="btn-primary lfi-share-btn" onclick="lfiShare(this)">📤 Partager</button>';
    echo '<span class="lfi-share-more" hidden>';
    echo '<a class="btn-ghost" href="https://api.whatsapp.com/send?text=' . $t . '%20' . $u . '" target="_blank" rel="noopener">🟢 WhatsApp</a>';
    echo '<a class="btn-ghost" href="https://www.facebook.com/sharer/sharer.php?u=' . $u . '" target="_blank" rel="noopener">📘 Facebook</a>';
    echo '<a class="btn-ghost" href="https://t.me/share/url?url=' . $u . '&text=' . $t . '" target="_blank" rel="noopener">✈️ Telegram</a>';
    echo '<a class="btn-ghost" href="https://twitter.com/intent/tweet?text=' . $t . '&url=' . $u . '" target="_blank" rel="noopener">𝕏 X</a>';
    echo '<button type="button" class="btn-ghost" onclick="lfiShareCopy(this)">🔗 Copier le lien</button>';
    echo '</span></span>';
    if (!$js_done) {
        $js_done = true;
        ?>
        <script>
        function lfiShare(btn){
            var box = btn.closest('.lfi-share');
            var url = box.getAttribute('data-url'), title = box.getAttribute('data-title');
            if (navigator.share) {
                navigator.share({title: title, text: title, url: url}).catch(function(){});
            } else {
                var more = box.querySelector('.lfi-share-more');
                if (more) more.hidden = !more.hidden;
            }
        }
        function lfiShareCopy(btn){
            var box = btn.closest('.lfi-share');
            var url = box.getAttribute('data-url');
            if (navigator.clipboard) navigator.clipboard.writeText(url).then(function(){ btn.textContent='✅ Copié'; });
        }
        </script>
        <style>
        .lfi-share-more { display:inline-flex; gap:6px; flex-wrap:wrap; margin-left:6px; }
        </style>
        <?php
    }
    return ob_get_clean();
}

function lfi_nct_app_render_register_sw() {
    /* Query-var direct = marche même si les rewrite rules ne sont pas flushées */
    $sw = esc_url(home_url('/?lfi_app=sw&v=' . LFI_NCT_VERSION));
    ?>
    <script>
    (function () {
        /* Marqueur body pour le CSS standalone */
        document.body && document.body.classList.add('page-app');

        /* App DÉJÀ installée (mode standalone) : on cache les boutons « Installer
           l'application » — incohérent de les montrer une fois installée. */
        function lfiHideInstall() {
            try {
                var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
                    || window.navigator.standalone === true
                    || document.referrer.indexOf('android-app://') === 0;
                if (!standalone) return;
                var links = document.querySelectorAll('a[href*="vue=installer"]');
                links.forEach(function (a) {
                    var box = a.closest('.lfi-app-tile') || a;
                    box.style.display = 'none';
                });
            } catch (e) {}
        }
        if (document.readyState !== 'loading') lfiHideInstall();
        else document.addEventListener('DOMContentLoaded', lfiHideInstall);

        if (!('serviceWorker' in navigator)) return;

        /* Si l'app est DÉJÀ contrôlée par un service worker et qu'une NOUVELLE
           version prend la main, on recharge une seule fois → la PWA installée
           (ex. le téléphone d'un membre) se met à jour toute seule, sans avoir
           à désinstaller/réinstaller. Pas de rechargement à la 1re installation. */
        var hadController = !!navigator.serviceWorker.controller;
        var reloaded = false;
        navigator.serviceWorker.addEventListener('controllerchange', function () {
            if (reloaded || !hadController) return;
            reloaded = true;
            window.location.reload();
        });

        window.addEventListener('load', function () {
            /* updateViaCache:'none' = le script du SW n'est jamais servi depuis le
               cache HTTP → la nouvelle version est détectée immédiatement. */
            navigator.serviceWorker.register('<?php echo $sw; ?>', { scope: '/', updateViaCache: 'none' })
                .then(function (reg) {
                    /* Force un contrôle de mise à jour à chaque ouverture. */
                    try { reg.update(); } catch (e) {}
                    setInterval(function () { try { reg.update(); } catch (e) {} }, 60 * 60 * 1000);
                })
                .catch(function (err) { console.warn('SW register failed', err); });
        });
    })();
    </script>
    <?php
}

/* ============================================================== *
 *  Tuiles d'admin partagées : utilisées par /app/ ET la barre     *
 *  flottante en haut de la home pour les admins connectés.        *
 * ============================================================== */
/* ============================================================== *
 *  MODULES PAR GROUPE (déploiement « une app par GA »)            *
 *  Le volet « travaux » (interventions, facturation, recouvrement,*
 *  cadre facturable) est ACTIF par défaut. Pour une app sans      *
 *  travaux (ex. groupe d'Irina), il suffit, sur le site concerné, *
 *  d'ajouter dans wp-config.php : define('LFI_NCT_DISABLE_TRAVAUX',*
 *  true);  — ou de poser l'option lfi_nct_disable_travaux.         *
 * ============================================================== */
function lfi_nct_travaux_enabled() {
    if (function_exists('lfi_nct_module_enabled')) return lfi_nct_module_enabled('travaux');
    if (defined('LFI_NCT_DISABLE_TRAVAUX') && LFI_NCT_DISABLE_TRAVAUX) return false;
    if (get_option('lfi_nct_disable_travaux')) return false;
    return true;
}
/** Routes masquées : agrège les routes de TOUS les modules désactivés. */
function lfi_nct_module_hidden_routes() {
    if (function_exists('lfi_nct_modules_registry')) {
        $hidden = [];
        foreach (lfi_nct_modules_registry() as $key => $m) {
            if (!lfi_nct_module_enabled($key)) $hidden = array_merge($hidden, (array) ($m['routes'] ?? []));
        }
        return array_values(array_unique($hidden));
    }
    if (lfi_nct_travaux_enabled()) return [];
    return ['interventions', 'intervention-add', 'intervention-edit', 'facture', 'recouvrements', 'recouvrement-dossier', 'cadre-juridique'];
}
function lfi_nct_module_filter_tiles($tiles) {
    $hidden = lfi_nct_module_hidden_routes();
    if (!$hidden) return $tiles;
    return array_values(array_filter((array) $tiles, function ($t) use ($hidden) {
        foreach ($hidden as $r) { if (strpos((string) ($t[3] ?? ''), 'vue=' . $r) !== false) return false; }
        return true;
    }));
}
function lfi_nct_module_filter_sections($sections) {
    if (lfi_nct_travaux_enabled()) return $sections;
    $out = [];
    foreach ((array) $sections as $title => $tiles) {
        $f = lfi_nct_module_filter_tiles($tiles);
        if ($f) $out[$title] = $f;
    }
    return $out;
}
/** Garde-fou en tête des vues du volet travaux. */
function lfi_nct_travaux_guard() {
    if (lfi_nct_travaux_enabled()) return true;
    lfi_nct_app_screen_open('Volet désactivé');
    echo '<div class="lfi-app-help">Le volet « travaux » (interventions, facturation, recouvrement) n\'est pas activé pour ce groupe.</div>';
    lfi_nct_app_screen_close(false);
    return false;
}

/**
 * Retire automatiquement la tuile d'inscription à la réunion du 26 juin une
 * fois la date passée (des suggestions, tuiles et raccourcis). Générique :
 * supprime toute tuile pointant vers l'écran « reunion » quand l'événement
 * est terminé. L'événement reste consultable, mais on ne propose plus de s'y
 * inscrire.
 */
function lfi_nct_prune_past_event_tiles($tiles) {
    if (!function_exists('lfi_nct_reunion_confluences_is_past') || !lfi_nct_reunion_confluences_is_past()) return $tiles;
    $reunion_url = lfi_nct_app_url('reunion');
    return array_values(array_filter((array) $tiles, function ($t) use ($reunion_url) {
        return !(is_array($t) && isset($t[3]) && $t[3] === $reunion_url);
    }));
}
/** Même chose sur les sections [titre => [tuiles…]] (retire aussi les sections vidées). */
function lfi_nct_prune_past_event_sections($sections) {
    if (!function_exists('lfi_nct_reunion_confluences_is_past') || !lfi_nct_reunion_confluences_is_past()) return $sections;
    foreach ($sections as $title => $tiles) {
        $sections[$title] = lfi_nct_prune_past_event_tiles($tiles);
        if (empty($sections[$title])) unset($sections[$title]);
    }
    return $sections;
}

function lfi_nct_admin_get_tiles($stats = null) {
    if ($stats === null) $stats = lfi_nct_app_quick_stats();
    return lfi_nct_prune_past_event_tiles(lfi_nct_module_filter_tiles([
        ['📣', 'Inscrits réunion 26 juin', $stats['reunion'] . ' inscription(s)', lfi_nct_app_url('reunion')],
        ['🏠', 'Enquêtes logement',         $stats['surveys'] . ' réponse(s)',     lfi_nct_app_url('enquetes')],
        ['📊', 'Stats enquête',             'Problèmes, adresses, gravité',        lfi_nct_app_url('stats-enquete')],
        ['🗺️', 'Carte interactive',         'Géolocalisation des réponses',        lfi_nct_app_url('carte')],
        ['➕', '+ Saisir une réponse d\'enquête', 'Porte-à-porte / collecte papier', lfi_nct_app_url('temoignage-add')],
        ['🪪', 'Comptes (GA & locataires)',  'Créer / gérer accès',                 lfi_nct_app_url('comptes')],
        ['🗂', 'Dossiers locataires',        'Photos, enquête, historique',         lfi_nct_app_url('dossiers')],
        ['👥', 'Membres actifs',            $stats['membres'] . ' membre(s) actif(s)', lfi_nct_app_url('membres')],
        ['📱', 'Envoyer SMS',               'Diffusion ciblée',                    lfi_nct_app_url('sms')],
        ['✉️', 'Email blast',               'Campagne mail',                       lfi_nct_app_url('email')],
        ['✍️', 'Signatures email',           'Le Collectif, Fabrice, etc.',         lfi_nct_app_url('signatures')],
        ['📅', 'Événements',                $stats['events']  . ' événement(s)',   lfi_nct_app_url('evenements')],
        ['📈', 'Stats globales',            'Compteurs du GA',                     lfi_nct_app_url('stats')],
        ['🔥', 'Purger le cache',           'Forcer la maj',                       lfi_nct_app_url('cache')],
        ['📰', 'Articles',                  'Édition WP',                          admin_url('edit.php')],
        ['📝', 'Pages',                     'Édition WP',                          admin_url('edit.php?post_type=page')],
        ['🚪', 'Se déconnecter',            'Quitter la console',                  wp_logout_url(home_url('/'))],
    ]));
}

/**
 * Dashboard tiles organisées en sections pour ne plus avoir une bouillie
 * de 17 tuiles. Renvoie un tableau de sections [titre => [tuiles...]].
 */
function lfi_nct_admin_get_tiles_sections($stats = null) {
    if ($stats === null) $stats = lfi_nct_app_quick_stats();
    return lfi_nct_prune_past_event_sections(lfi_nct_module_filter_sections([
        '🟣 VOLET GROUPE D\'ACTION (local)' => [
            ['🤖', 'Assistant',              'Demande un dossier, une stat, un contact', lfi_nct_app_url('assistant')],
            ['📓', 'Journal de bord',        'Suivi général : avocat, préfecture…', lfi_nct_app_url('journal')],
            ['📖', 'Guide d\'utilisation',   'Tout l\'outil, pas à pas',            lfi_nct_app_url('guide')],
            ['🎨', 'Personnalisation du GA', 'En-tête courriers · bailleurs',       lfi_nct_app_url('ga-params')],
            ['👥', 'Membres actifs',         $stats['membres'] . ' membre(s) actif(s)', lfi_nct_app_url('membres')],
            ['🪪', 'Comptes GA',             'Créer · importer · reset',            lfi_nct_app_url('comptes-ga')],
            ['📅', 'Événements',             $stats['events'] . ' à venir',         lfi_nct_app_url('evenements')],
            ['📣', 'Inscrits réunion',       $stats['reunion'] . ' inscription(s)', lfi_nct_app_url('reunion')],
            ['📱', 'SMS aux membres actifs', 'Modèles + diffusion',                 lfi_nct_app_url('sms')],
            ['✉️', 'Email blast',            'En-tête LFI + signature',             lfi_nct_app_url('email')],
            ['✍️', 'Signatures',             'Le Collectif, Fabrice…',              lfi_nct_app_url('signatures')],
        ],
        '📋 ENQUÊTE LOGEMENT (terrain)' => [
            ['🏠', 'Réponses',              $stats['surveys'] . ' réponse(s)',     lfi_nct_app_url('enquetes')],
            ['📊', 'Stats enquête',          'Problèmes · adresses · gravité',     lfi_nct_app_url('stats-enquete')],
            ['🗺️', 'Carte interactive',      'Tous les signalements',              lfi_nct_app_url('carte')],
            ['➕', 'Saisir une réponse',     'Porte-à-porte / papier',             lfi_nct_app_url('temoignage-add')],
        ],
        '🏠 ESPACE LOCATAIRES' => [
            ['🧠', 'Robot stratège',         'Meilleure tactique · amiable d\'abord', lfi_nct_app_url('strategie')],
            /* Le chiffrage du préjudice est AUTOMATIQUE et vit DANS le dossier
               (pré-rempli depuis les déclarations) — plus de calculateur isolé
               hors contexte ici. On y accède depuis la fiche du locataire. */
            ['🔎', 'Jurisprudence',          'Vraies décisions (Judilibre) par dossier', lfi_nct_app_url('jurisprudence')],
            ['🧭', 'Nouveau dossier (guidé)', 'Assistant pas-à-pas + plan d\'action', lfi_nct_app_url('dossier-wizard')],
            ['🏠', 'Comptes Locataires',     'Créer · éditer · reset',              lfi_nct_app_url('comptes-locataires')],
            ['🗂', 'Dossiers & suivi',       'Tout par locataire · photos',         lfi_nct_app_url('dossiers')],
            ['📥', 'Importer un email',      'Colle l\'email → bon dossier auto',    lfi_nct_app_url('email-import')],
            ['📲', 'SMS aux locataires',     'Vouvoiement · 7 modèles',             lfi_nct_app_url('sms-locataires')],
        ],
        '🔧 ESPACE INTERVENTION (brigade)' => [
            ['🔧', 'Interventions',          'Travaux chez les locataires',         lfi_nct_app_url('interventions')],
            ['☎️', 'Appels NMH',            'Journal + rapports d\'incident',       lfi_nct_app_url('appels-nmh')],
            ['⚖️', 'Recouvrement NMH',       'Mise en demeure, tribunal',           lfi_nct_app_url('recouvrements')],
            ['💶', 'Comptabilité',           'CA · relances · export factures',     lfi_nct_app_url('compta')],
            ['📅', 'Agenda',                 'RDV et interventions',                lfi_nct_app_url('agenda')],
            ['🛠', 'Tutoriels',              'Guides par problème',                 lfi_nct_app_url('tutoriels')],
            ['🔬', 'Outils scientifiques',   'Sonomètre, GPS, photo preuve…',       lfi_nct_app_url('outils')],
            ['⚙️', 'Paramètres facturation', 'Prestataire · bailleur · tarif',      lfi_nct_app_url('facturation-params')],
        ],
        '🏛 ESPACE ASSOCIATION' => [
            ['🏛', 'Association',            'Statuts · identité · documents',      lfi_nct_app_url('association')],
            ['📁', 'Dossiers juridiques',    'LRAR · relogement · SCHS/ARS',        lfi_nct_app_url('dossiers-juridiques')],
            ['⚖️', 'Cadre juridique',        'Ce qui est facturable, par qui',      lfi_nct_app_url('cadre-juridique')],
            ['🏛️', 'Préfecture',            'Partage anonyme par bâtiment',        lfi_nct_app_url('prefecture')],
            ['🏆', 'Réussites',             'Victoires anonymes · articles',       lfi_nct_app_url('reussites')],
        ],
        '👶 VOLET PROTECTION DE L\'ENFANCE' => [
            ['👶', 'Protection de l\'enfance', 'ASE · Conseil départemental · séparé du logement', lfi_nct_app_url('ase')],
        ],
        '🏛️ VOLET MUNICIPAL — élus locaux' => [
            ['🤝', 'Élu·es partenaires',       'Espace privé · ligne directe · dossier partagé', lfi_nct_app_url('partenaires')],
            ['🏛️', 'Stratégie municipale',     'William · le conseil · l\'audit NMH', lfi_nct_app_url('strategie-municipale')],
            ['💶', 'Où va mon loyer ? (audit NMH)', 'Chiffres CRC sourcés · 3 versions', lfi_nct_app_url('audit-nmh')],
            ['🌐', 'Tableau de bord du réseau', 'Tous les GA · stats cumulées',       lfi_nct_app_url('reseau-ga')],
            ['🗺️', 'Annuaire & créer un GA',   'Liste · création · binôme',          lfi_nct_app_url('groupes')],
            ['🎯', 'Périmètres des GA',        'Rayon d\'action · routage enquêtes', lfi_nct_app_url('geo-perimetres')],
            ['🗺️', 'Carte générale (tous les GA)', 'Toutes les enquêtes, une carte 3D', lfi_nct_app_url('reseau-carte')],
            ['📊', 'Stats enquête — réseau',    'Toutes les enquêtes additionnées',   lfi_nct_app_url('reseau-stats-enquete')],
            ['💡', 'Suggestions des GA',        'Besoins remontés par les admins',    lfi_nct_app_url('suggestions')],
            ['📡', 'Activité & connexions',     'Qui utilise l\'app · GA actifs/dormants', lfi_nct_app_url('activite')],
        ],
        '🇫🇷 VOLET NATIONAL — député·es' => [
            ['🤝', 'Élu·es partenaires',        'Espace privé des député·es',         lfi_nct_app_url('partenaires')],
            ['🩺', 'Santé publique (puffs)',    'Dossier national + européen',        lfi_nct_app_url('sante')],
            ['🇫🇷', 'Stratégie nationale',      'Remonter · multi-GA · députation',   lfi_nct_app_url('strategie-nationale')],
            ['🇫🇷', 'Tableau de bord national', 'Chiffres cumulés pour argumenter',   lfi_nct_app_url('national')],
            ['🗣️', 'Éléments de langage',       'Arguments prêts à l\'emploi',        lfi_nct_app_url('national-args')],
            ['📚', 'Études & données',           'Documents · données scientifiques',  lfi_nct_app_url('national-etudes')],
            ['📄', 'Dossier national (PDF)',     'À envoyer aux député·es',            lfi_nct_app_url('national-pdf')],
        ],
        '⚙️ SYSTÈME' => [
            ['💾', 'Sauvegarde & export',     'Télécharger les données (point fixe)', lfi_nct_app_url('sauvegarde')],
            ['🛡️', 'Cadre RGPD',              'Fichiers légaux · registre · droit à l\'oubli', lfi_nct_app_url('rgpd')],
            ['📬', 'Check emails auto',       'Surveillance boîte 24/7 · réponses prêtes', lfi_nct_app_url('mailcheck')],
            ['🧩', 'Modules',                'Activer / retirer les outils',        lfi_nct_app_url('modules-params')],
            ['🔄', 'Synchroniser',           'Forcer la maj sur tous mes appareils', admin_url('admin-post.php?action=lfi_nct_purge_all')],
            ['👁', 'Aperçu de l\'app',       'Voir comme un locataire / GA',        lfi_nct_app_url('preview')],
            ['📈', 'Stats globales',         'Tous les compteurs',                  lfi_nct_app_url('stats')],
            ['🔥', 'Purger le cache',        'Forcer la maj',                       lfi_nct_app_url('cache')],
            ['📰', 'Articles',               'Édition WP',                          admin_url('edit.php')],
            ['📝', 'Pages',                  'Édition WP',                          admin_url('edit.php?post_type=page')],
            ['🚪', 'Se déconnecter',         '',                                    wp_logout_url(home_url('/'))],
        ],
    ]));
}

/* ============================================================== *
 *  Barre flottante en haut de la home pour admin connecté         *
 *  - Visible uniquement pour les admins (front public = rien)     *
 *  - Sur la page d'accueil + /app/ (qui a déjà son menu)          *
 *  - Strip horizontal scrollable mobile, full grid desktop        *
 *  - Repliable / dépliable (état mémorisé en localStorage)        *
 * ============================================================== */
add_action('wp_body_open', 'lfi_nct_admin_homepage_strip', 1);
add_action('wp_footer',    'lfi_nct_admin_homepage_strip', 1);
function lfi_nct_admin_homepage_strip() {
    static $rendered = false;
    if ($rendered) return;
    if (!is_user_logged_in() || !current_user_can('manage_options')) return;
    /* Affiché sur l'accueil (et pas dans wp-admin évidemment) */
    if (is_admin()) return;
    if (!is_front_page() && !is_home()) return;
    $rendered = true;

    $tiles = lfi_nct_admin_get_tiles();
    $user  = wp_get_current_user();
    ?>
    <div id="lfi-quickbar" class="lfi-quickbar" role="region" aria-label="Outils GA">
        <button class="lfi-qb-toggle" type="button" aria-label="Replier la barre" aria-expanded="true">
            <span class="lfi-qb-brand">Φ <strong>GA LFI</strong><span class="lfi-qb-hi"> · <?php echo esc_html($user->display_name ?: $user->user_login); ?></span></span>
            <span class="lfi-qb-caret">▾</span>
        </button>
        <div class="lfi-qb-scroll">
            <?php foreach ($tiles as $t): ?>
                <a class="lfi-qb-tile" href="<?php echo esc_url($t[3]); ?>" title="<?php echo esc_attr($t[1] . ' — ' . $t[2]); ?>">
                    <span class="ico"><?php echo $t[0]; ?></span>
                    <span class="lbl"><?php echo esc_html($t[1]); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <style>
    .lfi-quickbar {
        position: fixed; top: 0; left: 0; right: 0; z-index: 99998;
        background: linear-gradient(180deg, #c8102e 0%, #a30b25 100%);
        color: #fff;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        box-shadow: 0 2px 12px rgba(0,0,0,.25);
        transition: transform .25s ease;
    }
    .lfi-quickbar.is-collapsed { transform: translateY(calc(-100% + 36px)); }
    .lfi-quickbar.is-collapsed .lfi-qb-caret { transform: rotate(180deg); }

    .lfi-qb-toggle {
        width: 100%; display: flex; justify-content: space-between; align-items: center;
        background: rgba(0,0,0,.18); color: #fff; border: 0;
        padding: 8px 14px; font-size: .85em; cursor: pointer;
        font-family: inherit;
    }
    .lfi-qb-brand strong { font-weight: 800; letter-spacing: .3px; margin-left: 4px; }
    .lfi-qb-hi { opacity: .85; font-weight: 400; }
    .lfi-qb-caret { font-size: 1.1em; display: inline-block; transition: transform .25s ease; }

    .lfi-qb-scroll {
        display: flex; gap: 8px; padding: 10px 10px 12px;
        overflow-x: auto; overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
    .lfi-qb-scroll::-webkit-scrollbar { height: 6px; }
    .lfi-qb-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.4); border-radius: 3px; }

    .lfi-qb-tile {
        flex: 0 0 auto;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        min-width: 78px; max-width: 100px; padding: 8px 10px;
        background: rgba(255,255,255,.14);
        border-radius: 10px; text-decoration: none; color: #fff;
        font-size: .72em; line-height: 1.15; text-align: center;
        transition: background .12s ease, transform .08s ease;
    }
    .lfi-qb-tile:hover, .lfi-qb-tile:focus { background: rgba(255,255,255,.26); color: #fff; }
    .lfi-qb-tile:active { transform: scale(.95); }
    .lfi-qb-tile .ico { font-size: 1.5em; margin-bottom: 3px; }
    .lfi-qb-tile .lbl { font-weight: 600; }

    /* Desktop : grille fluide qui passe à la ligne */
    @media (min-width: 800px) {
        .lfi-qb-scroll { flex-wrap: wrap; justify-content: center; overflow: visible; }
        .lfi-qb-tile { min-width: 92px; }
    }

    /* Pousse le contenu vers le bas pour qu'il ne soit pas masqué */
    body.lfi-has-quickbar { padding-top: 0 !important; }
    body.lfi-has-quickbar.lfi-quickbar-open { margin-top: 132px; }
    body.lfi-has-quickbar.lfi-quickbar-closed { margin-top: 36px; }
    @media (min-width: 800px) {
        body.lfi-has-quickbar.lfi-quickbar-open { margin-top: 160px; }
    }
    </style>
    <script>
    (function () {
        var bar  = document.getElementById('lfi-quickbar');
        var body = document.body;
        if (!bar || !body) return;
        body.classList.add('lfi-has-quickbar');

        function apply(collapsed) {
            bar.classList.toggle('is-collapsed', collapsed);
            body.classList.toggle('lfi-quickbar-open',   !collapsed);
            body.classList.toggle('lfi-quickbar-closed',  collapsed);
            var btn = bar.querySelector('.lfi-qb-toggle');
            if (btn) btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
        var saved = false;
        try { saved = localStorage.getItem('lfi-quickbar-collapsed') === '1'; } catch (e) {}
        apply(saved);

        var toggle = bar.querySelector('.lfi-qb-toggle');
        if (toggle) {
            toggle.addEventListener('click', function () {
                var willCollapse = !bar.classList.contains('is-collapsed');
                apply(willCollapse);
                try { localStorage.setItem('lfi-quickbar-collapsed', willCollapse ? '1' : '0'); } catch (e) {}
            });
        }
    })();
    </script>
    <?php
}

/* ============================================================== *
 *  ÉCRANS NATIFS                                                  *
 *                                                                  *
 *  Chacun fait son SQL en direct + rend du HTML mobile-friendly.  *
 *  Le bouton Accueil du navbar ramène à /app/.                    *
 * ============================================================== */

/* ---------- 📣 Inscrits réunion 26 juin ---------- */
function lfi_nct_app_view_reunion() {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_reunion_rsvp';

    /* MULTI-GA : cette réunion (26 juin) est propre à Clos Toreau. Les autres
       GA ne voient PAS ces inscriptions (espace vide tant qu'ils n'ont pas la
       leur). On considère « home » = super-admin sur son espace OU Clos Toreau. */
    $is_home_ga = !function_exists('lfi_nct_scope_ga_slug')
        || in_array(lfi_nct_scope_ga_slug(), ['', 'clos-toreau'], true);

    if ($is_home_ga && !empty($_POST['lfi_app_del']) && check_admin_referer('lfi_app_reunion_del')) {
        $wpdb->delete($table, ['id' => (int) $_POST['lfi_app_del']]);
        wp_safe_redirect(lfi_nct_app_url('reunion', ['deleted' => 1]));
        exit;
    }

    $rows  = $is_home_ga ? ($wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 200") ?: []) : [];
    $total = count($rows);
    $pers  = $is_home_ga ? (int) $wpdb->get_var("SELECT COALESCE(SUM(avec_qui),0) FROM $table") : 0;

    $reunion_past = function_exists('lfi_nct_reunion_confluences_is_past') && lfi_nct_reunion_confluences_is_past();
    lfi_nct_app_screen_open('📣 Réunion 26 juin' . ($reunion_past ? ' (passée)' : ''), $total . ' inscription(s) · ' . $pers . ' personne(s) annoncée(s)');

    if (!empty($_GET['deleted'])) lfi_nct_app_flash('Inscription supprimée.');
    if ($reunion_past) {
        echo '<div class="lfi-app-help" style="background:#f4f4f4;border-left:4px solid #999">🗓️ <strong>Réunion passée</strong> — les inscriptions sont closes. Cet écran reste consultable en historique (l\'événement reste au calendrier, marqué « passé »).</div>';
    }

    if (empty($rows)) {
        echo '<div class="lfi-app-empty">Aucune inscription pour l\'instant.<br><small>Partage le tract pour faire venir du monde.</small></div>';
    } else {
        echo '<ul class="lfi-app-list">';
        foreach ($rows as $r) {
            $name = trim($r->prenom . ' ' . $r->nom) ?: '(anonyme)';
            echo '<li class="lfi-app-card">';
            echo '<div class="head"><div class="who">' . esc_html($name) . '</div><div class="when">' . esc_html(wp_date('j M H:i', strtotime($r->created_at))) . '</div></div>';
            echo '<div class="meta">';
            if (!empty($r->tel))   echo '<a class="meta-chip" href="tel:' . esc_attr($r->tel) . '">📞 ' . esc_html($r->tel) . '</a>';
            if (!empty($r->email)) echo '<a class="meta-chip" href="mailto:' . esc_attr($r->email) . '">✉️ ' . esc_html($r->email) . '</a>';
            echo '<span class="meta-chip nb">👥 ' . (int) $r->avec_qui . '</span>';
            echo '</div>';
            if (!empty($r->commentaire)) {
                echo '<div class="com">« ' . esc_html($r->commentaire) . ' »</div>';
            }
            echo '<form method="post" class="row-actions" onsubmit="return confirm(\'Supprimer ' . esc_js($name) . ' ?\');">';
            wp_nonce_field('lfi_app_reunion_del');
            echo '<input type="hidden" name="lfi_app_del" value="' . (int) $r->id . '">';
            echo '<button type="submit" class="btn-del">🗑 Supprimer</button>';
            echo '</form>';
            echo '</li>';
        }
        echo '</ul>';
    }

    lfi_nct_app_screen_close();
}

/* ---------- 👥 Adhérents ---------- */
function lfi_nct_app_view_membres() {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_membres';

    // Ajout
    if (!empty($_POST['lfi_app_add']) && check_admin_referer('lfi_app_membre_add')) {
        $prenom = sanitize_text_field(wp_unslash($_POST['prenom'] ?? ''));
        $nom    = sanitize_text_field(wp_unslash($_POST['nom']    ?? ''));
        $email  = sanitize_email(wp_unslash($_POST['email']       ?? ''));
        $tel    = sanitize_text_field(wp_unslash($_POST['tel']    ?? ''));
        $statut = sanitize_text_field(wp_unslash($_POST['statut'] ?? 'membre'));
        if ($prenom || $email || $tel) {
            $wpdb->insert($table, [
                'statut' => $statut, 'prenom' => $prenom, 'nom' => $nom,
                'email' => $email, 'tel' => $tel,
                'abonne_emails' => 1, 'source' => 'app',
                'ga' => function_exists('lfi_nct_creation_ga') ? lfi_nct_creation_ga() : '',
                'unsubscribe_token' => function_exists('lfi_nct_make_unsub_token') ? lfi_nct_make_unsub_token() : bin2hex(random_bytes(20)),
                'membre_depuis' => current_time('mysql'),
            ]);
            wp_safe_redirect(lfi_nct_app_url('membres', ['added' => 1]));
            exit;
        }
    }
    /* MULTI-GA : cloisonnement des adhérents par groupe d'action. Chaque GA
       ne voit QUE ses adhérents ; l'espace home (Clos Toreau) = ga ''. */
    $gac = function_exists('lfi_nct_membres_ga_clause') ? lfi_nct_membres_ga_clause('ga') : '';

    // Suppression — bornée au GA en vigueur (on ne supprime pas l'adhérent d'un autre GA)
    if (!empty($_POST['lfi_app_del']) && check_admin_referer('lfi_app_membre_del')) {
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id = %d" . $gac, (int) $_POST['lfi_app_del']));
        wp_safe_redirect(lfi_nct_app_url('membres', ['deleted' => 1]));
        exit;
    }

    $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    if ($q) {
        $like = '%' . $wpdb->esc_like($q) . '%';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE (prenom LIKE %s OR nom LIKE %s OR email LIKE %s OR tel LIKE %s)" . $gac . " ORDER BY created_at DESC LIMIT 200",
            $like, $like, $like, $like
        )) ?: [];
    } else {
        $rows = $wpdb->get_results("SELECT * FROM $table WHERE 1=1" . $gac . " ORDER BY created_at DESC LIMIT 200") ?: [];
    }
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE 1=1" . $gac);

    lfi_nct_app_screen_open('👥 Membres actifs du GA', $total . ' membre(s) actif(s) au total');

    if (!empty($_GET['added']))   lfi_nct_app_flash('Membre actif ajouté.');
    if (!empty($_GET['deleted'])) lfi_nct_app_flash('Membre actif supprimé.');

    echo '<form method="get" class="lfi-app-searchbar">';
    echo '<input type="hidden" name="vue" value="membres">';
    echo '<input type="search" name="q" value="' . esc_attr($q) . '" placeholder="Recherche prénom, nom, email, tél…">';
    echo '<button type="submit">🔍</button>';
    if ($q) echo '<a class="clear" href="' . esc_url(lfi_nct_app_url('membres')) . '">×</a>';
    echo '</form>';

    echo '<details class="lfi-app-collapse"><summary>+ Ajouter un membre actif</summary>';
    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_membre_add');
    echo '<input type="hidden" name="lfi_app_add" value="1">';
    echo '<label>Prénom<input type="text" name="prenom" required></label>';
    echo '<label>Nom<input type="text" name="nom"></label>';
    echo '<label>Email<input type="email" name="email" placeholder="exemple@email.fr"></label>';
    echo '<label>Téléphone<input type="tel" name="tel" placeholder="06 12 34 56 78"></label>';
    echo '<label>Statut<select name="statut"><option value="membre">Membre</option><option value="sympathisant">Sympathisant</option><option value="contact">Contact</option></select></label>';
    echo '<button type="submit" class="btn-primary">✓ Ajouter</button>';
    echo '</form></details>';

    if (empty($rows)) {
        echo '<div class="lfi-app-empty">' . ($q ? 'Aucun résultat pour « ' . esc_html($q) . ' ».' : 'Aucun membre actif.') . '</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        foreach ($rows as $r) {
            $name = trim($r->prenom . ' ' . $r->nom) ?: ($r->pseudo ?: '(sans nom)');
            echo '<li class="lfi-app-card">';
            echo '<div class="head"><div class="who">' . esc_html($name) . '</div>';
            if (!empty($r->statut)) echo '<div class="badge">' . esc_html($r->statut) . '</div>';
            echo '</div>';
            echo '<div class="meta">';
            if (!empty($r->tel))   echo '<a class="meta-chip" href="tel:' . esc_attr($r->tel) . '">📞 ' . esc_html($r->tel) . '</a>';
            if (!empty($r->email)) echo '<a class="meta-chip" href="mailto:' . esc_attr($r->email) . '">✉️ ' . esc_html($r->email) . '</a>';
            if (!empty($r->tel)) {
                echo '<a class="meta-chip act" href="' . esc_url(lfi_nct_app_url('sms', ['membre' => $r->id])) . '">📱 SMS</a>';
            }
            echo '</div>';
            echo '<form method="post" class="row-actions" onsubmit="return confirm(\'Supprimer ' . esc_js($name) . ' ?\');">';
            wp_nonce_field('lfi_app_membre_del');
            echo '<input type="hidden" name="lfi_app_del" value="' . (int) $r->id . '">';
            echo '<button type="submit" class="btn-del">🗑 Supprimer</button>';
            echo '</form>';
            echo '</li>';
        }
        echo '</ul>';
    }

    lfi_nct_app_screen_close();
}

/* ---------- 📅 Événements ---------- */
function lfi_nct_app_view_evenements() {
    $cpts = [];
    if (post_type_exists('ag_evenement'))  $cpts[] = 'ag_evenement';
    if (post_type_exists('lfi_evenement')) $cpts[] = 'lfi_evenement';
    if (empty($cpts)) {
        lfi_nct_app_screen_open('📅 Événements');
        echo '<div class="lfi-app-empty">CPT événement absent.</div>';
        lfi_nct_app_screen_close();
        return;
    }
    $events = get_posts(['post_type' => $cpts, 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'meta_value', 'meta_key' => '_ag_event_date', 'order' => 'ASC']);
    /* Cloisonnement par GA : chaque GA ne voit QUE ses événements (filtre simple
       et sûr sur le rattachement _lfi_evt_ga, sans toucher au tri). */
    if (function_exists('lfi_nct_scope_ga_slug')) {
        $ev_slug = lfi_nct_scope_ga_slug();
        $ev_home = ($ev_slug === '' || $ev_slug === 'clos-toreau');
        $events = array_values(array_filter($events, function ($p) use ($ev_slug, $ev_home) {
            $g = (string) get_post_meta($p->ID, '_lfi_evt_ga', true);
            return $ev_home ? ($g === '' || $g === 'clos-toreau') : ($g === $ev_slug);
        }));
    }
    $total = count($events);
    lfi_nct_app_screen_open('📅 Événements', $total . ' événement(s)');

    if (!empty($_GET['evt_add'])) lfi_nct_app_flash('✅ Événement créé.');
    if (!empty($_GET['evt_upd'])) lfi_nct_app_flash('✅ Événement mis à jour.');
    /* Créer un événement : réservé aux admins (GA admins + super-admin). */
    $ev_can_edit = current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
    if ($ev_can_edit) {
        echo '<div style="margin:0 0 12px"><a class="btn-primary" href="' . esc_url(lfi_nct_app_url('evenement-add')) . '">➕ Créer un événement</a></div>';
    }

    if (empty($events)) {
        echo '<div class="lfi-app-empty">Aucun événement.</div>';
    } else {
        /* Tri : on sépare « à venir » et « passés », puis on trie chaque groupe.
           À venir = du plus proche au plus loin ; passés = du plus récent au plus ancien. */
        $can_edit = current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
        $upcoming = []; $past = [];
        foreach ($events as $p) {
            $d  = function_exists('lfi_nct_event_data') ? lfi_nct_event_data($p) : null;
            $ts = (int) ($d['ts'] ?? 0);
            if (!$ts) {
                $raw = get_post_meta($p->ID, '_ag_event_date', true) ?: get_post_meta($p->ID, '_lfi_evt_date_debut', true);
                $ts  = $raw ? (int) strtotime($raw) : 0;
            }
            $row = ['p' => $p, 'ts' => $ts, 'past' => (bool) ($d['is_past'] ?? false)];
            if ($row['past']) $past[] = $row; else $upcoming[] = $row;
        }
        /* À venir : ceux sans date connue (ts=0) partent à la fin. */
        usort($upcoming, function ($a, $b) {
            if ($a['ts'] === 0 xor $b['ts'] === 0) return $a['ts'] === 0 ? 1 : -1;
            return $a['ts'] <=> $b['ts'];
        });
        usort($past, function ($a, $b) { return $b['ts'] <=> $a['ts']; });

        /* Rendu d'une carte événement (mutualisé entre « à venir » et « passés »). */
        $render_card = function ($p, $ev_past) use ($can_edit) {
            $date  = get_post_meta($p->ID, '_ag_event_date', true) ?: get_post_meta($p->ID, '_lfi_evt_date_debut', true);
            $time  = get_post_meta($p->ID, '_ag_event_time', true);
            $place = get_post_meta($p->ID, '_ag_event_place', true);
            $city  = get_post_meta($p->ID, '_ag_event_city',  true);
            echo '<li class="lfi-app-card">';
            echo '<div class="head"><div class="who">' . esc_html(get_the_title($p)) . ($ev_past ? ' <span style="font-size:.72em;background:#eee;color:#888;padding:2px 7px;border-radius:6px;font-weight:600;vertical-align:middle">passé</span>' : '') . '</div></div>';
            echo '<div class="meta">';
            if ($date)  echo '<span class="meta-chip">🗓 ' . esc_html($date) . ($time ? ' · ' . esc_html($time) : '') . '</span>';
            if ($place) echo '<span class="meta-chip">📍 ' . esc_html($place) . ($city ? ', ' . esc_html($city) : '') . '</span>';
            echo '</div>';
            $ev_url   = get_permalink($p);
            $ev_title = get_the_title($p);
            echo '<div class="row-actions" style="flex-wrap:wrap;gap:6px">';
            echo '<a class="btn-primary" href="' . esc_url($ev_url) . '" target="_blank" rel="noopener">🔗 Page publique</a>';
            $ap_url = (string) get_post_meta($p->ID, '_lfi_evt_ap_url', true);
            if ($ap_url) echo '<a class="btn-ghost" href="' . esc_url($ap_url) . '" target="_blank" rel="noopener">📣 Action Populaire</a>';
            echo lfi_nct_app_share_control($ev_url, $ev_title . ' — GA LFI Nantes Sud');
            if ($can_edit) {
                echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('flyer', ['event' => $p->ID])) . '">🖨 Flyer + QR</a>';
                if (!$ev_past) echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('event-sms', ['event' => $p->ID])) . '">📱 Diffuser SMS</a>';
                echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('event-inscrits', ['event' => $p->ID])) . '">📋 Inscrit·es</a>';
                echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('evenement-edit', ['id' => $p->ID])) . '">✏️ Éditer</a>';
            }
            echo '</div>';
            echo '</li>';
        };

        /* --- À venir (du plus proche au plus loin) --- */
        echo '<div class="lfi-app-help" style="margin:0 0 8px;font-weight:700">📅 À venir (' . count($upcoming) . ')</div>';
        if ($upcoming) {
            echo '<ul class="lfi-app-list">';
            foreach ($upcoming as $row) $render_card($row['p'], false);
            echo '</ul>';
        } else {
            echo '<div class="lfi-app-empty">Aucun événement à venir.</div>';
        }

        /* --- Passés (à part, repliés, du plus récent au plus ancien) --- */
        if ($past) {
            echo '<details style="margin-top:16px">';
            echo '<summary style="cursor:pointer;font-weight:700;color:#666;padding:6px 0">🕓 Événements passés (' . count($past) . ')</summary>';
            echo '<ul class="lfi-app-list" style="margin-top:8px;opacity:.85">';
            foreach ($past as $row) $render_card($row['p'], true);
            echo '</ul>';
            echo '</details>';
        }
    }
    lfi_nct_app_screen_close();
}

/* ---------- ➕ Créer un événement (admins) — avec lien Action Populaire ---------- */
function lfi_nct_app_view_evenement_add() {
    if (!(function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'))) return;

    if (!empty($_POST['lfi_evt_add']) && check_admin_referer('lfi_evt_add')) {
        $title = sanitize_text_field(wp_unslash($_POST['titre'] ?? ''));
        if ($title !== '') {
            $cpt = post_type_exists('ag_evenement') ? 'ag_evenement' : (post_type_exists('lfi_evenement') ? 'lfi_evenement' : 'post');
            $pid = wp_insert_post([
                'post_type'   => $cpt,
                'post_status' => 'publish',
                'post_title'  => $title,
                'post_content'=> wp_kses_post(wp_unslash($_POST['description'] ?? '')),
                'post_author' => get_current_user_id(),
            ], true);
            if (!is_wp_error($pid) && $pid) {
                update_post_meta($pid, '_ag_event_date',  sanitize_text_field(wp_unslash($_POST['date']  ?? '')));
                update_post_meta($pid, '_ag_event_time',  sanitize_text_field(wp_unslash($_POST['heure'] ?? '')));
                update_post_meta($pid, '_ag_event_place', sanitize_text_field(wp_unslash($_POST['lieu']  ?? '')));
                update_post_meta($pid, '_ag_event_city',  sanitize_text_field(wp_unslash($_POST['ville'] ?? '')));
                $ap = esc_url_raw(wp_unslash($_POST['ap_url'] ?? ''));
                if ($ap) update_post_meta($pid, '_lfi_evt_ap_url', $ap);
                /* Rattache l'événement au GA courant (cloisonnement des événements). */
                if (function_exists('lfi_nct_creation_ga')) update_post_meta($pid, '_lfi_evt_ga', lfi_nct_creation_ga());
                update_post_meta($pid, '_lfi_evt_internal', 1);
                wp_safe_redirect(lfi_nct_app_url('evenements', ['evt_add' => 1]));
                exit;
            }
        }
    }

    lfi_nct_app_screen_open('➕ Nouvel événement', 'Créer un événement de ton GA');
    echo '<div class="lfi-app-help">L\'événement apparaît dans <strong>ton</strong> agenda GA et sur le site. Ajoute le lien <strong>Action Populaire</strong> pour que les gens s\'y inscrivent.</div>';
    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_evt_add');
    echo '<input type="hidden" name="lfi_evt_add" value="1">';
    echo '<label>Titre <span style="color:#c8102e">*</span><input type="text" name="titre" required placeholder="Ex : Kermesse Républicaine"></label>';
    echo '<label>Date<input type="date" name="date"></label>';
    echo '<label>Heure<input type="text" name="heure" placeholder="Ex : 14h – 23h"></label>';
    echo '<label>Lieu<input type="text" name="lieu" placeholder="Ex : Parc de la Crapaudine"></label>';
    echo '<label>Ville<input type="text" name="ville" placeholder="Ex : Nantes"></label>';
    echo '<label>Lien Action Populaire (inscription)<input type="url" name="ap_url" placeholder="https://actionpopulaire.fr/evenements/…" inputmode="url" autocapitalize="none"></label>';
    echo '<label>Description<textarea name="description" rows="4" placeholder="Programme, infos pratiques…"></textarea></label>';
    echo '<button type="submit" class="btn-primary">✅ Créer l\'événement</button>';
    echo '</form>';
    lfi_nct_app_screen_close();
}

/* Vrai si l'événement $p appartient au GA actuellement en scope (cloisonnement). */
function lfi_nct_event_in_scope($p) {
    if (!$p) return false;
    if (!function_exists('lfi_nct_scope_ga_slug')) return true;
    $slug = lfi_nct_scope_ga_slug();
    $home = ($slug === '' || $slug === 'clos-toreau');
    $g = (string) get_post_meta($p->ID, '_lfi_evt_ga', true);
    return $home ? ($g === '' || $g === 'clos-toreau') : ($g === $slug);
}

/* ---------- ✏️ Éditer un événement (admins) — dans l'app, pas dans wp-admin ---------- */
function lfi_nct_app_view_evenement_edit() {
    if (!(function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'))) {
        wp_safe_redirect(lfi_nct_app_url('evenements'));
        exit;
    }

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $p  = $id ? get_post($id) : null;
    $cpts = ['ag_evenement', 'lfi_evenement'];
    /* Événement inexistant, mauvais type, ou d'un AUTRE GA → refusé (cloisonnement). */
    if (!$p || !in_array($p->post_type, $cpts, true) || !lfi_nct_event_in_scope($p)) {
        lfi_nct_app_screen_open('✏️ Éditer un événement');
        echo '<div class="lfi-app-empty">Événement introuvable dans ton groupe d\'action.</div>';
        echo '<div style="margin-top:12px"><a class="btn-primary" href="' . esc_url(lfi_nct_app_url('evenements')) . '">← Retour aux événements</a></div>';
        lfi_nct_app_screen_close();
        return;
    }

    /* Enregistrement des modifications. */
    if (!empty($_POST['lfi_evt_edit']) && check_admin_referer('lfi_evt_edit_' . $id)) {
        $title = sanitize_text_field(wp_unslash($_POST['titre'] ?? ''));
        if ($title !== '') {
            wp_update_post([
                'ID'           => $id,
                'post_title'   => $title,
                'post_content' => wp_kses_post(wp_unslash($_POST['description'] ?? '')),
            ]);
            update_post_meta($id, '_ag_event_date',  sanitize_text_field(wp_unslash($_POST['date']  ?? '')));
            update_post_meta($id, '_ag_event_time',  sanitize_text_field(wp_unslash($_POST['heure'] ?? '')));
            update_post_meta($id, '_ag_event_place', sanitize_text_field(wp_unslash($_POST['lieu']  ?? '')));
            update_post_meta($id, '_ag_event_city',  sanitize_text_field(wp_unslash($_POST['ville'] ?? '')));
            $ap = esc_url_raw(wp_unslash($_POST['ap_url'] ?? ''));
            if ($ap) { update_post_meta($id, '_lfi_evt_ap_url', $ap); }
            else     { delete_post_meta($id, '_lfi_evt_ap_url'); }
            wp_safe_redirect(lfi_nct_app_url('evenements', ['evt_upd' => 1]));
            exit;
        }
    }

    /* Valeurs actuelles (compat clés _ag_* et _lfi_evt_*). */
    $v_title = get_the_title($p);
    $v_date  = get_post_meta($id, '_ag_event_date', true) ?: get_post_meta($id, '_lfi_evt_date_debut', true);
    $v_time  = get_post_meta($id, '_ag_event_time',  true);
    $v_place = get_post_meta($id, '_ag_event_place', true);
    $v_city  = get_post_meta($id, '_ag_event_city',  true);
    $v_ap    = get_post_meta($id, '_lfi_evt_ap_url', true);
    $v_desc  = $p->post_content;

    lfi_nct_app_screen_open('✏️ Éditer l\'événement', 'Corrige les informations de cet événement');
    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_evt_edit_' . $id);
    echo '<input type="hidden" name="lfi_evt_edit" value="1">';
    echo '<label>Titre <span style="color:#c8102e">*</span><input type="text" name="titre" required value="' . esc_attr($v_title) . '"></label>';
    echo '<label>Date<input type="date" name="date" value="' . esc_attr($v_date) . '"></label>';
    echo '<label>Heure<input type="text" name="heure" value="' . esc_attr($v_time) . '" placeholder="Ex : 14h – 23h"></label>';
    echo '<label>Lieu<input type="text" name="lieu" value="' . esc_attr($v_place) . '" placeholder="Ex : Parc de la Crapaudine"></label>';
    echo '<label>Ville<input type="text" name="ville" value="' . esc_attr($v_city) . '" placeholder="Ex : Nantes"></label>';
    echo '<label>Lien Action Populaire (inscription)<input type="url" name="ap_url" value="' . esc_attr($v_ap) . '" placeholder="https://actionpopulaire.fr/evenements/…" inputmode="url" autocapitalize="none"></label>';
    echo '<label>Description<textarea name="description" rows="4" placeholder="Programme, infos pratiques…">' . esc_textarea($v_desc) . '</textarea></label>';
    echo '<button type="submit" class="btn-primary">💾 Enregistrer</button>';
    echo '</form>';
    echo '<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">';
    echo '<a class="btn-ghost" href="' . esc_url(get_permalink($p)) . '" target="_blank" rel="noopener">🔗 Voir la page publique</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('evenements')) . '">← Retour</a>';
    echo '</div>';
    lfi_nct_app_screen_close();
}

/* ---------- 📱 Envoyer SMS ---------- */
function lfi_nct_app_view_sms() {
    global $wpdb;
    $mem  = $wpdb->prefix . 'lfi_nct_membres';
    $tpl  = $wpdb->prefix . 'lfi_nct_sms_templates';
    $logt = $wpdb->prefix . 'lfi_nct_sms_log';

    // Log d'envoi
    if (!empty($_POST['lfi_app_sms_sent']) && check_admin_referer('lfi_app_sms_sent')) {
        $wpdb->insert($logt, [
            'template_id' => ((int) ($_POST['tpl_id'] ?? 0)) ?: null,
            'membre_id'   => ((int) ($_POST['membre_id'] ?? 0)) ?: null,
            'tel'         => sanitize_text_field(wp_unslash($_POST['tel']  ?? '')),
            'body_sent'   => sanitize_textarea_field(wp_unslash($_POST['body'] ?? '')),
            'sent_by'     => get_current_user_id(),
        ]);
        wp_safe_redirect(lfi_nct_app_url('sms', ['logged' => 1, 'tpl' => (int) $_POST['tpl_id'], 'membre' => (int) $_POST['membre_id']]));
        exit;
    }

    $membre_id = isset($_GET['membre']) ? (int) $_GET['membre'] : 0;
    $tpl_id    = isset($_GET['tpl'])    ? (int) $_GET['tpl']    : 0;
    /* Identifiant brut du modèle (peut être « gaN » pour un modèle propre au GA). */
    $tpl_sel   = isset($_GET['tpl'])    ? sanitize_text_field(wp_unslash($_GET['tpl'])) : '';
    /* Cloisonnement : on ne charge le membre destinataire QUE s'il appartient
       au GA courant (sinon fuite nom/tél via ?membre= d'un autre GA). */
    $mem_gac = function_exists('lfi_nct_membres_ga_clause') ? lfi_nct_membres_ga_clause('ga') : '';
    $membre = $membre_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $mem WHERE id = %d" . $mem_gac, $membre_id)) : null;
    $tpl_row = $tpl_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $tpl WHERE id = %d", $tpl_id)) : null;

    $event_id = isset($_GET['event']) ? (int) $_GET['event'] : 0;
    if (!$event_id && function_exists('lfi_nct_sms_upcoming_events')) {
        $upc = lfi_nct_sms_upcoming_events(1);
        if ($upc) $event_id = $upc[0]->ID;
    }
    $event_post = $event_id ? get_post($event_id) : null;
    $event_vars = function_exists('lfi_nct_sms_event_vars') ? lfi_nct_sms_event_vars($event_post) : [];

    $body = '';
    if ($tpl_row && $membre && function_exists('lfi_nct_sms_render')) {
        $body = lfi_nct_sms_render($tpl_row->body, $membre, $event_vars);
    } elseif ($tpl_row) {
        $body = $tpl_row->body;
    }

    $gac       = function_exists('lfi_nct_membres_ga_clause') ? lfi_nct_membres_ga_clause('ga') : '';
    $templates = $wpdb->get_results("SELECT * FROM $tpl ORDER BY categorie, nom") ?: [];
    /* Modèles SMS propres au GA (cf. Personnalisation du GA). */
    if (function_exists('lfi_nct_ga_sms_templates')) {
        foreach (lfi_nct_ga_sms_templates() as $i => $gt) {
            $templates[] = (object) [
                'id'        => 'ga' . $i,
                'nom'       => (string) ($gt['nom'] ?? 'Modèle GA'),
                'body'      => (string) ($gt['texte'] ?? ''),
                'categorie' => 'Mon GA',
            ];
        }
        /* Si un modèle GA est sélectionné (id « gaN », donc non numérique), on le
           résout depuis la liste (le lookup SQL par id ne le trouve pas). */
        if (!$tpl_row && isset($_GET['tpl'])) {
            $sel = sanitize_text_field(wp_unslash($_GET['tpl']));
            foreach ($templates as $tt) {
                if ((string) $tt->id === $sel) { $tpl_row = $tt; break; }
            }
            if ($tpl_row) {
                $body = ($membre && function_exists('lfi_nct_sms_render'))
                    ? lfi_nct_sms_render($tpl_row->body, $membre, $event_vars) : $tpl_row->body;
            }
        }
    }
    $membres   = $wpdb->get_results("SELECT id, prenom, nom, tel FROM $mem WHERE tel <> ''" . $gac . " ORDER BY prenom, nom LIMIT 300") ?: [];

    lfi_nct_app_screen_open('📱 Envoyer un SMS', 'Choisis un membre + un modèle, puis ouvre ton appli SMS');

    if (!empty($_GET['logged'])) lfi_nct_app_flash('SMS noté comme envoyé. 👍');

    echo '<form method="get" class="lfi-app-form">';
    echo '<input type="hidden" name="vue" value="sms">';

    echo '<label>👤 Destinataire<select name="membre" onchange="this.form.submit()">';
    echo '<option value="">— choisir un membre —</option>';
    foreach ($membres as $m) {
        $lbl = trim($m->prenom . ' ' . $m->nom) . ' — ' . $m->tel;
        echo '<option value="' . (int) $m->id . '" ' . selected($membre_id, $m->id, false) . '>' . esc_html($lbl) . '</option>';
    }
    echo '</select></label>';

    echo '<label>💬 Modèle<select name="tpl" onchange="this.form.submit()">';
    echo '<option value="">— choisir un modèle —</option>';
    foreach ($templates as $t) {
        $sel = selected((string) $tpl_sel, (string) $t->id, false);
        echo '<option value="' . esc_attr((string) $t->id) . '" ' . $sel . '>' . esc_html($t->nom) . '</option>';
    }
    echo '</select></label>';

    if ($event_post) {
        echo '<div class="lfi-app-help">📅 Événement lié : <strong>' . esc_html(get_the_title($event_post)) . '</strong></div>';
    }
    echo '</form>';

    if ($membre && $body) {
        $sms_url = 'sms:' . preg_replace('/[^\d+]/', '', $membre->tel) . '?body=' . rawurlencode($body);
        echo '<div class="lfi-app-card sms-preview">';
        echo '<div class="head"><div class="who">📱 ' . esc_html(trim($membre->prenom . ' ' . $membre->nom)) . '</div><div class="badge">' . esc_html($membre->tel) . '</div></div>';
        echo '<textarea readonly rows="6" class="sms-body" id="lfi-sms-body">' . esc_textarea($body) . '</textarea>';
        echo '<div class="lfi-app-help"><small>' . strlen($body) . ' caractères</small></div>';
        echo '<div class="row-actions">';
        echo '<a class="btn-primary big" href="' . esc_url($sms_url) . '">📲 Ouvrir mon SMS</a>';
        echo '<button type="button" class="btn-ghost" onclick="navigator.clipboard.writeText(document.getElementById(\'lfi-sms-body\').value);this.textContent=\'✓ Copié\';">📋 Copier</button>';
        echo '</div>';
        echo '<form method="post" class="row-actions">';
        wp_nonce_field('lfi_app_sms_sent');
        echo '<input type="hidden" name="lfi_app_sms_sent" value="1">';
        echo '<input type="hidden" name="tpl_id"    value="' . (int) $tpl_id . '">';
        echo '<input type="hidden" name="membre_id" value="' . (int) $membre_id . '">';
        echo '<input type="hidden" name="tel"  value="' . esc_attr($membre->tel) . '">';
        echo '<input type="hidden" name="body" value="' . esc_attr($body) . '">';
        echo '<button type="submit" class="btn-ghost">✅ Marquer comme envoyé</button>';
        echo '</form>';
        echo '</div>';
    }

    lfi_nct_app_screen_close();
}

/* ---------- ✉️ Email blast ---------- */
function lfi_nct_app_view_email() {
    global $wpdb;
    $mem = $wpdb->prefix . 'lfi_nct_membres';

    $gac = function_exists('lfi_nct_membres_ga_clause') ? lfi_nct_membres_ga_clause('ga') : '';

    if (!empty($_POST['lfi_app_email_send']) && check_admin_referer('lfi_app_email_send')) {
        $sujet = sanitize_text_field(wp_unslash($_POST['sujet'] ?? ''));
        $body  = wp_kses_post(wp_unslash($_POST['body']  ?? ''));
        $signature_key = sanitize_key($_POST['signature'] ?? 'collectif');
        if ($sujet && $body) {
            $recipients = $wpdb->get_results(
                "SELECT id, prenom, email, unsubscribe_token FROM $mem
                 WHERE email <> '' AND abonne_emails = 1 AND jetable = 0" . $gac
            ) ?: [];
            $sent = 0; $errs = 0;
            add_filter('wp_mail_content_type', function () { return 'text/html'; });
            add_filter('wp_mail_from_name', function() { return 'LFI Nantes Sud Clos Toreau'; });
            foreach ($recipients as $r) {
                if (!is_email($r->email)) { $errs++; continue; }
                $html = function_exists('lfi_nct_email_wrap_html')
                    ? lfi_nct_email_wrap_html($r->prenom, wpautop($body), '', $signature_key)
                    : '<div>' . wpautop($body) . '</div>';
                if (wp_mail($r->email, $sujet, $html)) $sent++; else $errs++;
            }
            remove_all_filters('wp_mail_content_type');
            remove_all_filters('wp_mail_from_name');
            wp_safe_redirect(lfi_nct_app_url('email', ['sent' => $sent, 'errs' => $errs]));
            exit;
        }
    }

    $abonnes = (int) $wpdb->get_var("SELECT COUNT(*) FROM $mem WHERE email <> '' AND abonne_emails = 1 AND jetable = 0" . $gac);

    lfi_nct_app_screen_open('✉️ Email à tous', $abonnes . ' destinataire(s) abonné(s)');

    if (isset($_GET['sent'])) {
        $s = (int) $_GET['sent']; $e = (int) ($_GET['errs'] ?? 0);
        lfi_nct_app_flash("✅ {$s} envoyé(s)" . ($e ? " · ⚠️ {$e} échec(s)" : ''));
    }

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_email_send');
    echo '<input type="hidden" name="lfi_app_email_send" value="1">';
    echo '<label>Sujet<input type="text" name="sujet" required placeholder="Objet de l\'email"></label>';
    echo '<label>Message<textarea name="body" rows="10" required placeholder="Écris ton message — HTML simple OK (les sauts de ligne deviennent des paragraphes)"></textarea></label>';
    if (function_exists('lfi_nct_signatures')) {
        $sigs = lfi_nct_signatures();
        echo '<label>✍️ Signataire<select name="signature">';
        foreach ($sigs as $k => $s) {
            echo '<option value="' . esc_attr($k) . '" ' . selected('collectif', $k, false) . '>' . esc_html($s['nom']) . ($s['role'] ? ' — ' . esc_html($s['role']) : '') . '</option>';
        }
        echo '</select></label>';
        echo '<div class="lfi-app-help"><small>Gère les signataires : <a href="' . esc_url(lfi_nct_app_url('signatures')) . '">✍️ Signatures</a></small></div>';
    }
    echo '<div class="lfi-app-help">⚠️ Sera envoyé à <strong>' . $abonnes . '</strong> personne(s). Action immédiate, pas de brouillon. En-tête LFI Clos Toreau ajoutée automatiquement.</div>';
    echo '<button type="submit" class="btn-primary big" onclick="return confirm(\'Envoyer maintenant à ' . $abonnes . ' destinataire(s) ?\');">📤 Envoyer maintenant</button>';
    echo '</form>';

    lfi_nct_app_screen_close();
}

/* ---------- Helpers enquête : extrait le problème principal ----- */
function lfi_nct_app_enq_problem($row) {
    $data = !empty($row->data) ? json_decode($row->data, true) : [];
    if (!is_array($data)) $data = [];

    $types       = (array) ($data['problemes_types']       ?? []);
    $types_autre = trim((string) ($data['problemes_types_autre'] ?? ''));
    $gravite     = (int)         ($data['problemes_gravite']     ?? 0);
    $recurrent   = (string)      ($data['problemes_recurrent']   ?? '');
    $duree       = (string)      ($data['problemes_duree']       ?? '');
    $ec_nb       = trim((string) ($data['eau_chaude_nb_par_an']  ?? ''));
    $ec_duree    = trim((string) ($data['eau_chaude_duree_max']  ?? ''));

    $labels = [
        'degats_eaux'      => ['💧', 'dégâts des eaux'],
        'humidite'         => ['🌫', 'humidité / moisissures'],
        'insectes'         => ['🐜', 'nuisibles (cafards, rats…)'],
        'chauffage'        => ['🥶', 'chauffage défaillant'],
        'electricite'      => ['⚡', 'électricité défectueuse'],
        'ascenseur'        => ['🛗', 'ascenseur en panne'],
        'parties_communes' => ['🚪', 'parties communes dégradées'],
        'bruit'            => ['🔊', 'nuisances sonores'],
        'securite'         => ['🚨', 'insécurité'],
    ];

    $chips = [];
    foreach ($types as $t) {
        if (isset($labels[$t])) $chips[] = $labels[$t];
    }
    if ($types_autre) $chips[] = ['❗', $types_autre];
    if ($ec_nb || $ec_duree) {
        $detail = trim('coupures d\'eau chaude' . ($ec_nb ? ' (' . $ec_nb . ' / an)' : ''));
        $chips[] = ['🚿', $detail];
    }
    if (!$chips) return null;

    return [
        'main'      => $chips[0],
        'chips'     => $chips,
        'gravite'   => $gravite,
        'recurrent' => $recurrent,
        'duree'     => $duree,
    ];
}

/* Rend une phrase qui décrit le(s) problème(s) sans pronom, pour SMS/email */
function lfi_nct_app_enq_phrase($problem) {
    if (!$problem) return '';
    $names = array_map(function ($c) { return $c[1]; }, $problem['chips']);
    $n = count($names);
    if ($n === 1) return $names[0];
    if ($n === 2) return $names[0] . ' et ' . $names[1];
    $last = array_pop($names);
    return implode(', ', $names) . ' et ' . $last;
}

/* Modèles de messages personnalisés enquête (SMS court + email + sujet) */
function lfi_nct_app_enq_message_template($mode, $row, $event_post = null) {
    $prenom = (string) ($row->contact_prenom ?? '');
    $problem = lfi_nct_app_enq_problem($row);
    $phrase  = lfi_nct_app_enq_phrase($problem);

    $ev_titre = $event_post ? get_the_title($event_post) : '';
    $ev_date  = $event_post ? get_post_meta($event_post->ID, '_ag_event_date', true) : '';
    $ev_time  = $event_post ? get_post_meta($event_post->ID, '_ag_event_time', true) : '';
    $ev_place = $event_post ? get_post_meta($event_post->ID, '_ag_event_place', true) : '';
    $ev_url   = $event_post ? get_permalink($event_post) : lfi_nct_page_url('reunion-26-juin-2026');
    $ev_when  = trim($ev_date . ($ev_time ? ' à ' . $ev_time : ''));

    switch ($mode) {
        case 'reunion':
            $body = "Bonjour " . ($prenom ?: 'camarade') . ", c'est le Groupe d'Action LFI Nantes Sud Clos Toreau. On organise une réunion publique sur le logement vendredi 26 juin à 15h à la salle Confluences (4 pl. du Muguet). Venez ? Infos : " . lfi_nct_page_url('reunion-26-juin-2026');
            $sujet = "Réunion logement vendredi 26 juin — Confluences";
            return ['body' => $body, 'sujet' => $sujet];

        case 'event':
            if (!$event_post) {
                $body = "Bonjour " . ($prenom ?: '') . ", suivi de votre enquête logement : retrouvez nous bientôt, on vous tient au courant des prochains événements. Calendrier : " . lfi_nct_page_url('evenements');
                $sujet = "Suivi enquête logement — LFI Clos Toreau";
                return ['body' => $body, 'sujet' => $sujet];
            }
            $body = "Bonjour " . ($prenom ?: 'camarade') . ", prochain rendez-vous du GA LFI Clos Toreau : " . $ev_titre;
            if ($ev_when)  $body .= " — " . $ev_when;
            if ($ev_place) $body .= " à " . $ev_place;
            $body .= ". Infos & inscription : " . $ev_url;
            $sujet = $ev_titre . ($ev_when ? ' — ' . $ev_when : '');
            return ['body' => $body, 'sujet' => $sujet];

        case 'probleme':
            $intro = $prenom ? "Bonjour " . $prenom . ", " : "Bonjour, ";
            if ($phrase) {
                $body = $intro . "suite à votre réponse à notre enquête de voisinage où vous nous avez signalé " . $phrase . ", on continue le travail collectif sur le logement au Clos Toreau.";
            } else {
                $body = $intro . "merci d'avoir répondu à notre enquête de voisinage. On continue le travail collectif sur le logement au Clos Toreau.";
            }
            if ($event_post) {
                $body .= " On en parle " . ($ev_when ?: 'lors de notre prochaine réunion') . " à " . ($ev_place ?: 'Confluences') . ". Inscription : " . $ev_url;
            } else {
                $body .= " Prochaine réunion publique vendredi 26 juin à 15h, salle Confluences. Infos : " . lfi_nct_page_url('reunion-26-juin-2026');
            }
            $sujet = "Suivi de votre enquête logement" . ($phrase ? ' — ' . $phrase : '');
            return ['body' => $body, 'sujet' => $sujet];

        case 'libre':
        default:
            return ['body' => '', 'sujet' => ''];
    }
}

/* Liste canonique des types de problème (partagée liste + édition). */
function lfi_nct_enq_problem_types() {
    return [
        'degats_eaux'      => ['💧', 'Dégâts des eaux'],
        'humidite'         => ['🌫', 'Humidité / moisissures'],
        'insectes'         => ['🐜', 'Nuisibles (cafards, rats…)'],
        'chauffage'        => ['🥶', 'Chauffage défaillant'],
        'electricite'      => ['⚡', 'Électricité défectueuse'],
        'ascenseur'        => ['🛗', 'Ascenseur en panne'],
        'parties_communes' => ['🚪', 'Parties communes dégradées'],
        'bruit'            => ['🔊', 'Nuisances sonores'],
        'securite'         => ['🚨', 'Insécurité'],
    ];
}

/** Peut gérer les enquêtes (éditer / supprimer) : admin du GA ou super-admin. */
function lfi_nct_enq_can_manage() {
    return function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options');
}

/* ---------- 🏠 Enquêtes logement : liste + filtres + actions ----- */
function lfi_nct_app_view_enquetes() {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $can_manage = lfi_nct_enq_can_manage();

    /* Cloisonnement par GA (chaque GA ne voit que ses propres réponses). */
    $sc = function_exists('lfi_nct_responses_scope_clause') ? lfi_nct_responses_scope_clause() : '';

    /* --- Actions de gestion (suppression → corbeille), cloisonnées au GA --- */
    if ($can_manage && !empty($_POST['lfi_enq_manage']) && check_admin_referer('lfi_app_enq_manage')) {
        $now = current_time('mysql');
        $ids = [];
        if (!empty($_POST['del_one'])) {
            $ids = [(int) $_POST['del_one']];
        } elseif (!empty($_POST['bulk_del']) && !empty($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = array_map('intval', $_POST['ids']);
        }
        $ids = array_values(array_filter($ids));
        $n = 0;
        foreach ($ids as $rid) {
            /* La clause de scope garantit qu'on ne touche QUE les enquêtes de ce GA. */
            $n += (int) $wpdb->query($wpdb->prepare(
                "UPDATE $table SET deleted_at = %s WHERE id = %d AND deleted_at IS NULL" . $sc,
                $now, $rid
            ));
        }
        wp_safe_redirect(lfi_nct_app_url('enquetes', array_filter([
            'f'       => isset($_POST['f']) ? sanitize_key($_POST['f']) : null,
            'sort'    => isset($_POST['sort']) ? sanitize_key($_POST['sort']) : null,
            'trashed' => $n,
        ])));
        exit;
    }

    /* Filtre : par défaut on ne montre que ceux qui ACCEPTENT le recontact (RGPD) */
    $filter = isset($_GET['f']) ? sanitize_key($_GET['f']) : 'recontact';
    $sort   = isset($_GET['sort']) ? sanitize_key($_GET['sort']) : 'recent';

    $where = "WHERE deleted_at IS NULL" . $sc;
    switch ($filter) {
        case 'tel':       $where .= " AND contact_tel <> '' AND contact_tel IS NOT NULL"; break;
        case 'email':     $where .= " AND contact_email <> '' AND contact_email IS NOT NULL"; break;
        case 'recontact': $where .= " AND contact_recontact = 1"; break;
        case 'all':       /* tout sauf corbeille */ break;
    }

    $rows = $wpdb->get_results(
        "SELECT id, submitted_at, adresse, etage, contact_recontact,
                contact_prenom, contact_nom, contact_tel, contact_email, data, ga
         FROM $table $where
         ORDER BY submitted_at DESC LIMIT 300"
    ) ?: [];

    /* Tri en mémoire (la gravité est dans le JSON — plus simple qu'en SQL). */
    $grav_of = function ($r) {
        $d = json_decode((string) $r->data, true);
        return is_array($d) ? (int) ($d['problemes_gravite'] ?? 0) : 0;
    };
    switch ($sort) {
        case 'ancien':
            $rows = array_reverse($rows);
            break;
        case 'gravite':
            usort($rows, function ($a, $b) use ($grav_of) { return $grav_of($b) <=> $grav_of($a); });
            break;
        case 'immeuble':
            usort($rows, function ($a, $b) { return strcasecmp((string) $a->adresse, (string) $b->adresse); });
            break;
        case 'nom':
            usort($rows, function ($a, $b) {
                return strcasecmp(trim($a->contact_nom . ' ' . $a->contact_prenom), trim($b->contact_nom . ' ' . $b->contact_prenom));
            });
            break;
        case 'recent':
        default:
            /* déjà trié DESC par la requête */
            break;
    }

    $total       = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE deleted_at IS NULL" . $sc);
    $with_tel    = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE deleted_at IS NULL" . $sc . " AND contact_tel <> ''   AND contact_tel   IS NOT NULL AND contact_recontact = 1");
    $with_email  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE deleted_at IS NULL" . $sc . " AND contact_email <> '' AND contact_email IS NOT NULL AND contact_recontact = 1");
    $with_recont = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE deleted_at IS NULL" . $sc . " AND contact_recontact = 1");
    $in_trash    = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE deleted_at IS NOT NULL" . $sc);

    lfi_nct_app_screen_open('🏠 Enquêtes logement', $total . ' réponse(s) · ' . $with_recont . ' acceptent recontact');

    if (isset($_GET['trashed'])) lfi_nct_app_flash((int) $_GET['trashed'] . ' enquête(s) mise(s) à la corbeille.');
    if (!empty($_GET['edited']))  lfi_nct_app_flash('✅ Enquête modifiée.');

    /* Filtres en chips */
    $filters = [
        'recontact' => '✓ Recontact OK (' . $with_recont . ')',
        'tel'       => '📞 Avec tél (' . $with_tel . ')',
        'email'     => '✉️ Avec email (' . $with_email . ')',
        'all'       => 'Tout (' . $total . ')',
    ];
    echo '<div class="lfi-app-filter-chips">';
    foreach ($filters as $k => $label) {
        $cls = $filter === $k ? 'on' : '';
        echo '<a class="fc ' . esc_attr($cls) . '" href="' . esc_url(lfi_nct_app_url('enquetes', ['f' => $k, 'sort' => $sort])) . '">' . esc_html($label) . '</a>';
    }
    echo '</div>';

    /* Tri en chips */
    $sorts = [
        'recent'   => '📅 Récent',
        'ancien'   => '📅 Ancien',
        'gravite'  => '⚠️ Gravité',
        'immeuble' => '📍 Immeuble',
        'nom'      => '🔤 Nom',
    ];
    echo '<div class="lfi-app-filter-chips" style="margin-top:-4px"><span style="align-self:center;font-size:.8em;color:#888;margin-right:2px">Trier :</span>';
    foreach ($sorts as $k => $label) {
        $cls = $sort === $k ? 'on' : '';
        echo '<a class="fc ' . esc_attr($cls) . '" href="' . esc_url(lfi_nct_app_url('enquetes', ['f' => $filter, 'sort' => $k])) . '">' . esc_html($label) . '</a>';
    }
    echo '</div>';

    /* Actions groupées */
    echo '<div class="lfi-app-bulk-row">';
    echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('enquetes-sms')) . '">📱 SMS en série (' . $with_tel . ')</a>';
    echo '<a class="btn-ghost"   href="' . esc_url(lfi_nct_app_url('enquetes-email')) . '">✉️ Email groupé (' . $with_email . ')</a>';
    if ($can_manage && $in_trash > 0) {
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('enquetes-corbeille')) . '">🗑 Corbeille (' . $in_trash . ')</a>';
    }
    echo '</div>';

    if (empty($rows)) {
        echo '<div class="lfi-app-empty">Aucune réponse pour ce filtre.</div>';
        lfi_nct_app_screen_close();
        return;
    }

    /* Tout est dans un formulaire pour la sélection multiple + suppression. */
    if ($can_manage) {
        echo '<form method="post" id="lfi-enq-form">';
        wp_nonce_field('lfi_app_enq_manage');
        echo '<input type="hidden" name="lfi_enq_manage" value="1">';
        echo '<input type="hidden" name="f" value="' . esc_attr($filter) . '">';
        echo '<input type="hidden" name="sort" value="' . esc_attr($sort) . '">';
    }

    echo '<ul class="lfi-app-list">';
    foreach ($rows as $r) {
        $name = trim(($r->contact_prenom ?? '') . ' ' . ($r->contact_nom ?? '')) ?: '(anonyme)';
        $ref  = function_exists('lfi_nct_response_ref')
            ? lfi_nct_response_ref($r->id, function_exists('lfi_nct_response_ga_of') ? lfi_nct_response_ga_of($r) : '')
            : '';
        echo '<li class="lfi-app-card">';
        echo '<div class="head"><div class="who">';
        if ($can_manage) echo '<input type="checkbox" name="ids[]" value="' . (int) $r->id . '" style="width:18px;height:18px;margin-right:8px;vertical-align:middle" aria-label="Sélectionner">';
        if ($ref) echo '<span style="display:inline-block;background:#c8102e;color:#fff;font-weight:700;font-size:.72em;padding:2px 7px;border-radius:6px;margin-right:6px;vertical-align:middle;letter-spacing:.5px">' . esc_html($ref) . '</span>';
        echo esc_html($name) . '</div>';
        echo '<div class="when">' . esc_html(wp_date('j M', strtotime($r->submitted_at))) . '</div>';
        echo '</div>';

        $adr = trim($r->adresse . ($r->etage ? ' · ét. ' . $r->etage : ''));
        if ($adr) echo '<div class="meta"><span class="meta-chip">📍 ' . esc_html($adr) . '</span></div>';

        /* Problème principal — synthèse + gravité */
        $problem = lfi_nct_app_enq_problem($r);
        if ($problem) {
            echo '<div class="lfi-app-problem">';
            echo '<div class="prob-head">Problème(s) signalé(s)';
            if ($problem['gravite']) {
                echo ' <span class="prob-grav g' . (int) $problem['gravite'] . '">gravité ' . (int) $problem['gravite'] . '/10</span>';
            }
            if ($problem['recurrent'] === 'permanent') echo ' <span class="prob-recur">en permanence</span>';
            echo '</div>';
            echo '<div class="prob-chips">';
            foreach ($problem['chips'] as $ch) {
                echo '<span class="prob-chip">' . $ch[0] . ' ' . esc_html($ch[1]) . '</span>';
            }
            echo '</div></div>';
        }

        echo '<div class="meta">';
        if (!empty($r->contact_tel)) {
            $tel_clean = preg_replace('/[^\d+]/', '', $r->contact_tel);
            echo '<a class="meta-chip" href="tel:' . esc_attr($tel_clean) . '">📞 ' . esc_html($r->contact_tel) . '</a>';
            echo '<a class="meta-chip act" href="sms:' . esc_attr($tel_clean) . '">📱 SMS</a>';
        }
        if (!empty($r->contact_email)) {
            echo '<a class="meta-chip" href="mailto:' . esc_attr($r->contact_email) . '">✉️ ' . esc_html($r->contact_email) . '</a>';
        }
        if ($r->contact_recontact) {
            echo '<span class="meta-chip" style="background:#e7f5ee;color:#186a3b">✓ Recontact OK</span>';
        }
        echo '</div>';

        /* Actions de gestion par enquête */
        if ($can_manage) {
            echo '<div class="row-actions" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">';
            echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('enquete-edit', ['id' => (int) $r->id])) . '">✏️ Éditer</a>';
            echo '<button type="submit" name="del_one" value="' . (int) $r->id . '" class="btn-ghost" onclick="return confirm(\'Mettre cette enquête à la corbeille ?\');">🗑 Supprimer</button>';
            echo '</div>';
        }
        echo '</li>';
    }
    echo '</ul>';

    if ($can_manage) {
        echo '<div class="lfi-app-bulk-row" style="position:sticky;bottom:8px">';
        echo '<button type="submit" name="bulk_del" value="1" class="btn-ghost" style="border-color:#c8102e;color:#c8102e" onclick="return confirm(\'Mettre les enquêtes cochées à la corbeille ?\');">🗑 Supprimer la sélection</button>';
        echo '</div>';
        echo '</form>';
    }

    lfi_nct_app_screen_close();
}

/* ---------- ✏️ Éditer une enquête (admin du GA) ---------- */
function lfi_nct_app_view_enquete_edit() {
    if (!lfi_nct_enq_can_manage()) { wp_safe_redirect(lfi_nct_app_url('enquetes')); exit; }
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $sc = function_exists('lfi_nct_responses_scope_clause') ? lfi_nct_responses_scope_clause() : '';
    $id  = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    /* Chargement borné au GA en vigueur (impossible d'éditer l'enquête d'un autre GA). */
    $row = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND deleted_at IS NULL" . $sc, $id)) : null;
    if (!$row) {
        lfi_nct_app_screen_open('✏️ Éditer une enquête');
        echo '<div class="lfi-app-empty">Enquête introuvable dans ce groupe d\'action.</div>';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('enquetes')) . '">← Retour</a>';
        lfi_nct_app_screen_close();
        return;
    }

    if (!empty($_POST['lfi_enq_edit_save']) && check_admin_referer('lfi_enq_edit_' . $id)) {
        $adresse = sanitize_text_field(wp_unslash($_POST['adresse'] ?? ''));
        if (function_exists('lfi_nct_normalize_address')) $adresse = lfi_nct_normalize_address($adresse);
        $etage = sanitize_text_field(wp_unslash($_POST['etage'] ?? ''));
        $cp = sanitize_text_field(wp_unslash($_POST['contact_prenom'] ?? ''));
        $cn = sanitize_text_field(wp_unslash($_POST['contact_nom'] ?? ''));
        $ct = sanitize_text_field(wp_unslash($_POST['contact_tel'] ?? ''));
        $ce = sanitize_email(wp_unslash($_POST['contact_email'] ?? ''));
        $recontact = !empty($_POST['contact_recontact']) ? 1 : 0;

        $data = json_decode((string) $row->data, true);
        if (!is_array($data)) $data = [];
        $presence = (isset($_POST['problemes_presence']) && in_array($_POST['problemes_presence'], ['oui', 'non'], true))
            ? $_POST['problemes_presence'] : ($data['problemes_presence'] ?? 'oui');
        $data['problemes_presence'] = $presence;
        $data['problemes_types']    = array_values(array_intersect(
            array_keys(lfi_nct_enq_problem_types()),
            array_map('sanitize_key', (array) ($_POST['problemes_types'] ?? []))
        ));
        $data['problemes_gravite']  = max(0, min(10, (int) ($_POST['problemes_gravite'] ?? 0)));
        $rec = sanitize_key($_POST['problemes_recurrent'] ?? '');
        if (in_array($rec, ['permanent', 'parfois', 'ponctuel', ''], true)) $data['problemes_recurrent'] = $rec;

        $addr_changed = (trim($adresse) !== trim((string) $row->adresse));
        $upd = [
            'adresse' => $adresse, 'etage' => $etage,
            'contact_prenom' => $cp, 'contact_nom' => $cn,
            'contact_tel' => $ct, 'contact_email' => $ce,
            'contact_recontact' => $recontact,
            'data' => wp_json_encode($data, JSON_UNESCAPED_UNICODE),
        ];
        /* Adresse changée → on efface les coordonnées pour un nouveau géocodage. */
        if ($addr_changed) { $upd['lat'] = null; $upd['lng'] = null; }
        $wpdb->update($table, $upd, ['id' => $id]);
        delete_transient('lfi_nct_known_addresses');
        wp_safe_redirect(lfi_nct_app_url('enquetes', ['edited' => 1]));
        exit;
    }

    $data = json_decode((string) $row->data, true);
    if (!is_array($data)) $data = [];
    $cur_types    = (array) ($data['problemes_types'] ?? []);
    $cur_presence = (string) ($data['problemes_presence'] ?? 'oui');
    $cur_grav     = (int) ($data['problemes_gravite'] ?? 0);
    $cur_rec      = (string) ($data['problemes_recurrent'] ?? '');
    $ref = function_exists('lfi_nct_response_ref') ? lfi_nct_response_ref($row->id, function_exists('lfi_nct_response_ga_of') ? lfi_nct_response_ga_of($row) : '') : '';

    lfi_nct_app_screen_open('✏️ Éditer' . ($ref ? ' — ' . $ref : ''), 'Corrige les informations de cette enquête');

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_enq_edit_' . $id);
    echo '<input type="hidden" name="lfi_enq_edit_save" value="1">';

    echo '<h3 style="margin:8px 0 4px">📍 Logement</h3>';
    echo '<label>Adresse<input type="text" name="adresse" value="' . esc_attr($row->adresse) . '" required></label>';
    echo '<label>Étage<input type="text" name="etage" value="' . esc_attr($row->etage) . '"></label>';

    echo '<h3 style="margin:14px 0 4px">👤 Contact</h3>';
    echo '<label>Prénom<input type="text" name="contact_prenom" value="' . esc_attr($row->contact_prenom) . '"></label>';
    echo '<label>Nom<input type="text" name="contact_nom" value="' . esc_attr($row->contact_nom) . '"></label>';
    echo '<label>Téléphone<input type="tel" name="contact_tel" value="' . esc_attr($row->contact_tel) . '"></label>';
    echo '<label>Email<input type="email" name="contact_email" value="' . esc_attr($row->contact_email) . '"></label>';
    echo '<label class="lfi-app-checkbox-row"><input type="checkbox" name="contact_recontact" value="1" ' . checked($row->contact_recontact, 1, false) . '> Accepte d\'être recontacté·e</label>';

    echo '<h3 style="margin:14px 0 4px">⚠️ Problèmes</h3>';
    echo '<label>Présence de problèmes<select name="problemes_presence">';
    echo '<option value="oui" ' . selected($cur_presence, 'oui', false) . '>Oui</option>';
    echo '<option value="non" ' . selected($cur_presence, 'non', false) . '>Non</option>';
    echo '</select></label>';
    echo '<div class="lfi-app-help" style="margin:6px 0"><small>Types de problème :</small></div>';
    echo '<div style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px">';
    foreach (lfi_nct_enq_problem_types() as $slug => $lab) {
        echo '<label class="lfi-app-checkbox-row"><input type="checkbox" name="problemes_types[]" value="' . esc_attr($slug) . '" ' . checked(in_array($slug, $cur_types, true), true, false) . '> ' . $lab[0] . ' ' . esc_html($lab[1]) . '</label>';
    }
    echo '</div>';
    echo '<label>Gravité (0 à 10)<input type="number" name="problemes_gravite" min="0" max="10" value="' . (int) $cur_grav . '"></label>';
    echo '<label>Récurrence<select name="problemes_recurrent">';
    foreach (['' => '—', 'permanent' => 'En permanence', 'parfois' => 'Régulièrement', 'ponctuel' => 'Ponctuel'] as $k => $lab) {
        echo '<option value="' . esc_attr($k) . '" ' . selected($cur_rec, $k, false) . '>' . esc_html($lab) . '</option>';
    }
    echo '</select></label>';

    echo '<div class="row-actions" style="margin-top:14px;display:flex;gap:8px">';
    echo '<button type="submit" class="btn-primary">💾 Enregistrer</button>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('enquetes')) . '">Annuler</a>';
    echo '</div>';
    echo '</form>';

    lfi_nct_app_screen_close();
}

/* ---------- 🗑 Corbeille des enquêtes (admin du GA) ---------- */
function lfi_nct_app_view_enquetes_corbeille() {
    if (!lfi_nct_enq_can_manage()) { wp_safe_redirect(lfi_nct_app_url('enquetes')); exit; }
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $sc = function_exists('lfi_nct_responses_scope_clause') ? lfi_nct_responses_scope_clause() : '';

    if (!empty($_POST['lfi_enq_trash']) && check_admin_referer('lfi_app_enq_trash')) {
        $rid = (int) ($_POST['id'] ?? 0);
        if ($rid && !empty($_POST['restore'])) {
            $wpdb->query($wpdb->prepare("UPDATE $table SET deleted_at = NULL WHERE id = %d AND deleted_at IS NOT NULL" . $sc, $rid));
            delete_transient('lfi_nct_known_addresses');
            wp_safe_redirect(lfi_nct_app_url('enquetes-corbeille', ['restored' => 1]));
            exit;
        }
        if ($rid && !empty($_POST['purge'])) {
            /* Suppression DÉFINITIVE, bornée au GA. */
            $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id = %d AND deleted_at IS NOT NULL" . $sc, $rid));
            wp_safe_redirect(lfi_nct_app_url('enquetes-corbeille', ['purged' => 1]));
            exit;
        }
    }

    $rows = $wpdb->get_results(
        "SELECT id, submitted_at, deleted_at, adresse, etage, contact_prenom, contact_nom, data, ga
         FROM $table WHERE deleted_at IS NOT NULL" . $sc . " ORDER BY deleted_at DESC LIMIT 300"
    ) ?: [];

    lfi_nct_app_screen_open('🗑 Corbeille des enquêtes', count($rows) . ' enquête(s) supprimée(s)');
    if (!empty($_GET['restored'])) lfi_nct_app_flash('♻️ Enquête restaurée.');
    if (!empty($_GET['purged']))   lfi_nct_app_flash('❌ Enquête supprimée définitivement.');
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('enquetes')) . '">← Retour aux enquêtes</a>';

    if (empty($rows)) {
        echo '<div class="lfi-app-empty">La corbeille est vide.</div>';
        lfi_nct_app_screen_close();
        return;
    }

    echo '<ul class="lfi-app-list" style="margin-top:10px">';
    foreach ($rows as $r) {
        $name = trim(($r->contact_prenom ?? '') . ' ' . ($r->contact_nom ?? '')) ?: '(anonyme)';
        $ref  = function_exists('lfi_nct_response_ref') ? lfi_nct_response_ref($r->id, function_exists('lfi_nct_response_ga_of') ? lfi_nct_response_ga_of($r) : '') : '';
        echo '<li class="lfi-app-card">';
        echo '<div class="head"><div class="who">';
        if ($ref) echo '<span style="display:inline-block;background:#999;color:#fff;font-weight:700;font-size:.72em;padding:2px 7px;border-radius:6px;margin-right:6px;vertical-align:middle;letter-spacing:.5px">' . esc_html($ref) . '</span>';
        echo esc_html($name) . '</div>';
        echo '<div class="when">supprimée ' . esc_html(wp_date('j M', strtotime($r->deleted_at))) . '</div>';
        echo '</div>';
        $adr = trim($r->adresse . ($r->etage ? ' · ét. ' . $r->etage : ''));
        if ($adr) echo '<div class="meta"><span class="meta-chip">📍 ' . esc_html($adr) . '</span></div>';
        echo '<div class="row-actions" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">';
        echo '<form method="post" style="display:inline">';
        wp_nonce_field('lfi_app_enq_trash');
        echo '<input type="hidden" name="lfi_enq_trash" value="1"><input type="hidden" name="id" value="' . (int) $r->id . '">';
        echo '<button type="submit" name="restore" value="1" class="btn-ghost">♻️ Restaurer</button> ';
        echo '<button type="submit" name="purge" value="1" class="btn-ghost" style="border-color:#c8102e;color:#c8102e" onclick="return confirm(\'Supprimer DÉFINITIVEMENT ? Cette action est irréversible.\');">❌ Supprimer définitivement</button>';
        echo '</form>';
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}

/* ---------- 📱 SMS en série aux répondant·es enquête ---------- */
function lfi_nct_app_view_enquetes_sms() {
    global $wpdb;
    $rep  = $wpdb->prefix . 'lfi_nct_responses';
    $logt = $wpdb->prefix . 'lfi_nct_sms_log';

    if (!empty($_POST['lfi_app_sms_sent']) && check_admin_referer('lfi_app_enq_sms_sent')) {
        $wpdb->insert($logt, [
            'template_id' => null,
            'membre_id'   => null,
            'tel'         => sanitize_text_field(wp_unslash($_POST['tel']  ?? '')),
            'body_sent'   => sanitize_textarea_field(wp_unslash($_POST['body'] ?? '')),
            'sent_by'     => get_current_user_id(),
        ]);
        $next = (int) ($_POST['next'] ?? 0);
        $mode = sanitize_key($_POST['mode'] ?? 'reunion');
        wp_safe_redirect(lfi_nct_app_url('enquetes-sms', ['i' => $next, 'mode' => $mode, 'logged' => 1]));
        exit;
    }

    $enq_scope = function_exists('lfi_nct_responses_scope_clause') ? lfi_nct_responses_scope_clause('militant_user_id') : '';
    $contacts = $wpdb->get_results(
        "SELECT id, contact_prenom, contact_nom, contact_tel, data
         FROM $rep
         WHERE deleted_at IS NULL AND contact_tel <> '' AND contact_tel IS NOT NULL
               AND contact_recontact = 1" . $enq_scope . "
         ORDER BY submitted_at DESC"
    ) ?: [];
    $n = count($contacts);

    $i    = isset($_GET['i'])    ? max(0, min(max(0, $n - 1), (int) $_GET['i'])) : 0;
    $mode = isset($_GET['mode']) ? sanitize_key($_GET['mode']) : 'reunion';

    /* Prochain événement (par défaut : la réunion 26 juin a son URL fixe) */
    $event_id = isset($_GET['event']) ? (int) $_GET['event'] : 0;
    if (!$event_id && function_exists('lfi_nct_sms_upcoming_events')) {
        $upc = lfi_nct_sms_upcoming_events(1);
        if ($upc) $event_id = $upc[0]->ID;
    }
    $event_post = $event_id ? get_post($event_id) : null;

    lfi_nct_app_screen_open('📱 SMS aux répondant·es', $n . ' personne(s) avec tél & recontact OK');

    if (!empty($_GET['logged'])) lfi_nct_app_flash('✅ SMS noté envoyé. Au suivant·e.');

    if (!$n) {
        echo '<div class="lfi-app-empty">Personne avec tél + consentement recontact.</div>';
        lfi_nct_app_screen_close();
        return;
    }

    $c = $contacts[$i];
    $msg = lfi_nct_app_enq_message_template($mode, $c, $event_post);
    $body = $msg['body'];
    $problem = lfi_nct_app_enq_problem($c);

    /* Onglets de modes */
    $modes = [
        'reunion'  => '📣 Réunion 26 juin',
        'event'    => '📅 Prochain événement',
        'probleme' => '🏠 Lié à leur problème',
        'libre'    => '✍️ Texte libre',
    ];
    echo '<div class="lfi-app-modes">';
    foreach ($modes as $k => $label) {
        $cls = $mode === $k ? 'on' : '';
        echo '<a class="md ' . esc_attr($cls) . '" href="' . esc_url(lfi_nct_app_url('enquetes-sms', ['i' => $i, 'mode' => $k])) . '">' . esc_html($label) . '</a>';
    }
    echo '</div>';

    /* Fiche contact + rappel du problème */
    echo '<div class="lfi-app-card sms-preview">';
    echo '<div class="head">';
    echo '<div class="who">' . esc_html(trim($c->contact_prenom . ' ' . $c->contact_nom)) . '</div>';
    echo '<div class="badge">' . ($i + 1) . ' / ' . $n . '</div>';
    echo '</div>';
    echo '<div class="meta"><span class="meta-chip">📞 ' . esc_html($c->contact_tel) . '</span></div>';

    if ($problem) {
        echo '<div class="lfi-app-problem inline">';
        echo '<div class="prob-head">📝 Pour mémoire — problème(s) signalé(s)';
        if ($problem['gravite']) echo ' <span class="prob-grav g' . (int) $problem['gravite'] . '">gravité ' . (int) $problem['gravite'] . '/10</span>';
        echo '</div>';
        echo '<div class="prob-chips">';
        foreach ($problem['chips'] as $ch) {
            echo '<span class="prob-chip">' . $ch[0] . ' ' . esc_html($ch[1]) . '</span>';
        }
        echo '</div></div>';
    }

    /* Textarea modifiable + lien sms: live */
    $tel_clean = preg_replace('/[^\d+]/', '', $c->contact_tel);
    echo '<label for="enq-sms-body" style="display:block;margin-top:8px;font-size:.85em;color:#555">Texte du SMS — modifiable</label>';
    echo '<textarea id="enq-sms-body" rows="7" class="sms-body" data-tel="' . esc_attr($tel_clean) . '">' . esc_textarea($body) . '</textarea>';
    echo '<div class="lfi-app-help"><small><span id="enq-sms-count">' . mb_strlen($body) . '</span> caractères</small></div>';

    echo '<div class="row-actions">';
    echo '<a id="enq-sms-link" class="btn-primary big" href="sms:' . esc_attr($tel_clean) . '">📲 Ouvrir mon SMS</a>';
    echo '<button type="button" class="btn-ghost" onclick="navigator.clipboard.writeText(document.getElementById(\'enq-sms-body\').value);this.textContent=\'✓ Copié\';">📋 Copier</button>';
    echo '</div>';

    echo '<form method="post" class="row-actions" id="enq-sms-mark">';
    wp_nonce_field('lfi_app_enq_sms_sent');
    echo '<input type="hidden" name="lfi_app_sms_sent" value="1">';
    echo '<input type="hidden" name="tel"  value="' . esc_attr($c->contact_tel) . '">';
    echo '<input type="hidden" name="body" id="enq-sms-body-h" value="' . esc_attr($body) . '">';
    echo '<input type="hidden" name="next" value="' . ($i + 1 < $n ? $i + 1 : $i) . '">';
    echo '<input type="hidden" name="mode" value="' . esc_attr($mode) . '">';
    echo '<button type="submit" class="btn-ghost">✅ Envoyé · passer au suivant →</button>';
    echo '</form>';

    echo '</div>';

    /* Pager */
    echo '<div class="lfi-app-pager">';
    if ($i > 0) echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('enquetes-sms', ['i' => $i - 1, 'mode' => $mode])) . '">← Précédent</a>';
    else        echo '<span class="btn-ghost dis">← Précédent</span>';
    if ($i + 1 < $n) echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('enquetes-sms', ['i' => $i + 1, 'mode' => $mode])) . '">Suivant →</a>';
    else             echo '<span class="btn-ghost dis">Fin de liste</span>';
    echo '</div>';

    /* JS : mise à jour live du lien sms: et compteur */
    ?>
    <script>
    (function () {
        var ta = document.getElementById('enq-sms-body');
        var lnk = document.getElementById('enq-sms-link');
        var cnt = document.getElementById('enq-sms-count');
        var hid = document.getElementById('enq-sms-body-h');
        if (!ta || !lnk) return;
        var tel = ta.dataset.tel || '';
        function refresh() {
            var b = ta.value || '';
            lnk.href = 'sms:' + tel + '?body=' + encodeURIComponent(b);
            if (cnt) cnt.textContent = b.length;
            if (hid) hid.value = b;
        }
        ta.addEventListener('input', refresh);
        refresh();
    })();
    </script>
    <?php

    lfi_nct_app_screen_close();
}

/* ---------- ✉️ Email groupé aux répondant·es enquête ---------- */
function lfi_nct_app_view_enquetes_email() {
    global $wpdb;
    $rep = $wpdb->prefix . 'lfi_nct_responses';

    if (!empty($_POST['lfi_app_enq_email_send']) && check_admin_referer('lfi_app_enq_email_send')) {
        $sujet         = sanitize_text_field(wp_unslash($_POST['sujet'] ?? ''));
        $body          = wp_kses_post(wp_unslash($_POST['body']  ?? ''));
        $include_event = !empty($_POST['include_event']);
        $personalize   = !empty($_POST['personalize']);
        $signature_key = sanitize_key($_POST['signature'] ?? 'collectif');
        if ($sujet && $body) {
            $enq_scope_s = function_exists('lfi_nct_responses_scope_clause') ? lfi_nct_responses_scope_clause('militant_user_id') : '';
            $recipients = $wpdb->get_results(
                "SELECT contact_prenom, contact_email, data
                 FROM $rep
                 WHERE deleted_at IS NULL
                       AND contact_email <> '' AND contact_email IS NOT NULL
                       AND contact_recontact = 1" . $enq_scope_s
            ) ?: [];

            $event_post = null;
            if ($include_event && function_exists('lfi_nct_sms_upcoming_events')) {
                $upc = lfi_nct_sms_upcoming_events(1);
                if ($upc) $event_post = $upc[0];
            }
            $event_html = '';
            if ($event_post) {
                $date  = get_post_meta($event_post->ID, '_ag_event_date',  true);
                $time  = get_post_meta($event_post->ID, '_ag_event_time',  true);
                $place = get_post_meta($event_post->ID, '_ag_event_place', true);
                $event_html  = '<div style="background:#fff3f5;border-left:4px solid #c8102e;padding:14px 18px;margin:18px 0;border-radius:4px">';
                $event_html .= '<div style="font-weight:700;color:#c8102e;margin-bottom:6px">📅 ' . esc_html(get_the_title($event_post)) . '</div>';
                if ($date)  $event_html .= '<div>🗓 ' . esc_html($date) . ($time ? ' · ' . esc_html($time) : '') . '</div>';
                if ($place) $event_html .= '<div>📍 ' . esc_html($place) . '</div>';
                $event_html .= '<div style="margin-top:8px"><a href="' . esc_url(get_permalink($event_post)) . '" style="background:#c8102e;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;display:inline-block;font-weight:700">✓ Je participe</a></div>';
                $event_html .= '</div>';
            }

            $sent = 0; $errs = 0;
            add_filter('wp_mail_content_type', function () { return 'text/html'; });
            add_filter('wp_mail_from_name', function() { return 'LFI Nantes Sud Clos Toreau'; });
            foreach ($recipients as $r) {
                if (!is_email($r->contact_email)) { $errs++; continue; }
                $body_resolved = $body;
                $sujet_resolved = $sujet;
                /* Remplace les variables */
                $vars = [
                    '{{prenom}}' => $r->contact_prenom ?: '',
                ];
                if ($personalize) {
                    $problem = lfi_nct_app_enq_problem($r);
                    $vars['{{probleme}}']      = lfi_nct_app_enq_phrase($problem);
                    $vars['{{probleme_main}}'] = $problem && !empty($problem['main']) ? $problem['main'][1] : '';
                }
                $body_resolved  = strtr($body_resolved,  $vars);
                $sujet_resolved = strtr($sujet_resolved, $vars);
                $html = function_exists('lfi_nct_email_wrap_html')
                    ? lfi_nct_email_wrap_html(
                        $r->contact_prenom,
                        wpautop($body_resolved) . $event_html,
                        '',
                        $signature_key,
                        'Vous recevez cet email car vous avez répondu à l\'enquête de voisinage logement et accepté d\'être recontacté·e par notre Groupe d\'Action.'
                    )
                    : '<div>' . wpautop($body_resolved) . $event_html . '</div>';
                if (wp_mail($r->contact_email, $sujet_resolved, $html)) $sent++; else $errs++;
            }
            remove_all_filters('wp_mail_content_type');
            remove_all_filters('wp_mail_from_name');
            wp_safe_redirect(lfi_nct_app_url('enquetes-email', ['sent' => $sent, 'errs' => $errs]));
            exit;
        }
    }

    $n = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $rep
         WHERE deleted_at IS NULL
               AND contact_email <> '' AND contact_email IS NOT NULL
               AND contact_recontact = 1" . (function_exists('lfi_nct_responses_scope_clause') ? lfi_nct_responses_scope_clause('militant_user_id') : '')
    );

    /* Mode de pré-remplissage */
    $mode = isset($_GET['mode']) ? sanitize_key($_GET['mode']) : 'libre';
    $event_post = null;
    if (function_exists('lfi_nct_sms_upcoming_events')) {
        $upc = lfi_nct_sms_upcoming_events(1);
        if ($upc) $event_post = $upc[0];
    }

    /* On utilise une « fiche fictive » pour pré-remplir : prénom = {{prenom}}, pas de problème
       car le problème est résolu par destinataire au moment de l'envoi (cocher Personnaliser). */
    $stub_row = (object) ['contact_prenom' => '{{prenom}}', 'data' => null];
    $msg = lfi_nct_app_enq_message_template($mode, $stub_row, $event_post);

    /* Pour le mode « probleme », montre la variable {{probleme}} comme placeholder */
    if ($mode === 'probleme') {
        $msg['body'] = "Bonjour {{prenom}}, suite à votre réponse à notre enquête de voisinage où vous nous avez signalé {{probleme}}, on continue le travail collectif sur le logement au Clos Toreau."
                     . ($event_post
                         ? " On en parle " . trim(get_post_meta($event_post->ID, '_ag_event_date', true) . ' à ' . get_post_meta($event_post->ID, '_ag_event_time', true))
                            . " à " . get_post_meta($event_post->ID, '_ag_event_place', true)
                            . ". Inscription : " . get_permalink($event_post)
                         : " Prochaine réunion publique vendredi 26 juin à 15h, salle Confluences. Infos : " . lfi_nct_page_url('reunion-26-juin-2026'));
        $msg['sujet'] = "Suivi de votre enquête logement — {{probleme_main}}";
    }

    lfi_nct_app_screen_open('✉️ Email aux répondant·es', $n . ' email(s) consentent au recontact');

    if (isset($_GET['sent'])) {
        $s = (int) $_GET['sent']; $e = (int) ($_GET['errs'] ?? 0);
        lfi_nct_app_flash("✅ {$s} envoyé(s)" . ($e ? " · ⚠️ {$e} échec(s)" : ''));
    }

    /* Onglets de modes */
    $modes = [
        'reunion'  => '📣 Réunion 26 juin',
        'event'    => '📅 Prochain événement',
        'probleme' => '🏠 Lié à leur problème',
        'libre'    => '✍️ Texte libre',
    ];
    echo '<div class="lfi-app-modes">';
    foreach ($modes as $k => $label) {
        $cls = $mode === $k ? 'on' : '';
        echo '<a class="md ' . esc_attr($cls) . '" href="' . esc_url(lfi_nct_app_url('enquetes-email', ['mode' => $k])) . '">' . esc_html($label) . '</a>';
    }
    echo '</div>';

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_enq_email_send');
    echo '<input type="hidden" name="lfi_app_enq_email_send" value="1">';
    echo '<label>Sujet<input type="text" name="sujet" required value="' . esc_attr($msg['sujet']) . '" placeholder="Ex : Réunion publique sur le logement le 26 juin"></label>';
    echo '<label>Message<textarea name="body" rows="10" required>' . esc_textarea($msg['body']) . '</textarea></label>';
    /* Sélecteur de signature */
    if (function_exists('lfi_nct_signatures')) {
        $sigs = lfi_nct_signatures();
        echo '<label>✍️ Signataire<select name="signature">';
        foreach ($sigs as $k => $s) {
            echo '<option value="' . esc_attr($k) . '" ' . selected('collectif', $k, false) . '>' . esc_html($s['nom']) . ($s['role'] ? ' — ' . esc_html($s['role']) : '') . '</option>';
        }
        echo '</select></label>';
        echo '<div class="lfi-app-help"><small>Gère les signataires : <a href="' . esc_url(lfi_nct_app_url('signatures')) . '">✍️ Signatures</a></small></div>';
    }
    echo '<div class="lfi-app-help">Variables disponibles : <code>{{prenom}}</code>';
    if ($mode === 'probleme') echo ' · <code>{{probleme}}</code> (texte complet) · <code>{{probleme_main}}</code> (principal)';
    echo '</div>';
    if ($mode === 'probleme') {
        echo '<label class="lfi-app-checkbox-row"><input type="checkbox" name="personalize" value="1" checked> Personnaliser pour chaque destinataire (insère son problème individuel à la place de <code>{{probleme}}</code>)</label>';
    } else {
        echo '<label class="lfi-app-checkbox-row"><input type="checkbox" name="personalize" value="1"> Personnaliser par destinataire (insère leur problème individuel si <code>{{probleme}}</code> est dans le texte)</label>';
    }
    if ($event_post) {
        echo '<label class="lfi-app-checkbox-row"><input type="checkbox" name="include_event" value="1" ' . checked(in_array($mode, ['reunion','event','probleme'], true), true, false) . '> Inclure le bloc événement « ' . esc_html(get_the_title($event_post)) . ' » avec bouton « ✓ Je participe »</label>';
    }
    echo '<div class="lfi-app-help">⚠️ Envoyé à <strong>' . $n . '</strong> personne(s). Seuls celles ayant coché « j\'accepte d\'être recontacté·e » sont incluses (RGPD).</div>';
    echo '<button type="submit" class="btn-primary big" onclick="return confirm(\'Envoyer à ' . $n . ' répondant·es ?\');">📤 Envoyer maintenant</button>';
    echo '</form>';

    lfi_nct_app_screen_close();
}

/* ---------- 📊 Statistiques ---------- */
function lfi_nct_app_view_stats() {
    global $wpdb;
    $s = lfi_nct_app_quick_stats();
    $sms_sent   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lfi_nct_sms_log");
    $emails_sent = (int) $wpdb->get_var("SELECT COALESCE(SUM(recipients_count),0) FROM {$wpdb->prefix}lfi_nct_email_campaigns");
    $cards = [
        ['📣', $s['reunion'], 'Inscrits 26 juin'],
        ['🏠', $s['surveys'], 'Enquêtes logement'],
        ['📅', $s['events'],  'Événements'],
        ['👥', $s['membres'], 'Membres actifs'],
        ['📱', $sms_sent,     'SMS envoyés (logs)'],
        ['✉️', $emails_sent,  'Emails envoyés'],
    ];
    lfi_nct_app_screen_open('📊 Statistiques', 'État du GA en chiffres');
    echo '<div class="lfi-app-stats-grid">';
    foreach ($cards as $c) {
        echo '<div class="stat"><div class="ico">' . $c[0] . '</div><div class="n">' . (int) $c[1] . '</div><div class="l">' . esc_html($c[2]) . '</div></div>';
    }
    echo '</div>';
    lfi_nct_app_screen_close();
}

/* ---------- 🔥 Purger le cache ---------- */
function lfi_nct_app_view_cache() {
    if (!empty($_POST['lfi_app_cache_purge']) && check_admin_referer('lfi_app_cache_purge')) {
        do_action('litespeed_purge_all');
        if (function_exists('wp_cache_flush'))      wp_cache_flush();
        if (function_exists('opcache_reset'))       opcache_reset();
        if (function_exists('w3tc_flush_all'))      w3tc_flush_all();
        if (function_exists('rocket_clean_domain')) rocket_clean_domain();
        wp_safe_redirect(lfi_nct_app_url('cache', ['purged' => 1]));
        exit;
    }
    lfi_nct_app_screen_open('🔥 Purger les caches', 'Force la mise à jour du site');
    if (!empty($_GET['purged'])) lfi_nct_app_flash('✅ Tous les caches ont été vidés. Le site est rechargé en frais.');
    echo '<div class="lfi-app-help">Utile si tu viens de déployer une mise à jour et que tu ne la vois pas tout de suite sur le site public. Purge LiteSpeed Cache, l\'opcache PHP, et les caches WordPress.</div>';
    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_cache_purge');
    echo '<input type="hidden" name="lfi_app_cache_purge" value="1">';
    echo '<button type="submit" class="btn-primary big">🔥 Tout purger maintenant</button>';
    echo '</form>';
    lfi_nct_app_screen_close();
}
