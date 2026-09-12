ALTER TABLE users
    ADD COLUMN firebase_uid VARCHAR(128) NULL AFTER id,
    ADD UNIQUE KEY uq_users_firebase_uid (firebase_uid);
