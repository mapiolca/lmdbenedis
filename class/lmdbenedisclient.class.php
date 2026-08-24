<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/lmdbenedisconfig.class.php';

/**
 * Exception returned by the Enedis client.
 */
class LmdbEnedisApiException extends RuntimeException
{
	/** @var int HTTP status */
	private $httpStatus;

	/**
	 * @param string $message    Safe error message
	 * @param int    $httpStatus HTTP status
	 */
	public function __construct($message, $httpStatus = 0)
	{
		parent::__construct($message, $httpStatus);
		$this->httpStatus = (int) $httpStatus;
	}

	/**
	 * @return int
	 */
	public function getHttpStatus()
	{
		return $this->httpStatus;
	}
}

/**
 * OAuth2 and Mesures V1 HTTP client.
 *
 * This class deliberately uses an isolated cURL call instead of getURLContent():
 * the native helper logs outgoing headers and would therefore expose Basic and
 * Bearer credentials in Dolibarr logs.
 */
class LmdbEnedisClient
{
	public const RESOURCE_CONSUMPTION_LOAD_CURVE = 'consumption_load_curve';
	public const RESOURCE_PRODUCTION_LOAD_CURVE = 'production_load_curve';
	public const RESOURCE_DAILY_CONSUMPTION = 'daily_consumption';
	public const RESOURCE_DAILY_PRODUCTION = 'daily_production';
	public const RESOURCE_DAILY_CONSUMPTION_MAX_POWER = 'daily_consumption_max_power';
	public const RESOURCE_INDEX_CONSUMPTION = 'index_consumption';
	public const RESOURCE_INDEX_PRODUCTION = 'index_production';

	/** @var string */
	private $clientId;
	/** @var string */
	private $clientSecret;
	/** @var string */
	private $apiBaseUrl;
	/** @var string */
	private $tokenUrl;
	/** @var int */
	private $timeout;
	/** @var string */
	private $accessToken = '';
	/** @var int */
	private $accessTokenExpiresAt = 0;
	/** @var int */
	public $lastHttpCode = 0;

	/**
	 * @param string $clientId     Optional injected client identifier
	 * @param string $clientSecret Optional injected secret
	 * @param string $apiBaseUrl   Optional injected Mesures V1 URL
	 * @param string $tokenUrl     Optional injected OAuth2 URL
	 * @param int    $timeout      Optional injected timeout
	 */
	public function __construct($clientId = '', $clientSecret = '', $apiBaseUrl = '', $tokenUrl = '', $timeout = 0)
	{
		$this->clientId = $clientId !== '' ? $clientId : getDolGlobalString('LMDBENEDIS_CLIENT_ID');
		$this->clientSecret = $clientSecret !== '' ? $clientSecret : LmdbEnedisConfig::getClientSecret();
		$this->apiBaseUrl = rtrim($apiBaseUrl !== '' ? $apiBaseUrl : getDolGlobalString('LMDBENEDIS_API_BASE_URL'), '/');
		$this->tokenUrl = $tokenUrl !== '' ? $tokenUrl : getDolGlobalString('LMDBENEDIS_TOKEN_URL');
		$this->timeout = $timeout > 0 ? $timeout : max(5, getDolGlobalInt('LMDBENEDIS_HTTP_TIMEOUT', 60));
		$this->assertAllowedEnedisUrl($this->apiBaseUrl);
		$this->assertAllowedEnedisUrl($this->tokenUrl);
	}

	/**
	 * Return the supported Mesures V1 resources and API paths.
	 *
	 * @return array<string,string>
	 */
	public static function getResourcePaths()
	{
		return array(
			self::RESOURCE_CONSUMPTION_LOAD_CURVE => '/metering_data/consumption_load_curve',
			self::RESOURCE_PRODUCTION_LOAD_CURVE => '/metering_data/production_load_curve',
			self::RESOURCE_DAILY_CONSUMPTION => '/metering_data/daily_consumption',
			self::RESOURCE_DAILY_PRODUCTION => '/metering_data/daily_production',
			self::RESOURCE_DAILY_CONSUMPTION_MAX_POWER => '/metering_data/daily_consumption_max_power',
			self::RESOURCE_INDEX_CONSUMPTION => '/metering_data/index_consumption',
			self::RESOURCE_INDEX_PRODUCTION => '/metering_data/index_production',
		);
	}

	/**
	 * Validate credentials and retrieve a token.
	 *
	 * @return bool
	 * @throws LmdbEnedisApiException
	 */
	public function testConnection()
	{
		$this->getAccessToken(true);

		return true;
	}

