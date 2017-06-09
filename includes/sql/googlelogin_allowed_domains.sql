--
-- extension Google Login SQL schema
--
CREATE TABLE /*$wgDBprefix*/googlelogin_allowed_domains (
  gl_allowed_domain_id int unsigned AUTO_INCREMENT NOT NULL PRIMARY KEY,
  gl_allowed_domain varchar(255) NOT NULL,
  KEY(gl_allowed_domain)
) /*$wgDBTableOptions*/;
