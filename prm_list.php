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
require_once __DIR__.'/class/lmdbenedisprm.class.php';
require_once __DIR__.'/lib/lmdbenedis_access.lib.php';

$langs->load('lmdbenedis@lmdbenedis');
if (!isModEnabled('lmdbenedis') || !lmdbenedisCanDo($user, 'prm', 'read')) {
	accessforbidden();
}

$searchPrm = GETPOST('search_prm', 'restricthtml');
$searchLabel = GETPOST('search_label', 'restricthtml');
$searchStatus = GETPOST('search_status', 'int');
$searchAuthorization = GETPOST('search_authorization', 'aZ09');
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 't.usage_point_id';
$sortorder = GETPOST('sortorder', 'aZ09comma') ?: 'ASC';
$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : $conf->liste_limit;
$page = max(0, GETPOSTINT('page'));
$offset = $limit * $page;

$where = ' WHERE t.entity = '.((int) $conf->entity);
if ($searchPrm !== '') {
	$where .= " AND t.usage_point_id LIKE '%".$db->escape($searchPrm)."%'";
}
if ($searchLabel !== '') {
	$where .= " AND t.label LIKE '%".$db->escape($searchLabel)."%'";
}
if ($searchStatus !== '') {
	$where .= ' AND t.status = '.((int) $searchStatus);
}
$today = gmdate('Y-m-d');
if ($searchAuthorization === 'active') {
	$where .= " AND t.authorization_reference IS NOT NULL AND t.authorization_reference <> ''";
	$where .= " AND (t.authorization_end IS NULL OR t.authorization_end >= '".$db->escape($today)."')";
} elseif ($searchAuthorization === 'required') {
	$where .= " AND (t.authorization_reference IS NULL OR t.authorization_reference = '')";
} elseif ($searchAuthorization === 'expired') {
	$where .= " AND t.authorization_reference IS NOT NULL AND t.authorization_reference <> ''";
	$where .= " AND t.authorization_end IS NOT NULL AND t.authorization_end < '".$db->escape($today)."'";
}
$countSql = 'SELECT COUNT(*) AS nb FROM '.MAIN_DB_PREFIX.'lmdbenedis_prm AS t'.$where;
$countResult = $db->query($countSql);
if (!$countResult) {
	dol_print_error($db);
	exit;
}
$countRow = $db->fetch_object($countResult);
$totalnboflines = is_object($countRow) ? (int) $countRow->nb : 0;
$db->free($countResult);

$sql = 'SELECT t.rowid, t.usage_point_id, t.label, t.authorization_reference, t.authorization_end, t.status, t.last_sync_at, t.last_sync_status, t.fk_powerplant';
$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbenedis_prm AS t'.$where;
$allowedSorts = array('t.usage_point_id', 't.label', 't.authorization_reference', 't.status', 't.last_sync_at');
if (!in_array($sortfield, $allowedSorts, true)) {
	$sortfield = 't.usage_point_id';
}
$sortorder = strtoupper($sortorder) === 'DESC' ? 'DESC' : 'ASC';
$sql .= ' ORDER BY '.$sortfield.' '.$sortorder;
$sql .= ' LIMIT '.((int) $limit).' OFFSET '.((int) $offset);
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$rows = array();
while (is_object($row = $db->fetch_object($resql))) {
	$rows[] = $row;
}
$db->free($resql);

$arrayfields = array(
	't.usage_point_id' => array('label' => 'LmdbEnedisUsagePointId', 'checked' => 1, 'position' => 10),
	't.label' => array('label' => 'Label', 'checked' => 1, 'position' => 20),
	't.authorization_reference' => array('label' => 'LmdbEnedisAuthorization', 'checked' => 1, 'position' => 30),
	't.status' => array('label' => 'Status', 'checked' => 1, 'position' => 40),
	't.last_sync_at' => array('label' => 'LmdbEnedisLastSync', 'checked' => 1, 'position' => 50),
	't.last_sync_status' => array('label' => 'LmdbEnedisLastSyncStatus', 'checked' => 1, 'position' => 60),
);
$selectedFields = Form::multiSelectArrayWithCheckbox('selectedfields', $arrayfields, 'lmdbenedisprmlist');
$form = new Form($db);
$param = '&search_prm='.urlencode($searchPrm).'&search_label='.urlencode($searchLabel).'&search_authorization='.urlencode($searchAuthorization).'&search_status='.urlencode((string) $searchStatus);
$newButton = lmdbenedisCanDo($user, 'prm', 'write') ? dolGetButtonTitle($langs->trans('LmdbEnedisCreatePrm'), '', 'fa fa-plus-circle', dol_buildpath('/lmdbenedis/prm_card.php', 1).'?action=create') : '';

