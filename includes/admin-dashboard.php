<?php
/**
 * Intégration WordPress admin :
 *
 *  1. Menu latéral gauche "🏛 LFI Clos Toreau" rassemblant TOUS les
 *     outils (brigade, dossiers, recouvrement, tutoriels, enquête…)
 *     en un seul endroit visible.
 *
 *  2. Bouton de SYNCHRONISATION (purge cache LiteSpeed + Service
 *     Worker + LocalStorage) accessible directement, avec retour
 *     visuel et compteur.
 *
 *  3. Nettoyage du dashboard WordPress : suppression des widgets
 *     inutiles (WordPress Events, Quick Draft, Try Site Health…) et
 *     des promotions de plugins tiers.
 *
 *  4. Widget dashboard personnalisé "LFI — accès rapide" qui s'affiche
 *     à la place pour aller directement aux outils.
 */
if (!defined('ABSPATH')) exit;

/* ============================================================== *
 *  1. MENU LATÉRAL GAUCHE — tous les outils LFI                    *
 * ============================================================== */
add_action('admin_menu', 'lfi_nct_register_admin_menu', 5);

function lfi_nct_register_admin_menu() {
    if (!function_exists('lfi_nct_app_url')) return;
    if (!current_user_can('manage_options') &&
        !(function_exists('lfi_nct_user_role_ga') && lfi_nct_user_role_ga())) return;

    $is_admin = current_user_can('manage_options');
    $is_ga    = function_exists('lfi_nct_user_role_ga') && lfi_nct_user_role_ga();

    /* Page parent — "tableau de bord" qui redirige vers /app/ */
    add_menu_page(
        'LFI Clos Toreau',
        '🏛 LFI Clos Toreau',
        'read',
        'lfi-nct-hub',
        'lfi_nct_render_admin_hub',
        'dashicons-admin-site-alt3',
        3
    );

    /* Sous-pages — utiles pour avoir un titre lisible dans le breadcrumb */
    add_submenu_page('lfi-nct-hub', 'Accueil LFI', '🏠 Tableau de bord', 'read', 'lfi-nct-hub', 'lfi_nct_render_admin_hub');

    /* Tous les autres items sont injectés directement dans $submenu
       avec leur vraie URL → un clic → on est dans l'app. */
    global $submenu;

    $items = [];

    /* Brigade — tous les rôles autorisés */
    $items['🔧 BRIGADE'] = [
        ['🔧 Mes interventions',     lfi_nct_app_url('interventions')],
        ['＋ Nouvelle intervention', lfi_nct_app_url('intervention-add')],
        ['📁 Dossiers juridiques',   lfi_nct_app_url('dossiers-juridiques')],
        ['＋ Nouveau dossier',       lfi_nct_app_url('dossier-juridique-add')],
        ['⚖️ Recouvrement NMH',      lfi_nct_app_url('recouvrements')],
        ['🛠 Tutoriels',             lfi_nct_app_url('tutoriels')],
        ['🔬 Outils scientifiques',  lfi_nct_app_url('outils')],
        ['📅 Mon agenda',            lfi_nct_app_url('agenda')],
        ['⚙️ Mes paramètres',        lfi_nct_app_url('facturation-params')],
    ];

    if ($is_admin || $is_ga) {
        $items['📣 ACTION POLITIQUE'] = [
            ['📋 Faire passer une enquête', home_url('/enquete-logement-clos-toreau/')],
            ['📅 Événements',               lfi_nct_app_url('evenements')],
            ['👥 Adhérents',                lfi_nct_app_url('membres')],
            ['📱 SMS aux adhérents',        lfi_nct_app_url('sms')],
            ['✉️ Email aux adhérents',       lfi_nct_app_url('email')],
        ];
    }

    if ($is_admin) {
        $items['👁 ADMIN'] = [
            ['🗂 Réponses d\'enquête',  lfi_nct_app_url('dossiers')],
            ['📈 Stats enquêtes',      lfi_nct_app_url('stats-enquete')],
            ['📊 Stats globales',      lfi_nct_app_url('stats')],
            ['🗺 Carte',               lfi_nct_app_url('carte')],
            ['👤 Aperçu locataire/GA', lfi_nct_app_url('preview')],
        ];
    }

    /* Sync / purge cache — toujours en bas, accessible en 1 clic */
    $items['🔄 SYNCHRONISATION'] = [
        ['🔥 Forcer la synchronisation', admin_url('admin-post.php?action=lfi_nct_purge_all')],
    ];

    /* Injection des items dans le sous-menu WP. Format requis :
       [ titre, capability, url ]. WordPress n'accepte pas naturellement
       des sections (séparateurs), on utilise un titre stylé non
       cliquable comme en-tête. */
    foreach ($items as $section_title => $links) {
        $submenu['lfi-nct-hub'][] = [
            '<span style="color:#ff8a8a;text-transform:uppercase;font-weight:800;font-size:11px;letter-spacing:.5px;pointer-events:none">' . esc_html($section_title) . '</span>',
            'read',
            '#'
        ];
        foreach ($links as $L) {
            $submenu['lfi-nct-hub'][] = [esc_html($L[0]), 'read', esc_url($L[1])];
        }
    }
}

