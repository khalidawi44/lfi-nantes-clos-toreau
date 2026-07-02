<?php
/**
 * ROBOT ASSISTANT (étape 1 : commandes intelligentes, gratuit, LECTURE SEULE).
 *
 * Pour les administrateur·rices de GA : on tape une demande en langage
 * simple (« dossier locataire 27 », « enquête RE01 », « stats », « contacts
 * NMH », « que faire pour des moisissures »…) et le robot interroge la base
 * — STRICTEMENT cloisonnée au GA courant — puis renvoie le bon document /
 * les bons liens. Il ne modifie jamais rien (pas de write, pas de code).
 *
 * L'assistant s'ouvre dans une POPUP de discussion (chat) sans changer de
 * page — voir lfi_nct_app_render_assistant_button() dans app.php. La popup
 * appelle l'endpoint AJAX lfi_nct_robot_ajax(), qui réutilise exactement la
 * même logique de réponse que les vues plein écran ci-dessous.
 *
 * Étape 2 (plus tard) : brancher l'IA Claude par-dessus pour le langage libre.
 */
if (!defined('ABSPATH')) exit;

/** Peut utiliser le robot « admin » : admin du GA ou super-admin. */
function lfi_nct_robot_can() {
    return function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options');
}

/** Trouve une enquête par sa référence (RE01, CLO02…) dans le GA courant. */
function lfi_nct_robot_find_response_by_ref($ref) {
    $ref = strtoupper(preg_replace('/\s+/', '', (string) $ref));
    if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) return 0;
    $code = $m[1]; $num = (int) $m[2];
    $slug = function_exists('lfi_nct_scope_ga_slug') ? lfi_nct_scope_ga_slug() : '';
    if (function_exists('lfi_nct_ga_code') && strtoupper(lfi_nct_ga_code($slug)) !== $code) {
        /* Réf d'un autre GA → refusé (cloisonnement). */
        return 0;
    }
    $map = function_exists('lfi_nct_response_seq_map') ? lfi_nct_response_seq_map($slug) : [];
    foreach ($map as $id => $seq) { if ((int) $seq === $num) return (int) $id; }
    return 0;
}

/** Points de contact prioritaires (côté utilisateur·rice). */
function lfi_nct_robot_public_contacts() {
    $tel     = 'tel:+33623526074';
    $survey  = function_exists('lfi_nct_survey_url') ? lfi_nct_survey_url() : home_url('/');
    $contact = function_exists('lfi_nct_page_url') ? lfi_nct_page_url('signer', $survey) : $survey;
    $rdv     = function_exists('lfi_nct_page_url') ? lfi_nct_page_url('rendez-vous', $contact) : $contact;
    return ['tel' => $tel, 'contact' => $contact, 'rdv' => $rdv];
}

/** Puces de suggestions rapides (chat + plein écran). */
function lfi_nct_robot_chips($is_admin) {
    if ($is_admin) {
        return [
            'stats'                 => '📊 Stats',
            'carte'                 => '🗺️ Carte',
            'contacts NMH'          => '☎️ Contacts NMH',
            'que faire moisissures' => '📋 Que faire ?',
            'enquêtes'              => '🏠 Enquêtes',
            'locataires'            => '🗂 Locataires',
        ];
    }
    return [
        'moisissures' => '🌫 Moisissures',
        'chauffage'   => '🥶 Chauffage / eau chaude',
        'punaises'    => '🐜 Nuisibles',
        'électricité' => '⚡ Électricité',
        'insécurité'  => '🚨 Insécurité',
        'loyer'       => '💶 Loyer / charges',
    ];
}

