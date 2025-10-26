

-- 1) Create database
CREATE DATABASE IF NOT EXISTS eventConnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eventConnect;

-- 2) Roles table
CREATE TABLE IF NOT EXISTS roles (
  id TINYINT UNSIGNED PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  slug VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Seed roles (aligned names/slugs)
INSERT INTO roles (id, name, slug) VALUES
  (1, 'student', 'student'),
  (2, 'student_council', 'student_council'),
  (3, 'student_affair', 'student_affair'),
  (4, 'club_admin', 'club_admin')
ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug);

-- 3) Clubs table (new)
CREATE TABLE IF NOT EXISTS clubs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional seed clubs
INSERT IGNORE INTO clubs (name) VALUES
 ('Basketball Club'),
 ('Photography Club'),
 ('Debate Club');

-- 4) Users table
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role_id TINYINT UNSIGNED NOT NULL,
  student_id VARCHAR(50) DEFAULT NULL,
  contact VARCHAR(50) DEFAULT NULL,
  course VARCHAR(100) DEFAULT NULL,
  club_name VARCHAR(255) DEFAULT NULL, -- legacy; will be superseded by club_id
  club_id INT UNSIGNED DEFAULT NULL,   -- new FK to clubs
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
  CONSTRAINT fk_users_club FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5) Email verification table (OTP)
CREATE TABLE IF NOT EXISTS email_verifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  otp_code VARCHAR(6) NOT NULL,
  expires_at DATETIME NOT NULL,
  verified TINYINT(1) DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6) Password resets
CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7) Multi-role link table
CREATE TABLE IF NOT EXISTS user_roles (
  user_id INT UNSIGNED NOT NULL,
  role_id TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Migrate single-role assignments into user_roles (idempotent)
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT id, role_id FROM users;

-- 8) Events table (bind to clubs)
CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    organizer VARCHAR(255) NOT NULL, -- legacy; UI will shift to clubs
    description TEXT,
    venue VARCHAR(255) NOT NULL,
    start_at TIME NOT NULL,
    end_at TIME NOT NULL,
    event_date DATE NOT NULL,
    poster_path VARCHAR(255) DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    club_id INT UNSIGNED DEFAULT NULL, -- new FK to clubs (auto-set by admin's assigned club)
    status ENUM('pending_council','pending_affair','approved','rejected') DEFAULT 'pending_council',
    council_comment TEXT DEFAULT NULL,
    affair_comment TEXT DEFAULT NULL,
    delete_requested TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_events_club FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9) Event participants
CREATE TABLE IF NOT EXISTS event_participants (
    user_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, event_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10) Feedbacks (with survey JSON and uniqueness per user/event)
CREATE TABLE IF NOT EXISTS feedbacks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    survey_json JSON DEFAULT NULL, -- new: store structured survey answers
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_feedback_user_event (user_id, event_id) -- ensure one feedback per user per event
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Example: assign Student Affairs role to a specific user
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT id, (SELECT id FROM roles WHERE slug = 'student_affair')
FROM users
WHERE email = 'your_affair_user@example.com';

-- 11) Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12) Helpful indexes for analytics and performance
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role_id);
CREATE INDEX IF NOT EXISTS idx_users_club ON users(club_id);
CREATE INDEX IF NOT EXISTS idx_events_club ON events(club_id);
CREATE INDEX IF NOT EXISTS idx_events_status ON events(status);
CREATE INDEX IF NOT EXISTS idx_ep_event ON event_participants(event_id);
CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications(user_id, is_read);