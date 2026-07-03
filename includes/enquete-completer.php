<?php
/**
 * FICHES À COMPLÉTER — rappel pour le·la responsable du locataire.
 *
 * Une enquête peut être créée puis COMPLÉTÉE plus tard (on revient chez la
 * personne : faire signer l'adhésion, renseigner un champ manquant…). Ce module
 * repère les fiches incomplètes et affiche un rappel clair dans la console.
 *
 * Deux sources d'« incomplet » :
 *   1) AUTO : champ obligatoire vide (nom, moyen de recontact, adresse) ;
 *   2) MANUEL : le·la responsable note « à compléter » (ex. « adhésion à faire
 *      signer ») — car certaines choses (une signature papier) ne se devinent pas.
 *
 * Rien n'est bloquant à la saisie terrain : on RAPPELLE, on ne verrouille pas.
 */
if (!defined('ABSPATH')) exit;

/* --- Stockage --- */
function lfi_nct_enq_todo_map() {
    $o = get_option('lfi_nct_enq_acompleter', []);
    return is_array($o) ? $o : [];
}
function lfi_nct_enq_todo_note($id) {
    $m = lfi_nct_enq_todo_map();
    return isset($m[$id]) ? (string) ($m[$id]['note'] ?? '') : '';
}
function lfi_nct_enq_todo_set($id, $note, $by = 0) {
    $m = lfi_nct_enq_todo_map();
    $note = trim($note);
    if ($note === '') unset($m[(int) $id]);
    else $m[(int) $id] = ['note' => $note, 'by' => (int) $by, 'date' => current_time('mysql')];
    update_option('lfi_nct_enq_acompleter', $m, false);
}
function lfi_nct_enq_ok_set() {
    $o = get_option('lfi_nct_enq_complete_ok', []);
    return is_array($o) ? array_map('intval', $o) : [];
}
function lfi_nct_enq_ok_add($id) {
    $s = lfi_nct_enq_ok_set();
    if (!in_array((int) $id, $s, true)) { $s[] = (int) $id; update_option('lfi_nct_enq_complete_ok', $s, false); }
}
function lfi_nct_enq_ok_remove($id) {
    $s = array_values(array_diff(lfi_nct_enq_ok_set(), [(int) $id]));
    update_option('lfi_nct_enq_complete_ok', $s, false);
}

/** Champs obligatoires manquants sur une fiche (détection automatique). */
function lfi_nct_enq_row_missing($r) {
    $miss = [];
    if (trim((string) $r->contact_nom) === '' && trim((string) $r->contact_prenom) === '') $miss[] = 'nom de la personne';
    if (trim((string) $r->contact_tel) === '' && trim((string) $r->contact_email) === '')   $miss[] = 'téléphone ou email';
    if (trim((string) $r->adresse) === '')                                                   $miss[] = 'adresse';
    return $miss;
}

/** Fiches à compléter dans le périmètre courant (auto-incomplètes OU flaguées). */
function lfi_nct_enq_todo_rows($limit = 800) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_responses';
    $sc = function_exists('lfi_nct_responses_scope_clause') ? lfi_nct_responses_scope_clause() : '';
    $rows = $wpdb->get_results("SELECT * FROM $t WHERE deleted_at IS NULL" . $sc . " ORDER BY submitted_at DESC LIMIT " . (int) $limit) ?: [];
    $manual = lfi_nct_enq_todo_map();
    $ok = lfi_nct_enq_ok_set();
    $out = [];
    foreach ($rows as $r) {
        $id = (int) $r->id;
        $note = isset($manual[$id]) ? (string) ($manual[$id]['note'] ?? '') : '';
        $miss = in_array($id, $ok, true) ? [] : lfi_nct_enq_row_missing($r);
        if ($note === '' && empty($miss)) continue;
        $out[] = ['row' => $r, 'note' => $note, 'missing' => $miss];
    }
    return $out;
}
function lfi_nct_enq_todo_count() { return count(lfi_nct_enq_todo_rows()); }

/** Nom lisible d'une fiche. */
function lfi_nct_enq_row_name($r) {
    $n = trim(($r->contact_prenom ?? '') . ' ' . ($r->contact_nom ?? ''));
    if ($n === '') $n = trim((string) $r->adresse) ?: ('Fiche #' . (int) $r->id);
    return $n;
}

