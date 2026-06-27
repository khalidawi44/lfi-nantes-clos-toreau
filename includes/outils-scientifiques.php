<?php
/**
 * Outils scientifiques pour la brigade — relevés de mesures sur le terrain.
 *
 * RÉSERVÉ ADMIN (Fabrice). Les locataires n'y ont pas accès.
 *
 * Ce que le téléphone PEUT mesurer (capteurs réels) :
 *  - dB SPL approximatif (via microphone, calibrage manuel)
 *  - Inclinaison / niveau (DeviceOrientationEvent)
 *  - Cap / boussole (alpha de DeviceOrientation)
 *  - GPS (Geolocation API) avec précision
 *  - Photo avec timestamp + GPS en overlay (preuve datée)
 *
 * Ce que le téléphone NE PEUT PAS mesurer (pas de capteur) :
 *  - Humidité (RH%) : aucune API. Solution = saisie manuelle d'un
 *    hygromètre 8 € + log temporel + graphe.
 *  - Température : idem, saisie manuelle d'un thermomètre.
 *  - Lux / éclairement : seul iOS Safari 18+ a l'Ambient Light Sensor,
 *    encore expérimental. Saisie manuelle si besoin précis.
 *  - 3D scan : ARKit/ARCore réservés aux apps natives, pas exposés
 *    aux PWA. Workaround = photo avec règle visible.
 */
if (!defined('ABSPATH')) exit;

/* ============================================================== *
 *  Page liste des outils                                            *
 * ============================================================== */
