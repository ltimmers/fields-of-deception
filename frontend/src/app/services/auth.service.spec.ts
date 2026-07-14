import { HttpErrorResponse, provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { AuthService } from './auth.service';
import { environment } from '../../environments/environment';

describe('AuthService', () => {
  let service: AuthService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();

    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    service = TestBed.inject(AuthService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
    localStorage.clear();
  });

  it('clears local auth state when logout fails', () => {
    localStorage.setItem('auth_token', 'token');

    service.logout().subscribe({
      next: () => fail('Expected logout to fail'),
      error: (error: HttpErrorResponse) => {
        expect(error.status).toBe(500);
      },
    });

    const req = httpMock.expectOne(`${environment.apiUrl}/logout`);
    req.flush({ message: 'Server error' }, { status: 500, statusText: 'Server Error' });

    expect(service.getToken()).toBeNull();
    expect(service.getCurrentUser()).toBeNull();
  });
});
