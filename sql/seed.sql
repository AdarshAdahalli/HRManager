-- Seed Data for InternHub
-- Default password for all seed accounts: Password123!
-- Hash: $2y$10$FrECgeTMbNsWM6feLI7RruBLhvv.werkCDi2UGcbtud9ZJ/6V1ZG2

INSERT INTO users (id, intern_id, name, email, password, plain_temp_password, role, department, country, timezone, status) VALUES
(1, 'HR-ADMIN', 'Sarah Jenkins (HR Lead)', 'hr@internhub.com', '$2y$10$FrECgeTMbNsWM6feLI7RruBLhvv.werkCDi2UGcbtud9ZJ/6V1ZG2', 'Password123!', 'hr', 'Talent Operations', 'United States', 'America/New_York', 'active'),
(2, 'INT-2026-001', 'Alex Rivera', 'alex@internhub.com', '$2y$10$FrECgeTMbNsWM6feLI7RruBLhvv.werkCDi2UGcbtud9ZJ/6V1ZG2', 'Password123!', 'intern', 'Frontend Engineering', 'United States', 'America/New_York', 'active'),
(3, 'INT-2026-002', 'Sofia Bennett', 'sofia@internhub.com', '$2y$10$FrECgeTMbNsWM6feLI7RruBLhvv.werkCDi2UGcbtud9ZJ/6V1ZG2', 'Password123!', 'intern', 'Backend Engineering', 'United Kingdom', 'Europe/London', 'active'),
(4, 'INT-2026-003', 'Aarav Patel', 'aarav@internhub.com', '$2y$10$FrECgeTMbNsWM6feLI7RruBLhvv.werkCDi2UGcbtud9ZJ/6V1ZG2', 'Password123!', 'intern', 'Cloud & DevOps', 'India', 'Asia/Kolkata', 'active'),
(5, 'INT-2026-004', 'Kenji Sato', 'kenji@internhub.com', '$2y$10$FrECgeTMbNsWM6feLI7RruBLhvv.werkCDi2UGcbtud9ZJ/6V1ZG2', 'Password123!', 'intern', 'Data & AI', 'Japan', 'Asia/Tokyo', 'active')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Sample attendance logs
INSERT INTO attendance_logs (user_id, login_time, logout_time, session_minutes, ip_address, status) VALUES
(2, DATE_SUB(NOW(), INTERVAL 4 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR), 180, '192.168.1.10', 'completed'),
(3, DATE_SUB(NOW(), INTERVAL 3 HOUR), NULL, 180, '192.168.1.15', 'active'),
(4, DATE_SUB(NOW(), INTERVAL 5 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR), 180, '192.168.1.20', 'completed'),
(5, DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL, 60, '192.168.1.25', 'active');

-- Sample tasks
INSERT INTO tasks (title, description, assigned_to, created_by, priority, status, progress_percent, due_date, notes) VALUES
('Implement OAuth2 Login Screen', 'Create a responsive login UI with Tailwind CSS and validate credentials against LDAP/Database.', 2, 1, 'high', 'in_progress', 65, DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY), 'Mockups approved, working on form validation.'),
('Optimize MySQL Query Indexing', 'Analyze slow query logs on the reporting tables and add composite indexes where necessary.', 3, 1, 'urgent', 'in_progress', 40, DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), 'Execution plan shows full table scan on analytics table.'),
('Setup Docker Compose for Local Dev', 'Write docker-compose.yml including Apache, PHP 8.2, MySQL 8.0, and phpMyAdmin containers.', 4, 1, 'medium', 'completed', 100, DATE_ADD(CURRENT_DATE(), INTERVAL -1 DAY), 'Docker container configuration verified and pushed to repo.'),
('Build Intern Heatmap Visualization', 'Generate dynamic SVG/Canvas matrix showing availability overlaps across timezones.', 2, 1, 'medium', 'pending', 0, DATE_ADD(CURRENT_DATE(), INTERVAL 5 DAY), 'Waiting for timezone API contract specification.'),
('Train Text Summarization Model', 'Fine-tune small BERT/LLM model for automatic ticket summarization in HR portal.', 5, 1, 'high', 'in_progress', 50, DATE_ADD(CURRENT_DATE(), INTERVAL 4 DAY), 'Dataset tokenized; training epoch 2/5 in progress.');

