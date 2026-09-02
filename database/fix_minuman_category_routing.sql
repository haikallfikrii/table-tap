-- Fix menu + pending order routing: minuman / western / burger → minuman station.
-- Run on production after deploy. Safe to re-run.

UPDATE menu_items mi
INNER JOIN menu_categories mc ON mc.id = mi.menu_category_id
INNER JOIN stations s ON s.shop_id = mi.shop_id AND s.kod = 'minuman'
SET mi.station_id = s.id
WHERE mc.kod IN ('minuman', 'western', 'burger', 'drinks', 'air');

UPDATE menu_items mi
INNER JOIN menu_categories mc ON mc.id = mi.menu_category_id
INNER JOIN stations s ON s.shop_id = mi.shop_id AND s.kod = 'dapur'
SET mi.station_id = s.id
WHERE mc.kod = 'makanan';

-- Re-route open kitchen tickets (not yet picked up / cooking / ready).
UPDATE order_items oi
INNER JOIN orders o ON o.id = oi.order_id
INNER JOIN menu_items mi ON mi.id = oi.menu_item_id
INNER JOIN menu_categories mc ON mc.id = mi.menu_category_id
INNER JOIN stations s ON s.shop_id = o.shop_id AND s.kod = 'minuman'
SET oi.station_id_saat_order = s.id
WHERE mc.kod IN ('minuman', 'western', 'burger', 'drinks', 'air')
  AND oi.status_item IN ('menunggu', 'sedang_dimasak', 'siap');
