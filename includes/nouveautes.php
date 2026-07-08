<?php
/**
 * NOUVEAUTÉS — pop-up « quoi de neuf » pour les MEMBRES du GA.
 *
 * À chaque nouveauté qui touche directement les membres (nouveau bouton,
 * nouvelle fonctionnalité), on ajoute une annonce. Quand un membre ouvre son
 * espace, un petit pop-up (facile à fermer) lui présente ce qu'il a en plus.
 * Vu une fois → ne réapparaît plus (mémorisé par compte).
 */
if (!defined('ABSPATH')) exit;

/** Liste des annonces (option). La plus récente en dernier. */
function lfi_nct_news_list() {
    $d = get_option('lfi_nct_member_news', []);
    return is_array($d) ? $d : [];
}

/** Ajoute une annonce. Renvoie l'id. */
function lfi_nct_news_add($titre, $corps) {
    $list = lfi_nct_news_list();
    $id = (int) round(microtime(true) * 1000);
    $list[] = [
        'id'    => $id,
        'titre' => sanitize_text_field((string) $titre),
        'corps' => wp_kses_post((string) $corps),
        'date'  => current_time('mysql'),
    ];
    /* On borne l'historique. */
    if (count($list) > 30) $list = array_slice($list, -30);
    update_option('lfi_nct_member_news', $list, false);
    return $id;
}

/** Dernière annonce, ou null. */
function lfi_nct_news_latest() {
    $list = lfi_nct_news_list();
    return $list ? end($list) : null;
}

/**
 * Seed d'annonces « quoi de neuf » côté code (idempotent par flag).
 * Chaque nouveauté qui touche les membres ajoute une entrée ici avec une clé
 * unique ; elle n'est insérée qu'une fois, même après plusieurs déploiements.
 */
add_action('init', 'lfi_nct_news_seed_builtin', 1300);
function lfi_nct_news_seed_builtin() {
    $done = get_option('lfi_nct_news_seed_keys', []);
    if (!is_array($done)) $done = [];

    $seeds = [
        'audit-nmh-2026-07' => [
            'titre' => 'Nouveau : « Où va mon loyer ? »',
            'corps' => 'Un nouvel écran <strong>💶 Où va mon loyer ?</strong> arrive dans ta console. '
                     . 'C\'est l\'argumentaire NMH avec les <strong>vrais chiffres</strong> (rapport de la Chambre régionale des comptes) : '
                     . 'sur 330 € de loyer, à peine 21 € reviennent à l\'entretien du logement. '
                     . 'Tu y trouves une <strong>phrase simple à dire au porte-à-porte</strong> — utile pour répondre aux gens sans te tromper.',
        ],
        'mobilisation-2026-07' => [
            'titre' => 'Nouveau : se coordonner facilement',
            'corps' => 'Fini le calendrier bizarre dans le vide. Il y a maintenant <strong>🤝 Se coordonner</strong> : '
                     . 'tu choisis une action rattachée à un <strong>événement</strong> (ex. tractage pour la kermesse du 14 juillet) '
                     . 'ou à une <strong>campagne</strong>, tu vois les <strong>créneaux</strong> (jour + moment) et tu cliques '
                     . '<strong>« 🙋 Je participe »</strong> sur ceux qui t\'arrangent. Tu peux aussi <strong>proposer d\'autres dates</strong>. '
                     . 'Simple, et on voit tout de suite qui vient.',
        ],
        'suggerer-outil-2026-07' => [
            'titre' => 'Nouveau : suggère un outil',
            'corps' => 'L\'app n\'est pas que le logement. Avec <strong>🧰 Suggérer un outil</strong>, si ton terrain a besoin '
                     . 'd\'autre chose (bailleurs privés, énergie, services publics…), tu le dis — et l\'administrateur peut '
                     . 'le déployer pour ton GA. C\'est toi qui es sur le terrain : c\'est toi qui sais.',
        ],
        'agenda-invite-2026-07' => [
            'titre' => 'Les événements arrivent dans ton agenda',
            'corps' => 'Quand un événement du GA est créé, tu reçois un <strong>email avec une invitation calendrier</strong>. '
                     . 'Sur Gmail / Google Agenda, il s\'ajoute <strong>en un tap</strong>, avec un <strong>rappel</strong> la veille et 2 h avant. '
                     . 'Plus besoin de le noter à la main.',
        ],
    ];

    $changed = false;
    foreach ($seeds as $key => $a) {
        if (in_array($key, $done, true)) continue;
        lfi_nct_news_add($a['titre'], $a['corps']);
        $done[] = $key;
        $changed = true;
    }
    if ($changed) update_option('lfi_nct_news_seed_keys', $done, false);
}

