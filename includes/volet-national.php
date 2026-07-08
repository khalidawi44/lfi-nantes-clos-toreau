<?php
/**
 * VOLET NATIONAL — pour les député·es.
 *
 * Troisième niveau de l'outil, après le volet Groupe d'Action (local) et le
 * volet Municipal (réseau cumulé des GA, pour les élu·es locaux). Ici, TOUTES
 * les données de tous les GA sont agrégées au niveau national pour armer
 * l'argumentation des député·es sur le logement :
 *   - statistiques nationales cumulées ;
 *   - éléments de langage / arguments prêts à l'emploi (modifiables) ;
 *   - bibliothèque d'études & de données scientifiques (dépôt de documents) ;
 *   - export d'un « dossier national » imprimable / PDF.
 *
 * Réservé au super-admin.
 */
if (!defined('ABSPATH')) exit;

/** Accès au volet national : super-admin uniquement. */
function lfi_nct_national_can() {
    return current_user_can('manage_options');
}

/* ============================================================== *
 *  Agrégats nationaux (toutes les enquêtes, tous les GA)          *
 * ============================================================== */
function lfi_nct_national_stats() {
    global $wpdb;
    $t    = $wpdb->prefix . 'lfi_nct_responses';
    $rows = $wpdb->get_results("SELECT adresse, data, ga FROM $t WHERE deleted_at IS NULL") ?: [];

    $total = count($rows);
    $with_problem = 0;
    $ptypes = [];              // slug => count
    $grav_sum = 0; $grav_n = 0;
    $grav_bucket = [0, 0, 0, 0]; // léger / préoccupant / grave / critique
    $addr = [];                // clé canonique => 1
    $by_ga = [];               // slug => count

    foreach ($rows as $r) {
        $d = json_decode((string) $r->data, true);
        if (!is_array($d)) $d = [];
        if ((string) ($d['problemes_presence'] ?? '') === 'oui') $with_problem++;
        foreach ((array) ($d['problemes_types'] ?? []) as $ty) {
            $ty = (string) $ty;
            if ($ty === '') continue;
            $ptypes[$ty] = ($ptypes[$ty] ?? 0) + 1;
        }
        $g = (int) ($d['problemes_gravite'] ?? 0);
        if ($g > 0) {
            $grav_sum += $g; $grav_n++;
            if     ($g >= 8) $grav_bucket[3]++;
            elseif ($g >= 6) $grav_bucket[2]++;
            elseif ($g >= 3) $grav_bucket[1]++;
            else             $grav_bucket[0]++;
        }
        $a = trim((string) $r->adresse);
        if ($a !== '' && function_exists('lfi_nct_address_canonical_key')) {
            $k = lfi_nct_address_canonical_key($a);
            if ($k !== '') $addr[$k] = 1;
        }
        $slug = (string) $r->ga;
        if ($slug === '') $slug = 'clos-toreau';
        $by_ga[$slug] = ($by_ga[$slug] ?? 0) + 1;
    }
    arsort($ptypes);
    arsort($by_ga);

    return [
        'total'        => $total,
        'with_problem' => $with_problem,
        'pct_problem'  => $total ? (int) round($with_problem * 100 / $total) : 0,
        'ptypes'       => $ptypes,
        'grav_avg'     => $grav_n ? round($grav_sum / $grav_n, 1) : 0,
        'grav_bucket'  => $grav_bucket,
        'immeubles'    => count($addr),
        'by_ga'        => $by_ga,
        'nb_ga'        => count($by_ga),
    ];
}

/** Libellé lisible d'un type de problème. */
function lfi_nct_national_prob_label($slug) {
    $types = function_exists('lfi_nct_enq_problem_types') ? lfi_nct_enq_problem_types() : [];
    if (isset($types[$slug])) return $types[$slug][0] . ' ' . $types[$slug][1];
    return ucfirst(str_replace('_', ' ', $slug));
}

