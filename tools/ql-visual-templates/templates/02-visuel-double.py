#!/usr/bin/env python3
"""
Template 02 — Visuel à deux blocs contrastés.

Un bloc VERT (positif : « ce qu'on a obtenu ») + un bloc ROUGE (négatif : « ce qui manque »).
Cas d'usage : opposition victoire / dénonciation, avant / après, exigence contre réalité.

Sortie : ./out_02_double.png (1080×1350)
"""
from PIL import Image, ImageDraw, ImageFont
import os

W, H = 1080, 1350

# === PARAMÈTRES À MODIFIER ===
SURTITRE = "À Nantes — Clos-Toreau"
DATE_LINE = "APRÈS 5 JOURS DE PRESSION"
TITRE = "VICTOIRE."

BLOC_1_HEADER = "CE QU'ON A OBTENU"
BLOC_1_COLOR = (46, 130, 60)  # vert
BLOC_1_LINES = [
    "· Aliun et sa famille relogé·es.",
    "· Portage de courses effectif.",
    "· Prise en charge psychologique.",
]

BLOC_2_HEADER = "MAIS EN PRIVÉ, LES SERVICES DU MAIRE..."
BLOC_2_COLOR = (218, 25, 30)  # rouge
BLOC_2_LINES = [
    "· Ont menacé notre groupe d'une",
    "  plainte pour diffamation.",
    "· Pour des faits vus de nos yeux.",
    "· La vieille politique de dessous",
    "  de table. Sans nous.",
]

PUNCH_LINE_1 = "ON NE SE LAISSERA PAS"
PUNCH_LINE_2 = "INTIMIDER EN PRIVÉ."
SIGN_LIGNE_1 = "LFI NANTES-SUD CLOS-TOREAU"
SIGN_LIGNE_2 = "avec l'Union des Quartiers Libres"
PIED = "CLOS-TOREAU  ·  NANTES  ·  16 JUILLET 2026"

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

def draw_block(img, y, header, header_color, body_lines, font_h, font_b, box_x=40, box_w=None):
    """Dessine un bloc coloré avec header + contenu semi-opaque."""
    if box_w is None:
        box_w = img.width - 80
    draw2 = ImageDraw.Draw(img)
    header_h = 44
    draw2.rectangle([(box_x, y), (box_x + box_w, y + header_h)], fill=header_color)
    tb = draw2.textbbox((0, 0), header, font=font_h)
    hy = y + (header_h - (tb[3]-tb[1])) // 2 - 3
    draw2.text((box_x + 16, hy), header, font=font_h, fill=WHITE)
    body_h_total = 22
    for line in body_lines:
        tb = draw2.textbbox((0, 0), line, font=font_b)
        body_h_total += (tb[3] - tb[1]) + 5
    ov = Image.new("RGBA", (box_w, body_h_total), (0, 0, 0, 230))
    img.paste(ov, (box_x, y + header_h), ov)
    draw2 = ImageDraw.Draw(img)
    draw2.rectangle([(box_x, y + header_h), (box_x + box_w, y + header_h + body_h_total)], outline=header_color, width=2)
    yy = y + header_h + 12
    for line in body_lines:
        tb = draw2.textbbox((0, 0), line, font=font_b)
        draw2.text((box_x + 16, yy), line, font=font_b, fill=WHITE)
        yy += (tb[3] - tb[1]) + 5
    return y + header_h + body_h_total + 14

# === COMPOSITION ===
img = Image.new("RGB", (W, H), BLACK)
draw = ImageDraw.Draw(img)

# Motif géométrique en fond
for gy in range(0, H, 90):
    for gx in range(0, W, 90):
        offset = 45 if (gy // 90) % 2 else 0
        cx = gx + offset; cy = gy + 45
        pts = [(cx, cy-11), (cx+11, cy), (cx, cy+11), (cx-11, cy)]
        draw.polygon(pts, outline=(24, 24, 24), width=1)

f_surtitre = ImageFont.truetype(FONT_LOBSTER, 62)
f_date     = ImageFont.truetype(FONT_ANTON, 30)
f_titre    = ImageFont.truetype(FONT_ANTON, 110)
f_body_h   = ImageFont.truetype(FONT_ANTON, 30)
f_body     = ImageFont.truetype(FONT_ANTON, 28)
f_punch    = ImageFont.truetype(FONT_ANTON, 40)
f_sign_l   = ImageFont.truetype(FONT_ANTON, 42)
f_sign_s   = ImageFont.truetype(FONT_ANTON, 22)
try:
    f_mono = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf", 15)
except:
    f_mono = f_date

# HEADER
y = 34
tb = draw.textbbox((0, 0), SURTITRE, font=f_surtitre)
draw.text(((W - (tb[2]-tb[0]))/2, y), SURTITRE, font=f_surtitre, fill=YELLOW)
y += (tb[3]-tb[1]) + 10
tb = draw.textbbox((0, 0), DATE_LINE, font=f_date)
stroke_text(draw, ((W - (tb[2]-tb[0]))/2, y), DATE_LINE, f_date, WHITE, 2)
y += (tb[3]-tb[1]) + 22

# TITRE
tb = draw.textbbox((0, 0), TITRE, font=f_titre)
w_t = tb[2]-tb[0]; h_t = tb[3]-tb[1]
pad_h = 50
box_x1 = (W - w_t) / 2 - pad_h
box_x2 = box_x1 + w_t + pad_h*2
draw.rectangle([(box_x1, y+8), (box_x2, y + h_t + 24)], fill=YELLOW)
draw.text(((W - w_t) / 2, y), TITRE, font=f_titre, fill=BLACK)
y += h_t + 40

# BLOCS
y = draw_block(img, y, BLOC_1_HEADER, BLOC_1_COLOR, BLOC_1_LINES, f_body_h, f_body)
y = draw_block(img, y, BLOC_2_HEADER, BLOC_2_COLOR, BLOC_2_LINES, f_body_h, f_body)
y += 10

# PUNCH
tb = draw.textbbox((0, 0), PUNCH_LINE_1, font=f_punch)
stroke_text(draw, ((W - (tb[2]-tb[0]))/2, y), PUNCH_LINE_1, f_punch, WHITE, 2)
y += (tb[3]-tb[1]) + 4
tb = draw.textbbox((0, 0), PUNCH_LINE_2, font=f_punch)
stroke_text(draw, ((W - (tb[2]-tb[0]))/2, y), PUNCH_LINE_2, f_punch, YELLOW, 2)

# BANDEAU FINAL
band_h = 150
draw.rectangle([(0, H-band_h-42), (W, H-42)], fill=BLACK)
by = H - band_h - 42 + 24
tb = draw.textbbox((0, 0), SIGN_LIGNE_1, font=f_sign_l)
draw.text(((W - (tb[2]-tb[0]))/2, by), SIGN_LIGNE_1, font=f_sign_l, fill=WHITE)
by += (tb[3]-tb[1]) + 8
tb = draw.textbbox((0, 0), SIGN_LIGNE_2, font=f_sign_s)
draw.text(((W - (tb[2]-tb[0]))/2, by), SIGN_LIGNE_2, font=f_sign_s, fill=GREY)

draw.rectangle([(0, H-42), (W, H)], fill=(15, 15, 15))
tb = draw.textbbox((0, 0), PIED, font=f_mono)
draw.text(((W - (tb[2]-tb[0]))/2, H - 30), PIED, font=f_mono, fill=GREY)

out = "out_02_double.png"
img.save(out, "PNG", optimize=True)
print(f"✅ Visuel généré : {out}")
