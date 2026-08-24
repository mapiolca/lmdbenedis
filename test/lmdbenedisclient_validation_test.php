<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/../class/lmdbenedisclient.class.php';

$client = new LmdbEnedisClient(
	'client-id',
	'client-secret',
	'https://gw.ext.prod.api.enedis.fr/mesure_synchrone_auto/v1',
	'https://gw.ext.prod.api.enedis.fr/oauth2/v3/token',
	5
);

$blockedUrl = false;
try {
	new LmdbEnedisClient('client-id', 'client-secret', 'https://127.0.0.1/measure', 'https://gw.ext.prod.api.enedis.fr/oauth2/v3/token', 5);
} catch (LmdbEnedisApiException $e) {
	$blockedUrl = true;
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
