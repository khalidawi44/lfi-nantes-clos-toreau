<?php
/**
 * Synchronisation automatique GitHub -> site WordPress.
 *
 * Hébergement mutualisé (sans SSH/git) : on télécharge le zip de la branche
 * via l'API GitHub et on remplace les fichiers du plugin avec WP_Upgrader.
 * Un WP-Cron vérifie toutes les 5 minutes si la branche suivie a changé ;
 * si oui, il redéploie et vide le cache. Actions manuelles disponibles aussi.
 */
if (!defined('ABSPATH')) exit;

define('LFI_NCT_SYNC_REPO', 'khalidawi44/lfi-nantes-clos-toreau');
define('LFI_NCT_SYNC_CRON_HOOK', 'lfi_nct_sync_cron');
define('LFI_NCT_SYNC_SCHEDULE', 'lfi_nct_five_minutes');

/* ------------------------------------------------------------------ */
/* Réglages                                                            */
/* ------------------------------------------------------------------ */

function lfi_nct_sync_get_branch() {
    $b = trim((string) get_option('lfi_nct_sync_branch', 'main'));
    return $b === '' ? 'main' : $b;
}
function lfi_nct_sync_get_token() {
    return trim((string) get_option('lfi_nct_sync_token', ''));
}
function lfi_nct_sync_is_enabled() {
    return (bool) get_option('lfi_nct_sync_enabled', 0);
}

/* ------------------------------------------------------------------ */
/* Planification du cron toutes les 5 minutes                          */
/* ------------------------------------------------------------------ */

add_filter('cron_schedules', 'lfi_nct_sync_add_schedule');
function lfi_nct_sync_add_schedule($schedules) {
    $schedules[LFI_NCT_SYNC_SCHEDULE] = [
        'interval' => 5 * MINUTE_IN_SECONDS,
        'display'  => 'Toutes les 5 minutes (LFI sync)',
    ];
    return $schedules;
}

function lfi_nct_sync_schedule_event() {
    if (!wp_next_scheduled(LFI_NCT_SYNC_CRON_HOOK)) {
        wp_schedule_event(time() + MINUTE_IN_SECONDS, LFI_NCT_SYNC_SCHEDULE, LFI_NCT_SYNC_CRON_HOOK);
    }
}

function lfi_nct_sync_unschedule_event() {
    wp_clear_scheduled_hook(LFI_NCT_SYNC_CRON_HOOK);
}

add_action(LFI_NCT_SYNC_CRON_HOOK, 'lfi_nct_sync_run_cron');
function lfi_nct_sync_run_cron() {
    if (!lfi_nct_sync_is_enabled()) return;
    lfi_nct_sync_run(false);
}

/* ------------------------------------------------------------------ */
/* API GitHub                                                          */
/* ------------------------------------------------------------------ */

function lfi_nct_sync_api_headers($accept = 'application/vnd.github+json') {
    $headers = [
        'Accept'     => $accept,
        'User-Agent' => 'lfi-nct-sync',
    ];
    $token = lfi_nct_sync_get_token();
    if ($token !== '') {
        $headers['Authorization'] = 'Bearer ' . $token;
    }
    return $headers;
}

/**
 * Récupère le SHA du dernier commit de la branche suivie (réponse légère).
 * @return string|WP_Error
 */
function lfi_nct_sync_get_remote_sha() {
    $url = sprintf(
        'https://api.github.com/repos/%s/commits/%s',
        LFI_NCT_SYNC_REPO,
        rawurlencode(lfi_nct_sync_get_branch())
    );
    $resp = wp_remote_get($url, [
        'headers' => lfi_nct_sync_api_headers('application/vnd.github.sha'),
        'timeout' => 20,
    ]);
    if (is_wp_error($resp)) return $resp;

    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200) {
        return new WP_Error('lfi_nct_sync_api', 'GitHub a répondu HTTP ' . $code . '.');
    }
    $sha = trim(wp_remote_retrieve_body($resp));
    if (!preg_match('/^[0-9a-f]{40}$/', $sha)) {
        return new WP_Error('lfi_nct_sync_api', 'SHA distant invalide.');
    }
    return $sha;
}

