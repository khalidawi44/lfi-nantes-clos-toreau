<?php
/**
 * GÉO-ROUTAGE — Phase 1 : router une enquête vers le bon GA selon l'adresse.
 *
 * Quand quelqu'un remplit l'enquête depuis le site et saisit son adresse :
 *   1. on géocode l'adresse (→ lat/lng) ;
 *   2. on cherche le GA dont le PÉRIMÈTRE (centre + rayon en km) couvre ce point
 *      → l'enquête est rattachée à ce GA ;
 *   3. si la personne a demandé à être recontactée, on la met dans la liste
 *      « à contacter » de ce GA et on notifie discrètement l'admin.
 *
 * Le rayon est réglable par GA (serré pour un quartier HLM dense comme Clos
 * Toreau, large pour un GA rural). Aucune donnée inventée ; si l'adresse n'est
 * pas géocodable, on ne force rien.
 *
 * Phase 2 (à venir) : profil de terrain + modules par GA + suggestions.
 * Phase 3 (à venir) : créer un GA quand aucune zone ne couvre l'adresse.
 */
if (!defined('ABSPATH')) exit;

/* ---- Rayon par GA (km) --------------------------------------- */
function lfi_nct_geo_radii() {
    $r = get_option('lfi_nct_ga_radii', []);
    return is_array($r) ? $r : [];
}
function lfi_nct_geo_radius($slug) {
    $slug = ($slug === '' ? 'clos-toreau' : (string) $slug);
    $r = lfi_nct_geo_radii();
    if (isset($r[$slug])) return (float) $r[$slug];
    /* Défaut : quartier HLM dense = serré ; autres = plus large. */
    return ($slug === 'clos-toreau') ? 1.2 : 3.0;
}
function lfi_nct_geo_radius_set($slug, $km) {
    $slug = ($slug === '' ? 'clos-toreau' : sanitize_title($slug));
    $km = max(0.2, min(60, (float) $km));
    $r = lfi_nct_geo_radii();
    $r[$slug] = $km;
    update_option('lfi_nct_ga_radii', $r, false);
}

/* ---- Distance de Haversine (km) ------------------------------ */
function lfi_nct_geo_haversine($la1, $lo1, $la2, $lo2) {
    $R = 6371.0;
    $dLa = deg2rad($la2 - $la1);
    $dLo = deg2rad($lo2 - $lo1);
    $a = sin($dLa / 2) ** 2 + cos(deg2rad($la1)) * cos(deg2rad($la2)) * sin($dLo / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/* ---- Tous les GA avec leur centre ---------------------------- */
function lfi_nct_geo_all_centres() {
    $out = [];
    /* GA « maison » Clos Toreau. */
    $home = lfi_nct_ga_geo('clos-toreau');
    if (!empty($home['centre'])) $out[] = ['slug' => 'clos-toreau', 'nom' => lfi_nct_ga_nom('clos-toreau'), 'centre' => $home['centre']];
    /* Les autres GA. */
    if (function_exists('lfi_nct_groupes')) {
        foreach (lfi_nct_groupes(true) as $g) {
            $slug = (string) ($g['slug'] ?? '');
            if ($slug === '' || $slug === 'clos-toreau') continue;
            $geo = lfi_nct_ga_geo($slug);
            if (!empty($geo['centre'])) $out[] = ['slug' => $slug, 'nom' => ($g['nom'] ?? $slug), 'centre' => $geo['centre']];
        }
    }
    return $out;
}

/**
 * Rattache des coordonnées au meilleur GA dont le rayon les couvre.
 * Renvoie ['slug'=>..., 'nom'=>..., 'dist'=>km] ou null si aucun ne couvre.
 */
function lfi_nct_geo_match_coords($lat, $lng) {
    $best = null;
    foreach (lfi_nct_geo_all_centres() as $g) {
        $d = lfi_nct_geo_haversine((float) $lat, (float) $lng, (float) $g['centre'][0], (float) $g['centre'][1]);
        if ($d <= lfi_nct_geo_radius($g['slug'])) {
            if ($best === null || $d < $best['dist']) $best = ['slug' => $g['slug'], 'nom' => $g['nom'], 'dist' => $d];
        }
    }
    return $best;
}

/* ============================================================== *
 *  HOOK : une enquête vient d'être enregistrée → on la route      *
 * ============================================================== */
add_action('lfi_nct_submission_created', 'lfi_nct_geo_route_submission', 10, 2);
function lfi_nct_geo_route_submission($sub_id, $data = []) {
    global $wpdb;
    $sub_id = (int) $sub_id;
    if (!$sub_id) return;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $sub_id));
    if (!$row) return;

    /* Coordonnées : déjà là, sinon on géocode l'adresse maintenant. */
    $lat = $row->lat; $lng = $row->lng;
    if (($lat === null || $lng === null) && trim((string) $row->adresse) !== '' && function_exists('lfi_nct_geocode')) {
        $coords = lfi_nct_geocode($row->adresse);
        if ($coords) {
            $lat = $coords[0]; $lng = $coords[1];
            $wpdb->update($table, ['lat' => $lat, 'lng' => $lng], ['id' => $sub_id]);
        }
    }
    if ($lat === null || $lng === null) return; /* non géocodable → on ne force rien */

    /* Rattachement au bon GA (si une zone couvre l'adresse). */
    $match = lfi_nct_geo_match_coords($lat, $lng);
    if ($match) {
        $wpdb->update($table, ['ga' => $match['slug']], ['id' => $sub_id]);
    }

    /* Demande de contact → file « à contacter » + notification admin. */
    if ((int) $row->contact_recontact === 1) {
        lfi_nct_geo_queue_contact([
            'sub_id'  => $sub_id,
            'ga'      => $match ? $match['slug'] : '',
            'nom'     => trim((string) $row->contact_prenom . ' ' . (string) $row->contact_nom),
            'tel'     => (string) $row->contact_tel,
            'email'   => (string) $row->contact_email,
            'adresse' => (string) $row->adresse,
            'date'    => current_time('mysql'),
            'couvert' => $match ? 1 : 0,
        ]);
    }
}

