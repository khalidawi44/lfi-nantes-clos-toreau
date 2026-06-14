/**
 * Enquête porte-à-porte — affichage conditionnel.
 * - Le bloc « problèmes en détail » n'apparaît que si « Y a-t-il des problèmes ? » = Oui.
 * - Le bloc « coordonnées » n'apparaît que si « Accepteriez-vous qu'on revienne ? » = Oui.
 */
(function () {
    function toggle(el, show) { if (el) el.hidden = !show; }

    function refresh() {
        var presence = document.querySelector('input[name="problemes_presence"]:checked');
        toggle(document.getElementById('lfi-bloc-problemes'), presence && presence.value === 'oui');
        var revenir = document.querySelector('input[name="revenir_ok"]:checked');
        toggle(document.getElementById('lfi-bloc-contact'), revenir && revenir.value === 'oui');
    }

    document.addEventListener('change', function (e) {
        if (!e.target || !e.target.name) return;
        if (e.target.name === 'problemes_presence' || e.target.name === 'revenir_ok') refresh();
    });

    refresh();
})();
