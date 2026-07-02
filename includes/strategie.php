<?php
/**
 * ROBOT STRATÈGE — conseil tactique par dossier locataire.
 *
 * Deux objectifs :
 *  1. Recommander la MEILLEURE TACTIQUE pour que le locataire obtienne ce
 *     qu'il veut (travaux, relogement…) ET surtout RÉPARATION DE SON
 *     PRÉJUDICE, en PRIORISANT l'amiable (le judiciaire est long, lourd en
 *     preuves et expertises).
 *  2. Intégrer la CONTRAINTE : la brigade peut réaliser certains travaux
 *     « dans la limite de ses capacités » — c'est à la fois un LEVIER amiable
 *     (accélérer, réduire le préjudice) et une contrainte (capacité limitée →
 *     prioriser les urgences santé/sécurité).
 *
 * Lecture seule, cloisonné au GA. Rappel de la ligne fixée avec l'avocat
 * (Me Gouache) : l'association s'arrête à la limite des ACTES JUDICIAIRES ;
 * le judiciaire relève de l'avocat.
 */
if (!defined('ABSPATH')) exit;

/** Décode le champ « demandes » (JSON array, ou codes séparés). */
function lfi_nct_strat_demandes($row) {
    $raw = (string) ($row->demandes ?? '');
    if ($raw === '') return [];
    $j = json_decode($raw, true);
    if (is_array($j)) return array_values(array_filter(array_map('strval', $j)));
    $codes = array_keys(lfi_nct_dossier_demandes_labels());
    $out = [];
    foreach ($codes as $c) if (strpos($raw, $c) !== false) $out[] = $c;
    return $out;
}

/* ============================================================== *
 *  VUE : Conseil stratégique                                     *
 * ============================================================== */
