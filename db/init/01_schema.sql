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
  `value` TEXT
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
  showcase_enabled TINYINT(1) NOT NULL DEFAULT 0,
  showcase_image   VARCHAR(255) NOT NULL DEFAULT '',
  showcase_link    VARCHAR(255) NOT NULL DEFAULT '',
  showcase_desc    TEXT,
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

CREATE TABLE IF NOT EXISTS teacher_assignments (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  level          VARCHAR(20) NOT NULL,
  student_group  VARCHAR(20) NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_teacher_level_group (user_id, level, student_group),
  CONSTRAINT fk_ta_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_qc (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  project_id    INT NOT NULL,
  step_key      VARCHAR(20) NOT NULL,
  status        ENUM('pass','fail') NOT NULL,
  signed_by     VARCHAR(150) NOT NULL DEFAULT '',
  signed_date   DATE DEFAULT NULL,
  note          TEXT,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_project_step (project_id, step_key),
  CONSTRAINT fk_qc_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_proposals (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  student_id           INT NOT NULL,
  level                VARCHAR(20) NOT NULL DEFAULT '',
  student_group        VARCHAR(20) NOT NULL DEFAULT '',
  member_emails        TEXT,
  status               ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  approved_project_id  INT DEFAULT NULL,
  reviewed_by          VARCHAR(150) NOT NULL DEFAULT '',
  reviewed_at          DATETIME DEFAULT NULL,
  review_note          TEXT,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pp_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_project FOREIGN KEY (approved_project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_proposal_items (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  proposal_id   INT NOT NULL,
  seq           TINYINT NOT NULL,
  title         VARCHAR(255) NOT NULL,
  method        TEXT,
  benefit       TEXT,
  is_selected   TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_ppi_proposal FOREIGN KEY (proposal_id) REFERENCES project_proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_progress_rounds (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  project_id      INT NOT NULL,
  round_no        TINYINT NOT NULL,
  plan            TEXT,
  submitted_work  TEXT,
  submitted_date  DATE DEFAULT NULL,
  rating          VARCHAR(10) DEFAULT NULL,
  evaluated_by    VARCHAR(150) NOT NULL DEFAULT '',
  evaluated_date  DATE DEFAULT NULL,
  UNIQUE KEY uniq_project_round (project_id, round_no),
  CONSTRAINT fk_ppr_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_defense_requests (
  project_id       INT PRIMARY KEY,
  requested_by     VARCHAR(150) NOT NULL DEFAULT '',
  requested_date   DATE DEFAULT NULL,
  instructor_note  TEXT,
  instructor_by    VARCHAR(150) NOT NULL DEFAULT '',
  instructor_date  DATE DEFAULT NULL,
  CONSTRAINT fk_pdr_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (`key`, `value`) VALUES
  ('require_approval', '1'),
  ('site_name', 'ระบบติดตามโครงงานนักเรียน'),
  ('site_subtitle', 'สาขาวิชาเทคโนโลยีสารสนเทศ · วิทยาลัยเทคนิคนครนายก'),
  ('site_logo', ''),
  ('site_logo_text', 'IT'),
  ('footer_text', '©ศิริกัลยา 2565 แผนกวิชาเทคโนโลยีสารสนเทศ วิทยาลัยเทคนิคนครนายก · ข้อมูลบันทึกลงฐานข้อมูล MySQL');
