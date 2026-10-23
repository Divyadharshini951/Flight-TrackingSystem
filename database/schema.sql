CREATE DATABASE IF NOT EXISTS skyroute CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE skyroute;

CREATE TABLE IF NOT EXISTS flights (
  id INT AUTO_INCREMENT PRIMARY KEY,
  flight_number VARCHAR(12) NOT NULL UNIQUE,
  airline VARCHAR(80) NOT NULL,
  airline_code VARCHAR(4) NOT NULL,
  origin VARCHAR(80) NOT NULL,
  origin_code CHAR(3) NOT NULL,
  destination VARCHAR(80) NOT NULL,
  destination_code CHAR(3) NOT NULL,
  departure_time DATETIME NOT NULL,
  arrival_time DATETIME NOT NULL,
  duration_minutes INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  cabin_class VARCHAR(30) NOT NULL DEFAULT 'Economy',
  status ENUM('On Time','Boarding','Delayed','Departed','Landed','Cancelled') NOT NULL DEFAULT 'On Time',
  terminal VARCHAR(12) DEFAULT 'T1',
  gate VARCHAR(12) DEFAULT 'A12',
  aircraft VARCHAR(80) DEFAULT 'Airbus A320',
  seats_available INT NOT NULL DEFAULT 60,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS api_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_api_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_ref VARCHAR(12) NOT NULL UNIQUE,
  user_id INT NULL,
  flight_id INT NOT NULL,
  passenger_name VARCHAR(120) NOT NULL,
  passenger_email VARCHAR(190) NOT NULL,
  passenger_phone VARCHAR(30) NOT NULL,
  seats INT NOT NULL DEFAULT 1,
  total_amount DECIMAL(10,2) NOT NULL,
  booking_status ENUM('Confirmed','Cancelled') NOT NULL DEFAULT 'Confirmed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_booking_flight FOREIGN KEY (flight_id) REFERENCES flights(id),
  CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT IGNORE INTO flights
(flight_number,airline,airline_code,origin,origin_code,destination,destination_code,departure_time,arrival_time,duration_minutes,price,cabin_class,status,terminal,gate,aircraft,seats_available)
VALUES
('SR101','SkyRoute Air','SR','Chennai','MAA','Mumbai','BOM',DATE_ADD(NOW(), INTERVAL 1 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 1 DAY), INTERVAL 125 MINUTE),125,5499,'Economy','On Time','T2','B14','Airbus A320',42),
('SR204','SkyRoute Air','SR','Chennai','MAA','Delhi','DEL',DATE_ADD(NOW(), INTERVAL 1 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 1 DAY), INTERVAL 170 MINUTE),170,6899,'Economy','Boarding','T1','A08','Boeing 737-800',28),
('IN312','IndigoNova','IN','Bengaluru','BLR','Chennai','MAA',DATE_ADD(NOW(), INTERVAL 2 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 2 DAY), INTERVAL 60 MINUTE),60,3299,'Economy','On Time','T1','C03','Airbus A320neo',55),
('AR710','AeroVista','AV','Mumbai','BOM','Dubai','DXB',DATE_ADD(NOW(), INTERVAL 2 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 2 DAY), INTERVAL 200 MINUTE),200,15999,'Economy','Delayed','T2','D09','Boeing 737 MAX',19),
('SR550','SkyRoute Air','SR','Delhi','DEL','Bengaluru','BLR',DATE_ADD(NOW(), INTERVAL 3 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 3 DAY), INTERVAL 160 MINUTE),160,7299,'Economy','On Time','T3','F11','Airbus A321',31),
('IN881','IndigoNova','IN','Chennai','MAA','Singapore','SIN',DATE_ADD(NOW(), INTERVAL 4 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 4 DAY), INTERVAL 255 MINUTE),255,18999,'Economy','On Time','T2','E05','Airbus A320neo',22),
('AV091','AeroVista','AV','Hyderabad','HYD','Delhi','DEL',DATE_ADD(NOW(), INTERVAL 1 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 1 DAY), INTERVAL 130 MINUTE),130,6199,'Economy','Landed','T1','A17','Airbus A320',48),
('SR777','SkyRoute Air','SR','Kochi','COK','Mumbai','BOM',DATE_ADD(NOW(), INTERVAL 5 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 5 DAY), INTERVAL 115 MINUTE),115,4999,'Economy','On Time','T1','B02','Airbus A320',37),
('AV205','AeroVista','AV','Chennai','MAA','Bengaluru','BLR',DATE_ADD(NOW(), INTERVAL 1 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 1 DAY), INTERVAL 65 MINUTE),65,2899,'Economy','On Time','T1','C12','Airbus A320',44),
('IN426','IndigoNova','IN','Delhi','DEL','Mumbai','BOM',DATE_ADD(NOW(), INTERVAL 2 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 2 DAY), INTERVAL 135 MINUTE),135,5799,'Economy','On Time','T2','D03','Airbus A321neo',36),
('SR638','SkyRoute Air','SR','Hyderabad','HYD','Chennai','MAA',DATE_ADD(NOW(), INTERVAL 2 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 2 DAY), INTERVAL 80 MINUTE),80,3599,'Economy','Boarding','T1','A04','Airbus A320',29),
('AV319','AeroVista','AV','Bengaluru','BLR','Mumbai','BOM',DATE_ADD(NOW(), INTERVAL 3 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 3 DAY), INTERVAL 105 MINUTE),105,4699,'Economy','On Time','T2','B08','Boeing 737-800',52),
('IN572','IndigoNova','IN','Mumbai','BOM','Goa','GOI',DATE_ADD(NOW(), INTERVAL 3 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 3 DAY), INTERVAL 75 MINUTE),75,3999,'Economy','On Time','T1','E06','Airbus A320neo',33),
('SR842','SkyRoute Air','SR','Delhi','DEL','Jaipur','JAI',DATE_ADD(NOW(), INTERVAL 4 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 4 DAY), INTERVAL 55 MINUTE),55,3199,'Economy','On Time','T3','F02','Airbus A320',41),
('AV460','AeroVista','AV','Kochi','COK','Bengaluru','BLR',DATE_ADD(NOW(), INTERVAL 4 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 4 DAY), INTERVAL 75 MINUTE),75,3399,'Economy','On Time','T1','A11','Airbus A320',27),
('IN690','IndigoNova','IN','Chennai','MAA','Hyderabad','HYD',DATE_ADD(NOW(), INTERVAL 5 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 5 DAY), INTERVAL 85 MINUTE),85,3799,'Economy','On Time','T2','C07','Airbus A321',38),
('SR915','SkyRoute Air','SR','Mumbai','BOM','Singapore','SIN',DATE_ADD(NOW(), INTERVAL 6 DAY),DATE_ADD(DATE_ADD(NOW(), INTERVAL 6 DAY), INTERVAL 330 MINUTE),330,21999,'Economy','On Time','T2','G01','Boeing 787-9',24);
