-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

ALTER TABLE llx_lmdbenedis_prm_stream ADD UNIQUE INDEX uk_lmdbenedis_prm_stream (entity, fk_prm, resource_code);
ALTER TABLE llx_lmdbenedis_prm_stream ADD INDEX idx_lmdbenedis_prm_stream_entity (entity);
ALTER TABLE llx_lmdbenedis_prm_stream ADD INDEX idx_lmdbenedis_prm_stream_prm (fk_prm);
ALTER TABLE llx_lmdbenedis_prm_stream ADD INDEX idx_lmdbenedis_prm_stream_active (active);
ALTER TABLE llx_lmdbenedis_prm_stream ADD INDEX idx_lmdbenedis_prm_stream_cursor (cursor_date);
