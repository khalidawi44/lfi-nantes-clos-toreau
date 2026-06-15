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
        $batch = (int) $_POST['lfi_nct_geocode_batch'] ?: 10;
        $batch = max(1, min(100, $batch));
        @set_time_limit(max(60, $batch * 2));
        $res = lfi_nct_geocode_pending($batch);
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
            <strong><?php echo count($markers); ?></strong> enquête(s) géolocalisée(s) sur la carte.
            <?php if ($pending > 0): ?>
                <strong style="color:#bd8600;margin-left:1em">⚠️ <?php echo $pending; ?> enquête(s) sans coordonnées</strong>
                —
                <form method="post" style="display:inline">
                    <?php wp_nonce_field('lfi_nct_geocode'); ?>
                    <input type="hidden" name="lfi_nct_geocode_batch" value="10">
                    <button type="submit" name="lfi_nct_geocode" value="1" class="button">🌍 Géocoder 10</button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Va géocoder jusqu\'à 30 adresses, ~30 secondes. OK ?');">
                    <?php wp_nonce_field('lfi_nct_geocode'); ?>
                    <input type="hidden" name="lfi_nct_geocode_batch" value="30">
                    <button type="submit" name="lfi_nct_geocode" value="1" class="button">🌍 Géocoder 30</button>
                </form>
                <span class="description">(Nominatim/OpenStreetMap, ~1 sec par adresse)</span>
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
        .maplibregl-popup-content { font-size: 13px; line-height: 1.5; min-width: 220px; padding: 12px 14px; }
        .maplibregl-popup-content h3 { margin: 0 0 6px; font-size: 1.05em; color: #c8102e; }
        .maplibregl-popup-content .gravbadge { display:inline-block; padding:1px 8px; border-radius:10px; color:#fff; font-weight:600; font-size:.85em; }
        .maplibregl-popup-content .lfi-pop-types { margin: .3em 0; padding-left: 1.2em; }
        .lfi-3d-marker { pointer-events: auto; }
        </style>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">
    <script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
    <script src="https://unpkg.com/osmtogeojson@3.0.0-beta.5/osmtogeojson.js"></script>
    <script>
    (function () {
        var markers = <?php echo wp_json_encode($markers); ?>;
        var el = document.getElementById('lfi-map');
        if (!el || typeof maplibregl === 'undefined') return;

        var FLOOR_PX = 28;
        var center = [<?php echo (float) LFI_NCT_MAP_CENTER_LNG; ?>, <?php echo (float) LFI_NCT_MAP_CENTER_LAT; ?>];

        function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
        }); }
        function parseFloor(s) {
            if (!s) return 0;
            var m = String(s).match(/(\d+)/);
            return m ? parseInt(m[1], 10) : 0;
        }
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

        // === Carte MapLibre GL (vraie 3D : pitch + extrusion des immeubles) ===
        var map = new maplibregl.Map({
            container: 'lfi-map',
            style: {
                version: 8,
                sources: {
                    osm: {
                        type: 'raster',
                        tiles: [
                            'https://a.tile.openstreetmap.org/{z}/{x}/{y}.png',
                            'https://b.tile.openstreetmap.org/{z}/{x}/{y}.png',
                            'https://c.tile.openstreetmap.org/{z}/{x}/{y}.png'
                        ],
                        tileSize: 256,
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    }
                },
                layers: [{ id: 'osm', type: 'raster', source: 'osm' }]
            },
            center: center,
            zoom: <?php echo (int) LFI_NCT_MAP_CENTER_ZOOM; ?>,
            pitch: 55,
            bearing: -15,
            maxPitch: 75,
            antialias: true,
        });
        map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }));
        map.addControl(new maplibregl.ScaleControl({ maxWidth: 120 }));

        // === Markers étagés (un par étage, empilés au-dessus de leur immeuble) ===
        var bounds = new maplibregl.LngLatBounds();
        markers.forEach(function (m) {
            var floor = parseFloor(m.etage);
            var offY  = floor * FLOOR_PX;
            var label = floor > 0 ? floor : '?';

            var wrap = document.createElement('div');
            wrap.className = 'lfi-3d-marker';
            wrap.style.position = 'relative';
            wrap.style.width  = '24px';
            wrap.style.height = '24px';
            wrap.innerHTML =
                '<div class="lfi-3d-line" style="position:absolute;left:11px;top:-' + offY + 'px;width:2px;height:' + offY + 'px;background:' + m.gcolor + ';opacity:.6"></div>' +
                '<div class="lfi-3d-dot" style="position:absolute;left:0;top:-' + offY + 'px;width:24px;height:24px;border-radius:50%;background:' + m.gcolor + ';border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;line-height:1">' +
                  esc(label) +
                '</div>';

            new maplibregl.Marker({ element: wrap, anchor: 'center' })
                .setLngLat([m.lng, m.lat])
                .setPopup(new maplibregl.Popup({ offset: [0, -offY - 12], closeButton: true }).setHTML(popupHtml(m)))
                .addTo(map);
            bounds.extend([m.lng, m.lat]);
        });
        if (!bounds.isEmpty()) {
            map.fitBounds(bounds, { padding: 100, maxZoom: 18, pitch: 55, bearing: -15 });
        }

        // === Extrusion 3D des immeubles via Overpass / OSM ===
        function loadBuildings() {
            var b = map.getBounds();
            var s = b.getSouth(), w = b.getWest(), n = b.getNorth(), e = b.getEast();
            var pad = 0.001;
            s -= pad; w -= pad; n += pad; e += pad;
            var key = 'lfi_bldg_' + [s.toFixed(4), w.toFixed(4), n.toFixed(4), e.toFixed(4)].join('_');
            try {
                var cached = localStorage.getItem(key);
                if (cached) { addBuildingLayer(JSON.parse(cached)); return; }
            } catch (e2) {}
            var q = '[out:json][timeout:25];(way["building"](' + s + ',' + w + ',' + n + ',' + e + ');relation["building"](' + s + ',' + w + ',' + n + ',' + e + '););out body;>;out skel qt;';
            fetch('https://overpass-api.de/api/interpreter?data=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (typeof osmtogeojson === 'undefined') return;
                    var gj = osmtogeojson(data);
                    gj.features = (gj.features || []).filter(function (f) {
                        return f.geometry && (f.geometry.type === 'Polygon' || f.geometry.type === 'MultiPolygon');
                    });
                    gj.features.forEach(function (f) {
                        var p = f.properties || {};
                        var lvls = parseFloat(p['building:levels']);
                        var h = parseFloat(p.height);
                        if (!isFinite(h)) h = isFinite(lvls) ? lvls * 3 : 9;
                        f.properties._h = Math.max(3, h);
                    });
                    try { localStorage.setItem(key, JSON.stringify(gj)); } catch (e2) {}
                    addBuildingLayer(gj);
                })
                .catch(function (err) { console.warn('Overpass buildings fetch failed:', err); });
        }
        function addBuildingLayer(gj) {
            if (!map.isStyleLoaded() || map.getSource('buildings')) return;
            map.addSource('buildings', { type: 'geojson', data: gj });
            map.addLayer({
                id: 'buildings-3d',
                type: 'fill-extrusion',
                source: 'buildings',
                paint: {
                    'fill-extrusion-color': '#9aa0a8',
                    'fill-extrusion-height': ['get', '_h'],
                    'fill-extrusion-base': 0,
                    'fill-extrusion-opacity': 0.78,
                }
            });
        }
        map.on('load', loadBuildings);
    })();
    </script>

    <?php if ($pending > 0): ?>
        <h2 style="margin-top:2em">Enquêtes non placées sur la carte (sans coordonnées)</h2>
        <p class="description">Adresses pour lesquelles le géocodage n'a pas encore été fait — un clic sur « Géocoder » plus haut les ajoutera à la carte.</p>
        <?php
        $missing = $wpdb->get_results(
            "SELECT id, adresse, etage, submitted_at FROM $table
             WHERE deleted_at IS NULL
                   AND (lat IS NULL OR lng IS NULL)
                   AND adresse IS NOT NULL AND adresse != ''
             ORDER BY id DESC LIMIT 500"
        );
        ?>
        <table class="wp-list-table widefat striped" style="max-width:900px">
            <thead><tr><th>N°</th><th>Reçu le</th><th>Adresse</th><th>Étage</th></tr></thead>
            <tbody>
                <?php foreach ($missing as $i => $mrow): ?>
                    <tr>
                        <td>#<?php echo (int) $mrow->id; ?></td>
                        <td><?php echo esc_html($mrow->submitted_at); ?></td>
                        <td><?php echo esc_html($mrow->adresse); ?></td>
                        <td><?php echo esc_html($mrow->etage); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php
}
