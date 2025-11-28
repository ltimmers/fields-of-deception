import { Injectable, NgZone } from '@angular/core';
import { Subject } from 'rxjs';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { environment } from '../../environments/environment';
import { AuthService } from './auth.service';

// Make Pusher available globally for Laravel Echo
(window as any).Pusher = Pusher;

@Injectable({
  providedIn: 'root'
})
export class WebSocketService {
  private echo: Echo<'reverb'> | null = null;
  private gameEvents = new Subject<{ event: string; data: any }>();

  gameEvents$ = this.gameEvents.asObservable();

  constructor(
    private authService: AuthService,
    private ngZone: NgZone
  ) {}

  connect(): void {
    if (this.echo) {
      return;
    }

    const token = this.authService.getToken();

    this.echo = new Echo({
      broadcaster: 'reverb',
      key: environment.wsKey,
      wsHost: environment.wsHost,
      wsPort: environment.wsPort,
      wssPort: environment.wsPort,
      forceTLS: false,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: `${environment.apiUrl.replace('/api', '')}/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      },
    });
  }

  disconnect(): void {
    if (this.echo) {
      this.echo.disconnect();
      this.echo = null;
    }
  }

  subscribeToGame(gameId: number): void {
    if (!this.echo) {
      this.connect();
    }

    this.echo!.private(`game.${gameId}`)
      .listen('.game.started', (data: any) => {
        this.ngZone.run(() => {
          this.gameEvents.next({ event: 'game.started', data });
        });
      })
      .listen('.game.updated', (data: any) => {
        this.ngZone.run(() => {
          this.gameEvents.next({ event: 'game.updated', data });
        });
      })
      .listen('.move.made', (data: any) => {
        this.ngZone.run(() => {
          this.gameEvents.next({ event: 'move.made', data });
        });
      })
      .listen('.setup.complete', (data: any) => {
        this.ngZone.run(() => {
          this.gameEvents.next({ event: 'setup.complete', data });
        });
      });
  }

  unsubscribeFromGame(gameId: number): void {
    if (this.echo) {
      this.echo.leave(`game.${gameId}`);
    }
  }

  subscribeToGames(): void {
    if (!this.echo) {
      this.connect();
    }

    this.echo!.channel('games')
      .listen('.game.created', (data: any) => {
        this.ngZone.run(() => {
          this.gameEvents.next({ event: 'game.created', data });
        });
      });
  }

  unsubscribeFromGames(): void {
    if (this.echo) {
      this.echo.leave('games');
    }
  }
}
