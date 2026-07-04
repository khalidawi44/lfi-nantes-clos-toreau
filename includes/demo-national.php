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

/* Migration : remplace l'email placeholder de Bompard par son VRAI email de
   député (vérifié sur assemblee-nationale.fr) + casquette « national ». */
add_action('init', 'lfi_nct_demo_fix_bompard_email', 13);
function lfi_nct_demo_fix_bompard_email() {
    if (get_option('lfi_nct_demo_bompard_email_fixed')) return;
    $buid = function_exists('lfi_nct_demo_bompard_uid') ? lfi_nct_demo_bompard_uid() : 0;
    if (!$buid) return;
    $real = 'manuel.bompard@assemblee-nationale.fr';
    $cur  = get_userdata($buid);
    if ($cur && strpos((string) $cur->user_email, '@partenaire.example') !== false && !email_exists($real)) {
        wp_update_user(['ID' => $buid, 'user_email' => $real]);
    }
    if (function_exists('lfi_nct_partner_levels_save')) lfi_nct_partner_levels_save($buid, ['national']);
    update_option('lfi_nct_demo_bompard_email_fixed', 1, false);
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

/**
 * Graphe de NŒUDS technique (SVG) — pour la présentation DÉTAILLÉE (Bompard).
 * Des sphères reliées, étiquetées de termes précis (IMAP/POP3, géocodage,
 * anti-doublon, NLP, EXIF, cloisonnement…) : montre la complexité et la
 * puissance de la chaîne. Le cœur (moteur) reste central mais scellé.
 */
function lfi_nct_demo_svg_graph() {
    /* [x, y, r, icône, titre, sous-titre, type] — type: 'core' | 'tech' | 'humain'. */
    $N = [
        'engine'  => [500, 350, 58, '🧠', 'Moteur', 'automatisation + IA', 'core'],
        /* — Les HUMAINS (militant·es, locataire, avocat, élu·es, collectif) — */
        'militant'=> [150, 130, 46, '🧑‍🤝‍🧑', 'Militant·es', 'vont vers les gens', 'humain'],
        'locataire'=>[150, 570, 46, '🧑', 'Locataire', 'signale · dépose · signe', 'humain'],
        'collectif'=>[500, 610, 48, '✊', 'Le collectif (GA)', 'décide · se mobilise', 'humain'],
        'avocat'  => [850, 560, 44, '👩‍⚖️', 'Avocat·e', 'espace cloisonné', 'humain'],
        'elus'    => [860, 130, 44, '🏛️', 'Élu·es', 'rapport de force', 'humain'],
        /* — Les MÉCANISMES techniques — */
        'geo'     => [300, 210, 40, '🛰️', 'Géocodage', 'BAN · Nominatim', 'tech'],
        'auto'    => [300, 490, 40, '🧩', 'Auto-création', 'compte ⋈ dossier', 'tech'],
        'imap'    => [470, 150, 40, '📡', 'IMAP / POP3', 'ingestion mail', 'tech'],
        'tri'     => [640, 180, 38, '🔀', 'Tri', 'routage par identité', 'tech'],
        'dedup'   => [720, 300, 38, '🧬', 'Anti-doublon', 'Message-ID', 'tech'],
        'nlp'     => [700, 440, 40, '🏆', 'Détection victoire', 'analyse NLP', 'tech'],
        'exif'    => [360, 360, 36, '🕑', 'EXIF', 'horodatage', 'tech'],
        'cloison' => [500, 490, 42, '🔒', 'Cloisonnement', 'tenant_user_id', 'tech'],
        'mandat'  => [620, 560, 36, '✍️', 'Verrou mandat', 'signature requise', 'tech'],
        'reseau'  => [700, 100, 38, '🇫🇷', 'Agrégation', 'compteurs réseau', 'tech'],
    ];
    $E = [
        ['militant', 'geo'], ['militant', 'engine'], ['locataire', 'auto'], ['locataire', 'exif'], ['locataire', 'engine'],
        ['geo', 'auto'], ['geo', 'engine'], ['auto', 'engine'], ['auto', 'cloison'],
        ['imap', 'tri'], ['imap', 'engine'], ['tri', 'dedup'], ['dedup', 'nlp'], ['dedup', 'engine'],
        ['nlp', 'engine'], ['nlp', 'reseau'], ['exif', 'engine'], ['exif', 'cloison'],
        ['cloison', 'engine'], ['mandat', 'engine'], ['mandat', 'avocat'],
        ['reseau', 'elus'], ['reseau', 'engine'], ['avocat', 'engine'], ['elus', 'engine'],
        ['collectif', 'engine'], ['collectif', 'locataire'], ['collectif', 'militant'],
    ];
    ob_start(); ?>
    <svg viewBox="0 0 1000 680" width="100%" style="max-width:1000px;display:block;margin:0 auto;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Galaxie des mécanismes et des humains">
      <defs>
        <radialGradient id="space" cx="50%" cy="42%" r="75%"><stop offset="0" stop-color="#2b2b36"/><stop offset="100%" stop-color="#0e0e15"/></radialGradient>
        <radialGradient id="sph" cx="34%" cy="30%" r="78%"><stop offset="0" stop-color="#f3f3f6"/><stop offset="42%" stop-color="#b9b9c4"/><stop offset="100%" stop-color="#5c5c6b"/></radialGradient>
        <radialGradient id="sphH" cx="34%" cy="30%" r="78%"><stop offset="0" stop-color="#fbf6ec"/><stop offset="45%" stop-color="#cfc3a8"/><stop offset="100%" stop-color="#6b5a3a"/></radialGradient>
        <radialGradient id="sphC" cx="34%" cy="30%" r="80%"><stop offset="0" stop-color="#eef0fb"/><stop offset="45%" stop-color="#a9adcf"/><stop offset="100%" stop-color="#4a4a63"/></radialGradient>
        <radialGradient id="halo" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#ffffff" stop-opacity=".22"/><stop offset="100%" stop-color="#ffffff" stop-opacity="0"/></radialGradient>
      </defs>
      <rect x="0" y="0" width="1000" height="680" rx="16" fill="url(#space)"/>
      <?php
      /* Poussière d'étoiles (positions fixes, déterministes). */
      $stars = [[60,80],[180,40],[320,70],[540,50],[760,60],[900,100],[950,300],[880,470],[930,600],[720,640],[500,660],[260,640],[80,520],[40,300],[120,220],[420,110],[610,90],[820,260],[560,300],[400,540],[240,360],[680,520],[860,360],[300,300]];
      foreach ($stars as $i => $s) { $rr = 0.6 + ($i % 3) * 0.5; echo '<circle cx="' . $s[0] . '" cy="' . $s[1] . '" r="' . $rr . '" fill="#ffffff" fill-opacity="' . (0.35 + ($i % 4) * 0.12) . '"/>'; }
      /* Arêtes = filaments de la galaxie. */
      foreach ($E as $e) {
          $a = $N[$e[0]]; $b = $N[$e[1]];
          echo '<line x1="' . $a[0] . '" y1="' . $a[1] . '" x2="' . $b[0] . '" y2="' . $b[1] . '" stroke="#c9c9e0" stroke-width="1.2" stroke-opacity=".28"/>';
      }
      /* Sphères. */
      foreach ($N as $n) {
          list($x, $y, $r, $ico, $title, $sub, $type) = $n;
          $grad = $type === 'humain' ? 'sphH' : ($type === 'core' ? 'sphC' : 'sph');
          echo '<circle cx="' . $x . '" cy="' . $y . '" r="' . ($r + 8) . '" fill="url(#halo)"/>';
          echo '<circle cx="' . $x . '" cy="' . $y . '" r="' . $r . '" fill="url(#' . $grad . ')" stroke="' . ($type === 'humain' ? '#d8c79a' : '#3a3a48') . '" stroke-width="1"/>';
          echo '<text x="' . $x . '" y="' . ($y + 8) . '" text-anchor="middle" font-size="' . ($r > 50 ? 27 : 22) . '">' . $ico . '</text>';
          echo '<text x="' . $x . '" y="' . ($y + $r + 17) . '" text-anchor="middle" font-size="13.5" font-weight="800" fill="#eef0f6">' . esc_html($title) . '</text>';
          echo '<text x="' . $x . '" y="' . ($y + $r + 33) . '" text-anchor="middle" font-size="11.5" fill="#b9b9cc" font-style="italic">' . esc_html($sub) . '</text>';
      }
      ?>
      <text x="500" y="666" text-anchor="middle" font-size="12.5" font-weight="800" fill="#e6a3ae">🔒 Les militant·es et les locataires au cœur · l'humain décide, le moteur exécute — et son cœur reste scellé.</text>
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
    /* On compte l'UNION de deux sources, dédupliquée par (locataire, bataille) :
       (a) la meta locataire (bandeaux) ET (b) le registre des coupes — ainsi
       aucune victoire n'est oubliée (ex. Gwen posée avant le registre), et le
       total colle à celui de l'accueil. */
    $pairs = []; $familles = [];
    foreach (['urgence', 'indemnisation'] as $bat) {
        $uids = get_users([
            'meta_query' => [['key' => 'lfi_nct_' . $bat . '_won', 'value' => '', 'compare' => '!=']],
            'fields'     => 'ID',
        ]);
        foreach ($uids as $uid) { $pairs[(int) $uid . '|' . $bat] = 1; $familles[(int) $uid] = 1; }
    }
    if (function_exists('lfi_nct_victoires_all')) {
        foreach (lfi_nct_victoires_all() as $v) {
            $vu = (int) ($v['tenant_uid'] ?? 0); $vb = (string) ($v['bataille'] ?? '');
            if ($vu && $vb) { $pairs[$vu . '|' . $vb] = 1; $familles[$vu] = 1; }
        }
    }
    $victoires = count($pairs);
    /* Réussites publiées (toutes). */
    $publiees = 0;
    if (function_exists('lfi_nct_reussites')) foreach (lfi_nct_reussites() as $r) if (!empty($r['publie'])) $publiees++;
    /* Locataires ayant demandé à être suivis — RÉSEAU ENTIER (recontact = oui). */
    $suivis = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lfi_nct_responses WHERE contact_recontact = 1 AND (deleted_at IS NULL)");
    /* Foyers accompagnés = dossiers ouverts (distinct locataires) — solide, non disputé. */
    $foyers = (int) $wpdb->get_var("SELECT COUNT(DISTINCT tenant_user_id) FROM {$wpdb->prefix}lfi_nct_dossiers_locataires WHERE tenant_user_id > 0");
    return ['victoires' => $victoires, 'familles' => count($familles), 'publiees' => $publiees, 'suivis' => $suivis, 'foyers' => $foyers];
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
        <div class="stat"><div class="n">🏆 <?php echo (int) $st['publiees']; ?></div><div class="l">Victoires</div></div>
        <div class="stat"><div class="n">🏡 <?php echo (int) $st['foyers']; ?></div><div class="l">Foyers accompagnés</div></div>
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

/* ============================================================== *
 *  PRÉSENTATION DÉTAILLÉE (Manuel Bompard) — mécanismes précis    *
 *  Rigoureuse, minutieuse, pour un profil scientifique. Explique  *
 *  le MODÈLE, les INVARIANTS et les GARANTIES — pas la recette :   *
 *  le cœur (moteur) reste scellé (aucun code, clé, ni endpoint).  *
 * ============================================================== */
function lfi_nct_app_view_kit_technique() {
    $ok = current_user_can('manage_options')
        || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga())
        || (function_exists('lfi_nct_is_demo_user') && lfi_nct_is_demo_user()); /* Bompard peut la lire */
    if (!$ok) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $st = lfi_nct_demo_stats();
    $svg = function_exists('lfi_nct_demo_svg_graph') ? lfi_nct_demo_svg_graph() : '';

    /* Chaque mécanisme : titre · ce qu'il fait · comment (logique) · l'invariant/garantie. */
    $mecas = [
        ['①', 'Captation terrain', 'Deux entrées équivalentes : porte-à-porte (par un·e militant·e) et signalement web. Chaque réponse produit un enregistrement structuré (problèmes cochés, récurrence, coordonnées si consenties, position).', 'Garantie : une seule table de vérité pour l\'entrée ; le mode de saisie (papier retranscrit / web) n\'affecte pas le traitement aval.'],
        ['②', 'Géo-routage vers le bon GA', 'La réponse est rattachée à un Groupe d\'Action par un ancrage double : le GA du militant qui saisit, corrigé par la ville/adresse (géocodage). En cas de conflit, la ville prime.', 'Invariant : une réponse appartient à exactement un GA. Pas de réponse orpheline, pas de double rattachement.'],
        ['③', 'Auto-création liée', 'Si la personne demande à être suivie, le système crée — en une transaction — un compte locataire et un dossier, reliés par un identifiant unique de compte. Anti-doublon sur l\'identité de compte, jamais sur le nom.', 'Invariant fondateur : l\'agrégation d\'un dossier se fait UNIQUEMENT par identifiant de compte exact (tenant_user_id), jamais par nom ni adresse.'],
        ['④', 'Parcours à états', 'Le dossier suit une liste d\'étapes typées (à faire par l\'équipe / par le locataire). L\'avancement se déduit de l\'état ; certaines étapes deviennent « inutiles » quand un événement les rend caduques (ex. urgence gagnée).', 'Propriété : le parcours est monotone — une étape franchie ne redevient pas « à faire » ; une étape rendue inutile sort de la charge de travail.'],
        ['⑤', 'Le modèle « deux batailles »', 'Un dossier = deux sous-problèmes indépendants : ⚡ URGENCE (faire cesser le danger) et 💶 INDEMNISATION (réparer le préjudice). Gagner l\'urgence n\' arrête pas le dossier : ça POSE une coupe et LANCE la seconde bataille (greffe de ses étapes).', 'Séparation des préoccupations : les deux batailles ont des états disjoints ; la victoire de l\'une déclenche, sans la confondre, l\'ouverture de l\'autre.'],
        ['⑥', 'Boucle de correspondance', 'Les emails échangés avec le bailleur reviennent automatiquement se ranger dans le bon dossier : tri par identité (bailleur / membre / locataire), anti-doublon par identifiant de message, apprentissage des adresses non reconnues (une fois rattachées, mémorisées).', 'Verrou de sûreté : aucun courrier sortant vers le bailleur sans mandat signé. Le tri ne s\'appuie jamais sur une correspondance floue de noms.'],
        ['⑦', 'Détection automatique de victoire', 'À l\'arrivée d\'un email du bailleur, une analyse décide s\'il ACTE une demande (relogement/travaux accordés) ; si oui, la coupe « urgence » se pose seule et le groupe est prévenu. Garde-fous : les tournures négatives (refus, « pas favorable ») sont écartées.', 'Prudence dissymétrique : on ne pose une victoire que sur signal positif net ; le doute ne déclenche rien (faux positif coûteux évité).'],
        ['⑧', 'Métrique « championnat de rapidité »', 'Chaque victoire d\'urgence est datée : durée (jours entre 1re étape et victoire) et rang (à quelle étape sur le total on a gagné). Ces mesures alimentent un classement inter-dossiers, motivant.', 'Mesure comparable : durée et rang sont normalisés par dossier, donc agrégeables et classables entre situations.'],
        ['⑨', 'Escalade amiable → juridique', 'Si l\'amiable échoue, la Commission de conciliation est montée « prête » (saisine + pièces + contacts) ; puis les avocats partenaires interviennent depuis un espace cloisonné, avec jurisprudence intégrée, limitée à leurs dossiers.', 'Cloisonnement transverse : l\'avocat ne voit que les dossiers qui lui sont attribués ; l\'accès jurisprudence est scopé au dossier.'],
        ['⑩', 'Relogement / DALO', 'Quand l\'objectif est le relogement, le parcours ne dépend plus du seul bailleur : demande unique de logement social + Action Logement + saisine DALO. La saisine est générée ; le préfet a 6 mois pour reloger.', 'Voie indépendante : la victoire « relogement » ne suppose pas la bonne volonté du bailleur.'],
        ['⑪', 'Agrégation réseau', 'Les compteurs nationaux additionnent, sur tous les GA : victoires (par bataille, dédupliquées) et locataires demandant un suivi. Strictement anonymes — aucun nom, aucune donnée personnelle n\'en sort.', 'Additivité sans fuite : on agrège des compteurs, jamais des identités ; le national ne « voit » aucun dossier.'],
    ];

    nocache_headers();
    ?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Défendre les locataires — présentation détaillée</title>
    <style>
      :root{--r:#c8102e;--v:#4b2e83;--g:#186a3b}
      *{box-sizing:border-box}
      body{font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#16121f;margin:0;background:#f4f1fa}
      .slide{max-width:940px;margin:0 auto 22px;background:#fff;padding:40px;box-shadow:0 6px 24px rgba(0,0,0,.08);border-radius:14px}
      h1{font-size:2em;line-height:1.12;margin:6px 0 8px;color:var(--v)}
      h2{font-size:1.4em;color:var(--r);margin:0 0 14px;border-bottom:3px solid var(--r);padding-bottom:8px}
      .lead{font-size:1.12em;color:#333;line-height:1.6}
      .meca{border-left:4px solid var(--v);background:#f7f4fc;border-radius:0 10px 10px 0;padding:12px 16px;margin:12px 0}
      .meca .t{font-weight:800;color:var(--v);font-size:1.05em}
      .meca .how{color:#333;margin:6px 0}
      .meca .inv{font-size:.92em;color:var(--g);font-weight:700}
      .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:12px}
      .stat{background:#f6f2fc;border-radius:12px;padding:16px;text-align:center;border-top:5px solid var(--v)}
      .stat .n{font-size:2.2em;font-weight:900;color:var(--v)}
      .stat .l{color:#555;font-weight:600;font-size:.85em}
      .noprint{position:sticky;top:0;background:#4b2e83;color:#fff;text-align:center;padding:10px;z-index:5}
      .btn{background:#fff;color:#4b2e83;border:0;padding:10px 22px;border-radius:10px;font-weight:800;cursor:pointer}
      code{background:#efeafb;padding:1px 6px;border-radius:5px;font-size:.92em}
      @media print{ .noprint{display:none} body{background:#fff} .slide{box-shadow:none;margin:0;border-radius:0;page-break-after:always} }
    </style></head><body>
    <div class="noprint">Présentation détaillée — ou <button class="btn" onclick="window.print()">🖨️ Enregistrer en PDF</button></div>

    <section class="slide" style="text-align:center">
      <div style="letter-spacing:2px;text-transform:uppercase;color:#888;font-weight:700;font-size:.8em">La France Insoumise · Union des Quartiers Libres</div>
      <h1>Défendre les locataires, partout</h1>
      <p class="lead">Une présentation <strong>détaillée</strong> des mécanismes : le modèle, les invariants, les garanties. De la porte du locataire jusqu\'au tribunal — et reproductible dans chaque quartier.</p>
      <div class="grid">
        <div class="stat"><div class="n">🏠 <?php echo (int) $st['suivis']; ?></div><div class="l">Locataires suivis (réseau)</div></div>
        <div class="stat"><div class="n">🏆 <?php echo (int) $st['publiees']; ?></div><div class="l">Victoires</div></div>
        <div class="stat"><div class="n">🏡 <?php echo (int) $st['foyers']; ?></div><div class="l">Foyers accompagnés</div></div>
      </div>
      <p style="color:#888;font-size:.82em;margin-top:12px">Totaux France entière · strictement anonymes.</p>
    </section>

    <section class="slide">
      <h2>Vue d'ensemble</h2>
      <p class="lead">Le système est une <strong>chaîne de traitement</strong> qui transforme un signalement de terrain en rapport de force organisé. Un « moteur » (automatisation + intelligence artificielle) prend en charge le travail répétitif ; l'humain décide et pousse. Le schéma :</p>
      <?php echo $svg; ?>
      <p style="color:#7a6a99;font-size:.9em;margin-top:8px">Le cœur du moteur — le <em>comment</em> exact — n'est pas divulgué : c'est le savoir-faire, scellé. Cette présentation détaille le <strong>modèle</strong> et les <strong>garanties</strong>, pas la recette de fabrication.</p>
    </section>

    <section class="slide">
      <h2>Les mécanismes, un par un</h2>
      <?php foreach ($mecas as $m): ?>
        <div class="meca">
          <div class="t"><?php echo $m[0]; ?> · <?php echo esc_html($m[1]); ?></div>
          <div class="how"><?php echo esc_html($m[2]); ?></div>
          <div class="inv">◆ <?php echo esc_html($m[3]); ?></div>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="slide">
      <h2>Liste (quasi) exhaustive des mécanismes</h2>
      <p class="lead">À peu près <strong>tout</strong> ce que fait l'outil — l'orchestration exacte du moteur restant volontairement secrète.</p>
      <?php
      $liste = [
        'Terrain & entrée' => [
          'Double captation : porte-à-porte (militant·e) et signalement web, même table de vérité',
          'Géo-routage vers le bon GA : ancrage militant + ville, géocodage BAN · Nominatim',
          'Auto-création liée : compte locataire ⋈ dossier en une transaction, anti-doublon par identité de compte',
        ],
        'Le dossier' => [
          'Parcours à états typés (équipe / locataire), auto-avance, étapes rendues « inutiles »',
          'Modèle à deux batailles : ⚡ urgence puis 💶 indemnisation, états disjoints',
          'Greffe automatique des étapes d\'indemnisation dès l\'urgence gagnée',
          'Préjudice chiffré + base légale attachés au dossier',
          'Pièces & photos horodatées par métadonnées EXIF → chronologie fiable (sans IA)',
        ],
        'Correspondance email' => [
          'Boîte collectrice + ingestion IMAP / POP3 (et passerelle POST alternative)',
          'Tri des emails par identité (bailleur / membre / locataire), jamais par nom flou',
          'Anti-doublon par Message-ID · apprentissage des adresses (rattachement mémorisé)',
          'File « à rattacher » (aucun email perdu) + liste noire d\'expéditeurs',
          'Verrou mandat : aucun courrier sortant au bailleur sans adhésion signée',
          'Nettoyage de l\'historique cité (on ne garde que le message neuf)',
          'Détection automatique de victoire : analyse du mail (NLP), garde-fous sur les refus',
          'Brouillons de réponse générés + analyse de posture / ton conseillé',
        ],
        'Victoires' => [
          'Coupe idempotente : une par bataille, une famille comptée une seule fois',
          'Championnat de rapidité : durée + rang de la victoire, classement inter-dossiers',
          'Célébration scopée au GA concerné (pop-up à l\'ouverture)',
        ],
        'Escalade' => [
          'Commission de conciliation montée « prête » : saisine + pièces + contacts',
          'Espace avocat cloisonné + Judilibre (jurisprudence) scopé au seul dossier',
          'Relogement : demande unique + Action Logement + DALO (saisine générée, préfet 6 mois)',
        ],
        'Cloisonnement & rôles' => [
          'Invariant : agrégation par clé de compte exacte (tenant_user_id), jamais par nom/adresse',
          'Casquettes multiples d\'une même personne, liées automatiquement en base',
          'Rôles & interfaces adaptées (superadmin, admin GA, trésorier, responsable réunions…)',
          'Signatures adaptatives selon la casquette (au bailleur = association, à l\'avocat = LFI + UQL)',
        ],
        'Réseau & accès' => [
          'Agrégation multi-GA strictement anonyme (compteurs, jamais d\'identités) + carte réseau',
          'Connexion magique (lien usage-unique, 14 j) + onboarding (choix du mot de passe)',
          'Application installable (PWA, écran d\'accueil) · SMS natif de l\'appareil + blocklist RGPD',
          'Sauvegarde / export · cadre RGPD (registre, droit à l\'oubli)',
        ],
      ];
      foreach ($liste as $cat => $items):
      ?>
        <h3 style="color:#4b2e83;margin:14px 0 4px"><?php echo esc_html($cat); ?></h3>
        <ul style="margin:0;line-height:1.7"><?php foreach ($items as $it) echo '<li>' . esc_html($it) . '</li>'; ?></ul>
      <?php endforeach; ?>
      <p style="color:#7a6a99;font-size:.9em;margin-top:12px">🔒 Ce qui n'est <strong>pas</strong> dévoilé : l'orchestration précise du moteur (le « comment » exact) — c'est le savoir-faire scellé, non reproductible sans l'auteur.</p>
    </section>

    <section class="slide">
      <h2>L'invariant central : le cloisonnement</h2>
      <p class="lead">C'est la propriété la plus forte du système, et une règle absolue :</p>
      <ul style="line-height:1.8">
        <li>L'agrégation d'un dossier se fait <strong>uniquement par identifiant de compte exact</strong> (<code>tenant_user_id</code>) — <strong>jamais</strong> par nom ni adresse (qui produisent des collisions : homonymes, variantes d'orthographe).</li>
        <li>Rien ne « transpire » d'un dossier à un autre, ni d'un GA à un autre : ni pièce, ni information, ni lien.</li>
        <li>L'<strong>enquête de terrain n'est jamais partagée</strong> avec les élu·es partenaires — elle sert le rapport de force, pas la circulation d'informations personnelles.</li>
        <li>Le national n'agrège que des <strong>compteurs anonymes</strong> ; il ne voit aucun dossier.</li>
      </ul>
      <p style="color:var(--g);font-weight:800;margin-top:10px">Conséquence : la puissance d'agrégation ne crée jamais de fuite. C'est ce qui rend l'outil déployable à l'échelle sans trahir la confiance des locataires.</p>
    </section>

    <section class="slide">
      <h2>Le rapport de force (au-delà du juridique)</h2>
      <p class="lead">Le droit ne suffit pas : il agit dans une stratégie qui pèse sur le bailleur <em>avant</em> l'audience.</p>
      <ul style="line-height:1.8">
        <li><strong>Local</strong> : élus municipaux (audit de la gestion du bailleur), relais au conseil d'administration.</li>
        <li><strong>Institutions</strong> : interlocuteurs préfecture, SCHS / ARS (insalubrité), DALO (relogement d'urgence).</li>
        <li><strong>National</strong> : député·es (questions écrites, propositions de loi logement).</li>
        <li><strong>Populaire</strong> : presse locale, mobilisation collective, entraide de quartier.</li>
      </ul>
    </section>

    <section class="slide">
      <h2>Reproductibilité</h2>
      <p class="lead">Un GA s'en empare, configure son bailleur local, et toute la mécanique se met en place : enquête, dossiers, juridique, relogement, coordination. <strong>Chaque quartier peut avoir sa machine à gagner</strong> — sans jamais recopier le cœur, qui reste le savoir-faire de l'auteur.</p>
      <p style="text-align:center;color:#888;font-size:.85em;margin-top:14px">🔒 Protection absolue des locataires : aucune donnée personnelle n'est jamais exposée. © Concept propriétaire · Union des Quartiers Libres.</p>
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
    if ($vue === 'mon-profil')    { lfi_nct_app_view_mon_profil();   return true; }
    if ($vue === 'installer')     { lfi_nct_app_view_installer();    return true; }
    if ($vue === 'kit-technique') { lfi_nct_app_view_kit_technique(); return true; } /* présentation détaillée */
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
    echo '<div class="stat"><div class="ico">🏆</div><div class="n">' . (int) $st['publiees'] . '</div><div class="l">Victoires</div></div>';
    echo '<div class="stat"><div class="ico">🏡</div><div class="n">' . (int) $st['foyers'] . '</div><div class="l">Foyers accompagnés</div></div>';
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
    echo '<div style="margin:14px 0;padding:14px;background:#f6f2fc;border:1px solid #ddd0f0;border-radius:12px;text-align:center">';
    echo '<div style="font-weight:800;color:#4b2e83">🔬 Envie du détail complet ?</div>';
    echo '<div style="font-size:.9em;color:#444;margin:4px 0 8px">La présentation <strong>détaillée</strong> : chaque mécanisme, les invariants, les garanties (le cœur du moteur reste scellé).</div>';
    echo '<a class="btn-primary" style="background:#4b2e83" href="' . esc_url(lfi_nct_app_url('kit-technique')) . '" target="_blank" rel="noopener">Ouvrir la présentation détaillée →</a>';
    echo '</div>';

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
