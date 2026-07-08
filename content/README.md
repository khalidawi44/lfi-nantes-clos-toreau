# Contenu géré depuis nos conversations (Claude Code)

Ce dossier `content/` contient les contenus du site **pilotés depuis la
conversation avec Claude**. Le principe :

> Tu me dis quoi (ici, dans le chat). Je l'écris dans ces fichiers et je
> pousse sur GitHub. Hostinger déploie, et ça s'affiche sur le site.

Tu n'as (presque) plus besoin des formulaires WordPress.

## Fichiers

| Fichier | Ce que c'est | Comment ça s'affiche |
|---|---|---|
| `evenements.php` | Les événements du Groupe d'Action | Shortcode `[lfi_nct_evenements]` sur une page WordPress |
| `analyses-nmh.php` | Retranscriptions + analyses des emails de NMH | Dans le dossier juridique relié → bouton « 📑 Discussion + analyse » (PDF) |

## Comment on s'en sert (exemples)

- **Ajouter un événement** : « Claude, ajoute un événement le 12 juillet à
  18h, salle X, sur le thème Y. » → j'édite `evenements.php`, je pousse,
  il apparaît.
- **Analyser un email NMH** : « Claude, voici l'email de M. Morineau : … »
  → je le retranscris et j'écris l'analyse dans `analyses-nmh.php` (relié
  au bon dossier). Le document PDF du dossier l'affiche aussitôt.

## Mise en place (une seule fois)

Pour les événements : créer une page WordPress (ex. « Agenda ») et y mettre
le shortcode `[lfi_nct_evenements]`. Ensuite, tout se gère depuis le chat.
