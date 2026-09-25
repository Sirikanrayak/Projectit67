-- ระบบติดตามโครงงานนักเรียน — โครงสร้างฐานข้อมูล
-- รันอัตโนมัติเมื่อสร้างคอนเทนเนอร์ MySQL ครั้งแรก (docker-entrypoint-initdb.d)

SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE TABLE IF NOT EXISTS users (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(150) NOT NULL,
  email           VARCHAR(150) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  role            ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
  status          ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
  student_id      VARCHAR(30)  DEFAULT NULL,
  level           VARCHAR(20)  DEFAULT NULL,
  student_group   VARCHAR(20)  DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  `key`   VARCHAR(50) PRIMARY KEY,
  `value` VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  title           VARCHAR(255) NOT NULL,
  title_en        VARCHAR(255) NOT NULL DEFAULT '',
  code            VARCHAR(50)  NOT NULL DEFAULT '',
  type            VARCHAR(100) NOT NULL DEFAULT '',
  level           VARCHAR(20)  NOT NULL DEFAULT '',
  student_group   VARCHAR(20)  NOT NULL DEFAULT '',
  advisor         VARCHAR(150) NOT NULL DEFAULT '',
  co_advisor      VARCHAR(150) NOT NULL DEFAULT '',
  start_date      DATE DEFAULT NULL,
  due_date        DATE DEFAULT NULL,
  note            TEXT,
  member_names    JSON DEFAULT NULL,
  steps           JSON NOT NULL,
  evaluation      JSON DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_members (
  project_id  INT NOT NULL,
  user_id     INT NOT NULL,
  PRIMARY KEY (project_id, user_id),
  CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pm_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_logs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  project_id  INT NOT NULL,
  log_date    DATE NOT NULL,
  text        TEXT NOT NULL,
  by_name     VARCHAR(150) NOT NULL DEFAULT '',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pl_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_files (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  project_id     INT NOT NULL,
  stored_name    VARCHAR(255) NOT NULL,
  original_name  VARCHAR(255) NOT NULL,
  size           INT NOT NULL,
  mime_type      VARCHAR(100) NOT NULL DEFAULT '',
  uploaded_by    VARCHAR(150) NOT NULL DEFAULT '',
  uploaded_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pf_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (`key`, `value`) VALUES ('require_approval', '1');
