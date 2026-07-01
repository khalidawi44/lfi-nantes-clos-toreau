<?php
/**
 * Rendez-vous : prise de RDV via Telegram (ouvre l'appli sur mobile),
 * appel téléphonique, et formulaire de demande (date + tél/email, un des deux
 * obligatoire) enregistré en base et consultable dans l'admin.
 * Le tout est injecté sur la page /rendez-vous.
 */
if (!defined('ABSPATH')) exit;

if (!defined('LFI_NCT_TELEGRAM_INVITE')) {
    define('LFI_NCT_TELEGRAM_INVITE', 'https://t.me/+bt8Wm58ejXxlYWRk');
}
if (!defined('LFI_NCT_TELEGRAM_RDV')) {
    define('LFI_NCT_TELEGRAM_RDV', 'https://t.me/c/3045636337/37');
}
if (!defined('LFI_NCT_TEL')) {
    define('LFI_NCT_TEL', '0623526074');
}

/* ------------------------------------------------------------------ */
/* Base de données                                                     */
/* ------------------------------------------------------------------ */

function lfi_nct_rdv_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_rdv';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        date_souhaitee DATE DEFAULT NULL,
        creneau VARCHAR(50) DEFAULT NULL,
        prenom VARCHAR(100) DEFAULT NULL,
        nom VARCHAR(100) DEFAULT NULL,
        tel VARCHAR(30) DEFAULT NULL,
        email VARCHAR(150) DEFAULT NULL,
        motif TEXT DEFAULT NULL,
        PRIMARY KEY (id),
        KEY created_at (created_at)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Crée la table sans dépendre du hook d'activation (déploiement via Git).
add_action('init', 'lfi_nct_rdv_maybe_create_table');
function lfi_nct_rdv_maybe_create_table() {
    if (get_option('lfi_nct_rdv_db_v') !== '1') {
        lfi_nct_rdv_create_table();
        update_option('lfi_nct_rdv_db_v', '1', false);
    }
}

/* ------------------------------------------------------------------ */
/* Téléphone                                                           */
/* ------------------------------------------------------------------ */

function lfi_nct_tel_href() {
    $raw = preg_replace('/\D+/', '', LFI_NCT_TEL);
    if (strpos($raw, '0') === 0) {
        $raw = '+33' . substr($raw, 1);
    }
    return 'tel:' . $raw;
}

function lfi_nct_tel_display() {
    return trim(chunk_split(LFI_NCT_TEL, 2, ' '));
}

/* ------------------------------------------------------------------ */
/* Traitement du formulaire                                            */
/* ------------------------------------------------------------------ */

add_action('template_redirect', 'lfi_nct_rdv_handle');
function lfi_nct_rdv_handle() {
    if (empty($_POST['lfi_nct_rdv_nonce'])) return;
    if (!wp_verify_nonce($_POST['lfi_nct_rdv_nonce'], 'lfi_nct_rdv')) {
        $GLOBALS['lfi_nct_rdv_msg'] = ['error', 'Session expirée, recharge la page et réessaie.'];
        return;
    }

    $tel   = sanitize_text_field(wp_unslash($_POST['rdv_tel'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['rdv_email'] ?? ''));
    if ($tel === '' && $email === '') {
        $GLOBALS['lfi_nct_rdv_msg'] = ['error', 'Indiquez au moins un téléphone ou un email.'];
        return;
    }

    $date = sanitize_text_field(wp_unslash($_POST['rdv_date'] ?? ''));
    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = '';
    }

    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'lfi_nct_rdv', [
        'date_souhaitee' => $date !== '' ? $date : null,
        'creneau' => sanitize_text_field(wp_unslash($_POST['rdv_creneau'] ?? '')),
        'prenom'  => sanitize_text_field(wp_unslash($_POST['rdv_prenom'] ?? '')),
        'nom'     => sanitize_text_field(wp_unslash($_POST['rdv_nom'] ?? '')),
        'tel'     => $tel,
        'email'   => $email,
        'motif'   => sanitize_textarea_field(wp_unslash($_POST['rdv_motif'] ?? '')),
    ]);

    $GLOBALS['lfi_nct_rdv_msg'] = ['success', 'Votre demande de rendez-vous a bien été envoyée. On vous recontacte rapidement.'];
}

/* ------------------------------------------------------------------ */
/* Affichage sur la page /rendez-vous                                  */
/* ------------------------------------------------------------------ */

add_filter('the_content', 'lfi_nct_rdv_inject', 15);
function lfi_nct_rdv_inject($content) {
    if (is_admin() || !in_the_loop() || !is_main_query() || !is_page()) return $content;
    $post = get_post();
    if (!$post || $post->post_name !== 'rendez-vous') return $content;
    return lfi_nct_rdv_contact_box() . lfi_nct_rdv_form() . $content;
}

