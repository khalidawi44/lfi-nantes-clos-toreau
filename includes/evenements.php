<?php
/**
 * Module Événements — Custom Post Type 'lfi_evenement' avec champs structurés
 * (date début/fin, lieu, adresse, capacité), template public auto via filtre
 * sur the_content (pas de fichier de template à mettre dans le thème), et
 * inscription « Je participe » par événement.
 *
 * Slug d'archive : /evenements/ — listing des événements à venir.
 * Slug individuel : /evenements/<slug-de-l-event>/
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_EVT_CPT     = 'lfi_evenement';
const LFI_NCT_EVT_DBVER   = 'lfi_nct_event_rsvp_db_ver';
const LFI_NCT_EVT_FLUSHED = 'lfi_nct_event_rewrite_flushed';

/* ------------------------------------------------------------------ */
/* CPT                                                                  */
/* ------------------------------------------------------------------ */

add_action('init', 'lfi_nct_event_register_cpt', 10);
function lfi_nct_event_register_cpt() {
    register_post_type(LFI_NCT_EVT_CPT, [
        'labels' => [
            'name'               => 'Événements',
            'singular_name'      => 'Événement',
            'menu_name'          => '📅 Événements',
            'add_new'            => 'Ajouter',
            'add_new_item'       => 'Nouvel événement',
            'edit_item'          => 'Éditer l\'événement',
            'new_item'           => 'Nouvel événement',
            'view_item'          => 'Voir l\'événement',
            'search_items'       => 'Rechercher un événement',
            'not_found'          => 'Aucun événement.',
            'not_found_in_trash' => 'Aucun événement dans la corbeille.',
            'all_items'          => 'Tous les événements',
        ],
        'public'             => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'menu_icon'          => 'dashicons-calendar-alt',
        'menu_position'      => 26,
        'has_archive'        => 'evenements',
        'rewrite'            => ['slug' => 'evenements', 'with_front' => false],
        'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
        'capability_type'    => 'post',
        'hierarchical'       => false,
    ]);

    if (get_option(LFI_NCT_EVT_FLUSHED) !== '1') {
        flush_rewrite_rules(false);
        update_option(LFI_NCT_EVT_FLUSHED, '1', false);
    }
}

/* ------------------------------------------------------------------ */
/* Méta : date / lieu / capacité                                       */
/* ------------------------------------------------------------------ */

add_action('add_meta_boxes', 'lfi_nct_event_meta_box');
function lfi_nct_event_meta_box() {
    add_meta_box(
        'lfi_nct_event_details',
        '📅 Détails de l\'événement',
        'lfi_nct_event_meta_box_render',
        LFI_NCT_EVT_CPT,
        'side',
        'high'
    );
}

