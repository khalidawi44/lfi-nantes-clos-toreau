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
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    do_action('litespeed_control_set_nocache', 'LFI App : page dynamique');
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
    $tiles = lfi_nct_admin_get_tiles($stats);
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
    /* Query-var direct = marche même si les rewrite rules ne sont pas flushées */
    $sw = esc_url(home_url('/?lfi_app=sw&v=' . LFI_NCT_VERSION));
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

/* ============================================================== *
 *  Tuiles d'admin partagées : utilisées par /app/ ET la barre     *
 *  flottante en haut de la home pour les admins connectés.        *
 * ============================================================== */
function lfi_nct_admin_get_tiles($stats = null) {
    if ($stats === null) $stats = lfi_nct_app_quick_stats();
    return [
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
        ['🚪', 'Se déconnecter',            'Quitter la console',                  wp_logout_url(home_url('/'))],
    ];
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
