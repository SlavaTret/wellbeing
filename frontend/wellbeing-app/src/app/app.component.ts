import { Component, HostListener, OnInit } from '@angular/core';
import { ActivatedRoute, Router, NavigationEnd } from '@angular/router';
import { Title } from '@angular/platform-browser';
import { filter } from 'rxjs/operators';
import { ApiService } from './services/api/api.service';
import { UserService } from './services/user/user.service';
import { NotificationService } from './services/notification/notification.service';
import { BrandingService } from './services/branding/branding.service';
import { LangService } from './services/lang/lang.service';

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
  isAuthPage = false;
  isAdminPage = false;

  private siteTitlePrefix = 'Wellbeing';

  navItems: NavItem[] = [
    { id: 'dashboard',     label: 'Дашборд',       icon: 'dashboard' },
    { id: 'appointments',  label: 'Мої записи',    icon: 'calendar' },
    { id: 'documents',     label: 'Документи',     icon: 'file' },
    { id: 'payments',      label: 'Оплата',        icon: 'card' },
    { id: 'notifications', label: 'Сповіщення',    icon: 'bell' },
    { id: 'questionnaire', label: 'Анкета',        icon: 'clipboard' },
    { id: 'support',       label: "Зв'язок з WO",  icon: 'chat' },
    { id: 'settings',      label: 'Налаштування',  icon: 'settings' }
  ];

  constructor(
    public userService: UserService,
    public notifService: NotificationService,
    public branding: BrandingService,
    public lang: LangService,
    private apiService: ApiService,
    private router: Router,
    private activatedRoute: ActivatedRoute,
    private titleService: Title
  ) {
    this.lang.init();
    this.sidebarOpen = window.innerWidth > 768;

    this.isAdminPage = window.location.pathname.startsWith('/admin');

    this.router.events.pipe(
      filter(e => e instanceof NavigationEnd)
    ).subscribe((e: any) => {
      this.isAdminPage = e.urlAfterRedirects.startsWith('/admin');
      this.isAuthPage = ['/login', '/register'].includes(e.urlAfterRedirects);
      const path = e.urlAfterRedirects.split('/')[1];
      if (path) this.activeTab = path;

      // Update browser tab title from deepest route data
      let route = this.activatedRoute;
      while (route.firstChild) route = route.firstChild;
      const pageTitle = route.snapshot.data?.['title'];
      if (pageTitle) {
        this.titleService.setTitle(
          this.siteTitlePrefix ? `${this.siteTitlePrefix} — ${pageTitle}` : pageTitle
        );
      }
    });
  }

  @HostListener('window:resize')
  onResize(): void {
    if (window.innerWidth > 768) this.sidebarOpen = true;
  }

  ngOnInit(): void {
    // Load portal settings for page title prefix (public endpoint — no auth needed)
    this.apiService.getPortalSettings().subscribe({
      next: (data: any) => {
        if (data?.site_title_prefix) {
          this.siteTitlePrefix = data.site_title_prefix;
          // Re-apply title to current route after prefix is loaded
          let route = this.activatedRoute;
          while (route.firstChild) route = route.firstChild;
          const pageTitle = route.snapshot.data?.['title'];
          if (pageTitle) {
            this.titleService.setTitle(`${this.siteTitlePrefix} — ${pageTitle}`);
          }
        }
      },
      error: () => {}
    });

    if (this.isAdminPage) return;

    if (this.apiService.isLoggedIn()) {
      this.userService.load().subscribe({
        next: () => this.notifService.load(),
        error: () => {
          this.apiService.clearAccessToken();
          this.router.navigate(['/login']);
        }
      });
    } else {
      this.router.navigate(['/login']);
    }
  }

  get user(): any { return this.userService.current; }
  get notificationsCount(): number { return this.notifService.count; }

  navigate(tabId: string): void {
    this.activeTab = tabId;
    this.router.navigate([`/${tabId}`]);
    if (window.innerWidth <= 768) this.sidebarOpen = false;
  }

  openSidebar(): void  { this.sidebarOpen = true; }
  closeSidebar(): void { this.sidebarOpen = false; }

  logout(): void {
    this.apiService.logout().subscribe({
      next: () => {
        this.userService.clear();
        this.router.navigate(['/login']);
      },
      error: () => {
        this.apiService.clearAccessToken();
        this.userService.clear();
        this.router.navigate(['/login']);
      }
    });
  }
}
