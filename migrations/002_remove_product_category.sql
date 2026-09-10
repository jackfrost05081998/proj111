USE beanson_pos;

ALTER TABLE products
    DROP COLUMN IF EXISTS category;