/* ---- File des inscriptions « à contacter » ------------------- */
function lfi_nct_geo_queue_contact($item) {
    $q = get_option('lfi_nct_geo_contacts', []);
    if (!is_array($q)) $q = [];
    /* Dé-doublonnage par sub_id. */
    foreach ($q as $e) if ((int) ($e['sub_id'] ?? 0) === (int) $item['sub_id']) return;
    $q[] = $item;
    if (count($q) > 300) $q = array_slice($q, -300);
    update_option('lfi_nct_geo_contacts', $q, false);
}
function lfi_nct_geo_contacts_pending() {
    $q = get_option('lfi_nct_geo_contacts', []);
    if (!is_array($q)) return [];
    return array_values(array_filter($q, function ($e) { return empty($e['done']); }));
}
function lfi_nct_geo_contact_done($sub_id) {
    $q = get_option('lfi_nct_geo_contacts', []);
    if (!is_array($q)) return;
    foreach ($q as $i => $e) if ((int) ($e['sub_id'] ?? 0) === (int) $sub_id) $q[$i]['done'] = 1;
    update_option('lfi_nct_geo_contacts', $q, false);
}

/**
 * Bandeau discret sur le tableau de bord : nouvelles inscriptions à contacter.
 */
function lfi_nct_geo_admin_notice() {
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can) return;
    $n = count(lfi_nct_geo_contacts_pending());
    if ($n < 1) return;
    echo '<a href="' . esc_url(lfi_nct_app_url('geo-contacts')) . '" style="display:flex;align-items:center;gap:8px;margin:0 0 12px;padding:9px 13px;background:#fff3cd;border:1px solid #d39e00;border-radius:10px;text-decoration:none;color:#8a6d1f;font-weight:800">'
       . '<span style="font-size:1.1em">🔔</span><span>' . (int) $n . ' inscription' . ($n > 1 ? 's' : '') . ' à contacter (via le site)</span>'
       . '<span style="margin-left:auto;font-size:.85em;opacity:.8">Voir →</span></a>';
}

/* ============================================================== *
 *  VUE ADMIN : inscriptions à contacter                          *
 * ============================================================== */
