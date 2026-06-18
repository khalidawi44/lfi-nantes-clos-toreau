<?php
/**
 * Module Membres du Groupe d'Action — annuaire WP des inscrit·es Action Populaire.
 *
 * - Table wp_lfi_nct_membres (PII des membres : email, tél, adresse, statut).
 * - Import CSV au format Action Populaire (Statut/Pseudo/Nom/Prénom/E-mail/Téléphone/Adresse/Membre depuis le/Abonnement).
 * - Page admin Membres : liste filtrée, recherche, édition, suppression individuelle, désabonnement local.
 * - Liens d'auto-désabonnement publics (token unique par membre, pour les emails de masse).
 *
 * RGPD : aucune donnée n'est exposée publiquement, sauf la page unsubscribe (qui ne montre
 * que « vous êtes désabonné·e »). Aucun export tiers automatique.
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_MEMBRES_DBVER  = 'lfi_nct_membres_db_ver';

/* ------------------------------------------------------------------ */
/* DB                                                                  */
/* ------------------------------------------------------------------ */

add_action('init', 'lfi_nct_membres_db_setup', 5);
function lfi_nct_membres_db_setup() {
    if (get_option(LFI_NCT_MEMBRES_DBVER) === '1') return;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_membres';
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        statut VARCHAR(40) DEFAULT '',
        pseudo VARCHAR(120) DEFAULT '',
        prenom VARCHAR(120) DEFAULT '',
        nom VARCHAR(120) DEFAULT '',
        email VARCHAR(190) DEFAULT '',
        tel VARCHAR(40) DEFAULT '',
        adresse VARCHAR(255) DEFAULT '',
        membre_depuis DATETIME NULL,
        abonne_ap TINYINT(1) DEFAULT 1,
        abonne_emails TINYINT(1) DEFAULT 1,
        jetable TINYINT(1) DEFAULT 0,
        unsubscribe_token VARCHAR(64) DEFAULT '',
        source VARCHAR(40) DEFAULT 'action_populaire',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY email (email),
        KEY statut (statut),
        KEY abonne_emails (abonne_emails),
        KEY jetable (jetable)
    ) $charset;");
    update_option(LFI_NCT_MEMBRES_DBVER, '1', false);
}

/**
 * Liste (non exhaustive) de domaines d'emails jetables pour exclusion auto des envois en masse.
 * On peut en ajouter via le filtre `lfi_nct_disposable_email_domains`.
 */
function lfi_nct_disposable_domains() {
    $defaults = [
        'yopmail.com', 'mailinator.com', 'guerrillamail.com', 'guerrillamail.org',
        'tempmail.com', '10minutemail.com', 'temp-mail.org', 'sharklasers.com',
        'getnada.com', 'maildrop.cc', 'dispostable.com', 'fakeinbox.com',
        'trashmail.com', 'throwawaymail.com',
    ];
    return apply_filters('lfi_nct_disposable_email_domains', $defaults);
}

function lfi_nct_email_is_disposable($email) {
    $email = strtolower(trim((string) $email));
    if ($email === '') return false;
    $at = strrpos($email, '@');
    if ($at === false) return false;
    $domain = substr($email, $at + 1);
    return in_array($domain, lfi_nct_disposable_domains(), true);
}

function lfi_nct_make_unsub_token() {
    return bin2hex(random_bytes(20)); // 40 hex chars
}

/* ------------------------------------------------------------------ */
/* Import CSV                                                          */
/* ------------------------------------------------------------------ */

/**
 * Importe un CSV au format Action Populaire.
 * Renvoie ['ajoutes' => N, 'mis_a_jour' => N, 'ignores' => N, 'erreurs' => [...]].
 */
