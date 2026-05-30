-- GVC Display — Migration: adiciona slug na tabela devices
-- Rodar UMA vez no banco de produção

ALTER TABLE devices 
  ADD COLUMN IF NOT EXISTS slug VARCHAR(80) DEFAULT NULL UNIQUE
  AFTER name;

-- Gera slug automático para dispositivos existentes (baseado no nome)
UPDATE devices 
SET slug = LOWER(
  REPLACE(
    REPLACE(
      REPLACE(
        REPLACE(name, ' ', '-'),
        '/', '-'
      ),
      '--', '-'
    ),
    'ã', 'a'
  )
)
WHERE slug IS NULL;
