<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Secret-aware configuration services for Enedis Data Connect.
 */
class LmdbEnedisConfig
{
	/**
	 * Return the decrypted OAuth2 client secret.
	 *
	 * @return string
	 */
	public static function getClientSecret()
	{
		$value = getDolGlobalString('LMDBENEDIS_CLIENT_SECRET');
		if ($value === '') {
			return '';
		}

		self::loadNativeEncryptionLibrary();

		$decrypted = (string) dolDecrypt($value);
		if (preg_match('/^(dolobfuscation|dolcrypt|crypted):/', $value) && hash_equals($value, $decrypted)) {
			return '';
		}

		return $decrypted;
	}

	/**
	 * Save the OAuth2 client secret for the current entity.
	 *
	 * @param DoliDB $db          Database handler
	 * @param string $plainSecret Plain secret
	 * @return int
	 */
	public static function setClientSecret($db, $plainSecret)
	{
		global $conf;

		self::loadNativeEncryptionLibrary();

		$encrypted = $plainSecret === '' ? '' : dolEncrypt($plainSecret);

		return dolibarr_set_const($db, 'LMDBENEDIS_CLIENT_SECRET', $encrypted, 'chaine', 0, '', (int) $conf->entity);
	}

	/**
	 * Check that the required connection settings exist.
	 *
	 * @return bool
	 */
	public static function isConnectionConfigured()
	{
		return getDolGlobalString('LMDBENEDIS_CLIENT_ID') !== ''
			&& self::getClientSecret() !== ''
			&& getDolGlobalString('LMDBENEDIS_API_BASE_URL') !== ''
			&& getDolGlobalString('LMDBENEDIS_TOKEN_URL') !== '';
	}

	/**
	 * Load the native encryption functions on every supported Dolibarr version.
	 *
	 * Dolibarr v20 exposes them from core/lib/security.lib.php, while recent
	 * versions moved the implementation to blockedlog/lib/securitycore.lib.php.
	 *
	 * @return void
	 */
	private static function loadNativeEncryptionLibrary()
	{
		if (function_exists('dolEncrypt') && function_exists('dolDecrypt')) {
			return;
		}

		$currentLibrary = DOL_DOCUMENT_ROOT.'/blockedlog/lib/securitycore.lib.php';
		$legacyLibrary = DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
		if (is_readable($currentLibrary)) {
			require_once $currentLibrary;
		} elseif (is_readable($legacyLibrary)) {
			require_once $legacyLibrary;
		}
	}
}
