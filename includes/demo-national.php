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

/* ============================================================== *
 *  KIT DE PRÉSENTATION NATIONAL (Mélenchon) — page écran + PDF     *
 * ============================================================== */
function lfi_nct_app_view_kit_national() {
    if (!current_user_can('manage_options') && !(function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga())) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    $vs  = function_exists('lfi_nct_victoires_stats') ? lfi_nct_victoires_stats('clos-toreau') : ['coupes' => 0, 'familles' => 0];
    $pub = function_exists('lfi_nct_reussites_count_published') ? lfi_nct_reussites_count_published('clos-toreau') : 0;
    $logos = function_exists('lfi_nct_signature_logos_html') ? lfi_nct_signature_logos_html('avocat', 'center') : '';

    /* Exemples RÉELS mais ANONYMES (réussites publiées). */
    $ex = [];
    if (function_exists('lfi_nct_reussites')) {
        foreach (lfi_nct_reussites() as $r) {
            if (empty($r['publie'])) continue;
            $ti = (string) ($r['titre'] ?? '');
            if (function_exists('lfi_nct_reussite_flag_names') && lfi_nct_reussite_flag_names($ti)) $ti = 'Une victoire obtenue pour une famille du quartier';
            $ex[] = $ti;
            if (count($ex) >= 4) break;
        }
    }

    $etapes = [
        ['📋', 'On va vers les gens', 'Enquête en porte-à-porte + signalement en ligne → compte locataire et dossier créés automatiquement.'],
        ['🗂️', 'Un dossier qui se monte seul', 'Constat, photos horodatées classées par date de prise de vue, préjudice chiffré, base légale.'],
        ['⚔️', 'Deux batailles', '⚡ Urgence (faire cesser le danger) puis 💶 indemnisation. Chaque victoire = une coupe.'],
        ['✉️', 'La correspondance remonte seule', 'Les emails avec le bailleur se rangent tout seuls dans le bon dossier. Verrou « mandat » avant tout courrier.'],
        ['⚖️', 'Amiable puis juridique', 'Commission de conciliation prête ; avocats partenaires avec espace dédié + jurisprudence Judilibre intégrée.'],
        ['🏠', 'Relogement d\'urgence', 'Demande unique + DALO : le préfet a 6 mois pour reloger. La saisine est générée.'],
        ['💪', 'Le rapport de force', 'Élus municipaux, relais national, préfecture, SCHS/ARS, presse, mobilisation collective.'],
        ['🤝', 'Le collectif s\'organise', 'Coordination, votes, événements, victoires célébrées : le GA vit et se motive.'],
    ];

    nocache_headers();
    ?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Défendre les locataires, partout — présentation</title>
    <style>
      :root{--r:#c8102e;--v:#4b2e83;--g:#186a3b}
      *{box-sizing:border-box}
      body{font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#16121f;margin:0;background:#f4f1fa}
      .slide{max-width:900px;margin:0 auto;background:#fff;padding:44px 40px;min-height:auto;box-shadow:0 6px 24px rgba(0,0,0,.08);margin-bottom:22px;border-radius:14px}
      h1{font-size:2.2em;line-height:1.1;margin:6px 0 8px;color:var(--v)}
      h2{font-size:1.5em;color:var(--r);margin:0 0 14px;border-bottom:3px solid var(--r);padding-bottom:8px}
      .lead{font-size:1.15em;color:#444;line-height:1.6}
      .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:12px}
      .stat{background:#f6f2fc;border-radius:12px;padding:16px;text-align:center;border-top:5px solid var(--v)}
      .stat .n{font-size:2.4em;font-weight:900;color:var(--v)}
      .stat .l{color:#555;font-weight:600;font-size:.9em}
      .step{display:flex;gap:14px;padding:12px 0;border-bottom:1px solid #eee}
      .step .i{font-size:1.8em;line-height:1}
      .step b{color:var(--v)}
      ul{line-height:1.7}
      .cta{background:#111;color:#fff;border-radius:14px;padding:22px;text-align:center}
      .noprint{position:sticky;top:0;background:#4b2e83;color:#fff;text-align:center;padding:10px;z-index:5}
      .btn{background:#fff;color:#4b2e83;border:0;padding:10px 22px;border-radius:10px;font-weight:800;cursor:pointer}
      @media print{ .noprint{display:none} body{background:#fff} .slide{box-shadow:none;margin:0;border-radius:0;page-break-after:always;min-height:96vh} }
    </style></head><body>
    <div class="noprint">Présentation à l'écran — ou <button class="btn" onclick="window.print()">🖨️ Enregistrer en PDF</button></div>

    <section class="slide" style="text-align:center">
      <?php echo $logos; ?>
      <div style="letter-spacing:2px;text-transform:uppercase;color:#888;font-weight:700;font-size:.8em">La France Insoumise · Union des Quartiers Libres</div>
      <h1>Défendre les locataires, partout</h1>
      <p class="lead">Un outil de terrain né au Clos Toreau (Nantes Sud). De la porte du locataire jusqu'au tribunal — et <strong>reproductible dans chaque quartier de France</strong>.</p>
      <div class="grid">
        <div class="stat"><div class="n">🏆 <?php echo (int) $vs['coupes']; ?></div><div class="l">Batailles gagnées</div></div>
        <div class="stat"><div class="n">👪 <?php echo (int) $vs['familles']; ?></div><div class="l">Familles aidées</div></div>
        <div class="stat"><div class="n">📣 <?php echo (int) $pub; ?></div><div class="l">Victoires publiées</div></div>
      </div>
      <p style="color:#888;font-size:.85em;margin-top:12px">Chiffres d'un seul GA · données strictement anonymes.</p>
    </section>

    <section class="slide">
      <h2>Le constat</h2>
      <p class="lead">Des familles vivent dans des logements indignes (moisissures, nuisibles, coupures d'eau chaude, insalubrité) face à des bailleurs qui traînent. Isolées, elles renoncent. <strong>Notre pari : outiller le collectif pour rétablir le rapport de force, dossier par dossier.</strong></p>
    </section>

    <section class="slide">
      <h2>La chaîne complète — de la porte au tribunal</h2>
      <?php foreach ($etapes as $e): ?>
        <div class="step"><div class="i"><?php echo $e[0]; ?></div><div><b><?php echo esc_html($e[1]); ?></b><br><span style="color:#333"><?php echo esc_html($e[2]); ?></span></div></div>
      <?php endforeach; ?>
    </section>

    <section class="slide">
      <h2>Les mécaniques de force</h2>
      <p class="lead">Le juridique ne marche que dans une stratégie d'ensemble qui pèse sur le bailleur <em>avant</em> l'audience :</p>
      <ul>
        <li><strong>Local</strong> : élus municipaux (audit de la gestion du bailleur), relais au conseil d'administration du bailleur.</li>
        <li><strong>Institutions</strong> : interlocuteurs préfecture, SCHS / ARS (insalubrité), DALO (relogement d'urgence).</li>
        <li><strong>National</strong> : députés (questions écrites, propositions de loi logement).</li>
        <li><strong>Populaire</strong> : presse locale, mobilisation collective, entraide de quartier.</li>
      </ul>
    </section>

    <section class="slide">
      <h2>Des résultats concrets (anonymes)</h2>
      <?php if ($ex): ?><ul><?php foreach ($ex as $t): ?><li><?php echo esc_html($t); ?></li><?php endforeach; ?></ul>
      <?php else: ?><p class="lead">Les premières victoires sont documentées et publiées, sans aucun nom.</p><?php endif; ?>
      <p style="color:#186a3b;font-weight:800;margin-top:10px">La preuve, pas la promesse.</p>
    </section>

    <section class="slide">
      <h2>L'ambition nationale</h2>
      <p class="lead">Un GA s'en empare, configure son bailleur local, et toute la mécanique se met en place. <strong>Chaque quartier peut avoir sa machine à gagner.</strong></p>
      <div class="cta"><div style="font-weight:900;font-size:1.25em">Déployons-le partout</div><div style="opacity:.9;margin-top:6px">Un outil, mille quartiers. La France Insoumise, présente et utile, du porte-à-porte au tribunal.</div></div>
      <p style="text-align:center;color:#888;font-size:.85em;margin-top:14px">🔒 Protection absolue des locataires : aucune donnée personnelle n'est jamais exposée.</p>
    </section>
    </body></html><?php
    exit;
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
