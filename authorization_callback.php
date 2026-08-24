<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1');
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}

// GETPOST() is not available before main.inc.php. Decode only the fixed-width
// entity prefix of the high-entropy state so Dolibarr loads the right entity.
$rawState = isset($_GET['state']) && is_string($_GET['state']) ? $_GET['state'] : '';
if (preg_match('/^[0-9]{10}[a-f0-9]{63}[0-9]$/D', $rawState)) {
	$callbackEntity = (int) substr($rawState, 0, 10);
	if ($callbackEntity > 0 && !defined('DOLENTITY')) {
		define('DOLENTITY', $callbackEntity);
	}
}

$res = 0;
if (!$res && file_exists('../../main.inc.php')) {
	$res = include '../../main.inc.php';
}
if (!$res && file_exists('../main.inc.php')) {
	$res = include '../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once __DIR__.'/class/lmdbenedisauthorization.class.php';

$langs->load('lmdbenedis@lmdbenedis');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$state = GETPOST('state', 'aZ09');
$authorizationId = GETPOST('autorisation_id', 'aZ09');
$errorCode = GETPOST('error', 'aZ09');
$result = array('success' => false, 'prm_id' => 0, 'result' => 'invalid_callback');

try {
	$authorization = new LmdbEnedisAuthorization($db);
	$result = $authorization->consumeCallback($state, $authorizationId, $errorCode);
} catch (Throwable $e) {
	dol_syslog('LMDB Enedis authorization callback failed: '.$e->getMessage(), LOG_ERR);
	$result = array('success' => false, 'prm_id' => 0, 'result' => 'technical_error');
}

$resultTranslation = array(
	'granted' => 'LmdbEnedisAuthorizationCallbackGranted',
	'denied' => 'LmdbEnedisAuthorizationCallbackDenied',
	'expired' => 'LmdbEnedisAuthorizationCallbackExpired',
	'prm_mismatch' => 'LmdbEnedisAuthorizationCallbackPrmMismatch',
	'prm_not_found' => 'LmdbEnedisAuthorizationCallbackPrmNotFound',
	'requesting_user_unauthorized' => 'LmdbEnedisAuthorizationCallbackUserUnauthorized',
	'feature_unavailable' => 'LmdbEnedisAuthorizationCallbackUnavailable',
	'already_processed' => 'LmdbEnedisAuthorizationCallbackAlreadyProcessed',
	'invalid_state' => 'LmdbEnedisAuthorizationCallbackInvalid',
	'invalid_callback' => 'LmdbEnedisAuthorizationCallbackInvalid',
	'technical_error' => 'LmdbEnedisAuthorizationCallbackTechnicalError',
);
$messageKey = isset($resultTranslation[$result['result']]) ? $resultTranslation[$result['result']] : 'LmdbEnedisAuthorizationCallbackInvalid';
$title = $result['success'] ? $langs->trans('LmdbEnedisAuthorizationCallbackSuccessTitle') : $langs->trans('LmdbEnedisAuthorizationCallbackErrorTitle');
http_response_code($result['success'] ? 200 : 400);

top_htmlhead('', $title);
print '<body><main class="fiche"><div class="center">';
print '<h1>'.dol_escape_htmltag($title).'</h1>';
print '<p>'.dol_escape_htmltag($langs->trans($messageKey)).'</p>';
if ($result['success'] && $result['prm_id'] > 0) {
	print '<p><a class="button" href="'.dol_buildpath('/lmdbenedis/prm_card.php', 3).'?id='.(int) $result['prm_id'].'">'.$langs->trans('LmdbEnedisBackToPrm').'</a></p>';
}
print '<p class="opacitymedium">'.$langs->trans('LmdbEnedisAuthorizationCallbackClose').'</p>';
print '</div></main></body></html>';
