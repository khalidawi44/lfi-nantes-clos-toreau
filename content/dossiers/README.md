# Dossiers locataires — fiches maîtres gérées par le code

Chaque fichier `content/dossiers/{slug}.php` est **la fiche de référence** d'un
locataire. On la met à jour **depuis une conversation dédiée avec Claude** :
Claude édite le fichier, pousse sur GitHub, et le site/app affiche la fiche à
jour.

## Comment ça marche

- **Source unique** : `content/dossiers/fadiga.php` (par exemple) contient TOUT
  le dossier de Mme Fadiga (identité, objectif, désordres, emails, enquête,
  pièces, suivi).
- **Affichage dans WordPress** : route **`/app/?vue=dossier-synthese&slug=fadiga`**
  (bouton « 📂 Fiches de synthèse » dans la liste des dossiers juridiques).
- **PDF** : le même contenu sert à générer le PDF de synthèse.
- **Lien base WP** : `dossier_id` relie la fiche au dossier juridique
  correspondant dans la base WordPress.

## Ouvrir une conversation dédiée à une locataire

Dans une nouvelle conversation, il suffit de dire à Claude :

> « Ouvre le dossier de Mme Fadiga : `content/dossiers/fadiga.php`. Voici la
> mise à jour… »

Claude lit le fichier, applique la mise à jour demandée, pousse, et la fiche se
met à jour partout (app + PDF).

## Créer un nouveau dossier locataire

Copier `fadiga.php` vers `content/dossiers/{nouveau-slug}.php`, adapter les
valeurs, pousser. La fiche apparaît automatiquement dans la liste.

## Champs principaux

`slug`, `dossier_id`, `civilite/prenom/nom`, `adresse/etage/appartement`,
`anciennete`, `bailleur`, `bailleur_contact`, `medical`, `rdv`,
`objectif_locataire`, `objectifs_ga[]`, `desordres[]` (nom/nmh/obs),
`email_envoye`, `email_recu`, `enquete` (chiffres), `timeline[]`,
`prochaines_etapes[]`, `pieces[]`, `confidentiel`.
