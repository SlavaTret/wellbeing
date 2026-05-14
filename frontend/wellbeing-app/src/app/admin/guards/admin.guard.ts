import { Injectable } from '@angular/core';
import { CanActivate, Router } from '@angular/router';
import { AdminApiService } from '../services/admin-api.service';

@Injectable({ providedIn: 'root' })
export class AdminGuard implements CanActivate {
  constructor(private adminApi: AdminApiService, private router: Router) {}

  canActivate(): boolean {
    if (this.adminApi.isAdmin() || this.adminApi.isSpecialist()) return true;
    this.router.navigate(['/admin/login']);
    return false;
  }
}
