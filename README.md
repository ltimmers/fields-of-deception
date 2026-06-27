# Fields of Deception

A Stratego-like strategy game built with Laravel 12 (PHP 8.4) and Angular 20.

## Features

- 🎮 Classic Stratego gameplay with 10x10 board
- 🤖 AI opponent with three difficulty levels (Easy, Medium, Hard)
- 👥 Multiplayer support (play against other humans)
- 🔄 Real-time updates using Laravel Reverb WebSockets
- 🔐 User authentication with Laravel Sanctum
- 💾 Persistent game state with MySQL and Redis

## Tech Stack

### Backend
- PHP 8.4
- Laravel 12
- Laravel Reverb (WebSocket broadcasting)
- Laravel Sanctum (API authentication)
- MySQL (database)
- Redis (caching and sessions)
- OpenAI-compatible or Azure OpenAI LLM integration for AI moves

### Frontend
- Angular 20
- TypeScript
- SCSS
- Laravel Echo (WebSocket client)

### Infrastructure
- Docker & Docker Compose

## Prerequisites

- Docker and Docker Compose
- Node.js 20+ (for local frontend development)
- PHP 8.4+ (for local backend development)

## Quick Start with Docker

1. Clone the repository:
```bash
git clone https://github.com/ltimmers/Fields-of-Deception.git
cd Fields-of-Deception
```

2. Copy environment files:
```bash
cp backend/.env.example backend/.env
```

3. Start the Docker containers:
```bash
docker-compose up -d
```

4. Install backend dependencies and run migrations:
```bash
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
```

5. Install frontend dependencies:
```bash
docker-compose exec frontend npm install
```

6. Access the application:
- Frontend: http://localhost:4200
- Backend API: http://localhost:8000/api
- WebSocket Server: ws://localhost:8080

## Local Development (without Docker)

### Backend Setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

### Frontend Setup

```bash
cd frontend
npm install
npm start
```

### Start WebSocket Server

```bash
cd backend
php artisan reverb:start
```

### Configure LLM Provider

The AI opponent can use either a local OpenAI-compatible endpoint or Azure OpenAI.

For local/OpenAI-compatible services:
```bash
LLM_PROVIDER=openai_compatible
LLM_BASE_URL=http://localhost:1234/v1
LLM_MODEL=local-model
LLM_API_KEY=
```

For Azure OpenAI with a GPT-5.4 deployment:
```bash
LLM_PROVIDER=azure
LLM_API_KEY=your-azure-api-key
AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com
AZURE_OPENAI_API_VERSION=2024-10-21
AZURE_OPENAI_DEPLOYMENT=gpt-5.4
```

## Testing

The backend includes comprehensive unit and feature tests for the API, game logic, and models.

### Running Tests with Docker

Run all tests:
```bash
docker-compose exec app php artisan test
```

Run specific test suites:
```bash
# Run only feature tests
docker-compose exec app php artisan test --testsuite=Feature

# Run only unit tests
docker-compose exec app php artisan test --testsuite=Unit
```

Run with coverage (requires Xdebug):
```bash
docker-compose exec app php artisan test --coverage
```

### Running Tests Locally

```bash
cd backend
php artisan test
```

Run specific test files:
```bash
# Run a specific test file
php artisan test tests/Feature/AuthControllerTest.php

# Run a specific test method
php artisan test --filter test_user_can_register_with_valid_data
```

### Test Coverage

The test suite includes:
- **Feature Tests**: Authentication, game management, API endpoints
- **Unit Tests**: Game service logic, models, AI service, piece combat rules
- **Total**: 10 test files with 2,098 lines covering authentication, game mechanics, validation, and edge cases

## Game Rules

Fields of Deception follows classic Stratego rules:

### Pieces (40 per player)
| Rank | Name | Count | Special Ability |
|------|------|-------|-----------------|
| 10 | Marshal | 1 | Highest rank |
| 9 | General | 1 | |
| 8 | Colonel | 2 | |
| 7 | Major | 3 | |
| 6 | Captain | 4 | |
| 5 | Lieutenant | 4 | |
| 4 | Sergeant | 4 | |
| 3 | Miner | 5 | Can defuse Bombs |
| 2 | Scout | 8 | Can move multiple squares |
| 1 | Spy | 1 | Defeats Marshal when attacking |
| B | Bomb | 6 | Immovable, destroys attackers |
| F | Flag | 1 | Immovable, capture to win |

### Combat Rules
- Higher rank defeats lower rank
- Equal ranks destroy each other
- Spy defeats Marshal only when attacking
- Miners can defuse Bombs
- Bombs destroy all attackers except Miners

### Winning Conditions
- Capture opponent's Flag
- Opponent has no movable pieces

## API Endpoints

### Authentication
- `POST /api/register` - Register new user
- `POST /api/login` - Login
- `POST /api/logout` - Logout
- `GET /api/user` - Get current user

### Games
- `GET /api/games` - List user's games
- `GET /api/games/open` - List open games
- `POST /api/games` - Create new game
- `GET /api/games/{id}` - Get game state
- `POST /api/games/{id}/join` - Join a game
- `POST /api/games/{id}/setup` - Submit piece setup
- `POST /api/games/{id}/move` - Make a move
- `POST /api/games/{id}/valid-moves` - Get valid moves
- `POST /api/games/{id}/forfeit` - Forfeit game

## WebSocket Events

### Channels
- `games` - Public channel for game listings
- `game.{id}` - Private channel for specific game

### Events
- `game.created` - New game created
- `game.started` - Game started (both players ready)
- `game.updated` - Game state updated
- `move.made` - Move made
- `setup.complete` - Player completed setup

## Project Structure

```
Fields-of-Deception/
├── backend/                 # Laravel backend
│   ├── app/
│   │   ├── Enums/          # Game enums (Status, Color, Rank)
│   │   ├── Events/         # Broadcasting events
│   │   ├── Http/
│   │   │   └── Controllers/ # API controllers
│   │   ├── Models/         # Eloquent models
│   │   └── Services/       # Game and AI logic
│   ├── config/             # Laravel configuration
│   ├── database/
│   │   └── migrations/     # Database migrations
│   └── routes/             # API routes
├── frontend/               # Angular frontend
│   └── src/
│       └── app/
│           ├── components/ # Angular components
│           ├── guards/     # Route guards
│           ├── models/     # TypeScript interfaces
│           └── services/   # Angular services
├── docker/                 # Docker configurations
│   ├── nginx/
│   ├── node/
│   └── php/
└── docker-compose.yml      # Docker Compose config
```

## License

MIT License
