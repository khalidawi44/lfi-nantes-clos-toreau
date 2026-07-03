<?php
/**
 * ALERTES « À FAIRE » sur l'écran d'accueil.
 *
 * Bandeau en haut de la console qui rassemble les choses importantes à ne pas
 * mettre de côté, avec un lien qui envoie DIRECTEMENT au bon endroit :
 *
 *  - Alertes MANUELLES (content-as-code) : quand l'utilisateur signale « j'ai
 *    reçu un email de NMH », on ajoute une entrée dans content/alertes.php avec
 *    un lien vers la suite logique (enregistrer la réponse, écrire au SCHS…).
 *  - Alertes AUTOMATIQUES : déduites de l'état des dossiers (réponse reçue à
 *    analyser, courrier sans réponse à relancer). Elles disparaissent toutes
 *    seules une fois l'action faite.
 */
if (!defined('ABSPATH')) exit;

/** Alertes saisies à la main (content/alertes.php). */
function lfi_nct_alertes_manual() {
    $list = function_exists('lfi_nct_content_load') ? lfi_nct_content_load('alertes.php') : [];
    $out = [];
    foreach ((array) $list as $a) {
        if (!is_array($a) || empty($a['titre'])) continue;
        if (!empty($a['url'])) {
            $url = $a['url'];
        } elseif (!empty($a['route'])) {
            $url = lfi_nct_app_url($a['route'], (array) ($a['args'] ?? []));
            if (!empty($a['anchor'])) $url .= '#' . $a['anchor'];
        } else {
            $url = lfi_nct_app_url();
        }
        $out[] = [
            'prio'   => $a['priorite'] ?? 'haute',
            'titre'  => (string) $a['titre'],
            'detail' => (string) ($a['detail'] ?? ''),
            'url'    => $url,
            'manual' => true,
        ];
    }
    return $out;
}

/** Dossiers dont l'alerte « à faire » a été écartée à la main (par gestionnaire). */
function lfi_nct_alertes_dismissed() {
    $owner = function_exists('lfi_nct_dossier_owner_id') ? (int) lfi_nct_dossier_owner_id() : 0;
    $all = get_option('lfi_nct_alertes_dismissed', []);
    $d = (is_array($all) && !empty($all[$owner])) ? $all[$owner] : [];
    return array_map('intval', (array) $d);
}
function lfi_nct_alertes_dismiss($dossier_id) {
    $owner = function_exists('lfi_nct_dossier_owner_id') ? (int) lfi_nct_dossier_owner_id() : 0;
    $all = get_option('lfi_nct_alertes_dismissed', []);
    if (!is_array($all)) $all = [];
    $cur = isset($all[$owner]) && is_array($all[$owner]) ? $all[$owner] : [];
    $cur[] = (int) $dossier_id;
    $all[$owner] = array_values(array_unique(array_map('intval', $cur)));
    update_option('lfi_nct_alertes_dismissed', $all, false);
}
/** Handler : écarter une alerte de dossier. */
add_action('admin_post_lfi_nct_alert_dismiss', 'lfi_nct_alert_dismiss_handler');
function lfi_nct_alert_dismiss_handler() {
    if (!is_user_logged_in()) wp_die('non');
    $did = isset($_GET['did']) ? (int) $_GET['did'] : 0;
    if ($did && check_admin_referer('lfi_nct_alert_dismiss_' . $did)) lfi_nct_alertes_dismiss($did);
    wp_safe_redirect(function_exists('lfi_nct_app_url') ? lfi_nct_app_url() : home_url('/app/'));
    exit;
}