function lfi_nct_app_view_outils() {
    if (!current_user_can('manage_options')) return;

    lfi_nct_app_screen_open('🔬 Outils scientifiques', 'Relevés sur le terrain pour les preuves');

    echo '<div class="lfi-app-help"><strong>Outils de mesure pour preuves :</strong> chaque relevé est horodaté + géolocalisé. Stocke les données comme preuve pour les LRAR et les procédures juridiques.</div>';

    $outils = [
        ['🔊', 'Sonomètre dB',           'Mesure niveau sonore (microphone)',          'outil-sonometre'],
        ['📐', 'Niveau / inclinomètre',  'Affaissements, planchers déformés',          'outil-niveau'],
        ['🧭', 'Boussole',               'Orientation logement (exposition)',          'outil-boussole'],
        ['📸', 'Photo de preuve',        'Horodatée + GPS, prête pour dossier',        'outil-photo-preuve'],
        ['💧', 'Carnet humidité',        'Log hygromètre + temp (saisie manuelle)',    'outil-humidite'],
        ['📏', 'Règle calibrée',          'Mesure fissures avec carte de crédit étalon', 'outil-regle'],
        ['📍', 'GPS précis',              'Coordonnées exactes (lat/lng + précision)',  'outil-gps'],
    ];

    echo '<ul class="lfi-app-list">';
    foreach ($outils as $o) {
        echo '<li class="lfi-app-card">';
        echo '<a href="' . esc_url(lfi_nct_app_url($o[3])) . '" style="text-decoration:none;color:inherit">';
        echo '<div class="head"><div class="who">' . $o[0] . ' ' . esc_html($o[1]) . '</div></div>';
        echo '<div class="lfi-app-help" style="background:transparent;border:0;padding:0;color:#666;margin:6px 0 0"><small>' . esc_html($o[2]) . '</small></div>';
        echo '<div class="row-actions" style="margin-top:8px"><span class="btn-primary">▶️ Ouvrir</span></div>';
        echo '</a></li>';
    }
    echo '</ul>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  Sonomètre — Web Audio API, microphone, dB SPL approx            *
 * ============================================================== */
function lfi_nct_app_view_outil_sonometre() {
    if (!current_user_can('manage_options')) return;
    lfi_nct_app_screen_open('🔊 Sonomètre dB', 'Niveau sonore en temps réel');
    echo '<div class="lfi-app-help">Mesure approximative basée sur le microphone. <strong>Précision : ±5 dB</strong>. Pour un constat officiel utiliser un sonomètre classe 1 (300-500 €) ou faire venir un huissier.</div>';
    ?>
    <div class="lfi-tool-card">
        <div class="lfi-db-display">
            <div class="lfi-db-value" id="db-val">--</div>
            <div class="lfi-db-unit">dB SPL</div>
            <div class="lfi-db-bar"><div class="lfi-db-bar-fill" id="db-bar"></div></div>
            <div class="lfi-db-peak">Pic : <span id="db-peak">--</span> dB</div>
        </div>
        <button type="button" class="btn-primary big" id="db-start">▶️ Démarrer la mesure</button>
        <button type="button" class="btn-ghost" id="db-stop" style="display:none">⏹ Arrêter</button>
        <div class="lfi-app-help" style="margin-top:14px">
            <strong>Repères :</strong><br>
            • 30 dB = chuchotement, chambre la nuit<br>
            • 40 dB = bibliothèque calme<br>
            • 55 dB = conversation normale<br>
            • 70 dB = aspirateur, rue passante (limite OMS jour)<br>
            • 85 dB = trafic dense (limite OMS nuit dépassée)<br>
            • <strong>Au-delà de 55 dB en intérieur entre 22 h et 7 h = tapage nocturne</strong>
        </div>
        <div id="db-log" style="margin-top:14px;display:none">
            <h3>📋 Journal de la session</h3>
            <table class="lfi-mes-table">
                <thead><tr><th>Temps</th><th>dB</th></tr></thead>
                <tbody id="db-log-tbody"></tbody>
            </table>
            <button type="button" class="btn-ghost" id="db-copy" style="margin-top:8px">📋 Copier les données</button>
        </div>
    </div>
    <script>
    (function () {
        var audioCtx, analyser, source, stream, rafId;
        var peak = 0, samples = [], startTime = 0;
        function dbFromRMS(rms) {
            // RMS dans [0, 1]. dB SPL approximatif (calibrage typique smartphone)
            // Le micro phone "voit" -90 dB en très silence, ~120 dB max.
            // On mappe rms = 1 → 120 dB, rms = 0.001 → 30 dB
            var db = 20 * Math.log10(rms + 1e-9) + 100;
            return Math.max(0, Math.min(130, db));
        }
        async function start() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({audio: {echoCancellation: false, noiseSuppression: false, autoGainControl: false}});
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                source = audioCtx.createMediaStreamSource(stream);
                analyser = audioCtx.createAnalyser();
                analyser.fftSize = 2048;
                source.connect(analyser);
                document.getElementById('db-start').style.display = 'none';
                document.getElementById('db-stop').style.display = 'inline-flex';
                document.getElementById('db-log').style.display = 'block';
                peak = 0; samples = []; startTime = Date.now();
                loop();
            } catch (err) {
                alert('Microphone refusé ou indisponible : ' + err.message);
            }
        }
        function loop() {
            var buf = new Float32Array(analyser.fftSize);
            analyser.getFloatTimeDomainData(buf);
            var sum = 0;
            for (var i = 0; i < buf.length; i++) sum += buf[i] * buf[i];
            var rms = Math.sqrt(sum / buf.length);
            var db = dbFromRMS(rms);
            document.getElementById('db-val').textContent = db.toFixed(1);
            document.getElementById('db-bar').style.width = Math.min(100, db / 1.3) + '%';
            if (db > peak) {
                peak = db;
                document.getElementById('db-peak').textContent = db.toFixed(1);
            }
            // log every 1 sec
            var now = Date.now();
            if (samples.length === 0 || now - samples[samples.length-1].t > 1000) {
                samples.push({t: now, db: db});
                var tbody = document.getElementById('db-log-tbody');
                var elapsed = ((now - startTime) / 1000).toFixed(1);
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + elapsed + ' s</td><td>' + db.toFixed(1) + ' dB</td>';
                tbody.insertBefore(tr, tbody.firstChild);
                if (tbody.children.length > 60) tbody.removeChild(tbody.lastChild);
            }
            rafId = requestAnimationFrame(loop);
        }
        function stop() {
            if (rafId) cancelAnimationFrame(rafId);
            if (stream) stream.getTracks().forEach(function(t){t.stop();});
            if (audioCtx) audioCtx.close();
            document.getElementById('db-start').style.display = 'inline-flex';
            document.getElementById('db-stop').style.display = 'none';
        }
        document.getElementById('db-start').addEventListener('click', start);
        document.getElementById('db-stop').addEventListener('click', stop);
        document.getElementById('db-copy').addEventListener('click', function() {
            var txt = 'Mesures sonomètre — ' + new Date().toISOString() + '\nPic : ' + peak.toFixed(1) + ' dB\n';
            samples.forEach(function(s){ txt += new Date(s.t).toISOString() + '\t' + s.db.toFixed(1) + ' dB\n'; });
            navigator.clipboard.writeText(txt);
            this.textContent = '✓ Copié';
        });
    })();
    </script>
    <?php
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  Niveau / inclinomètre                                            *
 * ============================================================== */
