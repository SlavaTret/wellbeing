import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { AdminApiService } from '../../services/admin-api.service';

const STATUS_LABELS: Record<string, string> = {
  confirmed: 'Підтверджено',
  pending:   'Очікує',
  completed: 'Завершено',
  cancelled: 'Скасовано',
  noshow:    'Не прийшов',
};

@Component({
  selector: 'app-specialist-dashboard',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './specialist-dashboard.component.html',
  styleUrls: ['./specialist-dashboard.component.css'],
})
export class SpecialistDashboardComponent implements OnInit {
  loading = true;
  error   = '';

  specialist: any = null;
  stats: any      = null;
  recent: any[]   = [];

  constructor(private adminApi: AdminApiService, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.adminApi.getSpecialistDashboard().subscribe({
      next: (res: any) => {
        this.specialist = res.specialist;
        this.stats      = res.stats;
        this.recent     = res.recent_appointments || [];
        this.loading    = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.error   = 'Не вдалося завантажити дані';
        this.loading = false;
        this.cdr.markForCheck();
      },
    });
  }

  statusLabel(s: string): string { return STATUS_LABELS[s] || s; }

  statusClass(s: string): string {
    const m: Record<string, string> = {
      confirmed: 'badge--confirmed',
      pending:   'badge--pending',
      completed: 'badge--completed',
      cancelled: 'badge--cancelled',
      noshow:    'badge--noshow',
    };
    return m[s] || 'badge--pending';
  }

  formatDate(d: string): string {
    if (!d) return '—';
    const [y, m, day] = d.split('-');
    return `${day}.${m}.${y}`;
  }

  initials(name: string): string {
    if (!name) return '?';
    return name.trim().split(' ').slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('');
  }
}
