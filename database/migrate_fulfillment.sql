-- Waiter vs self-pickup + customer name on orders
ALTER TABLE shops
  ADD COLUMN fulfillment_mode ENUM('waiter','self_pickup') NOT NULL DEFAULT 'waiter' AFTER sst_rate;

ALTER TABLE orders
  ADD COLUMN nama_pelanggan VARCHAR(40) DEFAULT NULL AFTER jenis_hidang;
