import { Injectable } from '@angular/core';
import { CanActivate, Router } from '@angular/router';
import { AdminApiService } from '../services/admin-api.service';

@Injectable({ providedIn: 'root' })
export class AdminOnlyGuard implements CanActivate {
  constructor(private adminApi: AdminApiService, private router: Router) {}

  canActivate(): boolean {
    if (this.adminApi.isAdmin()) return true;
    if (this.adminApi.isSpecialist()) {
      this.router.navigate(['/admin/specialist/dashboard']);
      return false;
    }
    this.router.navigate(['/admin/login']);
    return false;
  }
}
