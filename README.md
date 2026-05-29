# TournamentFlow

Plataforma para crear y gestionar torneos 1v1: creación de torneos, inscripciones, generación de brackets (eliminación directa) y soporte para sistema Suizo.

Resumen (lo que se verá en GitHub)

- Pequeña aplicación web construida con Symfony y Doctrine para gestionar torneos de videojuegos.
- Permite crear torneos, inscribir participantes, generar brackets y registrar resultados.
- Integración opcional con la RAWG API para importar datos e imágenes de videojuegos.

Stack (confirmado en el proyecto)

- PHP >= 8.4
- Symfony 6.4
- Doctrine ORM
- Base de datos: configurable vía `DATABASE_URL` (PostgreSQL recomendado)

Instalación rápida

1. Clonar el repo y entrar al directorio:

   git clone <repo-url>
   cd TournamentFlow

2. Instalar dependencias:

   composer install

3. Configurar variables de entorno (`.env.local`):

   - `DATABASE_URL` (ej: PostgreSQL)
   - `APP_SECRET`
   - `RAWG_API_KEY` (opcional, para importar juegos desde RAWG)

4. Ejecutar migraciones:

   php bin/console doctrine:migrations:migrate

5. Crear usuario administrador (comando proporcionado):

   php bin/console app:create-admin

Comandos útiles

- `php bin/console doctrine:mapping:info` — listar entidades mapeadas.
- `php bin/console make:migration` — generar migración.
- `php bin/console doctrine:migrations:migrate` — aplicar migraciones.

Notas

- La configuración de Doctrine y rutas se encuentra en `config/`.
- RAWG API se usa desde `src/Service/RawgApiService.php` y el endpoint de búsqueda está en `/admin/juegos/buscar-rawg`.
- Para desarrollo rápido se incluye `compose.yaml` y `compose.override.yaml`.

