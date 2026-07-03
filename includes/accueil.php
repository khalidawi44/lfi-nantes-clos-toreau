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
    <div class="lfi-accueil" style="max-width:820px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1a1a1a">

      <!-- HERO -->
      <section style="background:linear-gradient(135deg,#c8102e,#9d0f26);color:#fff;border-radius:18px;padding:30px 24px;text-align:center">
        <div style="font-size:.9em;letter-spacing:1px;text-transform:uppercase;opacity:.9;margin-bottom:8px">Groupe d'Action La France Insoumise · Nantes Sud — Clos Toreau</div>
        <h2 style="font-size:1.8em;font-weight:900;line-height:1.15;margin:0 0 12px;color:#fff">Un souci dans votre logement ? On peut vous aider.</h2>
        <p style="font-size:1.08em;opacity:.96;max-width:600px;margin:0 auto 20px">Humidité, chauffage, nuisibles, eau chaude, réparations… On vous <strong>accompagne pour faire valoir vos droits</strong> auprès de votre bailleur — simplement, et <strong>gratuitement</strong>, avec l'association <strong>Union des Quartiers Libres</strong>.</p>
        <a href="<?php echo esc_url($survey); ?>" style="display:inline-block;background:#fff;color:#c8102e;font-weight:900;font-size:1.1em;padding:14px 26px;border-radius:12px;text-decoration:none">📋 Signaler mon problème de logement</a>
      </section>

      <!-- CE QU'ON FAIT -->
      <section style="margin-top:28px">
        <h3 style="font-size:1.4em;font-weight:800;color:#c8102e;text-align:center;margin:0 0 16px">Comment on vous aide</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px">
          <?php foreach ($combats as $c): ?>
            <div style="background:#f7f7f7;border-radius:12px;padding:16px">
              <div style="font-size:1.6em"><?php echo $c[0]; ?></div>
              <div style="font-weight:800;margin:4px 0 4px"><?php echo esc_html($c[1]); ?></div>
              <div style="font-size:.94em;color:#333;line-height:1.45"><?php echo wp_kses_post($c[2]); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- TEMOIGNER (bloc réutilisé) -->
      <section style="margin-top:30px">
        <?php echo function_exists('lfi_nct_temoigner_shortcode') ? lfi_nct_temoigner_shortcode([]) : ''; ?>
      </section>

      <!-- NOS RESULTATS -->
      <section style="margin-top:30px">
        <h3 style="font-size:1.4em;font-weight:800;color:#186a3b;text-align:center;margin:0 0 16px">Nos résultats</h3>
        <?php if ($nb_pub > 0 && function_exists('lfi_nct_reussites_shortcode')): ?>
          <?php echo lfi_nct_reussites_shortcode(['limite' => 4]); ?>
        <?php else: ?>
          <p style="text-align:center;color:#555">Nos réussites (relogements obtenus, travaux imposés, indemnisations) sont documentées et publiées ici au fur et à mesure. <strong>La preuve, pas la promesse.</strong></p>
        <?php endif; ?>
      </section>

      <!-- NOUS REJOINDRE -->
      <section style="margin-top:30px;background:#111;color:#fff;border-radius:18px;padding:26px 22px;text-align:center">
        <h3 style="font-size:1.4em;font-weight:900;margin:0 0 8px;color:#fff">Nous rejoindre</h3>
        <p style="opacity:.92;max-width:560px;margin:0 auto 18px">Adhésion à l'association <strong>gratuite</strong>. Que vous souhaitiez être accompagné·e ou donner un coup de main près de chez vous, il y a une place pour vous.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center">
          <a href="<?php echo esc_url($adh); ?>" style="background:#c8102e;color:#fff;font-weight:800;padding:13px 22px;border-radius:12px;text-decoration:none">✊ Adhérer / s'inscrire</a>
          <a href="<?php echo esc_url($events); ?>" style="background:#fff;color:#111;font-weight:800;padding:13px 22px;border-radius:12px;text-decoration:none">📅 Nos événements</a>
          <a href="<?php echo esc_url($contact); ?>" style="background:transparent;border:2px solid #fff;color:#fff;font-weight:800;padding:11px 20px;border-radius:12px;text-decoration:none">✍️ Nous contacter</a>
        </div>
      </section>

    </div>
    <?php
    return ob_get_clean();
}
