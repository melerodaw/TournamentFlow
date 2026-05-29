# TournamentFlow

Plataforma para gestionar torneos 1v1 (eliminacion simple ahora, suizo/round robin mas adelante).
Construido con Symfony y Doctrine, pensado para crecer sin rehacer el modelo.

## Stack

- Symfony 8
- Doctrine ORM
- PostgreSQL (recomendado)

## Modelo de dominio (resumen)

- User: cuenta base, organiza y participa en torneos
- Game: juego del torneo
- Tournament: torneo, formato y estado
- Participant: inscripcion de usuario al torneo
- TournamentMatch: partido del torneo
- MatchParticipant: slots 1/2 con score y ganador

## Requisitos

- PHP 8.5+
- Composer 2.x
- PostgreSQL 16+ (o Docker)

## Instalacion rapida

1. Instalar dependencias:
   - `composer install`
2. Configurar la base de datos (ver seccion "Base de datos")
3. Crear migraciones y tablas:
   - `php bin/console make:migration`
   - `php bin/console doctrine:migrations:migrate`
4. Levantar el servidor de desarrollo:
   - `php -S localhost:8000 -t public`

## Base de datos

La conexion se configura con `DATABASE_URL` en `.env` o mejor en `.env.local`.
Ejemplo con PostgreSQL local:

`DATABASE_URL="postgresql://app:TuPassword@127.0.0.1:5432/app?serverVersion=16&charset=utf8"`

Si prefieres Docker, puedes usar `compose.yaml`:

- `docker compose up -d`

## Comandos utiles

- Ver entidades mapeadas: `php bin/console doctrine:mapping:info`
- Crear migracion: `php bin/console make:migration`
- Ejecutar migraciones: `php bin/console doctrine:migrations:migrate`

## Notas

- El modelo esta preparado para soportar equipos en el futuro sin romper el esquema.