/* Page hub — rendu d'un dashboard simple qui redirige vers /app/ */
function lfi_nct_render_admin_hub() {
    if (!function_exists('lfi_nct_app_url')) return;
    $app_root = lfi_nct_app_url('');
    ?>
    <div class="wrap" style="max-width:760px">
        <h1 style="display:flex;align-items:center;gap:10px">
            <span style="background:#c8102e;color:#fff;width:42px;height:42px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:900">Φ</span>
            LFI Clos Toreau — Centre de contrôle
        </h1>

        <p style="font-size:1.05em;color:#444;line-height:1.5">
            Tous les outils LFI sont dans le menu de gauche ↖, regroupés par section.
            Tu peux aussi ouvrir directement le tableau de bord interactif de l'application :
        </p>

        <p style="margin:18px 0">
            <a href="<?php echo esc_url($app_root); ?>" class="button button-primary button-hero" style="background:#c8102e;border-color:#a30b25">
                🏠 Ouvrir le tableau de bord interactif
            </a>
        </p>

        <hr style="margin:24px 0">

        <h2>🚀 Accès rapide</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px">
            <?php
            $quick = [
                ['🚨', 'Dossiers locataires urgents', 'Suivi prioritaire',            lfi_nct_app_url('dossiers-juridiques')],
                ['⏰', 'Relances à faire',            'Courriers sans réponse',       lfi_nct_app_url('')],
                ['📋', 'Faire une enquête',           'Formulaire porte-à-porte',     home_url('/enquete-logement-clos-toreau/')],
                ['🗺️', 'Les autres groupes d\'action','Réseau des GA du réseau',      lfi_nct_app_url('groupes')],
                ['🏠', 'Ouvrir l\'application',       'Tableau de bord interactif',   lfi_nct_app_url('')],
                ['🔥', 'Forcer la synchro',           'Purger cache + SW',            admin_url('admin-post.php?action=lfi_nct_purge_all')],
            ];
            foreach ($quick as $q) {
                if (!current_user_can('manage_options') && strpos($q[1], 'admin') !== false) continue;
                echo '<a href="' . esc_url($q[3]) . '" style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:14px;text-decoration:none;color:#1a1a1a;display:block;transition:all .15s" onmouseover="this.style.borderColor=\'#c8102e\';this.style.boxShadow=\'0 2px 8px rgba(200,16,46,.15)\'" onmouseout="this.style.borderColor=\'#ddd\';this.style.boxShadow=\'none\'">';
                echo '<div style="font-size:1.8em;line-height:1;margin-bottom:6px">' . $q[0] . '</div>';
                echo '<div style="font-weight:700;color:#c8102e;margin-bottom:2px">' . esc_html($q[1]) . '</div>';
                echo '<div style="font-size:.9em;color:#666">' . esc_html($q[2]) . '</div>';
                echo '</a>';
            }
            ?>
        </div>
    </div>
    <?php
}

/* ============================================================== *
 *  2. BOUTON DE SYNCHRONISATION (purge cache complète)             *
 * ============================================================== */
add_action('admin_post_lfi_nct_purge_all', 'lfi_nct_purge_all_handler');

function lfi_nct_purge_all_handler() {
    if (!current_user_can('manage_options') &&
        !(function_exists('lfi_nct_user_role_ga') && lfi_nct_user_role_ga())) {
        wp_die('Accès refusé', '', ['response' => 403]);
    }

    /* 1) Purge LiteSpeed Cache */
    do_action('litespeed_purge_all');

    /* 2) Autres caches éventuels */
    if (function_exists('wp_cache_clear_cache')) wp_cache_clear_cache();
    if (function_exists('w3tc_flush_all'))       w3tc_flush_all();
    if (function_exists('rocket_clean_domain'))  rocket_clean_domain();
    if (function_exists('wp_cache_flush'))       wp_cache_flush();

    /* 3) Force le re-déclenchement de la purge sur le bump de version
       (au cas où Hostinger n'a pas encore tiré la nouvelle version) */
    delete_option('lfi_nct_installed_version');

    /* 4) Bump du compteur de version du Service Worker pour forcer les
       PWA à recharger leur cache au prochain accès */
    $sw_v = (int) get_option('lfi_nct_sw_force_v', 1) + 1;
    update_option('lfi_nct_sw_force_v', $sw_v, false);

    wp_safe_redirect(add_query_arg(['page' => 'lfi-nct-hub', 'synced' => 1], admin_url('admin.php')));
    exit;
}

