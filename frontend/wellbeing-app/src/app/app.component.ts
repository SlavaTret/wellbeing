import { Component, HostListener, OnInit } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { ApiService } from './services/api/api.service';
import { BrandingService } from './services/branding/branding.service';

interface NavItem {
  id: string;
  label: string;
  icon: string;
  badge?: number;
}

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.css']
})
export class AppComponent implements OnInit {
  activeTab = 'dashboard';
  sidebarOpen = false;
  user: any = null;
  notificationsCount = 3;
  isAuthPage = false;

  navItems: NavItem[] = [
    { id: 'dashboard',     label: 'Дашборд',         icon: 'dashboard' },
    { id: 'appointments',  label: 'Мої записи',      icon: 'calendar' },
    { id: 'documents',     label: 'Документи',       icon: 'file' },
    { id: 'payments',      label: 'Оплата',          icon: 'card' },
    { id: 'notifications', label: 'Сповіщення',      icon: 'bell',      badge: 3 },
    { id: 'questionnaire', label: 'Анкета',          icon: 'clipboard' },
    { id: 'support',       label: 'Зв\'язок з WO',   icon: 'chat' }
  ];

  constructor(
    private apiService: ApiService,
    private router: Router,
    private branding: BrandingService
  ) {
    this.sidebarOpen = window.innerWidth > 768;

    this.router.events.pipe(
      filter(e => e instanceof NavigationEnd)
    ).subscribe((e: any) => {
      this.isAuthPage = ['/login', '/register'].includes(e.urlAfterRedirects);
      const path = e.urlAfterRedirects.split('/')[1];
      if (path) this.activeTab = path;
    });
  }

  @HostListener('window:resize')
  onResize(): void {
    if (window.innerWidth > 768) this.sidebarOpen = true;
  }

  ngOnInit(): void {
    if (this.apiService.isLoggedIn()) {
      this.loadUserProfile();
    } else {
      this.router.navigate(['/login']);
    }
  }

  loadUserProfile(): void {
    this.apiService.getProfile().subscribe({
      next: (user) => {
        this.user = user;
        this.branding.set(user?.company_branding ?? null);
      },
      error: (error) => console.error('Failed to load profile', error)
    });
  }

  navigate(tabId: string): void {
    this.activeTab = tabId;
    this.router.navigate([`/${tabId}`]);
    if (window.innerWidth <= 768) this.sidebarOpen = false;
  }

  openSidebar(): void  { this.sidebarOpen = true; }
  closeSidebar(): void { this.sidebarOpen = false; }

  logout(): void {
    this.apiService.logout().subscribe({
      next: () => this.router.navigate(['/login']),
      error: () => {
        this.apiService.clearAccessToken();
        this.router.navigate(['/login']);
      }
    });
  }
}