/** Alertes déduites automatiquement de l'état des dossiers. */
function lfi_nct_alertes_auto() {
    global $wpdb;
    if (!function_exists('lfi_nct_dossier_owner_id')) return [];
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $owner = (int) lfi_nct_dossier_owner_id();
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE owner_user_id = %d ORDER BY updated_at DESC LIMIT 100", $owner));
    $now = (int) current_time('timestamp');
    $out = [];

    foreach ((array) $rows as $r) {
        $full = trim($r->tenant_prenom . ' ' . $r->tenant_nom) ?: ('Dossier #' . $r->id);
        $logs = json_decode($r->notes ?? '', true);
        $sent = (is_array($logs) && !empty($logs['email_log']))  ? $logs['email_log']  : [];
        $recu = (is_array($logs) && !empty($logs['email_recu'])) ? $logs['email_recu'] : [];
        $statut   = (string) ($r->statut ?? '');
        $has_lrar = !empty($r->lrar_travaux_date) || !empty($r->lrar_relogement_date);

        /* Ne pas générer d'alerte « premier contact » quand :
           - le dossier a été écarté à la main (croix) ;
           - le « locataire » est en fait un admin / membre du GA (ex. le
             gestionnaire lui-même) → on ne se contacte pas soi-même ;
           - c'est un dossier de démonstration (« modèle », « démo », « test »). */
        $skip_contact = false;
        if (in_array((int) $r->id, lfi_nct_alertes_dismissed(), true)) $skip_contact = true;
        $tuid = (int) ($r->tenant_user_id ?? 0);
        if ($tuid) {
            if ($tuid === $owner) $skip_contact = true;
            $tu = get_userdata($tuid);
            if ($tu && (in_array('administrator', (array) $tu->roles, true)
                || (defined('LFI_NCT_ROLE_GA') && in_array(LFI_NCT_ROLE_GA, (array) $tu->roles, true)))) $skip_contact = true;
        }
        if (preg_match('/mod[eè]le|d[ée]mo|\btest\b|exemple|sp[eé]cimen/iu', $full)) $skip_contact = true;

        /* 📞 PREMIER CONTACT : la personne veut de l'aide mais RIEN n'est engagé
           (aucun courrier envoyé, aucune mise en demeure, aucun SCHS). C'est le
           « ne pas oublier les gens à recontacter ». Disparaît dès qu'on agit. */
        if (!$skip_contact && $statut !== 'clos' && empty($sent) && !$has_lrar && empty($r->schs_date)) {
            $out[] = [
                'prio'      => 'haute',
                'titre'     => '📞 Premier contact à faire — ' . $full,
                'detail'    => 'La personne attend d\'être recontactée — invite-la sur l\'app, puis prends RDV.',
                'url'       => lfi_nct_app_url('dossier-juridique-edit', ['id' => $r->id]),
                'dossier_id'=> (int) $r->id,
                /* Invitation directe (SMS + lien app) si on a le compte locataire. */
                'invite_uid'=> $tuid > 0 ? $tuid : 0,
            ];
        }

        /* ⚖️ ÉTAPE SUIVANTE DU PLAN : SCHS saisi mais resté sans suite depuis
           plus de 15 jours → on passe la main à l'avocat.
           RÈGLE : le judiciaire / l'avocat, c'est FABRICE et lui SEUL. Cette
           alerte n'apparaît donc JAMAIS chez un membre du GA — uniquement pour
           l'admin (Fabrice). */
        if (!empty($r->schs_date) && $statut !== 'clos' && current_user_can('manage_options')) {
            $schs_ts = strtotime($r->schs_date);
            $replied_since = false;
            foreach ($recu as $e) { if ($schs_ts && strtotime($e['date'] ?? '') > $schs_ts) { $replied_since = true; break; } }
            if ($schs_ts && !$replied_since) {
                $age = (int) floor(($now - $schs_ts) / 86400);
                if ($age >= 15) {
                    $out[] = [
                        'prio'   => 'haute',
                        'titre'  => '⚖️ Étape suivante : avocat — ' . $full,
                        'detail' => 'SCHS saisi il y a ' . $age . ' j sans suite. Passe la main à l\'avocat (via toi).',
                        'url'    => lfi_nct_app_url('dossier-juridique-edit', ['id' => $r->id]),
                    ];
                }
            }
        }

        /* ⏰ Délai légal NMH dépassé (à partir de la mise en demeure). Dialogue
           avec Morineau rompu → deux leviers, dans l'ordre : d'abord REMONTER en
           AMIABLE à Christophe Jouin (CA de NMH, décideur), puis saisir le SCHS. */
        if (!empty($r->lrar_travaux_date) && empty($r->schs_date) && ($r->statut ?? '') !== 'clos' && function_exists('lfi_nct_nmh_deadline')) {
            $deadline = lfi_nct_nmh_deadline($r->lrar_travaux_date, $r->nmh_urgence ?: 'bailleur');
            if ($deadline && strtotime($deadline) < $now) {
                $late = (int) floor(($now - strtotime($deadline)) / 86400);
                /* Étape amiable : escalade au CA (Christophe Jouin) — plus de
                   pouvoir de décision, parle directement au conseil d'administration. */
                $out[] = [
                    'prio'   => 'haute',
                    'titre'  => '📈 Remonter à Christophe Jouin (CA NMH) — ' . $full,
                    'detail' => 'Dialogue Morineau rompu (délai dépassé de ' . $late . ' j). Étape amiable : escalade au décideur (christophe.jouin@mairie-nantes.fr). On donne le NOM de ce locataire + ses demandes (pas l\'enquête entière, pas le chiffrage).',
                    'url'    => lfi_nct_app_url('dossier-juridique-edit', ['id' => $r->id]),
                ];
                $out[] = [
                    'prio'   => 'haute',
                    'titre'  => '⏰ Délai NMH dépassé — ' . $full,
                    'detail' => 'Mise en demeure sans effet (date limite : ' . wp_date('j M Y', strtotime($deadline)) . ', dépassée de ' . $late . ' j). Si l\'amiable (Jouin) n\'aboutit pas : saisir le SCHS.',
                    'url'    => lfi_nct_app_url('dossier-doc-schs', ['id' => $r->id]),
                ];
            }
        }

        /* NOTE : on ne demande JAMAIS à l'utilisateur d'« analyser » un courrier.
           L'analyse et la rédaction de la réponse, c'est le travail de l'assistant.
           Les réponses prêtes apparaissent dans l'écran « À envoyer » : l'utilisateur
           les relit et les envoie, rien d'autre. */

        /* Dernier courrier envoyé sans réponse depuis 8 jours → relancer. */
        if (!empty($sent)) {
            usort($sent, function ($a, $b) { return strcmp($a['date'] ?? '', $b['date'] ?? ''); });
            $last = end($sent);
            $last_ts = strtotime($last['date'] ?? '');
            $replied = false;
            foreach ($recu as $e) { if ($last_ts && strtotime($e['date'] ?? '') > $last_ts) { $replied = true; break; } }
            if ($last_ts && !$replied) {
                $age = (int) floor(($now - $last_ts) / 86400);
                if ($age >= 8) {
                    $letter = sanitize_key($last['letter'] ?? '');
                    $url = $letter
                        ? lfi_nct_app_url('dossier-send-email', ['id' => $r->id, 'letter' => $letter, 'relance' => 1])
                        : lfi_nct_app_url('dossier-juridique-edit', ['id' => $r->id]);
                    $out[] = [
                        'prio'   => 'moyenne',
                        'titre'  => 'Relancer NMH — ' . $full,
                        'detail' => 'Dernier courrier il y a ' . $age . ' jours, sans réponse.',
                        'url'    => $url,
                    ];
                }
            }
        }
    }
    return $out;
}

