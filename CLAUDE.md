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

## Casquettes / signatures (cadre légal) — VARIER selon l'interlocuteur
- **Règle : on VARIE la casquette selon l'interlocuteur** (ce n'est PAS « jamais
  LFI »). Les 2 casquettes sont **distinctes** mais peuvent coexister.
- Au **bailleur** (NMH) : l'**association** (Union des Quartiers Libres) **parle
  POUR le locataire** (mandatée) ; on **peut mentionner La France Insoumise comme
  SOUTIEN** (« Avec le soutien de La France Insoumise »). Fabrice porte les deux
  casquettes, distinctes : Quartier Libre = quand il parle pour le locataire ;
  LFI = en soutien / autre casquette, séparée.
- Au **locataire** : fraternel, entre voisins (association).
- À l'**avocat** : LFI + Union des Quartiers Libres. Fabrice **n'est pas avocat**
  → **jamais « confraternellement »** (utiliser « Bien cordialement »).

## Déploiement
- Travailler sur la branche `claude/conversation-continuity-devices-giNbj`,
  puis merger sur `main` (le site déploie depuis `main`).
- Bumper la version (en-tête + `LFI_NCT_VERSION`) à chaque déploiement ;
  vérifier le live via `compte.js?ver=` sur la page d'accueil.
