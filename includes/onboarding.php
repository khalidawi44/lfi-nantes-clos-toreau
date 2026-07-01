<?php
/**
 * ACCUEIL PREMIÈRE CONNEXION (onboarding) — pour un ADMIN de GA, au tout
 * premier login (et une seule fois). Un pop-up en plusieurs étapes pour :
 *  - configurer son GA (nom, couleur, logo, code, responsable, bailleur) ;
 *  - nous suggérer ses besoins parmi de nombreuses propositions par thème.
 *
 * Le super-admin n'est pas concerné. Les suggestions sont stockées et
 * consultables par le super-admin (« 💡 Suggestions des GA »).
 */
if (!defined('ABSPATH')) exit;

/** Admin d'un GA (pas le super-admin). */
function lfi_nct_onboarding_is_ga_admin() {
    if (!is_user_logged_in()) return false;
    if (current_user_can('manage_options')) return false;
    return function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga();
}

/** Faut-il montrer l'accueil ? (admin de GA + jamais fait). */
function lfi_nct_onboarding_needed() {
    if (!lfi_nct_onboarding_is_ga_admin()) return false;
    return get_user_meta(get_current_user_id(), 'lfi_nct_ga_onboarded', true) === '';
}

/** Propositions de fonctionnalités, par thématique (pour « tes besoins »). */
function lfi_nct_onboarding_besoins() {
    return [
        '📋 Enquête & terrain' => [
            'Nouveaux types de problèmes personnalisés',
            'Questions supplémentaires dans l\'enquête',
            'Mode hors-ligne pour le porte-à-porte',
            'Import d\'enquêtes papier en lot',
        ],
        '⚖️ Locataires & juridique' => [
            'Cascade automatique SCHS → ARS → tribunal',
            'Modèles de courriers supplémentaires',
            'Aide juridictionnelle (formulaire assisté)',
            'Rappels automatiques des délais légaux',
        ],
        '📣 Communication' => [
            'Modèles SMS / email en plus',
            'Affiches & flyers personnalisables',
            'Newsletter aux adhérents',
            'Publications réseaux sociaux programmées',
        ],
        '📅 Événements' => [
            'Rappels automatiques aux inscrit·es',
            'Covoiturage entre participant·es',
            'Jauge / billetterie',
        ],
        '🗺️ Carte & données' => [
            'Filtres avancés sur la carte',
            'Statistiques par immeuble',
            'Export Excel / PDF',
        ],
        '✊ Mobilisation' => [
            'Pétitions en ligne',
            'Planification du porte-à-porte',
            'Suivi des adhésions',
        ],
        '🏢 Bailleurs' => [
            'Ajouter d\'autres bailleurs sociaux',
            'Annuaire des contacts bailleurs',
            'Journal des échanges avec le bailleur',
        ],
        '🎓 Formation & aide' => [
            'Tutoriels vidéo',
            'FAQ / guide pas à pas',
            'Assistant d\'aide (robot) intégré',
        ],
        '♿ Accessibilité' => [
            'Traduction (autres langues)',
            'Mode simplifié / gros texte',
        ],
        '🤝 Partenariats' => [
            'Annuaire d\'avocats & d\'associations',
            'Contacts presse',
        ],
    ];
}

/* ============================================================== *
 *  Traitement du formulaire (sauvegarde config + suggestions)     *
 * ============================================================== */
