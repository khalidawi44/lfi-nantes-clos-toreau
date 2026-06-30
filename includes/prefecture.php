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
    ], $d);
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
    $a = trim(preg_replace('/\s+/u', ' ', (string) $addr));
    $a = preg_replace('/[,\s]*(appartements?|appart\.?|apt\.?|porte|étages?|etages?|esc\.?|escalier|b[âa]t\.?|b[âa]timent)\s*:?\s*n?°?\s*[0-9A-Za-z°\/]+/iu', '', $a);
    return trim($a, " ,;-");
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
        $key = function_exists('mb_strtolower') ? mb_strtolower($addr) : strtolower($addr);

        if (!isset($buildings[$key])) {
            $buildings[$key] = [
                'adresse'          => $addr,
                'foyers'           => 0,
                'foyers_problemes' => 0,
                'gravite_sum'      => 0,
                'gravite_n'        => 0,
                'eau_chaude'       => 0,
                'types'            => array_fill_keys(array_keys($labels), 0),
            ];
        }

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

    /* Enregistrement du contact préfecture */
    if (!empty($_POST['lfi_prefecture_save']) && check_admin_referer('lfi_prefecture_save')) {
        $contact = [
            'organisme' => sanitize_text_field(wp_unslash($_POST['pref_organisme'] ?? '')),
            'nom'       => sanitize_text_field(wp_unslash($_POST['pref_nom'] ?? '')),
            'fonction'  => sanitize_text_field(wp_unslash($_POST['pref_fonction'] ?? '')),
            'email'     => sanitize_email(wp_unslash($_POST['pref_email'] ?? '')),
        ];
        update_option('lfi_nct_prefecture_contact', $contact, false);
        wp_safe_redirect(lfi_nct_app_url('prefecture', ['saved' => 1]));
        exit;
    }

    $contact = lfi_nct_prefecture_contact();
    $agg     = lfi_nct_prefecture_aggregate_by_building();
    $labels  = $agg['labels'];

    lfi_nct_app_screen_open('🏛️ Préfecture', 'Partage anonyme des données du porte-à-porte');

    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Contact préfecture enregistré.');

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
        if ($contact['email']) {
            echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_prefecture_gmail_url($contact)) . '" target="_blank" rel="noopener">📨 Préparer l\'email à la préfecture</a>';
        }
        echo '</div>';
        echo '<div class="lfi-app-help"><small>Le rapport s\'ouvre en page imprimable : « Imprimer » → « Enregistrer au format PDF », puis tu joins le PDF à ton email. L\'email Gmail s\'ouvre pré-rempli (le PDF est à joindre manuellement).</small></div>';
    }

    /* Contact préfecture (éditable) */
    echo '<h3 style="margin:22px 0 6px">Interlocutrice à la préfecture</h3>';
    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_prefecture_save');
    echo '<input type="hidden" name="lfi_prefecture_save" value="1">';
    echo '<label>Organisme<input type="text" name="pref_organisme" value="' . esc_attr($contact['organisme']) . '" placeholder="Préfecture de la Loire-Atlantique"></label>';
    echo '<label>Nom de l\'interlocutrice<input type="text" name="pref_nom" value="' . esc_attr($contact['nom']) . '" placeholder="Mme …"></label>';
    echo '<label>Fonction<input type="text" name="pref_fonction" value="' . esc_attr($contact['fonction']) . '" placeholder="ex : déléguée du préfet / chargée de quartier"></label>';
    echo '<label>Email<input type="email" name="pref_email" value="' . esc_attr($contact['email']) . '" placeholder="prenom.nom@loire-atlantique.gouv.fr"></label>';
    echo '<button type="submit" class="btn-primary">💾 Enregistrer le contact</button>';
    echo '</form>';

    lfi_nct_app_screen_close();
}

/** Construit l'URL de rédaction Gmail (compose) pré-remplie pour la préfecture. */
function lfi_nct_prefecture_gmail_url($contact) {
    $user = function_exists('lfi_nct_ga_gmail') ? lfi_nct_ga_gmail() : '';
    $su = 'Données anonymisées du porte-à-porte logement — Clos Toreau (Nantes Sud)';
    $body = "Madame,\n\n"
        . "Comme convenu, vous trouverez ci-joint le récapitulatif ANONYME des signalements recueillis lors de notre porte-à-porte dans le quartier, présenté par bâtiment.\n\n"
        . "Conformément à notre engagement auprès des habitant·es, ce document ne comporte aucune donnée nominative : ni numéro de porte, ni nom, ni coordonnées. Il ne fait apparaître que les problématiques agrégées par immeuble.\n\n"
        . "Je reste à votre disposition pour en échanger.\n\n"
        . "Cordialement,\nFabrice Doucet — Association Union des Quartiers Libres";
    return 'https://mail.google.com/mail/?view=cm&fs=1&tf=1'
        . ($user ? '&authuser=' . rawurlencode($user) : '')
        . '&to=' . rawurlencode($contact['email'])
        . '&su=' . rawurlencode($su)
        . '&body=' . rawurlencode($body);
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
