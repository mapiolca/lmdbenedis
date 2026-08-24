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
require_once __DIR__.'/class/lmdbenedissynchronizer.class.php';
require_once __DIR__.'/class/lmdbenedisconfig.class.php';
require_once __DIR__.'/lib/lmdbenedis.lib.php';
require_once __DIR__.'/lib/lmdbenedis_access.lib.php';

$langs->load('lmdbenedis@lmdbenedis');
if (!isModEnabled('lmdbenedis') || !lmdbenedisCanDo($user, 'prm', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$object = new LmdbEnedisPrm($db);
if ($id <= 0 || $object->fetch($id) <= 0 || !lmdbenedisCanDo($user, 'prm', 'read', $object)) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$form = new Form($db);
$enabledResources = $object->getEnabledStreams();

if ($action === 'sync_period') {
	if (!lmdbenedisCanDo($user, 'prm', 'sync', $object)) {
		accessforbidden();
	}
	$start = dol_mktime(0, 0, 0, GETPOSTINT('sync_startmonth'), GETPOSTINT('sync_startday'), GETPOSTINT('sync_startyear'), 'gmt');
	$end = dol_mktime(0, 0, 0, GETPOSTINT('sync_endmonth'), GETPOSTINT('sync_endday'), GETPOSTINT('sync_endyear'), 'gmt');
	$resources = GETPOST('resources', 'array:aZ09');
	$resources = is_array($resources) ? array_values(array_map('strval', $resources)) : array();
	$resources = array_values(array_unique(array_intersect($resources, $enabledResources)));
	if ($start <= 0 || $end <= 0 || $start >= $end) {
		setEventMessages($langs->trans('ErrorBadValueForParameter', $langs->trans('LmdbEnedisSyncPeriod')), null, 'errors');
	} elseif ($resources === array()) {
		setEventMessages($langs->trans('LmdbEnedisAtLeastOneResource'), null, 'errors');
	} elseif (!LmdbEnedisConfig::isConnectionConfigured()) {
		setEventMessages($langs->trans('LmdbEnedisConnectionNotConfigured'), null, 'errors');
	} else {
		$synchronizer = new LmdbEnedisSynchronizer($db);
		$result = $synchronizer->syncPrm($id, $resources, $start, $end);
		setEventMessages($langs->trans('LmdbEnedisSyncResult', $result['streams'], $result['points'], $result['errors']), $synchronizer->errors, $result['errors'] ? 'warnings' : 'mesgs');
	}
	header('Location: '.dol_buildpath('/lmdbenedis/prm_measure.php', 1).'?id='.(int) $id);
	exit;
}

$searchResource = GETPOST('search_resource', 'aZ09');
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'm.measure_date';
$sortorder = GETPOST('sortorder', 'aZ09comma') ?: 'DESC';
$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : $conf->liste_limit;
$page = max(0, GETPOSTINT('page'));
$offset = $limit * $page;
$where = ' WHERE m.entity = '.((int) $conf->entity).' AND m.fk_prm = '.((int) $id);
if ($searchResource !== '' && isset(LmdbEnedisClient::getResourcePaths()[$searchResource])) {
	$where .= " AND m.resource_code = '".$db->escape($searchResource)."'";
}
$countResult = $db->query('SELECT COUNT(*) AS nb FROM '.MAIN_DB_PREFIX.'lmdbenedis_measure AS m'.$where);
if (!$countResult) {
	dol_print_error($db);
	exit;
}
$countRow = $db->fetch_object($countResult);
$totalnboflines = is_object($countRow) ? (int) $countRow->nb : 0;
$db->free($countResult);
$allowedSorts = array('m.measure_date', 'm.resource_code', 'm.value', 'm.quality', 'm.interval_length', 'm.measure_type', 'm.flow_direction');
if (!in_array($sortfield, $allowedSorts, true)) {
	$sortfield = 'm.measure_date';
}
$sortorder = strtoupper($sortorder) === 'ASC' ? 'ASC' : 'DESC';
$sql = 'SELECT m.rowid, m.measure_date, m.resource_code, m.value, m.unit, m.quality, m.interval_length, m.measure_type, m.flow_direction, m.measurement_kind, m.calendar_label, m.temporal_class_label, m.quadrant_id';
$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbenedis_measure AS m'.$where;
$sql .= ' ORDER BY '.$sortfield.' '.$sortorder.', m.rowid DESC';
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
	'm.measure_date' => array('label' => 'LmdbEnedisMeasureDate', 'checked' => 1, 'position' => 10),
	'm.resource_code' => array('label' => 'LmdbEnedisResource', 'checked' => 1, 'position' => 20),
	'm.value' => array('label' => 'LmdbEnedisMeasureValue', 'checked' => 1, 'position' => 30),
	'm.quality' => array('label' => 'LmdbEnedisQuality', 'checked' => 1, 'position' => 40),
	'm.interval_length' => array('label' => 'LmdbEnedisInterval', 'checked' => 1, 'position' => 50),
	'm.measure_type' => array('label' => 'LmdbEnedisMeasureType', 'checked' => 1, 'position' => 60),
	'm.flow_direction' => array('label' => 'LmdbEnedisFlowDirection', 'checked' => 1, 'position' => 70),
);
$selectedFields = Form::multiSelectArrayWithCheckbox('selectedfields', $arrayfields, 'lmdbenedismeasurelist');

llxHeader('', $langs->trans('LmdbEnedisMeasurements'));
print dol_get_fiche_head(lmdbenedisPrmPrepareHead($object), 'measurements', $langs->trans('LmdbEnedisUsagePointId'), -1, 'fa-bolt');
print '<table class="border centpercent"><tr><td class="titlefield">'.$langs->trans('LmdbEnedisUsagePointId').'</td><td>'.$object->getNomUrl(1).'</td><td class="right">'.$object->getLibStatut(5).'</td></tr><tr><td>'.$langs->trans('Label').'</td><td colspan="2">'.dol_escape_htmltag($object->label).'</td></tr></table>';
print dol_get_fiche_end();

if (lmdbenedisCanDo($user, 'prm', 'sync', $object)) {
	$defaultEnd = dol_mktime(0, 0, 0, (int) gmdate('n'), (int) gmdate('j'), (int) gmdate('Y'), 'gmt');
	$defaultStart = $defaultEnd - 7 * 86400;
	print '<form method="POST" action="'.dol_buildpath('/lmdbenedis/prm_measure.php', 1).'">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="sync_period"><input type="hidden" name="id" value="'.(int) $id.'">';
	print '<table class="noborder centpercent"><tr class="liste_titre"><td colspan="4">'.$langs->trans('LmdbEnedisSyncPeriod').'</td></tr>';
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('LmdbEnedisSyncStart').'</td><td>'.$form->selectDate($defaultStart, 'sync_start', 0, 0, 0).'</td><td>'.$langs->trans('LmdbEnedisSyncEnd').'</td><td>'.$form->selectDate($defaultEnd, 'sync_end', 0, 0, 0).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('LmdbEnedisResources').'</td><td colspan="3">'.$form->multiselectarray('resources', lmdbenedisResourceOptions(), $enabledResources, 0, 0, 'minwidth500').'</td></tr>';
	print '</table><div class="center"><input class="button" type="submit" value="'.$langs->trans('LmdbEnedisSync').'"></div></form><br>';
}

$resourceOptions = lmdbenedisResourceOptions();
$param = '&id='.(int) $id.'&search_resource='.urlencode($searchResource);
print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="id" value="'.(int) $id.'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print_barre_liste($langs->trans('LmdbEnedisMeasurements'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', count($rows), $totalnboflines, 'chart', 0, $selectedFields, '', $limit);
print '<div class="div-table-responsive"><table class="tagtable liste centpercent">';
$visibleColumns = 0;
print '<tr class="liste_titre_filter">';
foreach ($arrayfields as $field => $definition) {
	if (empty($definition['checked'])) {
		continue;
	}
	$visibleColumns++;
	print '<td>';
	if ($field === 'm.resource_code') {
		print $form->selectarray('search_resource', $resourceOptions, $searchResource, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth250');
	}
	print '</td>';
}
print '<td class="right"><button class="liste_titre button_search" type="submit">'.img_picto('', 'search').'</button> <a class="liste_titre" href="'.dol_buildpath('/lmdbenedis/prm_measure.php', 1).'?id='.(int) $id.'" title="'.$langs->trans('RemoveFilter').'">'.img_picto('', 'remove-filter').'</a></td></tr>';
print '<tr class="liste_titre">';
foreach ($arrayfields as $field => $definition) {
	if (!empty($definition['checked'])) {
		print_liste_field_titre($langs->trans($definition['label']), $_SERVER['PHP_SELF'], $field, '', $param, $field === 'm.value' ? 'right' : '', $sortfield, $sortorder);
	}
}
print '<th></th></tr>';
if ($rows === array()) {
	print '<tr class="oddeven"><td colspan="'.((int) $visibleColumns + 1).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
} else {
	foreach ($rows as $row) {
		$dimensions = array_filter(array((string) $row->calendar_label, (string) $row->temporal_class_label, (string) $row->quadrant_id));
		print '<tr class="oddeven">';
		foreach ($arrayfields as $field => $definition) {
			if (empty($definition['checked'])) {
				continue;
			}
			if ($field === 'm.measure_date') {
				print '<td>'.dol_print_date($db->jdate((string) $row->measure_date), 'dayhour').'</td>';
			} elseif ($field === 'm.resource_code') {
				print '<td>'.dol_escape_htmltag(isset($resourceOptions[(string) $row->resource_code]) ? $resourceOptions[(string) $row->resource_code] : (string) $row->resource_code).($dimensions !== array() ? '<br><span class="opacitymedium">'.dol_escape_htmltag(implode(' / ', $dimensions)).'</span>' : '').'</td>';
			} elseif ($field === 'm.value') {
				print '<td class="right">'.dol_escape_htmltag((string) $row->value).' '.dol_escape_htmltag((string) $row->unit).'</td>';
			} elseif ($field === 'm.quality') {
				print '<td>'.dol_escape_htmltag((string) $row->quality).'</td>';
			} elseif ($field === 'm.interval_length') {
				print '<td>'.dol_escape_htmltag((string) $row->interval_length).'</td>';
			} elseif ($field === 'm.measure_type') {
				print '<td>'.dol_escape_htmltag((string) $row->measure_type).'</td>';
			} else {
				print '<td>'.dol_escape_htmltag((string) $row->flow_direction).'</td>';
			}
		}
		print '<td></td>';
		print '</tr>';
	}
}
print '</table></div></form>';
llxFooter();
