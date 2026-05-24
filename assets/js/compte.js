/**
 * Ouvre les liens marqués .lfi-popup dans une petite fenêtre,
 * pour que le visiteur ne quitte pas le site (l'onglet reste ouvert).
 * Sur mobile, le navigateur ignore la taille et ouvre un nouvel onglet
 * (le site reste tout de même ouvert) — comportement normal et inévitable.
 * Si le popup est bloqué, le lien s'ouvre normalement (fallback target="_blank").
 */
(function () {
    document.addEventListener('click', function (e) {
        var link = e.target.closest('a.lfi-popup');
        if (!link) return;

        var w = 480, h = 720;
        var baseLeft = window.screenLeft !== undefined ? window.screenLeft : (screen.left || 0);
        var baseTop = window.screenTop !== undefined ? window.screenTop : (screen.top || 0);
        var vw = window.innerWidth || document.documentElement.clientWidth || screen.width;
        var vh = window.innerHeight || document.documentElement.clientHeight || screen.height;
        var left = Math.max(0, baseLeft + (vw - w) / 2);
        var top = Math.max(0, baseTop + (vh - h) / 2);

        var popup = window.open(
            link.href,
            'lfi_popup',
            'scrollbars=yes,resizable=yes,width=' + w + ',height=' + h + ',left=' + left + ',top=' + top
        );

        if (popup) {
            e.preventDefault();
            popup.focus();
        }
    });
})();
