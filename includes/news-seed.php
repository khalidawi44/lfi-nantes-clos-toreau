<?php
/**
 * Module Actualités — purge des démos AG Starter + seed des vrais articles
 *
 * Le thème AG Starter Association injecte par défaut 3-6 articles
 * de démo dans wp_posts (« Hôpital public », « Pétition climat »,
 * « Nouveau groupe à Saint-Étienne », « démarré ses permanences »,
 * « Témoignages habitat », « Encadrement des loyers ») qui n'ont
 * pas grand rapport avec le Groupe d'Action Clos Toreau.
 *
 * Ce module :
 *   1. Purge à chaque init les articles dont le titre matche ces
 *      patterns démos (mais préserve ceux qui ont le marqueur LFI)
 *   2. Seed 5 vrais articles basés sur l'activité réelle du GA :
 *      enquête de voisinage, réunion 26 juin, eau chaude, permanences,
 *      porte-à-porte. Ne s'insère qu'une fois (flag).
 *   3. Auto-crée un article « Save the date » à chaque nouvel
 *      événement publié dans ag_evenement (avec marqueur LFI)
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_NEWS_SEED_FLAG = 'lfi_nct_news_seed_v2';

/* ------------------------------------------------------------------ */
/* 0bis. Strip côté navigateur (JS) en filet de sécurité                */
/* Si le buffer serveur a été bypassé par un autre plugin, ce JS         */
/* nettoie les cards démos directement dans le DOM.                     */
/* ------------------------------------------------------------------ */

add_action('wp_footer', 'lfi_nct_actu_strip_js', 999);
function lfi_nct_actu_strip_js() {
    if (is_admin()) return;
    ?>
    <script id="lfi-nct-actu-strip">
    (function () {
        var demoNeedles = [
            'Marche pour la justice climatique',
            'Hôpital public',
            'hôpital public',
            'Pétition climat',
            'pétition climat',
            'Nouveau groupe local',
            'nouveau groupe local',
            'Encadrement des loyers',
            'encadrement des loyers',
            'Témoignages habitat',
            'TÉMOIGNAGES HABITAT',
            'témoignages habitat',
            'a démarré ses permanences',
            'A DÉMARRÉ SES PERMANENCES',
            'recueilli ce trimestre',
            'RECUEILLI CE TRIMESTRE',
            'relance Nantes Métropole',
            'RELANCE NANTES MÉTROPOLE',
            // Démos supplémentaires détectés
            'Hello world',
            'hello world',
            'HELLO WORLD',
            'Welcome to WordPress',
            'NOS 12 PROPOSITIONS',
            'Nos 12 propositions',
            '12 propositions pour 2027',
            'Encadrement réel des loyers',
            'AG 2026',
            'CE QUI A ÉTÉ VOTÉ',
            'Ce qui a été voté',
            'Bilan financier, élection du CA',
            'programme stratégique 2027',
        ];

        function isDemo(text) {
            if (!text) return false;
            text = text.trim();
            if (!text.length) return false;
            for (var i = 0; i < demoNeedles.length; i++) {
                if (text.indexOf(demoNeedles[i]) !== -1) return true;
            }
            return false;
        }

        function strip() {
            // Sélecteurs des cards d'articles du thème AG Starter
            var selectors = [
                'article.ag-asso-actu',
                '.ag-asso-actu',
                '.ag-asso-actu-grid > article',
                '.ag-asso-actu-grid > a',
                '.ag-asso-actu-grid > div',
            ];
            for (var s = 0; s < selectors.length; s++) {
                var nodes = document.querySelectorAll(selectors[s]);
                for (var i = 0; i < nodes.length; i++) {
                    var n = nodes[i];
                    if (isDemo(n.textContent)) {
                        n.style.display = 'none';
                        if (n.parentNode) {
                            try { n.parentNode.removeChild(n); } catch (e) {}
                        }
                    }
                }
            }
            // Catch-all : tout article contenant le texte démo
            var allArticles = document.querySelectorAll('article, .card, [class*="actu"], [class*="news"], [class*="post"]');
            for (var j = 0; j < allArticles.length; j++) {
                var a = allArticles[j];
                if (a.children.length > 50) continue;
                if (isDemo(a.textContent) && a.textContent.length < 800) {
                    a.style.display = 'none';
                    if (a.parentNode) {
                        try { a.parentNode.removeChild(a); } catch (e) {}
                    }
                }
            }
        }

        strip();
        document.addEventListener('DOMContentLoaded', strip);
        setTimeout(strip,  100);
        setTimeout(strip,  500);
        setTimeout(strip, 1500);
        setTimeout(strip, 3000);

        // Observe les ajouts dynamiques (au cas où les cards seraient ajoutées par JS du thème)
        if (typeof MutationObserver !== 'undefined' && document.body) {
            var obs = new MutationObserver(strip);
            obs.observe(document.body, { childList: true, subtree: true });
            setTimeout(function () { try { obs.disconnect(); } catch (e) {} }, 10000);
        }
    })();
    </script>
    <?php
}

