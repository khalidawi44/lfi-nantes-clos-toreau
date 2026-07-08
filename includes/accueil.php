<?php
/**
 * PAGE D'ACCUEIL PUBLIQUE — reflet du terrain.
 *
 * Shortcode [lfi_nct_accueil] : une page d'accueil cohérente qui montre ce
 * qu'on fait vraiment (manifeste court → nos combats → témoigner → nos
 * résultats → nous rejoindre), pour remplacer une présentation fouillis.
 *
 * Règles : collectif et ANONYME (jamais de nom de locataire, jamais le profil
 * personnel), claims attribués (« d'après notre enquête de voisinage »),
 * sécheresse plutôt qu'emphase — image d'un groupe qui CONSTRUIT et qui LIVRE.
 */
if (!defined('ABSPATH')) exit;

/* La bannière hero du thème (.ag-asso-hero) doit être TOUT EN HAUT, collée sous
   le menu. Le thème n'étant pas dans ce dépôt, on force sa position par une
   injection CSS depuis le plugin (page d'accueil, front uniquement). */
/* La bannière du thème reste collée en haut (flush). */
add_action('wp_head', 'lfi_nct_hero_top_css', 99);
function lfi_nct_hero_top_css() {
    if (is_admin()) return;
    echo '<style id="lfi-hero-top">'
       . '.ag-asso-hero{margin-top:0 !important;order:-1}'
       . 'body.home main, body.home #main, body.home .site-main, body.home .entry-content{padding-top:0 !important;margin-top:0 !important}'
       . '</style>';
}

/* La bannière (.ag-asso-hero) est REPLACÉE par JS juste sous l'en-tête du thème :
   le CSS `order` ne marche que si le parent est en flex — pas fiable. On déplace
   donc le nœud lui-même en tête du contenu (marche aussi pour l'admin connecté,
   pour qui la barre fixe ci-dessous ne s'affiche pas). */
add_action('wp_footer', 'lfi_nct_hero_relocate_js', 6);
function lfi_nct_hero_relocate_js() {
    if (is_admin() || !is_front_page()) return;
    ?>
    <script>
    (function(){
      function place(){
        var hero=document.querySelector('.ag-asso-hero'); if(!hero) return;
        var header=document.querySelector('#masthead, .site-header, header[role="banner"], header');
        try{
          if(header && header.parentNode){ header.parentNode.insertBefore(hero, header.nextSibling); }
          else if(document.body){ document.body.insertBefore(hero, document.body.firstChild); }
        }catch(e){}
      }
      if(document.readyState!=='loading') place();
      else document.addEventListener('DOMContentLoaded', place);
    })();
    </script>
    <?php
}

/* Bandeau compact FIXE (indépendant du thème → reste figé quoi qu'il arrive) :
   apparaît en haut dès qu'on descend, avec le bouton « Signaler ». */
