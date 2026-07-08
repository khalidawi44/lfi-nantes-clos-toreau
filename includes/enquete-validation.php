<?php
/**
 * VALIDATION DES ENQUÊTES — détection AUTOMATIQUE des doublons.
 *
 * Règle (automatique, toujours) : une nouvelle enquête est signalée comme
 * DOUBLON si elle correspond à une enquête déjà enregistrée par —
 *   · le même TÉLÉPHONE, OU
 *   · le même EMAIL, OU
 *   · le même NOM ET la même ADRESSE.
 *
 * Un doublon détecté est FLAGGÉ (jamais supprimé) et retiré de la file « à
 * contacter ». Le référent le relit et confirme (rejet) ou l'écarte (ce n'est
 * pas un doublon). Non destructif : la preuve prime, on ne perd rien.
 *
 * (Prochaine étape : validation par le « référent de rue » et enquête menée
 * par les locataires eux-mêmes.)
 */
if (!defined('ABSPATH')) exit;

/* ---- Normalisations ------------------------------------------ */
function lfi_nct_dup_norm($s) {
    $s = (string) $s;
    if (function_exists('remove_accents')) $s = remove_accents($s);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim($s);
}
function lfi_nct_dup_norm_phone($s) {
    $d = preg_replace('/\D+/', '', (string) $s);
    if (strlen($d) === 11 && substr($d, 0, 2) === '33') $d = '0' . substr($d, 2);
    return $d;
}

/* ---- Recherche d'un doublon pour une réponse ----------------- */
function lfi_nct_dup_find($row) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_responses';
    $phone = lfi_nct_dup_norm_phone($row->contact_tel ?? '');
    $email = strtolower(trim((string) ($row->contact_email ?? '')));
    $name  = lfi_nct_dup_norm(($row->contact_prenom ?? '') . ' ' . ($row->contact_nom ?? ''));
    $addr  = lfi_nct_dup_norm($row->adresse ?? '');
    /* Rien d'identifiant → pas de détection possible (évite les faux positifs
       sur les saisies anonymes de porte-à-porte). */
    if ($phone === '' && $email === '' && ($name === '' || $addr === '')) return null;

    $cands = $wpdb->get_results($wpdb->prepare(
        "SELECT id, contact_tel, contact_email, contact_prenom, contact_nom, adresse
         FROM $t WHERE id <> %d AND deleted_at IS NULL ORDER BY id DESC LIMIT 3000",
        (int) $row->id));
    foreach ((array) $cands as $c) {
        if ($phone !== '' && lfi_nct_dup_norm_phone($c->contact_tel) === $phone)
            return ['id' => (int) $c->id, 'raison' => 'même téléphone'];
        if ($email !== '' && strtolower(trim((string) $c->contact_email)) === $email)
            return ['id' => (int) $c->id, 'raison' => 'même email'];
        if ($name !== '' && $addr !== ''
            && lfi_nct_dup_norm($c->contact_prenom . ' ' . $c->contact_nom) === $name
            && lfi_nct_dup_norm($c->adresse) === $addr)
            return ['id' => (int) $c->id, 'raison' => 'même nom et même adresse'];
    }
    return null;
}

/* ---- File des doublons flaggés (option) ---------------------- */
function lfi_nct_dup_flag($item) {
    $l = get_option('lfi_nct_enquete_doublons', []);
    if (!is_array($l)) $l = [];
    foreach ($l as $e) if ((int) ($e['sub_id'] ?? 0) === (int) $item['sub_id']) return;
    $l[] = $item;
    if (count($l) > 500) $l = array_slice($l, -500);
    update_option('lfi_nct_enquete_doublons', $l, false);
}
function lfi_nct_dup_is_flagged($sub_id) {
    $l = get_option('lfi_nct_enquete_doublons', []);
    if (!is_array($l)) return false;
    foreach ($l as $e) if ((int) ($e['sub_id'] ?? 0) === (int) $sub_id && empty($e['ecarte'])) return true;
    return false;
}
function lfi_nct_dup_pending() {
    $l = get_option('lfi_nct_enquete_doublons', []);
    if (!is_array($l)) return [];
    return array_values(array_filter($l, function ($e) { return empty($e['traite']); }));
}
function lfi_nct_dup_resolve($sub_id, $ecarte) {
    $l = get_option('lfi_nct_enquete_doublons', []);
    if (!is_array($l)) return;
    foreach ($l as $i => $e) if ((int) ($e['sub_id'] ?? 0) === (int) $sub_id) {
        $l[$i]['traite'] = 1;
        $l[$i]['ecarte'] = $ecarte ? 1 : 0; /* écarté = « ce n'est pas un doublon » */
    }
    update_option('lfi_nct_enquete_doublons', $l, false);
}

