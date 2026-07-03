<?php
/**
 * VOLET RÉUSSITES — articles ANONYMES de victoires obtenues pour les locataires.
 *
 * Dès qu'un dossier aboutit (problème résolu), on en tire un ARTICLE détaillé :
 * la situation, la méthode et les leviers actionnés, le résultat obtenu — le
 * tout STRICTEMENT ANONYME : jamais de nom de locataire, jamais de nom d'avocat,
 * pas d'adresse précise. Chaque article se termine par un appel « Contactez-nous ».
 *
 * Les articles publiés peuvent être affichés sur le site public via le
 * shortcode [lfi_nct_reussites] (outil de mobilisation : d'autres locataires
 * voient ce qui est possible et nous contactent).
 */
if (!defined('ABSPATH')) exit;

/** Liste des réussites (option). */
function lfi_nct_reussites() {
    $d = get_option('lfi_nct_reussites', []);
    return is_array($d) ? $d : [];
}
function lfi_nct_reussites_save($list) {
    update_option('lfi_nct_reussites', array_values($list), false);
}
function lfi_nct_reussite_get($id) {
    foreach (lfi_nct_reussites() as $r) if ((int) ($r['id'] ?? 0) === (int) $id) return $r;
    return null;
}

/**
 * AUTOMATISME : crée un BROUILLON de réussite à partir d'un dossier abouti,
 * une seule fois par dossier (on marque la fiche avec dossier_id). Non publié :
 * l'utilisateur relit et publie quand il veut. Renvoie l'id créé, ou 0.
 */
function lfi_nct_reussite_auto_from_dossier($dossier_id) {
    $dossier_id = (int) $dossier_id;
    if (!$dossier_id || !function_exists('lfi_nct_reussite_prefill_from_dossier')) return 0;
    $list = lfi_nct_reussites();
    foreach ($list as $r) if ((int) ($r['dossier_id'] ?? 0) === $dossier_id) return 0; /* déjà créée */
    $pref = lfi_nct_reussite_prefill_from_dossier($dossier_id);
    if (!is_array($pref)) return 0;
    $pref['id']         = (int) round(microtime(true) * 1000);
    $pref['dossier_id'] = $dossier_id;
    $pref['publie']     = false;   /* brouillon */
    $pref['auto']       = true;
    $pref['date']       = current_time('mysql');
    $list[] = $pref;
    lfi_nct_reussites_save($list);
    return $pref['id'];
}

/** Catalogue des leviers actionnables (cases à cocher). */
function lfi_nct_reussite_leviers() {
    return [
        'constat'        => 'Constat et documentation (photos datées, certificat médical)',
        'accompagnement' => "Accompagnement de l'association (rédaction des courriers, démarches, présence)",
        'courrier'       => 'Courrier formel au bailleur (mise en demeure / LRAR)',
        'schs'           => "Saisine du service d'hygiène de la Ville (SCHS)",
        'ars'            => "Saisine de l'ARS / santé publique",
        'prefecture'     => 'Signalement à la préfecture / pouvoirs publics',
        'dalo'           => 'Recours DALO (droit au logement opposable)',
        'avocat'         => 'Accompagnement juridique (avocat partenaire)',
        'refere'         => 'Procédure judiciaire en référé',
        'collectif'      => 'Mobilisation collective / médiatisation',
    ];
}

/** Catalogue des résultats obtenus. */
function lfi_nct_reussite_resultats() {
    return [
        'relogement'    => 'Relogement obtenu',
        'travaux'       => 'Travaux réalisés par le bailleur',
        'indemnisation' => 'Indemnisation / réduction de loyer',
        'insalubrite'   => "Arrêté ou mise en demeure obtenu",
        'autre'         => 'Autre résultat favorable',
    ];
}

/**
 * Anonymise un texte : retire les noms d'avocat (Me X / Maître X) et les noms
 * fournis (locataire). Garantit la règle « jamais de nom d'avocat ni de
 * locataire ». Les organisations (NMH, association…) ne sont pas touchées.
 */
