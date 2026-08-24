<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/lmdbenedisclient.class.php';
require_once __DIR__.'/lmdbenedismeasureparser.class.php';
require_once __DIR__.'/lmdbenedisprm.class.php';

/**
 * Transactional Mesures V1 synchronizer.
 */
class LmdbEnedisSynchronizer
{
	/** @var DoliDB */
	private $db;
	/** @var LmdbEnedisClient */
	private $client;
	/** @var string */
	public $error = '';
	/** @var array<int,string> */
	public $errors = array();
	/** @var string */
	public $output = '';
	/** @var bool Stop the current batch after an Enedis quota response */
	private $rateLimited = false;

	/**
	 * @param DoliDB                 $db     Database handler
	 * @param LmdbEnedisClient|null $client Optional client for tests
	 */
	public function __construct($db, $client = null)
	{
		$this->db = $db;
		$this->client = $client instanceof LmdbEnedisClient ? $client : new LmdbEnedisClient();
	}

	/**
	 * Synchronize active streams for one PRM.
	 *
	 * @param int               $prmId          PRM ID
	 * @param array<int,string> $requestedCodes Empty for all active streams
	 * @param int               $startTimestamp Optional inclusive start
	 * @param int               $endTimestamp   Optional exclusive end
	 * @return array{streams:int,points:int,errors:int}
	 */
	public function syncPrm($prmId, $requestedCodes = array(), $startTimestamp = 0, $endTimestamp = 0)
	{
		global $langs;

		$langs->load('lmdbenedis@lmdbenedis');
		$this->error = '';
		$this->rateLimited = false;

		$result = array('streams' => 0, 'points' => 0, 'errors' => 0);
		$prm = new LmdbEnedisPrm($this->db);
		if ($prm->fetch((int) $prmId) <= 0 || empty($prm->status)) {
			$this->error = $langs->trans('LmdbEnedisPrmUnavailable');
			$this->errors[] = $this->error;
			$result['errors']++;
			return $result;
		}
		if (trim($prm->authorization_reference) === '') {
			$this->error = $langs->trans('LmdbEnedisAuthorizationRequired');
			$this->errors[] = $this->error;
			$result['errors']++;
			if (!$this->savePrmSyncState((int) $prm->id, 'error', $this->error)) {
				$result['errors']++;
			}
			return $result;
		}
		$utcToday = dol_mktime(0, 0, 0, (int) gmdate('n'), (int) gmdate('j'), (int) gmdate('Y'), 'gmt');
		$authorizationEnd = empty($prm->authorization_end) ? 0 : (is_numeric($prm->authorization_end) ? (int) $prm->authorization_end : (int) strtotime((string) $prm->authorization_end.' UTC'));
		if ($authorizationEnd > 0 && $authorizationEnd < $utcToday) {
			$this->error = $langs->trans('LmdbEnedisAuthorizationExpired');
			$this->errors[] = $this->error;
			$result['errors']++;
			if (!$this->savePrmSyncState((int) $prm->id, 'error', $this->error)) {
				$result['errors']++;
			}
			return $result;
		}
		$streams = $this->fetchStreams((int) $prm->id, $requestedCodes);
		if ($streams === array()) {
			if ($this->error === '') {
				$this->error = $langs->trans('LmdbEnedisNoActiveStream');
				$this->errors[] = $this->error;
			}
			$result['errors']++;
			if (!$this->savePrmSyncState((int) $prm->id, 'error', $this->error)) {
				$result['errors']++;
			}
			return $result;
		}

		$automaticEnd = $utcToday - max(0, getDolGlobalInt('LMDBENEDIS_SYNC_LAG_DAYS', 2)) * 86400;
		$targetEnd = $endTimestamp > 0 ? $endTimestamp : $automaticEnd;
		if ($authorizationEnd > 0) {
			$targetEnd = min($targetEnd, $authorizationEnd + 86400);
		}
		foreach ($streams as $stream) {
			if ($this->rateLimited) {
				break;
			}
			$resourceCode = $stream['resource_code'];
			$cursorTimestamp = $stream['cursor_date'] !== '' ? strtotime($stream['cursor_date'].' UTC') : false;
			$defaultStart = $targetEnd - max(1, getDolGlobalInt('LMDBENEDIS_BACKFILL_DAYS', 30)) * 86400;
			if ($startTimestamp <= 0 && in_array($resourceCode, array(LmdbEnedisClient::RESOURCE_CONSUMPTION_LOAD_CURVE, LmdbEnedisClient::RESOURCE_PRODUCTION_LOAD_CURVE), true)) {
				$oldestLoadCurveDate = (new DateTimeImmutable('@'.$utcToday))->setTimezone(new DateTimeZone('UTC'))->modify('-24 months -15 days')->getTimestamp();
				$defaultStart = max($defaultStart, $oldestLoadCurveDate);
			}
			$streamStart = $startTimestamp > 0 ? $startTimestamp : ($cursorTimestamp !== false ? $cursorTimestamp : $defaultStart);
			if ($streamStart >= $targetEnd) {
				continue;
			}

			$chunkDays = in_array($resourceCode, array(LmdbEnedisClient::RESOURCE_CONSUMPTION_LOAD_CURVE, LmdbEnedisClient::RESOURCE_PRODUCTION_LOAD_CURVE), true) ? 7 : 31;
			$chunkStart = $streamStart;
			$streamHasError = false;
			while ($chunkStart < $targetEnd) {
				$chunkEnd = min($targetEnd, $chunkStart + $chunkDays * 86400);
				$this->markAttempt((int) $stream['rowid']);
				try {
					$payload = $this->client->fetchMeasurements(
						$resourceCode,
						$prm->usage_point_id,
						gmdate('Y-m-d', $chunkStart),
						gmdate('Y-m-d', $chunkEnd)
					);
					$rows = LmdbEnedisMeasureParser::parse($resourceCode, $payload);
					if ($this->persistChunk((int) $prm->id, $resourceCode, $rows, (int) $stream['rowid'], $chunkEnd) < 0) {
						throw new RuntimeException($this->error !== '' ? $this->error : 'Unable to persist Enedis measures');
					}
					$result['points'] += count($rows);
					$chunkStart = $chunkEnd;
				} catch (LmdbEnedisApiException $e) {
					$this->markStreamError((int) $stream['rowid'], $e->getHttpStatus(), $e->getMessage());
					$this->errors[] = $resourceCode.': '.$e->getMessage();
					$result['errors']++;
					$streamHasError = true;
					$this->rateLimited = $e->getHttpStatus() === 429;
					break;
				} catch (Throwable $e) {
					$this->markStreamError((int) $stream['rowid'], $this->client->lastHttpCode, $e->getMessage());
					$this->errors[] = $resourceCode.': '.$e->getMessage();
					$result['errors']++;
					$streamHasError = true;
					break;
				}
			}
			if (!$streamHasError) {
				$result['streams']++;
			}
		}

		$status = $result['errors'] > 0 ? 'error' : 'success';
		$message = $langs->trans('LmdbEnedisSyncResult', $result['streams'], $result['points'], $result['errors']);
		if (!$this->savePrmSyncState((int) $prm->id, $status, $message)) {
			$result['errors']++;
			$message = $langs->trans('LmdbEnedisSyncResult', $result['streams'], $result['points'], $result['errors']);
		}
		$this->output = $message;

		return $result;
	}

