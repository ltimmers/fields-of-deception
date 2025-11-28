import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { Subscription } from 'rxjs';
import { AuthService } from '../../services/auth.service';
import { GameService } from '../../services/game.service';
import { WebSocketService } from '../../services/websocket.service';
import { Game, AIDifficulty } from '../../models/game.model';
import { User } from '../../models/user.model';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="home-container">
      <header>
        <h1>Fields of Deception</h1>
        <div class="user-info">
          <span>Welcome, {{ user?.name }}</span>
          <button class="logout-btn" (click)="logout()">Logout</button>
        </div>
      </header>

      <main>
        <section class="new-game">
          <h2>Start New Game</h2>
          <div class="game-options">
            <div class="option-card" (click)="createGame(true)">
              <div class="icon">🤖</div>
              <h3>Play vs AI</h3>
              <select [(ngModel)]="aiDifficulty" (click)="$event.stopPropagation()">
                <option value="easy">Easy</option>
                <option value="medium">Medium</option>
                <option value="hard">Hard</option>
              </select>
            </div>
            <div class="option-card" (click)="createGame(false)">
              <div class="icon">👥</div>
              <h3>Play vs Human</h3>
              <p>Create a game and wait for opponent</p>
            </div>
          </div>
        </section>

        <section class="game-lists">
          <div class="list-section">
            <h2>Your Games</h2>
            @if (myGames.length === 0) {
              <p class="empty">No active games</p>
            } @else {
              <div class="game-list">
                @for (game of myGames; track game.id) {
                  <div class="game-card" (click)="goToGame(game.id)">
                    <div class="game-status" [class]="game.status">{{ formatStatus(game.status) }}</div>
                    <div class="game-info">
                      <span>{{ game.is_vs_ai ? 'vs AI (' + game.ai_difficulty + ')' : 'vs Human' }}</span>
                      <span class="turn" *ngIf="game.status === 'in_progress'">
                        {{ game.current_turn === 'red' ? 'Red' : 'Blue' }}'s turn
                      </span>
                      <span class="winner" *ngIf="game.winner">
                        {{ game.winner === 'red' ? 'Red' : 'Blue' }} wins!
                      </span>
                    </div>
                  </div>
                }
              </div>
            }
          </div>

          <div class="list-section">
            <h2>Open Games</h2>
            @if (openGames.length === 0) {
              <p class="empty">No open games available</p>
            } @else {
              <div class="game-list">
                @for (game of openGames; track game.id) {
                  <div class="game-card">
                    <div class="game-info">
                      <span>Created by {{ game.player_red?.name || 'Unknown' }}</span>
                    </div>
                    <button class="join-btn" (click)="joinGame(game.id)">Join Game</button>
                  </div>
                }
              </div>
            }
          </div>
        </section>
      </main>
    </div>
  `,
  styles: [`
    .home-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px;
    }

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 40px;
      padding-bottom: 20px;
      border-bottom: 1px solid #333;
    }

    h1 {
      color: #e94560;
      margin: 0;
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 20px;
      color: #ccc;
    }

    .logout-btn {
      padding: 8px 16px;
      background: transparent;
      border: 1px solid #e94560;
      color: #e94560;
      border-radius: 5px;
      cursor: pointer;
      transition: all 0.3s;
    }

    .logout-btn:hover {
      background: #e94560;
      color: #fff;
    }

    .new-game {
      margin-bottom: 40px;
    }

    h2 {
      color: #fff;
      margin-bottom: 20px;
    }

    .game-options {
      display: flex;
      gap: 20px;
    }

    .option-card {
      flex: 1;
      padding: 30px;
      background: #1a1a2e;
      border-radius: 10px;
      text-align: center;
      cursor: pointer;
      transition: transform 0.3s, box-shadow 0.3s;
    }

    .option-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 30px rgba(233, 69, 96, 0.3);
    }

    .option-card .icon {
      font-size: 48px;
      margin-bottom: 15px;
    }

    .option-card h3 {
      color: #fff;
      margin-bottom: 10px;
    }

    .option-card p {
      color: #888;
      font-size: 14px;
    }

    .option-card select {
      padding: 8px 16px;
      background: #16213e;
      border: 1px solid #333;
      color: #fff;
      border-radius: 5px;
      cursor: pointer;
    }

    .game-lists {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
    }

    .list-section {
      background: #1a1a2e;
      padding: 20px;
      border-radius: 10px;
    }

    .game-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .game-card {
      padding: 15px;
      background: #16213e;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.3s;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .game-card:hover {
      background: #1f2b47;
    }

    .game-status {
      padding: 4px 10px;
      border-radius: 4px;
      font-size: 12px;
      text-transform: uppercase;
    }

    .game-status.waiting {
      background: #ffd93d;
      color: #000;
    }

    .game-status.setup {
      background: #6bcb77;
      color: #000;
    }

    .game-status.in_progress {
      background: #4d96ff;
      color: #fff;
    }

    .game-status.finished {
      background: #888;
      color: #fff;
    }

    .game-info {
      display: flex;
      flex-direction: column;
      gap: 5px;
      color: #ccc;
    }

    .game-info .turn {
      color: #4d96ff;
      font-size: 14px;
    }

    .game-info .winner {
      color: #6bcb77;
      font-size: 14px;
    }

    .join-btn {
      padding: 8px 16px;
      background: #e94560;
      color: #fff;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      transition: background 0.3s;
    }

    .join-btn:hover {
      background: #c73e54;
    }

    .empty {
      color: #666;
      text-align: center;
      padding: 20px;
    }

    @media (max-width: 768px) {
      .game-options {
        flex-direction: column;
      }

      .game-lists {
        grid-template-columns: 1fr;
      }
    }
  `]
})
export class HomeComponent implements OnInit, OnDestroy {
  user: User | null = null;
  myGames: Game[] = [];
  openGames: Game[] = [];
  aiDifficulty: AIDifficulty = 'medium';
  private subscriptions: Subscription[] = [];

  constructor(
    private authService: AuthService,
    private gameService: GameService,
    private wsService: WebSocketService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.subscriptions.push(
      this.authService.currentUser$.subscribe(user => {
        this.user = user;
      })
    );

    this.loadGames();
    this.setupWebSocket();
  }

  ngOnDestroy(): void {
    this.subscriptions.forEach(sub => sub.unsubscribe());
    this.wsService.unsubscribeFromGames();
  }

  private loadGames(): void {
    this.gameService.getGames().subscribe(games => {
      this.myGames = games;
    });

    this.gameService.getOpenGames().subscribe(games => {
      this.openGames = games.filter(g => g.player_red_id !== this.user?.id);
    });
  }

  private setupWebSocket(): void {
    this.wsService.subscribeToGames();
    this.subscriptions.push(
      this.wsService.gameEvents$.subscribe(event => {
        if (event.event === 'game.created') {
          this.loadGames();
        }
      })
    );
  }

  createGame(vsAi: boolean): void {
    this.gameService.createGame(vsAi, this.aiDifficulty).subscribe(game => {
      this.router.navigate(['/game', game.id]);
    });
  }

  joinGame(gameId: number): void {
    this.gameService.joinGame(gameId).subscribe(game => {
      this.router.navigate(['/game', game.id]);
    });
  }

  goToGame(gameId: number): void {
    this.router.navigate(['/game', gameId]);
  }

  formatStatus(status: string): string {
    return status.replace('_', ' ');
  }

  logout(): void {
    this.authService.logout().subscribe(() => {
      this.router.navigate(['/login']);
    });
  }
}
