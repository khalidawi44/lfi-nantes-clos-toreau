<?php
/**
 * Content store — « contenu géré par le code »
 *
 * Idée : certains contenus (événements, analyses d'emails NMH…) sont
 * écrits dans des fichiers du dépôt, dans le dossier /content/, par
 * Claude Code à la demande. À chaque push, ils s'affichent sur le site.
 * Le but : tout piloter depuis la conversation, sans formulaire WordPress.
 *
 * Chaque fichier de /content/ renvoie un tableau PHP. On le charge de
 * façon défensive (jamais de page blanche si un fichier manque ou casse).
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_content_dir() {
    return LFI_NCT_PATH . 'content/';
}

/**
 * Charge un fichier de contenu (ex : 'evenements.php') et renvoie le
 * tableau qu'il retourne, ou [] si absent/illisible. Jamais d'erreur fatale.
 */
function lfi_nct_content_load($file) {
    $path = lfi_nct_content_dir() . ltrim($file, '/');
    if (!is_file($path) || !is_readable($path)) return [];
    try {
        $data = include $path;
        return is_array($data) ? $data : [];
    } catch (\Throwable $e) {
        if (function_exists('error_log')) error_log('[LFI content] ' . $file . ' : ' . $e->getMessage());
        return [];
    }
}

/* ============================================================== *
 *  ÉVÉNEMENTS                                                      *
 * ============================================================== */

/** Tous les événements déclarés dans content/evenements.php */
function lfi_nct_content_events() {
    $events = lfi_nct_content_load('evenements.php');
    /* Normalisation + tri par date croissante */
    $events = array_values(array_filter($events, function ($e) {
        return is_array($e) && !empty($e['titre']) && !empty($e['date']);
    }));
    usort($events, function ($a, $b) {
        return strcmp(($a['date'] ?? '') . ($a['heure'] ?? ''), ($b['date'] ?? '') . ($b['heure'] ?? ''));
    });
    return $events;
}

/** Événements à venir (date >= aujourd'hui) */
function lfi_nct_content_events_a_venir() {
    $today = current_time('Y-m-d');
    return array_values(array_filter(lfi_nct_content_events(), function ($e) use ($today) {
        return ($e['date'] ?? '') >= $today;
    }));
}

/**
 * Shortcode [lfi_nct_evenements] — liste publique des événements à venir.
 * À placer une seule fois sur une page WordPress ; ensuite, il suffit
 * d'éditer content/evenements.php (ici, avec Claude) et de pousser.
 *
 * Attribut : [lfi_nct_evenements passes="1"] pour inclure aussi les
 * événements passés.
 */
