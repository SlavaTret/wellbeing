import { Component, HostListener } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { AdminApiService } from '../../services/admin-api.service';

@Component({
  selector: 'app-admin-layout',
  templateUrl: './admin-layout.component.html',
  styleUrls: ['./admin-layout.component.css']
})
export class AdminLayoutComponent {
  activePage = 'dashboard';
  drawerOpen = false;

  readonly navItems = [
    { id: 'dashboard',    label: 'Дашборд',      icon: 'dashboard' },
    { id: 'companies',    label: 'Компанії',     icon: 'building' },
    { id: 'users',        label: 'Користувачі',  icon: 'users' },
    { id: 'payments',     label: 'Оплати',       icon: 'card' },
    { id: 'specialists',     label: 'Спеціалісти',    icon: 'heart' },
    { id: 'specializations', label: 'Спеціалізації', icon: 'award' },
    { id: 'appointments',    label: 'Записи',         icon: 'calendar' },
    { id: 'categories',      label: 'Категорії',      icon: 'tag' },
    { id: 'slots',        label: 'Слоти',        icon: 'clock' },
    { id: 'settings',     label: 'Налаштування', icon: 'settings' },
  ];

  readonly iconPaths: { [key: string]: string | string[] } = {
    dashboard:   ['M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z', 'M9 22V12h6v10'],
    building:    ['M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16', 'M3 21h18', 'M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
    users:       ['M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2', 'M23 21v-2a4 4 0 00-3-3.87', 'M16 3.13a4 4 0 010 7.75', 'M9 11a4 4 0 100-8 4 4 0 000 8z'],
    card:        ['M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
    heart:       ['M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z'],
    calendar:    ['M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
    award:       ['M12 15a7 7 0 100-14 7 7 0 000 14z', 'M8.21 13.89L7 23l5-3 5 3-1.21-9.12'],
    tag:         ['M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z', 'M7 7h.01'],
    clock:       ['M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z', 'M12 6v6l4 2'],
    settings:    ['M12 15a3 3 0 100-6 3 3 0 000 6z', 'M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z'],
    shield:      ['M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
    logout:      ['M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1'],
    menu:        ['M3 6h18', 'M3 12h18', 'M3 18h18'],
    x:           ['M6 18L18 6M6 6l12 12'],
  };

  getIconPaths(icon: string): string[] {
    const p = this.iconPaths[icon];
    return Array.isArray(p) ? p : [p as string];
  }

  get adminUser(): any { return this.adminApi.getAdminUser(); }

  get adminInitials(): string {
    const u = this.adminUser;
    if (!u) return 'АД';
    return ((u.first_name || 'А').charAt(0) + (u.last_name || 'Д').charAt(0)).toUpperCase();
  }

  get adminEmail(): string { return this.adminUser?.email || ''; }

  get adminName(): string {
    const u = this.adminUser;
    if (!u) return 'Адміністратор';
    return `${u.first_name || ''}  ${u.last_name || ''}`.trim() || 'Адміністратор';
  }

  constructor(public adminApi: AdminApiService, private router: Router) {
    this.router.events.pipe(filter(e => e instanceof NavigationEnd)).subscribe((e: any) => {
      const parts = e.urlAfterRedirects.split('/');
      this.activePage = parts[2] || 'dashboard';
    });
    // Set initial active page
    const parts = this.router.url.split('/');
    this.activePage = parts[2] || 'dashboard';
  }

  @HostListener('window:resize')
  onResize(): void { if (window.innerWidth > 768) this.drawerOpen = false; }

  navigate(id: string): void {
    this.router.navigate(['/admin', id]);
    this.drawerOpen = false;
  }

  get activeLabel(): string {
    return this.navItems.find(i => i.id === this.activePage)?.label || 'Адмін';
  }

  logout(): void {
    this.adminApi.clearAdminSession();
    this.router.navigate(['/admin/login']);
  }
}