function lfi_nct_app_view_geo_contacts() {
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    if (!empty($_POST['lfi_geo_done']) && check_admin_referer('lfi_geo_done')) {
        lfi_nct_geo_contact_done((int) $_POST['lfi_geo_done']);
        wp_safe_redirect(lfi_nct_app_url('geo-contacts', ['ok' => 1]));
        exit;
    }

    lfi_nct_app_screen_open('🔔 Inscriptions à contacter', 'Enquêtes du site où la personne veut être recontactée');
    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Marqué comme traité.');

    $pending = lfi_nct_geo_contacts_pending();
    if (empty($pending)) {
        echo '<div class="lfi-app-help">Aucune inscription en attente. Quand quelqu\'un remplit l\'enquête sur le site et coche « je veux être recontacté·e », il apparaît ici, déjà rattaché à son GA.</div>';
        lfi_nct_app_screen_close();
        return;
    }
    echo '<ul class="lfi-app-list">';
    foreach (array_reverse($pending) as $e) {
        $ga_nom = ($e['ga'] ?? '') !== '' ? lfi_nct_ga_nom($e['ga']) : 'Aucun GA sur cette zone';
        $accent = !empty($e['couvert']) ? '#186a3b' : '#d39e00';
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . esc_attr($accent) . '">';
        echo '<div class="head"><div class="who">📍 ' . esc_html($e['adresse'] ?: 'Adresse non précisée') . '</div>';
        echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html(wp_date('j M', strtotime($e['date'] ?? ''))) . '</div></div>';
        echo '<div class="meta"><span class="meta-chip">🏳️ ' . esc_html($ga_nom) . '</span>';
        if (!empty($e['couvert'])) echo '<span class="meta-chip">✅ dans un périmètre</span>';
        else echo '<span class="meta-chip">⚠️ hors périmètre — créer un GA ?</span>';
        echo '</div>';
        if (trim((string) ($e['nom'] ?? '')) !== '') echo '<div class="com"><strong>' . esc_html($e['nom']) . '</strong></div>';
        $coords = [];
        if (!empty($e['tel']))   $coords[] = '📞 <a href="tel:' . esc_attr($e['tel']) . '">' . esc_html($e['tel']) . '</a>';
        if (!empty($e['email'])) $coords[] = '✉️ <a href="mailto:' . esc_attr($e['email']) . '">' . esc_html($e['email']) . '</a>';
        if ($coords) echo '<div class="com" style="font-size:.9em">' . implode(' · ', $coords) . '</div>';

        /* Ouvrir un dossier juridique pré-rempli. */
        $new = lfi_nct_app_url('dossier-juridique-add', array_filter([
            'tenant_prenom'  => $e['contact_prenom'] ?? '',
            'tenant_nom'     => $e['nom'] ?? '',
            'tenant_adresse' => $e['adresse'] ?? '',
            'tenant_tel'     => $e['tel'] ?? '',
            'tenant_email'   => $e['email'] ?? '',
        ]));
        echo '<div class="row-actions" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">';
        echo '<a class="btn-primary" href="' . esc_url($new) . '">📁 Ouvrir un dossier</a>';
        echo '<form method="post" style="display:inline">' . wp_nonce_field('lfi_geo_done', '_wpnonce', true, false)
           . '<input type="hidden" name="lfi_geo_done" value="' . (int) $e['sub_id'] . '">'
           . '<button type="submit" class="btn-ghost" style="padding:6px 10px;font-size:.82em">✓ Traité</button></form>';
        echo '</div></li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE ADMIN : périmètres des GA (rayon en km)                   *
 * ============================================================== */
function lfi_nct_app_view_geo_perimetres() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    if (!empty($_POST['lfi_geo_radius_set']) && check_admin_referer('lfi_geo_radius_set')) {
        $slug = sanitize_title($_POST['slug'] ?? '');
        lfi_nct_geo_radius_set($slug, $_POST['km'] ?? 0);
        wp_safe_redirect(lfi_nct_app_url('geo-perimetres', ['ok' => 1]));
        exit;
    }

    lfi_nct_app_screen_open('🎯 Périmètres des GA', 'Le rayon d\'action de chaque groupe (pour router les enquêtes)');
    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Rayon enregistré.');

    echo '<div class="lfi-app-help">Une enquête saisie sur le site est rattachée au GA dont le <strong>rayon</strong> couvre l\'adresse. Serré pour un quartier HLM dense (Clos Toreau), large pour un GA rural.</div>';

    echo '<ul class="lfi-app-list">';
    foreach (lfi_nct_geo_all_centres() as $g) {
        $km = lfi_nct_geo_radius($g['slug']);
        echo '<li class="lfi-app-card" style="border-left:4px solid #0066a3">';
        echo '<div class="head"><div class="who">🏳️ ' . esc_html($g['nom']) . '</div></div>';
        echo '<form method="post" style="display:flex;gap:8px;align-items:center;margin-top:6px">';
        echo wp_nonce_field('lfi_geo_radius_set', '_wpnonce', true, false);
        echo '<input type="hidden" name="slug" value="' . esc_attr($g['slug']) . '">';
        echo '<label style="font-size:.9em">Rayon <input type="number" name="km" value="' . esc_attr($km) . '" min="0.2" max="60" step="0.1" style="width:80px;padding:7px;border:1px solid #ccc;border-radius:8px"> km</label>';
        echo '<button type="submit" class="btn-ghost" style="padding:6px 12px;font-size:.85em">Enregistrer</button>';
        echo '</form></li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}
