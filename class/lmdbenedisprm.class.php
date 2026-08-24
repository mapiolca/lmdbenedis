<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once __DIR__.'/lmdbenedisclient.class.php';

/**
 * Authorized Enedis usage point.
 */
class LmdbEnedisPrm extends CommonObject
{
	public $module = 'lmdbenedis';
	public $element = 'lmdbenedis_prm';
	public $table_element = 'lmdbenedis_prm';
	public $picto = 'fa-bolt';
	public $ismultientitymanaged = 1;

	/** @var array<string,array<string,mixed>> */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2, 'default' => 1),
		'usage_point_id' => array('type' => 'varchar(14)', 'label' => 'LmdbEnedisUsagePointId', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'default' => ''),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'fk_powerplant' => array('type' => 'integer', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'authorization_reference' => array('type' => 'varchar(128)', 'label' => 'LmdbEnedisAuthorizationReference', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'authorization_end' => array('type' => 'date', 'label' => 'LmdbEnedisAuthorizationEnd', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'status' => array('type' => 'smallint', 'label' => 'Status', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'last_sync_at' => array('type' => 'datetime', 'label' => 'LmdbEnedisLastSync', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => 1),
		'last_sync_status' => array('type' => 'varchar(32)', 'label' => 'LmdbEnedisLastSyncStatus', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => 1),
		'last_sync_message' => array('type' => 'text', 'label' => 'LmdbEnedisLastSyncMessage', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
	);

	public $rowid;
	public $entity;
	public $usage_point_id = '';
	public $label = '';
	public $fk_soc;
	public $fk_powerplant;
	public $authorization_reference = '';
	public $authorization_end;
	public $status = 1;
	public $last_sync_at;
	public $last_sync_status = '';
	public $last_sync_message = '';
	public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @param int         $id  ID
	 * @param string|null $ref PRM
	 * @return int
	 */
	public function fetch($id, $ref = null)
	{
		global $conf;

		return $this->fetchCommon($id, $ref, ' AND t.entity = '.((int) $conf->entity));
	}

	/**
	 * @param User $user      User
	 * @param int  $notrigger Disable trigger
	 * @return int
	 */
	public function create(User $user, $notrigger = 0)
	{
		global $conf;

		if (!$this->validateForSave()) {
			return -1;
		}
		$this->entity = (int) $conf->entity;
		$this->date_creation = dol_now();
		$this->fk_user_creat = (int) $user->id;
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbenedis_prm (entity, usage_point_id, label, fk_soc, fk_powerplant, authorization_reference, authorization_end, status, date_creation, fk_user_creat) VALUES (';
		$sql .= ((int) $this->entity).", '".$this->db->escape($this->usage_point_id)."', '".$this->db->escape($this->label)."', ";
		$sql .= $this->nullableInt($this->fk_soc).', '.$this->nullableInt($this->fk_powerplant).", '".$this->db->escape($this->authorization_reference)."', ";
		$sql .= $this->nullableDate($this->authorization_end).', '.((int) $this->status).", '".$this->db->idate($this->date_creation)."', ".((int) $this->fk_user_creat).')';

		$this->db->begin();
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			$this->db->rollback();
			return -1;
		}
		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'lmdbenedis_prm');
		if (!$notrigger && $this->call_trigger('LMDBENEDIS_PRM_CREATE', $user) < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		return $this->id;
	}

	/**
	 * @param User $user      User
	 * @param int  $notrigger Disable trigger
	 * @return int
	 */
	public function update(User $user, $notrigger = 0)
	{
		global $conf, $langs;

		$langs->load('lmdbenedis@lmdbenedis');
		if ($this->id <= 0 || (int) $this->entity !== (int) $conf->entity) {
			$this->error = $langs->trans('ErrorRecordNotFound');
			$this->errors[] = $this->error;
			return -1;
		}
		if (!$this->validateForSave()) {
			return -1;
		}
		$old = new self($this->db);
		if ($old->fetch((int) $this->id) <= 0) {
			$this->error = $langs->trans('ErrorRecordNotFound');
			$this->errors[] = $this->error;
			return -1;
		}
		$this->oldcopy = $old;
		$this->fk_user_modif = (int) $user->id;
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbenedis_prm SET';
		$sql .= " usage_point_id = '".$this->db->escape($this->usage_point_id)."'";
		$sql .= ", label = '".$this->db->escape($this->label)."'";
		$sql .= ', fk_soc = '.$this->nullableInt($this->fk_soc);
		$sql .= ', fk_powerplant = '.$this->nullableInt($this->fk_powerplant);
		$sql .= ", authorization_reference = '".$this->db->escape($this->authorization_reference)."'";
		$sql .= ', authorization_end = '.$this->nullableDate($this->authorization_end);
		$sql .= ', status = '.((int) $this->status);
		$sql .= ', fk_user_modif = '.((int) $this->fk_user_modif);
		$sql .= ' WHERE rowid = '.((int) $this->id).' AND entity = '.((int) $conf->entity);

		$this->db->begin();
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			$this->db->rollback();
			return -1;
		}
		$this->context['trigger_reason'] = 'prm_update';
		$this->context['changed_fields'] = $this->changedFields($old);
		if (!$notrigger && $this->call_trigger('LMDBENEDIS_PRM_UPDATE', $user) < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		return 1;
	}

	/**
	 * @param User $user      User
	 * @param int  $notrigger Disable trigger
	 * @return int
	 */
	public function delete(User $user, $notrigger = 0)
	{
		global $conf;

		if ($this->id <= 0 || (int) $this->entity !== (int) $conf->entity) {
			return -1;
		}
		$this->db->begin();
		if (!$notrigger && $this->call_trigger('LMDBENEDIS_PRM_DELETE', $user) < 0) {
			$this->db->rollback();
			return -1;
		}
		foreach (array('lmdbenedis_measure', 'lmdbenedis_prm_stream') as $table) {
			$sql = 'DELETE FROM '.MAIN_DB_PREFIX.$table.' WHERE fk_prm = '.((int) $this->id).' AND entity = '.((int) $conf->entity);
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->errors[] = $this->error;
				$this->db->rollback();
				return -1;
			}
		}
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'lmdbenedis_prm WHERE rowid = '.((int) $this->id).' AND entity = '.((int) $conf->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		return 1;
	}

	/**
	 * Replace the enabled resource list without losing synchronization cursors.
	 *
	 * @param array<int,string> $resourceCodes Resource codes
	 * @param User              $user          User
	 * @return int
	 */
	public function setEnabledStreams($resourceCodes, User $user)
	{
		global $conf, $langs;

		$langs->load('lmdbenedis@lmdbenedis');
		$allowed = array_keys(LmdbEnedisClient::getResourcePaths());
		$submittedCodes = array_values(array_unique(array_map('strval', $resourceCodes)));
		$resourceCodes = array_values(array_intersect($submittedCodes, $allowed));
		if ($resourceCodes === array() || count($submittedCodes) !== count($resourceCodes)) {
			$this->error = $langs->trans('LmdbEnedisInvalidResourceSelection');
			$this->errors[] = $this->error;
			return -1;
		}
		$this->db->begin();
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbenedis_prm_stream SET active = 0, fk_user_modif = '.((int) $user->id);
		$sql .= ' WHERE fk_prm = '.((int) $this->id).' AND entity = '.((int) $conf->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			$this->db->rollback();
			return -1;
		}
		foreach ($resourceCodes as $resourceCode) {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbenedis_prm_stream (entity, fk_prm, resource_code, active, date_creation, fk_user_creat, fk_user_modif) VALUES (';
			$sql .= ((int) $conf->entity).', '.((int) $this->id).", '".$this->db->escape($resourceCode)."', 1, '".$this->db->idate(dol_now())."', ".((int) $user->id).', '.((int) $user->id).')';
			$sql .= ' ON DUPLICATE KEY UPDATE active = 1, fk_user_modif = '.((int) $user->id);
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->errors[] = $this->error;
				$this->db->rollback();
				return -1;
			}
		}
		$this->db->commit();

		return 1;
	}

	/**
	 * @return array<int,string>
	 */
	public function getEnabledStreams()
	{
		global $conf;

		$resources = array();
		$sql = 'SELECT resource_code FROM '.MAIN_DB_PREFIX.'lmdbenedis_prm_stream';
		$sql .= ' WHERE fk_prm = '.((int) $this->id).' AND entity = '.((int) $conf->entity).' AND active = 1 ORDER BY resource_code';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $resources;
		}
		while (is_object($row = $this->db->fetch_object($resql))) {
			$resources[] = (string) $row->resource_code;
		}
		$this->db->free($resql);

		return $resources;
	}

	/**
	 * @param int    $withpicto Include picto
	 * @param string $option    Link option
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '')
	{
		$label = dol_escape_htmltag($this->usage_point_id);
		if ($withpicto) {
			$label = img_picto('', $this->picto).' '.$label;
		}
		if ($option === 'nolink') {
			return $label;
		}

		return '<a href="'.dol_buildpath('/lmdbenedis/prm_card.php', 1).'?id='.(int) $this->id.'">'.$label.'</a>';
	}

	/**
	 * @param int $mode Display mode
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 * @param int $status Status
	 * @param int $mode   Display mode
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;

		return dolGetStatus($langs->trans($status ? 'Enabled' : 'Disabled'), '', '', $status ? 'status4' : 'status5', $mode);
	}

	/** @return bool */
	private function validateForSave()
	{
		global $conf, $langs;

		$langs->load('lmdbenedis@lmdbenedis');

		$this->usage_point_id = trim((string) $this->usage_point_id);
		$this->label = trim((string) $this->label);
		$this->authorization_reference = trim((string) $this->authorization_reference);
		$this->status = (int) !empty($this->status);
		if (!preg_match('/^[0-9]{14}$/', $this->usage_point_id)) {
			$this->error = $langs->trans('LmdbEnedisInvalidUsagePointId');
			$this->errors[] = $this->error;
			return false;
		}
		if ($this->label === '') {
			$this->label = $this->usage_point_id;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbenedis_prm WHERE entity = '.((int) $conf->entity)." AND usage_point_id = '".$this->db->escape($this->usage_point_id)."'";
		if ((int) $this->id > 0) {
			$sql .= ' AND rowid <> '.((int) $this->id);
		}
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return false;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (is_object($row)) {
			$this->error = $langs->trans('LmdbEnedisDuplicateUsagePointId');
			$this->errors[] = $this->error;
			return false;
		}
		if (!empty($this->authorization_end)) {
			$authorizationEnd = is_numeric($this->authorization_end) ? (int) $this->authorization_end : strtotime((string) $this->authorization_end.' UTC');
			if ($authorizationEnd === false || $authorizationEnd <= 0) {
				$this->error = $langs->trans('ErrorBadValueForParameter', $langs->trans('LmdbEnedisAuthorizationEnd'));
				$this->errors[] = $this->error;
				return false;
			}
		}
		if ((int) $this->fk_soc > 0) {
			$entities = $this->db->sanitize(getEntity('societe'));
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE rowid = '.((int) $this->fk_soc).' AND entity IN ('.$entities.')';
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				$this->errors[] = $this->error;
				return false;
			}
			$row = $this->db->fetch_object($resql);
			$this->db->free($resql);
			if (!is_object($row)) {
				$this->error = $langs->trans('LmdbEnedisInvalidThirdParty');
				$this->errors[] = $this->error;
				return false;
			}
		}
		if ((int) $this->fk_powerplant > 0 && isModEnabled('powerplantpv')) {
			$sql = 'SELECT rowid, prm_pdl_number FROM '.MAIN_DB_PREFIX.'powerplantpv_powerplant WHERE rowid = '.((int) $this->fk_powerplant).' AND entity = '.((int) $conf->entity);
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				$this->errors[] = $this->error;
				return false;
			}
			$row = $this->db->fetch_object($resql);
			$this->db->free($resql);
			if (!is_object($row)) {
				$this->error = $langs->trans('LmdbEnedisInvalidPowerPlant');
				$this->errors[] = $this->error;
				return false;
			}
			if (trim((string) $row->prm_pdl_number) !== '' && trim((string) $row->prm_pdl_number) !== $this->usage_point_id) {
				$this->error = $langs->trans('LmdbEnedisPowerPlantPrmMismatch');
				$this->errors[] = $this->error;
				return false;
			}
		}

		return true;
	}

	/** @param mixed $value Value @return string */
	private function nullableInt($value)
	{
		return (int) $value > 0 ? (string) ((int) $value) : 'NULL';
	}

	/** @param mixed $value Value @return string */
	private function nullableDate($value)
	{
		if (empty($value)) {
			return 'NULL';
		}
		$timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);

		return $timestamp > 0 ? "'".$this->db->escape(gmdate('Y-m-d', $timestamp))."'" : 'NULL';
	}

	/**
	 * @param self $old Old object
	 * @return array<int,string>
	 */
	private function changedFields($old)
	{
		$changed = array();
		foreach (array('usage_point_id', 'label', 'fk_soc', 'fk_powerplant', 'authorization_reference', 'authorization_end', 'status') as $field) {
			if ((string) $old->{$field} !== (string) $this->{$field}) {
				$changed[] = $field;
			}
		}

		return $changed;
	}
}
