<?php
/**
 * COMPTABILITÉ — Clos Toreau uniquement (compta micro-entrepreneur de
 * l'accompagnement des locataires, factures émises au bailleur).
 *
 * S'appuie sur la table existante wp_lfi_nct_interventions (factures) et les
 * paramètres per-owner (prestataire, bailleur, délai). Ajoute :
 *   - un tableau de bord compta (CA encaissé / à recouvrer / en retard,
 *     par trimestre, provision URSSAF) ;
 *   - les échéances & alertes de retard ;
 *   - des relances email préconfigurées (semi-automatiques) + journal ;
 *   - un export structuré (registre CSV + factures par année/mois, point-fixe).
 *
 * Le transfert automatique vers Google Drive viendra en complément.
 */
if (!defined('ABSPATH')) exit;

/** Accès compta : admin, et UNIQUEMENT sur l'espace Clos Toreau (home). */
function lfi_nct_compta_can() {
    $can = function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options');
    if (!$can) return false;
    $slug = function_exists('lfi_nct_scope_ga_slug') ? lfi_nct_scope_ga_slug() : '';
    return ($slug === '' || $slug === 'clos-toreau');
}

function lfi_nct_compta_table() { global $wpdb; return $wpdb->prefix . 'lfi_nct_interventions'; }

/** Date d'échéance d'une facture = date de facture + délai de paiement. */
function lfi_nct_compta_echeance($facture_date) {
    if (empty($facture_date)) return '';
    $delai = function_exists('lfi_nct_fact_delai') ? (int) lfi_nct_fact_delai() : 30;
    return wp_date('Y-m-d', strtotime($facture_date . ' +' . $delai . ' days'));
}

/** Agrégats compta pour le propriétaire courant. */
function lfi_nct_compta_stats() {
    global $wpdb;
    $t = lfi_nct_compta_table();
    $owner = (int) lfi_nct_fact_owner_id();
    $where = $wpdb->prepare('owner_user_id = %d', $owner);

    $encaisse   = (float) $wpdb->get_var("SELECT COALESCE(SUM(total_ht),0) FROM $t WHERE $where AND statut='paye'");
    $a_recouvrer= (float) $wpdb->get_var("SELECT COALESCE(SUM(total_ht),0) FROM $t WHERE $where AND statut='facture'");
    $ca_total   = (float) $wpdb->get_var("SELECT COALESCE(SUM(total_ht),0) FROM $t WHERE $where AND statut IN ('realise','facture','paye')");
    $nb_impaye  = (int)   $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE $where AND statut='facture'");

    /* Retards : facturé non payé dont l'échéance est dépassée. */
    $today = current_time('Y-m-d');
    $unpaid = $wpdb->get_results("SELECT facture_numero, facture_date, total_ht, bailleur, tenant_nom, tenant_prenom, tenant_adresse FROM $t WHERE $where AND statut='facture' AND facture_date IS NOT NULL ORDER BY facture_date ASC") ?: [];
    $retards = []; $retard_montant = 0; $next_echeance = '';
    foreach ($unpaid as $r) {
        $ech = lfi_nct_compta_echeance($r->facture_date);
        $r->echeance = $ech;
        $r->jours_retard = ($ech && $today > $ech) ? (int) floor((strtotime($today) - strtotime($ech)) / 86400) : 0;
        if ($r->jours_retard > 0) { $retards[] = $r; $retard_montant += (float) $r->total_ht; }
        elseif ($ech && ($next_echeance === '' || $ech < $next_echeance)) { $next_echeance = $ech; }
    }

    /* Encaissé par trimestre (année en cours). */
    $year = (int) wp_date('Y');
    $trim = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
    foreach ($wpdb->get_results("SELECT paye_date, total_ht FROM $t WHERE $where AND statut='paye' AND paye_date IS NOT NULL") ?: [] as $r) {
        if ((int) wp_date('Y', strtotime($r->paye_date)) !== $year) continue;
        $m = (int) wp_date('n', strtotime($r->paye_date));
        $trim[(int) ceil($m / 3)] += (float) $r->total_ht;
    }

    /* Provision URSSAF (taux réglé par l'utilisateur, sinon non calculée). */
    $taux = (float) get_user_meta($owner, 'lfi_nct_compta_urssaf', true);
    $provision = $taux > 0 ? round($encaisse * $taux / 100, 2) : null;

    return compact('encaisse', 'a_recouvrer', 'ca_total', 'nb_impaye', 'retards', 'retard_montant', 'next_echeance', 'trim', 'taux', 'provision', 'year');
}

