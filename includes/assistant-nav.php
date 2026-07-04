<?php
/**
 * ASSISTANT — navigateur d'intention.
 *
 * Dans le robot de l'app, dès qu'on tape ce qu'on veut (même mal formulé), une
 * liste de destinations pertinentes apparaît, FILTRÉE PAR LE RÔLE (on ne propose
 * que ce à quoi la personne a droit). Un clic → on y est. Gratuit, hors-ligne
 * (filtrage côté navigateur sur une liste préparée côté serveur).
 */
if (!defined('ABSPATH')) exit;

/** Liste des destinations navigables pour l'utilisateur courant (selon son rôle). */
function lfi_nct_assist_actions() {
    if (!function_exists('lfi_nct_app_url')) return [];
    $A = [];
    $add = function ($ico, $label, $vue, $kw, $args = []) use (&$A) {
        $A[] = ['ico' => $ico, 'label' => $label, 'url' => lfi_nct_app_url($vue, $args), 'kw' => mb_strtolower($label . ' ' . $kw)];
    };

    $is_super  = current_user_can('manage_options');
    $is_ga_adm = function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga();
    $is_ga     = function_exists('lfi_nct_user_role_ga') && lfi_nct_user_role_ga();
    $is_tenant = function_exists('lfi_nct_user_role_tenant') && lfi_nct_user_role_tenant();
    $is_partner= function_exists('lfi_nct_user_role_partner') && lfi_nct_user_role_partner();
    $is_avocat = function_exists('lfi_nct_user_role_avocat') && lfi_nct_user_role_avocat();

    /* Commun à tous. */
    $add('🏠', 'Accueil', '', 'accueil tableau de bord home retour', []);

    if ($is_ga_adm || $is_super) {
        $add('🗂', 'Dossiers & suivi', 'dossiers', 'locataire suivi dossier parcours etapes', []);
        $add('📋', 'Faire passer une enquête', 'enquete', 'enquete porte a porte questionnaire terrain saisir', []);
        $add('🏠', 'Comptes locataires', 'comptes-locataires', 'compte locataire creer editer acces', []);
        $add('📁', 'Dossiers juridiques', 'dossiers-juridiques', 'juridique lrar mise en demeure lettre', []);
        $add('⚖️', 'Avocats partenaires', 'avocats', 'avocat me valet goache confier defense', []);
        $add('🏢', 'Bailleurs sociaux', 'bailleurs', 'bailleur nmh hlm interlocuteur agence config', []);
        $add('📥', 'Import email (auto)', 'inbox-import', 'email gmail import correspondance nmh boite rattacher', []);
        $add('🚫', 'Liste noire SMS', 'sms-blocklist', 'sms stop bloquer ne plus recevoir opposition', []);
        $add('📲', 'SMS aux locataires', 'sms-locataires', 'sms message texto locataire', []);
        $add('🔎', 'Jurisprudence', 'jurisprudence', 'jurisprudence judilibre decision tribunal', []);
        $add('🏆', 'Nos victoires', 'victoires', 'victoire coupe reussite championnat gagne', []);
        $add('📅', 'Événements', 'evenements', 'evenement agenda reunion action tractage', []);
        $add('🤝', 'Se coordonner', 'mobilisation', 'coordination mobilisation dispo creneau vote', []);
        $add('👥', 'Membres actifs', 'membres', 'membre militant adherent equipe', []);
        $add('🗺️', 'Carte', 'carte', 'carte geolocalisation adresse', []);
        $add('📈', 'Stats enquête', 'stats-enquete', 'statistique chiffre probleme gravite', []);
    }
    if ($is_super) {
        $add('🤝', 'Élu·es partenaires', 'partenaires', 'elu depute municipal william irina espace', []);
        $add('🏛️', 'Stratégie municipale', 'strategie-municipale', 'municipale william conseil', []);
        $add('🇫🇷', 'Stratégie nationale', 'strategie-nationale', 'national depute assemblee', []);
        $add('💶', 'Audit NMH', 'audit-nmh', 'audit nmh loyer chiffres crc', []);
        $add('🩺', 'Santé publique (puffs)', 'sante', 'sante puff cigarette gnr depute', []);
        $add('👶', 'Protection de l\'enfance', 'ase', 'ase enfance protection departement', []);
        $add('🌐', 'Réseau des GA', 'reseau-ga', 'reseau ga groupes carte cumule', []);
    }
    /* Membre simple (non admin) : mission terrain. */
    if ($is_ga && !$is_ga_adm && !$is_super) {
        $add('📋', 'Faire passer une enquête', 'enquete', 'enquete porte a porte questionnaire', []);
        $add('🤝', 'Se coordonner / mes dispos', 'mobilisation', 'dispo creneau coordination', []);
        $add('📅', 'Événements', 'evenements', 'evenement reunion action', []);
        $add('🏆', 'Nos victoires', 'victoires', 'victoire reussite', []);
    }
    /* Locataire : parcours ultra simple. */
    if ($is_tenant && !$is_ga_adm && !$is_super) {
        $add('📂', 'Mon dossier', '', 'dossier suivi ou j en suis bataille', []);
        $add('📎', 'Envoyer mes pièces / photos', 'envoyer-photo', 'photo piece document envoyer preuve', []);
        $add('🎯', 'Ce que je veux', 'mon-objectif', 'objectif relogement travaux indemnisation demande', []);
        $add('🪪', 'Mon profil', 'mon-profil-loc', 'profil fiche identite', []);
        $add('📅', 'Mes rendez-vous', 'mes-rdv', 'rdv rendez vous agenda', []);
        $add('📱', 'Installer l\'app', 'installer', 'installer application ecran accueil', []);
        $add('🏆', 'Nos victoires', 'victoires', 'victoire espoir reussite', []);
    }
    if ($is_partner || $is_avocat) {
        $add('🗂️', 'Mon espace', 'espace', 'espace dossier partage ligne directe', []);
    }
    /* Commun bas de liste. */
    $add('🪪', 'Mon profil', 'mon-profil', 'profil compte mot de passe', []);
    if ($is_tenant || $is_ga) $add('❓', 'Être aidé·e / aide', 'aide', 'aide contact accompagnement probleme', []);

    /* Dédoublonne par URL. */
    $seen = []; $out = [];
    foreach ($A as $a) { if (isset($seen[$a['url']])) continue; $seen[$a['url']] = 1; $out[] = $a; }
    return $out;
}

