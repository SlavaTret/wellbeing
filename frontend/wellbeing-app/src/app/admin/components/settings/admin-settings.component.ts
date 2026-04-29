import { Component, OnInit } from '@angular/core';
import { AdminApiService } from '../../services/admin-api.service';

@Component({
  selector: 'app-admin-settings',
  templateUrl: './admin-settings.component.html',
  styleUrls: ['./admin-settings.component.css']
})
export class AdminSettingsComponent implements OnInit {
  loading  = true;
  saving   = false;
  saved    = false;
  error    = '';

  showSecret = false;
  copied     = false;

  form = {
    app_url:              '',
    google_client_id:     '',
    google_client_secret: '',
  };

  get redirectUri(): string {
    const base = (this.form.app_url || 'http://localhost:4200').replace(/\/$/, '');
    return base + '/api/v1/google/callback';
  }

  googleConfigured = false;

  // ── Payment settings ────────────────────────────────────────
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
        this.form.app_url              = data.app_url              ?? '';
        this.form.google_client_id     = data.google_client_id     ?? '';
        this.form.google_client_secret = data.google_client_secret ?? '';
        this.googleConfigured          = data.google_configured    ?? false;
        this.loading = false;
      },
      error: () => { this.loading = false; }
    });

    this.adminApi.getPaymentSettings().subscribe({
      next: (data: any) => {
        this.paymentForm.active_gateway    = data.active_gateway    ?? 'liqpay';
        this.paymentForm.liqpay_public_key = data.liqpay_public_key ?? '';
        this.paymentForm.liqpay_private_key= data.liqpay_private_key ?? '';
        this.paymentForm.uapay_merchant_key= data.uapay_merchant_key ?? '';
        this.paymentForm.uapay_secret_key  = data.uapay_secret_key  ?? '';
        this.paymentForm.uapay_api_url     = data.uapay_api_url     ?? 'https://api.uapay.ua';
        this.paymentLoading = false;
      },
      error: () => { this.paymentLoading = false; }
    });
  }

  copyRedirectUri(): void {
    navigator.clipboard.writeText(this.redirectUri).then(() => {
      this.copied = true;
      setTimeout(() => this.copied = false, 2000);
    }).catch(() => {});
  }

  saveGoogle(): void {
    this.saving = true;
    this.saved  = false;
    this.error  = '';

    this.adminApi.saveAdminSettings({
      app_url:              this.form.app_url,
      google_client_id:     this.form.google_client_id,
      google_client_secret: this.form.google_client_secret,
    }).subscribe({
      next: () => {
        this.saving = false;
        this.saved  = true;
        this.googleConfigured = !!(this.form.google_client_id && this.form.google_client_secret);
        setTimeout(() => this.saved = false, 3000);
      },
      error: (err: any) => {
        this.saving = false;
        this.error  = err?.error?.error || 'Помилка збереження';
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