add_shortcode('lfi_nct_evenements', 'lfi_nct_evenements_shortcode');
function lfi_nct_evenements_shortcode($atts = []) {
    $atts = shortcode_atts(['passes' => '0', 'limite' => '0'], (array) $atts, 'lfi_nct_evenements');
    $events = !empty($atts['passes']) && $atts['passes'] !== '0'
        ? array_reverse(lfi_nct_content_events())
        : lfi_nct_content_events_a_venir();
    if ((int) $atts['limite'] > 0) $events = array_slice($events, 0, (int) $atts['limite']);

    ob_start();
    echo '<div class="lfi-evts">';
    echo '<style>
    .lfi-evts{max-width:760px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
    .lfi-evt{display:flex;gap:14px;background:#fff;border:1px solid #eee;border-left:5px solid #c8102e;border-radius:12px;padding:16px 18px;margin:0 0 14px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
    .lfi-evt-date{flex:0 0 auto;text-align:center;background:#c8102e;color:#fff;border-radius:10px;padding:10px 12px;min-width:64px;height:fit-content}
    .lfi-evt-date .j{font-size:1.6em;font-weight:800;line-height:1}
    .lfi-evt-date .m{font-size:.78em;text-transform:uppercase;letter-spacing:.04em}
    .lfi-evt-body{flex:1}
    .lfi-evt-body h3{margin:0 0 6px;color:#1a1a1a;font-size:1.15em}
    .lfi-evt-meta{color:#666;font-size:.9em;margin:0 0 8px;display:flex;gap:12px;flex-wrap:wrap}
    .lfi-evt-resume{color:#333;line-height:1.5}
    .lfi-evt-cta{display:inline-block;margin-top:10px;background:#c8102e;color:#fff;text-decoration:none;padding:8px 16px;border-radius:8px;font-weight:700}
    .lfi-evts-empty{text-align:center;color:#888;padding:24px;background:#fafafa;border-radius:12px}
    </style>';

    if (empty($events)) {
        echo '<div class="lfi-evts-empty">Aucun événement à venir pour le moment. Reviens bientôt !</div>';
    } else {
        foreach ($events as $e) {
            $ts = strtotime($e['date'] . ' ' . ($e['heure'] ?? ''));
            echo '<div class="lfi-evt">';
            echo '<div class="lfi-evt-date"><div class="j">' . esc_html(wp_date('j', $ts)) . '</div><div class="m">' . esc_html(wp_date('M', $ts)) . '</div></div>';
            echo '<div class="lfi-evt-body">';
            echo '<h3>' . esc_html($e['titre']) . '</h3>';
            echo '<div class="lfi-evt-meta">';
            echo '<span>🗓 ' . esc_html(wp_date('l j F Y', $ts)) . (!empty($e['heure']) ? ' · ' . esc_html($e['heure']) : '') . '</span>';
            if (!empty($e['lieu'])) echo '<span>📍 ' . esc_html($e['lieu']) . '</span>';
            echo '</div>';
            if (!empty($e['resume']))  echo '<div class="lfi-evt-resume">' . esc_html($e['resume']) . '</div>';
            if (!empty($e['details'])) echo '<div class="lfi-evt-resume" style="margin-top:6px;white-space:pre-line">' . esc_html($e['details']) . '</div>';
            if (!empty($e['lien']))    echo '<a class="lfi-evt-cta" href="' . esc_url($e['lien']) . '">S\'inscrire / en savoir plus</a>';
            echo '</div></div>';
        }
    }
    echo '</div>';
    return ob_get_clean();
}

/**
 * Crée automatiquement (une seule fois) la page publique « Événements »
 * avec le shortcode, SI elle n'existe pas déjà. Adresse : /evenements/.
 */
add_action('init', 'lfi_nct_ensure_evenements_page', 20);
function lfi_nct_ensure_evenements_page() {
    if (get_option('lfi_nct_evenements_page_done')) return;
    $existing = get_page_by_path('evenements');
    if (!$existing) {
        wp_insert_post([
            'post_title'   => 'Événements',
            'post_name'    => 'evenements',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[lfi_nct_evenements]',
        ]);
    }
    update_option('lfi_nct_evenements_page_done', 1, false);
}

/**
 * Affiche AUTOMATIQUEMENT les événements sur la page /evenements/ même si
 * cette page existait déjà (« Mobilisations à venir ») et ne contient pas
 * le shortcode. On AJOUTE la liste sous le contenu existant, sans le
 * modifier. Ainsi, dès que Claude pousse un événement, il apparaît.
 */
add_filter('the_content', 'lfi_nct_evenements_autoinject', 20);
function lfi_nct_evenements_autoinject($content) {
    if (is_admin() || !is_singular()) return $content;
    $post = get_post();
    if (!is_a($post, 'WP_Post') || $post->post_name !== 'evenements') return $content;
    /* Si la page contient déjà le shortcode, ne pas dupliquer. */
    if (has_shortcode($post->post_content, 'lfi_nct_evenements')) return $content;
    return $content . lfi_nct_evenements_shortcode([]);
}

/**
 * SYNCHRONISATION des événements du fichier vers le CPT du thème
 * (ag_evenement) → ils apparaissent nativement sur la page /evenements/.
 *
 * Idempotent : ne s'exécute que quand le fichier change (hash). Crée /
 * met à jour un post ag_evenement par événement (clé stable), et met à la
 * corbeille ceux qu'on a créés mais qui ne sont plus dans le fichier.
 * Ne touche JAMAIS les événements créés à la main (sans notre marqueur).
 */
add_action('init', 'lfi_nct_sync_content_events', 25);
function lfi_nct_sync_content_events() {
    $cpt = post_type_exists('ag_evenement') ? 'ag_evenement'
         : (post_type_exists('lfi_evenement') ? 'lfi_evenement' : null);
    if (!$cpt) return;

    $events = lfi_nct_content_events();
    $hash = md5(wp_json_encode($events) . '|' . $cpt . '|v1');
    if (get_option('lfi_nct_events_sync_hash') === $hash) return;

    $seen = [];
    foreach ($events as $e) {
        $titre = trim($e['titre'] ?? '');
        $date  = trim($e['date'] ?? '');
        if ($titre === '' || $date === '') continue;
        $key = substr(md5($titre . '|' . $date), 0, 12);
        $seen[] = $key;

        $found = get_posts([
            'post_type' => $cpt, 'post_status' => 'any', 'numberposts' => 1,
            'fields' => 'ids', 'meta_key' => '_lfi_content_event_key', 'meta_value' => $key,
        ]);

        $arr = [
            'post_type'     => $cpt,
            'post_status'   => 'publish',
            'post_title'    => $titre,
            'post_content'  => lfi_nct_content_event_html($e),
            'post_excerpt'  => mb_substr((string) ($e['resume'] ?? ''), 0, 200),
            'post_author'   => 1,
            'comment_status'=> 'closed',
            'ping_status'   => 'closed',
        ];
        if (!empty($found)) { $arr['ID'] = (int) $found[0]; $pid = wp_update_post($arr, true); }
        else                { $pid = wp_insert_post($arr, true); }
        if (is_wp_error($pid) || !$pid) continue;

        update_post_meta($pid, '_lfi_content_event_key', $key);
        update_post_meta($pid, '_lfi_content_event', 1);
        update_post_meta($pid, '_lfi_evt_internal', 1);
        update_post_meta($pid, '_ag_event_date', $date);
        if (!empty($e['heure'])) update_post_meta($pid, '_ag_event_time', $e['heure']);
        else delete_post_meta($pid, '_ag_event_time');
        if (!empty($e['lieu'])) {
            update_post_meta($pid, '_ag_event_place', $e['lieu']);
            update_post_meta($pid, '_ag_event_address', $e['lieu']);
        }
    }

    /* Corbeille les événements QUE NOUS avons créés et qui ont disparu du fichier. */
    $ours = get_posts([
        'post_type' => $cpt, 'post_status' => 'any', 'numberposts' => 200,
        'fields' => 'ids', 'meta_key' => '_lfi_content_event', 'meta_value' => 1,
    ]);
    foreach ($ours as $oid) {
        $k = get_post_meta($oid, '_lfi_content_event_key', true);
        if (!in_array($k, $seen, true)) wp_trash_post($oid);
    }

    update_option('lfi_nct_events_sync_hash', $hash, false);
}

/** Corps HTML (Gutenberg-friendly) d'un événement du fichier. */
function lfi_nct_content_event_html($e) {
    $ts = strtotime(($e['date'] ?? '') . ' ' . ($e['heure'] ?? ''));
    ob_start();
    if (!empty($e['resume'])) echo '<p><strong>' . esc_html($e['resume']) . '</strong></p>' . "\n";
    echo '<h3>📅 Quand &amp; où ?</h3>' . "\n<ul>\n";
    echo '  <li><strong>' . esc_html(wp_date('l j F Y', $ts)) . (!empty($e['heure']) ? ' · ' . esc_html($e['heure']) : '') . '</strong></li>' . "\n";
    if (!empty($e['lieu'])) echo '  <li>📍 ' . esc_html($e['lieu']) . '</li>' . "\n";
    echo "</ul>\n";
    if (!empty($e['details'])) echo '<p>' . nl2br(esc_html($e['details'])) . '</p>' . "\n";
    if (!empty($e['lien']))    echo '<p><a href="' . esc_url($e['lien']) . '">S\'inscrire / en savoir plus</a></p>' . "\n";
    return ob_get_clean();
}

/* ============================================================== *
 *  ANALYSES D'EMAILS NMH (gérées par le code)                     *
 * ============================================================== */

/** Toutes les analyses NMH déclarées dans content/analyses-nmh.php */
function lfi_nct_content_nmh() {
    return lfi_nct_content_load('analyses-nmh.php');
}

/**
 * Renvoie l'analyse NMH gérée par le code rattachée à un dossier (par
 * dossier_id), ou null. Permet au document « Discussion + analyse » de
 * lire un contenu que Claude a écrit dans le dépôt plutôt que la base.
 */
function lfi_nct_content_nmh_for_dossier($dossier_id) {
    $dossier_id = (int) $dossier_id;
    if (!$dossier_id) return null;
    foreach (lfi_nct_content_nmh() as $slug => $entry) {
        if (!is_array($entry)) continue;
        if ((int) ($entry['dossier_id'] ?? 0) === $dossier_id) {
            $entry['slug'] = is_string($slug) ? $slug : '';
            return $entry;
        }
    }
    return null;
}

/* ============================================================== *
 *  DOSSIERS LOCATAIRES (fiche maître par locataire, gérée code)   *
 * ============================================================== */

/** Charge la fiche maître d'un locataire : content/dossiers/{slug}.php */
function lfi_nct_content_dossier($slug) {
    $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $slug));
    if ($slug === '') return [];
    return lfi_nct_content_load('dossiers/' . $slug . '.php');
}

/** Liste des fiches dossiers disponibles (slugs). */
function lfi_nct_content_dossiers_list() {
    $dir = lfi_nct_content_dir() . 'dossiers/';
    if (!is_dir($dir)) return [];
    $out = [];
    foreach ((array) glob($dir . '*.php') as $f) {
        $out[] = basename($f, '.php');
    }
    sort($out);
    return $out;
}

/** Vue imprimable : synthèse complète d'un dossier locataire (depuis le fichier). */
function lfi_nct_app_view_dossier_synthese() {
    if (function_exists('lfi_nct_app_guard_brigade') && !lfi_nct_app_guard_brigade()) return;
    $slug = isset($_GET['slug']) ? sanitize_key($_GET['slug']) : '';
    $d = $slug ? lfi_nct_content_dossier($slug) : [];

    if (empty($d)) {
        lfi_nct_app_screen_open('📂 Dossier locataire');
        echo '<div class="lfi-app-help">Choisis un dossier locataire géré par le code :</div><ul class="lfi-app-list">';
        foreach (lfi_nct_content_dossiers_list() as $s) {
            echo '<li class="lfi-app-card"><div class="head"><div class="who">📂 ' . esc_html(ucfirst($s)) . '</div></div>';
            echo '<div class="row-actions"><a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dossier-synthese', ['slug' => $s])) . '">Ouvrir la fiche</a></div></li>';
        }
        echo '</ul>';
        lfi_nct_app_screen_close();
        return;
    }

    $nom = trim(($d['civilite'] ?? '') . ' ' . ($d['prenom'] ?? '') . ' ' . ($d['nom'] ?? ''));
    lfi_nct_app_screen_open('📂 Dossier — ' . $nom, 'Fiche de synthèse (gérée par le code)');
    if (function_exists('lfi_nct_rec_doc_styles')) lfi_nct_rec_doc_styles();

    echo '<div class="lfi-rec-doc">';
    if (!empty($d['confidentiel'])) {
        echo '<p style="background:#7a0000;color:#fff;font-weight:700;text-align:center;padding:5px;border-radius:4px;font-size:.85em">DOCUMENT CONFIDENTIEL — réservé à l\'avocat · Ne pas communiquer à Nantes Métropole Habitat</p>';
    }
    echo '<h1>Dossier de synthèse — ' . esc_html($nom) . '</h1>';
    if (!empty($d['rdv'])) echo '<p style="text-align:center">Rendez-vous : ' . esc_html($d['rdv']) . '</p>';

    /* 1. Identité */
    echo '<h2>1. Identité et logement</h2><table class="detail">';
    $logement = trim(($d['adresse'] ?? '') . ($d['etage'] ? ' — étage ' . $d['etage'] : '') . ($d['appartement'] ? ', appt ' . $d['appartement'] : ''));
    echo '<tr><td><strong>Locataire</strong></td><td>' . esc_html($nom ?: '—') . '</td></tr>';
    echo '<tr><td><strong>Logement</strong></td><td>' . esc_html($logement) . '</td></tr>';
    if (!empty($d['anciennete']))       echo '<tr><td><strong>Ancienneté</strong></td><td>' . esc_html($d['anciennete']) . '</td></tr>';
    if (!empty($d['bailleur']))         echo '<tr><td><strong>Bailleur</strong></td><td>' . esc_html($d['bailleur']) . '</td></tr>';
    if (!empty($d['bailleur_contact'])) echo '<tr><td><strong>Contact bailleur</strong></td><td>' . esc_html($d['bailleur_contact']) . '</td></tr>';
    if (!empty($d['medical']))          echo '<tr><td><strong>Élément médical</strong></td><td>' . esc_html($d['medical']) . '</td></tr>';
    echo '</table>';

    /* 2. Objectif */
    if (!empty($d['objectif_locataire']) || !empty($d['objectifs_ga'])) {
        echo '<h2>2. L\'objectif — ce que demande la locataire</h2>';
        echo '<div class="citations" style="border-left-color:#0066a3">';
        if (!empty($d['objectif_locataire'])) echo '<p><strong>' . esc_html($d['objectif_locataire']) . '</strong></p>';
        if (!empty($d['objectifs_ga'])) {
            echo '<p>Dans le cadre de l\'accompagnement par l\'association Union des Quartiers Libres, points que nous soumettons à votre appréciation pour ce dossier :</p><ul>';
            foreach ($d['objectifs_ga'] as $o) echo '<li>' . esc_html($o) . '</li>';
            echo '</ul>';
        }
        echo '</div>';
    }

    /* 3. La conversation (les emails D'ABORD) */
    if (!empty($d['email_envoye']) || !empty($d['email_recu'])) {
        echo '<h2>3. La conversation avec NMH</h2>';
        if (!empty($d['email_envoye'])) {
            $e = $d['email_envoye'];
            echo '<h3>Notre email envoyé</h3>';
            if (!empty($e['objet'])) echo '<p><strong>Objet :</strong> ' . esc_html($e['objet']) . '</p>';
            echo '<div class="citations">' . nl2br(esc_html($e['corps'] ?? '')) . '</div>';
        }
        if (!empty($d['email_recu'])) {
            $r = $d['email_recu'];
            echo '<h3>La réponse de NMH' . (!empty($r['de']) ? ' (' . esc_html($r['de']) . ')' : '') . '</h3>';
            echo '<div class="citations">' . nl2br(esc_html($r['corps'] ?? '')) . '</div>';
        }
    }

    /* 4. L'analyse (APRÈS les emails) */
    if (!empty($d['desordres'])) {
        echo '<h2>4. Notre analyse — désordres et position de NMH</h2>';
        echo '<table class="detail"><tr><td><strong>Désordre</strong></td><td><strong>Position de NMH</strong></td><td><strong>Notre observation</strong></td></tr>';
        foreach ($d['desordres'] as $dz) {
            echo '<tr><td>' . esc_html($dz['nom'] ?? '') . '</td><td><em>' . esc_html($dz['nmh'] ?? '') . '</em></td><td>' . esc_html($dz['obs'] ?? '') . '</td></tr>';
        }
        echo '</table>';
    }

    /* 5. Enquête */
    if (!empty($d['enquete'])) {
        $q = $d['enquete'];
        echo '<h2>5. Une situation qui n\'est pas isolée — enquête de voisinage (interne)</h2>';
        echo '<p>' . (int) ($q['reponses'] ?? 0) . ' réponses · ' . esc_html($q['logements'] ?? '') . ' · gravité ' . esc_html($q['gravite'] ?? '') . '.</p>';
        if (!empty($q['eau_chaude'])) echo '<p><strong>Eau chaude :</strong> ' . esc_html($q['eau_chaude']) . '</p>';
        if (!empty($q['problemes'])) {
            echo '<table class="detail"><tr><td><strong>Problème recensé</strong></td><td><strong>Ménages</strong></td><td><strong>Part</strong></td></tr>';
            foreach ($q['problemes'] as $p) echo '<tr><td>' . esc_html($p['type'] ?? '') . '</td><td>' . esc_html($p['n'] ?? '') . '</td><td>' . esc_html($p['pct'] ?? '') . '</td></tr>';
            echo '</table>';
        }
        if (!empty($q['immeuble'])) echo '<p class="small">' . esc_html($q['immeuble']) . '</p>';
    }

    /* 6. Pièces */
    if (!empty($d['pieces'])) {
        echo '<h2>6. Pièces disponibles</h2><ul>';
        foreach ($d['pieces'] as $p) echo '<li>' . esc_html($p) . '</li>';
        echo '</ul>';
    }

    echo '<div class="pj">Fiche établie par l\'<strong>association Union des Quartiers Libres</strong> (accompagnement des locataires)' . (!empty($d['maj']) ? ' — mise à jour le ' . esc_html($d['maj']) : '') . '. La représentation et la stratégie juridique relèvent de l\'avocat.</div>';
    echo '</div>';
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  STRATÉGIE AVOCATS (note générale, gérée par le code)           *
 * ============================================================== */
function lfi_nct_content_strategie_avocats() {
    return lfi_nct_content_load('strategie-avocats.php');
}

/** Document imprimable « ⚖️ Stratégie avocats ». */
function lfi_nct_app_view_doc_strategie_avocats() {
    if (function_exists('lfi_nct_app_guard_brigade') && !lfi_nct_app_guard_brigade()) return;
    $data = lfi_nct_content_strategie_avocats();
    lfi_nct_app_screen_open('⚖️ Stratégie avocats', 'Note générale à envoyer aux cabinets');
    if (function_exists('lfi_nct_rec_doc_styles')) lfi_nct_rec_doc_styles();

    echo '<div class="lfi-rec-doc">';
    echo '<h1>' . esc_html($data['titre'] ?? 'Stratégie — défense des locataires du Clos Toreau') . '</h1>';
    if (!empty($data['destinataires'])) echo '<p style="text-align:center"><strong>' . esc_html($data['destinataires']) . '</strong></p>';
    if (!empty($data['intro'])) echo '<p>' . nl2br(esc_html($data['intro'])) . '</p>';
    foreach (($data['sections'] ?? []) as $s) {
        if (!is_array($s)) continue;
        echo '<h2>' . esc_html($s['titre'] ?? '') . '</h2>';
        echo '<div class="citations">' . nl2br(esc_html($s['corps'] ?? '')) . '</div>';
    }
    echo '<p style="margin-top:20px">Fait à Nantes, le ' . esc_html(wp_date('j F Y')) . '.</p>';
    echo '</div>';
    lfi_nct_app_screen_close(false);
}
