<?php
/**
 * Module unifié des Événements LFI.
 *
 * Stratégie : on utilise directement le CPT « ag_evenement » du thème
 * AG Starter Association quand il est présent. Si le thème change, on
 * bascule sur un fallback « lfi_evenement » pour ne rien perdre.
 *
 * On AJOUTE par-dessus :
 *   - une metabox « Détails LFI » (capacité, toggle RSVP, URL Action Populaire)
 *   - un bandeau d'infos + formulaire « Je participe » sur la page single
 *     (via filtre sur the_content, pas de template à mettre dans le thème)
 *   - la table wp_lfi_nct_event_rsvp et la page admin « Inscriptions »
 *   - des helpers utilisés par le module SMS et le partage social
 *   - le seed idempotent de la réunion du 26 juin
 *   - une page Diag avec bouton « Vider les événements démo du thème »
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_EVT_CPT_THEME    = 'ag_evenement';
const LFI_NCT_EVT_CPT_FALLBACK = 'lfi_evenement';
const LFI_NCT_EVT_RSVP_DBVER   = 'lfi_nct_event_rsvp_db_ver';
const LFI_NCT_EVT_RSVP_TABLE   = 'lfi_nct_event_rsvp';

/* ------------------------------------------------------------------ */
/* Détection du CPT actif                                              */
/* ------------------------------------------------------------------ */

function lfi_nct_event_cpt() {
    static $cached = null;
    if ($cached !== null) return $cached;
    if (post_type_exists(LFI_NCT_EVT_CPT_THEME)) {
        $cached = LFI_NCT_EVT_CPT_THEME;
    } else {
        $cached = LFI_NCT_EVT_CPT_FALLBACK;
    }
    return $cached;
}

/* ------------------------------------------------------------------ */
/* Fallback CPT (seulement si le thème n'en fournit pas)                */
/* ------------------------------------------------------------------ */

add_action('init', 'lfi_nct_event_register_fallback_cpt', 12);
function lfi_nct_event_register_fallback_cpt() {
    if (post_type_exists(LFI_NCT_EVT_CPT_THEME)) return;
    if (post_type_exists(LFI_NCT_EVT_CPT_FALLBACK)) return;
    register_post_type(LFI_NCT_EVT_CPT_FALLBACK, [
        'labels' => [
            'name'          => 'Événements LFI',
            'singular_name' => 'Événement LFI',
            'menu_name'     => '📅 Événements LFI',
            'add_new'       => 'Ajouter',
            'add_new_item'  => 'Nouvel événement',
        ],
        'public'          => true,
        'show_in_menu'    => true,
        'show_in_rest'    => true,
        'menu_icon'       => 'dashicons-calendar-alt',
        'menu_position'   => 26,
        'has_archive'     => false,
        'rewrite'         => ['slug' => 'mes-evenements-lfi', 'with_front' => false],
        'supports'        => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
        'capability_type' => 'post',
    ]);
}

/* ------------------------------------------------------------------ */
/* Table RSVP                                                          */
/* ------------------------------------------------------------------ */

add_action('init', 'lfi_nct_event_rsvp_db_setup', 5);
function lfi_nct_event_rsvp_db_setup() {
    if (get_option(LFI_NCT_EVT_RSVP_DBVER) === '1') return;
    global $wpdb;
    $table = $wpdb->prefix . LFI_NCT_EVT_RSVP_TABLE;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id BIGINT(20) UNSIGNED NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        prenom VARCHAR(120) DEFAULT '',
        nom VARCHAR(120) DEFAULT '',
        tel VARCHAR(40) DEFAULT '',
        email VARCHAR(190) DEFAULT '',
        avec_qui INT UNSIGNED DEFAULT 1,
        commentaire TEXT,
        ip VARCHAR(45) DEFAULT '',
        PRIMARY KEY (id),
        KEY event_id (event_id),
        KEY created_at (created_at)
    ) $charset;");
    update_option(LFI_NCT_EVT_RSVP_DBVER, '1', false);
}

/* ------------------------------------------------------------------ */
/* Metabox « Détails LFI »                                              */
/* ------------------------------------------------------------------ */

add_action('add_meta_boxes', 'lfi_nct_event_extra_metabox');
function lfi_nct_event_extra_metabox() {
    add_meta_box(
        'lfi_nct_event_extra',
        '📋 Détails LFI',
        'lfi_nct_event_extra_metabox_render',
        lfi_nct_event_cpt(),
        'side',
        'default'
    );
}

