import { Component, ElementRef, OnInit, ViewChild } from '@angular/core';
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
  avatar_url?: string | null;
  is_active: boolean;
  created_at: string | number;
  email?: string;
  user_id?: number | null;
  linked_email?: string | null;
  creatio_contact_id?: string | null;
}

interface SpecialistForm {
  name: string;
  type: string;
  bio: string;
  experience_years: number | '';
  status: 'active' | 'inactive';
  email: string;
  creatio_contact_id: string;
}

interface Specialization {
  id: number;
  name: string;
  key: string;
  is_active: boolean;
}

@Component({
  selector: 'app-admin-specialists',
  templateUrl: './admin-specialists.component.html',
  styleUrls: ['./admin-specialists.component.css']
})
export class AdminSpecialistsComponent implements OnInit {
  specialists: AdminSpecialist[] = [];
  specializations: Specialization[] = [];
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

  // Avatar
  @ViewChild('avatarInput') avatarInput!: ElementRef<HTMLInputElement>;
  avatarFile: File | null    = null;
  avatarPreview: string | null = null;

  // Categories
  formCats: string[]   = [];
  allCats: string[]    = [];

  // Link account
  linkEmail    = '';
  linkPassword = '';
  linking      = false;
  unlinking    = false;
  linkError    = '';
  linkSuccess  = '';

  constructor(private adminApi: AdminApiService) {}

  ngOnInit(): void {
    this.loadSpecialists();
    this.adminApi.getAdminSpecializations().subscribe({
      next: (list: any[]) => {
        this.specializations = (list || []).filter((s: any) => s.is_active);
      }
    });
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
    this.modalSpec    = null;
    this.form         = this.emptyForm();
    this.formCats     = [];
    this.modalError   = '';
    this.avatarFile   = null;
    this.avatarPreview = null;
    this.showModal    = true;
  }

  openEdit(s: AdminSpecialist): void {
    this.modalSpec    = s;
    this.form = {
      name:               s.name,
      type:               s.type || (this.specializations[0]?.key ?? 'psychologist'),
      bio:                s.bio,
      experience_years:   s.experience_years || '',
      status:             s.is_active ? 'active' : 'inactive',
      email:              s.email || '',
      creatio_contact_id: s.creatio_contact_id || ''
    };
    this.formCats     = [...s.categories];
    this.modalError   = '';
    this.avatarFile   = null;
    this.avatarPreview = null;
    this.linkEmail    = '';
    this.linkPassword = '';
    this.linkError    = '';
    this.linkSuccess  = '';
    this.showModal    = true;
  }

  closeModal(): void { this.showModal = false; this.modalSpec = null; }