function lfi_nct_app_view_strategie() {
    $can = current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    global $wpdb;

    $id  = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $row = $id && function_exists('lfi_nct_dossier_get') ? lfi_nct_dossier_get($id) : null;

    /* Pas de dossier ciblé → sélecteur. */
    if (!$row) {
        lfi_nct_app_screen_open('🧠 Robot stratège', 'Choisis un dossier : je te propose la meilleure tactique');
        $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
        $owner = (int) lfi_nct_dossier_owner_id();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, tenant_prenom, tenant_nom, tenant_adresse FROM $t WHERE owner_user_id = %d ORDER BY updated_at DESC LIMIT 200", $owner)) ?: [];
        echo '<div class="lfi-app-help">Je raisonne <strong>amiable d\'abord</strong> (le judiciaire est long et lourd en preuves), avec la <strong>brigade travaux comme levier</strong>, et j\'oriente vers la <strong>réparation du préjudice</strong>.</div>';
        if (empty($rows)) {
            echo '<div class="lfi-app-empty">Aucun dossier locataire pour l\'instant.</div>';
        } else {
            echo '<ul class="lfi-app-list">';
            foreach ($rows as $r) {
                $nom = trim($r->tenant_prenom . ' ' . $r->tenant_nom) ?: ('Dossier #' . $r->id);
                echo '<li class="lfi-app-card"><div class="head"><div class="who">🗂 ' . esc_html($nom) . '</div></div>';
                if ($r->tenant_adresse) echo '<div class="meta"><span class="meta-chip">📍 ' . esc_html($r->tenant_adresse) . '</span></div>';
                echo '<div class="row-actions" style="margin-top:6px"><a class="btn-primary" href="' . esc_url(lfi_nct_app_url('strategie', ['id' => (int) $r->id])) . '">🧠 Stratégie</a></div></li>';
            }
            echo '</ul>';
        }
        lfi_nct_app_screen_close();
        return;
    }

    /* ---- Signaux du dossier ---- */
    $nom       = trim($row->tenant_prenom . ' ' . $row->tenant_nom) ?: ('Dossier #' . $row->id);
    $demandes  = lfi_nct_strat_demandes($row);
    $d_lbl     = lfi_nct_dossier_demandes_labels();
    $has_med   = trim((string) ($row->certificat_medecin ?? '') . (string) ($row->certificat_pathologie ?? '')) !== '';
    $has_const = trim((string) ($row->constatations ?? '')) !== '';
    $notes     = json_decode($row->notes ?? '', true);
    $nb_pieces = (is_array($notes) && !empty($notes['pieces'])) ? count($notes['pieces']) : 0;
    $recu      = (is_array($notes) && !empty($notes['email_recu'])) ? $notes['email_recu'] : [];
    $envoye    = (is_array($notes) && !empty($notes['email_log']))  ? $notes['email_log']  : [];
    $nmh_replied = !empty($recu);
    $lrar_trav = !empty($row->lrar_travaux_date);
    $lrar_relog= !empty($row->lrar_relogement_date);
    $schs      = !empty($row->schs_date);
    $ars       = !empty($row->ars_date);
    $urg       = $row->nmh_urgence ?: 'bailleur';
    $u_opt     = function_exists('lfi_nct_nmh_urgence_get') ? lfi_nct_nmh_urgence_get($urg) : ['court' => '', 'add' => ''];
    $deadline  = ($lrar_trav && function_exists('lfi_nct_nmh_deadline')) ? lfi_nct_nmh_deadline($row->lrar_travaux_date, $urg) : '';
    $today     = wp_date('Y-m-d');
    $delai_passe = $deadline && ($today > $deadline);

    $want_travaux = in_array('travaux_urgents', $demandes, true);
    $want_relog   = in_array('relogement_urgent', $demandes, true) || $has_med;
    $want_indem   = in_array('indemnisation', $demandes, true) || in_array('reduction_loyer', $demandes, true);

    lfi_nct_app_screen_open('🧠 Robot stratège — ' . $nom, 'Amiable d\'abord · brigade en levier · réparation du préjudice');

    /* Bandeau ligne directrice. */
    echo '<div class="lfi-app-help" style="border-left:4px solid #4b2e83;background:#f3f0fb">'
       . '<strong>Ma logique :</strong> obtenir le maximum <strong>à l\'amiable</strong> (rapide, sans expertise lourde), '
       . 'utiliser la <strong>brigade travaux comme levier</strong> (dans la limite de nos capacités), viser la '
       . '<strong>réparation du préjudice</strong>, et ne passer au <strong>judiciaire</strong> qu\'en dernier recours '
       . '(via l\'avocat — l\'association s\'arrête aux actes judiciaires).</div>';

    /* 1) Objectifs du locataire. */
    echo '<h3 style="margin:16px 0 6px;color:#c8102e">🎯 Ce que veut le locataire</h3>';
    if ($demandes) {
        echo '<div class="prob-chips">';
        foreach ($demandes as $c) echo '<span class="prob-chip">✅ ' . esc_html($d_lbl[$c] ?? $c) . '</span>';
        echo '</div>';
    } else {
        echo '<div class="lfi-app-help">Aucune demande enregistrée. <a href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => (int) $row->tenant_user_id])) . '">Précise-les dans le dossier</a> pour affiner la stratégie.</div>';
    }

    /* 2) Diagnostic express. */
    echo '<h3 style="margin:16px 0 6px;color:#c8102e">🩺 Diagnostic express</h3>';
    echo '<ul class="lfi-app-list">';
    $diag = [
        [$has_const, 'Constatations écrites', 'Documente pièce par pièce (base de toutes les lettres).'],
        [$nb_pieces > 0, $nb_pieces . ' pièce(s) jointe(s)', 'Ajoute photos datées, courriers, certificats.'],
        [$has_med, 'Certificat médical / vulnérabilité', 'Atout majeur pour le relogement et l\'urgence.'],
        [$lrar_trav, 'Mise en demeure travaux envoyée', 'Déclenche le délai légal (' . esc_html($u_opt['court'] ?? '') . ').'],
        [$nmh_replied, 'NMH a répondu', 'Analyse la réponse pour négocier.'],
    ];
    foreach ($diag as $dg) {
        $ok = (bool) $dg[0];
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . ($ok ? '#186a3b' : '#d39e00') . ';padding:9px 12px">';
        echo '<div class="head"><div class="who">' . ($ok ? '✅ ' : '⏳ ') . esc_html($dg[1]) . '</div></div>';
        if (!$ok) echo '<div class="com">' . esc_html($dg[2]) . '</div>';
        echo '</li>';
    }
    echo '</ul>';

    /* 3) Stratégie recommandée — amiable d'abord. */
    echo '<h3 style="margin:18px 0 6px;color:#c8102e">♟️ Stratégie recommandée — <span style="color:#186a3b">AMIABLE d\'abord</span></h3>';
    echo '<ol style="line-height:1.7;padding-left:18px">';
    if (!$has_const || $nb_pieces === 0) {
        echo '<li><strong>Consolider le dossier</strong> : constatations précises + photos datées' . ($want_relog ? ' + certificat médical' : '') . '. C\'est ton capital de négociation.</li>';
    }
    echo '<li><strong>Adhésion à l\'association</strong> (si pas encore faite) : elle légalise l\'accompagnement et l\'envoi de courriers en son nom.</li>';
    if ($want_travaux) {
        echo '<li><strong>Mise en demeure amiable NMH — travaux</strong>' . ($lrar_trav ? ' <em>(déjà envoyée le ' . esc_html(wp_date('j M', strtotime($row->lrar_travaux_date))) . ')</em>' : '') . ' : demande ferme + délai raisonnable, ton coopératif mais daté.</li>';
    }
    if ($want_relog) {
        echo '<li><strong>Demande de relogement d\'urgence</strong>' . ($has_med ? ' (appuyée sur le certificat médical)' : '') . ' : à porter en parallèle, c\'est souvent le levier le plus fort.</li>';
    }
    echo '<li style="background:#eafaf0;border-radius:6px;padding:6px 8px;margin:4px 0"><strong>🔧 Levier brigade (clé de l\'amiable)</strong> : propose à NMH que <strong>notre brigade réalise/accélère certains travaux dans la limite de nos capacités</strong>. Ça montre notre bonne foi, réduit le préjudice tout de suite, et crée un rapport de force <em>coopératif</em>. On priorise <strong>santé/sécurité</strong> (eau chaude, électricité, fuites) ; le reste attend nos disponibilités — c\'est la contrainte à assumer.</li>';
    echo '<li><strong>Négocier la réparation du préjudice à l\'amiable</strong> (voir ci-dessous) avant toute idée de tribunal.</li>';
    echo '</ol>';

    /* 4) Réparation du préjudice. */
    echo '<h3 style="margin:18px 0 6px;color:#c8102e">💶 Réparation du préjudice (à obtenir à l\'amiable)</h3>';
    echo '<div class="lfi-app-help">Chefs de préjudice à faire valoir dans la négociation (ordre de grandeur indicatif, à ajuster avec l\'avocat) :</div>';
    echo '<ul class="lfi-app-list">';
    $prej = [
        ['🏚️ Trouble de jouissance', 'Réduction / remise de loyer proportionnelle à la gêne et à la durée (souvent 10 à 30 %, plus si privation grave comme l\'eau chaude).'],
        ['💧 Privation d\'équipement essentiel', 'Eau chaude, chauffage : indemnisation spécifique pour la période concernée.'],
        ['🧾 Frais engagés', 'Chauffage d\'appoint, laverie, réparations avancées : remboursement sur justificatifs.'],
        ['🩺 Préjudice de santé / moral', 'Surtout si personne vulnérable + certificat médical : à documenter.'],
    ];
    foreach ($prej as $p) {
        echo '<li class="lfi-app-card" style="padding:9px 12px"><div class="head"><div class="who">' . $p[0] . '</div></div><div class="com">' . esc_html($p[1]) . '</div></li>';
    }
    echo '</ul>';
    echo '<div class="lfi-app-help"><small>Vise un <strong>protocole amiable écrit</strong> (travaux + calendrier + indemnisation/remise de loyer). Un accord signé vaut mieux qu\'un procès gagné dans 2 ans.</small></div>';

    /* 5) Escalade si l'amiable échoue. */
    echo '<h3 style="margin:18px 0 6px;color:#c8102e">🪜 Si l\'amiable échoue — escalade graduée</h3>';
    echo '<ol style="line-height:1.7;padding-left:18px">';
    $step = function ($done, $label, $desc) {
        echo '<li>' . ($done ? '✅ ' : '') . '<strong>' . $label . '</strong> — ' . esc_html($desc) . '</li>';
    };
    $step($schs, 'SCHS / service d\'hygiène', $delai_passe && !$schs ? 'Délai dépassé : à saisir maintenant.' : 'Enquête sur place + rapport ; peut mener à un arrêté.');
    $step($ars, 'ARS', 'Si dimension sanitaire (santé publique).');
    echo '<li><strong>Préfecture — Déléguée du Préfet</strong> (Mme Laurine) : appui institutionnel, surtout sur données agrégées du quartier.</li>';
    echo '<li><strong>Commission départementale de conciliation</strong> : gratuite, amiable, avant tout juge.</li>';
    echo '<li style="background:#fff3cd;border-radius:6px;padding:6px 8px"><strong>⚖️ Judiciaire = dernier recours, via l\'avocat (Me Gouache)</strong>. Rappel : <strong>l\'association s\'arrête aux actes judiciaires</strong> — on prépare et on transmet, l\'avocat agit.</li>';
    echo '</ol>';

    /* 5-0) Ligne de conduite (posture non négociable). */
    echo '<h3 style="margin:18px 0 6px;color:#c8102e">🧭 Ligne de conduite (non négociable)</h3>';
    echo '<ul class="lfi-app-list">';
    echo '<li class="lfi-app-card" style="border-left:4px solid #c8102e"><div class="head"><div class="who">🛡️ Interlocuteur unique</div></div>'
       . '<div class="com">Le locataire t\'a demandé de l\'aide : <strong>c\'est TOI l\'interlocuteur</strong>, pas lui/elle. On ne transmet <strong>jamais</strong> ses coordonnées. Toute prise de contact et tout accès au logement se font <strong>par ton intermédiaire et en ta présence</strong> — pour ce dossier et pour tous les suivants.</div></li>';
    echo '<li class="lfi-app-card" style="border-left:4px solid #c8102e"><div class="head"><div class="who">⚖️ On EXIGE, on ne demande pas</div></div>'
       . '<div class="com">Face aux institutions (SCHS, ARS, bailleur…), le ton est l\'<strong>exigence</strong>, pas la requête. Leur mission est d\'intervenir/constater sur signalement : ils <strong>n\'ont pas le choix</strong> de venir ou non. En tant que <strong>président d\'association</strong>, tu as <strong>constaté un dysfonctionnement</strong> — tu exiges qu\'ils viennent le constater à leur tour, et tu réclames une <strong>date</strong>.</div></li>';
    echo '</ul>';

    /* 5bis) Alliés & rapports de force. */
    echo '<h3 style="margin:18px 0 6px;color:#c8102e">🤝 Alliés & rapports de force</h3>';
    echo '<div class="lfi-app-help">Chercher des appuis — mais chacun à sa juste place. On garde toujours la main.</div>';
    echo '<ul class="lfi-app-list">';
    echo '<li class="lfi-app-card" style="border-left:4px solid #d39e00"><div class="head"><div class="who">🏛️ Préfecture (Déléguée du Préfet)</div></div>'
       . '<div class="com"><strong>Allié de circonstance, pas un·e ami·e.</strong> Levier de pression utile, mais <strong>méfiance</strong> : on ne livre que des données <strong>maîtrisées et anonymes</strong>, on ne dépend pas d\'elle, on garde l\'initiative. Utile pour peser sur NMH, pas pour arbitrer à notre place.</div></li>';
    echo '<li class="lfi-app-card" style="border-left:4px solid #186a3b"><div class="head"><div class="who">🧑‍🤝‍🧑 Les autres locataires</div></div>'
       . '<div class="com">La <strong>force principale</strong> : un signalement collectif par immeuble pèse bien plus qu\'un cas isolé. À mobiliser en priorité.</div></li>';
    echo '<li class="lfi-app-card" style="padding:9px 12px"><div class="com">Autres appuis : <strong>ADIL 44</strong> (conseil logement), <strong>commission de conciliation</strong>, <strong>DALO</strong>, <strong>CAF/APL</strong>, <strong>élus locaux</strong>, et la <strong>presse</strong> comme levier de dernier ressort.</div></li>';
    echo '<li class="lfi-app-card" style="border-left:4px solid #c8102e"><div class="head"><div class="who">🏢 NMH (bailleur)</div></div>'
       . '<div class="com">Adversaire à <strong>convertir en partenaire par l\'amiable</strong> — le levier brigade sert exactement à ça.</div></li>';
    echo '</ul>';

    /* 5ter) Identité selon le contexte (qui signe quoi). */
    echo '<h3 style="margin:18px 0 6px;color:#c8102e">🎭 Qui parle, selon le contexte</h3>';
    echo '<ul class="lfi-app-list">';
    echo '<li class="lfi-app-card" style="padding:9px 12px"><div class="head"><div class="who">🧑‍🤝‍🧑 Avec les locataires</div></div><div class="com"><strong>La France Insoumise</strong> ET <strong>l\'association</strong> (Union des Quartiers Libres) — les deux, à parts égales.</div></li>';
    echo '<li class="lfi-app-card" style="padding:9px 12px"><div class="head"><div class="who">🏛️ Avec NMH / institutions (négociation, pression)</div></div><div class="com">Au nom du <strong>Groupe d\'Action LFI Nantes Sud</strong> et de l\'<strong>association</strong> — c\'est l\'adhésion qui légalise l\'accompagnement.</div></li>';
    echo '<li class="lfi-app-card" style="padding:9px 12px"><div class="head"><div class="who">🔧 Travaux · devis · factures</div></div><div class="com"><strong>Fabrice Doucet</strong> (à titre personnel, auto-entrepreneur, mentions légales) — jamais l\'association ni LFI sur une facture.</div></li>';
    echo '</ul>';

    /* 6) Prochaine action concrète. */
    echo '<h3 style="margin:18px 0 6px;color:#c8102e">👉 Ta prochaine action, maintenant</h3>';
    if (!$has_const) {
        $next = 'Compléter les <strong>constatations</strong> et ajouter des <strong>photos datées</strong> au dossier.';
        $link = lfi_nct_app_url('dossier', ['uid' => (int) $row->tenant_user_id]);
    } elseif ($want_travaux && !$lrar_trav) {
        $next = 'Envoyer la <strong>mise en demeure amiable travaux</strong> à NMH (et proposer le levier brigade).';
        $link = lfi_nct_app_url('dossier', ['uid' => (int) $row->tenant_user_id]);
    } elseif ($lrar_trav && $delai_passe && !$schs) {
        $next = 'Délai NMH dépassé : <strong>saisir le SCHS</strong> tout en gardant la porte de l\'amiable ouverte.';
        $link = lfi_nct_app_url('dossier', ['uid' => (int) $row->tenant_user_id]);
    } elseif ($nmh_replied) {
        $next = 'NMH a répondu : <strong>analyser la réponse</strong> et pousser un <strong>protocole amiable</strong> (travaux + réparation du préjudice).';
        $link = lfi_nct_app_url('dossier', ['uid' => (int) $row->tenant_user_id]);
    } else {
        $next = 'Proposer le <strong>levier brigade</strong> à NMH et ouvrir la négociation sur la <strong>réparation du préjudice</strong>.';
        $link = lfi_nct_app_url('dossier', ['uid' => (int) $row->tenant_user_id]);
    }
    echo '<div class="lfi-app-card" style="border:2px solid #186a3b"><div class="com" style="font-size:1.02em">' . $next . '</div>';
    echo '<div class="row-actions" style="margin-top:8px"><a class="btn-primary" href="' . esc_url($link) . '">📂 Ouvrir le dossier</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-avocat', ['uid' => (int) $row->tenant_user_id])) . '" target="_blank">⚖️ Note avocat</a></div></div>';

    echo '<div class="lfi-app-help" style="margin-top:10px"><small>Conseil d\'orientation interne, pas un avis juridique. La validation du cadre revient à l\'avocat.</small></div>';
    lfi_nct_app_screen_close();
}
