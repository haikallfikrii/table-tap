-- Menu add-ons: pilihan (pick one, e.g. panas/sejuk) & tambahan (optional extras)
CREATE TABLE IF NOT EXISTS menu_addons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shop_id INT UNSIGNED NOT NULL,
    menu_item_id INT UNSIGNED NOT NULL,
    nama_my VARCHAR(80) NOT NULL,
    nama_en VARCHAR(80) NOT NULL,
    harga_delta DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    jenis ENUM('pilihan', 'tambahan') NOT NULL DEFAULT 'tambahan',
    urutan INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_menu_addons_item (shop_id, menu_item_id, is_active, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