/* --- Rappel (bandeau) sur le tableau de bord admin --- */
function lfi_nct_enq_todo_notice() {
    if (!function_exists('lfi_nct_enq_can_manage') || !lfi_nct_enq_can_manage()) return;
    $n = lfi_nct_enq_todo_count();
    if ($n < 1) return;
    $url = lfi_nct_app_url('enquetes-a-completer');
    echo '<a href="' . esc_url($url) . '" style="text-decoration:none;color:inherit;display:block">';
    echo '<div style="margin:0 0 12px;background:linear-gradient(135deg,#c8102e,#e0455e);color:#fff;border-radius:14px;padding:13px 16px;display:flex;align-items:center;gap:12px">';
    echo '<div style="font-size:1.7em">📝</div>';
    echo '<div style="flex:1"><div style="font-weight:900">' . (int) $n . ' fiche' . ($n > 1 ? 's' : '') . ' à compléter</div>';
    echo '<div style="font-size:.86em;opacity:.95">Champ manquant ou adhésion à faire signer — retourne les finir avec la personne</div></div>';
    echo '<div style="background:rgba(255,255,255,.22);border-radius:20px;padding:6px 12px;font-weight:800;font-size:.85em">Voir →</div>';
    echo '</div></a>';
}

/* --- Vue : liste des fiches à compléter --- */
function lfi_nct_app_view_enquetes_a_completer() {
    if (!function_exists('lfi_nct_enq_can_manage') || !lfi_nct_enq_can_manage()) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    /* Marquer comme complète (efface le flag manuel + ignore l'auto-détection). */
    if (!empty($_POST['lfi_enq_todo_done']) && check_admin_referer('lfi_enq_todo')) {
        $id = (int) $_POST['lfi_enq_todo_done'];
        lfi_nct_enq_todo_set($id, '');
        lfi_nct_enq_ok_add($id);
        wp_safe_redirect(lfi_nct_app_url('enquetes-a-completer', ['done' => 1])); exit;
    }
    /* (Re)noter une fiche « à compléter ». */
    if (!empty($_POST['lfi_enq_todo_flag']) && check_admin_referer('lfi_enq_todo')) {
        $id = (int) $_POST['lfi_enq_todo_flag'];
        lfi_nct_enq_todo_set($id, sanitize_text_field(wp_unslash($_POST['note'] ?? '')), get_current_user_id());
        lfi_nct_enq_ok_remove($id);
        wp_safe_redirect(lfi_nct_app_url('enquetes-a-completer', ['flagged' => 1])); exit;
    }

    lfi_nct_app_screen_open('📝 Fiches à compléter', 'On revient finir ce qui manque — champ obligatoire ou adhésion à signer');
    if (!empty($_GET['done']))    lfi_nct_app_flash('✅ Fiche marquée complète.');
    if (!empty($_GET['flagged'])) lfi_nct_app_flash('🔖 Fiche notée « à compléter ».');

    $rows = lfi_nct_enq_todo_rows();
    if (empty($rows)) {
        echo '<div class="lfi-app-empty">🎉 Rien à compléter — toutes les fiches du périmètre sont en ordre.</div>';
        echo '<div style="text-align:center;margin-top:10px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('enquetes')) . '">← Toutes les enquêtes</a></div>';
        lfi_nct_app_screen_close();
        return;
    }

    echo '<ul class="lfi-app-list">';
    foreach ($rows as $it) {
        $r = $it['row']; $id = (int) $r->id;
        echo '<li class="lfi-app-card" style="border-left:4px solid #c8102e">';
        echo '<div class="head"><div class="who">' . esc_html(lfi_nct_enq_row_name($r)) . '</div>';
        if (trim((string) $r->adresse) !== '') echo '<div class="when" style="font-size:.8em;color:#666">' . esc_html($r->adresse) . '</div>';
        echo '</div>';
        $reasons = [];
        if ($it['note'] !== '') $reasons[] = '🔖 ' . esc_html($it['note']);
        foreach ($it['missing'] as $m) $reasons[] = '⚠️ manque : ' . esc_html($m);
        echo '<div class="com" style="font-size:.92em;color:#555">' . implode('<br>', $reasons) . '</div>';
        echo '<div class="row-actions" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">';
        echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('enquete-edit', ['id' => $id])) . '">✏️ Compléter la fiche</a>';
        echo '<form method="post" style="display:inline">' . wp_nonce_field('lfi_enq_todo', '_wpnonce', true, false)
           . '<input type="hidden" name="lfi_enq_todo_done" value="' . $id . '">'
           . '<button type="submit" class="btn-ghost" style="color:#186a3b">✅ Marquer complète</button></form>';
        echo '</div></li>';
    }
    echo '</ul>';
    echo '<div style="text-align:center;margin-top:10px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('enquetes')) . '">← Toutes les enquêtes</a></div>';
    lfi_nct_app_screen_close();
}
