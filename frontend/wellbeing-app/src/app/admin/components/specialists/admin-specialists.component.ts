import { Component, OnInit } from '@angular/core';
import { AdminApiService } from '../../services/admin-api.service';

interface AdminSpecialist {
  id: number;
  name: string;
  type: string;
  type_name: string;
  bio: string;
  experience_years: number;
  rating: number;
  reviews_count: number;
  sessions_count: number;
  categories: string[];
  categories_str: string;
  avatar_initials: string;
  price: number;
  is_active: boolean;
  created_at: string | number;
}

interface SpecialistForm {
  name: string;
  type: string;
  bio: string;
  experience_years: number | '';
  price: number | '';
  status: 'active' | 'inactive';
}

@Component({
  selector: 'app-admin-specialists',
  templateUrl: './admin-specialists.component.html',
  styleUrls: ['./admin-specialists.component.css']
})
export class AdminSpecialistsComponent implements OnInit {
  specialists: AdminSpecialist[] = [];
  loading = true;
  error   = '';
  search  = '';
  searchTimer: any;

  // Modal state
  showModal     = false;
  modalSpec: AdminSpecialist | null = null;
  form: SpecialistForm = this.emptyForm();
  saving        = false;
  modalError    = '';

  // Categories
  formCats: string[]   = [];
  allCats: string[]    = [];


  constructor(private adminApi: AdminApiService) {}

  ngOnInit(): void {
    this.loadSpecialists();
    this.adminApi.getAdminCategories().subscribe({
      next: (list: any[]) => {
        this.allCats = (list || [])
          .filter((c: any) => c.status === 'active')
          .map((c: any) => c.name)
          .sort();
      }
    });
  }

  loadSpecialists(): void {
    this.loading = true;
    this.error   = '';
    this.adminApi.getAdminSpecialists(this.search).subscribe({
      next: (list: any[]) => {
        this.specialists = list || [];
        this.loading = false;
      },
      error: (err: any) => {
        this.error   = err?.error?.error || 'Помилка завантаження';
        this.loading = false;
      }
    });
  }

  onSearchInput(): void {
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => this.loadSpecialists(), 350);
  }


  get totalActive(): number { return this.specialists.filter(s => s.is_active).length; }

  get availableCats(): string[] {
    return this.allCats.filter(c => !this.formCats.includes(c));
  }

  // ── Modal ──────────────────────────────────────────────────────────

  openCreate(): void {
    this.modalSpec  = null;
    this.form       = this.emptyForm();
    this.formCats   = [];
    this.modalError = '';
    this.showModal    = true;
  }

  openEdit(s: AdminSpecialist): void {
    this.modalSpec    = s;
    const validTypes = ['psychologist', 'therapist', 'coach'];
    this.form = {
      name:             s.name,
      type:             validTypes.includes(s.type) ? s.type : 'psychologist',
      bio:              s.bio,
      experience_years: s.experience_years || '',
      price:            s.price || '',
      status:           s.is_active ? 'active' : 'inactive'
    };
    this.formCats   = [...s.categories];
    this.modalError = '';
    this.showModal  = true;
  }

  closeModal(): void { this.showModal = false; this.modalSpec = null; }

  saveModal(): void {
    this.saving     = true;
    this.modalError = '';

    const payload: any = {
      name:             this.form.name,
      type:             this.form.type,
      bio:              this.form.bio,
      experience_years: this.form.experience_years || 0,
      price:            this.form.price || 0,
      categories:       this.formCats.join(', '),
      is_active:        this.form.status === 'active'
    };

    const obs = this.modalSpec
      ? this.adminApi.updateSpecialist(this.modalSpec.id, payload)
      : this.adminApi.createSpecialist(payload);

    obs.subscribe({
      next: (res: any) => {
        if (this.modalSpec) {
          const idx = this.specialists.findIndex(s => s.id === this.modalSpec!.id);
          if (idx !== -1) this.specialists[idx] = res.specialist;
        } else {
          this.specialists.unshift(res.specialist);
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

  // ── Categories ────────────────────────────────────────────────────

  addCategory(cat: string): void {
    const c = cat.trim();
    if (c && !this.formCats.includes(c)) { this.formCats = [...this.formCats, c]; }
  }

  removeCategory(cat: string): void {
    this.formCats = this.formCats.filter(c => c !== cat);
  }

  // ── Helpers ───────────────────────────────────────────────────────

  private emptyForm(): SpecialistForm {
    return { name: '', type: 'psychologist', bio: '', experience_years: '', price: '', status: 'active' };
  }

  initials(name: string): string {
    return (name || '').trim().split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('');
  }

  ratingDisplay(r: number): string {
    return r ? r.toFixed(1) + '★' : '—';
  }

  typeClass(type: string): string {
    return { psychologist: 'badge--psychologist', therapist: 'badge--therapist', coach: 'badge--coach' }[type] || 'badge--psychologist';
  }

  typeName(type: string): string {
    return { psychologist: 'Психолог', therapist: 'Психотерапевт', coach: 'Коуч' }[type] || type;
  }

  expLabel(y: number | ''): string {
    if (!y) return '—';
    const n = Number(y);
    if (n === 1) return '1 рік';
    if (n < 5)   return `${n} роки`;
    return `${n} років`;
  }
}
