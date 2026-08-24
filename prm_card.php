<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once __DIR__.'/class/lmdbenedisprm.class.php';
require_once __DIR__.'/class/lmdbenedissynchronizer.class.php';
require_once __DIR__.'/class/lmdbenedisconfig.class.php';
require_once __DIR__.'/class/lmdbenedisauthorization.class.php';
require_once __DIR__.'/lib/lmdbenedis.lib.php';
require_once __DIR__.'/lib/lmdbenedis_access.lib.php';

$langs->loadLangs(array('companies', 'lmdbenedis@lmdbenedis'));
if (!isModEnabled('lmdbenedis') || !lmdbenedisCanDo($user, 'prm', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$object = new LmdbEnedisPrm($db);
$form = new Form($db);
$isCreate = $action === 'create' || ($id <= 0 && $action !== 'add');
$submittedResources = null;
if ($id > 0 && $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}

if ($action === 'add' || $action === 'update') {
	if (!lmdbenedisCanDo($user, 'prm', 'write', $id > 0 ? $object : null)) {
		accessforbidden();
	}
	if ($action === 'add') {
		$object = new LmdbEnedisPrm($db);
	}
	$object->usage_point_id = GETPOST('usage_point_id', 'aZ09');
	$object->label = GETPOST('label', 'restricthtml');
	$object->fk_soc = GETPOSTINT('fk_soc');
	if (lmdbenedisCanReadPowerPlant($user)) {
		$object->fk_powerplant = GETPOSTINT('fk_powerplant');
	} elseif ($action === 'add') {
		$object->fk_powerplant = 0;
	}
	$object->status = GETPOSTINT('status') ? 1 : 0;
	$resources = GETPOST('resources', 'array');
	$resources = is_array($resources) ? array_values(array_map('strval', $resources)) : array();
	$resources = array_values(array_unique(array_intersect($resources, array_keys(LmdbEnedisClient::getResourcePaths()))));
	$submittedResources = $resources;
	$db->begin();
	if ($resources === array()) {
		$object->error = $langs->trans('LmdbEnedisAtLeastOneResource');
		$object->errors[] = $object->error;
		$result = -1;
	} else {
		$result = $action === 'add' ? $object->create($user) : $object->update($user);
	}
	if ($result > 0) {
		if ($action === 'add') {
			$id = (int) $object->id;
		}
		if ($object->setEnabledStreams($resources, $user) < 0) {
			$result = -1;
		} else {
			$db->commit();
			setEventMessages($langs->trans($action === 'add' ? 'LmdbEnedisPrmCreated' : 'LmdbEnedisPrmUpdated'), null, 'mesgs');
			header('Location: '.dol_buildpath('/lmdbenedis/prm_card.php', 1).'?id='.(int) $id);
			exit;
		}
	}
	$db->rollback();
	if ($action === 'add') {
		$id = 0;
		$object->id = 0;
	}
	setEventMessages($object->error, $object->errors, 'errors');
	$isCreate = $action === 'add';
}

if ($action === 'confirm_delete' && $id > 0) {
	if (!lmdbenedisCanDo($user, 'prm', 'delete', $object)) {
		accessforbidden();
	}
	if ($object->delete($user) > 0) {
		setEventMessages($langs->trans('LmdbEnedisPrmDeleted'), null, 'mesgs');
		header('Location: '.dol_buildpath('/lmdbenedis/prm_list.php', 1));
		exit;
	}
	setEventMessages($object->error, $object->errors, 'errors');
}

if ($action === 'sync' && $id > 0) {
	if (!lmdbenedisCanDo($user, 'prm', 'sync', $object)) {
		accessforbidden();
	}
	if (!LmdbEnedisConfig::isConnectionConfigured()) {
		setEventMessages($langs->trans('LmdbEnedisConnectionNotConfigured'), null, 'errors');
	} else {
		$synchronizer = new LmdbEnedisSynchronizer($db);
		$result = $synchronizer->syncPrm($id);
		$message = $langs->trans('LmdbEnedisSyncResult', $result['streams'], $result['points'], $result['errors']);
		setEventMessages($message, $synchronizer->errors, $result['errors'] ? 'warnings' : 'mesgs');
	}
	header('Location: '.dol_buildpath('/lmdbenedis/prm_card.php', 1).'?id='.(int) $id);
	exit;
}

if ($action === 'request_authorization' && $id > 0) {
	if (!lmdbenedisCanDo($user, 'prm', 'write', $object)) {
		accessforbidden();
	}
	try {
		$authorization = new LmdbEnedisAuthorization($db);
		$authorizationUrl = $authorization->createRequest($object, $user);
		header('Location: '.$authorizationUrl, true, 303);
		exit;
	} catch (Throwable $e) {
		setEventMessages($langs->trans('LmdbEnedisAuthorizationStartFailed'), null, 'errors');
		dol_syslog('LMDB Enedis authorization request failed: '.$e->getMessage(), LOG_ERR);
		header('Location: '.dol_buildpath('/lmdbenedis/prm_card.php', 1).'?id='.(int) $id);
		exit;
	}
}

$formConfirm = '';
if ($action === 'delete' && $id > 0 && lmdbenedisCanDo($user, 'prm', 'delete', $object)) {
	$formConfirm = $form->formconfirm(
		dol_buildpath('/lmdbenedis/prm_card.php', 1).'?id='.(int) $id,
		$langs->trans('Delete'),
		$langs->trans('ConfirmDeleteObject'),
		'confirm_delete',
		'',
		0,
		1
	);
}

llxHeader('', $isCreate ? $langs->trans('LmdbEnedisCreatePrm') : $langs->trans('LmdbEnedisUsagePointId'));
print $formConfirm;

if ($isCreate || $action === 'edit' || $action === 'add' || ($action === 'update' && !empty($object->error))) {
	if (!lmdbenedisCanDo($user, 'prm', 'write', $id > 0 ? $object : null)) {
		accessforbidden();
	}
	if ($id > 0) {
		print dol_get_fiche_head(lmdbenedisPrmPrepareHead($object), 'card', $langs->trans('LmdbEnedisUsagePointId'), -1, 'fa-bolt');
	} else {
		print load_fiche_titre($langs->trans('LmdbEnedisCreatePrm'), '', 'fa-bolt');
	}
	$selectedResources = is_array($submittedResources) ? $submittedResources : ($id > 0 ? $object->getEnabledStreams() : array(
		LmdbEnedisClient::RESOURCE_DAILY_CONSUMPTION,
		LmdbEnedisClient::RESOURCE_DAILY_PRODUCTION,
		LmdbEnedisClient::RESOURCE_CONSUMPTION_LOAD_CURVE,
		LmdbEnedisClient::RESOURCE_PRODUCTION_LOAD_CURVE,
	));
	print '<form method="POST" action="'.dol_buildpath('/lmdbenedis/prm_card.php', 1).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.($id > 0 ? 'update' : 'add').'">';
	if ($id > 0) {
		print '<input type="hidden" name="id" value="'.((int) $id).'">';
	}
	print '<table class="border centpercent">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('LmdbEnedisUsagePointId').'</td><td><input class="flat minwidth200" maxlength="14" inputmode="numeric" name="usage_point_id" value="'.dol_escape_htmltag($object->usage_point_id).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Label').'</td><td><input class="flat minwidth300" name="label" value="'.dol_escape_htmltag($object->label).'"></td></tr>';
	print '<tr><td>'.$langs->trans('ThirdParty').'</td><td>'.$form->select_company((int) $object->fk_soc, 'fk_soc', '', 'SelectThirdParty', 1, 0, array(), 0, 'minwidth300').'</td></tr>';
	if (lmdbenedisCanReadPowerPlant($user)) {
		print '<tr><td>'.$langs->trans('PowerPlant').'</td><td>'.$form->selectarray('fk_powerplant', lmdbenedisPowerPlantOptions(), (int) $object->fk_powerplant, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
	}
	print '<tr><td>'.$langs->trans('LmdbEnedisAuthorization').'</td><td>';
	if ($id > 0 && $object->hasActiveAuthorization()) {
		print '<span class="badge badge-status4">'.$langs->trans('LmdbEnedisAuthorizationActive').'</span>';
	} elseif ($id > 0 && !empty($object->authorization_reference)) {
		print '<span class="badge badge-status8">'.$langs->trans('LmdbEnedisAuthorizationExpiredStatus').'</span>';
	} else {
		print '<span class="badge badge-status0">'.$langs->trans('LmdbEnedisAuthorizationRequired').'</span>';
	}
	print ' <span class="opacitymedium">'.$langs->trans('LmdbEnedisAuthorizationManagedByEnedis').'</span></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('LmdbEnedisResources').'</td><td>'.$form->multiselectarray('resources', lmdbenedisResourceOptions(), $selectedResources, 0, 0, 'minwidth500').'</td></tr>';
	print '<tr><td>'.$langs->trans('Status').'</td><td>'.$form->selectarray('status', array(1 => $langs->trans('Enabled'), 0 => $langs->trans('Disabled')), (int) $object->status, 0, 0, 0, '', 0, 0, 0, '', 'minwidth150').'</td></tr>';
	print '</table>';
	print '<div class="center"><input class="button button-save" type="submit" value="'.$langs->trans('Save').'"> <a class="button button-cancel" href="'.($id > 0 ? dol_buildpath('/lmdbenedis/prm_card.php', 1).'?id='.(int) $id : dol_buildpath('/lmdbenedis/prm_list.php', 1)).'">'.$langs->trans('Cancel').'</a></div>';
	print '</form>';
	if ($id > 0) {
		print dol_get_fiche_end();
	}
} else {
	print dol_get_fiche_head(lmdbenedisPrmPrepareHead($object), 'card', $langs->trans('LmdbEnedisUsagePointId'), -1, 'fa-bolt');
	print '<div class="fichecenter"><table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans('LmdbEnedisUsagePointId').'</td><td>'.$object->getNomUrl(1, 'nolink').'</td><td class="right">'.$object->getLibStatut(5).'</td></tr>';
	print '<tr><td>'.$langs->trans('Label').'</td><td colspan="2">'.dol_escape_htmltag($object->label).'</td></tr>';
	print '<tr><td>'.$langs->trans('ThirdParty').'</td><td colspan="2">';
	if ((int) $object->fk_soc > 0) {
		$thirdParty = new Societe($db);
		print $thirdParty->fetch((int) $object->fk_soc) > 0 ? $thirdParty->getNomUrl(1) : '';
	}
	print '</td></tr>';
	if (lmdbenedisCanReadPowerPlant($user)) {
		$powerPlantOptions = lmdbenedisPowerPlantOptions();
		print '<tr><td>'.$langs->trans('PowerPlant').'</td><td colspan="2">';
		if ((int) $object->fk_powerplant > 0 && isset($powerPlantOptions[(int) $object->fk_powerplant])) {
			print '<a href="'.dol_buildpath('/powerplantpv/powerplant_card.php', 1).'?id='.(int) $object->fk_powerplant.'">'.dol_escape_htmltag($powerPlantOptions[(int) $object->fk_powerplant]).'</a>';
		}
		print '</td></tr>';
	}
	$authorizationStatusClass = $object->hasActiveAuthorization() ? 'badge-status4' : (!empty($object->authorization_reference) ? 'badge-status8' : 'badge-status0');
	$authorizationStatusLabel = $object->hasActiveAuthorization() ? $langs->trans('LmdbEnedisAuthorizationActive') : (!empty($object->authorization_reference) ? $langs->trans('LmdbEnedisAuthorizationExpiredStatus') : $langs->trans('LmdbEnedisAuthorizationRequired'));
	print '<tr><td>'.$langs->trans('LmdbEnedisAuthorization').'</td><td colspan="2"><span class="badge '.$authorizationStatusClass.'">'.dol_escape_htmltag($authorizationStatusLabel).'</span></td></tr>';
	print '<tr><td>'.$langs->trans('LmdbEnedisAuthorizationReference').'</td><td colspan="2"><span class="opacitymedium">'.dol_escape_htmltag($object->authorization_reference).'</span></td></tr>';
	print '<tr><td>'.$langs->trans('LmdbEnedisAuthorizationEnd').'</td><td colspan="2">'.(!empty($object->authorization_end) ? dol_print_date($object->authorization_end, 'day') : '').'</td></tr>';
	$authorizationService = new LmdbEnedisAuthorization($db);
	$latestAuthorizationRequest = $authorizationService->getLatestRequest((int) $object->id, (int) $object->entity);
	if ($latestAuthorizationRequest !== array()) {
		$requestStatus = $latestAuthorizationRequest['status'];
		$requestStatusClass = $requestStatus === LmdbEnedisAuthorization::STATUS_GRANTED ? 'badge-status4' : ($requestStatus === LmdbEnedisAuthorization::STATUS_PENDING ? 'badge-status3' : 'badge-status8');
		$requestStatusKey = 'LmdbEnedisAuthorizationRequest'.ucfirst($requestStatus);
		print '<tr><td>'.$langs->trans('LmdbEnedisLastAuthorizationRequest').'</td><td colspan="2"><span class="badge '.$requestStatusClass.'">'.$langs->trans($requestStatusKey).'</span>';
		if ($latestAuthorizationRequest['date_creation'] !== '') {
			print ' <span class="opacitymedium">'.dol_print_date($latestAuthorizationRequest['date_creation'], 'dayhour').'</span>';
		}
		if ($latestAuthorizationRequest['error_code'] !== '') {
			print ' <span class="opacitymedium">'.dol_escape_htmltag($latestAuthorizationRequest['error_code']).'</span>';
		}
		print '</td></tr>';
	}
	print '<tr><td>'.$langs->trans('LmdbEnedisResources').'</td><td colspan="2">';
	$resourceOptions = lmdbenedisResourceOptions();
	foreach ($object->getEnabledStreams() as $resourceCode) {
		print '<span class="badge badge-status4 marginrightonlyshort">'.dol_escape_htmltag(isset($resourceOptions[$resourceCode]) ? $resourceOptions[$resourceCode] : $resourceCode).'</span>';
	}
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbEnedisLastSync').'</td><td colspan="2">'.(!empty($object->last_sync_at) ? dol_print_date($object->last_sync_at, 'dayhour') : '').'</td></tr>';
	$lastSyncClass = $object->last_sync_status === 'success' ? 'badge-status4' : ($object->last_sync_status === 'error' ? 'badge-status8' : 'badge-status0');
	$lastSyncLabel = $object->last_sync_status === 'success' ? $langs->trans('LmdbEnedisSyncStatusSuccess') : ($object->last_sync_status === 'error' ? $langs->trans('LmdbEnedisSyncStatusError') : $object->last_sync_status);
	print '<tr><td>'.$langs->trans('LmdbEnedisLastSyncStatus').'</td><td colspan="2">'.($lastSyncLabel !== '' ? '<span class="badge '.$lastSyncClass.'">'.dol_escape_htmltag($lastSyncLabel).'</span>' : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbEnedisLastSyncMessage').'</td><td colspan="2">'.dol_escape_htmltag($object->last_sync_message).'</td></tr>';
	print '</table></div>';
	print dol_get_fiche_end();

	print '<div class="tabsAction">';
	if (lmdbenedisCanDo($user, 'prm', 'write', $object)) {
		print '<a class="button" href="'.dol_buildpath('/lmdbenedis/prm_card.php', 1).'?id='.(int) $id.'&action=edit">'.$langs->trans('Modify').'</a>';
		if (LmdbEnedisAuthorization::isConfigured()) {
			print ' <form class="inline-block" method="POST" action="'.dol_buildpath('/lmdbenedis/prm_card.php', 1).'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="request_authorization"><input type="hidden" name="id" value="'.(int) $id.'"><input class="button" type="submit" value="'.$langs->trans($object->hasActiveAuthorization() ? 'LmdbEnedisRenewAuthorization' : 'LmdbEnedisRequestAuthorization').'"></form>';
		}
	}
	if (!empty($object->status) && $object->hasActiveAuthorization() && lmdbenedisCanDo($user, 'prm', 'sync', $object)) {
		print ' <form class="inline-block" method="POST" action="'.dol_buildpath('/lmdbenedis/prm_card.php', 1).'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="sync"><input type="hidden" name="id" value="'.(int) $id.'"><input class="button" type="submit" value="'.$langs->trans('LmdbEnedisSync').'"></form>';
	}
	if (lmdbenedisCanDo($user, 'prm', 'delete', $object)) {
		print ' <a class="button button-delete" href="'.dol_buildpath('/lmdbenedis/prm_card.php', 1).'?id='.(int) $id.'&action=delete&token='.newToken().'">'.$langs->trans('Delete').'</a>';
	}
	print '</div>';
}

llxFooter();
