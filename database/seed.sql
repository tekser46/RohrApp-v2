-- ═══════════════════════════════════════════════
-- RohrApp+ v2 — Seed Data
-- ═══════════════════════════════════════════════

-- ── Packages ──
INSERT INTO packages (slug, name, description, price_monthly, features, max_sipgate_numbers, max_websites, has_email_inbox, has_call_logs, has_messages, sort_order) VALUES
('demo', 'Demo', 'Kostenloser Einstieg — entdecken Sie RohrApp+ ohne Risiko.', 0.00,
 '{"dashboard":true,"profile":true,"license_view":true}',
 0, 1, 0, 0, 1, 1),

('starter', 'Starter', 'Für kleine Betriebe — Telefonie, E-Mail und Kundenkontakt.', 49.00,
 '{"dashboard":true,"profile":true,"license_view":true,"email_inbox":true,"call_logs":true,"messages":true,"sipgate":true}',
 3, 3, 1, 1, 1, 2),

('professional', 'Professional', 'Für wachsende Unternehmen — alle Funktionen, volle Kontrolle.', 99.00,
 '{"dashboard":true,"profile":true,"license_view":true,"email_inbox":true,"call_logs":true,"messages":true,"sipgate":true,"priority_support":true}',
 10, 10, 1, 1, 1, 3);

-- ── Admin User (password: admin123) ──
INSERT INTO users (email, password_hash, role, is_active, email_verified_at) VALUES
('admin@rohrapp.de', '$2y$12$6.J.htpj5ehKIH2bIurhD.aDl3LlXbA8MrLH2P3G5OvRlJxXvqFoq', 'admin', 1, NOW());

-- Admin profile
INSERT INTO user_profiles (user_id, first_name, last_name, company_name) VALUES
(1, 'System', 'Administrator', 'RohrApp+ GmbH');

-- Admin license (Professional, no expiry)
INSERT INTO user_licenses (user_id, package_id, license_key, status, starts_at) VALUES
(1, 3, UPPER(CONCAT(
    SUBSTRING(MD5(RAND()), 1, 4), '-',
    SUBSTRING(MD5(RAND()), 1, 4), '-',
    SUBSTRING(MD5(RAND()), 1, 4), '-',
    SUBSTRING(MD5(RAND()), 1, 4)
)), 'active', NOW());
