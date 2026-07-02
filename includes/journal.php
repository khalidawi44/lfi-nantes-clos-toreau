<?php
/**
 * JOURNAL DE BORD / SUIVI GÉNÉRAL du groupe d'action.
 *
 * Un fil chronologique TRANSVERSE, séparé des dossiers individuels des
 * locataires : rendez-vous avocat, échanges préfecture, décisions internes,
 * points de stratégie… Objectif : que Fabrice « se retrouve » — on ne
 * mélange pas le suivi général avec les dossiers, et on range par catégorie.
 *
 * Cloisonné par groupe d'action (chaque GA a son propre journal).
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_JOURNAL_DBVER = 'lfi_nct_journal_db_ver';

add_action('init', 'lfi_nct_journal_db_setup', 6);
function lfi_nct_journal_db_setup() {
    if (get_option(LFI_NCT_JOURNAL_DBVER) === '1') return;
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_journal';
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE $t (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        ga VARCHAR(60) DEFAULT '',
        date_evt DATE DEFAULT NULL,
        heure VARCHAR(12) DEFAULT '',
        categorie VARCHAR(40) DEFAULT 'general',
        titre VARCHAR(200) DEFAULT '',
        corps TEXT,
        pinned TINYINT(1) DEFAULT 0,
        created_by BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ga (ga),
        KEY date_evt (date_evt)
    ) $charset;");
    update_option(LFI_NCT_JOURNAL_DBVER, '1', false);
}

/** Peut utiliser le journal : admin du GA ou super-admin. */
function lfi_nct_journal_can() {
    return function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options');
}

/** Catégories (pour ne pas mélanger les sujets). */
function lfi_nct_journal_categories() {
    return [
        'juridique'   => ['⚖️', 'Juridique / Avocat'],
        'institution' => ['🏛️', 'Préfecture / Institutions'],
        'bailleur'    => ['🏢', 'Bailleur / NMH'],
        'association' => ['🤝', 'Association / Interne'],
        'general'     => ['📌', 'Général'],
    ];
}

/** Slug du GA à créditer lors d'une écriture. */
function lfi_nct_journal_write_ga() {
    return function_exists('lfi_nct_creation_ga') ? (string) lfi_nct_creation_ga() : '';
}

/** Clause SQL de cloisonnement (GA courant ; home = '' ou clos-toreau). */
function lfi_nct_journal_scope_clause() {
    global $wpdb;
    $slug = function_exists('lfi_nct_scope_ga_slug') ? (string) lfi_nct_scope_ga_slug() : '';
    if ($slug === '' || $slug === 'clos-toreau') {
        return " AND (ga = '' OR ga = 'clos-toreau')";
    }
    return $wpdb->prepare(" AND ga = %s", $slug);
}

/** Insère une entrée dans le journal du GA courant. Renvoie l'id ou 0. */
function lfi_nct_journal_add($args) {
    global $wpdb;
    $cats = lfi_nct_journal_categories();
    $cat  = sanitize_key($args['categorie'] ?? 'general');
    if (!isset($cats[$cat])) $cat = 'general';
    $date = sanitize_text_field($args['date_evt'] ?? '');
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = wp_date('Y-m-d');
    $titre = sanitize_text_field($args['titre'] ?? '');
    $corps = sanitize_textarea_field($args['corps'] ?? '');
    if ($titre === '' && $corps === '') return 0;
    $ok = $wpdb->insert($wpdb->prefix . 'lfi_nct_journal', [
        'ga'         => isset($args['ga']) ? sanitize_text_field($args['ga']) : lfi_nct_journal_write_ga(),
        'date_evt'   => $date,
        'heure'      => sanitize_text_field($args['heure'] ?? ''),
        'categorie'  => $cat,
        'titre'      => $titre,
        'corps'      => $corps,
        'pinned'     => !empty($args['pinned']) ? 1 : 0,
        'created_by' => (int) get_current_user_id(),
    ]);
    return $ok ? (int) $wpdb->insert_id : 0;
}

/* ============================================================== *
 *  VUE : Journal de bord (liste + ajout rapide)                  *
 * ============================================================== */
