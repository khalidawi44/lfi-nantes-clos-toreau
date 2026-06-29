<?php
/**
 * Barre de navigation persistante "Mon espace" sur le site
 *
 * Apparaît sur TOUTES les pages publiques pour les utilisateurs
 * connectés (hors /app/ qui a sa propre navbar interne). Donne accès
 * aux mêmes outils que l'application — locataire / GA / admin —
 * directement depuis n'importe quelle page du site.
 *
 * - Sticky en haut
 * - Adaptative selon le rôle (locataire, GA, admin)
 * - Mobile : icônes + scroll horizontal
 * - Desktop : icônes + labels + menu « Plus » overflow
 */
if (!defined('ABSPATH')) exit;

add_action('wp_body_open', 'lfi_nct_render_site_navbar', 5);

function lfi_nct_render_site_navbar() {
    if (!is_user_logged_in()) return;
    if (is_admin()) return;

    /* Ne pas afficher sur les pages qui ont déjà leur propre interface :
       l'app, la page mon-compte, le formulaire d'enquête. Sinon ça double. */
    global $post;
    if (is_singular() && is_a($post, 'WP_Post')) {
        if (has_shortcode($post->post_content, 'lfi_nct_app')) return;
        if (has_shortcode($post->post_content, 'lfi_nct_survey')) return;
        if (has_shortcode($post->post_content, 'lfi_nct_compte')) return;
        if ($post->post_name === 'mon-compte') return;
    }

    $u = wp_get_current_user();
    $is_admin  = current_user_can('manage_options');
    $is_ga     = function_exists('lfi_nct_user_role_ga')     && lfi_nct_user_role_ga();
    $is_tenant = function_exists('lfi_nct_user_role_tenant') && lfi_nct_user_role_tenant();
    if (!$is_admin && !$is_ga && !$is_tenant) return;

    $app_root = function_exists('lfi_nct_app_url') ? lfi_nct_app_url('') : home_url('/app/');

    /* === LOCATAIRE PUR (sans rôle admin/GA) === */
    if ($is_tenant && !$is_admin && !$is_ga) {
        $primary = [
            ['🏠', 'Mon espace',    $app_root],
            ['📋', 'Mon enquête',   lfi_nct_app_url('mon-enquete')],
            ['📅', 'Mes RDV',       lfi_nct_app_url('mes-rdv')],
            ['📲', 'Installer',     lfi_nct_app_url('installer')],
        ];
        $secondary = [
            ['✏️', 'Mon profil',     lfi_nct_app_url('mon-profil')],
            ['🔔', 'Notifications', lfi_nct_app_url('notifs')],
            ['🚪', 'Se déconnecter', wp_logout_url(home_url('/'))],
        ];
    }
    /* === GA / ADMIN === */
    else {
        $primary = [
            ['🏠', 'Tableau de bord', $app_root],
            ['🔧', 'Brigade',         lfi_nct_app_url('interventions')],
            ['📁', 'Dossiers',        lfi_nct_app_url('dossiers-juridiques')],
            ['⚖️', 'Recouvrement',    lfi_nct_app_url('recouvrements')],
            ['🛠', 'Tutos',           lfi_nct_app_url('tutoriels')],
        ];
        $secondary = [
            ['🔬', 'Outils scientifiques', lfi_nct_app_url('outils')],
            ['📅', 'Mon agenda',           lfi_nct_app_url('agenda')],
            ['📋', 'Faire passer enquête', home_url('/enquete-logement-clos-toreau/')],
        ];
        if ($is_admin) {
            $secondary[] = ['🗂', 'Réponses enquêtes', lfi_nct_app_url('dossiers')];
            $secondary[] = ['📈', 'Stats enquêtes',    lfi_nct_app_url('stats-enquete')];
            $secondary[] = ['👥', 'Comptes GA + loc.', lfi_nct_app_url('membres')];
        }
        if ($is_ga) {
            $secondary[] = ['👥', 'Adhérents',  lfi_nct_app_url('membres')];
            $secondary[] = ['📣', 'Événements', lfi_nct_app_url('evenements')];
            $secondary[] = ['📱', 'SMS aux adhérents', lfi_nct_app_url('sms')];
            $secondary[] = ['✉️', 'Email aux adhérents', lfi_nct_app_url('email')];
        }
        $secondary[] = ['⚙️', 'Mes paramètres', lfi_nct_app_url('facturation-params')];
        $secondary[] = ['📲', 'Installer l\'app', lfi_nct_app_url('installer')];
        $secondary[] = ['✏️', 'Mon profil',       lfi_nct_app_url('mon-profil')];
        $secondary[] = ['🚪', 'Se déconnecter',   wp_logout_url(home_url('/'))];
    }

    $hi_name = esc_html($u->display_name ?: $u->user_login);
    ?>
    <style>
    .lfi-site-navbar {
        position: sticky; top: 0; z-index: 9999;
        background: linear-gradient(135deg, #c8102e, #a30b25);
        color: #fff;
        padding: 6px 10px;
        display: flex; align-items: center; gap: 6px;
        box-shadow: 0 2px 8px rgba(0,0,0,.18);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 14px;
    }
    .lfi-site-navbar-logo {
        background: #fff; color: #c8102e;
        width: 30px; height: 30px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 900; font-size: 16px; flex-shrink: 0;
        text-decoration: none;
    }
    .lfi-site-navbar-items {
        display: flex; gap: 4px; align-items: center;
        overflow-x: auto; -webkit-overflow-scrolling: touch;
        flex: 1; min-width: 0;
        scrollbar-width: none;
    }
    .lfi-site-navbar-items::-webkit-scrollbar { display: none; }
    .lfi-site-navbar-items a {
        color: #fff !important; text-decoration: none !important;
        padding: 6px 10px; border-radius: 6px;
        display: inline-flex; align-items: center; gap: 5px;
        font-weight: 700; white-space: nowrap;
        transition: background .15s;
        font-size: 13.5px;
    }
    .lfi-site-navbar-items a:hover, .lfi-site-navbar-items a:focus {
        background: rgba(255,255,255,.18); color: #fff !important;
    }
    .lfi-site-navbar-items a .ico { font-size: 16px; }
    .lfi-site-navbar-more {
        position: relative; flex-shrink: 0;
    }
    .lfi-site-navbar-more-btn {
        background: rgba(255,255,255,.15); color: #fff; border: 0;
        padding: 6px 10px; border-radius: 6px; cursor: pointer;
        font-weight: 700; font-size: 18px; line-height: 1;
    }
    .lfi-site-navbar-more-btn:hover { background: rgba(255,255,255,.28); }
    .lfi-site-navbar-menu {
        position: absolute; top: calc(100% + 6px); right: 0;
        background: #fff; color: #1a1a1a; border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,.18);
        min-width: 240px; padding: 6px;
        display: none;
        max-height: 70vh; overflow-y: auto;
    }
    .lfi-site-navbar-menu.open { display: block; }
    .lfi-site-navbar-menu .lfi-site-navbar-hi {
        padding: 8px 12px; font-size: .85em; color: #666; border-bottom: 1px solid #eee; margin-bottom: 4px;
    }
    .lfi-site-navbar-menu a {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 12px; color: #1a1a1a !important; text-decoration: none !important;
        border-radius: 6px; font-weight: 600; font-size: 14px;
    }
    .lfi-site-navbar-menu a:hover { background: #fdf5f6; color: #c8102e !important; }
    .lfi-site-navbar-menu a .ico { font-size: 18px; width: 22px; text-align: center; }
    @media (max-width: 700px) {
        .lfi-site-navbar { padding: 5px 8px; gap: 4px; }
        .lfi-site-navbar-items a .lbl { display: none; }
        .lfi-site-navbar-items a { padding: 6px 8px; }
        .lfi-site-navbar-items a .ico { font-size: 18px; }
    }
    @media print {
        .lfi-site-navbar { display: none !important; }
    }
    </style>

    <div class="lfi-site-navbar" role="navigation" aria-label="Mon espace LFI">
        <a class="lfi-site-navbar-logo" href="<?php echo esc_url($app_root); ?>" title="Mon espace">Φ</a>
        <div class="lfi-site-navbar-items">
            <?php foreach ($primary as $p): ?>
                <a href="<?php echo esc_url($p[2]); ?>">
                    <span class="ico"><?php echo $p[0]; ?></span>
                    <span class="lbl"><?php echo esc_html($p[1]); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="lfi-site-navbar-more">
            <button type="button" class="lfi-site-navbar-more-btn" onclick="this.nextElementSibling.classList.toggle('open')" aria-label="Plus d'options" aria-haspopup="true">⋮</button>
            <div class="lfi-site-navbar-menu" role="menu">
                <div class="lfi-site-navbar-hi">👤 <?php echo $hi_name; ?></div>
                <?php foreach ($secondary as $s): ?>
                    <a href="<?php echo esc_url($s[2]); ?>" role="menuitem">
                        <span class="ico"><?php echo $s[0]; ?></span>
                        <span><?php echo esc_html($s[1]); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
    (function () {
        /* Ferme le menu quand on clique ailleurs */
        document.addEventListener('click', function (e) {
            var menu = document.querySelector('.lfi-site-navbar-menu');
            var btn  = document.querySelector('.lfi-site-navbar-more-btn');
            if (!menu || !btn) return;
            if (!menu.contains(e.target) && e.target !== btn) {
                menu.classList.remove('open');
            }
        });
    })();
    </script>
    <?php
}

/* ============================================================== *
 *  MENU « 🏛 LFI Clos Toreau » dans la barre WP admin              *
 *                                                                  *
 *  Visible :                                                       *
 *   - dans /wp-admin/ pour les admins (la barre noire est active)  *
 *   - sur le front pour les admins (la barre noire est visible)    *
 *   - NB : pour les GA et locataires, la barre admin est masquée   *
 *     par app-roles.php → ils utilisent la navbar rouge frontend.  *
 *                                                                  *
 *  Permet d'accéder à toutes les vues de l'app en un clic depuis   *
 *  n'importe quelle page de wp-admin (édition d'un article, etc.). *
 * ============================================================== */
add_action('admin_bar_menu', 'lfi_nct_admin_bar_menu', 30);

function lfi_nct_admin_bar_menu($bar) {
    if (!is_user_logged_in()) return;
    if (!function_exists('lfi_nct_app_url')) return;

    $is_admin  = current_user_can('manage_options');
    $is_ga     = function_exists('lfi_nct_user_role_ga')     && lfi_nct_user_role_ga();
    $is_tenant = function_exists('lfi_nct_user_role_tenant') && lfi_nct_user_role_tenant();
    if (!$is_admin && !$is_ga && !$is_tenant) return;

    $app_root = lfi_nct_app_url('');

    /* Nœud parent — bouton rouge bien visible */
    $bar->add_node([
        'id'    => 'lfi-nct',
        'title' => '<span style="display:inline-flex;align-items:center;gap:6px"><span style="font-size:15px">🏛</span><span style="font-weight:700">LFI Clos Toreau</span></span>',
        'href'  => $app_root,
        'meta'  => [
            'title' => 'Tous les outils LFI Clos Toreau',
            'class' => 'lfi-nct-bar-root',
        ],
    ]);

    /* Construit la liste des items selon le rôle */
    if ($is_tenant && !$is_admin && !$is_ga) {
        $sections = [
            ['📲 Mon espace', [
                ['🏠 Mon tableau de bord',  $app_root],
                ['📋 Mon enquête',          lfi_nct_app_url('mon-enquete')],
                ['📅 Mes RDV',              lfi_nct_app_url('mes-rdv')],
                ['✏️ Mon profil',           lfi_nct_app_url('mon-profil')],
                ['📲 Installer l\'app',     lfi_nct_app_url('installer')],
            ]],
        ];
    } else {
        $sections = [
            ['🔧 Brigade travaux', [
                ['🏠 Tableau de bord',       $app_root],
                ['🔧 Mes interventions',     lfi_nct_app_url('interventions')],
                ['＋ Nouvelle intervention', lfi_nct_app_url('intervention-add')],
                ['📁 Dossiers juridiques',   lfi_nct_app_url('dossiers-juridiques')],
                ['＋ Nouveau dossier',       lfi_nct_app_url('dossier-juridique-add')],
                ['⚖️ Recouvrement NMH',      lfi_nct_app_url('recouvrements')],
                ['🛠 Tutoriels',             lfi_nct_app_url('tutoriels')],
                ['🔬 Outils scientifiques',  lfi_nct_app_url('outils')],
                ['📅 Mon agenda',            lfi_nct_app_url('agenda')],
                ['⚙️ Mes paramètres',        lfi_nct_app_url('facturation-params')],
            ]],
        ];

        if ($is_admin || $is_ga) {
            $sections[] = ['📣 Action politique', [
                ['📋 Faire passer une enquête', home_url('/enquete-logement-clos-toreau/')],
                ['📅 Événements',               lfi_nct_app_url('evenements')],
                ['👥 Adhérents',                lfi_nct_app_url('membres')],
                ['📱 SMS aux adhérents',        lfi_nct_app_url('sms')],
                ['✉️ Email aux adhérents',       lfi_nct_app_url('email')],
            ]];
        }

        if ($is_admin) {
            $sections[] = ['👁 Admin (RGPD)', [
                ['🗂 Réponses d\'enquête',  lfi_nct_app_url('dossiers')],
                ['📈 Stats enquêtes',      lfi_nct_app_url('stats-enquete')],
                ['📊 Stats globales',      lfi_nct_app_url('stats')],
                ['🗺 Carte',               lfi_nct_app_url('carte')],
                ['👤 Aperçu locataire/GA', lfi_nct_app_url('preview')],
                ['🔥 Purger le cache',     lfi_nct_app_url('cache')],
            ]];
        }
    }

    /* Insère chaque section avec un en-tête non cliquable */
    foreach ($sections as $si => $section) {
        list($section_title, $items) = $section;
        $section_id = 'lfi-nct-section-' . $si;

        /* En-tête de section : titre en gras, désactivé (pas de href) */
        $bar->add_node([
            'id'     => $section_id,
            'parent' => 'lfi-nct',
            'title'  => '<span style="font-weight:800;color:#ff8a8a;text-transform:uppercase;letter-spacing:.5px;font-size:11px">' . esc_html($section_title) . '</span>',
            'meta'   => ['class' => 'lfi-nct-section-header'],
        ]);

        foreach ($items as $ii => $item) {
            $bar->add_node([
                'id'     => $section_id . '-item-' . $ii,
                'parent' => $section_id,
                'title'  => esc_html($item[0]),
                'href'   => $item[1],
            ]);
        }
    }
}

/* CSS pour mettre en évidence le bouton "LFI Clos Toreau" dans la barre */
add_action('admin_head', 'lfi_nct_admin_bar_css');
add_action('wp_head',    'lfi_nct_admin_bar_css');
function lfi_nct_admin_bar_css() {
    if (!is_admin_bar_showing()) return;
    ?>
    <style>
    #wpadminbar #wp-admin-bar-lfi-nct > .ab-item {
        background: linear-gradient(135deg, #c8102e, #a30b25) !important;
        color: #fff !important;
    }
    #wpadminbar #wp-admin-bar-lfi-nct:hover > .ab-item,
    #wpadminbar #wp-admin-bar-lfi-nct.hover > .ab-item {
        background: #a30b25 !important;
        color: #fff !important;
    }
    #wpadminbar #wp-admin-bar-lfi-nct .ab-sub-wrapper {
        min-width: 280px;
    }
    #wpadminbar #wp-admin-bar-lfi-nct .ab-submenu .lfi-nct-section-header > .ab-item {
        background: #2c3338 !important;
        pointer-events: none;
        padding-top: 10px !important;
        padding-bottom: 4px !important;
        border-top: 1px solid #444 !important;
    }
    #wpadminbar #wp-admin-bar-lfi-nct .ab-submenu .lfi-nct-section-header:first-child > .ab-item {
        border-top: 0 !important;
        padding-top: 6px !important;
    }
    #wpadminbar #wp-admin-bar-lfi-nct .ab-submenu .ab-submenu .ab-item {
        padding-left: 18px !important;
    }
    @media (max-width: 600px) {
        #wpadminbar #wp-admin-bar-lfi-nct > .ab-item span:last-child { display: none; }
    }
    </style>
    <?php
}