function lfi_nct_membres_import_csv($csv_path) {
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_membres';
    $stats = ['ajoutes' => 0, 'mis_a_jour' => 0, 'ignores' => 0, 'erreurs' => []];

    if (!is_readable($csv_path)) {
        $stats['erreurs'][] = 'Fichier illisible.';
        return $stats;
    }

    $fh = fopen($csv_path, 'r');
    if (!$fh) { $stats['erreurs'][] = 'Impossible d\'ouvrir le fichier.'; return $stats; }

    $header = fgetcsv($fh);
    if (!$header) { fclose($fh); $stats['erreurs'][] = 'Fichier vide.'; return $stats; }

    // Normalise le header (insensible à la casse, supprime accents bizarres).
    $expected = ['statut','pseudo','nom','prenom','email','telephone','adresse','membre_depuis','abonnement'];
    $map = [];
    foreach ($header as $i => $col) {
        $key = strtolower(trim($col));
        $key = preg_replace('/[éèê]/u', 'e', $key);
        $key = preg_replace('/[àâ]/u',   'a', $key);
        if (strpos($key, 'statut') !== false)          $map[$i] = 'statut';
        elseif (strpos($key, 'pseudo') !== false)      $map[$i] = 'pseudo';
        elseif ($key === 'nom')                        $map[$i] = 'nom';
        elseif (strpos($key, 'prenom') !== false)      $map[$i] = 'prenom';
        elseif (strpos($key, 'mail') !== false)        $map[$i] = 'email';
        elseif (strpos($key, 'tel') !== false)         $map[$i] = 'tel';
        elseif (strpos($key, 'adresse') !== false)     $map[$i] = 'adresse';
        elseif (strpos($key, 'membre depuis') !== false) $map[$i] = 'membre_depuis';
        elseif (strpos($key, 'abonnement') !== false)  $map[$i] = 'abonne_ap';
    }

    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) === 1 && trim($row[0]) === '') continue;
        $data = ['statut'=>'','pseudo'=>'','prenom'=>'','nom'=>'','email'=>'','tel'=>'','adresse'=>'','membre_depuis'=>null,'abonne_ap'=>1];
        foreach ($map as $i => $key) {
            $val = isset($row[$i]) ? trim($row[$i]) : '';
            if ($key === 'abonne_ap') {
                $data[$key] = (strtolower($val) === 'oui' || $val === '1') ? 1 : 0;
            } elseif ($key === 'membre_depuis') {
                if ($val) {
                    $ts = strtotime($val);
                    $data[$key] = $ts ? gmdate('Y-m-d H:i:s', $ts) : null;
                }
            } else {
                $data[$key] = $val;
            }
        }
        $email = strtolower($data['email']);
        if ($email === '' || !is_email($email)) {
            $stats['ignores']++;
            continue;
        }
        $data['email'] = $email;
        $data['jetable'] = lfi_nct_email_is_disposable($email) ? 1 : 0;

        // Upsert par email.
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id, unsubscribe_token, abonne_emails FROM $table WHERE email = %s", $email));
        if ($existing) {
            // On garde l'unsubscribe_token et abonne_emails existants (le membre a pu se désabonner localement).
            $wpdb->update($table, [
                'statut'        => $data['statut'],
                'pseudo'        => $data['pseudo'],
                'prenom'        => $data['prenom'],
                'nom'           => $data['nom'],
                'tel'           => $data['tel'],
                'adresse'       => $data['adresse'],
                'membre_depuis' => $data['membre_depuis'],
                'abonne_ap'     => $data['abonne_ap'],
                'jetable'       => $data['jetable'],
                'source'        => 'action_populaire',
            ], ['id' => $existing->id]);
            $stats['mis_a_jour']++;
        } else {
            $data['unsubscribe_token'] = lfi_nct_make_unsub_token();
            $data['source'] = 'action_populaire';
            $wpdb->insert($table, $data);
            $stats['ajoutes']++;
        }
    }
    fclose($fh);
    return $stats;
}

/* ------------------------------------------------------------------ */
/* Admin                                                               */
/* ------------------------------------------------------------------ */

add_action('admin_menu', 'lfi_nct_membres_admin_menu', 33);
function lfi_nct_membres_admin_menu() {
    add_submenu_page(
        'lfi-nct-responses',
        'Membres du Groupe d\'Action',
        '👥 Membres GA',
        'manage_options',
        'lfi-nct-membres',
        'lfi_nct_membres_admin_page'
    );
}

