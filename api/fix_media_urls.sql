-- GVC Display — Corrige URLs de mídia no banco
-- Rodar se os vídeos aparecem com 404 (URLs absolutas com path errado)
-- 
-- Remove o prefixo http://localhost/QUALQUER_PASTA e deixa só /uploads/...

UPDATE media 
SET url = CONCAT('/uploads/', SUBSTRING_INDEX(url, '/uploads/', -1))
WHERE url LIKE '%/uploads/%' 
  AND url NOT LIKE '/uploads/%';

-- Verificar resultado
SELECT id, original, url FROM media LIMIT 10;