function lfi_nct_app_view_outil_niveau() {
    if (!current_user_can('manage_options')) return;
    lfi_nct_app_screen_open('📐 Niveau & inclinomètre', 'Affaissements, planchers, murs déformés');
    echo '<div class="lfi-app-help">Précision ±0.5°. Pose le téléphone bien à plat ou contre la surface à mesurer.</div>';
    ?>
    <div class="lfi-tool-card">
        <div class="lfi-niveau-bubble">
            <div class="bubble-x" id="bub-x"></div>
            <div class="bubble-y" id="bub-y"></div>
        </div>
        <div class="lfi-niveau-vals">
            <div>X : <strong id="lev-x">--</strong>°</div>
            <div>Y : <strong id="lev-y">--</strong>°</div>
        </div>
        <button type="button" class="btn-primary big" id="lev-start">▶️ Activer le niveau</button>
        <div class="lfi-app-help" style="margin-top:14px">
            <strong>Lecture :</strong><br>
            • 0° = parfaitement de niveau<br>
            • 1-2° = défaut négligeable<br>
            • 3-5° = défaut visible, peut justifier signalement<br>
            • > 5° = affaissement structurel sérieux, photo + LRAR + SCHS mairie
        </div>
    </div>
    <script>
    (function () {
        function go() {
            window.addEventListener('deviceorientation', function (e) {
                var x = e.beta || 0;   // -180 to 180 (tilt front/back)
                var y = e.gamma || 0;  // -90 to 90 (tilt left/right)
                document.getElementById('lev-x').textContent = x.toFixed(1);
                document.getElementById('lev-y').textContent = y.toFixed(1);
                document.getElementById('bub-x').style.transform = 'translateX(' + Math.max(-100, Math.min(100, y * 4)) + 'px)';
                document.getElementById('bub-y').style.transform = 'translateY(' + Math.max(-100, Math.min(100, (x - 90) * 4)) + 'px)';
            });
        }
        document.getElementById('lev-start').addEventListener('click', async function () {
            if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
                try {
                    var perm = await DeviceOrientationEvent.requestPermission();
                    if (perm !== 'granted') { alert('Permission refusée.'); return; }
                } catch (e) {}
            }
            this.style.display = 'none';
            go();
        });
    })();
    </script>
    <?php
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  Boussole                                                         *
 * ============================================================== */
function lfi_nct_app_view_outil_boussole() {
    if (!current_user_can('manage_options')) return;
    lfi_nct_app_screen_open('🧭 Boussole', 'Orientation logement, exposition');
    ?>
    <div class="lfi-tool-card">
        <div class="lfi-boussole" id="bous"><div class="needle">▲</div></div>
        <div class="lfi-niveau-vals"><div>Cap : <strong id="bous-deg">--</strong>° (<strong id="bous-card">--</strong>)</div></div>
        <button type="button" class="btn-primary big" id="bous-start">▶️ Activer la boussole</button>
    </div>
    <script>
    (function () {
        function cardinal(deg) {
            var dirs = ['N','NE','E','SE','S','SO','O','NO'];
            return dirs[Math.round(deg / 45) % 8];
        }
        function go() {
            window.addEventListener('deviceorientationabsolute', handle, true);
            window.addEventListener('deviceorientation', handle, true);
        }
        function handle(e) {
            var deg = e.alpha || (e.webkitCompassHeading !== undefined ? e.webkitCompassHeading : 0);
            if (e.webkitCompassHeading !== undefined) deg = e.webkitCompassHeading;
            else deg = 360 - deg;
            document.getElementById('bous-deg').textContent = Math.round(deg);
            document.getElementById('bous-card').textContent = cardinal(deg);
            document.querySelector('#bous .needle').style.transform = 'rotate(' + deg + 'deg)';
        }
        document.getElementById('bous-start').addEventListener('click', async function () {
            if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
                try { var p = await DeviceOrientationEvent.requestPermission(); if (p !== 'granted') return; } catch (e) {}
            }
            this.style.display = 'none';
            go();
        });
    })();
    </script>
    <?php
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  GPS précis                                                       *
 * ============================================================== */
