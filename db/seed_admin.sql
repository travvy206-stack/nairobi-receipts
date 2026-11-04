-- Seed initial administrator account for NTA-Pay MySQL
-- Default password: admin123 (bcrypt hash: $2y$10$aIB3unljeym9iVjPmJumWOWurB/D63E/aoLaJRHV55OPknztYwuVe)

INSERT INTO users (full_name, phone_number, email, password_hash, role, status, store_name, mpesa_shortcode, mpesa_type)
VALUES ('System Administrator', '0700000000', 'admin@nta-pay.co.ke', '$2y$10$aIB3unljeym9iVjPmJumWOWurB/D63E/aoLaJRHV55OPknztYwuVe', 'admin', 'active', 'NTA Central Terminal', '174379', 'till')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