function lfi_nct_rdv_contact_box() {
    $invite   = esc_url(LFI_NCT_TELEGRAM_INVITE);
    $fil      = esc_url(LFI_NCT_TELEGRAM_RDV);
    $tel_href = esc_url(lfi_nct_tel_href());
    $tel_disp = esc_html(lfi_nct_tel_display());
    ob_start(); ?>
    <div class="lfi-rdv-card">
        <h2 class="lfi-rdv-title">📅 Prendre rendez-vous</h2>
        <p class="lfi-rdv-why">Un souci de logement, une démarche administrative ou juridique qui coince, ou simplement besoin d'un coup de main&nbsp;? Nos bénévoles vous reçoivent <strong>gratuitement et en toute confidentialité</strong> pour vous écouter et vous accompagner.</p>
        <p class="lfi-rdv-sub">Choisissez ce qui vous arrange :</p>
        <div class="lfi-rdv-actions">
            <a class="lfi-btn lfi-btn-tg lfi-popup" href="<?php echo $invite; ?>" target="_blank" rel="noopener">Rejoindre le groupe Telegram</a>
            <a class="lfi-btn lfi-btn-tel" href="<?php echo $tel_href; ?>">📞 Appeler (<?php echo $tel_disp; ?>)</a>
        </div>
        <p class="lfi-help">Déjà membre du groupe&nbsp;? <a class="lfi-popup" href="<?php echo $fil; ?>" target="_blank" rel="noopener">Accéder au fil rendez-vous</a>.</p>
    </div>
    <?php
    return ob_get_clean();
}

function lfi_nct_rdv_form() {
    $msg = $GLOBALS['lfi_nct_rdv_msg'] ?? null;
    ob_start(); ?>
    <div class="lfi-rdv-card">
        <h2 class="lfi-rdv-title">Demander un rendez-vous en ligne</h2>
        <p class="lfi-rdv-sub">Laissez vos coordonnées : un·e bénévole vous recontacte pour fixer le créneau.</p>
        <?php if ($msg): ?>
            <div class="lfi-<?php echo $msg[0] === 'success' ? 'success' : 'error'; ?>"><?php echo esc_html($msg[1]); ?></div>
        <?php endif; ?>
        <?php if (!$msg || $msg[0] !== 'success'): ?>
        <form method="post" class="lfi-rdv-fields">
            <?php wp_nonce_field('lfi_nct_rdv', 'lfi_nct_rdv_nonce'); ?>
            <div class="lfi-rdv-row">
                <label class="lfi-field">
                    <span class="lfi-label">Date souhaitée</span>
                    <input type="date" name="rdv_date" min="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                </label>
                <label class="lfi-field">
                    <span class="lfi-label">Créneau</span>
                    <select name="rdv_creneau">
                        <option value="">— indifférent —</option>
                        <option>Matin</option>
                        <option>Après-midi</option>
                        <option>Soir</option>
                    </select>
                </label>
            </div>
            <div class="lfi-rdv-row">
                <label class="lfi-field">
                    <span class="lfi-label">Prénom</span>
                    <input type="text" name="rdv_prenom">
                </label>
                <label class="lfi-field">
                    <span class="lfi-label">Nom</span>
                    <input type="text" name="rdv_nom">
                </label>
            </div>
            <div class="lfi-rdv-row">
                <label class="lfi-field">
                    <span class="lfi-label">Téléphone</span>
                    <input type="tel" name="rdv_tel" placeholder="06 12 34 56 78">
                </label>
                <label class="lfi-field">
                    <span class="lfi-label">Email</span>
                    <input type="email" name="rdv_email" placeholder="vous@email.fr">
                </label>
            </div>
            <p class="lfi-help">Indiquez au moins un téléphone <strong>ou</strong> un email.</p>
            <label class="lfi-field">
                <span class="lfi-label">Motif (optionnel)</span>
                <textarea name="rdv_motif" rows="3" placeholder="Ex : problème d'humidité, aide pour un courrier à NMH…"></textarea>
            </label>
            <button type="submit" class="lfi-btn lfi-btn-lg">Envoyer la demande</button>
        </form>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* Admin : liste des demandes                                          */
/* ------------------------------------------------------------------ */

add_action('admin_menu', 'lfi_nct_rdv_admin_menu', 30);
function lfi_nct_rdv_admin_menu() {
    add_submenu_page(
        'lfi-nct-responses',
        'Demandes de rendez-vous',
        '📅 Rendez-vous',
        'manage_options',
        'lfi-nct-rdv',
        'lfi_nct_rdv_admin_page'
    );
}

function lfi_nct_rdv_admin_page() {
    if (!current_user_can('manage_options')) return;
    if (function_exists('lfi_nct_admin_app_landing')) {
        lfi_nct_admin_app_landing('agenda', '📅 Rendez-vous', 'Les rendez-vous et l\'agenda sont dans l\'app.');
        return;
    }
    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}lfi_nct_rdv ORDER BY created_at DESC LIMIT 300");
    ?>
    <div class="wrap">
        <h1>Demandes de rendez-vous <?php echo lfi_nct_print_button('Imprimer la liste'); ?></h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr><th>Reçu le</th><th>Date souhaitée</th><th>Créneau</th><th>Prénom Nom</th><th>Téléphone</th><th>Email</th><th>Motif</th></tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7">Aucune demande pour l'instant.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo esc_html($r->created_at); ?></td>
                        <td><?php echo esc_html($r->date_souhaitee); ?></td>
                        <td><?php echo esc_html($r->creneau); ?></td>
                        <td><?php echo esc_html(trim($r->prenom . ' ' . $r->nom)); ?></td>
                        <td><?php echo esc_html($r->tel); ?></td>
                        <td><?php echo esc_html($r->email); ?></td>
                        <td><?php echo esc_html($r->motif); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
