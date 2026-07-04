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
                'id'      => lfi_nct_carto_next_id($list),
                'nom'     => $nom,
                'commune' => sanitize_text_field(wp_unslash($_POST['commune'] ?? '')),
                'email'   => sanitize_email(wp_unslash($_POST['email'] ?? '')),
                'contact' => sanitize_text_field(wp_unslash($_POST['contact'] ?? '')),
                'tel'     => sanitize_text_field(wp_unslash($_POST['tel'] ?? '')),
                'statut'  => 'a_contacter',
                'notes'   => '',
            ];
            lfi_nct_carto_save($list);
        }
        wp_safe_redirect(add_query_arg('added', 1, $back)); exit;
    }

    /* Import en masse : une ligne par GA, champs séparés par ; ou tabulation.
       Ordre : Nom ; Commune ; Email ; Contact ; Téléphone. */
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
                'id'      => lfi_nct_carto_next_id($list),
                'nom'     => $nom,
                'commune' => sanitize_text_field(trim($c[1] ?? '')),
                'email'   => sanitize_email(trim($c[2] ?? '')),
                'contact' => sanitize_text_field(trim($c[3] ?? '')),
                'tel'     => sanitize_text_field(trim($c[4] ?? '')),
                'statut'  => 'a_contacter',
                'notes'   => '',
            ];
            $n++;
        }
        lfi_nct_carto_save($list);
        wp_safe_redirect(add_query_arg('imported', $n, $back)); exit;
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

    $list = lfi_nct_carto_all();
    $statuts = lfi_nct_carto_statuts();
    $filter = isset($_GET['f']) ? sanitize_key($_GET['f']) : '';

    lfi_nct_app_screen_open('🗺️ Cartographie LFI Loire-Atlantique', 'Registre privé — inviter les GA, suivre où on en est');
    if (!empty($_GET['added']))    lfi_nct_app_flash('✅ GA ajouté.');
    if (isset($_GET['imported']))  lfi_nct_app_flash('✅ ' . (int) $_GET['imported'] . ' GA importé(s).');
    if (!empty($_GET['deleted']))  lfi_nct_app_flash('🗑 GA retiré.');
    echo '<div class="lfi-app-help" style="background:#fdeef0;border-left:4px solid #c8102e"><small>🔒 <strong>Privé</strong> — visible par toi seul (superadmin). Ce sont des contacts de GA, aucune donnée d\'enquête ni de locataire.</small></div>';

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
            if (!empty($e['contact'])) echo '<span class="meta-chip">👤 ' . esc_html($e['contact']) . '</span>';
            if (!empty($e['email'])) echo '<a class="meta-chip" href="mailto:' . esc_attr($e['email']) . '">✉️ ' . esc_html($e['email']) . '</a>';
            if (!empty($e['tel'])) echo '<a class="meta-chip" href="tel:' . esc_attr(preg_replace('/[^\d+]/', '', $e['tel'])) . '">📞 ' . esc_html($e['tel']) . '</a>';
            echo '</div>';
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
    echo '<label>Email de contact<input type="email" name="email" placeholder="ga.reze@…"></label>';
    echo '<label>Nom du contact (admin)<input type="text" name="contact" placeholder="ex. Prénom Nom"></label>';
    echo '<label>Téléphone<input type="tel" name="tel"></label>';
    echo '<button type="submit" class="btn-primary" style="background:#186a3b">Ajouter</button></form></details>';

    /* Import en masse. */
    echo '<details class="lfi-app-card" style="border-left:4px solid #4b2e83;margin-top:10px"><summary style="cursor:pointer;font-weight:800;color:#4b2e83">📥 Importer ma liste (en masse)</summary>';
    echo '<div class="lfi-app-help" style="margin:6px 0"><small>Une ligne par GA. Champs séparés par « ; » (ou tabulation) dans l\'ordre :<br><code>Nom du GA ; Commune ; Email ; Contact ; Téléphone</code><br>Seul le nom est obligatoire ; laisse vide ce que tu n\'as pas.</small></div>';
    echo '<form method="post">' . wp_nonce_field('lfi_carto_import', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_carto_import" value="1">';
    echo '<textarea name="bulk" style="width:100%;height:160px;font-family:monospace;font-size:.82em;padding:8px;border:1px solid #ccc;border-radius:8px" placeholder="GA Rezé Centre ; Rezé ; ga.reze@exemple.fr ; Prénom Nom ; 06…&#10;GA Saint-Herblain ; Saint-Herblain ; ga.herblain@exemple.fr ; ; "></textarea>';
    echo '<button type="submit" class="btn-primary" style="background:#4b2e83;margin-top:8px">Importer</button></form></details>';

    lfi_nct_app_screen_close();
}
