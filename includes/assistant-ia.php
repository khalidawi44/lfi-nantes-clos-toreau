<?php
/**
 * Assistant IA pour les visiteurs du site public.
 *
 * Un bouton flottant « 💬 Assistant » : le visiteur (souvent un·e
 * locataire) pose une question ; on interroge le moteur de recherche de
 * contenu de Hostinger (« Website Agents » MCP, outil `ask`) et on répond
 * avec les passages pertinents + les liens vers les bonnes pages
 * (Combats, Témoigner, Permanences, Enquête logement…).
 *
 * 100 % géré par le code : pas de réglage WordPress à faire.
 * Ne s'affiche PAS sur l'app (/app/, rendue en autonome) ni dans l'admin.
 */
if (!defined('ABSPATH')) exit;

/** URL du MCP « Website Server » de Hostinger pour ce site. */
function lfi_nct_assistant_mcp_url() {
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    $host = preg_replace('/^www\./', '', (string) $host);
    return 'https://websites-agents.hostinger.com/' . $host . '/mcp';
}

/* ============================================================== *
 *  Endpoint REST : reçoit la question, interroge le MCP, répond   *
 * ============================================================== */
add_action('rest_api_init', function () {
    register_rest_route('lfi-nct/v1', '/assistant', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'args'                => [
            'q' => ['required' => true, 'type' => 'string'],
        ],
        'callback'            => 'lfi_nct_assistant_answer',
    ]);
});

function lfi_nct_assistant_answer($request) {
    $q = trim((string) $request->get_param('q'));
    $q = wp_strip_all_tags($q);
    if (function_exists('mb_substr')) $q = mb_substr($q, 0, 300);
    if ($q === '') {
        return new WP_REST_Response(['ok' => false, 'message' => 'Pose-moi une question.'], 200);
    }

    $payload = [
        'jsonrpc' => '2.0',
        'id'      => 1,
        'method'  => 'tools/call',
        'params'  => ['name' => 'ask', 'arguments' => ['query' => $q]],
    ];

    $resp = wp_remote_post(lfi_nct_assistant_mcp_url(), [
        'timeout' => 25,
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json, text/event-stream',
        ],
        'body'    => wp_json_encode($payload),
    ]);

    if (is_wp_error($resp)) {
        return new WP_REST_Response([
            'ok' => false,
            'message' => 'Je n\'arrive pas à chercher pour le moment. Tu peux nous écrire directement via le formulaire « Témoigner / demander de l\'aide ».',
            'fallback' => home_url('/signer/'),
        ], 200);
    }

    $body = json_decode(wp_remote_retrieve_body($resp), true);
    $raw  = $body['result']['content'][0]['text'] ?? '';
    $chunks = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
    if (!is_array($chunks)) $chunks = [];

    /* Regroupe par page, garde un extrait propre. */
    $pages = [];
    foreach ($chunks as $c) {
        if (!is_array($c)) continue;
        $url = $c['page_url'] ?? '';
        if (!$url || isset($pages[$url])) continue;
        /* Saute les pages techniques / privées */
        if (preg_match('#/(app|mon-compte|wp-admin|le-don-a-echoue|confirmation-de-don)#', $url)) continue;
        $pages[$url] = [
            'url'     => $url,
            'titre'   => lfi_nct_assistant_titre($c['content'] ?? '', $url),
            'extrait' => lfi_nct_assistant_extrait($c['content'] ?? '', $q),
        ];
        if (count($pages) >= 3) break;
    }

    if (empty($pages)) {
        return new WP_REST_Response([
            'ok' => true,
            'message' => 'Je n\'ai pas trouvé de réponse précise sur le site. Le mieux : écris-nous via le formulaire « Témoigner / demander de l\'aide » et on te recontacte.',
            'fallback' => home_url('/signer/'),
            'pages' => [],
        ], 200);
    }

    return new WP_REST_Response([
        'ok' => true,
        'message' => 'Voici ce que j\'ai trouvé sur notre site :',
        'pages' => array_values($pages),
        'fallback' => home_url('/signer/'),
    ], 200);
}

