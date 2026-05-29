# DOCUMENTACIÓN del proyecto TournamentFlow

## 1. Descripción general
TournamentFlow es una aplicación web para crear, gestionar e inspeccionar torneos de videojuegos. Permite crear torneos, inscribir participantes, generar brackets de eliminación directa y torneos en formato Suizo, registrar resultados y publicar clasificaciones.

Tecnologías usadas (confirmadas en el código):
- PHP >= 8.4 (requerido en `composer.json`).
- Symfony 6.4 (paquetes `symfony/*` en `composer.json`).
- Doctrine ORM / DoctrineBundle para acceso a base de datos y migraciones.
- Twig para plantillas y vistas.
- Sistema de forms y validación de Symfony.
- Interacción con RAWG API mediante `App\Service\RawgApiService` para búsqueda e importación de datos e imágenes de juegos.

## 2. Requisitos del sistema
- PHP: >= 8.4 (según `composer.json`).
- Symfony: 6.4 (paquetes requeridos en `composer.json`).
- Base de datos: configurable mediante la variable de entorno `DATABASE_URL` (Doctrine está configurado en `config/packages/doctrine.yaml`). El proyecto usa Doctrine DBAL/ORM; la configuración es independiente del motor, aunque en el repo hay señales de uso con PostgreSQL (consultas de ejemplo y comentarios en las migraciones).

Dependencias principales (extracción de `composer.json`):
- doctrine/doctrine-bundle
- doctrine/doctrine-migrations-bundle
- doctrine/orm
- symfony/asset, console, dotenv, flex, form, framework-bundle, property-info, runtime, security-bundle, translation, twig-bundle, validator, yaml
- require-dev: symfony/maker-bundle

## 3. Instalación y configuración
A partir del repositorio (resumen de pasos basados en la estructura del proyecto):

- Clonar el proyecto:

  git clone <repo-url>
  cd TournamentFlow

- Instalar dependencias:

  composer install

- Configurar variables de entorno (usar `.env` o variables de entorno del entorno de despliegue):
  - `APP_SECRET` (usado en `config/packages/framework.yaml`).
  - `DATABASE_URL` (para Doctrine, `config/packages/doctrine.yaml`).
  - `RAWG_API_KEY` (necesaria si se quiere utilizar la integración con RAWG; la `RawgApiService` inyecta `%env(string:RAWG_API_KEY)%`).

- Ejecutar migraciones:

  php bin/console doctrine:migrations:migrate

  (Las migraciones están en `migrations/`.)

- Crear usuario admin (hay un comando disponible):

  php bin/console app:create-admin

  El comando `app:create-admin` creará o reseteará un usuario con rol `admin` e imprimirá credenciales por consola (valores por defecto definidos en `src/Command/CreateAdminCommand.php`).

## 4. Arquitectura del proyecto
Estructura de carpetas clave:
- `src/Controller/` — Controladores HTTP (vistas y API).
- `src/Entity/` — Entidades Doctrine (modelo de dominio).
- `src/Repository/` — Repositorios personalizados.
- `src/Service/` — Servicios (RAWG, lógica Swiss, etc.).
- `templates/` — Plantillas Twig para vistas.
- `config/` — Configuración de Symfony (rutas, paquetes, servicios).
- `migrations/` — Migraciones de Doctrine.

Entidades principales y relaciones (resumen textual, basado en `src/Entity`):
- `User`
  - Campos principales: `id`, `username`, `email`, `roles` (json), `role` (string), `password` (hash), `avatarPath`, `createdAt`.
  - Relaciones: OneToMany `tournamentsOrganized` -> `Tournament`, OneToMany `participants` -> `Participant`.

- `Game`
  - Campos: `id`, `name`, `imagePath`, `description`, `rawgId`.
  - Relaciones: OneToMany `tournaments` -> `Tournament`.

- `Tournament`
  - Campos: `id`, `organizer` (User), `game` (Game), `name`, `description`, `format` (`single_elim` por defecto), `status`, `maxParticipants`, `startAt`, `registrationDeadlineAt`, `createdAt`, `swissRounds`, `champion` (Participant).
  - Relaciones: ManyToOne `organizer` -> `User`, ManyToOne `game` -> `Game`, OneToMany `participants` -> `Participant`, OneToMany `matches` -> `TournamentMatch`, OneToMany `rounds` -> `Round`.

- `Participant`
  - Campos: `id`, `user` (User), `tournament` (Tournament), `registeredAt`, `seed`, `status`.
  - Relaciones: ManyToOne `user`, ManyToOne `tournament`, OneToMany `matchParticipants` -> `MatchParticipant`.

