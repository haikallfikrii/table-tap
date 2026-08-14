-- TableTap — dine-in / takeaway on orders
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `jenis_hidang` ENUM('dine_in','takeaway') NOT NULL DEFAULT 'dine_in';
