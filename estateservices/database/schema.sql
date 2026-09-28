-- Estate Serve — Database Schema (matches ER diagram, Figure 5.3)
-- Import this first, THEN open database/seed.php once in the browser.
CREATE DATABASE IF NOT EXISTS estate_serve CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE estate_serve;

CREATE TABLE IF NOT EXISTS estates (
  estateId INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  location VARCHAR(120) DEFAULT NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  userId INT AUTO_INCREMENT PRIMARY KEY,
  estateId INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(20) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('resident','provider','delivery','manager') NOT NULL,
  unit VARCHAR(30) DEFAULT NULL,
  bio VARCHAR(255) DEFAULT NULL,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (estateId) REFERENCES estates(estateId)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
  serviceId INT AUTO_INCREMENT PRIMARY KEY,
  providerId INT NOT NULL,
  category VARCHAR(60) NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  durationLabel VARCHAR(60) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (providerId) REFERENCES users(userId)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
  bookingId INT AUTO_INCREMENT PRIMARY KEY,
  residentId INT NOT NULL,
  serviceId INT NOT NULL,
  deliveryId INT DEFAULT NULL,
  status ENUM('pending','confirmed','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  scheduledDate DATE NOT NULL,
  scheduledTime TIME DEFAULT NULL,
  notes TEXT,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (residentId) REFERENCES users(userId),
  FOREIGN KEY (serviceId) REFERENCES services(serviceId),
  FOREIGN KEY (deliveryId) REFERENCES users(userId)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  paymentId INT AUTO_INCREMENT PRIMARY KEY,
  bookingId INT NOT NULL UNIQUE,
  mpesaReceiptNo VARCHAR(40),
  phone VARCHAR(20),
  amount DECIMAL(10,2) NOT NULL,
  status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bookingId) REFERENCES bookings(bookingId)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reviews (
  reviewId INT AUTO_INCREMENT PRIMARY KEY,
  bookingId INT NOT NULL UNIQUE,
  rating TINYINT NOT NULL,
  comment TEXT,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bookingId) REFERENCES bookings(bookingId),
  CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  notificationId INT AUTO_INCREMENT PRIMARY KEY,
  userId INT NOT NULL,
  type VARCHAR(40) NOT NULL,
  message VARCHAR(255) NOT NULL,
  isRead TINYINT(1) NOT NULL DEFAULT 0,
  sentAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (userId) REFERENCES users(userId)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS disputes (
  disputeId INT AUTO_INCREMENT PRIMARY KEY,
  bookingId INT NOT NULL,
  raisedBy INT NOT NULL,
  description TEXT NOT NULL,
  status ENUM('open','resolved') NOT NULL DEFAULT 'open',
  resolutionNote TEXT,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolvedAt TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (bookingId) REFERENCES bookings(bookingId),
  FOREIGN KEY (raisedBy) REFERENCES users(userId)
) ENGINE=InnoDB;