/* ---- Hook : à la création d'une enquête (AVANT le géo-routage) ---- */
add_action('lfi_nct_submission_created', 'lfi_nct_dup_on_submission', 5, 2);
function lfi_nct_dup_on_submission($sub_id, $data = []) {
    global $wpdb;
    $sub_id = (int) $sub_id;
    if (!$sub_id) return;
    $t = $wpdb->prefix . 'lfi_nct_responses';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $sub_id));
    if (!$row) return;
    $dup = lfi_nct_dup_find($row);
    if (!$dup) return;
    lfi_nct_dup_flag([
        'sub_id'     => $sub_id,
        'matched_id' => $dup['id'],
        'raison'     => $dup['raison'],
        'adresse'    => (string) $row->adresse,
        'nom'        => trim((string) $row->contact_prenom . ' ' . (string) $row->contact_nom),
        'date'       => current_time('mysql'),
    ]);
}

/* ---- Bandeau discret admin ----------------------------------- */
function lfi_nct_dup_admin_notice() {
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can) return;
    $n = count(lfi_nct_dup_pending());
    if ($n < 1) return;
    echo '<a href="' . esc_url(lfi_nct_app_url('enquete-doublons')) . '" style="display:flex;align-items:center;gap:8px;margin:0 0 12px;padding:9px 13px;background:#fff3cd;border:1px solid #d39e00;border-radius:10px;text-decoration:none;color:#8a6d1f;font-weight:800">'
       . '<span style="font-size:1.1em">🧬</span><span>' . (int) $n . ' enquête' . ($n > 1 ? 's' : '') . ' en doublon à vérifier</span>'
       . '<span style="margin-left:auto;font-size:.85em;opacity:.8">Voir →</span></a>';
}

/* ---- Vue admin : doublons à vérifier ------------------------- */
function lfi_nct_app_view_enquete_doublons() {
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    if (!empty($_POST['lfi_dup_action']) && check_admin_referer('lfi_dup_action')) {
        $sid = (int) ($_POST['sub_id'] ?? 0);
        $act = sanitize_key($_POST['lfi_dup_action']);
        if ($sid) {
            if ($act === 'ecarter') {
                lfi_nct_dup_resolve($sid, true);   /* ce n'est pas un doublon */
            } elseif ($act === 'rejeter') {
                /* Confirmé doublon → on marque la réponse comme supprimée (corbeille). */
                global $wpdb;
                $wpdb->update($wpdb->prefix . 'lfi_nct_responses', ['deleted_at' => current_time('mysql')], ['id' => $sid]);
                lfi_nct_dup_resolve($sid, false);
            }
        }
        wp_safe_redirect(lfi_nct_app_url('enquete-doublons', ['ok' => 1]));
        exit;
    }

    lfi_nct_app_screen_open('🧬 Doublons d\'enquête', 'Détectés automatiquement — à confirmer ou écarter');
    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Traité.');

    $pending = lfi_nct_dup_pending();
    if (empty($pending)) {
        echo '<div class="lfi-app-help">Aucun doublon en attente. Une enquête est signalée ici automatiquement si le téléphone, l\'email, ou le nom + l\'adresse correspondent à une enquête déjà enregistrée.</div>';
        lfi_nct_app_screen_close();
        return;
    }
    echo '<ul class="lfi-app-list">';
    foreach (array_reverse($pending) as $e) {
        echo '<li class="lfi-app-card" style="border-left:4px solid #d39e00">';
        echo '<div class="head"><div class="who">📍 ' . esc_html($e['adresse'] ?: 'Adresse non précisée') . '</div>';
        echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html(wp_date('j M', strtotime($e['date'] ?? ''))) . '</div></div>';
        echo '<div class="meta"><span class="meta-chip">⚠️ Doublon : ' . esc_html($e['raison'] ?? '') . '</span>';
        echo '<span class="meta-chip">enquête #' . (int) $e['sub_id'] . ' ≈ #' . (int) $e['matched_id'] . '</span></div>';
        if (trim((string) ($e['nom'] ?? '')) !== '') echo '<div class="com"><strong>' . esc_html($e['nom']) . '</strong></div>';
        echo '<div class="row-actions" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">';
        echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Confirmer le doublon et mettre cette enquête à la corbeille ?\');">' . wp_nonce_field('lfi_dup_action', '_wpnonce', true, false)
           . '<input type="hidden" name="sub_id" value="' . (int) $e['sub_id'] . '">'
           . '<button type="submit" name="lfi_dup_action" value="rejeter" class="btn-primary" style="background:#c8102e">🗑 Confirmer le doublon</button></form>';
        echo '<form method="post" style="display:inline">' . wp_nonce_field('lfi_dup_action', '_wpnonce', true, false)
           . '<input type="hidden" name="sub_id" value="' . (int) $e['sub_id'] . '">'
           . '<button type="submit" name="lfi_dup_action" value="ecarter" class="btn-ghost" style="padding:6px 10px;font-size:.82em">✓ Ce n\'est pas un doublon</button></form>';
        echo '</div></li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}