add_action('wp_footer', 'lfi_nct_fixed_hero_bar', 20);
function lfi_nct_fixed_hero_bar() {
    if (is_admin() || !is_front_page()) return;
    if (is_user_logged_in()) return; /* réservé aux visiteurs du site public */
    $survey = function_exists('lfi_nct_survey_url') ? lfi_nct_survey_url() : home_url('/');
    ?>
    <div id="lfi-fixbar" style="position:fixed;top:0;left:0;right:0;z-index:99997;transform:translateY(-110%);transition:transform .28s ease;background:linear-gradient(135deg,#c8102e,#9d0f26);color:#fff;box-shadow:0 4px 16px rgba(0,0,0,.25);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
      <div style="max-width:1080px;margin:0 auto;display:flex;align-items:center;gap:12px;padding:9px 16px">
        <strong style="font-size:.98em;white-space:nowrap">🏠 LFI Nantes Sud — Clos Toreau</strong>
        <span style="flex:1"></span>
        <a href="<?php echo esc_url($survey); ?>" style="background:#fff;color:#c8102e;font-weight:800;padding:8px 16px;border-radius:10px;text-decoration:none;white-space:nowrap;font-size:.92em">📋 Signaler mon logement</a>
      </div>
    </div>
    <style>@media(max-width:520px){#lfi-fixbar strong{font-size:.82em}#lfi-fixbar a{padding:7px 11px;font-size:.82em}}</style>
    <script>
    (function(){
      var bar=document.getElementById('lfi-fixbar'); if(!bar) return;
      function upd(){ var y=window.scrollY||document.documentElement.scrollTop; bar.style.transform = (y>220)?'translateY(0)':'translateY(-110%)'; }
      window.addEventListener('scroll', upd, {passive:true}); upd();
    })();
    </script>
    <?php
}

/* ============================================================== *
 *  AFFICHE A4 (hall d'immeuble) — QR vers l'enquête, imprimable.  *
 *  Route ?vue=affiche. Réservée aux admins pour l'imprimer.       *
 * ============================================================== */
function lfi_nct_app_view_affiche() {
    if (!current_user_can('manage_options') && !(function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga())) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $survey = function_exists('lfi_nct_survey_url') ? lfi_nct_survey_url() : home_url('/');
    $survey = add_query_arg('src', 'affiche', $survey);           /* pour repérer les scans d'affiche */
    $qr = 'https://api.qrserver.com/v1/create-qr-code/?size=900x900&margin=12&ecc=M&data=' . rawurlencode($survey);
    $logos = function_exists('lfi_nct_signature_logos_html') ? lfi_nct_signature_logos_html('avocat', 'center') : '';
    $probs = [
        ['💧', 'Humidité, moisissures, fuites'],
        ['🔥', 'Chauffage ou eau chaude en panne'],
        ['🐜', 'Nuisibles (punaises, cafards, rats)'],
        ['🏚️', 'Logement insalubre, dangereux'],
        ['🔧', 'Travaux promis, jamais faits'],
        ['🏠', 'Besoin d\'être relogé·e'],
    ];
    nocache_headers();
    ?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Affiche — Un souci dans votre logement ?</title>
    <style>
      :root{--r:#c8102e;--v:#4b2e83}
      *{box-sizing:border-box}
      html,body{margin:0;background:#e9e6f2}
      body{font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#16121f}
      .noprint{position:sticky;top:0;background:var(--v);color:#fff;text-align:center;padding:10px;z-index:5}
      .btn{background:#fff;color:var(--v);border:0;padding:10px 22px;border-radius:10px;font-weight:800;cursor:pointer}
      .sheet{width:210mm;min-height:297mm;margin:16px auto;background:#fff;box-shadow:0 8px 30px rgba(0,0,0,.15);display:flex;flex-direction:column;overflow:hidden}
      .top{background:linear-gradient(135deg,var(--r),#9d0f26);color:#fff;text-align:center;padding:26px 30px 22px}
      .eyebrow{letter-spacing:2px;text-transform:uppercase;font-weight:800;font-size:15px;opacity:.95}
      h1{font-size:52px;line-height:1.04;margin:12px 0 6px;font-weight:900}
      .top p{font-size:22px;margin:0;opacity:.97}
      .mid{flex:1;display:flex;flex-direction:column;align-items:center;padding:24px 30px 10px;text-align:center}
      .qrwrap{background:#fff;border:6px solid var(--v);border-radius:22px;padding:14px;box-shadow:0 6px 18px rgba(75,46,131,.25)}
      .qrwrap img{display:block;width:330px;height:330px}
      .scan{font-size:30px;font-weight:900;color:var(--v);margin:16px 0 2px}
      .scan small{display:block;font-size:18px;font-weight:700;color:#555;margin-top:4px}
      .probs{display:grid;grid-template-columns:1fr 1fr;gap:8px 22px;margin:16px auto 4px;max-width:620px;text-align:left}
      .probs div{font-size:18px;font-weight:600;display:flex;gap:10px;align-items:center}
      .probs .e{font-size:24px}
      .free{margin-top:12px;background:#eef7ee;border:2px solid #186a3b;color:#186a3b;border-radius:12px;padding:10px 16px;font-weight:800;font-size:19px}
      .bot{background:var(--v);color:#fff;text-align:center;padding:16px 20px}
      .bot .n{font-weight:900;font-size:20px}
      .bot .s{opacity:.92;font-size:15px;margin-top:2px}
      .logos img{max-height:52px!important;margin:0 8px;vertical-align:middle}
      @media print{ .noprint{display:none} html,body{background:#fff} .sheet{margin:0;box-shadow:none;width:auto;min-height:auto} @page{size:A4;margin:0} }
    </style></head><body>
    <div class="noprint">Affiche pour le hall — <button class="btn" onclick="window.print()">🖨️ Imprimer / PDF (A4)</button></div>
    <div class="sheet">
      <div class="top">
        <div class="logos" style="margin-bottom:8px"><?php echo $logos; ?></div>
        <div class="eyebrow">La France Insoumise · Nantes Sud — Clos Toreau</div>
        <h1>Un souci dans<br>votre logement ?</h1>
        <p>On peut vous aider — <strong>gratuitement, entre voisins.</strong></p>
      </div>
      <div class="mid">
        <div class="qrwrap"><img src="<?php echo esc_url($qr); ?>" alt="QR code — signaler mon logement"></div>
        <div class="scan">📱 Scannez ce code<small>Signalez votre problème en 2 minutes, depuis votre téléphone</small></div>
        <div class="probs">
          <?php foreach ($probs as $p): ?><div><span class="e"><?php echo $p[0]; ?></span> <?php echo esc_html($p[1]); ?></div><?php endforeach; ?>
        </div>
        <div class="free">🔒 Confidentiel · vous choisissez d'être recontacté·e ou non</div>
      </div>
      <div class="bot">
        <div class="n">On va vers les gens. On écoute. On agit — ensemble.</div>
        <div class="s">La France Insoumise · Nantes Sud — Clos Toreau &nbsp;·&nbsp; Association Union des Quartiers Libres</div>
      </div>
    </div>
    </body></html><?php
    exit;
}

add_shortcode('lfi_nct_accueil', 'lfi_nct_accueil_shortcode');
function lfi_nct_accueil_shortcode($atts) {
    $survey  = function_exists('lfi_nct_survey_url') ? lfi_nct_survey_url() : home_url('/');
    $events  = function_exists('lfi_nct_app_url') ? lfi_nct_app_url('evenements') : home_url('/');
    $adh     = function_exists('lfi_nct_app_url') ? lfi_nct_app_url('inscription') : home_url('/');
    $contact = function_exists('lfi_nct_robot_public_contacts') ? (lfi_nct_robot_public_contacts()['contact'] ?? home_url('/')) : home_url('/');
    $nb_pub  = function_exists('lfi_nct_reussites') ? count(array_filter(lfi_nct_reussites(), function ($r) { return !empty($r['publie']); })) : 0;

    $combats = [
        ['🏠', 'Logement décent', 'Humidité, moisissures, fuites, dégradations : on vous aide à faire <strong>constater</strong> (photos, certificats) et à rappeler au bailleur ses <strong>obligations</strong>.'],
        ['🚿', 'Chauffage & eau chaude', 'Panne durable, coupures : on vous aide à obtenir une <strong>remise en état</strong> dans les délais.'],
        ['🐜', 'Nuisibles', 'Punaises, blattes, rats : le <strong>traitement est à la charge du bailleur</strong>. On vous aide à l\'obtenir.'],
        ['🔑', 'Relogement & réparation', 'Quand le logement n\'est pas décent : on demande le <strong>relogement</strong> (à la charge du bailleur) et la <strong>réparation</strong> des préjudices.'],
        ['🤝', 'Accompagnement gratuit', 'Rédaction des courriers, démarches, service d\'hygiène, avocat partenaire — <strong>à vos côtés</strong>, de bout en bout.'],
        ['👋', 'À la rencontre des habitant·es', 'On va vers les gens, on écoute, on informe sur les droits. Ensemble, on est plus fort·es.'],
    ];

    ob_start(); ?>
    <style>
      .lfi-accueil{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1a1a1a;overflow-x:hidden}
      /* Bandes PLEIN ÉCRAN : chaque section prend toute la largeur, même dans un
         conteneur de thème étroit (technique « full-bleed » 100vw). */
      .lfi-acc-band{position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw;width:100vw;max-width:100vw;padding:40px 18px;box-sizing:border-box}
      .lfi-acc-inner{max-width:1040px;margin:0 auto}
      .lfi-acc-h{font-size:1.55em;font-weight:900;text-align:center;margin:0 0 6px}
      .lfi-acc-sub{text-align:center;color:#555;max-width:720px;margin:0 auto 22px;font-size:1.02em;line-height:1.5}
      @media(max-width:600px){.lfi-acc-band{padding:26px 14px}}
    </style>
    <div class="lfi-accueil">

      <!-- 1) SIGNALEMENT — une seule porte d'entrée -->
      <div class="lfi-acc-band" style="background:linear-gradient(135deg,#c8102e,#9d0f26)">
        <div class="lfi-acc-inner" style="text-align:center;color:#fff">
          <div style="font-size:.85em;letter-spacing:1.2px;text-transform:uppercase;opacity:.9;margin-bottom:10px">Groupe d'Action La France Insoumise · Nantes Sud — Clos Toreau</div>
          <h2 style="font-size:2em;font-weight:900;line-height:1.12;margin:0 0 14px;color:#fff">Un souci dans votre logement&nbsp;?<br>On peut vous aider.</h2>
          <p style="font-size:1.12em;opacity:.96;max-width:680px;margin:0 auto 22px;line-height:1.5">Humidité, moisissures, nuisibles, chauffage, eau chaude, réparations… On vous <strong>accompagne pour faire valoir vos droits</strong> auprès de votre bailleur — simplement, et <strong>gratuitement</strong>, avec l'association <strong>Union des Quartiers Libres</strong>.</p>
          <a href="<?php echo esc_url($survey); ?>" style="display:inline-block;background:#fff;color:#c8102e;font-weight:900;font-size:1.15em;padding:15px 30px;border-radius:12px;text-decoration:none;box-shadow:0 8px 22px rgba(0,0,0,.25)">📋 Signaler mon logement (5&nbsp;min)</a>
          <div style="margin-top:12px;font-size:.92em;opacity:.9">C'est <strong>confidentiel</strong>, et vous pouvez demander qu'<strong>on vous recontacte</strong>.</div>
        </div>
      </div>

      <!-- 2) COMMENT ON VOUS AIDE -->
      <div class="lfi-acc-band" style="background:#fff">
        <div class="lfi-acc-inner">
          <h3 class="lfi-acc-h" style="color:#c8102e">Comment on vous aide</h3>
          <p class="lfi-acc-sub">Nos combats concrets, quartier par quartier — la loi est de votre côté, on la fait respecter.</p>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">
            <?php foreach ($combats as $c): ?>
              <div style="background:#f7f7f7;border-radius:14px;padding:18px;border-top:4px solid #c8102e">
                <div style="font-size:1.7em"><?php echo $c[0]; ?></div>
                <div style="font-weight:800;margin:6px 0 5px;font-size:1.05em"><?php echo esc_html($c[1]); ?></div>
                <div style="font-size:.94em;color:#333;line-height:1.5"><?php echo wp_kses_post($c[2]); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- 3) NOS RÉSULTATS — coupe + victoires encadrées en colonnes -->
      <div class="lfi-acc-band" style="background:#f1f7f2">
        <div class="lfi-acc-inner">
          <h3 class="lfi-acc-h" style="color:#186a3b">Nos résultats</h3>
          <p class="lfi-acc-sub">La preuve, pas la promesse. Chaque victoire est documentée et <strong>anonyme</strong>.</p>
          <?php echo function_exists('lfi_nct_tableau_reussites_shortcode') ? lfi_nct_tableau_reussites_shortcode([]) : ''; ?>
        </div>
      </div>

      <!-- 4) NOUS REJOINDRE -->
      <div class="lfi-acc-band" style="background:#111">
        <div class="lfi-acc-inner" style="text-align:center;color:#fff">
          <h3 class="lfi-acc-h" style="color:#fff">Nous rejoindre</h3>
          <p style="opacity:.92;max-width:620px;margin:0 auto 20px;line-height:1.5">Adhésion à l'association <strong>gratuite</strong>. Que vous souhaitiez être accompagné·e ou donner un coup de main près de chez vous, il y a une place pour vous.</p>
          <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center">
            <a href="<?php echo esc_url($adh); ?>" style="background:#c8102e;color:#fff;font-weight:800;padding:13px 22px;border-radius:12px;text-decoration:none">✊ Adhérer / s'inscrire</a>
            <a href="<?php echo esc_url($events); ?>" style="background:#fff;color:#111;font-weight:800;padding:13px 22px;border-radius:12px;text-decoration:none">📅 Nos événements</a>
            <a href="<?php echo esc_url($contact); ?>" style="background:transparent;border:2px solid #fff;color:#fff;font-weight:800;padding:11px 20px;border-radius:12px;text-decoration:none">✍️ Nous contacter</a>
          </div>
        </div>
      </div>

    </div>
    <?php
    return ob_get_clean();
}
