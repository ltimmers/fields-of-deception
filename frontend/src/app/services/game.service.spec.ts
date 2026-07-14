import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { GameService } from './game.service';
import { environment } from '../../environments/environment';
import { GameResponse } from '../models/game.model';

describe('GameService', () => {
  let service: GameService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    service = TestBed.inject(GameService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  it('accepts game responses without raw board_state', () => {
    const response: GameResponse = {
      game: {
        id: 1,
        player_red_id: 1,
        player_blue_id: null,
        status: 'in_progress',
        current_turn: 'red',
        winner: null,
        is_vs_ai: true,
        ai_difficulty: 'medium',
        use_llm: false,
        red_setup_complete: true,
        blue_setup_complete: true,
        created_at: '2026-01-01T00:00:00.000000Z',
        updated_at: '2026-01-01T00:00:00.000000Z',
      },
      board: [[]],
      player_color: 'red',
      is_my_turn: true,
    };

    service.getGame(1).subscribe(result => {
      expect(result.game.board_state).toBeUndefined();
      expect(result.board).toEqual([[]]);
    });

    const req = httpMock.expectOne(`${environment.apiUrl}/games/1`);
    req.flush(response);
  });
});