/** Modèles de relance préconfigurés. */
function lfi_nct_compta_relance_templates() {
    return [
        'j30' => ['label' => 'Rappel (J+30) — ton courtois', 'objet' => 'Rappel — facture {num}'],
        'j45' => ['label' => 'Relance ferme (J+45)',         'objet' => 'Relance — facture {num} impayée'],
        'j60' => ['label' => 'Mise en demeure (J+60)',       'objet' => 'Mise en demeure — facture {num}'],
    ];
}

/** Corps d'une relance, variables remplies. */
function lfi_nct_compta_relance_corps($level, $vars) {
    $presta = function_exists('lfi_nct_fact_prestataire') ? lfi_nct_fact_prestataire() : [];
    $sign = "\n\nCordialement,\n" . ($presta['nom'] ?? '');
    switch ($level) {
        case 'j45':
            $c = "Madame, Monsieur,\n\nSauf erreur de notre part, la facture {num} d'un montant de {montant}, échue le {echeance}, demeure impayée à ce jour ({jours} jours de retard).\n\nNous vous remercions de bien vouloir procéder à son règlement sous 8 jours. À défaut, nous serons contraints d'engager une procédure de recouvrement.";
            break;
        case 'j60':
            $c = "Madame, Monsieur,\n\nMALGRÉ NOS RELANCES, la facture {num} d'un montant de {montant}, échue le {echeance}, reste impayée ({jours} jours de retard).\n\nLa présente vaut MISE EN DEMEURE de payer sous 8 jours. Passé ce délai, des pénalités de retard seront appliquées et le dossier sera transmis pour recouvrement contentieux.";
            break;
        case 'j30':
        default:
            $c = "Madame, Monsieur,\n\nNous nous permettons de vous rappeler que la facture {num} d'un montant de {montant} est arrivée à échéance le {echeance}.\n\nNous vous remercions de bien vouloir procéder à son règlement. Si c'est déjà fait, merci de ne pas tenir compte de ce message.";
            break;
    }
    return strtr($c . $sign, $vars);
}

/* ============================================================== *
 *  Tableau de bord compta                                         *
 * ============================================================== */
