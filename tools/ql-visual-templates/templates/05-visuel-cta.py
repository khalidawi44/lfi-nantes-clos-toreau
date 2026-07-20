#!/usr/bin/env python3
"""
Template 05 — Visuel Call-To-Action à 3 canaux.

Cas d'usage : slide finale de carrousel Instagram, invitation à rejoindre le collectif.
3 blocs de couleurs distinctes (Telegram bleu, LFI rose, UQL brun) avec un CTA chacun.

Sortie : ./out_05_cta.png (1080×1350)
"""
from PIL import Image, ImageDraw, ImageFont
import os

W, H = 1080, 1350

# === PARAMÈTRES ===
SURTITRE = "À Nantes — Clos-Toreau"
SOUS_TOP = "Le collectif actif sur le terrain"
TITRE = "REJOINS-NOUS."

CANAL_1_HEADER = "TELEGRAM"
CANAL_1_COLOR = (40, 130, 210)
CANAL_1_LINES = ["Le canal du groupe — infos rapides,", "coups de main, mobilisations."]
CANAL_1_CTA = "→ Lien dans la caption"

CANAL_2_HEADER = "ACTION POPULAIRE — LFI"
CANAL_2_COLOR = (206, 32, 90)
CANAL_2_LINES = ["Rejoindre officiellement le groupe", "d'action politique local."]
CANAL_2_CTA = "→ actionpopulaire.fr"

CANAL_3_HEADER = "SIGNALER MON LOGEMENT"
CANAL_3_COLOR = (150, 100, 30)
CANAL_3_LINES = ["Vous avez un problème dans votre", "logement ? Union des Quartiers Libres", "vous accompagne — enquête habitante."]
CANAL_3_CTA = "→ Formulaire en ligne"

SIGN_HAUT = "TRANSPARENCE. TERRAIN. RÉSULTATS."
SIGN_LIGNE_1 = "LFI NANTES-SUD CLOS-TOREAU"
SIGN_LIGNE_2 = "avec l'Union des Quartiers Libres"
PIED = "REJOINS  ·  AGIS  ·  SIGNALE"

FONT_ANTON = os.path.expanduser("~/.fonts/Anton-Regular.ttf")
FONT_LOBSTER = os.path.expanduser("~/.fonts/Lobster-Regular.ttf")

YELLOW = (245, 197, 24); BLACK = (10, 10, 10); WHITE = (255, 255, 255); GREY = (170, 170, 170)

