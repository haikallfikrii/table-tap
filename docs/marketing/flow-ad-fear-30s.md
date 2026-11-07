# TableTap Ad — Fear / Free trial (25–30s) · Google Flow

Mentor brief: max **25–30s**, fokus **takut owner**, CTA jelas (**cuba percuma**), generate di **Google Flow + Veo dengan audio**, visual **konsisten** walau akaun Flow berbeza.

## 1. Satu ketakutan (jangan campur)

**Fear #1 (pilih ini untuk Ads):**  
Peak malam → order salah / lambat → customer marah → takut hilang pelanggan & reputasi.

Bukan cerita “duit + pager + bill” sekali gus. Satu takut saja.

**CTA (jujur dengan closing WA anda):**  
“**Cuba percuma** — demo 15 minit” (bukan “booking call”).  
Kalau mahu literal free trial produk: tawarkan **7 hari cuba percuma** selepas demo — tapi di iklan cukup “Cuba percuma”.

---

## 2. Skrip siap baca (≈28 saat)

| Detik | Visual | VO / dialog (BM ringkas, utara OK) |
|------:|--------|-------------------------------------|
| 0–7 | Kedai sibuk, owner peluh, staf teriak order | “Peak malam… takut order salah.” |
| 7–14 | Customer marah / tinggalkan meja | “Takut customer tak datang balik.” |
| 14–22 | Telefon scan QR → skrin dapur jelas | “Dengan TableTap, customer pesan sendiri. Dapur terus nampak.” |
| 22–28 | End card logo + teks besar | “Cuba percuma. WhatsApp sekarang. tabletap.my” |

**VO penuh (satu nafas, ~28s):**

> Peak malam, takut order salah.  
> Takut customer marah — tak datang balik.  
> TableTap: scan QR, pesan sendiri, dapur terus nampak.  
> Cuba percuma — WhatsApp sekarang.

Jangan lebih dari ~55–65 patah kata.

---

## 3. Pecahan clip di Google Flow

Veo biasanya **4 / 6 / 8 saat** per generate. Buat **4 clip**, edit gabung di CapCut/VN:

| Clip | Lama | Isi |
|------|------|-----|
| A | 8s | Peak chaos + takut salah order |
| B | 6s | Customer kecewa / pergi |
| C | 8s | QR + skrin dapur (penyelesaian) |
| D | 6s | CTA cuba percuma + logo feel |

Aspect: **9:16** (Reels/Ads). Model: **Veo 3.1** (atau Veo 3) dengan **audio ON**.

---

## 4. Konsisten merentas akaun Flow (penting)

Akaun Google berbeza = project berbeza. Consistency **bukan** dari akaun, tapi dari **fail Ingredients yang sama**.

### Langkah sekali (akaun utama)

1. Di Flow → buat **Ingredients** (imej rujukan, latar plain):
   - `owner.png` — lelaki Melayu ~32–40 thn, baju polo gelap, rambut pendek, wajah jelas
   - `kedai.png` — interior kedai makan MY malam, meja kayu, lampu hangat
   - `phone-qr.png` — tangan + telefon tunjuk QR / menu (optional)
2. **Download ketiga-tiga PNG** → simpan di folder `clients/` atau Drive bersama.
3. Setiap akaun Flow lain: **upload Ingredients yang sama** (fail byte sama), jangan generate semula watak baru.

### Lock style (tampal di SETIAP prompt)

```text
STYLE LOCK: photorealistic Malaysian F&B documentary, warm tungsten night lighting,
shallow depth of field, vertical 9:16, handheld slight movement, no subtitles,
no extra logos, no text overlay, consistent face matching ingredient reference.
```

### Lock voice (tampal di SETIAP prompt audio)

```text
AUDIO: single Malay male narrator, calm worried then hopeful, clear Malaysian Malay
accent (not Indonesian), natural room tone of a busy restaurant under the voice,
no music bed, no English.
```

Dialog dalam petikan `"..."` supaya Veo generate suara segerak.

---

## 5. Prompt siap paste (Ingredients to Video)

**Sedia Ingredients:** `@owner` + `@kedai` (+ `@phone` untuk clip C).

### Clip A — takut (8s)

