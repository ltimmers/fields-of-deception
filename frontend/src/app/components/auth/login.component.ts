import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="auth-container">
      <h1>Fields of Deception</h1>
      <h2>Login</h2>

      <form (ngSubmit)="onSubmit()" #loginForm="ngForm">
        <div class="form-group">
          <label for="email">Email</label>
          <input
            type="email"
            id="email"
            name="email"
            [(ngModel)]="credentials.email"
            required
            email
          />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input
            type="password"
            id="password"
            name="password"
            [(ngModel)]="credentials.password"
            required
          />
        </div>

        @if (error) {
          <div class="error">{{ error }}</div>
        }

        <button type="submit" [disabled]="loading || !loginForm.valid">
          {{ loading ? 'Logging in...' : 'Login' }}
        </button>
      </form>

      <p>Don't have an account? <a routerLink="/register">Register</a></p>
    </div>
  `,
  styles: [`
    .auth-container {
      max-width: 400px;
      margin: 50px auto;
      padding: 30px;
      background: #1a1a2e;
      border-radius: 10px;
      text-align: center;
    }

    h1 {
      color: #e94560;
      margin-bottom: 10px;
    }

    h2 {
      color: #fff;
      margin-bottom: 30px;
    }

    .form-group {
      margin-bottom: 20px;
      text-align: left;
    }

    label {
      display: block;
      color: #ccc;
      margin-bottom: 5px;
    }

    input {
      width: 100%;
      padding: 12px;
      border: 1px solid #333;
      border-radius: 5px;
      background: #16213e;
      color: #fff;
      font-size: 16px;
      box-sizing: border-box;
    }

    input:focus {
      outline: none;
      border-color: #e94560;
    }

    button {
      width: 100%;
      padding: 12px;
      background: #e94560;
      color: #fff;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      cursor: pointer;
      transition: background 0.3s;
    }

    button:hover:not(:disabled) {
      background: #c73e54;
    }

    button:disabled {
      background: #666;
      cursor: not-allowed;
    }

    .error {
      color: #ff6b6b;
      margin-bottom: 15px;
      padding: 10px;
      background: rgba(255, 107, 107, 0.1);
      border-radius: 5px;
    }

    p {
      margin-top: 20px;
      color: #ccc;
    }

    a {
      color: #e94560;
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
    }
  `]
})
export class LoginComponent {
  credentials = {
    email: '',
    password: ''
  };
  loading = false;
  error = '';

  constructor(
    private authService: AuthService,
    private router: Router
  ) {}

  onSubmit(): void {
    this.loading = true;
    this.error = '';

    this.authService.login(this.credentials).subscribe({
      next: () => {
        this.router.navigate(['/']);
      },
      error: (err) => {
        this.loading = false;
        this.error = err.error?.message || 'Login failed. Please try again.';
      }
    });
  }
}
