-- Rate limiting needs to see who asked, not just which number was asked for:
-- one attacker can burn through many phone numbers from a single IP, which a
-- per-phone cooldown alone never catches.
ALTER TABLE otp_codes
    ADD COLUMN ip_address VARCHAR(45) NULL AFTER purpose,
    ADD INDEX idx_otp_ip_created (ip_address, created_at),
    ADD INDEX idx_otp_phone_created (phone, created_at);
