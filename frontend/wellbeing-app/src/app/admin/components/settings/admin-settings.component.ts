import { Component, ElementRef, OnInit, ViewChild } from '@angular/core';
import { AdminApiService } from '../../services/admin-api.service';

const TIMEZONES = [
  { value: 'Europe/Kyiv',    label: 'Київ (UTC+2/+3)' },
  { value: 'Europe/Warsaw',  label: 'Варшава (UTC+1/+2)' },
  { value: 'Europe/Berlin',  label: 'Берлін (UTC+1/+2)' },
  { value: 'Europe/Paris',   label: 'Париж (UTC+1/+2)' },
  { value: 'Europe/London',  label: 'Лондон (UTC+0/+1)' },
  { value: 'UTC',            label: 'UTC' },
];

const LOCALES = [
  { value: 'uk', label: 'Українська' },
  { value: 'en', label: 'English' },
];

@Component({
  selector: 'app-admin-settings',
  templateUrl: './admin-settings.component.html',
  styleUrls: ['./admin-settings.component.css']
})
export class AdminSettingsComponent implements OnInit {
  activeTab: 'portal' | 'payment' | 'integrations' | 'content' = 'portal';

  loading = true;

  timezones = TIMEZONES;
  locales   = LOCALES;

  // ── Portal tab ────────────────────────────────────────────────
  portalSaving = false;
  portalSaved  = false;
  portalError  = '';

  portalForm = {
    site_title_prefix: 'Wellbeing',
    company_name:      '',
    app_url:           '',
    timezone:          'Europe/Kyiv',
    default_locale:    'uk',
    support_phone:     '',
    support_viber_url: '',
    support_tg_url:    '',
  };

  faviconPreview:    string | null = null;
  faviconUploading = false;
  faviconError     = '';

  @ViewChild('faviconInput') faviconInput!: ElementRef<HTMLInputElement>;

  // ── Integrations tab ──────────────────────────────────────────
  googleSaving   = false;
  googleSaved    = false;
  googleError    = '';
  showSecret     = false;
  copied         = false;
  googleConfigured = false;

  googleForm = {
    google_client_id:     '',
    google_client_secret: '',
  };

  get redirectUri(): string {
    const base = (this.portalForm.app_url || 'http://localhost:4200').replace(/\/$/, '');
    return base + '/api/v1/google/callback';
  }

  // ── CRM tab ───────────────────────────────────────────────────
  crmSaving       = false;
  crmSaved        = false;
  crmError        = '';
  showCrmSecret   = false;
  crmConfigured   = false;

  crmForm = {
    creatio_base_url:      '',
    creatio_client_id:     '',
    creatio_client_secret: '',
    creatio_enabled:       false,
  };

  // ── Content tab ───────────────────────────────────────────────
  contentSaving = false;
  contentSaved  = false;
  contentError  = '';
  contentLang: 'uk' | 'en' = 'uk';

  contentForm = {
    terms_of_service_uk: '',
    terms_of_service_en: '',
    privacy_policy_uk:   '',
    privacy_policy_en:   '',
  };

  // ── Payment tab ───────────────────────────────────────────────
  paymentLoading  = true;
  paymentSaving   = false;
  paymentSaved    = false;
  paymentError    = '';
  showLiqPrivate  = false;
  showUapaySecret = false;

  paymentForm = {
    active_gateway:    'liqpay' as 'liqpay' | 'uapay',
    liqpay_public_key: '',
    liqpay_private_key:'',
    uapay_merchant_key:'',
    uapay_secret_key:  '',
    uapay_api_url:     'https://api.uapay.ua',
  };

  constructor(private adminApi: AdminApiService) {}

  ngOnInit(): void {
    this.adminApi.getAdminSettings().subscribe({
      next: (data: any) => {
        this.portalForm.site_title_prefix = data.site_title_prefix ?? 'Wellbeing';
        this.portalForm.company_name      = data.company_name      ?? '';
        this.portalForm.app_url           = data.app_url           ?? '';
        this.portalForm.timezone          = data.timezone          ?? 'Europe/Kyiv';
        this.portalForm.default_locale    = data.default_locale    ?? 'uk';
        if (data.favicon_url) this.faviconPreview = data.favicon_url;

        this.googleForm.google_client_id     = data.google_client_id     ?? '';
        this.googleForm.google_client_secret = data.google_client_secret ?? '';
        this.googleConfigured                = data.google_configured    ?? false;
        this.contentForm.terms_of_service_uk = data.terms_of_service_uk ?? '';
        this.contentForm.terms_of_service_en = data.terms_of_service_en ?? '';
        this.contentForm.privacy_policy_uk   = data.privacy_policy_uk   ?? '';
        this.contentForm.privacy_policy_en   = data.privacy_policy_en   ?? '';
        this.portalForm.support_phone        = data.support_phone        ?? '';
        this.portalForm.support_viber_url    = data.support_viber_url    ?? '';
        this.portalForm.support_tg_url       = data.support_tg_url       ?? '';
        this.crmForm.creatio_base_url        = data.creatio_base_url     ?? '';
        this.crmForm.creatio_client_id       = data.creatio_client_id    ?? '';
        this.crmForm.creatio_client_secret   = data.creatio_client_secret ?? '';
        this.crmForm.creatio_enabled         = data.creatio_enabled       ?? false;
        this.crmConfigured                   = data.creatio_configured    ?? false;
        this.loading = false;
      },
      error: () => { this.loading = false; }
    });

    this.adminApi.getPaymentSettings().subscribe({
      next: (data: any) => {
        this.paymentForm.active_gateway     = data.active_gateway     ?? 'liqpay';
        this.paymentForm.liqpay_public_key  = data.liqpay_public_key  ?? '';
        this.paymentForm.liqpay_private_key = data.liqpay_private_key ?? '';
        this.paymentForm.uapay_merchant_key = data.uapay_merchant_key ?? '';
        this.paymentForm.uapay_secret_key   = data.uapay_secret_key   ?? '';
        this.paymentForm.uapay_api_url      = data.uapay_api_url      ?? 'https://api.uapay.ua';
        this.paymentLoading = false;
      },
      error: () => { this.paymentLoading = false; }
    });
  }