function lfi_nct_app_view_journal() {
    if (!lfi_nct_journal_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    global $wpdb;
    $t    = $wpdb->prefix . 'lfi_nct_journal';
    $cats = lfi_nct_journal_categories();

    /* Ajout rapide. */
    if (!empty($_POST['lfi_journal_add']) && check_admin_referer('lfi_journal_add')) {
        lfi_nct_journal_add([
            'date_evt'  => wp_unslash($_POST['date_evt'] ?? ''),
            'heure'     => wp_unslash($_POST['heure'] ?? ''),
            'categorie' => wp_unslash($_POST['categorie'] ?? 'general'),
            'titre'     => wp_unslash($_POST['titre'] ?? ''),
            'corps'     => wp_unslash($_POST['corps'] ?? ''),
            'pinned'    => !empty($_POST['pinned']),
        ]);
        wp_safe_redirect(lfi_nct_app_url('journal', ['ok' => 1]));
        exit;
    }
    /* Suppression. */
    if (!empty($_POST['lfi_journal_del']) && check_admin_referer('lfi_journal_del')) {
        $did = (int) $_POST['lfi_journal_del'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d" . lfi_nct_journal_scope_clause(), $did));
        if ($row) $wpdb->delete($t, ['id' => $did]);
        wp_safe_redirect(lfi_nct_app_url('journal', ['del' => 1]));
        exit;
    }

    $cat_f = isset($_GET['cat']) ? sanitize_key($_GET['cat']) : '';
    $where = "1=1" . lfi_nct_journal_scope_clause();
    if ($cat_f !== '' && isset($cats[$cat_f])) {
        $where .= $wpdb->prepare(" AND categorie = %s", $cat_f);
    }
    $rows = $wpdb->get_results("SELECT * FROM $t WHERE $where ORDER BY pinned DESC, date_evt DESC, created_at DESC LIMIT 500") ?: [];

    lfi_nct_app_screen_open('📓 Journal de bord', 'Suivi général du GA — séparé des dossiers locataires');
    if (!empty($_GET['ok']))  lfi_nct_app_flash('✅ Note ajoutée au journal.');
    if (!empty($_GET['del'])) lfi_nct_app_flash('🗑 Note supprimée.');

    echo '<div class="lfi-app-help">Ici, le suivi <strong>transverse</strong> : rendez-vous avocat, préfecture, décisions internes… <strong>Pas</strong> les dossiers des locataires (chacun a le sien). Range par catégorie pour t\'y retrouver.</div>';

    /* Filtres par catégorie. */
    echo '<div class="lfi-app-filter-chips">';
    $allcls = $cat_f === '' ? 'on' : '';
    echo '<a class="fc ' . esc_attr($allcls) . '" href="' . esc_url(lfi_nct_app_url('journal')) . '">Tout</a>';
    foreach ($cats as $k => $c) {
        $cls = $cat_f === $k ? 'on' : '';
        echo '<a class="fc ' . esc_attr($cls) . '" href="' . esc_url(lfi_nct_app_url('journal', ['cat' => $k])) . '">' . $c[0] . ' ' . esc_html($c[1]) . '</a>';
    }
    echo '</div>';

    /* Ajout rapide. */
    echo '<details class="lfi-journal-add" style="background:#f3f0fb;border-radius:8px;padding:10px 14px;margin:10px 0"' . (empty($rows) ? ' open' : '') . '>';
    echo '<summary style="cursor:pointer;font-weight:700;color:#4b2e83">➕ Ajouter une note</summary>';
    echo '<form method="post" class="lfi-app-form" style="margin-top:10px">';
    wp_nonce_field('lfi_journal_add');
    echo '<input type="hidden" name="lfi_journal_add" value="1">';
    echo '<label>Catégorie<select name="categorie">';
    foreach ($cats as $k => $c) echo '<option value="' . esc_attr($k) . '">' . $c[0] . ' ' . esc_html($c[1]) . '</option>';
    echo '</select></label>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
    echo '<label style="flex:1;min-width:130px">Date<input type="date" name="date_evt" value="' . esc_attr(wp_date('Y-m-d')) . '"></label>';
    echo '<label style="flex:1;min-width:110px">Heure<input type="text" name="heure" placeholder="Ex : 15h30"></label>';
    echo '</div>';
    echo '<label>Titre<input type="text" name="titre" placeholder="Ex : RDV Maître Gouache"></label>';
    echo '<label>Note<textarea name="corps" rows="4" placeholder="Ce qui a été dit, décidé, à faire…"></textarea></label>';
    echo '<label class="lfi-app-checkbox-row"><input type="checkbox" name="pinned" value="1"> 📌 Épingler en haut (consigne importante)</label>';
    echo '<button type="submit" class="btn-primary">💾 Ajouter au journal</button>';
    echo '</form>';
    echo '</details>';

    if (empty($rows)) {
        echo '<div class="lfi-app-empty">Aucune note pour l\'instant.</div>';
        lfi_nct_app_screen_close();
        return;
    }

    echo '<ul class="lfi-app-list">';
    foreach ($rows as $r) {
        $c = $cats[$r->categorie] ?? ['📌', 'Général'];
        $accent = $r->pinned ? '#c8102e' : '#4b2e83';
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . $accent . '">';
        echo '<div class="head"><div class="who">' . ($r->pinned ? '📌 ' : '') . esc_html($r->titre ?: $c[1]) . '</div>';
        echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html($r->date_evt) . ($r->heure ? ' · ' . esc_html($r->heure) : '') . '</div></div>';
        echo '<div class="meta"><span class="meta-chip">' . $c[0] . ' ' . esc_html($c[1]) . '</span></div>';
        if ($r->corps) echo '<div class="com" style="white-space:pre-wrap">' . esc_html($r->corps) . '</div>';
        echo '<div class="row-actions" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('journal-edit', ['id' => (int) $r->id])) . '">✏️ Éditer</a>';
        echo '<form method="post" style="display:inline;margin:0" onsubmit="return confirm(\'Supprimer cette note ?\');">';
        wp_nonce_field('lfi_journal_del');
        echo '<input type="hidden" name="lfi_journal_del" value="' . (int) $r->id . '">';
        echo '<button type="submit" class="btn-ghost" style="color:#c8102e;border-color:#c8102e">🗑</button>';
        echo '</form>';
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE : Éditer une note du journal                              *
 * ============================================================== */
function lfi_nct_app_view_journal_edit() {
    if (!lfi_nct_journal_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    global $wpdb;
    $t    = $wpdb->prefix . 'lfi_nct_journal';
    $cats = lfi_nct_journal_categories();
    $id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $row  = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d" . lfi_nct_journal_scope_clause(), $id)) : null;
    if (!$row) {
        lfi_nct_app_screen_open('✏️ Éditer une note');
        echo '<div class="lfi-app-empty">Note introuvable dans ton journal.</div>';
        echo '<div style="margin-top:12px"><a class="btn-primary" href="' . esc_url(lfi_nct_app_url('journal')) . '">← Retour au journal</a></div>';
        lfi_nct_app_screen_close();
        return;
    }

    if (!empty($_POST['lfi_journal_edit']) && check_admin_referer('lfi_journal_edit_' . $id)) {
        $cat = sanitize_key(wp_unslash($_POST['categorie'] ?? 'general'));
        if (!isset($cats[$cat])) $cat = 'general';
        $date = sanitize_text_field(wp_unslash($_POST['date_evt'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = $row->date_evt;
        $wpdb->update($t, [
            'categorie' => $cat,
            'date_evt'  => $date,
            'heure'     => sanitize_text_field(wp_unslash($_POST['heure'] ?? '')),
            'titre'     => sanitize_text_field(wp_unslash($_POST['titre'] ?? '')),
            'corps'     => sanitize_textarea_field(wp_unslash($_POST['corps'] ?? '')),
            'pinned'    => !empty($_POST['pinned']) ? 1 : 0,
        ], ['id' => $id]);
        wp_safe_redirect(lfi_nct_app_url('journal', ['ok' => 1]));
        exit;
    }

    lfi_nct_app_screen_open('✏️ Éditer une note', 'Journal de bord');
    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_journal_edit_' . $id);
    echo '<input type="hidden" name="lfi_journal_edit" value="1">';
    echo '<label>Catégorie<select name="categorie">';
    foreach ($cats as $k => $c) echo '<option value="' . esc_attr($k) . '"' . selected($row->categorie, $k, false) . '>' . $c[0] . ' ' . esc_html($c[1]) . '</option>';
    echo '</select></label>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
    echo '<label style="flex:1;min-width:130px">Date<input type="date" name="date_evt" value="' . esc_attr($row->date_evt) . '"></label>';
    echo '<label style="flex:1;min-width:110px">Heure<input type="text" name="heure" value="' . esc_attr($row->heure) . '" placeholder="Ex : 15h30"></label>';
    echo '</div>';
    echo '<label>Titre<input type="text" name="titre" value="' . esc_attr($row->titre) . '"></label>';
    echo '<label>Note<textarea name="corps" rows="5">' . esc_textarea($row->corps) . '</textarea></label>';
    echo '<label class="lfi-app-checkbox-row"><input type="checkbox" name="pinned" value="1" ' . checked($row->pinned, 1, false) . '> 📌 Épinglée</label>';
    echo '<button type="submit" class="btn-primary">💾 Enregistrer</button>';
    echo '</form>';
    echo '<div style="margin-top:12px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('journal')) . '">← Retour au journal</a></div>';
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  REST : ajout d'une note à distance (via la clé d'intégration) *
 * ============================================================== */
add_action('rest_api_init', function () {
    register_rest_route('lfi-nct/v1', '/journal', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_journal_rest_add',
        'permission_callback' => 'lfi_nct_ingest_rest_auth',
    ]);
});
function lfi_nct_journal_rest_add($request) {
    global $wpdb;
    $cats = lfi_nct_journal_categories();
    $cat  = sanitize_key((string) $request->get_param('categorie'));
    if (!isset($cats[$cat])) $cat = 'general';
    $date = sanitize_text_field((string) $request->get_param('date_evt'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = wp_date('Y-m-d');
    $titre = sanitize_text_field((string) $request->get_param('titre'));
    $corps = sanitize_textarea_field(wp_check_invalid_utf8((string) $request->get_param('corps')));
    if ($titre === '' && $corps === '') {
        return new WP_REST_Response(['ok' => false, 'error' => 'note_vide'], 400);
    }
    $ok = $wpdb->insert($wpdb->prefix . 'lfi_nct_journal', [
        'ga'         => sanitize_text_field((string) $request->get_param('ga')),
        'date_evt'   => $date,
        'heure'      => sanitize_text_field((string) $request->get_param('heure')),
        'categorie'  => $cat,
        'titre'      => $titre,
        'corps'      => $corps,
        'pinned'     => $request->get_param('pinned') ? 1 : 0,
        'created_by' => 0,
    ]);
    if (!$ok) return new WP_REST_Response(['ok' => false, 'error' => 'insert_failed'], 500);
    return new WP_REST_Response(['ok' => true, 'id' => (int) $wpdb->insert_id], 200);
}
