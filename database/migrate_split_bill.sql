-- Optional manual backup for Hostinger (auto-patch also runs on boot).
-- Split-bill metadata: paid child order points back to source order.

ALTER TABLE orders
  ADD COLUMN split_from_order_id INT UNSIGNED DEFAULT NULL AFTER sumber_order;

ALTER TABLE orders
  ADD KEY idx_orders_split_from (split_from_order_id);
