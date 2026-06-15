<?php
/**
 * Carte interactive des adresses enquêtées (Leaflet + OpenStreetMap).
 * Markers colorés selon la gravité, popup au clic.
 * Géocodage via Nominatim (OpenStreetMap), gratuit, sans clé API.
 */
if (!defined('ABSPATH')) exit;

if (!defined('LFI_NCT_MAP_CENTER_LAT')) define('LFI_NCT_MAP_CENTER_LAT', 47.1933);
if (!defined('LFI_NCT_MAP_CENTER_LNG')) define('LFI_NCT_MAP_CENTER_LNG', -1.5380);
if (!defined('LFI_NCT_MAP_CENTER_ZOOM')) define('LFI_NCT_MAP_CENTER_ZOOM', 16);

/* ------------------------------------------------------------------ */
/* Géocodage                                                           */
/* ------------------------------------------------------------------ */

/**
 * Géocode une adresse libre via Nominatim. Renvoie [lat, lng] ou null.
 * Respect des CGU Nominatim : User-Agent identifié + 1 req/sec max côté appelant.
 */
function lfi_nct_geocode($address) {
    $address = trim((string) $address);
    if ($address === '') return null;

    // On contextualise l'adresse pour améliorer la précision sur Nantes Sud.
    $query = $address;
    if (stripos($query, 'nantes') === false) $query .= ', Nantes';
    if (stripos($query, 'france') === false) $query .= ', France';

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q'            => $query,
        'format'       => 'json',
        'limit'        => 1,
        'countrycodes' => 'fr',
    ]);

    $resp = wp_remote_get($url, [
        'timeout' => 12,
        'headers' => [
            'User-Agent' => 'LFI-Nantes-Clos-Toreau-Survey/1.0 (https://lfi-nantes-clostoreau.fr)',
            'Accept'     => 'application/json',
        ],
    ]);
    if (is_wp_error($resp)) return null;

    $body = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($body) || empty($body)) return null;
    $first = $body[0];
    if (!isset($first['lat'], $first['lon'])) return null;

    return [(float) $first['lat'], (float) $first['lon']];
}

/**
 * Géocode jusqu'à $limit réponses sans coordonnées (corbeille exclue).
 * Renvoie ['traitees' => int, 'geocodees' => int, 'restantes' => int].
 */
function lfi_nct_geocode_pending($limit = 10) {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, adresse FROM $table
         WHERE lat IS NULL AND adresse IS NOT NULL AND adresse != ''
               AND deleted_at IS NULL
         ORDER BY id ASC LIMIT %d", $limit
    ));

    $done = 0;
    foreach ($rows as $r) {
        $coords = lfi_nct_geocode($r->adresse);
        if ($coords) {
            $wpdb->update($table, ['lat' => $coords[0], 'lng' => $coords[1]], ['id' => $r->id]);
            $done++;
        }
        // Respect des CGU Nominatim : 1 req/sec.
        sleep(1);
    }

    $remaining = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $table
         WHERE lat IS NULL AND adresse IS NOT NULL AND adresse != ''
               AND deleted_at IS NULL"
    );

    return ['traitees' => count($rows), 'geocodees' => $done, 'restantes' => $remaining];
}

/* ------------------------------------------------------------------ */
/* Page admin : carte                                                  */
/* ------------------------------------------------------------------ */

add_action('admin_menu', 'lfi_nct_map_admin_menu', 35);
function lfi_nct_map_admin_menu() {
    add_submenu_page(
        'lfi-nct-responses',
        'LFI Clos Toreau — Carte',
        '🗺 Carte',
        'manage_options',
        'lfi-nct-map',
        'lfi_nct_map_page'
    );
}