	/**
	 * Synchronize a bounded number of active PRMs for the current entity.
	 *
	 * @param int $limit Maximum PRMs, 0 for configured value
	 * @return int Number of errors, negative on lock/query failure
	 */
	public function syncAll($limit = 0)
	{
		global $conf, $langs;

		$langs->load('lmdbenedis@lmdbenedis');

		$limit = $limit > 0 ? $limit : max(1, getDolGlobalInt('LMDBENEDIS_CRON_MAX_PRMS', 50));
		$lockName = 'lmdbenedis_sync_entity_'.((int) $conf->entity);
		$lockSql = "SELECT GET_LOCK('".$this->db->escape($lockName)."', 0) AS acquired";
		$lockResult = $this->db->query($lockSql);
		$lockRow = $lockResult ? $this->db->fetch_object($lockResult) : false;
		if ($lockResult) {
			$this->db->free($lockResult);
		}
		if (!is_object($lockRow) || (int) $lockRow->acquired !== 1) {
			$this->error = $langs->trans('LmdbEnedisSyncAlreadyRunning');
			return -1;
		}

		$totalErrors = 0;
		$totalPoints = 0;
		$totalPrms = 0;
		try {
			$sql = 'SELECT DISTINCT p.rowid FROM '.MAIN_DB_PREFIX.'lmdbenedis_prm AS p';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbenedis_prm_stream AS s ON s.fk_prm = p.rowid AND s.entity = p.entity AND s.active = 1';
			$sql .= ' WHERE p.entity = '.((int) $conf->entity).' AND p.status = 1';
			$sql .= " AND p.authorization_reference IS NOT NULL AND p.authorization_reference <> ''";
			$sql .= " AND (p.authorization_end IS NULL OR p.authorization_end >= '".$this->db->escape(gmdate('Y-m-d'))."')";
			$sql .= ' ORDER BY p.last_sync_at ASC, p.rowid ASC LIMIT '.((int) $limit);
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			while (is_object($row = $this->db->fetch_object($resql))) {
				$syncResult = $this->syncPrm((int) $row->rowid);
				$totalPrms++;
				$totalPoints += $syncResult['points'];
				$totalErrors += $syncResult['errors'];
				if ($this->rateLimited) {
					break;
				}
			}
			$this->db->free($resql);
		} finally {
			$this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($lockName)."')");
		}

