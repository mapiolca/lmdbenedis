# Consignes du module LMDB Enedis

| Information | Valeur |
|---|---|
| Version du référentiel local | `2026.08.24.1` |
| Date de mise à jour | `2026-08-24` |

## Historique

### `2026.08.24.1` — 2026-08-24

- Première version des règles propres au module LMDB Enedis.

## Règles

- Le module cible Dolibarr v20+ et PHP 8.0+.
- Toute modification doit rester dans la racine `lmdbenedis/`.
- Les données sont strictement séparées par `entity` ; les identifiants et secrets Data Connect sont configurés par entité.
- Les secrets OAuth2 sont chiffrés avec `dolEncrypt()` et ne doivent jamais être journalisés.
- Les appels Mesures V1 doivent respecter les limites de période, le caractère exclusif de la date de fin et les réponses 429.
- La liaison à PowerPlantPV doit rester facultative et ne doit jamais devenir une dépendance dure.
- Les écritures de PRM appellent uniquement les triggers CRUD `LMDBENEDIS_PRM_CREATE`, `LMDBENEDIS_PRM_UPDATE` et `LMDBENEDIS_PRM_DELETE`.
