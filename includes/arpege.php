<?php
/**
 * Bloqués CPAM (Arpège) — outil pour les assurés bloqués par le
 * déploiement du nouveau SI CNAM (test Loire-Atlantique / Vendée).
 *
 * - Page publique avec formulaire de signalement.
 * - À l'envoi : courrier de Commission de Recours Amiable (CRA) pré-rempli,
 *   imprimable, à signer et envoyer en LRAR.
 * - Stockage en base + page d'admin (liste + export CSV) pour constituer
 *   le dossier collectif.
 */
if (!defined('ABSPATH')) exit;

if (!defined('LFI_NCT_ARPEGE_SLUG')) {
    define('LFI_NCT_ARPEGE_SLUG', 'arpege-cpam-bloque');
}

/* ------------------------------------------------------------------ */
/* Base de données                                                     */
/* ------------------------------------------------------------------ */

function lfi_nct_arpege_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_arpege';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        departement VARCHAR(10) DEFAULT NULL,
        type_arret VARCHAR(50) DEFAULT NULL,
        date_premier_blocage DATE DEFAULT NULL,
        montant_manquant DECIMAL(10,2) DEFAULT NULL,
        prenom VARCHAR(100) DEFAULT NULL,
        nom VARCHAR(100) DEFAULT NULL,
        tel VARCHAR(30) DEFAULT NULL,
        email VARCHAR(150) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        statut VARCHAR(20) DEFAULT 'nouveau',
        PRIMARY KEY (id),
        KEY created_at (created_at),
        KEY departement (departement)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

add_action('init', 'lfi_nct_arpege_maybe_create_table');
function lfi_nct_arpege_maybe_create_table() {
    if (get_option('lfi_nct_arpege_db_v') !== '1') {
        lfi_nct_arpege_create_table();
        update_option('lfi_nct_arpege_db_v', '1', false);
    }
}

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */

function lfi_nct_arpege_dept_label($dept) {
    $map = ['44' => 'Loire-Atlantique', '85' => 'Vendée', 'autre' => 'autre département'];
    return $map[$dept] ?? $dept;
}

function lfi_nct_arpege_dept_cpam($dept) {
    $map = [
        '44' => 'CPAM de Loire-Atlantique, Nantes',
        '85' => 'CPAM de la Vendée, La Roche-sur-Yon',
    ];
    return $map[$dept] ?? 'CPAM de votre département';
}

function lfi_nct_arpege_type_label($t) {
    $map = [
        'maladie'   => 'arrêt maladie',
        'mi_temps'  => 'mi-temps thérapeutique',
        'atmp'      => 'accident du travail / maladie professionnelle',
        'maternite' => 'congé maternité',
        'paternite' => 'congé paternité / second parent',
    ];
    return $map[$t] ?? $t;
}

/* ------------------------------------------------------------------ */
/* Traitement du formulaire                                            */
/* ------------------------------------------------------------------ */

add_action('template_redirect', 'lfi_nct_arpege_handle');
function lfi_nct_arpege_handle() {
    if (empty($_POST['lfi_nct_arpege_nonce'])) return;
    if (!wp_verify_nonce($_POST['lfi_nct_arpege_nonce'], 'lfi_nct_arpege')) {
        $GLOBALS['lfi_nct_arpege_msg'] = ['error', 'Session expirée, rechargez la page et réessayez.'];
        return;
    }

    $tel   = sanitize_text_field(wp_unslash($_POST['arp_tel'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['arp_email'] ?? ''));
    if ($tel === '' && $email === '') {
        $GLOBALS['lfi_nct_arpege_msg'] = ['error', 'Indiquez au moins un téléphone ou un email pour qu\'on puisse vous recontacter.'];
        return;
    }

    $date = sanitize_text_field(wp_unslash($_POST['arp_date'] ?? ''));
    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = '';

    $montant_raw = $_POST['arp_montant'] ?? '';
    $montant = is_numeric($montant_raw) ? (float) $montant_raw : null;

    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'lfi_nct_arpege', [
        'departement'          => sanitize_text_field(wp_unslash($_POST['arp_dept'] ?? '')),
        'type_arret'           => sanitize_text_field(wp_unslash($_POST['arp_type'] ?? '')),
        'date_premier_blocage' => $date !== '' ? $date : null,
        'montant_manquant'     => $montant,
        'prenom'               => sanitize_text_field(wp_unslash($_POST['arp_prenom'] ?? '')),
        'nom'                  => sanitize_text_field(wp_unslash($_POST['arp_nom'] ?? '')),
        'tel'                  => $tel,
        'email'                => $email,
        'description'          => sanitize_textarea_field(wp_unslash($_POST['arp_desc'] ?? '')),
    ]);

    $GLOBALS['lfi_nct_arpege_msg']     = ['success', 'Votre situation est enregistrée.'];
    $GLOBALS['lfi_nct_arpege_last_id'] = (int) $wpdb->insert_id;
}

