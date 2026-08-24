<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Return whether a user has administrator-level functional elevation.
 *
 * @param User|null $user User
 * @return bool
 */
function lmdbenedisUserIsFullAdmin($user)
{
	if (!is_object($user)) {
		return false;
	}
	if (!empty($user->admin)) {
		return true;
	}
	if (isModEnabled('multicompany')) {
		return $user->hasRight('multicompany', 'entities', 'write')
			|| $user->hasRight('multicompany', 'setup', 'write')
			|| $user->hasRight('multicompany', 'admin', 'write');
	}

	return false;
}

/**
 * Central permission and entity check.
 *
 * Enedis authorizations and OAuth2 credentials belong to the current entity,
 * so PRMs are intentionally not shared across entities.
 *
 * @param User|null         $user   User
 * @param string            $object Permission object
 * @param string            $action Permission action
 * @param CommonObject|null $record Optional record
 * @return bool
 */
function lmdbenedisCanDo($user, $object, $action, $record = null)
{
	global $conf;

	if (!is_object($user)) {
		return false;
	}
	if (is_object($record) && !empty($record->entity) && (int) $record->entity !== (int) $conf->entity) {
		return false;
	}
	if (lmdbenedisUserIsFullAdmin($user)) {
		return true;
	}

	return $user->hasRight('lmdbenedis', $object, $action);
}

/**
 * Check the native PowerPlantPV read permission before exposing plant data.
 *
 * @param User|null $user User
 * @return bool
 */
function lmdbenedisCanReadPowerPlant($user)
{
	if (!isModEnabled('powerplantpv') || !is_object($user)) {
		return false;
	}

	return !empty($user->admin) || $user->hasRight('powerplantpv', 'powerplant', 'read');
}
