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

/* ============================================================== *
 *  Page WordPress /app/                                            *
 * ============================================================== */
add_action('init', 'lfi_nct_app_create_page', 30);
function lfi_nct_app_create_page() {
    if (get_option('lfi_nct_app_page_created') === 'done') return;
    if (get_page_by_path(LFI_NCT_APP_SLUG)) {
        update_option('lfi_nct_app_page_created', 'done', false);
        return;
    }
    wp_insert_post([
        'post_title'    => 'App du GA',
        'post_name'     => LFI_NCT_APP_SLUG,
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_content'  => '[lfi_nct_app]',
        'post_author'   => 1,
        'comment_status'=> 'closed',
        'ping_status'   => 'closed',
    ]);
    update_option('lfi_nct_app_page_created', 'done', false);
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
add_filter('query_vars', function ($v) { $v[] = 'lfi_app'; $v[] = 'size'; return $v; });

add_action('template_redirect', 'lfi_nct_app_serve_endpoints');
function lfi_nct_app_serve_endpoints() {
    $ep = get_query_var('lfi_app');
    if (!$ep) return;

    if ($ep === 'manifest') {
        nocache_headers();
        header('Content-Type: application/manifest+json; charset=utf-8');
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
                ['src' => home_url('/lfi-app-icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => home_url('/lfi-app-icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
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
        nocache_headers();
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        lfi_nct_app_render_icon($size);
        exit;
    }
}

function lfi_nct_app_render_icon($size) {
    if (!function_exists('imagecreatetruecolor')) {
        /* PNG 1×1 rouge de secours */
        echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        return;
    }
    $im = imagecreatetruecolor($size, $size);
    $rouge = imagecolorallocate($im, 200, 16, 46);
    $blanc = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, $size, $size, $rouge);

    /* Texte "LFI" gros au centre */
    $font_path = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
    if (function_exists('imagettftext') && file_exists($font_path)) {
        $fs = (int) ($size * 0.42);
        $bbox = imagettfbbox($fs, 0, $font_path, 'LFI');
        $tw = $bbox[2] - $bbox[0];
        $th = $bbox[1] - $bbox[7];
        $x = (int) (($size - $tw) / 2) - $bbox[0];
        $y = (int) (($size + $th) / 2) - 4;
        imagettftext($im, $fs, 0, $x, $y, $blanc, $font_path, 'LFI');

        /* Sous-titre "GA" plus petit */
        $fs2 = (int) ($size * 0.14);
        $bbox2 = imagettfbbox($fs2, 0, $font_path, 'GA');
        $tw2 = $bbox2[2] - $bbox2[0];
        $x2 = (int) (($size - $tw2) / 2) - $bbox2[0];
        imagettftext($im, $fs2, 0, $x2, (int) ($size * 0.82), $blanc, $font_path, 'GA');
    } else {
        /* fallback bitmap font */
        $cw = imagefontwidth(5);
        $ch = imagefontheight(5);
        imagestring($im, 5, (int) (($size - $cw * 3) / 2), (int) (($size - $ch) / 2), 'LFI', $blanc);
    }

    imagepng($im);
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
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    do_action('litespeed_control_set_nocache', 'LFI App : page dynamique');
}

add_action('wp_head', 'lfi_nct_app_head_meta', 1);
function lfi_nct_app_head_meta() {
    global $post;
    if (!is_a($post, 'WP_Post') || $post->post_name !== LFI_NCT_APP_SLUG) return;
    $manifest = esc_url(home_url('/lfi-app-manifest.json'));
    $icon192  = esc_url(home_url('/lfi-app-icon-192.png'));
    ?>
    <link rel="manifest" href="<?php echo $manifest; ?>">
    <meta name="theme-color" content="#c8102e">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GA LFI">
    <link rel="apple-touch-icon" sizes="192x192" href="<?php echo $icon192; ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $icon192; ?>">
    <meta name="robots" content="noindex,nofollow,noarchive,nosnippet">
    <?php
}

/* ============================================================== *
 *  Shortcode [lfi_nct_app] — coquille de la PWA                   *
 * ============================================================== */
add_shortcode('lfi_nct_app', 'lfi_nct_app_shortcode');
function lfi_nct_app_shortcode() {
    ob_start();

    if (!is_user_logged_in()) {
        lfi_nct_app_render_login();
    } else {
        lfi_nct_app_render_dashboard();
    }

    lfi_nct_app_render_styles();
    lfi_nct_app_render_register_sw();

    return ob_get_clean();
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
                <input type="text" name="log" autocomplete="username" required>
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
        </form>

        <div class="lfi-app-install-hint">
            <strong>📱 Installer l'app sur le téléphone</strong>
            <div class="ios">iPhone : Safari → bouton « Partager » → <strong>Sur l'écran d'accueil</strong></div>
            <div class="android">Android : Chrome → menu ⋮ → <strong>Installer l'application</strong></div>
        </div>
    </div>
    <?php
}

function lfi_nct_app_render_dashboard() {
    if (!current_user_can('manage_options')) {
        echo '<div class="lfi-app"><div class="lfi-app-error">Cette console est réservée aux administrateurs du GA. <a href="' . esc_url(wp_logout_url(home_url('/' . LFI_NCT_APP_SLUG . '/'))) . '">Se déconnecter</a>.</div></div>';
        return;
    }

    $user = wp_get_current_user();
    $stats = lfi_nct_app_quick_stats();

    /* Définition des tuiles : [icône, titre, sous-titre, URL] */
    $tiles = [
        ['📣', 'Inscrits réunion 26 juin', $stats['reunion'] . ' inscription(s)', admin_url('admin.php?page=lfi-nct-reunion-rsvp')],
        ['🏠', 'Enquêtes logement',         $stats['surveys'] . ' réponse(s)',     admin_url('admin.php?page=lfi-nct-responses')],
        ['📅', 'Événements',                $stats['events']  . ' événement(s)',   admin_url('admin.php?page=lfi-nct-event-rsvp')],
        ['👥', 'Adhérents',                 $stats['membres'] . ' adhérent(s)',    admin_url('admin.php?page=lfi-nct-membres')],
        ['📱', 'Envoyer SMS',               'Diffusion ciblée',                    admin_url('admin.php?page=lfi-nct-sms')],
        ['✉️', 'Email blast',               'Campagne mail',                       admin_url('admin.php?page=lfi-nct-email')],
        ['📊', 'Statistiques',              'Vue d\'ensemble',                     admin_url('admin.php?page=lfi-nct-stats')],
        ['📰', 'Articles',                  'Actus du GA',                         admin_url('edit.php')],
        ['📍', 'Carte / RDV',               'Demandes en cours',                   admin_url('admin.php?page=lfi-nct-rdv')],
        ['🔥', 'Purger le cache',           'Forcer la maj',                       admin_url('admin.php?page=lfi-nct-maintenance')],
        ['📝', 'Pages',                     'Édition rapide',                      admin_url('edit.php?post_type=page')],
        ['🚪', 'Se déconnecter',            'Quitter la console',                  wp_logout_url(home_url('/' . LFI_NCT_APP_SLUG . '/'))],
    ];
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
            <div class="q"><span class="n"><?php echo (int) $stats['reunion']; ?></span><span class="l">Inscrits 26 juin</span></div>
            <div class="q"><span class="n"><?php echo (int) $stats['surveys']; ?></span><span class="l">Enquêtes</span></div>
            <div class="q"><span class="n"><?php echo (int) $stats['membres']; ?></span><span class="l">Adhérents</span></div>
        </div>

        <div class="lfi-app-grid">
            <?php foreach ($tiles as $t): ?>
                <a class="lfi-app-tile" href="<?php echo esc_url($t[3]); ?>">
                    <div class="ico"><?php echo $t[0]; ?></div>
                    <div class="tit"><?php echo esc_html($t[1]); ?></div>
                    <div class="sub"><?php echo esc_html($t[2]); ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="lfi-app-foot">
            <div>LFI Nantes Sud Clos Toreau · v<?php echo esc_html(LFI_NCT_VERSION); ?></div>
            <div class="lfi-app-install-hint mini">📱 Ajoute cette page à l'écran d'accueil pour l'avoir comme une app</div>
        </div>
    </div>
    <?php
}

function lfi_nct_app_quick_stats() {
    global $wpdb;
    $reunion = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lfi_nct_reunion_rsvp");
    $surveys = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lfi_nct_responses");
    $membres = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lfi_nct_membres");
    $events  = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN (%s, %s)",
        'ag_evenement', 'lfi_evenement'
    ));
    return [
        'reunion' => max(0, $reunion),
        'surveys' => max(0, $surveys),
        'membres' => max(0, $membres),
        'events'  => max(0, $events),
    ];
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
    </style>
    <?php
}

function lfi_nct_app_render_register_sw() {
    $sw = esc_url(home_url('/lfi-app-sw.js'));
    ?>
    <script>
    (function () {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('<?php echo $sw; ?>', { scope: '/' })
                    .catch(function (err) { console.warn('SW register failed', err); });
            });
        }
        /* Marqueur body pour le CSS standalone */
        document.body && document.body.classList.add('page-app');
    })();
    </script>
    <?php
}
