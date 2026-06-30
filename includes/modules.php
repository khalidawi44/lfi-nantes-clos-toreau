<?php
/**
 * GESTION DES MODULES (activables / désactivables par les admins du groupe).
 *
 * Tous les outils sont présents, mais chaque groupe d'action peut activer ou
 * désactiver ce qu'il veut depuis la page « Modules ». Quand un module est
 * désactivé : ses tuiles disparaissent de l'accueil et ses pages sont protégées.
 *
 * Compatibilité : le volet travaux reste aussi désactivable par la constante
 * LFI_NCT_DISABLE_TRAVAUX (wp-config), en plus de la case ci-dessous.
 */
if (!defined('ABSPATH')) exit;

/** Catalogue des modules : clé => [label, desc, routes contrôlées, défaut]. */
function lfi_nct_modules_registry() {
    return [
        'enquete' => [
            'label'  => 'Enquête logement',
            'desc'   => 'Formulaire porte-à-porte, réponses, statistiques, carte.',
            'routes' => ['enquetes', 'stats-enquete', 'carte', 'temoignage-add'],
            'default'=> true,
        ],
        'dossiers' => [
            'label'  => 'Dossiers locataires',
            'desc'   => 'Le cœur de l\'outil : suivi par locataire, courriers, correspondance.',
            'routes' => [],
            'default'=> true,
        ],
        'travaux' => [
            'label'  => 'Volet travaux',
            'desc'   => 'Interventions chez les locataires, facturation, recouvrement, cadre facturable.',
            'routes' => ['interventions', 'intervention-add', 'intervention-edit', 'facture', 'recouvrements', 'recouvrement-dossier', 'cadre-juridique'],
            'default'=> true,
        ],
        'prefecture' => [
            'label'  => 'Préfecture',
            'desc'   => 'Partage anonyme des données par bâtiment + correspondance avec la préfecture.',
            'routes' => ['prefecture', 'prefecture-rapport', 'prefecture-email'],
            'default'=> true,
        ],
        'reussites' => [
            'label'  => 'Réussites',
            'desc'   => 'Articles anonymes de victoires + page publique « Contactez-nous ».',
            'routes' => ['reussites', 'reussite-edit', 'reussite-article'],
            'default'=> true,
        ],
        'aide_jurid' => [
            'label'  => 'Aide juridictionnelle',
            'desc'   => 'Calculateur d\'éligibilité selon les revenus.',
            'routes' => ['aj-calcul'],
            'default'=> true,
        ],
        'appels_nmh' => [
            'label'  => 'Appels au bailleur',
            'desc'   => 'Journal d\'appels + rapports d\'incident.',
            'routes' => ['appels-nmh', 'appel-nmh-add', 'appel-nmh-edit', 'appel-nmh-rapport', 'appel-guide'],
            'default'=> true,
        ],
        'agenda' => [
            'label'  => 'Agenda / rendez-vous',
            'desc'   => 'Rendez-vous et interventions planifiées.',
            'routes' => ['agenda', 'rdv-add', 'rdv-edit'],
            'default'=> true,
        ],
        'tutoriels' => [
            'label'  => 'Tutoriels',
            'desc'   => 'Guides pratiques par problème (humidité, nuisibles…).',
            'routes' => ['tutoriels', 'tutoriel'],
            'default'=> true,
        ],
        'outils' => [
            'label'  => 'Outils scientifiques',
            'desc'   => 'Sonomètre, GPS, niveau, photo-preuve datée…',
            'routes' => ['outils', 'outil-sonometre', 'outil-niveau', 'outil-boussole', 'outil-gps', 'outil-photo-preuve', 'outil-humidite', 'outil-regle'],
            'default'=> true,
        ],
        'sms' => [
            'label'  => 'SMS',
            'desc'   => 'Envoi de SMS aux adhérents et aux locataires.',
            'routes' => ['sms', 'sms-locataires'],
            'default'=> true,
        ],
        'email_blast' => [
            'label'  => 'Email blast',
            'desc'   => 'Campagnes email à la liste.',
            'routes' => ['email'],
            'default'=> true,
        ],
        'alertes' => [
            'label'  => 'Alertes d\'accueil',
            'desc'   => 'Le bandeau « À faire » en haut de l\'accueil.',
            'routes' => [],
            'default'=> true,
        ],
        'assistant_ia' => [
            'label'  => 'Assistant IA',
            'desc'   => 'Bulle d\'aide à la rédaction et à la recherche.',
            'routes' => [],
            'default'=> true,
        ],
    ];
}

/** Un module est-il actif ? (option lfi_nct_modules, défaut du registre). */
function lfi_nct_module_enabled($key) {
    $reg = lfi_nct_modules_registry();
    if (!isset($reg[$key])) return true;
    if ($key === 'travaux' && defined('LFI_NCT_DISABLE_TRAVAUX') && LFI_NCT_DISABLE_TRAVAUX) return false;
    $opt = get_option('lfi_nct_modules', []);
    if (is_array($opt) && array_key_exists($key, $opt)) return (bool) $opt[$key];
    return !empty($reg[$key]['default']);
}

/* ============================================================== *
 *  VUE : page « Modules » (admins du groupe)                      *
 * ============================================================== */
function lfi_nct_app_view_modules_params() {
    if (!current_user_can('manage_options')) {
        lfi_nct_app_screen_open('Modules');
        echo '<div class="lfi-app-empty">Réservé aux administrateurs.</div>';
        lfi_nct_app_screen_close(false);
        return;
    }
    $reg = lfi_nct_modules_registry();

    if (!empty($_POST['lfi_modules_save']) && check_admin_referer('lfi_modules_save')) {
        $vals = [];
        foreach ($reg as $k => $m) $vals[$k] = !empty($_POST['mod'][$k]);
        update_option('lfi_nct_modules', $vals, false);
        wp_safe_redirect(lfi_nct_app_url('modules-params', ['saved' => 1]));
        exit;
    }

    lfi_nct_app_screen_open('🧩 Modules', 'Activez ou retirez les outils de votre groupe');
    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Modules mis à jour.');

    echo '<div class="lfi-app-help" style="background:#e8f0ff;border-left:4px solid #0066a3">Cochez les outils que votre groupe veut utiliser. Ceux que vous décochez <strong>disparaissent de l\'accueil</strong> et leurs pages sont fermées. Vous pouvez les réactiver à tout moment ici.</div>';

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_modules_save');
    echo '<input type="hidden" name="lfi_modules_save" value="1">';
    foreach ($reg as $k => $m) {
        $on = lfi_nct_module_enabled($k);
        echo '<label style="display:flex;gap:10px;align-items:flex-start;padding:10px;border:1px solid #eee;border-radius:8px;margin:6px 0;font-weight:400">';
        echo '<input type="checkbox" name="mod[' . esc_attr($k) . ']" value="1"' . ($on ? ' checked' : '') . ' style="margin-top:3px">';
        echo '<span><strong>' . esc_html($m['label']) . '</strong><br><span style="font-size:.88em;color:#555">' . esc_html($m['desc']) . '</span></span>';
        echo '</label>';
    }
    echo '<button type="submit" class="btn-primary big">💾 Enregistrer</button>';
    echo '</form>';

    lfi_nct_app_screen_close();
}