/* ------------------------------------------------------------------ */
/* 0. Output buffer : arrache les cards démos du HTML /actu/            */
/* ------------------------------------------------------------------ */

add_action('template_redirect', 'lfi_nct_actu_buffer_start', 0);
function lfi_nct_actu_buffer_start() {
    if (is_admin()) return;
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    // On bufferise sur toutes les pages publiques. Le strip ne s'active
    // que si du texte démo est détecté (fast-path sinon).
    ob_start('lfi_nct_actu_strip_demos_html');
}

function lfi_nct_actu_strip_demos_html($html) {
    if (!$html || strlen($html) < 100) return $html;

    $needles = [
        'Hôpital public',
        'hôpital public',
        'Pétition climat',
        'pétition climat',
        'Nouveau groupe local à Saint',
        'nouveau groupe local à Saint',
        'Encadrement des loyers : on relance',
        'encadrement des loyers : on relance',
        'Témoignages habitat : ce qu',
        'TÉMOIGNAGES HABITAT',
        'groupe LFI Clos Toreau a démarré',
        'LFI Clos Toreau a démarré',
        'a démarré ses permanences',
        'A DÉMARRÉ SES PERMANENCES',
        'recueilli ce trimestre',
        'RECUEILLI CE TRIMESTRE',
        'relance Nantes Métropole',
        'RELANCE NANTES MÉTROPOLE',
    ];
    $found = false;
    foreach ($needles as $n) {
        if (stripos($html, $n) !== false) { $found = true; break; }
    }
    if (!$found) return $html;

    // Patterns d'arrachage : article.ag-asso-actu contenant le mot-clé
    foreach ($needles as $kw) {
        $kw_re = preg_quote($kw, '/');
        $patterns = [
            '/<article[^>]*class\s*=\s*"[^"]*ag-asso-actu[^"]*"[^>]*>(?:(?!<\/article>)[\s\S]){0,6000}?' . $kw_re . '(?:(?!<\/article>)[\s\S]){0,6000}?<\/article>/i',
            '/<article\b[^>]*>(?:(?!<\/article>)[\s\S]){0,6000}?' . $kw_re . '(?:(?!<\/article>)[\s\S]){0,6000}?<\/article>/i',
            '/<div[^>]*class\s*=\s*"[^"]*ag-asso-actu[^"]*"[^>]*>(?:(?!<\/div>)[\s\S]){0,6000}?' . $kw_re . '(?:(?!<\/div>)[\s\S]){0,6000}?<\/div>/i',
            '/<a[^>]*class\s*=\s*"[^"]*ag-asso-actu[^"]*"[^>]*>(?:(?!<\/a>)[\s\S]){0,6000}?' . $kw_re . '(?:(?!<\/a>)[\s\S]){0,6000}?<\/a>/i',
        ];
        foreach ($patterns as $pat) {
            $new = @preg_replace($pat, '', $html);
            if ($new !== null && $new !== $html) $html = $new;
        }
    }
    return $html;
}

/* ------------------------------------------------------------------ */
/* 1. Purge des articles démo (à chaque init, idempotent)              */
/* ------------------------------------------------------------------ */

