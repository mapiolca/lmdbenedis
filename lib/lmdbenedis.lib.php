<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/../class/lmdbenedisclient.class.php';

/**
 * Prepare administration tabs.
 *
 * @return array<int,array<int,string>>
 */
function lmdbenedisAdminPrepareHead()
{
	global $langs;

	$langs->loadLangs(array('admin', 'lmdbenedis@lmdbenedis'));
	$head = array();
	$h = 0;
	$head[$h] = array(dol_buildpath('/lmdbenedis/admin/setup.php', 1), $langs->trans('Settings'), 'settings');
	$h++;
	$head[$h] = array(dol_buildpath('/lmdbenedis/admin/compatibility.php', 1), $langs->trans('Compatibility'), 'compatibility');
	$h++;
	$head[$h] = array(dol_buildpath('/lmdbenedis/admin/about.php', 1), $langs->trans('About'), 'about');

	return $head;
}

/**
 * Render the shared administration header.
 *
 * @param string $activeTab Active tab
 * @return void
 */
function lmdbenedisPrintAdminHeader($activeTab)
{
	global $langs;

	$linkBack = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('lmdbenedis').'">'.$langs->trans('BackToModuleList').'</a>';
	print load_fiche_titre($langs->trans('LmdbEnedisSetup'), $linkBack, 'title_setup');
	print dol_get_fiche_head(lmdbenedisAdminPrepareHead(), $activeTab, '', -1);
}

/**
 * Prepare PRM card tabs.
 *
 * @param LmdbEnedisPrm $object PRM
 * @return array<int,array<int,string>>
 */
function lmdbenedisPrmPrepareHead($object)
{
	global $langs;

	$head = array();
	$head[] = array(dol_buildpath('/lmdbenedis/prm_card.php', 1).'?id='.(int) $object->id, $langs->trans('Card'), 'card');
	$head[] = array(dol_buildpath('/lmdbenedis/prm_measure.php', 1).'?id='.(int) $object->id, $langs->trans('LmdbEnedisMeasurements'), 'measurements');

	return $head;
}

/**
 * Return translated resource labels.
 *
 * @return array<string,string>
 */
function lmdbenedisResourceOptions()
{
	global $langs;

	return array(
		LmdbEnedisClient::RESOURCE_CONSUMPTION_LOAD_CURVE => $langs->trans('LmdbEnedisResourceConsumptionLoadCurve'),
		LmdbEnedisClient::RESOURCE_PRODUCTION_LOAD_CURVE => $langs->trans('LmdbEnedisResourceProductionLoadCurve'),
		LmdbEnedisClient::RESOURCE_DAILY_CONSUMPTION => $langs->trans('LmdbEnedisResourceDailyConsumption'),
		LmdbEnedisClient::RESOURCE_DAILY_PRODUCTION => $langs->trans('LmdbEnedisResourceDailyProduction'),
		LmdbEnedisClient::RESOURCE_DAILY_CONSUMPTION_MAX_POWER => $langs->trans('LmdbEnedisResourceDailyConsumptionMaxPower'),
		LmdbEnedisClient::RESOURCE_INDEX_CONSUMPTION => $langs->trans('LmdbEnedisResourceIndexConsumption'),
		LmdbEnedisClient::RESOURCE_INDEX_PRODUCTION => $langs->trans('LmdbEnedisResourceIndexProduction'),
	);
}

/**
 * Return selectable power plants for the current entity.
 *
 * @return array<int,string>
 */
function lmdbenedisPowerPlantOptions()
{
	global $db, $conf, $user;

	$options = array();
	if (!function_exists('lmdbenedisCanReadPowerPlant')) {
		require_once __DIR__.'/lmdbenedis_access.lib.php';
	}
	if (!lmdbenedisCanReadPowerPlant($user)) {
		return $options;
	}
	$sql = 'SELECT rowid, ref, label, prm_pdl_number FROM '.MAIN_DB_PREFIX.'powerplantpv_powerplant';
	$sql .= ' WHERE entity = '.((int) $conf->entity);
	$sql .= ' ORDER BY ref ASC, label ASC';
	$resql = $db->query($sql);
	if (!$resql) {
		return $options;
	}
	while (is_object($row = $db->fetch_object($resql))) {
		$label = trim((string) $row->ref.' - '.(string) $row->label, ' -');
		if (!empty($row->prm_pdl_number)) {
			$label .= ' ['.(string) $row->prm_pdl_number.']';
		}
		$options[(int) $row->rowid] = $label;
	}
	$db->free($resql);

	return $options;
}
