<?php
/**
 * CARTOGRAPHIE LFI LOIRE-ATLANTIQUE — registre PRIVÉ de prospection.
 *
 * Réservé au SUPERADMIN (Fabrice). Sert à recenser tous les GA du département
 * (nom, commune, email, contact), suivre où on en est (à contacter / contacté /
 * intéressé / utilise l'app) et envoyer l'invitation en 1 clic.
 *
 * Rien de cloisonné ici : ce sont des contacts de GA (pas des données de
 * locataires). Aucune donnée d'enquête n'y figure jamais.
 */
if (!defined('ABSPATH')) exit;

if (!defined('LFI_NCT_CARTO_OPT')) define('LFI_NCT_CARTO_OPT', 'lfi_nct_carto_ga');

function lfi_nct_carto_all() {
    $v = get_option(LFI_NCT_CARTO_OPT, []);
    return is_array($v) ? $v : [];
}
function lfi_nct_carto_save($list) {
    update_option(LFI_NCT_CARTO_OPT, array_values($list), false);
}
function lfi_nct_carto_statuts() {
    return [
        'a_contacter' => ['📇', 'À contacter', '#c8102e'],
        'contacte'    => ['📞', 'Contacté',    '#bd8600'],
        'interesse'   => ['👍', 'Intéressé',   '#0066a3'],
        'app'         => ['✅', 'Utilise l\'app', '#186a3b'],
    ];
}
function lfi_nct_carto_next_id($list) {
    $m = 0; foreach ($list as $e) $m = max($m, (int) ($e['id'] ?? 0));
    return $m + 1;
}

/* ---- ORGANIGRAMME (pyramide) : personnes qui ne sont pas des admins de GA ---- */
if (!defined('LFI_NCT_CARTO_PEOPLE_OPT')) define('LFI_NCT_CARTO_PEOPLE_OPT', 'lfi_nct_carto_people');
function lfi_nct_carto_people_all() {
    $v = get_option(LFI_NCT_CARTO_PEOPLE_OPT, []);
    return is_array($v) ? $v : [];
}
function lfi_nct_carto_people_save($list) {
    update_option(LFI_NCT_CARTO_PEOPLE_OPT, array_values($list), false);
}
/** Niveaux de la pyramide, du HAUT (Mélenchon) vers le BAS (terrain). */
function lfi_nct_carto_niveaux() {
    return [
        'national'      => ['👑', 'National — coordination / porte-parole', '#4b2e83'],
        'deputes'       => ['🇫🇷', 'Député·es (Assemblée nationale)',        '#0b3d91'],
        'regional'      => ['🌍', 'Régional — Pays de la Loire',            '#6a1b9a'],
        'departemental' => ['🏛️', 'Départemental — Loire-Atlantique',       '#0066a3'],
        'municipaux'    => ['🏙️', 'Conseiller·es municipaux / métropolitains', '#7a1fa2'],
        'circo'         => ['🗳️', 'Circonscription / secteur',              '#bd8600'],
        'admin_ga'      => ['🏳️', 'Admins de GA (binôme)',                  '#c8102e'],
        'terrain'       => ['✊', 'Militant·e de terrain',                  '#186a3b'],
    ];
}

/**
 * Affichage respectueux de la vie privée : « Prénom N. ».
 * On ne montre JAMAIS le nom de famille complet ni l'email en clair.
 * (Prénom = 1er mot ; N. = initiale du dernier mot.)
 */
function lfi_nct_carto_short_name($name) {
    $name = trim(preg_replace('/\s+/', ' ', (string) $name));
    if ($name === '') return '';
    $parts = explode(' ', $name);
    if (count($parts) === 1) return $parts[0];
    $prenom  = $parts[0];
    $initial = function_exists('mb_strtoupper')
        ? mb_strtoupper(mb_substr(end($parts), 0, 1))
        : strtoupper(substr(end($parts), 0, 1));
    return $prenom . ' ' . $initial . '.';
}

/** Message d'invitation personnalisé pour un GA (avec chiffres réseau en direct). */
function lfi_nct_carto_invite_message($e) {
    $nom = trim((string) ($e['contact'] ?? '')) ?: (trim((string) ($e['nom'] ?? '')) ?: 'camarade');
    $tel = '06 23 52 60 74';
    if (function_exists('lfi_nct_author_contact')) { $t = trim((string) (lfi_nct_author_contact()['tel'] ?? '')); if ($t !== '') $tel = $t; }
    $bilan = '';
    if (function_exists('lfi_nct_demo_stats')) {
        $st = lfi_nct_demo_stats();
        $bilan = 'Déjà ' . (int) ($st['publiees'] ?? 0) . ' victoire(s) et ' . (int) ($st['suivis'] ?? 0) . ' locataire(s) suivi(s) chez nous. ';
    }
    return 'Salut ' . $nom . ", ici Fabrice (GA Nantes Sud – Clos Toreau). On a un outil qui marche pour défendre les locataires : enquête + QR d'affiche dans les halls, dossiers montés tout seuls, emails du bailleur rangés automatiquement, amiable → avocat → relogement, victoires suivies. " . $bilan
        . "Et surtout, ça nous évite au maximum le bazar de Telegram pour s'organiser (réunions, votes, événements, chacun son rôle). Je te crée ton espace, cloisonné (tes données restent chez toi). "
        . 'Appelle-moi, je te montre en 10 min et je te configure ton accès : 📞 ' . $tel . '. — Fabrice · Union des Quartiers Libres';
}

