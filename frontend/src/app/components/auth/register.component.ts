import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="auth-container">
      <h1>Fields of Deception</h1>
      <h2>Register</h2>

      <form (ngSubmit)="onSubmit()" #registerForm="ngForm">
        <div class="form-group">
          <label for="name">Name</label>
          <input
            type="text"
            id="name"
            name="name"
            [(ngModel)]="user.name"
            required
            minlength="2"
          />
        </div>

        <div class="form-group">
          <label for="email">Email</label>
          <input
            type="email"
            id="email"
            name="email"
            [(ngModel)]="user.email"
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
            [(ngModel)]="user.password"
            required
            minlength="8"
          />
        </div>

        <div class="form-group">
          <label for="password_confirmation">Confirm Password</label>
          <input
            type="password"
            id="password_confirmation"
            name="password_confirmation"
            [(ngModel)]="user.password_confirmation"
            required
          />
        </div>

        @if (error) {
          <div class="error">{{ error }}</div>
        }

        <button type="submit" [disabled]="loading || !registerForm.valid">
          {{ loading ? 'Registering...' : 'Register' }}
        </button>
      </form>

      <p>Already have an account? <a routerLink="/login">Login</a></p>
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
export class RegisterComponent {
  user = {
    name: '',
    email: '',
    password: '',
    password_confirmation: ''
  };
  loading = false;
  error = '';

  constructor(
    private authService: AuthService,
    private router: Router
  ) {}

  onSubmit(): void {
    if (this.user.password !== this.user.password_confirmation) {
      this.error = 'Passwords do not match';
      return;
    }

    this.loading = true;
    this.error = '';

    this.authService.register(this.user).subscribe({
      next: () => {
        this.router.navigate(['/']);
      },
      error: (err) => {
        this.loading = false;
        this.error = err.error?.message || 'Registration failed. Please try again.';
      }
    });
  }
}
