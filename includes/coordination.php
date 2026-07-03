<?php
/**
 * COORDINATION DU GA — disponibilités & propositions d'actions.
 *
 * Trois écrans :
 *  - « Mes disponibilités » : chacun déclare quand il/elle est libre (date +
 *    créneau + types d'action : collage, tractage, porte-à-porte…).
 *  - « Dispos communes » : la vue partagée — qui est dispo, quand, pour quoi —
 *    pour caler une action sur les créneaux où on est le plus nombreux.
 *  - « Propositions » : un espace où chacun propose une action/un événement ;
 *    les autres disent « ça m'intéresse » (compteur + qui).
 *
 * Règle événements : une proposition n'est PAS un événement publié. Quand une
 * action est retenue, l'événement se crée d'abord sur Action Populaire, puis se
 * transcrit (voir module événements). Ici, on prépare et on se coordonne.
 */
if (!defined('ABSPATH')) exit;

define('LFI_NCT_COORD_DBVER', 'lfi_nct_coord_dbver');

add_action('init', 'lfi_nct_coord_db_setup', 6);
function lfi_nct_coord_db_setup() {
    if (get_option(LFI_NCT_COORD_DBVER) === '1') return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $d = $wpdb->prefix . 'lfi_nct_dispos';
    dbDelta("CREATE TABLE $d (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED DEFAULT 0,
        ga VARCHAR(60) DEFAULT '',
        date_dispo DATE DEFAULT NULL,
        creneau VARCHAR(20) DEFAULT 'journee',
        types VARCHAR(160) DEFAULT '',
        note VARCHAR(255) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ga (ga),
        KEY date_dispo (date_dispo),
        KEY user_id (user_id)
    ) $charset;");
    $p = $wpdb->prefix . 'lfi_nct_propositions';
    dbDelta("CREATE TABLE $p (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED DEFAULT 0,
        ga VARCHAR(60) DEFAULT '',
        titre VARCHAR(200) DEFAULT '',
        type VARCHAR(30) DEFAULT 'autre',
        date_souhaitee DATE DEFAULT NULL,
        lieu VARCHAR(200) DEFAULT '',
        description TEXT,
        statut VARCHAR(20) DEFAULT 'proposee',
        interesses TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ga (ga),
        KEY statut (statut)
    ) $charset;");
    update_option(LFI_NCT_COORD_DBVER, '1', false);
}

/** Accès coordination : tout membre connecté (adhérent GA / admin). */
function lfi_nct_coord_can() {
    return is_user_logged_in();
}

/** Peut modérer (changer statut, supprimer la proposition d'un autre). */
function lfi_nct_coord_can_moderate() {
    return current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
}

/** GA à créditer à l'écriture (cloisonnement). */
function lfi_nct_coord_ga() {
    return function_exists('lfi_nct_creation_ga') ? (string) lfi_nct_creation_ga() : '';
}

/** Clause de cloisonnement par GA. */
function lfi_nct_coord_scope_clause($col = 'ga') {
    global $wpdb;
    $slug = function_exists('lfi_nct_scope_ga_slug') ? (string) lfi_nct_scope_ga_slug() : '';
    if ($slug === '' || $slug === 'clos-toreau') return " AND ($col = '' OR $col = 'clos-toreau')";
    return $wpdb->prepare(" AND $col = %s", $slug);
}

/** Types d'action (collage, tractage, porte-à-porte…). */
function lfi_nct_coord_action_types() {
    return [
        'collage'       => ['🖌️', 'Collage'],
        'tractage'      => ['📄', 'Tractage'],
        'porte-a-porte' => ['🚪', 'Porte-à-porte'],
        'reunion'       => ['🤝', 'Réunion'],
        'autre'         => ['✨', 'Autre'],
    ];
}

/** Créneaux de la journée. */
function lfi_nct_coord_creneaux() {
    return [
        'matin'   => 'Matin (9h–12h)',
        'aprem'   => 'Après-midi (14h–18h)',
        'soiree'  => 'Soirée (18h–21h)',
        'journee' => 'Toute la journée',
    ];
}

/** Nom lisible d'un utilisateur. */
function lfi_nct_coord_user_name($uid) {
    $u = get_userdata((int) $uid);
    return $u ? ($u->display_name ?: $u->user_login) : 'Membre';
}

/* ============================================================== *
 *  ÉCRAN : Mes disponibilités                                    *
 * ============================================================== */