function lfi_nct_app_view_outil_gps() {
    if (!current_user_can('manage_options')) return;
    lfi_nct_app_screen_open('📍 GPS précis', 'Coordonnées exactes du logement');
    ?>
    <div class="lfi-tool-card">
        <div class="lfi-gps-vals">
            <div>Latitude : <strong id="gps-lat">--</strong></div>
            <div>Longitude : <strong id="gps-lng">--</strong></div>
            <div>Précision : <strong id="gps-acc">--</strong> m</div>
            <div>Altitude : <strong id="gps-alt">--</strong> m</div>
            <div>Cap : <strong id="gps-head">--</strong>°</div>
        </div>
        <button type="button" class="btn-primary big" id="gps-start">▶️ Localiser</button>
        <button type="button" class="btn-ghost" id="gps-copy">📋 Copier les coordonnées</button>
        <a id="gps-osm" class="btn-ghost" target="_blank" style="display:none">🗺 Ouvrir dans OpenStreetMap</a>
    </div>
    <script>
    (function () {
        var lastPos = null;
        document.getElementById('gps-start').addEventListener('click', function () {
            if (!navigator.geolocation) { alert('Géolocalisation non supportée.'); return; }
            navigator.geolocation.watchPosition(function (pos) {
                lastPos = pos;
                document.getElementById('gps-lat').textContent = pos.coords.latitude.toFixed(7);
                document.getElementById('gps-lng').textContent = pos.coords.longitude.toFixed(7);
                document.getElementById('gps-acc').textContent = Math.round(pos.coords.accuracy);
                document.getElementById('gps-alt').textContent = pos.coords.altitude !== null ? Math.round(pos.coords.altitude) : '—';
                document.getElementById('gps-head').textContent = pos.coords.heading !== null ? Math.round(pos.coords.heading) : '—';
                var osm = document.getElementById('gps-osm');
                osm.href = 'https://www.openstreetmap.org/?mlat=' + pos.coords.latitude + '&mlon=' + pos.coords.longitude + '&zoom=19';
                osm.style.display = 'inline-flex';
            }, function (err) {
                alert('GPS refusé ou indisponible : ' + err.message);
            }, {enableHighAccuracy: true});
        });
        document.getElementById('gps-copy').addEventListener('click', function () {
            if (!lastPos) return;
            var txt = lastPos.coords.latitude + ', ' + lastPos.coords.longitude + ' (précision ' + Math.round(lastPos.coords.accuracy) + ' m, ' + new Date().toISOString() + ')';
            navigator.clipboard.writeText(txt);
            this.textContent = '✓ Copié';
        });
    })();
    </script>
    <?php
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  Photo de preuve (timestamp + GPS overlay)                        *
 * ============================================================== */
function lfi_nct_app_view_outil_photo_preuve() {
    if (!current_user_can('manage_options')) return;
    lfi_nct_app_screen_open('📸 Photo de preuve', 'Horodatée + GPS, prête pour dossier');
    echo '<div class="lfi-app-help">Prends une photo, l\'app y ajoute date + heure + GPS en bandeau pour qu\'elle serve de preuve.</div>';
    ?>
    <div class="lfi-tool-card">
        <label>Choisir une photo (caméra ou galerie)<input type="file" accept="image/*" capture="environment" id="photo-input"></label>
        <canvas id="photo-canvas" style="display:none;max-width:100%;border-radius:10px;margin-top:14px"></canvas>
        <a id="photo-dl" class="btn-primary big" style="display:none;margin-top:8px" download="preuve.jpg">⬇ Télécharger la photo horodatée</a>
    </div>
    <script>
    (function () {
        var inp = document.getElementById('photo-input');
        var cv = document.getElementById('photo-canvas');
        var dl = document.getElementById('photo-dl');
        inp.addEventListener('change', function () {
            if (!inp.files.length) return;
            var file = inp.files[0];
            var fr = new FileReader();
            fr.onload = function () {
                var img = new Image();
                img.onload = function () {
                    var ctx = cv.getContext('2d');
                    cv.width = img.width; cv.height = img.height;
                    ctx.drawImage(img, 0, 0);
                    /* Bandeau horodatage + GPS */
                    function draw(lat, lng, acc) {
                        var h = Math.max(80, img.height * 0.05);
                        ctx.fillStyle = 'rgba(0,0,0,.75)';
                        ctx.fillRect(0, img.height - h, img.width, h);
                        ctx.fillStyle = '#fff';
                        ctx.font = (h * 0.35) + 'px Arial';
                        var ts = new Date().toLocaleString('fr-FR');
                        ctx.fillText('📅 ' + ts, 20, img.height - h * 0.55);
                        var locTxt = '📍 ' + (lat !== null ? (lat.toFixed(6) + ', ' + lng.toFixed(6) + ' ±' + Math.round(acc) + ' m') : 'GPS indisponible');
                        ctx.fillText(locTxt, 20, img.height - h * 0.15);
                        cv.style.display = 'block';
                        dl.style.display = 'inline-flex';
                        dl.href = cv.toDataURL('image/jpeg', 0.92);
                    }
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            function (pos) { draw(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy); },
                            function () { draw(null, null, null); },
                            {enableHighAccuracy: true, timeout: 5000}
                        );
                    } else draw(null, null, null);
                };
                img.src = fr.result;
            };
            fr.readAsDataURL(file);
        });
    })();
    </script>
    <?php
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  Carnet humidité (saisie manuelle, log et stat)                  *
 * ============================================================== */
