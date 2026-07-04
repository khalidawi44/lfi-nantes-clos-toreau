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
