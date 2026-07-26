-- Create table for car makes and models
CREATE TABLE car_models (
  id INT AUTO_INCREMENT PRIMARY KEY,
  make VARCHAR(50) NOT NULL,
  model VARCHAR(50) NOT NULL,
  UNIQUE(make, model)
);

-- Create table for individual cars referencing car_models
CREATE TABLE cars (
  id INT AUTO_INCREMENT PRIMARY KEY,
  car_model_id INT NOT NULL,
  year INT NOT NULL,
  color VARCHAR(30),
  mileage INT,
  transmission ENUM('automatic', 'manual'),
  fuel_type ENUM('petrol', 'diesel', 'electric', 'hybrid'),
  available TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  image_url VARCHAR(255),
  FOREIGN KEY (car_model_id) REFERENCES car_models(id)
);

-- Create admin table for login
CREATE TABLE IF NOT EXISTS admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL -- Store hashed passwords
);

-- Bookings table for car reservations
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_id INT NOT NULL,
    user_name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (car_id) REFERENCES cars(id)
);

-- Insert some car brands and models
INSERT INTO car_models (make, model) VALUES
('Toyota', 'Corolla'),
('Toyota', 'Camry'),
('Honda', 'Civic'),
('Honda', 'Accord'),
('Ford', 'Focus'),
('Ford', 'Fiesta'),
('Tesla', 'Model S'),
('Tesla', 'Model 3'),
('BMW', '3 Series'),
('BMW', '5 Series');
('Wolkeswagen', 'Polo');

-- Insert some fictitious cars (assuming car_model_id values exist)
INSERT INTO cars (car_model_id, year, color, mileage, transmission, fuel_type, available)
VALUES
(1, 2020, 'White', 15000, 'automatic', 'petrol', 1),
(2, 2019, 'Black', 22000, 'manual', 'petrol', 1),
(3, 2021, 'Blue', 8000, 'automatic', 'diesel', 1),
(4, 2018, 'Red', 35000, 'manual', 'petrol', 0),
(5, 2022, 'Silver', 5000, 'automatic', 'hybrid', 1),
(6, 2017, 'Green', 42000, 'manual', 'petrol', 1),
(7, 2023, 'White', 1200, 'automatic', 'electric', 1),
(8, 2022, 'Black', 3000, 'automatic', 'electric', 1),
(9, 2020, 'Gray', 17000, 'manual', 'diesel', 1),
(10, 2019, 'Blue', 25000, 'automatic', 'petrol', 0);

-- Insert a default admin user (password: admin123, hashed with PHP's password_hash)
INSERT INTO admin (username, password) VALUES
('admin', '$2y$10$wHn8xQ6v8Qw2n8Qw2n8QwOQw2n8Qw2n8Qw2n8Qw2n8Qw2n8Qw2n8Q'); -- Replace with a real hash

ALTER TABLE cars
    ADD COLUMN image_url VARCHAR(255);

-- Add status column
ALTER TABLE users ADD COLUMN status ENUM('active', 'suspended', 'inactive') DEFAULT 'active';

-- Add admin_notes column
ALTER TABLE users ADD COLUMN admin_notes TEXT;

-- Add verification_status column
ALTER TABLE users ADD COLUMN verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending';

-- Add is_verified column
ALTER TABLE users ADD COLUMN is_verified BOOLEAN DEFAULT FALSE;

-- Add address column
ALTER TABLE users ADD COLUMN address TEXT;

-- Add phone column
ALTER TABLE users ADD COLUMN phone VARCHAR(20);

-- Add full_name column
ALTER TABLE users ADD COLUMN full_name VARCHAR(100);

-- Add created_at column
ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Add updated_at column
ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Create verification_documents table if it doesn't exist
CREATE TABLE IF NOT EXISTS verification_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    document_type ENUM('passport', 'drivers_license', 'national_id') NOT NULL,
    document_number VARCHAR(50) NOT NULL,
    document_image VARCHAR(255) NOT NULL,
    expiry_date DATE,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    submission_date DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create rentals table if it doesn't exist
CREATE TABLE IF NOT EXISTS rentals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    car_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
);