- `TournamentMatch`
  - Campos: `id`, `tournament`, `round`, `slot`, `status`, `scheduledAt`, `playedAt`, `winner`, `participant1`, `participant2`.
  - Relaciones: ManyToOne `tournament`, ManyToOne `round`, ManyToOne `winner` -> `Participant`, ManyToOne `participant1`, `participant2` -> `Participant`, OneToMany `matchParticipants` -> `MatchParticipant`.

- `MatchParticipant`
  - Campos: `id`, `match` (TournamentMatch), `participant` (Participant), `slot`, `score`, `isWinner`.

- `Round`
  - Campos: `id`, `tournament`, `number`, `name`.
  - Relaciones: ManyToOne `tournament`, OneToMany `matches` -> `TournamentMatch`.

Diagrama de base de datos (texto, entidades principales y claves):

User (1) <--- (N) Tournament.organizer
Game (1) <--- (N) Tournament.game
Tournament (1) <--- (N) Participant.tournament
User (1) <--- (N) Participant.user
Tournament (1) <--- (N) Round.tournament
Round (1) <--- (N) TournamentMatch.round
Tournament (1) <--- (N) TournamentMatch.tournament
Participant (1) <--- (N) TournamentMatch.participant1 / participant2 / winner
TournamentMatch (1) <--- (N) MatchParticipant.match
Participant (1) <--- (N) MatchParticipant.participant

## 5. Funcionalidades implementadas
A continuación se describen las funcionalidades observadas en el código.

- Sistema de autenticación y roles
  - Autenticación gestionada por Symfony Security (`config/packages/security.yaml`).
  - `App\Security\AppAuthenticator` implementa el proceso de login (archivo `src/Security/AppAuthenticator.php`).
  - La entidad `User` mantiene tanto `roles` (JSON) como un campo `role` string (`admin` o `user`) que se mapea a `ROLE_ADMIN` o `ROLE_USER` en `setRole()`.
  - Access-control en `security.yaml` permite rutas públicas (`/`, `/home`, `/api`, `/login`, `/register`, etc.) y restringe `/admin` a `ROLE_ADMIN`.

- Gestión de juegos (CRUD admin + RAWG API)
  - CRUD de juegos en `src/Controller/GameController.php` con vistas en `templates/game/*`.
  - Creación/edición/eliminación con permisos (las rutas de creación/edición/eliminación usan `#[IsGranted('ROLE_ADMIN')]`).
  - `RawgApiService` (`src/Service/RawgApiService.php`) implementa `searchGames()`, `getGame()` y `downloadImage()`. Existe un endpoint JSON en `AdminRawgGameController::searchRawg` (`/admin/juegos/buscar-rawg`) que devuelve `results`.
  - Al crear un juego desde la UI, si se selecciona un `rawg_id`, el `GameController` intenta importar datos (nombre, descripción, imagen) y descarga la imagen usando `RawgApiService::downloadImage()`.

- Torneos (crear, editar, cancelar, finalizar)
  - `TournamentController` implementa creación (`new`), edición (`edit`), ver detalles (`show`), y eliminación (`delete`).
  - Un torneo tiene estados (`status`) y un método para computar estado (`getComputedStatus`) en la entidad que suma reglas de fechas y plazas.
  - Cancelar torneo: ruta `app_tournament_cancel` (requiere ser organizador o admin, según `canManageTournament()`).
  - Finalizarse automáticamente cuando se registra campeón (en `BracketController` al registrar resultados) o en lógica Swiss cuando están completas las rondas.

- Inscripción y abandono de torneos
  - `TournamentController::join` y `::leave` (POST) para unirse o abandonar. Se validan tokens CSRF y condiciones (plazas, si ya está inscrito, si no tiene partidos jugados para abandonar).

- Bracket de eliminación directa
  - `BracketController::generate` crea rondas y `TournamentMatch` para eliminación directa. Requiere que el número de participantes sea una potencia de dos y al menos 4.
  - `BracketController::recordResult` registra resultado de un partido, asigna ganador, avanza al siguiente partido y marca torneo finalizado cuando corresponde.
  - Plantilla de visualización: `templates/tournament/bracket.html.twig`.

- Sistema Suizo (Swiss)
  - Implementado en `App\Service\SwissBracketService`.
  - `generateFirstRound()` genera emparejamientos iniciales (mezcla aleatoria y empareja en parejas; maneja bye si hay impar).
  - `generateNextRound()` agrupa por victorias, evita emparejamientos repetidos cuando sea posible y genera rondas siguientes.
  - `calculateStandings()` calcula puntos, victorias, derrotas y Buchholz (suma de puntos de los oponentes).
  - Endpoints para generar rondas y ver bracket suizo en `BracketController` (`/torneo/{id}/swiss/generar-ronda`, `/torneo/{id}/swiss/bracket`, `/torneo/{id}/swiss/resultado/{matchId}`).