/** Variables de substitution pour les éléments de langage. */
function lfi_nct_national_vars($s) {
    $top = '';
    if (!empty($s['ptypes'])) {
        $k = array_key_first($s['ptypes']);
        $top = lfi_nct_national_prob_label($k);
    }
    return [
        '{total}'        => number_format_i18n($s['total']),
        '{avec_probleme}'=> number_format_i18n($s['with_problem']),
        '{pct}'          => $s['pct_problem'] . ' %',
        '{immeubles}'    => number_format_i18n($s['immeubles']),
        '{gravite}'      => str_replace('.', ',', (string) $s['grav_avg']),
        '{nb_ga}'        => number_format_i18n($s['nb_ga']),
        '{top_probleme}' => $top,
    ];
}

/** Éléments de langage par défaut (si rien n'a été personnalisé). */
function lfi_nct_national_args_default() {
    return
"Constat de terrain (données citoyennes, enquête porte-à-porte) :\n" .
"— Sur {total} logements enquêtés dans {nb_ga} quartier·s, {pct} présentent au moins un problème d'habitabilité signalé par les locataires.\n" .
"— {immeubles} immeubles sont concernés. La gravité moyenne déclarée est de {gravite}/10.\n" .
"— Le problème le plus fréquent est : {top_probleme}.\n\n" .
"Argument : ces chiffres, collectés directement auprès des habitant·es, montrent l'ampleur de l'habitat indigne dans le parc social et l'insuffisance du traitement par les bailleurs dans les délais légaux.\n\n" .
"Demande : renforcer les obligations de résultat des bailleurs sociaux, les moyens des services d'hygiène (SCHS/ARS), et l'encadrement effectif des loyers.";
}

/* ============================================================== *
 *  VUE : Tableau de bord national (stats + accès)                 *
 * ============================================================== */
