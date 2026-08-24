<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

if (!defined('CSRFCHECK_WITH_TOKEN')) {
	define('CSRFCHECK_WITH_TOKEN', '1');
}

$res = 0;
if (!$res && file_exists('../../../main.inc.php')) {
	$res = include '../../../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = include '../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/../lib/lmdbenedis.lib.php';
require_once __DIR__.'/../lib/lmdbenedis_access.lib.php';
require_once __DIR__.'/../class/lmdbenedisconfig.class.php';
require_once __DIR__.'/../class/lmdbenedisclient.class.php';
require_once __DIR__.'/../class/lmdbenedisauthorization.class.php';

$langs->loadLangs(array('admin', 'lmdbenedis@lmdbenedis'));
if (!isModEnabled('lmdbenedis') || !lmdbenedisUserIsFullAdmin($user)) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$setupUrl = dol_buildpath('/lmdbenedis/admin/setup.php', 1);

if ($action === 'save_settings') {
	$settings = array(
		'LMDBENEDIS_ENVIRONMENT' => GETPOST('LMDBENEDIS_ENVIRONMENT', 'aZ09'),
		'LMDBENEDIS_API_BASE_URL' => rtrim(trim(GETPOST('LMDBENEDIS_API_BASE_URL', 'url')), '/'),
		'LMDBENEDIS_TOKEN_URL' => trim(GETPOST('LMDBENEDIS_TOKEN_URL', 'url')),
		'LMDBENEDIS_SUBSCRIBED_SERVICES_URL' => rtrim(trim(GETPOST('LMDBENEDIS_SUBSCRIBED_SERVICES_URL', 'url')), '/'),
		'LMDBENEDIS_CLIENT_ID' => trim(GETPOST('LMDBENEDIS_CLIENT_ID', 'alphanohtml')),
		'LMDBENEDIS_AUTHORIZE_URL' => rtrim(trim(GETPOST('LMDBENEDIS_AUTHORIZE_URL', 'url')), '/'),
		'LMDBENEDIS_AUTHORIZATION_DURATION' => GETPOST('LMDBENEDIS_AUTHORIZATION_DURATION', 'aZ09'),
		'LMDBENEDIS_BACKFILL_DAYS' => (string) min(1100, max(1, GETPOSTINT('LMDBENEDIS_BACKFILL_DAYS'))),
		'LMDBENEDIS_SYNC_LAG_DAYS' => (string) min(30, max(0, GETPOSTINT('LMDBENEDIS_SYNC_LAG_DAYS'))),
		'LMDBENEDIS_HTTP_TIMEOUT' => (string) min(300, max(5, GETPOSTINT('LMDBENEDIS_HTTP_TIMEOUT'))),
		'LMDBENEDIS_CRON_MAX_PRMS' => (string) min(1000, max(1, GETPOSTINT('LMDBENEDIS_CRON_MAX_PRMS'))),
	);
	$error = 0;
	$endpointError = false;
	try {
		new LmdbEnedisClient('validation-client', 'validation-secret', $settings['LMDBENEDIS_API_BASE_URL'], $settings['LMDBENEDIS_TOKEN_URL'], (int) $settings['LMDBENEDIS_HTTP_TIMEOUT'], $settings['LMDBENEDIS_SUBSCRIBED_SERVICES_URL']);
	} catch (LmdbEnedisApiException $e) {
		$error++;
		$endpointError = true;
		setEventMessages($langs->trans('LmdbEnedisInvalidEndpoints'), null, 'errors');
	}
	if (!isset(LmdbEnedisAuthorization::getEnvironmentOptions()[$settings['LMDBENEDIS_ENVIRONMENT']]) || !LmdbEnedisAuthorization::isAllowedAuthorizeUrl($settings['LMDBENEDIS_AUTHORIZE_URL']) || !isset(LmdbEnedisAuthorization::getDurationOptions()[$settings['LMDBENEDIS_AUTHORIZATION_DURATION']])) {
		$error++;
		$endpointError = true;
		setEventMessages($langs->trans('LmdbEnedisInvalidAuthorizationSettings'), null, 'errors');
	}
	if (!$error) {
		$db->begin();
		foreach ($settings as $name => $value) {
			if (dolibarr_set_const($db, $name, $value, 'chaine', 0, '', (int) $conf->entity) <= 0) {
				$error++;
			}
		}
		$clientSecret = GETPOST('LMDBENEDIS_CLIENT_SECRET', 'password');
		if ($clientSecret !== '' && LmdbEnedisConfig::setClientSecret($db, $clientSecret) <= 0) {
			$error++;
		}
		if ($error) {
			$db->rollback();
		} else {
			$db->commit();
		}
	}
	if ($error) {
		if (!$endpointError) {
			setEventMessages($langs->trans('Error'), null, 'errors');
		}
	} else {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	}
	header('Location: '.$setupUrl);
	exit;
}

if ($action === 'confirm_clear_secret') {
	if (LmdbEnedisConfig::setClientSecret($db, '') > 0) {
		setEventMessages($langs->trans('LmdbEnedisSecretCleared'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
	header('Location: '.$setupUrl);
	exit;
}

if ($action === 'test_connection') {
	try {
		$client = new LmdbEnedisClient();
		$client->testConnection();
		setEventMessages($langs->trans('LmdbEnedisConnectionSuccessful'), null, 'mesgs');
	} catch (Throwable $e) {
		setEventMessages($langs->trans('LmdbEnedisConnectionFailed', dol_escape_htmltag($e->getMessage())), null, 'errors');
	}
	header('Location: '.$setupUrl);
	exit;
}

$form = new Form($db);
$environmentOptions = array();
foreach (LmdbEnedisAuthorization::getEnvironmentOptions() as $environment => $label) {
	$environmentOptions[$environment] = $langs->trans('LmdbEnedisEnvironment'.ucfirst($environment));
}
$durationOptions = array();
foreach (LmdbEnedisAuthorization::getDurationOptions() as $duration => $label) {
	$durationOptions[$duration] = $langs->trans('LmdbEnedisAuthorizationDuration'.$duration);
}
$callbackUrl = LmdbEnedisAuthorization::getCallbackUrl();
$formConfirm = '';
if ($action === 'clear_secret') {
	$formConfirm = $form->formconfirm(
		$setupUrl,
		$langs->trans('LmdbEnedisClearSecret'),
		$langs->trans('LmdbEnedisConfirmClearSecret'),
		'confirm_clear_secret',
		'',
		0,
		1
	);
}
llxHeader('', $langs->trans('LmdbEnedisSetup'));
lmdbenedisPrintAdminHeader('settings');
print $formConfirm;

print '<div class="info">'.$langs->trans('LmdbEnedisSetupHelp').'</div><br>';
print '<form method="POST" action="'.$setupUrl.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_settings">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('LmdbEnedisConnectionSettings').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('LmdbEnedisEnvironment').'</td><td>'.$form->selectarray('LMDBENEDIS_ENVIRONMENT', $environmentOptions, getDolGlobalString('LMDBENEDIS_ENVIRONMENT', 'sandbox'), 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'<br><span class="opacitymedium">'.$langs->trans('LmdbEnedisEnvironmentHelp').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbEnedisApiBaseUrl').'</td><td><input class="flat minwidth500" name="LMDBENEDIS_API_BASE_URL" value="'.dol_escape_htmltag(getDolGlobalString('LMDBENEDIS_API_BASE_URL')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbEnedisTokenUrl').'</td><td><input class="flat minwidth500" name="LMDBENEDIS_TOKEN_URL" value="'.dol_escape_htmltag(getDolGlobalString('LMDBENEDIS_TOKEN_URL')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbEnedisSubscribedServicesUrl').'</td><td><input class="flat minwidth500" name="LMDBENEDIS_SUBSCRIBED_SERVICES_URL" value="'.dol_escape_htmltag(getDolGlobalString('LMDBENEDIS_SUBSCRIBED_SERVICES_URL', 'https://gw.ext.prod.api.enedis.fr/subscribed_services/v1')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbEnedisClientId').'</td><td><input class="flat minwidth300" autocomplete="off" name="LMDBENEDIS_CLIENT_ID" value="'.dol_escape_htmltag(getDolGlobalString('LMDBENEDIS_CLIENT_ID')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbEnedisClientSecret').'</td><td><input class="flat minwidth300" type="password" autocomplete="new-password" name="LMDBENEDIS_CLIENT_SECRET" value="" placeholder="'.(LmdbEnedisConfig::getClientSecret() !== '' ? dol_escape_htmltag($langs->trans('LmdbEnedisSecretAlreadySaved')) : '').'"></td></tr>';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('LmdbEnedisAuthorizationSettings').'</td></tr>';
if (!LmdbEnedisAuthorization::isProductionEnvironment()) {
	print '<tr class="oddeven"><td colspan="2"><div class="warning">'.$langs->trans('LmdbEnedisSandboxAuthorizationUnavailable').'</div></td></tr>';
}
print '<tr class="oddeven"><td>'.$langs->trans('LmdbEnedisAuthorizeUrl').'</td><td><input class="flat minwidth500" name="LMDBENEDIS_AUTHORIZE_URL" value="'.dol_escape_htmltag(getDolGlobalString('LMDBENEDIS_AUTHORIZE_URL')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbEnedisAuthorizationDuration').'</td><td>'.$form->selectarray('LMDBENEDIS_AUTHORIZATION_DURATION', $durationOptions, getDolGlobalString('LMDBENEDIS_AUTHORIZATION_DURATION', 'P3Y'), 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbEnedisAuthorizationCallbackUrl').'</td><td><code>'.dol_escape_htmltag($callbackUrl).'</code><br><span class="opacitymedium">'.$langs->trans('LmdbEnedisAuthorizationCallbackHelp').'</span>';
if (!LmdbEnedisAuthorization::isAllowedCallbackUrl($callbackUrl)) {
	print '<br><span class="error">'.$langs->trans('LmdbEnedisAuthorizationCallbackHttpsRequired').'</span>';
}
print '</td></tr>';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('LmdbEnedisSynchronizationSettings').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbEnedisBackfillDays').'</td><td><input class="flat width75" name="LMDBENEDIS_BACKFILL_DAYS" value="'.getDolGlobalInt('LMDBENEDIS_BACKFILL_DAYS', 30).'"> '.$langs->trans('days').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbEnedisSyncLagDays').'</td><td><input class="flat width75" name="LMDBENEDIS_SYNC_LAG_DAYS" value="'.getDolGlobalInt('LMDBENEDIS_SYNC_LAG_DAYS', 2).'"> '.$langs->trans('days').' <span class="opacitymedium">'.$langs->trans('LmdbEnedisSyncLagHelp').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbEnedisHttpTimeout').'</td><td><input class="flat width75" name="LMDBENEDIS_HTTP_TIMEOUT" value="'.getDolGlobalInt('LMDBENEDIS_HTTP_TIMEOUT', 60).'"> '.$langs->trans('Seconds').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbEnedisCronMaxPrms').'</td><td><input class="flat width75" name="LMDBENEDIS_CRON_MAX_PRMS" value="'.getDolGlobalInt('LMDBENEDIS_CRON_MAX_PRMS', 50).'"></td></tr>';
print '</table>';
print '<div class="center"><input class="button button-save" type="submit" value="'.$langs->trans('Save').'"></div>';
print '</form>';

print '<div class="tabsAction">';
print '<form class="inline-block" method="POST" action="'.$setupUrl.'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="test_connection"><input class="button" type="submit" value="'.$langs->trans('LmdbEnedisTestConnection').'"></form>';
if (LmdbEnedisConfig::getClientSecret() !== '') {
	print ' <a class="button button-delete" href="'.$setupUrl.'?action=clear_secret&token='.newToken().'">'.$langs->trans('LmdbEnedisClearSecret').'</a>';
}
print '</div>';

print dol_get_fiche_end();
llxFooter();
