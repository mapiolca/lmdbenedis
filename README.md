# LMDB Enedis

Module externe Dolibarr pour collecter et exploiter les mesures de consommation et de production des PRM autorisés dans Enedis Data Connect « Mesures V1 ».

## Fonctionnalités V1

- configuration OAuth2 Client Credentials par entité, avec secret chiffré par Dolibarr ;
- parcours **Autorisation Data Connect 2026** depuis la fiche PRM, avec consentement du titulaire, callback sécurisé et résolution du PRM par Services souscrits V1 ;
- registre autonome des PRM autorisés ;
- activation indépendante des sept flux Mesures V1 ;
- synchronisation manuelle et planifiée, avec découpage automatique des périodes ;
- stockage normalisé et idempotent des courbes, agrégats journaliers, puissances maximales et index ;
- liaison facultative d’un PRM à une centrale PowerPlantPV ;
- onglet de consultation dans PowerPlantPV lorsque ce module est actif.

## Autorisations et sécurité

Après création d’une fiche PRM, l’action **Obtenir l’autorisation Enedis** redirige le titulaire vers son compte Enedis avec l’endpoint Data Connect 2026 `/dataconnect/v2/oauth2/authorize`. Le retour officiel contient `autorisation_id` et l’état de sécurité. Le module échange immédiatement cet identifiant auprès de **Services souscrits V1**, exige un PRM unique et vérifie qu’il correspond à la fiche ayant initié la demande. Enedis reste la source d’autorité et peut refuser un PRM ou un flux non couvert par le consentement.

Le réglage **Mode Data Connect** distingue le bac à sable de la production. Le consentement réel du titulaire est désactivé en bac à sable : Enedis demande l’URL de retour lors du passage de l’application en production. En production, l’URL HTTPS affichée dans la configuration du module doit être transmise exactement à Enedis lors de la souscription. Elle n’est pas ajoutée comme paramètre `redirect_uri`. La durée demandée est configurable entre trois mois et trois ans. Une autorisation absente ou expirée bloque les synchronisations manuelles et planifiées.

Les identifiants, demandes d’autorisation, curseurs et mesures sont strictement séparés par entité Dolibarr. Le secret client est chiffré avec `dolEncrypt()`. Les états OAuth2 et les `autorisation_id` ne sont jamais stockés en clair : seules leurs empreintes SHA-256 sont conservées. La résolution du PRM est effectuée hors transaction longue, après revendication atomique de la demande, afin d’empêcher les rejeux concurrents. Les appels HTTP n’utilisent pas le helper journalisant les en-têtes d’authentification. Les URLs Mesures V1 et Services souscrits V1 restent limitées aux hôtes HTTPS du domaine `api.enedis.fr` et l’URL d’autorisation au portail officiel `mon-compte-particulier.enedis.fr`.

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

En bac à sable, conserver le mode correspondant et tester la connexion OAuth2 sans lancer de consentement réel. Lors du passage en production, transmettre à Enedis l’URL **URL de retour à déclarer auprès d’Enedis**, attendre son enregistrement, puis sélectionner le mode **Production**. L’instance Dolibarr doit être accessible publiquement en HTTPS sur cette URL.

## Sources officielles

- [Documentation Mesures V1](https://datahub-enedis.fr/services-api/data-connect/documentation/mesures-v1/)
- [Documentation Services souscrits V1](https://datahub-enedis.fr/services-api/data-connect/documentation/services-souscrits/)
- [Guide des évolutions Data Connect 2026](https://datahub-enedis.fr/wp-content/uploads/2026_Guide-des-evolutions-DataConnect.pdf)
- [Enedis DataHub](https://datahub-enedis.fr/)

## Licence

GNU General Public License v3.0 ou ultérieure.
