#!/bin/bash

# Script de despliegue para cPanel
echo "Iniciando despliegue..."

# Instalar dependencias
echo "Instalando dependencias..."
npm install

# Construir el proyecto
echo "Construyendo proyecto..."
npm run build

# Copiar archivos del build al directorio público
echo "Copiando archivos..."
cp -r dist/* $HOME/public_html/

# Configurar permisos
echo "Configurando permisos..."
chmod -R 755 $HOME/public_html/

echo "¡Despliegue completado!"