/** Toutes les alertes, triées par priorité (haute → basse). */
function lfi_nct_alertes_all() {
    $all = array_merge(lfi_nct_alertes_manual(), lfi_nct_alertes_auto());
    $order = ['haute' => 0, 'moyenne' => 1, 'basse' => 2];
    usort($all, function ($x, $y) use ($order) {
        return ($order[$x['prio']] ?? 1) <=> ($order[$y['prio']] ?? 1);
    });
    return $all;
}

/** Bandeau « À faire » en haut de l'accueil. */
function lfi_nct_render_home_alerts() {
    if (function_exists('lfi_nct_module_enabled') && !lfi_nct_module_enabled('alertes')) return;
    $alertes = lfi_nct_alertes_all();
    if (empty($alertes)) return;
    echo '<div class="lfi-app-alertes" style="background:#fff;border:2px solid #c8102e;border-radius:12px;padding:10px 12px;margin:12px 0">';
    echo '<div style="font-weight:800;color:#c8102e;margin-bottom:4px">🔔 À faire — à ne pas mettre de côté (' . count($alertes) . ')</div>';
    foreach ($alertes as $al) {
        $dot = $al['prio'] === 'haute' ? '#c8102e' : ($al['prio'] === 'moyenne' ? '#d39e00' : '#888');
        echo '<a href="' . esc_url($al['url']) . '" style="display:flex;gap:10px;align-items:flex-start;text-decoration:none;color:#1a1a1a;padding:8px 4px;border-top:1px solid #eee">';
        echo '<span style="width:10px;height:10px;border-radius:50%;background:' . $dot . ';margin-top:5px;flex:0 0 auto"></span>';
        echo '<span style="flex:1"><strong>' . esc_html($al['titre']) . '</strong>' . (!empty($al['detail']) ? '<br><span style="font-size:.88em;color:#555">' . esc_html($al['detail']) . '</span>' : '') . '</span>';
        echo '<span style="color:#c8102e;font-weight:700;white-space:nowrap;align-self:center">Ouvrir →</span>';
        echo '</a>';
        /* Bouton d'invitation directe : SMS pré-rédigé + lien app (parcours guidé). */
        if (!empty($al['invite_uid'])) {
            echo '<div style="padding:0 4px 8px 24px"><a class="btn-primary" style="background:#0066a3;padding:7px 12px;font-size:.85em" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => (int) $al['invite_uid'], 'autoshare' => 1])) . '">📲 Inviter sur l\'app (SMS pré-rédigé)</a></div>';
        }
        /* Croix « écarter » pour les alertes liées à un dossier (fait / non pertinent). */
        if (!empty($al['dossier_id'])) {
            $du = wp_nonce_url(admin_url('admin-post.php?action=lfi_nct_alert_dismiss&did=' . (int) $al['dossier_id']), 'lfi_nct_alert_dismiss_' . (int) $al['dossier_id']);
            echo '<div style="text-align:right;margin:-6px 4px 2px"><a href="' . esc_url($du) . '" onclick="return confirm(\'Écarter cette alerte (déjà fait / non pertinent) ?\');" style="font-size:.78em;color:#888;text-decoration:none">✕ écarter</a></div>';
        }
    }
    echo '</div>';
}
