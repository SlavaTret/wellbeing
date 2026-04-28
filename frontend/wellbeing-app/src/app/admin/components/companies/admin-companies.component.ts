import { Component, OnInit } from '@angular/core';
import { AdminApiService } from '../../services/admin-api.service';

interface Company {
  id: number;
  code: string;
  name: string;
  logo_url: string | null;
  primary_color: string;
  secondary_color: string;
  accent_color: string;
  free_sessions_per_user: number;
  is_active: boolean;
  created_at: number;
  total_users: number;
  total_appointments: number;
  total_revenue: number;
}

@Component({
  selector: 'app-admin-companies',
  templateUrl: './admin-companies.component.html',
  styleUrls: ['./admin-companies.component.css']
})
export class AdminCompaniesComponent implements OnInit {
  companies: Company[] = [];
  loading = true;
  error = '';

  // Modal state
  modalOpen = false;
  modalMode: 'create' | 'edit' = 'create';
  saving = false;
  modalError = '';
  logoUploading = false;
  logoPreview: string | null = null;

  deleteConfirmId: number | null = null;
  deleting = false;

  form: Partial<Company> & { name: string; code: string } = this.emptyForm();

  constructor(private adminApi: AdminApiService) {}

  ngOnInit(): void { this.load(); }

  load(): void {
    this.loading = true;
    this.adminApi.getAdminCompanies().subscribe({
      next: (res: any) => { this.companies = res; this.loading = false; },
      error: () => { this.error = 'Не вдалося завантажити компанії'; this.loading = false; }
    });
  }

  openCreate(): void {
    this.form = this.emptyForm();
    this.logoPreview = null;
    this.modalMode = 'create';
    this.modalError = '';
    this.modalOpen = true;
  }

  openEdit(c: Company): void {
    this.form = {
      id: c.id, name: c.name, code: c.code,
      logo_url: c.logo_url || '',
      primary_color: c.primary_color || '#2DB928',
      secondary_color: c.secondary_color || '#1C2B20',
      accent_color: c.accent_color || '#E8F5E9',
      free_sessions_per_user: c.free_sessions_per_user,
      is_active: c.is_active,
    };
    this.logoPreview = c.logo_url || null;
    this.modalMode = 'edit';
    this.modalError = '';
    this.modalOpen = true;
  }

  closeModal(): void { this.modalOpen = false; }

  onLogoFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    const allowed = ['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'];
    if (!allowed.includes(file.type)) {
      this.modalError = 'Дозволені формати: SVG, PNG, JPG, WEBP';
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      this.modalError = 'Файл завеликий (макс. 2 МБ)';
      return;
    }

    this.logoUploading = true;
    this.modalError = '';

    // Show local preview immediately
    const reader = new FileReader();
    reader.onload = (e) => { this.logoPreview = e.target?.result as string; };
    reader.readAsDataURL(file);

    this.adminApi.uploadLogo(file).subscribe({
      next: (res: any) => {
        this.form.logo_url = res.url;
        this.logoUploading = false;
      },
      error: (err: any) => {
        this.logoUploading = false;
        this.modalError = err?.error?.error || 'Помилка завантаження файлу';
        this.logoPreview = this.form.logo_url || null;
      }
    });
  }

  clearLogo(): void {
    this.form.logo_url = '';
    this.logoPreview = null;
  }

  save(): void {
    if (!this.form.name?.trim() || !this.form.code?.trim()) {
      this.modalError = 'Назва та код є обов\'язковими';
      return;
    }
    this.saving = true;
    this.modalError = '';

    const payload = {
      name: this.form.name,
      code: this.form.code,
      logo_url: this.form.logo_url || null,
      primary_color: this.form.primary_color,
      secondary_color: this.form.secondary_color,
      accent_color: this.form.accent_color,
      free_sessions_per_user: this.form.free_sessions_per_user,
      is_active: this.form.is_active ?? true,
    };

    const req = this.modalMode === 'create'
      ? this.adminApi.createCompany(payload)
      : this.adminApi.updateCompany(this.form.id!, payload);

    req.subscribe({
      next: () => { this.saving = false; this.modalOpen = false; this.load(); },
      error: (err: any) => {
        this.saving = false;
        const errs = err?.error?.errors;
        if (errs) {
          this.modalError = Object.values(errs).flat().join(', ');
        } else {
          this.modalError = err?.error?.error || 'Помилка збереження';
        }
      }
    });
  }

  confirmDelete(id: number): void { this.deleteConfirmId = id; }
  cancelDelete(): void { this.deleteConfirmId = null; }

  doDelete(): void {
    if (!this.deleteConfirmId) return;
    this.deleting = true;
    this.adminApi.deleteCompany(this.deleteConfirmId).subscribe({
      next: () => { this.deleting = false; this.deleteConfirmId = null; this.load(); },
      error: () => { this.deleting = false; this.deleteConfirmId = null; }
    });
  }

  formatAmount(n: number): string {
    if (n >= 1000) return (n / 1000).toFixed(1).replace('.0', '') + 'к';
    return n.toLocaleString('uk-UA');
  }

  formatDate(ts: number): string {
    if (!ts) return '—';
    return new Date(ts * 1000).toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  initial(name: string): string { return (name || '?').charAt(0).toUpperCase(); }

  private emptyForm(): any {
    return {
      name: '', code: '', logo_url: '',
      primary_color: '#2DB928', secondary_color: '#1C2B20',
      accent_color: '#E8F5E9', free_sessions_per_user: 5, is_active: true,
    };
  }
}