/** Message d'accueil de la popup (première bulle). */
function lfi_nct_robot_welcome_html($is_admin) {
    ob_start();
    if ($is_admin) {
        echo '<div class="lfi-app-help" style="margin:0">👋 Dis-moi ce que tu cherches, en langage simple : un dossier, une enquête, des stats, un contact…</div>';
        echo '<div class="lfi-app-help" style="margin-top:8px;background:#f7f7f7"><small>🔒 Je lis seulement les données de <strong>ton</strong> groupe d\'action, je ne modifie rien.</small></div>';
    } else {
        $c = lfi_nct_robot_public_contacts();
        echo '<div style="font-weight:800;color:#c8102e;margin-bottom:6px">📞 Être accompagné·e gratuitement</div>';
        echo '<div class="lfi-app-help" style="margin:0 0 8px">Un problème dans ton logement ? Ne reste pas seul·e. Dis-moi ce qui se passe, ou contacte-nous directement.</div>';
        echo '<div class="row-actions" style="display:flex;gap:8px;flex-wrap:wrap">';
        echo '<a class="btn-primary" href="' . esc_url($c['contact']) . '">✍️ Nous écrire</a>';
        echo '<a class="btn-ghost" href="' . esc_attr($c['tel']) . '">📞 Appeler</a>';
        echo '<a class="btn-ghost" href="' . esc_url($c['rdv']) . '">📅 Rendez-vous</a>';
        echo '</div>';
    }
    return ob_get_clean();
}

/* ============================================================== *
 *  RÉPONSE ADMIN (langage simple → cartes/liens, cloisonné GA)    *
 *  Réutilisée par la vue plein écran ET la popup AJAX.            *
 * ============================================================== */