/* ------------------------------------------------------------------ */
/* Déploiement                                                         */
/* ------------------------------------------------------------------ */

/**
 * Vérifie la branche et déploie si nécessaire.
 * @param bool $force  Redéploie même si le SHA n'a pas changé.
 * @return string|WP_Error  'updated' | 'up-to-date' | WP_Error
 */
function lfi_nct_sync_run($force = false) {
    $remote_sha = lfi_nct_sync_get_remote_sha();
    if (is_wp_error($remote_sha)) {
        lfi_nct_sync_log('error', $remote_sha->get_error_message());
        return $remote_sha;
    }

    update_option('lfi_nct_sync_last_check', time(), false);

    $installed_sha = get_option('lfi_nct_sync_installed_sha', '');
    if (!$force && $remote_sha === $installed_sha) {
        return 'up-to-date';
    }

    $zip_url = sprintf(
        'https://api.github.com/repos/%s/zipball/%s',
        LFI_NCT_SYNC_REPO,
        rawurlencode(lfi_nct_sync_get_branch())
    );

    $installed = lfi_nct_sync_install_from_url($zip_url);
    if (is_wp_error($installed)) {
        lfi_nct_sync_log('error', $installed->get_error_message());
        return $installed;
    }

    update_option('lfi_nct_sync_installed_sha', $remote_sha, false);
    update_option('lfi_nct_sync_last_sync', time(), false);
    lfi_nct_sync_log('success', 'Déployé sur ' . substr($remote_sha, 0, 8) . ' (branche ' . lfi_nct_sync_get_branch() . ').');

    lfi_nct_purge_cache();
    return 'updated';
}

/**
 * Télécharge le zip GitHub puis remplace les fichiers du plugin.
 * @return true|WP_Error
 */
function lfi_nct_sync_install_from_url($zip_url) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $local_zip = lfi_nct_sync_download($zip_url);
    if (is_wp_error($local_zip)) return $local_zip;

    $upgrader = new WP_Upgrader(new Automatic_Upgrader_Skin());
    $upgrader->init();

    $result = $upgrader->run([
        'package'                     => $local_zip,
        'destination'                 => untrailingslashit(LFI_NCT_PATH),
        'clear_destination'           => true,
        'clear_working'               => true,
        'abort_if_destination_exists' => false,
        'is_multi'                    => false,
        'hook_extra'                  => ['type' => 'plugin', 'action' => 'update'],
    ]);

    if (file_exists($local_zip)) {
        @unlink($local_zip);
    }

    if (is_wp_error($result)) return $result;
    if ($result === false) {
        return new WP_Error(
            'lfi_nct_sync_fs',
            "Accès au système de fichiers refusé. L'hébergeur exige peut-être des identifiants FTP pour écrire les fichiers."
        );
    }
    return true;
}

/**
 * Télécharge l'archive dans un fichier temporaire (avec en-têtes API/token).
 * @return string|WP_Error  Chemin du fichier temporaire.
 */
function lfi_nct_sync_download($url) {
    require_once ABSPATH . 'wp-admin/includes/file.php';

    $tmp = wp_tempnam('lfi-nct-sync.zip');
    if (!$tmp) {
        return new WP_Error('lfi_nct_sync_tmp', 'Impossible de créer un fichier temporaire.');
    }

    $resp = wp_remote_get($url, [
        'headers'  => lfi_nct_sync_api_headers(),
        'timeout'  => 60,
        'stream'   => true,
        'filename' => $tmp,
    ]);

    if (is_wp_error($resp)) {
        @unlink($tmp);
        return $resp;
    }
    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200) {
        @unlink($tmp);
        return new WP_Error('lfi_nct_sync_dl', 'Téléchargement GitHub échoué (HTTP ' . $code . ').');
    }
    return $tmp;
}

