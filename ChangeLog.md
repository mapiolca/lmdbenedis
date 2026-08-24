# Historique des versions

## 1.2.0 — 2026-08-25

- Migration de l’autorisation vers le parcours Data Connect 2026 `/dataconnect/v2/oauth2/authorize` et son callback `autorisation_id`.
- Ajout de la résolution obligatoire du PRM par Services souscrits V1, avec refus des réponses absentes, incohérentes ou portant plusieurs PRM.
- Séparation explicite des modes bac à sable et production ; le consentement réel reste indisponible tant que l’URL de retour n’a pas été enregistrée par Enedis.
- Migration idempotente de l’ancienne URL d’autorisation par défaut et ajout conservateur de l’empreinte d’identifiant d’autorisation en base.
- Renforcement de l’anti-rejeu avec un état de traitement atomique et aucun appel réseau conservé dans une transaction SQL longue.

## 1.1.0 — 2026-08-25

- Ajout du parcours Autorisation V1 depuis une fiche PRM, avec redirection vers le compte du titulaire et callback public HTTPS.
- Ajout des demandes à état unique et durée limitée, stockage exclusif des empreintes SHA-256, contrôle du PRM retourné et suivi des échecs.
- Blocage des synchronisations sans autorisation active et exclusion des PRM non autorisés des travaux planifiés.
- Ajout des réglages d’URL, de durée et de l’URL de retour à déclarer dans DataHub Enedis.
- Installation et réactivation rendues silencieusement idempotentes pour les index ; correction du rendu des dates SQL et de la validation des sélections multiples.

## 1.0.0 — 2026-08-24

- Ajout de la configuration OAuth2 Enedis Data Connect par entité.
- Ajout du registre des PRM, de leur lecture stricte sur 14 chiffres, du contrôle local d’expiration des autorisations et de la sélection des flux Mesures V1 autorisés.
- Ajout de la synchronisation manuelle et planifiée avec reprise sur curseur, limites de période officielles et gestion des quotas HTTP.
- Ajout du stockage normalisé des courbes, mesures journalières, puissances maximales et index.
- Ajout de l’intégration facultative aux fiches centrales PowerPlantPV.
