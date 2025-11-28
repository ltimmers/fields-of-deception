import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { Game, GameResponse, MoveResponse, SetupPiece, AIDifficulty, Move } from '../models/game.model';

@Injectable({
  providedIn: 'root'
})
export class GameService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getGames(): Observable<Game[]> {
    return this.http.get<Game[]>(`${this.apiUrl}/games`);
  }

  getOpenGames(): Observable<Game[]> {
    return this.http.get<Game[]>(`${this.apiUrl}/games/open`);
  }

  createGame(vsAi: boolean = false, aiDifficulty: AIDifficulty = 'medium'): Observable<Game> {
    return this.http.post<Game>(`${this.apiUrl}/games`, {
      vs_ai: vsAi,
      ai_difficulty: aiDifficulty
    });
  }

  joinGame(gameId: number): Observable<Game> {
    return this.http.post<Game>(`${this.apiUrl}/games/${gameId}/join`, {});
  }

  getGame(gameId: number): Observable<GameResponse> {
    return this.http.get<GameResponse>(`${this.apiUrl}/games/${gameId}`);
  }

  submitSetup(gameId: number, pieces: SetupPiece[]): Observable<GameResponse> {
    return this.http.post<GameResponse>(`${this.apiUrl}/games/${gameId}/setup`, { pieces });
  }

  makeMove(gameId: number, fromRow: number, fromCol: number, toRow: number, toCol: number): Observable<MoveResponse> {
    return this.http.post<MoveResponse>(`${this.apiUrl}/games/${gameId}/move`, {
      from_row: fromRow,
      from_col: fromCol,
      to_row: toRow,
      to_col: toCol
    });
  }

  getValidMoves(gameId: number, row: number, col: number): Observable<{ moves: Move[] }> {
    return this.http.post<{ moves: Move[] }>(`${this.apiUrl}/games/${gameId}/valid-moves`, { row, col });
  }

  forfeitGame(gameId: number): Observable<Game> {
    return this.http.post<Game>(`${this.apiUrl}/games/${gameId}/forfeit`, {});
  }
}
