<?php
/**
 * Module SMS — gestionnaire de modèles + envoi un-par-un via l'app SMS du téléphone.
 *
 * Workflow (= ce que fait l'utilisateur)
 *   1. Crée des modèles SMS catégorisés (Événement, Invitation, Accueil…).
 *   2. Sur la page « Envoi », choisit un modèle + filtre les membres.
 *   3. Voit la liste des SMS personnalisés prêts (variables {{prenom}} etc. remplacées).
 *   4. Pour chaque ligne : clique le lien « sms: » (sur mobile → ouvre l'app SMS pré-remplie)
 *      ou scanne le QR code (sur PC → idem mais après scan téléphone).
 *   5. Valide « Marquer comme envoyé » → log la trace.
 *
 * Aucun envoi automatique. Les SMS partent depuis le forfait du téléphone de
 * l'animateur·ice (compatible avec l'usage déjà en place sur le site Alliance).
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_SMS_DBVER = 'lfi_nct_sms_db_ver';

/* ------------------------------------------------------------------ */
/* DB                                                                  */
/* ------------------------------------------------------------------ */

add_action('init', 'lfi_nct_sms_db_setup', 5);
function lfi_nct_sms_db_setup() {
    if (get_option(LFI_NCT_SMS_DBVER) === '4') return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $tpl_table = $wpdb->prefix . 'lfi_nct_sms_templates';
    dbDelta("CREATE TABLE $tpl_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        nom VARCHAR(120) DEFAULT '',
        categorie VARCHAR(40) DEFAULT 'autre',
        body TEXT,
        ajouter_stop TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY categorie (categorie)
    ) $charset;");

    $log_table = $wpdb->prefix . 'lfi_nct_sms_log';
    dbDelta("CREATE TABLE $log_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        template_id BIGINT(20) UNSIGNED DEFAULT NULL,
        membre_id BIGINT(20) UNSIGNED DEFAULT NULL,
        tel VARCHAR(40) DEFAULT '',
        body_sent TEXT,
        sent_by BIGINT(20) UNSIGNED DEFAULT NULL,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY template_id (template_id),
        KEY membre_id (membre_id),
        KEY sent_at (sent_at)
    ) $charset;");

    // Modèles par défaut au premier setup (DBVER vide)
    $first_install = get_option(LFI_NCT_SMS_DBVER) === false;
    if ($first_install) {
        $defaults = [
            ['Convocation réunion', 'reunion',     "Salut {{prenom}} ! On se retrouve {{event_jour}} {{event_date}} à {{event_heure}} pour {{event_titre}} ({{event_lieu}}). Infos & inscription : {{event_url_short}}"],
            ['Invitation événement', 'evenement',  "{{prenom}}, nouveau rdv du GA Clos Toreau : {{event_titre}} le {{event_date}} à {{event_lieu}}. Tu viens ? {{event_url_short}}"],
            ['Accueil nouveau membre','accueil',   "Bienvenue {{prenom}} dans le Groupe d'Action LFI Nantes Sud Clos Toreau ! Notre prochain rendez-vous : {{event_titre}}, {{event_jour}} {{event_date}}. {{event_url_short}}"],
            ['Relance porte-à-porte', 'mobilisation',"{{prenom}}, on relance un porte-à-porte logement au Clos Toreau {{event_jour}}. RDV {{event_heure}} {{event_lieu}}. Infos : {{event_url_short}}"],
            ['Rappel cotisation',    'autre',       "Salut {{prenom}}, petit rappel : pense à ta cotisation annuelle au GA. Plus d'infos auprès des animateur·ices."],
            ['Info importante',      'autre',       "{{prenom}}, info GA Clos Toreau : [à compléter]. Plus d'infos sur lfi-nantes-clostoreau.fr"],
        ];
        foreach ($defaults as $d) {
            $wpdb->insert($tpl_table, [
                'nom' => $d[0], 'categorie' => $d[1], 'body' => $d[2], 'ajouter_stop' => 0,
            ]);
        }
    }

    // Upgrade vers DBVER 3 : ajoute 2 modèles dédiés au prochain événement, sans toucher aux existants
    if (get_option(LFI_NCT_SMS_DBVER) === '2') {
        $new_for_v3 = [
            ['📅 Prochain événement (court)',  'evenement',
                "{{prenom}}, prochain RDV du GA : {{event_titre}} — {{event_jour}} {{event_date}} {{event_heure}} à {{event_lieu}}. Inscris-toi : {{event_url_short}}"],
            ['📅 Convocation détaillée',      'reunion',
                "Camarade {{prenom}}, on compte sur toi pour {{event_titre}}.\nQuand : {{event_jour}} {{event_date}} à {{event_heure}}\nOù : {{event_lieu}}\nProgramme & inscription : {{event_url_short}}"],
        ];
        foreach ($new_for_v3 as $d) {
            $wpdb->insert($tpl_table, [
                'nom' => $d[0], 'categorie' => $d[1], 'body' => $d[2], 'ajouter_stop' => 0,
            ]);
        }
    }

    // Upgrade vers DBVER 4 : retire la mention STOP sur TOUS les modèles existants.
    // Ce sont des camarades inscrit·es au groupe, pas du marketing ; la mention
    // « STOP au 36180 » n'a pas de sens pour ces destinataires.
    if (in_array(get_option(LFI_NCT_SMS_DBVER), ['1','2','3'], true)) {
        $wpdb->query("UPDATE $tpl_table SET ajouter_stop = 0");
    }

    update_option(LFI_NCT_SMS_DBVER, '4', false);
}