/** Déduit un titre lisible à partir du contenu markdown (1er « # … »). */
function lfi_nct_assistant_titre($content, $url) {
    if (preg_match('/^#\s+(.+)$/m', (string) $content, $m)) {
        return trim($m[1]);
    }
    /* Fallback : depuis le slug de l'URL */
    $slug = trim(wp_parse_url($url, PHP_URL_PATH) ?: '', '/');
    $slug = preg_replace('/.*\//', '', $slug);
    $slug = ucfirst(str_replace('-', ' ', urldecode($slug)));
    return $slug ?: 'Voir la page';
}

/** Extrait un passage pertinent (autour des mots de la question). */
function lfi_nct_assistant_extrait($content, $q) {
    $content = (string) $content;
    /* Retire les menus de navigation (lignes « - [..](..) ») et liens d'entête */
    $content = preg_replace('/^\s*-\s*\[.*$/m', '', $content);
    $content = preg_replace('/\[[^\]]*\]\(https?:[^)]*\)/', '', $content);
    $content = preg_replace('/[#>*`]+/', '', $content);
    $content = preg_replace('/\s+/', ' ', $content);
    $content = trim($content);

    /* Cherche le 1er mot significatif de la question dans le texte */
    $pos = false;
    foreach (preg_split('/\s+/', strtolower($q)) as $w) {
        if (mb_strlen($w) < 4) continue;
        $p = mb_stripos($content, $w);
        if ($p !== false) { $pos = $p; break; }
    }
    if ($pos === false) $pos = 0;
    $start = max(0, $pos - 60);
    $extrait = mb_substr($content, $start, 240);
    if ($start > 0) $extrait = '…' . $extrait;
    if (mb_strlen($content) > $start + 240) $extrait .= '…';
    return trim($extrait);
}

/* ============================================================== *
 *  Widget front : bouton flottant + panneau (pages publiques)     *
 * ============================================================== */
