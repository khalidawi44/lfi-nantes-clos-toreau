# QL Visual Templates — Charte graphique + scripts prêts à l'emploi

Ce package permet de reproduire les visuels Instagram/Facebook aux couleurs et à la typographie du dossier Clos-Toreau (LFI Nantes-Sud + Union des Quartiers Libres + Quartier Libre) sur n'importe quel autre repo / machine.

## Ce qu'il contient

```
ql-visual-templates/
├── README.md                  ← ce fichier
├── requirements.txt           ← dépendances Python
├── install.sh                 ← installation fonts + deps (Linux/macOS)
├── fonts/
│   ├── Anton-Regular.ttf      ← police titre (bold condensée)
│   └── Lobster-Regular.ttf    ← police surtitre (cursive)
└── templates/
    ├── 01-visuel-simple.py    ← titre + 1 bloc (ex : « VICTOIRE »)
    ├── 02-visuel-double.py    ← 2 blocs (ex : « victoire + révélation »)
    ├── 03-visuel-photo.py     ← photo en fond + voile + texte
    ├── 04-affiche-16-9.py     ← affiche horizontale (featured image)
    └── 05-visuel-cta.py       ← 3 canaux call-to-action
```

## Installation

### 1. Dépendances Python

```bash
pip install -r requirements.txt
```

`Pillow` suffit pour tous les visuels. `weasyprint` seulement pour générer des PDF depuis HTML (non utilisé ici).

### 2. Installation des fonts (Linux / macOS)

```bash
bash install.sh
```

Ou manuellement :

```bash
mkdir -p ~/.fonts
cp fonts/*.ttf ~/.fonts/
fc-cache -f ~/.fonts
fc-list | grep -iE "anton|lobster"
```

Sous **Windows** : copier `fonts/Anton-Regular.ttf` et `fonts/Lobster-Regular.ttf` dans `C:\Windows\Fonts` (ou les installer via double-clic). Puis dans les scripts Python, adapter les chemins :

```python
anton = "C:/Windows/Fonts/Anton-Regular.ttf"
lobster = "C:/Windows/Fonts/Lobster-Regular.ttf"
```

## Charte graphique — LES INVARIANTS

### Palette

| Couleur | Hex | RGB | Usage |
|---|---|---|---|
| Jaune QL | `#F5C518` | `(245, 197, 24)` | Surtitres, surlignages de mots-clés, punchs, accents |
| Noir | `#0A0A0A` | `(10, 10, 10)` | Fond principal, texte sur jaune |
| Blanc | `#FFFFFF` | `(255, 255, 255)` | Texte sur fond sombre, titres principaux |
| Rouge | `#DA1A1E` | `(218, 25, 30)` | Alerte, bloc « ce qui ne va pas » |
| Vert | `#2E823C` | `(46, 130, 60)` | Bloc « ce qui a été obtenu / positif » |
| Gris | `#AAAAAA` | `(170, 170, 170)` | Pied de page, textes secondaires |

### Typographie

| Police | Fichier | Taille type | Usage |
|---|---|---|---|
| **Anton Regular** | `Anton-Regular.ttf` | 60-140pt (titre) / 24-40pt (corps) | Titres, corps, punchs, majuscules |
| **Lobster Regular** | `Lobster-Regular.ttf` | 50-80pt | Surtitre cursif jaune (identifie le lieu) |
| **DejaVu Sans Mono** (système) | | 14-20pt | Pied de page, sources, coordonnées |

### Formats standards

| Format | Dimensions | Usage |
|---|---|---|
| **Instagram Post 4:5** | 1080 × 1350 | Post carrousel principal |
| **Instagram Carré** | 1080 × 1080 | Post standard |
| **Instagram Story 9:16** | 1080 × 1920 | Story Insta + TikTok + Reels cover |
| **Featured image 16:9** | 1600 × 900 | Bannière article web |

### Structure verticale type (post 1080 × 1350)

```
┌────────────────────────────────┐
│  Surtitre Lobster jaune        │  ← identifie le lieu (« À Nantes — X »)
│                                │
│  Date / contexte (Anton petit) │
│                                │
│  ┌──────────────────────────┐  │
│  │  TITRE SURLIGNÉ JAUNE    │  │  ← Anton XL, texte noir sur fond jaune
│  └──────────────────────────┘  │
│                                │
│  ┌──────────────────────────┐  │
│  │ Bloc coloré (vert/rouge) │  │  ← header couleur + contenu noir/semi-opaque
│  │ - Point 1                │  │
│  │ - Point 2                │  │
│  └──────────────────────────┘  │
│                                │
│  PUNCH central (Anton, jaune)  │
│                                │
│  ┌──────────────────────────┐  │
│  │  BANDEAU FINAL NOIR      │  │  ← signature politique
│  │  SIGNATURE               │  │
│  └──────────────────────────┘  │
│  Pied gris (lieu · date)       │
└────────────────────────────────┘
```

## Comment utiliser les templates

Chaque template `.py` dans `templates/` est **autonome** :

```bash
python3 templates/01-visuel-simple.py
```

Sortie : un fichier `.png` dans le répertoire courant.

Chaque template a des constantes en haut du fichier (`W`, `H`, chemins des fonts, contenu texte) — édite-les selon ton besoin. Les fonctions `stroke_text()`, `draw_block()` et les couleurs sont partagées.

## Règles éditoriales à respecter

1. **Aucune invention de fait.** Chaque affirmation doit être sourçable.
2. **Ne pas nommer nommément un individu** dans un contexte accusatoire sans preuves solides.
3. **Distinguer le narrateur** : « Le collectif exige » (voix politique militante) vs « X affirme, Y répond » (voix journalistique QL).
4. **Les surlignages jaunes = mots-clés de la revendication**, pas les liaisons.
5. **Signature politique en pied de bandeau noir** : LFI Nantes-Sud Clos-Toreau, ou UQL, ou les deux — jamais QL sur un visuel militant.

## Auteur / crédits

Charte inspirée de Contre-Attaque, adaptée pour QuartierLibre / LFI Nantes-Sud Clos-Toreau / Union des Quartiers Libres.

Fonts : Anton et Lobster — libres, licence SIL Open Font License.

Pour toute reprise : cette charte peut être adaptée librement pour un autre groupe local. Les couleurs et la structure sont paramétrables via les constantes en haut de chaque script.
