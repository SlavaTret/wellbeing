import { Component, OnInit } from '@angular/core';
import { AdminApiService } from '../../services/admin-api.service';

interface AdminSpecialization {
  id: number;
  name: string;
  key: string;
  is_active: boolean;
  sort_order: number;
  specialist_count: number;
  created_at: string | number;
}

@Component({
  selector: 'app-admin-specializations',
  templateUrl: './admin-specializations.component.html',
  styleUrls: ['./admin-specializations.component.css']
})
export class AdminSpecializationsComponent implements OnInit {
  specializations: AdminSpecialization[] = [];
  loading     = true;
  listLoading = false;
  error       = '';

  showModal  = false;
  modalSpec: AdminSpecialization | null = null;
  formName       = '';
  formKey        = '';
  formStatus     = 'active';
  formSortOrder  = 0;
  keyAutoSync    = true;
  saving         = false;
  deleting       = false;
  modalError     = '';

  constructor(private adminApi: AdminApiService) {}

  ngOnInit(): void { this.load(); }

  load(soft = false): void {
    if (soft) { this.listLoading = true; }
    else      { this.loading = true; this.error = ''; }

    this.adminApi.getAdminSpecializations().subscribe({
      next: (list: any[]) => {
        this.specializations = list || [];
        this.loading     = false;
        this.listLoading = false;
      },
      error: (err: any) => {
        if (!soft) this.error = err?.error?.error || 'Помилка завантаження';
        this.loading     = false;
        this.listLoading = false;
      }
    });
  }

  get totalActive(): number { return this.specializations.filter(s => s.is_active).length; }

  openCreate(): void {
    this.modalSpec    = null;
    this.formName     = '';
    this.formKey      = '';
    this.formStatus   = 'active';
    this.formSortOrder = this.specializations.length + 1;
    this.keyAutoSync  = true;
    this.modalError   = '';
    this.showModal    = true;
  }

  openEdit(s: AdminSpecialization, event: Event): void {
    event.stopPropagation();
    this.modalSpec    = s;
    this.formName     = s.name;
    this.formKey      = s.key;
    this.formStatus   = s.is_active ? 'active' : 'inactive';
    this.formSortOrder = s.sort_order;
    this.keyAutoSync  = false;
    this.modalError   = '';
    this.showModal    = true;
  }

  closeModal(): void { this.showModal = false; this.modalSpec = null; }

  onNameInput(): void {
    if (this.keyAutoSync && !this.modalSpec) {
      this.formKey = this.formName
        .toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .substring(0, 50);
    }
  }

  onKeyInput(): void {
    this.keyAutoSync = false;
  }

  save(): void {
    this.saving     = true;
    this.modalError = '';

    const payload: any = {
      name:       this.formName.trim(),
      key:        this.formKey.trim(),
      is_active:  this.formStatus === 'active',
      sort_order: this.formSortOrder || 0,
    };

    const obs = this.modalSpec
      ? this.adminApi.updateSpecialization(this.modalSpec.id, payload)
      : this.adminApi.createSpecialization(payload);

    obs.subscribe({
      next: (res: any) => {
        if (this.modalSpec) {
          const idx = this.specializations.findIndex(s => s.id === this.modalSpec!.id);
          if (idx !== -1) this.specializations[idx] = res.specialization;
        } else {
          this.specializations.push(res.specialization);
          this.specializations.sort((a, b) => a.sort_order - b.sort_order || a.name.localeCompare(b.name, 'uk'));
        }
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

  delete(): void {
    if (!this.modalSpec) return;
    if (!confirm(`Видалити спеціалізацію «${this.modalSpec.name}»?`)) return;
    this.deleting = true;
    this.adminApi.deleteSpecialization(this.modalSpec.id).subscribe({
      next: () => {
        this.specializations = this.specializations.filter(s => s.id !== this.modalSpec!.id);
        this.deleting = false;
        this.closeModal();
      },
      error: (err: any) => {
        this.modalError = err?.error?.error || 'Помилка видалення';
        this.deleting = false;
      }
    });
  }
}
