<?php
/**
 * VOLET PRÉFECTURE — partage ANONYME des données du porte-à-porte.
 *
 * Objectif : pouvoir transmettre à la préfecture (interlocutrice de quartier)
 * un état des PROBLÉMATIQUES PAR BÂTIMENT, sans jamais révéler l'identité des
 * locataires. On n'expose QUE des compteurs agrégés par adresse d'immeuble.
 *
 * RÈGLE D'ANONYMAT (non négociable) : ce module ne lit et n'affiche JAMAIS :
 *   - le numéro de porte / d'appartement / d'étage (colonne `etage`) ;
 *   - le nom, prénom, téléphone, email des locataires (colonnes `contact_*`) ;
 *   - les citations verbatim ni les champs texte libres (ré-identifiables) ;
 *   - l'identité du militant enquêteur, ni les coordonnées GPS exactes.
 * Seuls sortent : l'adresse de l'IMMEUBLE et des COMPTEURS de problèmes.
 */
if (!defined('ABSPATH')) exit;

/** Contact préfecture (enregistré en option, éditable depuis le volet). */
function lfi_nct_prefecture_contact() {
    $d = get_option('lfi_nct_prefecture_contact', []);
    if (!is_array($d)) $d = [];
    return array_merge([
        'organisme' => 'Préfecture de la Loire-Atlantique',
        'nom'       => '',
        'fonction'  => '',
        'email'     => '',
        /* 2ᵉ interlocutrice : Gwenaëlle Gourdien */
        'nom2'      => 'Gwenaëlle Gourdien',
        'fonction2' => '',
        'email2'    => '',
    ], $d);
}

/** Liste des emails préfecture renseignés (déléguée + Gwenaëlle). */
function lfi_nct_prefecture_emails() {
    $c = lfi_nct_prefecture_contact();
    $out = [];
    foreach ([$c['email'], $c['email2']] as $e) {
        $e = trim((string) $e);
        if ($e !== '' && strpos($e, '@') !== false) $out[] = $e;
    }
    return $out;
}

/** Journal des correspondances avec la préfecture (envoyées + reçues). */
function lfi_nct_prefecture_corr() {
    $d = get_option('lfi_nct_prefecture_corr', []);
    if (!is_array($d)) $d = [];
    return ['sent' => $d['sent'] ?? [], 'recu' => $d['recu'] ?? []];
}
function lfi_nct_prefecture_corr_save($corr) {
    update_option('lfi_nct_prefecture_corr', [
        'sent' => array_values($corr['sent'] ?? []),
        'recu' => array_values($corr['recu'] ?? []),
    ], false);
}

/** Libellés lisibles des catégories de problèmes (ordre d'affichage). */
function lfi_nct_prefecture_type_labels() {
    return [
        'humidite'         => 'Humidité / moisissures',
        'insectes'         => 'Nuisibles (cafards, punaises, rongeurs)',
        'chauffage'        => 'Chauffage défaillant',
        'degats_eaux'      => 'Dégâts des eaux / fuites',
        'electricite'      => 'Électricité',
        'ascenseur'        => 'Ascenseur',
        'parties_communes' => 'Parties communes',
        'bruit'            => 'Bruit / isolation',
        'securite'         => 'Sécurité',
        'autre'            => 'Autre',
    ];
}

/**
 * Nettoie une adresse pour ne garder que l'IMMEUBLE. Retire par sécurité toute
 * mention d'appartement / porte / étage / bâtiment qu'un·e enquêteur·rice aurait
 * pu saisir par erreur dans le champ adresse (le n° de porte vit normalement
 * dans la colonne `etage`, que l'on ne lit jamais ici).
 */
