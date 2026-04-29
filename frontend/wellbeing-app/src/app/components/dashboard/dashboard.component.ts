import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { ApiService } from '../../services/api/api.service';
import { UserService } from '../../services/user/user.service';

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent implements OnInit, OnDestroy {
  upcomingLoading = true;

  // Displayed (animated) values
  displayStats = [
    { labelKey: 'dashboard.stats.upcoming',       value: 0, icon: 'calendar', color: '#2DB928', bg: '#E8F5E9' },
    { labelKey: 'dashboard.stats.completed',      value: 0, icon: 'check',    color: '#1565C0', bg: '#E3F2FD' },
    { labelKey: 'dashboard.stats.free_remaining', value: 0, icon: 'shield',   color: '#6A1B9A', bg: '#F3E5F5' },
    { labelKey: 'dashboard.stats.cancelled',      value: 0, icon: 'x',        color: '#C62828', bg: '#FFEBEE' },
  ];

  freeSessionsUsed  = 0;
  freeSessionsTotal = 5;
  freePercent       = 0;       // starts at 0, animates to real value
  freePercentTarget = 0;

  upcoming: any[] = [];

  // Google Calendar
  googleConnected      = false;
  googleEvents: any[]  = [];
  googleEventsLoading  = false;
  private googleInterval: any;

  private rafIds: number[] = [];

  constructor(private router: Router, private api: ApiService, private userService: UserService, private translate: TranslateService) {}

  ngOnInit(): void {
    this.loadGoogleEvents();
    this.googleInterval = setInterval(() => this.loadGoogleEvents(), 60_000);

    // Show cached free-sessions immediately — no API wait
    const cached = this.userService.freeSessions;
    if (cached) {
      this.freeSessionsTotal = cached.total;
      this.freeSessionsUsed  = cached.used;
      this.freePercent       = cached.percent;
      this.freePercentTarget = cached.percent;
      this.displayStats[2]   = { ...this.displayStats[2], value: cached.remaining };
    }

    this.api.getDashboard().subscribe({
      next: (data: any) => {
        const s  = data.stats;
        const fs = data.free_sessions;

        const targets = [s.upcoming, s.completed, s.free_remaining, s.cancelled];
        targets.forEach((target, i) => this.animateCounter(i, target));

        // Only animate progress bar if value changed since cache
        if (!cached || cached.percent !== fs.percent) {
          this.freePercentTarget = fs.percent;
          this.animateProgress(fs.percent);
        }
        this.freeSessionsTotal = fs.total;
        this.freeSessionsUsed  = fs.used;

        this.userService.setFreeSessions(fs);

        this.upcoming        = data.upcoming_appointments || [];
        this.upcomingLoading = false;
      },
      error: () => { this.upcomingLoading = false; }
    });
  }

  ngOnDestroy(): void {
    this.rafIds.forEach(id => cancelAnimationFrame(id));
    if (this.googleInterval) clearInterval(this.googleInterval);
  }

  private loadGoogleEvents(): void {
    this.googleEventsLoading = true;
    this.api.getGoogleUpcomingEvents().subscribe({
      next: (res) => {
        this.googleConnected     = res.connected;
        this.googleEvents        = res.events ?? [];
        this.googleEventsLoading = false;
      },
      error: () => { this.googleEventsLoading = false; }
    });
  }

  formatEventTime(isoStr: string | null): string {
    if (!isoStr) return '';
    const ukMonths = ['','січ','лют','бер','квіт','трав','черв','лип','серп','вер','жовт','лист','груд'];
    // All-day event: date-only string "YYYY-MM-DD"
    if (/^\d{4}-\d{2}-\d{2}$/.test(isoStr)) {
      const [, m, day] = isoStr.split('-').map(Number);
      return `${day} ${ukMonths[m]}`;
    }
    const d = new Date(isoStr);
    return `${d.getDate()} ${ukMonths[d.getMonth() + 1]}, ${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`;
  }

  connectGoogleCalendar(): void {
    this.api.getGoogleAuthUrl().subscribe({
      next: (res) => { window.location.href = res.url; },
      error: () => {}
    });
  }

  private animateCounter(index: number, target: number, duration = 900): void {
    if (target === 0) return;
    const start    = performance.now();
    const tick = (now: number) => {
      const elapsed  = now - start;
      const progress = Math.min(elapsed / duration, 1);
      // ease-out cubic
      const eased    = 1 - Math.pow(1 - progress, 3);
      this.displayStats[index] = {
        ...this.displayStats[index],
        value: Math.round(eased * target)
      };
      if (progress < 1) {
        this.rafIds.push(requestAnimationFrame(tick));
      } else {
        this.displayStats[index] = { ...this.displayStats[index], value: target };
      }
    };
    this.rafIds.push(requestAnimationFrame(tick));
  }

  private animateProgress(targetPercent: number, duration = 1000): void {
    // Small delay so the bar starts at 0 visibly before animating
    setTimeout(() => {
      const start = performance.now();
      const tick  = (now: number) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased    = 1 - Math.pow(1 - progress, 3);
        this.freePercent = Math.round(eased * targetPercent);
        if (progress < 1) {
          this.rafIds.push(requestAnimationFrame(tick));
        } else {
          this.freePercent = targetPercent;
        }
      };
      this.rafIds.push(requestAnimationFrame(tick));
    }, 100);
  }

  getStatusLabel(status: string): string {
    return this.translate.instant('appointments.status.' + status) || status;
  }

  getStatusClass(status: string): string { return `badge-${status}`; }

  typeName(type: string): string {
    return this.translate.instant('specialist.type.' + type) || type;
  }

  goToAppointments() { this.router.navigate(['/appointments']); }
}
