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

        /* 📞 PREMIER CONTACT : la personne veut de l'aide mais RIEN n'est engagé
           (aucun courrier envoyé, aucune mise en demeure, aucun SCHS). C'est le
           « ne pas oublier les gens à recontacter ». Disparaît dès qu'on agit. */
        if ($statut !== 'clos' && empty($sent) && !$has_lrar && empty($r->schs_date)) {
            $out[] = [
                'prio'   => 'haute',
                'titre'  => '📞 Premier contact à faire — ' . $full,
                'detail' => 'La personne attend d\'être recontactée — rien n\'est encore engagé.',
                'url'    => lfi_nct_app_url('dossier-juridique-edit', ['id' => $r->id]),
            ];
        }

        /* ⚖️ ÉTAPE SUIVANTE DU PLAN : SCHS saisi mais resté sans suite depuis
           plus de 15 jours → on passe la main à l'avocat (via toi). */
        if (!empty($r->schs_date) && $statut !== 'clos') {
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
    }
    echo '</div>';
}
