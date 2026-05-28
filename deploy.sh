#!/bin/bash
# GVC Display — Script de deploy no VPS
# Uso: bash deploy.sh

echo "GVC Display — Iniciando deploy..."

# Puxa as atualizações do GitHub
git pull origin main

# Garante permissões corretas na pasta de uploads
chmod -R 755 uploads/
find uploads/ -type d -exec chmod 755 {} \;

echo "✅ Deploy concluído!"
echo "📺 Sistema disponível em: http://72.61.42.148/display"