add_action('init', 'lfi_nct_purge_news_demos', 1100);
function lfi_nct_purge_news_demos() {
    global $wpdb;
    $patterns = [
        '%H_pital%public%contre%budget%',
        '%P_tition%climat%signatures%',
        '%nouveau%groupe%saint%tienne%',
        '%Encadrement%loyers%Nantes%M_tropole%',
        '%T_moignages%habitat%trimestre%',
        '%groupe%LFI%Clos%Toreau%d_marr_%permanences%',
        // Démos sample WordPress + AG Starter additionnels
        'Hello world%',
        'Hello, world%',
        '%12 propositions pour 2027%',
        '%Encadrement r_el des loyers%',
        '%AG 2026%CE QUI%VOT_%',
        '%AG 2026%vot_%',
        '%Bilan financier%lection du CA%',
        '%programme strat_gique 2027%',
    ];
    foreach ($patterns as $pat) {
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'post'
               AND post_status IN ('publish','draft','pending','private','future','trash')
               AND post_title LIKE %s
             LIMIT 20",
            $pat
        ));
        foreach ($posts as $p) {
            // On préserve les articles marqués LFI
            if (get_post_meta($p->ID, '_lfi_news_origin', true)) continue;
            wp_delete_post($p->ID, true);
        }
    }
}

/* ------------------------------------------------------------------ */
/* 2. Seed des vrais articles (idempotent)                              */
/* ------------------------------------------------------------------ */

