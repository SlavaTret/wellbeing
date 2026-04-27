import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from '../../../services/api/api.service';
import { UserService } from '../../../services/user/user.service';

@Component({
  selector: 'app-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.css']
})
export class LoginComponent {
  email = '';
  password = '';
  showPassword = false;
  loading = false;
  error = '';

  constructor(
    private api: ApiService,
    private userService: UserService,
    private router: Router
  ) {}

  submit(): void {
    if (!this.email || !this.password) {
      this.error = 'Заповніть усі поля';
      return;
    }
    this.loading = true;
    this.error = '';

    this.api.login(this.email, this.password).subscribe({
      next: () => {
        // Load profile before navigating so sidebar has data immediately
        this.userService.load().subscribe({
          next: () => this.router.navigate(['/dashboard']),
          error: () => this.router.navigate(['/dashboard'])
        });
      },
      error: (err) => {
        this.loading = false;
        this.error = err?.error?.error || err?.error?.message || 'Невірний email або пароль';
      }
    });
  }
}
