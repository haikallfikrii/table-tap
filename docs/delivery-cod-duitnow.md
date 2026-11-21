# Delivery — COD & DuitNow

## Customer

- Alamat + telefon (jika wajib)  
- Bayar: counter / COD / DuitNow (ikut setting shop)  
- DuitNow: upload bukti → tunggu kasir confirm  

## Kasir

| State | Tindakan |
|-------|----------|
| COD | Tandai cash received bila waiter/hantar sudah pegang duit |
| DuitNow + proof | View proof → Confirm / Reject |
| Counter delivery | Mark paid manual |

Kitchen boleh di-hold sehingga lunas jika `hold_kitchen_until_paid` on.