function lfi_nct_prefecture_clean_building($addr) {
    $a = (string) $addr;
    /* Anonymat : on retire tout ce qui pourrait identifier une personne, même
       si un·e enquêteur·rice l'a saisi par erreur dans le champ adresse.
       On enchaîne plusieurs filets de sécurité. */

    /* 1) Emails */
    $a = preg_replace('/[^\s@]+@[^\s@]+\.[^\s@]+/u', ' ', $a);
    /* 2) Contenu entre parenthèses (souvent un nom / commentaire) */
    $a = preg_replace('/\([^)]*\)/u', ' ', $a);
    /* 3) Tout ce qui SUIT un mot « danger » (un nom/numéro suit en général) */
    $a = preg_replace('/\b(contacts?|t[ée]l[ée]?phones?|t[ée]ls?|portables?|mobiles?|gsm|mails?|e-?mails?|interphones?|sonnettes?|chez|locataires?|nom\s*:|occupants?)\b.*$/iu', ' ', $a);
    /* 4) Civilité + nom propre (Mme Traoré, M. Ba, Monsieur Dupont…) */
    $a = preg_replace('/\b(mr|mme|mlle|mle|m|monsieur|madame|mademoiselle)\b\.?\s*\p{Lu}[\p{L}\'\-]*/u', ' ', $a);
    /* 5) Numéros de téléphone : séquences d'au moins 9 chiffres */
    $a = preg_replace_callback('/[0-9][0-9 .\-]{7,}[0-9]/u', function ($m) {
        return preg_match_all('/[0-9]/', $m[0]) >= 9 ? ' ' : $m[0];
    }, $a);
    /* 6) Appartement / porte / étage / bâtiment (le n° de porte vit dans la
          colonne `etage`, jamais lue ici) */
    $a = preg_replace('/[,\s]*(appartements?|apparts?\.?|appts?\.?|apt\.?|logements?|logt\.?|porte|étages?|etages?|esc\.?|escalier|b[âa]t\.?|b[âa]timent)\s*:?\s*n?°?\s*[0-9A-Za-z°\/]+/iu', '', $a);
    /* 7) Filet final : si un code postal est présent, on coupe tout ce qui
          suit la ville (au plus 3 mots), pour éliminer un nom résiduel. */
    if (preg_match('/\b\d{5}\b/u', $a, $mm, PREG_OFFSET_CAPTURE)) {
        $pos   = $mm[0][1];
        $after = substr($a, $pos);
        if (preg_match('/^\d{5}[ ,]*(?:\p{L}[\p{L}\'\-]*[ ,]*){0,3}/u', $after, $cm)) {
            $a = substr($a, 0, $pos) . $cm[0];
        }
    }
    $a = preg_replace('/\s+/u', ' ', $a);
    return trim($a, " ,;-.");
}

/**
 * Clé canonique d'un IMMEUBLE, pour regrouper toutes les orthographes d'une
 * même adresse (« 14 rue de Saint-Jean-de-Luz », « 14 rue St Jean de Luz »,
 * « 14 rue Saint-Jean-de-luz »… → une seule case). On normalise : minuscules,
 * accents, abréviations (St→saint), et on retire les mots de liaison (de, du,
 * la…) et le code postal / la ville. Le numéro de rue reste distinctif
 * (14 ≠ 12 ≠ 2 : ce sont des immeubles différents).
 */
function lfi_nct_prefecture_addr_key($addr) {
    $a = lfi_nct_prefecture_clean_building($addr);
    $a = function_exists('mb_strtolower') ? mb_strtolower($a, 'UTF-8') : strtolower($a);
    $a = strtr($a, [
        'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ô'=>'o','ö'=>'o','ú'=>'u','ù'=>'u',
        'û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
    ]);
    $a = preg_replace('/[^a-z0-9]+/', ' ', $a);
    $abbr = [
        'st'=>'saint','ste'=>'sainte','av'=>'avenue','ave'=>'avenue','bd'=>'boulevard',
        'bld'=>'boulevard','blvd'=>'boulevard','pl'=>'place','imp'=>'impasse','sq'=>'square',
        'rte'=>'route','che'=>'chemin','crs'=>'cours','ste'=>'sainte',
    ];
    $filler = ['de'=>1,'du'=>1,'des'=>1,'d'=>1,'la'=>1,'le'=>1,'les'=>1,'l'=>1,'et'=>1,'au'=>1,'aux'=>1,'a'=>1,'nantes'=>1];
    $out = [];
    foreach (explode(' ', trim($a)) as $t) {
        if ($t === '') continue;
        if (preg_match('/^\d{5}$/', $t)) continue;     // code postal
        if (isset($abbr[$t])) $t = $abbr[$t];
        if (isset($filler[$t])) continue;              // mots de liaison
        $out[] = $t;
    }
    $key = implode(' ', $out);
    return $key !== '' ? $key : '__adresse_non_precisee__';
}

/**
 * Choisit, parmi plusieurs orthographes d'une même adresse, celle à afficher :
 * la mieux formée (le plus de tirets, puis de majuscules, puis la plus longue).
 */
function lfi_nct_prefecture_pick_display($candidates) {
    $best = '';
    $best_score = [-1, -1, -1];
    foreach ($candidates as $c) {
        $score = [substr_count($c, '-'), preg_match_all('/[A-ZÀ-Ý]/u', $c), function_exists('mb_strlen') ? mb_strlen($c) : strlen($c)];
        if ($score[0] > $best_score[0]
            || ($score[0] === $best_score[0] && $score[1] > $best_score[1])
            || ($score[0] === $best_score[0] && $score[1] === $best_score[1] && $score[2] > $best_score[2])) {
            $best = $c; $best_score = $score;
        }
    }
    return $best;
}

