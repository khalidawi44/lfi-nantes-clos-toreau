<?php
/**
 * « Partage social » — publier en 1 clic un événement du thème AG Starter
 * (post-type ag_evenement) sur :
 *   - une page Facebook
 *   - un compte Instagram Business (lié à la page FB)
 *   - un canal Telegram (via un bot)
 *
 * Le module fournit :
 *   - une page Réglages où coller les tokens / IDs (chiffrés à plat dans wp_options)
 *   - une meta box sur l'écran d'édition de l'événement, avec cases à cocher par plateforme
 *     et un bouton « Publier maintenant » (AJAX)
 *   - une option « Publier automatiquement à la prochaine sauvegarde »
 *   - les balises Open Graph sur la page publique pour que les liens partagés
 *     (WhatsApp, Telegram, X, etc.) affichent un beau preview
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_SOCIAL_OPT      = 'lfi_nct_social_share';
const LFI_NCT_SOCIAL_META_AUTO = '_lfi_nct_social_auto';
const LFI_NCT_SOCIAL_META_PLAT = '_lfi_nct_social_platforms';
const LFI_NCT_SOCIAL_META_LOG  = '_lfi_nct_social_log';

/** Slugs des CPT événement qu'on supporte. AG Starter en tête. */
function lfi_nct_social_event_cpts() {
    return array_values(array_filter([
        'ag_evenement', 'ag_event',
        'tribe_events',
        'evenement', 'event', 'events',
    ], 'post_type_exists'));
}

function lfi_nct_social_get_opts() {
    $defaults = [
        'fb_page_id'    => '',
        'fb_page_token' => '',
        'ig_user_id'    => '',
        'tg_bot_token'  => '',
        'tg_channel'    => '',
        'enabled'       => ['fb' => false, 'ig' => false, 'tg' => false],
    ];
    $opts = get_option(LFI_NCT_SOCIAL_OPT, []);
    return array_merge($defaults, is_array($opts) ? $opts : []);
}

/* ------------------------------------------------------------------ */
/* Réglages : page d'admin                                              */
/* ------------------------------------------------------------------ */

add_action('admin_menu', 'lfi_nct_social_admin_menu', 36);
function lfi_nct_social_admin_menu() {
    add_submenu_page(
        'lfi-nct-responses',
        'Partage social',
        '📣 Partage social',
        'manage_options',
        'lfi-nct-social',
        'lfi_nct_social_settings_page'
    );
}

