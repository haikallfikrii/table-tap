# SST settings

Owner panel: tetapan kedai.

| Field | Makna |
|-------|--------|
| `sst_enabled` | On/off cukai pada bil |
| `sst_rate` | Peratus (cth 6.00) |

## Kesan

- Order baharu: `subtotal`, `sst_jumlah`, `total_harga` dikira masa submit  
- Resit thermal & e-resit tunjuk line SST jika &gt; 0  
- Laporan harian / shift ikut total tersimpan (bukan kira semula rate lama)  

Tukar rate tidak mengubah order lama secara automatik.
