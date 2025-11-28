import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { Subscription } from 'rxjs';
import { GameService } from '../../services/game.service';
import { WebSocketService } from '../../services/websocket.service';
import { AuthService } from '../../services/auth.service';
import {
  Game,
  Piece,
  PieceRank,
  PieceNames,
  PieceCounts,
  PlayerColor,
  SetupPiece,
  Move,
  MoveResult
} from '../../models/game.model';
import { isLakePosition, LAKE_POSITIONS } from '../../models/game-constants';
import { PieceSelectorComponent } from '../piece-selector/piece-selector.component';

@Component({
  selector: 'app-game-board',
  standalone: true,
  imports: [CommonModule, PieceSelectorComponent],
  template: `
    <div class="game-container">
      <header>
        <button class="back-btn" (click)="goBack()">← Back</button>
        <h1>Fields of Deception</h1>
        <div class="game-info">
          <span class="status" [class]="game?.status">{{ formatStatus(game?.status || '') }}</span>
          @if (game?.status === 'in_progress') {
            <span class="turn" [class.my-turn]="isMyTurn">
              {{ game?.current_turn === 'red' ? 'Red' : 'Blue' }}'s Turn
              {{ isMyTurn ? '(Your Turn)' : '' }}
            </span>
          }
          @if (game?.winner) {
            <span class="winner">{{ game?.winner === 'red' ? 'Red' : 'Blue' }} Wins!</span>
          }
        </div>
      </header>

      @if (game?.status === 'setup' && !setupComplete) {
        <div class="setup-phase">
          <h2>Setup Phase - Place Your Pieces</h2>
          <p>Click on a piece below, then click on the board to place it.</p>
          <p>Place your pieces in the {{ playerColor === 'red' ? 'bottom 4 rows' : 'top 4 rows' }}</p>

          <app-piece-selector
            [availablePieces]="availablePieces"
            [selectedPiece]="selectedSetupPiece"
            (pieceSelected)="selectSetupPiece($event)"
          ></app-piece-selector>

          <button
            class="submit-setup"
            [disabled]="!isSetupValid()"
            (click)="submitSetup()"
          >
            Confirm Setup ({{ placedPieces.length }}/40 pieces)
          </button>
        </div>
      }

      <div class="board-container">
        <div class="board">
          @for (row of [0,1,2,3,4,5,6,7,8,9]; track row) {
            <div class="row">
              @for (col of [0,1,2,3,4,5,6,7,8,9]; track col) {
                <div
                  class="cell"
                  [class.lake]="isLake(row, col)"
                  [class.valid-move]="isValidMoveTarget(row, col)"
                  [class.selected]="isSelected(row, col)"
                  [class.setup-zone]="isSetupZone(row, col)"
                  [class.last-move]="isLastMove(row, col)"
                  (click)="onCellClick(row, col)"
                >
                  @if (board && board[row] && board[row][col]) {
                    @if (board[row][col]?.type !== 'lake') {
                      <div
                        class="piece"
                        [class.red]="board[row][col]?.color === 'red'"
                        [class.blue]="board[row][col]?.color === 'blue'"
                        [class.hidden]="board[row][col]?.hidden"
                        [class.revealed]="board[row][col]?.revealed"
                      >
                        @if (!board[row][col]?.hidden) {
                          <span class="rank">{{ getPieceName(board[row][col]?.rank) }}</span>
                          <span class="rank-number">{{ board[row][col]?.rank }}</span>
                        } @else {
                          <span class="hidden-piece">?</span>
                        }
                      </div>
                    }
                  }
                </div>
              }
            </div>
          }
        </div>
      </div>

      @if (lastMoveResult) {
        <div class="move-result" [class]="lastMoveResult.type">
          <h3>{{ getMoveResultTitle(lastMoveResult) }}</h3>
          <p>{{ getMoveResultDescription(lastMoveResult) }}</p>
          <button (click)="lastMoveResult = null">Dismiss</button>
        </div>
      }

      @if (game?.status === 'in_progress') {
        <div class="game-controls">
          <button class="forfeit-btn" (click)="forfeit()">Forfeit Game</button>
        </div>
      }

      @if (message) {
        <div class="message" [class.error]="isError">{{ message }}</div>
      }
    </div>
  `,
  styles: [`
    .game-container {
      max-width: 900px;
      margin: 0 auto;
      padding: 20px;
    }

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 10px;
    }

    .back-btn {
      padding: 8px 16px;
      background: transparent;
      border: 1px solid #e94560;
      color: #e94560;
      border-radius: 5px;
      cursor: pointer;
    }

    h1 {
      color: #e94560;
      margin: 0;
    }

    .game-info {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .status {
      padding: 4px 12px;
      border-radius: 4px;
      font-size: 14px;
      text-transform: uppercase;
    }

    .status.setup {
      background: #6bcb77;
      color: #000;
    }

    .status.in_progress {
      background: #4d96ff;
      color: #fff;
    }

    .status.finished {
      background: #888;
      color: #fff;
    }

    .turn {
      color: #ccc;
    }

    .turn.my-turn {
      color: #6bcb77;
      font-weight: bold;
    }

    .winner {
      color: #ffd93d;
      font-weight: bold;
    }

    .setup-phase {
      text-align: center;
      margin-bottom: 20px;
      padding: 20px;
      background: #1a1a2e;
      border-radius: 10px;
    }

    .setup-phase h2 {
      color: #fff;
      margin-bottom: 10px;
    }

    .setup-phase p {
      color: #ccc;
      margin-bottom: 15px;
    }

    .submit-setup {
      padding: 12px 24px;
      background: #6bcb77;
      color: #000;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      cursor: pointer;
      margin-top: 15px;
    }

    .submit-setup:disabled {
      background: #666;
      cursor: not-allowed;
    }

    .board-container {
      display: flex;
      justify-content: center;
      margin: 20px 0;
    }

    .board {
      display: flex;
      flex-direction: column;
      background: #2d4059;
      padding: 10px;
      border-radius: 10px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    .row {
      display: flex;
    }

    .cell {
      width: 70px;
      height: 70px;
      background: #3e5f8a;
      border: 1px solid #2d4059;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s;
      position: relative;
    }

    .cell:hover {
      background: #4a6fa5;
    }

    .cell.lake {
      background: #1e3a5f;
      cursor: not-allowed;
    }

    .cell.lake::after {
      content: '~';
      color: #4a90a4;
      font-size: 24px;
    }

    .cell.valid-move {
      background: rgba(107, 203, 119, 0.4);
    }

    .cell.valid-move:hover {
      background: rgba(107, 203, 119, 0.6);
    }

    .cell.selected {
      background: #ffd93d;
    }

    .cell.setup-zone {
      background: rgba(77, 150, 255, 0.2);
    }

    .cell.last-move {
      box-shadow: inset 0 0 0 3px #ffd93d;
    }

    .piece {
      width: 60px;
      height: 60px;
      border-radius: 8px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      transition: transform 0.2s;
    }

    .piece.red {
      background: linear-gradient(145deg, #e94560, #c73e54);
      color: #fff;
    }

    .piece.blue {
      background: linear-gradient(145deg, #4d96ff, #3a7bd5);
      color: #fff;
    }

    .piece.hidden {
      background: linear-gradient(145deg, #555, #444);
    }

    .piece.revealed {
      box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
    }

    .piece:hover {
      transform: scale(1.05);
    }

    .rank {
      font-size: 10px;
      text-transform: uppercase;
    }

    .rank-number {
      font-size: 18px;
    }

    .hidden-piece {
      font-size: 24px;
    }

    .move-result {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      padding: 30px;
      background: #1a1a2e;
      border-radius: 10px;
      text-align: center;
      z-index: 1000;
      box-shadow: 0 10px 50px rgba(0, 0, 0, 0.8);
    }

    .move-result.win {
      border: 2px solid #6bcb77;
    }

    .move-result.lose {
      border: 2px solid #e94560;
    }

    .move-result.draw {
      border: 2px solid #ffd93d;
    }

    .move-result.move {
      border: 2px solid #4d96ff;
    }

    .move-result h3 {
      color: #fff;
      margin-bottom: 10px;
    }

    .move-result p {
      color: #ccc;
      margin-bottom: 20px;
    }

    .move-result button {
      padding: 10px 20px;
      background: #e94560;
      color: #fff;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }

    .game-controls {
      text-align: center;
      margin-top: 20px;
    }

    .forfeit-btn {
      padding: 10px 20px;
      background: transparent;
      border: 1px solid #e94560;
      color: #e94560;
      border-radius: 5px;
      cursor: pointer;
    }

    .forfeit-btn:hover {
      background: #e94560;
      color: #fff;
    }

    .message {
      position: fixed;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      padding: 15px 30px;
      background: #1a1a2e;
      color: #fff;
      border-radius: 5px;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.5);
    }

    .message.error {
      background: #e94560;
    }

    @media (max-width: 768px) {
      .cell {
        width: 35px;
        height: 35px;
      }

      .piece {
        width: 30px;
        height: 30px;
      }

      .rank {
        font-size: 6px;
      }

      .rank-number {
        font-size: 10px;
      }
    }
  `]
})
export class GameBoardComponent implements OnInit, OnDestroy {
  gameId!: number;
  game: Game | null = null;
  board: (Piece | null)[][] = [];
  playerColor: PlayerColor = 'red';
  isMyTurn = false;

