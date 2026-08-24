<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/../class/lmdbenedisauthorization.class.php';

$authorizeUrl = 'https://mon-compte-particulier.enedis.fr/dataconnect/v1/oauth2/authorize';
$callbackUrl = 'https://erp.example.test/custom/lmdbenedis/authorization_callback.php';
$state = '0000000001'.str_repeat('a', 63).'0';
$service = new LmdbEnedisAuthorization(null, 'client-id', $authorizeUrl, 'P3Y', $callbackUrl);
$url = $service->buildAuthorizationUrl($state);
$query = array();
parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
if (($query['client_id'] ?? '') !== 'client-id' || ($query['response_type'] ?? '') !== 'code' || ($query['state'] ?? '') !== $state || ($query['duration'] ?? '') !== 'P3Y') {
	fwrite(STDERR, "Authorization URL test failed\n");
	exit(1);
}

$generatedState = LmdbEnedisAuthorization::generateState(42);
if (!preg_match('/^0000000042[a-f0-9]{63}[0-9]$/D', $generatedState)) {
	fwrite(STDERR, "Authorization state generation test failed\n");
	exit(1);
}

if (!LmdbEnedisAuthorization::isAllowedAuthorizeUrl($authorizeUrl)
	|| LmdbEnedisAuthorization::isAllowedAuthorizeUrl('https://example.test/dataconnect/v1/oauth2/authorize')
	|| !LmdbEnedisAuthorization::isAllowedCallbackUrl($callbackUrl)
	|| LmdbEnedisAuthorization::isAllowedCallbackUrl('http://erp.example.test/callback')) {
	fwrite(STDERR, "Authorization endpoint allowlist test failed\n");
	exit(1);
}

$start = gmmktime(0, 0, 0, 8, 25, 2026);
$expectedEnd = gmmktime(0, 0, 0, 8, 25, 2029);
if (LmdbEnedisAuthorization::calculateAuthorizationEnd('P3Y', $start) !== $expectedEnd) {
	fwrite(STDERR, "Authorization duration test failed\n");
	exit(1);
}

$invalidDurationRejected = false;
try {
	LmdbEnedisAuthorization::calculateAuthorizationEnd('P4Y', $start);
} catch (InvalidArgumentException $e) {
	$invalidDurationRejected = true;
}
if (!$invalidDurationRejected) {
	fwrite(STDERR, "Authorization duration allowlist test failed\n");
	exit(1);
}

print "LmdbEnedisAuthorization validation tests passed\n";