function lfi_nct_onboarding_maybe_handle() {
    if (!lfi_nct_onboarding_is_ga_admin()) return;

    /* « Passer pour l'instant » : on marque comme fait, sans configurer. */
    if (!empty($_POST['lfi_onboarding_skip']) && check_admin_referer('lfi_onboarding')) {
        update_user_meta(get_current_user_id(), 'lfi_nct_ga_onboarded', current_time('mysql'));
        wp_safe_redirect(lfi_nct_app_url());
        exit;
    }

    if (empty($_POST['lfi_onboarding_save']) || !check_admin_referer('lfi_onboarding')) return;

    $slug = function_exists('lfi_nct_scope_ga_slug') ? lfi_nct_scope_ga_slug() : '';

    /* 1) Personnalisation du GA. */
    if (function_exists('lfi_nct_ga_perso') && function_exists('lfi_nct_ga_perso_save')) {
        $p = lfi_nct_ga_perso($slug);
        $nom = sanitize_text_field(wp_unslash($_POST['entete_nom'] ?? ''));
        if ($nom !== '') $p['entete_nom'] = $nom;
        $p['responsable'] = sanitize_text_field(wp_unslash($_POST['responsable'] ?? ''));
        $coul = trim((string) wp_unslash($_POST['couleur'] ?? ''));
        if ($coul !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $coul)) $p['couleur'] = $coul;
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_POST['code'] ?? '')));
        if ($code !== '') $p['code'] = substr($code, 0, 5);
        if (!empty($_FILES['logo']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $aid = media_handle_upload('logo', 0);
            if (!is_wp_error($aid)) $p['logo_id'] = (int) $aid;
        }
        lfi_nct_ga_perso_save($slug, $p);
    }

    /* 2) Bailleur principal. */
    if (function_exists('lfi_nct_fact_owner_id')) {
        $owner = (int) lfi_nct_fact_owner_id();
        if ($owner) {
            $b = get_user_meta($owner, 'lfi_nct_fact_bailleur', true);
            if (!is_array($b)) $b = [];
            $bn = sanitize_text_field(wp_unslash($_POST['bailleur_nom'] ?? ''));
            $bt = sanitize_text_field(wp_unslash($_POST['bailleur_tel'] ?? ''));
            $be = sanitize_email(wp_unslash($_POST['bailleur_email'] ?? ''));
            if ($bn !== '') $b['nom'] = $bn;
            if ($bt !== '') $b['agence_tel'] = $bt;
            if ($be !== '') $b['agence_email'] = $be;
            if ($bn !== '' || $bt !== '' || $be !== '') update_user_meta($owner, 'lfi_nct_fact_bailleur', $b);
        }
    }

    /* 3) Besoins / suggestions → visibles par le super-admin. */
    $items = array_values(array_filter(array_map('sanitize_text_field', (array) wp_unslash($_POST['besoins'] ?? []))));
    $free  = sanitize_textarea_field(wp_unslash($_POST['besoins_libre'] ?? ''));
    if ($items || $free !== '') {
        $all = get_option('lfi_nct_ga_suggestions', []);
        if (!is_array($all)) $all = [];
        $all[] = [
            'ga'     => $slug,
            'ga_nom' => function_exists('lfi_nct_ga_nom') ? lfi_nct_ga_nom($slug) : $slug,
            'by'     => get_current_user_id(),
            'by_nom' => wp_get_current_user()->display_name,
            'date'   => current_time('mysql'),
            'items'  => $items,
            'libre'  => $free,
        ];
        update_option('lfi_nct_ga_suggestions', $all, false);
    }

    update_user_meta(get_current_user_id(), 'lfi_nct_ga_onboarded', current_time('mysql'));
    wp_safe_redirect(lfi_nct_app_url('', ['bienvenue' => 1]));
    exit;
}

/* ============================================================== *
 *  Rendu du pop-up d'accueil (overlay multi-étapes)               *
 * ============================================================== */
