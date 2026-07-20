#!/usr/bin/env python3
"""
Template 03 — Visuel avec photo en fond + voile + texte.

Cas d'usage : preuve visuelle forte (photo choc) qui devient la scène du message.
La photo doit être en portrait ou paysage, on la crope automatiquement en 4:5.

Sortie : ./out_03_photo.png (1080×1350)

Usage :
    python3 03-visuel-photo.py chemin/vers/photo.jpeg
"""
from PIL import Image, ImageDraw, ImageFont, ImageEnhance, ExifTags
import os, sys

W, H = 1080, 1350

# === PARAMÈTRES À MODIFIER ===
SURTITRE = "À Nantes — Clos-Toreau"
TITRE_LINE_1 = "PAR LA FENÊTRE"
TITRE_LINE_2_PART_A = "DU 6"  # sera suivi d'un « e » exposant + partie C
TITRE_LINE_2_PART_C = " ÉTAGE"
SOUS_TITRE_LINES = [
    "UN BÉBÉ DE 7 MOIS",
    "À 18 M DU SOL",
]
ACCROCHE_LINES = [
    "PARCE QUE NANTES MÉTROPOLE HABITAT",
    "AVAIT CADENASSÉ LES TRAPPES",
    "D'ÉVACUATION DES FUMÉES.",
]
SIGN_LIGNE_1 = "LFI NANTES-SUD CLOS-TOREAU"
PIED = "CLOS-TOREAU  ·  NUIT DU 11-12 JUILLET 2026  ·  2H30 DU MATIN"

FONT_ANTON = os.path.expanduser("~/.fonts/Anton-Regular.ttf")
FONT_LOBSTER = os.path.expanduser("~/.fonts/Lobster-Regular.ttf")

# === COULEURS ===
YELLOW = (245, 197, 24); BLACK = (10, 10, 10); WHITE = (255, 255, 255); GREY = (170, 170, 170)

# === HELPERS ===
def stroke_text(draw, xy, txt, font, fill, stroke=2, sc=BLACK):
    x, y = xy
    for dx in range(-stroke, stroke+1):
        for dy in range(-stroke, stroke+1):
            if dx or dy:
                draw.text((x+dx, y+dy), txt, font=font, fill=sc)
    draw.text((x, y), txt, font=font, fill=fill)

def load_and_crop_photo(src, target_w, target_h, crop_offset_v=0.2):
    """Charge une photo, applique la rotation EXIF, la crope en cover pour target_w × target_h."""
    im = Image.open(src)
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
    tr = target_w / target_h
    sr = bgw / bgh
    if sr > tr:
        new_w = int(bgh * tr)
        left = (bgw - new_w) // 2
        im = im.crop((left, 0, left + new_w, bgh))
    else:
        new_h = int(bgw / tr)
        top = int((bgh - new_h) * crop_offset_v)
        im = im.crop((0, top, bgw, top + new_h))
    return im.resize((target_w, target_h), Image.LANCZOS)

# === CHARGEMENT DE LA PHOTO ===
if len(sys.argv) < 2:
    print("Usage : python3 03-visuel-photo.py <chemin/vers/photo.jpeg>")
    print("→ démo sans photo : fond noir uni.")
    src_photo = None
else:
    src_photo = sys.argv[1]

if src_photo and os.path.isfile(src_photo):
    bg = load_and_crop_photo(src_photo, W, H, crop_offset_v=0.20)
    bg = ImageEnhance.Brightness(bg).enhance(1.15)
    bg = ImageEnhance.Contrast(bg).enhance(1.12)
    bg = ImageEnhance.Color(bg).enhance(1.10)
    img = bg.convert("RGB")
else:
    img = Image.new("RGB", (W, H), BLACK)

# === VOILE MODULÉ (charte QL : 0.20 → 0.55 max, plus dense en haut/bas) ===
overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
odraw = ImageDraw.Draw(overlay)
for y in range(H):
    if y < 620:
        a = int(60 + (620-y)/620 * 100)
    elif y < 900:
        a = 60
    else:
        a = int(60 + (y-900)/(H-900) * 100)
    odraw.rectangle([(0, y), (W, y+1)], fill=(0, 0, 0, a))
