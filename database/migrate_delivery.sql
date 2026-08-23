-- Optional manual backup (auto-patch also runs on boot).
-- Pro delivery: QR channel, COD / DuitNow proof, contact phone option.

ALTER TABLE shops
  MODIFY COLUMN cafe_verify ENUM('email','phone','email_phone','none') NOT NULL DEFAULT 'email';

ALTER TABLE shops
  ADD COLUMN delivery_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER cafe_verify,
  ADD COLUMN delivery_token VARCHAR(64) DEFAULT NULL AFTER delivery_enabled,
  ADD COLUMN pay_counter TINYINT(1) NOT NULL DEFAULT 1 AFTER delivery_token,
  ADD COLUMN pay_cod TINYINT(1) NOT NULL DEFAULT 1 AFTER pay_counter,
  ADD COLUMN pay_duitnow TINYINT(1) NOT NULL DEFAULT 1 AFTER pay_cod,
  ADD COLUMN duitnow_qr_url VARCHAR(255) DEFAULT NULL AFTER pay_duitnow,
  ADD COLUMN hold_kitchen_until_paid TINYINT(1) NOT NULL DEFAULT 1 AFTER duitnow_qr_url,
  ADD COLUMN delivery_require_phone TINYINT(1) NOT NULL DEFAULT 0 AFTER hold_kitchen_until_paid;

ALTER TABLE orders
  MODIFY COLUMN jenis_hidang ENUM('dine_in','takeaway','delivery') NOT NULL DEFAULT 'dine_in';

ALTER TABLE orders
  ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER nama_pelanggan,
  ADD COLUMN alamat VARCHAR(500) DEFAULT NULL AFTER phone,
  ADD COLUMN payment_method ENUM('counter','cod','duitnow') NOT NULL DEFAULT 'counter' AFTER alamat,
  ADD COLUMN payment_proof_url VARCHAR(255) DEFAULT NULL AFTER payment_method,
  ADD COLUMN payment_proof_status ENUM('none','uploaded','rejected','confirmed') NOT NULL DEFAULT 'none' AFTER payment_proof_url;

ALTER TABLE orders
  ADD KEY idx_orders_payment (shop_id, payment_method, payment_proof_status);