function lfi_nct_social_settings_page() {
    if (!current_user_can('manage_options')) return;

    if (!empty($_POST['lfi_nct_social_save']) && check_admin_referer('lfi_nct_social_save')) {
        $opts = [
            'fb_page_id'    => sanitize_text_field(wp_unslash($_POST['fb_page_id']    ?? '')),
            'fb_page_token' => sanitize_text_field(wp_unslash($_POST['fb_page_token'] ?? '')),
            'ig_user_id'    => sanitize_text_field(wp_unslash($_POST['ig_user_id']    ?? '')),
            'tg_bot_token'  => sanitize_text_field(wp_unslash($_POST['tg_bot_token']  ?? '')),
            'tg_channel'    => sanitize_text_field(wp_unslash($_POST['tg_channel']    ?? '')),
            'enabled'       => [
                'fb' => !empty($_POST['enabled_fb']),
                'ig' => !empty($_POST['enabled_ig']),
                'tg' => !empty($_POST['enabled_tg']),
            ],
        ];
        update_option(LFI_NCT_SOCIAL_OPT, $opts, false);
        echo '<div class="notice notice-success is-dismissible"><p>Paramètres sauvegardés.</p></div>';
    }

    $opts = lfi_nct_social_get_opts();
    $mask = function ($s) { return $s === '' ? '' : substr($s, 0, 4) . '••••' . substr($s, -4); };
    ?>
    <div class="wrap">
        <h1>📣 Partage social — paramètres</h1>
        <p>Connectez ici vos comptes pour permettre la publication en 1 clic depuis l'écran d'édition d'un événement.</p>

        <form method="POST">
            <?php wp_nonce_field('lfi_nct_social_save'); ?>

            <h2 style="margin-top:1.5em">📘 Facebook (page)</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="fb_page_id">ID de la page Facebook</label></th>
                    <td><input id="fb_page_id" type="text" name="fb_page_id" value="<?php echo esc_attr($opts['fb_page_id']); ?>" class="regular-text" placeholder="123456789012345"></td>
                </tr>
                <tr>
                    <th><label for="fb_page_token">Page Access Token (longue durée)</label></th>
                    <td>
                        <input id="fb_page_token" type="password" name="fb_page_token" value="<?php echo esc_attr($opts['fb_page_token']); ?>" class="large-text" placeholder="<?php echo esc_attr($mask($opts['fb_page_token']) ?: 'EAAB…'); ?>">
                        <p class="description">Doit avoir les permissions <code>pages_manage_posts</code> + <code>pages_read_engagement</code>. Lien : Meta for Developers → Graph API Explorer.</p>
                    </td>
                </tr>
                <tr>
                    <th>Activer Facebook</th>
                    <td><label><input type="checkbox" name="enabled_fb" value="1" <?php checked($opts['enabled']['fb']); ?>> Oui, publier sur Facebook</label></td>
                </tr>
            </table>

            <h2 style="margin-top:1.5em">📷 Instagram (compte Business lié à la page FB)</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="ig_user_id">ID du compte Instagram Business</label></th>
                    <td>
                        <input id="ig_user_id" type="text" name="ig_user_id" value="<?php echo esc_attr($opts['ig_user_id']); ?>" class="regular-text" placeholder="17841400000000000">
                        <p class="description">Réutilise le même Page Access Token que Facebook. L'ID se trouve via <code>GET /{page-id}?fields=instagram_business_account</code>.</p>
                    </td>
                </tr>
                <tr>
                    <th>Activer Instagram</th>
                    <td><label><input type="checkbox" name="enabled_ig" value="1" <?php checked($opts['enabled']['ig']); ?>> Oui, publier sur Instagram</label></td>
                </tr>
            </table>

            <h2 style="margin-top:1.5em">💬 Telegram (canal via bot)</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="tg_bot_token">Bot Token</label></th>
                    <td>
                        <input id="tg_bot_token" type="password" name="tg_bot_token" value="<?php echo esc_attr($opts['tg_bot_token']); ?>" class="large-text" placeholder="<?php echo esc_attr($mask($opts['tg_bot_token']) ?: '1234567890:AAA…'); ?>">
                        <p class="description">Créez un bot avec <strong>@BotFather</strong> sur Telegram, puis ajoutez le bot comme admin de votre canal.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="tg_channel">Canal cible</label></th>
                    <td><input id="tg_channel" type="text" name="tg_channel" value="<?php echo esc_attr($opts['tg_channel']); ?>" class="regular-text" placeholder="@lfi_nantes_sud_clostoreau"></td>
                </tr>
                <tr>
                    <th>Activer Telegram</th>
                    <td><label><input type="checkbox" name="enabled_tg" value="1" <?php checked($opts['enabled']['tg']); ?>> Oui, publier sur Telegram</label></td>
                </tr>
            </table>

            <p style="margin-top:1.5em"><button type="submit" name="lfi_nct_social_save" value="1" class="button button-primary">💾 Enregistrer les paramètres</button></p>
        </form>

        <hr style="margin-top:2em">
        <h2>Guide rapide</h2>
        <details>
            <summary><strong>🔑 Obtenir le Page Access Token Facebook (15 min)</strong></summary>
            <ol>
                <li>Ouvrir <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">developers.facebook.com/apps</a> et créer une <em>Business app</em>.</li>
                <li>Dans l'app, ajouter le produit <strong>Facebook Login</strong> + <strong>Instagram Graph API</strong>.</li>
                <li>Aller dans <strong>Graph API Explorer</strong> (icône d'outil en haut à droite).</li>
                <li>Sélectionner votre app, puis <em>Get Token → Get User Access Token</em> avec ces permissions :
                    <code>pages_show_list</code>, <code>pages_manage_posts</code>, <code>pages_read_engagement</code>,
                    <code>instagram_basic</code>, <code>instagram_content_publish</code>.</li>
                <li>Échanger le token contre un <em>Page Access Token</em> en sélectionnant votre page LFI dans le menu déroulant des tokens.</li>
                <li>Convertir en token longue durée (60 jours) :
                    <code>GET /oauth/access_token?grant_type=fb_exchange_token&client_id=APP_ID&client_secret=APP_SECRET&fb_exchange_token=…</code></li>
                <li>Copier ce token longue durée ici, dans le champ <em>Page Access Token</em>.</li>
            </ol>
        </details>
        <details>
            <summary><strong>📷 Trouver l'ID du compte Instagram Business</strong></summary>
            <ol>
                <li>Votre compte Insta doit être en mode <em>Business</em> ou <em>Creator</em>, lié à votre page FB.</li>
                <li>Dans Graph API Explorer : <code>GET /{page-id}?fields=instagram_business_account</code>.</li>
                <li>Copier <code>instagram_business_account.id</code> ici.</li>
            </ol>
        </details>
        <details>
            <summary><strong>💬 Créer un bot Telegram (1 min)</strong></summary>
            <ol>
                <li>Sur Telegram, chercher <strong>@BotFather</strong>, démarrer la conversation.</li>
                <li>Envoyer <code>/newbot</code>, choisir un nom + un username terminé par <code>_bot</code>.</li>
                <li>BotFather renvoie un <strong>Bot Token</strong> — copier ici.</li>
                <li>Ouvrir votre canal Telegram, l'ajouter comme <em>administrateur</em> avec permission <em>Post messages</em>.</li>
                <li>Mettre le username du canal ici, format <code>@mon_canal</code> (ou son ID numérique).</li>
            </ol>
        </details>
    </div>
    <?php
}

/* ------------------------------------------------------------------ */
/* Préparation du contenu d'un événement                                */
/* ------------------------------------------------------------------ */

/**
 * Construit le payload texte + image pour un événement :
 *   ['title' => ..., 'caption' => ..., 'permalink' => ..., 'image_url' => ...|null]
 */
function lfi_nct_social_event_payload($post_id) {
    $post = get_post($post_id);
    if (!$post) return null;

    $title    = wp_strip_all_tags(get_the_title($post));
    $permalink = get_permalink($post);
    $excerpt  = trim(wp_strip_all_tags(get_the_excerpt($post)));
    if ($excerpt === '') {
        $excerpt = trim(wp_strip_all_tags($post->post_content));
    }
    $excerpt = mb_substr($excerpt, 0, 600);

    // Date/lieu via les meta keys courants d'AG Starter ou The Events Calendar.
    $date_meta = '';
    foreach (['event_date', 'date_evenement', '_event_start_date', '_EventStartDate', 'start_date'] as $k) {
        $v = get_post_meta($post_id, $k, true);
        if ($v) { $date_meta = $v; break; }
    }
    $location = '';
    foreach (['event_location', 'lieu', 'location', '_event_location', '_EventVenue'] as $k) {
        $v = get_post_meta($post_id, $k, true);
        if ($v) { $location = $v; break; }
    }
    $caption  = $title;
    if ($date_meta) $caption .= "\n📅 " . $date_meta;
    if ($location)  $caption .= "\n📍 " . $location;
    if ($excerpt)   $caption .= "\n\n" . $excerpt;
    $caption .= "\n\n👉 " . $permalink;

    $image_url = null; $image_w = 0; $image_h = 0;
    if (has_post_thumbnail($post_id)) {
        /* Grande version paysage pour l'aperçu réseaux sociaux (évite le crop
           du logo carré) : on prend la plus grande dispo et on renvoie ses
           dimensions réelles pour que les plateformes affichent la bonne carte. */
        $tid = get_post_thumbnail_id($post_id);
        $src = $tid ? wp_get_attachment_image_src($tid, 'full') : false;
        if ($src) { $image_url = $src[0]; $image_w = (int) $src[1]; $image_h = (int) $src[2]; }
        else { $image_url = get_the_post_thumbnail_url($post_id, 'large'); }
    }

    return [
        'title'     => $title,
        'caption'   => $caption,
        'permalink' => $permalink,
        'image_url' => $image_url,
        'image_w'   => $image_w,
        'image_h'   => $image_h,
        'excerpt'   => $excerpt,
    ];
}

/* ------------------------------------------------------------------ */
/* Publishers                                                          */
/* ------------------------------------------------------------------ */

function lfi_nct_social_publish_fb($post_id, $opts, $payload) {
    if ($opts['fb_page_id'] === '' || $opts['fb_page_token'] === '') {
        return ['ok' => false, 'message' => 'Facebook non configuré.'];
    }
    $endpoint = $payload['image_url']
        ? "https://graph.facebook.com/v21.0/{$opts['fb_page_id']}/photos"
        : "https://graph.facebook.com/v21.0/{$opts['fb_page_id']}/feed";
    $body = ['access_token' => $opts['fb_page_token']];
    if ($payload['image_url']) {
        $body['url']     = $payload['image_url'];
        $body['caption'] = $payload['caption'];
    } else {
        $body['message'] = $payload['caption'];
    }
    $resp = wp_remote_post($endpoint, ['timeout' => 30, 'body' => $body]);
    return lfi_nct_social_parse_graph_response($resp, 'Facebook');
}

function lfi_nct_social_publish_ig($post_id, $opts, $payload) {
    if ($opts['ig_user_id'] === '' || $opts['fb_page_token'] === '') {
        return ['ok' => false, 'message' => 'Instagram non configuré (besoin du token Facebook).'];
    }
    if (!$payload['image_url']) {
        return ['ok' => false, 'message' => 'Instagram exige une image — ajoutez une image mise en avant à l\'événement.'];
    }
    // 1) Créer un container média
    $r1 = wp_remote_post("https://graph.facebook.com/v21.0/{$opts['ig_user_id']}/media", [
        'timeout' => 30,
        'body' => [
            'image_url'    => $payload['image_url'],
            'caption'      => $payload['caption'],
            'access_token' => $opts['fb_page_token'],
        ],
    ]);
    $r1d = lfi_nct_social_parse_graph_response($r1, 'Instagram (création container)');
    if (!$r1d['ok'] || empty($r1d['raw']['id'])) return $r1d;
    $creation_id = $r1d['raw']['id'];
    // 2) Publier
    $r2 = wp_remote_post("https://graph.facebook.com/v21.0/{$opts['ig_user_id']}/media_publish", [
        'timeout' => 30,
        'body' => [
            'creation_id'  => $creation_id,
            'access_token' => $opts['fb_page_token'],
        ],
    ]);
    return lfi_nct_social_parse_graph_response($r2, 'Instagram');
}

function lfi_nct_social_publish_tg($post_id, $opts, $payload) {
    if ($opts['tg_bot_token'] === '' || $opts['tg_channel'] === '') {
        return ['ok' => false, 'message' => 'Telegram non configuré.'];
    }
    $endpoint = $payload['image_url']
        ? "https://api.telegram.org/bot{$opts['tg_bot_token']}/sendPhoto"
        : "https://api.telegram.org/bot{$opts['tg_bot_token']}/sendMessage";
    $body = ['chat_id' => $opts['tg_channel']];
    if ($payload['image_url']) {
        $body['photo']   = $payload['image_url'];
        $body['caption'] = $payload['caption'];
    } else {
        $body['text']                  = $payload['caption'];
        $body['disable_web_page_preview'] = false;
    }
    $resp = wp_remote_post($endpoint, ['timeout' => 30, 'body' => $body]);
    if (is_wp_error($resp)) {
        return ['ok' => false, 'message' => 'Telegram : ' . $resp->get_error_message()];
    }
    $code = wp_remote_retrieve_response_code($resp);
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code === 200 && !empty($data['ok'])) {
        return ['ok' => true, 'message' => 'Telegram : publié 👍', 'raw' => $data];
    }
    $desc = $data['description'] ?? 'erreur HTTP ' . $code;
    return ['ok' => false, 'message' => 'Telegram : ' . $desc];
}