img = Image.alpha_composite(img.convert("RGBA"), overlay).convert("RGB")

draw = ImageDraw.Draw(img)

# === POLICES ===
f_surtitre = ImageFont.truetype(FONT_LOBSTER, 84)
f_titre_l = ImageFont.truetype(FONT_ANTON, 108)
f_exposant = ImageFont.truetype(FONT_ANTON, 52)
f_sous = ImageFont.truetype(FONT_ANTON, 54)
f_accroche = ImageFont.truetype(FONT_ANTON, 38)
f_sign = ImageFont.truetype(FONT_ANTON, 36)
try:
    f_mono = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf", 18)
except:
    f_mono = f_sign

# 1. SURTITRE
y = 78
tb = draw.textbbox((0, 0), SURTITRE, font=f_surtitre)
stroke_text(draw, ((W - (tb[2]-tb[0]))/2, y), SURTITRE, f_surtitre, YELLOW, 3)
y += (tb[3]-tb[1]) + 22

# 2. TITRE LIGNE 1
tb = draw.textbbox((0, 0), TITRE_LINE_1, font=f_titre_l)
stroke_text(draw, ((W - (tb[2]-tb[0]))/2, y), TITRE_LINE_1, f_titre_l, WHITE, 3)
y += (tb[3]-tb[1]) + 12

# 3. TITRE LIGNE 2 (surligné jaune, avec « e » exposant)
part_pre = TITRE_LINE_2_PART_A
part_e = "e"
part_post = TITRE_LINE_2_PART_C
tb_pre = draw.textbbox((0, 0), part_pre, font=f_titre_l)
tb_e = draw.textbbox((0, 0), part_e, font=f_exposant)
tb_post = draw.textbbox((0, 0), part_post, font=f_titre_l)
w_pre = tb_pre[2] - tb_pre[0]
w_e = tb_e[2] - tb_e[0]
w_post = tb_post[2] - tb_post[0]
h_t = tb_pre[3] - tb_pre[1]
gap = 4
w_key = w_pre + gap + w_e + w_post
pad_x = 16
box_x1 = (W - w_key - pad_x*2) / 2
box_x2 = box_x1 + w_key + pad_x*2
draw.rectangle([(box_x1, y + 6), (box_x2, y + h_t + 20)], fill=YELLOW)
kx = box_x1 + pad_x
draw.text((kx, y), part_pre, font=f_titre_l, fill=BLACK)
draw.text((kx + w_pre + gap, y - 8), part_e, font=f_exposant, fill=BLACK)
draw.text((kx + w_pre + gap + w_e, y), part_post, font=f_titre_l, fill=BLACK)
y += h_t + 44

# 4. SOUS-TITRE
for line in SOUS_TITRE_LINES:
    tb = draw.textbbox((0, 0), line, font=f_sous)
    stroke_text(draw, ((W - (tb[2]-tb[0]))/2, y), line, f_sous, WHITE, 3)
    y += (tb[3]-tb[1]) + 10

y += 30

# 5. ACCROCHE
for line in ACCROCHE_LINES:
    tb = draw.textbbox((0, 0), line, font=f_accroche)
    stroke_text(draw, ((W - (tb[2]-tb[0]))/2, y), line, f_accroche, WHITE, 2)
    y += (tb[3]-tb[1]) + 6

# 6. PIED avec fond noir pour lisibilité
draw.rectangle([(0, H-115), (W, H)], fill=BLACK)
tb = draw.textbbox((0, 0), PIED, font=f_mono)
draw.text(((W - (tb[2]-tb[0]))/2, H - 92), PIED, font=f_mono, fill=GREY)
tb = draw.textbbox((0, 0), SIGN_LIGNE_1, font=f_sign)
draw.text(((W - (tb[2]-tb[0]))/2, H - 55), SIGN_LIGNE_1, font=f_sign, fill=WHITE)

# SAUVEGARDE
out = "out_03_photo.png"
img.save(out, "PNG", optimize=True)
print(f"✅ Visuel généré : {out}")
