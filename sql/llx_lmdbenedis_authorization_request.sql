-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

CREATE TABLE IF NOT EXISTS llx_lmdbenedis_authorization_request(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_prm integer NOT NULL,
	state_hash char(64) NOT NULL,
	duration varchar(16) NOT NULL,
	status varchar(16) DEFAULT 'pending' NOT NULL,
	usage_point_id varchar(14),
	code_hash char(64),
	expires_at datetime NOT NULL,
	completed_at datetime,
	error_code varchar(64),
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer
) ENGINE=innodb;
