<?php
/**
 * CARTE COLLABORATIVE DES RATS (habitant·es ⇄ GA).
 *
 *  Objectif : transformer « il y a plein de rats dans le quartier » en une
 *  PREUVE chiffrée, datée et cartographiée à mettre sous le nez du bailleur
 *  (NMH) et du Service Communal d'Hygiène et de Santé (SCHS).
 *
 *  - N'IMPORTE QUEL habitant·e signale un rat / un terrier en 2 clics :
 *    sa position (géoloc du téléphone) OU une adresse + une date + (option)
 *    une photo. Tout est HORODATÉ.
 *  - Une CARTE des points chauds (Leaflet/OpenStreetMap) agrège les
 *    signalements : plus il y a de signalements à un endroit, plus la
 *    pastille est grosse et rouge.
 *  - Les admins du GA disposent d'un EXPORT CSV « preuve pour le bailleur /
 *    SCHS » (dates, lieux, nombres) + d'un compteur.
 *
 *  Cloisonnement : chaque signalement porte le slug du GA de la personne ;
 *  la carte n'affiche que les signalements du GA de la personne connectée
 *  (le super-admin voit tout / son GA regardé).
 */
if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------- *
 *  Stockage (option globale, cloisonnée par GA).                 *
 * -------------------------------------------------------------- */
function lfi_nct_rat_signals_get() {
    $v = get_option('lfi_nct_rat_signals', []);
    return is_array($v) ? $v : [];
}
function lfi_nct_rat_signals_save($list) {
    update_option('lfi_nct_rat_signals', array_slice(array_values($list), -2000), false);
}

/** Types de signalement (emoji, libellé court, famille : 'rat' ou 'eau').
 *  La famille 'eau' regroupe les travaux / fuites / points d'eau, pour voir sur
 *  la MÊME carte que les rats suivent les fuites (corrélation = preuve). */
function lfi_nct_rat_types() {
    return [
        'rat'      => ['🐀', 'Rat vu (vivant)',        'rat'],
        'nid'      => ['🪹', 'Nid de rats',            'rat'],
        'terrier'  => ['🕳️', 'Terrier / trou',         'rat'],
        'crottes'  => ['💩', 'Crottes / traces',       'rat'],
        'frequent' => ['🔁', 'Présence fréquente',     'rat'],
        'mort'     => ['☠️', 'Rat mort',               'rat'],
        'travaux'  => ['🚧', 'Travaux / tranchée',     'eau'],
        'eau'      => ['💧', 'Fuite / point d\'eau',    'eau'],
    ];
}

/** Ajoute un signalement horodaté. Renvoie l'id créé (0 si échec). */
function lfi_nct_rat_signal_add($uid, $args) {
    $uid = (int) $uid; if (!$uid) return 0;
    $u = get_userdata($uid);
    $types = lfi_nct_rat_types();
    $type = (string) ($args['type'] ?? 'rat');
    if (!isset($types[$type])) $type = 'rat';
    $lat = isset($args['lat']) ? (float) $args['lat'] : 0;
    $lng = isset($args['lng']) ? (float) $args['lng'] : 0;
    if (!$lat || !$lng) return 0;
    $list = lfi_nct_rat_signals_get();
    $id = (int) (round(microtime(true) * 1000) % 1000000000);
    $list[] = [
        'id'      => $id,
        'uid'     => $uid,
        'name'    => $u ? ($u->display_name ?: $u->user_login) : ('#' . $uid),
        'ga'      => (string) get_user_meta($uid, 'lfi_nct_ga', true),
        'type'    => $type,
        'lat'     => $lat,
        'lng'     => $lng,
        'adresse' => (string) ($args['adresse'] ?? ''),
        'nombre'  => max(1, (int) ($args['nombre'] ?? 1)),
        'date'    => (string) ($args['date'] ?? current_time('Y-m-d')),
        'desc'    => (string) ($args['desc'] ?? ''),
        'photo'   => (int) ($args['photo'] ?? 0),
        'ts'      => current_time('mysql'),
    ];
    lfi_nct_rat_signals_save($list);
    return $id;
}

