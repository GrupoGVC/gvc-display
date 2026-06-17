#!/bin/bash
# ================================================================
# GVC Display — Deploy script
# Uso: bash deploy.sh
# Execute no VPS após git pull / git reset --hard
# ================================================================

set -e

VPS_ROOT="/var/www/gvc-display"
NGINX_CONF="/etc/nginx/sites-available/gvc-display"

echo ""
echo "▶  GVC Display — Deploy"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# 1. Permissões
echo "→ Ajustando permissões..."
chown -R www-data:www-data "$VPS_ROOT/uploads"
chmod -R 755 "$VPS_ROOT/uploads"

# 2. Nginx
echo "→ Copiando config do Nginx..."
cp "$VPS_ROOT/NGINX_CONFIG.txt" "$NGINX_CONF"
nginx -t && systemctl reload nginx
echo "   ✓ Nginx recarregado"

# 3. Valida PHP
echo "→ Validando arquivos PHP..."
find "$VPS_ROOT/api" -name "*.php" -exec php -l {} \; | grep -v "No syntax errors" || true
php -l "$VPS_ROOT/tv.php"
php -l "$VPS_ROOT/api/meta_injector.php"
echo "   ✓ PHP OK"

echo ""
echo "✅  Deploy concluído!"
echo ""