  saveModal(): void {
    this.saving     = true;
    this.modalError = '';

    const payload: any = {
      name:               this.form.name,
      type:               this.form.type,
      bio:                this.form.bio,
      experience_years:   this.form.experience_years || 0,
      categories:         this.formCats.join(', '),
      is_active:          this.form.status === 'active',
      email:              this.form.email || null,
      creatio_contact_id: this.form.creatio_contact_id?.trim() || null
    };

    const obs = this.modalSpec
      ? this.adminApi.updateSpecialist(this.modalSpec.id, payload)
      : this.adminApi.createSpecialist(payload);

    obs.subscribe({
      next: (res: any) => {
        const specialist: AdminSpecialist = res.specialist;
        if (this.avatarFile) {
          this.adminApi.uploadSpecialistAvatar(specialist.id, this.avatarFile).subscribe({
            next: (up: any) => {
              specialist.avatar_url = up.avatar_url;
              this.finishSave(specialist);
            },
            error: () => this.finishSave(specialist)
          });
        } else {
          this.finishSave(specialist);
        }
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

  private finishSave(specialist: AdminSpecialist): void {
    if (this.modalSpec) {
      const idx = this.specialists.findIndex(s => s.id === this.modalSpec!.id);
      if (idx !== -1) this.specialists[idx] = specialist;
    } else {
      this.specialists.unshift(specialist);
    }
    this.saving = false;
    this.closeModal();
  }

  // ── Avatar ────────────────────────────────────────────────────────

  triggerAvatarInput(): void {
    this.avatarInput.nativeElement.click();
  }

  onAvatarChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file  = input.files?.[0];
    if (!file) return;
    this.avatarFile = file;
    const reader = new FileReader();
    reader.onload = (e) => { this.avatarPreview = e.target?.result as string; };
    reader.readAsDataURL(file);
    input.value = '';
  }

  get avatarSrc(): string | null {
    if (this.avatarPreview) return this.avatarPreview;
    if (this.modalSpec?.avatar_url) return this.modalSpec.avatar_url;
    return null;
  }

  // ── Categories ────────────────────────────────────────────────────

  addCategory(cat: string): void {
    const c = cat.trim();
    if (c && !this.formCats.includes(c)) { this.formCats = [...this.formCats, c]; }
  }

  removeCategory(cat: string): void {
    this.formCats = this.formCats.filter(c => c !== cat);
  }

  // ── Link account ──────────────────────────────────────────────────

  linkAccount(): void {
    if (!this.modalSpec || this.linking) return;
    this.linkError   = '';
    this.linkSuccess = '';
    this.linking     = true;
    this.adminApi.linkSpecialistUser(this.modalSpec.id, { email: this.linkEmail, password: this.linkPassword }).subscribe({
      next: (res: any) => {
        this.modalSpec!.user_id      = res.user_id;
        this.modalSpec!.linked_email = res.email;
        const idx = this.specialists.findIndex(s => s.id === this.modalSpec!.id);
        if (idx !== -1) {
          this.specialists[idx] = { ...this.specialists[idx], user_id: res.user_id, linked_email: res.email };
        }
        this.linkEmail   = '';
        this.linkPassword = '';
        this.linkSuccess = 'Доступ надано: ' + res.email;
        this.linking     = false;
      },
      error: (err: any) => {
        this.linkError = err?.error?.error || 'Помилка';
        this.linking   = false;
      }
    });
  }

  unlinkAccount(): void {
    if (!this.modalSpec || this.unlinking) return;
    this.linkError   = '';
    this.linkSuccess = '';
    this.unlinking   = true;
    this.adminApi.unlinkSpecialistUser(this.modalSpec.id).subscribe({
      next: () => {
        this.modalSpec!.user_id      = null;
        this.modalSpec!.linked_email = null;
        const idx = this.specialists.findIndex(s => s.id === this.modalSpec!.id);
        if (idx !== -1) {
          this.specialists[idx] = { ...this.specialists[idx], user_id: null, linked_email: null };
        }
        this.linkSuccess = 'Доступ відкликано';
        this.unlinking   = false;
      },
      error: (err: any) => {
        this.linkError  = err?.error?.error || 'Помилка';
        this.unlinking  = false;
      }
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────

  private emptyForm(): SpecialistForm {
    return { name: '', type: '', bio: '', experience_years: '', status: 'active', email: '', creatio_contact_id: '' };
  }

  initials(name: string): string {
    return (name || '').trim().split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('');
  }

  ratingDisplay(r: number): string {
    return r ? r.toFixed(1) + '★' : '—';
  }

  typeClass(type: string): string {
    const map: { [k: string]: string } = {
      psychologist: 'badge--psychologist',
      therapist:    'badge--therapist',
      coach:        'badge--coach'
    };
    return map[type] || 'badge--generic';
  }

  typeName(s: AdminSpecialist): string {
    return s.type_name || s.type || '—';
  }

  expLabel(y: number | ''): string {
    if (!y) return '—';
    const n = Number(y);
    if (n === 1) return '1 рік';
    if (n < 5)   return `${n} роки`;
    return `${n} років`;
  }
}