/* ------------------------------------------------------------------ */
/* Purge du cache                                                      */
/* ------------------------------------------------------------------ */

/**
 * Vide tout cache détecté (objet + page). Inoffensif si absent.
 * @return string[]  Liste des caches vidés.
 */
function lfi_nct_purge_cache() {
    $done = [];

    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
        $done[] = 'cache objet';
    }
    if (function_exists('wp_cache_clear_cache')) { // WP Super Cache
        wp_cache_clear_cache();
        $done[] = 'WP Super Cache';
    }
    if (function_exists('w3tc_flush_all')) { // W3 Total Cache
        w3tc_flush_all();
        $done[] = 'W3 Total Cache';
    }
    if (function_exists('rocket_clean_domain')) { // WP Rocket
        rocket_clean_domain();
        $done[] = 'WP Rocket';
    }
    global $wp_fastest_cache; // WP Fastest Cache
    if (is_object($wp_fastest_cache) && method_exists($wp_fastest_cache, 'deleteCache')) {
        $wp_fastest_cache->deleteCache(true);
        $done[] = 'WP Fastest Cache';
    }
    if (defined('LSCWP_V') || has_action('litespeed_purge_all')) { // LiteSpeed Cache
        do_action('litespeed_purge_all');
        $done[] = 'LiteSpeed Cache';
    }
    if (class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall')) { // Autoptimize
        autoptimizeCache::clearall();
        $done[] = 'Autoptimize';
    }
    if (class_exists('Cache_Enabler') && method_exists('Cache_Enabler', 'clear_total_cache')) { // Cache Enabler
        Cache_Enabler::clear_total_cache();
        $done[] = 'Cache Enabler';
    }
    if (function_exists('sg_cachepress_purge_cache')) { // SiteGround SG Optimizer
        sg_cachepress_purge_cache();
        $done[] = 'SG Optimizer';
    }

    update_option('lfi_nct_sync_last_purge', time(), false);
    lfi_nct_sync_log('purge', 'Cache vidé : ' . (empty($done) ? 'aucun cache de page détecté' : implode(', ', $done)));
    return $done;
}

/* ------------------------------------------------------------------ */
/* Journal                                                             */
/* ------------------------------------------------------------------ */

function lfi_nct_sync_log($type, $message) {
    $log = get_option('lfi_nct_sync_log', []);
    if (!is_array($log)) $log = [];
    array_unshift($log, [
        'time' => current_time('mysql'),
        'type' => $type,
        'msg'  => $message,
    ]);
    update_option('lfi_nct_sync_log', array_slice($log, 0, 20), false);
}

/* ------------------------------------------------------------------ */
/* Page d'administration                                               */
/* ------------------------------------------------------------------ */

add_action('admin_menu', 'lfi_nct_sync_admin_menu', 20);
function lfi_nct_sync_admin_menu() {
    add_submenu_page(
        'lfi-nct-responses',
        'LFI Clos Toreau — Synchronisation',
        '🔄 Synchronisation',
        'manage_options',
        'lfi-nct-sync',
        'lfi_nct_sync_admin_page'
    );
}

