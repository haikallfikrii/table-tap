-- Kasir auto-print on paid + thermal printer beep counts
ALTER TABLE shops
  ADD COLUMN IF NOT EXISTS kasir_print_on_paid TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS printer_beep_kitchen TINYINT UNSIGNED NOT NULL DEFAULT 4,
  ADD COLUMN IF NOT EXISTS printer_beep_kasir TINYINT UNSIGNED NOT NULL DEFAULT 0;
