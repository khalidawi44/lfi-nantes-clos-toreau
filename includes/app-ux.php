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

      /* Un clic sur une image (ou un lien-image) DANS l'app → galerie. */
      document.addEventListener('click', function(e){
        var a = e.target.closest && e.target.closest('a');
        if (a && (isImgUrl(a.getAttribute('href')) || a.querySelector('img'))){
          var img = a.querySelector('img');
          var full = isImgUrl(a.getAttribute('href')) ? a.href : (img ? img.src : '');
          if (full){ e.preventDefault(); openLb(full); return; }
        }
        var t = e.target;
        if (t && t.tagName === 'IMG' && t.closest('.lfi-app-card, .lfi-app-list, #lfiRobotMsgs, .lfi-app-main, .lfi-piece')){
          if (t.naturalWidth === 0 || t.width > 24){ e.preventDefault(); openLb(t.src); }
        }
      }, true);
    })();
    </script>
    <?php
}
