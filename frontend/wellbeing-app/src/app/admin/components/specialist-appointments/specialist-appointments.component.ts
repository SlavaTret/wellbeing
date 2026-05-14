import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { AdminApiService } from '../../services/admin-api.service';

const STATUS_LABELS: Record<string, string> = {
  all:       'Всі',
  confirmed: 'Підтверджено',
  pending:   'Очікує',
  completed: 'Завершено',
  cancelled: 'Скасовано',
  noshow:    'Не прийшов',
};

@Component({
  selector: 'app-specialist-appointments',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './specialist-appointments.component.html',
  styleUrls: ['./specialist-appointments.component.css'],
})
export class SpecialistAppointmentsComponent implements OnInit {
  items: any[]    = [];
  loading         = true;
  listLoading     = false;
  error           = '';
  search          = '';
  statusFilter    = 'all';
  searchTimer: any;

  total   = 0;
  page    = 1;
  pages   = 1;
  perPage = 15;

  readonly statusTabs = ['all','confirmed','pending','completed','cancelled','noshow'];

  showModal    = false;
  modalItem: any = null;
  quickStatus  = '';
  saving       = false;
  modalError   = '';

  constructor(private adminApi: AdminApiService, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void { this.load(); }

  load(soft = false): void {
    if (soft) this.listLoading = true;
    else { this.loading = true; this.error = ''; }

    this.adminApi.getMyAppointments({ search: this.search, status: this.statusFilter, page: this.page }).subscribe({
      next: (res: any) => {
        this.items       = res.items || [];
        this.total       = res.total;
        this.pages       = res.pages;
        this.loading     = false;
        this.listLoading = false;
        this.cdr.markForCheck();
      },
      error: (err: any) => {
        if (!soft) this.error = err?.error?.error || 'Помилка завантаження';
        this.loading     = false;
        this.listLoading = false;
        this.cdr.markForCheck();
      },
    });
  }

  onSearchInput(): void {
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => { this.page = 1; this.load(true); }, 350);
  }

  setStatus(s: string): void {
    if (this.statusFilter === s) return;
    this.statusFilter = s;
    this.page = 1;
    this.load(true);
  }

  goPage(p: number): void {
    if (p < 1 || p > this.pages) return;
    this.page = p;
    this.load(true);
  }

  get pageNumbers(): number[] {
    const arr: number[] = [];
    const start = Math.max(1, this.page - 2);
    const end   = Math.min(this.pages, this.page + 2);
    for (let i = start; i <= end; i++) arr.push(i);
    return arr;
  }

  openModal(item: any): void {
    this.modalItem  = { ...item };
    this.quickStatus = item.status;
    this.modalError = '';
    this.showModal  = true;
  }

  closeModal(): void { this.showModal = false; this.modalItem = null; }

  saveStatus(): void {
    if (!this.modalItem || this.quickStatus === this.modalItem.status) { this.closeModal(); return; }
    this.saving = true;
    this.adminApi.updateMyAppointment(this.modalItem.id, { status: this.quickStatus }).subscribe({
      next: () => {
        const idx = this.items.findIndex(a => a.id === this.modalItem.id);
        if (idx !== -1) this.items[idx] = { ...this.items[idx], status: this.quickStatus };
        this.saving = false;
        this.closeModal();
        this.cdr.markForCheck();
      },
      error: (err: any) => {
        this.modalError = err?.error?.error || 'Помилка збереження';
        this.saving = false;
        this.cdr.markForCheck();
      },
    });
  }

  statusLabel(s: string): string { return STATUS_LABELS[s] || s; }

  statusClass(s: string): string {
    const m: Record<string, string> = {
      confirmed: 'badge--confirmed', pending: 'badge--pending',
      completed: 'badge--completed', cancelled: 'badge--cancelled', noshow: 'badge--noshow',
    };
    return m[s] || 'badge--pending';
  }

  formatDate(d: string): string {
    if (!d) return '—';
    const [y, m, day] = d.split('-');
    return `${day}.${m}.${y}`;
  }

  initials(name: string): string {
    return (name || '').trim().split(' ').slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('');
  }

  get rangeStart(): number { return (this.page - 1) * this.perPage + 1; }
  get rangeEnd(): number   { return Math.min(this.page * this.perPage, this.total); }
}
