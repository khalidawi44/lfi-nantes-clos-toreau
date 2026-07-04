<?php
/**
 * ESPACE DÉMO NATIONAL — pour Manuel Bompard (coordinateur LFI).
 *
 * Un compte dédié + un tableau de bord « découverte » : ludique, instructif,
 * qui montre la PUISSANCE de l'outil et son caractère REPRODUCTIBLE partout.
 *
 * RÈGLE ABSOLUE : aucune donnée réelle de locataire (nom, enquête, dossier) —
 * uniquement des CHIFFRES AGRÉGÉS et des VICTOIRES DÉJÀ ANONYMISÉES. La démo
 * illustre le mécanisme, jamais un vrai dossier.
 */
if (!defined('ABSPATH')) exit;

/* Seed du compte Manuel Bompard (partenaire + drapeau démo). */
add_action('init', 'lfi_nct_demo_seed_bompard', 12);
function lfi_nct_demo_seed_bompard() {
    if (get_option('lfi_nct_demo_bompard_done')) return;
    if (!defined('LFI_NCT_ROLE_PARTNER') || !get_role(LFI_NCT_ROLE_PARTNER)) return;
    $already = get_users(['meta_key' => 'lfi_nct_partner_seed', 'meta_value' => 'bompard', 'number' => 1, 'fields' => 'ID']);
    if (!empty($already)) { update_user_meta((int) $already[0], 'lfi_nct_demo_national', 1); update_option('lfi_nct_demo_bompard_done', 1, false); return; }
    $login = username_exists('manuel.bompard') ? 'manuel.bompard.lfi' : 'manuel.bompard';
    $email = 'manuel.bompard@partenaire.example';
    if (username_exists($login) || email_exists($email)) { update_option('lfi_nct_demo_bompard_done', 1, false); return; }
    $uid = wp_insert_user([
        'user_login'   => $login,
        'user_email'   => $email,
        'user_pass'    => wp_generate_password(16),
        'display_name' => 'Manuel Bompard',
        'first_name'   => 'Manuel',
        'last_name'    => 'Bompard',
        'role'         => LFI_NCT_ROLE_PARTNER,
    ]);
    if (!is_wp_error($uid)) {
        update_user_meta($uid, 'lfi_nct_partner_seed', 'bompard');
        update_user_meta($uid, 'lfi_nct_demo_national', 1);
    }
    update_option('lfi_nct_demo_bompard_done', 1, false);
}

/** Le compte courant est-il un espace démo ? */
function lfi_nct_is_demo_user($uid = 0) {
    $uid = $uid ?: get_current_user_id();
    return $uid && get_user_meta((int) $uid, 'lfi_nct_demo_national', true);
}

/** Dispatch : un compte démo ne voit QUE le tableau de bord découverte. */
function lfi_nct_demo_dispatch() {
    if (current_user_can('manage_options')) return false; /* toi : console normale */
    if (!lfi_nct_is_demo_user()) return false;
    $vue = isset($_GET['vue']) ? sanitize_key($_GET['vue']) : '';
    if ($vue === 'mon-profil')  { lfi_nct_app_view_mon_profil(); return true; }
    if ($vue === 'installer')   { lfi_nct_app_view_installer();  return true; }
    lfi_nct_app_view_demo_national();
    return true;
}

