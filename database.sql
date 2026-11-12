CREATE DATABASE IF NOT EXISTS kakang_senduk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kakang_senduk;
CREATE TABLE IF NOT EXISTS users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 email VARCHAR(120) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
 role ENUM('admin','voter') NOT NULL DEFAULT 'voter',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS participants (
 id INT AUTO_INCREMENT PRIMARY KEY,
 number INT NOT NULL UNIQUE,
 name VARCHAR(120) NOT NULL,
 category ENUM('Kakang','Senduk') NOT NULL,
 origin VARCHAR(120) DEFAULT '',
 bio TEXT,
 photo VARCHAR(255) DEFAULT '',
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS payments (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 invoice VARCHAR(40) NOT NULL UNIQUE,
 amount INT NOT NULL,
 vote_qty INT NOT NULL,
 status ENUM('PENDING','PAID') NOT NULL DEFAULT 'PENDING',
 paid_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS votes (
 id INT AUTO_INCREMENT PRIMARY KEY,
 payment_id INT NOT NULL,
 participant_id INT NOT NULL,
 quantity INT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY unique_payment_participant (payment_id, participant_id),
 FOREIGN KEY (payment_id) REFERENCES payments(id),
 FOREIGN KEY (participant_id) REFERENCES participants(id)
);
INSERT IGNORE INTO participants (number,name,category,origin,bio) VALUES
(1,'Contoh Kakang 1','Kakang','Ponorogo','Ganti dengan profil peserta resmi.'),
(2,'Contoh Kakang 2','Kakang','Ponorogo','Ganti dengan profil peserta resmi.'),
(3,'Contoh Senduk 1','Senduk','Ponorogo','Ganti dengan profil peserta resmi.'),
(4,'Contoh Senduk 2','Senduk','Ponorogo','Ganti dengan profil peserta resmi.');