function lfi_nct_app_view_dispos() {
    if (!lfi_nct_coord_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dispos';
    $uid = get_current_user_id();

    if (!empty($_POST['lfi_dispo_add']) && check_admin_referer('lfi_dispo_add')) {
        $date = sanitize_text_field(wp_unslash($_POST['date_dispo'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = '';
        $creneau = sanitize_key($_POST['creneau'] ?? 'journee');
        if (!isset(lfi_nct_coord_creneaux()[$creneau])) $creneau = 'journee';
        $types_in = (array) ($_POST['types'] ?? []);
        $valid = array_keys(lfi_nct_coord_action_types());
        $types = implode(',', array_values(array_intersect($valid, array_map('sanitize_key', $types_in))));
        $note = sanitize_text_field(wp_unslash($_POST['note'] ?? ''));
        if ($date !== '') {
            $wpdb->insert($t, [
                'user_id'    => $uid,
                'ga'         => lfi_nct_coord_ga(),
                'date_dispo' => $date,
                'creneau'    => $creneau,
                'types'      => $types,
                'note'       => $note,
            ]);
        }
        wp_safe_redirect(lfi_nct_app_url('dispos', ['ok' => 1]));
        exit;
    }
    if (!empty($_POST['lfi_dispo_del']) && check_admin_referer('lfi_dispo_del')) {
        $did = (int) $_POST['lfi_dispo_del'];
        /* On ne supprime que SA propre dispo. */
        $wpdb->delete($t, ['id' => $did, 'user_id' => $uid]);
        wp_safe_redirect(lfi_nct_app_url('dispos', ['del' => 1]));
        exit;
    }

    lfi_nct_app_screen_open('🗓 Mes disponibilités', 'Dis quand tu es libre — on cale les actions dessus');
    if (!empty($_GET['ok']))  lfi_nct_app_flash('✅ Disponibilité ajoutée.');
    if (!empty($_GET['del'])) lfi_nct_app_flash('🗑 Disponibilité retirée.');

    echo '<div style="text-align:center;margin:6px 0"><a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dispos-communes')) . '">👥 Voir les dispos de tout le monde</a></div>';

    /* Formulaire d'ajout. */
    $creneaux = lfi_nct_coord_creneaux();
    $types    = lfi_nct_coord_action_types();
    echo '<form method="post" class="lfi-app-card" style="border-left:4px solid #186a3b">';
    echo wp_nonce_field('lfi_dispo_add', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_dispo_add" value="1">';
    echo '<div class="head"><div class="who">➕ Ajouter une disponibilité</div></div>';
    echo '<div style="margin:6px 0"><label>Date<br><input type="date" name="date_dispo" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px"></label></div>';
    echo '<div style="margin:6px 0"><label>Créneau<br><select name="creneau" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px">';
    foreach ($creneaux as $k => $lbl) echo '<option value="' . esc_attr($k) . '">' . esc_html($lbl) . '</option>';
    echo '</select></label></div>';
    echo '<div style="margin:6px 0"><div style="font-weight:600;margin-bottom:4px">Pour quelles actions ?</div>';
    foreach ($types as $k => $meta) {
        echo '<label style="display:inline-block;margin:2px 8px 2px 0"><input type="checkbox" name="types[]" value="' . esc_attr($k) . '"> ' . $meta[0] . ' ' . esc_html($meta[1]) . '</label>';
    }
    echo '</div>';
    echo '<div style="margin:6px 0"><label>Note (optionnel)<br><input type="text" name="note" maxlength="255" placeholder="ex : dispo après 17h" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px"></label></div>';
    echo '<div style="margin-top:8px"><button type="submit" class="btn-primary" style="background:#186a3b">Enregistrer</button></div>';
    echo '</form>';

    /* Mes prochaines dispos. */
    $today = wp_date('Y-m-d');
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $t WHERE user_id = %d AND date_dispo >= %s" . lfi_nct_coord_scope_clause() . " ORDER BY date_dispo ASC, creneau ASC",
        $uid, $today)) ?: [];
    echo '<h3 style="margin:18px 0 6px">Mes prochaines disponibilités</h3>';
    if (empty($rows)) {
        echo '<div class="lfi-app-help">Aucune disponibilité à venir. Ajoute-en une ci-dessus.</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        foreach ($rows as $r) lfi_nct_coord_render_dispo_line($r, true);
        echo '</ul>';
    }
    lfi_nct_app_screen_close();
}

/** Affiche une ligne de dispo ; $own = affiche le bouton supprimer. */
function lfi_nct_coord_render_dispo_line($r, $own = false) {
    $creneaux = lfi_nct_coord_creneaux();
    $types    = lfi_nct_coord_action_types();
    $when = wp_date('l j M', strtotime($r->date_dispo));
    echo '<li class="lfi-app-card" style="border-left:4px solid #186a3b">';
    echo '<div class="head"><div class="who">📅 ' . esc_html(ucfirst($when)) . '</div>';
    echo '<div class="when" style="font-size:.8em;color:#666">' . esc_html($creneaux[$r->creneau] ?? $r->creneau) . '</div></div>';
    if (!$own) echo '<div class="com"><strong>' . esc_html(lfi_nct_coord_user_name($r->user_id)) . '</strong></div>';
    $tk = array_filter(explode(',', (string) $r->types));
    if ($tk) {
        echo '<div class="meta">';
        foreach ($tk as $k) if (isset($types[$k])) echo '<span class="meta-chip">' . $types[$k][0] . ' ' . esc_html($types[$k][1]) . '</span>';
        echo '</div>';
    }
    if (trim((string) $r->note) !== '') echo '<div class="com" style="color:#555">' . esc_html($r->note) . '</div>';
    if ($own) {
        echo '<form method="post" style="margin-top:6px" onsubmit="return confirm(\'Retirer cette disponibilité ?\');">';
        echo wp_nonce_field('lfi_dispo_del', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_dispo_del" value="' . (int) $r->id . '">';
        echo '<button type="submit" class="btn-ghost" style="padding:4px 10px;font-size:.82em;color:#c8102e">🗑 Retirer</button>';
        echo '</form>';
    }
    echo '</li>';
}

/* ============================================================== *
 *  ÉCRAN : Dispos communes (vue partagée)                        *
 * ============================================================== */
function lfi_nct_app_view_dispos_communes() {
    if (!lfi_nct_coord_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dispos';
    $today = wp_date('Y-m-d');
    $types = lfi_nct_coord_action_types();

    $filter = isset($_GET['type']) ? sanitize_key($_GET['type']) : '';
    if ($filter !== '' && !isset($types[$filter])) $filter = '';

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $t WHERE date_dispo >= %s" . lfi_nct_coord_scope_clause() . " ORDER BY date_dispo ASC, creneau ASC",
        $today)) ?: [];
    if ($filter !== '') {
        $rows = array_filter($rows, function ($r) use ($filter) {
            return in_array($filter, array_filter(explode(',', (string) $r->types)), true);
        });
    }

    lfi_nct_app_screen_open('👥 Dispos communes', 'Qui est libre, quand — pour caler nos actions');
    echo '<div style="text-align:center;margin:6px 0"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dispos')) . '">🗓 Mes disponibilités</a></div>';

    /* Filtres par type d'action. */
    echo '<div class="lfi-app-filter-chips" style="margin:8px 0">';
    $allcls = $filter === '' ? 'on' : '';
    echo '<a class="fc ' . esc_attr($allcls) . '" href="' . esc_url(lfi_nct_app_url('dispos-communes')) . '">Toutes</a>';
    foreach ($types as $k => $meta) {
        $cls = $filter === $k ? 'on' : '';
        echo '<a class="fc ' . esc_attr($cls) . '" href="' . esc_url(lfi_nct_app_url('dispos-communes', ['type' => $k])) . '">' . $meta[0] . ' ' . esc_html($meta[1]) . '</a>';
    }
    echo '</div>';

    if (empty($rows)) {
        echo '<div class="lfi-app-card" style="border:2px solid #186a3b"><div class="com">Personne n\'a encore déclaré de disponibilité' . ($filter !== '' ? ' pour ce type d\'action' : '') . '. Sois le/la premier·e : <a href="' . esc_url(lfi_nct_app_url('dispos')) . '">ajoute la tienne</a>.</div></div>';
        lfi_nct_app_screen_close();
        return;
    }

    /* Regroupé par date (du plus proche au plus loin). */
    $by_date = [];
    foreach ($rows as $r) $by_date[$r->date_dispo][] = $r;
    foreach ($by_date as $date => $items) {
        echo '<h3 style="margin:16px 0 6px;color:#186a3b">📅 ' . esc_html(ucfirst(wp_date('l j M Y', strtotime($date)))) . ' <span style="font-weight:400;color:#888;font-size:.8em">— ' . count($items) . ' dispo(s)</span></h3>';
        echo '<ul class="lfi-app-list">';
        foreach ($items as $r) lfi_nct_coord_render_dispo_line($r, false);
        echo '</ul>';
    }
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  ÉCRAN : Propositions d'actions / événements                   *
 * ============================================================== */
