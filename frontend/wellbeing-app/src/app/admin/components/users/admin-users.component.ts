import { Component, OnInit } from '@angular/core';
import { AdminApiService } from '../../services/admin-api.service';

interface AdminUser {
  id: number;
  first_name: string;
  last_name: string;
  name: string;
  email: string;
  phone: string;
  avatar_url: string | null;
  status: number;
  is_active: boolean;
  is_admin: boolean;
  company_id: number | null;
  company_name: string | null;
  total_appointments: number;
  sessions_left: number;
  created_at: string | number;
}

interface UserForm {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  password: string;
  company_id: number | '';
  role: 'admin' | 'user';
  status: 'active' | 'inactive';
}

@Component({
  selector: 'app-admin-users',
  templateUrl: './admin-users.component.html',
  styleUrls: ['./admin-users.component.css']
})
export class AdminUsersComponent implements OnInit {
  users: AdminUser[] = [];
  companies: any[] = [];
  loading = true;
  error = '';

  totalAll  = 0;
  activeAll = 0;

  search = '';
  searchTimer: any;

  page    = 1;
  pages   = 1;
  total   = 0;
  perPage = 8;

  // Single modal handles both create and edit
  showModal = false;
  modalUser: AdminUser | null = null; // null for create, user for edit
  form: UserForm = this.emptyForm();
  saving      = false;
  modalError  = '';

  constructor(private adminApi: AdminApiService) {}

  ngOnInit(): void {
    this.loadCompanies();
    this.loadUsers();
  }

  private loadCompanies(): void {
    this.adminApi.getAdminCompanies().subscribe({
      next: (list: any) => { this.companies = list || []; },
      error: () => {}
    });
  }

  loadUsers(): void {
    this.loading = true;
    this.error = '';
    this.adminApi.getAdminUsers({ search: this.search, page: this.page }).subscribe({
      next: (res: any) => {
        this.users     = res.users    || [];
        this.total     = res.total    || 0;
        this.totalAll  = res.total_all  ?? res.total ?? 0;
        this.activeAll = res.active_all ?? 0;
        this.pages     = res.pages    || 1;
        this.perPage   = res.per_page || 8;
        this.loading   = false;
      },
      error: (err: any) => {
        this.error   = err?.error?.error || 'Помилка завантаження';
        this.loading = false;
      }
    });
  }

  onSearchInput(): void {
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => { this.page = 1; this.loadUsers(); }, 350);
  }

  goPage(p: number): void {
    if (p < 1 || p > this.pages || p === this.page) return;
    this.page = p;
    this.loadUsers();
  }

  get pageFrom(): number { return (this.page - 1) * this.perPage + 1; }
  get pageTo(): number   { return Math.min(this.page * this.perPage, this.total); }

  get pageNumbers(): number[] {
    return Array.from({ length: this.pages }, (_, i) => i + 1);
  }

  // ── Modal (create/edit) ───────────────────────────────────────────

  openCreate(): void {
    this.modalUser  = null;
    this.form       = this.emptyForm();
    this.modalError = '';
    this.showModal  = true;
  }

  openEdit(u: AdminUser): void {
    this.modalUser  = u;
    this.form = {
      first_name: u.first_name,
      last_name:  u.last_name,
      email:      u.email,
      phone:      u.phone || '',
      password:   '',
      company_id: u.company_id ?? '',
      role:       u.is_admin ? 'admin' : 'user',
      status:     u.is_active ? 'active' : 'inactive'
    };
    this.modalError = '';
    this.showModal  = true;
  }

  closeModal(): void { this.showModal = false; this.modalUser = null; }

  saveModal(): void {
    this.saving     = true;
    this.modalError = '';

    const isEdit = !!this.modalUser;
    const payload: any = {
      first_name: this.form.first_name,
      last_name:  this.form.last_name,
      email:      this.form.email,
      phone:      this.form.phone,
      company_id: this.form.company_id || null,
      is_admin:   this.form.role === 'admin',
      status:     this.form.status === 'active' ? 10 : 9
    };
    if (!isEdit) payload.password = this.form.password;

    const obs = isEdit
      ? this.adminApi.updateUser(this.modalUser!.id, payload)
      : this.adminApi.createUser(payload);

    obs.subscribe({
      next: (res: any) => {
        if (isEdit) {
          const idx = this.users.findIndex(u => u.id === this.modalUser!.id);
          if (idx !== -1) this.users[idx] = res.user;
        } else {
          this.users.unshift(res.user);
          this.total++;
          this.totalAll++;
        }
        this.activeAll = this.users.filter(u => u.is_active).length;
        this.saving = false;
        this.closeModal();
      },
      error: (err: any) => {
        const errs = err?.error?.errors;
        this.modalError = errs
          ? Object.values(errs).flat().join('; ')
          : (err?.error?.error || 'Помилка збереження');
        this.saving = false;
      }
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────

  private emptyForm(): UserForm {
    return { first_name: '', last_name: '', email: '', phone: '', password: '', company_id: '', role: 'user', status: 'active' };
  }

  initials(name: string): string {
    return (name || '').trim().split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('');
  }

  formatDate(d: string | number): string {
    if (!d) return '—';
    const num = Number(d);
    const date = !isNaN(num) && num > 0
      ? new Date(num < 1e10 ? num * 1000 : num)
      : new Date(String(d));
    if (isNaN(date.getTime())) return '—';
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }
}
