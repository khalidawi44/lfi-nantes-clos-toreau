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

/**
 * Visuel « boîte noire » (SVG autonome) — MONTRE ce que l'outil fait et ce
 * qu'il gagne, JAMAIS comment il est construit.
 *
 * PROTECTION DU CONCEPT : le moteur (automatisation + IA) est volontairement
 * représenté comme une boîte scellée. Aucune « recette » technique n'est
 * divulguée (pas de service email, pas d'endpoint, pas de clé, pas de nom de
 * table, pas de brique réutilisable) — pour qu'on ne puisse pas le reproduire
 * de son côté sans l'auteur.
 */
function lfi_nct_demo_svg_schema() {
    $b = function ($x, $y, $w, $h, $ico, $title, $sub, $fill = '#f6f2fc', $stroke = '#6f4bb0') {
        $o  = '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="14" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="2"/>';
        $o .= '<text x="' . ($x + 16) . '" y="' . ($y + 27) . '" font-size="15.5" font-weight="800" fill="#3a2668">' . $ico . '  ' . esc_html($title) . '</text>';
        if ($sub !== '') $o .= '<text x="' . ($x + 16) . '" y="' . ($y + 47) . '" font-size="12.5" fill="#444">' . esc_html($sub) . '</text>';
        return $o;
    };
    $arrow = function ($x1, $y1, $x2, $y2, $col = '#6f4bb0') {
        return '<line x1="' . $x1 . '" y1="' . $y1 . '" x2="' . $x2 . '" y2="' . $y2 . '" stroke="' . $col . '" stroke-width="3" marker-end="url(#ar)"/>';
    };
    ob_start(); ?>
    <svg viewBox="0 0 900 760" width="100%" style="max-width:900px;display:block;margin:0 auto;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Schéma : de la porte du locataire à la victoire">
      <defs>
        <marker id="ar" markerWidth="11" markerHeight="11" refX="8" refY="3.2" orient="auto"><path d="M0,0 L9,3.2 L0,6.4 Z" fill="#6f4bb0"/></marker>
        <linearGradient id="core" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4b2e83"/><stop offset="1" stop-color="#8a5cd8"/></linearGradient>
        <linearGradient id="win" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1b7a43"/><stop offset="1" stop-color="#37a862"/></linearGradient>
      </defs>

      <!-- cadre cloisonnement (protection des locataires) -->
      <rect x="8" y="8" width="884" height="672" rx="18" fill="none" stroke="#c8102e" stroke-width="2" stroke-dasharray="8 7"/>
      <text x="24" y="705" font-size="12.5" font-weight="800" fill="#c8102e">🔒 Cloisonnement strict — chaque dossier est étanche, l'enquête de terrain n'est jamais partagée.</text>
      <text x="24" y="726" font-size="12" font-weight="700" fill="#7a6a99">© Concept propriétaire · Union des Quartiers Libres — reproduction et déploiement réservés à l'auteur.</text>

      <?php
      /* ENTRÉE — le terrain */
      echo $b(40, 34, 250, 64, '📋', 'Le terrain', 'On va vers les gens : porte-à-porte + signalement en ligne.', '#eef7ee', '#186a3b');
      echo $arrow(290, 66, 352, 66, '#186a3b');

      /* CŒUR SCELLÉ — la boîte noire (automatisation + IA) */
      echo '<rect x="352" y="26" width="308" height="150" rx="18" fill="url(#core)"/>';
      echo '<text x="506" y="58" text-anchor="middle" font-size="17" font-weight="900" fill="#fff">🔒 Le moteur</text>';
      echo '<text x="506" y="82" text-anchor="middle" font-size="13" fill="#efe7ff">Automatisation + intelligence</text>';
      echo '<text x="506" y="100" text-anchor="middle" font-size="13" fill="#efe7ff">artificielle</text>';
      echo '<text x="506" y="128" text-anchor="middle" font-size="12" fill="#d9c9ff" font-style="italic">Le savoir-faire — scellé.</text>';
      echo '<text x="506" y="150" text-anchor="middle" font-size="11.5" fill="#c7b3f5">Non divulgué · non reproductible sans l\'auteur</text>';
      echo $arrow(506, 176, 506, 210, '#6f4bb0');

      /* SORTIES — ce que ça produit, en éventail (le RÉSULTAT, pas la recette) */
      echo $b(40, 210, 250, 66, '🗂️', 'Le dossier se monte seul', 'Constat, photos datées, préjudice chiffré, base légale.');
      echo $b(325, 210, 250, 66, '⚔️', 'Deux batailles', '⚡ Faire cesser le danger, puis 💶 réparer le préjudice.', '#fff8e6', '#bd8600');
      echo $b(610, 210, 250, 66, '✉️', 'La correspondance se range', 'Les échanges avec le bailleur reviennent au bon endroit.', '#eaf2fb', '#0066a3');

      echo $b(40, 300, 250, 66, '⚖️', 'Amiable puis justice', 'Conciliation prête, puis avocats partenaires équipés.', '#f3e9fb', '#6a1b9a');
      echo $b(325, 300, 250, 66, '🏠', 'Relogement d\'urgence', 'Demande unique + DALO : le préfet a 6 mois pour reloger.', '#eaf2fb', '#0066a3');
      echo $b(610, 300, 250, 66, '💪', 'Le rapport de force', 'Élus, national, préfecture, presse, mobilisation.', '#fdeef0', '#c8102e');

      /* La récompense */
      echo $arrow(325, 388, 300, 402, '#1b7a43');
      echo $arrow(575, 388, 600, 402, '#1b7a43');
      echo '<rect x="230" y="402" width="440" height="86" rx="16" fill="url(#win)"/>';
      echo '<text x="450" y="436" text-anchor="middle" font-size="18" font-weight="900" fill="#fff">🏆 On gagne — et on le célèbre</text>';
      echo '<text x="450" y="462" text-anchor="middle" font-size="13" fill="#e6ffee">Une coupe par bataille gagnée · championnat de rapidité.</text>';
      echo '<text x="450" y="480" text-anchor="middle" font-size="12.5" fill="#d5f5df">Chaque victoire encourage tout le groupe.</text>';
      echo $arrow(450, 488, 450, 520, '#1b7a43');

      /* Réseau national */
      echo '<rect x="200" y="520" width="500" height="72" rx="16" fill="#3a2668"/>';
      echo '<text x="450" y="552" text-anchor="middle" font-size="16" font-weight="900" fill="#fff">🇫🇷 Reproductible dans chaque quartier</text>';
      echo '<text x="450" y="576" text-anchor="middle" font-size="12.5" fill="#d9c9ff">Un GA s\'en empare, configure son bailleur local — la machine se met en route.</text>';
      ?>
    </svg>
    <?php
    return ob_get_clean();
}