/* ------------------------------------------------------------------ */
/* Affichage public                                                    */
/* ------------------------------------------------------------------ */

add_filter('the_content', 'lfi_nct_arpege_inject', 15);
function lfi_nct_arpege_inject($content) {
    if (is_admin() || !in_the_loop() || !is_main_query() || !is_page()) return $content;
    $post = get_post();
    if (!$post || $post->post_name !== LFI_NCT_ARPEGE_SLUG) return $content;
    if (has_shortcode($post->post_content, 'lfi_nct_arpege')) return $content;
    return $content . do_shortcode('[lfi_nct_arpege]');
}

add_shortcode('lfi_nct_arpege', 'lfi_nct_arpege_shortcode');
function lfi_nct_arpege_shortcode() {
    $msg     = $GLOBALS['lfi_nct_arpege_msg'] ?? null;
    $last_id = $GLOBALS['lfi_nct_arpege_last_id'] ?? 0;
    if ($msg && $msg[0] === 'success' && $last_id > 0) {
        return lfi_nct_arpege_success_view($last_id);
    }
    return lfi_nct_arpege_form_view($msg);
}

function lfi_nct_arpege_form_view($msg) {
    ob_start(); ?>
    <div class="lfi-rdv-card lfi-arpege-card">
        <h2 class="lfi-rdv-title">🆘 Bloqué·e par la CPAM ? Vous n'êtes pas seul·e</h2>
        <p class="lfi-rdv-why">Vous attendez des indemnités journalières qui ne tombent pas&nbsp;? La CPAM de <strong>Loire-Atlantique et de Vendée</strong> bascule sur un nouveau logiciel (<strong>Arpège</strong>) qui plante sur beaucoup de dossiers — c'est documenté publiquement par les syndicats CGT-CPAM, SNFOCOS et la presse. Voici comment <strong>débloquer votre situation</strong> et nous aider à constituer un dossier collectif.</p>

        <?php if ($msg && $msg[0] === 'error'): ?>
            <div class="lfi-error"><?php echo esc_html($msg[1]); ?></div>
        <?php endif; ?>

        <form method="post" class="lfi-rdv-fields">
            <?php wp_nonce_field('lfi_nct_arpege', 'lfi_nct_arpege_nonce'); ?>
            <div class="lfi-rdv-row">
                <label class="lfi-field"><span class="lfi-label">Département</span>
                    <select name="arp_dept" required>
                        <option value="">— choisir —</option>
                        <option value="44">44 — Loire-Atlantique</option>
                        <option value="85">85 — Vendée</option>
                        <option value="autre">Autre</option>
                    </select>
                </label>
                <label class="lfi-field"><span class="lfi-label">Type d'arrêt</span>
                    <select name="arp_type" required>
                        <option value="">— choisir —</option>
                        <option value="maladie">Arrêt maladie classique</option>
                        <option value="mi_temps">Mi-temps thérapeutique</option>
                        <option value="atmp">Accident du travail / maladie pro</option>
                        <option value="maternite">Congé maternité</option>
                        <option value="paternite">Congé paternité / second parent</option>
                    </select>
                </label>
            </div>
            <div class="lfi-rdv-row">
                <label class="lfi-field"><span class="lfi-label">Date du 1er non-versement</span>
                    <input type="date" name="arp_date">
                </label>
                <label class="lfi-field"><span class="lfi-label">Montant approximatif manquant (€)</span>
                    <input type="number" name="arp_montant" min="0" step="1" placeholder="ex : 1200">
                </label>
            </div>
            <div class="lfi-rdv-row">
                <label class="lfi-field"><span class="lfi-label">Prénom</span><input type="text" name="arp_prenom"></label>
                <label class="lfi-field"><span class="lfi-label">Nom</span><input type="text" name="arp_nom"></label>
            </div>
            <div class="lfi-rdv-row">
                <label class="lfi-field"><span class="lfi-label">Téléphone</span><input type="tel" name="arp_tel" placeholder="06 12 34 56 78"></label>
                <label class="lfi-field"><span class="lfi-label">Email</span><input type="email" name="arp_email" placeholder="vous@email.fr"></label>
            </div>
            <p class="lfi-help">Indiquez au moins un téléphone <strong>ou</strong> un email — sans ça on ne peut pas vous recontacter.</p>
            <label class="lfi-field"><span class="lfi-label">Décrivez votre situation (court)</span>
                <textarea name="arp_desc" rows="4" placeholder="Ex : arrêt depuis le 12 mars, mi-temps thérapeutique, deux mois sans versement, plusieurs appels au 3646 sans réponse…"></textarea>
            </label>
            <button type="submit" class="lfi-btn lfi-btn-lg">Envoyer et obtenir mon courrier CRA</button>
        </form>

        <p class="lfi-help" style="margin-top:1.5em">🔒 Vos informations sont strictement internes au Groupe d'Action LFI Nantes Sud Clos Toreau, jamais transmises à un tiers sans votre accord.</p>
    </div>
    <?php
    return ob_get_clean();
}

