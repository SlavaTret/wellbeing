import { Component, OnInit } from '@angular/core';
import { AdminApiService } from '../../services/admin-api.service';

@Component({
  selector: 'app-admin-dashboard',
  templateUrl: './admin-dashboard.component.html',
  styleUrls: ['./admin-dashboard.component.css']
})
export class AdminDashboardComponent implements OnInit {
  loading = true;
  error = '';

  stats: any = null;
  recentAppointments: any[] = [];
  pendingPayments: any[] = [];
  companyStats: any[] = [];

  constructor(private adminApi: AdminApiService) {}

  ngOnInit(): void {
    this.adminApi.getDashboard().subscribe({
      next: (res: any) => {
        this.stats = res.stats;
        this.recentAppointments = res.recent_appointments || [];
        this.pendingPayments = res.pending_payments || [];
        this.companyStats = res.company_stats || [];
        this.loading = false;
      },
      error: () => {
        this.error = 'Не вдалося завантажити дані дашборду';
        this.loading = false;
      }
    });
  }

  formatAmount(val: number | string): string {
    const n = parseFloat(val as string) || 0;
    if (n >= 1000) return (n / 1000).toFixed(1).replace('.0', '') + 'к';
    return n.toLocaleString('uk-UA', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  formatDateShort(d: string): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  initials(name: string): string {
    if (!name) return '?';
    const parts = name.trim().split(' ');
    return ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase();
  }

  avatarColor(name: string): string {
    const colors = ['#2DB928', '#1976D2', '#7B1FA2', '#E65100', '#0097A7', '#C62828', '#388E3C', '#1565C0'];
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
    return colors[Math.abs(hash) % colors.length];
  }

  statusLabel(s: string): string {
    const map: any = {
      active: 'Активний', confirmed: 'Підтверджено',
      completed: 'Завершено', cancelled: 'Скасовано',
      pending: 'Очікує', noshow: 'Не з\'явився',
    };
    return map[s] || s;
  }

  statusClass(s: string): string {
    const map: any = {
      active: 'badge--confirmed', confirmed: 'badge--confirmed',
      completed: 'badge--completed', cancelled: 'badge--cancelled',
      pending: 'badge--pending', noshow: 'badge--noshow',
    };
    return map[s] || 'badge--pending';
  }

  get activeCount(): number {
    return this.recentAppointments.filter(a => a.status === 'confirmed' || a.status === 'active').length;
  }
}
