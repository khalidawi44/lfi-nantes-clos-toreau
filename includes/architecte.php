<?php
/**
 * ROBOT ARCHITECTE — proactif.
 *
 * Il PREND LA PAROLE en premier : à chaque ouverture du tableau de bord, il
 * scanne TOUS les dossiers, consomme le rapport du robot PSY sur les dernières
 * réponses (institutions / bailleur), et t'INTERPELLE avec les coups à jouer —
 * sans que tu aies rien demandé. Objectif constant : amiable d'abord, brigade
 * en levier, réparation du préjudice ; le judiciaire via l'avocat.
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_architecte_can() {
    return current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
}

/** Scanne tous les dossiers du GA courant et renvoie les interpellations triées. */
function lfi_nct_architecte_scan() {
    global $wpdb;
    if (!function_exists('lfi_nct_dossier_owner_id')) return [];
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $owner = (int) lfi_nct_dossier_owner_id();
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE owner_user_id = %d ORDER BY updated_at DESC LIMIT 200", $owner)) ?: [];

    $today = wp_date('Y-m-d');
    $items = [];
    $did = 0;
    $push = function ($prio, $ico, $nom, $msg, $uid, $posture = '') use (&$items, &$did) {
        $items[] = ['prio' => $prio, 'ico' => $ico, 'nom' => $nom, 'msg' => $msg, 'uid' => (int) $uid, 'did' => (int) $did, 'posture' => $posture];
    };

    foreach ($rows as $r) {
        $did = (int) $r->id;
        $nom = trim($r->tenant_prenom . ' ' . $r->tenant_nom) ?: ('Dossier #' . $r->id);
        $uid = (int) $r->tenant_user_id;
        $demandes = function_exists('lfi_nct_strat_demandes') ? lfi_nct_strat_demandes($r) : [];
        $has_const = trim((string) ($r->constatations ?? '')) !== '';
        $has_med   = trim((string) ($r->certificat_medecin ?? '') . (string) ($r->certificat_pathologie ?? '')) !== '';
        $notes = json_decode($r->notes ?? '', true);
        $recu  = (is_array($notes) && !empty($notes['email_recu'])) ? $notes['email_recu'] : [];
        $analyse = (is_array($notes) && !empty($notes['analyse_nmh'])) ? trim((string) $notes['analyse_nmh']) : '';
        $lrar_trav = !empty($r->lrar_travaux_date);
        $lrar_relog= !empty($r->lrar_relogement_date);
        $schs = !empty($r->schs_date);
        $urg  = $r->nmh_urgence ?: 'bailleur';
        $deadline = ($lrar_trav && function_exists('lfi_nct_nmh_deadline')) ? lfi_nct_nmh_deadline($r->lrar_travaux_date, $urg) : '';
        $delai_passe = $deadline && ($today > $deadline);
        $want_travaux = in_array('travaux_urgents', $demandes, true);
        $want_indem   = in_array('indemnisation', $demandes, true) || in_array('reduction_loyer', $demandes, true);
        $age_days = $r->updated_at ? (int) floor((strtotime($today) - strtotime($r->updated_at)) / 86400) : 0;

        /* PRIORITÉ 1 — urgent. */
        if ($delai_passe && !$schs) {
            $push(1, '⏰', $nom, 'Délai NMH dépassé et pas de SCHS : saisis le service d\'hygiène (en gardant l\'amiable ouvert).', $uid);
        }
        if ($has_med && !$lrar_relog) {
            $push(1, '🏥', $nom, 'Certificat médical au dossier mais aucune demande de relogement envoyée : active ce levier, c\'est le plus fort.', $uid);
        }

        /* PRIORITÉ 2 — réponse à traiter (avec lecture PSY). */
        if ($recu && $analyse === '') {
            $last = end($recu);
            $posture = '';
            if (function_exists('lfi_nct_psy_analyse')) {
                $rep = lfi_nct_psy_analyse((string) ($last['corps'] ?? ''), 'institution');
                $posture = $rep['label'] . ' — ' . $rep['ton'];
            }
            $push(2, '📩', $nom, 'Une réponse reçue n\'est pas encore analysée. Traite-la et pousse un protocole amiable.', $uid, $posture);
        }
        if ($want_travaux && !$lrar_trav) {
            $push(2, '📨', $nom, 'Travaux demandés mais mise en demeure pas encore envoyée : propose aussi le levier brigade.', $uid);
        }

        /* PRIORITÉ 3 — consolidation. */
        if (!$has_const) {
            $push(3, '📝', $nom, 'Dossier peu documenté : constatations + photos datées (c\'est le capital de négociation).', $uid);
        }
        if ($want_indem && $analyse === '' && !$recu) {
            $push(3, '💶', $nom, 'Réparation du préjudice demandée mais rien d\'engagé : prépare le chiffrage (trouble de jouissance…).', $uid);
        }
        if ($age_days >= 21 && ($r->statut ?? '') === 'ouvert') {
            $push(3, '💤', $nom, 'Aucun mouvement depuis ' . $age_days . ' jours : relance NMH ou fais avancer d\'un cran.', $uid);
        }
    }

    usort($items, function ($a, $b) { return $a['prio'] <=> $b['prio']; });
    return $items;
}