function lfi_nct_sms_categories() {
    return [
        'reunion'      => '🗣 Convocation réunion',
        'evenement'    => '📅 Invitation événement',
        'accueil'      => '👋 Accueil nouveau membre',
        'mobilisation' => '✊ Mobilisation / porte-à-porte',
        'urgence'      => '🚨 Urgence',
        'autre'        => '✏️ Autre',
    ];
}

/**
 * Remplace les variables {{prenom}}, {{nom}}, etc. dans un modèle pour un membre donné.
 */
function lfi_nct_sms_render($body, $membre, $extra = []) {
    $vars = array_merge([
        'prenom'  => $membre->prenom ?: $membre->pseudo,
        'nom'     => $membre->nom,
        'pseudo'  => $membre->pseudo,
        'statut'  => $membre->statut,
    ], $extra);
    $out = $body;
    foreach ($vars as $k => $v) {
        $out = str_replace('{{' . $k . '}}', (string) $v, $out);
    }
    return $out;
}

/**
 * Liste les événements à venir — délègue au module events.php.
 */
function lfi_nct_sms_upcoming_events($limit = 10) {
    if (!function_exists('lfi_nct_upcoming_events')) return [];
    return lfi_nct_upcoming_events($limit);
}

/**
 * Variables {{event_*}} pour un post événement (CPT du thème ou fallback).
 */
function lfi_nct_sms_event_vars($event) {
    if (!$event || !function_exists('lfi_nct_event_data')) {
        return array_fill_keys([
            'event_titre','event_url','event_url_short','event_date',
            'event_jour','event_heure','event_lieu','event_adresse',
            'event_date_complete',
        ], '');
    }
    $d = lfi_nct_event_data($event);
    return [
        'event_titre'         => $d['titre'],
        'event_url'           => $d['url'],
        'event_url_short'     => $d['short_url'],
        'event_date'          => $d['date_fr'],
        'event_jour'          => $d['jour'],
        'event_heure'         => $d['heure_debut'],
        'event_lieu'          => $d['lieu'],
        'event_adresse'       => $d['adresse'],
        'event_date_complete' => $d['date_complete'],
    ];
}

/* ------------------------------------------------------------------ */
/* Admin                                                               */
/* ------------------------------------------------------------------ */

add_action('admin_menu', 'lfi_nct_sms_admin_menu', 37);
function lfi_nct_sms_admin_menu() {
    add_submenu_page(
        'lfi-nct-responses',
        'Envoi SMS aux membres',
        '📱 SMS',
        'manage_options',
        'lfi-nct-sms',
        'lfi_nct_sms_router'
    );
}