function lfi_nct_reussite_anonymize($text, $names = []) {
    $t = (string) $text;
    /* Me / Maître + Nom (1 ou 2 mots) → « un avocat partenaire » */
    $t = preg_replace('/\b(Ma[iî]tre|M[eE])\.?\s+\p{Lu}[\p{L}\'\-]+(\s+\p{Lu}[\p{L}\'\-]+)?/u', 'un avocat partenaire', $t);
    /* Noms explicites (locataire), avec civilité éventuelle → « la personne
       accompagnée » (ex. « Mme Fadiga » → « la personne accompagnée »). */
    foreach ($names as $n) {
        $n = trim((string) $n);
        if (mb_strlen($n) >= 2) {
            $t = preg_replace('/(?:\b(?:Mme|Mlle|Mr|M|Monsieur|Madame|Mademoiselle)\.?\s+)?\b' . preg_quote($n, '/') . '\b/iu', 'la personne accompagnée', $t);
        }
    }
    /* Nettoie une éventuelle répétition « la personne accompagnée … accompagnée ». */
    $t = preg_replace('/(la personne accompagnée)(\s+la personne accompagnée)+/u', '$1', $t);
    return $t;
}

/** Détecte un éventuel reliquat de nom (civilité + Majuscule) pour alerter. */
function lfi_nct_reussite_flag_names($text) {
    return (bool) preg_match('/\b(Ma[iî]tre|M[eE]|Mme|Mlle|M\.|Monsieur|Madame)\.?\s+\p{Lu}[\p{L}\'\-]+/u', (string) $text);
}

/** Lien « Contactez-nous » (page contact du site, configurable). */
function lfi_nct_reussite_contact_url() {
    $u = get_option('lfi_nct_reussite_contact_url', '');
    if ($u) return $u;
    /* Repli sans 404 : page /contact/ si elle existe, sinon la page « Prendre
       RDV », sinon le formulaire d'enquête, sinon l'accueil. */
    $rdv = function_exists('lfi_nct_page_url') ? lfi_nct_page_url('rendez-vous', (function_exists('lfi_nct_survey_url') ? lfi_nct_survey_url() : home_url('/'))) : home_url('/');
    return function_exists('lfi_nct_page_url') ? lfi_nct_page_url('contact', $rdv) : home_url('/');
}

/* ============================================================== *
 *  VUE : liste des réussites (espace association)                 *
 * ============================================================== */
