<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Enedis Data Connect Autorisation V1 request service.
 *
 * The OAuth state and returned authorization code are never persisted in
 * clear text. A request is short-lived, one-time and bound to one PRM and one
 * Dolibarr entity before the customer is redirected to Enedis.
 */
class LmdbEnedisAuthorization
{
	public const STATUS_PENDING = 'pending';
	public const STATUS_GRANTED = 'granted';
	public const STATUS_FAILED = 'failed';
	public const STATUS_EXPIRED = 'expired';
	public const STATUS_CANCELLED = 'cancelled';

	/** @var DoliDB|null */
	private $db;
	/** @var string */
	private $clientId;
	/** @var string */
	private $authorizeUrl;
	/** @var string */
	private $duration;
	/** @var string */
	private $callbackUrl;
	/** @var string */
	public $error = '';
	/** @var array<int,string> */
	public $errors = array();

	/**
	 * @param DoliDB|null $db           Database handler
	 * @param string      $clientId     Optional injected client ID
	 * @param string      $authorizeUrl Optional injected Autorisation V1 URL
	 * @param string      $duration     Optional injected ISO 8601 duration
	 * @param string      $callbackUrl  Optional injected public callback URL
	 */
	public function __construct($db, $clientId = '', $authorizeUrl = '', $duration = '', $callbackUrl = '')
	{
		$this->db = $db;
		$this->clientId = $clientId !== '' ? $clientId : (function_exists('getDolGlobalString') ? getDolGlobalString('LMDBENEDIS_CLIENT_ID') : '');
		$this->authorizeUrl = $authorizeUrl !== '' ? $authorizeUrl : (function_exists('getDolGlobalString') ? getDolGlobalString('LMDBENEDIS_AUTHORIZE_URL') : '');
		$this->duration = $duration !== '' ? $duration : (function_exists('getDolGlobalString') ? getDolGlobalString('LMDBENEDIS_AUTHORIZATION_DURATION', 'P3Y') : 'P3Y');
		$this->callbackUrl = $callbackUrl !== '' ? $callbackUrl : self::getCallbackUrl();
	}

	/**
	 * @return array<string,string>
	 */
	public static function getDurationOptions()
	{
		return array(
			'P3M' => '3 months',
			'P6M' => '6 months',
			'P1Y' => '1 year',
			'P2Y' => '2 years',
			'P3Y' => '3 years',
		);
	}

	/**
	 * Return the callback URL that must be registered on Enedis DataHub.
	 *
	 * @return string
	 */
	public static function getCallbackUrl()
	{
		return function_exists('dol_buildpath') ? dol_buildpath('/lmdbenedis/authorization_callback.php', 3) : '';
	}

	/**
	 * @return bool
	 */
	public static function isConfigured()
	{
		try {
			$service = new self(null);
			$service->validateConfiguration();
			return true;
		} catch (Throwable $e) {
			return false;
		}
	}

	/**
	 * Restrict the browser redirect to the official Autorisation V1 endpoint.
	 *
	 * @param string $url URL
	 * @return bool
	 */
	public static function isAllowedAuthorizeUrl($url)
	{
		$parts = parse_url($url);
		return is_array($parts)
			&& strtolower((string) ($parts['scheme'] ?? '')) === 'https'
			&& strtolower((string) ($parts['host'] ?? '')) === 'mon-compte-particulier.enedis.fr'
			&& (int) ($parts['port'] ?? 443) === 443
			&& (string) ($parts['path'] ?? '') === '/dataconnect/v1/oauth2/authorize'
			&& empty($parts['user'])
			&& empty($parts['pass'])
			&& empty($parts['query'])
			&& empty($parts['fragment']);
	}

	/**
	 * @param string $url URL
	 * @return bool
	 */
	public static function isAllowedCallbackUrl($url)
	{
		$parts = parse_url($url);
		return is_array($parts)
			&& strtolower((string) ($parts['scheme'] ?? '')) === 'https'
			&& trim((string) ($parts['host'] ?? '')) !== ''
			&& empty($parts['user'])
			&& empty($parts['pass'])
			&& empty($parts['query'])
			&& empty($parts['fragment']);
	}