	/**
	 * Retrieve a Mesures V1 response.
	 *
	 * @param string            $resourceCode Resource code
	 * @param string            $usagePointId PRM, exactly 14 digits
	 * @param string            $start        Inclusive ISO date
	 * @param string            $end          Exclusive ISO date
	 * @param array<string,string> $extra     Whitelisted endpoint parameters
	 * @return array<string,mixed>
	 * @throws LmdbEnedisApiException
	 */
	public function fetchMeasurements($resourceCode, $usagePointId, $start, $end, $extra = array())
	{
		$paths = self::getResourcePaths();
		if (!isset($paths[$resourceCode])) {
			throw new LmdbEnedisApiException('Unsupported Mesures V1 resource');
		}
		if (!preg_match('/^[0-9]{14}$/', $usagePointId)) {
			throw new LmdbEnedisApiException('The usage point identifier must contain exactly 14 digits');
		}
		$startTimestamp = $this->parseApiDate($start);
		$endTimestamp = $this->parseApiDate($end);
		if ($startTimestamp === false || $endTimestamp === false || $startTimestamp >= $endTimestamp) {
			throw new LmdbEnedisApiException('The measurement period must use valid YYYY-MM-DD dates with an exclusive end after the start');
		}
		if (in_array($resourceCode, array(self::RESOURCE_CONSUMPTION_LOAD_CURVE, self::RESOURCE_PRODUCTION_LOAD_CURVE), true) && $endTimestamp - $startTimestamp > 7 * 86400) {
			throw new LmdbEnedisApiException('A load curve request cannot exceed seven consecutive days');
		}
		$oldestAllowedTimestamp = $this->getOldestAllowedTimestamp($resourceCode);
		if ($oldestAllowedTimestamp > 0 && $startTimestamp < $oldestAllowedTimestamp) {
			throw new LmdbEnedisApiException('The requested period exceeds the history available for this Mesures V1 resource');
		}

		$query = array(
			'usage_point_id' => $usagePointId,
			'start' => $start,
			'end' => $end,
		);
		if ($resourceCode === self::RESOURCE_DAILY_CONSUMPTION_MAX_POWER) {
			$query['measuring_period'] = isset($extra['measuring_period']) && in_array($extra['measuring_period'], array('P1D', 'P1M'), true) ? $extra['measuring_period'] : 'P1D';
			$query['grandeurPhysique'] = isset($extra['grandeurPhysique']) && in_array($extra['grandeurPhysique'], array('PMA', 'TOUT'), true) ? $extra['grandeurPhysique'] : 'PMA';
		}

		$url = $this->apiBaseUrl.$paths[$resourceCode].'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
		$token = $this->getAccessToken(false);
		$response = $this->request('GET', $url, '', array(
			'Accept: application/json',
			'Authorization: Bearer '.$token,
		));
		if ($response['status'] === 401) {
			$token = $this->getAccessToken(true);
			$response = $this->request('GET', $url, '', array(
				'Accept: application/json',
				'Authorization: Bearer '.$token,
			));
		}

		return $this->decodeSuccessfulResponse($response);
	}

	/**
	 * @param bool $forceRefresh Force a new token
	 * @return string
	 * @throws LmdbEnedisApiException
	 */
	private function getAccessToken($forceRefresh)
	{
		if (!$forceRefresh && $this->accessToken !== '' && $this->accessTokenExpiresAt > time() + 30) {
			return $this->accessToken;
		}
		if ($this->clientId === '' || $this->clientSecret === '') {
			throw new LmdbEnedisApiException('Missing Enedis OAuth2 client credentials');
		}
		$this->assertAllowedEnedisUrl($this->tokenUrl);

		$response = $this->request('POST', $this->tokenUrl, 'grant_type=client_credentials', array(
			'Accept: application/json',
			'Content-Type: application/x-www-form-urlencoded',
			'Authorization: Basic '.base64_encode($this->clientId.':'.$this->clientSecret),
		));
		$data = $this->decodeSuccessfulResponse($response);
		if (empty($data['access_token']) || !is_string($data['access_token'])) {
			throw new LmdbEnedisApiException('OAuth2 response does not contain an access token', $response['status']);
		}

		$this->accessToken = $data['access_token'];
		$expiresIn = isset($data['expires_in']) && is_numeric($data['expires_in']) ? (int) $data['expires_in'] : 300;
		$this->accessTokenExpiresAt = time() + max(60, $expiresIn);

		return $this->accessToken;
	}

