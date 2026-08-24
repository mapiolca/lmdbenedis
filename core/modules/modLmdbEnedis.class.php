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
		$this->version = '1.0.0';
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

		return $this->_init($sql, $options);
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
