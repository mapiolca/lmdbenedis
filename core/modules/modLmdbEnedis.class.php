<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        core/modules/modLmdbEnedis.class.php
 * \ingroup     lmdbenedis
 * \brief       Descriptor for the LMDB Enedis module.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Module descriptor.
 */
class modLmdbEnedis extends DolibarrModules
{
	/** @var string Module author */
	public $author = 'Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>';
	/** @var string SPDX-compatible license identifier */
	public $license = 'GPL-3.0-or-later';

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->numero = 450030;
		$this->rights_class = 'lmdbenedis';
		$this->family = 'Les Métiers du Bâtiment';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'LmdbEnedisModuleDescription';
		$this->descriptionlong = 'LmdbEnedisModuleDescriptionLong';
		$this->version = '1.2.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-bolt';
		$this->editor_name = 'Les Métiers du Bâtiment';
		$this->editor_url = 'https://lesmetiersdubatiment.fr';

		$this->module_parts = array();
		$this->dirs = array();
		$this->config_page_url = array('setup.php@lmdbenedis');
		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->phpmin = array(8, 0);
		$this->need_dolibarr_version = array(20, 0);
		$this->langfiles = array('lmdbenedis@lmdbenedis');

		$this->const = array(
			1 => array('LMDBENEDIS_API_BASE_URL', 'chaine', 'https://gw.ext.prod.api.enedis.fr/mesure_synchrone_auto/v1', 'Mesures V1 base URL', 0, 'current', 1),
			2 => array('LMDBENEDIS_TOKEN_URL', 'chaine', 'https://gw.ext.prod.api.enedis.fr/oauth2/v3/token', 'OAuth2 token URL', 0, 'current', 1),
			3 => array('LMDBENEDIS_CLIENT_ID', 'chaine', '', 'Data Connect client identifier', 0, 'current', 1),
			4 => array('LMDBENEDIS_CLIENT_SECRET', 'chaine', '', 'Encrypted Data Connect client secret', 0, 'current', 1),
			5 => array('LMDBENEDIS_BACKFILL_DAYS', 'chaine', '30', 'Initial synchronization history in days', 0, 'current', 1),
			6 => array('LMDBENEDIS_SYNC_LAG_DAYS', 'chaine', '2', 'Safety delay before automatic synchronization', 0, 'current', 1),
			7 => array('LMDBENEDIS_HTTP_TIMEOUT', 'chaine', '60', 'HTTP response timeout in seconds', 0, 'current', 1),
			8 => array('LMDBENEDIS_CRON_MAX_PRMS', 'chaine', '50', 'Maximum number of PRMs per cron execution', 0, 'current', 1),
			9 => array('LMDBENEDIS_ENVIRONMENT', 'chaine', 'sandbox', 'Data Connect environment', 0, 'current', 1),
			10 => array('LMDBENEDIS_AUTHORIZE_URL', 'chaine', 'https://mon-compte-particulier.enedis.fr/dataconnect/v2/oauth2/authorize', 'Data Connect 2026 authorization URL', 0, 'current', 1),
			11 => array('LMDBENEDIS_AUTHORIZATION_DURATION', 'chaine', 'P3Y', 'Requested Data Connect authorization duration', 0, 'current', 1),
			12 => array('LMDBENEDIS_SUBSCRIBED_SERVICES_URL', 'chaine', 'https://gw.ext.prod.api.enedis.fr/subscribed_services/v1', 'Data Connect Services souscrits V1 URL', 0, 'current', 1),
		);

		$this->tabs = array();
		$this->tabs[] = array(
			'data' => 'powerplant@powerplantpv:+lmdbenedis_measurements:LmdbEnedisMeasurements:lmdbenedis@lmdbenedis:isModEnabled("powerplantpv") && isModEnabled("lmdbenedis") && ($user->admin || $user->hasRight("powerplantpv", "powerplant", "read")) && ($user->admin || $user->hasRight("lmdbenedis", "prm", "read") || (isModEnabled("multicompany") && ($user->hasRight("multicompany", "entities", "write") || $user->hasRight("multicompany", "setup", "write") || $user->hasRight("multicompany", "admin", "write")))):/lmdbenedis/powerplant_measure.php?id=__ID__',
		);

		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array(
			0 => array(
				'label' => 'LmdbEnedisCronSyncLabel',
				'jobtype' => 'method',
				'class' => '/lmdbenedis/class/lmdbenediscron.class.php',
				'objectname' => 'LmdbEnedisCron',
				'method' => 'runSync',
				'parameters' => '',
				'comment' => 'LmdbEnedisCronSyncComment',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 0,
				'test' => 'isModEnabled("lmdbenedis")',
				'priority' => 50,
			),
		);

