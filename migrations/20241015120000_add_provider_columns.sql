ALTER TABLE products
    ADD COLUMN provider_code VARCHAR(100) NULL AFTER status,
    ADD COLUMN provider_product_id VARCHAR(100) NULL AFTER provider_code;