add_action('init', 'lfi_nct_seed_real_news', 1200);
function lfi_nct_seed_real_news() {
    if (get_option(LFI_NCT_NEWS_SEED_FLAG) === 'done') return;
    global $wpdb;

    // Bannière SVG colorée + emoji thématique. C'est un data:URI auto-suffisant,
    // pas besoin d'uploader une image — fonctionne dans n'importe quel navigateur.
    $img = function ($emoji, $color1, $color2, $caption) {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 400"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $color1 . '"/><stop offset="1" stop-color="' . $color2 . '"/></linearGradient></defs><rect width="800" height="400" fill="url(#g)"/><text x="400" y="180" text-anchor="middle" font-size="120" fill="white">' . $emoji . '</text><text x="400" y="290" text-anchor="middle" font-family="Arial,sans-serif" font-size="32" font-weight="700" fill="white" letter-spacing="2">' . htmlspecialchars(strtoupper($caption), ENT_QUOTES, 'UTF-8') . '</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    };

    $news = [
        [
            'slug'    => 'enquete-voisinage-insalubrite-clos-toreau',
            'title'   => 'Notre enquête de voisinage sur l\'insalubrité au Clos Toreau',
            'excerpt' => 'Depuis plusieurs mois, le Groupe d\'Action mène une enquête de voisinage porte-à-porte sur l\'état du logement social au Clos Toreau. Premiers résultats : 100% des immeubles touchés par les coupures d\'eau chaude récurrentes.',
            'image'   => $img('🏠', '#c8102e', '#7a0000', 'Enquête logement'),
            'content' => '',
            'days_ago' => 2,
        ],
        [
            'slug'    => 'reunion-publique-26-juin-votre-logement-votre-droit',
            'title'   => 'Réunion publique du 26 juin : Votre logement, votre droit',
            'excerpt' => 'Le vendredi 26 juin de 15h à 17h à la Salle de Diffusion (Confluences, 4 place du Muguet), on présente les résultats de l\'enquête et on s\'organise pour la suite.',
            'image'   => $img('📅', '#1a7f37', '#0d5020', 'Réunion 26 juin'),
            'content' => '',
            'days_ago' => 5,
        ],
        [
            'slug'    => 'eau-chaude-coupures-repetition-clos-toreau',
            'title'   => 'Coupures d\'eau chaude : ce qu\'on a découvert dans les immeubles du quartier',
            'excerpt' => 'Plus de 10 coupures par an, plus de 10 jours cumulés sans eau chaude, durées allant de 2 jours à 3 semaines consécutives. Toutes les enquêtées concernées.',
            'image'   => $img('💧', '#0088cc', '#005a8a', 'Eau chaude'),
            'content' => '',
            'days_ago' => 9,
        ],
        [
            'slug'    => 'permanences-logement-clos-toreau',
            'title'   => 'Permanences habitat : on vous accompagne dans vos démarches',
            'excerpt' => 'Le Groupe d\'Action tient des permanences d\'accompagnement administratif et juridique pour les habitant·es du quartier. Premières permanences déjà tenues.',
            'image'   => $img('⚖️', '#bd8600', '#7a5500', 'Permanences'),
            'content' => '',
            'days_ago' => 14,
        ],
        [
            'slug'    => 'porte-a-porte-clos-toreau',
            'title'   => 'Porte-à-porte : nos militant·es à votre rencontre',
            'excerpt' => 'Toutes les semaines, des militant·es du Groupe d\'Action font du porte-à-porte au Clos Toreau pour discuter logement, droits et combats à mener.',
            'image'   => $img('🚪', '#7a0000', '#3d0000', 'Porte-à-porte'),
            'content' => '',
            'days_ago' => 20,
        ],
    ];

    // Contenus riches (avec image inline en tête)
    $news[0]['content'] = "<!-- wp:image --><figure class=\"wp-block-image size-large\"><img src=\"" . $news[0]['image'] . "\" alt=\"Enquête logement\"/></figure><!-- /wp:image -->\n\n<!-- wp:paragraph --><p>Depuis l'automne, les militant·es du Groupe d'Action LFI Nantes Sud Clos Toreau frappent aux portes des immeubles du quartier pour recueillir la parole des habitant·es sur leurs conditions de logement.</p><!-- /wp:paragraph -->\n\n<!-- wp:heading --><h2>Ce qu'on a déjà constaté</h2><!-- /wp:heading -->\n\n<!-- wp:list --><ul><li><strong>100% des immeubles enquêtés</strong> subissent des coupures d'eau chaude récurrentes (+ de 10 par an, + de 10 jours cumulés)</li><li>Durée variant de <strong>2 jours à 3 semaines consécutives</strong> selon les immeubles</li><li>Présence massive d'<strong>humidité, moisissures, nuisibles</strong></li><li>Parties communes dégradées, ascenseurs en panne</li></ul><!-- /wp:list -->\n\n<!-- wp:paragraph --><p>Ces données sont alarmantes. Elles seront présentées en détail lors de notre réunion publique du <strong>vendredi 26 juin</strong>.</p><!-- /wp:paragraph -->\n\n<!-- wp:paragraph --><p>👉 <a href=\"/evenements/votre-logement-votre-droit-reunion-26-juin/\">Voir la réunion du 26 juin</a></p><!-- /wp:paragraph -->";

    $news[1]['content'] = "<!-- wp:image --><figure class=\"wp-block-image size-large\"><img src=\"" . $news[1]['image'] . "\" alt=\"Réunion 26 juin\"/></figure><!-- /wp:image -->\n\n<!-- wp:paragraph --><p>Nous y sommes presque. Après des mois d'enquête de voisinage, le Groupe d'Action Clos Toreau organise une <strong>grande réunion publique</strong> pour partager les résultats et passer à l'action collective.</p><!-- /wp:paragraph -->\n\n<!-- wp:heading --><h2>Au programme</h2><!-- /wp:heading -->\n\n<!-- wp:list {\"ordered\":true} --><ol><li>Résultats de l'enquête de voisinage — chiffres et témoignages</li><li>Vos droits et les recours possibles — démarches concrètes</li><li>Questions / Réponses — partagez votre situation</li></ol><!-- /wp:list -->\n\n<!-- wp:paragraph --><p>📅 Vendredi 26 juin 2026, 15h-17h<br>📍 Salle de Diffusion – Confluences, 4 place du Muguet, Nantes<br>👉 Entrée libre, pas besoin de s'inscrire (mais ça nous aide à prévoir les chaises !)</p><!-- /wp:paragraph -->\n\n<!-- wp:paragraph --><p>👉 <a href=\"/evenements/votre-logement-votre-droit-reunion-26-juin/\">Voir l'événement et confirmer ta venue</a></p><!-- /wp:paragraph -->";

    $news[2]['content'] = "<!-- wp:image --><figure class=\"wp-block-image size-large\"><img src=\"" . $news[2]['image'] . "\" alt=\"Coupures d'eau chaude\"/></figure><!-- /wp:image -->\n\n<!-- wp:paragraph --><p>C'est probablement le résultat le plus marquant de notre enquête de voisinage : <strong>100% des locataires interrogé·es subissent des coupures d'eau chaude récurrentes</strong>. Aucune exception.</p><!-- /wp:paragraph -->\n\n<!-- wp:heading --><h2>Chiffres clés</h2><!-- /wp:heading -->\n\n<!-- wp:list --><ul><li>Plus de <strong>10 coupures par an</strong> en moyenne</li><li>Plus de <strong>10 jours cumulés</strong> sans eau chaude</li><li>Durée d'une coupure variant de <strong>2 jours</strong> à <strong>3 semaines consécutives</strong> selon les immeubles</li><li>Phénomène présent depuis <strong>plus de 5 ans</strong> sans amélioration significative</li></ul><!-- /wp:list -->\n\n<!-- wp:paragraph --><p>Certains habitant·es n'évoquent même plus ces coupures comme un problème — ils ou elles ont fini par s'y habituer. C'est précisément cette banalisation que nous voulons casser : pas d'eau chaude pendant 3 semaines, ce n'est pas « comme ça », c'est une <strong>défaillance grave du bailleur</strong> qui doit être traitée.</p><!-- /wp:paragraph -->";

    $news[3]['content'] = "<!-- wp:image --><figure class=\"wp-block-image size-large\"><img src=\"" . $news[3]['image'] . "\" alt=\"Permanences\"/></figure><!-- /wp:image -->\n\n<!-- wp:paragraph --><p>Vous avez un problème de logement avec votre bailleur ? Une demande de logement social qui n'avance pas ? Une APL qui a été suspendue ? Le Groupe d'Action est là pour vous accompagner.</p><!-- /wp:paragraph -->\n\n<!-- wp:heading --><h2>Ce qu'on fait pendant la permanence</h2><!-- /wp:heading -->\n\n<!-- wp:list --><ul><li>On vous aide à rédiger des courriers (mise en demeure, recours)</li><li>On vous oriente vers les bons interlocuteurs (CAF, conciliation, ADIL, justice)</li><li>On peut vous accompagner physiquement dans les démarches</li><li>On vous explique vos droits clairement, sans jargon</li></ul><!-- /wp:list -->\n\n<!-- wp:paragraph --><p>👉 <a href=\"/rendez-vous/\">Prendre rendez-vous</a> ou <a href=\"/enquete-logement/\">remplir le formulaire d'enquête logement</a></p><!-- /wp:paragraph -->";

    $news[4]['content'] = "<!-- wp:image --><figure class=\"wp-block-image size-large\"><img src=\"" . $news[4]['image'] . "\" alt=\"Porte-à-porte\"/></figure><!-- /wp:image -->\n\n<!-- wp:paragraph --><p>Notre travail commence à votre porte. Régulièrement, des militant·es du Groupe d'Action sillonnent les immeubles du Clos Toreau pour échanger directement avec les habitant·es.</p><!-- /wp:paragraph -->\n\n<!-- wp:heading --><h2>Pourquoi le porte-à-porte ?</h2><!-- /wp:heading -->\n\n<!-- wp:paragraph --><p>Parce que c'est sur le pas de votre porte que se mesure vraiment la situation. Les statistiques officielles invisibilisent souvent les vrais problèmes ; en venant frapper chez vous, on entend les <strong>vrais témoignages, les vrais combats du quotidien</strong>.</p><!-- /wp:paragraph -->\n\n<!-- wp:paragraph --><p>Si vous voulez nous rejoindre pour un porte-à-porte, ou simplement nous signaler qu'on passe dans votre immeuble :</p><!-- /wp:paragraph -->\n\n<!-- wp:paragraph --><p>👉 <a href=\"/rendez-vous/\">Contactez-nous</a></p><!-- /wp:paragraph -->";

    foreach ($news as $n) {
        $existing = get_page_by_path($n['slug'], OBJECT, 'post');
        if ($existing) continue;
        $date    = date('Y-m-d H:i:s', strtotime('-' . $n['days_ago'] . ' days'));
        $gmt_date = get_gmt_from_date($date);
        $id = wp_insert_post([
            'post_type'     => 'post',
            'post_status'   => 'publish',
            'post_title'    => $n['title'],
            'post_name'     => $n['slug'],
            'post_excerpt'  => $n['excerpt'],
            'post_content'  => $n['content'],
            'post_date'     => $date,
            'post_date_gmt' => $gmt_date,
            'post_author'   => 1,
        ]);
        if (!is_wp_error($id) && $id) {
            update_post_meta($id, '_lfi_news_origin', 'seed');
            if (!empty($n['image'])) {
                update_post_meta($id, '_lfi_news_image', $n['image']);
            }
        }
    }
    update_option(LFI_NCT_NEWS_SEED_FLAG, 'done', false);
}

