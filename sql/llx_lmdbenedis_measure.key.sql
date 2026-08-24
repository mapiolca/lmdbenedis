-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

ALTER TABLE llx_lmdbenedis_measure ADD UNIQUE INDEX uk_lmdbenedis_measure_stable (entity, fk_prm, resource_code, data_key);
ALTER TABLE llx_lmdbenedis_measure ADD INDEX idx_lmdbenedis_measure_entity (entity);
ALTER TABLE llx_lmdbenedis_measure ADD INDEX idx_lmdbenedis_measure_prm (fk_prm);
ALTER TABLE llx_lmdbenedis_measure ADD INDEX idx_lmdbenedis_measure_resource_date (resource_code, measure_date);
ALTER TABLE llx_lmdbenedis_measure ADD INDEX idx_lmdbenedis_measure_prm_date (fk_prm, measure_date);
