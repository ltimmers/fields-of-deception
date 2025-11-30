export type GameStatus = 'waiting' | 'setup' | 'in_progress' | 'finished' | 'abandoned';
export type PlayerColor = 'red' | 'blue';
export type AIDifficulty = 'easy' | 'medium' | 'hard';

export enum PieceRank {
  FLAG = 0,
  SPY = 1,
  SCOUT = 2,
  MINER = 3,
  SERGEANT = 4,
  LIEUTENANT = 5,
  CAPTAIN = 6,
  MAJOR = 7,
  COLONEL = 8,
  GENERAL = 9,
  MARSHAL = 10,
  BOMB = 11,
}

export const PieceNames: Record<PieceRank, string> = {
  [PieceRank.FLAG]: 'Flag',
  [PieceRank.SPY]: 'Spy',
  [PieceRank.SCOUT]: 'Scout',
  [PieceRank.MINER]: 'Miner',
  [PieceRank.SERGEANT]: 'Sergeant',
  [PieceRank.LIEUTENANT]: 'Lieutenant',
  [PieceRank.CAPTAIN]: 'Captain',
  [PieceRank.MAJOR]: 'Major',
  [PieceRank.COLONEL]: 'Colonel',
  [PieceRank.GENERAL]: 'General',
  [PieceRank.MARSHAL]: 'Marshal',
  [PieceRank.BOMB]: 'Bomb',
};

export const PieceCounts: Record<PieceRank, number> = {
  [PieceRank.FLAG]: 1,
  [PieceRank.SPY]: 1,
  [PieceRank.SCOUT]: 8,
  [PieceRank.MINER]: 5,
  [PieceRank.SERGEANT]: 4,
  [PieceRank.LIEUTENANT]: 4,
  [PieceRank.CAPTAIN]: 4,
  [PieceRank.MAJOR]: 3,
  [PieceRank.COLONEL]: 2,
  [PieceRank.GENERAL]: 1,
  [PieceRank.MARSHAL]: 1,
  [PieceRank.BOMB]: 6,
};

export interface Piece {
  rank?: PieceRank;
  color?: PlayerColor;
  revealed?: boolean;
  hidden?: boolean;
  type?: 'lake';
}

export interface Game {
  id: number;
  player_red_id: number;
  player_blue_id: number | null;
  status: GameStatus;
  current_turn: PlayerColor;
  winner: PlayerColor | null;
  board_state: (Piece | null)[][];
  is_vs_ai: boolean;
  ai_difficulty: AIDifficulty;
  red_setup_complete: boolean;
  blue_setup_complete: boolean;
  created_at: string;
  updated_at: string;
  player_red?: { id: number; name: string };
  player_blue?: { id: number; name: string };
}

export interface GameResponse {
  game: Game;
  board: (Piece | null)[][];
  player_color: PlayerColor;
  is_my_turn: boolean;
}

export interface MoveResult {
  type: 'move' | 'win' | 'lose' | 'draw';
  attacker: Piece;
  defender: Piece | null;
  captured: Piece | Piece[] | null;
  winner: PlayerColor | null;
}

export interface MoveResponse {
  game: Game;
  board: (Piece | null)[][];
  result: MoveResult;
  ai_move?: Move;
  ai_result?: MoveResult;
}

export interface Move {
  from: { row: number; col: number };
  to: { row: number; col: number };
  rank?: PieceRank;
}

export interface SetupPiece {
  row: number;
  col: number;
  rank: PieceRank;
}