```text
STYLE LOCK: photorealistic Malaysian F&B documentary, warm tungsten night lighting, shallow depth of field, vertical 9:16, handheld slight movement, no subtitles, no extra logos, no text overlay, consistent face matching ingredient reference.

Using the owner and kedai ingredients: medium shot of the same Malay restaurant owner in a busy night cafe, sweat on temple, staff moving behind him with order tickets, he looks anxious at the counter. Camera slow push-in. He mutters in Malaysian Malay with worried tone: "Peak malam... takut order salah."

AUDIO: single Malay male narrator, calm worried then hopeful, clear Malaysian Malay accent (not Indonesian), natural room tone of a busy restaurant under the voice, no music bed, no English.
```

### Clip B — akibat (6s)

```text
STYLE LOCK: photorealistic Malaysian F&B documentary, warm tungsten night lighting, shallow depth of field, vertical 9:16, handheld slight movement, no subtitles, no extra logos, no text overlay, consistent face matching ingredient reference.

Using the owner and kedai ingredients: over-shoulder from the owner toward a frustrated customer standing up from a table, empty plates, shaking head, walking toward the door. Owner's face matches the ingredient. Soft VO in Malaysian Malay, fearful: "Takut customer tak datang balik."

AUDIO: single Malay male narrator, calm worried then hopeful, clear Malaysian Malay accent (not Indonesian), natural room tone of a busy restaurant under the voice, no music bed, no English.
```

### Clip C — penyelesaian (8s)

```text
STYLE LOCK: photorealistic Malaysian F&B documentary, warm tungsten night lighting, shallow depth of field, vertical 9:16, handheld slight movement, no subtitles, no extra logos, no text overlay, consistent face matching ingredient reference.

Using the owner, kedai, and phone ingredients: customer hand scans a table QR on phone, cut to a kitchen tablet screen showing clear large order text, then the same Malay owner smiles slightly checking his phone. Hopeful VO in Malaysian Malay: "TableTap: scan QR, pesan sendiri, dapur terus nampak."

AUDIO: single Malay male narrator, calm worried then hopeful, clear Malaysian Malay accent (not Indonesian), natural room tone of a busy restaurant under the voice, no music bed, no English. Soft UI tap/scan SFX only.
```

### Clip D — CTA (6s)

```text
STYLE LOCK: photorealistic Malaysian F&B documentary, warm tungsten night lighting, shallow depth of field, vertical 9:16, handheld slight movement, no subtitles, no extra logos, no text overlay, consistent face matching ingredient reference.

Using the owner ingredient: clean medium close-up of the same Malay owner looking to camera, calm confident, restaurant soft-bokeh behind. He says clearly in Malaysian Malay: "Cuba percuma. WhatsApp sekarang." Hold last frame for end card edit.

AUDIO: single Malay male narrator, calm worried then hopeful, clear Malaysian Malay accent (not Indonesian), quiet restaurant ambience, no music bed, no English.
```

---

## 6. Edit akhir (CapCut / VN) — 28s

1. Susun A→B→C→D, potong nafas kosong.  
2. Tambah **teks besar** hanya di 3 saat terakhir (luar AI):  
   `CUBA PERCUMA`  
   `WhatsApp · tabletap.my`  
3. Logo TableTap 1 saat terakhir (PNG brand anda).  
4. Export 1080×1920, 30fps.  
5. Jangan biarkan VO AI sebut harga panjang — harga boleh di caption Ads.

**Caption Ads (primary text):**

> Peak malam takut order salah & hilang customer?  
> TableTap — customer scan QR, dapur terus nampak.  
> **Cuba percuma** 15 minit. Tekan WhatsApp.

**Pre-filled WA (≤80):**  
`Minat cuba percuma TableTap. Online/di kedai? Kedai+bandar:`

---

## 7. Checklist konsistensi multi-akaun

- [ ] Ingredients PNG sama (share Drive) di setiap akaun  
- [ ] Prompt selalu mula dengan STYLE LOCK + AUDIO lock yang sama  
- [ ] Watak disebut “the same Malay restaurant owner” + “matching ingredient”  
- [ ] Tiada generate watak baru dari text-only  
- [ ] 9:16 + Veo 3.1 audio on  
- [ ] Total final ≤ 30s  
