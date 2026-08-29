-- Port Santai D'Highway: route Western menu items to Minuman/Air station.
-- Replace @shop_id after looking up: SELECT shop_id FROM users WHERE username = 'ownerportsantai';

SET @shop_id := (
  SELECT shop_id FROM users WHERE username = 'ownerportsantai' AND role = 'owner' LIMIT 1
);

SET @cat_id := (
  SELECT id FROM menu_categories
  WHERE shop_id = @shop_id AND kod = 'western'
  LIMIT 1
);

SET @station_id := (
  SELECT id FROM stations
  WHERE shop_id = @shop_id AND kod = 'minuman'
  LIMIT 1
);

UPDATE menu_items
SET station_id = @station_id
WHERE shop_id = @shop_id
  AND menu_category_id = @cat_id;

SELECT mi.id, mi.nama_my, mi.station_id, mc.kod AS category_kod, s.kod AS station_kod
FROM menu_items mi
LEFT JOIN menu_categories mc ON mc.id = mi.menu_category_id
LEFT JOIN stations s ON s.id = mi.station_id
WHERE mi.shop_id = @shop_id AND mc.kod = 'western';
