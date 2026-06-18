<?php
/**
 * Réunion publique « Votre logement, votre droit » — 26 juin 2026
 * Salle de Diffusion · Confluences, 4 place du Muguet, Nantes.
 *
 * - Crée automatiquement la page WordPress `/reunion-26-juin-2026`
 *   avec le shortcode [lfi_nct_reunion_confluences].
 * - Crée une table dédiée des RSVP « Je participe ».
 * - Page admin pour voir la liste des inscrits.
 * - Le QR code du tract pointe vers cette page.
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_REUNION_CONFLUENCES_SLUG  = 'reunion-26-juin-2026';
const LFI_NCT_REUNION_CONFLUENCES_FLAG  = 'lfi_nct_reunion_confluences_page_created';
const LFI_NCT_REUNION_CONFLUENCES_DBVER = 'lfi_nct_reunion_confluences_db_ver';

/* ------------------------------------------------------------------ */
/* DB                                                                  */
/* ------------------------------------------------------------------ */

add_action('init', 'lfi_nct_reunion_confluences_db_setup', 5);
function lfi_nct_reunion_confluences_db_setup() {
    if (get_option(LFI_NCT_REUNION_CONFLUENCES_DBVER) === '1') return;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_reunion_rsvp';
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        prenom VARCHAR(120) DEFAULT '',
        nom VARCHAR(120) DEFAULT '',
        tel VARCHAR(40) DEFAULT '',
        email VARCHAR(190) DEFAULT '',
        avec_qui INT UNSIGNED DEFAULT 1,
        commentaire TEXT,
        ip VARCHAR(45) DEFAULT '',
        PRIMARY KEY (id),
        KEY created_at (created_at)
    ) $charset;");
    update_option(LFI_NCT_REUNION_CONFLUENCES_DBVER, '1', false);
}

/* ------------------------------------------------------------------ */
/* Création de la page WordPress (une seule fois)                      */
/* ------------------------------------------------------------------ */

add_action('init', 'lfi_nct_reunion_confluences_page_create', 25);
function lfi_nct_reunion_confluences_page_create() {
    if (get_option(LFI_NCT_REUNION_CONFLUENCES_FLAG) === 'done') return;
    $existing = get_page_by_path(LFI_NCT_REUNION_CONFLUENCES_SLUG);
    if ($existing) {
        update_option(LFI_NCT_REUNION_CONFLUENCES_FLAG, 'done', false);
        return;
    }
    $page_id = wp_insert_post([
        'post_title'    => 'Réunion publique — Votre logement, votre droit',
        'post_name'     => LFI_NCT_REUNION_CONFLUENCES_SLUG,
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_content'  => '[lfi_nct_reunion_confluences]',
        'post_author'   => 1,
        'comment_status'=> 'closed',
        'ping_status'   => 'closed',
    ], true);
    if (!is_wp_error($page_id) && $page_id) {
        update_option(LFI_NCT_REUNION_CONFLUENCES_FLAG, 'done', false);
    }
}

/* ------------------------------------------------------------------ */
/* Privacy & cache                                                     */
/* ------------------------------------------------------------------ */

add_action('wp', 'lfi_nct_reunion_confluences_no_cache');
function lfi_nct_reunion_confluences_no_cache() {
    if (!is_singular()) return;
    $post = get_post();
    if (!$post || $post->post_name !== LFI_NCT_REUNION_CONFLUENCES_SLUG) return;
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    do_action('litespeed_control_set_nocache', 'LFI : page avec nonce dynamique');
}

add_action('wp_enqueue_scripts', 'lfi_nct_reunion_confluences_assets');
function lfi_nct_reunion_confluences_assets() {
    if (!is_singular()) return;
    $post = get_post();
    if (!$post || $post->post_name !== LFI_NCT_REUNION_CONFLUENCES_SLUG) return;
    wp_enqueue_style('lfi-nct-css', LFI_NCT_URL . 'assets/css/form.css', [], LFI_NCT_VERSION);
}

/* ------------------------------------------------------------------ */
/* Shortcode public                                                    */
/* ------------------------------------------------------------------ */