/** Injecte les données + le comportement du navigateur dans le robot de l'app. */
function lfi_nct_assist_inject() {
    $actions = lfi_nct_assist_actions();
    if (empty($actions)) return;
    ?>
    <script>
    (function(){
      var ACTIONS = <?php echo wp_json_encode($actions); ?>;
      function norm(s){ return (s||'').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,''); }
      function score(q, a){
        q = norm(q); if(!q) return 0;
        var kw = norm(a.kw), lbl = norm(a.label), s = 0;
        var words = q.split(/\s+/).filter(Boolean);
        words.forEach(function(w){
          if(w.length < 2) return;
          if(lbl.indexOf(w) >= 0) s += 5;
          else if(kw.indexOf(w) >= 0) s += 3;
          else if(kw.split(/\s+/).some(function(k){ return k.indexOf(w)===0 && w.length>=3; })) s += 1;
        });
        return s;
      }
      function ensureBox(){
        var chips = document.getElementById('lfiRobotChips');
        var box = document.getElementById('lfiNavResults');
        if(!box && chips){
          box = document.createElement('div');
          box.id = 'lfiNavResults';
          box.style.cssText = 'display:flex;flex-direction:column;gap:6px;padding:0 14px 6px';
          chips.parentNode.insertBefore(box, chips);
        }
        return box;
      }
      function render(q){
        var box = ensureBox(); if(!box) return;
        if(!q || !q.trim()){ box.innerHTML=''; return; }
        var ranked = ACTIONS.map(function(a){ return {a:a, s:score(q,a)}; })
                            .filter(function(x){ return x.s>0; })
                            .sort(function(a,b){ return b.s-a.s; })
                            .slice(0,5);
        if(!ranked.length){ box.innerHTML='<div style="font-size:.82em;color:#999;padding:2px 2px">Aucun raccourci — appuie sur ➤ pour poser ta question.</div>'; return; }
        box.innerHTML = '<div style="font-size:.72em;color:#8a6d1f;font-weight:800;text-transform:uppercase;letter-spacing:.4px;margin:2px 0">Y aller directement</div>';
        ranked.forEach(function(x){
          var a = document.createElement('a');
          a.href = x.a.url;
          a.style.cssText = 'display:flex;align-items:center;gap:9px;background:#f4f0fb;border:1px solid #e2d7f5;border-radius:10px;padding:9px 11px;text-decoration:none;color:#2a1a4a;font-weight:600';
          a.innerHTML = '<span style="font-size:1.15em">'+x.a.ico+'</span><span style="flex:1">'+x.a.label+'</span><span style="color:#4b2e83;font-weight:800">→</span>';
          box.appendChild(a);
        });
      }
      function hook(){
        var inp = document.getElementById('lfiRobotQ'); if(!inp || inp._lfinav) return;
        inp._lfinav = 1;
        inp.addEventListener('input', function(){ render(inp.value); });
        var form = document.getElementById('lfiRobotForm');
        if(form){ form.addEventListener('submit', function(e){
          var box = document.getElementById('lfiNavResults');
          var first = box && box.querySelector('a');
          // si une destination évidente correspond, on y va direct.
          if(first){ e.preventDefault(); window.location.href = first.href; }
        }); }
      }
      // le panneau peut être créé/ouvert dynamiquement → on ré-accroche.
      document.addEventListener('click', function(){ setTimeout(hook, 120); });
      if(document.readyState!=='loading') hook(); else document.addEventListener('DOMContentLoaded', hook);
    })();
    </script>
    <?php
}