llxHeader('', $langs->trans('LmdbEnedisPrmList'));
print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print_barre_liste($langs->trans('LmdbEnedisPrmList'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', count($rows), $totalnboflines, 'fa-bolt', 0, $newButton.$selectedFields, '', $limit);
print '<div class="div-table-responsive"><table class="tagtable liste centpercent">';
print '<tr class="liste_titre_filter">';
$visibleColumns = 0;
foreach ($arrayfields as $field => $definition) {
	if (empty($definition['checked'])) {
		continue;
	}
	$visibleColumns++;
	print '<td>';
	if ($field === 't.usage_point_id') {
		print '<input class="flat maxwidth150" name="search_prm" value="'.dol_escape_htmltag($searchPrm).'">';
	} elseif ($field === 't.label') {
		print '<input class="flat maxwidth150" name="search_label" value="'.dol_escape_htmltag($searchLabel).'">';
	} elseif ($field === 't.status') {
		print $form->selectarray('search_status', array(1 => $langs->trans('Enabled'), 0 => $langs->trans('Disabled')), $searchStatus, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125');
	} elseif ($field === 't.authorization_reference') {
		print $form->selectarray('search_authorization', array(
			'active' => $langs->trans('LmdbEnedisAuthorizationActive'),
			'required' => $langs->trans('LmdbEnedisAuthorizationRequiredStatus'),
			'expired' => $langs->trans('LmdbEnedisAuthorizationExpiredStatus'),
		), $searchAuthorization, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150');
	}
	print '</td>';
}
print '<td class="liste_titre right"><button class="liste_titre button_search" type="submit" name="button_search" value="x">'.img_picto('', 'search').'</button> <a class="liste_titre" href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" title="'.$langs->trans('RemoveFilter').'">'.img_picto('', 'remove-filter').'</a></td></tr>';
print '<tr class="liste_titre">';
foreach ($arrayfields as $field => $definition) {
	if (!empty($definition['checked'])) {
		print_liste_field_titre($langs->trans($definition['label']), $_SERVER['PHP_SELF'], $field, '', $param, '', $sortfield, $sortorder);
	}
}
print '<td></td></tr>';

if ($rows === array()) {
	print '<tr class="oddeven"><td colspan="'.((int) $visibleColumns + 1).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
} else {
	foreach ($rows as $row) {
		$prm = new LmdbEnedisPrm($db);
		$prm->id = (int) $row->rowid;
		$prm->usage_point_id = (string) $row->usage_point_id;
		$prm->authorization_reference = (string) $row->authorization_reference;
		$prm->authorization_end = $row->authorization_end;
		$prm->status = (int) $row->status;
		print '<tr class="oddeven">';
		foreach ($arrayfields as $field => $definition) {
			if (empty($definition['checked'])) {
				continue;
			}
			if ($field === 't.usage_point_id') {
				print '<td>'.$prm->getNomUrl(1).'</td>';
			} elseif ($field === 't.label') {
				print '<td>'.dol_escape_htmltag((string) $row->label).'</td>';
			} elseif ($field === 't.status') {
				print '<td>'.$prm->getLibStatut(5).'</td>';
			} elseif ($field === 't.authorization_reference') {
				$authorizationClass = $prm->hasActiveAuthorization() ? 'badge-status4' : ($prm->authorization_reference !== '' ? 'badge-status8' : 'badge-status0');
				$authorizationLabel = $prm->hasActiveAuthorization() ? $langs->trans('LmdbEnedisAuthorizationActive') : ($prm->authorization_reference !== '' ? $langs->trans('LmdbEnedisAuthorizationExpiredStatus') : $langs->trans('LmdbEnedisAuthorizationRequiredStatus'));
				print '<td><span class="badge '.$authorizationClass.'">'.dol_escape_htmltag($authorizationLabel).'</span></td>';
			} elseif ($field === 't.last_sync_at') {
				print '<td>'.(!empty($row->last_sync_at) ? dol_print_date($db->jdate((string) $row->last_sync_at), 'dayhour') : '').'</td>';
			} else {
				$statusClass = (string) $row->last_sync_status === 'success' ? 'badge-status4' : ((string) $row->last_sync_status === 'error' ? 'badge-status8' : 'badge-status0');
				$statusLabel = (string) $row->last_sync_status === 'success' ? $langs->trans('LmdbEnedisSyncStatusSuccess') : ((string) $row->last_sync_status === 'error' ? $langs->trans('LmdbEnedisSyncStatusError') : (string) $row->last_sync_status);
				print '<td>'.(!empty($row->last_sync_status) ? '<span class="badge '.$statusClass.'">'.dol_escape_htmltag($statusLabel).'</span>' : '').'</td>';
			}
		}
		print '<td class="right"><a href="'.dol_buildpath('/lmdbenedis/prm_measure.php', 1).'?id='.(int) $row->rowid.'">'.img_picto($langs->trans('LmdbEnedisMeasurements'), 'chart').'</a></td>';
		print '</tr>';
	}
}
print '</table></div></form>';
llxFooter();
