-- Update catalog: fix zero/abnormal prices to approximate VND values, give realistic stock.
-- Prices are approximate ("giá trị chỉ là tương đối"), tuned for a Vietnamese consumer PC shop.
-- Idempotent: safe to re-run.

-- 1) Fix products with no price (were 0) + abnormal outliers -> reasonable approximate VND
UPDATE products SET price = 1290000  WHERE id = 1;   -- NZXT HUE 2 RGB Lighting Kit
UPDATE products SET price = 1690000  WHERE id = 2;   -- NZXT Hue+
UPDATE products SET price = 7490000  WHERE id = 11;  -- Apricorn Aegis Fortress L3 (was ~325M)
UPDATE products SET price = 11990000 WHERE id = 12;  -- WD My Book Duo (was ~30M)
UPDATE products SET price = 790000   WHERE id = 14;  -- Lian Li UNI HUB SL Controller
UPDATE products SET price = 12900000 WHERE id = 16;  -- HiFiMAN Susvara (was ~150M)
UPDATE products SET price = 9990000  WHERE id = 38;  -- SteelSeries Arena 9 (was ~22M)
UPDATE products SET price = 17900000 WHERE id = 42;  -- APC SURT20KRMXLT (was ~602M)

-- 2) Safety net: any remaining zero-priced product gets a modest placeholder price
UPDATE products SET price = 990000 WHERE price IS NULL OR price <= 0;

-- 3) Realistic, varied stock for every product (deterministic, 6..55) instead of a flat 20
UPDATE products SET quantity = 6 + ((id * 13) % 50);

-- 4) Keep low-stock threshold sensible
UPDATE products SET low_stock_threshold = 5 WHERE low_stock_threshold IS NULL OR low_stock_threshold = 0;
