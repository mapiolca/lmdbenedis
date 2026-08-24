<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

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

require_once __DIR__.'/../lib/lmdbenedis.lib.php';
require_once __DIR__.'/../lib/lmdbenedis_access.lib.php';
require_once __DIR__.'/../core/modules/modLmdbEnedis.class.php';

$langs->loadLangs(array('admin', 'lmdbenedis@lmdbenedis'));
if (!isModEnabled('lmdbenedis') || !lmdbenedisUserIsFullAdmin($user)) {
	accessforbidden();
}

$module = new modLmdbEnedis($db);
$dolibarrMin = implode('.', $module->need_dolibarr_version);
$phpMin = implode('.', $module->phpmin);
$dependencyLabels = $module->depends === array() ? $langs->trans('LmdbEnedisNoRequiredDependency') : implode(', ', $module->depends);
$powerPlantPvStatus = isModEnabled('powerplantpv') ? $langs->trans('LmdbEnedisPowerPlantPVEnabled') : $langs->trans('LmdbEnedisPowerPlantPVOptional');

llxHeader('', $langs->trans('About'));
lmdbenedisPrintAdminHeader('about');
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('About').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Module').'</td><td>'.$langs->trans('LmdbEnedis').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Version').'</td><td>'.dol_escape_htmltag($module->version).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Publisher').'</td><td>'.dol_escape_htmltag($module->editor_name).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Author').'</td><td>'.dol_escape_htmltag($module->author).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Description').'</td><td>'.$langs->trans($module->descriptionlong).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Compatibility').'</td><td>Dolibarr '.dol_escape_htmltag($dolibarrMin).'+, PHP '.dol_escape_htmltag($phpMin).'+</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Dependencies').'</td><td>'.dol_escape_htmltag($dependencyLabels).' — '.$powerPlantPvStatus.'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MainFeatures').'</td><td>'.$langs->trans('LmdbEnedisMainFeatures').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('UsefulLinks').'</td><td><a href="https://datahub-enedis.fr/services-api/data-connect/documentation/mesures-v1/" target="_blank" rel="noopener">Enedis Data Connect — Mesures V1</a></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('License').'</td><td>'.dol_escape_htmltag($module->license).'</td></tr>';
print '</table>';

print dol_get_fiche_end();
llxFooter();