function lfi_nct_event_extra_metabox_render($post) {
    wp_nonce_field('lfi_nct_event_extra_save', 'lfi_nct_event_extra_nonce');
    $capacite   = get_post_meta($post->ID, '_lfi_evt_capacite',   true);
    $rsvp_actif = get_post_meta($post->ID, '_lfi_evt_rsvp_actif', true);
    if ($rsvp_actif === '') $rsvp_actif = '1';
    $url_ap = get_post_meta($post->ID, '_lfi_evt_url_ap', true);
    ?>
    <p>
        <label><strong>Capacité max</strong> (optionnel)</label>
        <input type="number" name="lfi_evt_capacite" value="<?php echo esc_attr($capacite); ?>" min="0" class="widefat" placeholder="ex : 80">
    </p>
    <p>
        <label>
            <input type="checkbox" name="lfi_evt_rsvp_actif" value="1" <?php checked($rsvp_actif, '1'); ?>>
            Activer le formulaire « Je participe » sur la page de l'événement
        </label>
    </p>
    <p>
        <label><strong>URL Action Populaire</strong> (si l'événement existe aussi sur AP)</label>
        <input type="url" name="lfi_evt_url_ap" value="<?php echo esc_attr($url_ap); ?>" class="widefat" placeholder="https://actionpopulaire.fr/evenements/...">
    </p>
    <?php
}

add_action('save_post', 'lfi_nct_event_extra_save', 10, 2);
function lfi_nct_event_extra_save($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['lfi_nct_event_extra_nonce']) || !wp_verify_nonce($_POST['lfi_nct_event_extra_nonce'], 'lfi_nct_event_extra_save')) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if ($post->post_type !== lfi_nct_event_cpt()) return;

    update_post_meta($post_id, '_lfi_evt_capacite',   (int) ($_POST['lfi_evt_capacite'] ?? 0));
    update_post_meta($post_id, '_lfi_evt_rsvp_actif', !empty($_POST['lfi_evt_rsvp_actif']) ? '1' : '0');
    update_post_meta($post_id, '_lfi_evt_url_ap',     esc_url_raw($_POST['lfi_evt_url_ap'] ?? ''));
}

/* ------------------------------------------------------------------ */
/* Helpers unifiés                                                      */
/* ------------------------------------------------------------------ */

function lfi_nct_upcoming_events($limit = 10) {
    $cpt = lfi_nct_event_cpt();
    $date_key = $cpt === LFI_NCT_EVT_CPT_THEME ? '_ag_event_date' : '_lfi_evt_date_debut';
    return get_posts([
        'post_type'      => $cpt,
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'meta_key'       => $date_key,
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [[
            'key'     => $date_key,
            'value'   => current_time('Y-m-d'),
            'compare' => '>=',
        ]],
    ]);
}

function lfi_nct_event_data($id_or_post) {
    $post = is_object($id_or_post) ? $id_or_post : get_post($id_or_post);
    if (!$post) return null;
    $id = $post->ID;

    if ($post->post_type === LFI_NCT_EVT_CPT_THEME) {
        $date        = get_post_meta($id, '_ag_event_date',  true);
        $heure_debut = get_post_meta($id, '_ag_event_time',  true);
        $heure_fin   = get_post_meta($id, '_ag_event_end',   true);
        $lieu        = get_post_meta($id, '_ag_event_place', true);
        $ville       = get_post_meta($id, '_ag_event_city',  true);
        $adresse     = trim(($lieu ? $lieu : '') . ($ville ? (($lieu ? ', ' : '') . $ville) : ''));
    } else {
        $date_debut = get_post_meta($id, '_lfi_evt_date_debut', true);
        $date_fin   = get_post_meta($id, '_lfi_evt_date_fin',   true);
        $ts_debut   = $date_debut ? strtotime($date_debut) : 0;
        $ts_fin     = $date_fin   ? strtotime($date_fin)   : 0;
        $date        = $ts_debut ? date('Y-m-d', $ts_debut) : '';
        $heure_debut = $ts_debut ? date('H\hi', $ts_debut)  : '';
        $heure_fin   = $ts_fin   ? date('H\hi', $ts_fin)    : '';
        $lieu        = get_post_meta($id, '_lfi_evt_lieu',    true);
        $adresse     = get_post_meta($id, '_lfi_evt_adresse', true);
    }

    // Reconstruit le timestamp complet
    $heure_clean = preg_replace('/[^\d:]/', '', str_replace('h', ':', $heure_debut));
    $ts = $date ? strtotime($date . ($heure_clean ? ' ' . $heure_clean : '')) : 0;

    return [
        'id'             => $id,
        'titre'          => get_the_title($post),
        'url'            => get_permalink($post),
        'short_url'      => wp_get_shortlink($id) ?: get_permalink($post),
        'date'           => $date,
        'heure_debut'    => $heure_debut,
        'heure_fin'      => $heure_fin,
        'lieu'           => $lieu,
        'adresse'        => $adresse,
        'capacite'       => (int) get_post_meta($id, '_lfi_evt_capacite', true),
        'rsvp_actif'     => get_post_meta($id, '_lfi_evt_rsvp_actif', true) !== '0',
        'url_ap'         => get_post_meta($id, '_lfi_evt_url_ap', true),
        'ts'             => $ts,
        'jour'           => $ts ? date_i18n('l', $ts) : '',
        'date_fr'        => $ts ? date_i18n('d/m', $ts) : '',
        'date_complete'  => $ts ? date_i18n('l j F · H\hi', $ts) : ($date ? date_i18n('l j F', strtotime($date)) : ''),
    ];
}

