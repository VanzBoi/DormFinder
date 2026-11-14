-- DORMFINDER academic prototype. Every account, property and transaction is fictional.
-- Import into a NEW database using phpMyAdmin. This file never drops existing data.
CREATE DATABASE IF NOT EXISTS dormfinder_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dormfinder_db;
SET NAMES utf8mb4;

CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('student','owner','admin') NOT NULL DEFAULT 'student',
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 phone VARCHAR(30) NOT NULL DEFAULT '',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE universities (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(160) NOT NULL UNIQUE
) ENGINE=InnoDB;
CREATE TABLE room_types (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;
CREATE TABLE amenities (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;
CREATE TABLE settings (
 setting_key VARCHAR(60) PRIMARY KEY,
 setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;
CREATE TABLE dormitories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 owner_id BIGINT UNSIGNED NOT NULL,
 university_id INT UNSIGNED NOT NULL,
 room_type_id INT UNSIGNED NOT NULL,
 name VARCHAR(160) NOT NULL,
 location VARCHAR(120) NOT NULL,
 exact_address VARCHAR(255) NOT NULL,
 monthly_rent DECIMAL(10,2) NOT NULL,
 capacity INT UNSIGNED NOT NULL,
 available_slots INT UNSIGNED NOT NULL,
 description TEXT NOT NULL,
 house_rules TEXT NOT NULL,
 curfew VARCHAR(255) NOT NULL DEFAULT 'Contact owner for details',
 security_info TEXT NOT NULL,
 contact_number VARCHAR(30) NOT NULL,
 map_url VARCHAR(1000) NOT NULL DEFAULT '',
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 is_demo TINYINT(1) NOT NULL DEFAULT 0,
 rejection_reason TEXT NULL,
 deleted_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (owner_id) REFERENCES users(id),
 FOREIGN KEY (university_id) REFERENCES universities(id),
 FOREIGN KEY (room_type_id) REFERENCES room_types(id),
 CONSTRAINT rent_positive CHECK (monthly_rent > 0),
 CONSTRAINT slots_within_capacity CHECK (available_slots <= capacity),
 INDEX public_search (status,is_active,deleted_at,monthly_rent),
 INDEX area_search (location), INDEX owner_search (owner_id)
) ENGINE=InnoDB;
CREATE TABLE dormitory_images (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 dormitory_id BIGINT UNSIGNED NOT NULL,
 file_path VARCHAR(255) NOT NULL,
 is_public TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (dormitory_id) REFERENCES dormitories(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE dormitory_amenities (
 dormitory_id BIGINT UNSIGNED NOT NULL,
 amenity_id INT UNSIGNED NOT NULL,
 PRIMARY KEY (dormitory_id,amenity_id),
 FOREIGN KEY (dormitory_id) REFERENCES dormitories(id) ON DELETE CASCADE,
 FOREIGN KEY (amenity_id) REFERENCES amenities(id)
) ENGINE=InnoDB;
CREATE TABLE favorites (
 user_id BIGINT UNSIGNED NOT NULL,
 dormitory_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id,dormitory_id),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (dormitory_id) REFERENCES dormitories(id)
) ENGINE=InnoDB;
CREATE TABLE unlock_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference VARCHAR(50) NOT NULL UNIQUE,
 user_id BIGINT UNSIGNED NOT NULL,
 dormitory_id BIGINT UNSIGNED NOT NULL,
 payment_method ENUM('gcash','maya','bank') NOT NULL,
 amount DECIMAL(10,2) NOT NULL,
 status ENUM('success','failed') NOT NULL,
 successful_listing BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'success' THEN dormitory_id ELSE NULL END) STORED,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY one_success (user_id,successful_listing),
 UNIQUE KEY access_identity (id,user_id,dormitory_id),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (dormitory_id) REFERENCES dormitories(id),
 CONSTRAINT fee_positive CHECK (amount > 0)
) ENGINE=InnoDB;
CREATE TABLE unlocked_listings (
 user_id BIGINT UNSIGNED NOT NULL,
 dormitory_id BIGINT UNSIGNED NOT NULL,
 transaction_id BIGINT UNSIGNED NOT NULL UNIQUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id,dormitory_id),
 FOREIGN KEY (transaction_id,user_id,dormitory_id) REFERENCES unlock_transactions(id,user_id,dormitory_id)
) ENGINE=InnoDB;
CREATE TABLE recent_views (
 user_id BIGINT UNSIGNED NOT NULL,
 dormitory_id BIGINT UNSIGNED NOT NULL,
 viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id,dormitory_id),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (dormitory_id) REFERENCES dormitories(id)
) ENGINE=InnoDB;
CREATE TABLE activity_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NULL,
 action VARCHAR(100) NOT NULL,
 details VARCHAR(500) NOT NULL DEFAULT '',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id),
 INDEX activity_date (created_at)
) ENGINE=InnoDB;