function lfi_nct_app_view_propositions() {
    if (!lfi_nct_coord_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_propositions';
    $uid = get_current_user_id();
    $types = lfi_nct_coord_action_types();

    /* Ajout d'une proposition. */
    if (!empty($_POST['lfi_prop_add']) && check_admin_referer('lfi_prop_add')) {
        $titre = sanitize_text_field(wp_unslash($_POST['titre'] ?? ''));
        $type  = sanitize_key($_POST['type'] ?? 'autre');
        if (!isset($types[$type])) $type = 'autre';
        $date  = sanitize_text_field(wp_unslash($_POST['date_souhaitee'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = null;
        $lieu  = sanitize_text_field(wp_unslash($_POST['lieu'] ?? ''));
        $desc  = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        if ($titre !== '') {
            $wpdb->insert($t, [
                'user_id'        => $uid,
                'ga'             => lfi_nct_coord_ga(),
                'titre'          => $titre,
                'type'           => $type,
                'date_souhaitee' => $date,
                'lieu'           => $lieu,
                'description'    => $desc,
                'statut'         => 'proposee',
                'interesses'     => wp_json_encode([]),
            ]);
        }
        wp_safe_redirect(lfi_nct_app_url('propositions', ['ok' => 1]));
        exit;
    }
    /* « Ça m'intéresse » (toggle). */
    if (!empty($_POST['lfi_prop_interest']) && check_admin_referer('lfi_prop_interest')) {
        $pid = (int) $_POST['lfi_prop_interest'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $pid));
        if ($row) {
            $list = json_decode((string) $row->interesses, true);
            if (!is_array($list)) $list = [];
            if (in_array($uid, $list, true)) $list = array_values(array_diff($list, [$uid]));
            else $list[] = $uid;
            $wpdb->update($t, ['interesses' => wp_json_encode(array_values(array_unique($list)))], ['id' => $pid]);
        }
        wp_safe_redirect(lfi_nct_app_url('propositions'));
        exit;
    }
    /* Changer le statut (modération) / supprimer. */
    if (!empty($_POST['lfi_prop_statut']) && check_admin_referer('lfi_prop_statut') && lfi_nct_coord_can_moderate()) {
        $pid = (int) $_POST['lfi_prop_statut'];
        $st  = sanitize_key($_POST['statut'] ?? 'proposee');
        if (in_array($st, ['proposee', 'retenue', 'planifiee', 'refusee'], true)) {
            $wpdb->update($t, ['statut' => $st], ['id' => $pid]);
        }
        wp_safe_redirect(lfi_nct_app_url('propositions'));
        exit;
    }
    if (!empty($_POST['lfi_prop_del']) && check_admin_referer('lfi_prop_del')) {
        $pid = (int) $_POST['lfi_prop_del'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT user_id FROM $t WHERE id = %d", $pid));
        /* On supprime sa propre proposition, ou n'importe laquelle si modérateur. */
        if ($row && ((int) $row->user_id === $uid || lfi_nct_coord_can_moderate())) {
            $wpdb->delete($t, ['id' => $pid]);
        }
        wp_safe_redirect(lfi_nct_app_url('propositions', ['del' => 1]));
        exit;
    }

    lfi_nct_app_screen_open('💡 Propositions d\'actions', 'Propose une action ou un événement — les autres disent « ça m\'intéresse »');
    if (!empty($_GET['ok']))  lfi_nct_app_flash('✅ Proposition publiée.');
    if (!empty($_GET['del'])) lfi_nct_app_flash('🗑 Proposition supprimée.');

    /* Formulaire de proposition. */
    echo '<details class="lfi-app-card" style="border-left:4px solid #4b2e83"><summary style="cursor:pointer;font-weight:700;color:#4b2e83">➕ Proposer une action / un événement</summary>';
    echo '<form method="post" style="margin-top:8px">';
    echo wp_nonce_field('lfi_prop_add', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_prop_add" value="1">';
    echo '<div style="margin:6px 0"><label>Titre<br><input type="text" name="titre" required maxlength="200" placeholder="ex : Collage quartier Clos Toreau" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px"></label></div>';
    echo '<div style="margin:6px 0"><label>Type<br><select name="type" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px">';
    foreach ($types as $k => $meta) echo '<option value="' . esc_attr($k) . '">' . esc_html($meta[1]) . '</option>';
    echo '</select></label></div>';
    echo '<div style="margin:6px 0"><label>Date souhaitée (optionnel)<br><input type="date" name="date_souhaitee" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px"></label></div>';
    echo '<div style="margin:6px 0"><label>Lieu (optionnel)<br><input type="text" name="lieu" maxlength="200" placeholder="ex : place du Pays Basque" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px"></label></div>';
    echo '<div style="margin:6px 0"><label>Description (optionnel)<br><textarea name="description" rows="3" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px"></textarea></label></div>';
    echo '<div style="margin-top:8px"><button type="submit" class="btn-primary" style="background:#4b2e83">Publier la proposition</button></div>';
    echo '</form></details>';

    /* Liste des propositions (à venir d'abord, puis récentes). */
    $rows = $wpdb->get_results("SELECT * FROM $t WHERE 1=1" . lfi_nct_coord_scope_clause() . " ORDER BY (date_souhaitee IS NULL) ASC, date_souhaitee ASC, created_at DESC LIMIT 200") ?: [];
    if (empty($rows)) {
        echo '<div class="lfi-app-help">Aucune proposition pour l\'instant. Lance la première !</div>';
        lfi_nct_app_screen_close();
        return;
    }
    $statut_meta = [
        'proposee'  => ['#0066a3', 'Proposée'],
        'retenue'   => ['#186a3b', 'Retenue'],
        'planifiee' => ['#4b2e83', 'Planifiée'],
        'refusee'   => ['#888',    'Écartée'],
    ];
    echo '<ul class="lfi-app-list">';
    foreach ($rows as $r) {
        $sm = $statut_meta[$r->statut] ?? $statut_meta['proposee'];
        $tmeta = $types[$r->type] ?? ['✨', 'Autre'];
        $list = json_decode((string) $r->interesses, true);
        if (!is_array($list)) $list = [];
        $i_am = in_array($uid, $list, true);
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . esc_attr($sm[0]) . '">';
        echo '<div class="head"><div class="who">' . $tmeta[0] . ' ' . esc_html($r->titre) . '</div>';
        echo '<div class="badge" style="background:' . esc_attr($sm[0]) . ';color:#fff">' . esc_html($sm[1]) . '</div></div>';
        echo '<div class="meta">';
        echo '<span class="meta-chip">' . esc_html($tmeta[1]) . '</span>';
        if ($r->date_souhaitee) echo '<span class="meta-chip">📅 ' . esc_html(ucfirst(wp_date('j M Y', strtotime($r->date_souhaitee)))) . '</span>';
        if (trim((string) $r->lieu) !== '') echo '<span class="meta-chip">📍 ' . esc_html($r->lieu) . '</span>';
        echo '</div>';
        if (trim((string) $r->description) !== '') echo '<div class="com" style="white-space:pre-wrap">' . esc_html($r->description) . '</div>';
        echo '<div class="com" style="font-size:.82em;color:#888">Proposé par ' . esc_html(lfi_nct_coord_user_name($r->user_id)) . '</div>';

        /* Intéressés. */
        $names = array_map('lfi_nct_coord_user_name', $list);
        echo '<div class="com" style="font-size:.86em"><strong>👍 ' . count($list) . ' intéressé·e·s</strong>' . (count($names) ? ' : ' . esc_html(implode(', ', $names)) : '') . '</div>';

        echo '<div class="row-actions" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">';
        echo '<form method="post" style="display:inline">' . wp_nonce_field('lfi_prop_interest', '_wpnonce', true, false)
           . '<input type="hidden" name="lfi_prop_interest" value="' . (int) $r->id . '">'
           . '<button type="submit" class="btn-primary" style="background:' . ($i_am ? '#888' : '#186a3b') . '">' . ($i_am ? '✓ Ça m\'intéresse (retirer)' : '👍 Ça m\'intéresse') . '</button></form>';
        if ((int) $r->user_id === $uid || lfi_nct_coord_can_moderate()) {
            echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Supprimer cette proposition ?\');">' . wp_nonce_field('lfi_prop_del', '_wpnonce', true, false)
               . '<input type="hidden" name="lfi_prop_del" value="' . (int) $r->id . '">'
               . '<button type="submit" class="btn-ghost" style="padding:6px 10px;font-size:.82em;color:#c8102e">🗑 Supprimer</button></form>';
        }
        echo '</div>';

        /* Modération : changer le statut. */
        if (lfi_nct_coord_can_moderate()) {
            echo '<form method="post" style="margin-top:6px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">' . wp_nonce_field('lfi_prop_statut', '_wpnonce', true, false)
               . '<input type="hidden" name="lfi_prop_statut" value="' . (int) $r->id . '">'
               . '<span style="font-size:.82em;color:#666">Statut :</span><select name="statut" style="padding:5px;border:1px solid #ccc;border-radius:6px;font-size:.85em">';
            foreach ($statut_meta as $sk => $smv) echo '<option value="' . esc_attr($sk) . '"' . selected($r->statut, $sk, false) . '>' . esc_html($smv[1]) . '</option>';
            echo '</select><button type="submit" class="btn-ghost" style="padding:5px 10px;font-size:.82em">Mettre à jour</button></form>';
        }
        echo '</li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}
