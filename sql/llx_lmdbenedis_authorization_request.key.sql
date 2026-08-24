-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

ALTER TABLE llx_lmdbenedis_authorization_request ADD UNIQUE INDEX uk_lmdbenedis_authreq_state (state_hash);
ALTER TABLE llx_lmdbenedis_authorization_request ADD INDEX idx_lmdbenedis_authreq_entity (entity);
ALTER TABLE llx_lmdbenedis_authorization_request ADD INDEX idx_lmdbenedis_authreq_prm (fk_prm);
ALTER TABLE llx_lmdbenedis_authorization_request ADD INDEX idx_lmdbenedis_authreq_status (entity, status, expires_at);
