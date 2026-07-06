-- =========================================================
-- Migration: Novo fluxo de pareamento
-- Rode UMA VEZ no banco existente
-- =========================================================
USE db_gvc_display;

-- Permite devices sem slug/token (TVs criadas no admin, ainda não pareadas)
ALTER TABLE devices MODIFY slug VARCHAR(100) NULL;
ALTER TABLE devices MODIFY token VARCHAR(64) NULL;

-- Adiciona client_id em devices (fingerprint da TV vinculada)
ALTER TABLE devices ADD COLUMN IF NOT EXISTS client_id VARCHAR(64) NULL AFTER token;
ALTER TABLE devices ADD KEY IF NOT EXISTS idx_devices_client_id (client_id);

-- Permite códigos de pareamento sem device_id (gerados pela TV antes de parear)
ALTER TABLE pairing_codes DROP FOREIGN KEY IF EXISTS fk_pairing_codes_device;
ALTER TABLE pairing_codes MODIFY device_id INT UNSIGNED NULL;
ALTER TABLE pairing_codes ADD CONSTRAINT fk_pairing_codes_device
  FOREIGN KEY (device_id) REFERENCES devices (id)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- Adiciona coluna client_id em pairing_codes (fingerprint da TV que gerou o código)
ALTER TABLE pairing_codes ADD COLUMN IF NOT EXISTS client_id VARCHAR(64) NULL AFTER device_id;
ALTER TABLE pairing_codes ADD KEY IF NOT EXISTS idx_pairing_codes_client_id (client_id);

SELECT 'Migration aplicada com sucesso.' AS status;