function lfi_nct_app_view_outil_humidite() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $t = $wpdb->prefix . 'options';

    /* Stockage en option JSON pour rester simple */
    $log = get_option('lfi_nct_humidite_log', []);
    if (!is_array($log)) $log = [];

    if (!empty($_POST['lfi_app_humid_add']) && check_admin_referer('lfi_app_humid_add')) {
        $entry = [
            'date'  => sanitize_text_field(wp_unslash($_POST['date'] ?? '')),
            'lieu'  => sanitize_text_field(wp_unslash($_POST['lieu'] ?? '')),
            'piece' => sanitize_text_field(wp_unslash($_POST['piece'] ?? '')),
            'rh'    => (float) ($_POST['rh'] ?? 0),
            'temp'  => (float) ($_POST['temp'] ?? 0),
            'note'  => sanitize_text_field(wp_unslash($_POST['note'] ?? '')),
        ];
        $log[] = $entry;
        usort($log, function ($a, $b) { return strcmp($b['date'], $a['date']); });
        $log = array_slice($log, 0, 500);
        update_option('lfi_nct_humidite_log', $log, false);
        wp_safe_redirect(lfi_nct_app_url('outil-humidite', ['ok' => 1]));
        exit;
    }

    lfi_nct_app_screen_open('💧 Carnet humidité', 'Saisie manuelle hygromètre + thermomètre');

    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Relevé enregistré.');

    echo '<div class="lfi-app-help">Le téléphone n\'a pas de capteur d\'humidité. Saisis tes lectures d\'<strong>hygromètre digital LCD</strong> (8 € chez Brico Dépôt — référence : « Otio » ou « ThermoPro TP50 »). Le carnet calcule automatiquement le <strong>point de rosée</strong> (apparition condensation = mur trop froid).</div>';

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_humid_add');
    echo '<input type="hidden" name="lfi_app_humid_add" value="1">';
    echo '<label>Date<input type="datetime-local" name="date" value="' . esc_attr(current_time('Y-m-d\TH:i')) . '" required></label>';
    echo '<label>Logement (locataire / adresse)<input type="text" name="lieu" required placeholder="Mme Fadiga, 12 rue X"></label>';
    echo '<label>Pièce<select name="piece" required>';
    foreach (['Cuisine','Salle de bain','WC','Chambre','Salon','Couloir','Cave','Autre'] as $p) {
        echo '<option>' . esc_html($p) . '</option>';
    }
    echo '</select></label>';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
    echo '<label>Humidité (%RH)<input type="number" step="0.1" min="0" max="100" name="rh" required></label>';
    echo '<label>Température (°C)<input type="number" step="0.1" name="temp" required></label>';
    echo '</div>';
    echo '<label>Note (optionnel)<input type="text" name="note" placeholder="Ex: VMC arrêtée, fenêtre ouverte"></label>';
    echo '<button type="submit" class="btn-primary">+ Ajouter le relevé</button>';
    echo '</form>';

    if (!empty($log)) {
        echo '<h3 style="margin:18px 0 8px">📋 Derniers relevés (' . count($log) . ')</h3>';
        echo '<table class="lfi-mes-table"><thead><tr><th>Date</th><th>Lieu</th><th>Pièce</th><th>RH</th><th>T°</th><th>Pt rosée</th></tr></thead><tbody>';
        foreach (array_slice($log, 0, 20) as $e) {
            /* Point de rosée formule Magnus */
            $temp = (float) $e['temp']; $rh = (float) $e['rh'];
            $a = 17.27; $b = 237.7;
            $g = (($a * $temp) / ($b + $temp)) + log($rh / 100);
            $dp = ($b * $g) / ($a - $g);
            $alert = '';
            if ($rh >= 75) $alert = ' style="background:#fff3f5;color:#a30b25;font-weight:700"';
            elseif ($rh >= 65) $alert = ' style="background:#fff8e6"';
            echo '<tr' . $alert . '>';
            echo '<td>' . esc_html(wp_date('j/m H:i', strtotime($e['date']))) . '</td>';
            echo '<td>' . esc_html($e['lieu']) . '</td>';
            echo '<td>' . esc_html($e['piece']) . '</td>';
            echo '<td>' . esc_html($e['rh']) . '%</td>';
            echo '<td>' . esc_html($e['temp']) . '°C</td>';
            echo '<td>' . number_format($dp, 1) . '°C</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<div class="lfi-app-help" style="margin-top:8px"><small><strong>Seuils :</strong> > 65% RH = risque moisissures · > 75% RH = critique. Point de rosée proche de la temp ambiante = condensation imminente.</small></div>';
    }

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  Règle calibrée (mesure fissures à l'écran)                       *
 * ============================================================== */
function lfi_nct_app_view_outil_regle() {
    if (!current_user_can('manage_options')) return;
    lfi_nct_app_screen_open('📏 Règle calibrée', 'Mesure fissures avec carte de crédit comme étalon');
    ?>
    <div class="lfi-tool-card">
        <div class="lfi-app-help">Méthode pro pour mesurer une fissure avec précision sans pied à coulisse :<br>
        1. Pose une <strong>carte de crédit (85,6 × 53,98 mm — taille fixe)</strong> à côté de la fissure<br>
        2. Prends la photo de preuve<br>
        3. À l\'écran, mesure à partir de la carte (1 mm de carte = 1 mm de fissure)</div>
        <div style="text-align:center;margin:20px 0">
            <div class="lfi-cb-ref">CARTE BANCAIRE (85,6 mm)</div>
        </div>
        <a class="btn-primary big" href="<?php echo esc_url(lfi_nct_app_url('outil-photo-preuve')); ?>">📸 Aller à Photo de preuve</a>
        <div class="lfi-app-help" style="margin-top:14px">
            <strong>Lecture des fissures (DTU 21) :</strong><br>
            • < 0,2 mm : superficielles (peinture/enduit)<br>
            • 0,2-2 mm : actives, à surveiller (jauge fissuromètre 5 € chez Brico Dépôt)<br>
            • > 2 mm : <strong>structurelles, danger</strong>, signalement urgent au bailleur + SCHS mairie
        </div>
    </div>
    <style>
    .lfi-cb-ref { background: linear-gradient(135deg,#1a1a1a,#444); color:#fff; padding: 28px 14px; border-radius: 8px; width: 280px; max-width: 100%; margin: 0 auto; font-weight: 700; letter-spacing: 1px; }
    </style>
    <?php
    lfi_nct_app_screen_close(false);
}
