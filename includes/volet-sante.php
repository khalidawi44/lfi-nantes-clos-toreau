<?php
/**
 * VOLET « SANTÉ PUBLIQUE » — national & européen.
 *
 * Volet SÉPARÉ (ni logement, ni protection de l'enfance). Porte le dossier
 * « puffs » (cigarettes électroniques jetables) : analyse, mécanique, et
 * propositions législatives à destination des député·es (national) et des
 * député·es européen·nes (échelon UE).
 *
 * PRINCIPE : « la preuve, pas la promesse ». Le cadre ci-dessous ne pose que
 * des FAITS PUBLICS vérifiables. L'analyse fine (mécanique du produit) et les
 * propositions de loi sont saisies par Fabrice (zones éditables) — rien n'est
 * inventé.
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_sante_can() { return current_user_can('manage_options'); }

/* -------------------------------------------------------------- *
 *  Dashboard du volet                                            *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_sante() {
    if (!lfi_nct_sante_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    lfi_nct_app_screen_open('🩺 Santé publique', 'Dossier « puffs » — national & européen · pour les député·es');
    echo '<div class="lfi-app-help" style="background:#eef4ff;border-left:4px solid #0066a3"><strong>Volet séparé.</strong> Un dossier de santé publique porté à l\'échelon <strong>national</strong> (député·es) et <strong>européen</strong> (député·es européen·nes). On documente, on chiffre quand c\'est sourcé, on propose du droit. Jamais de chiffre inventé.</div>';

    /* --- Cadre factuel (faits publics, vérifiables) --- */
    echo '<h3 style="margin:16px 0 6px;color:#c8102e">Le cadre factuel</h3>';
    echo '<ul class="lfi-app-list">';
    foreach ([
        ['Qu\'est-ce qu\'une « puff » ?', 'Une <strong>cigarette électronique jetable</strong>, à usage unique, souvent aromatisée et colorée, contenant de la nicotine et une <strong>batterie lithium intégrée non rechargeable</strong> (déchet électronique une fois vide).'],
        ['France — la loi', 'Une <strong>proposition de loi visant à interdire les puffs</strong> a été <strong>adoptée définitivement par le Parlement</strong> (Assemblée nationale et Sénat) début 2025. Son entrée en vigueur est soumise à la <strong>procédure de notification à la Commission européenne</strong> (TRIS), les puffs relevant du marché unique.'],
        ['Europe — le contexte', 'La <strong>Belgique</strong> est devenue, au 1<sup>er</sup> janvier 2025, le <strong>premier pays de l\'UE</strong> à interdire les cigarettes électroniques jetables. La <strong>directive sur les produits du tabac (TPD 2014/40/UE)</strong> encadre le sujet à l\'échelle européenne : c\'est le levier pour une <strong>interdiction harmonisée</strong> portée par les eurodéputé·es.'],
        ['Les enjeux', 'Santé des jeunes (produit d\'appel vers la nicotine, marketing ciblant les mineurs) · Environnement (batteries lithium et plastiques jetés) · Cohérence des politiques anti-tabac.'],
    ] as $it) {
        echo '<li class="lfi-app-card" style="border-left:4px solid #c8102e"><div class="head"><div class="who">' . esc_html($it[0]) . '</div></div><div class="com">' . wp_kses_post($it[1]) . '</div></li>';
    }
    echo '</ul>';
    echo '<div class="lfi-app-help"><small>⚠️ À vérifier avant diffusion : les dates exactes de promulgation / d\'entrée en vigueur (procédure UE en cours). On cite la source à chaque affirmation.</small></div>';

    /* --- Sous-parties éditables --- */
    $tiles = [
        ['🔬', 'Mon analyse & la mécanique', 'Le décorticage du produit (à saisir)', lfi_nct_app_url('sante-analyse')],
        ['⚖️', 'Mes propositions de loi',     'National + européen (à saisir)',       lfi_nct_app_url('sante-propositions')],
    ];
    echo '<div class="lfi-app-grid" style="margin-top:14px">';
    foreach ($tiles as $t) {
        echo '<a class="lfi-app-tile" href="' . esc_url($t[3]) . '"><div class="ico">' . $t[0] . '</div><div class="tit">' . esc_html($t[1]) . '</div><div class="sub">' . esc_html($t[2]) . '</div></a>';
    }
    echo '</div>';

    lfi_nct_app_screen_close();
}

/* -------------------------------------------------------------- *
 *  Zone éditable générique (analyse / propositions)              *
 * -------------------------------------------------------------- */
function lfi_nct_sante_editable($opt_key, $nonce, $route, $titre, $aide, $placeholder) {
    if (!lfi_nct_sante_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    if (!empty($_POST['lfi_sante_save']) && check_admin_referer($nonce)) {
        update_option($opt_key, wp_kses_post(wp_unslash($_POST['contenu'] ?? '')), false);
        wp_safe_redirect(lfi_nct_app_url($route, ['saved' => 1])); exit;
    }
    $raw = (string) get_option($opt_key, '');
    lfi_nct_app_screen_open($titre, 'Volet santé publique — zone rédactionnelle');
    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Enregistré.');
    echo '<div style="text-align:center;margin:4px 0 10px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('sante')) . '">← Retour au volet santé</a></div>';
    echo '<div class="lfi-app-help">' . wp_kses_post($aide) . '</div>';
    if ($raw !== '') {
        echo '<div class="lfi-app-card"><div class="com">' . wpautop(wp_kses_post($raw)) . '</div></div>';
    }
    echo '<form method="post" style="margin-top:8px">' . wp_nonce_field($nonce, '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_sante_save" value="1">';
    echo '<textarea name="contenu" rows="14" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px" placeholder="' . esc_attr($placeholder) . '">' . esc_textarea($raw) . '</textarea>';
    echo '<div style="margin-top:8px"><button type="submit" class="btn-primary">💾 Enregistrer</button></div></form>';
    lfi_nct_app_screen_close();
}

function lfi_nct_app_view_sante_analyse() {
    lfi_nct_sante_editable(
        'lfi_nct_sante_analyse', 'lfi_sante_analyse', 'sante-analyse',
        '🔬 Mon analyse & la mécanique',
        'Colle ici <strong>ton analyse de la puff</strong> : la mécanique du produit (batterie, dosage nicotine, arômes, marketing), ce que tu as observé. C\'est TA matière — elle nourrira le dossier envoyé aux député·es.',
        "Ex : composition, mécanique de la dépendance, ciblage marketing des mineurs, impact déchets…"
    );
}
function lfi_nct_app_view_sante_propositions() {
    lfi_nct_sante_editable(
        'lfi_nct_sante_propositions', 'lfi_sante_propositions', 'sante-propositions',
        '⚖️ Mes propositions de loi',
        'Rédige ici tes <strong>propositions</strong>, séparées par échelon : <strong>national</strong> (député·es) et <strong>européen</strong> (eurodéputé·es). Une mesure = un objectif clair + le levier juridique.',
        "Ex — National : … / Européen (révision TPD 2014/40/UE) : …"
    );
}