- Panel de administración
  - `AdminController` y `AdminTournamentResultsController` bajo `/admin` (requieren `ROLE_ADMIN` mediante atributo `#[IsGranted('ROLE_ADMIN')]`).
  - Panel permite ver juegos, torneos y eliminar torneos desde la interfaz administradora.

## 6. API REST
Rutas expuestas por `src/Controller/ApiController.php` (prefijo `/api`):

- GET `/api/juegos` (nombre de ruta `api_games_index`)
  - Descripción: Devuelve listado de juegos serializados.
  - Ejemplo de respuesta (cada item):
    {
      "id": 1,
      "name": "Nombre del juego",
      "imagePath": "https://host/images/games/foo.jpg"
    }

- GET `/api/torneos` (nombre `api_tournaments_index`)
  - Descripción: Lista torneos con resumen (id, name, status, format, maxParticipants, startAt, registrationDeadlineAt, participantsCount, game, organizer).
  - Ejemplo (simplificado):
    [
      {
        "id": 10,
        "name": "Liga Ejemplo",
        "status": "open",
        "format": "single_elim",
        "maxParticipants": 16,
        "startAt": "2026-06-01T18:00:00",
        "registrationDeadlineAt": "2026-05-31T23:59:00",
        "participantsCount": 8,
        "game": { "id": 1, "name": "Juego", "imagePath": null },
        "organizer": { "id": 2, "username": "user1" }
      }
    ]

- GET `/api/torneos/{id}` (nombre `api_tournaments_show`)
  - Descripción: Detalle de un torneo, incluye `participants` y `champion` (si existe).

- GET `/api/torneos/{id}/bracket` (nombre `api_tournaments_bracket`)
  - Descripción: Devuelve la estructura del bracket según `format` del torneo. Si `format` es `single_elim` devuelve `rounds` con matches; si `swiss` devuelve `rounds` y `standings`.