function lfi_nct_event_meta_box_render($post) {
    wp_nonce_field('lfi_nct_event_save_meta', 'lfi_nct_event_meta_nonce');
    $date_debut  = get_post_meta($post->ID, '_lfi_evt_date_debut',  true);
    $date_fin    = get_post_meta($post->ID, '_lfi_evt_date_fin',    true);
    $lieu        = get_post_meta($post->ID, '_lfi_evt_lieu',        true);
    $adresse     = get_post_meta($post->ID, '_lfi_evt_adresse',     true);
    $capacite    = get_post_meta($post->ID, '_lfi_evt_capacite',    true);
    $rsvp_actif  = get_post_meta($post->ID, '_lfi_evt_rsvp_actif',  true);
    if ($rsvp_actif === '') $rsvp_actif = '1';
    $url_ap      = get_post_meta($post->ID, '_lfi_evt_url_ap',      true);
    ?>
    <p>
        <label><strong>Date / heure de début</strong></label>
        <input type="datetime-local" name="lfi_evt_date_debut" value="<?php echo esc_attr($date_debut); ?>" class="widefat">
    </p>
    <p>
        <label><strong>Date / heure de fin</strong> (optionnel)</label>
        <input type="datetime-local" name="lfi_evt_date_fin" value="<?php echo esc_attr($date_fin); ?>" class="widefat">
    </p>
    <p>
        <label><strong>Lieu (nom court)</strong></label>
        <input type="text" name="lfi_evt_lieu" value="<?php echo esc_attr($lieu); ?>" class="widefat" placeholder="Salle de Diffusion — Confluences">
    </p>
    <p>
        <label><strong>Adresse complète</strong></label>
        <textarea name="lfi_evt_adresse" rows="2" class="widefat" placeholder="4 place du Muguet, 44200 Nantes"><?php echo esc_textarea($adresse); ?></textarea>
    </p>
    <p>
        <label><strong>Capacité max</strong> (optionnel)</label>
        <input type="number" name="lfi_evt_capacite" value="<?php echo esc_attr($capacite); ?>" min="0" class="widefat" placeholder="ex : 80">
    </p>
    <p>
        <label><input type="checkbox" name="lfi_evt_rsvp_actif" value="1" <?php checked($rsvp_actif, '1'); ?>>
            Activer le formulaire « Je participe »</label>
    </p>
    <p>
        <label><strong>URL Action Populaire</strong> (si l'événement existe aussi sur AP)</label>
        <input type="url" name="lfi_evt_url_ap" value="<?php echo esc_attr($url_ap); ?>" class="widefat" placeholder="https://actionpopulaire.fr/evenements/...">
    </p>
    <?php
}

add_action('save_post_' . LFI_NCT_EVT_CPT, 'lfi_nct_event_save_meta', 10, 1);
function lfi_nct_event_save_meta($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['lfi_nct_event_meta_nonce']) || !wp_verify_nonce($_POST['lfi_nct_event_meta_nonce'], 'lfi_nct_event_save_meta')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $map = [
        '_lfi_evt_date_debut' => 'lfi_evt_date_debut',
        '_lfi_evt_date_fin'   => 'lfi_evt_date_fin',
        '_lfi_evt_lieu'       => 'lfi_evt_lieu',
        '_lfi_evt_adresse'    => 'lfi_evt_adresse',
        '_lfi_evt_capacite'   => 'lfi_evt_capacite',
        '_lfi_evt_url_ap'     => 'lfi_evt_url_ap',
    ];
    foreach ($map as $meta_key => $post_key) {
        $val = isset($_POST[$post_key]) ? sanitize_text_field(wp_unslash($_POST[$post_key])) : '';
        if ($meta_key === '_lfi_evt_adresse') $val = sanitize_textarea_field(wp_unslash($_POST[$post_key] ?? ''));
        if ($meta_key === '_lfi_evt_url_ap')  $val = esc_url_raw(wp_unslash($_POST[$post_key] ?? ''));
        update_post_meta($post_id, $meta_key, $val);
    }
    update_post_meta($post_id, '_lfi_evt_rsvp_actif', !empty($_POST['lfi_evt_rsvp_actif']) ? '1' : '0');
}

/* ------------------------------------------------------------------ */
/* Table RSVP générique                                                */
/* ------------------------------------------------------------------ */

add_action('init', 'lfi_nct_event_rsvp_db_setup', 5);
function lfi_nct_event_rsvp_db_setup() {
    if (get_option(LFI_NCT_EVT_DBVER) === '1') return;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_event_rsvp';
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
    update_option(LFI_NCT_EVT_DBVER, '1', false);
}

/* ------------------------------------------------------------------ */
/* Rendu public : bandeau infos + formulaire RSVP en bas du contenu     */
/* ------------------------------------------------------------------ */

add_filter('the_content', 'lfi_nct_event_render_content', 20);
function lfi_nct_event_render_content($content) {
    if (!is_singular(LFI_NCT_EVT_CPT) || !in_the_loop() || !is_main_query()) return $content;
    $post = get_post();
    if (!$post) return $content;

    $date_debut = get_post_meta($post->ID, '_lfi_evt_date_debut', true);
    $date_fin   = get_post_meta($post->ID, '_lfi_evt_date_fin',   true);
    $lieu       = get_post_meta($post->ID, '_lfi_evt_lieu',       true);
    $adresse    = get_post_meta($post->ID, '_lfi_evt_adresse',    true);
    $rsvp_actif = get_post_meta($post->ID, '_lfi_evt_rsvp_actif', true);
    $url_ap     = get_post_meta($post->ID, '_lfi_evt_url_ap',     true);

    // Bandeau infos en tête
    $bandeau = '<div class="lfi-evt-bandeau" style="background:#fff3f5;border-left:6px solid #c8102e;padding:18px 22px;margin:0 0 1.5em;border-radius:6px">';
    if ($date_debut) {
        $ts_debut = strtotime($date_debut);
        $bandeau .= '<div style="font-size:1.1em;font-weight:700">📅 ' . esc_html(date_i18n('l j F Y', $ts_debut)) . ' · ' . esc_html(date_i18n('H\hi', $ts_debut));
        if ($date_fin) {
            $ts_fin = strtotime($date_fin);
            $bandeau .= ' – ' . esc_html(date_i18n('H\hi', $ts_fin));
        }
        $bandeau .= '</div>';
    }
    if ($lieu) {
        $bandeau .= '<div style="margin-top:.3em">📍 <strong>' . esc_html($lieu) . '</strong>';
        if ($adresse) $bandeau .= ' — ' . esc_html($adresse);
        $bandeau .= '</div>';
    }
    if ($url_ap) {
        $bandeau .= '<div style="margin-top:.3em;font-size:.9em">↗ <a href="' . esc_url($url_ap) . '" target="_blank" rel="noopener">Aussi sur Action Populaire</a></div>';
    }
    $bandeau .= '</div>';

    $rsvp_html = '';
    if ($rsvp_actif === '1') {
        $rsvp_html = lfi_nct_event_render_rsvp_form($post->ID);
    }

    return $bandeau . $content . $rsvp_html;
}

