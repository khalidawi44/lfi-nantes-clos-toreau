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

add_action('wp_body_open', 'lfi_nct_render_site_navbar_safe', 5);

/* Wrapper protégé : un plantage de la navbar ne doit JAMAIS blanchir
   tout le site (le hook wp_body_open tourne sur chaque page). */
function lfi_nct_render_site_navbar_safe() {
    try {
        lfi_nct_render_site_navbar();
    } catch (\Throwable $e) {
        if (function_exists('error_log')) error_log('[LFI navbar] ' . $e->getMessage());
    }
}

/* URL de connexion (page « mon-compte ») avec repli sur le login WP. */
function lfi_nct_login_page_url() {
    return function_exists('lfi_nct_page_url') ? lfi_nct_page_url('mon-compte', wp_login_url()) : wp_login_url();
}
/* URL d'inscription (landing app : locataire ou GA). */
function lfi_nct_register_page_url() {
    return function_exists('lfi_nct_app_url') ? lfi_nct_app_url('inscription') : home_url('/app/?vue=inscription');
}

function lfi_nct_render_site_navbar() {
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

    /* === VISITEUR NON CONNECTÉ : barre « S'inscrire / Se connecter » === */
    if (!is_user_logged_in()) {
        lfi_nct_render_site_navbar_loggedout();
        return;
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
    /* === ADMIN du GA (accès complet, y compris données locataires) === */
    elseif ($is_admin) {
        $primary = [
            ['🏠', 'Tableau de bord', $app_root],
            ['🔧', 'Brigade',         lfi_nct_app_url('interventions')],
            ['📁', 'Dossiers',        lfi_nct_app_url('dossiers-juridiques')],
            ['☎️', 'Appels NMH',      lfi_nct_app_url('appels-nmh')],
            ['⚖️', 'Recouvrement',    lfi_nct_app_url('recouvrements')],
            ['🛠', 'Tutos',           lfi_nct_app_url('tutoriels')],
        ];
        $secondary = [
            ['🔬', 'Outils scientifiques', lfi_nct_app_url('outils')],
            ['📅', 'Mon agenda',           lfi_nct_app_url('agenda')],
            ['📋', 'Faire passer enquête', lfi_nct_survey_url()],
            ['🔄', 'Synchroniser',         admin_url('admin-post.php?action=lfi_nct_purge_all')],
            ['🗂', 'Réponses enquêtes',    lfi_nct_app_url('dossiers')],
            ['📈', 'Stats enquêtes',       lfi_nct_app_url('stats-enquete')],
            ['👥', 'Comptes GA + loc.',    lfi_nct_app_url('membres')],
            ['📣', 'Événements',           lfi_nct_app_url('evenements')],
            ['📱', 'SMS aux adhérents',    lfi_nct_app_url('sms')],
            ['✉️', 'Email aux adhérents',   lfi_nct_app_url('email')],
            ['⚙️', 'Mes paramètres',       lfi_nct_app_url('facturation-params')],
            ['📲', 'Installer l\'app',     lfi_nct_app_url('installer')],
            ['✏️', 'Mon profil',           lfi_nct_app_url('mon-profil')],
            ['🚪', 'Se déconnecter',       wp_logout_url(home_url('/'))],
        ];
    }
    /* === MEMBRE du GA (militant, NON-admin) : accès restreint. Aucune donnée
       locataire (dossiers, comptes, recouvrement…), pas de SMS/email de masse.
       Uniquement : enquête, photos, événements, coordination, aide. === */
    else {
        $primary = [
            ['🏠', 'Mon espace',           $app_root],
            ['📋', 'Faire passer enquête', lfi_nct_survey_url()],
            ['📸', 'Photos',               lfi_nct_app_url('enquete-photos')],
            ['📅', 'Événements',           lfi_nct_app_url('evenements')],
        ];
        $secondary = [
            ['💡', 'Proposer une action',  lfi_nct_app_url('propositions')],
            ['🗓', 'Mes disponibilités',   lfi_nct_app_url('dispos')],
            ['👥', 'Dispos de l\'équipe',  lfi_nct_app_url('dispos-communes')],
            ['🤖', 'Aide',                 lfi_nct_app_url('aide')],
            ['📲', 'Installer l\'app',     lfi_nct_app_url('installer')],
            ['✏️', 'Mon profil',           lfi_nct_app_url('mon-profil')],
            ['🚪', 'Se déconnecter',       wp_logout_url(home_url('/'))],
        ];
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
 *  Barre publique (visiteur non connecté) : S'inscrire / Se        *
 *  connecter. Sans elle, les personnes déjà inscrites n'ont aucun  *
 *  lien pour se connecter.                                          *
 * ============================================================== */
function lfi_nct_render_site_navbar_loggedout() {
    $login    = lfi_nct_login_page_url();
    $register = lfi_nct_register_page_url();
    ?>
    <style>
    .lfi-pub-navbar {
        position: sticky; top: 0; z-index: 9999;
        background: linear-gradient(135deg, #c8102e, #a30b25);
        color: #fff; padding: 7px 12px;
        display: flex; align-items: center; gap: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,.18);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .lfi-pub-navbar .lfi-pub-brand {
        display: inline-flex; align-items: center; gap: 7px;
        color: #fff; text-decoration: none; font-weight: 800; font-size: 14px;
        margin-right: auto; min-width: 0;
    }
    .lfi-pub-brand .dot {
        background: #fff; color: #c8102e; width: 26px; height: 26px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center; font-weight: 900; flex-shrink: 0;
    }
    .lfi-pub-brand .lfi-pub-brand-txt { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lfi-pub-navbar a.lfi-pub-btn {
        text-decoration: none; font-weight: 800; font-size: 13.5px;
        padding: 8px 14px; border-radius: 999px; white-space: nowrap;
        display: inline-flex; align-items: center; gap: 6px; transition: transform .12s, background .15s;
    }
    .lfi-pub-navbar a.lfi-pub-btn.ghost { color: #fff !important; border: 1.5px solid rgba(255,255,255,.7); }
    .lfi-pub-navbar a.lfi-pub-btn.ghost:hover { background: rgba(255,255,255,.16); color: #fff !important; }
    .lfi-pub-navbar a.lfi-pub-btn.solid { background: #fff; color: #c8102e !important; }
    .lfi-pub-navbar a.lfi-pub-btn.solid:hover { transform: scale(1.04); color: #a30b25 !important; }
    @media (max-width: 480px) {
        .lfi-pub-brand .lfi-pub-brand-txt { display: none; }
        .lfi-pub-navbar a.lfi-pub-btn { padding: 8px 12px; font-size: 13px; }
    }
    @media print { .lfi-pub-navbar { display: none !important; } }
    </style>
    <div class="lfi-pub-navbar" role="navigation" aria-label="Connexion">
        <a class="lfi-pub-brand" href="<?php echo esc_url(home_url('/')); ?>">
            <span class="dot">Φ</span><span class="lfi-pub-brand-txt">LFI Nantes Sud — Clos Toreau</span>
        </a>
        <a class="lfi-pub-btn ghost" href="<?php echo esc_url($register); ?>">✍️ S'inscrire</a>
        <a class="lfi-pub-btn solid" href="<?php echo esc_url($login); ?>">🔑 Se connecter</a>
    </div>
    <?php
}

/* ============================================================== *
 *  Injection dans le MENU du thème : « Se connecter / S'inscrire » *
 *  (déconnecté) ou « Mon espace / Se déconnecter » (connecté).     *
 *  Ciblé sur le menu principal (header) pour ne pas polluer le      *
 *  menu de pied de page.                                            *
 * ============================================================== */
add_filter('wp_nav_menu_items', 'lfi_nct_menu_auth_items', 20, 2);
function lfi_nct_menu_auth_items($items, $args) {
    if (is_admin()) return $items;
    /* On ne l'ajoute qu'au menu principal / d'en-tête. */
    $loc = is_object($args) ? strtolower((string) ($args->theme_location ?? '')) : '';
    $is_primary = $loc !== '' && (
        strpos($loc, 'primary') !== false || strpos($loc, 'main') !== false ||
        strpos($loc, 'header')  !== false || strpos($loc, 'top')  !== false ||
        $loc === 'menu-1'
    );
    if (!$is_primary) return $items;

    if (is_user_logged_in()) {
        $space  = function_exists('lfi_nct_app_url') ? lfi_nct_app_url('') : home_url('/app/');
        $logout = wp_logout_url(home_url('/'));
        $items .= '<li class="menu-item lfi-menu-auth"><a href="' . esc_url($space) . '">🏠 Mon espace</a></li>';
        $items .= '<li class="menu-item lfi-menu-auth"><a href="' . esc_url($logout) . '">🚪 Se déconnecter</a></li>';
    } else {
        $items .= '<li class="menu-item lfi-menu-auth"><a href="' . esc_url(lfi_nct_register_page_url()) . '">✍️ S\'inscrire</a></li>';
        $items .= '<li class="menu-item lfi-menu-auth lfi-menu-auth-cta"><a href="' . esc_url(lfi_nct_login_page_url()) . '">🔑 Se connecter</a></li>';
    }
    return $items;
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
add_action('admin_bar_menu', 'lfi_nct_admin_bar_menu_safe', 30);

/* Wrapper protégé — la barre admin tourne sur tout le site et wp-admin. */
function lfi_nct_admin_bar_menu_safe($bar) {
    try {
        lfi_nct_admin_bar_menu($bar);
    } catch (\Throwable $e) {
        if (function_exists('error_log')) error_log('[LFI adminbar] ' . $e->getMessage());
    }
}

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
    } elseif (!$is_admin && $is_ga) {
        /* MEMBRE du GA (militant, non-admin) : accès restreint, aucune donnée
           locataire, pas de SMS/email de masse. */
        $sections = [
            ['📣 Mes actions', [
                ['🏠 Mon espace',               $app_root],
                ['📋 Faire passer une enquête', lfi_nct_survey_url()],
                ['📸 Photos chez un locataire', lfi_nct_app_url('enquete-photos')],
                ['📅 Événements',               lfi_nct_app_url('evenements')],
            ]],
            ['🤝 Coordination', [
                ['💡 Proposer une action',      lfi_nct_app_url('propositions')],
                ['🗓 Mes disponibilités',       lfi_nct_app_url('dispos')],
                ['👥 Dispos de l\'équipe',      lfi_nct_app_url('dispos-communes')],
            ]],
            ['⚙️ Mon compte', [
                ['🤖 Aide',                     lfi_nct_app_url('aide')],
                ['📲 Installer l\'app',         lfi_nct_app_url('installer')],
                ['✏️ Mon profil',               lfi_nct_app_url('mon-profil')],
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
                ['☎️ Appels NMH',           lfi_nct_app_url('appels-nmh')],
                ['⚖️ Recouvrement NMH',      lfi_nct_app_url('recouvrements')],
                ['🛠 Tutoriels',             lfi_nct_app_url('tutoriels')],
                ['🔬 Outils scientifiques',  lfi_nct_app_url('outils')],
                ['📅 Mon agenda',            lfi_nct_app_url('agenda')],
                ['⚙️ Mes paramètres',        lfi_nct_app_url('facturation-params')],
            ]],
        ];

        if ($is_admin) {
            $sections[] = ['📣 Action politique', [
                ['📋 Faire passer une enquête', lfi_nct_survey_url()],
                ['📅 Événements',               lfi_nct_app_url('evenements')],
                ['👥 Membres actifs',           lfi_nct_app_url('membres')],
                ['📱 SMS aux membres actifs',   lfi_nct_app_url('sms')],
                ['✉️ Email aux adhérents',       lfi_nct_app_url('email')],
            ]];
        }

        if ($is_admin) {
            $sections[] = ['👁 Admin (RGPD)', [
                ['🏠 Comptes locataires (wp-admin)', admin_url('admin.php?page=lfi-nct-comptes-loc')],
                ['👥 Comptes membres GA (wp-admin)', admin_url('admin.php?page=lfi-nct-comptes-ga')],
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
