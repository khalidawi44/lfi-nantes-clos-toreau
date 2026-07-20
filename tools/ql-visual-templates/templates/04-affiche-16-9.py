#!/usr/bin/env python3
"""
Template 04 — Affiche horizontale 16:9 (1600 × 900).

Cas d'usage : featured image d'article web, bannière Twitter/X, cover Facebook.

Sortie : ./out_04_affiche.png (1600×900)

Usage :
    python3 04-affiche-16-9.py chemin/vers/photo.jpeg
"""
from PIL import Image, ImageDraw, ImageFont, ImageEnhance, ExifTags
import os, sys

W, H = 1600, 900

# === PARAMÈTRES ===
SURTITRE = "À Nantes — Clos-Toreau"
TITRE_LINE_1 = "2H30 DU MATIN"
TITRE_LIGNE_2_SURLIGNE = "  LES JEUNES ONT SAUVÉ L'IMMEUBLE  "
SOUS_TITRE = "que Nantes Métropole Habitat avait cadenassé"
PIED = "CLOS-TOREAU · NUIT DU 11-12 JUILLET 2026"

FONT_ANTON = os.path.expanduser("~/.fonts/Anton-Regular.ttf")
FONT_LOBSTER = os.path.expanduser("~/.fonts/Lobster-Regular.ttf")

YELLOW = (245, 197, 24); BLACK = (10, 10, 10); WHITE = (255, 255, 255); GREY = (200, 200, 200)

def stroke_text(draw, xy, txt, font, fill, stroke=3, sc=BLACK):
    x, y = xy
    for dx in range(-stroke, stroke+1):
        for dy in range(-stroke, stroke+1):
            if dx or dy:
                draw.text((x+dx, y+dy), txt, font=font, fill=sc)
    draw.text((x, y), txt, font=font, fill=fill)

# CHARGEMENT PHOTO
if len(sys.argv) < 2:
    print("Usage : python3 04-affiche-16-9.py <chemin/vers/photo>")
    print("→ démo sans photo : fond noir.")
    src_photo = None
else:
    src_photo = sys.argv[1]

if src_photo and os.path.isfile(src_photo):
    im = Image.open(src_photo)
    try:
        exif = im._getexif()
        if exif:
            for tag, value in exif.items():
                if ExifTags.TAGS.get(tag) == "Orientation":
                    if value == 3: im = im.rotate(180, expand=True)
                    elif value == 6: im = im.rotate(270, expand=True)
                    elif value == 8: im = im.rotate(90, expand=True)
    except: pass
    bgw, bgh = im.size
    tr = W / H; sr = bgw / bgh
    if sr > tr:
        new_w = int(bgh * tr); left = (bgw - new_w) // 2
        im = im.crop((left, 0, left + new_w, bgh))
    else:
        new_h = int(bgw / tr); top = int((bgh - new_h) * 0.2)
        im = im.crop((0, top, bgw, top + new_h))
    im = im.resize((W, H), Image.LANCZOS)
    im = ImageEnhance.Brightness(im).enhance(0.65)
    im = ImageEnhance.Contrast(im).enhance(1.15)
    img = im.convert("RGB")
else:
    img = Image.new("RGB", (W, H), BLACK)

# VOILE
overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
odraw = ImageDraw.Draw(overlay)
for y in range(H):
    if y < 250: a = 60
    elif y < 700: a = 110
    else: a = 145
    odraw.rectangle([(0, y), (W, y+1)], fill=(0, 0, 0, a))
img = Image.alpha_composite(img.convert("RGBA"), overlay).convert("RGB")

draw = ImageDraw.Draw(img)

f_surtitre = ImageFont.truetype(FONT_LOBSTER, 78)
f_titre_l = ImageFont.truetype(FONT_ANTON, 108)
f_titre_m = ImageFont.truetype(FONT_ANTON, 60)
f_soustitre = ImageFont.truetype(FONT_ANTON, 40)
f_pied = ImageFont.truetype(FONT_ANTON, 30)

y = 120
tb = draw.textbbox((0, 0), SURTITRE, font=f_surtitre)
stroke_text(draw, ((W - (tb[2]-tb[0]))/2, y), SURTITRE, f_surtitre, YELLOW, 3)
y += (tb[3]-tb[1]) + 40

tb = draw.textbbox((0, 0), TITRE_LINE_1, font=f_titre_l)
stroke_text(draw, ((W - (tb[2]-tb[0]))/2, y), TITRE_LINE_1, f_titre_l, WHITE, 3)
y += (tb[3]-tb[1]) + 20

tb = draw.textbbox((0, 0), TITRE_LIGNE_2_SURLIGNE, font=f_titre_m)
w_l = tb[2] - tb[0]; h_l = tb[3] - tb[1]
box_x = (W - w_l) / 2
draw.rectangle([(box_x, y + 4), (box_x + w_l, y + h_l + 24)], fill=YELLOW)
draw.text((box_x, y), TITRE_LIGNE_2_SURLIGNE, font=f_titre_m, fill=BLACK)
y += h_l + 44

tb = draw.textbbox((0, 0), SOUS_TITRE, font=f_soustitre)
stroke_text(draw, ((W - (tb[2]-tb[0]))/2, y), SOUS_TITRE, f_soustitre, WHITE, 2)

tb = draw.textbbox((0, 0), PIED, font=f_pied)
draw.text(((W - (tb[2]-tb[0]))/2, H - 60), PIED, font=f_pied, fill=GREY)

out = "out_04_affiche.png"
img.save(out, "PNG", optimize=True)
print(f"✅ Affiche 16:9 générée : {out}")
