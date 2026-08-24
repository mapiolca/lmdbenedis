<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/lmdbenedisconfig.class.php';
require_once __DIR__.'/lmdbenedissynchronizer.class.php';

/**
 * Native scheduled job entry point.
 */
class LmdbEnedisCron
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';
	/** @var array<int,string> */
	public $errors = array();
	/** @var string */
	public $output = '';

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @return int 1 on success, -1 on error
	 */
	public function runSync()
	{
		global $langs;

		$langs->load('lmdbenedis@lmdbenedis');
		if (!isModEnabled('lmdbenedis')) {
			$this->error = $langs->trans('LmdbEnedisModuleDisabled');
			return -1;
		}
		if (!LmdbEnedisConfig::isConnectionConfigured()) {
			$this->error = $langs->trans('LmdbEnedisConnectionNotConfigured');
			return -1;
		}

		$synchronizer = new LmdbEnedisSynchronizer($this->db);
		$result = $synchronizer->syncAll();
		$this->output = $synchronizer->output;
		$this->error = $synchronizer->error;
		$this->errors = $synchronizer->errors;

		return $result < 0 || $result > 0 ? -1 : 1;
	}
}