	/**
	 * @param array{status:int,body:string,error:string} $response HTTP response
	 * @return array<string,mixed>
	 * @throws LmdbEnedisApiException
	 */
	private function decodeSuccessfulResponse($response)
	{
		$this->lastHttpCode = $response['status'];
		if ($response['error'] !== '') {
			throw new LmdbEnedisApiException('Network error while contacting Enedis: '.$response['error']);
		}

		$data = json_decode($response['body'], true);
		if ($response['status'] < 200 || $response['status'] >= 300) {
			$message = 'Enedis API returned HTTP '.$response['status'];
			if (is_array($data)) {
				foreach (array('message', 'error_description', 'error', 'title', 'description') as $key) {
					if (isset($data[$key]) && is_scalar($data[$key]) && trim((string) $data[$key]) !== '') {
						$message .= ': '.trim((string) $data[$key]);
						break;
					}
				}
			}
			throw new LmdbEnedisApiException(dol_trunc($message, 1000), $response['status']);
		}
		if (!is_array($data)) {
			throw new LmdbEnedisApiException('Invalid JSON response returned by Enedis', $response['status']);
		}

		return $data;
	}

	/**
	 * Perform an HTTPS request without logging credentials.
	 *
	 * @param string        $method  HTTP method
	 * @param string        $url     URL
	 * @param string        $body    Request body
	 * @param array<int,string> $headers Headers
	 * @return array{status:int,body:string,error:string}
	 * @throws LmdbEnedisApiException
	 */
	private function request($method, $url, $body, $headers)
	{
		$this->assertAllowedEnedisUrl($url);
		if (!function_exists('curl_init')) {
			throw new LmdbEnedisApiException('PHP cURL extension is required');
		}

		$ch = curl_init();
		if ($ch === false) {
			throw new LmdbEnedisApiException('Unable to initialize cURL');
		}
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_CONNECTTIMEOUT => min(15, $this->timeout),
			CURLOPT_TIMEOUT => $this->timeout,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_USERAGENT => 'Dolibarr LMDB-Enedis/1.0',
		);
		if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
			$options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
		}
		if ($method === 'POST') {
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = $body;
		}
		curl_setopt_array($ch, $options);
		$responseBody = curl_exec($ch);
		$error = curl_error($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return array(
			'status' => $status,
			'body' => is_string($responseBody) ? $responseBody : '',
			'error' => $error,
		);
	}

	/**
	 * Prevent configuration from turning the connector into an SSRF proxy.
	 *
	 * @param string $url URL to validate
	 * @return void
	 * @throws LmdbEnedisApiException
	 */
	private function assertAllowedEnedisUrl($url)
	{
		$parts = parse_url($url);
		$scheme = is_array($parts) && isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
		$host = is_array($parts) && isset($parts['host']) ? strtolower((string) $parts['host']) : '';
		$port = is_array($parts) && isset($parts['port']) ? (int) $parts['port'] : 443;
		if ($scheme !== 'https' || $port !== 443 || !preg_match('/(^|\.)api\.enedis\.fr$/', $host)) {
			throw new LmdbEnedisApiException('Only HTTPS endpoints hosted under api.enedis.fr are allowed');
		}
	}

	/**
	 * @param string $date RFC 3339 full-date
	 * @return int|false UTC timestamp or false
	 */
	private function parseApiDate($date)
	{
		$utc = new DateTimeZone('UTC');
		$value = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $utc);
		if (!$value instanceof DateTimeImmutable || $value->format('Y-m-d') !== $date) {
			return false;
		}

		return $value->getTimestamp();
	}

	/**
	 * Return the oldest request date documented for a resource.
	 *
	 * Index endpoints do not publish a historical-depth constraint in the
	 * Mesures V1 contract and therefore remain under Enedis authority.
	 *
	 * @param string $resourceCode Resource code
	 * @return int UTC timestamp, or 0 when the contract declares no limit
	 */
	private function getOldestAllowedTimestamp($resourceCode)
	{
		$today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
		if (in_array($resourceCode, array(self::RESOURCE_CONSUMPTION_LOAD_CURVE, self::RESOURCE_PRODUCTION_LOAD_CURVE), true)) {
			return $today->modify('-24 months -15 days')->getTimestamp();
		}
		if (in_array($resourceCode, array(self::RESOURCE_DAILY_CONSUMPTION, self::RESOURCE_DAILY_PRODUCTION, self::RESOURCE_DAILY_CONSUMPTION_MAX_POWER), true)) {
			return $today->modify('-36 months -15 days')->getTimestamp();
		}

		return 0;
	}
}