/* ------------------------------------------------------------------ */
/* Bandeau + RSVP sur la page single                                    */
/* ------------------------------------------------------------------ */

add_filter('the_content', 'lfi_nct_event_render_content', 20);
function lfi_nct_event_render_content($content) {
    if (!is_singular(lfi_nct_event_cpt()) || !in_the_loop() || !is_main_query()) return $content;
    $data = lfi_nct_event_data(get_post());
    if (!$data) return $content;

    $bandeau = '<div class="lfi-evt-bandeau" style="background:#fff3f5;border-left:6px solid #c8102e;padding:18px 22px;margin:0 0 1.5em;border-radius:6px">';
    if ($data['date']) {
        $bandeau .= '<div style="font-size:1.1em;font-weight:700">📅 ' . esc_html($data['date_complete']);
        if ($data['heure_fin']) $bandeau .= ' – ' . esc_html($data['heure_fin']);
        $bandeau .= '</div>';
    }
    if ($data['lieu']) {
        $bandeau .= '<div style="margin-top:.3em">📍 <strong>' . esc_html($data['lieu']) . '</strong>';
        if ($data['adresse'] && $data['adresse'] !== $data['lieu']) {
            $bandeau .= ' — ' . esc_html($data['adresse']);
        }
        $bandeau .= '</div>';
    }
    if ($data['url_ap']) {
        $bandeau .= '<div style="margin-top:.3em;font-size:.9em">↗ <a href="' . esc_url($data['url_ap']) . '" target="_blank" rel="noopener">Aussi sur Action Populaire</a></div>';
    }
    $bandeau .= '</div>';

    $rsvp = $data['rsvp_actif'] ? lfi_nct_render_event_rsvp_form($data['id']) : '';
    return $bandeau . $content . $rsvp;
}