function lfi_nct_sms_router() {
    if (!current_user_can('manage_options')) return;
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'envoyer';
    ?>
    <div class="wrap">
        <h1>📱 SMS aux membres du GA</h1>
        <nav class="nav-tab-wrapper">
            <a class="nav-tab <?php echo $tab === 'envoyer' ? 'nav-tab-active' : ''; ?>" href="?page=lfi-nct-sms&tab=envoyer">✉️ Envoyer</a>
            <a class="nav-tab <?php echo $tab === 'modeles' ? 'nav-tab-active' : ''; ?>" href="?page=lfi-nct-sms&tab=modeles">📝 Modèles</a>
            <a class="nav-tab <?php echo $tab === 'historique' ? 'nav-tab-active' : ''; ?>" href="?page=lfi-nct-sms&tab=historique">📜 Historique</a>
        </nav>
        <?php
        if ($tab === 'modeles')    lfi_nct_sms_page_modeles();
        elseif ($tab === 'historique') lfi_nct_sms_page_historique();
        else                       lfi_nct_sms_page_envoi();
        ?>
    </div>
    <?php
}

/* ----- Onglet 1 : envoi ----- */

function lfi_nct_sms_page_envoi() {
    global $wpdb;
    $tpl_table   = $wpdb->prefix . 'lfi_nct_sms_templates';
    $log_table   = $wpdb->prefix . 'lfi_nct_sms_log';
    $mem_table   = $wpdb->prefix . 'lfi_nct_membres';

    /* ----- Log d'un envoi (clic « Marquer envoyé ») ----- */
    if (!empty($_POST['lfi_sms_log']) && check_admin_referer('lfi_sms_log')) {
        $tpl_id     = (int) ($_POST['tpl_id']     ?? 0);
        $membre_id  = (int) ($_POST['membre_id']  ?? 0);
        $tel        = sanitize_text_field(wp_unslash($_POST['tel'] ?? ''));
        $body       = sanitize_textarea_field(wp_unslash($_POST['body'] ?? ''));
        $wpdb->insert($log_table, [
            'template_id' => $tpl_id ?: null,
            'membre_id'   => $membre_id ?: null,
            'tel'         => $tel,
            'body_sent'   => $body,
            'sent_by'     => get_current_user_id(),
        ]);
        wp_safe_redirect(add_query_arg(['logged' => 1, 'tpl' => $tpl_id, 'f_statut' => $_POST['f_statut'] ?? '', 'q' => $_POST['q'] ?? ''], admin_url('admin.php?page=lfi-nct-sms&tab=envoyer')));
        exit;
    }

    $templates = $wpdb->get_results("SELECT * FROM $tpl_table ORDER BY categorie, nom");
    $tpl_id    = (int) ($_GET['tpl'] ?? 0);
    $tpl       = $tpl_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $tpl_table WHERE id=%d", $tpl_id)) : null;

    $f_statut = isset($_GET['f_statut']) ? sanitize_text_field($_GET['f_statut']) : '';
    $q        = isset($_GET['q'])        ? sanitize_text_field($_GET['q'])        : '';

    // Sélecteur d'événement : défaut = prochain à venir
    $upcoming   = lfi_nct_sms_upcoming_events(20);
    $event_id   = isset($_GET['event']) ? (int) $_GET['event'] : ($upcoming ? $upcoming[0]->ID : 0);
    $event_post = $event_id ? get_post($event_id) : null;
    $event_vars = lfi_nct_sms_event_vars($event_post);

    if (!empty($_GET['logged'])) {
        echo '<div class="notice notice-success is-dismissible"><p>✅ SMS noté comme envoyé.</p></div>';
    }
    ?>
    <h2 style="margin-top:1em">1. Choisir un modèle &amp; un événement à lier</h2>
    <form method="get" id="lfi-sms-form" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;background:#fff;padding:14px 18px;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.08)">
        <input type="hidden" name="page" value="lfi-nct-sms">
        <input type="hidden" name="tab"  value="envoyer">
        <label>Modèle
            <select name="tpl" required onchange="this.form.submit()">
                <option value="">— choisir —</option>
                <?php
                $cats = lfi_nct_sms_categories();
                $current_cat = '';
                foreach ($templates as $t) {
                    if ($t->categorie !== $current_cat) {
                        if ($current_cat !== '') echo '</optgroup>';
                        $current_cat = $t->categorie;
                        printf('<optgroup label="%s">', esc_attr($cats[$current_cat] ?? $current_cat));
                    }
                    printf('<option value="%d" %s>%s</option>',
                        $t->id, selected($tpl_id, $t->id, false), esc_html($t->nom));
                }
                if ($current_cat !== '') echo '</optgroup>';
                ?>
            </select>
        </label>
        <label>Événement lié
            <select name="event" onchange="this.form.submit()">
                <option value="0">— aucun —</option>
                <?php foreach ($upcoming as $e):
                    $dd = get_post_meta($e->ID, '_lfi_evt_date_debut', true);
                    $label = get_the_title($e) . ($dd ? ' — ' . date_i18n('d/m H\hi', strtotime($dd)) : '');
                    ?>
                    <option value="<?php echo (int) $e->ID; ?>" <?php selected($event_id, $e->ID); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Recherche membre
            <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="prénom, nom, pseudo">
        </label>
        <label>Statut
            <select name="f_statut">
                <option value="">— tous —</option>
                <?php
                $statuts = $wpdb->get_col("SELECT DISTINCT statut FROM $mem_table WHERE statut <> '' ORDER BY statut");
                foreach ($statuts as $s) {
                    printf('<option value="%s" %s>%s</option>',
                        esc_attr($s), selected($f_statut, $s, false), esc_html($s));
                }
                ?>
            </select>
        </label>
        <button class="button button-primary">Filtrer</button>
        <a href="?page=lfi-nct-sms&tab=envoyer" class="button">Reset</a>
    </form>

    <?php if (!$tpl): ?>
        <p class="description" style="margin-top:1.5em">Choisis un modèle pour voir la liste des SMS prêts à envoyer.</p>
        <?php return;
    endif; ?>

    <h2 style="margin-top:1.5em">2. Aperçu du modèle « <?php echo esc_html($tpl->nom); ?> »</h2>
    <?php
    $preview_membre = (object) ['prenom' => 'Prénom', 'nom' => 'NOM', 'pseudo' => 'pseudo', 'statut' => 'Membre actif'];
    $preview_body   = lfi_nct_sms_render($tpl->body, $preview_membre, $event_vars);
    if ($tpl->ajouter_stop) $preview_body .= "\n— STOP au 36180 pour ne plus recevoir";
    ?>
    <div style="background:#f8f8f8;border-left:4px solid #c8102e;padding:12px 16px;border-radius:4px;font-family:monospace;white-space:pre-wrap;max-width:600px"><?php echo esc_html($preview_body); ?></div>
    <p class="description" style="margin-top:.4em"><?php echo strlen($preview_body); ?> caractères<?php if ($event_post): ?> · événement lié : <strong><?php echo esc_html(get_the_title($event_post)); ?></strong><?php endif; ?></p>

    <h2 style="margin-top:1.5em">3. Destinataires (n'affiche QUE les membres ayant un téléphone)</h2>
    <?php
    $where = ["tel <> ''", "abonne_emails = 1", "jetable = 0"];
    $args = [];
    if ($f_statut !== '') { $where[] = "statut = %s"; $args[] = $f_statut; }
    if ($q !== '') {
        $where[] = "(prenom LIKE %s OR nom LIKE %s OR pseudo LIKE %s)";
        $like = '%' . $wpdb->esc_like($q) . '%';
        $args[] = $like; $args[] = $like; $args[] = $like;
    }
    $sql = "SELECT * FROM $mem_table WHERE " . implode(' AND ', $where) . " ORDER BY prenom, nom";
    $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, ...$args)) : $wpdb->get_results($sql);
    ?>

    <p><strong><?php echo count($rows); ?></strong> destinataire(s).
       <?php if (!count($rows)): ?>Aucun membre ne correspond à ce filtre OU n'a de téléphone.<?php endif; ?>
    </p>

    <?php if ($rows): ?>
    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th style="width:12%">Membre</th>
                <th style="width:12%">Téléphone</th>
                <th>Message personnalisé</th>
                <th style="width:18%">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $m):
            $body = lfi_nct_sms_render($tpl->body, $m, $event_vars);
            if ($tpl->ajouter_stop) $body .= "\n— STOP au 36180 pour ne plus recevoir";
            $sms_url = 'sms:' . preg_replace('/[^\d+]/', '', $m->tel) . '?body=' . rawurlencode($body);
            $body_for_qr = $sms_url; // Le QR encode l'URL sms: complète
            $alreadyArr = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $log_table WHERE membre_id=%d AND template_id=%d",
                $m->id, $tpl->id
            ));
        ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($m->prenom ?: $m->pseudo); ?></strong>
                    <?php if ($m->nom): ?><br><span style="color:#666"><?php echo esc_html($m->nom); ?></span><?php endif; ?>
                </td>
                <td><a href="tel:<?php echo esc_attr($m->tel); ?>"><?php echo esc_html($m->tel); ?></a></td>
                <td><div style="font-family:monospace;font-size:.9em;white-space:pre-wrap;max-height:6em;overflow:auto"><?php echo esc_html($body); ?></div>
                    <p class="description" style="margin:.3em 0 0"><?php echo strlen($body); ?> caractères</p>
                </td>
                <td>
                    <?php if ($alreadyArr): ?>
                        <span style="color:#1a7f37;font-size:.9em">✅ déjà envoyé <?php echo (int) $alreadyArr; ?>×</span><br>
                    <?php endif; ?>
                    <a href="<?php echo esc_attr($sms_url); ?>" class="button button-primary" target="_blank">📲 Ouvrir SMS</a>
                    <button type="button" class="button button-small" onclick="this.nextElementSibling.style.display='block'">📷 QR</button>
                    <div style="display:none;margin-top:6px">
                        <img alt="QR" loading="lazy"
                             src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=<?php echo esc_attr(rawurlencode($body_for_qr)); ?>"
                             style="border:1px solid #ddd;padding:4px;background:#fff">
                    </div>
                    <form method="post" style="margin-top:6px">
                        <?php wp_nonce_field('lfi_sms_log'); ?>
                        <input type="hidden" name="tpl_id"   value="<?php echo (int) $tpl->id; ?>">
                        <input type="hidden" name="membre_id" value="<?php echo (int) $m->id; ?>">
                        <input type="hidden" name="tel"       value="<?php echo esc_attr($m->tel); ?>">
                        <input type="hidden" name="body"      value="<?php echo esc_attr($body); ?>">
                        <input type="hidden" name="f_statut"  value="<?php echo esc_attr($f_statut); ?>">
                        <input type="hidden" name="q"         value="<?php echo esc_attr($q); ?>">
                        <button name="lfi_sms_log" value="1" class="button button-small">Marquer envoyé</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="description" style="margin-top:1em">
        💡 <strong>Astuce</strong> : ouvre cette page sur ton téléphone (même réseau que ton portable Free) — le bouton
        « 📲 Ouvrir SMS » lance directement l'app SMS de ton téléphone avec le numéro et le message déjà tapés. Sur PC,
        scanne le QR code de la ligne avec ton portable, ça revient au même.
    </p>
    <?php endif; ?>
    <?php
}

