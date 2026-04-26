import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from './services/api/api.service';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.css']
})
export class AppComponent implements OnInit {
  title = 'Wellbeing';
  activeTab = 'dashboard';
  sidebarOpen = true;
  user: any = null;
  notificationsCount = 0;

  navItems = [
    { id: 'dashboard', label: 'Дашборд', icon: '📊' },
    { id: 'appointments', label: 'Мої записи', icon: '📅' },
    { id: 'documents', label: 'Документи', icon: '📄' },
    { id: 'payments', label: 'Оплата', icon: '💳', badge: 0 },
    { id: 'notifications', label: 'Сповіщення', icon: '🔔', badge: 3 },
    { id: 'questionnaire', label: 'Анкета', icon: '📋' },
    { id: 'support', label: 'Зв\'язок з WO', icon: '💬' }
  ];

  constructor(private apiService: ApiService, private router: Router) {}

  ngOnInit(): void {
    if (this.apiService.isLoggedIn()) {
      this.loadUserProfile();
    } else {
      this.router.navigate(['/login']);
    }
  }

  loadUserProfile(): void {
    this.apiService.getProfile().subscribe(
      (user) => {
        this.user = user;
      },
      (error) => {
        console.error('Failed to load profile', error);
      }
    );
  }

  navigate(tabId: string): void {
    this.activeTab = tabId;
    this.router.navigate([`/${tabId}`]);
  }

  toggleSidebar(): void {
    this.sidebarOpen = !this.sidebarOpen;
  }

  logout(): void {
    this.apiService.logout().subscribe(
      () => {
        this.router.navigate(['/login']);
      },
      (error) => {
        console.error('Logout failed', error);
        this.apiService.clearAccessToken();
        this.router.navigate(['/login']);
      }
    );
  }
}
