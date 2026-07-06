<?php
/**
 * CONFORT D'USAGE DE L'APP (mobile / PWA) — chargé sur toutes les pages de /app/.
 *
 *  1. Pull-to-refresh : tirer vers le bas en haut de page → recharge.
 *  2. Galerie photo (lightbox) : clic sur une photo → plein écran, ✕ pour
 *     quitter, flèches ◀▶ entre les photos, double-tap / pincer pour zoomer.
 *  3. Bouton flottant « ↑ Haut de page » (apparaît quand on descend) + « ⟳ »
 *     rafraîchir (pour ceux qui ne connaissent pas le geste).
 *  4. Mémoire des accordéons : chaque <details> retient s'il est ouvert/fermé.
 *
 * Tout est autonome (vanilla JS + CSS inline), aucune dépendance externe.
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_app_render_ux_boost() {
    static $done = false;
    if ($done) return;
    $done = true;
    ?>
    <style>
    /* --- Pull-to-refresh --- */
    #lfiPtr{position:fixed;top:0;left:0;right:0;display:flex;align-items:center;justify-content:center;
      height:0;overflow:hidden;z-index:99998;pointer-events:none;transition:height .12s ease;
      color:#c8102e;font-family:-apple-system,BlinkMacSystemFont,sans-serif;font-weight:800;font-size:.9em}
    #lfiPtr .lfiPtrIn{display:flex;align-items:center;gap:8px;background:#fff;border:2px solid #c8102e;
      border-radius:999px;padding:6px 14px;box-shadow:0 4px 14px rgba(0,0,0,.12);margin-top:8px}
    #lfiPtr .lfiPtrSpin{width:16px;height:16px;border:2.5px solid #f0b6c1;border-top-color:#c8102e;
      border-radius:50%;display:inline-block}
    #lfiPtr.spin .lfiPtrSpin{animation:lfiSpin .7s linear infinite}
    @keyframes lfiSpin{to{transform:rotate(360deg)}}
    /* --- Boutons flottants (haut de page + rafraîchir) --- */
    #lfiFabStack{position:fixed;right:16px;bottom:calc(env(safe-area-inset-bottom,0px) + 96px);
      z-index:99985;display:flex;flex-direction:column;gap:8px;opacity:0;transform:translateY(8px);
      transition:opacity .18s ease,transform .18s ease;pointer-events:none}
    #lfiFabStack.show{opacity:1;transform:none;pointer-events:auto}
    #lfiFabStack button{width:44px;height:44px;border-radius:50%;border:none;background:#fff;color:#0b3d91;
      font-size:1.25em;font-weight:800;box-shadow:0 4px 14px rgba(0,0,0,.2);cursor:pointer;line-height:1}
    #lfiFabStack button:active{transform:scale(.92)}
    /* --- Lightbox / galerie photo --- */
    #lfiLightbox{position:fixed;inset:0;z-index:100000;background:rgba(10,10,15,.93);
      display:none;align-items:center;justify-content:center;padding:12px;touch-action:none;overflow:hidden}
    #lfiLightbox.open{display:flex}
    #lfiLightbox img{max-width:98vw;max-height:94vh;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.5);
      object-fit:contain;transform-origin:center center;transition:transform .05s linear;will-change:transform}
    #lfiLightbox .lfiLbBtn{position:fixed;background:#fff;color:#111;border:none;border-radius:50%;
      width:46px;height:46px;font-size:1.4em;font-weight:800;line-height:1;cursor:pointer;
      box-shadow:0 4px 14px rgba(0,0,0,.4);z-index:2;display:flex;align-items:center;justify-content:center}
    #lfiLightbox .lfiLbClose{top:calc(env(safe-area-inset-top,0px) + 12px);right:14px}
    #lfiLightbox .lfiLbPrev{left:12px;top:50%;transform:translateY(-50%)}
    #lfiLightbox .lfiLbNext{right:12px;top:50%;transform:translateY(-50%)}
    #lfiLightbox .lfiLbCount{position:fixed;top:calc(env(safe-area-inset-top,0px) + 18px);left:50%;
      transform:translateX(-50%);color:#fff;font-family:-apple-system,sans-serif;font-weight:700;font-size:.9em;
      background:rgba(0,0,0,.4);border-radius:999px;padding:4px 12px;z-index:2}
    #lfiLightbox .lfiLbOpen{position:fixed;bottom:calc(env(safe-area-inset-bottom,0px) + 16px);left:50%;
      transform:translateX(-50%);background:#fff;color:#111;border:none;border-radius:999px;padding:9px 16px;
      font-weight:700;font-size:.9em;text-decoration:none;box-shadow:0 4px 14px rgba(0,0,0,.4);z-index:2}
    @media (max-width:520px){#lfiLightbox .lfiLbPrev,#lfiLightbox .lfiLbNext{width:40px;height:40px;font-size:1.2em}}
    </style>

    <div id="lfiPtr"><div class="lfiPtrIn"><span class="lfiPtrSpin"></span><span class="lfiPtrTxt">Relâche pour rafraîchir</span></div></div>

    <div id="lfiFabStack">
      <button type="button" id="lfiFabRefresh" aria-label="Rafraîchir la page" title="Rafraîchir">⟳</button>
      <button type="button" id="lfiFabTop" aria-label="Revenir en haut" title="Haut de page">↑</button>
    </div>

    <div id="lfiLightbox" role="dialog" aria-modal="true" aria-label="Photo">
      <div class="lfiLbCount"></div>
      <button type="button" class="lfiLbBtn lfiLbClose" aria-label="Fermer la photo">✕</button>
      <button type="button" class="lfiLbBtn lfiLbPrev" aria-label="Photo précédente">‹</button>
      <img alt="Photo agrandie" src="">
      <button type="button" class="lfiLbBtn lfiLbNext" aria-label="Photo suivante">›</button>
      <a class="lfiLbOpen" href="#" target="_blank" rel="noopener">⤢ Plein écran</a>
    </div>

    <div id="lfiDocView" role="dialog" aria-modal="true" aria-label="Document">
      <div class="lfiDocBar">
        <span class="lfiDocName">Document</span>
        <a class="lfiDocOpen" href="#" target="_blank" rel="noopener">⤢ Onglet</a>
        <button type="button" class="lfiDocClose" aria-label="Fermer le document">✕ Fermer</button>
      </div>
      <iframe class="lfiDocFrame" src="about:blank" title="Document"></iframe>
    </div>
    <style>
    #lfiDocView{position:fixed;inset:0;z-index:100001;background:#fff;display:none;flex-direction:column}
    #lfiDocView.open{display:flex}
    #lfiDocView .lfiDocBar{display:flex;align-items:center;gap:10px;padding:calc(env(safe-area-inset-top,0px) + 8px) 12px 8px;
      background:#c8102e;color:#fff}
    #lfiDocView .lfiDocName{flex:1;font-weight:800;font-size:.92em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    #lfiDocView .lfiDocOpen{color:#fff;text-decoration:none;font-weight:700;font-size:.82em;background:rgba(255,255,255,.2);padding:6px 10px;border-radius:999px}
    #lfiDocView .lfiDocClose{background:#fff;color:#c8102e;border:none;border-radius:999px;padding:7px 14px;font-weight:800;font-size:.9em;cursor:pointer}
    #lfiDocView .lfiDocFrame{flex:1;border:0;width:100%;background:#f4f4f6}
    </style>

    <script>
    (function(){
      if (window.__lfiUxBoot) return; window.__lfiUxBoot = 1;
      function scrollTop(){ return window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0; }

      /* ============ 1) PULL-TO-REFRESH ============ */
      var ptr = document.getElementById('lfiPtr');
      var startY = 0, pulling = false, dist = 0, THRESH = 70;
      document.addEventListener('touchstart', function(e){
        if (scrollTop() > 0 || document.getElementById('lfiLightbox').classList.contains('open')) { pulling = false; return; }
        startY = e.touches[0].clientY; pulling = true; dist = 0;
      }, {passive:true});
      document.addEventListener('touchmove', function(e){
        if (!pulling) return;
        dist = e.touches[0].clientY - startY;
        if (dist <= 0) { ptr.style.height = '0px'; return; }
        ptr.style.height = Math.min(dist * 0.5, 64) + 'px';
        ptr.querySelector('.lfiPtrTxt').textContent = (dist > THRESH) ? 'Relâche pour rafraîchir' : 'Tire pour rafraîchir';
      }, {passive:true});
      document.addEventListener('touchend', function(){
        if (!pulling) return; pulling = false;
        if (dist > THRESH) {
          ptr.classList.add('spin'); ptr.style.height = '46px';
          ptr.querySelector('.lfiPtrTxt').textContent = 'Rafraîchissement…';
          setTimeout(function(){ location.reload(); }, 200);
        } else { ptr.style.height = '0px'; }
        dist = 0;
      });

      /* ============ 2) BOUTONS FLOTTANTS ============ */
      var stack = document.getElementById('lfiFabStack');
      document.getElementById('lfiFabTop').addEventListener('click', function(){ window.scrollTo({top:0,behavior:'smooth'}); });
      document.getElementById('lfiFabRefresh').addEventListener('click', function(){ location.reload(); });
      function onScroll(){ if (scrollTop() > 350) stack.classList.add('show'); else stack.classList.remove('show'); }
      window.addEventListener('scroll', onScroll, {passive:true}); onScroll();

      /* ============ 3) MÉMOIRE DES ACCORDÉONS ============ */
      function accKey(d){
        var s = (d.querySelector('summary') && d.querySelector('summary').textContent || '').trim().slice(0,50);
        return 'lfiacc:' + location.pathname + '|' + s;
      }
      try {
        document.querySelectorAll('details > summary').forEach(function(sm){
          var d = sm.parentNode, k = accKey(d), v = localStorage.getItem(k);
          if (v === '1') d.open = true; else if (v === '0') d.open = false;
          d.addEventListener('toggle', function(){ try{ localStorage.setItem(k, d.open ? '1':'0'); }catch(e){} });
        });
      } catch(e){}

      /* ============ 4) GALERIE PHOTO (lightbox + zoom) ============ */
      var lb = document.getElementById('lfiLightbox');
      var lbImg = lb.querySelector('img');
      var lbOpen = lb.querySelector('.lfiLbOpen');
      var lbCount = lb.querySelector('.lfiLbCount');
      var gallery = [], gi = 0, scale = 1, tx = 0, ty = 0;
      function isImgUrl(u){ return /\.(jpe?g|png|gif|webp|heic|heif|bmp)(\?|#|$)/i.test(u||''); }
      function collect(){
        var set = [], seen = {};
        document.querySelectorAll('.lfi-app-card img, .lfi-app-list img, #lfiRobotMsgs img, .lfi-app-main img, .lfi-piece img').forEach(function(im){
          if (im.closest('#lfiLightbox')) return;
          if (!(im.naturalWidth === 0 || im.width > 24)) return;
          var a = im.closest('a');
          var full = (a && isImgUrl(a.getAttribute('href'))) ? a.href : im.src;
          if (seen[full]) return; seen[full] = 1;
          set.push({thumb: im.src, full: full});
        });
        return set;
      }
      function applyTransform(){ lbImg.style.transform = 'translate('+tx+'px,'+ty+'px) scale('+scale+')'; }
      function resetZoom(){ scale = 1; tx = 0; ty = 0; applyTransform(); }
      function show(i){
        if (!gallery.length) return;
        gi = (i + gallery.length) % gallery.length;
        resetZoom();
        lbImg.src = gallery[gi].thumb; lbOpen.href = gallery[gi].full;
        var full = new Image(); full.onload = function(){ if (lb.classList.contains('open')) lbImg.src = gallery[gi].full; }; full.src = gallery[gi].full;
        lbCount.textContent = gallery.length > 1 ? (gi+1)+' / '+gallery.length : '';
        var multi = gallery.length > 1;
        lb.querySelector('.lfiLbPrev').style.display = multi ? '' : 'none';
        lb.querySelector('.lfiLbNext').style.display = multi ? '' : 'none';
      }
      function openLb(full){
        gallery = collect();
        var idx = 0; for (var j=0;j<gallery.length;j++){ if (gallery[j].full === full){ idx = j; break; } }
        if (!gallery.length) gallery = [{thumb: full, full: full}];
        lb.classList.add('open'); document.body.style.overflow = 'hidden'; show(idx);
      }
      function closeLb(){ lb.classList.remove('open'); lbImg.src=''; document.body.style.overflow=''; resetZoom(); }
      lb.querySelector('.lfiLbClose').addEventListener('click', closeLb);
      lb.querySelector('.lfiLbPrev').addEventListener('click', function(e){ e.stopPropagation(); show(gi-1); });
      lb.querySelector('.lfiLbNext').addEventListener('click', function(e){ e.stopPropagation(); show(gi+1); });
      lb.addEventListener('click', function(e){ if (e.target === lb) closeLb(); });
      document.addEventListener('keydown', function(e){
        if (!lb.classList.contains('open')) return;
        if (e.key === 'Escape') closeLb();
        else if (e.key === 'ArrowLeft') show(gi-1);
        else if (e.key === 'ArrowRight') show(gi+1);
      });

      /* Double-tap → zoom ; pincer (2 doigts) → zoom ; glisser si zoomé → déplacer. */
      var lastTap = 0, pinchStart = 0, pinchScale = 1, panX = 0, panY = 0, panning = false;
      lbImg.addEventListener('touchstart', function(e){
        if (e.touches.length === 2){
          pinchStart = Math.hypot(e.touches[0].clientX-e.touches[1].clientX, e.touches[0].clientY-e.touches[1].clientY);
          pinchScale = scale;
        } else if (e.touches.length === 1 && scale > 1){
          panning = true; panX = e.touches[0].clientX - tx; panY = e.touches[0].clientY - ty;
        }
      }, {passive:true});
      lbImg.addEventListener('touchmove', function(e){
        if (e.touches.length === 2 && pinchStart){
          var d = Math.hypot(e.touches[0].clientX-e.touches[1].clientX, e.touches[0].clientY-e.touches[1].clientY);
          scale = Math.max(1, Math.min(6, pinchScale * (d / pinchStart))); applyTransform();
        } else if (panning && e.touches.length === 1){
          tx = e.touches[0].clientX - panX; ty = e.touches[0].clientY - panY; applyTransform();
        }
      }, {passive:true});
      lbImg.addEventListener('touchend', function(e){
        panning = false; pinchStart = 0;
        if (scale <= 1.02){ scale = 1; tx = 0; ty = 0; applyTransform(); }
        var now = Date.now();
        if (now - lastTap < 300 && e.touches.length === 0){
          scale = (scale > 1.1) ? 1 : 3.2; tx = 0; ty = 0; applyTransform();
        }
        lastTap = now;
      }, {passive:true});

      /* ============ 5) VISIONNEUSE DOCUMENT (PDF / .md / …) avec ✕ ============ */
      var dv = document.getElementById('lfiDocView');
      var dFrame = dv.querySelector('.lfiDocFrame');
      var dOpen = dv.querySelector('.lfiDocOpen');
      var dName = dv.querySelector('.lfiDocName');
      function openDoc(url, name){
        dName.textContent = name || 'Document';
        dOpen.href = url; dFrame.src = url;
        dv.classList.add('open'); document.body.style.overflow = 'hidden';
      }
      function closeDoc(){ dv.classList.remove('open'); dFrame.src = 'about:blank'; document.body.style.overflow = ''; }
      dv.querySelector('.lfiDocClose').addEventListener('click', closeDoc);
      document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && dv.classList.contains('open')) closeDoc(); });
      /* Un fichier NON-image (PDF, .md, .txt, doc…) OU tout fichier des uploads. */
      function isDocUrl(u){ return /\.(pdf|md|markdown|txt|csv|docx?|xlsx?|pptx?|odt|rtf)(\?|#|$)/i.test(u||''); }
      function isUploadUrl(u){ return /\/wp-content\/uploads\//i.test(u||''); }

      /* Un clic sur une image / un lien-fichier DANS l'app → galerie ou visionneuse. */
      document.addEventListener('click', function(e){
        var a = e.target.closest && e.target.closest('a');
        if (a){
          var href = a.getAttribute('href') || '';
          var inApp = a.closest('.lfi-app-card, .lfi-app-list, #lfiRobotMsgs, .lfi-app-main, .lfi-piece');
          /* image → galerie */
          if (isImgUrl(href) || a.querySelector('img')){
            var img = a.querySelector('img');
            var full = isImgUrl(href) ? a.href : (img ? img.src : '');
            if (full){ e.preventDefault(); openLb(full); return; }
          }
          /* document (pdf/md/…) ou fichier des uploads, dans l'app → visionneuse ✕ */
          if (inApp && (isDocUrl(href) || (isUploadUrl(href) && !isImgUrl(href)))){
            e.preventDefault();
            var nm = (a.textContent || '').trim() || href.split('/').pop();
            openDoc(a.href, nm); return;
          }
        }
        var t = e.target;
        if (t && t.tagName === 'IMG' && t.closest('.lfi-app-card, .lfi-app-list, #lfiRobotMsgs, .lfi-app-main, .lfi-piece')){
          if (t.naturalWidth === 0 || t.width > 24){ e.preventDefault(); openLb(t.src); }
        }
      }, true);
    })();
    </script>

    <!-- ⚡ RÉACTIVITÉ — le tap doit BOUGER en < 100 ms, surtout vieux Android -->
    <style>
    /* Supprime le délai de 300 ms au tap (double-tap zoom) sur vieux Chrome/Android. */
    a,button,input,select,textarea,label,summary,[role=button],[onclick],.lfi-app-card,.meta-chip,.btn-primary,.btn-ghost{touch-action:manipulation}
    html{-webkit-tap-highlight-color:rgba(200,16,46,.10)}
    /* Retour visuel INSTANTANÉ à l'appui (avant même que le serveur réponde). */
    a:active,button:active,.btn-primary:active,.btn-ghost:active,.lfi-app-card:active,.meta-chip:active,[role=button]:active,summary:active{
      opacity:.55;transform:scale(.975);transition:opacity .04s ease,transform .04s ease}
    .lfi-busy{opacity:.6 !important;pointer-events:none !important}
    /* Barre de progression en haut : apparaît dès le tap d'un lien/soumission. */
    #lfiTopbar{position:fixed;top:0;left:0;height:3px;width:0;z-index:100050;
      background:linear-gradient(90deg,#c8102e,#ff5a76);box-shadow:0 0 8px rgba(200,16,46,.5);
      opacity:0;transition:width .2s ease,opacity .2s ease;pointer-events:none}
    #lfiTopbar.on{opacity:1}
    </style>
    <script>
    (function(){
      if (window.__lfiPerf) return; window.__lfiPerf = true;

      /* --- Barre de progression : feedback immédiat sur toute navigation --- */
      var bar = document.createElement('div'); bar.id='lfiTopbar'; document.documentElement.appendChild(bar);
      var timer=null;
      function startBar(){
        bar.classList.add('on'); bar.style.width='8%';
        var w=8;
        clearInterval(timer);
        timer=setInterval(function(){ w += (90-w)*0.18; bar.style.width=w.toFixed(1)+'%'; if(w>=89){clearInterval(timer);} },120);
      }
      function stopBar(){ clearInterval(timer); bar.style.width='100%'; setTimeout(function(){bar.classList.remove('on');bar.style.width='0';},220); }
      window.addEventListener('pageshow', stopBar);
      window.addEventListener('beforeunload', startBar);

      /* --- Un lien interne cliqué : barre + état occupé tout de suite --- */
      function sameApp(href){ try{ var u=new URL(href, location.href); return u.origin===location.origin; }catch(e){ return false; } }
      document.addEventListener('click', function(e){
        var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a) return;
        var href=a.getAttribute('href')||'';
        if (a.target==='_blank' || href.charAt(0)==='#' || /^(tel:|mailto:|sms:|javascript:)/i.test(href)) return;
        if (!sameApp(a.href)) return;
        if (e.defaultPrevented) return;              /* lightbox / visionneuse ont déjà géré */
        /* Pop-up de vote : on referme tout de suite (optimiste) → sensation instantanée. */
        var ov = a.closest('#lfi-vote-ov'); if (ov){ ov.style.transition='opacity .12s'; ov.style.opacity='0'; }
        a.classList.add('lfi-busy'); startBar();
      }, false);

      /* --- Soumission de formulaire : barre + anti double-envoi --- */
      document.addEventListener('submit', function(e){
        if (e.defaultPrevented) return;              /* confirm() annulé / validation échouée */
        var f=e.target; if(!f||f.__lfiSent){ return; }
        f.__lfiSent=true; startBar();
        var b=f.querySelector('button[type=submit],button:not([type]),input[type=submit]');
        if(b){ b.classList.add('lfi-busy'); }
        /* filet : si rien ne se passe (validation), on réarme après 4 s */
        setTimeout(function(){ f.__lfiSent=false; if(b) b.classList.remove('lfi-busy'); }, 4000);
      }, false);

      /* --- Préchargement au TOUCHER (démarre la nav ~150 ms plus tôt) --- */
      var pref={}; var slow = (navigator.connection && (navigator.connection.saveData || /2g/.test(navigator.connection.effectiveType||'')));
      function prefetch(href){
        if (slow || pref[href]) return; pref[href]=1;
        var l=document.createElement('link'); l.rel='prefetch'; l.href=href; document.head.appendChild(l);
      }
      document.addEventListener('pointerdown', function(e){
        var a=e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if(!a) return; var href=a.getAttribute('href')||'';
        if (a.target==='_blank' || href.charAt(0)==='#' || /^(tel:|mailto:|sms:|javascript:)/i.test(href)) return;
        if (sameApp(a.href) && a.href.indexOf('admin-post.php')===-1) prefetch(a.href);
      }, {passive:true});
    })();
    </script>
    <?php
}
