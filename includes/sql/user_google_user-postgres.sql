--
-- extension Google Login SQL schema
--
CREATE TABLE user_google_user (
	user_googleid DECMIAL(25, 0) check (user_googleid > 0) NOT NULL PRIMARY KEY,
	user_id int check (user_id > 0) NOT NULL
);
CREATE INDEX(user_id);