/** AJAX : le membre a vu l'annonce → on mémorise (ne réapparaît plus). */
add_action('wp_ajax_lfi_nct_news_seen', 'lfi_nct_news_seen_ajax');
function lfi_nct_news_seen_ajax() {
    check_ajax_referer('lfi_nct_news', 'nonce');
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id && get_current_user_id()) update_user_meta(get_current_user_id(), 'lfi_nct_news_seen_id', $id);
    wp_send_json_success();
}

/**
 * Affiche le pop-up « quoi de neuf » si le membre n'a pas encore vu la dernière
 * annonce. À appeler dans la console membre. Facile à fermer (croix / clic hors).
 */
function lfi_nct_render_member_news_popup() {
    if (!is_user_logged_in()) return;
    /* Cible : membres du GA (les admins pilotent, pas besoin). */
    $is_ga    = function_exists('lfi_nct_user_role_ga') && lfi_nct_user_role_ga();
    $is_admin = current_user_can('manage_options');
    if (!$is_ga || $is_admin) return;

    $latest = lfi_nct_news_latest();
    if (!$latest) return;
    $seen = (int) get_user_meta(get_current_user_id(), 'lfi_nct_news_seen_id', true);
    if ($seen >= (int) $latest['id']) return; /* déjà vu */

    $nonce = wp_create_nonce('lfi_nct_news');
    $ajax  = admin_url('admin-ajax.php');
    ?>
    <div id="lfi-news-ov" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px">
      <div style="background:#fff;color:#1a1a1a;border-radius:16px;max-width:420px;width:100%;padding:20px 18px;box-shadow:0 12px 40px rgba(0,0,0,.3);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
          <div style="font-weight:900;font-size:1.15em;color:#c8102e">✨ <?php echo esc_html($latest['titre']); ?></div>
          <button id="lfi-news-x" aria-label="Fermer" style="border:0;background:#eee;border-radius:50%;width:32px;height:32px;font-size:1.1em;cursor:pointer;flex:0 0 auto">✕</button>
        </div>
        <div style="margin-top:10px;line-height:1.5"><?php echo wp_kses_post($latest['corps']); ?></div>
        <div style="margin-top:16px;text-align:right">
          <button id="lfi-news-ok" style="background:#186a3b;color:#fff;border:0;font-weight:800;padding:11px 20px;border-radius:10px;cursor:pointer">👍 J'ai compris</button>
        </div>
      </div>
    </div>
    <script>
    (function(){
      var ov = document.getElementById('lfi-news-ov');
      if(!ov) return;
      function seen(){
        try {
          var fd = new FormData();
          fd.append('action','lfi_nct_news_seen');
          fd.append('nonce','<?php echo esc_js($nonce); ?>');
          fd.append('id','<?php echo (int) $latest['id']; ?>');
          fetch('<?php echo esc_url($ajax); ?>', {method:'POST', body:fd, credentials:'same-origin'}).catch(function(){});
        } catch(e){}
      }
      function close(){ seen(); ov.parentNode && ov.parentNode.removeChild(ov); }
      document.getElementById('lfi-news-x').addEventListener('click', close);
      document.getElementById('lfi-news-ok').addEventListener('click', close);
      ov.addEventListener('click', function(e){ if(e.target === ov) close(); });
    })();
    </script>
    <?php
}