function lfi_nct_arpege_success_view($id) {
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_arpege WHERE id = %d", $id));
    if (!$row) return '';

    $rdv_url        = esc_url(lfi_nct_page_url('rendez-vous'));
    $today_fr       = wp_date('j F Y');
    $name           = trim($row->prenom . ' ' . $row->nom);
    $type_label     = lfi_nct_arpege_type_label($row->type_arret);
    $cpam           = lfi_nct_arpege_dept_cpam($row->departement);
    $date_blocage_fr = $row->date_premier_blocage
        ? wp_date('j F Y', strtotime($row->date_premier_blocage))
        : '[date du 1er non-versement]';
    $montant = $row->montant_manquant
        ? number_format((float) $row->montant_manquant, 0, ',', ' ') . ' €'
        : '[montant manquant]';

    ob_start(); ?>
    <div class="lfi-rdv-card lfi-arpege-card">
        <h2 class="lfi-rdv-title">✅ Votre situation est enregistrée</h2>
        <p>Merci. Nous gardons votre cas pour notre <strong>dossier collectif</strong> (transmis à la Médiation de l'Assurance Maladie, au Défenseur des droits, à des député·es et aux syndicats CPAM).</p>
        <p>Un·e bénévole vous recontactera. En attendant, voici votre <strong>courrier de Commission de Recours Amiable (CRA) pré-rempli</strong>. <strong>Relisez-le, complétez les champs entre crochets, signez-le, et envoyez-le en lettre recommandée avec accusé de réception (LRAR).</strong></p>
        <p class="lfi-rdv-actions">
            <button type="button" class="lfi-btn lfi-btn-lg" onclick="window.print()">🖨️ Imprimer mon courrier CRA</button>
            <a class="lfi-btn lfi-btn-tg" href="<?php echo $rdv_url; ?>">📅 Prendre RDV avec un·e bénévole</a>
        </p>
        <p class="lfi-help">⚠️ Ce courrier est un <strong>modèle indicatif</strong>. Pour valider votre situation précise, faites-le relire par une permanence sociale (France Services, syndicat CGT-CPAM, défenseur syndical) avant envoi.</p>
    </div>

    <div class="lfi-arpege-letter">
        <p class="lfi-letter-meta">[Lieu], le <?php echo esc_html($today_fr); ?></p>

        <p>
            <strong><?php echo esc_html($name); ?></strong><br>
            [Adresse postale à compléter]<br>
            N° de Sécurité sociale&nbsp;: [à compléter]<br>
            <?php if ($row->tel): ?>Tél&nbsp;: <?php echo esc_html($row->tel); ?><br><?php endif; ?>
            <?php if ($row->email): ?>Courriel&nbsp;: <?php echo esc_html($row->email); ?><?php endif; ?>
        </p>

        <p>
            À l'attention de<br>
            Madame ou Monsieur le Président de la Commission de Recours Amiable<br>
            <?php echo esc_html($cpam); ?><br>
            [Adresse postale de la CPAM à compléter]
        </p>

        <p><strong>Objet&nbsp;:</strong> Saisine de la Commission de Recours Amiable — non-versement d'indemnités journalières<br>
        <strong>Lettre recommandée avec accusé de réception</strong></p>

        <p>Madame, Monsieur le Président,</p>

        <p>Je me trouve en <strong><?php echo esc_html($type_label); ?></strong>. Malgré le respect des procédures (transmission des volets 1 et 2 dans les 48 heures, fourniture des pièces demandées et démarches répétées auprès de vos services), je n'ai perçu aucune indemnité journalière depuis le <strong><?php echo esc_html($date_blocage_fr); ?></strong>, pour un montant estimé à <strong><?php echo esc_html($montant); ?></strong>.</p>

        <?php if ($row->description): ?>
        <p><em><?php echo nl2br(esc_html($row->description)); ?></em></p>
        <?php endif; ?>

        <p>Mes démarches (appels au 3646, courriers, passages en accueil) n'ont pas permis de débloquer la situation et aucune décision motivée ne m'a été notifiée.</p>

        <p>Je note par ailleurs que la <?php echo esc_html($cpam); ?> est en cours de bascule sur le nouveau système d'information <strong>Arpège</strong>, dont les dysfonctionnements affectant le traitement des dossiers d'indemnités journalières — en particulier les cas de <?php echo esc_html($type_label); ?> — sont publiquement documentés (alertes des syndicats CGT-CPAM et SNFOCOS, couverture médiatique). Ces dysfonctionnements semblent à l'origine du blocage de mon dossier.</p>

        <p>En conséquence, je sollicite&nbsp;:</p>
        <ol>
            <li>Le versement immédiat des indemnités journalières dues depuis le <?php echo esc_html($date_blocage_fr); ?>, soit <?php echo esc_html($montant); ?>.</li>
            <li>Le versement des intérêts de retard au taux légal en vigueur.</li>
            <li>La transmission par écrit des motifs du non-versement constaté à ce jour.</li>
            <li>Le traitement manuel de mon dossier en cas de blocage technique avéré du système d'information Arpège.</li>
        </ol>

        <p>À défaut de réponse favorable dans le délai légal de deux mois, je saisirai le pôle social du Tribunal judiciaire compétent. Je signale par ailleurs ma situation à la Médiation de l'Assurance Maladie et au Défenseur des droits, et tiens à votre disposition l'ensemble des pièces justificatives.</p>

        <p>Je vous prie d'agréer, Madame, Monsieur le Président, l'expression de mes salutations distinguées.</p>

        <p style="margin-top:3em">[Signature]<br><strong><?php echo esc_html($name); ?></strong></p>

        <p><em>PJ&nbsp;: copies des arrêts de travail, 12 derniers bulletins de salaire, relevés bancaires prouvant le non-versement, copies des échanges avec la CPAM.</em></p>
    </div>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* Admin                                                               */
/* ------------------------------------------------------------------ */

add_action('admin_menu', 'lfi_nct_arpege_admin_menu', 40);
function lfi_nct_arpege_admin_menu() {
    add_submenu_page(
        'lfi-nct-responses',
        'Cas Arpège bloqués',
        '🆘 Bloqués CPAM',
        'manage_options',
        'lfi-nct-arpege',
        'lfi_nct_arpege_admin_page'
    );
}

/**
 * Export CSV Arpège : déclenché tôt sur admin_init pour ne pas se faire
 * polluer par le HTML de l'admin (cf. même bug que l'export enquête).
 */
add_action('admin_init', 'lfi_nct_arpege_handle_csv_export', 1);
function lfi_nct_arpege_handle_csv_export() {
    if (!current_user_can('manage_options')) return;
    $page   = isset($_GET['page'])   ? (string) $_GET['page']   : '';
    $export = isset($_GET['export']) ? (string) $_GET['export'] : '';
    if ($page !== 'lfi-nct-arpege' || $export !== 'csv') return;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_arpege';
    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    while (ob_get_level() > 0) { ob_end_clean(); }
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lfi-arpege-' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID','Reçu le','Département','Type arrêt','Date blocage','Montant manquant','Prénom','Nom','Téléphone','Email','Description','Statut'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [$r->id, $r->created_at, $r->departement, $r->type_arret, $r->date_premier_blocage, $r->montant_manquant, $r->prenom, $r->nom, $r->tel, $r->email, $r->description, $r->statut], ';');
    }
    fclose($out);
    exit;
}