/**
 * Agrège les réponses d'enquête par IMMEUBLE, de façon strictement anonyme.
 * Ne sélectionne que `adresse` et `data` — jamais `etage`/`contact_*`.
 *
 * @return array{buildings: array, labels: array, foyers_total: int}
 */
function lfi_nct_prefecture_aggregate_by_building() {
    global $wpdb;
    $table  = $wpdb->prefix . 'lfi_nct_responses';
    $labels = lfi_nct_prefecture_type_labels();

    $rows = $wpdb->get_results("SELECT adresse, data FROM $table WHERE deleted_at IS NULL");
    $buildings = [];
    $foyers_total = 0;

    foreach ((array) $rows as $r) {
        $addr = lfi_nct_prefecture_clean_building($r->adresse);
        if ($addr === '') $addr = 'Adresse non précisée';
        $key = lfi_nct_prefecture_addr_key($r->adresse);

        if (!isset($buildings[$key])) {
            $buildings[$key] = [
                'adresse'          => $addr,
                '_disp'            => [],   // toutes les orthographes vues (choix d'affichage)
                'foyers'           => 0,
                'foyers_problemes' => 0,
                'gravite_sum'      => 0,
                'gravite_n'        => 0,
                'eau_chaude'       => 0,
                'types'            => array_fill_keys(array_keys($labels), 0),
            ];
        }
        $buildings[$key]['_disp'][$addr] = true;

        $buildings[$key]['foyers']++;
        $foyers_total++;

        $data = json_decode($r->data ?? '', true);
        if (!is_array($data)) continue;

        if (($data['problemes_presence'] ?? '') === 'oui') {
            $buildings[$key]['foyers_problemes']++;
        }
        foreach ((array) ($data['problemes_types'] ?? []) as $t) {
            if (isset($buildings[$key]['types'][$t])) $buildings[$key]['types'][$t]++;
        }
        $g = (int) ($data['problemes_gravite'] ?? 0);
        if ($g > 0) { $buildings[$key]['gravite_sum'] += $g; $buildings[$key]['gravite_n']++; }

        $ec_nb = trim((string) ($data['eau_chaude_nb_par_an'] ?? ''));
        $ec_du = trim((string) ($data['eau_chaude_duree_max'] ?? ''));
        if ($ec_nb !== '' || $ec_du !== '') $buildings[$key]['eau_chaude']++;
    }

    $out = [];
    foreach ($buildings as $b) {
        $cands = array_keys($b['_disp']);
        if (count($cands) > 1) $b['adresse'] = lfi_nct_prefecture_pick_display($cands);
        unset($b['_disp']);
        $b['gravite_moyenne'] = $b['gravite_n'] ? round($b['gravite_sum'] / $b['gravite_n'], 1) : 0;
        $out[] = $b;
    }
    usort($out, function ($a, $b) {
        return ($b['foyers_problemes'] <=> $a['foyers_problemes']) ?: ($b['foyers'] <=> $a['foyers']);
    });

    return ['buildings' => $out, 'labels' => $labels, 'foyers_total' => $foyers_total];
}

/** Totaux par type de problème, tous immeubles confondus. */
function lfi_nct_prefecture_totaux($agg) {
    $tot = array_fill_keys(array_keys($agg['labels']), 0);
    foreach ($agg['buildings'] as $b) {
        foreach ($b['types'] as $k => $n) $tot[$k] += $n;
    }
    return $tot;
}

/* ============================================================== *
 *  VUE : Volet Préfecture (paramètre actionnable)                 *
 * ============================================================== */
