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
require_once __DIR__.'/../class/lmdbenediscompatibility.class.php';

$langs->loadLangs(array('admin', 'lmdbenedis@lmdbenedis'));
if (!isModEnabled('lmdbenedis') || !lmdbenedisUserIsFullAdmin($user)) {
	accessforbidden();
}

llxHeader('', $langs->trans('Compatibility'));
lmdbenedisPrintAdminHeader('compatibility');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('Environment').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('DetectedPHPVersion').'</td><td>'.dol_escape_htmltag(PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DetectedDolibarrVersion').'</td><td>'.(defined('DOL_VERSION') ? dol_escape_htmltag(DOL_VERSION) : '').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MinimumPHPVersion').'</td><td>8.0</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MinimumDolibarrVersion').'</td><td>20.0</td></tr>';
print '</table><br>';

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Code').'</th><th>'.$langs->trans('Label').'</th><th>'.$langs->trans('Description').'</th><th>'.$langs->trans('MinimumVersion').'</th><th>'.$langs->trans('LmdbEnedisCoreAvailability').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('Reason').'</th></tr>';
foreach (LmdbEnedisCompatibility::getFeatureStatuses() as $code => $feature) {
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($code).'</td>';
	print '<td>'.$langs->trans($feature['label']).'</td>';
	print '<td>'.$langs->trans($feature['description']).'</td>';
	print '<td>Dolibarr '.dol_escape_htmltag($feature['min_dolibarr']).' / PHP '.dol_escape_htmltag($feature['min_php']).'</td>';
	print '<td>'.$langs->trans('LmdbEnedisAvailableFromDolibarr', dol_escape_htmltag($feature['core_available_from'])).'<br><span class="opacitymedium">'.dol_escape_htmltag($feature['compatibility_check']).'</span></td>';
	print '<td>'.($feature['available'] ? '<span class="badge badge-status4">'.$langs->trans('Available').'</span>' : '<span class="badge badge-status8">'.$langs->trans('Unavailable').'</span>').'</td>';
	print '<td>'.($feature['reason'] !== '' ? $langs->trans($feature['reason']) : '').'</td>';
	print '</tr>';
}
print '</table></div>';

print dol_get_fiche_end();
llxFooter();