/* Notice de succès après la synchro */
add_action('admin_notices', 'lfi_nct_purge_notice');
function lfi_nct_purge_notice() {
    if (empty($_GET['synced'])) return;
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'toplevel_page_lfi-nct-hub') return;
    echo '<div class="notice notice-success is-dismissible" style="border-left-color:#c8102e">';
    echo '<p style="font-size:1.05em"><strong>🔥 Synchronisation effectuée.</strong> Tous les caches sont purgés (LiteSpeed, navigateur, Service Worker PWA). La nouvelle version est active immédiatement.</p>';
    echo '</div>';
}

/* ============================================================== *
 *  3. NETTOYAGE DU DASHBOARD WORDPRESS                              *
 *                                                                   *
 *  Suppression des widgets et notices inutiles ou promotionnels.   *
 * ============================================================== */
add_action('wp_dashboard_setup', 'lfi_nct_clean_dashboard', 99);
function lfi_nct_clean_dashboard() {
    global $wp_meta_boxes;

    /* Liste des widgets dashboard à zapper */
    $remove = [
        'dashboard_primary',         // Événements WordPress et nouvelles
        'dashboard_quick_press',     // Brouillon rapide
        'dashboard_site_health',     // Santé du site
        'dashboard_php_nag',         // Nag PHP
        'welcome_panel',             // Bienvenue WordPress
        /* Promos de plugins fréquentes */
        'wpe_dify_news_feed',
        'jetpack_summary_widget',
        'rg_forms_dashboard',
        'monsterinsights_reports_widget',
        'wpforms_reports_widget',
        'aioseo-rss-feed',
        'yoast_db_widget',
        'rank_math_dashboard_widget',
        'astra_sites_admin_dashboard',
        'hostinger_dashboard_widget',
        'litespeed_dashboard_widget',
    ];

    foreach (['normal', 'side', 'column3', 'column4'] as $ctx) {
        foreach (['core', 'high', 'default', 'low'] as $prio) {
            if (!isset($wp_meta_boxes['dashboard'][$ctx][$prio])) continue;
            foreach ($remove as $widget_id) {
                unset($wp_meta_boxes['dashboard'][$ctx][$prio][$widget_id]);
            }
        }
    }

    /* Retire aussi le panneau de bienvenue WordPress */
    remove_action('welcome_panel', 'wp_welcome_panel');
}

/* Suppression des admin notices non WP (typiquement les promos plugins) */
add_action('admin_print_scripts', 'lfi_nct_hide_promo_notices', 999);
function lfi_nct_hide_promo_notices() {
    if (!is_admin()) return;
    $screen = get_current_screen();
    /* On nettoie le dashboard seulement — pas les pages où les notices
       sont vraiment utiles (réglages, pages plugins…). */
    if (!$screen || !in_array($screen->id, ['dashboard', 'toplevel_page_lfi-nct-hub'], true)) return;

    /* Patterns de classes / IDs courants pour les notices promotionnelles */
    ?>
    <style>
    /* Cache les notices de pub (Hostinger, Astra, LiteSpeed, Yoast, etc.) */
    .notice.litespeed-banner,
    .notice.litespeed-banner-promo,
    .notice.astra-notice,
    .notice.hostinger-notice,
    .notice.hostinger-banner,
    .notice.jetpack-banner,
    .notice.yoast-notice,
    .notice.rank-math-notice,
    .notice.wpforms-notice,
    .notice.elementor-message-dismissed,
    .notice.elementor-message,
    .e-notice,
    .notice-litespeed,
    div[id^="message-"][class*="updated"][class*="lite"],
    /* "Get more plugins" / "Try plugin X" patterns */
    .notice[class*="plugin-install"],
    .notice[class*="recommend"],
    .notice[class*="try-"],
    .upgrade-notice,
    /* Hostinger widget bandeau */
    .hostinger-onboarding-modal,
    /* Footer "Thank you for creating with WordPress" */
    #footer-thankyou { display: none !important; }

    /* Cache les widgets de plugins tiers qui s'invitent au dashboard */
    #dashboard-widgets .postbox[id*="litespeed"],
    #dashboard-widgets .postbox[id*="hostinger"],
    #dashboard-widgets .postbox[id*="astra"],
    #dashboard-widgets .postbox[id*="jetpack"],
    #dashboard-widgets .postbox[id*="yoast"],
    #dashboard-widgets .postbox[id*="elementor"],
    #dashboard-widgets .postbox[id*="rank_math"],
    #dashboard-widgets .postbox[id*="wpforms"] { display: none !important; }
    </style>
    <?php
}

