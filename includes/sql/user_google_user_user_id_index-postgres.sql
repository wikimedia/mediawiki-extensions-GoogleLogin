--
-- extension Google Login SQL schema update. Add index on user_id
--
ALTER TABLE user_google_user ADD CREATE INDEX (user_id);