/* Export CSV sur admin_init pour ne pas polluer le HTML. */
add_action('admin_init', 'lfi_nct_membres_csv_export', 1);
function lfi_nct_membres_csv_export() {
    if (!current_user_can('manage_options')) return;
    if (($_GET['page'] ?? '') !== 'lfi-nct-membres') return;
    if (($_GET['export'] ?? '') !== 'csv') return;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_membres';
    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    while (ob_get_level() > 0) { ob_end_clean(); }
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lfi-membres-' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID','Statut','Pseudo','Prénom','Nom','Email','Tél','Adresse','Membre depuis','Abonné AP','Abonné emails','Email jetable','Source','Notes'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r->id, $r->statut, $r->pseudo, $r->prenom, $r->nom, $r->email, $r->tel, $r->adresse,
            $r->membre_depuis, $r->abonne_ap ? 'Oui' : 'Non', $r->abonne_emails ? 'Oui' : 'Non',
            $r->jetable ? 'Oui' : 'Non', $r->source, $r->notes,
        ], ';');
    }
    fclose($out);
    exit;
}

function lfi_nct_membres_admin_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'lfi_nct_membres';

    $notice = '';

    /* ----- Import CSV ----- */
    if (!empty($_POST['lfi_nct_membres_import']) && check_admin_referer('lfi_nct_membres_import')) {
        if (empty($_FILES['csvfile']['tmp_name']) || $_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
            $notice = 'error|Fichier CSV manquant ou en erreur.';
        } else {
            $res = lfi_nct_membres_import_csv($_FILES['csvfile']['tmp_name']);
            $notice = sprintf(
                'success|Import terminé : %d ajouté(s), %d mis à jour, %d ignoré(s).%s',
                $res['ajoutes'], $res['mis_a_jour'], $res['ignores'],
                $res['erreurs'] ? ' Erreurs : ' . esc_html(implode(' · ', $res['erreurs'])) : ''
            );
        }
    }

    /* ----- Désabonnement local ----- */
    if (!empty($_GET['unsub']) && check_admin_referer('lfi_nct_membres_unsub_' . (int) $_GET['unsub'])) {
        $wpdb->update($table, ['abonne_emails' => 0], ['id' => (int) $_GET['unsub']]);
        wp_safe_redirect(add_query_arg(['notice' => 'unsubed'], remove_query_arg(['unsub', '_wpnonce'])));
        exit;
    }
    /* ----- Réabonnement local ----- */
    if (!empty($_GET['resub']) && check_admin_referer('lfi_nct_membres_resub_' . (int) $_GET['resub'])) {
        $wpdb->update($table, ['abonne_emails' => 1], ['id' => (int) $_GET['resub']]);
        wp_safe_redirect(add_query_arg(['notice' => 'resubed'], remove_query_arg(['resub', '_wpnonce'])));
        exit;
    }
    /* ----- Suppression ----- */
    if (!empty($_GET['del']) && check_admin_referer('lfi_nct_membres_del_' . (int) $_GET['del'])) {
        $wpdb->delete($table, ['id' => (int) $_GET['del']]);
        wp_safe_redirect(add_query_arg(['notice' => 'deleted'], remove_query_arg(['del', '_wpnonce'])));
        exit;
    }

    if (!empty($_GET['notice'])) {
        $msg = ['unsubed' => 'success|Membre désabonné·e des emails groupés.',
                'resubed' => 'success|Membre ré-abonné·e aux emails groupés.',
                'deleted' => 'success|Membre supprimé.'][$_GET['notice']] ?? '';
        if ($msg) $notice = $msg;
    }

    /* ----- Filtres ----- */
    $f_statut = isset($_GET['f_statut']) ? sanitize_text_field($_GET['f_statut']) : '';
    $f_sub    = isset($_GET['f_sub'])    ? sanitize_text_field($_GET['f_sub'])    : '';
    $q        = isset($_GET['q'])        ? sanitize_text_field($_GET['q'])        : '';

    $where = ['1=1'];
    $args = [];
    if ($f_statut !== '') { $where[] = 'statut = %s'; $args[] = $f_statut; }
    if ($f_sub === 'in')  { $where[] = 'abonne_emails = 1 AND jetable = 0'; }
    if ($f_sub === 'out') { $where[] = 'abonne_emails = 0'; }
    if ($f_sub === 'disposable') { $where[] = 'jetable = 1'; }
    if ($q !== '') {
        $where[] = '(email LIKE %s OR prenom LIKE %s OR nom LIKE %s OR pseudo LIKE %s)';
        $like = '%' . $wpdb->esc_like($q) . '%';
        $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like;
    }
    $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT 1000";
    $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, ...$args)) : $wpdb->get_results($sql);

    $total            = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $total_subscribed = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE abonne_emails = 1 AND jetable = 0");
    $total_jetable    = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE jetable = 1");
    $total_unsub      = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE abonne_emails = 0");
    ?>
    <div class="wrap">
        <h1>👥 Membres du Groupe d'Action</h1>

        <?php if ($notice):
            [$lvl, $msg] = array_pad(explode('|', $notice, 2), 2, '');
            $cls = $lvl === 'error' ? 'notice-error' : 'notice-success';
            ?>
            <div class="notice <?php echo esc_attr($cls); ?> is-dismissible"><p><?php echo wp_kses_post($msg); ?></p></div>
        <?php endif; ?>

        <div class="lfi-stats-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:18px 0">
            <a class="lfi-card" href="?page=lfi-nct-membres" style="background:#fff;padding:14px;border-left:4px solid #c8102e;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.08);text-decoration:none;color:inherit">
                <div style="font-size:1.8em;font-weight:700;color:#c8102e"><?php echo $total; ?></div>
                <div>Membres au total</div>
            </a>
            <a class="lfi-card" href="?page=lfi-nct-membres&f_sub=in" style="background:#fff;padding:14px;border-left:4px solid #1a7f37;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.08);text-decoration:none;color:inherit">
                <div style="font-size:1.8em;font-weight:700;color:#1a7f37"><?php echo $total_subscribed; ?></div>
                <div>Joignables par email</div>
            </a>
            <a class="lfi-card" href="?page=lfi-nct-membres&f_sub=disposable" style="background:#fff;padding:14px;border-left:4px solid #bd8600;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.08);text-decoration:none;color:inherit">
                <div style="font-size:1.8em;font-weight:700;color:#bd8600"><?php echo $total_jetable; ?></div>
                <div>Emails jetables</div>
            </a>
            <a class="lfi-card" href="?page=lfi-nct-membres&f_sub=out" style="background:#fff;padding:14px;border-left:4px solid #666;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.08);text-decoration:none;color:inherit">
                <div style="font-size:1.8em;font-weight:700;color:#666"><?php echo $total_unsub; ?></div>
                <div>Désabonné·es</div>
            </a>
        </div>

        <details style="background:#fff;padding:14px 18px;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin:16px 0" <?php echo $total === 0 ? 'open' : ''; ?>>
            <summary style="font-size:1.1em;font-weight:600;cursor:pointer">📥 Importer un CSV Action Populaire</summary>
            <p class="description" style="margin-top:.8em">
                Sur Action Populaire (app ou web) → page de ton groupe → bouton « Télécharger la liste des membres et contacts au format CSV ».
                Le format attendu : <code>Statut, Pseudo, Nom, Prénom, E-mail, Téléphone, Adresse, Membre depuis le, Abonnement…</code>
            </p>
            <p class="description">
                Les emails déjà présents seront <strong>mis à jour</strong>, les nouveaux <strong>ajoutés</strong>.
                Les emails jetables (@yopmail, @mailinator, etc.) sont détectés automatiquement et exclus des envois en masse.
            </p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('lfi_nct_membres_import'); ?>
                <input type="file" name="csvfile" accept=".csv,text/csv" required>
                <button type="submit" name="lfi_nct_membres_import" value="1" class="button button-primary">Importer</button>
            </form>
        </details>

        <form method="get" style="margin:1em 0;display:flex;gap:8px;align-items:end;flex-wrap:wrap">
            <input type="hidden" name="page" value="lfi-nct-membres">
            <label>Recherche
                <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="email, nom, pseudo…">
            </label>
            <label>Statut
                <select name="f_statut">
                    <option value="">— tous —</option>
                    <?php
                    $statuts = $wpdb->get_col("SELECT DISTINCT statut FROM $table WHERE statut <> '' ORDER BY statut");
                    foreach ($statuts as $s) {
                        printf('<option value="%s" %s>%s</option>',
                            esc_attr($s),
                            selected($f_statut, $s, false),
                            esc_html($s));
                    }
                    ?>
                </select>
            </label>
            <label>Filtre
                <select name="f_sub">
                    <option value="">— tous —</option>
                    <option value="in"         <?php selected($f_sub, 'in'); ?>>Joignables par email</option>
                    <option value="out"        <?php selected($f_sub, 'out'); ?>>Désabonné·es</option>
                    <option value="disposable" <?php selected($f_sub, 'disposable'); ?>>Emails jetables</option>
                </select>
            </label>
            <button class="button">Filtrer</button>
            <a href="?page=lfi-nct-membres&export=csv" class="button">📥 Exporter CSV</a>
        </form>

        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>#</th><th>Statut</th><th>Pseudo</th><th>Prénom</th><th>Nom</th>
                    <th>Email</th><th>Tél</th><th>Adresse</th><th>Membre depuis</th>
                    <th>Emails</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="11"><em>Aucun membre. Importe un CSV ci-dessus.</em></td></tr>
            <?php else: foreach ($rows as $r):
                $unsub_url = wp_nonce_url(add_query_arg(['unsub' => $r->id], remove_query_arg(['notice','_wpnonce'])), 'lfi_nct_membres_unsub_' . $r->id);
                $resub_url = wp_nonce_url(add_query_arg(['resub' => $r->id], remove_query_arg(['notice','_wpnonce'])), 'lfi_nct_membres_resub_' . $r->id);
                $del_url   = wp_nonce_url(add_query_arg(['del'   => $r->id], remove_query_arg(['notice','_wpnonce'])), 'lfi_nct_membres_del_'   . $r->id);
            ?>
                <tr<?php echo $r->jetable ? ' style="opacity:.55;background:#fff8e1"' : ''; ?>>
                    <td>#<?php echo (int) $r->id; ?></td>
                    <td><?php echo esc_html($r->statut); ?></td>
                    <td><?php echo esc_html($r->pseudo); ?></td>
                    <td><?php echo esc_html($r->prenom); ?></td>
                    <td><?php echo esc_html($r->nom); ?></td>
                    <td>
                        <a href="mailto:<?php echo esc_attr($r->email); ?>"><?php echo esc_html($r->email); ?></a>
                        <?php if ($r->jetable): ?><br><span class="dashicons dashicons-warning" style="color:#bd8600"></span> jetable<?php endif; ?>
                    </td>
                    <td><?php echo $r->tel ? '<a href="tel:' . esc_attr($r->tel) . '">' . esc_html($r->tel) . '</a>' : ''; ?></td>
                    <td><?php echo esc_html($r->adresse); ?></td>
                    <td><?php echo $r->membre_depuis ? esc_html(date_i18n('d/m/Y', strtotime($r->membre_depuis))) : ''; ?></td>
                    <td>
                        <?php if ($r->abonne_emails): ?>
                            <span style="color:#1a7f37">✓ abonné·e</span>
                        <?php else: ?>
                            <span style="color:#c8102e">✗ désab.</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r->abonne_emails): ?>
                            <a href="<?php echo esc_url($unsub_url); ?>" class="button button-small" title="Désabonner localement"
                               onclick="return confirm('Désabonner <?php echo esc_js($r->email); ?> des envois groupés ?')">🚫</a>
                        <?php else: ?>
                            <a href="<?php echo esc_url($resub_url); ?>" class="button button-small" title="Ré-abonner">✓</a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($del_url); ?>" class="button button-small" title="Supprimer définitivement"
                           onclick="return confirm('Supprimer définitivement ce membre de la base WP ? (n'affecte pas Action Populaire)')">🗑</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <p class="description" style="margin-top:1em">
            🔒 <strong>RGPD</strong> : ces données restent strictement internes au Groupe d'Action.
            Les emails sont stockés en clair pour permettre les envois groupés depuis WP.
            Aucun export tiers, aucune transmission automatique.
        </p>
    </div>
    <?php
}
