#!/usr/bin/env sh
set -eu
docker compose up -d --build
echo "Aplicación: http://localhost:8085"
echo "Base de datos: http://localhost:8086"
echo "Usuario: admin@prueba.local"
echo "Clave: Prueba2026!"