/* ---------------- VUE (privée, superadmin) ---------------- */
function lfi_nct_app_view_carto() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $back = lfi_nct_app_url('carto');

    /* Ajout d'un GA. */
    if (!empty($_POST['lfi_carto_add']) && check_admin_referer('lfi_carto_add')) {
        $list = lfi_nct_carto_all();
        $nom  = sanitize_text_field(wp_unslash($_POST['nom'] ?? ''));
        if ($nom !== '') {
            $list[] = [
                'id'       => lfi_nct_carto_next_id($list),
                'nom'      => $nom,
                'commune'  => sanitize_text_field(wp_unslash($_POST['commune'] ?? '')),
                'email'    => sanitize_email(wp_unslash($_POST['email'] ?? '')),
                'contact'  => sanitize_text_field(wp_unslash($_POST['contact'] ?? '')),
                'tel'      => sanitize_text_field(wp_unslash($_POST['tel'] ?? '')),
                'contact2' => sanitize_text_field(wp_unslash($_POST['contact2'] ?? '')),
                'email2'   => sanitize_email(wp_unslash($_POST['email2'] ?? '')),
                'statut'   => 'a_contacter',
                'notes'    => '',
            ];
            lfi_nct_carto_save($list);
        }
        wp_safe_redirect(add_query_arg('added', 1, $back)); exit;
    }

    /* Import en masse : une ligne par GA, champs séparés par ; ou tabulation.
       Ordre : Nom ; Commune ; Email admin1 ; Admin1 ; Tél ; Admin2 ; Email admin2. */
    if (!empty($_POST['lfi_carto_import']) && check_admin_referer('lfi_carto_import')) {
        $raw = (string) wp_unslash($_POST['bulk'] ?? '');
        $list = lfi_nct_carto_all();
        $n = 0;
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $c = preg_split('/\t|;|\|/', $line);
            $nom = sanitize_text_field(trim($c[0] ?? ''));
            if ($nom === '') continue;
            $list[] = [
                'id'       => lfi_nct_carto_next_id($list),
                'nom'      => $nom,
                'commune'  => sanitize_text_field(trim($c[1] ?? '')),
                'email'    => sanitize_email(trim($c[2] ?? '')),
                'contact'  => sanitize_text_field(trim($c[3] ?? '')),
                'tel'      => sanitize_text_field(trim($c[4] ?? '')),
                'contact2' => sanitize_text_field(trim($c[5] ?? '')),
                'email2'   => sanitize_email(trim($c[6] ?? '')),
                'statut'   => 'a_contacter',
                'notes'    => '',
            ];
            $n++;
        }
        lfi_nct_carto_save($list);
        wp_safe_redirect(add_query_arg('imported', $n, $back)); exit;
    }

    /* Charger TOUTE la cartographie (42 GA + organigramme) en 1 clic, dédoublonné. */
    if (!empty($_POST['lfi_carto_seed']) && check_admin_referer('lfi_carto_seed') && function_exists('lfi_nct_carto_seed_load')) {
        $rep = lfi_nct_carto_seed_load();
        wp_safe_redirect(add_query_arg([
            'seed_ga'  => (int) $rep['ga_add'],
            'seed_gad' => (int) $rep['ga_dup'],
            'seed_pp'  => (int) $rep['ppl_add'],
            'seed_ppd' => (int) $rep['ppl_dup'],
        ], $back)); exit;
    }

    /* Changer le statut. */
    if (!empty($_POST['lfi_carto_status']) && check_admin_referer('lfi_carto_status')) {
        $id = (int) ($_POST['id'] ?? 0);
        $st = sanitize_key($_POST['statut'] ?? '');
        if (!isset(lfi_nct_carto_statuts()[$st])) $st = 'a_contacter';
        $list = lfi_nct_carto_all();
        foreach ($list as &$e) if ((int) ($e['id'] ?? 0) === $id) $e['statut'] = $st;
        unset($e);
        lfi_nct_carto_save($list);
        wp_safe_redirect($back); exit;
    }

    /* Supprimer. */
    if (!empty($_POST['lfi_carto_del']) && check_admin_referer('lfi_carto_del')) {
        $id = (int) ($_POST['id'] ?? 0);
        $list = array_values(array_filter(lfi_nct_carto_all(), function ($e) use ($id) { return (int) ($e['id'] ?? 0) !== $id; }));
        lfi_nct_carto_save($list);
        wp_safe_redirect(add_query_arg('deleted', 1, $back)); exit;
    }

    /* ORGANIGRAMME : ajouter une personne. */
    if (!empty($_POST['lfi_orga_add']) && check_admin_referer('lfi_orga_add')) {
        $p = lfi_nct_carto_people_all();
        $nom = sanitize_text_field(wp_unslash($_POST['nom'] ?? ''));
        $niv = sanitize_key($_POST['niveau'] ?? '');
        if (!isset(lfi_nct_carto_niveaux()[$niv])) $niv = 'terrain';
        if ($nom !== '') {
            $p[] = [
                'id'        => lfi_nct_carto_next_id($p),
                'nom'       => $nom,
                'fonction'  => sanitize_text_field(wp_unslash($_POST['fonction'] ?? '')),
                'email'     => sanitize_email(wp_unslash($_POST['email'] ?? '')),
                'ap_url'    => esc_url_raw(wp_unslash($_POST['ap_url'] ?? '')),
                'niveau'    => $niv,
                'ga_nom'    => sanitize_text_field(wp_unslash($_POST['ga_nom'] ?? '')),
                'ga_membres'=> (int) ($_POST['ga_membres'] ?? 0),
            ];
            lfi_nct_carto_people_save($p);
        }
        wp_safe_redirect(add_query_arg('padded', 1, $back)); exit;
    }
    /* ORGANIGRAMME : import — Nom ; Fonction ; Email ; URL AP ; Niveau ; GA ; NbMembres. */
    if (!empty($_POST['lfi_orga_import']) && check_admin_referer('lfi_orga_import')) {
        $raw = (string) wp_unslash($_POST['bulk'] ?? '');
        $p = lfi_nct_carto_people_all();
        $niveaux = lfi_nct_carto_niveaux();
        $n = 0;
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line); if ($line === '') continue;
            $c = preg_split('/\t|;|\|/', $line);
            $nom = sanitize_text_field(trim($c[0] ?? '')); if ($nom === '') continue;
            $niv = sanitize_key(trim($c[4] ?? '')); if (!isset($niveaux[$niv])) $niv = 'terrain';
            $p[] = [
                'id'        => lfi_nct_carto_next_id($p),
                'nom'       => $nom,
                'fonction'  => sanitize_text_field(trim($c[1] ?? '')),
                'email'     => sanitize_email(trim($c[2] ?? '')),
                'ap_url'    => esc_url_raw(trim($c[3] ?? '')),
                'niveau'    => $niv,
                'ga_nom'    => sanitize_text_field(trim($c[5] ?? '')),
                'ga_membres'=> (int) trim($c[6] ?? ''),
            ];
            $n++;
        }
        lfi_nct_carto_people_save($p);
        wp_safe_redirect(add_query_arg('pimported', $n, $back)); exit;
    }
    if (!empty($_POST['lfi_orga_del']) && check_admin_referer('lfi_orga_del')) {
        $id = (int) ($_POST['id'] ?? 0);
        $p = array_values(array_filter(lfi_nct_carto_people_all(), function ($e) use ($id) { return (int) ($e['id'] ?? 0) !== $id; }));
        lfi_nct_carto_people_save($p);
        wp_safe_redirect(add_query_arg('pdeleted', 1, $back)); exit;
    }

    $list = lfi_nct_carto_all();
    $statuts = lfi_nct_carto_statuts();
    $filter = isset($_GET['f']) ? sanitize_key($_GET['f']) : '';

    lfi_nct_app_screen_open('🗺️ Cartographie LFI Loire-Atlantique', 'Registre privé — inviter les GA, suivre où on en est');
    if (!empty($_GET['added']))    lfi_nct_app_flash('✅ GA ajouté.');
    if (isset($_GET['imported']))  lfi_nct_app_flash('✅ ' . (int) $_GET['imported'] . ' GA importé(s).');
    if (!empty($_GET['deleted']))  lfi_nct_app_flash('🗑 GA retiré.');
    if (!empty($_GET['padded']))   lfi_nct_app_flash('✅ Personne ajoutée à l\'organigramme.');
    if (isset($_GET['pimported']))  lfi_nct_app_flash('✅ ' . (int) $_GET['pimported'] . ' personne(s) importée(s).');
    if (!empty($_GET['pdeleted']))  lfi_nct_app_flash('🗑 Personne retirée.');
    echo '<div class="lfi-app-help" style="background:#fdeef0;border-left:4px solid #c8102e"><small>🔒 <strong>Privé</strong> — visible par toi seul (superadmin). Ce sont des contacts de GA, aucune donnée d\'enquête ni de locataire.<br>👁️ <strong>Affichage protégé</strong> : seuls le GA et « <em>Prénom N.</em> » sont montrés. Les <strong>emails ne sont jamais affichés en clair</strong> (masqués <code>••</code>) — ils restent stockés pour l\'envoi automatique et s\'utilisent via les boutons ✉️/📱.</small></div>';

    if (isset($_GET['seed_ga'])) {
        lfi_nct_app_flash('✅ Cartographie chargée : ' . (int) $_GET['seed_ga'] . ' GA + ' . (int) ($_GET['seed_pp'] ?? 0) . ' personnes ajoutés · doublons évités : ' . (int) ($_GET['seed_gad'] ?? 0) . ' GA / ' . (int) ($_GET['seed_ppd'] ?? 0) . ' personnes.');
    }

    /* Chargement en 1 clic de toute la cartographie compilée (dédoublonné). */
    $nb_ga_now = count(lfi_nct_carto_all());
    echo '<details class="lfi-app-card" style="border-left:4px solid #186a3b"' . ($nb_ga_now === 0 ? ' open' : '') . '><summary style="cursor:pointer;font-weight:800;color:#186a3b">📥 Charger toute la cartographie (42 GA + organigramme) en 1 clic</summary>';
    echo '<div class="com" style="font-size:.92em;margin-top:6px">Charge d\'un coup les 42 GA de Loire-Atlantique et l\'organigramme (députés, boucle départementale, admins de GA). <strong>Dédoublonné</strong> : rien n\'est ajouté deux fois, tu peux cliquer sans risque.</div>';
    echo '<form method="post" style="margin-top:8px">' . wp_nonce_field('lfi_carto_seed', '_wpnonce', true, false) . '<input type="hidden" name="lfi_carto_seed" value="1"><button type="submit" class="btn-primary" style="background:#186a3b">📥 Tout charger maintenant</button></form>';
    echo '<div class="lfi-app-help" style="margin-top:6px"><small>Actuellement : <strong>' . (int) $nb_ga_now . '</strong> GA dans le registre.</small></div></details>';

    /* Compteurs + filtre. */
    $counts = ['' => count($list)];
    foreach ($statuts as $k => $v) $counts[$k] = 0;
    foreach ($list as $e) { $s = $e['statut'] ?? 'a_contacter'; $counts[$s] = ($counts[$s] ?? 0) + 1; }
    echo '<div class="lfi-app-filter-chips" style="margin:8px 0 12px">';
    echo '<a class="fc' . ($filter === '' ? ' on' : '') . '" href="' . esc_url($back) . '">Tous (' . (int) $counts[''] . ')</a>';
    foreach ($statuts as $k => $v) echo '<a class="fc' . ($filter === $k ? ' on' : '') . '" href="' . esc_url(add_query_arg('f', $k, $back)) . '">' . $v[0] . ' ' . esc_html($v[1]) . ' (' . (int) ($counts[$k] ?? 0) . ')</a>';
    echo '</div>';

    /* Liste. */
    $shown = array_filter($list, function ($e) use ($filter) { return $filter === '' || ($e['statut'] ?? 'a_contacter') === $filter; });
    if (empty($shown)) {
        echo '<div class="lfi-app-empty">Aucun GA ' . ($filter ? 'dans ce statut' : 'pour l\'instant') . '. Ajoute-les ou importe ta liste ci-dessous.</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        foreach (array_reverse($shown) as $e) {
            $id = (int) ($e['id'] ?? 0);
            $s  = $e['statut'] ?? 'a_contacter';
            $sm = $statuts[$s] ?? $statuts['a_contacter'];
            echo '<li class="lfi-app-card" style="border-left:4px solid ' . esc_attr($sm[2]) . '">';
            echo '<div class="head"><div class="who">' . esc_html($e['nom']) . ($e['commune'] ? ' <span style="font-weight:400;color:#888">· ' . esc_html($e['commune']) . '</span>' : '') . '</div><div class="badge" style="background:' . esc_attr($sm[2]) . ';color:#fff">' . $sm[0] . ' ' . esc_html($sm[1]) . '</div></div>';
            echo '<div class="meta">';
            if (!empty($e['contact'])) echo '<span class="meta-chip">👤 ' . esc_html(lfi_nct_carto_short_name($e['contact'])) . '</span>';
            if (!empty($e['email'])) echo '<span class="meta-chip" style="color:#999" title="Email masqué — non public">✉️ •••</span>';
            echo '</div>';
            if (!empty($e['contact2']) || !empty($e['email2'])) {
                echo '<div class="meta"><span class="meta-chip" style="background:#eef;color:#33a">👥 binôme</span>';
                if (!empty($e['contact2'])) echo '<span class="meta-chip">👤 ' . esc_html(lfi_nct_carto_short_name($e['contact2'])) . '</span>';
                if (!empty($e['email2'])) echo '<span class="meta-chip" style="color:#999" title="Email masqué — non public">✉️ •••</span>';
                echo '</div>';
            }
            /* Invitation prête + statut + suppression. */
            $msg = lfi_nct_carto_invite_message($e);
            echo '<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:8px">';
            if (function_exists('lfi_nct_copy_button')) echo lfi_nct_copy_button($msg, '📋 Copier l\'invitation');
            if (!empty($e['email'])) echo '<a class="btn-ghost" style="font-size:.82em" href="mailto:' . esc_attr($e['email']) . '?subject=' . rawurlencode('Un outil pour défendre les locataires') . '&body=' . rawurlencode($msg) . '">✉️ Ouvrir l\'email</a>';
            if (!empty($e['tel'])) echo '<a class="btn-ghost" style="font-size:.82em" href="sms:' . esc_attr(preg_replace('/[^\d+]/', '', $e['tel'])) . '?body=' . rawurlencode($msg) . '">📱 SMS</a>';
            echo '</div>';
            echo '<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:8px">';
            echo '<form method="post" style="margin:0;display:flex;gap:4px;align-items:center">' . wp_nonce_field('lfi_carto_status', '_wpnonce', true, false) . '<input type="hidden" name="lfi_carto_status" value="1"><input type="hidden" name="id" value="' . $id . '"><select name="statut" style="font-size:.82em" onchange="this.form.submit()">';
            foreach ($statuts as $k => $v) echo '<option value="' . esc_attr($k) . '"' . selected($k, $s, false) . '>' . $v[0] . ' ' . esc_html($v[1]) . '</option>';
            echo '</select></form>';
            echo '<form method="post" style="margin:0" onsubmit="return confirm(\'Retirer ce GA ?\')">' . wp_nonce_field('lfi_carto_del', '_wpnonce', true, false) . '<input type="hidden" name="lfi_carto_del" value="1"><input type="hidden" name="id" value="' . $id . '"><button type="submit" class="btn-ghost" style="font-size:.78em">🗑</button></form>';
            echo '</div></li>';
        }
        echo '</ul>';
    }

    /* Ajouter un GA. */
    echo '<details class="lfi-app-card" style="border-left:4px solid #186a3b;margin-top:12px"><summary style="cursor:pointer;font-weight:800;color:#186a3b">➕ Ajouter un GA</summary>';
    echo '<form method="post" class="lfi-app-form" style="margin-top:8px;box-shadow:none;padding:0">' . wp_nonce_field('lfi_carto_add', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_carto_add" value="1">';
    echo '<label>Nom du GA<input type="text" name="nom" required placeholder="ex. GA Rezé Centre"></label>';
    echo '<label>Commune / secteur<input type="text" name="commune" placeholder="ex. Rezé"></label>';
    echo '<label>Admin 1 — nom<input type="text" name="contact" placeholder="ex. Prénom Nom"></label>';
    echo '<label>Admin 1 — email<input type="email" name="email" placeholder="admin1@…"></label>';
    echo '<label>Téléphone<input type="tel" name="tel"></label>';
    echo '<label>Admin 2 — nom (binôme)<input type="text" name="contact2" placeholder="ex. Prénom Nom"></label>';
    echo '<label>Admin 2 — email<input type="email" name="email2" placeholder="admin2@…"></label>';
    echo '<button type="submit" class="btn-primary" style="background:#186a3b">Ajouter</button></form></details>';

    /* Import en masse. */
    echo '<details class="lfi-app-card" style="border-left:4px solid #4b2e83;margin-top:10px"><summary style="cursor:pointer;font-weight:800;color:#4b2e83">📥 Importer ma liste (en masse)</summary>';
    echo '<div class="lfi-app-help" style="margin:6px 0"><small>Une ligne par GA. Champs séparés par « ; » dans l\'ordre :<br><code>Nom du GA ; Commune ; Email admin1 ; Admin1 ; Téléphone ; Admin2 ; Email admin2</code><br>Seul le nom est obligatoire ; laisse vide ce que tu n\'as pas (2 admins possibles = binôme).</small></div>';
    echo '<form method="post">' . wp_nonce_field('lfi_carto_import', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_carto_import" value="1">';
    echo '<textarea name="bulk" style="width:100%;height:160px;font-family:monospace;font-size:.82em;padding:8px;border:1px solid #ccc;border-radius:8px" placeholder="GA Rezé Centre ; Rezé ; admin1@exemple.fr ; Prénom Nom ; 06… ; Prénom2 Nom2 ; admin2@exemple.fr"></textarea>';
    echo '<button type="submit" class="btn-primary" style="background:#4b2e83;margin-top:8px">Importer</button></form></details>';

    /* ============ ORGANIGRAMME (pyramide) ============ */
    $people  = lfi_nct_carto_people_all();
    $niveaux = lfi_nct_carto_niveaux();
    echo '<h2 style="margin:22px 0 4px;color:#4b2e83;border-bottom:3px solid #4b2e83;padding-bottom:6px">🔺 Organigramme (pyramide)</h2>';
    echo '<div class="lfi-app-help"><small>De <strong>Mélenchon</strong> (en haut) jusqu\'au <strong>militant·e de terrain</strong> (en bas). On y range les personnes qui <strong>ne sont pas admin d\'un GA</strong> (ex. référent·es de la boucle départementale).</small></div>';
    $by = []; foreach ($niveaux as $k => $v) $by[$k] = [];
    foreach ($people as $e) { $nv = $e['niveau'] ?? 'terrain'; if (!isset($by[$nv])) $nv = 'terrain'; $by[$nv][] = $e; }
    foreach ($niveaux as $k => $v) {
        echo '<div style="margin:10px 0 4px;font-weight:800;color:' . esc_attr($v[2]) . '">' . $v[0] . ' ' . esc_html($v[1]) . ' <span style="color:#999;font-weight:600">(' . count($by[$k]) . ')</span></div>';
        if (empty($by[$k])) { echo '<div style="font-size:.85em;color:#aaa;margin:0 0 4px 6px">—</div>'; continue; }
        echo '<ul class="lfi-app-list">';
        foreach ($by[$k] as $e) {
            $id = (int) ($e['id'] ?? 0);
            echo '<li class="lfi-app-card" style="border-left:4px solid ' . esc_attr($v[2]) . '">';
            echo '<div class="head"><div class="who">' . esc_html(lfi_nct_carto_short_name($e['nom'])) . '</div></div>';
            if (!empty($e['fonction'])) echo '<div style="font-size:.9em;color:#333;margin:2px 0">🎽 ' . esc_html($e['fonction']) . '</div>';
            if (!empty($e['ga_nom'])) echo '<div style="font-size:.9em;color:#c8102e;font-weight:700;margin:2px 0">🏳️ ' . esc_html($e['ga_nom']) . (!empty($e['ga_membres']) ? ' · <span style="color:#555">' . (int) $e['ga_membres'] . ' membres</span>' : '') . '</div>';
            echo '<div class="meta">';
            if (!empty($e['email'])) echo '<span class="meta-chip" style="color:#999" title="Email masqué — non public">✉️ •••</span>';
            if (!empty($e['ap_url'])) echo '<a class="meta-chip" href="' . esc_url($e['ap_url']) . '" target="_blank" rel="noopener">🔗 Action Populaire</a>';
            echo '</div>';
            echo '<form method="post" style="margin-top:6px" onsubmit="return confirm(\'Retirer cette personne ?\')">' . wp_nonce_field('lfi_orga_del', '_wpnonce', true, false) . '<input type="hidden" name="lfi_orga_del" value="1"><input type="hidden" name="id" value="' . $id . '"><button type="submit" class="btn-ghost" style="font-size:.78em">🗑</button></form>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /* Ajouter une personne. */
    echo '<details class="lfi-app-card" style="border-left:4px solid #186a3b;margin-top:12px"><summary style="cursor:pointer;font-weight:800;color:#186a3b">➕ Ajouter une personne (organigramme)</summary>';
    echo '<form method="post" class="lfi-app-form" style="margin-top:8px;box-shadow:none;padding:0">' . wp_nonce_field('lfi_orga_add', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_orga_add" value="1">';
    echo '<label>Nom<input type="text" name="nom" required></label>';
    echo '<label>Fonction / référence<input type="text" name="fonction" placeholder="ex. Gestion de la caisse départementale"></label>';
    echo '<label>Email<input type="email" name="email"></label>';
    echo '<label>URL Action Populaire<input type="url" name="ap_url" placeholder="https://actionpopulaire.fr/…"></label>';
    echo '<label>Niveau<select name="niveau">';
    foreach ($niveaux as $k => $v) echo '<option value="' . esc_attr($k) . '"' . selected($k, 'departemental', false) . '>' . $v[0] . ' ' . esc_html($v[1]) . '</option>';
    echo '</select></label>';
    echo '<label>GA (si admin de GA)<input type="text" name="ga_nom" placeholder="ex. GA Rezé Centre"></label>';
    echo '<label>Nombre de membres du GA<input type="number" name="ga_membres" min="0"></label>';
    echo '<button type="submit" class="btn-primary" style="background:#186a3b">Ajouter</button></form></details>';

    /* Import en masse (personnes). */
    echo '<details class="lfi-app-card" style="border-left:4px solid #4b2e83;margin-top:10px"><summary style="cursor:pointer;font-weight:800;color:#4b2e83">📥 Importer des personnes (en masse)</summary>';
    echo '<div class="lfi-app-help" style="margin:6px 0"><small>Une ligne par personne :<br><code>Nom ; Fonction ; Email ; URL Action Populaire ; Niveau ; GA ; NbMembres</code><br>Niveau = <code>national</code>, <code>deputes</code>, <code>regional</code>, <code>departemental</code>, <code>circo</code>, <code>admin_ga</code> ou <code>terrain</code>. Les 2 derniers champs (GA + nb membres) servent pour les <strong>admins de GA</strong>.</small></div>';
    echo '<form method="post">' . wp_nonce_field('lfi_orga_import', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_orga_import" value="1">';
    echo '<textarea name="bulk" style="width:100%;height:150px;font-family:monospace;font-size:.82em;padding:8px;border:1px solid #ccc;border-radius:8px" placeholder="Guillaume MARO ; Gestion de la caisse départementale ; maro.guillaume@gmail.com ; https://actionpopulaire.fr/groupes/… ; departemental"></textarea>';
    echo '<button type="submit" class="btn-primary" style="background:#4b2e83;margin-top:8px">Importer</button></form></details>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE PUBLIQUE — ANNUAIRE LÉGER DES GA (?vue=annuaire)           *
 *  Chaque GA + sa commune + le PRÉNOM de qui l'anime.            *
 *  JAMAIS d'email, JAMAIS de nom de famille. Sans connexion.     *
 * ============================================================== */
/** Prénom seul (public). */
function lfi_nct_carto_first_name($name) {
    $name = trim(preg_replace('/\s+/', ' ', (string) $name));
    if ($name === '') return '';
    $p = explode(' ', $name);
    return $p[0];
}

/**
 * Annuaire public INTÉGRÉ (Loire-Atlantique) — GA + commune + PRÉNOMS des
 * animateur·ices. AUCUN email, AUCUN nom de famille : c'est volontairement
 * public et léger (les animateur·ices se déclarent déjà publiquement comme
 * gestionnaires de leur GA sur Action Populaire). Enrichi en direct si des
 * données sont importées dans la carto privée.
 */
function lfi_nct_gas_public_seed() {
    return [
        ['Groupe d\'action « Demain est à nous » à Machecoul', 'Machecoul', ['Séverine', 'Bleiz']],
        ['Couëron avec Jean-Luc Mélenchon', 'Couëron', ['Jean-Claude', 'Martine', 'Olivier']],
        ['GA Bouguenais', 'Bouguenais', ['Matthieu']],
        ['GA Cœur de la Brière', 'Saint-Lyphard', ['Lucyole', 'Bertrand']],
        ['GA Guérande', 'Guérande', ['Claire', 'Hélène']],
        ['GA Nantes Dervallières', 'Nantes', ['Ibrahim', 'Camille']],
        ['GA Nantes Erdre (Port-Boyer · Beaujoire · Halvêque)', 'Nantes', ['Julien', 'Alice']],
        ['GA Nantes Nord', 'Nantes', ['Erwan', 'Erika']],
        ['GA Saint-Nazaire EST-CARÈNE-AGGLO', 'Saint-Nazaire', ['Juliette', 'Christophe']],
        ['GA Savenay et environs', 'Savenay', ['Christelle', 'Pascal']],
        ['GA de Saint-Nazaire', 'Saint-Nazaire', ['Thomas', 'Laurence']],
        ['GA de Vallet', 'Vallet', ['Pauline', 'Guilhem']],
        ['Groupe d\'action Carquefou', 'Carquefou', ['Hakim', 'Sarah']],
        ['Groupe d\'action Donges et alentours', 'Donges', ['Jennifer', 'Thomas']],
        ['Groupe d\'action de Derval', 'Derval', ['Didier']],
        ['Groupe d\'action de Nantes Sud – Clos Toreau', 'Nantes', ['Fabrice', 'Gwenaëlle']],
        ['Groupe d\'action de Paimbœuf', 'Paimbœuf', ['Patricia', 'Thierry']],
        ['Groupe d\'action de Pornic', 'Pornic', ['Timothée', 'Armelle']],
        ['Groupe d\'action de Rezé', 'Rezé', ['Lu']],
        ['Groupe d\'action de Saint-Sébastien-sur-Loire', 'Saint-Sébastien-sur-Loire', ['Jenny', 'Laurent']],
        ['Groupe d\'action de l\'Université Nantaise', 'Nantes', ['Anne', 'Frédéric']],
        ['Groupe d\'action du Pays d\'Ancenis', 'Ancenis', ['Guillaume', 'Alex']],
        ['Île de Nantes · Malakoff · Olivettes', 'Nantes', ['Anaïs']],
        ['Insoumis·es du Pays de Blain', 'Blain', ['Christine', 'Hugo']],
        ['Jeunes insoumis·es de Nantes', 'Nantes', ['Ismael', 'Noelly']],
        ['Jeunes insoumis·es de Saint-Nazaire', 'Saint-Nazaire', ['Briac', 'Radwa']],
        ['La-Chapelle-sur-Erdre insoumise', 'La Chapelle-sur-Erdre', ['Noëlle']],
        ['Les insoumis·es Herblinois·es', 'Saint-Herblain', ['Romane']],
        ['Les insoumis·es de Clisson', 'Clisson', ['Annick', 'Françoise']],
        ['Les insoumis·es de Vertou', 'Vertou', ['Sophie', 'Michel']],
        ['Nantes Doulon-Bottière', 'Nantes', ['Charlotte', 'Emily']],
        ['Nantes Ouest', 'Nantes', ['Mathilde', 'Baptiste']],
        ['Nantes Rond-Point de Rennes / Orvault', 'Orvault', ['Cédric', 'Cassandre']],
        ['Nantes – Centre', 'Nantes', ['Elsa', 'Alizée']],
        ['Sainte-Luce et Thouaré insoumises', 'Sainte-Luce-sur-Loire', ['Delphine', 'Timéo']],
        ['Union Populaire St-Étienne-de-Montluc · Temple · Vigneux · Cordemais', 'Saint-Étienne-de-Montluc', ['Bernadette', 'Gabriel']],
        ['Jade insoumise', 'Pornic', ['Michèle', 'Nicolas']],
        ['GA Loire-Divatte', 'Le Loroux-Bottereau', ['Laurent', 'Viviane']],
        ['GA Presqu\'île', 'La Baule-Escoublac', ['Christian', 'Sylvie']],
        ['GA de la Côte Sauvage', 'Le Croisic', ['Dominique', 'Thierry']],
        ['GA Pontchâteau-Herbignac', 'Pontchâteau', ['Jacek']],
        ['Rive Gauche 44 l\'insoumise', 'Rezé', ['Julien']],
    ];
}
/** Slug stable d'un GA (pour l'URL de sa page publique). */
function lfi_nct_ga_slug($nom) {
    return substr(md5(mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $nom)))), 0, 12);
}
/** Coordonnées (lat,lng) des communes de Loire-Atlantique où on a un GA. */
function lfi_nct_commune_coords() {
    return [
        'nantes' => [47.2184, -1.5536], 'rezé' => [47.1836, -1.5497], 'reze' => [47.1836, -1.5497],
        'saint-nazaire' => [47.2735, -2.2135], 'guérande' => [47.3286, -2.4289], 'guerande' => [47.3286, -2.4289],
        'saint-lyphard' => [47.3969, -2.3122], 'machecoul' => [46.9931, -1.8256], 'couëron' => [47.2156, -1.7208], 'coueron' => [47.2156, -1.7208],
        'bouguenais' => [47.1758, -1.6167], 'savenay' => [47.3608, -1.9436], 'vallet' => [47.1614, -1.2647],
        'carquefou' => [47.2969, -1.4933], 'donges' => [47.3186, -2.0736], 'derval' => [47.6672, -1.6708],
        'paimbœuf' => [47.2889, -2.0311], 'paimboeuf' => [47.2889, -2.0311], 'pornic' => [47.1147, -2.1050],
        'saint-sébastien-sur-loire' => [47.2075, -1.5011], 'saint-sebastien-sur-loire' => [47.2075, -1.5011],
        'ancenis' => [47.3667, -1.1772], 'blain' => [47.4767, -1.7622],
        'la chapelle-sur-erdre' => [47.2989, -1.5461], 'saint-herblain' => [47.2122, -1.6486],
        'clisson' => [47.0881, -1.2861], 'vertou' => [47.1686, -1.4667], 'orvault' => [47.2717, -1.6222],
        'sainte-luce-sur-loire' => [47.2456, -1.4844], 'saint-étienne-de-montluc' => [47.2764, -1.7803], 'saint-etienne-de-montluc' => [47.2764, -1.7803],
        'pontchâteau' => [47.4381, -2.0894], 'pontchateau' => [47.4381, -2.0894],
        'le loroux-bottereau' => [47.2419, -1.3486], 'la baule-escoublac' => [47.2864, -2.3936],
        'la baule' => [47.2864, -2.3936], 'le croisic' => [47.2933, -2.5108],
    ];
}
/** Liste FUSIONNÉE des GA (seed intégré + registre carto + organigramme),
 *  dédupliquée par nom, triée. Réutilisée par l'annuaire ET la page GA. */
function lfi_nct_public_gas_list() {
    $gas = [];
    foreach (lfi_nct_gas_public_seed() as $s) {
        $nom = trim((string) ($s[0] ?? '')); if ($nom === '') continue;
        $gas[$nom] = ['nom' => $nom, 'commune' => (string) ($s[1] ?? ''), 'prenoms' => array_values($s[2] ?? [])];
    }
    if (function_exists('lfi_nct_carto_all')) foreach (lfi_nct_carto_all() as $e) {
        $nom = trim((string) ($e['nom'] ?? '')); if ($nom === '') continue;
        if (!isset($gas[$nom])) $gas[$nom] = ['nom' => $nom, 'commune' => (string) ($e['commune'] ?? ''), 'prenoms' => []];
        if (($gas[$nom]['commune'] ?? '') === '' && !empty($e['commune'])) $gas[$nom]['commune'] = (string) $e['commune'];
        foreach ([lfi_nct_carto_first_name($e['contact'] ?? ''), lfi_nct_carto_first_name($e['contact2'] ?? '')] as $pr) {
            if ($pr !== '' && !in_array($pr, $gas[$nom]['prenoms'], true)) $gas[$nom]['prenoms'][] = $pr;
        }
    }
    if (function_exists('lfi_nct_carto_people_all')) foreach (lfi_nct_carto_people_all() as $p) {
        if (($p['niveau'] ?? '') !== 'admin_ga') continue;
        $ga = trim((string) ($p['ga_nom'] ?? '')); if ($ga === '') continue;
        if (!isset($gas[$ga])) $gas[$ga] = ['nom' => $ga, 'commune' => '', 'prenoms' => []];
        $pr = lfi_nct_carto_first_name($p['nom'] ?? '');
        if ($pr !== '' && !in_array($pr, $gas[$ga]['prenoms'], true)) $gas[$ga]['prenoms'][] = $pr;
    }
    $list = array_values($gas);
    usort($list, function ($a, $b) {
        $ca = mb_strtolower((string) ($a['commune'] ?? '')); $cb = mb_strtolower((string) ($b['commune'] ?? ''));
        if ($ca === $cb) return strcasecmp((string) ($a['nom'] ?? ''), (string) ($b['nom'] ?? ''));
        return strcmp($ca, $cb);
    });
    return $list;
}

function lfi_nct_app_view_public_gas() {
    $list = lfi_nct_public_gas_list();

    lfi_nct_app_screen_open('✊ Groupes d\'Action — Loire-Atlantique', 'Trouve le groupe près de chez toi et rejoins-nous');

    /* Bannière légère aux couleurs LFI. */
    $logo = function_exists('lfi_nct_logo_lfi_url') ? lfi_nct_logo_lfi_url() : '';
    echo '<div style="background:linear-gradient(135deg,#c8102e,#8a0b20);color:#fff;border-radius:14px;padding:16px 18px;margin-bottom:14px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">';
    if ($logo) echo '<img src="' . esc_url($logo) . '" alt="La France Insoumise" style="height:46px;width:auto;background:#fff;border-radius:8px;padding:4px">';
    echo '<div style="flex:1;min-width:180px"><div style="font-size:1.15em;font-weight:900;line-height:1.2">La France Insoumise · Loire-Atlantique</div><div style="opacity:.92;font-size:.92em">' . count($list) . ' groupes d\'action près de chez toi. Rejoins le tien.</div></div>';
    echo '</div>';

    if (empty($list)) {
        echo '<div class="lfi-app-empty">L\'annuaire des groupes arrive très bientôt.</div>';
        lfi_nct_app_screen_close();
        return;
    }

    /* 🗺️ CARTE : un marqueur par GA (par commune), clic = petite fenêtre →
       « Entrer dans le GA ». */
    $coords = lfi_nct_commune_coords();
    $markers = [];
    $seen_comm = [];
    foreach ($list as $e) {
        $nom = (string) ($e['nom'] ?? ''); if ($nom === '') continue;
        $ck = mb_strtolower(trim((string) ($e['commune'] ?? '')));
        if ($ck === '' || !isset($coords[$ck])) continue;
        [$lat, $lng] = $coords[$ck];
        /* Décale légèrement les GA d'une même commune pour ne pas les empiler. */
        $n = $seen_comm[$ck] ?? 0; $seen_comm[$ck] = $n + 1;
        if ($n > 0) { $lat += 0.006 * cos($n) * $n; $lng += 0.008 * sin($n) * $n; }
        $markers[] = [
            'lat' => $lat, 'lng' => $lng, 'nom' => $nom,
            'commune' => (string) ($e['commune'] ?? ''),
            'anim' => !empty($e['prenoms']) ? implode(' & ', $e['prenoms']) : '',
            'url' => lfi_nct_app_url('ga', ['g' => lfi_nct_ga_slug($nom)]),
        ];
    }
    echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
    echo '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
    echo '<div id="ga-map" style="height:320px;border-radius:12px;overflow:hidden;margin-bottom:12px;border:1px solid #ddd;background:#eef"></div>';
    echo '<script>var LFI_GA_MARKERS=' . wp_json_encode($markers) . ';</script>';

    /* Filtre instantané (commune / nom du GA). */
    echo '<input id="ga-filter" type="search" placeholder="🔎 Ta commune ou ton quartier…" oninput="lfiGaFilter(this.value)" style="width:100%;padding:11px 13px;border:1px solid #ccc;border-radius:10px;margin-bottom:12px;font-size:1em">';

    echo '<div id="ga-list" style="display:flex;flex-direction:column;gap:8px">';
    foreach ($list as $e) {
        $nom = (string) ($e['nom'] ?? ''); if ($nom === '') continue;
        $commune = (string) ($e['commune'] ?? '');
        $anim = !empty($e['prenoms']) ? implode(' & ', $e['prenoms']) : '';
        $needle = mb_strtolower($nom . ' ' . $commune);
        $url = lfi_nct_app_url('ga', ['g' => lfi_nct_ga_slug($nom)]);
        echo '<a class="ga-item" href="' . esc_url($url) . '" data-s="' . esc_attr($needle) . '" style="text-decoration:none;color:inherit;display:block;background:#fff;border:1px solid #e6e6e6;border-left:4px solid #c8102e;border-radius:10px;padding:11px 13px">';
        echo '<div style="font-weight:800;color:#1a1a1a">🏳️ ' . esc_html($nom) . ' <span style="color:#c8102e;font-weight:700;float:right">→</span></div>';
        if ($commune !== '') echo '<div style="color:#0066a3;font-size:.9em;font-weight:600;margin-top:1px">📍 ' . esc_html($commune) . '</div>';
        if ($anim !== '') echo '<div style="color:#555;font-size:.9em;margin-top:3px">✊ Animé par <strong>' . esc_html($anim) . '</strong></div>';
        echo '</a>';
    }
    echo '</div>';
    echo '<div id="ga-none" style="display:none;color:#888;text-align:center;padding:14px">Aucun groupe trouvé pour « <span id="ga-q"></span> ». Écris-nous, on t\'oriente.</div>';

    /* Lien vers les combats/victoires (route publique existante). */
    echo '<div style="text-align:center;margin-top:16px"><a href="' . esc_url(lfi_nct_app_url('victoires')) . '" style="display:inline-block;background:#186a3b;color:#fff;padding:11px 18px;border-radius:10px;text-decoration:none;font-weight:800">🏆 Voir nos combats gagnés</a></div>';
    echo '<div class="lfi-app-help" style="margin-top:12px;text-align:center"><small>Pour préserver la vie privée, seuls les <strong>prénoms</strong> des animateur·ices sont affichés — jamais d\'email ni de nom de famille.</small></div>';

    ?>
    <script>
    function lfiGaFilter(q){
        q = (q||'').toLowerCase().trim();
        var items = document.querySelectorAll('#ga-list .ga-item'), shown = 0;
        items.forEach(function(it){
            var ok = !q || (it.getAttribute('data-s')||'').indexOf(q) !== -1;
            it.style.display = ok ? '' : 'none'; if (ok) shown++;
        });
        var none = document.getElementById('ga-none');
        if (none){ none.style.display = shown ? 'none' : 'block'; var s=document.getElementById('ga-q'); if(s) s.textContent = q; }
    }
    (function initGaMap(){
        if (typeof L === 'undefined' || !document.getElementById('ga-map')) { return setTimeout(initGaMap, 200); }
        var map = L.map('ga-map', {scrollWheelZoom:false}).setView([47.28, -1.75], 9);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:18, attribution:'© OpenStreetMap'}).addTo(map);
        var pts = [];
        (window.LFI_GA_MARKERS||[]).forEach(function(m){
            if (!m.lat) return;
            var mk = L.marker([m.lat, m.lng]).addTo(map);
            mk.bindPopup('<div style="min-width:150px"><strong>'+m.nom+'</strong><br><span style="color:#0066a3">📍 '+(m.commune||'')+'</span>'+(m.anim?'<br>✊ Animé par <strong>'+m.anim+'</strong>':'')+'<br><a href="'+m.url+'" style="display:inline-block;margin-top:6px;background:#c8102e;color:#fff;padding:5px 10px;border-radius:6px;text-decoration:none;font-weight:700">Entrer dans le GA →</a></div>');
            pts.push([m.lat, m.lng]);
        });
        if (pts.length) map.fitBounds(pts, {padding:[30,30], maxZoom:11});
    })();
    </script>
    <?php
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  PAGE PUBLIQUE D'UN GA (?vue=ga&g=slug) — infos + actualités +  *
 *  événements. Prénoms seulement, aucun email.                    *
 * ============================================================== */
function lfi_nct_app_view_public_ga() {
    $slug = isset($_GET['g']) ? sanitize_text_field(wp_unslash($_GET['g'])) : '';
    $ga = null;
    foreach (lfi_nct_public_gas_list() as $e) {
        if (lfi_nct_ga_slug($e['nom']) === $slug) { $ga = $e; break; }
    }
    if (!$ga) { wp_safe_redirect(lfi_nct_app_url('annuaire')); exit; }

    $nom = (string) $ga['nom'];
    $commune = (string) ($ga['commune'] ?? '');
    $anim = !empty($ga['prenoms']) ? implode(' & ', $ga['prenoms']) : '';
    $is_clos = (stripos($nom, 'clos toreau') !== false);

    lfi_nct_app_screen_open('🏳️ ' . $nom, $commune ?: 'Groupe d\'action France Insoumise');

    echo '<div style="background:linear-gradient(135deg,#c8102e,#8a0b20);color:#fff;border-radius:14px;padding:16px 18px;margin-bottom:14px">';
    echo '<div style="font-size:1.2em;font-weight:900;line-height:1.2">' . esc_html($nom) . '</div>';
    if ($commune) echo '<div style="opacity:.92;margin-top:2px">📍 ' . esc_html($commune) . '</div>';
    if ($anim) echo '<div style="opacity:.92;margin-top:2px">✊ Animé par <strong>' . esc_html($anim) . '</strong></div>';
    echo '</div>';
    echo '<div style="margin-bottom:12px"><a href="' . esc_url(lfi_nct_app_url('annuaire')) . '" style="color:#666;text-decoration:none">← Tous les groupes</a></div>';

    /* 📅 Actualités & événements. */
    echo '<h3 style="margin:6px 0 8px;color:#0b3d91">📅 Actualités & événements</h3>';
    $shown = false;
    if ($is_clos && function_exists('lfi_nct_events_upcoming_public')) {
        $evs = lfi_nct_events_upcoming_public(6);
        if ($evs) {
            $shown = true;
            echo '<div style="display:flex;flex-direction:column;gap:8px">';
            foreach ($evs as $ev) {
                echo '<div class="lfi-app-card" style="border-left:4px solid #186a3b"><div style="font-weight:800">' . esc_html($ev['titre'] ?? 'Événement') . '</div>';
                if (!empty($ev['quand'])) echo '<div style="color:#186a3b;font-size:.9em">🗓 ' . esc_html($ev['quand']) . '</div>';
                if (!empty($ev['lieu']))  echo '<div style="color:#555;font-size:.9em">📍 ' . esc_html($ev['lieu']) . '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
    }
    if (!$shown) {
        echo '<div class="lfi-app-help">Les prochaines actions et événements de ce groupe arrivent bientôt. <strong>Rejoins-le</strong> pour être au courant en premier.</div>';
    }

    /* CTA rejoindre (via le formulaire d'enquête / contact public). */
    echo '<div style="text-align:center;margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;justify-content:center">';
    echo '<a href="' . esc_url(lfi_nct_app_url('victoires')) . '" style="background:#186a3b;color:#fff;padding:11px 16px;border-radius:10px;text-decoration:none;font-weight:800">🏆 Nos combats gagnés</a>';
    echo '<a href="' . esc_url(home_url('/')) . '" style="background:#c8102e;color:#fff;padding:11px 16px;border-radius:10px;text-decoration:none;font-weight:800">✊ Rejoindre / nous écrire</a>';
    echo '</div>';
    echo '<div class="lfi-app-help" style="margin-top:12px;text-align:center"><small>Vie privée : seuls les <strong>prénoms</strong> des animateur·ices sont affichés.</small></div>';

    lfi_nct_app_screen_close();
}
