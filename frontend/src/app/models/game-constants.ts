/**
 * Game constants shared across the application
 */

export const BOARD_SIZE = 10;

/**
 * Lake positions on the board (row, col)
 * These are impassable squares in the center of the board
 */
export const LAKE_POSITIONS: [number, number][] = [
  [4, 2], [4, 3], [5, 2], [5, 3],
  [4, 6], [4, 7], [5, 6], [5, 7],
];

/**
 * Check if a position is a lake
 */
export function isLakePosition(row: number, col: number): boolean {
  return LAKE_POSITIONS.some(([r, c]) => r === row && c === col);
}

import { PieceRank } from './game.model';

/**
 * Piece ordering for display (highest to lowest value, then special pieces)
 */
export const PIECE_DISPLAY_ORDER: PieceRank[] = [
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