function lfi_nct_robot_answer_admin_html($q) {
    if (!lfi_nct_robot_can()) return '';
    global $wpdb;
    $q = trim((string) $q);
    if ($q === '') return lfi_nct_robot_welcome_html(true);

    ob_start();

    $ql = function_exists('mb_strtolower') ? mb_strtolower($q) : strtolower($q);
    $has = function ($needles) use ($ql) {
        foreach ((array) $needles as $n) if (strpos($ql, $n) !== false) return true;
        return false;
    };
    $num = (preg_match('/\d+/', $q, $mm)) ? (int) $mm[0] : 0;
    $answered = false;

    /* ---- 1) Enquête par référence (RE01, CLO02…) ---- */
    if (preg_match('/\b([a-z]{2,4}\s?\d{1,3})\b/i', $q, $rm)) {
        $rid = lfi_nct_robot_find_response_by_ref($rm[1]);
        if ($rid) {
            $sc = function_exists('lfi_nct_responses_scope_clause') ? lfi_nct_responses_scope_clause() : '';
            $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d AND deleted_at IS NULL" . $sc, $rid));
            if ($r) {
                $ref = function_exists('lfi_nct_response_ref') ? lfi_nct_response_ref($r->id, function_exists('lfi_nct_response_ga_of') ? lfi_nct_response_ga_of($r) : '') : '';
                $prob = function_exists('lfi_nct_app_enq_problem') ? lfi_nct_app_enq_problem($r) : null;
                echo '<div class="lfi-app-card"><div class="head"><div class="who">🏠 Enquête ' . esc_html($ref) . ' — ' . esc_html(trim($r->contact_prenom . ' ' . $r->contact_nom) ?: '(anonyme)') . '</div></div>';
                if ($r->adresse) echo '<div class="meta"><span class="meta-chip">📍 ' . esc_html(trim($r->adresse . ($r->etage ? ' · ét. ' . $r->etage : ''))) . '</span></div>';
                if ($prob && !empty($prob['chips'])) {
                    echo '<div class="prob-chips" style="margin-top:4px">';
                    foreach ($prob['chips'] as $ch) echo '<span class="prob-chip">' . $ch[0] . ' ' . esc_html($ch[1]) . '</span>';
                    echo '</div>';
                }
                echo '<div class="row-actions" style="margin-top:8px"><a class="btn-primary" href="' . esc_url(lfi_nct_app_url('enquetes')) . '">Ouvrir les enquêtes</a></div></div>';
                $answered = true;
            }
        }
    }

    /* ---- 2) Dossier / locataire ---- */
    if (!$answered && $has(['dossier', 'locataire', 'locataires'])) {
        /* a) par numéro de dossier (borné au GA via lfi_nct_dossier_get) */
        $d = ($num && function_exists('lfi_nct_dossier_get')) ? lfi_nct_dossier_get($num) : null;
        if ($d) {
            lfi_nct_robot_render_dossier_card($d->tenant_user_id, trim($d->tenant_prenom . ' ' . $d->tenant_nom), $d->tenant_adresse);
            $answered = true;
        } else {
            /* b) recherche par nom parmi les locataires du GA */
            $terms = trim(preg_replace('/\b(dossier|locataire|locataires|de|du|la|le|numero|numéro|n°)\b/iu', ' ', $q));
            $args = ['role' => defined('LFI_NCT_ROLE_TENANT') ? LFI_NCT_ROLE_TENANT : 'lfi_nct_tenant', 'number' => 20, 'fields' => ['ID', 'display_name']];
            if ($terms !== '') { $args['search'] = '*' . $terms . '*'; $args['search_columns'] = ['display_name', 'user_login', 'user_email']; }
            if (function_exists('lfi_nct_users_ga_query')) $args = lfi_nct_users_ga_query($args);
            $found = get_users($args);
            if ($found) {
                echo '<div class="lfi-app-help">' . count($found) . ' locataire(s) trouvé(s) :</div><ul class="lfi-app-list">';
                foreach ($found as $u) {
                    echo '<li class="lfi-app-card" style="padding:9px 12px"><div class="head"><div class="who">🗂 ' . esc_html($u->display_name) . '</div></div>';
                    echo '<div class="row-actions" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">';
                    echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => $u->ID])) . '">📂 Dossier</a>';
                    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-avocat', ['uid' => $u->ID])) . '" target="_blank">⚖️ Note avocat (PDF)</a>';
                    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-recap-nmh', ['uid' => $u->ID])) . '" target="_blank">🧾 Récap NMH</a>';
                    echo '</div></li>';
                }
                echo '</ul>';
            } else {
                echo '<div class="lfi-app-empty">Aucun locataire trouvé dans ton GA pour « ' . esc_html($q) . ' ».</div>';
            }
            $answered = true;
        }
    }

    /* ---- 3) Stats / carte ---- */
    if (!$answered && $has(['stat', 'chiffre', 'combien'])) {
        $s = function_exists('lfi_nct_app_quick_stats') ? lfi_nct_app_quick_stats() : [];
        echo '<div class="lfi-app-stats-grid">';
        echo '<div class="stat"><div class="ico">🏠</div><div class="n">' . (int) ($s['surveys'] ?? 0) . '</div><div class="l">Enquêtes</div></div>';
        echo '<div class="stat"><div class="ico">👥</div><div class="n">' . (int) ($s['membres'] ?? 0) . '</div><div class="l">Membres actifs</div></div>';
        echo '<div class="stat"><div class="ico">📅</div><div class="n">' . (int) ($s['events'] ?? 0) . '</div><div class="l">Événements</div></div>';
        echo '</div>';
        echo '<div class="row-actions" style="margin-top:8px"><a class="btn-primary" href="' . esc_url(lfi_nct_app_url('stats-enquete')) . '">📊 Stats détaillées</a> <a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('carte')) . '">🗺️ Carte</a></div>';
        $answered = true;
    }
    if (!$answered && $has(['carte', 'map', 'géoloc', 'geoloc'])) {
        echo '<div class="lfi-app-card">🗺️ Ta carte 3D des signalements.<div class="row-actions" style="margin-top:8px"><a class="btn-primary" href="' . esc_url(lfi_nct_app_url('carte')) . '">Ouvrir la carte</a></div></div>';
        $answered = true;
    }

    /* ---- 4) Contacts NMH / bailleur ---- */
    if (!$answered && $has(['nmh', 'bailleur', 'contact', 'morineau', 'agence', 'téléphone', 'telephone', 'mail', 'email'])) {
        $b = function_exists('lfi_nct_fact_bailleur') ? lfi_nct_fact_bailleur() : [];
        echo '<div class="lfi-app-card"><div class="head"><div class="who">☎️ ' . esc_html($b['nom'] ?? 'Bailleur') . '</div></div><div class="meta" style="flex-direction:column;align-items:flex-start;gap:4px">';
        if (!empty($b['agence_nom']))     echo '<span class="meta-chip">🏘 ' . esc_html($b['agence_nom']) . (!empty($b['agence_secteur']) ? ' — ' . esc_html($b['agence_secteur']) : '') . '</span>';
        if (!empty($b['agence_contact'])) echo '<span class="meta-chip">👤 ' . esc_html($b['agence_contact']) . '</span>';
        if (!empty($b['agence_tel']))     echo '<a class="meta-chip" href="tel:' . esc_attr(preg_replace('/[^\d+]/', '', $b['agence_tel'])) . '">📞 ' . esc_html($b['agence_tel']) . '</a>';
        if (!empty($b['agence_email']))   echo '<a class="meta-chip" href="mailto:' . esc_attr($b['agence_email']) . '">✉️ ' . esc_html($b['agence_email']) . '</a>';
        echo '</div><div class="row-actions" style="margin-top:8px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('facturation-params')) . '">⚙️ Modifier les contacts</a></div></div>';
        $answered = true;
    }

    /* ---- 5) Marche à suivre ---- */
    if (!$answered && $has(['que faire', 'quoi faire', 'démarche', 'demarche', 'étape', 'etape', 'moisiss', 'insalub', 'humidit', 'procédure', 'procedure', 'comment'])) {
        echo '<div class="lfi-app-card"><div class="head"><div class="who">📋 Marche à suivre — insalubrité / non-décence</div></div><ol style="line-height:1.7;margin:8px 0 0;padding-left:18px">';
        echo '<li><strong>Documenter</strong> : enquête + photos horodatées + dossier du locataire (fais-le adhérer à l\'asso).</li>';
        echo '<li><strong>Mise en demeure LRAR</strong> au bailleur (délai légal selon l\'urgence : 8 j santé/sécurité, 1 mois réparation, 2 mois autre).</li>';
        echo '<li><strong>Délai dépassé → SCHS / mairie</strong> (service d\'hygiène) puis <strong>ARS</strong>.</li>';
        echo '<li><strong>Tribunal</strong> (référé travaux, action au fond) — via un avocat, selon la volonté du locataire.</li>';
        echo '</ol><div class="row-actions" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap"><a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dossiers')) . '">🗂 Dossiers locataires</a><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('tutoriels')) . '">🛠 Tutoriels</a></div></div>';
        $answered = true;
    }

    /* ---- Fallback ---- */
    if (!$answered) {
        echo '<div class="lfi-app-empty">Je n\'ai pas compris « ' . esc_html($q) . ' ». Essaie : <em>dossier locataire &lt;nom ou n°&gt;</em>, <em>enquête RE01</em>, <em>stats</em>, <em>carte</em>, <em>contacts NMH</em>, <em>que faire pour…</em></div>';
    }

    return ob_get_clean();
}

