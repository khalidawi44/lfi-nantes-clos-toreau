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
        /* CONFIGURATION DU GA — tout en haut, facile à trouver. */
        $items['🏢 CONFIGURATION DU GA'] = [
            ['🏢 Bailleurs sociaux',    lfi_nct_app_url('bailleurs')],
            ['📥 Import email (auto)',  lfi_nct_app_url('inbox-import')],
        ];

        $items['📣 ACTION POLITIQUE'] = [
            ['📋 Faire passer une enquête', lfi_nct_survey_url()],
            ['📅 Événements',               lfi_nct_app_url('evenements')],
            ['🤝 Se coordonner',            lfi_nct_app_url('mobilisation')],
            ['👥 Membres actifs',           lfi_nct_app_url('membres')],
            ['📱 SMS aux membres actifs',   lfi_nct_app_url('sms')],
            ['✉️ Email aux adhérents',       lfi_nct_app_url('email')],
        ];

        /* Défense des locataires — juridique + pièces + avocats. */
        $items['⚖️ JURIDIQUE & DÉFENSE'] = [
            ['🏠 Comptes locataires',       lfi_nct_app_url('comptes-locataires')],
            ['🗂 Dossiers & suivi',         lfi_nct_app_url('dossiers')],
            ['📁 Dossiers juridiques',      lfi_nct_app_url('dossiers-juridiques')],
            ['⚖️ Avocat·es partenaires',    lfi_nct_app_url('avocats')],
            ['🔎 Jurisprudence (Judilibre)', lfi_nct_app_url('jurisprudence')],
            ['🏆 Nos victoires',            lfi_nct_app_url('victoires')],
            ['🚫 Liste noire SMS',          lfi_nct_app_url('sms-blocklist')],
        ];
    }

    if ($is_admin) {
        /* Élu·es & institutions — députés, municipaux, audit NMH. */
        $items['🏛 ÉLU·ES & INSTITUTIONS'] = [
            ['🤝 Élu·es partenaires',       lfi_nct_app_url('partenaires')],
            ['🏛️ Stratégie municipale',     lfi_nct_app_url('strategie-municipale')],
            ['🇫🇷 Stratégie nationale',      lfi_nct_app_url('strategie-nationale')],
            ['💶 Où va mon loyer ? (audit NMH)', lfi_nct_app_url('audit-nmh')],
            ['🏛️ Préfecture',               lfi_nct_app_url('prefecture')],
        ];

        /* Volets thématiques. */
        $items['📚 VOLETS'] = [
            ['🩺 Santé publique (puffs)',   lfi_nct_app_url('sante')],
            ['👶 Protection de l\'enfance', lfi_nct_app_url('ase')],
        ];

        $items['👁 ADMIN'] = [
            ['🗂 Réponses d\'enquête',  lfi_nct_app_url('dossiers')],
            ['📈 Stats enquêtes',      lfi_nct_app_url('stats-enquete')],
            ['📊 Stats globales',      lfi_nct_app_url('stats')],
            ['🗺 Carte',               lfi_nct_app_url('carte')],
            ['🌐 Réseau des GA',       lfi_nct_app_url('reseau-ga')],
            ['📥 Import email (auto)', lfi_nct_app_url('inbox-import')],
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

/* ============================================================== *
 *  BLOCS sur l'accueil wp-admin (widgets « tableau de bord »)      *
 *  → l'admin WordPress affiche les outils LFI en blocs, comme      *
 *  l'app, rangés par catégorie.                                    *
 * ============================================================== */
add_action('wp_dashboard_setup', 'lfi_nct_register_dashboard_widgets');
function lfi_nct_register_dashboard_widgets() {
    if (!(current_user_can('manage_options') || (function_exists('lfi_nct_user_role_ga') && lfi_nct_user_role_ga()))) return;
    wp_add_dashboard_widget('lfi_nct_tools_widget', '🏛 LFI Clos Toreau — Vos outils', 'lfi_nct_render_tools_widget');
    /* On remonte le bloc tout en haut de la colonne principale. */
    global $wp_meta_boxes;
    if (isset($wp_meta_boxes['dashboard']['normal']['core']['lfi_nct_tools_widget'])) {
        $w = $wp_meta_boxes['dashboard']['normal']['core']['lfi_nct_tools_widget'];
        unset($wp_meta_boxes['dashboard']['normal']['core']['lfi_nct_tools_widget']);
        $wp_meta_boxes['dashboard']['normal']['core'] = ['lfi_nct_tools_widget' => $w] + $wp_meta_boxes['dashboard']['normal']['core'];
    }
}

function lfi_nct_render_tools_widget() {
    if (!function_exists('lfi_nct_app_url')) return;
    $is_admin = current_user_can('manage_options');
    $survey   = function_exists('lfi_nct_survey_url') ? lfi_nct_survey_url() : home_url('/');

    $groups = [
        '🔧 Brigade & dossiers' => [
            ['📋', 'Faire une enquête', $survey],
            ['🗂', 'Dossiers & suivi', lfi_nct_app_url('dossiers')],
            ['📁', 'Dossiers juridiques', lfi_nct_app_url('dossiers-juridiques')],
            ['🔧', 'Interventions', lfi_nct_app_url('interventions')],
            ['⚖️', 'Recouvrement NMH', lfi_nct_app_url('recouvrements')],
        ],
        '⚖️ Juridique & défense' => [
            ['🏠', 'Comptes locataires', lfi_nct_app_url('comptes-locataires')],
            ['⚖️', 'Avocat·es partenaires', lfi_nct_app_url('avocats')],
            ['🔎', 'Jurisprudence', lfi_nct_app_url('jurisprudence')],
            ['🏆', 'Nos victoires', lfi_nct_app_url('victoires')],
            ['🚫', 'Liste noire SMS', lfi_nct_app_url('sms-blocklist')],
        ],
        '📣 Action politique' => [
            ['📅', 'Événements', lfi_nct_app_url('evenements')],
            ['🤝', 'Se coordonner', lfi_nct_app_url('mobilisation')],
            ['👥', 'Membres actifs', lfi_nct_app_url('membres')],
            ['📱', 'SMS aux membres', lfi_nct_app_url('sms')],
            ['✉️', 'Email aux adhérents', lfi_nct_app_url('email')],
        ],
    ];
    $groups['🏢 Configuration du GA'] = [
        ['🏢', 'Bailleurs sociaux', lfi_nct_app_url('bailleurs')],
        ['📥', 'Import email (auto)', lfi_nct_app_url('inbox-import')],
    ];
    if ($is_admin) {
        $groups['🏛 Élu·es & institutions'] = [
            ['🤝', 'Élu·es partenaires', lfi_nct_app_url('partenaires')],
            ['🏛️', 'Stratégie municipale', lfi_nct_app_url('strategie-municipale')],
            ['🇫🇷', 'Stratégie nationale', lfi_nct_app_url('strategie-nationale')],
            ['💶', 'Audit NMH', lfi_nct_app_url('audit-nmh')],
            ['🏛️', 'Préfecture', lfi_nct_app_url('prefecture')],
        ];
        $groups['📚 Volets'] = [
            ['🩺', 'Santé publique (puffs)', lfi_nct_app_url('sante')],
            ['👶', 'Protection de l\'enfance', lfi_nct_app_url('ase')],
        ];
    }

    echo '<p style="margin:2px 0 12px"><a href="' . esc_url(lfi_nct_app_url('')) . '" class="button button-primary" style="background:#c8102e;border-color:#a30b25">🏠 Ouvrir l\'application complète</a></p>';
    foreach ($groups as $title => $tiles) {
        echo '<div style="font-weight:800;color:#c8102e;text-transform:uppercase;font-size:11px;letter-spacing:.5px;margin:12px 0 6px">' . esc_html($title) . '</div>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px">';
        foreach ($tiles as $t) {
            echo '<a href="' . esc_url($t[2]) . '" style="display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #e0e0e0;border-radius:9px;padding:9px 11px;text-decoration:none;color:#1a1a1a" onmouseover="this.style.borderColor=\'#c8102e\'" onmouseout="this.style.borderColor=\'#e0e0e0\'">';
            echo '<span style="font-size:1.25em;line-height:1">' . $t[0] . '</span><span style="font-weight:600;font-size:.9em">' . esc_html($t[1]) . '</span></a>';
        }
        echo '</div>';
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
                ['📋', 'Faire une enquête',           'Formulaire porte-à-porte',     lfi_nct_survey_url()],
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

        <hr style="margin:24px 0">
        <h2>🔗 Vérification des liens</h2>
        <p style="color:#444">État des liens vers les <strong>pages du site</strong> (les boutons de l'app en <code>/app/?vue=…</code> ne peuvent pas faire de 404). ✅ = page trouvée · ❌ = page ABSENTE (c'est elle qui fait le 404).</p>
        <?php
        $survey_url = function_exists('lfi_nct_survey_url') ? lfi_nct_survey_url() : home_url('/');
        $survey_ok  = (strpos($survey_url, 'enquete-logement-clos-toreau') === false) || (bool) get_page_by_path('enquete-logement-clos-toreau', OBJECT, ['page', 'post']);
        $app_url_real = function_exists('lfi_nct_app_page_url') ? lfi_nct_app_page_url() : home_url('/' . LFI_NCT_APP_SLUG . '/');
        $app_page     = get_page_by_path(LFI_NCT_APP_SLUG, OBJECT, 'page');
        $app_status   = $app_page ? $app_page->post_status : 'absente';
        $app_public   = ($app_status === 'publish' && (string) ($app_page->post_password ?? '') === '');
        $app_label    = '📱 Application (lien des SMS) — page : ' . ($app_public ? 'publiée ✅' : 'PAS PUBLIQUE (' . $app_status . ') → c\'est ça qui fait le 404 pour les membres déconnectés');
        $rows = [
            [$app_label, $app_url_real, $app_public],
            ['📋 Formulaire d\'enquête', $survey_url, $survey_ok],
        ];
        foreach ([
            'rendez-vous'  => '📅 Prendre RDV',
            'mon-compte'   => '👤 Espace adhérent',
            'adherer'      => '🤝 Adhérer',
            'signer'       => '✍️ Pétition',
            'contact'      => '✉️ Contact',
            'evenements'   => '📣 Événements',
        ] as $slug => $label) {
            $p = get_page_by_path($slug, OBJECT, ['page', 'post']);
            $rows[] = [$label . ' (/' . $slug . '/)', $p ? get_permalink($p) : home_url('/' . $slug . '/'), (bool) $p];
        }
        echo '<div style="overflow-x:auto"><table class="widefat striped" style="max-width:760px"><thead><tr><th>Lien</th><th>Statut</th><th>Tester</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $badge = $r[2] ? '<span style="color:#186a3b;font-weight:700">✅ page trouvée</span>'
                           : '<span style="color:#c8102e;font-weight:700">❌ page absente</span>';
            echo '<tr><td>' . esc_html($r[0]) . '</td><td>' . $badge . '</td><td><a href="' . esc_url($r[1]) . '" target="_blank" rel="noopener">ouvrir ↗</a></td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<p style="color:#666;font-size:.92em">Une ligne <strong>❌</strong> = c\'est ce lien qui envoie vers le 404. Dis-moi lequel (ou crée la page manquante) et je l\'oriente vers la bonne page. Les liens de l\'app (enquête incluse) se réparent désormais tout seuls.</p>';
        ?>
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
 *  2bis. SUPPRESSION DU DIAGNOSTIC « MU-plugin LFI v3 »            *
 *                                                                  *
 *  Ce tableau « Diagnostic ag_evenement » est injecté par un       *
 *  must-use plugin externe (wp-content/mu-plugins/), PAS par ce    *
 *  plugin. Comme il n'est pas dans le dépôt et qu'on ne veut pas   *
 *  toucher au serveur, on le retire côté navigateur : on cherche   *
 *  le bloc par son texte distinctif et on l'enlève. Garde-fou :    *
 *  on ne supprime jamais un bloc contenant notre propre hub.       *
 * ============================================================== */
add_action('admin_footer', 'lfi_nct_strip_muplugin_diag', 99);
function lfi_nct_strip_muplugin_diag() {
    if (!is_admin()) return;
    ?>
    <script>
    (function () {
        function clean() {
            var needles = ['Diagnostic ag_evenement', 'MU-plugin LFI'];
            var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null);
            var hits = [], n;
            while ((n = walker.nextNode())) {
                var v = n.nodeValue || '';
                if (needles.some(function (k) { return v.indexOf(k) > -1; })) hits.push(n);
            }
            hits.forEach(function (t) {
                var el = t.parentElement;
                if (!el) return;
                /* On remonte vers un bloc qui englobe aussi un <table>. */
                var block = el, up = 0;
                while (block && up < 6) {
                    if (block.querySelector && block.querySelector('table')) break;
                    block = block.parentElement; up++;
                }
                var safe = function (node) {
                    if (!node || node === document.body) return false;
                    var txt = node.textContent || '';
                    if (txt.indexOf('Centre de contrôle') > -1) return false;   // notre hub
                    if (node.querySelector && node.querySelector('a.button-hero')) return false;
                    if (node.id === 'wpbody' || node.id === 'wpbody-content' || node.id === 'wpcontent') return false;
                    return true;
                };
                if (block && block.querySelector && block.querySelector('table') && safe(block)) {
                    block.remove();
                    return;
                }
                /* Repli : on enlève le titre puis les frères jusqu'au tableau inclus. */
                var sib = el.nextElementSibling;
                el.remove();
                while (sib) {
                    var next = sib.nextElementSibling;
                    var tag = sib.tagName ? sib.tagName.toLowerCase() : '';
                    sib.remove();
                    if (tag === 'table') break;
                    sib = next;
                }
            });
        }
        if (document.readyState !== 'loading') clean();
        else document.addEventListener('DOMContentLoaded', clean);
        setTimeout(clean, 400); /* re-passe après que WP ait déplacé les notices */
    })();
    </script>
    <?php
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
        ['📋', 'Faire passer enquête',  lfi_nct_survey_url()],
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
