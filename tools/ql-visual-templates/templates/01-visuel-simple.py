#!/usr/bin/env python3
"""
Template 01 — Visuel simple.

Un surtitre Lobster + un gros titre Anton (surligné jaune) + un message + signature.
Cas d'usage : annonce forte, slogan, revendication en un mot.

Sortie : ./out_01_simple.png (1080×1350)
"""
from PIL import Image, ImageDraw, ImageFont
import os, sys

# === PARAMÈTRES À MODIFIER ===
W, H = 1080, 1350
SURTITRE = "À Nantes — Clos-Toreau"
DATE_LINE = "MERCREDI 16 JUILLET 2026"
TITRE = "VICTOIRE."
MESSAGE_LINES = [
    "Le collectif a obtenu, en 5 jours,",
    "ce que l'institution refusait depuis des mois.",
]
SIGN_HAUT = "TRANSPARENCE. TERRAIN. RÉSULTATS."
SIGN_LIGNE_1 = "LFI NANTES-SUD CLOS-TOREAU"
SIGN_LIGNE_2 = "avec l'Union des Quartiers Libres"
PIED = "CLOS-TOREAU  ·  NANTES  ·  16 JUILLET 2026"

# Chemins des fonts (adapter selon le système)
FONT_ANTON = os.path.expanduser("~/.fonts/Anton-Regular.ttf")
FONT_LOBSTER = os.path.expanduser("~/.fonts/Lobster-Regular.ttf")

# === COULEURS DE LA CHARTE (ne pas modifier sauf refonte de charte) ===
YELLOW = (245, 197, 24)
BLACK  = (10, 10, 10)
WHITE  = (255, 255, 255)
GREY   = (170, 170, 170)

# === HELPERS ===
def stroke_text(draw, xy, txt, font, fill, stroke=2, stroke_color=BLACK):
    """Dessine du texte avec un contour pour lisibilité sur fond variable."""
    x, y = xy
    for dx in range(-stroke, stroke+1):
        for dy in range(-stroke, stroke+1):
            if dx or dy:
                draw.text((x+dx, y+dy), txt, font=font, fill=stroke_color)
    draw.text((x, y), txt, font=font, fill=fill)

# === COMPOSITION ===
img = Image.new("RGB", (W, H), BLACK)
draw = ImageDraw.Draw(img)

# Motif géométrique sobre en fond
for gy in range(0, H, 90):
    for gx in range(0, W, 90):
        offset = 45 if (gy // 90) % 2 else 0
        cx = gx + offset; cy = gy + 45
        pts = [(cx, cy-11), (cx+11, cy), (cx, cy+11), (cx-11, cy)]
        draw.polygon(pts, outline=(24, 24, 24), width=1)

# Polices
f_surtitre = ImageFont.truetype(FONT_LOBSTER, 62)
f_date     = ImageFont.truetype(FONT_ANTON, 30)
f_titre    = ImageFont.truetype(FONT_ANTON, 130)
f_body     = ImageFont.truetype(FONT_ANTON, 32)
f_sign_h   = ImageFont.truetype(FONT_ANTON, 28)
f_sign_l   = ImageFont.truetype(FONT_ANTON, 42)
f_sign_s   = ImageFont.truetype(FONT_ANTON, 22)
try:
    f_mono = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf", 15)
except:
    f_mono = f_date

# 1. SURTITRE (Lobster italique jaune)
y = 60
tb = draw.textbbox((0, 0), SURTITRE, font=f_surtitre)
draw.text(((W - (tb[2]-tb[0]))/2, y), SURTITRE, font=f_surtitre, fill=YELLOW)
y += (tb[3]-tb[1]) + 12

# 2. DATE / CONTEXTE (Anton blanc plus petit)
tb = draw.textbbox((0, 0), DATE_LINE, font=f_date)
stroke_text(draw, ((W - (tb[2]-tb[0]))/2, y), DATE_LINE, f_date, WHITE, 2)
y += (tb[3]-tb[1]) + 40

# 3. TITRE (surligné jaune, fond jaune + texte noir)
tb = draw.textbbox((0, 0), TITRE, font=f_titre)
w_t = tb[2]-tb[0]; h_t = tb[3]-tb[1]
pad_h = 50
box_x1 = (W - w_t) / 2 - pad_h
box_x2 = box_x1 + w_t + pad_h*2
draw.rectangle([(box_x1, y+8), (box_x2, y + h_t + 26)], fill=YELLOW)
draw.text(((W - w_t) / 2, y), TITRE, font=f_titre, fill=BLACK)
y += h_t + 60

# 4. MESSAGE (Anton blanc)
for line in MESSAGE_LINES:
    tb = draw.textbbox((0, 0), line, font=f_body)
    stroke_text(draw, ((W - (tb[2]-tb[0]))/2, y), line, f_body, WHITE, 2)
    y += (tb[3]-tb[1]) + 6

# 5. BANDEAU FINAL noir (signature politique)
band_h = 175
draw.rectangle([(0, H-band_h-42), (W, H-42)], fill=BLACK)
by = H - band_h - 42 + 20
tb = draw.textbbox((0, 0), SIGN_HAUT, font=f_sign_h)
draw.text(((W - (tb[2]-tb[0]))/2, by), SIGN_HAUT, font=f_sign_h, fill=YELLOW)
by += (tb[3]-tb[1]) + 18
tb = draw.textbbox((0, 0), SIGN_LIGNE_1, font=f_sign_l)
draw.text(((W - (tb[2]-tb[0]))/2, by), SIGN_LIGNE_1, font=f_sign_l, fill=WHITE)
by += (tb[3]-tb[1]) + 8
tb = draw.textbbox((0, 0), SIGN_LIGNE_2, font=f_sign_s)
draw.text(((W - (tb[2]-tb[0]))/2, by), SIGN_LIGNE_2, font=f_sign_s, fill=GREY)

# 6. PIED
draw.rectangle([(0, H-42), (W, H)], fill=(15, 15, 15))
tb = draw.textbbox((0, 0), PIED, font=f_mono)
draw.text(((W - (tb[2]-tb[0]))/2, H - 30), PIED, font=f_mono, fill=GREY)

# === SAUVEGARDE ===
out = "out_01_simple.png"
img.save(out, "PNG", optimize=True)
print(f"✅ Visuel généré : {out} ({W}×{H})")
