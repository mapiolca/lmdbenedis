<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/../class/lmdbenedisclient.class.php';

$client = new LmdbEnedisClient(
	'client-id',
	'client-secret',
	'https://gw.ext.prod.api.enedis.fr/mesure_synchrone_auto/v1',
	'https://gw.ext.prod.api.enedis.fr/oauth2/v3/token',
	5,
	'https://gw.ext.prod.api.enedis.fr/subscribed_services/v1'
);

$blockedUrl = false;
try {
	new LmdbEnedisClient('client-id', 'client-secret', 'https://127.0.0.1/measure', 'https://gw.ext.prod.api.enedis.fr/oauth2/v3/token', 5, 'https://gw.ext.prod.api.enedis.fr/subscribed_services/v1');
} catch (LmdbEnedisApiException $e) {
	$blockedUrl = true;
}

$authorizationId = '987654321';
$subscribedServices = array(
	'nbTotalServices' => 2,
	'serviceSouscrit' => array(
		array(
			'pointId' => '12345678901234',
			'serviceCode' => 'ACCES',
			'etatCode' => 'ACTIF',
			'autorisation' => array('autorisationId' => 987654321),
		),
		array(
			'pointId' => '12345678901234',
			'serviceCode' => 'ACCES',
			'etatCode' => 'DEMANDE',
			'autorisation' => array('autorisationId' => 987654321),
		),
	),
);
if (LmdbEnedisClient::extractUsagePointIdFromSubscribedServices($subscribedServices, $authorizationId) !== '12345678901234') {
	fwrite(STDERR, "Subscribed services PRM extraction test failed\n");
	exit(1);
}

$invalidAuthorizationIds = array('', '0', '-1', 'abc', '9223372036854775808');
foreach ($invalidAuthorizationIds as $invalidAuthorizationId) {
	$failedAsExpected = false;
	try {
		LmdbEnedisClient::normalizeAuthorizationId($invalidAuthorizationId);
	} catch (LmdbEnedisApiException $e) {
		$failedAsExpected = true;
	}
	if (!$failedAsExpected) {
		fwrite(STDERR, "Authorization identifier validation test failed\n");
		exit(1);
	}
}

$ambiguousServices = $subscribedServices;
$ambiguousServices['serviceSouscrit'][] = array(
	'pointId' => '99999999999999',
	'serviceCode' => 'ACCES',
	'etatCode' => 'ACTIF',
	'autorisation' => array('autorisationId' => 987654321),
);
$ambiguousRejected = false;
try {
	LmdbEnedisClient::extractUsagePointIdFromSubscribedServices($ambiguousServices, $authorizationId);
} catch (LmdbEnedisApiException $e) {
	$ambiguousRejected = true;
}
if (!$ambiguousRejected) {
	fwrite(STDERR, "Ambiguous subscribed services response test failed\n");
	exit(1);
}

$unboundServices = $subscribedServices;
unset($unboundServices['serviceSouscrit'][0]['autorisation'], $unboundServices['serviceSouscrit'][1]['autorisation']);
$unboundRejected = false;
try {
	LmdbEnedisClient::extractUsagePointIdFromSubscribedServices($unboundServices, $authorizationId);
} catch (LmdbEnedisApiException $e) {
	$unboundRejected = true;
}
if (!$unboundRejected) {
	fwrite(STDERR, "Unbound subscribed services response test failed\n");
	exit(1);
}
if (!$blockedUrl) {
	fwrite(STDERR, "Client URL allowlist test failed\n");
	exit(1);
}

$invalidCalls = array(
	array('unknown', '12345678901234', '2026-08-01', '2026-08-02'),
	array(LmdbEnedisClient::RESOURCE_DAILY_CONSUMPTION, '123', '2026-08-01', '2026-08-02'),
	array(LmdbEnedisClient::RESOURCE_DAILY_CONSUMPTION, '12345678901234', '2026-02-30', '2026-03-01'),
	array(LmdbEnedisClient::RESOURCE_DAILY_CONSUMPTION, '12345678901234', '2026-08-02', '2026-08-02'),
	array(LmdbEnedisClient::RESOURCE_CONSUMPTION_LOAD_CURVE, '12345678901234', '2026-08-01', '2026-08-09'),
	array(LmdbEnedisClient::RESOURCE_CONSUMPTION_LOAD_CURVE, '12345678901234', '2020-01-01', '2020-01-02'),
	array(LmdbEnedisClient::RESOURCE_DAILY_CONSUMPTION, '12345678901234', '2020-01-01', '2020-01-02'),
);

foreach ($invalidCalls as $arguments) {
	$failedAsExpected = false;
	try {
		$client->fetchMeasurements($arguments[0], $arguments[1], $arguments[2], $arguments[3]);
	} catch (LmdbEnedisApiException $e) {
		$failedAsExpected = true;
	}
	if (!$failedAsExpected) {
		fwrite(STDERR, "Client validation test failed\n");
		exit(1);
	}
}

print "LmdbEnedisClient validation tests passed\n";