function lfi_nct_sync_admin_page() {
    if (!current_user_can('manage_options')) return;

    $notice = '';
    $notice_type = 'updated';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('lfi_nct_sync_action');

        if (isset($_POST['lfi_nct_sync_save'])) {
            $enabled = !empty($_POST['lfi_nct_sync_enabled']) ? 1 : 0;
            $branch  = sanitize_text_field(wp_unslash($_POST['lfi_nct_sync_branch'] ?? 'main'));
            $token   = sanitize_text_field(wp_unslash($_POST['lfi_nct_sync_token'] ?? ''));

            update_option('lfi_nct_sync_branch', ($branch === '' ? 'main' : $branch), false);
            update_option('lfi_nct_sync_token', $token, false);
            update_option('lfi_nct_sync_enabled', $enabled, false);

            if ($enabled) {
                lfi_nct_sync_schedule_event();
                // Baseline : on évite un premier redéploiement inutile (auto-écrasement).
                if (get_option('lfi_nct_sync_installed_sha', '') === '') {
                    $sha = lfi_nct_sync_get_remote_sha();
                    if (!is_wp_error($sha)) {
                        update_option('lfi_nct_sync_installed_sha', $sha, false);
                    }
                }
                $notice = 'Réglages enregistrés. Synchro automatique activée (vérification toutes les 5 minutes).';
            } else {
                lfi_nct_sync_unschedule_event();
                $notice = 'Réglages enregistrés. Synchro automatique désactivée.';
            }
        } elseif (isset($_POST['lfi_nct_sync_now'])) {
            $res = lfi_nct_sync_run(true);
            if (is_wp_error($res)) {
                $notice = 'Échec de la synchronisation : ' . $res->get_error_message();
                $notice_type = 'error';
            } else {
                $notice = 'Synchronisation effectuée et cache vidé.';
            }
        } elseif (isset($_POST['lfi_nct_purge'])) {
            $done = lfi_nct_purge_cache();
            $notice = 'Cache vidé : ' . (empty($done) ? 'aucun cache de page détecté.' : implode(', ', $done));
        }
    }

    $enabled       = lfi_nct_sync_is_enabled();
    $branch        = lfi_nct_sync_get_branch();
    $token         = lfi_nct_sync_get_token();
    $installed_sha = get_option('lfi_nct_sync_installed_sha', '');
    $last_sync     = get_option('lfi_nct_sync_last_sync', 0);
    $last_check    = get_option('lfi_nct_sync_last_check', 0);
    $last_purge    = get_option('lfi_nct_sync_last_purge', 0);
    $next_cron     = wp_next_scheduled(LFI_NCT_SYNC_CRON_HOOK);
    $log           = get_option('lfi_nct_sync_log', []);
    if (!is_array($log)) $log = [];

    $remote_sha = lfi_nct_sync_get_remote_sha();
    $remote_err = is_wp_error($remote_sha) ? $remote_sha->get_error_message() : '';
    $up_to_date = (!$remote_err && $installed_sha && $remote_sha === $installed_sha);

    $fmt = function ($ts) {
        return $ts ? esc_html(wp_date('d/m/Y H:i:s', $ts)) : '—';
    };
    ?>
    <div class="wrap">
        <h1>LFI Clos Toreau — Synchronisation GitHub</h1>

        <?php if ($notice): ?>
            <div class="<?php echo $notice_type === 'error' ? 'notice notice-error' : 'notice notice-success'; ?>">
                <p><?php echo esc_html($notice); ?></p>
            </div>
        <?php endif; ?>

        <?php if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON): ?>
            <div class="notice notice-warning">
                <p><strong>Attention :</strong> WP-Cron est désactivé (<code>DISABLE_WP_CRON</code>). La synchro automatique
                ne se déclenchera que si une tâche cron système appelle <code>wp-cron.php</code>.</p>
            </div>
        <?php endif; ?>

        <h2>État</h2>
        <table class="widefat striped" style="max-width:760px">
            <tbody>
                <tr>
                    <th style="width:240px">Synchro automatique</th>
                    <td><?php echo $enabled
                        ? '<span style="color:#1a7f37;font-weight:600">✅ Activée</span> (toutes les 5 min)'
                        : '<span style="color:#b32d2e;font-weight:600">⏸ Désactivée</span>'; ?></td>
                </tr>
                <tr><th>Dépôt</th><td><code><?php echo esc_html(LFI_NCT_SYNC_REPO); ?></code></td></tr>
                <tr><th>Branche suivie</th><td><code><?php echo esc_html($branch); ?></code></td></tr>
                <tr>
                    <th>Version installée (SHA)</th>
                    <td><code><?php echo $installed_sha ? esc_html(substr($installed_sha, 0, 8)) : '—'; ?></code></td>
                </tr>
                <tr>
                    <th>Version distante (SHA)</th>
                    <td>
                        <?php if ($remote_err): ?>
                            <span style="color:#b32d2e">Erreur : <?php echo esc_html($remote_err); ?></span>
                        <?php else: ?>
                            <code><?php echo esc_html(substr($remote_sha, 0, 8)); ?></code>
                            <?php echo $up_to_date
                                ? ' <span style="color:#1a7f37">— à jour</span>'
                                : ' <span style="color:#bd8600">— mise à jour disponible</span>'; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr><th>Dernier déploiement</th><td><?php echo $fmt($last_sync); ?></td></tr>
                <tr><th>Dernière vérification</th><td><?php echo $fmt($last_check); ?></td></tr>
                <tr><th>Dernière purge cache</th><td><?php echo $fmt($last_purge); ?></td></tr>
                <tr><th>Prochaine vérification auto</th><td><?php echo $fmt($next_cron); ?></td></tr>
            </tbody>
        </table>

        <h2 style="margin-top:2em">Actions manuelles</h2>
        <p style="display:flex;gap:10px;flex-wrap:wrap">
            <form method="post" style="margin:0">
                <?php wp_nonce_field('lfi_nct_sync_action'); ?>
                <button type="submit" name="lfi_nct_sync_now" value="1" class="button button-primary">
                    🔄 Synchroniser maintenant
                </button>
            </form>
            <form method="post" style="margin:0">
                <?php wp_nonce_field('lfi_nct_sync_action'); ?>
                <button type="submit" name="lfi_nct_purge" value="1" class="button">
                    🧹 Vider le cache
                </button>
            </form>
        </p>
        <p class="description">« Synchroniser maintenant » télécharge la branche <code><?php echo esc_html($branch); ?></code>,
        remplace les fichiers du plugin puis vide le cache.</p>

        <h2 style="margin-top:2em">Réglages</h2>
        <form method="post">
            <?php wp_nonce_field('lfi_nct_sync_action'); ?>
            <table class="form-table" role="presentation" style="max-width:760px">
                <tr>
                    <th scope="row">Synchro automatique</th>
                    <td>
                        <label>
                            <input type="checkbox" name="lfi_nct_sync_enabled" value="1" <?php checked($enabled); ?>>
                            Activer la vérification automatique toutes les 5 minutes
                        </label>
                        <p class="description">Conseil : cliquez d'abord sur « Synchroniser maintenant » pour vérifier que
                        l'écriture des fichiers fonctionne, puis activez l'automatique.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="lfi_nct_sync_branch">Branche suivie</label></th>
                    <td>
                        <input type="text" id="lfi_nct_sync_branch" name="lfi_nct_sync_branch"
                               value="<?php echo esc_attr($branch); ?>" class="regular-text">
                        <p class="description">Par défaut <code>main</code>.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="lfi_nct_sync_token">Token GitHub (optionnel)</label></th>
                    <td>
                        <input type="password" id="lfi_nct_sync_token" name="lfi_nct_sync_token"
                               value="<?php echo esc_attr($token); ?>" class="regular-text" autocomplete="off">
                        <p class="description">Dépôt public : non requis. Recommandé sur hébergement mutualisé pour éviter
                        les limites de débit de l'API GitHub (IP partagée). Indispensable si le dépôt devient privé.</p>
                    </td>
                </tr>
            </table>
            <p><button type="submit" name="lfi_nct_sync_save" value="1" class="button button-primary">Enregistrer</button></p>
        </form>

        <h2 style="margin-top:2em">Journal (20 dernières entrées)</h2>
        <table class="widefat striped" style="max-width:900px">
            <thead><tr><th style="width:160px">Date</th><th style="width:90px">Type</th><th>Message</th></tr></thead>
            <tbody>
                <?php if (empty($log)): ?>
                    <tr><td colspan="3">Aucune activité pour l'instant.</td></tr>
                <?php else: foreach ($log as $entry): ?>
                    <tr>
                        <td><?php echo esc_html($entry['time'] ?? ''); ?></td>
                        <td><?php echo esc_html($entry['type'] ?? ''); ?></td>
                        <td><?php echo esc_html($entry['msg'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