-- Sample difficulties reported by interns
INSERT INTO difficulties (user_id, task_id, title, description, severity, status, hr_response, resolved_by, resolved_at) VALUES
(3, 2, 'Blocked by database production replica permissions', 'I do not have read access to the production replica query logs needed to test the explain plan.', 'high', 'in_review', 'Requested temporary staging replica access from DevOps. Will ping you once ready.', 1, NULL),
(2, 4, 'Timezone ambiguity during Daylight Savings transition', 'Need clarification on whether to store user windows as standard UTC offsets or IANA timezone strings like America/New_York.', 'medium', 'resolved', 'Always store IANA timezone strings to automatically handle DST changes safely!', 1, NOW());

-- Intern availability windows (Mon-Fri)
-- Alex (New York - America/New_York): Available 09:00 to 13:00, 14:00 to 17:00 local time
INSERT INTO intern_availability (user_id, day_of_week, start_time, end_time, timezone) VALUES
(2, 1, '09:00:00', '13:00:00', 'America/New_York'),
(2, 1, '14:00:00', '17:00:00', 'America/New_York'),
(2, 2, '09:00:00', '13:00:00', 'America/New_York'),
(2, 2, '14:00:00', '17:00:00', 'America/New_York'),
(2, 3, '09:00:00', '13:00:00', 'America/New_York'),
(2, 3, '14:00:00', '17:00:00', 'America/New_York'),
(2, 4, '09:00:00', '13:00:00', 'America/New_York'),
(2, 4, '14:00:00', '17:00:00', 'America/New_York'),
(2, 5, '09:00:00', '13:00:00', 'America/New_York'),
(2, 5, '14:00:00', '17:00:00', 'America/New_York');

-- Sofia (London - Europe/London): Available 10:00 to 17:00 local time
INSERT INTO intern_availability (user_id, day_of_week, start_time, end_time, timezone) VALUES
(3, 1, '10:00:00', '17:00:00', 'Europe/London'),
(3, 2, '10:00:00', '17:00:00', 'Europe/London'),
(3, 3, '10:00:00', '17:00:00', 'Europe/London'),
(3, 4, '10:00:00', '17:00:00', 'Europe/London'),
(3, 5, '10:00:00', '17:00:00', 'Europe/London');

-- Aarav (India - Asia/Kolkata): Available 13:00 to 21:00 local time
INSERT INTO intern_availability (user_id, day_of_week, start_time, end_time, timezone) VALUES
(4, 1, '13:00:00', '21:00:00', 'Asia/Kolkata'),
(4, 2, '13:00:00', '21:00:00', 'Asia/Kolkata'),
(4, 3, '13:00:00', '21:00:00', 'Asia/Kolkata'),
(4, 4, '13:00:00', '21:00:00', 'Asia/Kolkata'),
(4, 5, '13:00:00', '21:00:00', 'Asia/Kolkata');

-- Kenji (Japan - Asia/Tokyo): Available 18:00 to 23:30 local time
INSERT INTO intern_availability (user_id, day_of_week, start_time, end_time, timezone) VALUES
(5, 1, '18:00:00', '23:30:00', 'Asia/Tokyo'),
(5, 2, '18:00:00', '23:30:00', 'Asia/Tokyo'),
(5, 3, '18:00:00', '23:30:00', 'Asia/Tokyo'),
(5, 4, '18:00:00', '23:30:00', 'Asia/Tokyo'),
(5, 5, '18:00:00', '23:30:00', 'Asia/Tokyo');
