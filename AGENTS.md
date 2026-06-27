# AGENTS.md

## Project overview
- Fields of Deception is a Stratego-like game with a Laravel 12 backend (`backend/`) and an Angular 20 frontend (`frontend/`).
- Backend exposes a token-authenticated JSON API using Laravel Sanctum and broadcasts real-time game events with Reverb.
- Frontend is a standalone-component Angular app using lazy-loaded routes, `HttpClient`, and an auth interceptor.
- Core game domains include authentication, game lifecycle, setup validation, move validation, AI opponents, and optional LLM-assisted AI.

## Repository layout
- `backend/app/Http/Controllers/` — API controllers, mainly `AuthController` and `GameController`
- `backend/app/Services/` — game rules, AI logic, and LLM integration
- `backend/app/Models/` and `backend/app/Enums/` — persistence and game state types
- `backend/routes/api.php` — API route definitions
- `backend/tests/Feature` and `backend/tests/Unit` — Laravel test suites
- `frontend/src/app/components/` — UI screens such as home, auth, and game board
- `frontend/src/app/services/` — API, auth, websocket, and interceptor services
- `frontend/src/app/models/` — TypeScript interfaces for API/game data
- `docker/` and `docker-compose.yml` — local containerized development

## Architecture notes
- The backend is the source of truth for game state and rules. Prefer putting rule changes in Laravel services, not the Angular client.
- Auth endpoints return Sanctum tokens; the frontend stores the token in `localStorage` and injects it via an HTTP interceptor.
- Game routes are protected by `auth:sanctum`; frontend protected routes use `authGuard`.
- Angular routes are lazy-loaded with `loadComponent`, so preserve that pattern when adding screens.
- Multiplayer updates rely on broadcast events; avoid introducing polling where websocket updates already exist.
- AI-related behavior spans controller + service layers. Keep AI move/setup changes aligned with backend validation and API contracts.

## Developer workflow
- Backend setup: `cd backend && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate && php artisan db:seed`
- Frontend setup: `cd frontend && npm install`
- Run backend locally: `cd backend && php artisan serve`
- Run websocket server: `cd backend && php artisan reverb:start`
- Run frontend locally: `cd frontend && npm start`
- Docker flow is available through `docker-compose up -d`

## Testing and validation
- Before changing code, understand existing behavior in both the backend API and frontend consumers.
- Backend tests: `cd backend && php artisan test`
- Frontend tests: `cd frontend && npm test -- --watch=false`
- If your change touches API contracts, validate both Laravel tests and Angular build/test impact.
- Prefer updating or adding tests close to the affected layer when behavior changes.

## Change guidance for agents
- Make surgical changes and avoid refactoring unrelated areas.
- Preserve API shapes unless the task explicitly requires contract changes.
- When changing game rules, check controller validation, service logic, model serialization, and frontend model/service usage together.
- When changing auth flows, verify backend Sanctum responses and frontend token persistence/logout behavior.
- When changing real-time features, inspect both backend broadcast events and frontend websocket consumers.
- Keep environment-specific values in existing config or environment files; do not hardcode secrets or endpoints.
- Update README only when workflow or externally visible behavior changes.

## High-risk areas
- Stratego rule enforcement in `backend/app/Services/GameService.php`
- AI and LLM-assisted move/setup behavior in `backend/app/Services/AIService.php` and `backend/app/Services/LLMService.php`
- Large UI state flows in `frontend/src/app/components/game-board/game-board.component.ts`
- Auth/session handling across `AuthController`, `auth.interceptor`, `authGuard`, and `AuthService`

## Practical tips
- Start investigation from `backend/routes/api.php` and the frontend services consuming those endpoints.
- For gameplay bugs, trace: route/controller -> service -> model payload -> frontend model/service -> component rendering.
- For UI issues, confirm whether the bug is present in client state only or originates from API payloads.
- Respect the existing standalone Angular style and Laravel conventions already used in the repo.
