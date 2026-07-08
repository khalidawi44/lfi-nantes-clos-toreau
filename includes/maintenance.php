<?php
/**
 * Page Maintenance — gros bouton « tout purger / tout rejouer ».
 *
 * Quand l'animateur·ice en a marre des caches qui retiennent les changements,
 * ou des seeds qui ne sont pas (re)joués, il clique le bouton et tout repart
 * propre : caches purgés, flags d'idempotence reset, hooks init re-déclenchés.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'lfi_nct_maintenance_menu', 99);
function lfi_nct_maintenance_menu() {
    add_submenu_page(
        'lfi-nct-responses',
        'Maintenance — purger tout',
        '🔄 Maintenance',
        'manage_options',
        'lfi-nct-maintenance',
        'lfi_nct_maintenance_page'
    );
}

function lfi_nct_maintenance_page() {
    if (!current_user_can('manage_options')) return;
    nocache_headers();

    $notices = [];

    if (!empty($_POST['lfi_nct_purge_all']) && check_admin_referer('lfi_nct_purge_all')) {
        $notices = lfi_nct_run_full_purge();
    }
    ?>
    <div class="wrap">
        <h1>🔄 Maintenance</h1>

        <?php foreach ($notices as $n): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo wp_kses_post($n); ?></p></div>
        <?php endforeach; ?>

        <div style="background:#fff3f5;border-left:6px solid #c8102e;padding:24px;margin:20px 0;border-radius:6px">
            <h2 style="margin:0 0 .5em;color:#c8102e">🔥 PURGER TOUT MAINTENANT</h2>
            <p style="margin:0 0 1em">
                Vide tous les caches (LiteSpeed, cache objet WP, OPcache PHP, plugins tiers),
                réinitialise tous les flags d'idempotence (les seeds et migrations rejouent),
                relance les hooks <code>init</code>.
            </p>
            <p style="margin:0 0 1em"><strong>Conseil :</strong> après le clic, fais <kbd>Ctrl+F5</kbd> (ou <kbd>Cmd+Shift+R</kbd> sur Mac) sur ton onglet du site pour aussi vider le cache navigateur.</p>
            <form method="post" onsubmit="return confirm('Purger tous les caches, réinitialiser tous les flags et rejouer tous les seeds/migrations ?');">
                <?php wp_nonce_field('lfi_nct_purge_all'); ?>
                <button type="submit" name="lfi_nct_purge_all" value="1" class="button button-primary" style="background:#c8102e;border-color:#a30b25;font-size:1.2em;padding:10px 24px;height:auto;font-weight:700">
                    🔥 PURGER TOUT MAINTENANT
                </button>
            </form>
        </div>

        <details style="background:#fff;padding:18px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08)">
            <summary style="cursor:pointer;font-weight:600">Ce que fait le bouton (détail)</summary>
            <ol style="margin-top:.8em">
                <li><code>do_action('litespeed_purge_all')</code> — purge LiteSpeed Cache</li>
                <li><code>wp_cache_flush()</code> — vide le cache objet WordPress</li>
                <li><code>opcache_reset()</code> — réinitialise OPcache PHP</li>
                <li><code>wp_cache_clear_cache()</code> / <code>w3tc_flush_all()</code> / <code>rocket_clean_domain()</code> — purge plugins tiers s'ils sont actifs</li>
                <li><code>delete_option(*)</code> — réinitialise les flags d'idempotence : autopurge des démos, seed de la réunion 26 juin, migration legacy <code>lfi_evenement</code>, resync miroirs, autopurge cache à l'upgrade</li>
                <li><code>flush_rewrite_rules()</code> — réécrit les permaliens</li>
                <li><code>do_action('init')</code> — relance les hooks (l'autopurge démos, le seed réunion, la migration repassent immédiatement)</li>
                <li>Re-purge finale LiteSpeed pour que le front reflète tout</li>
            </ol>
        </details>
    </div>
    <?php
}

/**
 * Exécute la purge complète. Renvoie un array de messages.
 */
function lfi_nct_run_full_purge() {
    $msgs = [];

    // 1. LiteSpeed Cache
    do_action('litespeed_purge_all');
    $msgs[] = '✅ LiteSpeed Cache purgé';

    // 2. WP object cache
    if (function_exists('wp_cache_flush')) wp_cache_flush();
    $msgs[] = '✅ Cache objet WordPress vidé';

    // 3. OPcache PHP
    if (function_exists('opcache_reset')) {
        @opcache_reset();
        $msgs[] = '✅ OPcache PHP réinitialisé';
    }

    // 4. Plugins tiers
    if (function_exists('wp_cache_clear_cache')) wp_cache_clear_cache(); // WP Super Cache
    if (function_exists('w3tc_flush_all'))       w3tc_flush_all();       // W3 Total Cache
    if (function_exists('rocket_clean_domain'))  rocket_clean_domain();  // WP Rocket
    $msgs[] = '✅ Caches plugins tiers purgés (si présents)';

    // 5. Reset des flags d'idempotence
    $flags = [
        'lfi_nct_autopurge_demos_v1',
        'lfi_nct_seed_reunion_26juin_v3',
        'lfi_nct_seed_reunion_26juin_v2',
        'lfi_nct_seed_reunion_26juin_cpt',
        'lfi_nct_migrate_lfi_evenement_to_ag',
        'lfi_nct_mirror_resync_version',
        'lfi_nct_mirror_last_run_ts',
        'lfi_nct_event_rewrite_flushed',
        'lfi_nct_installed_version',
    ];
    foreach ($flags as $opt) delete_option($opt);
    $msgs[] = '✅ ' . count($flags) . ' flag(s) d\'idempotence réinitialisés';

    // 6. Permaliens
    flush_rewrite_rules(false);
    $msgs[] = '✅ Permaliens réécrits';

    // 7. Relance des hooks init pour rejouer seeds + migrations + autopurge
    do_action('init');
    $msgs[] = '✅ Hooks <code>init</code> relancés (seeds + migrations + autopurge démos rejoués)';

    // 8. Re-purge finale
    do_action('litespeed_purge_all');
    if (function_exists('wp_cache_flush')) wp_cache_flush();
    $msgs[] = '✅ Re-purge finale après seeds';

    $msgs[] = '<strong>🎉 Terminé.</strong> Rafraîchis l\'onglet du site (<kbd>Ctrl+F5</kbd> ou <kbd>Cmd+Shift+R</kbd>) pour voir le résultat immédiatement.';

    return $msgs;
}
