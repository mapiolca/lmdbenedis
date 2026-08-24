-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

CREATE TABLE IF NOT EXISTS llx_lmdbenedis_prm_stream(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_prm integer NOT NULL,
	resource_code varchar(64) NOT NULL,
	active smallint DEFAULT 1 NOT NULL,
	cursor_date datetime,
	last_attempt_at datetime,
	last_success_at datetime,
	last_http_code integer,
	last_error text,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer
) ENGINE=innodb;
