import { Component, OnInit } from '@angular/core';
import { UserService } from '../../services/user/user.service';
import { ApiService } from '../../services/api/api.service';
import { TranslateService } from '@ngx-translate/core';

@Component({
  selector: 'app-profile',
  templateUrl: './profile.component.html',
  styleUrls: ['./profile.component.css']
})
export class ProfileComponent implements OnInit {
  editing = false;
  saving = false;
  saved = false;
  loading = true;
  error = '';

  avatarUploading = false;
  avatarError = '';
  termsSaving = false;

  legalModal: 'terms' | 'privacy' | null = null;

  // Change password modal
  pwdModalOpen = false;
  pwdModalVisible = false;
  pwdOld = '';
  pwdNew = '';
  pwdConfirm = '';
  pwdShowOld = false;
  pwdShowNew = false;
  pwdSaving = false;
  pwdError = '';
  pwdSuccess = false;
  private portalSettings: any = {};

  get termsContent():   string { const l = this.translate.currentLang || 'uk'; return this.portalSettings['terms_of_service_' + l] || this.portalSettings['terms_of_service_uk'] || ''; }
  get privacyContent(): string { const l = this.translate.currentLang || 'uk'; return this.portalSettings['privacy_policy_' + l]   || this.portalSettings['privacy_policy_uk']   || ''; }

  companyName = '';

  form = {
    firstName: '',
    lastName: '',
    phone: '',
    email: '',
    acceptedTerms: false
  };

  constructor(public userService: UserService, private api: ApiService, private translate: TranslateService) {}

  ngOnInit(): void {
    this.api.getPortalSettings().subscribe({
      next: (s: any) => { this.portalSettings = s; }
    });

    this.userService.user$.subscribe(user => {
      if (user) {
        this.loading = false;
        this.companyName = user.company_name ?? user.company_branding?.name ?? user.company ?? '';
        this.form = {
          firstName:    user.first_name   ?? '',
          lastName:     user.last_name    ?? '',
          phone:        user.phone        ?? '',
          email:        user.email        ?? '',
          acceptedTerms: !!user.accepted_terms
        };
      }
    });
  }

  get avatarLetter(): string {
    return (this.form.firstName || this.userService.current?.email || 'U').charAt(0).toUpperCase();
  }

  get userId(): string {
    return this.userService.current?.id ?? '—';
  }

  startEdit(): void { this.editing = true; this.saved = false; this.error = ''; }

  cancelEdit(): void {
    this.editing = false;
    const user = this.userService.current;
    if (user) {
      this.form = {
        firstName:    user.first_name   ?? '',
        lastName:     user.last_name    ?? '',
        phone:        user.phone        ?? '',
        email:        user.email        ?? '',
        acceptedTerms: !!user.accepted_terms
      };
    }
  }

  save(): void {
    this.saving = true;
    this.error = '';
    this.userService.update({
      first_name:     this.form.firstName,
      last_name:      this.form.lastName,
      phone:          this.form.phone,
      accepted_terms: this.form.acceptedTerms
    }).subscribe({
      next: () => {
        this.saving = false;
        this.editing = false;
        this.saved = true;
        setTimeout(() => this.saved = false, 3000);
      },
      error: (err) => {
        this.saving = false;
        this.error = err?.error?.message || 'Помилка збереження. Спробуйте ще раз.';
      }
    });
  }

  toggleTerms(): void {
    if (this.termsSaving) return;
    this.form.acceptedTerms = !this.form.acceptedTerms;
    this.termsSaving = true;
    this.userService.update({ accepted_terms: this.form.acceptedTerms }).subscribe({
      next: () => { this.termsSaving = false; },
      error: () => {
        this.form.acceptedTerms = !this.form.acceptedTerms; // revert on failure
        this.termsSaving = false;
      }
    });
  }

  openPwdModal(): void {
    this.pwdOld = ''; this.pwdNew = ''; this.pwdConfirm = '';
    this.pwdShowOld = false; this.pwdShowNew = false;
    this.pwdError = ''; this.pwdSuccess = false;
    this.pwdModalOpen = true;
    setTimeout(() => this.pwdModalVisible = true, 10);
  }

  closePwdModal(): void {
    this.pwdModalVisible = false;
    setTimeout(() => this.pwdModalOpen = false, 280);
  }

  generatePassword(): void {
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const lower = 'abcdefghijkmnpqrstuvwxyz';
    const digits = '23456789';
    const symbols = '!@#$%&*?';
    const all = upper + lower + digits + symbols;
    const pick = (set: string) => set[Math.floor(Math.random() * set.length)];
    let pwd = pick(upper) + pick(lower) + pick(digits) + pick(symbols);
    for (let i = 0; i < 8; i++) pwd += pick(all);
    pwd = pwd.split('').sort(() => Math.random() - 0.5).join('');
    this.pwdNew = pwd;
    this.pwdConfirm = pwd;
    this.pwdShowNew = true;
  }

  submitPwd(): void {
    this.pwdError = '';
    if (!this.pwdOld) { this.pwdError = 'Введіть поточний пароль'; return; }
    if (!this.pwdNew) { this.pwdError = 'Введіть новий пароль'; return; }
    if (this.pwdNew.length < 6) { this.pwdError = 'Мінімум 6 символів'; return; }
    if (this.pwdNew !== this.pwdConfirm) { this.pwdError = 'Паролі не співпадають'; return; }
    this.pwdSaving = true;
    this.api.changePassword(this.pwdOld, this.pwdNew).subscribe({
      next: () => {
        this.pwdSaving = false;
        this.pwdSuccess = true;
        setTimeout(() => this.closePwdModal(), 1800);
      },
      error: (err) => {
        this.pwdSaving = false;
        this.pwdError = err?.error?.error || 'Помилка зміни пароля';
      }
    });
  }

  onAvatarFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    input.value = '';

    this.avatarUploading = true;
    this.avatarError = '';
    this.api.uploadAvatar(file).subscribe({
      next: (res: any) => {
        this.avatarUploading = false;
        if (res?.user) {
          this.userService.setUser(res.user);
        }
      },
      error: (err) => {
        this.avatarUploading = false;
        this.avatarError = err?.error?.error || 'Помилка завантаження фото';
      }
    });
  }
}
