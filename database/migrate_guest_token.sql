-- Per-order guest token for customer API access (optional column; code detects via SHOW COLUMNS)
ALTER TABLE `orders`
  ADD COLUMN `guest_token` VARCHAR(64) DEFAULT NULL AFTER `nama_pelanggan`,
  ADD KEY `idx_guest_token` (`guest_token`);