/* ----- Onglet 2 : modèles ----- */

function lfi_nct_sms_page_modeles() {
    global $wpdb;
    $tpl_table = $wpdb->prefix . 'lfi_nct_sms_templates';
    $cats = lfi_nct_sms_categories();

    if (!empty($_POST['lfi_sms_tpl_save']) && check_admin_referer('lfi_sms_tpl_save')) {
        $id  = (int) ($_POST['id'] ?? 0);
        $data = [
            'nom'          => sanitize_text_field(wp_unslash($_POST['nom']       ?? '')),
            'categorie'    => sanitize_text_field(wp_unslash($_POST['categorie'] ?? 'autre')),
            'body'         => sanitize_textarea_field(wp_unslash($_POST['body']  ?? '')),
            'ajouter_stop' => !empty($_POST['ajouter_stop']) ? 1 : 0,
        ];
        if (!isset($cats[$data['categorie']])) $data['categorie'] = 'autre';
        if ($id > 0) {
            $wpdb->update($tpl_table, $data, ['id' => $id]);
        } else {
            $wpdb->insert($tpl_table, $data);
        }
        wp_safe_redirect(admin_url('admin.php?page=lfi-nct-sms&tab=modeles&saved=1'));
        exit;
    }
    if (!empty($_GET['del']) && check_admin_referer('lfi_sms_tpl_del_' . (int) $_GET['del'])) {
        $wpdb->delete($tpl_table, ['id' => (int) $_GET['del']]);
        wp_safe_redirect(admin_url('admin.php?page=lfi-nct-sms&tab=modeles&deleted=1'));
        exit;
    }

    if (!empty($_GET['saved']))   echo '<div class="notice notice-success is-dismissible"><p>Modèle enregistré.</p></div>';
    if (!empty($_GET['deleted'])) echo '<div class="notice notice-success is-dismissible"><p>Modèle supprimé.</p></div>';

    $edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
    $edit    = $edit_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $tpl_table WHERE id=%d", $edit_id)) : null;
    ?>
    <h2 style="margin-top:1em"><?php echo $edit ? '✏️ Modifier le modèle' : '➕ Nouveau modèle'; ?></h2>
    <form method="post" style="background:#fff;padding:18px;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.08);max-width:760px">
        <?php wp_nonce_field('lfi_sms_tpl_save'); ?>
        <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int) $edit->id; ?>"><?php endif; ?>
        <table class="form-table">
            <tr>
                <th><label for="nom">Nom du modèle</label></th>
                <td><input id="nom" type="text" name="nom" value="<?php echo $edit ? esc_attr($edit->nom) : ''; ?>" required class="regular-text" placeholder="Ex : Convocation réunion mensuelle"></td>
            </tr>
            <tr>
                <th><label for="categorie">Catégorie</label></th>
                <td>
                    <select id="categorie" name="categorie">
                        <?php foreach ($cats as $k => $label): ?>
                            <option value="<?php echo esc_attr($k); ?>" <?php selected($edit ? $edit->categorie : 'autre', $k); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="body">Contenu du SMS</label></th>
                <td>
                    <textarea id="body" name="body" rows="5" class="large-text" required placeholder="Salut {{prenom}}, …"><?php echo $edit ? esc_textarea($edit->body) : ''; ?></textarea>
                    <p class="description">
                        <strong>Variables membre :</strong>
                        <code>{{prenom}}</code> · <code>{{nom}}</code> · <code>{{pseudo}}</code> · <code>{{statut}}</code><br>
                        <strong>Variables événement</strong> (résolues automatiquement avec le prochain événement à venir, ou celui choisi sur la page Envoi) :<br>
                        <code>{{event_titre}}</code> · <code>{{event_jour}}</code> · <code>{{event_date}}</code> · <code>{{event_heure}}</code> ·
                        <code>{{event_lieu}}</code> · <code>{{event_adresse}}</code> · <code>{{event_url_short}}</code> · <code>{{event_date_complete}}</code><br>
                        Conseil : un SMS classique fait <strong>160 caractères</strong>. Au-delà, il sera coupé en plusieurs SMS (compte comme plusieurs SMS chez l'opérateur).
                    </p>
                </td>
            </tr>
            <tr>
                <th>Mention STOP</th>
                <td><label><input type="checkbox" name="ajouter_stop" value="1" <?php checked($edit ? $edit->ajouter_stop : 0); ?>>
                    Ajouter « STOP au 36180 » à la fin</label>
                <p class="description">Pas nécessaire pour les camarades inscrit·es au GA, c'est utile uniquement pour les listes de prospects froides (style SMS de campagne électorale grand public).</p></td>
            </tr>
        </table>
        <p>
            <button type="submit" name="lfi_sms_tpl_save" value="1" class="button button-primary"><?php echo $edit ? 'Mettre à jour' : 'Créer le modèle'; ?></button>
            <?php if ($edit): ?>
                <a href="?page=lfi-nct-sms&tab=modeles" class="button">Annuler</a>
            <?php endif; ?>
        </p>
    </form>

    <h2 style="margin-top:2em">Modèles existants</h2>
    <?php
    $all = $wpdb->get_results("SELECT * FROM $tpl_table ORDER BY categorie, nom");
    ?>
    <table class="wp-list-table widefat striped">
        <thead><tr><th>Nom</th><th>Catégorie</th><th>Aperçu</th><th>STOP</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($all)): ?>
            <tr><td colspan="5"><em>Aucun modèle. Crée-en un avec le formulaire ci-dessus.</em></td></tr>
        <?php else: foreach ($all as $t):
            $del_url = wp_nonce_url(add_query_arg(['del' => $t->id]), 'lfi_sms_tpl_del_' . $t->id);
        ?>
            <tr>
                <td><strong><?php echo esc_html($t->nom); ?></strong></td>
                <td><?php echo esc_html($cats[$t->categorie] ?? $t->categorie); ?></td>
                <td><div style="font-family:monospace;font-size:.85em;max-width:380px;white-space:pre-wrap"><?php echo esc_html(mb_substr($t->body, 0, 120)); ?><?php echo mb_strlen($t->body) > 120 ? '…' : ''; ?></div></td>
                <td><?php echo $t->ajouter_stop ? '✅' : '—'; ?></td>
                <td>
                    <a href="?page=lfi-nct-sms&tab=modeles&edit=<?php echo (int) $t->id; ?>" class="button button-small">✏️ Modifier</a>
                    <a href="<?php echo esc_url($del_url); ?>" class="button button-small"
                       onclick="return confirm('Supprimer le modèle « <?php echo esc_js($t->nom); ?> » ? Les SMS déjà envoyés gardent leur trace.')">🗑</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?php
}

