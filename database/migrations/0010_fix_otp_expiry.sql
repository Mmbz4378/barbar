-- MariaDB auto-applies DEFAULT/ON UPDATE CURRENT_TIMESTAMP to the first
-- TIMESTAMP column of a table when none is declared. That silently made
-- every UPDATE on otp_codes (incrementing `attempts`) reset `expires_at`
-- to NOW() — so a single mistyped digit instantly expired an otherwise
-- valid code and the user was told to request a new one.
-- DATETIME is not subject to that rule.
ALTER TABLE otp_codes
    MODIFY expires_at DATETIME NOT NULL;

DELETE FROM otp_codes WHERE purpose = 'test';
