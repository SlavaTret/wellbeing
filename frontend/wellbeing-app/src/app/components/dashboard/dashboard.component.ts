import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from '../../services/api/api.service';

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent implements OnInit, OnDestroy {
  upcomingLoading = true;

  // Displayed (animated) values
  displayStats = [
    { label: 'Заплановані консультації', value: 0, icon: 'calendar', color: '#2DB928', bg: '#E8F5E9' },
    { label: 'Завершені консультації',    value: 0, icon: 'check',    color: '#1565C0', bg: '#E3F2FD' },
    { label: 'Залишок безкоштовних',      value: 0, icon: 'shield',   color: '#6A1B9A', bg: '#F3E5F5' },
    { label: 'Скасовані',                 value: 0, icon: 'x',        color: '#C62828', bg: '#FFEBEE' },
  ];

  freeSessionsUsed  = 0;
  freeSessionsTotal = 5;
  freePercent       = 0;       // starts at 0, animates to real value
  freePercentTarget = 0;

  upcoming: any[] = [];

  private rafIds: number[] = [];

  constructor(private router: Router, private api: ApiService) {}

  ngOnInit(): void {
    this.api.getDashboard().subscribe({
      next: (data: any) => {
        const s  = data.stats;
        const fs = data.free_sessions;

        const targets = [s.upcoming, s.completed, s.free_remaining, s.cancelled];

        // Animate each counter
        targets.forEach((target, i) => {
          this.animateCounter(i, target);
        });

        // Animate free-sessions values
        this.freeSessionsTotal = fs.total;
        this.freeSessionsUsed  = fs.used;
        this.freePercentTarget = fs.percent;
        this.animateProgress(fs.percent);

        this.upcoming       = data.upcoming_appointments || [];
        this.upcomingLoading = false;
      },
      error: () => { this.upcomingLoading = false; }
    });
  }

  ngOnDestroy(): void {
    this.rafIds.forEach(id => cancelAnimationFrame(id));
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
    const map: any = { confirmed: 'Підтверджено', pending: 'Очікування', completed: 'Завершено', cancelled: 'Скасовано' };
    return map[status] || status;
  }

  getStatusClass(status: string): string { return `badge-${status}`; }

  goToAppointments() { this.router.navigate(['/appointments']); }
}