function lfi_nct_map_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_responses';

    $notice = '';
    if (!empty($_POST['lfi_nct_geocode'])) {
        check_admin_referer('lfi_nct_geocode');
        $res = lfi_nct_geocode_pending(10);
        $notice = sprintf(
            '%d adresse(s) traitée(s), %d géocodée(s) avec succès. %d restant(e)s.',
            $res['traitees'], $res['geocodees'], $res['restantes']
        );
    }

    $rows = $wpdb->get_results(
        "SELECT id, adresse, etage, data, lat, lng, submitted_at
         FROM $table
         WHERE deleted_at IS NULL AND lat IS NOT NULL AND lng IS NOT NULL
         ORDER BY submitted_at DESC"
    );

    $pending = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $table
         WHERE lat IS NULL AND adresse IS NOT NULL AND adresse != ''
               AND deleted_at IS NULL"
    );

    $type_labels = [
        'degats_eaux' => 'Dégâts des eaux', 'humidite' => 'Humidité', 'insectes' => 'Nuisibles',
        'chauffage' => 'Chauffage', 'electricite' => 'Électricité', 'ascenseur' => 'Ascenseur',
        'parties_communes' => 'Parties communes', 'bruit' => 'Bruit', 'securite' => 'Insécurité',
        'autre' => 'Autre',
    ];
    $rec_labels = ['permanent' => 'En permanence', 'parfois' => 'Régulièrement', 'ponctuel' => 'Ponctuel'];

    // Préparation des markers pour le JS
    $markers = [];
    foreach ($rows as $r) {
        $data = json_decode((string) $r->data, true);
        if (!is_array($data)) $data = [];
        $score = lfi_nct_gravity_score($data);
        list($gkey, $glabel, $gcolor) = lfi_nct_gravity_level($score);

        $types_raw = (array) ($data['problemes_types'] ?? []);
        $types = array_map(function ($t) use ($type_labels) { return $type_labels[$t] ?? $t; }, $types_raw);

        $markers[] = [
            'id'       => (int) $r->id,
            'lat'      => (float) $r->lat,
            'lng'      => (float) $r->lng,
            'adresse'  => (string) $r->adresse,
            'etage'    => (string) $r->etage,
            'appt'     => (string) ($data['appartement'] ?? ''),
            'date'     => (string) $r->submitted_at,
            'score'    => $score,
            'gkey'     => $gkey,
            'glabel'   => $glabel,
            'gcolor'   => $gcolor,
            'types'    => $types,
            'recurrent' => $rec_labels[$data['problemes_recurrent'] ?? ''] ?? '',
            'presence' => $data['problemes_presence'] ?? '',
            'detail_url' => admin_url('admin.php?page=lfi-nct-stats&view=' . (int) $r->id),
        ];
    }
    ?>
    <div class="wrap">
        <h1>🗺 Carte des enquêtes <?php echo lfi_nct_print_button('Imprimer la carte'); ?></h1>

        <?php if ($notice): ?>
            <div class="notice notice-info is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>

        <p>
            <strong><?php echo count($markers); ?></strong> adresse(s) géolocalisée(s) sur la carte.
            <?php if ($pending > 0): ?>
                <strong style="color:#bd8600;margin-left:1em">⚠️ <?php echo $pending; ?> adresse(s) sans coordonnées</strong>
                — <form method="post" style="display:inline">
                    <?php wp_nonce_field('lfi_nct_geocode'); ?>
                    <button type="submit" name="lfi_nct_geocode" value="1" class="button">🌍 Géocoder 10 adresses</button>
                </form>
                <span class="description">(géocodage Nominatim/OpenStreetMap, ~1 sec par adresse)</span>
            <?php endif; ?>
        </p>

        <p class="lfi-map-legend">Légende&nbsp;:
            <span class="lfi-leg lfi-leg-leger">🟢 Sans souci</span>
            <span class="lfi-leg lfi-leg-preoccupant">🟡 Préoccupant</span>
            <span class="lfi-leg lfi-leg-grave">🔴 Grave</span>
            <span class="lfi-leg lfi-leg-critique">🚨 Critique</span>
        </p>

        <div id="lfi-map" style="width:100%;height:600px;border:1px solid #ccc;border-radius:6px;background:#f5f5f5"></div>

        <style>
        .lfi-map-legend { margin: 1em 0; font-size: 0.95em; }
        .lfi-leg { display: inline-block; padding: 2px 10px; border-radius: 12px; color: #fff; margin-right: 6px; font-weight: 600; font-size: 0.9em; }
        .lfi-leg-leger { background:#1a7f37 }
        .lfi-leg-preoccupant { background:#bd8600 }
        .lfi-leg-grave { background:#c8102e }
        .lfi-leg-critique { background:#7a0000 }
        .lfi-pop-types li { margin: 0; padding: 0; }
        .lfi-pop-types { margin: .3em 0; padding-left: 1.2em; }
        .leaflet-popup-content { font-size: 13px; line-height: 1.5; min-width: 200px; }
        .leaflet-popup-content h3 { margin: 0 0 6px; font-size: 1.05em; color: #c8102e; }
        .leaflet-popup-content .gravbadge { display:inline-block; padding:1px 8px; border-radius:10px; color:#fff; font-weight:600; font-size:.85em; }
        </style>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
    (function () {
        var markers = <?php echo wp_json_encode($markers); ?>;
        var el = document.getElementById('lfi-map');
        if (!el || typeof L === 'undefined') return;

        var center = [<?php echo (float) LFI_NCT_MAP_CENTER_LAT; ?>, <?php echo (float) LFI_NCT_MAP_CENTER_LNG; ?>];
        var map = L.map(el).setView(center, <?php echo (int) LFI_NCT_MAP_CENTER_ZOOM; ?>);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(map);

        function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
        }); }

        function popupHtml(m) {
            var unite = 'Étage ' + esc(m.etage) + (m.appt ? ' · Appt ' + esc(m.appt) : '');
            var html = '<h3>' + esc(m.adresse) + '</h3>'
                     + '<div>' + unite + '</div>'
                     + '<div style="margin:.4em 0"><span class="gravbadge" style="background:' + esc(m.gcolor) + '">'
                       + esc(m.glabel) + (m.score ? ' (' + m.score + '/10)' : '') + '</span></div>';
            if (m.presence === 'non') {
                html += '<div>✅ Aucun problème déclaré.</div>';
            } else if (m.types && m.types.length) {
                html += '<div><strong>Problèmes :</strong><ul class="lfi-pop-types">';
                m.types.forEach(function(t){ html += '<li>' + esc(t) + '</li>'; });
                html += '</ul></div>';
                if (m.recurrent) html += '<div><strong>Récurrence :</strong> ' + esc(m.recurrent) + '</div>';
            }
            html += '<div style="margin-top:.5em;font-size:.85em;color:#666">' + esc(m.date) + '</div>'
                  + '<div style="margin-top:.3em"><a href="' + esc(m.detail_url) + '">Voir le détail complet →</a></div>';
            return html;
        }

        var bounds = [];
        markers.forEach(function (m) {
            var marker = L.circleMarker([m.lat, m.lng], {
                radius: 9,
                color: '#fff',
                weight: 2,
                fillColor: m.gcolor,
                fillOpacity: 0.9,
            }).addTo(map);
            marker.bindPopup(popupHtml(m));
            bounds.push([m.lat, m.lng]);
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 17 });
        }
    })();
    </script>
    <?php
}