function lfi_nct_onboarding_render() {
    if (!lfi_nct_onboarding_needed()) return;
    $slug   = function_exists('lfi_nct_scope_ga_slug') ? lfi_nct_scope_ga_slug() : '';
    $ga_nom = function_exists('lfi_nct_ga_nom') ? lfi_nct_ga_nom($slug) : 'ton groupe d\'action';
    $p      = function_exists('lfi_nct_ga_perso') ? lfi_nct_ga_perso($slug) : [];
    $coul   = (!empty($p['couleur']) && preg_match('/^#[0-9a-fA-F]{6}$/', $p['couleur'])) ? $p['couleur'] : '#c8102e';
    $code_auto = function_exists('lfi_nct_ga_code') ? lfi_nct_ga_code($slug) : '';

    ?>
    <div class="lfi-onb-overlay" id="lfi-onb">
      <div class="lfi-onb-modal">
        <form method="post" enctype="multipart/form-data" class="lfi-onb-form">
          <?php wp_nonce_field('lfi_onboarding'); ?>
          <input type="hidden" name="lfi_onboarding_save" value="1">

          <div class="lfi-onb-head" style="background:<?php echo esc_attr($coul); ?>">
            <div class="lfi-onb-steps"><span class="s on" data-dot="1"></span><span class="s" data-dot="2"></span><span class="s" data-dot="3"></span><span class="s" data-dot="4"></span></div>
            <div class="lfi-onb-title">Bienvenue 👋</div>
          </div>

          <!-- Étape 1 -->
          <section class="lfi-onb-step" data-step="1">
            <h3>Bienvenue dans l'app de <?php echo esc_html($ga_nom); ?> !</h3>
            <p>On va configurer ton groupe d'action en quelques étapes rapides. Tu pourras tout modifier plus tard dans « 🎨 Personnalisation du GA ».</p>
            <ul class="lfi-onb-list">
              <li>🎨 L'identité de ton GA (nom, couleur, logo)</li>
              <li>🏢 Ton bailleur principal</li>
              <li>💡 Tes besoins : dis-nous ce que tu veux qu'on ajoute</li>
            </ul>
          </section>

          <!-- Étape 2 : identité -->
          <section class="lfi-onb-step" data-step="2" hidden>
            <h3>🎨 L'identité de ton GA</h3>
            <label>Nom affiché (en-tête des courriers)
              <input type="text" name="entete_nom" value="<?php echo esc_attr($p['entete_nom'] ?? ''); ?>" placeholder="Ex : Groupe d'Action LFI Rezé">
            </label>
            <label>Responsable (qui signe)
              <input type="text" name="responsable" value="<?php echo esc_attr($p['responsable'] ?? ''); ?>" placeholder="Prénom Nom">
            </label>
            <div class="lfi-onb-row">
              <label>Couleur<input type="color" name="couleur" value="<?php echo esc_attr($coul); ?>"></label>
              <label>Code enquêtes<input type="text" name="code" maxlength="5" value="<?php echo esc_attr($p['code'] ?? ''); ?>" placeholder="<?php echo esc_attr($code_auto); ?>" style="text-transform:uppercase"></label>
            </div>
            <label>Logo (optionnel)<input type="file" name="logo" accept="image/*"></label>
          </section>

          <!-- Étape 3 : bailleur -->
          <section class="lfi-onb-step" data-step="3" hidden>
            <h3>🏢 Ton bailleur principal</h3>
            <p class="lfi-onb-hint">Le bailleur social principal de ton quartier (tu pourras en ajouter d'autres après).</p>
            <label>Nom du bailleur<input type="text" name="bailleur_nom" placeholder="Ex : Nantes Métropole Habitat, Atlantique Habitations…"></label>
            <label>Téléphone de l'agence<input type="tel" name="bailleur_tel" placeholder="02 …"></label>
            <label>Email de l'agence<input type="email" name="bailleur_email" placeholder="agence@…"></label>
          </section>

          <!-- Étape 4 : besoins -->
          <section class="lfi-onb-step" data-step="4" hidden>
            <h3>💡 De quoi as-tu besoin ?</h3>
            <p class="lfi-onb-hint">Coche tout ce qui t'intéresse : ça nous aide à améliorer l'outil pour ton GA. Rien n'est obligatoire.</p>
            <?php foreach (lfi_nct_onboarding_besoins() as $theme => $list): ?>
              <div class="lfi-onb-theme"><div class="lfi-onb-theme-t"><?php echo esc_html($theme); ?></div>
                <?php foreach ($list as $item): ?>
                  <label class="lfi-onb-check"><input type="checkbox" name="besoins[]" value="<?php echo esc_attr($theme . ' — ' . $item); ?>"> <?php echo esc_html($item); ?></label>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
            <label>Autre besoin / idée (écris librement)
              <textarea name="besoins_libre" rows="3" placeholder="Explique ce qu'il te manque, une idée…"></textarea>
            </label>
          </section>

          <div class="lfi-onb-actions">
            <button type="submit" name="lfi_onboarding_skip" value="1" class="lfi-onb-skip">Passer</button>
            <div class="lfi-onb-nav">
              <button type="button" class="lfi-onb-prev" hidden>← Précédent</button>
              <button type="button" class="lfi-onb-next" style="background:<?php echo esc_attr($coul); ?>">Suivant →</button>
              <button type="submit" class="lfi-onb-finish" style="background:<?php echo esc_attr($coul); ?>;display:none">✅ Terminer</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <style>
    .lfi-onb-overlay{position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;padding:12px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
    .lfi-onb-modal{background:#fff;border-radius:16px;max-width:480px;width:100%;max-height:92vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,.35)}
    .lfi-onb-head{color:#fff;padding:16px 20px;border-radius:16px 16px 0 0;position:sticky;top:0}
    .lfi-onb-title{font-weight:800;font-size:1.2em;margin-top:6px}
    .lfi-onb-steps{display:flex;gap:6px}
    .lfi-onb-steps .s{width:26px;height:5px;border-radius:3px;background:rgba(255,255,255,.4)}
    .lfi-onb-steps .s.on{background:#fff}
    .lfi-onb-form{margin:0}
    .lfi-onb-step{padding:18px 20px}
    .lfi-onb-step h3{margin:0 0 8px;color:#222}
    .lfi-onb-step p{color:#555;margin:0 0 10px}
    .lfi-onb-hint{font-size:.9em}
    .lfi-onb-list{margin:8px 0;padding-left:18px;color:#333;line-height:1.7}
    .lfi-onb-form label{display:flex;flex-direction:column;gap:4px;font-size:.9em;color:#555;margin:8px 0}
    .lfi-onb-form input[type=text],.lfi-onb-form input[type=tel],.lfi-onb-form input[type=email],.lfi-onb-form textarea{font-size:1em;padding:11px 12px;border:1.5px solid #ddd;border-radius:10px;background:#fafafa}
    .lfi-onb-row{display:flex;gap:12px}.lfi-onb-row label{flex:1}
    .lfi-onb-theme{border:1px solid #eee;border-radius:10px;padding:8px 12px;margin:8px 0}
    .lfi-onb-theme-t{font-weight:700;margin-bottom:4px;color:#333}
    .lfi-onb-check{flex-direction:row!important;align-items:center;gap:8px;margin:4px 0!important;color:#333!important}
    .lfi-onb-check input{width:18px;height:18px}
    .lfi-onb-actions{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:14px 20px;border-top:1px solid #eee;position:sticky;bottom:0;background:#fff}
    .lfi-onb-skip{background:none;border:none;color:#888;text-decoration:underline;cursor:pointer;font-size:.9em}
    .lfi-onb-nav{display:flex;gap:8px}
    .lfi-onb-nav button{color:#fff;border:none;padding:11px 16px;border-radius:10px;font-weight:700;cursor:pointer}
    .lfi-onb-prev{background:#999!important}
    </style>
    <script>
    (function(){
      var root=document.getElementById('lfi-onb'); if(!root) return;
      var steps=root.querySelectorAll('.lfi-onb-step');
      var dots=root.querySelectorAll('.lfi-onb-steps .s');
      var prev=root.querySelector('.lfi-onb-prev'), next=root.querySelector('.lfi-onb-next'), finish=root.querySelector('.lfi-onb-finish');
      var cur=1, max=steps.length;
      function show(n){
        cur=n;
        steps.forEach(function(s){ s.hidden = (parseInt(s.dataset.step,10)!==n); });
        dots.forEach(function(d,i){ d.classList.toggle('on', i < n); });
        prev.hidden = (n===1);
        var last = (n===max);
        next.style.display = last ? 'none':'inline-block';
        finish.style.display = last ? 'inline-block':'none';
        root.querySelector('.lfi-onb-modal').scrollTop=0;
      }
      next.addEventListener('click',function(){ if(cur<max) show(cur+1); });
      prev.addEventListener('click',function(){ if(cur>1) show(cur-1); });
      show(1);
    })();
    </script>
    <?php
}

/* ============================================================== *
 *  VUE super-admin : suggestions reçues des GA                    *
 * ============================================================== */
function lfi_nct_app_view_suggestions() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    if (!empty($_POST['lfi_sugg_del']) && check_admin_referer('lfi_sugg_del')) {
        $idx = (int) $_POST['idx'];
        $all = get_option('lfi_nct_ga_suggestions', []);
        if (is_array($all) && isset($all[$idx])) { unset($all[$idx]); update_option('lfi_nct_ga_suggestions', array_values($all), false); }
        wp_safe_redirect(lfi_nct_app_url('suggestions', ['deleted' => 1]));
        exit;
    }

    $all = get_option('lfi_nct_ga_suggestions', []);
    if (!is_array($all)) $all = [];
    lfi_nct_app_screen_open('💡 Suggestions des GA', count($all) . ' demande(s) reçue(s)');
    if (!empty($_GET['deleted'])) lfi_nct_app_flash('Suggestion supprimée.');

    if (empty($all)) {
        echo '<div class="lfi-app-empty">Aucune suggestion pour l\'instant. Les admins de GA en envoient à leur première connexion (et tu pourras rouvrir cette porte plus tard).</div>';
        lfi_nct_app_screen_close();
        return;
    }
    echo '<ul class="lfi-app-list">';
    foreach (array_reverse($all, true) as $idx => $s) {
        echo '<li class="lfi-app-card">';
        echo '<div class="head"><div class="who">' . esc_html($s['ga_nom'] ?? $s['ga'] ?? '') . '</div><div class="when">' . esc_html(!empty($s['date']) ? wp_date('j M Y', strtotime($s['date'])) : '') . '</div></div>';
        echo '<div class="lfi-app-help" style="margin:4px 0"><small>Par ' . esc_html($s['by_nom'] ?? '') . '</small></div>';
        if (!empty($s['items'])) {
            echo '<ul style="margin:6px 0;padding-left:18px">';
            foreach ($s['items'] as $it) echo '<li>' . esc_html($it) . '</li>';
            echo '</ul>';
        }
        if (!empty($s['libre'])) echo '<div class="lfi-app-card" style="background:#f7f7f7;margin-top:6px">💬 ' . esc_html($s['libre']) . '</div>';
        echo '<form method="post" style="margin-top:8px">';
        wp_nonce_field('lfi_sugg_del');
        echo '<input type="hidden" name="lfi_sugg_del" value="1"><input type="hidden" name="idx" value="' . (int) $idx . '">';
        echo '<button type="submit" class="btn-ghost" onclick="return confirm(\'Supprimer cette suggestion ?\');">🗑 Traité / supprimer</button>';
        echo '</form>';
        echo '</li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}