/* ------------------------------------------------------------------ */
/* 3. Auto-création d'un article quand un événement est publié          */
/* ------------------------------------------------------------------ */

add_action('save_post_ag_evenement', 'lfi_nct_auto_news_from_event', 30, 3);
function lfi_nct_auto_news_from_event($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if ($post->post_status !== 'publish') return;

    // Évite la création multiple : on stocke l'ID news associé
    $news_id = (int) get_post_meta($post_id, '_lfi_news_post_id', true);
    if ($news_id && get_post($news_id)) {
        // Article déjà créé, on met juste à jour
        wp_update_post([
            'ID'           => $news_id,
            'post_title'   => '📅 ' . get_the_title($post),
            'post_content' => lfi_nct_news_content_from_event($post),
            'post_excerpt' => wp_trim_words(get_the_excerpt($post), 30),
        ]);
        return;
    }

    // Création
    $event_date = get_post_meta($post_id, '_ag_event_date', true);
    $event_time = get_post_meta($post_id, '_ag_event_time', true);
    $event_lieu = get_post_meta($post_id, '_ag_event_place', true);
    $excerpt = "Le " . ($event_date ? date_i18n('j F Y', strtotime($event_date)) : '') . ($event_time ? ' à ' . $event_time : '') . ($event_lieu ? ' à ' . $event_lieu : '') . ' — viens nous rejoindre.';

    $news_id = wp_insert_post([
        'post_type'    => 'post',
        'post_status'  => 'publish',
        'post_title'   => '📅 ' . get_the_title($post),
        'post_excerpt' => $excerpt,
        'post_content' => lfi_nct_news_content_from_event($post),
        'post_author'  => $post->post_author ?: 1,
    ]);
    if (!is_wp_error($news_id) && $news_id) {
        update_post_meta($news_id,   '_lfi_news_origin',    'event');
        update_post_meta($news_id,   '_lfi_news_event_id',  $post_id);
        update_post_meta($post_id,   '_lfi_news_post_id',   $news_id);
    }
}

