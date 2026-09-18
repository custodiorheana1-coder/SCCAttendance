CREATE DATABASE IF NOT EXISTS event_attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE event_attendance;

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_no VARCHAR(50) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100) NULL,
  course VARCHAR(100) NULL,
  year_level VARCHAR(20) NULL,
  section VARCHAR(50) NULL,
  email VARCHAR(150) NULL,
  contact_number VARCHAR(40) NULL,
  qr_code VARCHAR(255) UNIQUE,
  account_status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_student_no (student_no)
);

CREATE TABLE IF NOT EXISTS events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  event_type VARCHAR(80) NULL,
  venue VARCHAR(180) NULL,
  event_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  scan_in_start TIME NOT NULL,
  scan_in_end TIME NOT NULL,
  scan_out_start TIME NOT NULL,
  scan_out_end TIME NOT NULL,
  fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  attendance_required TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('UPCOMING','ACTIVE','COMPLETED','CANCELLED','DRAFT','CLOSED') NOT NULL DEFAULT 'UPCOMING',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_events_active (status, event_date)
);
CREATE TABLE IF NOT EXISTS event_students (
  event_id INT NOT NULL,
  student_id INT NOT NULL,
  PRIMARY KEY (event_id, student_id),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS event_attendance (
  attendance_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  event_id INT NOT NULL,
  scan_in DATETIME NULL,
  scan_out DATETIME NULL,
  attendance_status ENUM('PRESENT','ABSENT','LATE','EXCUSED','INCOMPLETE') NOT NULL DEFAULT 'INCOMPLETE',
  scanner VARCHAR(80) NULL,
  recorded_date DATE NOT NULL,
  remarks VARCHAR(255) NULL,
  UNIQUE KEY uq_attendance_event_student (event_id, student_id),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS fines (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  student_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  status ENUM('UNPAID','PARTIALLY PAID','PAID','WAIVED') NOT NULL DEFAULT 'UNPAID',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY uq_fine_event_student (event_id, student_id)
);
CREATE TABLE IF NOT EXISTS payments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  fine_id BIGINT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_method ENUM('CASH','GCASH','MAYA','BANK TRANSFER','OTHER') NOT NULL DEFAULT 'CASH',
  reference_number VARCHAR(100) NULL,
  remarks VARCHAR(255) NULL,
  paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  recorded_by INT NULL,
  FOREIGN KEY (fine_id) REFERENCES fines(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS clearance (
  student_id INT PRIMARY KEY,
  status ENUM('PENDING','ELIGIBLE','ON HOLD','SIGNED') NOT NULL DEFAULT 'PENDING',
  signed_at DATETIME NULL,
  signed_by INT NULL,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ssc_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  permissions JSON NULL,
  account_status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  account_status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS event_requests (event_request_id INT AUTO_INCREMENT PRIMARY KEY, ssc_user_id INT NOT NULL, event_name VARCHAR(180) NOT NULL, description TEXT NULL, event_type VARCHAR(80) NULL, venue VARCHAR(180) NULL, event_date DATE NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, scan_in_start TIME NOT NULL, scan_in_end TIME NOT NULL, scan_out_start TIME NOT NULL, scan_out_end TIME NOT NULL, fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0, status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING', admin_note VARCHAR(255) NULL, reviewed_by INT NULL, reviewed_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_event_requests_status (status), FOREIGN KEY (ssc_user_id) REFERENCES ssc_users(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('ADMIN','SSC','SYSTEM') NOT NULL DEFAULT 'ADMIN',
  actor_id INT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_logs_created (created_at)
);

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  notification_type VARCHAR(80) NOT NULL,
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ssc_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS attendance_corrections (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  attendance_id BIGINT NOT NULL,
  old_status VARCHAR(30) NOT NULL,
  new_status VARCHAR(30) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  corrected_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (attendance_id) REFERENCES event_attendance(attendance_id) ON DELETE CASCADE
);
