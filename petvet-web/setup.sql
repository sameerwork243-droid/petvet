CREATE DATABASE IF NOT EXISTS petvet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE petvet;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('OWNER','ADMIN','USER') DEFAULT 'USER',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    address TEXT,
    phone VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(255) NOT NULL,
    contact_number VARCHAR(50),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    pet_name VARCHAR(255) NOT NULL,
    sex ENUM('Male','Female','Unknown') DEFAULT 'Unknown',
    species VARCHAR(100) DEFAULT 'Dog',
    breed VARCHAR(255),
    color VARCHAR(100),
    date_of_birth DATE,
    age VARCHAR(50),
    is_neutered TINYINT(1) DEFAULT 0,
    is_microchipped TINYINT(1) DEFAULT 0,
    deceased TINYINT(1) DEFAULT 0,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    position VARCHAR(255),
    designation VARCHAR(255),
    salary DECIMAL(12,2) DEFAULT 0,
    contact VARCHAR(50),
    joined_on DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) DEFAULT 'General',
    base_rate DECIMAL(12,2) DEFAULT 0,
    purchase_price DECIMAL(12,2) DEFAULT 0,
    is_grooming TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode_number VARCHAR(100),
    name VARCHAR(255) NOT NULL,
    price DECIMAL(12,2) DEFAULT 0,
    quantity INT DEFAULT 0,
    category VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    contact_number VARCHAR(50),
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    discount_type ENUM('PERCENT','FIXED') DEFAULT 'PERCENT',
    discount_value DECIMAL(12,2) DEFAULT 0,
    start_date DATE,
    expiry_date DATE,
    usage_limit INT DEFAULT 0,
    times_used INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category_id INT,
    amount DECIMAL(12,2) DEFAULT 0,
    date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    client_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME,
    notes TEXT,
    status ENUM('CONFIRMED','CANCELLED','COMPLETED') DEFAULT 'CONFIRMED',
    doctor VARCHAR(255),
    branch_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS appointment_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    service_id INT,
    service_name VARCHAR(255),
    quantity INT DEFAULT 1,
    rate DECIMAL(12,2) DEFAULT 0,
    notes TEXT,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS billing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT,
    client_id INT,
    customer_name VARCHAR(255),
    customer_phone VARCHAR(50),
    subtotal DECIMAL(12,2) DEFAULT 0,
    discount DECIMAL(12,2) DEFAULT 0,
    final_total DECIMAL(12,2) DEFAULT 0,
    amount_paid DECIMAL(12,2) DEFAULT 0,
    status ENUM('PAID','UNPAID','PARTIALLY_PAID') DEFAULT 'UNPAID',
    payment_mode ENUM('CASH','CARD','BANK_TRANSFER') DEFAULT 'CASH',
    coupon_code VARCHAR(50),
    invoice_no VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS billing_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    billing_id INT NOT NULL,
    product_id INT,
    name VARCHAR(255),
    quantity INT DEFAULT 1,
    price DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) DEFAULT 0,
    FOREIGN KEY (billing_id) REFERENCES billing(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS soap_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    appointment_id INT,
    doctor VARCHAR(255),
    subjective TEXT,
    objective TEXT,
    assessment TEXT,
    diagnosis TEXT,
    `plan` TEXT,
    temperature DECIMAL(5,2),
    heart_rate INT,
    respiratory_rate INT,
    weight DECIMAL(8,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS vaccinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    vaccine_name VARCHAR(255),
    administered_on DATE,
    next_due_date DATE,
    batch_number VARCHAR(100),
    notes TEXT,
    administered_by VARCHAR(255),
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    prescribed_by VARCHAR(255),
    prescribed_on DATE,
    notes TEXT,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS prescription_medications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    name VARCHAR(255),
    dosage VARCHAR(255),
    frequency VARCHAR(255),
    duration VARCHAR(255),
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('appointment','pet') DEFAULT 'pet',
    entity_id INT,
    remind_on DATE,
    note TEXT,
    is_dismissed TINYINT(1) DEFAULT 0,
    due_date DATE,
    doctor VARCHAR(255),
    pet_name VARCHAR(255),
    client_name VARCHAR(255),
    contact_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin user (password: admin123)
INSERT INTO users (name, username, email, password, role) VALUES
('Admin', 'admin', 'admin@petvet.local', '$2y$10$FHnxsYgNI89k4UKCA/6/beKUkBn7w4js4Ny5D51If0FgsiIt/NO36', 'OWNER');

-- Default expense categories
INSERT INTO expense_categories (name, description) VALUES
('Rent', 'Office/clinic rent'),
('Utilities', 'Electricity, water, internet'),
('Supplies', 'Medical and office supplies'),
('Salary', 'Employee salaries'),
('Maintenance', 'Equipment and facility maintenance'),
('Other', 'Miscellaneous expenses');

-- Default services
INSERT INTO services (name, category, base_rate) VALUES
('Consultation', 'General', 500),
('Vaccination', 'Medical', 800),
('Surgery', 'Medical', 5000),
('Grooming - Basic', 'Grooming', 300),
('Grooming - Premium', 'Grooming', 600),
('Deworming', 'Medical', 400),
('Lab Test', 'Laboratory', 1200),
('X-Ray', 'Laboratory', 2500),
('Boarding - Standard', 'Boarding', 500),
('Boarding - Premium', 'Boarding', 800);