		$this->rights = array();
		$r = 0;
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'LmdbEnedisRightRead';
		$this->rights[$r][4] = 'prm';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'LmdbEnedisRightWrite';
		$this->rights[$r][4] = 'prm';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'LmdbEnedisRightDelete';
		$this->rights[$r][4] = 'prm';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'LmdbEnedisRightSync';
		$this->rights[$r][4] = 'prm';
		$this->rights[$r][5] = 'sync';

		$this->menu = array();
		$this->menu[] = array(
			'fk_menu' => 'fk_mainmenu=tools',
			'type' => 'left',
			'titre' => 'LmdbEnedisPrmMenu',
			'mainmenu' => 'tools',
			'leftmenu' => 'lmdbenedis_prm',
			'url' => '/lmdbenedis/prm_list.php',
			'langs' => 'lmdbenedis@lmdbenedis',
			'position' => 150,
			'enabled' => 'isModEnabled("lmdbenedis")',
			'perms' => '$user->admin || $user->hasRight("lmdbenedis", "prm", "read") || (isModEnabled("multicompany") && ($user->hasRight("multicompany", "entities", "write") || $user->hasRight("multicompany", "setup", "write") || $user->hasRight("multicompany", "admin", "write")))',
			'target' => '',
			'user' => 2,
		);
	}

	/**
	 * Initialize module.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function init($options = '')
	{
		$sql = array();
		$result = $this->_load_tables('/lmdbenedis/sql/');
		if ($result < 0) {
			return -1;
		}
		if ($this->ensureDatabaseSchema() < 0) {
			return -1;
		}
		if ($this->ensureDatabaseIndexes() < 0) {
			return -1;
		}
		$result = $this->_init($sql, $options);
		if ($result <= 0) {
			return $result;
		}
		if ($this->migrateDataConnect2026Settings() < 0) {
			return -1;
		}

		return $result;
	}

	/**
	 * Add columns introduced after the initial module release.
	 *
	 * @return int 1 on success, -1 on error
	 */
	private function ensureDatabaseSchema()
	{
		$table = MAIN_DB_PREFIX.'lmdbenedis_authorization_request';
		$sql = 'SHOW COLUMNS FROM '.$table." LIKE 'authorization_id_hash'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$exists = $this->db->num_rows($resql) > 0;
		$this->db->free($resql);
		if (!$exists && !$this->db->query('ALTER TABLE '.$table.' ADD authorization_id_hash char(64) AFTER code_hash')) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Migrate only the exact legacy default; preserve every custom endpoint.
	 *
	 * @return int 1 on success, -1 on error
	 */
	private function migrateDataConnect2026Settings()
	{
		global $conf;

		$legacyAuthorizeUrl = 'https://mon-compte-particulier.enedis.fr/dataconnect/v1/oauth2/authorize';
		if (getDolGlobalString('LMDBENEDIS_AUTHORIZE_URL') !== $legacyAuthorizeUrl) {
			return 1;
		}
		$result = dolibarr_set_const(
			$this->db,
			'LMDBENEDIS_AUTHORIZE_URL',
			'https://mon-compte-particulier.enedis.fr/dataconnect/v2/oauth2/authorize',
			'chaine',
			0,
			'Data Connect 2026 authorization URL',
			(int) $conf->entity
		);
		if ($result <= 0) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Install missing indexes without emitting duplicate-key errors on reactivation.
	 *
	 * @return int 1 on success, -1 on error
	 */
	private function ensureDatabaseIndexes()
	{
		/** @var array<int,array{table:string,name:string,columns:string,unique:bool}> $indexes */
		$indexes = array(
			array('table' => 'lmdbenedis_authorization_request', 'name' => 'uk_lmdbenedis_authreq_state', 'columns' => 'state_hash', 'unique' => true),
			array('table' => 'lmdbenedis_authorization_request', 'name' => 'idx_lmdbenedis_authreq_entity', 'columns' => 'entity', 'unique' => false),
			array('table' => 'lmdbenedis_authorization_request', 'name' => 'idx_lmdbenedis_authreq_prm', 'columns' => 'fk_prm', 'unique' => false),
			array('table' => 'lmdbenedis_authorization_request', 'name' => 'idx_lmdbenedis_authreq_status', 'columns' => 'entity, status, expires_at', 'unique' => false),
			array('table' => 'lmdbenedis_measure', 'name' => 'uk_lmdbenedis_measure_stable', 'columns' => 'entity, fk_prm, resource_code, data_key', 'unique' => true),
			array('table' => 'lmdbenedis_measure', 'name' => 'idx_lmdbenedis_measure_entity', 'columns' => 'entity', 'unique' => false),
			array('table' => 'lmdbenedis_measure', 'name' => 'idx_lmdbenedis_measure_prm', 'columns' => 'fk_prm', 'unique' => false),
			array('table' => 'lmdbenedis_measure', 'name' => 'idx_lmdbenedis_measure_resource_date', 'columns' => 'resource_code, measure_date', 'unique' => false),
			array('table' => 'lmdbenedis_measure', 'name' => 'idx_lmdbenedis_measure_prm_date', 'columns' => 'fk_prm, measure_date', 'unique' => false),
			array('table' => 'lmdbenedis_prm', 'name' => 'uk_lmdbenedis_prm_entity_usagepoint', 'columns' => 'entity, usage_point_id', 'unique' => true),
			array('table' => 'lmdbenedis_prm', 'name' => 'idx_lmdbenedis_prm_entity', 'columns' => 'entity', 'unique' => false),
			array('table' => 'lmdbenedis_prm', 'name' => 'idx_lmdbenedis_prm_soc', 'columns' => 'fk_soc', 'unique' => false),
			array('table' => 'lmdbenedis_prm', 'name' => 'idx_lmdbenedis_prm_powerplant', 'columns' => 'fk_powerplant', 'unique' => false),
			array('table' => 'lmdbenedis_prm', 'name' => 'idx_lmdbenedis_prm_status', 'columns' => 'status', 'unique' => false),
			array('table' => 'lmdbenedis_prm_stream', 'name' => 'uk_lmdbenedis_prm_stream', 'columns' => 'entity, fk_prm, resource_code', 'unique' => true),
			array('table' => 'lmdbenedis_prm_stream', 'name' => 'idx_lmdbenedis_prm_stream_entity', 'columns' => 'entity', 'unique' => false),
			array('table' => 'lmdbenedis_prm_stream', 'name' => 'idx_lmdbenedis_prm_stream_prm', 'columns' => 'fk_prm', 'unique' => false),
			array('table' => 'lmdbenedis_prm_stream', 'name' => 'idx_lmdbenedis_prm_stream_active', 'columns' => 'active', 'unique' => false),
			array('table' => 'lmdbenedis_prm_stream', 'name' => 'idx_lmdbenedis_prm_stream_cursor', 'columns' => 'cursor_date', 'unique' => false),
		);

		foreach ($indexes as $index) {
			$table = MAIN_DB_PREFIX.$index['table'];
			$sql = 'SHOW INDEX FROM '.$table." WHERE Key_name = '".$this->db->escape($index['name'])."'";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$exists = $this->db->num_rows($resql) > 0;
			$this->db->free($resql);
			if ($exists) {
				continue;
			}

			$sql = 'ALTER TABLE '.$table.' ADD '.($index['unique'] ? 'UNIQUE ' : '').'INDEX '.$index['name'].' ('.$index['columns'].')';
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Disable module while preserving entity settings.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function remove($options = '')
	{
		$sql = array();
		$declaredConstants = $this->const;
		$this->const = array();
		$result = $this->_remove($sql, $options);
		$this->const = $declaredConstants;

		return $result;
	}
}