  // Setup phase
  setupComplete = false;
  selectedSetupPiece: PieceRank | null = null;
  placedPieces: SetupPiece[] = [];
  availablePieces: Map<PieceRank, number> = new Map();

  // Game phase
  selectedPiece: { row: number; col: number } | null = null;
  validMoves: Move[] = [];
  lastMove: { from: { row: number; col: number }; to: { row: number; col: number } } | null = null;
  lastMoveResult: MoveResult | null = null;

  message = '';
  isError = false;

  private subscriptions: Subscription[] = [];

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private gameService: GameService,
    private wsService: WebSocketService,
    private authService: AuthService
  ) {}

  ngOnInit(): void {
    this.gameId = +this.route.snapshot.paramMap.get('id')!;
    this.initAvailablePieces();
    this.loadGame();
    this.setupWebSocket();
  }

  ngOnDestroy(): void {
    this.subscriptions.forEach(sub => sub.unsubscribe());
    this.wsService.unsubscribeFromGame(this.gameId);
  }

  private initAvailablePieces(): void {
    Object.values(PieceRank)
      .filter(v => typeof v === 'number')
      .forEach(rank => {
        this.availablePieces.set(rank as PieceRank, PieceCounts[rank as PieceRank]);
      });
  }

  private loadGame(): void {
    this.gameService.getGame(this.gameId).subscribe({
      next: (response) => {
        this.game = response.game;
        this.board = response.board;
        this.playerColor = response.player_color;
        this.isMyTurn = response.is_my_turn;

        if (this.playerColor === 'red') {
          this.setupComplete = this.game.red_setup_complete;
        } else {
          this.setupComplete = this.game.blue_setup_complete;
        }
      },
      error: (err) => {
        this.showMessage('Failed to load game', true);
      }
    });
  }

  private setupWebSocket(): void {
    this.wsService.subscribeToGame(this.gameId);
    this.subscriptions.push(
      this.wsService.gameEvents$.subscribe(event => {
        switch (event.event) {
          case 'game.started':
          case 'game.updated':
          case 'setup.complete':
            this.loadGame();
            break;
          case 'move.made':
            this.loadGame();
            break;
        }
      })
    );
  }

  isLake(row: number, col: number): boolean {
    return isLakePosition(row, col);
  }

  isSetupZone(row: number, col: number): boolean {
    if (this.game?.status !== 'setup' || this.setupComplete) return false;

    if (this.playerColor === 'red') {
      return row >= 6 && row <= 9;
    } else {
      return row >= 0 && row <= 3;
    }
  }

  selectSetupPiece(rank: PieceRank): void {
    this.selectedSetupPiece = rank;
  }

  onCellClick(row: number, col: number): void {
    if (this.isLake(row, col)) return;

    // Setup phase
    if (this.game?.status === 'setup' && !this.setupComplete) {
      this.handleSetupClick(row, col);
      return;
    }

    // Game phase
    if (this.game?.status === 'in_progress' && this.isMyTurn) {
      this.handleGameClick(row, col);
    }
  }

  private handleSetupClick(row: number, col: number): void {
    if (!this.isSetupZone(row, col)) {
      this.showMessage('Place pieces in your setup zone', true);
      return;
    }

    // Check if there's already a piece here
    const existingIndex = this.placedPieces.findIndex(p => p.row === row && p.col === col);

    if (existingIndex >= 0) {
      // Remove the piece
      const removed = this.placedPieces.splice(existingIndex, 1)[0];
      this.availablePieces.set(removed.rank, (this.availablePieces.get(removed.rank) || 0) + 1);
      this.updateBoardForSetup();
      return;
    }

    if (this.selectedSetupPiece === null) {
      this.showMessage('Select a piece first', true);
      return;
    }

    const available = this.availablePieces.get(this.selectedSetupPiece) || 0;
    if (available <= 0) {
      this.showMessage('No more pieces of this type available', true);
      return;
    }

    // Place the piece
    this.placedPieces.push({ row, col, rank: this.selectedSetupPiece });
    this.availablePieces.set(this.selectedSetupPiece, available - 1);
    this.updateBoardForSetup();

    // Auto-select next available piece
    if (this.availablePieces.get(this.selectedSetupPiece) === 0) {
      this.selectedSetupPiece = null;
      for (const [rank, count] of this.availablePieces) {
        if (count > 0) {
          this.selectedSetupPiece = rank;
          break;
        }
      }
    }
  }

  private updateBoardForSetup(): void {
    // Create empty board
    this.board = Array(10).fill(null).map(() => Array(10).fill(null));

    // Mark lakes using shared constant
    LAKE_POSITIONS.forEach(([row, col]) => {
      this.board[row][col] = { type: 'lake' };
    });

    // Place pieces
    this.placedPieces.forEach(piece => {
      this.board[piece.row][piece.col] = {
        rank: piece.rank,
        color: this.playerColor,
        revealed: false
      };
    });
  }

  isSetupValid(): boolean {
    return this.placedPieces.length === 40;
  }

  submitSetup(): void {
    if (!this.isSetupValid()) return;

    this.gameService.submitSetup(this.gameId, this.placedPieces).subscribe({
      next: (response) => {
        this.game = response.game;
        this.board = response.board;
        this.setupComplete = true;
        this.showMessage('Setup complete!', false);
      },
      error: (err) => {
        this.showMessage('Failed to submit setup', true);
      }
    });
  }

  private handleGameClick(row: number, col: number): void {
    const piece = this.board[row]?.[col];

    // If we have a selected piece and this is a valid move target
    if (this.selectedPiece && this.isValidMoveTarget(row, col)) {
      this.makeMove(this.selectedPiece.row, this.selectedPiece.col, row, col);
      return;
    }

    // If clicking on own piece, select it
    if (piece && piece.color === this.playerColor && !piece.hidden) {
      this.selectedPiece = { row, col };
      this.loadValidMoves(row, col);
      return;
    }

    // Deselect
    this.selectedPiece = null;
    this.validMoves = [];
  }

  private loadValidMoves(row: number, col: number): void {
    this.gameService.getValidMoves(this.gameId, row, col).subscribe({
      next: (response) => {
        this.validMoves = response.moves;
      },
      error: () => {
        this.validMoves = [];
      }
    });
  }

  isValidMoveTarget(row: number, col: number): boolean {
    return this.validMoves.some(m => m.to.row === row && m.to.col === col);
  }

  isSelected(row: number, col: number): boolean {
    return this.selectedPiece?.row === row && this.selectedPiece?.col === col;
  }

  isLastMove(row: number, col: number): boolean {
    if (!this.lastMove) return false;
    return (this.lastMove.from.row === row && this.lastMove.from.col === col) ||
           (this.lastMove.to.row === row && this.lastMove.to.col === col);
  }

  private makeMove(fromRow: number, fromCol: number, toRow: number, toCol: number): void {
    this.gameService.makeMove(this.gameId, fromRow, fromCol, toRow, toCol).subscribe({
      next: (response) => {
        this.game = response.game;
        this.board = response.board;
        this.isMyTurn = this.game.current_turn === this.playerColor;
        this.selectedPiece = null;
        this.validMoves = [];
        this.lastMove = { from: { row: fromRow, col: fromCol }, to: { row: toRow, col: toCol } };

        if (response.result.type !== 'move') {
          this.lastMoveResult = response.result;
        }

        // Show AI move result if applicable
        if (response.ai_result && response.ai_result.type !== 'move') {
          setTimeout(() => {
            this.lastMoveResult = response.ai_result!;
          }, 1000);
        }
      },
      error: (err) => {
        this.showMessage('Invalid move', true);
      }
    });
  }

  getPieceName(rank: PieceRank | undefined): string {
    if (rank === undefined) return '';
    return PieceNames[rank] || '';
  }

  getMoveResultTitle(result: MoveResult): string {
    switch (result.type) {
      case 'win': return 'Attack Successful!';
      case 'lose': return 'Attack Failed!';
      case 'draw': return 'Draw - Both Eliminated!';
      default: return 'Move Complete';
    }
  }

  getMoveResultDescription(result: MoveResult): string {
    const attackerName = result.attacker?.rank !== undefined ? PieceNames[result.attacker.rank] : 'Unknown';
    const defenderName = result.defender?.rank !== undefined ? PieceNames[result.defender.rank] : 'Unknown';

    switch (result.type) {
      case 'win':
        return `Your ${attackerName} defeated the enemy ${defenderName}!`;
      case 'lose':
        return `Your ${attackerName} was defeated by the enemy ${defenderName}!`;
      case 'draw':
        return `Both ${attackerName}s were eliminated!`;
      default:
        return 'Piece moved successfully';
    }
  }

  formatStatus(status: string): string {
    return status.replace('_', ' ');
  }

  forfeit(): void {
    if (confirm('Are you sure you want to forfeit?')) {
      this.gameService.forfeitGame(this.gameId).subscribe({
        next: () => {
          this.loadGame();
        },
        error: () => {
          this.showMessage('Failed to forfeit', true);
        }
      });
    }
  }

  goBack(): void {
    this.router.navigate(['/']);
  }

  private showMessage(text: string, isError: boolean): void {
    this.message = text;
    this.isError = isError;
    setTimeout(() => {
      this.message = '';
    }, 3000);
  }
}