function lfi_nct_app_view_national() {
    if (!lfi_nct_national_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $s = lfi_nct_national_stats();

    lfi_nct_app_screen_open('🇫🇷 Volet national — député·es', 'Données citoyennes agrégées pour l\'argumentation nationale sur le logement');
    echo '<div class="lfi-app-help" style="background:#eef4ff;border-left:4px solid #0066a3"><strong>Toutes nos données au service de l\'argumentation nationale.</strong> Chiffres cumulés de tous les groupes d\'action, éléments de langage prêts à l\'emploi, études & données scientifiques, et un dossier PDF à envoyer aux député·es. Vue réservée au super-admin.</div>';

    /* Grille de chiffres. */
    $cards = [
        ['🏠', number_format_i18n($s['total']),        'Logements enquêtés'],
        ['⚠️', $s['pct_problem'] . ' %',                'Avec un problème'],
        ['🏢', number_format_i18n($s['immeubles']),     'Immeubles touchés'],
        ['📈', str_replace('.', ',', (string) $s['grav_avg']) . '/10', 'Gravité moyenne'],
        ['🗺️', number_format_i18n($s['nb_ga']),         'Quartiers / GA'],
        ['🔴', number_format_i18n($s['grav_bucket'][2] + $s['grav_bucket'][3]), 'Cas graves/critiques'],
    ];
    echo '<div class="lfi-app-stats-grid">';
    foreach ($cards as $c) {
        echo '<div class="stat"><div class="ico">' . $c[0] . '</div><div class="n">' . esc_html($c[1]) . '</div><div class="l">' . esc_html($c[2]) . '</div></div>';
    }
    echo '</div>';

    /* Top problèmes. */
    if (!empty($s['ptypes'])) {
        echo '<h3 style="margin:16px 0 6px">Problèmes les plus signalés</h3>';
        echo '<ul class="lfi-app-list">';
        $i = 0;
        foreach ($s['ptypes'] as $slug => $n) {
            if ($i++ >= 8) break;
            $pct = $s['total'] ? round($n * 100 / $s['total']) : 0;
            echo '<li class="lfi-app-card" style="padding:10px 12px"><div class="head"><div class="who">' . esc_html(lfi_nct_national_prob_label($slug)) . '</div><div class="when">' . (int) $n . ' · ' . $pct . '%</div></div></li>';
        }
        echo '</ul>';
    }

    /* Accès aux sous-parties. */
    $tiles = [
        ['🗣️', 'Éléments de langage', 'Arguments prêts à l\'emploi', lfi_nct_app_url('national-args')],
        ['📚', 'Études & données',     'Documents, études scientifiques', lfi_nct_app_url('national-etudes')],
        ['📄', 'Dossier national (PDF)', 'À envoyer aux député·es', lfi_nct_app_url('national-pdf')],
    ];
    echo '<div class="lfi-app-grid" style="margin-top:14px">';
    foreach ($tiles as $t) {
        echo '<a class="lfi-app-tile" href="' . esc_url($t[3]) . '"><div class="ico">' . $t[0] . '</div><div class="tit">' . esc_html($t[1]) . '</div><div class="sub">' . esc_html($t[2]) . '</div></a>';
    }
    echo '</div>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE : Éléments de langage (modifiables)                        *
 * ============================================================== */
function lfi_nct_app_view_national_args() {
    if (!lfi_nct_national_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    if (!empty($_POST['lfi_nat_args_save']) && check_admin_referer('lfi_nat_args')) {
        update_option('lfi_nct_national_args', sanitize_textarea_field(wp_unslash($_POST['args'] ?? '')), false);
        wp_safe_redirect(lfi_nct_app_url('national-args', ['saved' => 1]));
        exit;
    }

    $s    = lfi_nct_national_stats();
    $vars = lfi_nct_national_vars($s);
    $raw  = (string) get_option('lfi_nct_national_args', '');
    if ($raw === '') $raw = lfi_nct_national_args_default();
    $rendered = strtr($raw, $vars);

    lfi_nct_app_screen_open('🗣️ Éléments de langage', 'Arguments pour les député·es, remplis avec nos chiffres');
    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Éléments de langage enregistrés.');

    /* Aperçu (variables remplacées). */
    echo '<h3 style="margin:6px 0 6px">Aperçu (chiffres à jour)</h3>';
    echo '<div class="lfi-app-card" style="white-space:pre-wrap;line-height:1.5">' . esc_html($rendered) . '</div>';
    if (function_exists('lfi_nct_copy_button')) {
        echo '<div style="margin:8px 0">' . lfi_nct_copy_button($rendered, '📋 Copier le texte') . '</div>';
    }

    /* Édition (avec variables). */
    echo '<h3 style="margin:16px 0 6px">Modifier le modèle</h3>';
    echo '<div class="lfi-app-help"><small>Variables disponibles (remplacées automatiquement) : <code>{total}</code>, <code>{avec_probleme}</code>, <code>{pct}</code>, <code>{immeubles}</code>, <code>{gravite}</code>, <code>{nb_ga}</code>, <code>{top_probleme}</code>.</small></div>';
    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_nat_args');
    echo '<input type="hidden" name="lfi_nat_args_save" value="1">';
    echo '<label>Modèle d\'argumentaire<textarea name="args" rows="12">' . esc_textarea($raw) . '</textarea></label>';
    echo '<button type="submit" class="btn-primary">💾 Enregistrer</button>';
    echo '</form>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE : Bibliothèque d'études & données                          *
 * ============================================================== */
function lfi_nct_national_etudes() {
    $e = get_option('lfi_nct_national_etudes', []);
    return is_array($e) ? $e : [];
}

function lfi_nct_app_view_national_etudes() {
    if (!lfi_nct_national_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    /* Ajout d'une étude. */
    if (!empty($_POST['lfi_nat_etude_add']) && check_admin_referer('lfi_nat_etude')) {
        $titre = sanitize_text_field(wp_unslash($_POST['titre'] ?? ''));
        $desc  = sanitize_textarea_field(wp_unslash($_POST['desc'] ?? ''));
        $lien  = esc_url_raw(wp_unslash($_POST['lien'] ?? ''));
        $att   = 0;
        if (!empty($_FILES['doc']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $aid = media_handle_upload('doc', 0);
            if (!is_wp_error($aid)) $att = (int) $aid;
        }
        if ($titre !== '' && ($att || $lien)) {
            $list   = lfi_nct_national_etudes();
            $list[] = ['titre' => $titre, 'desc' => $desc, 'att' => $att, 'lien' => $lien, 'date' => current_time('mysql')];
            update_option('lfi_nct_national_etudes', $list, false);
        }
        wp_safe_redirect(lfi_nct_app_url('national-etudes', ['added' => 1]));
        exit;
    }
    /* Suppression. */
    if (!empty($_POST['lfi_nat_etude_del']) && check_admin_referer('lfi_nat_etude_del')) {
        $idx  = (int) $_POST['idx'];
        $list = lfi_nct_national_etudes();
        if (isset($list[$idx])) { unset($list[$idx]); update_option('lfi_nct_national_etudes', array_values($list), false); }
        wp_safe_redirect(lfi_nct_app_url('national-etudes', ['deleted' => 1]));
        exit;
    }

    $list = lfi_nct_national_etudes();
    lfi_nct_app_screen_open('📚 Études & données', count($list) . ' document(s) — pour l\'argumentation nationale');
    if (!empty($_GET['added']))   lfi_nct_app_flash('✅ Étude ajoutée.');
    if (!empty($_GET['deleted'])) lfi_nct_app_flash('Étude supprimée.');

    echo '<div class="lfi-app-help">Dépose ici les études et données qui appuient l\'argumentation (ex : étude sur les finances de Nantes Métropole Habitat, cas concret d\'un loyer). Fichier (PDF, image, tableur) et/ou lien.</div>';

    echo '<details class="lfi-app-collapse"><summary>+ Ajouter une étude / un document</summary>';
    echo '<form method="post" enctype="multipart/form-data" class="lfi-app-form" style="margin-top:10px">';
    wp_nonce_field('lfi_nat_etude');
    echo '<input type="hidden" name="lfi_nat_etude_add" value="1">';
    echo '<label>Titre<input type="text" name="titre" required placeholder="Ex : Finances de Nantes Métropole Habitat — cas d\'un loyer"></label>';
    echo '<label>Description / ce que ça montre<textarea name="desc" rows="3" placeholder="Résumé, chiffres clés, source…"></textarea></label>';
    echo '<label>Fichier (PDF, image, tableur)<input type="file" name="doc" accept=".pdf,image/*,.csv,.xlsx,.xls,.doc,.docx"></label>';
    echo '<label>…ou un lien<input type="url" name="lien" placeholder="https://…"></label>';
    echo '<button type="submit" class="btn-primary">➕ Ajouter</button>';
    echo '</form></details>';

    if (empty($list)) {
        echo '<div class="lfi-app-empty">Aucune étude pour l\'instant.</div>';
        lfi_nct_app_screen_close();
        return;
    }
    echo '<ul class="lfi-app-list">';
    foreach ($list as $idx => $e) {
        $url = !empty($e['att']) ? wp_get_attachment_url((int) $e['att']) : ($e['lien'] ?? '');
        echo '<li class="lfi-app-card">';
        echo '<div class="head"><div class="who">📄 ' . esc_html($e['titre'] ?? '') . '</div><div class="when">' . esc_html(!empty($e['date']) ? wp_date('j M Y', strtotime($e['date'])) : '') . '</div></div>';
        if (!empty($e['desc'])) echo '<div class="lfi-app-help" style="margin:6px 0">' . esc_html($e['desc']) . '</div>';
        echo '<div class="row-actions" style="display:flex;gap:8px;flex-wrap:wrap">';
        if ($url) echo '<a class="btn-primary" href="' . esc_url($url) . '" target="_blank" rel="noopener">⬇️ Ouvrir</a>';
        echo '<form method="post" style="display:inline">';
        wp_nonce_field('lfi_nat_etude_del');
        echo '<input type="hidden" name="lfi_nat_etude_del" value="1"><input type="hidden" name="idx" value="' . (int) $idx . '">';
        echo '<button type="submit" class="btn-ghost" style="border-color:#c8102e;color:#c8102e" onclick="return confirm(\'Retirer cette étude ?\');">🗑 Retirer</button>';
        echo '</form>';
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE : Dossier national imprimable (PDF via impression)         *
 * ============================================================== */
function lfi_nct_app_view_national_pdf() {
    if (!lfi_nct_national_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $s     = lfi_nct_national_stats();
    $vars  = lfi_nct_national_vars($s);
    $raw   = (string) get_option('lfi_nct_national_args', '');
    if ($raw === '') $raw = lfi_nct_national_args_default();
    $args  = strtr($raw, $vars);
    $etudes = lfi_nct_national_etudes();

    echo '<div class="lfi-app">';
    echo '<div class="lfi-noprint" style="max-width:640px;margin:0 auto 12px;display:flex;gap:8px;flex-wrap:wrap">';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('national')) . '">← Volet national</a>';
    echo '<button type="button" class="btn-primary" onclick="window.print()">🖨 Imprimer / PDF</button>';
    echo '</div>';

    echo '<div class="lfi-dossier" style="max-width:720px;margin:0 auto;background:#fff;border:1px solid #eee;border-radius:12px;padding:26px 30px;color:#1a1a1a">';
    echo '<div style="text-align:center;border-bottom:3px solid #c8102e;padding-bottom:12px;margin-bottom:18px">';
    echo '<h1 style="color:#c8102e;margin:0;font-size:1.5em">Dossier national — Logement</h1>';
    echo '<div style="color:#666;margin-top:4px">Données citoyennes de terrain · France Insoumise Nantes Sud & réseau des groupes d\'action</div>';
    echo '<div style="color:#999;font-size:.85em;margin-top:2px">Édité le ' . esc_html(wp_date('j F Y')) . '</div>';
    echo '</div>';

    echo '<h2 style="color:#c8102e;font-size:1.15em">1. Les chiffres</h2>';
    echo '<ul style="line-height:1.7">';
    echo '<li><strong>' . esc_html(number_format_i18n($s['total'])) . '</strong> logements enquêtés dans <strong>' . esc_html(number_format_i18n($s['nb_ga'])) . '</strong> quartier·s / groupes d\'action.</li>';
    echo '<li><strong>' . esc_html($s['pct_problem']) . ' %</strong> présentent au moins un problème d\'habitabilité (' . esc_html(number_format_i18n($s['with_problem'])) . ' logements).</li>';
    echo '<li><strong>' . esc_html(number_format_i18n($s['immeubles'])) . '</strong> immeubles concernés.</li>';
    echo '<li>Gravité moyenne déclarée : <strong>' . esc_html(str_replace('.', ',', (string) $s['grav_avg'])) . '/10</strong> ; <strong>' . esc_html(number_format_i18n($s['grav_bucket'][2] + $s['grav_bucket'][3])) . '</strong> cas graves ou critiques.</li>';
    echo '</ul>';
    if (!empty($s['ptypes'])) {
        echo '<div style="margin-top:6px"><strong>Problèmes les plus fréquents :</strong><ul style="line-height:1.6">';
        $i = 0;
        foreach ($s['ptypes'] as $slug => $n) {
            if ($i++ >= 6) break;
            $pct = $s['total'] ? round($n * 100 / $s['total']) : 0;
            echo '<li>' . esc_html(lfi_nct_national_prob_label($slug)) . ' — ' . (int) $n . ' (' . $pct . ' %)</li>';
        }
        echo '</ul></div>';
    }

    echo '<h2 style="color:#c8102e;font-size:1.15em;margin-top:20px">2. Arguments</h2>';
    echo '<div style="white-space:pre-wrap;line-height:1.6">' . esc_html($args) . '</div>';

    echo '<h2 style="color:#c8102e;font-size:1.15em;margin-top:20px">3. Études & données (annexes)</h2>';
    if (empty($etudes)) {
        echo '<div style="color:#666">Aucune étude jointe.</div>';
    } else {
        echo '<ul style="line-height:1.6">';
        foreach ($etudes as $e) {
            $url = !empty($e['att']) ? wp_get_attachment_url((int) $e['att']) : ($e['lien'] ?? '');
            echo '<li><strong>' . esc_html($e['titre'] ?? '') . '</strong>';
            if (!empty($e['desc'])) echo ' — ' . esc_html($e['desc']);
            if ($url) echo '<br><span style="font-size:.85em;color:#666;word-break:break-all">' . esc_html($url) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }

    echo '<div style="margin-top:22px;border-top:1px solid #eee;padding-top:10px;font-size:.8em;color:#999">Sources : enquêtes de voisinage porte-à-porte menées par les groupes d\'action. Données anonymisées, agrégées au niveau national.</div>';
    echo '</div>'; // .lfi-dossier
    echo '</div>'; // .lfi-app

    echo '<style>@media print{body *{visibility:hidden!important}.lfi-dossier,.lfi-dossier *{visibility:visible!important}.lfi-dossier{position:absolute;left:0;top:0;width:100%;border:0;border-radius:0}.lfi-noprint{display:none!important}.lfi-public-install,.lfi-app-emergency{display:none!important}}</style>';
}
