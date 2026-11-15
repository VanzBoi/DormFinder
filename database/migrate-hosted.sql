-- Run ONCE on a database that was created from an earlier dormfinder.sql (before hosted mode existed).
-- Fresh installs from the current dormfinder.sql already include these changes. Back up first.
-- (If a statement says the column or table already exists, that part is already done.)
ALTER TABLE dormitory_images ADD COLUMN image_data MEDIUMBLOB NULL AFTER file_path;
ALTER TABLE dormitory_images ADD COLUMN image_mime VARCHAR(50) NULL AFTER image_data;
CREATE TABLE IF NOT EXISTS sessions (
 id VARCHAR(128) NOT NULL PRIMARY KEY,
 data MEDIUMBLOB NOT NULL,
 updated_at INT UNSIGNED NOT NULL,
 INDEX session_age (updated_at)
) ENGINE=InnoDB;
