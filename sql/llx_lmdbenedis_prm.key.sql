-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

ALTER TABLE llx_lmdbenedis_prm ADD UNIQUE INDEX uk_lmdbenedis_prm_entity_usagepoint (entity, usage_point_id);
ALTER TABLE llx_lmdbenedis_prm ADD INDEX idx_lmdbenedis_prm_entity (entity);
ALTER TABLE llx_lmdbenedis_prm ADD INDEX idx_lmdbenedis_prm_soc (fk_soc);
ALTER TABLE llx_lmdbenedis_prm ADD INDEX idx_lmdbenedis_prm_powerplant (fk_powerplant);
ALTER TABLE llx_lmdbenedis_prm ADD INDEX idx_lmdbenedis_prm_status (status);