function lfi_nct_app_view_reussites() {
    if (!lfi_nct_app_guard_brigade()) return;

    /* Supprimer une réussite */
    if (!empty($_POST['lfi_reussite_del']) && check_admin_referer('lfi_reussite_del')) {
        $id = (int) ($_POST['reussite_id'] ?? 0);
        $list = array_values(array_filter(lfi_nct_reussites(), function ($r) use ($id) { return (int) ($r['id'] ?? 0) !== $id; }));
        lfi_nct_reussites_save($list);
        wp_safe_redirect(lfi_nct_app_url('reussites', ['del' => 1]));
        exit;
    }

    lfi_nct_app_screen_open('🏆 Réussites', "Nos victoires — anonymes — pour les locataires");

    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Réussite enregistrée.');
    if (!empty($_GET['del']))   lfi_nct_app_flash('🗑 Réussite supprimée.');

    echo '<div class="lfi-app-help" style="background:#e8f5ea;border-left:4px solid #186a3b">';
    echo '🔒 <strong>Anonyme par principe.</strong> Chaque fiche décrit la méthode et les leviers, le résultat obtenu — <strong>jamais</strong> le nom du locataire ni de l\'avocat, ni l\'adresse précise. Les fiches publiées peuvent être affichées sur le site (shortcode <code>[lfi_nct_reussites]</code>) pour montrer ce qui est possible.';
    echo '</div>';

    echo '<a class="btn-primary big" href="' . esc_url(lfi_nct_app_url('reussite-edit')) . '">➕ Nouvelle réussite</a>';

    $list = lfi_nct_reussites();
    /* Tri : plus récentes d'abord */
    usort($list, function ($a, $b) { return strcmp($b['date'] ?? '', $a['date'] ?? ''); });

    if (empty($list)) {
        echo '<div class="lfi-app-help" style="margin-top:14px">Aucune réussite pour l\'instant. Dès qu\'un dossier aboutit, crée la fiche ici (ou depuis le dossier abouti).</div>';
    } else {
        echo '<ul class="lfi-app-list" style="margin-top:14px">';
        foreach ($list as $r) {
            $resultats = lfi_nct_reussite_resultats();
            $res_lbl = $resultats[$r['resultat'] ?? ''] ?? '';
            echo '<li class="lfi-app-card" style="border-left:4px solid #186a3b">';
            echo '<div class="head"><div class="who">' . ($r['publie'] ?? false ? '🟢 Publiée' : '⚪ Brouillon') . '</div>';
            echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html($r['date'] ?? '') . '</div></div>';
            echo '<div class="com"><strong>' . esc_html($r['titre'] ?? 'Réussite') . '</strong></div>';
            if ($res_lbl) echo '<div class="meta"><span class="meta-chip">' . esc_html($res_lbl) . '</span>' . (!empty($r['delai']) ? '<span class="meta-chip">⏱ ' . esc_html($r['delai']) . '</span>' : '') . '</div>';
            echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">';
            echo '<a class="btn-ghost" style="padding:4px 10px;font-size:.85em" href="' . esc_url(lfi_nct_app_url('reussite-article', ['id' => $r['id']])) . '">📄 Article</a>';
            echo '<a class="btn-ghost" style="padding:4px 10px;font-size:.85em" href="' . esc_url(lfi_nct_app_url('reussite-edit', ['id' => $r['id']])) . '">✏️ Modifier</a>';
            echo '<form method="post" onsubmit="return confirm(\'Supprimer cette réussite ?\')" style="display:inline">';
            wp_nonce_field('lfi_reussite_del');
            echo '<input type="hidden" name="lfi_reussite_del" value="1"><input type="hidden" name="reussite_id" value="' . (int) $r['id'] . '">';
            echo '<button type="submit" class="btn-ghost" style="color:#c8102e;border-color:#c8102e;padding:4px 10px;font-size:.85em">🗑</button>';
            echo '</form>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE : créer / modifier une réussite                            *
 * ============================================================== */
function lfi_nct_app_view_reussite_edit() {
    if (!lfi_nct_app_guard_brigade()) return;

    $leviers   = lfi_nct_reussite_leviers();
    $resultats = lfi_nct_reussite_resultats();

    /* Enregistrement */
    if (!empty($_POST['lfi_reussite_save']) && check_admin_referer('lfi_reussite_save')) {
        $list = lfi_nct_reussites();
        $id   = (int) ($_POST['reussite_id'] ?? 0);

        $names = array_filter(array_map('trim', explode(',', (string) wp_unslash($_POST['anonymize_names'] ?? ''))));
        $sel_leviers = array_values(array_intersect(array_keys($leviers), (array) ($_POST['leviers'] ?? [])));

        $entry = [
            'id'        => $id ?: (int) (microtime(true) * 1000),
            'date'      => current_time('Y-m-d'),
            'titre'     => lfi_nct_reussite_anonymize(sanitize_text_field(wp_unslash($_POST['titre'] ?? '')), $names),
            'situation' => lfi_nct_reussite_anonymize(sanitize_textarea_field(wp_unslash($_POST['situation'] ?? '')), $names),
            'leviers'   => $sel_leviers,
            'leviers_detail' => lfi_nct_reussite_anonymize(sanitize_textarea_field(wp_unslash($_POST['leviers_detail'] ?? '')), $names),
            'resultat'  => sanitize_key($_POST['resultat'] ?? 'autre'),
            'resultat_detail' => lfi_nct_reussite_anonymize(sanitize_text_field(wp_unslash($_POST['resultat_detail'] ?? '')), $names),
            'delai'     => sanitize_text_field(wp_unslash($_POST['delai'] ?? '')),
            'quartier'  => sanitize_text_field(wp_unslash($_POST['quartier'] ?? '')),
            'publie'    => !empty($_POST['publie']),
        ];
        /* Conserve la date d'origine si édition */
        if ($id) {
            foreach ($list as $r) if ((int) ($r['id'] ?? 0) === $id && !empty($r['date'])) { $entry['date'] = $r['date']; break; }
        }
        /* Remplace ou ajoute */
        $found = false;
        foreach ($list as $k => $r) if ((int) ($r['id'] ?? 0) === $entry['id']) { $list[$k] = $entry; $found = true; break; }
        if (!$found) $list[] = $entry;
        lfi_nct_reussites_save($list);
        wp_safe_redirect(lfi_nct_app_url('reussite-article', ['id' => $entry['id'], 'saved' => 1]));
        exit;
    }

    /* Données existantes (édition) ou pré-remplissage depuis un dossier abouti */
    $id = (int) ($_GET['id'] ?? 0);
    $cur = $id ? lfi_nct_reussite_get($id) : null;
    if (!$cur) $cur = lfi_nct_reussite_prefill_from_dossier((int) ($_GET['from_dossier'] ?? 0));
    $cur = array_merge([
        'id' => 0, 'titre' => '', 'situation' => '', 'leviers' => [], 'leviers_detail' => '',
        'resultat' => 'relogement', 'resultat_detail' => '', 'delai' => '', 'quartier' => 'Clos Toreau (Nantes Sud)',
        'publie' => false, 'anonymize_names' => '',
    ], (array) $cur);

    lfi_nct_app_screen_open($cur['id'] ? '✏️ Modifier la réussite' : '➕ Nouvelle réussite', 'Anonyme — aucun nom de locataire ni d\'avocat');

    echo '<div class="lfi-app-help" style="background:#fff3cd;border-left:4px solid #d39e00">⚠️ <strong>N\'écris aucun nom</strong> (ni locataire, ni avocat). Les noms d\'avocat (« Me … ») et les noms listés ci-dessous sont retirés automatiquement à l\'enregistrement, mais reste vigilant.</div>';

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_reussite_save');
    echo '<input type="hidden" name="lfi_reussite_save" value="1">';
    echo '<input type="hidden" name="reussite_id" value="' . (int) $cur['id'] . '">';

    echo '<label>Titre<input type="text" name="titre" value="' . esc_attr($cur['titre']) . '" placeholder="Ex : Relogement obtenu pour une famille en logement insalubre" required></label>';

    echo '<label>La situation (anonyme)<textarea name="situation" rows="4" placeholder="Décris le problème sans aucun nom ni adresse : type de désordres (humidité, nuisibles…), impact (santé, enfants…), attitude du bailleur.">' . esc_textarea($cur['situation']) . '</textarea></label>';

    echo '<fieldset style="border:1px solid #ddd;border-radius:8px;padding:10px;margin:10px 0"><legend style="font-weight:700">Leviers actionnés</legend>';
    foreach ($leviers as $k => $lbl) {
        $checked = in_array($k, (array) $cur['leviers'], true) ? ' checked' : '';
        echo '<label style="display:flex;align-items:center;gap:8px;margin:4px 0;font-weight:400"><input type="checkbox" name="leviers[]" value="' . esc_attr($k) . '"' . $checked . '> ' . esc_html($lbl) . '</label>';
    }
    echo '</fieldset>';

    echo '<label>Détail de la méthode (optionnel)<textarea name="leviers_detail" rows="4" placeholder="Étapes clés, dans l\'ordre. Toujours sans nom.">' . esc_textarea($cur['leviers_detail']) . '</textarea></label>';

    echo '<label>Résultat obtenu<select name="resultat">';
    foreach ($resultats as $k => $lbl) echo '<option value="' . esc_attr($k) . '" ' . selected($cur['resultat'], $k, false) . '>' . esc_html($lbl) . '</option>';
    echo '</select></label>';
    echo '<label>Précision sur le résultat (optionnel)<input type="text" name="resultat_detail" value="' . esc_attr($cur['resultat_detail']) . '" placeholder="Ex : relogement dans un logement adapté + prise en charge des frais"></label>';
    echo '<label>Délai (optionnel)<input type="text" name="delai" value="' . esc_attr($cur['delai']) . '" placeholder="Ex : 2 mois"></label>';
    echo '<label>Quartier (sans adresse précise)<input type="text" name="quartier" value="' . esc_attr($cur['quartier']) . '" placeholder="Clos Toreau (Nantes Sud)"></label>';

    echo '<label>Noms à retirer automatiquement (séparés par des virgules) — sécurité<input type="text" name="anonymize_names" value="' . esc_attr($cur['anonymize_names'] ?? '') . '" placeholder="Ex : noms à ne jamais publier"></label>';

    echo '<label style="display:flex;align-items:center;gap:8px;margin:8px 0"><input type="checkbox" name="publie" value="1"' . ($cur['publie'] ? ' checked' : '') . '> 🟢 Publier (visible sur le site via le shortcode)</label>';

    echo '<button type="submit" class="btn-primary big">💾 Enregistrer et voir l\'article</button>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('reussites')) . '">← Retour</a>';
    echo '</form>';

    lfi_nct_render_voice_helper();
    lfi_nct_app_screen_close();
}

/**
 * Pré-remplit une réussite à partir d'un dossier ABOUTI (anonymisé).
 * Déduit les leviers des courriers envoyés ; n'inclut JAMAIS de nom.
 */
function lfi_nct_reussite_prefill_from_dossier($dossier_id) {
    if (!$dossier_id || !function_exists('lfi_nct_dossier_get')) return null;
    $d = lfi_nct_dossier_get((int) $dossier_id);
    if (!$d) return null;

    $leviers = ['constat', 'accompagnement'];
    $logs = json_decode($d->notes ?? '', true);
    $sent = (is_array($logs) && !empty($logs['email_log'])) ? $logs['email_log'] : [];
    foreach ($sent as $e) {
        $lk = $e['letter'] ?? '';
        if (in_array($lk, ['lrar_travaux', 'lrar_relogement'], true)) $leviers[] = 'courrier';
        if ($lk === 'schs') $leviers[] = 'schs';
        if ($lk === 'ars')  $leviers[] = 'ars';
    }
    if (!empty($d->certificat_medecin)) $leviers[] = 'constat';
    $leviers = array_values(array_unique($leviers));

    $names = array_filter([trim((string) $d->tenant_prenom), trim((string) $d->tenant_nom), trim($d->tenant_prenom . ' ' . $d->tenant_nom)]);

    return [
        'id'        => 0,
        'titre'     => 'Situation de mal-logement résolue au Clos Toreau',
        'situation' => lfi_nct_reussite_anonymize((string) ($d->constatations ?? ''), $names),
        'leviers'   => $leviers,
        'leviers_detail' => '',
        'resultat'  => 'relogement',
        'resultat_detail' => '',
        'delai'     => '',
        'quartier'  => 'Clos Toreau (Nantes Sud)',
        'publie'    => false,
        /* On N'EXPOSE PAS les noms réels dans le formulaire : la situation
           pré-remplie est déjà anonymisée ci-dessus. Le champ reste vide. */
        'anonymize_names' => '',
    ];
}

/* ============================================================== *
 *  VUE : article d'une réussite (lisible / imprimable / partage)  *
 * ============================================================== */
function lfi_nct_app_view_reussite_article() {
    if (!lfi_nct_app_guard_brigade()) return;
    $id = (int) ($_GET['id'] ?? 0);
    $r = lfi_nct_reussite_get($id);
    if (!$r) { lfi_nct_app_screen_open('Réussite introuvable'); echo '<div class="lfi-app-help">Cette réussite n\'existe pas.</div>'; lfi_nct_app_screen_close(); return; }

    lfi_nct_app_screen_open('🏆 ' . ($r['titre'] ?? 'Réussite'), $r['publie'] ? 'Publiée' : 'Brouillon (non publié)');
    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Réussite enregistrée.');

    if (lfi_nct_reussite_flag_names(($r['situation'] ?? '') . ' ' . ($r['leviers_detail'] ?? ''))) {
        echo '<div class="lfi-error">⚠️ Un nom (civilité + nom propre) semble encore présent. <a href="' . esc_url(lfi_nct_app_url('reussite-edit', ['id' => $id])) . '">Vérifie et corrige</a> avant publication.</div>';
    }

    echo lfi_nct_reussite_article_html($r);

    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('reussite-edit', ['id' => $id])) . '">✏️ Modifier</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('reussites')) . '">← Toutes les réussites</a>';
    echo '</div>';

    lfi_nct_app_screen_close();
}

/** Rendu HTML de l'article (réutilisé dans l'app et le shortcode public). */
function lfi_nct_reussite_article_html($r) {
    $leviers   = lfi_nct_reussite_leviers();
    $resultats = lfi_nct_reussite_resultats();
    $res_lbl = $resultats[$r['resultat'] ?? ''] ?? '';

    ob_start();
    echo '<article class="lfi-reussite" style="background:#fff;border-radius:12px;padding:18px 20px;color:#1a1a1a;line-height:1.6;max-width:760px;margin:0 auto">';
    echo '<div style="display:inline-block;background:#186a3b;color:#fff;font-weight:700;padding:3px 10px;border-radius:20px;font-size:.8em;margin-bottom:8px">✅ On a réussi</div>';
    echo '<h2 style="margin:6px 0 12px;color:#186a3b">' . esc_html($r['titre'] ?? 'Réussite') . '</h2>';

    if (!empty($r['situation'])) {
        echo '<h3 style="color:#c8102e;margin:16px 0 6px">La situation</h3>';
        echo '<p style="white-space:pre-wrap">' . esc_html($r['situation']) . '</p>';
    }

    if (!empty($r['leviers'])) {
        echo '<h3 style="color:#c8102e;margin:16px 0 6px">Ce que nous avons mis en place</h3>';
        echo '<ul>';
        foreach ((array) $r['leviers'] as $k) if (isset($leviers[$k])) echo '<li>' . esc_html($leviers[$k]) . '</li>';
        echo '</ul>';
    }
    if (!empty($r['leviers_detail'])) echo '<p style="white-space:pre-wrap">' . esc_html($r['leviers_detail']) . '</p>';

    echo '<h3 style="color:#c8102e;margin:16px 0 6px">Le résultat obtenu</h3>';
    echo '<p style="font-size:1.1em"><strong>' . esc_html($res_lbl ?: 'Résultat favorable') . '</strong>';
    if (!empty($r['resultat_detail'])) echo ' — ' . esc_html($r['resultat_detail']);
    if (!empty($r['delai'])) echo '<br><span style="color:#555">Délai : ' . esc_html($r['delai']) . '</span>';
    echo '</p>';

    if (!empty($r['quartier'])) echo '<p style="color:#777;font-size:.9em">Quartier : ' . esc_html($r['quartier']) . ' — récit anonyme.</p>';

    echo '<div style="margin-top:18px;padding:14px 16px;background:#e8f5ea;border-left:4px solid #186a3b;border-radius:8px">';
    echo '<strong>Vous vivez une situation similaire ?</strong><br>Vous n\'êtes pas seul·e. Nous accompagnons gratuitement les locataires dans leurs démarches. <a href="' . esc_url(lfi_nct_reussite_contact_url()) . '" style="color:#c8102e;font-weight:700">Contactez-nous</a>.';
    echo '</div>';
    echo '</article>';
    return ob_get_clean();
}

/* ============================================================== *
 *  SHORTCODE PUBLIC : [lfi_nct_reussites]                         *
 * ============================================================== */
add_shortcode('lfi_nct_reussites', 'lfi_nct_reussites_shortcode');
function lfi_nct_reussites_shortcode($atts) {
    $atts = shortcode_atts(['limite' => 0], $atts, 'lfi_nct_reussites');
    $list = array_values(array_filter(lfi_nct_reussites(), function ($r) { return !empty($r['publie']); }));
    usort($list, function ($a, $b) { return strcmp($b['date'] ?? '', $a['date'] ?? ''); });
    if ((int) $atts['limite'] > 0) $list = array_slice($list, 0, (int) $atts['limite']);

    if (empty($list)) return '<p>Nos réussites seront bientôt publiées ici.</p>';
    $out = '<div class="lfi-reussites-public" style="display:flex;flex-direction:column;gap:20px">';
    foreach ($list as $r) $out .= lfi_nct_reussite_article_html($r);
    $out .= '</div>';
    return $out;
}

/* ============================================================== *
 *  SHORTCODE PUBLIC : [lfi_nct_temoigner]                         *
 *  Grande porte d'entrée du site, tournée TERRAIN : « Témoigner   *
 *  de mon logement » → déclenche toute la mécanique (enquête →    *
 *  dossier → accompagnement). Une autre porte d'entrée chez les   *
 *  gens, en plus du porte-à-porte.                                *
 * ============================================================== */
add_shortcode('lfi_nct_temoigner', 'lfi_nct_temoigner_shortcode');
function lfi_nct_temoigner_shortcode($atts) {
    $survey = function_exists('lfi_nct_survey_url') ? lfi_nct_survey_url() : home_url('/');
    $nb_pub = count(array_filter(lfi_nct_reussites(), function ($r) { return !empty($r['publie']); }));
    ob_start(); ?>
    <div class="lfi-temoigner" style="max-width:760px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
      <div style="background:linear-gradient(135deg,#c8102e,#8a0b20);color:#fff;border-radius:16px;padding:26px 22px;text-align:center">
        <div style="font-size:1.7em;font-weight:900;line-height:1.15;margin-bottom:8px">Un problème dans ton logement&nbsp;HLM&nbsp;? Tu n'es pas seul·e.</div>
        <div style="font-size:1.05em;opacity:.95;margin-bottom:18px">Humidité, moisissures, nuisibles, chauffage, eau chaude, insécurité… On <strong>constate</strong>, on <strong>écrit au bailleur</strong>, on <strong>t'accompagne juridiquement</strong> — <strong>gratuitement</strong>. On l'a déjà fait pour tes voisin·es.</div>
        <a href="<?php echo esc_url($survey); ?>" style="display:inline-block;background:#fff;color:#c8102e;font-weight:900;font-size:1.15em;padding:15px 26px;border-radius:12px;text-decoration:none">📋 Témoigner de mon logement (5&nbsp;min)</a>
        <div style="margin-top:10px;font-size:.92em;opacity:.9">Anonyme si tu veux. Et tu peux demander qu'<strong>on te recontacte</strong>.</div>
      </div>

      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:18px">
        <div style="flex:1;min-width:200px;background:#f7f7f7;border-radius:12px;padding:16px">
          <div style="font-weight:800;color:#c8102e;margin-bottom:4px">🚪 Ce qu'on fait, sur le terrain</div>
          <div style="font-size:.95em;color:#333">Porte-à-porte dans le quartier, constats photos datés, courriers et mises en demeure au bailleur, saisine du service d'hygiène, accompagnement jusqu'au bout.</div>
        </div>
        <div style="flex:1;min-width:200px;background:#f7f7f7;border-radius:12px;padding:16px">
          <div style="font-weight:800;color:#186a3b;margin-bottom:4px">🏆 Des résultats réels</div>
          <div style="font-size:.95em;color:#333"><?php echo $nb_pub > 0 ? ('<strong>' . (int) $nb_pub . '</strong> réussite(s) déjà obtenue(s) et publiées ci-dessous.') : 'Relogements, travaux imposés, indemnisations — on documente chaque victoire.'; ?></div>
        </div>
      </div>

      <div style="text-align:center;margin-top:20px">
        <a href="<?php echo esc_url($survey); ?>" style="display:inline-block;background:#c8102e;color:#fff;font-weight:800;padding:13px 24px;border-radius:12px;text-decoration:none">✍️ Je témoigne maintenant</a>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