function lfi_nct_social_parse_graph_response($resp, $label) {
    if (is_wp_error($resp)) {
        return ['ok' => false, 'message' => "$label : " . $resp->get_error_message()];
    }
    $code = wp_remote_retrieve_response_code($resp);
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code >= 200 && $code < 300 && !isset($data['error'])) {
        return ['ok' => true, 'message' => "$label : publié 👍", 'raw' => $data];
    }
    $msg = $data['error']['message'] ?? "HTTP $code";
    return ['ok' => false, 'message' => "$label : $msg", 'raw' => $data];
}

function lfi_nct_social_publish($post_id, $platforms = ['fb', 'ig', 'tg']) {
    $opts    = lfi_nct_social_get_opts();
    $payload = lfi_nct_social_event_payload($post_id);
    if (!$payload) return ['error' => 'Événement introuvable.'];

    $results = [];
    if (in_array('fb', $platforms, true) && $opts['enabled']['fb']) {
        $results['fb'] = lfi_nct_social_publish_fb($post_id, $opts, $payload);
    }
    if (in_array('ig', $platforms, true) && $opts['enabled']['ig']) {
        $results['ig'] = lfi_nct_social_publish_ig($post_id, $opts, $payload);
    }
    if (in_array('tg', $platforms, true) && $opts['enabled']['tg']) {
        $results['tg'] = lfi_nct_social_publish_tg($post_id, $opts, $payload);
    }

    // Log dans la post meta pour qu'on voit l'historique à la prochaine ouverture
    $log = get_post_meta($post_id, LFI_NCT_SOCIAL_META_LOG, true);
    if (!is_array($log)) $log = [];
    $log[] = ['at' => current_time('mysql'), 'results' => $results];
    update_post_meta($post_id, LFI_NCT_SOCIAL_META_LOG, $log);

    return $results;
}

