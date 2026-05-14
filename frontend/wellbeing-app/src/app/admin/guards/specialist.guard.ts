import { Injectable } from '@angular/core';
import { CanActivate, Router } from '@angular/router';
import { AdminApiService } from '../services/admin-api.service';

@Injectable({ providedIn: 'root' })
export class SpecialistGuard implements CanActivate {
  constructor(private adminApi: AdminApiService, private router: Router) {}

  canActivate(): boolean {
    if (this.adminApi.isSpecialist()) return true;
    if (this.adminApi.isAdmin()) {
      this.router.navigate(['/admin/dashboard']);
      return false;
    }
    this.router.navigate(['/admin/login']);
    return false;
  }
}
