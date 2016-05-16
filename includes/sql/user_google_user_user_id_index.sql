--
-- extension Google Login SQL schema update. Remove primary key
--
ALTER TABLE /*$wgDBprefix*/user_google_user ADD INDEX (user_id);
