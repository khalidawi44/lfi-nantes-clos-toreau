<?php
/**
 * Masque les éléments promotionnels du thème AG Starter Association :
 *   - Pop-up « VOUS AIMEZ CE SITE ? Téléchargez le template »
 *   - Ligne pied de page « Fièrement créé par Alliance Groupe »
 *
 * Approche en double : CSS préemptif sur classes connues, + JS qui scanne
 * et masque tout élément contenant les textes promo (résiste aux changements
 * de classes côté thème).
 */
if (!defined('ABSPATH')) exit;

add_action('wp_head', 'lfi_nct_hide_theme_promo_css', 99);
function lfi_nct_hide_theme_promo_css() {
    ?>
    <style id="lfi-nct-hide-theme-promo">
    [class*="ag-starter-promo"],
    [class*="ag-promo"],
    [class*="ag-template-promo"],
    [class*="alliance-promo"],
    [class*="alliance-popup"],
    [class*="theme-promo"],
    [class*="template-promo"],
    [id*="ag-starter-modal"],
    [id*="alliance-promo"],
    [id*="ag-promo"] { display: none !important; visibility: hidden !important; pointer-events: none !important; }
    body.lfi-promo-purged { /* sécurité, neutralise certains effets de scroll-lock */
        overflow: auto !important;
    }
    </style>
    <?php
}

add_action('wp_footer', 'lfi_nct_hide_theme_promo_js', 99);
function lfi_nct_hide_theme_promo_js() {
    ?>
    <script>
    (function () {
        var promoNeedles = [
            'VOUS AIMEZ CE SITE',
            'Fièrement créé par Alliance Groupe',
            'TÉLÉCHARGER LE TEMPLATE GRATUIT',
            'AG Starter Association',
        ];

        function isPromoText(t) {
            t = (t || '').trim();
            if (t.length === 0 || t.length > 600) return false;
            for (var i = 0; i < promoNeedles.length; i++) {
                if (t.indexOf(promoNeedles[i]) !== -1) return true;
            }
            return false;
        }

        function hideMatching() {
            // 1. Pop-up plein écran : on cherche les conteneurs racines qui contiennent le texte
            var nodes = document.body ? document.body.querySelectorAll('div, section, aside, dialog') : [];
            for (var i = 0; i < nodes.length; i++) {
                var el = nodes[i];
                if (!el || el.children.length > 80) continue;
                var txt = el.textContent || '';
                if (isPromoText(txt)) {
                    var wrap = el.closest('.modal, .popup, [role="dialog"], [class*="overlay"], [class*="modal"], [class*="popup"], [class*="lightbox"]') || el;
                    wrap.style.display = 'none';
                    wrap.setAttribute('aria-hidden', 'true');
                    if (wrap.parentNode && wrap.dataset && !wrap.dataset.lfiPurged) {
                        wrap.dataset.lfiPurged = '1';
                        try { wrap.parentNode.removeChild(wrap); } catch (e) {}
                    }
                }
            }
            // 2. Pied de page : neutralise toute ligne mentionnant Alliance Groupe en tant que footer credit
            var footers = document.querySelectorAll('footer *, .site-footer *, .footer *');
            for (var j = 0; j < footers.length; j++) {
                var fEl = footers[j];
                if (fEl.children.length === 0 && isPromoText(fEl.textContent)) {
                    fEl.style.display = 'none';
                }
            }
            // 3. Neutralise un éventuel scroll-lock posé par le popup
            document.documentElement.style.overflow = '';
            document.body.style.overflow = '';
            document.body.classList.add('lfi-promo-purged');
        }

        hideMatching();
        document.addEventListener('DOMContentLoaded', hideMatching);
        setTimeout(hideMatching,  300);
        setTimeout(hideMatching, 1200);
        setTimeout(hideMatching, 3000);

        // Observe les ajouts dynamiques pendant 8 sec (le popup peut apparaître après scroll/délai)
        if (typeof MutationObserver !== 'undefined' && document.body) {
            var obs = new MutationObserver(function () { hideMatching(); });
            obs.observe(document.body, { childList: true, subtree: true });
            setTimeout(function () { try { obs.disconnect(); } catch (e) {} }, 8000);
        }
    })();
    </script>
    <?php
}
