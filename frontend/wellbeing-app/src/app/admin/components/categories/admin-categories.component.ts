import { Component, OnInit } from '@angular/core';
import { AdminApiService } from '../../services/admin-api.service';

interface AdminCategory {
  id: number;
  name: string;
  status: string;
  specialist_count: number;
  sessions_count: number;
  created_at: string | number;
}

@Component({
  selector: 'app-admin-categories',
  templateUrl: './admin-categories.component.html',
  styleUrls: ['./admin-categories.component.css']
})
export class AdminCategoriesComponent implements OnInit {
  categories: AdminCategory[] = [];
  loading     = true;
  listLoading = false;
  error       = '';
  search      = '';
  searchTimer: any;

  showModal   = false;
  modalCat: AdminCategory | null = null;
  formName    = '';
  formStatus  = 'active';
  saving      = false;
  deleting    = false;
  modalError  = '';

  constructor(private adminApi: AdminApiService) {}

  ngOnInit(): void { this.load(); }

  load(soft = false): void {
    if (soft) { this.listLoading = true; }
    else      { this.loading = true; this.error = ''; }

    this.adminApi.getAdminCategories(this.search).subscribe({
      next: (list: any[]) => {
        this.categories  = list || [];
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

  onSearchInput(): void {
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => this.load(true), 350);
  }

  get totalActive(): number { return this.categories.filter(c => c.status === 'active').length; }

  openCreate(): void {
    this.modalCat   = null;
    this.formName   = '';
    this.formStatus = 'active';
    this.modalError = '';
    this.showModal  = true;
  }

  openEdit(c: AdminCategory, event: Event): void {
    event.stopPropagation();
    this.modalCat   = c;
    this.formName   = c.name;
    this.formStatus = c.status;
    this.modalError = '';
    this.showModal  = true;
  }

  closeModal(): void { this.showModal = false; this.modalCat = null; }

  save(): void {
    this.saving     = true;
    this.modalError = '';

    const payload = { name: this.formName.trim(), status: this.formStatus };

    const obs = this.modalCat
      ? this.adminApi.updateCategory(this.modalCat.id, payload)
      : this.adminApi.createCategory(payload);

    obs.subscribe({
      next: (res: any) => {
        if (this.modalCat) {
          const idx = this.categories.findIndex(c => c.id === this.modalCat!.id);
          if (idx !== -1) this.categories[idx] = res.category;
        } else {
          this.categories.push(res.category);
          this.categories.sort((a, b) => a.name.localeCompare(b.name, 'uk'));
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
    if (!this.modalCat) return;
    if (!confirm(`Видалити категорію «${this.modalCat.name}»?\nВона буде видалена з усіх спеціалістів.`)) return;
    this.deleting = true;
    this.adminApi.deleteCategory(this.modalCat.id).subscribe({
      next: () => {
        this.categories = this.categories.filter(c => c.id !== this.modalCat!.id);
        this.deleting = false;
        this.closeModal();
      },
      error: () => { this.deleting = false; }
    });
  }
}