/* ============================================================== *
 *  RÉPONSE UTILISATEUR·RICE (locataires, visiteurs) — orientation *
 *  + mise en relation prioritaire. Réutilisée plein écran + popup.*
 * ============================================================== */
function lfi_nct_robot_answer_public_html($q) {
    $q = trim((string) $q);
    if ($q === '') return lfi_nct_robot_welcome_html(false);

    $c  = lfi_nct_robot_public_contacts();
    $ql = function_exists('mb_strtolower') ? mb_strtolower($q) : strtolower($q);
    $has = function ($needles) use ($ql) { foreach ((array) $needles as $n) if (strpos($ql, $n) !== false) return true; return false; };

    $titre = '💬 Ta situation'; $texte = '';
    if ($has(['moisiss', 'humidit', 'infiltrat', 'condensation'])) {
        $titre = '🌫 Moisissures / humidité';
        $texte = 'Ton logement doit être <strong>décent et sain</strong> (loi n° 89-462, art. 6 ; décret 2002-120). Les moisissures liées au bâti sont à la charge du <strong>bailleur</strong>. <br><strong>Première action :</strong> signale-le <strong>par écrit</strong> au bailleur et prends des <strong>photos datées</strong>. On rédige la lettre avec toi et on suit les délais.';
    } elseif ($has(['chauff', 'eau chaude', 'froid', 'radiateur'])) {
        $titre = '🥶 Chauffage / eau chaude';
        $texte = 'Le bailleur doit garantir un <strong>chauffage et une eau chaude</strong> qui fonctionnent. Une panne durable, surtout l\'hiver ou avec des enfants, est une <strong>urgence</strong>. On t\'aide à le mettre en demeure rapidement.';
    } elseif ($has(['punais', 'cafard', 'blatte', 'rat', 'souris', 'nuisib', 'cafr'])) {
        $titre = '🐜 Nuisibles';
        $texte = 'Un logement décent doit être <strong>exempt de nuisibles</strong>. Selon les cas, le traitement incombe au bailleur. Ne paie pas seul·e un traitement coûteux sans nous en parler — on t\'oriente.';
    } elseif ($has(['électri', 'electri', 'prise', 'disjonct', 'court-circuit'])) {
        $titre = '⚡ Électricité';
        $texte = 'Une installation électrique <strong>dangereuse</strong> met ta sécurité en jeu : le bailleur doit la remettre aux normes. C\'est à traiter en priorité.';
    } elseif ($has(['insécur', 'insecur', 'partie commune', 'parties communes', 'ascenseur', 'porte', 'serrure'])) {
        $titre = '🚨 Sécurité / parties communes';
        $texte = 'L\'entretien des <strong>parties communes</strong> et la sécurité de l\'immeuble relèvent du bailleur. Si ça dure, un signalement collectif est souvent plus efficace — on peut t\'aider à mobiliser tes voisin·es.';
    } elseif ($has(['loyer', 'charge', 'expuls', 'impayé', 'impaye', 'apl', 'caf'])) {
        $titre = '💶 Loyer / charges / expulsion';
        $texte = 'Selon ta situation, il existe des <strong>recours et des aides</strong> (CAF, ADIL 44, commission de conciliation). Ne laisse pas traîner : plus tôt on agit, mieux c\'est. Contacte-nous, on t\'oriente.';
    } else {
        $texte = 'On n\'a pas de réponse toute prête, mais on peut t\'aider. <strong>Le mieux : nous contacter</strong>. On regarde ta situation avec toi.';
    }

    ob_start();
    echo '<div class="lfi-app-card"><div class="head"><div class="who">' . $titre . '</div></div><div style="line-height:1.55;margin-top:6px">' . $texte . '</div>';
    echo '<div class="row-actions" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">';
    echo '<a class="btn-primary" href="' . esc_url($c['contact']) . '">✍️ Être accompagné·e</a>';
    echo '<a class="btn-ghost" href="' . esc_attr($c['tel']) . '">📞 Appeler</a>';
    echo '</div></div>';
    echo '<div class="lfi-app-help" style="margin-top:8px"><small>Ceci est une première orientation, pas un avis juridique. On t\'accompagne pour la suite.</small></div>';
    return ob_get_clean();
}

