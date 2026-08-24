-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

CREATE TABLE IF NOT EXISTS llx_lmdbenedis_prm(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	usage_point_id varchar(14) NOT NULL,
	label varchar(255) DEFAULT '' NOT NULL,
	fk_soc integer,
	fk_powerplant integer,
	authorization_reference varchar(128),
	authorization_end date,
	status smallint DEFAULT 1 NOT NULL,
	last_sync_at datetime,
	last_sync_status varchar(32),
	last_sync_message text,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer
) ENGINE=innodb;