/** Chiffres AGRÉGÉS de TOUT le réseau (France entière), anonymes. */
function lfi_nct_demo_stats() {
    global $wpdb;
    /* Batailles gagnées = SOURCE DE VÉRITÉ = l'état réel de chaque dossier (la
       meta locataire, celle qui pilote les bandeaux « 🏆 gagné »). On lit donc
       les deux batailles gagnées, y compris les victoires posées AVANT le
       registre des coupes (ex. Gwen) — que l'ancien comptage oubliait. */
    $victoires = 0; $familles = [];
    foreach (['urgence', 'indemnisation'] as $bat) {
        $uids = get_users([
            'meta_query' => [['key' => 'lfi_nct_' . $bat . '_won', 'value' => '', 'compare' => '!=']],
            'fields'     => 'ID',
        ]);
        foreach ($uids as $uid) { $victoires++; $familles[(int) $uid] = 1; }
    }
    /* Réussites publiées (toutes). */
    $publiees = 0;
    if (function_exists('lfi_nct_reussites')) foreach (lfi_nct_reussites() as $r) if (!empty($r['publie'])) $publiees++;
    /* Locataires ayant demandé à être suivis — RÉSEAU ENTIER (recontact = oui). */
    $suivis = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lfi_nct_responses WHERE contact_recontact = 1 AND (deleted_at IS NULL)");
    return ['victoires' => $victoires, 'familles' => count($familles), 'publiees' => $publiees, 'suivis' => $suivis];
}

/* ============================================================== *
 *  KIT DE PRÉSENTATION NATIONAL (Mélenchon) — page écran + PDF     *
 * ============================================================== */