	/**
	 * Generate a high-entropy OAuth2 state. The final digit also remains
	 * compatible with the scenario selector documented for the Enedis sandbox.
	 *
	 * @param int $entity Entity ID encoded for the public Multicompany callback
	 * @return string
	 */
	public static function generateState($entity)
	{
		if ($entity <= 0 || $entity > 9999999999) {
			throw new InvalidArgumentException('Invalid authorization entity');
		}

		return sprintf('%010d', $entity).substr(bin2hex(random_bytes(32)), 0, 63).((string) random_int(0, 9));
	}

	/**
	 * @param string $state One-time raw state
	 * @return string
	 */
	public function buildAuthorizationUrl($state)
	{
		$this->validateConfiguration();
		if (!preg_match('/^[0-9]{10}[a-f0-9]{63}[0-9]$/D', $state)) {
			throw new InvalidArgumentException('Invalid OAuth2 state');
		}

		return $this->authorizeUrl.'?'.http_build_query(array(
			'client_id' => $this->clientId,
			'response_type' => 'code',
			'state' => $state,
			'duration' => $this->duration,
		), '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * Create a one-time request bound to an existing PRM.
	 *
	 * @param LmdbEnedisPrm $prm  PRM
	 * @param User           $user Requesting user
	 * @return string Authorization URL
	 */
	public function createRequest($prm, $user)
	{
		global $conf;

		$this->validateConfiguration();
		if (!is_object($this->db) || !is_object($prm) || (int) $prm->id <= 0 || (int) $prm->entity !== (int) $conf->entity || !is_object($user) || (int) $user->id <= 0) {
			throw new RuntimeException('Invalid authorization request context');
		}
		$state = self::generateState((int) $conf->entity);
		$stateHash = hash('sha256', $state);
		$now = dol_now();
		$expiresAt = $now + 15 * 60;

		$this->db->begin();
		$sql = 'UPDATE '.MAIN_DB_PREFIX."lmdbenedis_authorization_request SET status = '".self::STATUS_CANCELLED."', fk_user_modif = ".((int) $user->id);
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_prm = '.((int) $prm->id)." AND status = '".self::STATUS_PENDING."'";
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			throw new RuntimeException($this->db->lasterror());
		}
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbenedis_authorization_request (entity, fk_prm, state_hash, duration, status, expires_at, date_creation, fk_user_creat) VALUES (';
		$sql .= ((int) $conf->entity).', '.((int) $prm->id).", '".$this->db->escape($stateHash)."', '".$this->db->escape($this->duration)."', '".self::STATUS_PENDING."', '";
		$sql .= $this->db->idate($expiresAt)."', '".$this->db->idate($now)."', ".((int) $user->id).')';
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			throw new RuntimeException($this->db->lasterror());
		}
		$this->db->commit();

		return $this->buildAuthorizationUrl($state);
	}

	/**
	 * Consume the Enedis callback exactly once.
	 *
	 * @param string $state            OAuth2 state
	 * @param string $code             Authorization code
	 * @param string $usagePointId     Returned PRM
	 * @param string $errorCode        Optional Enedis error code
	 * @return array{success:bool,prm_id:int,result:string}
	 */
	public function consumeCallback($state, $code, $usagePointId, $errorCode = '')
	{
		global $conf;

		if (function_exists('isModEnabled') && !isModEnabled('lmdbenedis')) {
			return array('success' => false, 'prm_id' => 0, 'result' => 'feature_unavailable');
		}
		if (!is_object($this->db) || !preg_match('/^[0-9]{10}[a-f0-9]{63}[0-9]$/D', $state)) {
			return array('success' => false, 'prm_id' => 0, 'result' => 'invalid_state');
		}
		$stateHash = hash('sha256', $state);
		$this->db->begin();
		$sql = 'SELECT rowid, entity, fk_prm, duration, status, expires_at, fk_user_creat FROM '.MAIN_DB_PREFIX.'lmdbenedis_authorization_request';
		$sql .= " WHERE state_hash = '".$this->db->escape($stateHash)."' FOR UPDATE";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->db->rollback();
			throw new RuntimeException($this->db->lasterror());
		}
		$request = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($request)) {
			$this->db->rollback();
			return array('success' => false, 'prm_id' => 0, 'result' => 'invalid_state');
		}
		if ((int) substr($state, 0, 10) !== (int) $request->entity) {
			$this->db->rollback();
			return array('success' => false, 'prm_id' => 0, 'result' => 'invalid_state');
		}
		if ((string) $request->status === self::STATUS_GRANTED) {
			$this->db->rollback();
			return array('success' => false, 'prm_id' => 0, 'result' => 'already_processed');
		}
		if ((string) $request->status !== self::STATUS_PENDING) {
			$this->db->rollback();
			return array('success' => false, 'prm_id' => 0, 'result' => 'already_processed');
		}
		$expiresAt = $this->db->jdate((string) $request->expires_at);
		if ($expiresAt <= 0 || $expiresAt < dol_now()) {
			$this->setRequestFailure((int) $request->rowid, self::STATUS_EXPIRED, 'expired');
			$this->db->commit();
			return array('success' => false, 'prm_id' => 0, 'result' => 'expired');
		}
		if ($errorCode !== '') {
			$this->setRequestFailure((int) $request->rowid, self::STATUS_FAILED, $errorCode);
			$this->db->commit();
			return array('success' => false, 'prm_id' => 0, 'result' => 'denied');
		}
		if (!preg_match('/^[A-Za-z0-9._~-]{1,512}$/D', $code) || !preg_match('/^[0-9]{14}$/D', $usagePointId)) {
			$this->setRequestFailure((int) $request->rowid, self::STATUS_FAILED, 'invalid_callback');
			$this->db->commit();
			return array('success' => false, 'prm_id' => 0, 'result' => 'invalid_callback');
		}

		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		require_once __DIR__.'/lmdbenedisprm.class.php';
		require_once __DIR__.'/../lib/lmdbenedis_access.lib.php';
		$user = new User($this->db);
		if ($user->fetch((int) $request->fk_user_creat) <= 0) {
			$this->setRequestFailure((int) $request->rowid, self::STATUS_FAILED, 'requesting_user_not_found');
			$this->db->commit();
			return array('success' => false, 'prm_id' => 0, 'result' => 'requesting_user_unauthorized');
		}
		$prm = new LmdbEnedisPrm($this->db);
		if ($prm->fetchForEntity((int) $request->fk_prm, (int) $request->entity) <= 0) {
			$this->setRequestFailure((int) $request->rowid, self::STATUS_FAILED, 'prm_not_found');
			$this->db->commit();
			return array('success' => false, 'prm_id' => 0, 'result' => 'prm_not_found');
		}
		if (!hash_equals($prm->usage_point_id, $usagePointId)) {
			$this->setRequestFailure((int) $request->rowid, self::STATUS_FAILED, 'prm_mismatch');
			$this->db->commit();
			return array('success' => false, 'prm_id' => 0, 'result' => 'prm_mismatch');
		}
		$user->loadRights();
		if (empty($user->statut) || !lmdbenedisCanDo($user, 'prm', 'write', $prm)) {
			$this->setRequestFailure((int) $request->rowid, self::STATUS_FAILED, 'requesting_user_unauthorized');
			$this->db->commit();
			return array('success' => false, 'prm_id' => 0, 'result' => 'requesting_user_unauthorized');
		}