function lfi_nct_render_event_rsvp_form($event_id) {
    global $wpdb;
    $table = $wpdb->prefix . LFI_NCT_EVT_RSVP_TABLE;
    $event_id = (int) $event_id;
    $notice = '';
    $error  = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lfi_evt_rsvp_submit']) && (int) ($_POST['lfi_evt_id'] ?? 0) === $event_id) {
        if (!isset($_POST['lfi_evt_rsvp_nonce']) || !wp_verify_nonce($_POST['lfi_evt_rsvp_nonce'], 'lfi_evt_rsvp_' . $event_id)) {
            $error = 'Jeton de sécurité invalide. Recharge la page.';
        } else {
            $prenom = sanitize_text_field(wp_unslash($_POST['prenom'] ?? ''));
            $nom    = sanitize_text_field(wp_unslash($_POST['nom']    ?? ''));
            $tel    = sanitize_text_field(wp_unslash($_POST['tel']    ?? ''));
            $email  = sanitize_email(wp_unslash($_POST['email']       ?? ''));
            $avec   = max(1, min(20, (int) ($_POST['avec_qui'] ?? 1)));
            $com    = sanitize_textarea_field(wp_unslash($_POST['commentaire'] ?? ''));
            if ($prenom === '')                                $error = 'Indiquez au moins votre prénom.';
            elseif ($tel === '' && $email === '')              $error = 'Téléphone ou email — au moins l\'un des deux.';
            else {
                $wpdb->insert($table, [
                    'event_id'    => $event_id,
                    'prenom'      => $prenom, 'nom' => $nom, 'tel' => $tel, 'email' => $email,
                    'avec_qui'    => $avec,   'commentaire' => $com,
                    'ip'          => substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45),
                ]);
                $notice = 'Merci ' . esc_html($prenom) . ' ! On compte sur toi. 🌟';
            }
        }
    }

    $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(avec_qui),0) FROM $table WHERE event_id = %d", $event_id));

    ob_start(); ?>
    <div class="lfi-evt-rsvp" style="background:#fff;padding:18px 22px;margin-top:1.5em;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08)">
        <h3 style="color:#c8102e;margin:0 0 .4em">Je participe</h3>
        <p><strong><?php echo $count; ?></strong> personne<?php echo $count > 1 ? 's' : ''; ?> <?php echo $count > 1 ? 'ont' : 'a'; ?> déjà confirmé.</p>

        <?php if ($notice): ?>
            <div style="background:#e7f5ee;border-left:4px solid #1a7f37;padding:10px 14px;border-radius:4px">✅ <?php echo $notice; ?></div>
        <?php else: ?>
            <?php if ($error): ?>
                <div style="background:#fcebec;border-left:4px solid #c8102e;padding:10px 14px;border-radius:4px"><strong>Erreur :</strong> <?php echo esc_html($error); ?></div>
            <?php endif; ?>
            <form method="post" style="margin-top:1em">
                <?php wp_nonce_field('lfi_evt_rsvp_' . $event_id, 'lfi_evt_rsvp_nonce'); ?>
                <input type="hidden" name="lfi_evt_id" value="<?php echo $event_id; ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <label>Prénom *<br><input type="text" name="prenom" required style="width:100%"></label>
                    <label>Nom<br><input type="text" name="nom" style="width:100%"></label>
                    <label>Téléphone<br><input type="tel" name="tel" placeholder="06 12 34 56 78" style="width:100%"></label>
                    <label>Email<br><input type="email" name="email" placeholder="vous@email.fr" style="width:100%"></label>
                    <div style="grid-column:1/-1">
                        <div style="margin-bottom:.3em">Nombre de personnes (vous compris)</div>
                        <div class="lfi-evt-stepper">
                            <button type="button" class="lfi-step-minus" aria-label="Moins">−</button>
                            <input type="number" name="avec_qui" value="1" min="1" max="20" readonly inputmode="numeric">
                            <button type="button" class="lfi-step-plus"  aria-label="Plus">+</button>
                        </div>
                    </div>
                    <label style="grid-column:1/-1">Un mot ?<br><textarea name="commentaire" rows="2" style="width:100%"></textarea></label>
                </div>
                <p style="margin-top:1em"><button type="submit" name="lfi_evt_rsvp_submit" value="1" style="background:#c8102e;color:#fff;border:none;padding:.7em 1.4em;border-radius:4px;font-weight:700;cursor:pointer">✓ Je participe</button></p>
            </form>
        <?php endif; ?>

        <p style="font-size:.85em;color:#666;margin-top:1em">
            🔒 Tes coordonnées restent strictement au Groupe d'Action LFI Nantes Sud Clos Toreau, uniquement pour te reconfirmer cet événement.
        </p>
    </div>
    <style>
    .lfi-evt-stepper { display:inline-flex; align-items:stretch; border:2px solid #c8102e; border-radius:10px; overflow:hidden; background:#fff; user-select:none; -webkit-user-select:none; }
    .lfi-evt-stepper button { background:#c8102e; color:#fff; border:none; width:56px; height:56px; font-size:1.8em; font-weight:700; line-height:1; cursor:pointer; touch-action:manipulation; }
    .lfi-evt-stepper button:hover, .lfi-evt-stepper button:active { background:#a30b25; }
    .lfi-evt-stepper button:disabled { background:#ddd; color:#999; cursor:not-allowed; }
    .lfi-evt-stepper input { width:80px; text-align:center; font-size:1.6em; font-weight:700; border:none; background:#fff; color:#222; padding:0; -moz-appearance:textfield; }
    .lfi-evt-stepper input::-webkit-outer-spin-button, .lfi-evt-stepper input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
    .lfi-evt-stepper input:focus { outline:none; background:#fff3f5; }
    </style>
    <script>
    (function(){
        document.querySelectorAll('.lfi-evt-stepper').forEach(function(box){
            var input = box.querySelector('input[type=number]');
            var minus = box.querySelector('.lfi-step-minus');
            var plus  = box.querySelector('.lfi-step-plus');
            var min = parseInt(input.min,10)||1, max = parseInt(input.max,10)||20;
            function refresh(){ var v = parseInt(input.value,10)||min; v = Math.max(min,Math.min(max,v)); input.value = v; minus.disabled = (v<=min); plus.disabled = (v>=max); }
            minus.addEventListener('click', function(){ input.value = (parseInt(input.value,10)||min) - 1; refresh(); });
            plus .addEventListener('click', function(){ input.value = (parseInt(input.value,10)||min) + 1; refresh(); });
            refresh();
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* DONOTCACHEPAGE sur le single                                          */
/* ------------------------------------------------------------------ */

add_action('wp', 'lfi_nct_event_no_cache');
function lfi_nct_event_no_cache() {
    if (!is_singular(lfi_nct_event_cpt())) return;
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    do_action('litespeed_control_set_nocache', 'LFI : page événement avec nonce');
}

/* ------------------------------------------------------------------ */
/* Shortcode [lfi_nct_agenda]                                           */
/* ------------------------------------------------------------------ */

add_shortcode('lfi_nct_agenda', 'lfi_nct_event_shortcode_agenda');
function lfi_nct_event_shortcode_agenda($atts) {
    $atts = shortcode_atts(['limit' => 10], $atts);
    $events = lfi_nct_upcoming_events((int) $atts['limit']);
    if (empty($events)) return '<p><em>Aucun événement programmé pour le moment.</em></p>';
    $out = '<div class="lfi-agenda" style="display:grid;gap:14px">';
    foreach ($events as $e) {
        $d = lfi_nct_event_data($e);
        $thumb = get_the_post_thumbnail_url($e->ID, 'medium');
        $out .= '<a href="' . esc_url($d['url']) . '" style="display:flex;gap:14px;align-items:center;background:#fff;padding:14px;border-left:4px solid #c8102e;border-radius:4px;text-decoration:none;color:inherit;box-shadow:0 1px 3px rgba(0,0,0,.08)">';
        if ($thumb) $out .= '<img src="' . esc_url($thumb) . '" alt="" style="width:120px;height:90px;object-fit:cover;border-radius:4px;flex-shrink:0">';
        $out .= '<div>';
        $out .= '<div style="color:#c8102e;font-weight:700;font-size:.9em">' . esc_html($d['date_complete']) . '</div>';
        $out .= '<div style="font-size:1.15em;font-weight:700;margin:.2em 0">' . esc_html($d['titre']) . '</div>';
        if ($d['lieu']) $out .= '<div style="color:#555">📍 ' . esc_html($d['lieu']) . '</div>';
        $out .= '</div>';
        $out .= '</a>';
    }
    $out .= '</div>';
    return $out;
}

/* ------------------------------------------------------------------ */
/* Admin : page Inscriptions                                            */
/* ------------------------------------------------------------------ */

add_action('admin_menu', 'lfi_nct_event_rsvp_admin_menu', 50);
function lfi_nct_event_rsvp_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=' . lfi_nct_event_cpt(),
        'Inscriptions aux événements',
        '👥 Inscriptions',
        'manage_options',
        'lfi-nct-event-rsvp',
        'lfi_nct_event_rsvp_admin_page'
    );
}

function lfi_nct_event_rsvp_admin_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = $wpdb->prefix . LFI_NCT_EVT_RSVP_TABLE;
    $event_id = isset($_GET['event']) ? (int) $_GET['event'] : 0;

    if (!empty($_GET['del']) && check_admin_referer('lfi_nct_evt_rsvp_del_' . (int) $_GET['del'])) {
        $wpdb->delete($table, ['id' => (int) $_GET['del']]);
        wp_safe_redirect(add_query_arg('deleted', 1, remove_query_arg(['del','_wpnonce'])));
        exit;
    }
    ?>
    <div class="wrap">
        <h1>👥 Inscriptions aux événements</h1>
        <?php if (!empty($_GET['deleted'])): ?><div class="notice notice-success is-dismissible"><p>Inscription supprimée.</p></div><?php endif; ?>

        <form method="get" style="margin:1em 0">
            <input type="hidden" name="post_type" value="<?php echo esc_attr(lfi_nct_event_cpt()); ?>">
            <input type="hidden" name="page" value="lfi-nct-event-rsvp">
            <label>Événement
                <select name="event" onchange="this.form.submit()">
                    <option value="0">— tous —</option>
                    <?php
                    $events = get_posts(['post_type' => lfi_nct_event_cpt(), 'numberposts' => -1, 'post_status' => 'any', 'orderby' => 'date', 'order' => 'DESC']);
                    foreach ($events as $e) {
                        printf('<option value="%d" %s>%s</option>',
                            $e->ID, selected($event_id, $e->ID, false), esc_html(get_the_title($e)));
                    }
                    ?>
                </select>
            </label>
        </form>

        <?php
        $sql = "SELECT r.*, p.post_title FROM $table r LEFT JOIN {$wpdb->posts} p ON p.ID = r.event_id";
        if ($event_id) $sql .= $wpdb->prepare(" WHERE r.event_id = %d", $event_id);
        $sql .= " ORDER BY r.created_at DESC LIMIT 1000";
        $rows = $wpdb->get_results($sql);
        $tot_pers = array_sum(array_map(function($r){ return (int) $r->avec_qui; }, $rows));
        ?>
        <p><strong><?php echo count($rows); ?></strong> inscription(s) · <strong><?php echo $tot_pers; ?></strong> personne(s).</p>

        <table class="wp-list-table widefat striped">
            <thead><tr><th>#</th><th>Date</th><th>Événement</th><th>Prénom</th><th>Nom</th><th>Tél</th><th>Email</th><th>Nb</th><th>Mot</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="10"><em>Aucune inscription.</em></td></tr>
            <?php else: foreach ($rows as $r):
                $del_url = wp_nonce_url(add_query_arg('del', $r->id), 'lfi_nct_evt_rsvp_del_' . $r->id);
            ?>
                <tr>
                    <td>#<?php echo (int) $r->id; ?></td>
                    <td><?php echo esc_html($r->created_at); ?></td>
                    <td><?php echo esc_html($r->post_title ?: '(supprimé)'); ?></td>
                    <td><?php echo esc_html($r->prenom); ?></td>
                    <td><?php echo esc_html($r->nom); ?></td>
                    <td><?php echo $r->tel ? '<a href="tel:'.esc_attr($r->tel).'">'.esc_html($r->tel).'</a>' : ''; ?></td>
                    <td><?php echo $r->email ? '<a href="mailto:'.esc_attr($r->email).'">'.esc_html($r->email).'</a>' : ''; ?></td>
                    <td><?php echo (int) $r->avec_qui; ?></td>
                    <td><?php echo esc_html($r->commentaire); ?></td>
                    <td><a href="<?php echo esc_url($del_url); ?>" class="button button-small" onclick="return confirm('Supprimer cette inscription ?')">🗑</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/* ------------------------------------------------------------------ */
/* Admin : Diag CPT + bouton « Vider démos »                            */
/* ------------------------------------------------------------------ */

add_action('admin_menu', 'lfi_nct_event_diag_menu', 50);
function lfi_nct_event_diag_menu() {
    add_submenu_page(
        'lfi-nct-responses',
        'Diag événements',
        '🔍 Diag événements',
        'manage_options',
        'lfi-nct-event-diag',
        'lfi_nct_event_diag_page'
    );
}

function lfi_nct_event_diag_page() {
    if (!current_user_can('manage_options')) return;
    $cpt = lfi_nct_event_cpt();
    $is_theme_cpt = ($cpt === LFI_NCT_EVT_CPT_THEME);

    $purge_notice = '';
    if (!empty($_POST['lfi_nct_purge_demos']) && check_admin_referer('lfi_nct_purge_demos') && $is_theme_cpt) {
        $all_theme = get_posts(['post_type' => $cpt, 'post_status' => 'any', 'posts_per_page' => 500]);
        $deleted = 0;
        foreach ($all_theme as $p) {
            // Garde les événements LFI (marqueur _lfi_evt_origin_id ou ceux qu'on a créés via metabox)
            if (get_post_meta($p->ID, '_lfi_evt_origin_id', true)) continue;
            // Garde aussi ceux qui ont notre meta _lfi_evt_rsvp_actif ou _lfi_evt_url_ap (= édités via notre metabox)
            if (get_post_meta($p->ID, '_lfi_evt_url_ap', true) || get_post_meta($p->ID, '_lfi_evt_capacite', true)) continue;
            // Garde la réunion 26 juin par son slug exact
            if ($p->post_name === 'votre-logement-votre-droit-reunion-26-juin') continue;
            wp_delete_post($p->ID, true);
            $deleted++;
        }
        do_action('litespeed_purge_all');
        if (function_exists('wp_cache_flush')) wp_cache_flush();
        $purge_notice = "$deleted événement(s) démo supprimé(s).";
    }

    $events = get_posts(['post_type' => $cpt, 'post_status' => 'any', 'numberposts' => 100, 'orderby' => 'date', 'order' => 'DESC']);
    $demos  = [];
    foreach ($events as $p) {
        if (get_post_meta($p->ID, '_lfi_evt_origin_id', true)) continue;
        if (get_post_meta($p->ID, '_lfi_evt_url_ap', true) || get_post_meta($p->ID, '_lfi_evt_capacite', true)) continue;
        if ($p->post_name === 'votre-logement-votre-droit-reunion-26-juin') continue;
        $demos[] = $p;
    }
    ?>
    <div class="wrap">
        <h1>🔍 Diag événements</h1>
        <?php if ($purge_notice): ?>
            <div class="notice notice-success is-dismissible"><p><strong>🗑 <?php echo esc_html($purge_notice); ?></strong></p></div>
        <?php endif; ?>
        <p>CPT actif : <strong><?php echo esc_html($cpt); ?></strong> <?php echo $is_theme_cpt ? '(CPT du thème AG Starter)' : '(fallback LFI)'; ?></p>
        <p>Date meta key : <code><?php echo $is_theme_cpt ? '_ag_event_date' : '_lfi_evt_date_debut'; ?></code></p>
        <p>Total événements en base : <strong><?php echo count($events); ?></strong></p>

        <?php if ($is_theme_cpt && !empty($demos)): ?>
            <form method="post" style="background:#fff;padding:14px 18px;border-radius:4px;border-left:4px solid #bd8600;margin:16px 0" onsubmit="return confirm('Supprimer définitivement <?php echo count($demos); ?> événement(s) démo du thème ? Les événements créés via le plugin LFI sont préservés.');">
                <?php wp_nonce_field('lfi_nct_purge_demos'); ?>
                <p style="margin:0 0 .6em"><strong>🗑 Vider les événements démo du thème AG Starter</strong></p>
                <p class="description" style="margin:0 0 .6em">
                    Le thème a installé <strong><?php echo count($demos); ?> événement(s) démo</strong> (Marche climatique, AG annuelle, Université d'été…). Ils trônent sur la home et ne sont pas les tiens.
                </p>
                <p class="description" style="margin:0 0 .6em"><strong>À supprimer :</strong>
                    <?php
                    $titles = [];
                    foreach (array_slice($demos, 0, 10) as $d) $titles[] = '#' . $d->ID . ' ' . wp_trim_words($d->post_title, 6);
                    echo esc_html(implode(' · ', $titles));
                    if (count($demos) > 10) echo ' …';
                    ?>
                </p>
                <button type="submit" name="lfi_nct_purge_demos" value="1" class="button" style="background:#bd8600;color:#fff;border-color:#bd8600">🗑 Supprimer les <?php echo count($demos); ?> démo(s)</button>
            </form>
        <?php endif; ?>

        <h2>Liste des 100 derniers événements</h2>
        <table class="wp-list-table widefat striped">
            <thead><tr><th>ID</th><th>Titre</th><th>Date</th><th>Lieu</th><th>RSVP</th><th>Marqueur LFI</th></tr></thead>
            <tbody>
            <?php foreach ($events as $p):
                $d = lfi_nct_event_data($p);
                $is_lfi = get_post_meta($p->ID, '_lfi_evt_url_ap', true)
                       || get_post_meta($p->ID, '_lfi_evt_capacite', true)
                       || get_post_meta($p->ID, '_lfi_evt_origin_id', true)
                       || $p->post_name === 'votre-logement-votre-droit-reunion-26-juin';
            ?>
                <tr<?php echo $is_lfi ? ' style="background:#e7f5ee"' : ''; ?>>
                    <td>#<?php echo $p->ID; ?></td>
                    <td><a href="<?php echo esc_url(get_edit_post_link($p->ID)); ?>"><?php echo esc_html($d['titre']); ?></a></td>
                    <td><?php echo esc_html($d['date']); ?> <?php echo esc_html($d['heure_debut']); ?></td>
                    <td><?php echo esc_html($d['lieu']); ?></td>
                    <td><?php echo $d['rsvp_actif'] ? '✓' : '—'; ?></td>
                    <td><?php echo $is_lfi ? '🟢 LFI' : '⚪ thème'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/* ------------------------------------------------------------------ */
/* Seed de la réunion du 26 juin (idempotent)                           */
/* ------------------------------------------------------------------ */

const LFI_NCT_SEED_REUNION_V3 = 'lfi_nct_seed_reunion_26juin_v3';

add_action('init', 'lfi_nct_seed_reunion_26juin', 30);
function lfi_nct_seed_reunion_26juin() {
    if (get_option(LFI_NCT_SEED_REUNION_V3) === 'done') return;
    $cpt = lfi_nct_event_cpt();
    if (!post_type_exists($cpt)) return;

    $slug = 'votre-logement-votre-droit-reunion-26-juin';
    $existing = get_page_by_path($slug, OBJECT, $cpt);
    if (!$existing) {
        $by_title = get_posts(['post_type' => $cpt, 'title' => 'Votre logement, votre droit — Réunion publique', 'posts_per_page' => 1, 'post_status' => 'any']);
        if (!empty($by_title)) $existing = $by_title[0];
    }

    $meta_ag = [
        '_ag_event_date'  => '2026-06-26',
        '_ag_event_time'  => '15h00',
        '_ag_event_end'   => '17h00',
        '_ag_event_place' => 'Salle de Diffusion — Confluences',
        '_ag_event_city'  => 'Nantes',
    ];
    $meta_lfi = [
        '_lfi_evt_capacite'   => 80,
        '_lfi_evt_rsvp_actif' => '1',
        '_lfi_evt_url_ap'     => 'https://actionpopulaire.fr/evenements/b9e423c3-a850-4d5b-8507-7a979b791299/',
    ];

    if ($existing) {
        foreach ($meta_ag as $k => $v)  update_post_meta($existing->ID, $k, $v);
        foreach ($meta_lfi as $k => $v) update_post_meta($existing->ID, $k, $v);
        update_option(LFI_NCT_SEED_REUNION_V3, 'done', false);
        return;
    }

    $content = <<<HTML
<!-- wp:paragraph --><p>Depuis plusieurs mois, le Groupe d'Action LFI Nantes Sud Clos Toreau mène une <strong>enquête de voisinage sur l'insalubrité au Clos Toreau</strong> : humidité, moisissures, nuisibles, logements dégradés, coupures d'eau chaude à répétition. Des problèmes que <em>vous</em> subissez.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Il est temps de faire le point ensemble et de <strong>passer à l'action</strong>.</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2>Au programme</h2><!-- /wp:heading -->
<!-- wp:list {"ordered":true} --><ol><li><strong>Résultats de l'enquête de voisinage</strong> — chiffres et témoignages à l'appui.</li><li><strong>Vos droits et les recours possibles</strong> — démarches concrètes.</li><li><strong>Questions / Réponses</strong> — partagez votre situation, on vous écoute.</li></ol><!-- /wp:list -->
<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center"><strong>VENEZ, PARLEZ, ON VOUS ÉCOUTE.</strong></p><!-- /wp:paragraph -->
HTML;

    $id = wp_insert_post([
        'post_type'    => $cpt,
        'post_status'  => 'publish',
        'post_title'   => 'Votre logement, votre droit — Réunion publique',
        'post_name'    => $slug,
        'post_content' => $content,
        'post_excerpt' => "Présentation des résultats de l'enquête de voisinage sur l'insalubrité au Clos Toreau et organisation de la suite.",
        'post_author'  => 1,
    ], true);

    if (!is_wp_error($id) && $id) {
        foreach ($meta_ag as $k => $v)  update_post_meta($id, $k, $v);
        foreach ($meta_lfi as $k => $v) update_post_meta($id, $k, $v);
        update_option(LFI_NCT_SEED_REUNION_V3, 'done', false);
    }
}

/* ------------------------------------------------------------------ */
/* Migration : supprime les posts lfi_evenement orphelins (ancien CPT)  */
/* ------------------------------------------------------------------ */

const LFI_NCT_MIGRATE_LEGACY = 'lfi_nct_migrate_lfi_evenement_to_ag';

add_action('init', 'lfi_nct_migrate_legacy_lfi_evenement', 40);
function lfi_nct_migrate_legacy_lfi_evenement() {
    if (get_option(LFI_NCT_MIGRATE_LEGACY) === 'done') return;
    // Migration seulement si on bascule sur le CPT du thème
    if (lfi_nct_event_cpt() !== LFI_NCT_EVT_CPT_THEME) return;
    if (!post_type_exists(LFI_NCT_EVT_CPT_FALLBACK)) {
        // CPT fallback déjà désinscrit, mais les posts peuvent rester en DB
    }
    global $wpdb;
    $rsvp_table = $wpdb->prefix . LFI_NCT_EVT_RSVP_TABLE;

    $legacy = $wpdb->get_results($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
        LFI_NCT_EVT_CPT_FALLBACK
    ));

    foreach ($legacy as $p) {
        $mirror_id = (int) get_post_meta($p->ID, '_lfi_evt_theme_mirror_id', true);
        // Réoriente les RSVPs vers le mirror si possible
        if ($mirror_id && get_post($mirror_id)) {
            $wpdb->update($rsvp_table, ['event_id' => $mirror_id], ['event_id' => $p->ID]);
        }
        wp_delete_post($p->ID, true);
    }
    update_option(LFI_NCT_MIGRATE_LEGACY, 'done', false);
}