		$this->output = $langs->trans('LmdbEnedisSyncAllResult', $totalPrms, $totalPoints, $totalErrors);

		return $totalErrors;
	}

	/**
	 * @param int               $prmId         PRM ID
	 * @param array<int,string> $requestedCodes Optional resource filter
	 * @return array<int,array{rowid:int,resource_code:string,cursor_date:string}>
	 */
	private function fetchStreams($prmId, $requestedCodes)
	{
		global $conf;

		$allowed = array_keys(LmdbEnedisClient::getResourcePaths());
		$requestedCodes = array_values(array_intersect($requestedCodes, $allowed));
		$sql = 'SELECT rowid, resource_code, cursor_date FROM '.MAIN_DB_PREFIX.'lmdbenedis_prm_stream';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_prm = '.((int) $prmId).' AND active = 1';
		if ($requestedCodes !== array()) {
			$escaped = array();
			foreach ($requestedCodes as $code) {
				$escaped[] = "'".$this->db->escape($code)."'";
			}
			$sql .= ' AND resource_code IN ('.implode(',', $escaped).')';
		}
		$sql .= ' ORDER BY rowid ASC';
		$resql = $this->db->query($sql);
		$streams = array();
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return $streams;
		}
		while (is_object($row = $this->db->fetch_object($resql))) {
			$streams[] = array(
				'rowid' => (int) $row->rowid,
				'resource_code' => (string) $row->resource_code,
				'cursor_date' => empty($row->cursor_date) ? '' : (string) $row->cursor_date,
			);
		}
		$this->db->free($resql);

		return $streams;
	}

	/**
	 * Persist the latest PRM synchronization result for cron fairness and audit.
	 *
	 * @param int    $prmId  PRM ID
	 * @param string $status Synchronization status
	 * @param string $message User-facing summary
	 * @return bool
	 */
	private function savePrmSyncState($prmId, $status, $message)
	{
		global $conf;

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbenedis_prm SET';
		$sql .= " last_sync_at = '".$this->db->idate(dol_now())."'";
		$sql .= ", last_sync_status = '".$this->db->escape($status)."'";
		$sql .= ", last_sync_message = '".$this->db->escape(dol_trunc($message, 65535))."'";
		$sql .= ' WHERE rowid = '.((int) $prmId).' AND entity = '.((int) $conf->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return false;
		}

		$this->output = $message;

		return true;
	}

	/**
	 * @param int                      $prmId        PRM ID
	 * @param string                   $resourceCode Resource
	 * @param array<int,array<string,mixed>> $rows  Parsed rows
	 * @param int                      $streamId     Stream ID
	 * @param int                      $cursorEnd    Exclusive cursor
	 * @return int
	 */
	private function persistChunk($prmId, $resourceCode, $rows, $streamId, $cursorEnd)
	{
		global $conf;

		$this->db->begin();
		foreach ($rows as $row) {
			if ($this->upsertMeasure($prmId, $resourceCode, $row) < 0) {
				$this->db->rollback();
				return -1;
			}
		}
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbenedis_prm_stream SET';
		$sql .= " cursor_date = '".$this->db->escape(gmdate('Y-m-d H:i:s', $cursorEnd))."'";
		$sql .= ", last_success_at = '".$this->db->idate(dol_now())."'";
		$sql .= ', last_http_code = '.((int) $this->client->lastHttpCode).', last_error = NULL';
		$sql .= ' WHERE rowid = '.((int) $streamId).' AND entity = '.((int) $conf->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		return 1;
	}

	/**
	 * @param int                 $prmId        PRM ID
	 * @param string              $resourceCode Resource
	 * @param array<string,mixed> $row          Parsed row
	 * @return int
	 */
	private function upsertMeasure($prmId, $resourceCode, $row)
	{
		global $conf;

		$stringFields = array(
			'unit', 'quality', 'reading_context', 'interval_length', 'measure_type', 'flow_direction',
			'measurement_kind', 'aggregate_kind', 'measuring_period', 'calendar_id', 'calendar_label',
			'temporal_class_id', 'temporal_class_label', 'quadrant_id',
		);
		$fields = array('entity', 'fk_prm', 'resource_code', 'data_key', 'measure_date', 'value');
		$values = array(
			(string) ((int) $conf->entity),
			(string) ((int) $prmId),
			"'".$this->db->escape($resourceCode)."'",
			"'".$this->db->escape((string) $row['data_key'])."'",
			"'".$this->db->escape((string) $row['measure_date'])."'",
			(string) ((float) $row['value']),
		);
		foreach ($stringFields as $field) {
			$fields[] = $field;
			$values[] = "'".$this->db->escape(isset($row[$field]) ? (string) $row[$field] : '')."'";
		}
		foreach (array('source_start', 'source_end') as $field) {
			$fields[] = $field;
			$value = isset($row[$field]) ? (string) $row[$field] : '';
			$values[] = $value === '' ? 'NULL' : "'".$this->db->escape($value)."'";
		}
		$fields[] = 'date_creation';
		$values[] = "'".$this->db->idate(dol_now())."'";
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbenedis_measure ('.implode(', ', $fields).') VALUES ('.implode(', ', $values).')';
		$updates = array('measure_date', 'value');
		$updates = array_merge($updates, $stringFields, array('source_start', 'source_end'));
		$clauses = array();
		foreach ($updates as $field) {
			$clauses[] = $field.' = VALUES('.$field.')';
		}
		$sql .= ' ON DUPLICATE KEY UPDATE '.implode(', ', $clauses);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/** @param int $streamId Stream ID @return void */
	private function markAttempt($streamId)
	{
		global $conf;

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbenedis_prm_stream SET last_attempt_at = ';
		$sql .= "'".$this->db->idate(dol_now())."' WHERE rowid = ".((int) $streamId).' AND entity = '.((int) $conf->entity);
		$this->db->query($sql);
	}

	/**
	 * @param int    $streamId Stream ID
	 * @param int    $httpCode HTTP status
	 * @param string $message  Error
	 * @return void
	 */
	private function markStreamError($streamId, $httpCode, $message)
	{
		global $conf;

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbenedis_prm_stream SET last_http_code = '.((int) $httpCode);
		$sql .= ", last_error = '".$this->db->escape(dol_trunc($message, 2000))."'";
		$sql .= ' WHERE rowid = '.((int) $streamId).' AND entity = '.((int) $conf->entity);
		$this->db->query($sql);
	}
}