INSERT INTO users (id,name,email,password_hash,role,phone) VALUES
 (1,'Demo Administrator','admin@dormfinder.test','$2y$12$5dZWkHC53ZvchmspXnj7J.47sCjPj466N2klojfzR5pCH41PeCt7u','admin',''),
 (2,'Mara Santos (Demo Owner)','owner@dormfinder.test','$2y$12$5dZWkHC53ZvchmspXnj7J.47sCjPj466N2klojfzR5pCH41PeCt7u','owner','0900-000-0000'),
 (3,'Alex Reyes (Demo Student)','student@dormfinder.test','$2y$12$5dZWkHC53ZvchmspXnj7J.47sCjPj466N2klojfzR5pCH41PeCt7u','student',''),
 (4,'Nico Cruz (Demo Owner)','owner2@dormfinder.test','$2y$12$5dZWkHC53ZvchmspXnj7J.47sCjPj466N2klojfzR5pCH41PeCt7u','owner','0900-000-0001');
INSERT INTO universities (id,name) VALUES (1,'University of Cebu - Main Campus'),(2,'University of San Carlos - Downtown Campus'),(3,'Cebu Normal University'),(4,'Cebu Institute of Technology - University');
INSERT INTO room_types (id,name) VALUES (1,'Bedspace'),(2,'Shared room'),(3,'Private room'),(4,'Studio');
INSERT INTO amenities (id,name) VALUES (1,'Wi-Fi'),(2,'Air conditioning'),(3,'Study area'),(4,'Laundry area'),(5,'Shared kitchen'),(6,'Private bathroom'),(7,'Bike parking'),(8,'Water included');
INSERT INTO settings VALUES ('unlock_fee','49.00');
INSERT INTO dormitories (id,owner_id,university_id,room_type_id,name,location,exact_address,monthly_rent,capacity,available_slots,description,house_rules,curfew,security_info,contact_number,status,is_active,is_demo) VALUES
 (1,2,1,2,'Casa Study Cebu','Sanciangko','DEMO ONLY: 12 Sample Lane, Sanciangko, Cebu City',3500.00,4,2,'A bright shared room with a dedicated study nook and plenty of space to settle into student life. Fictional academic demonstration listing.','Quiet hours after 10 PM. No smoking indoors. Keep shared spaces clean.','10 PM; coordinate late arrivals with owner','Demo description: gated entry and common-area cameras. Not independently verified.','0900-000-0000','approved',1,1),
 (2,2,2,3,'The Juniper Rooms','P. del Rosario','DEMO ONLY: 24 Sample Lane, P. del Rosario, Cebu City',6500.00,1,1,'Your own calm corner near campus, with a comfortable desk and a sunlit window. Fictional academic demonstration listing.','No smoking. Visitors register at reception.','11 PM','Demo description: staffed entrance. Not independently verified.','0900-000-0000','approved',1,1),
 (3,4,3,1,'Osmeña Student House','Osmeña Boulevard','DEMO ONLY: 36 Sample Lane, Osmeña Boulevard, Cebu City',2500.00,6,3,'An affordable bedspace with friendly common areas and a shared kitchen. Fictional academic demonstration listing.','Respect quiet hours and assigned storage.','10 PM','Demo description: locked entrance. Not independently verified.','0900-000-0001','approved',1,1),
 (4,2,1,4,'Harborlight Studios','Sanciangko','DEMO ONLY: 48 Sample Lane, Sanciangko, Cebu City',8500.00,2,1,'An independent studio with room to study, recharge, and make yourself at home. Fictional academic demonstration listing.','No smoking. No parties.','No fixed curfew','Demo description: individual door locks. Not independently verified.','0900-000-0000','approved',1,1),
 (5,4,2,2,'Mango & Maple Dorm','P. del Rosario','DEMO ONLY: 60 Sample Lane, P. del Rosario, Cebu City',4200.00,4,0,'A welcoming shared space designed around the everyday routines of college life. Fictional academic demonstration listing.','Keep noise low after 9 PM.','10 PM','Demo description: common-area cameras. Not independently verified.','0900-000-0001','approved',1,1),
 (6,2,4,1,'Southside Study Stay','N. Bacalso','DEMO ONLY: 72 Sample Lane, N. Bacalso, Cebu City',2800.00,8,4,'Simple, practical bedspaces with a shared study area. Fictional academic demonstration listing.','No smoking. Clean shared kitchen after use.','11 PM','Demo description: gated entry. Not independently verified.','0900-000-0000','approved',1,1),
 (7,4,3,3,'Little Fern Residence','Osmeña Boulevard','DEMO ONLY: 84 Sample Lane, Osmeña Boulevard, Cebu City',5800.00,2,2,'A cozy private room with warm light and a quiet place to focus. Fictional academic demonstration listing.','Visitors until 8 PM.','10 PM','Demo description: locked main gate. Not independently verified.','0900-000-0001','approved',1,1),
 (8,2,1,2,'Campus Corner Cebu','Colon','DEMO ONLY: 96 Sample Lane, Colon, Cebu City',3200.00,4,2,'A convenient home base for your campus days. Fictional academic demonstration listing.','No pets. Quiet hours after 10 PM.','10 PM','Demo description: caretaker on site. Not independently verified.','0900-000-0000','pending',1,1),
 (9,4,2,4,'The Blue Door Loft','P. del Rosario','DEMO ONLY: 108 Sample Lane, P. del Rosario, Cebu City',7500.00,2,0,'An airy studio with a small kitchenette. Fictional academic demonstration listing.','No parties.','No fixed curfew','Demo description: secure lobby. Not independently verified.','0900-000-0001','approved',0,1),
 (10,2,3,1,'City Nest Bedspaces','Osmeña Boulevard','DEMO ONLY: 120 Sample Lane, Osmeña Boulevard, Cebu City',2300.00,6,5,'Budget-conscious living with practical student amenities. Fictional academic demonstration listing.','Shared chores. No smoking.','10 PM','Demo description: gated entry. Not independently verified.','0900-000-0000','pending',1,1),
 (11,4,1,3,'Acacia Study Rooms','Colon','DEMO ONLY: 132 Sample Lane, Colon, Cebu City',5000.00,2,1,'A private space with a desk and shared laundry area. Fictional academic demonstration listing.','Quiet hours after 10 PM.','11 PM','Demo description: caretaker on site. Not independently verified.','0900-000-0001','rejected',1,1),
 (12,2,4,2,'Sunroom Student Living','N. Bacalso','DEMO ONLY: 144 Sample Lane, N. Bacalso, Cebu City',3900.00,4,3,'A light-filled shared room with thoughtful storage for student essentials. Fictional academic demonstration listing.','No smoking. Keep walkways clear.','10 PM','Demo description: locked entry. Not independently verified.','0900-000-0000','approved',1,1);
