-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

CREATE TABLE IF NOT EXISTS llx_lmdbenedis_measure(
	rowid bigint AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_prm integer NOT NULL,
	resource_code varchar(64) NOT NULL,
	data_key char(64) NOT NULL,
	measure_date datetime NOT NULL,
	value double(24,8) NOT NULL,
	unit varchar(32) DEFAULT '' NOT NULL,
	quality varchar(32),
	reading_context varchar(32),
	interval_length varchar(16),
	measure_type varchar(16),
	flow_direction varchar(32),
	measurement_kind varchar(64),
	aggregate_kind varchar(32),
	measuring_period varchar(16),
	calendar_id varchar(128),
	calendar_label varchar(255),
	temporal_class_id varchar(128),
	temporal_class_label varchar(255),
	quadrant_id varchar(128),
	source_start datetime,
	source_end datetime,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