/* ------------------------------------------------------------------ */
/* Meta box sur l'écran d'édition de l'événement                       */
/* ------------------------------------------------------------------ */

add_action('add_meta_boxes', 'lfi_nct_social_meta_box_register');
function lfi_nct_social_meta_box_register() {
    foreach (lfi_nct_social_event_cpts() as $cpt) {
        add_meta_box('lfi-nct-social-box', '📣 Partager sur les réseaux', 'lfi_nct_social_meta_box_render', $cpt, 'side', 'high');
    }
}

function lfi_nct_social_meta_box_render($post) {
    $opts = lfi_nct_social_get_opts();
    $auto = (bool) get_post_meta($post->ID, LFI_NCT_SOCIAL_META_AUTO, true);
    $plat = (array) get_post_meta($post->ID, LFI_NCT_SOCIAL_META_PLAT, true);
    if (!$plat) $plat = ['fb', 'ig', 'tg'];
    $log  = (array) get_post_meta($post->ID, LFI_NCT_SOCIAL_META_LOG, true);
    $nonce = wp_create_nonce('lfi_nct_social_publish_' . $post->ID);
    $settings_url = admin_url('admin.php?page=lfi-nct-social');
    ?>
    <p>Choisissez les plateformes :</p>
    <?php wp_nonce_field('lfi_nct_social_meta_save', 'lfi_nct_social_meta_nonce'); ?>
    <p>
        <label style="display:block;margin:.3em 0">
            <input type="checkbox" name="lfi_social_platforms[]" value="fb" <?php checked(in_array('fb', $plat, true)); ?> <?php disabled(!$opts['enabled']['fb']); ?>>
            📘 Facebook <?php echo $opts['enabled']['fb'] ? '' : '<em style="color:#999">(non configuré)</em>'; ?>
        </label>
        <label style="display:block;margin:.3em 0">
            <input type="checkbox" name="lfi_social_platforms[]" value="ig" <?php checked(in_array('ig', $plat, true)); ?> <?php disabled(!$opts['enabled']['ig']); ?>>
            📷 Instagram <?php echo $opts['enabled']['ig'] ? '' : '<em style="color:#999">(non configuré)</em>'; ?>
        </label>
        <label style="display:block;margin:.3em 0">
            <input type="checkbox" name="lfi_social_platforms[]" value="tg" <?php checked(in_array('tg', $plat, true)); ?> <?php disabled(!$opts['enabled']['tg']); ?>>
            💬 Telegram <?php echo $opts['enabled']['tg'] ? '' : '<em style="color:#999">(non configuré)</em>'; ?>
        </label>
    </p>

    <p style="border-top:1px solid #eee;padding-top:.6em">
        <label>
            <input type="checkbox" name="lfi_social_auto" value="1" <?php checked($auto); ?>>
            Publier automatiquement à la prochaine sauvegarde
        </label>
    </p>

    <p>
        <button type="button" class="button button-primary" id="lfi-nct-social-publish-now"
                data-post="<?php echo (int) $post->ID; ?>"
                data-nonce="<?php echo esc_attr($nonce); ?>">
            📣 Publier maintenant
        </button>
    </p>
    <div id="lfi-nct-social-result" style="min-height:1.5em"></div>

    <?php if (!empty($log)): ?>
        <details style="margin-top:.6em">
            <summary>Historique (<?php echo count($log); ?>)</summary>
            <ul style="font-size:.85em;margin:.4em 0 0 1em">
                <?php foreach (array_reverse($log) as $entry): ?>
                    <li>
                        <strong><?php echo esc_html($entry['at']); ?></strong>
                        <?php foreach ($entry['results'] as $plat_k => $res): ?>
                            <br>· <?php echo $res['ok'] ? '✅' : '❌'; ?> <?php echo esc_html($res['message']); ?>
                        <?php endforeach; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>

    <p class="description" style="margin-top:.8em">
        <a href="<?php echo esc_url($settings_url); ?>">⚙️ Configurer les comptes</a>
    </p>

    <script>
    (function(){
        var btn = document.getElementById('lfi-nct-social-publish-now');
        var box = document.getElementById('lfi-nct-social-result');
        if (!btn) return;
        btn.addEventListener('click', function(){
            var plats = Array.prototype.slice.call(document.querySelectorAll('input[name="lfi_social_platforms[]"]:checked')).map(function(el){ return el.value; });
            if (!plats.length) { box.innerHTML = '<em style="color:#a00">Cochez au moins une plateforme.</em>'; return; }
            btn.disabled = true;
            box.innerHTML = '⏳ Publication en cours…';
            var fd = new FormData();
            fd.append('action', 'lfi_nct_social_publish');
            fd.append('post_id', btn.dataset.post);
            fd.append('_ajax_nonce', btn.dataset.nonce);
            plats.forEach(function(p){ fd.append('platforms[]', p); });
            fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(j){
                    btn.disabled = false;
                    if (!j.success) { box.innerHTML = '<em style="color:#a00">Erreur : ' + (j.data || 'inconnue') + '</em>'; return; }
                    var html = '';
                    Object.keys(j.data).forEach(function(k){
                        var r = j.data[k];
                        html += '<div>' + (r.ok ? '✅' : '❌') + ' ' + r.message + '</div>';
                    });
                    box.innerHTML = html || '<em>Aucune plateforme active.</em>';
                })
                .catch(function(){ btn.disabled = false; box.innerHTML = '<em style="color:#a00">Erreur réseau.</em>'; });
        });
    })();
    </script>
    <?php
}