/* ----- Onglet 3 : historique ----- */

function lfi_nct_sms_page_historique() {
    global $wpdb;
    $log_table = $wpdb->prefix . 'lfi_nct_sms_log';
    $tpl_table = $wpdb->prefix . 'lfi_nct_sms_templates';
    $mem_table = $wpdb->prefix . 'lfi_nct_membres';
    $rows = $wpdb->get_results("
        SELECT l.*, t.nom AS tpl_nom, m.prenom AS mem_prenom, m.nom AS mem_nom, m.pseudo AS mem_pseudo
        FROM $log_table l
        LEFT JOIN $tpl_table t ON t.id = l.template_id
        LEFT JOIN $mem_table m ON m.id = l.membre_id
        ORDER BY l.sent_at DESC LIMIT 500
    ");
    ?>
    <h2 style="margin-top:1em">Historique des SMS marqués comme envoyés</h2>
    <p class="description">Les <strong>500 derniers</strong> envois. Le plugin ne sait pas si tu as réellement appuyé sur « Envoyer » dans ton app SMS, il sait juste que tu as cliqué sur « Marquer envoyé » dans cette interface.</p>
    <table class="wp-list-table widefat striped">
        <thead><tr><th>Date</th><th>Modèle</th><th>Destinataire</th><th>Téléphone</th><th>SMS envoyé</th></tr></thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="5"><em>Aucun envoi enregistré.</em></td></tr>
        <?php else: foreach ($rows as $l): ?>
            <tr>
                <td><?php echo esc_html($l->sent_at); ?></td>
                <td><?php echo esc_html($l->tpl_nom ?: '—'); ?></td>
                <td><?php echo esc_html(trim(($l->mem_prenom ?: '') . ' ' . ($l->mem_nom ?: '')) ?: ($l->mem_pseudo ?: '—')); ?></td>
                <td><?php echo esc_html($l->tel); ?></td>
                <td><div style="font-family:monospace;font-size:.85em;max-width:380px;white-space:pre-wrap;max-height:5em;overflow:auto"><?php echo esc_html($l->body_sent); ?></div></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?php
}