  // ── Portal ────────────────────────────────────────────────────

  savePortal(): void {
    this.portalSaving = true;
    this.portalSaved  = false;
    this.portalError  = '';

    this.adminApi.saveAdminSettings({
      site_title_prefix: this.portalForm.site_title_prefix,
      company_name:      this.portalForm.company_name,
      app_url:           this.portalForm.app_url,
      timezone:          this.portalForm.timezone,
      default_locale:    this.portalForm.default_locale,
      support_phone:     this.portalForm.support_phone,
      support_viber_url: this.portalForm.support_viber_url,
      support_tg_url:    this.portalForm.support_tg_url,
    }).subscribe({
      next: () => {
        this.portalSaving = false;
        this.portalSaved  = true;
        setTimeout(() => this.portalSaved = false, 3000);
      },
      error: (err: any) => {
        this.portalSaving = false;
        this.portalError  = err?.error?.error || 'Помилка збереження';
      }
    });
  }

  triggerFaviconInput(): void {
    this.faviconInput?.nativeElement.click();
  }

  onFaviconChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file  = input.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = e => { this.faviconPreview = e.target?.result as string; };
    reader.readAsDataURL(file);

    this.faviconUploading = true;
    this.faviconError     = '';
    this.adminApi.uploadFavicon(file).subscribe({
      next: (res: any) => {
        this.faviconUploading = false;
        if (res.favicon_url) this.faviconPreview = res.favicon_url;
      },
      error: (err: any) => {
        this.faviconUploading = false;
        this.faviconError     = err?.error?.error || 'Помилка завантаження';
      }
    });
  }

  // ── Integrations ──────────────────────────────────────────────

  copyRedirectUri(): void {
    navigator.clipboard.writeText(this.redirectUri).then(() => {
      this.copied = true;
      setTimeout(() => this.copied = false, 2000);
    }).catch(() => {});
  }

  saveGoogle(): void {
    this.googleSaving = true;
    this.googleSaved  = false;
    this.googleError  = '';

    this.adminApi.saveAdminSettings({
      google_client_id:     this.googleForm.google_client_id,
      google_client_secret: this.googleForm.google_client_secret,
    }).subscribe({
      next: () => {
        this.googleSaving = false;
        this.googleSaved  = true;
        this.googleConfigured = !!(this.googleForm.google_client_id && this.googleForm.google_client_secret);
        setTimeout(() => this.googleSaved = false, 3000);
      },
      error: (err: any) => {
        this.googleSaving = false;
        this.googleError  = err?.error?.error || 'Помилка збереження';
      }
    });
  }

  // ── Payment ───────────────────────────────────────────────────

  saveCrm(): void {
    this.crmSaving = true;
    this.crmSaved  = false;
    this.crmError  = '';

    this.adminApi.saveAdminSettings({
      creatio_base_url:      this.crmForm.creatio_base_url,
      creatio_client_id:     this.crmForm.creatio_client_id,
      creatio_client_secret: this.crmForm.creatio_client_secret,
      creatio_enabled:       this.crmForm.creatio_enabled,
    }).subscribe({
      next: () => {
        this.crmSaving = false;
        this.crmSaved  = true;
        this.crmConfigured = !!(this.crmForm.creatio_base_url && this.crmForm.creatio_client_id);
        setTimeout(() => this.crmSaved = false, 3000);
      },
      error: (err: any) => {
        this.crmSaving = false;
        this.crmError  = err?.error?.error || 'Помилка збереження';
      }
    });
  }

  saveContent(): void {
    this.contentSaving = true;
    this.contentSaved  = false;
    this.contentError  = '';

    this.adminApi.saveAdminSettings({
      terms_of_service_uk: this.contentForm.terms_of_service_uk,
      terms_of_service_en: this.contentForm.terms_of_service_en,
      privacy_policy_uk:   this.contentForm.privacy_policy_uk,
      privacy_policy_en:   this.contentForm.privacy_policy_en,
    }).subscribe({
      next: () => {
        this.contentSaving = false;
        this.contentSaved  = true;
        setTimeout(() => this.contentSaved = false, 3000);
      },
      error: (err: any) => {
        this.contentSaving = false;
        this.contentError  = err?.error?.error || 'Помилка збереження';
      }
    });
  }

  savePayment(): void {
    this.paymentSaving = true;
    this.paymentSaved  = false;
    this.paymentError  = '';

    this.adminApi.savePaymentSettings(this.paymentForm).subscribe({
      next: () => {
        this.paymentSaving = false;
        this.paymentSaved  = true;
        setTimeout(() => this.paymentSaved = false, 3000);
      },
      error: (err: any) => {
        this.paymentSaving = false;
        this.paymentError  = err?.error?.error || 'Помилка збереження';
      }
    });
  }
}