add_action('save_post', 'lfi_nct_social_meta_save', 10, 2);
function lfi_nct_social_meta_save($post_id, $post) {
    if (!isset($_POST['lfi_nct_social_meta_nonce'])) return;
    if (!wp_verify_nonce($_POST['lfi_nct_social_meta_nonce'], 'lfi_nct_social_meta_save')) return;
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    if (!in_array($post->post_type, lfi_nct_social_event_cpts(), true)) return;
    if (!current_user_can('edit_post', $post_id)) return;
    update_post_meta($post_id, LFI_NCT_SOCIAL_META_AUTO, !empty($_POST['lfi_social_auto']) ? 1 : 0);
    $platforms = array_map('sanitize_text_field', (array) ($_POST['lfi_social_platforms'] ?? []));
    update_post_meta($post_id, LFI_NCT_SOCIAL_META_PLAT, $platforms);
}

/* ------------------------------------------------------------------ */
/* Auto-publication à la sauvegarde si la case est cochée              */
/* ------------------------------------------------------------------ */

add_action('save_post', 'lfi_nct_social_auto_publish_on_save', 20, 3);
function lfi_nct_social_auto_publish_on_save($post_id, $post, $update) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    if (!in_array($post->post_type, lfi_nct_social_event_cpts(), true)) return;
    if ($post->post_status !== 'publish') return;
    $auto = (int) get_post_meta($post_id, LFI_NCT_SOCIAL_META_AUTO, true);
    if (!$auto) return;
    // Ne re-publie pas si déjà fait pour ce post (on garde le drapeau)
    if (get_post_meta($post_id, '_lfi_nct_social_done', true)) return;
    $plat = (array) get_post_meta($post_id, LFI_NCT_SOCIAL_META_PLAT, true);
    if (!$plat) $plat = ['fb', 'ig', 'tg'];
    lfi_nct_social_publish($post_id, $plat);
    update_post_meta($post_id, '_lfi_nct_social_done', current_time('mysql'));
}

