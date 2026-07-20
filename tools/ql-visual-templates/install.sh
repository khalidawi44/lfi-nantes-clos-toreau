#!/bin/bash
# Installation des fonts + dépendances Python (Linux/macOS)
set -e

echo "→ Installation des dépendances Python..."
pip install --quiet -r requirements.txt

echo "→ Installation des fonts dans ~/.fonts/"
mkdir -p ~/.fonts
cp fonts/Anton-Regular.ttf ~/.fonts/
cp fonts/Lobster-Regular.ttf ~/.fonts/

if command -v fc-cache >/dev/null 2>&1; then
    fc-cache -f ~/.fonts
    echo "→ Cache fontconfig rafraîchi."
    fc-list | grep -iE "anton|lobster" || echo "⚠  Fonts non détectées par fontconfig"
fi

echo ""
echo "✅ Installation terminée."
echo "→ Teste avec :  python3 templates/01-visuel-simple.py"
