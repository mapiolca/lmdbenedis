# LMDB Enedis

Module externe Dolibarr pour collecter et exploiter les mesures de consommation et de production des PRM autorisés dans Enedis Data Connect « Mesures V1 ».

## Fonctionnalités V1

- configuration OAuth2 Client Credentials par entité, avec secret chiffré par Dolibarr ;
- registre autonome des PRM autorisés ;
- activation indépendante des sept flux Mesures V1 ;
- synchronisation manuelle et planifiée, avec découpage automatique des périodes ;
- stockage normalisé et idempotent des courbes, agrégats journaliers, puissances maximales et index ;
- liaison facultative d’un PRM à une centrale PowerPlantPV ;
- onglet de consultation dans PowerPlantPV lorsque ce module est actif.

## Autorisations et sécurité

Le module n’obtient pas le consentement du titulaire : seuls les PRM déjà autorisés pour l’application Data Connect doivent être enregistrés. Enedis reste la source d’autorité et refuse un PRM ou un flux non couvert par l’autorisation. La date de fin facultative enregistrée dans Dolibarr ajoute un garde-fou local : une autorisation expirée bloque la synchronisation.

Les identifiants, autorisations, curseurs et mesures sont strictement séparés par entité Dolibarr. Le secret client est chiffré avec `dolEncrypt()` et les appels HTTP n’utilisent pas le helper journalisant les en-têtes d’authentification. Les URLs configurables restent limitées aux hôtes HTTPS du domaine `api.enedis.fr`.

La synchronisation automatique respecte une période de sécurité configurable et découpe les courbes de charge par tranches de sept jours. Le client applique les profondeurs historiques officielles : 24 mois et 15 jours pour les courbes de charge, 36 mois et 15 jours pour les mesures quotidiennes et la puissance maximale. Les index restent soumis à la profondeur effectivement accordée par Enedis, aucune borne n’étant publiée pour ces deux ressources dans le contrat Mesures V1.

Lorsqu’un PRM est lié à PowerPlantPV, le registre LMDB Enedis reste la source utilisée pour interroger Data Connect. Le champ PRM/PDL de la centrale n’est ni recopié ni modifié ; si celui-ci est déjà renseigné avec une autre valeur, la liaison est refusée afin d’éviter deux sources contradictoires.

## Compatibilité

- Dolibarr 20 ou supérieur ;
- PHP 8.0 ou supérieur ;
- MySQL/MariaDB ;
- extension PHP cURL ;
- PowerPlantPV facultatif.

## Installation

Placer le répertoire `lmdbenedis` dans le répertoire des modules externes de Dolibarr, puis activer **LMDB Enedis** depuis la liste des modules. La configuration est accessible depuis l’unique roue dentée du module.

La tâche **Synchronisation Enedis Mesures V1** est déclarée dans les Travaux planifiés de Dolibarr. Elle est désactivée par défaut.

## Sources officielles

- [Documentation Mesures V1](https://datahub-enedis.fr/services-api/data-connect/documentation/mesures-v1/)
- [Enedis DataHub](https://datahub-enedis.fr/)

## Licence

GNU General Public License v3.0 ou ultérieure.