function lfi_nct_news_content_from_event($event_post) {
    $date  = get_post_meta($event_post->ID, '_ag_event_date',  true);
    $time  = get_post_meta($event_post->ID, '_ag_event_time',  true);
    $end   = get_post_meta($event_post->ID, '_ag_event_end',   true);
    $place = get_post_meta($event_post->ID, '_ag_event_place', true);
    $city  = get_post_meta($event_post->ID, '_ag_event_city',  true);
    $perma = get_permalink($event_post);

    $when = $date ? date_i18n('l j F Y', strtotime($date)) : '';
    if ($time) $when .= ' · ' . $time . ($end ? ' – ' . $end : '');

    $where = trim(($place ? $place : '') . ($city ? ($place ? ', ' : '') . $city : ''));

    return "<!-- wp:paragraph --><p>📅 <strong>" . esc_html($when) . "</strong></p><!-- /wp:paragraph -->\n"
         . "<!-- wp:paragraph --><p>📍 " . esc_html($where) . "</p><!-- /wp:paragraph -->\n"
         . "<!-- wp:paragraph --><p>" . wp_kses_post($event_post->post_excerpt ?: '') . "</p><!-- /wp:paragraph -->\n"
         . "<!-- wp:paragraph --><p>👉 <a href=\"" . esc_url($perma) . "\">Voir les détails et confirmer ta venue</a></p><!-- /wp:paragraph -->";
}