Notas: las respuestas se construyen con métodos `serializeGame`, `serializeTournamentSummary`, `serializeMatches`, y formatos JSON con `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.

## 7. Formatos de torneo
- Eliminación directa (single_elim)
  - Generación: requiere número de participantes potencia de dos y >= 4.
  - Se generan rondas con `Round` y `TournamentMatch` en `BracketController::generate`.
  - Al registrar resultados, el ganador avanza al siguiente match (lógica en `recordResult`).

- Sistema Suizo (swiss)
  - Generación y avance: `SwissBracketService` implementa `generateFirstRound` y `generateNextRound`.
  - Los emparejamientos se intentan evitar contra oponentes previos (heurística greedy), se usan puntos y Buchholz para clasificar en `calculateStandings`.
  - Soporta bye (victoria automática) si hay número impar de participantes.

## 8. Roles y permisos
Basado en `config/packages/security.yaml`, atributos `IsGranted` y comprobaciones de controladores:

- `admin` / `ROLE_ADMIN`:
  - Acceso al panel `/admin` (todas las rutas bajo `/admin`).
  - Crear/editar/eliminar juegos (`GameController` protege creación/edición/borrado con `IsGranted('ROLE_ADMIN')`).
  - Eliminar torneos desde el panel admin (`AdminController` / `AdminTournamentResultsController`).
  - Generar/gestionar resultados y rondas en cualquier torneo desde la UI.

- `usuario` / `ROLE_USER` (usuario autenticado):
  - Crear torneos (`app_tournament_new`).
  - Editar, cancelar o eliminar sus propios torneos (métodos comprueban si el usuario es el organizador o `ROLE_ADMIN`).
  - Inscribirse (`join`) y abandonar (`leave`) torneos.
  - Registrar resultados en sus torneos si es organizador (o si es admin).

- `anónimo` / público:
  - Ver listado de juegos, landing y listado de torneos (`/`, `/home`, `/tournament`), y la API (`/api/*`) están permitidos en `access_control`.
  - Rutas de registro e inicio de sesión accesibles públicamente (`/register`, `/login`).

## 9. Integración con RAWG API
- Servicio: `App\Service\RawgApiService`.
  - `searchGames(string $query)` consulta el endpoint `https://api.rawg.io/api/games` y normaliza resultados (`id`, `name`, `background_image`, `genres`, `description_raw`).
  - `getGame(int $rawgId)` obtiene detalle de un juego por id RAWG.
  - `downloadImage(string $imageUrl, string $gameName)` descarga y guarda la imagen en `public/images/games/` devolviendo la ruta relativa si la descarga y guardado son exitosos.
- Endpoint de búsqueda: `AdminRawgGameController::searchRawg` -> GET `/admin/juegos/buscar-rawg` devuelve JSON con `results`.
- Flujo en UI: en `templates/game/new.html.twig` hay un buscador RAWG que llama al endpoint anterior; al seleccionar un resultado, el formulario `GameType` se completa con `rawg_id`, nombre, descripción y (si se puede descargar) `imagePath`.

## 10. Capturas y rutas principales
A continuación, rutas principales encontradas en los controladores (ruta / método / descripción breve):

- `/` (GET) — `app_landing` : Landing público (`HomeController::landing`).
- `/home` (GET) — `app_home` : Panel de usuario o landing si no autenticado.
- `/login` (GET, POST) — `app_login` : Inicio de sesión (`SecurityController`).
- `/logout` (route) — `app_logout` : Logout (gestionado por firewall).
- `/register` (GET, POST) — `app_register` : Registro de usuario (`RegistrationController`).

- `/game` (GET) — `app_game_index` : Listado de juegos (`GameController::index`).
- `/game/new` (GET, POST) — `app_game_new` : Crear juego (admin).
- `/game/{id}` (GET) — `app_game_show` : Mostrar detalle juego.
- `/game/{id}/edit` (GET, POST) — `app_game_edit` : Editar juego (admin).
- `/game/{id}` (POST) — `app_game_delete` : Eliminar juego (admin).

- `/admin` (GET) — `app_admin_index` : Panel admin (`AdminController::index`) — protegido `ROLE_ADMIN`.
- `/admin/tournament/{id}/delete` (POST) — `app_admin_tournament_delete` : Eliminar torneo desde admin.
- `/admin/juegos/buscar-rawg` (GET) — `app_game_search_rawg` : Búsqueda RAWG (JSON).

- `/tournament` (GET) — `app_tournament_index` : Listado de torneos.
- `/tournament/mis-torneos` (GET) — `app_tournament_mine` : Mis torneos (usuario autenticado).
- `/tournament/new` (GET, POST) — `app_tournament_new` : Crear torneo (usuario autenticado).
- `/tournament/{id}` (GET) — `app_tournament_show` : Ver torneo.
- `/tournament/{id}/join` (POST) — `app_tournament_join` : Unirse a torneo.
- `/tournament/{id}/leave` (POST) — `app_tournament_leave` : Abandonar torneo.
- `/tournament/{id}/edit` (GET, POST) — `app_tournament_edit` : Editar torneo (organizador o admin).
- `/tournament/torneo/{id}/cancelar` (POST) — `app_tournament_cancel` : Cancelar torneo (nota: ruta definida con `'/torneo/{id}/cancelar'` en el controlador que tiene prefijo `/tournament`, por lo que la ruta final es `/tournament/torneo/{id}/cancelar`).
- `/tournament/{id}` (POST) — `app_tournament_delete` : Eliminar torneo (organizador o admin).

- `/torneo/{id}/bracket` (GET) — `app_tournament_bracket` : Ver bracket eliminación directa (`BracketController`).
- `/torneo/{id}/generar-bracket` (POST) — `app_tournament_generate_bracket` : Generar bracket eliminación directa.
- `/torneo/{id}/match/{matchId}/result` (POST) — `app_tournament_match_result` : Registrar resultado de match.

- Swiss (prefijo `/torneo` en `BracketController`):
  - `/torneo/{id}/swiss/generar-ronda` (POST) — `app_tournament_swiss_generate_round` : Generar ronda Swiss.
  - `/torneo/{id}/swiss/bracket` (GET) — `app_tournament_swiss_bracket` : Ver bracket Suizo.
  - `/torneo/{id}/swiss/resultado/{matchId}` (POST) — `app_tournament_swiss_result` : Registrar resultado Swiss.

- API (prefijo `/api` en `ApiController`):
  - `/api/juegos` (GET) — `api_games_index`.
  - `/api/torneos` (GET) — `api_tournaments_index`.
  - `/api/torneos/{id}` (GET) — `api_tournaments_show`.
  - `/api/torneos/{id}/bracket` (GET) — `api_tournaments_bracket`.


---

Si quieres, puedo:
- Añadir ejemplos de comandos `bin/console` concretos para tareas comunes (migraciones, fixtures, crear admin). 
- Generar un diagrama en formato más visual (PlantUML o mermaid) con las relaciones de entidad.

Archivo generado: DOCUMENTACION.md ubicado en la raíz del proyecto.