# === COMPOSITION ===
img = Image.new("RGB", (W, H), BLACK)
draw = ImageDraw.Draw(img)
for gy in range(0, H, 90):
    for gx in range(0, W, 90):
        offset = 45 if (gy // 90) % 2 else 0
        cx = gx + offset; cy = gy + 45
        pts = [(cx, cy-11), (cx+11, cy), (cx, cy+11), (cx-11, cy)]
        draw.polygon(pts, outline=(24, 24, 24), width=1)

f_surtitre  = ImageFont.truetype(FONT_LOBSTER, 60)
f_titre_xl  = ImageFont.truetype(FONT_ANTON, 140)
f_soustitre = ImageFont.truetype(FONT_ANTON, 34)
f_ch_h      = ImageFont.truetype(FONT_ANTON, 36)
f_ch_b      = ImageFont.truetype(FONT_ANTON, 24)
f_ch_cta    = ImageFont.truetype(FONT_ANTON, 26)
f_sign_l    = ImageFont.truetype(FONT_ANTON, 40)
f_sign_h    = ImageFont.truetype(FONT_ANTON, 26)
f_sign_s    = ImageFont.truetype(FONT_ANTON, 20)
try:
    f_mono = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf", 15)
except:
    f_mono = f_ch_b

def stroke_text(draw, xy, txt, font, fill, stroke=2, sc=BLACK):
    x, y = xy
    for dx in range(-stroke, stroke+1):
        for dy in range(-stroke, stroke+1):
            if dx or dy:
                draw.text((x+dx, y+dy), txt, font=font, fill=sc)
    draw.text((x, y), txt, font=font, fill=fill)

def draw_channel(y, header, header_col, desc_lines, cta):
    global img
    draw2 = ImageDraw.Draw(img)
    box_x = 40; box_w = W - 80
    header_h = 52
    draw2.rectangle([(box_x, y), (box_x + box_w, y + header_h)], fill=header_col)
    tb = draw2.textbbox((0, 0), header, font=f_ch_h)
    hy = y + (header_h - (tb[3]-tb[1])) // 2 - 3
    draw2.text((box_x + 20, hy), header, font=f_ch_h, fill=WHITE)
    body_h_total = 22
    for line in desc_lines:
        tb = draw2.textbbox((0, 0), line, font=f_ch_b)
        body_h_total += (tb[3] - tb[1]) + 4
    body_h_total += 44
    ov = Image.new("RGBA", (box_w, body_h_total), (0, 0, 0, 235))
    img.paste(ov, (box_x, y + header_h), ov)
    draw2 = ImageDraw.Draw(img)
    draw2.rectangle([(box_x, y + header_h), (box_x + box_w, y + header_h + body_h_total)], outline=header_col, width=2)
    yy = y + header_h + 14
    for line in desc_lines:
        tb = draw2.textbbox((0, 0), line, font=f_ch_b)
        draw2.text((box_x + 20, yy), line, font=f_ch_b, fill=WHITE)
        yy += (tb[3] - tb[1]) + 4
    yy += 6
    draw2.text((box_x + 20, yy), cta, font=f_ch_cta, fill=YELLOW)
    return y + header_h + body_h_total + 14

y = 34
tb = draw.textbbox((0, 0), SURTITRE, font=f_surtitre)
draw.text(((W - (tb[2]-tb[0]))/2, y), SURTITRE, font=f_surtitre, fill=YELLOW)
y += (tb[3]-tb[1]) + 12
tb = draw.textbbox((0, 0), SOUS_TOP, font=f_soustitre)
stroke_text(draw, ((W - (tb[2]-tb[0]))/2, y), SOUS_TOP, f_soustitre, WHITE, 2)
y += (tb[3]-tb[1]) + 32

tb = draw.textbbox((0, 0), TITRE, font=f_titre_xl)
w_t = tb[2]-tb[0]; h_t = tb[3]-tb[1]
pad_h = 30
box_x1 = (W - w_t) / 2 - pad_h
box_x2 = box_x1 + w_t + pad_h*2
draw.rectangle([(box_x1, y+12), (box_x2, y + h_t + 32)], fill=YELLOW)
draw.text(((W - w_t) / 2, y), TITRE, font=f_titre_xl, fill=BLACK)
y += h_t + 46

y = draw_channel(y, CANAL_1_HEADER, CANAL_1_COLOR, CANAL_1_LINES, CANAL_1_CTA)
y = draw_channel(y, CANAL_2_HEADER, CANAL_2_COLOR, CANAL_2_LINES, CANAL_2_CTA)
y = draw_channel(y, CANAL_3_HEADER, CANAL_3_COLOR, CANAL_3_LINES, CANAL_3_CTA)

band_h = 175
draw.rectangle([(0, H-band_h-42), (W, H-42)], fill=BLACK)
by = H - band_h - 42 + 20
tb = draw.textbbox((0, 0), SIGN_HAUT, font=f_sign_h)
draw.text(((W - (tb[2]-tb[0]))/2, by), SIGN_HAUT, font=f_sign_h, fill=YELLOW)
by += (tb[3]-tb[1]) + 16
tb = draw.textbbox((0, 0), SIGN_LIGNE_1, font=f_sign_l)
draw.text(((W - (tb[2]-tb[0]))/2, by), SIGN_LIGNE_1, font=f_sign_l, fill=WHITE)
by += (tb[3]-tb[1]) + 8
tb = draw.textbbox((0, 0), SIGN_LIGNE_2, font=f_sign_s)
draw.text(((W - (tb[2]-tb[0]))/2, by), SIGN_LIGNE_2, font=f_sign_s, fill=GREY)

draw.rectangle([(0, H-42), (W, H)], fill=(15, 15, 15))
tb = draw.textbbox((0, 0), PIED, font=f_mono)
draw.text(((W - (tb[2]-tb[0]))/2, H - 30), PIED, font=f_mono, fill=GREY)

out = "out_05_cta.png"
img.save(out, "PNG", optimize=True)
print(f"✅ Visuel CTA généré : {out}")