/** Panneau proactif sur le tableau de bord — l'architecte t'interpelle. */
function lfi_nct_architecte_render_panel() {
    if (!lfi_nct_architecte_can()) return;
    $items = lfi_nct_architecte_scan();
    if (empty($items)) return; /* Rien d'urgent : on ne bavarde pas. */

    $top = array_slice($items, 0, 5);
    $color = [1 => '#c8102e', 2 => '#d39e00', 3 => '#0066a3'];
    echo '<div class="lfi-app-section"><div class="lfi-app-section-title">🧠 Le stratège t\'interpelle</div>';
    echo '<div style="background:#f3f0fb;border-radius:12px;padding:8px 10px">';
    foreach ($top as $it) {
        $c = $color[$it['prio']] ?? '#4b2e83';
        echo '<div style="border-left:4px solid ' . $c . ';background:#fff;border-radius:8px;padding:9px 12px;margin:6px 0">';
        echo '<div style="font-weight:700">' . $it['ico'] . ' ' . esc_html($it['nom']) . '</div>';
        echo '<div style="font-size:.92em;margin:2px 0 4px">' . esc_html($it['msg']) . '</div>';
        if (!empty($it['posture'])) echo '<div style="font-size:.82em;color:#4b2e83"><strong>Lecture psy :</strong> ' . esc_html($it['posture']) . '</div>';
        echo '<div style="margin-top:5px"><a class="btn-ghost" style="padding:4px 10px;font-size:.82em" href="' . esc_url(lfi_nct_app_url('strategie', ['id' => $it['did']])) . '">🧠 Stratégie</a> ';
        if ($it['uid']) echo '<a class="btn-ghost" style="padding:4px 10px;font-size:.82em" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => $it['uid']])) . '">📂 Dossier</a>';
        echo '</div></div>';
    }
    $n = count($items);
    if ($n > 5) echo '<div style="text-align:center;margin:4px 0"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('architecte')) . '">Voir les ' . (int) $n . ' interpellations →</a></div>';
    else echo '<div style="text-align:center;margin:4px 0"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('architecte')) . '">Ouvrir l\'architecte →</a></div>';
    echo '</div></div>';
}

/* ============================================================== *
 *  VUE : Architecte (toutes les interpellations)                 *
 * ============================================================== */
function lfi_nct_app_view_architecte() {
    if (!lfi_nct_architecte_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $items = lfi_nct_architecte_scan();
    lfi_nct_app_screen_open('🧠 Robot architecte', 'Ce que je te recommande, maintenant — dossier par dossier');
    echo '<div class="lfi-app-help">Je prends l\'initiative : voici les coups à jouer, du plus urgent au moins urgent. Le <strong>robot psy</strong> lit la posture des interlocuteurs et je l\'intègre.</div>';
    if (empty($items)) {
        echo '<div class="lfi-app-card" style="border:2px solid #186a3b"><div class="com">✅ Rien d\'urgent sur tes dossiers. Continue le suivi ; je te préviens dès qu\'un coup se présente.</div></div>';
        lfi_nct_app_screen_close();
        return;
    }
    $color = [1 => '#c8102e', 2 => '#d39e00', 3 => '#0066a3'];
    $lbl   = [1 => 'Urgent', 2 => 'À traiter', 3 => 'À consolider'];
    echo '<ul class="lfi-app-list">';
    foreach ($items as $it) {
        $c = $color[$it['prio']] ?? '#4b2e83';
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . $c . '">';
        echo '<div class="head"><div class="who">' . $it['ico'] . ' ' . esc_html($it['nom']) . '</div>';
        echo '<div class="badge" style="background:' . $c . ';color:#fff">' . esc_html($lbl[$it['prio']] ?? '') . '</div></div>';
        echo '<div class="com">' . esc_html($it['msg']) . '</div>';
        if (!empty($it['posture'])) echo '<div class="com" style="color:#4b2e83"><strong>Lecture psy :</strong> ' . esc_html($it['posture']) . '</div>';
        echo '<div class="row-actions" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">';
        echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('strategie', ['id' => $it['did']])) . '">🧠 Stratégie</a>';
        if ($it['uid']) echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => $it['uid']])) . '">📂 Dossier</a>';
        echo '</div></li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}