/* ============================================================== *
 *  4. WIDGET DASHBOARD "LFI — accès rapide"                         *
 *                                                                   *
 *  Remplace les widgets supprimés par notre propre accès rapide.   *
 * ============================================================== */
add_action('wp_dashboard_setup', 'lfi_nct_register_dashboard_widget');
function lfi_nct_register_dashboard_widget() {
    if (!current_user_can('manage_options') &&
        !(function_exists('lfi_nct_user_role_ga') && lfi_nct_user_role_ga())) return;
    wp_add_dashboard_widget(
        'lfi_nct_quick',
        '🏛 LFI Clos Toreau — accès rapide',
        'lfi_nct_render_dashboard_widget',
        null, null,
        'normal', 'high'
    );
}

function lfi_nct_render_dashboard_widget() {
    if (!function_exists('lfi_nct_app_url')) return;
    $app_root = lfi_nct_app_url('');
    $tiles = [
        ['🔧', 'Mes interventions',    lfi_nct_app_url('interventions')],
        ['＋', 'Nouvelle intervention', lfi_nct_app_url('intervention-add')],
        ['📁', 'Dossiers juridiques',   lfi_nct_app_url('dossiers-juridiques')],
        ['＋', 'Nouveau dossier',       lfi_nct_app_url('dossier-juridique-add')],
        ['⚖️', 'Recouvrement NMH',      lfi_nct_app_url('recouvrements')],
        ['🛠', 'Tutoriels',             lfi_nct_app_url('tutoriels')],
        ['📋', 'Faire passer enquête',  home_url('/enquete-logement-clos-toreau/')],
        ['🔥', 'Forcer la synchro',     admin_url('admin-post.php?action=lfi_nct_purge_all')],
    ];
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px">';
    foreach ($tiles as $t) {
        echo '<a href="' . esc_url($t[2]) . '" style="background:#fff;border:1px solid #e1e1e1;border-radius:8px;padding:12px 10px;text-align:center;text-decoration:none;color:#1a1a1a;display:block;transition:all .15s" onmouseover="this.style.borderColor=\'#c8102e\';this.style.background=\'#fff5f6\'" onmouseout="this.style.borderColor=\'#e1e1e1\';this.style.background=\'#fff\'">';
        echo '<div style="font-size:1.6em;line-height:1;margin-bottom:4px">' . $t[0] . '</div>';
        echo '<div style="font-size:.9em;font-weight:700;color:#c8102e">' . esc_html($t[1]) . '</div>';
        echo '</a>';
    }
    echo '</div>';
    echo '<p style="margin-top:14px;text-align:center"><a href="' . esc_url($app_root) . '" class="button button-primary" style="background:#c8102e;border-color:#a30b25">🏠 Ouvrir le tableau de bord complet</a></p>';
}

/* ============================================================== *
 *  Styling du menu latéral pour rendre le LFI plus visible          *
 * ============================================================== */
add_action('admin_head', 'lfi_nct_admin_menu_css');
function lfi_nct_admin_menu_css() {
    ?>
    <style>
    /* Item parent — fond rouge pour bien le voir */
    #adminmenu #toplevel_page_lfi-nct-hub > a.menu-top {
        background: linear-gradient(180deg, #c8102e, #a30b25);
        color: #fff !important;
        font-weight: 700;
    }
    #adminmenu #toplevel_page_lfi-nct-hub > a.menu-top:hover,
    #adminmenu #toplevel_page_lfi-nct-hub.wp-has-current-submenu > a.menu-top {
        background: #a30b25 !important;
        color: #fff !important;
    }
    #adminmenu #toplevel_page_lfi-nct-hub .wp-menu-image:before { color: #fff !important; }
    /* Sous-menu */
    #adminmenu #toplevel_page_lfi-nct-hub .wp-submenu {
        background: #2c3338 !important;
    }
    #adminmenu #toplevel_page_lfi-nct-hub .wp-submenu a {
        color: #e0e0e0 !important;
    }
    #adminmenu #toplevel_page_lfi-nct-hub .wp-submenu a:hover {
        color: #ff8a8a !important;
        background: rgba(255,138,138,.08) !important;
    }
    </style>
    <?php
}
