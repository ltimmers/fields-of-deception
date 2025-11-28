import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PieceRank, PieceNames } from '../../models/game.model';

@Component({
  selector: 'app-piece-selector',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="piece-selector">
      <h3>Available Pieces</h3>
      <div class="pieces-grid">
        @for (piece of getPieces(); track piece.rank) {
          <div
            class="piece-option"
            [class.selected]="selectedPiece === piece.rank"
            [class.disabled]="piece.count === 0"
            (click)="selectPiece(piece.rank)"
          >
            <div class="piece-icon">{{ piece.rank }}</div>
            <div class="piece-name">{{ piece.name }}</div>
            <div class="piece-count">x{{ piece.count }}</div>
          </div>
        }
      </div>
    </div>
  `,
  styles: [`
    .piece-selector {
      margin: 20px 0;
    }

    h3 {
      color: #fff;
      margin-bottom: 15px;
    }

    .pieces-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      justify-content: center;
    }

    .piece-option {
      width: 80px;
      padding: 10px;
      background: #16213e;
      border: 2px solid transparent;
      border-radius: 8px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s;
    }

    .piece-option:hover:not(.disabled) {
      border-color: #4d96ff;
    }

    .piece-option.selected {
      border-color: #6bcb77;
      background: rgba(107, 203, 119, 0.2);
    }

    .piece-option.disabled {
      opacity: 0.3;
      cursor: not-allowed;
    }

    .piece-icon {
      font-size: 24px;
      font-weight: bold;
      color: #e94560;
      margin-bottom: 5px;
    }

    .piece-name {
      font-size: 11px;
      color: #ccc;
      margin-bottom: 3px;
    }

    .piece-count {
      font-size: 12px;
      color: #6bcb77;
      font-weight: bold;
    }
  `]
})
export class PieceSelectorComponent {
  @Input() availablePieces: Map<PieceRank, number> = new Map();
  @Input() selectedPiece: PieceRank | null = null;
  @Output() pieceSelected = new EventEmitter<PieceRank>();

  getPieces(): { rank: PieceRank; name: string; count: number }[] {
    const pieces: { rank: PieceRank; name: string; count: number }[] = [];

    // Order: Marshal, General, Colonel, Major, Captain, Lieutenant, Sergeant, Miner, Scout, Spy, Bomb, Flag
    const order = [
      PieceRank.MARSHAL,
      PieceRank.GENERAL,
      PieceRank.COLONEL,
      PieceRank.MAJOR,
      PieceRank.CAPTAIN,
      PieceRank.LIEUTENANT,
      PieceRank.SERGEANT,
      PieceRank.MINER,
      PieceRank.SCOUT,
      PieceRank.SPY,
      PieceRank.BOMB,
      PieceRank.FLAG,
    ];

    order.forEach(rank => {
      pieces.push({
        rank,
        name: PieceNames[rank],
        count: this.availablePieces.get(rank) || 0
      });
    });

    return pieces;
  }

  selectPiece(rank: PieceRank): void {
    if ((this.availablePieces.get(rank) || 0) > 0) {
      this.pieceSelected.emit(rank);
    }
  }
}
