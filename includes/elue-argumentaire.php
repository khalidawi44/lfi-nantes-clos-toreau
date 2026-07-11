<?php
/**
 * ESPACE ARGUMENTAIRE ÉLUES (conseillères municipales) — Clos Toreau.
 *
 *  Un outil pour que les élu·es alliées (ex. Irina, Érika) puissent PARLER AUX
 *  HABITANT·ES du Clos Toreau en connaissance de cause :
 *   - des statistiques AGRÉGÉES et ANONYMISÉES des enquêtes (jamais de nom, ni
 *     d'adresse, ni de dossier individuel — cf. règle de cloisonnement) ;
 *   - la RÉPARTITION DU LOYER : ce que les locataires paient et à quoi ça sert
 *     chez Nantes Métropole Habitat ;
 *   - des points de langage prêts à l'emploi.
 *
 *  Accès par un QR / lien personnel (un par élue). La page ne contient AUCUNE
 *  donnée personnelle de locataire → pas de risque de fuite (elle peut donc
 *  rester sans connexion). RÈGLE RESPECTÉE : on ne partage jamais l'enquête
 *  terrain brute avec les élu·es, seulement des agrégats anonymes.
 */
if (!defined('ABSPATH')) exit;

/** Les deux élues connues (prénom seulement). Clé = slug du QR. */
function lfi_nct_elues_list() {
    return [
        'irina' => 'Irina',
        'erika' => 'Érika',
    ];
}

/** Répartition du loyer (chiffres du GA, éditables). Par défaut : 109 € pour
 *  l'emprunt bancaire de NMH, 79 € pour son fonctionnement. */
function lfi_nct_elue_rent() {
    return [
        'pret'         => (float) get_option('lfi_nct_rent_pret', 109),
        'fonctionnement' => (float) get_option('lfi_nct_rent_fonct', 79),
    ];
}

/** Statistiques AGRÉGÉES + ANONYMISÉES des enquêtes du Clos Toreau.
 *  Aucun nom, aucune adresse, aucun identifiant — que des compteurs. */
function lfi_nct_elue_stats() {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_responses';
    $slug = function_exists('lfi_nct_qr_default_ga') ? lfi_nct_qr_default_ga() : 'clos-toreau';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT data, contact_recontact FROM $t WHERE deleted_at IS NULL AND (ga = %s OR ga = '' OR ga IS NULL)",
        $slug
    )) ?: [];
    $labels = [
        'degats_eaux' => '💧 Dégâts des eaux / fuites', 'odeurs_egout' => '🤢 Odeurs d\'égout / eaux usées', 'humidite' => '🦠 Moisissures / humidité',
        'insectes' => '🐛 Nuisibles (punaises, cafards, rats)', 'chauffage' => '🔥 Chauffage / eau chaude',
        'electricite' => '⚡ Électricité / sécurité', 'ascenseur' => '🛗 Ascenseur',
        'parties_communes' => '🏢 Parties communes', 'bruit' => '🔊 Bruit',
        'securite' => '🚨 Sécurité', 'autre' => '🏠 Autre',
    ];
    $out = ['total' => 0, 'problemes' => 0, 'recontact' => 0, 'gravite_sum' => 0, 'gravite_n' => 0, 'types' => array_fill_keys(array_keys($labels), 0), 'labels' => $labels];
    foreach ($rows as $r) {
        $out['total']++;
        $data = json_decode((string) $r->data, true);
        if (!is_array($data)) continue;
        if (($data['problemes_presence'] ?? '') === 'oui') $out['problemes']++;
        foreach ((array) ($data['problemes_types'] ?? []) as $ty) if (isset($out['types'][$ty])) $out['types'][$ty]++;
        $g = (int) ($data['problemes_gravite'] ?? 0);
        if ($g > 0) { $out['gravite_sum'] += $g; $out['gravite_n']++; }
        if ((int) $r->contact_recontact === 1) $out['recontact']++;
    }
    $out['gravite_moyenne'] = $out['gravite_n'] ? round($out['gravite_sum'] / $out['gravite_n'], 1) : 0;
    arsort($out['types']);
    return $out;
}

