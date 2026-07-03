/**
 * Enquête porte-à-porte — affichage conditionnel.
 * - Sous-bloc « depuis quand + récurrence » d'un problème : révélé quand on
 *   COCHE ce problème (case .lfi-prob-cb → sous-bloc #<data-sub>).
 * - Bloc « fiche d'adhésion + signature » : révélé quand « Oui, je veux être
 *   suivi·e » est choisi (revenir_ok = oui → #lfi-bloc-contact).
 * - Compat : ancien modèle « problemes_presence → #lfi-bloc-problemes ».
 */
(function () {
    function toggle(el, show) { if (el) el.hidden = !show; }

    function refresh() {
        /* Ancien modèle (si encore présent sur certaines pages). */
        var presence = document.querySelector('input[name="problemes_presence"]:checked');
        if (presence) toggle(document.getElementById('lfi-bloc-problemes'), presence.value === 'oui');

        /* Fiche d'adhésion + signature. */
        var revenir = document.querySelector('input[name="revenir_ok"]:checked');
        toggle(document.getElementById('lfi-bloc-contact'), !!(revenir && revenir.value === 'oui'));

        /* Sous-blocs par problème : visibles quand la case est cochée. */
        var cbs = document.querySelectorAll('.lfi-prob-cb');
        for (var i = 0; i < cbs.length; i++) {
            var cb = cbs[i];
            var subId = cb.getAttribute('data-sub');
            if (subId) toggle(document.getElementById(subId), cb.checked);
        }
    }

    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t) return;
        if (t.name === 'problemes_presence' || t.name === 'revenir_ok') { refresh(); return; }
        if (t.classList && t.classList.contains('lfi-prob-cb')) { refresh(); return; }
    });

    refresh();
})();
