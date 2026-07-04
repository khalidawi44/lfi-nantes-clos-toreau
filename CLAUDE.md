# Règles du projet — LFI Nantes Sud · Clos Toreau

## RÈGLE ABSOLUE — Vérifier avant de déployer (ne rien inventer)
À chaque fois : **vérifier CHAQUE entrée / chaque fait, relire, corriger, PUIS déployer.**
- Ne JAMAIS inventer un nom, un rôle, un chiffre, une fonction, une attribution.
- Si une info n'est pas vérifiée : rester générique OU demander, jamais deviner.
- Relire chaque ligne d'un contenu (email, lettre, document) avant le déploiement.
- Exemple d'erreur à ne plus refaire : **Christophe Jouin** = membre du **conseil
  d'administration de NMH** (majorité), **PAS** la préfecture.

## Cloisonnement (règle absolue)
- Rien ne doit transpirer d'un dossier à un autre : ni pièce, ni info, ni lien.
- L'agrégation d'un dossier se fait UNIQUEMENT par `tenant_user_id` exact
  (jamais par nom ni adresse).
- Ne JAMAIS partager l'enquête terrain avec les élu·es (partenaires).

## Casquettes / signatures (cadre légal)
- Au **bailleur** (NMH) : c'est l'**association** mandatée (Union des Quartiers
  Libres) — **jamais LFI**.
- Au **locataire** : fraternel, entre voisins (association).
- À l'**avocat** : LFI + Union des Quartiers Libres. Fabrice **n'est pas avocat**
  → **jamais « confraternellement »** (utiliser « Bien cordialement »).

## Déploiement
- Travailler sur la branche `claude/conversation-continuity-devices-giNbj`,
  puis merger sur `main` (le site déploie depuis `main`).
- Bumper la version (en-tête + `LFI_NCT_VERSION`) à chaque déploiement ;
  vérifier le live via `compte.js?ver=` sur la page d'accueil.