function lfi_nct_app_view_compta() {
    if (!lfi_nct_compta_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $owner = (int) lfi_nct_fact_owner_id();

    if (!empty($_POST['lfi_compta_urssaf']) && check_admin_referer('lfi_compta_urssaf')) {
        $tx = str_replace(',', '.', (string) wp_unslash($_POST['taux'] ?? ''));
        $tx = max(0, min(100, (float) $tx));
        update_user_meta($owner, 'lfi_nct_compta_urssaf', $tx);
        wp_safe_redirect(lfi_nct_app_url('compta', ['saved' => 1]));
        exit;
    }

    $s = lfi_nct_compta_stats();
    $eur = function_exists('lfi_nct_fact_format_eur') ? 'lfi_nct_fact_format_eur' : function ($n) { return number_format((float) $n, 2, ',', ' ') . ' €'; };

    lfi_nct_app_screen_open('💶 Comptabilité — Clos Toreau', 'CA, encaissements, retards et provisions');
    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Taux enregistré.');

    echo '<div class="lfi-app-stats-grid">';
    echo '<div class="stat"><div class="ico">💰</div><div class="n">' . esc_html(call_user_func($eur, $s['encaisse'])) . '</div><div class="l">Encaissé</div></div>';
    echo '<div class="stat"><div class="ico">🧾</div><div class="n">' . esc_html(call_user_func($eur, $s['a_recouvrer'])) . '</div><div class="l">À recouvrer</div></div>';
    echo '<div class="stat"><div class="ico">⏰</div><div class="n">' . esc_html(call_user_func($eur, $s['retard_montant'])) . '</div><div class="l">En retard (' . count($s['retards']) . ')</div></div>';
    echo '<div class="stat"><div class="ico">📈</div><div class="n">' . esc_html(call_user_func($eur, $s['ca_total'])) . '</div><div class="l">CA total</div></div>';
    echo '</div>';

    /* Provision URSSAF */
    echo '<h3 style="margin:16px 0 6px">🏛 Provision URSSAF / cotisations</h3>';
    if ($s['provision'] !== null) {
        echo '<div class="lfi-app-card">À mettre de côté (' . esc_html(rtrim(rtrim(number_format($s['taux'], 2, ',', ' '), '0'), ',')) . ' % de l\'encaissé) : <strong>' . esc_html(call_user_func($eur, $s['provision'])) . '</strong></div>';
    }
    echo '<form method="post" class="lfi-app-form" style="margin-top:6px">';
    wp_nonce_field('lfi_compta_urssaf');
    echo '<input type="hidden" name="lfi_compta_urssaf" value="1">';
    echo '<label>Ton taux de cotisations (%) <small>— tu le connais mieux que moi ; ex. 21,2 % en libéral BNC. Laisse vide si tu ne veux pas de provision.</small><input type="text" name="taux" value="' . esc_attr($s['taux'] ? number_format($s['taux'], 2, ',', '') : '') . '" placeholder="Ex : 21,2"></label>';
    echo '<button type="submit" class="btn-ghost">💾 Enregistrer le taux</button>';
    echo '</form>';

    /* Par trimestre */
    echo '<h3 style="margin:16px 0 6px">📅 Encaissé par trimestre ' . (int) $s['year'] . '</h3>';
    echo '<div class="lfi-app-stats-grid">';
    foreach ([1, 2, 3, 4] as $q) {
        echo '<div class="stat"><div class="ico">T' . $q . '</div><div class="n" style="font-size:1.05em">' . esc_html(call_user_func($eur, $s['trim'][$q])) . '</div><div class="l">Trimestre ' . $q . '</div></div>';
    }
    echo '</div>';

    /* Retards + accès relances */
    echo '<div class="lfi-app-bulk-row" style="margin-top:14px">';
    echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('compta-relances')) . '">✉️ Relances (' . count($s['retards']) . ')</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('compta-export')) . '">📁 Export factures</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('interventions')) . '">🧾 Factures</a>';
    echo '</div>';

    if (!empty($s['retards'])) {
        echo '<h3 style="margin:16px 0 6px">⏰ Factures en retard</h3><ul class="lfi-app-list">';
        foreach ($s['retards'] as $r) {
            echo '<li class="lfi-app-card"><div class="head"><div class="who">' . esc_html($r->facture_numero) . '</div><div class="when" style="color:#c8102e">+' . (int) $r->jours_retard . ' j</div></div>';
            echo '<div class="meta"><span class="meta-chip">' . esc_html(call_user_func($eur, $r->total_ht)) . '</span><span class="meta-chip">échéance ' . esc_html(wp_date('j M Y', strtotime($r->echeance))) . '</span></div>';
            echo '<div class="row-actions" style="margin-top:6px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('compta-relances', ['num' => $r->facture_numero])) . '">✉️ Préparer une relance</a></div></li>';
        }
        echo '</ul>';
    } elseif ($s['next_echeance']) {
        echo '<div class="lfi-app-help">Prochaine échéance : <strong>' . esc_html(wp_date('j M Y', strtotime($s['next_echeance']))) . '</strong>. Aucune facture en retard 👍</div>';
    }

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  Relances email préconfigurées (semi-auto) + journal            *
 * ============================================================== */
function lfi_nct_app_view_compta_relances() {
    if (!lfi_nct_compta_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    global $wpdb;
    $t = lfi_nct_compta_table();
    $owner = (int) lfi_nct_fact_owner_id();
    $eur = function_exists('lfi_nct_fact_format_eur') ? 'lfi_nct_fact_format_eur' : function ($n) { return number_format((float) $n, 2, ',', ' ') . ' €'; };
    $bailleur = function_exists('lfi_nct_fact_bailleur') ? lfi_nct_fact_bailleur() : [];
    $to_default = $bailleur['agence_email'] ?? ($bailleur['email'] ?? '');

    /* Envoi d'une relance (semi-auto) + journal. */
    if (!empty($_POST['lfi_relance_send']) && check_admin_referer('lfi_compta_relance')) {
        $num   = sanitize_text_field(wp_unslash($_POST['num'] ?? ''));
        $level = sanitize_key($_POST['level'] ?? 'j30');
        $to    = sanitize_email(wp_unslash($_POST['to'] ?? ''));
        $obj   = sanitize_text_field(wp_unslash($_POST['objet'] ?? ''));
        $body  = sanitize_textarea_field(wp_unslash($_POST['body'] ?? ''));
        $ok = false;
        if (is_email($to) && $obj && $body) {
            $cc = 'nantessudclostoreau@gmail.com'; // archive interne
            $ok = wp_mail($to, $obj, $body, ['Cc: ' . $cc]);
        }
        if ($ok) {
            $log = get_user_meta($owner, 'lfi_nct_compta_relances', true); if (!is_array($log)) $log = [];
            $log[] = ['num' => $num, 'level' => $level, 'to' => $to, 'date' => current_time('mysql')];
            update_user_meta($owner, 'lfi_nct_compta_relances', $log);
        }
        wp_safe_redirect(lfi_nct_app_url('compta-relances', ['sent' => $ok ? 1 : 0]));
        exit;
    }

    $today = current_time('Y-m-d');
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE owner_user_id = %d AND statut='facture' AND facture_date IS NOT NULL ORDER BY facture_date ASC", $owner)) ?: [];
    $log = get_user_meta($owner, 'lfi_nct_compta_relances', true); if (!is_array($log)) $log = [];
    $log_by_num = [];
    foreach ($log as $l) { $log_by_num[$l['num']] = ($log_by_num[$l['num']] ?? 0) + 1; }

    $focus = isset($_GET['num']) ? sanitize_text_field(wp_unslash($_GET['num'])) : '';

    lfi_nct_app_screen_open('✉️ Relances de paiement', 'Semi-automatique — tu relis, tu envoies');
    if (isset($_GET['sent'])) lfi_nct_app_flash($_GET['sent'] ? '✅ Relance envoyée et journalisée.' : '⚠️ Envoi impossible (email manquant ?).', $_GET['sent'] ? 'ok' : 'err');

    echo '<div class="lfi-app-help">Les relances partent à l\'agence du bailleur, avec copie d\'archive à nantessudclostoreau@gmail.com. Tu peux modifier le texte avant d\'envoyer.</div>';

    if (empty($rows)) { echo '<div class="lfi-app-empty">Aucune facture en attente de paiement 👍</div>'; lfi_nct_app_screen_close(); return; }

    echo '<ul class="lfi-app-list">';
    foreach ($rows as $r) {
        $ech = lfi_nct_compta_echeance($r->facture_date);
        $jr  = ($ech && $today > $ech) ? (int) floor((strtotime($today) - strtotime($ech)) / 86400) : 0;
        $lvl = $jr >= 60 ? 'j60' : ($jr >= 45 ? 'j45' : ($jr >= 30 ? 'j30' : 'j30'));
        $vars = [
            '{num}'      => $r->facture_numero,
            '{montant}'  => call_user_func($eur, $r->total_ht),
            '{echeance}' => $ech ? wp_date('j M Y', strtotime($ech)) : '',
            '{jours}'    => $jr,
        ];
        $tpls = lfi_nct_compta_relance_templates();
        $objet = strtr($tpls[$lvl]['objet'], $vars);
        $body  = lfi_nct_compta_relance_corps($lvl, $vars);
        $open = ($focus !== '' && $focus === $r->facture_numero);

        echo '<li class="lfi-app-card">';
        echo '<div class="head"><div class="who">' . esc_html($r->facture_numero) . '</div><div class="when"' . ($jr > 0 ? ' style="color:#c8102e"' : '') . '>' . ($jr > 0 ? '+' . $jr . ' j' : 'à échoir') . '</div></div>';
        echo '<div class="meta"><span class="meta-chip">' . esc_html(call_user_func($eur, $r->total_ht)) . '</span><span class="meta-chip">échéance ' . esc_html($ech ? wp_date('j M', strtotime($ech)) : '?') . '</span>';
        if (!empty($log_by_num[$r->facture_numero])) echo '<span class="meta-chip">📜 ' . (int) $log_by_num[$r->facture_numero] . ' relance(s)</span>';
        echo '</div>';

        echo '<details class="lfi-app-collapse"' . ($open ? ' open' : '') . '><summary>✉️ Préparer la relance (' . esc_html($tpls[$lvl]['label']) . ')</summary>';
        echo '<form method="post" class="lfi-app-form" style="margin-top:8px">';
        wp_nonce_field('lfi_compta_relance');
        echo '<input type="hidden" name="lfi_relance_send" value="1"><input type="hidden" name="num" value="' . esc_attr($r->facture_numero) . '"><input type="hidden" name="level" value="' . esc_attr($lvl) . '">';
        echo '<label>Destinataire<input type="email" name="to" value="' . esc_attr($to_default) . '" required></label>';
        echo '<label>Objet<input type="text" name="objet" value="' . esc_attr($objet) . '" required></label>';
        echo '<label>Message<textarea name="body" rows="9" required>' . esc_textarea($body) . '</textarea></label>';
        echo '<button type="submit" class="btn-primary" onclick="return confirm(\'Envoyer cette relance ?\');">📤 Envoyer la relance</button>';
        echo '</form></details>';
        echo '</li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  Export structuré des factures (registre + factures par mois)   *
 *  Utilise le GÉNÉRATEUR UNIQUE lfi_nct_facture_render_html()      *
 *  → factures strictement identiques à la version officielle       *
 *    (mentions légales micro-entrepreneur conformes).              *
 * ============================================================== */
add_action('admin_post_lfi_nct_compta_export', 'lfi_nct_compta_export_download');
function lfi_nct_compta_export_download() {
    if (!current_user_can('manage_options')) wp_die('Réservé.');
    check_admin_referer('lfi_nct_compta_export');
    if (!class_exists('ZipArchive')) wp_die('ZipArchive non disponible.');
    global $wpdb;
    $t = lfi_nct_compta_table();
    $owner = (int) lfi_nct_fact_owner_id();

    $mode  = isset($_GET['mode']) ? sanitize_key($_GET['mode']) : 'full';
    $since = ($mode === 'incr') ? (string) get_option('lfi_nct_compta_export_checkpoint', '') : '';
    $now   = current_time('mysql');

    /* Une ligne PAR FACTURE (regroupe les interventions du même numéro). */
    $where = $wpdb->prepare('owner_user_id = %d', $owner) . " AND facture_numero IS NOT NULL AND facture_numero <> ''";
    if ($since !== '') $where .= $wpdb->prepare(' AND (facture_date > %s OR updated_at > %s)', $since, $since);
    $factures = $wpdb->get_results(
        "SELECT facture_numero AS num, MIN(facture_date) AS fd, SUM(total_ht) AS tot,
                MAX(paye_date) AS pd, MAX(bailleur) AS bailleur
         FROM $t WHERE $where GROUP BY facture_numero ORDER BY fd ASC"
    ) ?: [];

    @set_time_limit(0);
    $tmp = wp_tempnam('lfi-compta');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) wp_die('Archive impossible.');

    /* Registre CSV comptable (une ligne par facture) + factures HTML conformes. */
    $csv = [];
    foreach ($factures as $f) {
        $ech = lfi_nct_compta_echeance($f->fd);
        $statut = $f->pd ? 'paye' : 'facture';
        $csv[] = [$f->num, $f->fd, $ech, $f->bailleur, number_format((float) $f->tot, 2, ',', ''), $statut, $f->pd];
        $y = $f->fd ? wp_date('Y', strtotime($f->fd)) : '0000';
        $m = $f->fd ? wp_date('m', strtotime($f->fd)) : '00';
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $f->num);
        $inner = function_exists('lfi_nct_facture_render_html') ? lfi_nct_facture_render_html($f->num, $owner) : '';
        $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Facture ' . esc_html($f->num) . '</title></head><body style="max-width:760px;margin:16px auto">' . $inner . '</body></html>';
        $zip->addFromString("Factures/$y/$m/Facture-$safe.html", $html);
    }
    $header = ['Numero', 'Date facture', 'Echeance', 'Bailleur', 'Montant HT', 'Statut', 'Paye le'];
    $fh = fopen('php://temp', 'r+'); fputcsv($fh, $header); foreach ($csv as $c) fputcsv($fh, $c); rewind($fh);
    $zip->addFromString('registre-factures.csv', "\xEF\xBB\xBF" . stream_get_contents($fh)); fclose($fh);
    $zip->addFromString('_INFOS.txt', "Export factures Clos Toreau\nDate : $now\nType : " . ($mode === 'incr' ? 'Nouveautes' : 'Complet') . "\nRange dans : LFI/00_SITE_ET_ASSOCIATION/ (ou ta compta)\n");
    $zip->close();

    update_option('lfi_nct_compta_export_checkpoint', $now, false);

    $fname = 'Factures-ClosToreau-' . ($mode === 'incr' ? 'nouveautes-' : '') . wp_date('Y-m-d') . '.zip';
    nocache_headers();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

function lfi_nct_app_view_compta_export() {
    if (!lfi_nct_compta_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $cp = (string) get_option('lfi_nct_compta_export_checkpoint', '');
    lfi_nct_app_screen_open('📁 Export des factures', 'Registre comptable + factures par année/mois');
    echo '<div class="lfi-app-help">Télécharge tes factures rangées par année/mois + un registre CSV pour ta compta. Système de point-fixe : la fois d\'après, tu ne prends que les nouvelles.</div>';
    $full = wp_nonce_url(admin_url('admin-post.php?action=lfi_nct_compta_export&mode=full'), 'lfi_nct_compta_export');
    $incr = wp_nonce_url(admin_url('admin-post.php?action=lfi_nct_compta_export&mode=incr'), 'lfi_nct_compta_export');
    echo '<div style="display:flex;flex-direction:column;gap:10px;margin:14px 0">';
    echo '<a class="btn-primary big" href="' . esc_url($full) . '">⬇️ Export COMPLET des factures</a>';
    if ($cp !== '') echo '<a class="btn-ghost" href="' . esc_url($incr) . '">🔄 Seulement les nouvelles depuis le ' . esc_html(wp_date('j M Y', strtotime($cp))) . '</a>';
    echo '</div>';
    echo '<div class="lfi-app-help" style="background:#f7f7f7"><small>Le transfert automatique vers Google Drive (pour que tout arrive seul dans ton dossier local) sera branché ensuite.</small></div>';
    lfi_nct_app_screen_close();
}
