#!/bin/sh
# Cria o usuário da aplicação (sem superusuário), o banco principal e o de testes,
# e o schema "extensions" com pg_trgm e unaccent em cada um.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" <<SQL
CREATE ROLE "$APP_DB_USER" LOGIN PASSWORD '$APP_DB_PASSWORD' NOSUPERUSER NOCREATEDB NOCREATEROLE;
CREATE DATABASE "$APP_DB_NAME" OWNER "$APP_DB_USER";
CREATE DATABASE "${APP_DB_NAME}_test" OWNER "$APP_DB_USER";
SQL

for db in "$APP_DB_NAME" "${APP_DB_NAME}_test"; do
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" -d "$db" <<SQL
CREATE SCHEMA IF NOT EXISTS extensions;
GRANT USAGE ON SCHEMA extensions TO "$APP_DB_USER";
CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA extensions;
CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA extensions;
SQL
done
