<?php
/**
 * Victoires internes — la « coupe » du Groupe d'Action.
 *
 * Un dossier locataire = DEUX batailles :
 *   ⚡ le volet URGENCE (faire cesser le danger : travaux, relogement d'urgence,
 *      insalubrité) ;
 *   💶 le volet INDEMNISATION (réparer le préjudice — amiable puis juridique).
 *
 * Gagner une bataille = une COUPE. Deux coupes possibles par locataire (une par
 * bataille), mais UN SEUL locataire compté dans les statistiques « familles
 * aidées » (une coupe par bataille, une famille par personne).
 *
 * Chaque coupe :
 *   - marque le dossier (meta locataire, source de vérité des bandeaux) ;
 *   - crée la réussite ANONYME (brouillon, jamais de nom) — inchangé ;
 *   - se met en file de célébration pour les membres du GA CONCERNÉ : à
 *     l'ouverture de l'app, un pop-up « 🏆 On a gagné ! » les encourage.
 *
 * Détection AUTOMATIQUE : dès qu'un email de NMH est enregistré et qu'il ACTE
 * une de nos demandes (relogement accordé, travaux programmés…), la coupe du
 * volet urgence est posée automatiquement et le GA est prévenu.
 */
if (!defined('ABSPATH')) exit;

if (!defined('LFI_NCT_VICTOIRES_OPT')) define('LFI_NCT_VICTOIRES_OPT', 'lfi_nct_victoires');

/** Toutes les coupes enregistrées. */
function lfi_nct_victoires_all() {
    $v = get_option(LFI_NCT_VICTOIRES_OPT, []);
    return is_array($v) ? $v : [];
}
function lfi_nct_victoires_save($list) {
    update_option(LFI_NCT_VICTOIRES_OPT, array_values($list), false);
}

/** Libellés des deux batailles. */
function lfi_nct_batailles() {
    return [
        'urgence'       => ['ico' => '⚡', 'label' => 'Volet urgence',       'sub' => 'faire cesser le danger'],
        'indemnisation' => ['ico' => '💶', 'label' => 'Volet indemnisation', 'sub' => 'réparer le préjudice'],
    ];
}

/** A-t-on gagné cette bataille pour ce locataire ? (renvoie la date mysql ou ''). */
function lfi_nct_victoire_won($tenant_uid, $bataille) {
    return (string) get_user_meta((int) $tenant_uid, 'lfi_nct_' . $bataille . '_won', true);
}

/**
 * Enregistre une COUPE pour (locataire, bataille). Idempotent : une seule coupe
 * par couple. Marque le meta locataire, crée la réussite anonyme (brouillon),
 * ajoute une étape « gagnée » au parcours, et met la coupe en file de
 * célébration pour le GA concerné.
 *
 * @return int  id de la coupe (0 si invalide).
 */
function lfi_nct_victoire_record($tenant_uid, $bataille, $dossier_id = 0, $source = 'manuel') {
    global $wpdb;
    $tenant_uid = (int) $tenant_uid;
    $batailles  = lfi_nct_batailles();
    if (!$tenant_uid || !isset($batailles[$bataille])) return 0;

    /* Déjà une coupe pour cette bataille ? → rien à refaire (une coupe / bataille). */
    $list = lfi_nct_victoires_all();
    foreach ($list as $v) {
        if ((int) ($v['tenant_uid'] ?? 0) === $tenant_uid && ($v['bataille'] ?? '') === $bataille) {
            return (int) ($v['id'] ?? 0);
        }
    }

    /* meta locataire (source de vérité pour les bandeaux du dossier). */
    update_user_meta($tenant_uid, 'lfi_nct_' . $bataille . '_won', current_time('mysql'));

    /* Réussite anonyme (brouillon) — respecte la règle « jamais de nom ». */
    if (!$dossier_id) {
        $dt = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
        $dossier_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $dt WHERE tenant_user_id = %d ORDER BY id DESC LIMIT 1", $tenant_uid));
    }
    if ($dossier_id && function_exists('lfi_nct_reussite_auto_from_dossier')) {
        lfi_nct_reussite_auto_from_dossier($dossier_id);
    }
    /* Le volet indemnisation gagné clôt réellement le dossier. */
    if ($bataille === 'indemnisation' && $dossier_id) {
        $dt = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
        $wpdb->update($dt, ['statut' => 'abouti'], ['id' => $dossier_id]);
    }

    /* Étape « gagnée » dans le parcours (retour visible, cochée). */
    $steps = get_user_meta($tenant_uid, 'lfi_nct_suivi_steps', true);
    if (!is_array($steps)) $steps = [];
    $b = $batailles[$bataille];
    $txt = $b['ico'] . ' ' . $b['label'] . ' : bataille GAGNÉE 🏆';
    $already = false;
    foreach ($steps as $s) { if (($s['text'] ?? '') === $txt) { $already = true; break; } }
    if (!$already) {
        $steps[] = ['text' => $txt, 'who' => 'admin', 'done' => true, 'echeance' => '', 'created' => current_time('Y-m-d')];
        update_user_meta($tenant_uid, 'lfi_nct_suivi_steps', array_values($steps));
    }

    $rec = [
        'id'         => (int) round(microtime(true) * 1000),
        'tenant_uid' => $tenant_uid,
        'ga'         => function_exists('lfi_nct_user_ga') ? (string) lfi_nct_user_ga($tenant_uid) : '',
        'bataille'   => $bataille,
        'dossier_id' => (int) $dossier_id,
        'source'     => (string) $source,
        'date'       => current_time('mysql'),
    ];
    $list[] = $rec;
    lfi_nct_victoires_save($list);
    return $rec['id'];
}

