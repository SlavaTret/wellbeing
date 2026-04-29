import { Component, OnInit } from '@angular/core';
import { AdminApiService } from '../../services/admin-api.service';

interface AdminAppointment {
  id: number;
  user_id: number;
  user_name: string;
  user_email: string;
  company_name: string;
  specialist_name: string;
  specialist_type: string;
  appointment_date: string;
  appointment_time: string;
  status: string;
  payment_status: string;
  notes: string;
  price: number;
  created_at: string | number;
}

const STATUS_LABELS: Record<string, string> = {
  all:       'Всі',
  confirmed: 'Підтверджено',
  pending:   'Очікує',
  completed: 'Завершено',
  cancelled: 'Скасовано',
  noshow:    'Не прийшов',
};

const TYPE_LABELS: Record<string, string> = {
  psychologist: 'Психолог',
  therapist:    'Психотерапевт',
  coach:        'Коуч',
};

@Component({
  selector: 'app-admin-appointments',
  templateUrl: './admin-appointments.component.html',
  styleUrls: ['./admin-appointments.component.css']
})
export class AdminAppointmentsPageComponent implements OnInit {
  appointments: AdminAppointment[] = [];
  loading      = true;
  listLoading  = false;
  error        = '';
  search       = '';
  statusFilter = 'all';
  searchTimer: any;

  total   = 0;
  page    = 1;
  pages   = 1;
  perPage = 15;

  readonly statusTabs = ['all','confirmed','pending','completed','cancelled','noshow'];

  // Modal
  showModal   = false;
  isCreate    = false;
  modalApp: AdminAppointment | null = null;

  // Create form fields
  createUserId      = '';
  createSpecId      = '';
  createDate        = '';
  createTime        = '';

  // Quick-status change
  quickStatusValue  = '';

  saving      = false;
  cancelling  = false;
  modalError  = '';

  specialists: any[] = [];
  users: any[]       = [];
  listsLoaded        = false;

  constructor(private adminApi: AdminApiService) {}

  ngOnInit(): void { this.load(); }

  load(soft = false): void {
    if (soft) { this.listLoading = true; }
    else      { this.loading = true; this.error = ''; }

    this.adminApi.getAdminAppointments({ search: this.search, status: this.statusFilter, page: this.page }).subscribe({
      next: (res: any) => {
        this.appointments = res.items || [];
        this.total        = res.total;
        this.pages        = res.pages;
        this.loading      = false;
        this.listLoading  = false;
      },
      error: (err: any) => {
        if (!soft) this.error = err?.error?.error || 'Помилка завантаження';
        this.loading     = false;
        this.listLoading = false;
      }
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

  get rangeStart(): number { return (this.page - 1) * this.perPage + 1; }
  get rangeEnd(): number   { return Math.min(this.page * this.perPage, this.total); }

  // ── Modal ───────────────────────────────────────────────────────

  openCreate(): void {
    this.isCreate        = true;
    this.modalApp        = null;
    this.createUserId    = '';
    this.createSpecId    = '';
    this.createDate      = '';
    this.createTime      = '';
    this.modalError      = '';
    this.showModal       = true;
    this.loadLists();
  }

  openView(a: AdminAppointment): void {
    this.isCreate       = false;
    this.modalApp       = { ...a };
    this.quickStatusValue = a.status;
    this.modalError     = '';
    this.showModal      = true;
    this.loadLists();
  }

  closeModal(): void { this.showModal = false; this.modalApp = null; }

  private loadLists(): void {
    if (this.listsLoaded) return;
    this.adminApi.getAdminSpecialists().subscribe({ next: (s: any[]) => { this.specialists = s || []; } });
    this.adminApi.getAdminUsers().subscribe({ next: (res: any) => { this.users = res.items || []; this.listsLoaded = true; } });
  }

  onSpecSelect(e: Event): void {
    const id = +(e.target as HTMLSelectElement).value;
    this.createSpecId = String(id);
  }

  saveCreate(): void {
    this.saving     = true;
    this.modalError = '';

    const spec = this.specialists.find((s: any) => s.id === +this.createSpecId);
    const payload: any = {
      user_id:          +this.createUserId || undefined,
      specialist_name:  spec?.name || '',
      specialist_type:  spec?.type || 'psychologist',
      specialist_id:    spec?.id || undefined,
      appointment_date: this.createDate,
      appointment_time: this.createTime,
      status:           'pending',
      payment_status:   'unpaid',
      price:            spec?.price || 0,
    };

    this.adminApi.createAdminAppointment(payload).subscribe({
      next: (res: any) => {
        this.appointments.unshift(res.appointment);
        this.total++;
        this.saving = false;
        this.closeModal();
      },
      error: (err: any) => {
        const errs = err?.error?.errors;
        this.modalError = errs ? Object.values(errs).flat().join('; ') : (err?.error?.error || 'Помилка збереження');
        this.saving = false;
      }
    });
  }

  saveStatus(): void {
    if (!this.modalApp || this.quickStatusValue === this.modalApp.status) { this.closeModal(); return; }
    this.saving = true;
    this.adminApi.updateAdminAppointment(this.modalApp.id, { status: this.quickStatusValue }).subscribe({
      next: (res: any) => {
        const idx = this.appointments.findIndex(a => a.id === this.modalApp!.id);
        if (idx !== -1) this.appointments[idx] = res.appointment;
        this.saving = false;
        this.closeModal();
      },
      error: (err: any) => {
        this.modalError = err?.error?.error || 'Помилка збереження';
        this.saving = false;
      }
    });
  }

  cancelAppointment(): void {
    if (!this.modalApp) return;
    if (!confirm(`Скасувати запис #${this.modalApp.id}?`)) return;
    this.cancelling = true;
    this.adminApi.updateAdminAppointment(this.modalApp.id, { status: 'cancelled' }).subscribe({
      next: (res: any) => {
        const idx = this.appointments.findIndex(a => a.id === this.modalApp!.id);
        if (idx !== -1) this.appointments[idx] = res.appointment;
        this.cancelling = false;
        this.closeModal();
      },
      error: () => { this.cancelling = false; }
    });
  }

  // ── Helpers ─────────────────────────────────────────────────────

  statusLabel(s: string): string  { return STATUS_LABELS[s] || s; }
  typeLabel(t: string): string    { return TYPE_LABELS[t] || t; }

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

  paymentClass(s: string): string { 
    if (s === 'paid') return 'badge--paid';
    if (s === 'subscription') return 'badge--subscription';
    return 'badge--unpaid'; 
  }
  
  paymentLabel(s: string): string { 
    if (s === 'paid') return 'Оплачено';
    if (s === 'subscription') return 'Корпоративна підписка';
    return 'Не оплачено'; 
  }

  formatDate(d: string): string {
    if (!d) return '—';
    const [y, m, day] = d.split('-');
    return `${day}.${m}.${y}`;
  }

  formatPrice(p: number): string { return p ? `${p} ₴` : '—'; }
}
