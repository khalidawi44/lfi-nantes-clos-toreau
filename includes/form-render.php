<?php
/**
 * Formulaire d'enquête porte-à-porte (version courte).
 * 8 questions max, conditionnel : si pas de problème, on saute aux coordonnées.
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_render_form($edit_id = 0) {
    $edit_id = (int) $edit_id;
    ob_start(); ?>
    <form method="POST" id="lfi-nct-form" class="lfi-survey lfi-survey-simple" enctype="multipart/form-data">
        <?php wp_nonce_field('lfi_nct_submit_nonce', 'lfi_nct_nonce'); ?>
        <?php if ($edit_id): ?><input type="hidden" name="lfi_nct_edit_id" value="<?php echo $edit_id; ?>"><?php endif; ?>

        <div class="lfi-print-bar">
            <button type="button" class="lfi-btn-print" onclick="window.print()">🖨️ Imprimer / Photocopier (version papier)</button>
        </div>

        <h2>Enquête porte-à-porte — logement</h2>
        <p class="lfi-section-desc">Quelques questions rapides pour identifier les problèmes et organiser une suite si besoin.</p>

        <fieldset class="lfi-fieldset">
            <legend class="lfi-legend">📍 Logement visité</legend>
            <label class="lfi-field">
                <span class="lfi-label">Immeuble / adresse <span class="req">*</span></span>
                <input type="text" name="adresse" required placeholder="Ex : 6 rue de Saint-Jean-de-Luz" list="lfi-nct-known-adr" autocomplete="off">
                <?php echo function_exists('lfi_nct_addresses_datalist') ? lfi_nct_addresses_datalist('lfi-nct-known-adr') : ''; ?>
            </label>
            <?php
            /* Ville OBLIGATOIRE — pré-remplie avec la commune du GA (souvent Nantes).
               Sans elle, une rue homonyme part dans la mauvaise ville. */
            $ville_def = 'Nantes';
            if (is_user_logged_in() && function_exists('lfi_nct_user_ga') && function_exists('lfi_nct_geo_perimetre')) {
                $g = (string) lfi_nct_user_ga();
                if ($g !== '') { $c = (string) (lfi_nct_geo_perimetre($g)['commune'] ?? ''); if ($c !== '') $ville_def = $c; }
            }
            ?>
            <label class="lfi-field">
                <span class="lfi-label">Ville / commune <span class="req">*</span></span>
                <input type="text" name="ville" required value="<?php echo esc_attr($ville_def); ?>" placeholder="Ex : Nantes">
            </label>
            <label class="lfi-field">
                <span class="lfi-label">Étage <span class="req">*</span></span>
                <input type="text" name="etage" required placeholder="Ex : 3">
            </label>
            <label class="lfi-field">
                <span class="lfi-label">Numéro d'appartement</span>
                <input type="text" name="appartement" placeholder="Ex : 32">
            </label>
            <label class="lfi-field">
                <span class="lfi-label">Nombre d'enfants au foyer</span>
                <input type="number" name="enfants" min="0" placeholder="Ex : 3">
            </label>
        </fieldset>

        <fieldset class="lfi-fieldset">
            <legend class="lfi-legend">Y a-t-il des problèmes dans ce logement ? <span class="req">*</span></legend>
            <label class="lfi-radio"><input type="radio" name="problemes_presence" value="oui" required> Oui</label>
            <label class="lfi-radio"><input type="radio" name="problemes_presence" value="non"> Non</label>
        </fieldset>

        <div id="lfi-bloc-problemes" hidden>
            <fieldset class="lfi-fieldset">
                <legend class="lfi-legend">Lesquels ? (cochez tout ce qui s'applique)</legend>
                <?php
                /* Clos Toreau (GA maison) = bloc « eau chaude » détaillé plus bas.
                   Ailleurs, pas de bloc dédié : l'eau chaude est une simple option. */
                $lfi_is_clos = !function_exists('lfi_nct_scope_ga_slug')
                    || in_array(lfi_nct_scope_ga_slug(), ['', 'clos-toreau'], true);
                /* Types de base + ceux appris des saisies précédentes (cf. lfi_nct_learn_custom_problem) */
                $types = function_exists('lfi_nct_problem_types_all') ? lfi_nct_problem_types_all() : [
                    'degats_eaux'      => '💧 Dégâts des eaux / fuites / infiltrations',
                    'humidite'         => '🌫️ Humidité / moisissures',
                    'insectes'         => '🐜 Insectes / nuisibles (cafards, punaises, rats…)',
                    'chauffage'        => '🥶 Chauffage insuffisant / panne',
                    'electricite'      => '⚡ Problèmes électriques',
                    'ascenseur'        => '🛗 Ascenseur en panne / défaillant',
                    'parties_communes' => '🚪 Parties communes dégradées',
                    'bruit'            => '🔊 Nuisances sonores / voisinage',
                    'securite'         => '🚨 Insécurité (entrées, parties communes…)',
                ];
                /* Hors Clos Toreau : l'eau chaude devient une option parmi les autres
                   (au Clos Toreau elle a son bloc détaillé, on ne double pas). */
                if (!$lfi_is_clos && !isset($types['eau_chaude'])) {
                    $types['eau_chaude'] = '🚿 Coupures d\'eau chaude';
                }
                ?>
                <p class="lfi-help">Pour chaque problème coché, précisez <strong>depuis quand</strong> et <strong>combien de fois par an</strong> (estimation).</p>
                <?php
                $durees = [
                    'moins_1_mois'        => "Moins d'un mois",
                    '1_6_mois'            => '1 à 6 mois',
                    '6_12_mois'           => '6 à 12 mois',
                    '1_5_ans'             => "Plus d'un an",
                    'plus_5_ans'          => 'Plus de 5 ans',
                    'depuis_emmenagement' => "Depuis mon emménagement",
                    'permanent'           => 'En permanence (toujours)',
                ];
                /* Fréquence / récurrence : « un peu … beaucoup … tout le temps ». */
                $recurrences = [
                    'permanent' => 'En permanence (tout le temps)',
                    'souvent'   => 'Souvent (beaucoup)',
                    'parfois'   => 'De temps en temps (un peu)',
                    'rare'      => 'Rarement / ponctuel',
                ];
                /* Sous-bloc ouvert quand on coche : récurrence + depuis quand + nb/an. */
                $sub = function ($k) use ($durees, $recurrences) {
                    ob_start(); ?>
                    <div class="lfi-prob-sub" id="sub-<?php echo esc_attr($k); ?>" hidden>
                        <div class="lfi-label" style="font-weight:700;color:#c8102e;margin:0 0 4px">Est-ce récurrent&nbsp;?</div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin:0 0 8px">
                            <?php foreach ($recurrences as $rk => $rl): ?>
                                <label class="lfi-radio" style="margin:0"><input type="radio" name="probleme_recurrent[<?php echo esc_attr($k); ?>]" value="<?php echo esc_attr($rk); ?>"> <?php echo esc_html($rl); ?></label>
                            <?php endforeach; ?>
                        </div>
                        <label class="lfi-field" style="margin:0 0 6px"><span class="lfi-label">Depuis quand&nbsp;?</span>
                            <select name="probleme_depuis[<?php echo esc_attr($k); ?>]">
                                <option value="">— choisir —</option>
                                <?php foreach ($durees as $dk => $dl): ?>
                                    <option value="<?php echo esc_attr($dk); ?>"><?php echo esc_html($dl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="lfi-field" style="margin:0"><span class="lfi-label">Combien de fois par an&nbsp;? (estimation)</span>
                            <input type="text" name="probleme_par_an[<?php echo esc_attr($k); ?>]" inputmode="numeric" placeholder="ex : 10, « plus de 15 », « je ne sais plus »">
                        </label>
                    </div>
                    <?php return ob_get_clean();
                };
                foreach ($types as $k => $label): ?>
                    <div class="lfi-prob-item">
                        <label class="lfi-check"><input type="checkbox" class="lfi-prob-cb" name="problemes_types[]" value="<?php echo esc_attr($k); ?>"> <?php echo $label; ?></label>
                    </div>
                <?php endforeach; ?>
                <div class="lfi-prob-item">
                    <label class="lfi-check"><input type="checkbox" class="lfi-prob-cb" name="problemes_types[]" value="autre"> Autre — précisez :</label>
                    <input type="text" name="problemes_types_autre" class="lfi-other-input" placeholder="Décrivez ici le problème (texte libre)">
                </div>

                <style>
                .lfi-prob-item { margin: 2px 0; }
                .lfi-prob-sub { margin: 4px 0 10px 26px; padding: 8px 10px; border-left: 3px solid #c8102e; background: #faf6f7; border-radius: 6px; }
                .lfi-prob-sub .lfi-label { font-size: .85em; color: #555; }
                @media print { .lfi-prob-sub[hidden] { display: block !important; } }
                </style>
                <script>
                (function () {
                    var boxes = document.querySelectorAll('.lfi-prob-cb');
                    boxes.forEach(function (cb) {
                        var sub = document.getElementById(cb.getAttribute('data-sub'));
                        if (!sub) return;
                        function upd() { sub.hidden = !cb.checked; }
                        cb.addEventListener('change', upd);
                        upd();
                    });
                })();
                </script>
            </fieldset>

            <fieldset class="lfi-fieldset">
                <legend class="lfi-legend">Depuis quand ? À quelle fréquence ? <span style="color:#888;font-weight:400">(en général, facultatif)</span></legend>
                <label class="lfi-field" style="margin:0 0 6px"><span class="lfi-label">Depuis quand&nbsp;?</span>
                    <select name="problemes_duree">
                        <option value="">— choisir —</option>
                        <?php foreach ($durees as $dk => $dl): ?>
                            <option value="<?php echo esc_attr($dk); ?>"><?php echo esc_html($dl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="lfi-label" style="font-weight:700;color:#c8102e;margin:2px 0 4px">Est-ce récurrent&nbsp;?</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px">
                    <?php foreach ($recurrences as $rk => $rl): ?>
                        <label class="lfi-radio" style="margin:0"><input type="radio" name="problemes_recurrent" value="<?php echo esc_attr($rk); ?>"> <?php echo esc_html($rl); ?></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="lfi-fieldset">
                <legend class="lfi-legend">Gravité ressentie <span class="req">*</span></legend>
                <p class="lfi-help">1 = mineur · 10 = insupportable / critique — une seule note pour l'ensemble</p>
                <div class="lfi-scale">
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <label class="lfi-radio-btn"><input type="radio" name="problemes_gravite" value="<?php echo $i; ?>"> <?php echo $i; ?></label>
                    <?php endfor; ?>
                </div>
            </fieldset>
        </div>

        <?php
        /* Bailleur social : proposé au choix (le parc n'est pas le même selon le
           quartier). L'enquêteur/le locataire choisit ; « Autre » = à préciser. */
        $lfi_bailleurs = function_exists('lfi_nct_bailleurs_for_ga') ? lfi_nct_bailleurs_for_ga() : [];
        if (!empty($lfi_bailleurs)): ?>
        <fieldset class="lfi-fieldset">
            <legend class="lfi-legend">🏢 Bailleur social (propriétaire du logement)</legend>
            <label class="lfi-field">
                <select name="bailleur">
                    <option value="">— choisir —</option>
                    <?php foreach ($lfi_bailleurs as $bl): ?>
                        <option value="<?php echo esc_attr($bl['nom']); ?>"><?php echo esc_html($bl['nom'] . ($bl['sigle'] ? ' (' . $bl['sigle'] . ')' : '')); ?></option>
                    <?php endforeach; ?>
                    <option value="__autre">Autre bailleur…</option>
                    <option value="__inconnu">Je ne sais pas</option>
                </select>
            </label>
            <input type="text" name="bailleur_autre" class="lfi-other-input" placeholder="Nom du bailleur si « Autre »" style="margin-top:6px">
        </fieldset>
        <?php endif; ?>

        <?php
        /* Section « coupures d'eau chaude » : spécifique au Clos Toreau (fait de
           quartier). On ne l'affiche QUE pour ce GA (home / clos-toreau) ; ailleurs
           l'eau chaude est déjà proposée en simple option ci-dessus. */
        if ($lfi_is_clos): ?>
        <fieldset class="lfi-fieldset">
            <legend class="lfi-legend">🚿 Coupures d'eau chaude récurrentes</legend>
            <p class="lfi-help">
                <strong>Fait avéré sur le quartier</strong> : 100&nbsp;% des immeubles enquêtés sont touchés, avec
                plus de 10 coupures par an et plus de 10 jours cumulés sans eau chaude. Durée variant de
                2&nbsp;jours à 3&nbsp;semaines consécutives selon les immeubles.<br>
                Notez ici les éléments concrets que la personne donne, et son verbatim ci-dessous.
            </p>
            <label class="lfi-field">
                <span class="lfi-label">Nombre de coupures par an (estimation)</span>
                <input type="text" name="eau_chaude_nb_par_an" placeholder="ex : 10, « plus de 15 », « je ne sais plus »">
            </label>
            <label class="lfi-field">
                <span class="lfi-label">Plus longue coupure subie</span>
                <input type="text" name="eau_chaude_duree_max" placeholder="ex : 3 semaines, 5 jours…">
            </label>
            <label class="lfi-field">
                <span class="lfi-label">Citation / déclaration (verbatim)</span>
                <textarea name="eau_chaude_citation" rows="2" placeholder="Notez tel que dit, sans rien changer"></textarea>
            </label>
        </fieldset>
        <?php endif; ?>

        <fieldset class="lfi-fieldset">
            <legend class="lfi-legend">📸 Photos du logement (facultatif)</legend>
            <p class="lfi-help">
                Si la personne vous <strong>invite à entrer</strong> et accepte, vous pouvez prendre
                <strong>plusieurs photos</strong> (fuites, moisissures, dégâts, chauffage…).
                Elles sont <strong>horodatées automatiquement</strong> (date et heure d'enregistrement)
                et restent <strong>strictement internes</strong> au Groupe d'Action — jamais transmises à Nantes Habitat.
            </p>
            <label class="lfi-field">
                <span class="lfi-label">Ajouter des photos</span>
                <input type="file" name="enquete_photos[]" accept="image/*" multiple class="lfi-photo-input" id="lfi-enquete-photos">
            </label>
            <p class="lfi-help">Sur iPhone : « Photothèque » pour en choisir plusieurs d'un coup, ou « Prendre une photo » pour la caméra. Vous pouvez en ajouter autant que nécessaire.</p>
            <div id="lfi-enquete-photos-preview" class="lfi-photos-preview" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px"></div>
            <script>
            (function(){
                var inp = document.getElementById('lfi-enquete-photos');
                var box = document.getElementById('lfi-enquete-photos-preview');
                var form = document.getElementById('lfi-nct-form');
                if(!inp || !box) return;
                /* Les photos d'iPhone pèsent 3–5 Mo : plusieurs d'un coup font un
                   envoi de 15–20 Mo qui DÉPASSE la limite du serveur → « connexion
                   réseau perdue » et RIEN ne s'enregistre. On COMPRESSE chaque photo
                   dans le navigateur (max 1600 px, JPEG) — l'envoi devient léger et
                   fiable. Bonus : les HEIC iPhone sont converties en JPEG. */
                var MAXW = 1600, Q = 0.82, busy = false;
                function submitBtns(){ return form ? form.querySelectorAll('button[type=submit],input[type=submit]') : []; }
                function setBusy(b){
                    busy = b;
                    var btns = submitBtns();
                    for (var i=0;i<btns.length;i++){ btns[i].disabled = b; btns[i].style.opacity = b ? '.5' : ''; }
                }
                function compressOne(file){
                    return new Promise(function(resolve){
                        if(!/^image\//.test(file.type) && !/\.(jpe?g|png|heic|heif)$/i.test(file.name||'')){ resolve(file); return; }
                        var url = URL.createObjectURL(file);
                        var img = new Image();
                        img.onload = function(){
                            try{
                                var w = img.naturalWidth||img.width, h = img.naturalHeight||img.height;
                                if(!w||!h){ URL.revokeObjectURL(url); resolve(file); return; }
                                var scale = Math.min(1, MAXW/Math.max(w,h));
                                var cw = Math.max(1,Math.round(w*scale)), ch = Math.max(1,Math.round(h*scale));
                                var cv = document.createElement('canvas'); cv.width=cw; cv.height=ch;
                                cv.getContext('2d').drawImage(img,0,0,cw,ch);
                                URL.revokeObjectURL(url);
                                cv.toBlob(function(blob){
                                    if(!blob){ resolve(file); return; }
                                    /* Si l'original est déjà plus léger, on le garde. */
                                    if(blob.size >= (file.size||Infinity)){ resolve(file); return; }
                                    var name = (file.name||'photo').replace(/\.(heic|heif|png)$/i,'.jpg');
                                    if(!/\.jpe?g$/i.test(name)) name += '.jpg';
                                    try{ resolve(new File([blob], name, {type:'image/jpeg', lastModified: file.lastModified||0})); }
                                    catch(e){ resolve(blob); }
                                }, 'image/jpeg', Q);
                            }catch(e){ try{URL.revokeObjectURL(url);}catch(_){} resolve(file); }
                        };
                        img.onerror = function(){ try{URL.revokeObjectURL(url);}catch(_){} resolve(file); };
                        img.src = url;
                    });
                }
                function preview(files, note){
                    box.innerHTML = '';
                    var info = document.createElement('div');
                    info.style.cssText='width:100%;font-size:.9em;color:#186a3b;font-weight:700';
                    info.textContent = note;
                    box.appendChild(info);
                    Array.prototype.forEach.call(files, function(f){
                        var img = document.createElement('img');
                        img.style.cssText='width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid #ccc';
                        img.src = URL.createObjectURL(f);
                        box.appendChild(img);
                    });
                }
                inp.addEventListener('change', function(){
                    var files = inp.files || [];
                    if(!files.length){ box.innerHTML=''; return; }
                    /* Sans DataTransfer on ne peut pas réinjecter les fichiers
                       compressés : on garde l'original (au moins ça reste fonctionnel). */
                    var canReplace = true; try{ new DataTransfer(); }catch(e){ canReplace = false; }
                    if(!canReplace){ preview(files, '📸 ' + files.length + ' photo(s) prête(s).'); return; }
                    setBusy(true);
                    preview(files, '⏳ Préparation de ' + files.length + ' photo(s)…');
                    var tasks = Array.prototype.map.call(files, compressOne);
                    Promise.all(tasks).then(function(out){
                        try{
                            var dt = new DataTransfer();
                            out.forEach(function(f){ dt.items.add(f); });
                            inp.files = dt.files;
                        }catch(e){}
                        preview(inp.files, '📸 ' + inp.files.length + ' photo(s) prête(s) — horodatée(s) à l\'enregistrement.');
                        setBusy(false);
                    }).catch(function(){ setBusy(false); });
                });
                /* Filet : si on tente d'envoyer pendant la préparation, on bloque
                   une seconde le temps que les photos soient prêtes. */
                if(form) form.addEventListener('submit', function(e){
                    if(busy){ e.preventDefault(); alert('Les photos finissent de se préparer, réessayez dans un instant.'); }
                });
            })();
            </script>
        </fieldset>

        <fieldset class="lfi-fieldset">
            <legend class="lfi-legend">Accepteriez-vous d'être suivi·e et accompagné·e ?</legend>
            <p class="lfi-help">On revient constater sur place, on fait pression sur Nantes Habitat, et on vous accompagne <strong>juridiquement</strong> — <strong>gratuitement</strong>. Pour agir légalement en votre nom, vous devenez <strong>adhérent·e (gratuit·e) de l'association Union des Quartiers Libres</strong>.</p>
            <label class="lfi-radio"><input type="radio" name="revenir_ok" value="oui"> Oui, je veux être suivi·e <span style="color:#186a3b">(adhésion gratuite)</span></label>
            <label class="lfi-radio"><input type="radio" name="revenir_ok" value="non"> Non, merci</label>
        </fieldset>

        <div id="lfi-bloc-contact" hidden>
            <div class="lfi-info-box" style="background:#eef7ee;border:1px solid #186a3b">
                👉 <strong>Militant·e</strong> : la personne devient <strong>adhérente gratuite</strong> de l'association. Remplis son identité, puis <strong>fais-lui signer sa fiche d'adhésion</strong> ci-dessous, directement sur l'écran.
            </div>
            <fieldset class="lfi-fieldset">
                <legend class="lfi-legend">🪪 Fiche d'adhésion — Union des Quartiers Libres</legend>
                <label class="lfi-field"><span class="lfi-label">Prénom <span style="color:#888;font-weight:400">(facultatif)</span></span><input type="text" name="contact_prenom"></label>
                <label class="lfi-field"><span class="lfi-label">Nom <span style="color:#888;font-weight:400">(facultatif)</span></span><input type="text" name="contact_nom"></label>
                <label class="lfi-field"><span class="lfi-label">Date de naissance</span><input type="date" name="adhesion_naissance"></label>
                <label class="lfi-field"><span class="lfi-label">Adresse complète</span><input type="text" name="adhesion_adresse" placeholder="N°, rue, étage, appartement"></label>
                <label class="lfi-field"><span class="lfi-label">Téléphone</span><input type="tel" name="contact_tel" placeholder="06 12 34 56 78"></label>
                <label class="lfi-field"><span class="lfi-label">Email</span><input type="email" name="contact_email" placeholder="vous@email.fr"></label>
                <p class="lfi-help">Téléphone <strong>ou</strong> email — au moins l'un des deux pour qu'on puisse vous recontacter.</p>
            </fieldset>

            <fieldset class="lfi-fieldset">
                <legend class="lfi-legend">✍️ Signature de l'adhésion</legend>
                <div class="lfi-info-box" style="font-size:.9em">
                    En signant, je demande mon adhésion <strong>gratuite</strong> à l'association <strong>Union des Quartiers Libres</strong> et je l'autorise, avec le <strong>Groupe d'Action La France Insoumise Nantes Sud – Clos Toreau</strong>, à m'<strong>accompagner et à agir en mon nom</strong> auprès du bailleur et des institutions pour la défense de mon logement. Mes données restent internes (RGPD) ; je peux retirer mon adhésion à tout moment.
                </div>
                <label class="lfi-check"><input type="checkbox" name="adhesion_consent" value="1"> J'ai lu et j'accepte (adhésion gratuite)</label>
                <div style="margin-top:8px">
                    <canvas id="lfi-sign-pad" width="600" height="180" style="width:100%;height:180px;border:2px dashed #c8102e;border-radius:10px;background:#fff;touch-action:none;display:block"></canvas>
                    <input type="hidden" name="adhesion_signature" id="lfi-sign-data">
                    <div style="margin-top:6px;display:flex;gap:8px;align-items:center">
                        <button type="button" id="lfi-sign-clear" class="lfi-btn">Effacer</button>
                        <span class="lfi-help" style="margin:0">Signez avec le doigt dans le cadre.</span>
                    </div>
                </div>
                <script>
                (function(){
                    var cv = document.getElementById('lfi-sign-pad');
                    var hidden = document.getElementById('lfi-sign-data');
                    var clr = document.getElementById('lfi-sign-clear');
                    if(!cv || !hidden) return;
                    var ctx = cv.getContext('2d');
                    /* Résolution réelle du canvas = taille affichée (net sur mobile). */
                    function fit(){
                        var r = cv.getBoundingClientRect();
                        if(!r.width) return;
                        var ratio = window.devicePixelRatio || 1;
                        cv.width = r.width * ratio; cv.height = r.height * ratio;
                        ctx.setTransform(ratio,0,0,ratio,0,0);
                        ctx.lineWidth = 2.2; ctx.lineCap='round'; ctx.lineJoin='round'; ctx.strokeStyle='#111';
                    }
                    setTimeout(fit, 60);
                    var SKEY = 'lfi_sign_backup';
                    var drawing=false, dirty=false, last=null;
                    function pos(e){
                        var r = cv.getBoundingClientRect();
                        var p = (e.touches && e.touches[0]) ? e.touches[0] : e;
                        return { x:p.clientX - r.left, y:p.clientY - r.top };
                    }
                    function start(e){ e.preventDefault(); drawing=true; last=pos(e); }
                    function move(e){ if(!drawing) return; e.preventDefault(); var p=pos(e);
                        ctx.beginPath(); ctx.moveTo(last.x,last.y); ctx.lineTo(p.x,p.y); ctx.stroke();
                        last=p; dirty=true; }
                    /* Export ALLÉGÉ : la signature est réduite à 600px de large max,
                       fond blanc. En porte-à-porte le réseau est faible : un POST
                       léger part de façon fiable (le PNG plein écran Retina pesait
                       des centaines de Ko et faisait échouer l'envoi). */
                    function exportSig(){
                        try{
                            var maxW = 600, scale = Math.min(1, maxW / cv.width);
                            var out = document.createElement('canvas');
                            out.width = Math.max(1, Math.round(cv.width * scale));
                            out.height = Math.max(1, Math.round(cv.height * scale));
                            var octx = out.getContext('2d');
                            octx.fillStyle = '#fff'; octx.fillRect(0,0,out.width,out.height);
                            octx.drawImage(cv, 0,0, out.width, out.height);
                            return out.toDataURL('image/png');
                        }catch(err){ try{ return cv.toDataURL('image/png'); }catch(e){ return ''; } }
                    }
                    /* Capture la signature dans le champ caché + sauvegarde locale
                       (au cas où l'envoi échoue : on la restaure au rechargement). */
                    function commit(){ if(!dirty) return; var d = exportSig(); if(d){ hidden.value = d; try{ sessionStorage.setItem(SKEY, d); }catch(e){} } }
                    function end(){ if(!drawing) return; drawing=false; commit(); }
                    cv.addEventListener('mousedown',start); cv.addEventListener('mousemove',move);
                    window.addEventListener('mouseup',end);
                    cv.addEventListener('touchstart',start,{passive:false});
                    cv.addEventListener('touchmove',move,{passive:false});
                    cv.addEventListener('touchend',end);
                    cv.addEventListener('touchcancel',end);
                    if(clr) clr.addEventListener('click',function(){ ctx.clearRect(0,0,cv.width,cv.height); hidden.value=''; dirty=false; try{ sessionStorage.removeItem(SKEY); }catch(e){} });
                    /* Le pavé est dans un bloc masqué au départ : on recalcule sa
                       taille quand la personne choisit « Oui » (bloc révélé). */
                    document.addEventListener('change', function(e){
                        if(e.target && e.target.name === 'revenir_ok' && e.target.value === 'oui'){ setTimeout(fit, 90); }
                    });
                    /* FILET DE SÉCURITÉ à l'envoi : on recapture la signature (si un
                       touchend a été manqué → sinon l'enquête partait SANS signature)
                       et on la garde en secours. */
                    var form = document.getElementById('lfi-nct-form');
                    if(form){ form.addEventListener('submit', function(){ commit(); }); }
                    /* RESTAURATION : si l'envoi précédent a échoué (réseau perdu) et
                       qu'on revient sur CE formulaire (même onglet), on remet la
                       signature capturée — tant qu'on n'a pas effacé ni re-signé. */
                    try{
                        var saved = sessionStorage.getItem(SKEY);
                        if(saved && !hidden.value){
                            hidden.value = saved;
                            var img = new Image();
                            img.onload = function(){
                                try{ fit(); var rr = cv.getBoundingClientRect();
                                    ctx.drawImage(img, 0, 0, rr.width, rr.height); dirty = true; }catch(e){}
                            };
                            img.src = saved;
                        }
                    }catch(e){}
                })();
                </script>
            </fieldset>
        </div>

        <details class="lfi-fieldset" style="padding:10px 14px">
            <summary style="cursor:pointer;font-weight:700;color:#0b3d91;list-style:none">➕ Rester en lien avec le Groupe d'Action <span style="color:#888;font-weight:400">(facultatif)</span></summary>
            <div style="margin-top:8px">
                <label class="lfi-check"><input type="checkbox" name="interesse_ga" value="1"> <strong>Intéressé·e par le Groupe d'Action</strong> — être tenu·e informé·e, participer aux actions du quartier</label>
                <label class="lfi-field"><span class="lfi-label">Prénom <span style="color:#888;font-weight:400">(facultatif)</span></span><input type="text" name="interesse_prenom"></label>
                <label class="lfi-field"><span class="lfi-label">Nom <span style="color:#888;font-weight:400">(facultatif)</span></span><input type="text" name="interesse_nom"></label>
                <label class="lfi-field"><span class="lfi-label">Téléphone ou email <span style="color:#888;font-weight:400">(facultatif)</span></span><input type="text" name="interesse_contact" placeholder="pour vous tenir informé·e"></label>
                <p class="lfi-help">Rien n'est obligatoire ici. Ces informations servent uniquement à vous tenir informé·e des actions du quartier — jamais transmises à un tiers.</p>
            </div>
        </details>

        <div class="lfi-info-box">
            🔒 <strong>RGPD</strong> : ces infos sont strictement internes au Groupe d'Action LFI Nantes Sud Clos Toreau, jamais transmises à un tiers. Vous pouvez demander leur suppression à tout moment.
        </div>

        <p>
            <button type="submit" name="lfi_nct_submit" class="lfi-btn lfi-btn-lg lfi-submit">✓ Enregistrer l'enquête</button>
        </p>
    </form>
    <script>
    /* FILET DE SÉCURITÉ : dans l'app (rendu autonome), form.js peut ne pas se
       charger. On garantit ici l'ouverture du bloc « problèmes » (présence=oui)
       et du bloc « contact / recontact » (suivi=oui), sinon la personne ne peut
       pas accepter d'être recontactée → aucun dossier créé. */
    (function(){
        function byName(n){ return document.querySelector('input[name="'+n+'"]:checked'); }
        function toggle(id, show){ var el=document.getElementById(id); if(el) el.hidden = !show; }
        function refresh(){
            var p = byName('problemes_presence'); toggle('lfi-bloc-problemes', !!(p && p.value==='oui'));
            var r = byName('revenir_ok');         toggle('lfi-bloc-contact',   !!(r && r.value==='oui'));
        }
        document.addEventListener('change', function(e){
            if(e.target && (e.target.name==='problemes_presence' || e.target.name==='revenir_ok')) refresh();
        });
        refresh();
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Résumé imprimable de la réponse qui vient d'être envoyée.
 */
function lfi_nct_render_submission_summary($id) {
    global $wpdb;
    $id = (int) $id;
    if ($id <= 0) return '';
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    if (!$row) return '';
    $data = $row->data ? json_decode($row->data, true) : [];
    if (!is_array($data)) $data = [];

    $fresh_url = esc_url(remove_query_arg(['_wp_http_referer']));

    $type_labels = [
        'degats_eaux'      => 'Dégâts des eaux / infiltrations',
        'odeurs_egout'     => 'Odeurs d\'égout / remontées d\'eaux usées',
        'humidite'         => 'Humidité / moisissures',
        'insectes'         => 'Insectes / nuisibles',
        'chauffage'        => 'Chauffage insuffisant',
        'eau_chaude'       => 'Coupures d\'eau chaude',
        'electricite'      => 'Problèmes électriques',
        'ascenseur'        => 'Ascenseur',
        'parties_communes' => 'Parties communes',
        'bruit'            => 'Nuisances sonores',
        'securite'         => 'Insécurité',
        'autre'            => 'Autre',
    ];
    $duree_labels = [
        'moins_1_mois'        => "Moins d'un mois",
        '1_6_mois'            => '1 à 6 mois',
        '6_12_mois'           => '6 à 12 mois',
        '1_5_ans'             => "Plus d'un an",
        'plus_5_ans'          => 'Plus de 5 ans',
        'depuis_emmenagement' => "Depuis l'emménagement",
        'permanent'           => 'En permanence',
    ];
    $rec_labels = [
        'permanent' => 'En permanence',
        'souvent'   => 'Souvent',
        'parfois'   => 'De temps en temps',
        'rare'      => 'Rarement / ponctuel',
        'ponctuel'  => 'Ponctuel',
    ];

    $presence    = $data['problemes_presence'] ?? '';
    $types       = (array) ($data['problemes_types'] ?? []);
    $types_autre = $data['problemes_types_autre'] ?? '';
    $duree       = $data['problemes_duree'] ?? '';
    $rec         = $data['problemes_recurrent'] ?? '';
    $gravite     = (int) ($data['problemes_gravite'] ?? 0);
    $revenir     = $data['revenir_ok'] ?? '';
    $appt        = $data['appartement'] ?? '';

    ob_start(); ?>
    <div class="lfi-survey lfi-submission">
        <div class="lfi-print-bar">
            <button type="button" class="lfi-btn-print" onclick="window.print()">🖨️ Imprimer ma réponse</button>
            <a href="<?php echo $fresh_url; ?>" class="lfi-btn-print" style="margin-left:.5em;text-decoration:none">📝 Saisir une nouvelle enquête</a>
        </div>

        <h2>Réponse enregistrée — enquête n°<?php echo (int) $row->id; ?></h2>
        <p class="lfi-help">Enregistrée le <?php echo esc_html($row->submitted_at); ?>.</p>

        <h3>📍 Logement</h3>
        <ul class="lfi-summary-list">
            <li><strong>Adresse :</strong> <?php echo esc_html($row->adresse); ?></li>
            <li><strong>Étage :</strong> <?php echo esc_html($row->etage); ?></li>
            <?php if ($appt !== ''): ?><li><strong>Appartement :</strong> <?php echo esc_html($appt); ?></li><?php endif; ?>
        </ul>

        <h3>Problèmes</h3>
        <p><strong><?php
            if ($presence === 'oui') echo '⚠️ Oui';
            elseif ($presence === 'non') echo '✅ Aucun';
            else echo '—';
        ?></strong></p>

        <?php if ($presence === 'oui'): ?>
            <?php
            $p_depuis = (array) ($data['probleme_depuis'] ?? []);
            $p_paran  = (array) ($data['probleme_par_an']  ?? []);
            ?>
            <ul class="lfi-summary-list">
                <?php if ($types): ?>
                    <?php foreach ($types as $t): ?>
                        <li><strong><?php echo esc_html($type_labels[$t] ?? $t); ?></strong>
                            <?php
                            $bits = [];
                            $d = trim((string) ($p_depuis[$t] ?? ''));
                            $n = trim((string) ($p_paran[$t]  ?? ''));
                            if ($d !== '') $bits[] = 'depuis ' . ($duree_labels[$d] ?? $d);
                            if ($n !== '') $bits[] = $n . ' fois/an';
                            if ($bits) echo ' — <span style="color:#555">' . esc_html(implode(' · ', $bits)) . '</span>';
                            if ($t === 'autre' && $types_autre !== '') echo ' <span style="color:#555">(' . esc_html($types_autre) . ')</span>';
                            ?>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($duree !== ''): ?><li><strong>Durée (globale) :</strong> <?php echo esc_html($duree_labels[$duree] ?? $duree); ?></li><?php endif; ?>
                <?php if ($rec !== ''): ?><li><strong>Récurrence :</strong> <?php echo esc_html($rec_labels[$rec] ?? $rec); ?></li><?php endif; ?>
                <?php if ($gravite > 0): ?><li><strong>Gravité ressentie :</strong> <?php echo $gravite; ?> / 10</li><?php endif; ?>
            </ul>
        <?php endif; ?>

        <?php
        $ec_nb     = trim((string) ($data['eau_chaude_nb_par_an'] ?? ''));
        $ec_duree  = trim((string) ($data['eau_chaude_duree_max'] ?? ''));
        $ec_cit    = trim((string) ($data['eau_chaude_citation']  ?? ''));
        if ($ec_nb !== '' || $ec_duree !== '' || $ec_cit !== ''): ?>
            <h3>🚿 Coupures d'eau chaude récurrentes</h3>
            <ul class="lfi-summary-list">
                <?php if ($ec_nb !== ''): ?>
                    <li><strong>Coupures par an :</strong> <?php echo esc_html($ec_nb); ?></li>
                <?php endif; ?>
                <?php if ($ec_duree !== ''): ?>
                    <li><strong>Plus longue coupure :</strong> <?php echo esc_html($ec_duree); ?></li>
                <?php endif; ?>
                <?php if ($ec_cit !== ''): ?>
                    <li><strong>Déclaration :</strong> «&nbsp;<?php echo esc_html($ec_cit); ?>&nbsp;»</li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>

        <?php
        $photos = (array) ($data['photos'] ?? []);
        if ($photos): ?>
            <h3>📸 Photos du logement (horodatées)</h3>
            <p class="lfi-help"><?php echo count($photos); ?> photo(s) enregistrée(s) — internes au Groupe d'Action.</p>
            <div class="lfi-photos-grid" style="display:flex;flex-wrap:wrap;gap:8px">
                <?php foreach ($photos as $ph):
                    $pid = (int) ($ph['id'] ?? 0);
                    if (!$pid) continue;
                    $thumb = wp_get_attachment_image_url($pid, 'medium');
                    $full  = wp_get_attachment_url($pid);
                    if (!$thumb) continue; ?>
                    <a href="<?php echo esc_url($full); ?>" target="_blank" rel="noopener" style="text-decoration:none">
                        <img src="<?php echo esc_url($thumb); ?>" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:8px;border:1px solid #ccc">
                        <span style="display:block;font-size:.72em;color:#666;text-align:center;margin-top:2px"><?php echo esc_html($ph['date'] ?? ''); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h3>Suivi</h3>
        <p><strong>Souhaite être recontacté·e :</strong> <?php echo $revenir === 'oui' ? '✅ Oui' : '❌ Non'; ?></p>
        <?php if ($revenir === 'oui'): ?>
            <ul class="lfi-summary-list">
                <?php if ($row->contact_prenom !== ''): ?><li><strong>Prénom :</strong> <?php echo esc_html($row->contact_prenom); ?></li><?php endif; ?>
                <?php if ($row->contact_nom !== ''): ?><li><strong>Nom :</strong> <?php echo esc_html($row->contact_nom); ?></li><?php endif; ?>
                <?php if ($row->contact_tel !== ''): ?><li><strong>Téléphone :</strong> <?php echo esc_html($row->contact_tel); ?></li><?php endif; ?>
                <?php if ($row->contact_email !== ''): ?><li><strong>Email :</strong> <?php echo esc_html($row->contact_email); ?></li><?php endif; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($data['interesse_ga'])): ?>
            <h3>📌 Intéressé·e par le Groupe d'Action</h3>
            <ul class="lfi-summary-list">
                <li><strong>Souhaite rester en lien :</strong> ✅ Oui</li>
                <?php if (!empty($data['interesse_prenom'])): ?><li><strong>Prénom :</strong> <?php echo esc_html($data['interesse_prenom']); ?></li><?php endif; ?>
                <?php if (!empty($data['interesse_nom'])): ?><li><strong>Nom :</strong> <?php echo esc_html($data['interesse_nom']); ?></li><?php endif; ?>
                <?php if (!empty($data['interesse_contact'])): ?><li><strong>Contact :</strong> <?php echo esc_html($data['interesse_contact']); ?></li><?php endif; ?>
            </ul>
        <?php endif; ?>

        <?php
        $adh = (array) ($data['adhesion'] ?? []);
        if (!empty($adh)): ?>
            <h3>🪪 Adhésion — Union des Quartiers Libres</h3>
            <ul class="lfi-summary-list">
                <li><strong>Statut :</strong> <?php echo !empty($adh['signed']) ? '✅ Adhésion signée' : '🕒 À signer'; ?></li>
                <?php if (!empty($adh['naissance'])): ?><li><strong>Date de naissance :</strong> <?php echo esc_html($adh['naissance']); ?></li><?php endif; ?>
                <?php if (!empty($adh['adresse'])): ?><li><strong>Adresse :</strong> <?php echo esc_html($adh['adresse']); ?></li><?php endif; ?>
                <?php if (!empty($adh['date'])): ?><li><strong>Signée le :</strong> <?php echo esc_html($adh['date']); ?></li><?php endif; ?>
            </ul>
            <?php if (!empty($adh['signature_url'])): ?>
                <div><strong>Signature :</strong><br><img src="<?php echo esc_url($adh['signature_url']); ?>" alt="Signature" style="max-width:280px;border:1px solid #ccc;border-radius:8px;background:#fff"></div>
            <?php endif; ?>
        <?php endif; ?>
        <?php /* Enquête bien enregistrée → on purge la sauvegarde locale de la
                 signature pour qu'elle ne « déborde » JAMAIS sur l'enquête suivante. */ ?>
        <script>try{ sessionStorage.removeItem('lfi_sign_backup'); }catch(e){}</script>
    </div>
    <?php
    return ob_get_clean();
}