/** Annule la coupe d'une bataille (fausse détection). */
function lfi_nct_victoire_annuler($tenant_uid, $bataille) {
    $tenant_uid = (int) $tenant_uid;
    delete_user_meta($tenant_uid, 'lfi_nct_' . $bataille . '_won');
    $list = lfi_nct_victoires_all();
    $list = array_values(array_filter($list, function ($v) use ($tenant_uid, $bataille) {
        return !((int) ($v['tenant_uid'] ?? 0) === $tenant_uid && ($v['bataille'] ?? '') === $bataille);
    }));
    lfi_nct_victoires_save($list);
}

/** Compteurs statistiques : coupes (batailles gagnées) + familles (distinctes). */
function lfi_nct_victoires_stats($ga = null) {
    $ga = ($ga === null && function_exists('lfi_nct_scope_ga_slug')) ? lfi_nct_scope_ga_slug() : (string) $ga;
    $coupes = 0; $familles = [];
    foreach (lfi_nct_victoires_all() as $v) {
        if ($ga !== '' && ($v['ga'] ?? '') !== $ga) continue;
        $coupes++;
        $familles[(int) ($v['tenant_uid'] ?? 0)] = 1;
    }
    return ['coupes' => $coupes, 'familles' => count($familles)];
}

/* ============================================================== *
 *  DÉTECTION AUTOMATIQUE — NMH acte une de nos demandes.          *
 * ============================================================== */
/**
 * Analyse un email reçu (objet + corps) : si NMH ACCEPTE clairement une de nos
 * demandes (relogement, travaux, intervention), pose la coupe du volet urgence.
 * Garde-fous : on écarte les tournures négatives (refus / pas favorable).
 *
 * @return int id de la coupe posée, ou 0 (rien détecté / déjà gagné).
 */
function lfi_nct_victoire_detect_from_email($tenant_uid, $objet, $corps, $dossier_id = 0) {
    $tenant_uid = (int) $tenant_uid;
    if (!$tenant_uid) return 0;
    if (lfi_nct_victoire_won($tenant_uid, 'urgence')) return 0; /* déjà gagné */

    $txt = ' ' . mb_strtolower($objet . ' ' . $corps, 'UTF-8') . ' ';
    /* retire les accents pour matcher « accordé/accorde ». */
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
        if ($ascii !== false) $txt = mb_strtolower($ascii, 'UTF-8');
    }

    /* Tournures de REFUS → on ne pose surtout pas de fausse coupe. */
    $negations = [
        'ne pouvons', 'ne peut', 'ne pourra', 'pas favorable', 'defavorable',
        'refus', 'rejet', 'impossible', 'ne donnons pas', 'ne sera pas',
        'aucune suite', 'n\'accord', 'ne pas donner suite', 'sans suite',
    ];
    foreach ($negations as $n) { if (strpos($txt, $n) !== false) return 0; }

    /* Tournures d'ACCEPTATION claires. */
    $accept = [
        'accord', 'accorde', 'favorable', 'nous procederons', 'nous allons proceder',
        'nous procedons', 'proposition de relogement', 'relogement accorde',
        'mutation accordee', 'intervention programmee', 'entreprise mandatee',
        'entreprise interviendra', 'travaux programmes', 'travaux seront realises',
        'sera realise', 'prise en charge', 'nous vous proposons un logement',
        'proposition de logement', 'sera relogee', 'sera reloge', 'nous relogeons',
        'bon d\'intervention', 'ordre de service', 'nous avons mandate',
    ];
    $hit = false;
    foreach ($accept as $a) { if (strpos($txt, $a) !== false) { $hit = true; break; } }
    if (!$hit) return 0;

    return lfi_nct_victoire_record($tenant_uid, 'urgence', (int) $dossier_id, 'auto-email');
}

