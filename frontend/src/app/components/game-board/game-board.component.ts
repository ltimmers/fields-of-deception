import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { Subscription } from 'rxjs';
import { GameService } from '../../services/game.service';
import { WebSocketService } from '../../services/websocket.service';
import {
  Game,
  Piece,
  PieceRank,
  PieceNames,
  PieceCounts,
  PlayerColor,
  SetupPiece,
  Move,
  MoveResult,
  MoveResponse
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

      <div class="game-layout">
        <div class="move-console">
          <h3>Battle Log</h3>
          <div class="console-content" #consoleContent>
            @if (moveLog.length === 0) {
              <div class="console-empty">No moves yet...</div>
            }
            @for (entry of moveLog; track $index) {
              <div class="console-entry" [class]="entry.type" [class.red]="entry.color === 'red'" [class.blue]="entry.color === 'blue'">
                <span class="entry-color">{{ entry.color === 'red' ? '🔴' : '🔵' }}</span>
                <span class="entry-message">{{ entry.message }}</span>
              </div>
            }
          </div>
        </div>

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
                    [class.animating-from]="isAnimatingFrom(row, col)"
                    [class.animating-to]="isAnimatingTo(row, col)"
                    [class.animating-red]="isAnimatingTo(row, col) && getAnimatingColor() === 'red'"
                    [class.animating-blue]="isAnimatingTo(row, col) && getAnimatingColor() === 'blue'"
                    [class.battle-attacker]="isBattleAttacker(row, col)"
                    [class.battle-defender]="isBattleDefender(row, col)"
                    (click)="onCellClick(row, col)"
                  >
                    @if (getBattlePiece(row, col); as battlePiece) {
                      <div
                        class="piece battle-piece-reveal"
                        [class.red]="battlePiece.color === 'red'"
                        [class.blue]="battlePiece.color === 'blue'"
                      >
                        <span class="rank">{{ getPieceName(battlePiece.rank) }}</span>
                        <span class="rank-number">{{ battlePiece.rank }}</span>
                      </div>
                    } @else if (board && board[row] && board[row][col]) {
                      @if (board[row][col]?.type !== 'lake') {
                        <div
                          class="piece"
                          [class.red]="board[row][col]?.color === 'red'"
                          [class.blue]="board[row][col]?.color === 'blue'"
                          [class.hidden]="board[row][col]?.hidden"
                          [class.revealed]="board[row][col]?.revealed"
                          [class.piece-animating]="isAnimatingTo(row, col)"
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

          @if (animatingMove) {
            <div class="move-indicator" [class]="animatingMove.color">
              {{ animatingMove.color === 'red' ? 'Red' : 'Blue' }} is moving...
            </div>
          }

          @if (battlePreview) {
            <div class="battle-indicator">
              ⚔️ Battle! ⚔️
            </div>
          }

          @if (isThinking) {
            <div class="thinking-popup">
              <span>AI is thinking</span><span class="thinking-dots"></span>
            </div>
          }

        </div>
      </div>

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
      max-width: 1000px;
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

    .game-layout {
      display: flex;
      justify-content: center;
      gap: 20px;
      margin: 20px 0;
    }

    .move-console {
      width: 560px;
      background: #1a1a2e;
      border-radius: 10px;
      padding: 15px;
      display: flex;
      flex-direction: column;
      max-height: 720px;
    }

    .move-console h3 {
      color: #fff;
      margin: 0 0 15px 0;
      padding-bottom: 10px;
      border-bottom: 1px solid #333;
      font-size: 16px;
    }

    .console-content {
      flex: 1;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .console-empty {
      color: #666;
      font-style: italic;
      text-align: center;
      padding: 20px;
    }

    .console-entry {
      padding: 10px 12px;
      border-radius: 6px;
      background: #252540;
      display: flex;
      align-items: flex-start;
      gap: 8px;
      font-size: 13px;
      line-height: 1.4;
    }

    .console-entry.win {
      background: rgba(107, 203, 119, 0.15);
      border-left: 3px solid #6bcb77;
    }

    .console-entry.lose {
      background: rgba(233, 69, 96, 0.15);
      border-left: 3px solid #e94560;
    }

    .console-entry.draw {
      background: rgba(255, 217, 61, 0.15);
      border-left: 3px solid #ffd93d;
    }

    .console-entry.move {
      background: rgba(77, 150, 255, 0.1);
      border-left: 3px solid #4d96ff;
    }

    .entry-color {
      flex-shrink: 0;
    }

    .entry-message {
      color: #ccc;
      white-space: pre-line;
    }

    .console-entry.win .entry-message {
      color: #6bcb77;
    }

    .console-entry.lose .entry-message {
      color: #e94560;
    }

    .console-entry.draw .entry-message {
      color: #ffd93d;
    }

    .board-container {
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
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

    .cell.animating-from {
      background: rgba(255, 217, 61, 0.3);
      animation: pulse-from 0.8s ease-in-out;
    }

    .cell.animating-to {
      animation: pulse-to 0.8s ease-in-out;
    }

    .cell.animating-red {
      box-shadow: inset 0 0 0 4px #e94560, 0 0 20px rgba(233, 69, 96, 0.6);
    }

    .cell.animating-blue {
      box-shadow: inset 0 0 0 4px #4d96ff, 0 0 20px rgba(77, 150, 255, 0.6);
    }

    @keyframes pulse-from {
      0% { background: rgba(255, 217, 61, 0.5); }
      50% { background: rgba(255, 217, 61, 0.2); }
      100% { background: rgba(255, 217, 61, 0.3); }
    }

    @keyframes pulse-to {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
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

    .piece.piece-animating {
      animation: piece-appear 0.5s ease-out;
    }

    @keyframes piece-appear {
      0% {
        transform: scale(0.5);
        opacity: 0.5;
      }
      50% {
        transform: scale(1.1);
        opacity: 1;
      }
      100% {
        transform: scale(1);
        opacity: 1;
      }
    }

    .move-indicator {
      text-align: center;
      padding: 10px 20px;
      margin-top: 15px;
      border-radius: 8px;
      font-weight: bold;
      font-size: 16px;
      animation: indicator-pulse 1s ease-in-out infinite;
    }

    .move-indicator.red {
      background: rgba(233, 69, 96, 0.2);
      color: #e94560;
      border: 2px solid #e94560;
    }

    .move-indicator.blue {
      background: rgba(77, 150, 255, 0.2);
      color: #4d96ff;
      border: 2px solid #4d96ff;
    }

    @keyframes indicator-pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.6; }
    }

    .cell.battle-attacker {
      box-shadow: inset 0 0 0 4px #ffd93d, 0 0 25px rgba(255, 217, 61, 0.8);
      animation: battle-glow 1s ease-in-out infinite;
    }

    .cell.battle-defender {
      box-shadow: inset 0 0 0 4px #e94560, 0 0 25px rgba(233, 69, 96, 0.8);
      animation: battle-glow 1s ease-in-out infinite;
    }

    @keyframes battle-glow {
      0%, 100% { 
        filter: brightness(1);
      }
      50% { 
        filter: brightness(1.3);
      }
    }

    .piece.battle-piece-reveal {
      animation: piece-reveal 0.5s ease-out;
      box-shadow: 0 0 20px rgba(255, 255, 255, 0.5);
    }

    @keyframes piece-reveal {
      0% {
        transform: scale(0.8) rotateY(180deg);
        opacity: 0;
      }
      100% {
        transform: scale(1) rotateY(0deg);
        opacity: 1;
      }
    }

    .battle-indicator {
      text-align: center;
      padding: 12px 24px;
      margin-top: 15px;
      border-radius: 8px;
      font-weight: bold;
      font-size: 20px;
      background: rgba(255, 217, 61, 0.2);
      color: #ffd93d;
      border: 2px solid #ffd93d;
      animation: indicator-pulse 1s ease-in-out infinite;
    }

    .thinking-popup {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      display: flex;
      align-items: center;
      padding: 12px 20px;
      border-radius: 8px;
      background: #1a1a2e;
      color: #4d96ff;
      border: 2px solid #4d96ff;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
      font-weight: bold;
      font-size: 14px;
      z-index: 1000;
      white-space: nowrap;
    }

    .thinking-dots {
      display: inline-block;
      width: 24px;
      text-align: left;
    }

    .thinking-dots::after {
      content: '';
      animation: dots 1.5s steps(4, end) infinite;
    }

    @keyframes dots {
      0% { content: ''; }
      25% { content: '.'; }
      50% { content: '..'; }
      75% { content: '...'; }
      100% { content: ''; }
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

    @media (max-width: 1400px) {
      .game-layout {
        flex-direction: column-reverse;
        align-items: center;
      }

      .move-console {
        width: 100%;
        max-width: 720px;
        max-height: 200px;
      }
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

      .move-console {
        max-height: 150px;
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

  // Animation state
  animatingMove: { from: { row: number; col: number }; to: { row: number; col: number }; color: PlayerColor } | null = null;
  isProcessingMove = false;
  isThinking = false;

  // Battle preview state
  battlePreview: {
    attacker: { row: number; col: number; rank: PieceRank; color: PlayerColor };
    defender: { row: number; col: number; rank: PieceRank; color: PlayerColor };
  } | null = null;

  // Move log
  moveLog: { color: PlayerColor; type: 'move' | 'win' | 'lose' | 'draw'; message: string; timestamp: Date }[] = [];

  message = '';
  isError = false;

  private subscriptions: Subscription[] = [];

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private gameService: GameService,
    private wsService: WebSocketService,
    private cdr: ChangeDetectorRef
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
    if (this.isProcessingMove) return; // Prevent clicks during animation

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

  isAnimatingFrom(row: number, col: number): boolean {
    return this.animatingMove?.from.row === row && this.animatingMove?.from.col === col;
  }

  isAnimatingTo(row: number, col: number): boolean {
    return this.animatingMove?.to.row === row && this.animatingMove?.to.col === col;
  }

  getAnimatingColor(): PlayerColor | null {
    return this.animatingMove?.color || null;
  }

  isBattleAttacker(row: number, col: number): boolean {
    return this.battlePreview?.attacker.row === row && this.battlePreview?.attacker.col === col;
  }

  isBattleDefender(row: number, col: number): boolean {
    return this.battlePreview?.defender.row === row && this.battlePreview?.defender.col === col;
  }

  getBattlePiece(row: number, col: number): { rank: PieceRank; color: PlayerColor } | null {
    if (!this.battlePreview) return null;
    if (this.battlePreview.attacker.row === row && this.battlePreview.attacker.col === col) {
      return { rank: this.battlePreview.attacker.rank, color: this.battlePreview.attacker.color };
    }
    if (this.battlePreview.defender.row === row && this.battlePreview.defender.col === col) {
      return { rank: this.battlePreview.defender.rank, color: this.battlePreview.defender.color };
    }
    return null;
  }

  private makeMove(fromRow: number, fromCol: number, toRow: number, toCol: number): void {
    if (this.isProcessingMove) return;
    this.isProcessingMove = true;

    this.gameService.makeMove(this.gameId, fromRow, fromCol, toRow, toCol).subscribe({
      next: (response) => {
        this.selectedPiece = null;
        this.validMoves = [];

        const isBattle = response.result.type !== 'move';

        // Helper to process player move animation
        const processPlayerMove = () => {
          this.animatingMove = {
            from: { row: fromRow, col: fromCol },
            to: { row: toRow, col: toCol },
            color: this.playerColor
          };
          this.lastMove = { from: { row: fromRow, col: fromCol }, to: { row: toRow, col: toCol } };

          // Update board after player move
          this.board = response.board;
          this.game = response.game;

          // Log player's move result
          this.addMoveToLog(this.playerColor, response.result, { row: fromRow, col: fromCol }, { row: toRow, col: toCol });

          // After delay, clear player animation and check if AI move is needed
          setTimeout(() => {
            this.animatingMove = null;
            
            // If AI move is pending, show thinking and make separate API call
            if (response.ai_pending) {
              this.isThinking = true;
              this.cdr.detectChanges();
              // Small delay to ensure thinking indicator renders before API call
              setTimeout(() => {
                this.requestAiMove();
              }, 100);
            } else {
              // No AI move needed, update final state
              this.isMyTurn = this.game!.current_turn === this.playerColor;
              this.lastMove = null;
              this.isProcessingMove = false;
            }
          }, 800);
        };

        // If this is a battle, show preview first
        if (isBattle && response.result.attacker && response.result.defender) {
          this.battlePreview = {
            attacker: {
              row: fromRow,
              col: fromCol,
              rank: response.result.attacker.rank!,
              color: this.playerColor
            },
            defender: {
              row: toRow,
              col: toCol,
              rank: response.result.defender.rank!,
              color: this.playerColor === 'red' ? 'blue' : 'red'
            }
          };

          // Wait 2 seconds, then clear preview and process move
          setTimeout(() => {
            this.battlePreview = null;
            processPlayerMove();
          }, 2000);
        } else {
          // No battle, just process the move
          processPlayerMove();
        }
      },
      error: (err) => {
        this.isProcessingMove = false;
        this.showMessage('Invalid move', true);
      }
    });
  }

  private requestAiMove(): void {
    const startTime = Date.now();
    const minThinkingTime = 1000; // Show thinking for at least 1 second

    this.gameService.requestAiMove(this.gameId).subscribe({
      next: (response) => {
        const elapsed = Date.now() - startTime;
        const remainingDelay = Math.max(0, minThinkingTime - elapsed);
        
        // Ensure thinking shows for at least minThinkingTime
        setTimeout(() => {
          this.isThinking = false;
          this.processAiMove(response);
        }, remainingDelay);
      },
      error: (err) => {
        this.isThinking = false;
        this.isProcessingMove = false;
        this.showMessage('AI move failed', true);
        this.loadGame(); // Reload game state
      }
    });
  }

  private processAiMove(response: MoveResponse): void {
    if (response.ai_move && response.ai_result) {
      const isAiBattle = response.ai_result.type !== 'move';

      const processAiMoveAnimation = () => {
        this.animatingMove = {
          from: response.ai_move!.from,
          to: response.ai_move!.to,
          color: 'blue'
        };
        this.lastMove = { from: response.ai_move!.from, to: response.ai_move!.to };

        // Update to final board (after AI move)
        this.board = response.board;
        this.game = response.game;
        this.isMyTurn = this.game!.current_turn === this.playerColor;

        // Log AI's move result
        this.addMoveToLog('blue', response.ai_result!, response.ai_move!.from, response.ai_move!.to);

        // Clear AI animation after delay
        setTimeout(() => {
          this.animatingMove = null;
          this.lastMove = null;
          this.isProcessingMove = false;
        }, 800);
      };

      // If AI move is a battle, show preview first
      if (isAiBattle && response.ai_result.attacker && response.ai_result.defender) {
        const aiAttacker = response.ai_result.attacker;
        const aiDefender = response.ai_result.defender;
        this.battlePreview = {
          attacker: {
            row: response.ai_move!.from.row,
            col: response.ai_move!.from.col,
            rank: aiAttacker.rank!,
            color: 'blue'
          },
          defender: {
            row: response.ai_move!.to.row,
            col: response.ai_move!.to.col,
            rank: aiDefender.rank!,
            color: 'red'
          }
        };

        // Wait 2 seconds, then clear preview and process AI move
        setTimeout(() => {
          this.battlePreview = null;
          processAiMoveAnimation();
        }, 2000);
      } else {
        // No AI battle, just process the move
        processAiMoveAnimation();
      }
    } else {
      // No AI move data, just update final state
      this.board = response.board;
      this.game = response.game;
      this.isMyTurn = this.game!.current_turn === this.playerColor;
      this.lastMove = null;
      this.isProcessingMove = false;
    }
  }

  private addMoveToLog(color: PlayerColor, result: MoveResult, from: {row: number, col: number}, to: {row: number, col: number}): void {
    // Only reveal enemy piece ranks when there's actual combat (win/lose/draw)
    // For simple moves, never show the enemy's moving piece rank
    const isPlayerMove = color === this.playerColor;
    const isBattle = result.type === 'win' || result.type === 'lose' || result.type === 'draw';
    
    // Format coordinates as (row,col)
    const moveCoords = `(${from.row},${from.col})→(${to.row},${to.col})`;
    
    let attackerName: string;
    let defenderName: string;
    
    if (isPlayerMove) {
      // Player is attacking - always show player's piece rank
      attackerName = result.attacker?.rank !== undefined ? PieceNames[result.attacker.rank] : 'Piece';
      // Only show defender rank if there was a battle
      defenderName = (isBattle && result.defender?.rank !== undefined) 
        ? PieceNames[result.defender.rank] 
        : '???';
    } else {
      // AI is attacking - only show AI's piece rank if there was a battle
      attackerName = (isBattle && result.attacker?.rank !== undefined)
        ? PieceNames[result.attacker.rank]
        : '???';
      // Always show player's piece rank (defender)
      defenderName = result.defender?.rank !== undefined ? PieceNames[result.defender.rank] : '???';
    }

    let message: string;
    switch (result.type) {
      case 'win':
        message = `${attackerName} defeats ${defenderName}!\n${moveCoords}`;
        break;
      case 'lose':
        message = `${attackerName} defeated by ${defenderName}\n${moveCoords}`;
        break;
      case 'draw':
        message = `${attackerName} vs ${defenderName} - both eliminated!\n${moveCoords}`;
        break;
      default:
        message = `${attackerName} moves\n${moveCoords}`;
    }

    this.moveLog.unshift({
      color,
      type: result.type,
      message,
      timestamp: new Date()
    });
  }

  getPieceName(rank: PieceRank | undefined): string {
    if (rank === undefined) return '';
    return PieceNames[rank] || '';
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