function lfi_nct_app_view_prefecture() {
    if (!lfi_nct_app_guard_brigade()) return;

    /* Enregistrement des contacts préfecture + mon Gmail perso */
    if (!empty($_POST['lfi_prefecture_save']) && check_admin_referer('lfi_prefecture_save')) {
        $contact = [
            'organisme' => sanitize_text_field(wp_unslash($_POST['pref_organisme'] ?? '')),
            'nom'       => sanitize_text_field(wp_unslash($_POST['pref_nom'] ?? '')),
            'fonction'  => sanitize_text_field(wp_unslash($_POST['pref_fonction'] ?? '')),
            'email'     => sanitize_email(wp_unslash($_POST['pref_email'] ?? '')),
            'nom2'      => sanitize_text_field(wp_unslash($_POST['pref_nom2'] ?? '')),
            'fonction2' => sanitize_text_field(wp_unslash($_POST['pref_fonction2'] ?? '')),
            'email2'    => sanitize_email(wp_unslash($_POST['pref_email2'] ?? '')),
        ];
        update_option('lfi_nct_prefecture_contact', $contact, false);
        $perso = sanitize_email(wp_unslash($_POST['pref_perso_gmail'] ?? ''));
        if ($perso !== '') update_option('lfi_nct_perso_gmail', $perso, false);
        wp_safe_redirect(lfi_nct_app_url('prefecture', ['saved' => 1]));
        exit;
    }

    /* Enregistrer une correspondance REÇUE de la préfecture */
    if (!empty($_POST['lfi_pref_recu']) && check_admin_referer('lfi_pref_recu')) {
        $corr  = lfi_nct_prefecture_corr();
        $de    = sanitize_text_field(wp_unslash($_POST['pref_recu_de'] ?? ''));
        $objet = sanitize_text_field(wp_unslash($_POST['pref_recu_objet'] ?? ''));
        $corps = sanitize_textarea_field(wp_unslash($_POST['pref_recu_corps'] ?? ''));
        if ($de !== '' || $objet !== '' || $corps !== '') {
            $corr['recu'][] = ['de' => $de, 'objet' => $objet, 'corps' => $corps, 'date' => current_time('Y-m-d H:i')];
            lfi_nct_prefecture_corr_save($corr);
        }
        wp_safe_redirect(lfi_nct_app_url('prefecture', ['corr_ok' => 1]));
        exit;
    }

    /* Supprimer une entrée de correspondance */
    if (!empty($_POST['lfi_pref_corr_del']) && check_admin_referer('lfi_pref_corr_del')) {
        $sens = (($_POST['del_sens'] ?? '') === 'recu') ? 'recu' : 'sent';
        $idx  = (int) ($_POST['del_idx'] ?? -1);
        $corr = lfi_nct_prefecture_corr();
        if ($idx >= 0 && isset($corr[$sens][$idx])) {
            array_splice($corr[$sens], $idx, 1);
            lfi_nct_prefecture_corr_save($corr);
        }
        wp_safe_redirect(lfi_nct_app_url('prefecture', ['corr_del' => 1]));
        exit;
    }

    $contact = lfi_nct_prefecture_contact();
    $agg     = lfi_nct_prefecture_aggregate_by_building();
    $labels  = $agg['labels'];

    lfi_nct_app_screen_open('🏛️ Préfecture', 'Partage anonyme des données du porte-à-porte');

    if (!empty($_GET['saved']))    lfi_nct_app_flash('✅ Contacts enregistrés.');
    if (!empty($_GET['corr_ok']))  lfi_nct_app_flash('📨 Correspondance enregistrée.');
    if (!empty($_GET['corr_del'])) lfi_nct_app_flash('🗑 Entrée supprimée.');

    /* Garantie d'anonymat */
    echo '<div class="lfi-app-help" style="background:#e8f5ea;border-left:4px solid #186a3b">';
    echo '🔒 <strong>Données strictement anonymes.</strong> Ce volet ne transmet que les <strong>problématiques par bâtiment</strong> (adresse de l\'immeuble + compteurs). Il n\'expose <strong>jamais</strong> les numéros de porte, ni les noms, téléphones ou contacts des locataires, ni aucune citation. L\'enquête de voisinage détaillée reste interne.';
    echo '</div>';

    /* Synthèse rapide */
    $nb_bat = count($agg['buildings']);
    echo '<div class="lfi-app-card" style="background:#fff;border-radius:10px;padding:14px 16px;margin:12px 0">';
    echo '<div style="font-size:1.05em"><strong>' . (int) $agg['foyers_total'] . '</strong> foyer(s) rencontré(s) · <strong>' . (int) $nb_bat . '</strong> bâtiment(s) recensé(s)</div>';
    echo '</div>';

    /* Tableau anonyme (aperçu) */
    if ($nb_bat === 0) {
        echo '<div class="lfi-app-help">Aucune réponse d\'enquête enregistrée pour l\'instant.</div>';
    } else {
        echo '<h3 style="margin:16px 0 6px">Problématiques par bâtiment (aperçu)</h3>';
        echo '<div style="overflow-x:auto"><table class="lfi-pref-table" style="width:100%;border-collapse:collapse;font-size:.9em">';
        echo '<thead><tr style="background:#f3f3f3;text-align:left">';
        echo '<th style="padding:6px 8px;border-bottom:2px solid #ccc">Bâtiment</th>';
        echo '<th style="padding:6px 8px;border-bottom:2px solid #ccc;text-align:center">Foyers</th>';
        echo '<th style="padding:6px 8px;border-bottom:2px solid #ccc;text-align:center">Avec problème</th>';
        echo '<th style="padding:6px 8px;border-bottom:2px solid #ccc">Principaux problèmes signalés</th>';
        echo '</tr></thead><tbody>';
        foreach ($agg['buildings'] as $b) {
            $puces = [];
            foreach ($b['types'] as $k => $n) {
                if ($n > 0) $puces[] = esc_html($labels[$k]) . ' (' . (int) $n . ')';
            }
            if ($b['eau_chaude'] > 0) $puces[] = 'Coupures d\'eau chaude (' . (int) $b['eau_chaude'] . ')';
            echo '<tr>';
            echo '<td style="padding:6px 8px;border-bottom:1px solid #eee"><strong>' . esc_html($b['adresse']) . '</strong></td>';
            echo '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:center">' . (int) $b['foyers'] . '</td>';
            echo '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:center">' . (int) $b['foyers_problemes'] . '</td>';
            echo '<td style="padding:6px 8px;border-bottom:1px solid #eee">' . ($puces ? implode(' · ', $puces) : '<span style="color:#999">—</span>') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';

        echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin:16px 0">';
        echo '<a class="btn-primary big" href="' . esc_url(lfi_nct_app_url('prefecture-rapport')) . '">📄 Rapport anonyme à imprimer / PDF</a>';
        echo '</div>';
        echo '<div class="lfi-app-help"><small>Le rapport s\'ouvre en page imprimable : « Imprimer » → « Enregistrer au format PDF », puis tu joins le PDF à ton email.</small></div>';
    }

    /* ===== Boutons d'envoi (avec suivi des correspondances) ===== */
    echo '<h3 style="margin:22px 0 6px">📨 Écrire (avec suivi)</h3>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0">';
    echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('prefecture-email', ['type' => 'prefecture'])) . '">✉️ Écrire à la préfecture</a>';
    echo '<a class="btn-primary" style="background:#0066a3" href="' . esc_url(lfi_nct_app_url('prefecture-email', ['type' => 'nmh'])) . '">🏢 Écrire à NMH (préfecture en copie)</a>';
    echo '</div>';
    echo '<div class="lfi-app-help"><small>« Écrire à la préfecture » part de l\'association. « Écrire à NMH » part de <strong>ton</strong> Gmail perso (' . esc_html(lfi_nct_perso_gmail()) . ') avec la préfecture (' . esc_html(implode(', ', lfi_nct_prefecture_emails()) ?: 'à renseigner ci-dessous') . ') + l\'archive de l\'association en copie.</small></div>';

    /* ===== Suivi des correspondances (envoyées + reçues) ===== */
    $corr = lfi_nct_prefecture_corr();
    $timeline = [];
    foreach ($corr['sent'] as $i => $e) { $e['sens'] = 'sent'; $e['_idx'] = $i; $timeline[] = $e; }
    foreach ($corr['recu'] as $i => $e) { $e['sens'] = 'recu'; $e['_idx'] = $i; $timeline[] = $e; }
    usort($timeline, function ($a, $b) { return strcmp($a['date'] ?? '', $b['date'] ?? ''); });

    echo '<h3 style="margin:22px 0 6px;color:#c8102e">📨 Correspondances avec la préfecture</h3>';
    if (empty($timeline)) {
        echo '<div class="lfi-app-help">Aucune correspondance pour l\'instant. Tes envois (boutons ci-dessus) sont archivés ici, et tu peux coller un email reçu.</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        foreach ($timeline as $e) {
            $is_recu = ($e['sens'] === 'recu');
            echo '<li class="lfi-app-card" style="border-left:4px solid ' . ($is_recu ? '#0066a3' : '#186a3b') . '">';
            echo '<div class="head"><div class="who">' . ($is_recu ? '📥 Reçu' : '📤 Envoyé') . '</div>';
            echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html($e['date'] ?? '') . '</div></div>';
            echo '<div class="meta">';
            if ($is_recu && !empty($e['de'])) echo '<span class="meta-chip">de ' . esc_html($e['de']) . '</span>';
            if (!$is_recu && !empty($e['to'])) echo '<span class="meta-chip">à ' . esc_html($e['to']) . '</span>';
            if (!$is_recu && !empty($e['cc'])) echo '<span class="meta-chip">cc ' . esc_html($e['cc']) . '</span>';
            echo '</div>';
            if (!empty($e['objet'])) echo '<div class="com"><strong>' . esc_html($e['objet']) . '</strong></div>';
            if (!empty($e['corps'])) echo '<div class="com" style="white-space:pre-wrap">' . esc_html(mb_substr($e['corps'], 0, 600)) . (mb_strlen($e['corps']) > 600 ? '…' : '') . '</div>';
            echo '<form method="post" onsubmit="return confirm(\'Supprimer cette entrée ?\')" style="margin-top:8px">';
            wp_nonce_field('lfi_pref_corr_del');
            echo '<input type="hidden" name="lfi_pref_corr_del" value="1">';
            echo '<input type="hidden" name="del_sens" value="' . esc_attr($e['sens']) . '">';
            echo '<input type="hidden" name="del_idx" value="' . (int) $e['_idx'] . '">';
            echo '<button type="submit" class="btn-ghost" style="color:#c8102e;border-color:#c8102e;padding:4px 10px;font-size:.85em">🗑 Supprimer cette entrée</button>';
            echo '</form>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /* Coller un email reçu de la préfecture */
    echo '<details style="margin-top:10px;background:#e8f0ff;border-radius:8px;padding:10px 14px">';
    echo '<summary style="cursor:pointer;font-weight:700;color:#0066a3">📥 Enregistrer un email reçu de la préfecture</summary>';
    echo '<form method="post" class="lfi-app-form" style="margin-top:10px">';
    wp_nonce_field('lfi_pref_recu');
    echo '<input type="hidden" name="lfi_pref_recu" value="1">';
    echo '<label>De (expéditeur)<input type="text" name="pref_recu_de" placeholder="prenom.nom@loire-atlantique.gouv.fr"></label>';
    echo '<label>Objet<input type="text" name="pref_recu_objet"></label>';
    echo '<label>Contenu<textarea name="pref_recu_corps" rows="5" placeholder="Colle ici le texte de l\'email reçu…"></textarea></label>';
    echo '<button type="submit" class="btn-primary">📥 Enregistrer</button>';
    echo '</form></details>';

    /* ===== Contacts éditables (déléguée + Gwenaëlle + mon Gmail perso) ===== */
    echo '<h3 style="margin:22px 0 6px">Interlocutrices à la préfecture</h3>';
    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_prefecture_save');
    echo '<input type="hidden" name="lfi_prefecture_save" value="1">';
    echo '<label>Organisme<input type="text" name="pref_organisme" value="' . esc_attr($contact['organisme']) . '" placeholder="Préfecture de la Loire-Atlantique"></label>';
    echo '<div style="border-left:3px solid #c8102e;padding-left:10px;margin:8px 0">';
    echo '<label>Déléguée — nom<input type="text" name="pref_nom" value="' . esc_attr($contact['nom']) . '" placeholder="Mme …"></label>';
    echo '<label>Fonction<input type="text" name="pref_fonction" value="' . esc_attr($contact['fonction']) . '" placeholder="ex : déléguée du préfet / cheffe de projet quartier"></label>';
    echo '<label>Email<input type="email" name="pref_email" value="' . esc_attr($contact['email']) . '" placeholder="prenom.nom@loire-atlantique.gouv.fr"></label>';
    echo '</div>';
    echo '<div style="border-left:3px solid #0066a3;padding-left:10px;margin:8px 0">';
    echo '<label>2ᵉ interlocutrice — nom<input type="text" name="pref_nom2" value="' . esc_attr($contact['nom2']) . '" placeholder="Gwenaëlle Gourdien"></label>';
    echo '<label>Fonction<input type="text" name="pref_fonction2" value="' . esc_attr($contact['fonction2']) . '"></label>';
    echo '<label>Email<input type="email" name="pref_email2" value="' . esc_attr($contact['email2']) . '" placeholder="prenom.nom@…gouv.fr"></label>';
    echo '</div>';
    echo '<label>Mon Gmail perso (pour « écrire à NMH » sur mon dossier)<input type="email" name="pref_perso_gmail" value="' . esc_attr(lfi_nct_perso_gmail()) . '" placeholder="fabrice.doucet44@gmail.com"></label>';
    echo '<button type="submit" class="btn-primary">💾 Enregistrer</button>';
    echo '</form>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE : Composer un email préfecture / NMH (avec suivi)          *
 * ============================================================== */
function lfi_nct_app_view_prefecture_email() {
    if (!lfi_nct_app_guard_brigade()) return;

    $type    = (($_GET['type'] ?? '') === 'nmh') ? 'nmh' : 'prefecture';
    $contact = lfi_nct_prefecture_contact();
    $pref_em = lfi_nct_prefecture_emails();
    $ga      = lfi_nct_ga_gmail();
    $perso   = lfi_nct_perso_gmail();
    $bailleur = function_exists('lfi_nct_fact_bailleur') ? lfi_nct_fact_bailleur() : [];

    /* HANDLER : journalise l'envoi (posté en arrière-plan par l'opener Gmail). */
    if (!empty($_POST['lfi_pref_gmail_log']) && check_admin_referer('lfi_pref_email_send')) {
        $to = sanitize_text_field(wp_unslash($_POST['email_to'] ?? ''));
        $cc = sanitize_text_field(wp_unslash($_POST['email_cc'] ?? ''));
        $su = sanitize_text_field(wp_unslash($_POST['email_subject'] ?? ''));
        $corr = lfi_nct_prefecture_corr();
        $corr['sent'][] = ['to' => $to, 'cc' => $cc, 'objet' => $su, 'date' => current_time('Y-m-d H:i'), 'type' => $type];
        lfi_nct_prefecture_corr_save($corr);
        wp_safe_redirect(lfi_nct_app_url('prefecture', ['corr_ok' => 1]));
        exit;
    }

    if ($type === 'nmh') {
        $from    = $perso;
        $to      = trim($bailleur['email'] ?? '') ?: trim($bailleur['agence_email'] ?? '');
        $cc      = trim(implode(', ', array_values(array_unique(array_filter(array_merge($pref_em, [$ga]))))));
        $title   = 'Écrire à NMH — préfecture en copie';
        $subject = 'Mon logement — demande / signalement';
        $sig     = "\n\n—\nFabrice Doucet";
        $body    = "Madame, Monsieur,\n\n[Décris ici ta demande ou ton signalement concernant ton logement.]\n\nDans l'attente de votre retour, je vous prie d'agréer mes salutations distinguées.";
        $help    = 'Part de ton Gmail perso. La préfecture (' . (implode(', ', $pref_em) ?: 'à renseigner') . ') et l\'archive de l\'association sont en copie, pour qu\'elles aient toute la correspondance.';
    } else {
        $from    = $ga;
        $to      = implode(', ', $pref_em);
        $cc      = $ga;
        $title   = 'Écrire à la préfecture';
        $subject = 'Logement social — Clos Toreau (Nantes Sud)';
        $sig     = "\n\n—\nFabrice Doucet — Association Union des Quartiers Libres";
        $body    = "Madame,\n\n[Ton message à la préfecture.]\n\nJe reste à votre disposition pour en échanger.";
        $help    = 'Part du Gmail de l\'association, avec l\'archive en copie. Destinataires : la déléguée et Gwenaëlle (si renseignées).';
    }

    lfi_nct_app_screen_open('📧 ' . $title, 'Suivi automatique dans le volet Préfecture');

    if ($type === 'nmh' && $to === '') {
        echo '<div class="lfi-app-help" style="background:#fff3cd;border-left:4px solid #d39e00"><small>⚠️ L\'email de NMH n\'est pas renseigné. Renseigne-le dans <a href="' . esc_url(lfi_nct_app_url('facturation-params')) . '">Paramètres facturation → Bailleur</a>, ou saisis-le ci-dessous.</small></div>';
    }
    if (empty($pref_em)) {
        echo '<div class="lfi-app-help" style="background:#fff3cd;border-left:4px solid #d39e00"><small>⚠️ Aucun email préfecture renseigné. Ajoute la déléguée et/ou Gwenaëlle dans le <a href="' . esc_url(lfi_nct_app_url('prefecture')) . '">volet Préfecture</a>.</small></div>';
    }

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_pref_email_send');
    echo '<label>Destinataire(s)<input type="text" name="email_to" value="' . esc_attr($to) . '" required></label>';
    echo '<label>Copie (CC)<input type="text" name="email_cc" value="' . esc_attr($cc) . '"></label>';
    echo '<label>Objet<input type="text" name="email_subject" value="' . esc_attr($subject) . '" required></label>';
    echo '<label>Mot d\'intro (optionnel)<textarea name="email_intro" rows="2" placeholder="Optionnel"></textarea></label>';
    echo '<label>Message<textarea name="email_body" id="lfi-email-body" rows="12" required>' . esc_textarea($body) . '</textarea></label>';

    lfi_nct_render_gmail_opener($from, $sig, 'lfi_pref_gmail_log', '📨 Ouvrir dans Gmail (' . $from . ')');
    echo '<div class="lfi-app-help" style="background:#e8f0ff;border-left:4px solid #0066a3"><small>' . esc_html($help) . ' Sur iPhone, ça ouvre l\'app Gmail avec le message prêt. L\'envoi est aussitôt consigné dans le suivi.</small></div>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('prefecture')) . '">← Retour au volet Préfecture</a>';
    echo '</form>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE : Rapport anonyme imprimable (PDF)                          *
 * ============================================================== */
function lfi_nct_app_view_prefecture_rapport() {
    if (!lfi_nct_app_guard_brigade()) return;

    $contact = lfi_nct_prefecture_contact();
    $agg     = lfi_nct_prefecture_aggregate_by_building();
    $labels  = $agg['labels'];
    $totaux  = lfi_nct_prefecture_totaux($agg);
    $nb_bat  = count($agg['buildings']);

    lfi_nct_app_screen_open('📄 Rapport préfecture', 'Document anonyme — par bâtiment');

    /* Réutilise le style document + bouton imprimer du module recouvrement. */
    if (function_exists('lfi_nct_rec_doc_styles')) lfi_nct_rec_doc_styles();

    echo '<div class="lfi-rec-doc">';

    echo '<div class="expediteur"><strong>Association Union des Quartiers Libres</strong><br>';
    echo 'Groupe d\'Action — Nantes Sud / Clos Toreau<br>';
    echo 'Contact : ' . esc_html(function_exists('lfi_nct_ga_gmail') ? lfi_nct_ga_gmail() : '') . '</div>';

    echo '<div class="destinataire"><strong>' . esc_html($contact['organisme'] ?: 'Préfecture de la Loire-Atlantique') . '</strong><br>';
    if ($contact['nom'])      echo esc_html($contact['nom']) . '<br>';
    if ($contact['fonction']) echo esc_html($contact['fonction']);
    echo '</div>';

    echo '<div class="lieu-date">À Nantes, le ' . esc_html(wp_date('j F Y')) . '</div>';

    echo '<h1>État anonyme des signalements par bâtiment</h1>';

    echo '<p class="objet">Objet : signalements recueillis lors du porte-à-porte logement (quartier Clos Toreau, Nantes Sud) — données agrégées par immeuble, sans information nominative.</p>';

    echo '<div class="citations"><strong>Garantie d\'anonymat.</strong> Ce document ne comporte aucune donnée nominative. Il ne mentionne ni numéro de porte ou d\'appartement, ni nom, téléphone ou contact de locataire, ni citation individuelle. Seules figurent l\'adresse des immeubles et le nombre de foyers ayant signalé chaque type de problème. L\'enquête détaillée demeure interne à l\'association.</div>';

    echo '<p>Le présent état porte sur <strong>' . (int) $agg['foyers_total'] . ' foyer(s)</strong> rencontré(s) répartis sur <strong>' . (int) $nb_bat . ' bâtiment(s)</strong>. Pour chaque immeuble, le tableau indique le nombre de foyers rencontrés, le nombre de foyers déclarant au moins un problème, et le décompte par catégorie de désordre.</p>';

    if ($nb_bat === 0) {
        echo '<p><em>Aucune réponse enregistrée à ce jour.</em></p>';
    } else {
        echo '<h2>Détail par bâtiment</h2>';
        foreach ($agg['buildings'] as $b) {
            echo '<table class="detail" style="margin-bottom:18px">';
            echo '<tr class="total"><td colspan="2">' . esc_html($b['adresse']) . '</td></tr>';
            echo '<tr><td>Foyers rencontrés</td><td class="num">' . (int) $b['foyers'] . '</td></tr>';
            echo '<tr><td>Foyers déclarant au moins un problème</td><td class="num">' . (int) $b['foyers_problemes'] . '</td></tr>';
            if ($b['gravite_moyenne'] > 0) {
                echo '<tr><td>Gravité moyenne déclarée (échelle 1–10)</td><td class="num">' . esc_html(number_format_i18n($b['gravite_moyenne'], 1)) . '</td></tr>';
            }
            foreach ($b['types'] as $k => $n) {
                if ($n > 0) echo '<tr><td>' . esc_html($labels[$k]) . '</td><td class="num">' . (int) $n . '</td></tr>';
            }
            if ($b['eau_chaude'] > 0) {
                echo '<tr><td>Coupures d\'eau chaude signalées</td><td class="num">' . (int) $b['eau_chaude'] . '</td></tr>';
            }
            echo '</table>';
        }

        echo '<h2>Synthèse — tous bâtiments confondus</h2>';
        echo '<table class="detail">';
        foreach ($totaux as $k => $n) {
            if ($n > 0) echo '<tr><td>' . esc_html($labels[$k]) . '</td><td class="num">' . (int) $n . ' foyer(s)</td></tr>';
        }
        echo '</table>';
    }

    echo '<p class="pj">Document établi par l\'association Union des Quartiers Libres dans le cadre de son action d\'accompagnement des habitant·es. Données agrégées et anonymisées ; le détail individuel n\'est pas communiqué.</p>';

    echo '</div>'; // .lfi-rec-doc

    lfi_nct_app_screen_close(false);
}