/* ============================================================== *
 *  POP-UP « COUPE » — célébration à l'ouverture de l'app          *
 * ============================================================== */
/** La coupe la plus récente visible par l'utilisateur courant, non encore vue. */
function lfi_nct_victoire_pending() {
    if (!is_user_logged_in()) return null;
    $seen = (int) get_user_meta(get_current_user_id(), 'lfi_nct_victoire_seen_id', true);
    $best = null;
    foreach (lfi_nct_victoires_all() as $v) {
        $id = (int) ($v['id'] ?? 0);
        if ($id <= $seen) continue;
        /* Cloisonnement : seulement les coupes du GA de l'utilisateur. */
        if (function_exists('lfi_nct_uid_in_scope') && !lfi_nct_uid_in_scope((int) ($v['tenant_uid'] ?? 0))) continue;
        if ($best === null || $id > (int) $best['id']) $best = $v;
    }
    return $best;
}

add_action('wp_ajax_lfi_nct_victoire_seen', 'lfi_nct_victoire_seen_ajax');
function lfi_nct_victoire_seen_ajax() {
    check_ajax_referer('lfi_nct_victoire_seen', 'nonce');
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id && get_current_user_id()) {
        $cur = (int) get_user_meta(get_current_user_id(), 'lfi_nct_victoire_seen_id', true);
        if ($id > $cur) update_user_meta(get_current_user_id(), 'lfi_nct_victoire_seen_id', $id);
    }
    wp_send_json_success();
}

/**
 * Pop-up de célébration, affiché une fois par nouvelle coupe, aux membres du GA
 * concerné. ANONYME : jamais le nom du locataire.
 */
function lfi_nct_render_victoire_celebration() {
    $v = lfi_nct_victoire_pending();
    if (!$v) return;
    $bat = lfi_nct_batailles();
    $b   = $bat[$v['bataille']] ?? ['ico' => '🏆', 'label' => 'une bataille', 'sub' => ''];
    $id  = (int) $v['id'];
    $nonce = wp_create_nonce('lfi_nct_victoire_seen');
    $ajax  = admin_url('admin-ajax.php');
    ?>
    <div id="lfi-vic-ov" style="position:fixed;inset:0;background:rgba(0,0,0,.58);z-index:100002;display:flex;align-items:center;justify-content:center;padding:16px">
      <div style="background:#fff;color:#1a1a1a;border-radius:18px;max-width:440px;width:100%;padding:24px 20px;box-shadow:0 16px 50px rgba(0,0,0,.4);text-align:center;font-family:-apple-system,'Segoe UI',Roboto,sans-serif;position:relative;overflow:hidden">
        <div style="position:absolute;inset:0 0 auto;height:8px;background:linear-gradient(90deg,#c8102e,#8a6d1f,#186a3b,#4b2e83)"></div>
        <div style="font-size:56px;line-height:1;margin-top:8px">🏆</div>
        <div style="font-weight:900;font-size:1.35em;color:#186a3b;margin-top:4px">Une coupe pour le Groupe d'Action !</div>
        <div style="margin-top:10px;line-height:1.5;font-size:1.05em"><strong><?php echo esc_html($b['ico'] . ' ' . $b['label']); ?> — bataille gagnée</strong></div>
        <div style="margin-top:8px;color:#444">On a fait <?php echo esc_html($b['sub']); ?> pour une famille du quartier. C'est le collectif qui gagne — bravo à toutes et tous !</div>
        <button id="lfi-vic-ok" style="margin-top:16px;background:#186a3b;color:#fff;border:0;font-weight:800;padding:12px 26px;border-radius:12px;cursor:pointer;font-size:1em">🎊 On continue !</button>
      </div>
    </div>
    <script>
    (function(){
      var ov=document.getElementById('lfi-vic-ov'); if(!ov) return;
      function seen(){ try{ var fd=new FormData(); fd.append('action','lfi_nct_victoire_seen'); fd.append('nonce','<?php echo esc_js($nonce); ?>'); fd.append('id','<?php echo $id; ?>'); fetch('<?php echo esc_url($ajax); ?>',{method:'POST',body:fd,credentials:'same-origin'}).catch(function(){}); }catch(e){} }
      function close(){ seen(); ov.parentNode && ov.parentNode.removeChild(ov); }
      document.getElementById('lfi-vic-ok').addEventListener('click', close);
      ov.addEventListener('click', function(e){ if(e.target===ov) close(); });
    })();
    </script>
    <?php
}
