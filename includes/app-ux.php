<?php
/**
 * CONFORT D'USAGE DE L'APP (mobile / PWA) — chargé sur toutes les pages de /app/.
 *
 *  1. « Tirer vers le bas pour rafraîchir » (pull-to-refresh) — comme un site
 *     web : en haut de page, on tire vers le bas → la page se recharge. Plus
 *     besoin de fermer/rouvrir l'app pour voir les mises à jour.
 *  2. Visionneuse photo (lightbox) avec un vrai bouton ✕ pour QUITTER — un clic
 *     sur une photo l'ouvre EN GRAND par-dessus la page ; ✕ ou clic à côté ferme.
 *     Fini le « j'ouvre une photo et je ne peux plus revenir ».
 *
 * Tout est autonome (vanilla JS + CSS inline), aucune dépendance externe.
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_app_render_ux_boost() {
    static $done = false;
    if ($done) return; /* une seule fois par page */
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
    /* --- Lightbox photo --- */
    #lfiLightbox{position:fixed;inset:0;z-index:100000;background:rgba(10,10,15,.92);
      display:none;align-items:center;justify-content:center;padding:20px}
    #lfiLightbox.open{display:flex}
    #lfiLightbox img{max-width:100%;max-height:88vh;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.5);object-fit:contain}
    #lfiLightbox .lfiLbClose{position:fixed;top:calc(env(safe-area-inset-top,0px) + 12px);right:14px;
      width:46px;height:46px;border-radius:50%;border:none;background:#fff;color:#111;font-size:1.5em;
      font-weight:800;line-height:1;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.4);z-index:2}
    #lfiLightbox .lfiLbOpen{position:fixed;bottom:calc(env(safe-area-inset-bottom,0px) + 16px);left:50%;
      transform:translateX(-50%);background:#fff;color:#111;border:none;border-radius:999px;padding:9px 16px;
      font-weight:700;font-size:.9em;text-decoration:none;box-shadow:0 4px 14px rgba(0,0,0,.4)}
    </style>

    <div id="lfiPtr"><div class="lfiPtrIn"><span class="lfiPtrSpin"></span><span class="lfiPtrTxt">Relâche pour rafraîchir</span></div></div>
    <div id="lfiLightbox" role="dialog" aria-modal="true" aria-label="Photo">
      <button type="button" class="lfiLbClose" aria-label="Fermer la photo">✕</button>
      <img alt="Photo agrandie" src="">
      <a class="lfiLbOpen" href="#" target="_blank" rel="noopener">⤢ Ouvrir en plein écran</a>
    </div>

    <script>
    (function(){
      if (window.__lfiUxBoot) return; window.__lfiUxBoot = 1;

      /* ============ 1) PULL-TO-REFRESH ============ */
      var ptr = document.getElementById('lfiPtr');
      var startY = 0, pulling = false, dist = 0, THRESH = 70;
      function scrollTop(){ return window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0; }
      document.addEventListener('touchstart', function(e){
        if (scrollTop() > 0) { pulling = false; return; }
        if (document.getElementById('lfiLightbox').classList.contains('open')) return;
        startY = e.touches[0].clientY; pulling = true; dist = 0;
      }, {passive:true});
      document.addEventListener('touchmove', function(e){
        if (!pulling) return;
        dist = e.touches[0].clientY - startY;
        if (dist <= 0) { ptr.style.height = '0px'; return; }
        var h = Math.min(dist * 0.5, 64);
        ptr.style.height = h + 'px';
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

      /* ============ 2) LIGHTBOX PHOTO ============ */
      var lb = document.getElementById('lfiLightbox');
      var lbImg = lb.querySelector('img');
      var lbOpen = lb.querySelector('.lfiLbOpen');
      function isImgUrl(u){ return /\.(jpe?g|png|gif|webp|heic|heif|bmp)(\?|#|$)/i.test(u||''); }
      function openLb(src, full){
        lbImg.src = src; lbOpen.href = full || src;
        lb.classList.add('open'); document.body.style.overflow = 'hidden';
      }
      function closeLb(){ lb.classList.remove('open'); lbImg.src=''; document.body.style.overflow=''; }
      lb.querySelector('.lfiLbClose').addEventListener('click', closeLb);
      lb.addEventListener('click', function(e){ if (e.target === lb) closeLb(); });
      document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeLb(); });

      /* Un clic sur une image (ou un lien vers une image) DANS le contenu de
         l'app → on ouvre la lightbox au lieu de quitter la page. */
      document.addEventListener('click', function(e){
        /* a) lien enveloppant une image / pointant vers une image */
        var a = e.target.closest && e.target.closest('a');
        if (a && (isImgUrl(a.getAttribute('href')) || a.querySelector('img'))) {
          var img = a.querySelector('img');
          var full = isImgUrl(a.getAttribute('href')) ? a.href : (img ? img.src : '');
          if (full) { e.preventDefault(); openLb(img ? img.src : full, full); return; }
        }
        /* b) image nue cliquable dans une carte de l'app */
        var t = e.target;
        if (t && t.tagName === 'IMG' && t.closest('.lfi-app-card, .lfi-app-list, #lfiRobotMsgs, .lfi-app-main, .lfi-piece')) {
          /* on évite les petites icônes/logos de la coquille */
          if (t.naturalWidth === 0 || (t.width > 24)) { e.preventDefault(); openLb(t.src, t.src); }
        }
      }, true);
    })();
    </script>
    <?php
}
