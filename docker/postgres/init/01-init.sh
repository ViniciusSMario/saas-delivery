#!/bin/sh
# Cria o usuário da aplicação (sem superusuário), o banco principal e o de testes,
# e o schema "extensions" com pg_trgm e unaccent em cada um.
#
# CREATEROLE: a aplicação cria um papel por estabelecimento (TENANCY_DATABASE_ROLES),
# que só enxerga o próprio schema. Continua sem ser superusuário.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" <<SQL
CREATE ROLE "$APP_DB_USER" LOGIN PASSWORD '$APP_DB_PASSWORD' NOSUPERUSER NOCREATEDB CREATEROLE;
CREATE DATABASE "$APP_DB_NAME" OWNER "$APP_DB_USER";
CREATE DATABASE "${APP_DB_NAME}_test" OWNER "$APP_DB_USER";
SQL

for db in "$APP_DB_NAME" "${APP_DB_NAME}_test"; do
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" -d "$db" <<SQL
CREATE SCHEMA IF NOT EXISTS extensions;
-- Funções das extensões (busca por trigram) liberadas para todos os papéis.
GRANT USAGE ON SCHEMA extensions TO PUBLIC;
CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA extensions;
CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA extensions;
SQL
done