add_shortcode('lfi_nct_reunion_confluences', 'lfi_nct_reunion_confluences_shortcode');
function lfi_nct_reunion_confluences_shortcode() {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_reunion_rsvp';

    $notice = '';
    $error  = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lfi_reunion_submit'])) {
        if (!isset($_POST['lfi_reunion_nonce']) || !wp_verify_nonce($_POST['lfi_reunion_nonce'], 'lfi_reunion_rsvp')) {
            $error = 'Sécurité : jeton invalide. Rechargez la page.';
        } else {
            $prenom = sanitize_text_field(wp_unslash($_POST['prenom'] ?? ''));
            $nom    = sanitize_text_field(wp_unslash($_POST['nom']    ?? ''));
            $tel    = sanitize_text_field(wp_unslash($_POST['tel']    ?? ''));
            $email  = sanitize_email(wp_unslash($_POST['email']       ?? ''));
            $avec   = max(1, min(20, (int) ($_POST['avec_qui'] ?? 1)));
            $com    = sanitize_textarea_field(wp_unslash($_POST['commentaire'] ?? ''));
            if ($prenom === '') {
                $error = 'Indiquez au moins votre prénom.';
            } elseif ($tel === '' && $email === '') {
                $error = 'Téléphone ou email — au moins l\'un des deux pour qu\'on puisse vous reconfirmer.';
            } else {
                $wpdb->insert($table, [
                    'prenom'      => $prenom,
                    'nom'         => $nom,
                    'tel'         => $tel,
                    'email'       => $email,
                    'avec_qui'    => $avec,
                    'commentaire' => $com,
                    'ip'          => substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45),
                ]);
                $notice = 'Merci ' . esc_html($prenom) . ' ! On compte sur vous le 26 juin. À très vite. 🌟';
            }
        }
    }

    $count = (int) $wpdb->get_var("SELECT COALESCE(SUM(avec_qui), 0) FROM $table");

    ob_start(); ?>
    <div class="lfi-survey lfi-reunion">
        <h2 style="color:#c8102e;margin:0 0 .4em">📣 Votre logement, votre droit</h2>
        <p style="font-size:1.05em;margin:0 0 1em">
            Réunion publique du Groupe d'Action LFI Nantes Sud Clos Toreau,
            avec les résultats de l'enquête de voisinage sur l'insalubrité.
        </p>

        <div class="lfi-info-box" style="background:#fff3f5;border-left:6px solid #c8102e">
            <div style="font-size:1.1em;font-weight:700;letter-spacing:.5px">VENDREDI 26 JUIN 2026 · 15h00 – 17h00</div>
            <div style="margin-top:.3em">📍 <strong>Salle de Diffusion — Confluences</strong>, 4 place du Muguet, Nantes</div>
            <div style="margin-top:.3em">Entrée libre — réunion ouverte à tous</div>
        </div>

        <h3 style="margin-top:1.5em">Au programme</h3>
        <ol>
            <li><strong>Résultats de l'enquête de voisinage</strong> — chiffres et témoignages à l'appui.</li>
            <li><strong>Vos droits et les recours possibles</strong> — démarches concrètes.</li>
            <li><strong>Questions / Réponses</strong> — partagez votre situation, on vous écoute.</li>
        </ol>

        <?php if ($notice): ?>
            <div class="lfi-success"><h2 style="margin:0 0 .4em">✅ Inscription enregistrée</h2><p><?php echo $notice; ?></p></div>
        <?php else: ?>

        <?php if ($error): ?>
            <div class="lfi-error"><strong>Erreur :</strong> <?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <h3 style="margin-top:1.5em">Je participe</h3>
        <p>
            <strong><?php echo $count; ?></strong> personne<?php echo $count > 1 ? 's' : ''; ?>
            <?php echo $count > 1 ? 'ont' : 'a'; ?> déjà confirmé sa venue. Rejoignez-nous !
        </p>
        <p class="lfi-help" style="margin-top:-.3em">
            Pas obligatoire — l'entrée est libre. Mais ça nous aide à prévoir les chaises et boissons.
        </p>

        <form method="POST" class="lfi-survey-simple">
            <?php wp_nonce_field('lfi_reunion_rsvp', 'lfi_reunion_nonce'); ?>
            <fieldset class="lfi-fieldset">
                <legend class="lfi-legend">Vos coordonnées</legend>
                <label class="lfi-field"><span class="lfi-label">Prénom <span class="req">*</span></span>
                    <input type="text" name="prenom" required></label>
                <label class="lfi-field"><span class="lfi-label">Nom</span>
                    <input type="text" name="nom"></label>
                <label class="lfi-field"><span class="lfi-label">Téléphone</span>
                    <input type="tel" name="tel" placeholder="06 12 34 56 78"></label>
                <label class="lfi-field"><span class="lfi-label">Email</span>
                    <input type="email" name="email" placeholder="vous@email.fr"></label>
                <p class="lfi-help">Téléphone <strong>ou</strong> email — au moins l'un des deux.</p>
            </fieldset>
            <fieldset class="lfi-fieldset">
                <legend class="lfi-legend">Vous venez à combien ?</legend>
                <div class="lfi-field">
                    <span class="lfi-label">Nombre de personnes (vous compris)</span>
                    <div class="lfi-evt-stepper">
                        <button type="button" class="lfi-step-minus" aria-label="Moins">−</button>
                        <input type="number" name="avec_qui" value="1" min="1" max="20" readonly inputmode="numeric">
                        <button type="button" class="lfi-step-plus"  aria-label="Plus">+</button>
                    </div>
                </div>
                <label class="lfi-field"><span class="lfi-label">Un mot, une question ?</span>
                    <textarea name="commentaire" rows="2" placeholder="Si vous voulez nous dire quelque chose avant la réunion"></textarea></label>
            </fieldset>
            <p><button type="submit" name="lfi_reunion_submit" class="lfi-btn lfi-btn-lg lfi-submit">✓ Je participe</button></p>
        </form>

        <?php endif; ?>

        <div class="lfi-info-box" style="margin-top:1.5em">
            🔒 <strong>RGPD</strong> : ces informations restent au Groupe d'Action LFI Nantes Sud Clos Toreau,
            sont utilisées uniquement pour vous reconfirmer la réunion, et ne sont jamais transmises à un tiers.
        </div>
    </div>
    <style>
    .lfi-evt-stepper {
        display: inline-flex; align-items: stretch; border: 2px solid #c8102e;
        border-radius: 10px; overflow: hidden; background: #fff;
        user-select: none; -webkit-user-select: none; margin-top: .3em;
    }
    .lfi-evt-stepper button {
        background: #c8102e; color: #fff; border: none; width: 56px; height: 56px;
        font-size: 1.8em; font-weight: 700; line-height: 1; cursor: pointer; touch-action: manipulation;
    }
    .lfi-evt-stepper button:hover, .lfi-evt-stepper button:active { background: #a30b25; }
    .lfi-evt-stepper button:disabled { background: #ddd; color: #999; cursor: not-allowed; }
    .lfi-evt-stepper input {
        width: 80px; text-align: center; font-size: 1.6em; font-weight: 700;
        border: none; background: #fff; color: #222; padding: 0; -moz-appearance: textfield;
    }
    .lfi-evt-stepper input::-webkit-outer-spin-button,
    .lfi-evt-stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .lfi-evt-stepper input:focus { outline: none; background: #fff3f5; }
    </style>
    <script>
    (function(){
        document.querySelectorAll('.lfi-evt-stepper').forEach(function(box){
            var input = box.querySelector('input[type=number]');
            var minus = box.querySelector('.lfi-step-minus');
            var plus  = box.querySelector('.lfi-step-plus');
            var min = parseInt(input.min, 10) || 1;
            var max = parseInt(input.max, 10) || 20;
            function refresh(){
                var v = parseInt(input.value, 10) || min;
                v = Math.max(min, Math.min(max, v));
                input.value = v;
                minus.disabled = (v <= min);
                plus.disabled  = (v >= max);
            }
            minus.addEventListener('click', function(){ input.value = (parseInt(input.value, 10) || min) - 1; refresh(); });
            plus.addEventListener('click',  function(){ input.value = (parseInt(input.value, 10) || min) + 1; refresh(); });
            refresh();
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* Admin — liste des inscrits                                          */
/* ------------------------------------------------------------------ */

add_action('admin_menu', 'lfi_nct_reunion_confluences_admin_menu', 38);
function lfi_nct_reunion_confluences_admin_menu() {
    add_submenu_page(
        'lfi-nct-responses',
        'Réunion 26 juin — inscrits',
        '📣 Inscrits réunion',
        'manage_options',
        'lfi-nct-reunion-rsvp',
        'lfi_nct_reunion_confluences_admin_page'
    );
}

add_action('admin_init', 'lfi_nct_reunion_confluences_csv_export', 1);
function lfi_nct_reunion_confluences_csv_export() {
    if (!current_user_can('manage_options')) return;
    if (($_GET['page'] ?? '') !== 'lfi-nct-reunion-rsvp') return;
    if (($_GET['export'] ?? '') !== 'csv') return;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_reunion_rsvp';
    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    while (ob_get_level() > 0) { ob_end_clean(); }
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lfi-reunion-26juin-' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID','Reçu le','Prénom','Nom','Téléphone','Email','Nb personnes','Commentaire'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [$r->id, $r->created_at, $r->prenom, $r->nom, $r->tel, $r->email, $r->avec_qui, $r->commentaire], ';');
    }
    fclose($out);
    exit;
}

function lfi_nct_reunion_confluences_admin_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_reunion_rsvp';

    if (isset($_GET['del']) && check_admin_referer('lfi_reunion_del_' . (int) $_GET['del'])) {
        $wpdb->delete($table, ['id' => (int) $_GET['del']]);
        wp_safe_redirect(admin_url('admin.php?page=lfi-nct-reunion-rsvp&deleted=1'));
        exit;
    }

    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 1000");
    $total_inscrits = count($rows);
    $total_personnes = (int) $wpdb->get_var("SELECT COALESCE(SUM(avec_qui),0) FROM $table");
    $url_event = home_url('/' . LFI_NCT_REUNION_CONFLUENCES_SLUG . '/');
    ?>
    <div class="wrap">
        <h1>📣 Réunion 26 juin — inscrits</h1>
        <?php if (!empty($_GET['deleted'])): ?>
            <div class="notice notice-success is-dismissible"><p>Inscription supprimée.</p></div>
        <?php endif; ?>
        <p>
            <strong><?php echo $total_inscrits; ?></strong> inscription(s) ·
            <strong><?php echo $total_personnes; ?></strong> personne(s) annoncée(s) au total.
            <a href="<?php echo esc_url($url_event); ?>" class="button" target="_blank" rel="noopener">🔗 Voir la page</a>
            <a href="?page=lfi-nct-reunion-rsvp&export=csv" class="button button-primary">📥 Exporter CSV</a>
        </p>
        <p class="description">
            Lien public de la page : <code><?php echo esc_html($url_event); ?></code><br>
            Ce lien est encodé dans le QR code du tract.
        </p>

        <table class="wp-list-table widefat striped">
            <thead>
                <tr><th>#</th><th>Inscrit le</th><th>Prénom</th><th>Nom</th><th>Tél</th><th>Email</th><th>Nb</th><th>Commentaire</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9"><em>Aucune inscription pour l'instant. Partagez le tract&nbsp;!</em></td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td>#<?php echo (int) $r->id; ?></td>
                    <td><?php echo esc_html($r->created_at); ?></td>
                    <td><?php echo esc_html($r->prenom); ?></td>
                    <td><?php echo esc_html($r->nom); ?></td>
                    <td><?php echo $r->tel ? '<a href="tel:' . esc_attr($r->tel) . '">' . esc_html($r->tel) . '</a>' : ''; ?></td>
                    <td><?php echo $r->email ? '<a href="mailto:' . esc_attr($r->email) . '">' . esc_html($r->email) . '</a>' : ''; ?></td>
                    <td><?php echo (int) $r->avec_qui; ?></td>
                    <td><?php echo esc_html($r->commentaire); ?></td>
                    <td>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=lfi-nct-reunion-rsvp&del=' . (int) $r->id), 'lfi_reunion_del_' . (int) $r->id)); ?>"
                           class="button button-small"
                           onclick="return confirm('Supprimer cette inscription ?')">🗑</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
