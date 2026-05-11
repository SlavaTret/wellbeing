import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from '../../../services/api/api.service';
import { CompanyBranding } from '../../../services/branding/branding.service';
import { TranslateService } from '@ngx-translate/core';
import { LangService, Lang } from '../../../services/lang/lang.service';
import { RecaptchaService } from '../../../services/recaptcha/recaptcha.service';

@Component({
  selector: 'app-register',
  templateUrl: './register.component.html',
  styleUrls: ['./register.component.css']
})
export class RegisterComponent implements OnInit {
  firstName = '';
  lastName = '';
  email = '';
  password = '';
  companyId: number | null = null;
  companies: CompanyBranding[] = [];
  loading = false;
  loadingCompanies = true;
  error = '';
  showPassword = false;
  acceptedTerms = false;

  legalModal: 'terms' | 'privacy' | null = null;
  private portalSettings: any = {};

  get termsContent():   string { const l = this.translate.currentLang || 'uk'; return this.portalSettings['terms_of_service_' + l] || this.portalSettings['terms_of_service_uk'] || ''; }
  get privacyContent(): string { const l = this.translate.currentLang || 'uk'; return this.portalSettings['privacy_policy_' + l]   || this.portalSettings['privacy_policy_uk']   || ''; }

  constructor(
    private api: ApiService,
    private router: Router,
    private translate: TranslateService,
    private langService: LangService,
    private recaptcha: RecaptchaService
  ) {}

  ngOnInit(): void {
    const saved = localStorage.getItem('wb_lang');
    if (!saved) {
      const browser = navigator.language || '';
      let detected: Lang = 'uk';
      if (browser.startsWith('ru')) {
        detected = 'ru';
      } else if (browser.startsWith('en')) {
        detected = 'en';
      }
      this.langService.use(detected);
    }

    this.api.getPortalSettings().subscribe({
      next: (s: any) => { this.portalSettings = s; }
    });

    this.api.getCompanies().subscribe({
      next: (list: CompanyBranding[]) => {
        this.companies = list || [];
        this.loadingCompanies = false;
      },
      error: () => {
        this.loadingCompanies = false;
      }
    });
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
    // Shuffle
    pwd = pwd.split('').sort(() => Math.random() - 0.5).join('');

    this.password = pwd;
    this.showPassword = true;
  }

  togglePasswordVisibility(): void {
    this.showPassword = !this.showPassword;
  }

  async submit(): Promise<void> {
    if (!this.firstName || !this.lastName || !this.email || !this.password) {
      this.error = 'Заповніть усі поля';
      return;
    }
    if (!this.companyId) {
      this.error = 'Оберіть компанію';
      return;
    }
    if (this.password.length < 8) {
      this.error = 'Пароль має бути не менше 8 символів';
      return;
    }
    if (!this.acceptedTerms) {
      this.error = 'Необхідно погодитись з умовами користування сервісом';
      return;
    }
    this.loading = true;
    this.error = '';

    const recaptchaToken = await this.recaptcha.execute('register');

    this.api.register({
      email: this.email,
      password: this.password,
      firstName: this.firstName,
      lastName: this.lastName,
      companyId: this.companyId,
      acceptedTerms: true,
      recaptchaToken
    }).subscribe({
      next: () => this.router.navigate(['/dashboard']),
      error: (err) => {
        this.loading = false;
        this.error = err?.error?.message || err?.error?.error || 'Помилка реєстрації. Спробуйте ще раз.';
      }
    });
  }
}