		$oldEntity = (int) $conf->entity;
		$conf->entity = (int) $request->entity;
		try {
			$codeHash = hash('sha256', $code);
			$reference = 'sha256:'.$codeHash;
			$authorizationEnd = self::calculateAuthorizationEnd((string) $request->duration, dol_now());
			if ($prm->applyAuthorization($reference, $authorizationEnd, $user, 0, 0) <= 0) {
				throw new RuntimeException($prm->error !== '' ? $prm->error : 'Unable to update the PRM authorization');
			}
			$sql = 'UPDATE '.MAIN_DB_PREFIX."lmdbenedis_authorization_request SET status = '".self::STATUS_GRANTED."', usage_point_id = '".$this->db->escape($usagePointId)."', ";
			$sql .= "code_hash = '".$this->db->escape($codeHash)."', completed_at = '".$this->db->idate(dol_now())."', fk_user_modif = ".((int) $user->id);
			$sql .= ' WHERE rowid = '.((int) $request->rowid).' AND entity = '.((int) $request->entity);
			if (!$this->db->query($sql)) {
				throw new RuntimeException($this->db->lasterror());
			}
			$this->db->commit();
		} catch (Throwable $e) {
			$this->db->rollback();
			throw $e;
		} finally {
			$conf->entity = $oldEntity;
		}