/** Tableau de bord DÉMO — la visite guidée. */
function lfi_nct_app_view_demo_national() {
    $me = wp_get_current_user();
    $prenom = $me->first_name ?: $me->display_name;

    /* Chiffres AGRÉGÉS et anonymes. */
    $vs = function_exists('lfi_nct_victoires_stats') ? lfi_nct_victoires_stats('clos-toreau') : ['coupes' => 0, 'familles' => 0];
    $pub = function_exists('lfi_nct_reussites_count_published') ? lfi_nct_reussites_count_published('clos-toreau') : 0;

    lfi_nct_app_screen_open('👋 Bienvenue, ' . $prenom, 'Découverte de l\'outil — défense des locataires');

    /* Hero. */
    echo '<div style="background:linear-gradient(135deg,#4b2e83,#6f4bb0);color:#fff;border-radius:16px;padding:22px 20px;text-align:center">';
    echo '<div style="font-size:1.5em;font-weight:900;line-height:1.15">Un outil qui transforme un Groupe d\'Action<br>en machine à défendre les locataires</div>';
    echo '<div style="opacity:.95;margin-top:8px;max-width:620px;margin-left:auto;margin-right:auto">Conçu et éprouvé au Clos Toreau (Nantes Sud). <strong>Reproductible dans chaque quartier, chaque ville, chaque GA.</strong> Voici, en 3 minutes, ce qu\'il permet.</div>';
    echo '</div>';

    /* En chiffres (anonymes). */
    echo '<div class="lfi-app-stats-grid" style="margin:14px 0">';
    echo '<div class="stat"><div class="ico">🏆</div><div class="n">' . (int) $vs['coupes'] . '</div><div class="l">Batailles gagnées</div></div>';
    echo '<div class="stat"><div class="ico">👪</div><div class="n">' . (int) $vs['familles'] . '</div><div class="l">Familles aidées</div></div>';
    echo '<div class="stat"><div class="ico">📣</div><div class="n">' . (int) $pub . '</div><div class="l">Victoires publiées</div></div>';
    echo '</div>';
    echo '<div class="lfi-app-help" style="text-align:center"><small>Chiffres d\'un seul GA. Aucune donnée personnelle n\'est visible ici — tout est anonyme.</small></div>';

    /* La chaîne complète — visite guidée (capacités, pas de données). */
    $etapes = [
        ['📋', '1 · On va vers les gens', 'Enquête en porte-à-porte + signalement en ligne. Chaque réponse crée automatiquement un compte locataire et un dossier.'],
        ['🗂️', '2 · Un dossier qui se monte seul', 'Constat, photos horodatées classées par date de prise de vue, préjudice chiffré, base légale, chronologie fiable.'],
        ['⚔️', '3 · Deux batailles', '⚡ Urgence (faire cesser le danger) puis 💶 indemnisation (réparer le préjudice). Chaque victoire = une « coupe ».'],
        ['✉️', '4 · Correspondance qui remonte seule', 'Les emails avec le bailleur arrivent automatiquement dans le bon dossier (tri par nom / adresse), avec verrou « mandat » avant tout courrier.'],
        ['⚖️', '5 · Amiable puis juridique', 'Commission de conciliation montée toute prête, puis avocats partenaires avec leur espace + jurisprudence Judilibre intégrée.'],
        ['🏠', '6 · Relogement d\'urgence', 'Demande unique de logement social + DALO : le préfet a 6 mois pour reloger. L\'outil génère la saisine.'],
        ['💪', '7 · Le rapport de force', 'Élus municipaux, relais national (députés), interlocuteurs préfecture, SCHS/ARS, presse, mobilisation collective — le juridique dans une stratégie d\'ensemble.'],
        ['🤝', '8 · Le collectif s\'organise', 'Coordination des créneaux, votes, événements, victoires célébrées — le GA vit et se motive.'],
    ];
    echo '<h3 style="margin:16px 0 8px;color:#4b2e83">🧭 La chaîne complète, de la porte au tribunal</h3>';
    echo '<div style="display:flex;flex-direction:column;gap:8px">';
    foreach ($etapes as $e) {
        echo '<div style="display:flex;gap:12px;background:#f6f2fc;border-left:4px solid #6f4bb0;border-radius:10px;padding:12px 14px">';
        echo '<div style="font-size:1.7em;line-height:1">' . $e[0] . '</div>';
        echo '<div><div style="font-weight:800;color:#4b2e83">' . esc_html($e[1]) . '</div><div style="font-size:.92em;color:#333;margin-top:2px">' . esc_html($e[2]) . '</div></div>';
        echo '</div>';
    }
    echo '</div>';

    /* Nos victoires — le tableau anonyme (ludique). */
    echo '<h3 style="margin:18px 0 8px;color:#186a3b">🏆 Ce qu\'on a déjà obtenu (anonyme)</h3>';
    echo function_exists('lfi_nct_tableau_reussites_shortcode') ? lfi_nct_tableau_reussites_shortcode([]) : do_shortcode('[lfi_nct_tableau_reussites]');

    /* Pourquoi déployer partout. */
    echo '<div style="margin-top:18px;background:#111;color:#fff;border-radius:16px;padding:20px;text-align:center">';
    echo '<div style="font-weight:900;font-size:1.2em">Reproductible partout, dès demain</div>';
    echo '<div style="opacity:.92;margin-top:8px;max-width:640px;margin-left:auto;margin-right:auto">Un GA s\'en empare, configure son bailleur local, et toute la mécanique se met en place : enquête, dossiers, juridique, relogement, coordination. <strong>Chaque quartier peut avoir sa machine à gagner.</strong></div>';
    echo '</div>';

    echo '<div class="lfi-app-help" style="margin-top:12px;text-align:center"><small>🔒 Démo : les dossiers réels et l\'enquête terrain ne sont jamais accessibles ici — protection absolue des locataires (RGPD).</small></div>';

    lfi_nct_app_screen_close();
}
