# 🧠 MÉMOIRE / HANDOFF — Union des Quartiers Libres & GA LFI Clos Toreau

> **À quoi sert ce fichier :** c'est la **mémoire permanente** du projet.
> À chaque fois que Fabrice dit « **enregistre ça** » / « mets ça en mémoire »,
> on ajoute une ligne datée dans la section **« À RETENIR »** ci-dessous, puis on
> le lui rappelle. Ce fichier survit d'une session à l'autre (il est versionné
> avec le code). Au début d'une nouvelle session : **lire ce fichier d'abord.**

Dernière mise à jour : **01/07/2026**.

---

## ⭐ À RETENIR (journal — le plus récent en haut)

- **01/07/2026** — 📌 **Demande de modification des statuts DÉPOSÉE** sur
  demarches.service-public.gouv.fr (nouvel objet : défense des locataires /
  logement + **capacité d'ester en justice**, nouvel art. 9 ; RNA
  **W442030899**). **Décision en réserve** — Fabrice communiquera le récépissé.
  → Rappel agenda posé le **08/07/2026**.
- **01/07/2026** — 🧑‍⚖️ **Visite chez Maître Gouache (avocat)** effectuée.
  **Compte rendu à venir** de la part de Fabrice → rappel agenda le **02/07/2026**.
  Objectif : transformer ses conseils en plan d'action (mandats locataires,
  référés, SCHS/ARS/tribunal).
- **01/07/2026** — 3 PDF préfecture fournis, **datés 01/07/2026 et signés**
  (signatures réelles de Fabrice + Gwenaëlle) : feuille de présence, PV, statuts.

---

## 👥 Association (état civil)
- **Union des Quartiers Libres** — loi 1901 — RNA **W442030899**.
- Siège : 14 rue de Saint-Jean-de-Luz, 44200 Nantes.
- **2 membres** : **Fabrice Doucet** (président), **Gwenaëlle Gourdien** (secrétaire).
- Email : fabrice.doucet44@gmail.com (agenda) ; quartierlibre44@proton.me (asso).

---

## ⚠️ RÈGLES À RESPECTER ABSOLUMENT
1. **Ne jamais rien inventer** (chiffres, faits, données).
2. **Jamais de faux / d'antidatage** de documents officiels.
3. **PERSO ≠ ASSO**, jamais mélangé :
   - **PERSO** = Fabrice auto-entrepreneur → **travaux + factures** (son nom, son compte).
   - **ASSO** = Union des Quartiers Libres → **mandats, statuts, préfecture, adhésions** (compte asso).
   - L'asso **accompagne et mandate** ; Fabrice **répare et facture**.
4. **Factures** : générateur unique conforme (`lfi_nct_facture_render_html`),
   mentions micro-entrepreneur, **numérotation continue jamais touchée**.
5. **Cloisonnement par GA** strict. **L'app est la référence** (pas le wp-admin).
6. Enquête jamais révélée à Nantes Habitat ; préfecture/réussites anonymes ;
   membres GA et locataires jamais dans les mêmes listes.

---

## 🔧 Projet technique
- Plugin WordPress + app PWA `/app/`. Site : lfi-nantes-clostoreau.fr (Hostinger).
- **Version en ligne : v2.48.**
- Branche de travail : `claude/conversation-continuity-devices-giNbj`.
- **Déploiement** : restart depuis `origin/main` → force-with-lease → PR
  **draft → ready → squash-merge**. Purge cache au bump de version.
- Cloisonnement : colonne `ga` sur les réponses ; owner-pivot ; helpers dans
  `includes/ga-tenancy.php` / `ga-perso.php`.

### Modules récents notables
- Comptabilité Clos Toreau (`compta.php`), sauvegarde point-fixe (`sauvegarde.php`,
  séparation PERSO/ASSO), volet national (`volet-national.php`), onboarding
  (`onboarding.php`), note avocat (dans `dossiers-locataires.php`).

---

## ⏳ EN ATTENTE / À FAIRE
1. **Google Drive** — export auto des factures (compte de service Google : projet
   GCP + clé JSON + partage de dossier). *Proposé, pas encore fait.*
2. **Robot / assistant** — choix acté : commandes intelligentes d'abord, puis IA
   Claude ; docs = dossier locataire, récap enquête, stats/carte, contacts.
   ⚠️ Un module `assistant-ia.php` existe déjà → l'inspecter avant de coder.
3. **Cascade SCHS → ARS → tribunal** selon la volonté du locataire — à finir.
4. Statuts art. 10 : adresse perso de Gwenaëlle à ajouter si besoin.

---

## 📅 Rappels agenda (Google, fabrice.doucet44@gmail.com)
- **02/07/2026** — Faire le compte rendu de la visite chez Maître Gouache.
- **08/07/2026** — Vérifier la décision/récépissé préfecture (modif statuts).
