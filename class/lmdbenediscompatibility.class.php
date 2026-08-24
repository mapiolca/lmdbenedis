<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/lmdbenedisconfig.class.php';
require_once __DIR__.'/lmdbenedisclient.class.php';
require_once __DIR__.'/lmdbenedisauthorization.class.php';

/**
 * Central compatibility registry.
 */
class LmdbEnedisCompatibility
{
	/**
	 * @return array<string,array{label:string,description:string,min_dolibarr:string,core_available_from:string,module_available_from:string,min_php:string,compatibility_check:string,available:bool,reason:string}>
	 */
	public static function getFeatureStatuses()
	{
		$dolibarrOk = defined('DOL_VERSION') && version_compare(DOL_VERSION, '20.0.0', '>=');
		$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
		$curlOk = function_exists('curl_init');
		$configured = LmdbEnedisConfig::isConnectionConfigured();
		$cronEnabled = isModEnabled('cron');
		$productionEnvironment = LmdbEnedisAuthorization::isProductionEnvironment();
		$authorizationConfigured = LmdbEnedisAuthorization::isConfigured();
		$endpointsOk = false;
		if ($configured) {
			try {
				new LmdbEnedisClient();
				$endpointsOk = true;
			} catch (Throwable $e) {
				$endpointsOk = false;
			}
		}

		return array(
			'authorization_2026' => array(
				'label' => 'LmdbEnedisFeatureAuthorization2026',
				'description' => 'LmdbEnedisFeatureAuthorization2026Description',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '20.0.0',
				'min_php' => '8.0.0',
				'compatibility_check' => "version_compare(DOL_VERSION, '20.0.0', '>=') && PHP_VERSION >= 8.0.0 && extension_loaded('curl') && environment == production && authorize_v2_url_valid && subscribed_services_v1_configured && callback_url_https",
				'available' => $dolibarrOk && $phpOk && $authorizationConfigured,
				'reason' => !$dolibarrOk ? 'LmdbEnedisRequiresDolibarr20' : (!$phpOk ? 'LmdbEnedisRequiresPhp80' : (!$curlOk ? 'LmdbEnedisRequiresCurl' : (!$productionEnvironment ? 'LmdbEnedisRequiresProductionEnvironment' : ($authorizationConfigured ? '' : 'LmdbEnedisRequiresAuthorizationConfiguration')))),
			),
			'measure_v1' => array(
				'label' => 'LmdbEnedisFeatureMeasureV1',
				'description' => 'LmdbEnedisFeatureMeasureV1Description',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '20.0.0',
				'min_php' => '8.0.0',
				'compatibility_check' => "version_compare(DOL_VERSION, '20.0.0', '>=') && PHP_VERSION >= 8.0.0 && extension_loaded('curl') && connection_configured",
				'available' => $dolibarrOk && $phpOk && $curlOk && $configured && $endpointsOk,
				'reason' => !$dolibarrOk ? 'LmdbEnedisRequiresDolibarr20' : (!$phpOk ? 'LmdbEnedisRequiresPhp80' : (!$curlOk ? 'LmdbEnedisRequiresCurl' : (!$configured ? 'LmdbEnedisRequiresConfiguration' : (!$endpointsOk ? 'LmdbEnedisRequiresValidEndpoints' : '')))),
			),
			'cron_sync' => array(
				'label' => 'LmdbEnedisFeatureCron',
				'description' => 'LmdbEnedisFeatureCronDescription',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '20.0.0',
				'min_php' => '8.0.0',
				'compatibility_check' => "version_compare(DOL_VERSION, '20.0.0', '>=') && PHP_VERSION >= 8.0.0 && isModEnabled('cron') && extension_loaded('curl') && connection_configured",
				'available' => $dolibarrOk && $phpOk && $cronEnabled && $curlOk && $configured && $endpointsOk,
				'reason' => !$dolibarrOk ? 'LmdbEnedisRequiresDolibarr20' : (!$phpOk ? 'LmdbEnedisRequiresPhp80' : (!$cronEnabled ? 'LmdbEnedisRequiresCron' : (!$curlOk ? 'LmdbEnedisRequiresCurl' : (!$configured ? 'LmdbEnedisRequiresConfiguration' : (!$endpointsOk ? 'LmdbEnedisRequiresValidEndpoints' : ''))))),
			),
			'powerplantpv' => array(
				'label' => 'LmdbEnedisFeaturePowerPlantPV',
				'description' => 'LmdbEnedisFeaturePowerPlantPVDescription',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '20.0.0',
				'min_php' => '8.0.0',
				'compatibility_check' => "version_compare(DOL_VERSION, '20.0.0', '>=') && PHP_VERSION >= 8.0.0 && isModEnabled('powerplantpv')",
				'available' => $dolibarrOk && $phpOk && isModEnabled('powerplantpv'),
				'reason' => !$dolibarrOk ? 'LmdbEnedisRequiresDolibarr20' : (!$phpOk ? 'LmdbEnedisRequiresPhp80' : (isModEnabled('powerplantpv') ? '' : 'LmdbEnedisPowerPlantPVOptional')),
			),
		);
	}
}