/* ============================================================== *
 *  VUE PUBLIQUE : l'espace argumentaire d'une élue (cible du QR). *
 * ============================================================== */
function lfi_nct_app_view_argumentaire_elue() {
    $elues = lfi_nct_elues_list();
    $key = isset($_GET['e']) ? sanitize_key($_GET['e']) : '';
    $prenom = $elues[$key] ?? '';

    $st = lfi_nct_elue_stats();
    $rent = lfi_nct_elue_rent();

    lfi_nct_app_screen_open('🏛️ ' . ($prenom ? 'Espace de ' . $prenom : 'Espace élue') . ' — Clos Toreau', 'Pour parler aux habitant·es en connaissance de cause');

    if ($prenom) echo '<div class="lfi-app-help">Bonjour <strong>' . esc_html($prenom) . '</strong>. Voici de quoi <strong>échanger avec les habitant·es du Clos Toreau</strong> : l\'ampleur des problèmes (chiffres anonymes), la répartition de leur loyer, et des points de langage.</div>';

    /* ── Répartition du loyer (le cœur de l'argumentaire) ── */
    echo '<div class="lfi-app-card" style="border:2px solid #c8102e;margin-bottom:14px">';
    echo '<div class="head"><div class="who">💶 Où va le loyer chez Nantes Métropole Habitat</div></div>';
    echo '<div style="padding:4px 2px 2px">';
    $pret = $rent['pret']; $fonct = $rent['fonctionnement'];
    $max = max($pret, $fonct, 1);
    $bar = function ($label, $val, $color, $max) {
        $w = max(6, round($val * 100 / $max));
        return '<div style="margin:8px 0">'
            . '<div style="display:flex;justify-content:space-between;font-weight:700;font-size:.92em"><span>' . $label . '</span><span>' . number_format($val, 0, ',', ' ') . ' €</span></div>'
            . '<div style="background:#eee;border-radius:8px;height:16px;overflow:hidden;margin-top:3px"><div style="width:' . $w . '%;height:100%;background:' . $color . '"></div></div></div>';
    };
    echo $bar('🏦 Remboursement de l\'emprunt bancaire de NMH', $pret, '#c8102e', $max);
    echo $bar('⚙️ Fonctionnement de NMH', $fonct, '#0b3d91', $max);
    echo '<div class="lfi-app-help" style="margin-top:8px"><small>Sur le loyer d\'un·e locataire du Clos Toreau : <strong>' . number_format($pret, 0, ',', ' ') . ' €</strong> remboursent l\'emprunt bancaire de Nantes Métropole Habitat, <strong>' . number_format($fonct, 0, ',', ' ') . ' €</strong> servent à son fonctionnement. <em>Chiffres communiqués par le Groupe d\'Action — à vérifier / actualiser.</em></small></div>';
    echo '</div></div>';

    /* ── Statistiques ANONYMES des enquêtes ── */
    echo '<div class="lfi-app-card" style="margin-bottom:14px">';
    echo '<div class="head"><div class="who">📊 Ce que disent les habitant·es (anonyme)</div><div class="badge" style="background:#0b3d91;color:#fff">' . (int) $st['total'] . ' foyers</div></div>';
    if ($st['total'] === 0) {
        echo '<div class="lfi-app-empty">Les enquêtes du Clos Toreau arrivent — reviens bientôt.</div>';
    } else {
        $pct = function ($n, $d) { return $d ? round($n * 100 / $d) : 0; };
        echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin:6px 0 10px">';
        echo '<div style="flex:1;min-width:90px;background:#fff3f5;border-radius:10px;padding:10px;text-align:center"><div style="font-size:1.5em;font-weight:900;color:#c8102e">' . $pct($st['problemes'], $st['total']) . '%</div><div style="font-size:.8em;color:#555">signalent des problèmes</div></div>';
        if ($st['gravite_moyenne']) echo '<div style="flex:1;min-width:90px;background:#fff8e6;border-radius:10px;padding:10px;text-align:center"><div style="font-size:1.5em;font-weight:900;color:#bd8600">' . esc_html($st['gravite_moyenne']) . '/10</div><div style="font-size:.8em;color:#555">gravité moyenne</div></div>';
        echo '<div style="flex:1;min-width:90px;background:#eef7ee;border-radius:10px;padding:10px;text-align:center"><div style="font-size:1.5em;font-weight:900;color:#186a3b">' . (int) $st['recontact'] . '</div><div style="font-size:.8em;color:#555">veulent une action</div></div>';
        echo '</div>';
        echo '<div style="font-weight:700;color:#0b3d91;margin:4px 0 4px">Problèmes les plus cités</div>';
        $shown = 0;
        foreach ($st['types'] as $ty => $n) {
            if ($n <= 0 || $shown >= 6) continue; $shown++;
            $w = $pct($n, $st['total']);
            echo '<div style="margin:5px 0"><div style="display:flex;justify-content:space-between;font-size:.88em"><span>' . esc_html($st['labels'][$ty]) . '</span><span style="color:#888">' . (int) $n . '</span></div>';
            echo '<div style="background:#eee;border-radius:6px;height:10px;overflow:hidden;margin-top:2px"><div style="width:' . $w . '%;height:100%;background:#c8102e"></div></div></div>';
        }
        if (!$shown) echo '<div style="font-size:.9em;color:#777">Détail des problèmes à venir.</div>';
    }
    echo '<div class="lfi-app-help" style="margin-top:8px"><small>🔒 Chiffres <strong>agrégés et anonymes</strong> : aucun nom, aucune adresse, aucun dossier individuel n\'est partagé.</small></div>';
    echo '</div>';

    /* ── Points de langage ── */
    echo '<div class="lfi-app-card">';
    echo '<div class="head"><div class="who">🗣️ Points de langage</div></div>';
    echo '<ul style="margin:6px 0 2px;padding-left:20px;font-size:.94em;line-height:1.55;color:#26374f">';
    echo '<li>Les locataires <strong>paient leur loyer et leurs charges</strong> — et vivent pourtant avec moisissures, nuisibles et coupures d\'eau chaude.</li>';
    echo '<li>Sur ce loyer, <strong>' . number_format($pret, 0, ',', ' ') . ' € remboursent l\'emprunt bancaire</strong> de NMH et <strong>' . number_format($fonct, 0, ',', ' ') . ' € son fonctionnement</strong> : l\'argent existe, la rénovation doit suivre.</li>';
    echo '<li>Nous demandons un <strong>diagnostic complet</strong> et un <strong>calendrier public de travaux, précis et opposable</strong>.</li>';
    echo '<li>La Ville et la Métropole ne peuvent pas se réfugier derrière l\'État : des familles vivent <strong>aujourd\'hui</strong> dans l\'indignité.</li>';
    echo '</ul>';
    echo '<div style="margin-top:10px"><a href="' . esc_url(lfi_nct_app_url('communiques')) . '" style="display:block;text-align:center;background:#c8102e;color:#fff;font-weight:800;border-radius:10px;padding:10px;text-decoration:none">📰 Lire le communiqué de presse</a></div>';
    echo '</div>';

    echo '<div class="lfi-app-help" style="margin-top:12px"><small>Outil réservé aux élu·es alliées pour dialoguer avec les habitant·es. Merci de ne pas diffuser de données nominatives — il n\'y en a pas ici.</small></div>';
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE ADMIN : générer les 2 QR codes (Irina, Érika) + loyer.    *
 * ============================================================== */
function lfi_nct_app_view_qr_elues() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $can = current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    /* Édition des chiffres de loyer. */
    if (!empty($_POST['lfi_rent_save']) && check_admin_referer('lfi_rent')) {
        update_option('lfi_nct_rent_pret', max(0, (float) str_replace(',', '.', (string) ($_POST['pret'] ?? 109))), false);
        update_option('lfi_nct_rent_fonct', max(0, (float) str_replace(',', '.', (string) ($_POST['fonct'] ?? 79))), false);
        wp_safe_redirect(lfi_nct_app_url('qr-elues', ['ok' => 1])); exit;
    }

    $elues = lfi_nct_elues_list();
    $rent = lfi_nct_elue_rent();

    lfi_nct_app_screen_open('🔳 QR codes des élues', 'Un QR par conseillère · argumentaire Clos Toreau');
    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Répartition du loyer enregistrée.');
    echo '<div class="lfi-app-help">Chaque QR ouvre l\'<strong>espace argumentaire</strong> de l\'élue : statistiques anonymes des enquêtes + répartition du loyer + points de langage. À imprimer ou envoyer.</div>';

    /* Réglage des chiffres de loyer. */
    echo '<form method="post" class="lfi-app-form" style="background:#f6f8fb;border:1px solid #dfe6f0;border-radius:12px;padding:12px;margin-bottom:14px">' . wp_nonce_field('lfi_rent', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_rent_save" value="1">';
    echo '<div style="font-weight:800;color:#0b3d91;margin-bottom:6px">💶 Répartition du loyer (chiffres à vérifier)</div>';
    echo '<label>🏦 Emprunt bancaire NMH (€)<input type="number" step="1" min="0" name="pret" value="' . esc_attr($rent['pret']) . '"></label>';
    echo '<label>⚙️ Fonctionnement NMH (€)<input type="number" step="1" min="0" name="fonct" value="' . esc_attr($rent['fonctionnement']) . '"></label>';
    echo '<button type="submit" class="btn-primary" style="background:#0b3d91">💾 Enregistrer</button>';
    echo '</form>';

    $urls = [];
    foreach ($elues as $slug => $nom) $urls[$slug] = ['nom' => $nom, 'url' => lfi_nct_app_url('argumentaire-elue', ['e' => $slug])];

    echo '<div id="qre-wrap" style="display:flex;flex-direction:column;gap:14px">';
    foreach ($urls as $slug => $info) {
        echo '<div class="qre-card" style="text-align:center;background:#fff;border:1px solid #e6e6e6;border-radius:14px;padding:16px">';
        echo '<div style="font-weight:900;color:#c8102e;font-size:1.1em">🏛️ ' . esc_html($info['nom']) . '</div>';
        echo '<div style="color:#555;font-size:.86em;margin-bottom:10px">Argumentaire Clos Toreau — scanne avec l\'appareil photo</div>';
        echo '<canvas class="qre-canvas" data-url="' . esc_attr($info['url']) . '" style="width:230px;height:230px;max-width:75vw"></canvas>';
        echo '<div class="qre-fallback"></div>';
        echo '<div style="font-size:.78em;color:#888;margin-top:8px;word-break:break-all">' . esc_html($info['url']) . '</div>';
        echo '<div style="margin-top:8px"><a href="' . esc_url($info['url']) . '" target="_blank" rel="noopener" style="color:#0b3d91;font-weight:700;text-decoration:none;font-size:.9em">👁 Prévisualiser l\'espace →</a></div>';
        echo '</div>';
    }
    echo '</div>';

    echo '<button type="button" onclick="window.print()" class="btn-primary" style="background:#0b3d91;width:100%;margin-top:12px">🖨️ Imprimer les QR</button>';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script>
    (function render(){
        var cs = document.querySelectorAll('.qre-canvas');
        cs.forEach(function(c){
            var url = c.getAttribute('data-url');
            if (typeof QRious !== 'undefined') { try{ new QRious({element:c, value:url, size:460, level:'M'}); return; }catch(e){} }
            var fb = c.nextElementSibling; if (fb) fb.innerHTML = '<img alt="QR" style="width:230px;max-width:75vw" src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='+encodeURIComponent(url)+'">';
        });
    })();
    setTimeout(function(){ if(typeof QRious!=='undefined'){ document.querySelectorAll('.qre-canvas').forEach(function(c){ try{ new QRious({element:c, value:c.getAttribute('data-url'), size:460, level:'M'}); }catch(e){} }); } }, 600);
    </script>
    <style>@media print{.lfi-app-navbar,.lfi-app-other-shortcuts,.lfi-app-form,.btn-primary,.lfi-app-help{display:none!important}.qre-card{page-break-inside:avoid;border:none}}</style>
    <?php
    lfi_nct_app_screen_close();
}