function lfi_nct_event_render_rsvp_form($event_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_event_rsvp';
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
            🔒 Tes coordonnées restent strictement au Groupe d'Action LFI Nantes Sud Clos Toreau,
            uniquement pour te reconfirmer cet événement.
        </p>
    </div>
    <style>
    .lfi-evt-stepper {
        display: inline-flex;
        align-items: stretch;
        border: 2px solid #c8102e;
        border-radius: 10px;
        overflow: hidden;
        background: #fff;
        user-select: none;
        -webkit-user-select: none;
    }
    .lfi-evt-stepper button {
        background: #c8102e;
        color: #fff;
        border: none;
        width: 56px;
        height: 56px;
        font-size: 1.8em;
        font-weight: 700;
        line-height: 1;
        cursor: pointer;
        touch-action: manipulation;
    }
    .lfi-evt-stepper button:hover,
    .lfi-evt-stepper button:active { background: #a30b25; }
    .lfi-evt-stepper button:disabled { background: #ddd; color: #999; cursor: not-allowed; }
    .lfi-evt-stepper input {
        width: 80px;
        text-align: center;
        font-size: 1.6em;
        font-weight: 700;
        border: none;
        background: #fff;
        color: #222;
        padding: 0;
        -moz-appearance: textfield;
    }
    .lfi-evt-stepper input::-webkit-outer-spin-button,
    .lfi-evt-stepper input::-webkit-inner-spin-button {
        -webkit-appearance: none; margin: 0;
    }
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
            minus.addEventListener('click', function(){
                input.value = (parseInt(input.value, 10) || min) - 1;
                refresh();
            });
            plus.addEventListener('click', function(){
                input.value = (parseInt(input.value, 10) || min) + 1;
                refresh();
            });
            refresh();
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* Archive : tri par date d'événement (et pas date de publication)     */
/* ------------------------------------------------------------------ */

add_action('pre_get_posts', 'lfi_nct_event_order_archive');
function lfi_nct_event_order_archive($q) {
    if (is_admin() || !$q->is_main_query()) return;
    if (!$q->is_post_type_archive(LFI_NCT_EVT_CPT)) return;
    $q->set('meta_key',  '_lfi_evt_date_debut');
    $q->set('orderby',   'meta_value');
    $q->set('order',     'ASC');
    // Par défaut on n'affiche que les événements à venir
    if (!isset($_GET['passes'])) {
        $q->set('meta_query', [[
            'key'     => '_lfi_evt_date_debut',
            'value'   => current_time('Y-m-d H:i:s'),
            'compare' => '>=',
            'type'    => 'DATETIME',
        ]]);
    }
}

/* ------------------------------------------------------------------ */
/* Shortcode [lfi_nct_agenda] — liste des événements à venir            */
/* ------------------------------------------------------------------ */

add_shortcode('lfi_nct_agenda', 'lfi_nct_event_shortcode_agenda');
function lfi_nct_event_shortcode_agenda($atts) {
    $atts = shortcode_atts(['limit' => 10], $atts);
    $events = get_posts([
        'post_type'      => LFI_NCT_EVT_CPT,
        'posts_per_page' => (int) $atts['limit'],
        'meta_key'       => '_lfi_evt_date_debut',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [[
            'key'     => '_lfi_evt_date_debut',
            'value'   => current_time('Y-m-d H:i:s'),
            'compare' => '>=',
            'type'    => 'DATETIME',
        ]],
    ]);
    if (empty($events)) {
        return '<p><em>Aucun événement programmé pour le moment. Reviens plus tard !</em></p>';
    }
    $out = '<div class="lfi-agenda" style="display:grid;gap:14px">';
    foreach ($events as $e) {
        $dd = get_post_meta($e->ID, '_lfi_evt_date_debut', true);
        $lieu = get_post_meta($e->ID, '_lfi_evt_lieu', true);
        $ts = $dd ? strtotime($dd) : 0;
        $thumb = get_the_post_thumbnail_url($e->ID, 'medium');
        $out .= '<a href="' . esc_url(get_permalink($e->ID)) . '" style="display:flex;gap:14px;align-items:center;background:#fff;padding:14px;border-left:4px solid #c8102e;border-radius:4px;text-decoration:none;color:inherit;box-shadow:0 1px 3px rgba(0,0,0,.08)">';
        if ($thumb) $out .= '<img src="' . esc_url($thumb) . '" alt="" style="width:120px;height:90px;object-fit:cover;border-radius:4px;flex-shrink:0">';
        $out .= '<div>';
        $out .= '<div style="color:#c8102e;font-weight:700;font-size:.9em">' . ($ts ? esc_html(date_i18n('l j F · H\hi', $ts)) : '') . '</div>';
        $out .= '<div style="font-size:1.15em;font-weight:700;margin:.2em 0">' . esc_html(get_the_title($e)) . '</div>';
        if ($lieu) $out .= '<div style="color:#555">📍 ' . esc_html($lieu) . '</div>';
        $out .= '</div>';
        $out .= '</a>';
    }
    $out .= '</div>';
    return $out;
}

/* ------------------------------------------------------------------ */
/* Admin : RSVP par événement                                          */
/* ------------------------------------------------------------------ */

add_filter('manage_' . LFI_NCT_EVT_CPT . '_posts_columns', 'lfi_nct_event_admin_columns');
function lfi_nct_event_admin_columns($cols) {
    $new = [];
    foreach ($cols as $k => $v) {
        $new[$k] = $v;
        if ($k === 'title') {
            $new['evt_date']  = 'Date';
            $new['evt_lieu']  = 'Lieu';
            $new['evt_rsvps'] = 'Inscrit·es';
        }
    }
    return $new;
}

add_action('manage_' . LFI_NCT_EVT_CPT . '_posts_custom_column', 'lfi_nct_event_admin_column_render', 10, 2);
function lfi_nct_event_admin_column_render($col, $post_id) {
    global $wpdb;
    if ($col === 'evt_date') {
        $dd = get_post_meta($post_id, '_lfi_evt_date_debut', true);
        echo $dd ? esc_html(date_i18n('d/m/Y H\hi', strtotime($dd))) : '—';
    } elseif ($col === 'evt_lieu') {
        echo esc_html(get_post_meta($post_id, '_lfi_evt_lieu', true) ?: '—');
    } elseif ($col === 'evt_rsvps') {
        $table = $wpdb->prefix . 'lfi_nct_event_rsvp';
        $n = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE event_id = %d", $post_id));
        $p = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(avec_qui),0) FROM $table WHERE event_id = %d", $post_id));
        echo $n > 0 ? '<strong>' . $n . '</strong> · ' . $p . ' pers.' : '—';
    }
}

add_action('admin_menu', 'lfi_nct_event_rsvp_admin_menu');
function lfi_nct_event_rsvp_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=' . LFI_NCT_EVT_CPT,
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
    $table = $wpdb->prefix . 'lfi_nct_event_rsvp';
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
            <input type="hidden" name="post_type" value="<?php echo esc_attr(LFI_NCT_EVT_CPT); ?>">
            <input type="hidden" name="page" value="lfi-nct-event-rsvp">
            <label>Événement
                <select name="event" onchange="this.form.submit()">
                    <option value="0">— tous —</option>
                    <?php
                    $events = get_posts(['post_type' => LFI_NCT_EVT_CPT, 'numberposts' => -1, 'post_status' => 'any', 'orderby' => 'date', 'order' => 'DESC']);
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
/* Pas de cache sur la page événement (nonce dynamique)                */
/* ------------------------------------------------------------------ */

add_action('wp', 'lfi_nct_event_no_cache');
function lfi_nct_event_no_cache() {
    if (!is_singular(LFI_NCT_EVT_CPT)) return;
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    do_action('litespeed_control_set_nocache', 'LFI : page événement avec nonce');
}
