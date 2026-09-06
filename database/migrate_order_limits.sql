-- Per-shop customer order limits (owner settings). Safe to re-run.
ALTER TABLE shops
  ADD COLUMN IF NOT EXISTS order_burst_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 90,
  ADD COLUMN IF NOT EXISTS order_burst_max SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  ADD COLUMN IF NOT EXISTS cart_max_qty_per_item SMALLINT UNSIGNED NOT NULL DEFAULT 99,
  ADD COLUMN IF NOT EXISTS cart_max_distinct_items SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  ADD COLUMN IF NOT EXISTS cart_max_total_qty SMALLINT UNSIGNED NOT NULL DEFAULT 300;

-- Busy shops (e.g. Port Santai): generous defaults if still on old global 15/60 feel.
UPDATE shops
SET order_burst_seconds = GREATEST(order_burst_seconds, 90),
    order_burst_max = GREATEST(order_burst_max, 30),
    cart_max_qty_per_item = GREATEST(cart_max_qty_per_item, 99),
    cart_max_distinct_items = GREATEST(cart_max_distinct_items, 100),
    cart_max_total_qty = GREATEST(cart_max_total_qty, 300);
