import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { AdminApiService } from '../../services/admin-api.service';

@Component({
  selector: 'app-admin-login',
  templateUrl: './admin-login.component.html',
  styleUrls: ['./admin-login.component.css']
})
export class AdminLoginComponent {
  email = '';
  password = '';
  loading = false;
  error = '';
  showPassword = false;

  constructor(private adminApi: AdminApiService, private router: Router) {
    if (this.adminApi.isAdminLoggedIn()) {
      this.router.navigate(['/admin/dashboard']);
    }
  }

  submit(): void {
    if (!this.email || !this.password) { this.error = 'Введіть email та пароль'; return; }
    this.loading = true;
    this.error = '';
    this.adminApi.login(this.email, this.password).subscribe({
      next: (res: any) => {
        if (!res?.user?.is_admin) {
          this.loading = false;
          this.error = 'Доступ заборонено. У вас немає прав адміністратора.';
          return;
        }
        this.adminApi.setAdminSession(res.access_token, res.user);
        this.router.navigate(['/admin/dashboard']);
      },
      error: (err: any) => {
        this.loading = false;
        const raw = err?.error?.message || err?.error?.error || '';
        if (err?.status === 401 || raw.toLowerCase().includes('invalid') || raw.toLowerCase().includes('password')) {
          this.error = 'Невірний email або пароль';
        } else if (err?.status === 403) {
          this.error = 'Доступ заборонено';
        } else if (raw) {
          this.error = 'Помилка сервера. Спробуйте ще раз.';
        } else {
          this.error = 'Невірний email або пароль';
        }
      }
    });
  }
}