add_action('wp_footer', 'lfi_nct_assistant_widget', 20);
function lfi_nct_assistant_widget() {
    if (is_admin()) return;
    /* Pas sur l'app (rendue en autonome de toute façon) ni le wp-login */
    $post = get_post();
    if (is_a($post, 'WP_Post') && $post->post_name === LFI_NCT_APP_SLUG) return;

    $endpoint = esc_url_raw(rest_url('lfi-nct/v1/assistant'));
    $signer   = esc_url(home_url('/signer/'));
    ?>
    <style>
    #lfi-ia-btn{position:fixed;right:18px;bottom:18px;z-index:99998;background:#c8102e;color:#fff;border:0;border-radius:30px;padding:12px 18px;font-weight:800;font-size:1em;box-shadow:0 6px 20px rgba(200,16,46,.4);cursor:pointer;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
    #lfi-ia-panel{position:fixed;right:18px;bottom:74px;z-index:99999;width:min(380px,calc(100vw - 36px));max-height:min(70vh,560px);background:#fff;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.25);display:none;flex-direction:column;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
    #lfi-ia-panel.open{display:flex}
    .lfi-ia-head{background:#c8102e;color:#fff;padding:14px 16px;font-weight:800;display:flex;justify-content:space-between;align-items:center}
    .lfi-ia-head button{background:transparent;border:0;color:#fff;font-size:1.3em;cursor:pointer;line-height:1}
    .lfi-ia-msgs{flex:1;overflow-y:auto;padding:14px;background:#f7f7f8}
    .lfi-ia-msg{margin:0 0 10px;line-height:1.45;font-size:.95em}
    .lfi-ia-msg.bot{background:#fff;border:1px solid #eee;border-radius:12px;padding:10px 12px;color:#1a1a1a}
    .lfi-ia-msg.me{background:#c8102e;color:#fff;border-radius:12px;padding:8px 12px;margin-left:auto;max-width:85%;width:fit-content}
    .lfi-ia-card{display:block;background:#fff;border:1px solid #eee;border-left:4px solid #c8102e;border-radius:10px;padding:10px 12px;margin-top:8px;text-decoration:none;color:#1a1a1a}
    .lfi-ia-card b{color:#c8102e;display:block;margin-bottom:3px}
    .lfi-ia-card span{font-size:.86em;color:#555}
    .lfi-ia-foot{display:flex;gap:6px;padding:10px;border-top:1px solid #eee;background:#fff}
    .lfi-ia-foot input{flex:1;border:1.5px solid #ddd;border-radius:10px;padding:11px 12px;font-size:1em}
    .lfi-ia-foot button{background:#c8102e;color:#fff;border:0;border-radius:10px;padding:0 16px;font-weight:800;cursor:pointer}
    .lfi-ia-hint{font-size:.8em;color:#888;padding:0 14px 8px;background:#f7f7f8}
    </style>

    <button id="lfi-ia-btn" type="button" aria-label="Ouvrir l'assistant">💬 Assistant</button>
    <div id="lfi-ia-panel" role="dialog" aria-label="Assistant du Groupe d'Action">
        <div class="lfi-ia-head"><span>💬 Assistant du Groupe d'Action</span><button id="lfi-ia-close" aria-label="Fermer">×</button></div>
        <div class="lfi-ia-msgs" id="lfi-ia-msgs">
            <div class="lfi-ia-msg bot">Bonjour 👋 Je peux t'aider sur le <strong>logement</strong>, tes <strong>droits</strong> et nos <strong>permanences</strong>. Pose ta question (ex : « moisissures dans mon logement », « comment vous contacter ? »).</div>
        </div>
        <div class="lfi-ia-hint">Réponses tirées de notre site. Pour un cas personnel : <a href="<?php echo $signer; ?>">Témoigner / demander de l'aide</a>.</div>
        <div class="lfi-ia-foot">
            <input id="lfi-ia-input" type="text" placeholder="Ta question…" autocomplete="off">
            <button id="lfi-ia-send" type="button">→</button>
        </div>
    </div>

    <script>
    (function(){
        var ENDPOINT = <?php echo wp_json_encode($endpoint); ?>;
        var FALLBACK = <?php echo wp_json_encode($signer); ?>;
        var btn=document.getElementById('lfi-ia-btn'), panel=document.getElementById('lfi-ia-panel'),
            close=document.getElementById('lfi-ia-close'), msgs=document.getElementById('lfi-ia-msgs'),
            input=document.getElementById('lfi-ia-input'), send=document.getElementById('lfi-ia-send'), busy=false;
        function esc(s){var d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML;}
        function add(html,cls){var d=document.createElement('div');d.className='lfi-ia-msg '+cls;d.innerHTML=html;msgs.appendChild(d);msgs.scrollTop=msgs.scrollHeight;return d;}
        function open(){panel.classList.add('open');setTimeout(function(){input.focus();},80);}
        btn.addEventListener('click',function(){panel.classList.contains('open')?panel.classList.remove('open'):open();});
        close.addEventListener('click',function(){panel.classList.remove('open');});
        function ask(){
            var q=input.value.trim(); if(!q||busy) return;
            add(esc(q),'me'); input.value=''; busy=true;
            var loading=add('…','bot');
            fetch(ENDPOINT,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({q:q})})
            .then(function(r){return r.json();})
            .then(function(d){
                loading.remove(); busy=false;
                var html=esc(d.message||'Voici ce que j\'ai trouvé :');
                if(d.pages&&d.pages.length){
                    d.pages.forEach(function(p){
                        html+='<a class="lfi-ia-card" href="'+esc(p.url)+'"><b>'+esc(p.titre)+'</b><span>'+esc(p.extrait)+'</span></a>';
                    });
                } else if(d.fallback||FALLBACK){
                    html+='<a class="lfi-ia-card" href="'+esc(d.fallback||FALLBACK)+'"><b>Nous contacter</b><span>Décris ta situation, on te recontacte.</span></a>';
                }
                add(html,'bot');
            })
            .catch(function(){
                loading.remove(); busy=false;
                add('Désolé, une erreur est survenue. Écris-nous via <a href="'+esc(FALLBACK)+'">Témoigner / demander de l\'aide</a>.','bot');
            });
        }
        send.addEventListener('click',ask);
        input.addEventListener('keydown',function(e){if(e.key==='Enter')ask();});
    })();
    </script>
    <?php
}