function lfi_nct_arpege_admin_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_arpege';

    $rows  = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 500");
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    ?>
    <div class="wrap">
        <h1>🆘 Cas Arpège bloqués <?php echo lfi_nct_print_button('Imprimer la liste'); ?></h1>
        <p><strong><?php echo $total; ?></strong> cas enregistré(s). <a href="?page=lfi-nct-arpege&export=csv" class="button">📥 Exporter CSV</a></p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr><th>Reçu le</th><th>Dpt</th><th>Type</th><th>Date blocage</th><th>Montant</th><th>Prénom Nom</th><th>Téléphone</th><th>Email</th><th>Description</th></tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9">Aucun cas enregistré pour l'instant.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo esc_html($r->created_at); ?></td>
                        <td><?php echo esc_html($r->departement); ?></td>
                        <td><?php echo esc_html(lfi_nct_arpege_type_label($r->type_arret)); ?></td>
                        <td><?php echo esc_html($r->date_premier_blocage); ?></td>
                        <td><?php echo $r->montant_manquant ? esc_html(number_format((float) $r->montant_manquant, 0, ',', ' ')) . ' €' : '—'; ?></td>
                        <td><?php echo esc_html(trim($r->prenom . ' ' . $r->nom)); ?></td>
                        <td><?php echo esc_html($r->tel); ?></td>
                        <td><?php echo esc_html($r->email); ?></td>
                        <td><?php echo esc_html(mb_substr((string) $r->description, 0, 200)); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