		return array('success' => true, 'prm_id' => (int) $request->fk_prm, 'result' => 'granted');
	}

	/**
	 * @param int $prmId PRM ID
	 * @param int $entity Entity ID
	 * @return array{status:string,date_creation:int,error_code:string}|array{}
	 */
	public function getLatestRequest($prmId, $entity)
	{
		if (!is_object($this->db)) {
			return array();
		}
		$sql = 'SELECT status, date_creation, expires_at, error_code FROM '.MAIN_DB_PREFIX.'lmdbenedis_authorization_request';
		$sql .= ' WHERE fk_prm = '.((int) $prmId).' AND entity = '.((int) $entity).' ORDER BY rowid DESC LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return array();
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($row)) {
			return array();
		}

		$status = (string) $row->status;
		$expiresAt = $this->db->jdate((string) $row->expires_at);
		if ($status === self::STATUS_PENDING && $expiresAt > 0 && $expiresAt < dol_now()) {
			$status = self::STATUS_EXPIRED;
		}

		return array(
			'status' => $status,
			'date_creation' => !empty($row->date_creation) ? $this->db->jdate((string) $row->date_creation) : 0,
			'error_code' => (string) $row->error_code,
		);
	}

	/**
	 * @param string $duration ISO 8601 duration
	 * @param int    $from     UTC timestamp
	 * @return int
	 */
	public static function calculateAuthorizationEnd($duration, $from)
	{
		if (!isset(self::getDurationOptions()[$duration])) {
			throw new InvalidArgumentException('Unsupported authorization duration');
		}
		$date = (new DateTimeImmutable('@'.$from))->setTimezone(new DateTimeZone('UTC'));

		return $date->add(new DateInterval($duration))->getTimestamp();
	}

	/** @return void */
	private function validateConfiguration()
	{
		if ($this->clientId === '' || !self::isAllowedAuthorizeUrl($this->authorizeUrl) || !isset(self::getDurationOptions()[$this->duration]) || !self::isAllowedCallbackUrl($this->callbackUrl)) {
			throw new RuntimeException('Enedis Autorisation V1 is not configured or the public callback is not HTTPS');
		}
	}

	/**
	 * @param int    $requestId   Request ID
	 * @param string $status      Terminal status
	 * @param string $errorCode   Error code
	 * @return void
	 */
	private function setRequestFailure($requestId, $status, $errorCode)
	{
		$errorCode = substr($errorCode, 0, 64);
		$sql = 'UPDATE '.MAIN_DB_PREFIX."lmdbenedis_authorization_request SET status = '".$this->db->escape($status)."', error_code = '".$this->db->escape($errorCode)."', ";
		$sql .= "completed_at = '".$this->db->idate(dol_now())."' WHERE rowid = ".((int) $requestId);
		if (!$this->db->query($sql)) {
			throw new RuntimeException($this->db->lasterror());
		}
	}
}