/* ============================================================== *
 *  ENDPOINT AJAX : appelé par la popup de discussion.            *
 *  Cloisonné : mêmes fonctions de scope que les vues plein écran.*
 * ============================================================== */
add_action('wp_ajax_lfi_nct_robot', 'lfi_nct_robot_ajax');
add_action('wp_ajax_nopriv_lfi_nct_robot', 'lfi_nct_robot_ajax');
function lfi_nct_robot_ajax() {
    check_ajax_referer('lfi_nct_robot', 'nonce');
    $q = isset($_POST['q']) ? trim(sanitize_text_field(wp_unslash($_POST['q']))) : '';
    $is_admin = lfi_nct_robot_can();
    $html = $is_admin ? lfi_nct_robot_answer_admin_html($q) : lfi_nct_robot_answer_public_html($q);
    if ($html === '' || $html === null) {
        $html = '<div class="lfi-app-empty">Je n\'ai pas bien compris. Reformule, ou contacte-nous directement.</div>';
    }
    wp_send_json_success(['html' => $html, 'admin' => (bool) $is_admin]);
}

/* ============================================================== *
 *  VUE PLEIN ÉCRAN : Robot assistant (admin) — repli sans JS.     *
 * ============================================================== */
function lfi_nct_app_view_assistant() {
    if (!lfi_nct_robot_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $q = isset($_GET['q']) ? trim(sanitize_text_field(wp_unslash($_GET['q']))) : '';

    lfi_nct_app_screen_open('🤖 Assistant', 'Demande-moi un dossier, une enquête, des stats, un contact…');

    /* Barre de recherche. */
    echo '<form method="get" class="lfi-app-searchbar" style="margin-bottom:10px">';
    echo '<input type="hidden" name="vue" value="assistant">';
    echo '<input type="search" name="q" value="' . esc_attr($q) . '" placeholder="Ex : dossier locataire 27 · enquête RE01 · stats · contacts NMH">';
    echo '<button type="submit">🔎</button>';
    echo '</form>';

    if ($q === '') {
        echo '<div class="lfi-app-help">Tape ce que tu veux, en langage simple. Exemples :</div>';
        echo '<div class="lfi-app-filter-chips">';
        foreach (lfi_nct_robot_chips(true) as $ex => $lab) {
            echo '<a class="fc" href="' . esc_url(lfi_nct_app_url('assistant', ['q' => $ex])) . '">' . esc_html($lab) . '</a>';
        }
        echo '</div>';
        echo '<div class="lfi-app-help" style="margin-top:10px;background:#f7f7f7"><small>🔒 L\'assistant lit seulement les données de <strong>ton</strong> groupe d\'action, ne modifie rien, et te renvoie les documents (dossiers, récapitulatifs, stats).</small></div>';
        lfi_nct_app_screen_close();
        return;
    }

    echo lfi_nct_robot_answer_admin_html($q);
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE PLEIN ÉCRAN : aide utilisateur·rice — repli sans JS.       *
 * ============================================================== */
function lfi_nct_app_view_aide() {
    $q = isset($_GET['q']) ? trim(sanitize_text_field(wp_unslash($_GET['q']))) : '';
    $c = lfi_nct_robot_public_contacts();

    lfi_nct_app_screen_open('🤖 On peut t\'aider', 'Dis-nous ton problème de logement — on t\'accompagne, gratuitement');

    /* --- CTA PRIORITAIRE : nous contacter --- */
    echo '<div class="lfi-app-card" style="border:2px solid #c8102e">';
    echo '<div style="font-weight:800;color:#c8102e;font-size:1.05em;margin-bottom:6px">📞 Être accompagné·e gratuitement</div>';
    echo '<div class="lfi-app-help" style="margin:0 0 8px">Un problème dans ton logement ? Ne reste pas seul·e. On t\'aide à faire valoir tes droits, pas à pas.</div>';
    echo '<div class="row-actions" style="display:flex;gap:8px;flex-wrap:wrap">';
    echo '<a class="btn-primary big" href="' . esc_url($c['contact']) . '">✍️ Nous écrire / demander de l\'aide</a>';
    echo '<a class="btn-ghost" href="' . esc_attr($c['tel']) . '">📞 Appeler</a>';
    echo '<a class="btn-ghost" href="' . esc_url($c['rdv']) . '">📅 Prendre rendez-vous</a>';
    echo '</div></div>';

    /* --- Recherche du problème --- */
    echo '<form method="get" class="lfi-app-searchbar" style="margin:12px 0 8px">';
    echo '<input type="hidden" name="vue" value="aide">';
    echo '<input type="search" name="q" value="' . esc_attr($q) . '" placeholder="Décris ton problème : moisissures, chauffage, punaises, loyer…">';
    echo '<button type="submit">🔎</button>';
    echo '</form>';

    if ($q === '') {
        echo '<div class="lfi-app-filter-chips">';
        foreach (lfi_nct_robot_chips(false) as $ex => $lab) {
            echo '<a class="fc" href="' . esc_url(lfi_nct_app_url('aide', ['q' => $ex])) . '">' . esc_html($lab) . '</a>';
        }
        echo '</div>';
        lfi_nct_app_screen_close(false);
        return;
    }

    echo lfi_nct_robot_answer_public_html($q);
    lfi_nct_app_screen_close(false);
}

/** Carte d'un locataire (liens dossier / note avocat / récap NMH). */
function lfi_nct_robot_render_dossier_card($uid, $name, $adresse) {
    echo '<div class="lfi-app-card"><div class="head"><div class="who">🗂 ' . esc_html($name ?: ('Locataire #' . (int) $uid)) . '</div></div>';
    if ($adresse) echo '<div class="meta"><span class="meta-chip">📍 ' . esc_html($adresse) . '</span></div>';
    echo '<div class="row-actions" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">';
    if ($uid) {
        echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => $uid])) . '">📂 Dossier</a>';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-avocat', ['uid' => $uid])) . '" target="_blank">⚖️ Note avocat (PDF)</a>';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-recap-nmh', ['uid' => $uid])) . '" target="_blank">🧾 Récap NMH</a>';
    } else {
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossiers')) . '">🗂 Voir les dossiers</a>';
    }
    echo '</div></div>';
}
