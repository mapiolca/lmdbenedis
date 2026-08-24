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

if (!isModEnabled('powerplantpv')) {
	accessforbidden();
}

dol_include_once('/powerplantpv/class/powerplant.class.php');
dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');
require_once __DIR__.'/class/lmdbenedisprm.class.php';
require_once __DIR__.'/lib/lmdbenedis.lib.php';
require_once __DIR__.'/lib/lmdbenedis_access.lib.php';

$langs->loadLangs(array('powerplantpv@powerplantpv', 'lmdbenedis@lmdbenedis'));
if (!isModEnabled('lmdbenedis') || !lmdbenedisCanDo($user, 'prm', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$object = new PowerPlant($db);
if ($id <= 0 || $object->fetch($id) <= 0 || (int) $object->entity !== (int) $conf->entity) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
if (!lmdbenedisCanReadPowerPlant($user)) {
	accessforbidden();
}
restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', 0);

$sql = 'SELECT p.rowid, p.usage_point_id, p.label, p.status, p.last_sync_at FROM '.MAIN_DB_PREFIX.'lmdbenedis_prm AS p';
$sql .= ' WHERE p.entity = '.((int) $conf->entity).' AND p.fk_powerplant = '.((int) $id);
$sql .= ' ORDER BY p.status DESC, p.usage_point_id ASC';
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$prms = array();
while (is_object($row = $db->fetch_object($resql))) {
	$prms[] = $row;
}
$db->free($resql);

llxHeader('', $langs->trans('LmdbEnedisMeasurements'));
$head = powerplantPrepareHead($object);
print dol_get_fiche_head($head, 'lmdbenedis_measurements', $langs->trans('PowerPlant'), -1, $object->picto);
print '<table class="border centpercent"><tr><td class="titlefield">'.$langs->trans('PowerPlant').'</td><td>'.$object->getNomUrl(1).'</td></tr><tr><td>'.$langs->trans('Label').'</td><td>'.dol_escape_htmltag((string) $object->label).'</td></tr></table>';
print dol_get_fiche_end();

if ($prms === array()) {
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('LmdbEnedisUsagePointId').'</th></tr><tr class="oddeven"><td><span class="opacitymedium">'.$langs->trans('LmdbEnedisNoPrmForPowerPlant').'</span></td></tr></table></div>';
} else {
	$resourceOptions = lmdbenedisResourceOptions();
	foreach ($prms as $prmRow) {
		$prm = new LmdbEnedisPrm($db);
		$prm->id = (int) $prmRow->rowid;
		$prm->usage_point_id = (string) $prmRow->usage_point_id;
		$prm->status = (int) $prmRow->status;
		print load_fiche_titre($prm->getNomUrl(1).' — '.dol_escape_htmltag((string) $prmRow->label), '', 'fa-bolt');
		$measureSql = 'SELECT resource_code, measure_date, value, unit, quality FROM '.MAIN_DB_PREFIX.'lmdbenedis_measure';
		$measureSql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_prm = '.((int) $prmRow->rowid);
		$measureSql .= ' ORDER BY measure_date DESC, rowid DESC LIMIT 20';
		$measureResult = $db->query($measureSql);
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('LmdbEnedisMeasureDate').'</th><th>'.$langs->trans('LmdbEnedisResource').'</th><th class="right">'.$langs->trans('LmdbEnedisMeasureValue').'</th><th>'.$langs->trans('LmdbEnedisQuality').'</th></tr>';
		$count = 0;
		if ($measureResult) {
			while (is_object($measure = $db->fetch_object($measureResult))) {
				$count++;
				print '<tr class="oddeven"><td>'.dol_print_date($measure->measure_date, 'dayhour').'</td><td>'.dol_escape_htmltag(isset($resourceOptions[(string) $measure->resource_code]) ? $resourceOptions[(string) $measure->resource_code] : (string) $measure->resource_code).'</td><td class="right">'.dol_escape_htmltag((string) $measure->value).' '.dol_escape_htmltag((string) $measure->unit).'</td><td>'.dol_escape_htmltag((string) $measure->quality).'</td></tr>';
			}
			$db->free($measureResult);
		}
		if ($count === 0) {
			print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
		}
		print '</table></div><div class="right"><a class="button" href="'.dol_buildpath('/lmdbenedis/prm_measure.php', 1).'?id='.(int) $prmRow->rowid.'">'.$langs->trans('LmdbEnedisMeasurements').'</a></div><br>';
	}
}

llxFooter();