/** Slug du GA « regardé » par la personne connectée (cloisonnement). */
function lfi_nct_rat_scope_ga() {
    if (function_exists('lfi_nct_super_admin') && lfi_nct_super_admin()) {
        $vg = function_exists('lfi_nct_view_ga') ? lfi_nct_view_ga() : '';
        return ($vg && $vg !== '__all__') ? $vg : ''; /* '' = tout voir */
    }
    return function_exists('lfi_nct_user_ga') ? lfi_nct_user_ga() : '';
}

/** Signalements visibles pour la personne connectée (scope GA), + récents. */
function lfi_nct_rat_signals_scoped() {
    $ga = lfi_nct_rat_scope_ga();
    $out = [];
    foreach (lfi_nct_rat_signals_get() as $s) {
        if ($ga !== '' && (string) ($s['ga'] ?? '') !== $ga) continue;
        if (empty($s['lat']) || empty($s['lng'])) continue;
        $out[] = $s;
    }
    usort($out, function ($a, $b) { return strcmp((string) ($b['ts'] ?? ''), (string) ($a['ts'] ?? '')); });
    return $out;
}

/* -------------------------------------------------------------- *
 *  ÉCRAN : « 🐀 Carte des rats » (carte + signalement en 2 clics) *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_carte_rats() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $user = wp_get_current_user(); $uid = (int) $user->ID;
    $types = lfi_nct_rat_types();
    $can_admin = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');

    /* --- Enregistrement d'un signalement (POST auto sur cette page). --- */
    if (!empty($_POST['lfi_rat_signal']) && check_admin_referer('lfi_rat_signal')) {
        $lat = isset($_POST['lat']) ? (float) $_POST['lat'] : 0;
        $lng = isset($_POST['lng']) ? (float) $_POST['lng'] : 0;
        $adresse = sanitize_text_field(wp_unslash($_POST['adresse'] ?? ''));

        /* Pas de géoloc → on géocode l'adresse saisie (Nantes par défaut). */
        if ((!$lat || !$lng) && $adresse !== '' && function_exists('lfi_nct_evt_geocode')) {
            $q = $adresse;
            if (stripos($q, 'nantes') === false && stripos($q, 'rezé') === false && stripos($q, 'reze') === false) {
                $q .= ', Nantes';
            }
            $geo = lfi_nct_evt_geocode($q);
            if ($geo) { $lat = $geo['lat']; $lng = $geo['lng']; }
        }

        if (!$lat || !$lng) {
            wp_safe_redirect(lfi_nct_app_url('carte-rats', ['err' => 'lieu'])); exit;
        }

        /* Photo facultative (une seule). */
        $photo_id = 0;
        if (!empty($_FILES['photo']['name']) && (int) ($_FILES['photo']['error'] ?? 4) === 0) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $f = $_FILES['photo'];
            if ((int) $f['size'] <= 15 * 1024 * 1024) {
                $mime = function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : $f['type'];
                if (strpos((string) $mime, 'image/') === 0) {
                    $up = wp_handle_upload($f, ['test_form' => false]);
                    if (empty($up['error'])) {
                        $att = wp_insert_attachment(['post_mime_type' => $up['type'], 'post_title' => 'Signalement rat — ' . $user->display_name, 'post_status' => 'private', 'post_author' => $uid], $up['file']);
                        if (!is_wp_error($att) && $att) {
                            update_post_meta($att, '_lfi_rat_signal', 1);
                            wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $up['file']));
                            $photo_id = (int) $att;
                        }
                    }
                }
            }
        }

        $id = lfi_nct_rat_signal_add($uid, [
            'type'    => sanitize_key($_POST['type'] ?? 'rat'),
            'lat'     => $lat, 'lng' => $lng,
            'adresse' => $adresse,
            'nombre'  => (int) ($_POST['nombre'] ?? 1),
            'date'    => sanitize_text_field(wp_unslash($_POST['date'] ?? '')) ?: current_time('Y-m-d'),
            'desc'    => sanitize_textarea_field(wp_unslash($_POST['desc'] ?? '')),
            'photo'   => $photo_id,
        ]);
        wp_safe_redirect(lfi_nct_app_url('carte-rats', ['ok' => $id ? 1 : 0])); exit;
    }

    $signals = lfi_nct_rat_signals_scoped();

    /* Points pour la carte (avec libellé lisible, jamais de nom de famille). */
    $markers = [];
    foreach ($signals as $s) {
        $t = $types[$s['type']] ?? $types['rat'];
        $markers[] = [
            'lat'  => (float) $s['lat'],
            'lng'  => (float) $s['lng'],
            'emo'  => $t[0],
            'lab'  => $t[1],
            'fam'  => $t[2] ?? 'rat',
            'nb'   => (int) ($s['nombre'] ?? 1),
            'date' => $s['date'] ? wp_date('j M Y', strtotime($s['date'])) : '',
            'adr'  => (string) ($s['adresse'] ?? ''),
        ];
    }

    lfi_nct_app_screen_open('🐀 Carte des rats', 'Signalez, on cartographie, on prouve');
    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Merci ! Votre signalement est enregistré et daté. Il apparaît sur la carte.');
    if (isset($_GET['ok']) && !$_GET['ok']) lfi_nct_app_flash('⚠️ Signalement non enregistré. Réessayez.', 'err');
    if (!empty($_GET['err'])) lfi_nct_app_flash('⚠️ Indiquez un lieu : activez « Utiliser ma position » ou tapez une adresse.', 'err');

    echo '<div class="lfi-app-help">Un rat, un nid, un trou, un chantier ou une fuite d\'eau ? <strong>Touchez la carte à l\'endroit exact</strong> pour poser un point. Chaque signalement est <strong>daté et cartographié</strong>. On construit une preuve chiffrée pour le bailleur (NMH) et le service d\'hygiène de la Ville (SCHS) — et on voit que <strong>les rats suivent les fuites d\'eau</strong>.</div>';

    /* --- Compteurs --- */
    $nb = count($signals);
    $nb_rat = count(array_filter($signals, function ($s) use ($types) { return (($types[$s['type']][2] ?? 'rat')) === 'rat'; }));
    $nb_eau = $nb - $nb_rat;
    echo '<div style="display:flex;gap:8px;margin-bottom:10px">';
    echo '<div style="flex:1;background:#3a1f1f;color:#fff;border-radius:12px;padding:12px;text-align:center"><div style="font-size:1.7em;font-weight:900">' . (int) $nb_rat . '</div><div style="font-size:.82em;opacity:.9">🐀 rats</div></div>';
    echo '<div style="flex:1;background:#0b3d91;color:#fff;border-radius:12px;padding:12px;text-align:center"><div style="font-size:1.7em;font-weight:900">' . (int) $nb_eau . '</div><div style="font-size:.82em;opacity:.9">💧 eau / travaux</div></div>';
    echo '</div>';

    echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
    echo '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
    /* Style des épingles emoji (icône lisible, aucune image externe à charger). */
    echo '<style>.rat-emo{font-size:22px;line-height:30px;text-align:center;text-shadow:0 0 3px #fff,0 0 3px #fff,0 0 3px #fff;filter:drop-shadow(0 1px 1px rgba(0,0,0,.4))}.rat-pick{animation:ratBob .8s infinite alternate}@keyframes ratBob{to{transform:translateY(-4px)}}</style>';
    echo '<div id="rat-map" style="height:340px;border-radius:12px;overflow:hidden;margin-bottom:6px;border:1px solid #ddd;background:#eef"></div>';
    echo '<div id="rat-pick-hint" style="text-align:center;font-size:.85em;color:#0b3d91;font-weight:700;margin-bottom:10px">👆 Touchez la carte pour poser votre point (zoomez avec deux doigts).</div>';
    echo '<script>var LFI_RAT_MARKERS=' . wp_json_encode($markers) . ';</script>';

    /* --- Bouton « Signaler » (ouvre le formulaire) --- */
    echo '<button type="button" onclick="lfiRatToggle()" id="rat-open-btn" class="btn-primary big" style="background:#c8102e;width:100%;margin-bottom:10px">➕ Signaler ici</button>';

    /* --- Formulaire (lieu par la carte + type) --- */
    echo '<form method="post" enctype="multipart/form-data" class="lfi-app-form" id="rat-form" style="display:none">' . wp_nonce_field('lfi_rat_signal', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_rat_signal" value="1">';
    echo '<input type="hidden" name="lat" id="rat-lat" value=""><input type="hidden" name="lng" id="rat-lng" value="">';

    echo '<div style="background:#f6f8fb;border:1px solid #dfe6f0;border-radius:12px;padding:12px;margin-bottom:8px">';
    echo '<div style="font-weight:800;color:#0b3d91;margin-bottom:6px">📍 Où ?</div>';
    echo '<div id="rat-loc-status" style="font-size:.9em;color:#8a6d1f;margin-bottom:8px;text-align:center;font-weight:700">👆 Touchez la carte ci-dessus pour placer le point.</div>';
    echo '<button type="button" onclick="lfiRatGeo()" id="rat-geo-btn" style="width:100%;background:#0b3d91;color:#fff;border:0;border-radius:10px;padding:10px;font-weight:800;font-size:.95em">📡 Ou : utiliser ma position</button>';
    echo '<div style="text-align:center;color:#999;font-size:.85em;margin:6px 0">— ou tapez une adresse —</div>';
    echo '<input type="text" name="adresse" id="rat-adr" placeholder="Adresse / repère (ex : 12 rue de … ou « près du 8 »)" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:10px">';
    echo '</div>';

    echo '<label>Que signalez-vous ?<select name="type" id="rat-type" onchange="lfiRatTypeChange()">';
    $cur_fam = '';
    foreach ($types as $tk => $tv) {
        $fam = $tv[2] ?? 'rat';
        if ($fam !== $cur_fam) {
            if ($cur_fam !== '') echo '</optgroup>';
            echo '<optgroup label="' . ($fam === 'eau' ? '💧 Eau / travaux (les rats suivent les fuites)' : '🐀 Rats') . '">';
            $cur_fam = $fam;
        }
        echo '<option value="' . esc_attr($tk) . '" data-emo="' . esc_attr($tv[0]) . '">' . $tv[0] . ' ' . esc_html($tv[1]) . '</option>';
    }
    if ($cur_fam !== '') echo '</optgroup>';
    echo '</select></label>';
    echo '<label>Combien environ ?<select name="nombre"><option value="1">1</option><option value="2">2 à 3</option><option value="5">4 à 10</option><option value="15">Plus de 10</option></select></label>';
    echo '<label>📅 Quand ?<input type="date" name="date" value="' . esc_attr(current_time('Y-m-d')) . '" max="' . esc_attr(current_time('Y-m-d')) . '"></label>';
    echo '<label>📷 Photo (facultatif)<input type="file" name="photo" accept="image/*"></label>';
    echo '<label>✍️ Précision (facultatif)<textarea name="desc" rows="2" placeholder="Ex : sort d\'un trou au pied de la haie, tous les soirs."></textarea></label>';
    echo '<button type="submit" class="btn-primary big" style="background:#c8102e">🐀 Envoyer mon signalement</button>';
    echo '</form>';

    /* --- Export admin (preuve) --- */
    if ($can_admin) {
        echo '<div style="margin-top:14px;padding-top:12px;border-top:1px dashed #ddd">';
        echo '<a href="' . esc_url(lfi_nct_app_url('carte-rats-export')) . '" style="display:block;text-align:center;background:#186a3b;color:#fff;font-weight:800;border-radius:10px;padding:11px;text-decoration:none">📊 Exporter la preuve (CSV — dates, lieux, nombres)</a>';
        echo '<div style="font-size:.82em;color:#777;margin-top:6px;text-align:center">Pour le courrier au bailleur (NMH) et au service d\'hygiène (SCHS).</div>';
        echo '</div>';
    }

    /* --- Derniers signalements (liste, sans nom de famille) --- */
    if ($signals) {
        echo '<h3 style="margin:16px 0 8px;color:#0b3d91">🕒 Derniers signalements</h3>';
        echo '<div style="display:flex;flex-direction:column;gap:7px">';
        foreach (array_slice($signals, 0, 12) as $s) {
            $t = $types[$s['type']] ?? $types['rat'];
            $when = $s['date'] ? wp_date('j M Y', strtotime($s['date'])) : '';
            echo '<div style="background:#fff;border:1px solid #eee;border-left:4px solid #c8102e;border-radius:10px;padding:9px 11px">';
            echo '<div style="font-weight:800;color:#3a1f1f">' . $t[0] . ' ' . esc_html($t[1]) . ((int) ($s['nombre'] ?? 1) > 1 ? ' <span style="color:#c8102e">×' . (int) $s['nombre'] . '</span>' : '') . '</div>';
            if (!empty($s['adresse'])) echo '<div style="font-size:.88em;color:#0066a3">📍 ' . esc_html($s['adresse']) . '</div>';
            if (!empty($s['desc'])) echo '<div style="font-size:.86em;color:#555;margin-top:2px">' . esc_html(mb_substr($s['desc'], 0, 120)) . '</div>';
            if ($when) echo '<div style="font-size:.78em;color:#999;margin-top:2px">🗓 ' . esc_html($when) . '</div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="lfi-app-empty" style="margin-top:14px">Aucun signalement pour l\'instant. Soyez le premier — chaque signalement rend la preuve plus solide.</div>';
    }

    echo '<div class="lfi-app-help" style="margin-top:12px"><small>🔒 Vie privée : aucun nom n\'est affiché publiquement, seulement le lieu et la date. Les signalements ne sont partagés qu\'au sein de votre groupe.</small></div>';

    ?>
    <script>
    var LFI_RAT_MAP=null, LFI_RAT_PICK=null;
    function lfiRatEmoIcon(emo,cls){ return L.divIcon({html:'<div class="rat-emo '+(cls||'')+'">'+emo+'</div>',className:'',iconSize:[30,30],iconAnchor:[15,15],popupAnchor:[0,-14]}); }
    function lfiRatCurEmo(){ var s=document.getElementById('rat-type'); if(s&&s.options[s.selectedIndex]){var e=s.options[s.selectedIndex].getAttribute('data-emo');if(e)return e;} return '🐀'; }
    function lfiRatSet(lat,lng,label){
        document.getElementById('rat-lat').value=lat;
        document.getElementById('rat-lng').value=lng;
        if(LFI_RAT_MAP){
            if(!LFI_RAT_PICK){
                LFI_RAT_PICK=L.marker([lat,lng],{icon:lfiRatEmoIcon(lfiRatCurEmo(),'rat-pick'),draggable:true,zIndexOffset:1000}).addTo(LFI_RAT_MAP);
                LFI_RAT_PICK.on('dragend',function(e){var ll=e.target.getLatLng();lfiRatSet(ll.lat,ll.lng,'Point déplacé');});
            } else { LFI_RAT_PICK.setLatLng([lat,lng]); }
        }
        var ls=document.getElementById('rat-loc-status');
        if(ls){ls.style.color='#186a3b';ls.textContent='✅ '+(label||'Point placé')+' — vous pouvez envoyer. (Glissez l\'épingle pour ajuster.)';}
        var h=document.getElementById('rat-pick-hint'); if(h){h.style.color='#186a3b';h.textContent='✅ Point placé. Glissez l\'épingle pour ajuster, puis remplissez le formulaire.';}
    }
    function lfiRatTypeChange(){ if(LFI_RAT_PICK) LFI_RAT_PICK.setIcon(lfiRatEmoIcon(lfiRatCurEmo(),'rat-pick')); }
    function lfiRatToggle(){
        var f=document.getElementById('rat-form'), b=document.getElementById('rat-open-btn');
        if(!f)return; var show=f.style.display==='none';
        f.style.display=show?'block':'none'; if(b)b.style.display=show?'none':'block';
        if(show){ try{ f.scrollIntoView({behavior:'smooth',block:'start'}); }catch(e){} }
    }
    function lfiRatGeo(){
        var st=document.getElementById('rat-loc-status');
        if(!navigator.geolocation){ if(st){st.style.color='#c8102e';st.textContent='Géolocalisation indisponible — touchez la carte ou tapez une adresse.';} return; }
        if(st){st.style.color='#8a6d1f';st.textContent='📡 Localisation en cours…';}
        navigator.geolocation.getCurrentPosition(function(p){
            if(LFI_RAT_MAP) LFI_RAT_MAP.setView([p.coords.latitude,p.coords.longitude],17);
            lfiRatSet(p.coords.latitude,p.coords.longitude,'Ma position');
        }, function(){
            if(st){st.style.color='#c8102e';st.textContent='Position refusée — touchez la carte ou tapez une adresse.';}
        }, {enableHighAccuracy:true, timeout:10000, maximumAge:60000});
    }
    (function initRatMap(){
        if (typeof L === 'undefined' || !document.getElementById('rat-map')) { return setTimeout(initRatMap, 200); }
        var map = L.map('rat-map', {scrollWheelZoom:true, tap:true}).setView([47.1966, -1.5316], 15);
        LFI_RAT_MAP = map;
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'© OpenStreetMap'}).addTo(map);
        /* Toucher/cliquer la carte = poser (ou déplacer) son point + ouvrir le formulaire. */
        map.on('click', function(e){
            lfiRatSet(e.latlng.lat, e.latlng.lng, 'Point placé sur la carte');
            var f=document.getElementById('rat-form'); if(f && f.style.display==='none') lfiRatToggle();
        });
        var pts = [];
        (window.LFI_RAT_MARKERS||[]).forEach(function(m){
            if (!m.lat) return;
            var nb = m.nb||1;
            var mk = L.marker([m.lat, m.lng], {icon:lfiRatEmoIcon(m.emo)}).addTo(map);
            var html='<div style="min-width:150px"><strong>'+m.emo+' '+m.lab+(nb>1?' ×'+nb:'')+'</strong>';
            if(m.adr) html+='<br><span style="color:#0066a3">📍 '+m.adr+'</span>';
            if(m.date) html+='<br><span style="color:#777">🗓 '+m.date+'</span>';
            html+='</div>';
            mk.bindPopup(html);
            pts.push([m.lat, m.lng]);
        });
        if (pts.length) map.fitBounds(pts, {padding:[30,30], maxZoom:16});
    })();
    </script>
    <?php
    lfi_nct_app_screen_close();
}

/* -------------------------------------------------------------- *
 *  EXPORT CSV « preuve » (admins du GA uniquement).              *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_carte_rats_export() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $can_admin = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can_admin) { wp_safe_redirect(lfi_nct_app_url('carte-rats')); exit; }

    $types = lfi_nct_rat_types();
    $signals = lfi_nct_rat_signals_scoped();
    /* Tri chronologique (par date d'observation) pour un tableau lisible. */
    usort($signals, function ($a, $b) { return strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')); });

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=carte-rats-preuve-' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); /* BOM UTF-8 pour Excel */
    fputcsv($out, ['Date observation', 'Type', 'Nombre estimé', 'Adresse / repère', 'Latitude', 'Longitude', 'Précision', 'Signalé le'], ';');
    foreach ($signals as $s) {
        $t = $types[$s['type']] ?? $types['rat'];
        fputcsv($out, [
            $s['date'] ?? '',
            $t[1],
            (int) ($s['nombre'] ?? 1),
            $s['adresse'] ?? '',
            $s['lat'] ?? '',
            $s['lng'] ?? '',
            $s['desc'] ?? '',
            $s['ts'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}