/* ------------------------------------------------------------------ */
/* AJAX : publier maintenant                                            */
/* ------------------------------------------------------------------ */

add_action('wp_ajax_lfi_nct_social_publish', 'lfi_nct_social_publish_ajax');
function lfi_nct_social_publish_ajax() {
    $post_id = (int) ($_POST['post_id'] ?? 0);
    if (!$post_id) wp_send_json_error('post_id manquant');
    if (!check_ajax_referer('lfi_nct_social_publish_' . $post_id, '_ajax_nonce', false)) {
        wp_send_json_error('nonce invalide');
    }
    if (!current_user_can('edit_post', $post_id)) wp_send_json_error('permission refusée');
    $platforms = array_map('sanitize_text_field', (array) ($_POST['platforms'] ?? []));
    $result = lfi_nct_social_publish($post_id, $platforms);
    wp_send_json_success($result);
}

/* ------------------------------------------------------------------ */
/* Open Graph : preview joli quand on partage l'URL de l'événement     */
/* ------------------------------------------------------------------ */

add_action('wp_head', 'lfi_nct_social_og_meta', 5);
function lfi_nct_social_og_meta() {
    if (!is_singular(lfi_nct_social_event_cpts())) return;
    global $post;
    $payload = lfi_nct_social_event_payload($post->ID);
    if (!$payload) return;
    echo '<meta property="og:title" content="' . esc_attr($payload['title']) . '">' . "\n";
    if ($payload['excerpt']) {
        echo '<meta property="og:description" content="' . esc_attr($payload['excerpt']) . '">' . "\n";
    }
    if ($payload['image_url']) {
        echo '<meta property="og:image" content="' . esc_url($payload['image_url']) . '">' . "\n";
        echo '<meta property="og:image:secure_url" content="' . esc_url($payload['image_url']) . '">' . "\n";
        /* Dimensions réelles → les plateformes (Telegram, FB…) affichent la
           bonne carte au lieu de rogner au hasard. */
        if (!empty($payload['image_w']) && !empty($payload['image_h'])) {
            echo '<meta property="og:image:width" content="' . (int) $payload['image_w'] . '">' . "\n";
            echo '<meta property="og:image:height" content="' . (int) $payload['image_h'] . '">' . "\n";
        }
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    }
    echo '<meta property="og:url" content="' . esc_url($payload['permalink']) . '">' . "\n";
    echo '<meta property="og:type" content="event">' . "\n";
}