UPDATE dormitories SET rejection_reason='Please clarify the room capacity and upload another room image.' WHERE id=11;
INSERT INTO dormitory_images (dormitory_id,file_path,is_public) SELECT id, CONCAT('assets/images/room-',MOD(id-1,3)+1,'.jpg'),1 FROM dormitories;
INSERT INTO dormitory_images (dormitory_id,file_path,is_public) SELECT id, CONCAT('assets/images/room-',MOD(id,3)+1,'.jpg'),0 FROM dormitories;
INSERT INTO dormitory_amenities SELECT id,1 FROM dormitories;
INSERT INTO dormitory_amenities SELECT id,3 FROM dormitories;
INSERT INTO dormitory_amenities SELECT id,4 FROM dormitories;
INSERT INTO dormitory_amenities SELECT id,5 FROM dormitories;
INSERT INTO dormitory_amenities SELECT id,2 FROM dormitories WHERE monthly_rent>=3500;
INSERT INTO dormitory_amenities SELECT id,6 FROM dormitories WHERE room_type_id IN (3,4);
INSERT INTO unlock_transactions (id,reference,user_id,dormitory_id,payment_method,amount,status) VALUES (1,'DEMO-SUCCESS-0001',3,1,'gcash',49.00,'success'),(2,'DEMO-FAILED-0002',3,2,'maya',49.00,'failed');
INSERT INTO unlocked_listings (user_id,dormitory_id,transaction_id) VALUES (3,1,1);
INSERT INTO favorites (user_id,dormitory_id) VALUES (3,1),(3,2);
INSERT INTO activity_logs (user_id,action,details) VALUES (1,'demo.seed','Fictional academic sample data installed.');