function lfi_nct_app_view_kit_national() {
    if (!current_user_can('manage_options') && !(function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga())) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    $st = lfi_nct_demo_stats();
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
        <div class="stat"><div class="n">🏠 <?php echo (int) $st['suivis']; ?></div><div class="l">Locataires qui demandent à être suivis</div></div>
        <div class="stat"><div class="n">🏆 <?php echo (int) $st['victoires']; ?></div><div class="l">Batailles gagnées</div></div>
        <div class="stat"><div class="n">📣 <?php echo (int) $st['publiees']; ?></div><div class="l">Victoires publiées</div></div>
      </div>
      <p style="color:#888;font-size:.85em;margin-top:12px">Totaux <strong>France entière</strong> (tout le réseau) · données strictement anonymes.</p>
    </section>

    <section class="slide">
      <h2>Le constat</h2>
      <p class="lead">Des familles vivent dans des logements indignes (moisissures, nuisibles, coupures d'eau chaude, insalubrité) face à des bailleurs qui traînent. Isolées, elles renoncent. <strong>Notre pari : outiller le collectif pour rétablir le rapport de force, dossier par dossier.</strong></p>
    </section>

    <section class="slide">
      <h2>Comment ça marche — en une image</h2>
      <p class="lead">Pas besoin d'être informaticien. C'est de l'<strong>automatisation avec de l'intelligence artificielle</strong> : on récolte ce qui vient du terrain, un « moteur » fait le travail répétitif tout seul (monter le dossier, ranger les courriers, repérer une victoire…), et il ne reste qu'à pousser jusqu'au bout.</p>
      <?php echo lfi_nct_demo_svg_schema(); ?>
      <p style="color:#6a1b9a;font-weight:700;margin-top:12px">👉 Comme un assistant infatigable : il prépare tout, l'humain décide et gagne.</p>
      <p style="color:#7a6a99;font-size:.85em;margin-top:6px">Le cœur du moteur — le « comment » exact — reste notre savoir-faire. Il n'est pas divulgué et ne peut pas être reproduit sans son auteur.</p>
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

/** Uid du compte démo Bompard (0 si absent). */
function lfi_nct_demo_bompard_uid() {
    $r = get_users(['meta_key' => 'lfi_nct_partner_seed', 'meta_value' => 'bompard', 'number' => 1, 'fields' => 'ID']);
    return $r ? (int) $r[0] : 0;
}

/**
 * HUB DÉPLOIEMENT NATIONAL (réservé à toi) — le point d'entrée des deux espaces :
 *   🧑‍🏫 Manuel Bompard : espace CONNECTÉ « découverte » (il se connecte et explore).
 *   🎤 Jean-Luc Mélenchon : PRÉSENTATION écran / PDF (tu la montres en personne).
 * Ici : aperçu, lien de connexion Bompard, ouverture de la présentation.
 */
function lfi_nct_app_view_demo_hub() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    /* Génération à la demande du lien de connexion de Bompard (usage unique). */
    $link = '';
    $buid = lfi_nct_demo_bompard_uid();
    if (!empty($_POST['lfi_demo_genlink']) && check_admin_referer('lfi_demo_genlink') && $buid && function_exists('lfi_nct_login_link')) {
        $link = lfi_nct_login_link($buid, lfi_nct_app_url());
    }

    lfi_nct_app_screen_open('🇫🇷 Déploiement national', 'Présenter l\'outil — Manuel Bompard & Jean-Luc Mélenchon');

    echo '<div class="lfi-app-help" style="background:#f6f2fc;border-left:4px solid #6f4bb0;padding:10px 12px;border-radius:8px;margin-bottom:14px">';
    echo '🔒 <strong>Concept protégé.</strong> Ces deux espaces <strong>montrent</strong> ce que l\'outil fait et gagne, mais jamais <em>comment il est fabriqué</em> — le moteur reste ton savoir-faire, non reproductible sans toi.';
    echo '</div>';

    /* ── Carte Bompard ──────────────────────────────────────────── */
    echo '<div class="lfi-app-card" style="border:2px solid #6f4bb0;margin-bottom:14px">';
    echo '<div class="head"><div class="who">🧑‍🏫 Manuel Bompard — espace connecté</div><div class="badge" style="background:#6f4bb0;color:#fff">découverte</div></div>';
    echo '<div style="padding:4px 2px 0"><p style="margin:6px 0;color:#333">Il se connecte à <strong>son</strong> espace et explore lui-même : chiffres du réseau (anonymes), le visuel « boîte noire », la chaîne complète, les victoires. Aucune donnée réelle de locataire.</p>';
    if ($buid) {
        echo '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px">';
        echo '<a class="btn-primary" style="background:#6f4bb0" href="' . esc_url(lfi_nct_app_url('demo-preview')) . '">👁 Prévisualiser son espace</a>';
        echo '<form method="post" style="margin:0">' . wp_nonce_field('lfi_demo_genlink', '_wpnonce', true, false) . '<input type="hidden" name="lfi_demo_genlink" value="1"><button type="submit" class="btn-ghost">🔗 Générer son lien de connexion</button></form>';
        echo '</div>';
        if ($link) {
            echo '<div style="margin-top:10px;padding:10px;background:#eef7ee;border-radius:8px">';
            echo '<div style="font-weight:800;color:#186a3b;font-size:.85em">Lien de connexion direct (usage unique) — à lui envoyer :</div>';
            echo '<textarea readonly onclick="this.select()" style="width:100%;box-sizing:border-box;margin-top:6px;font-size:.8em;padding:6px;border-radius:6px;border:1px solid #cbd5c0">' . esc_textarea($link) . '</textarea>';
            echo '</div>';
        }
    } else {
        echo '<p style="color:#c8102e;margin-top:8px">Le compte Bompard n\'est pas encore créé (il se crée automatiquement au prochain chargement).</p>';
    }
    echo '</div></div>';

    /* ── Carte Mélenchon ────────────────────────────────────────── */
    echo '<div class="lfi-app-card" style="border:2px solid #c8102e;margin-bottom:14px">';
    echo '<div class="head"><div class="who">🎤 Jean-Luc Mélenchon — présentation</div><div class="badge" style="background:#c8102e;color:#fff">écran / PDF</div></div>';
    echo '<div style="padding:4px 2px 0"><p style="margin:6px 0;color:#333">Une présentation <strong>simple et imagée</strong> — pas besoin d\'être informaticien : « automatisation avec de l\'intelligence artificielle », un assistant infatigable. À montrer en personne ou à <strong>enregistrer en PDF</strong>.</p>';
    echo '<a class="btn-primary" style="background:#c8102e;margin-top:6px" href="' . esc_url(lfi_nct_app_url('kit-national')) . '" target="_blank" rel="noopener">▶ Ouvrir la présentation (nouvel onglet)</a>';
    echo '</div></div>';

    lfi_nct_app_screen_close();
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

    /* Chiffres AGRÉGÉS et anonymes — RÉSEAU ENTIER (France). */
    $st = lfi_nct_demo_stats();

    $is_preview = current_user_can('manage_options');
    lfi_nct_app_screen_open('👋 Bienvenue, ' . ($is_preview ? 'Manuel' : $prenom), 'Découverte de l\'outil — défense des locataires');

    if ($is_preview) {
        echo '<div class="lfi-app-help" style="background:#fff8e6;border-left:4px solid #bd8600;padding:8px 12px;border-radius:8px;margin-bottom:12px">👁 <strong>Aperçu</strong> — voici exactement ce que voit Manuel Bompard en se connectant. <a href="' . esc_url(lfi_nct_app_url('demo-national')) . '">← retour au hub</a></div>';
    }

    /* Hero. */
    echo '<div style="background:linear-gradient(135deg,#4b2e83,#6f4bb0);color:#fff;border-radius:16px;padding:22px 20px;text-align:center">';
    echo '<div style="font-size:1.5em;font-weight:900;line-height:1.15">Un outil qui transforme un Groupe d\'Action<br>en machine à défendre les locataires</div>';
    echo '<div style="opacity:.95;margin-top:8px;max-width:620px;margin-left:auto;margin-right:auto">Conçu et éprouvé au Clos Toreau (Nantes Sud). <strong>Reproductible dans chaque quartier, chaque ville, chaque GA.</strong> Voici, en 3 minutes, ce qu\'il permet.</div>';
    echo '</div>';

    /* En chiffres (anonymes). */
    echo '<div class="lfi-app-stats-grid" style="margin:14px 0">';
    echo '<div class="stat"><div class="ico">🏠</div><div class="n">' . (int) $st['suivis'] . '</div><div class="l">Locataires suivis (France)</div></div>';
    echo '<div class="stat"><div class="ico">🏆</div><div class="n">' . (int) $st['victoires'] . '</div><div class="l">Batailles gagnées</div></div>';
    echo '<div class="stat"><div class="ico">📣</div><div class="n">' . (int) $st['publiees'] . '</div><div class="l">Victoires publiées</div></div>';
    echo '</div>';
    echo '<div class="lfi-app-help" style="text-align:center"><small>Totaux France entière (tout le réseau). Aucune donnée personnelle n\'est visible ici — tout est anonyme.</small></div>';

    /* Le visuel « boîte noire » — impressionne sans livrer la recette. */
    echo '<h3 style="margin:18px 0 6px;color:#4b2e83">🧠 Comment ça marche, en un coup d\'œil</h3>';
    echo '<p style="color:#444;margin:0 0 10px;font-size:.95em">En clair : c\'est de l\'<strong>automatisation dopée à l\'intelligence artificielle</strong>. On récolte le terrain, un moteur fait le gros du travail, et il ne reste qu\'à pousser jusqu\'à la victoire. <em>Le cœur du moteur reste notre savoir-faire.</em></p>';
    echo lfi_nct_demo_svg_schema();

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
