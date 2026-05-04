import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '../../services/api/api.service';

@Component({
  selector: 'app-settings',
  templateUrl: './settings.component.html',
  styleUrls: ['./settings.component.css']
})
export class SettingsComponent implements OnInit {
  activeTab: 'notifications' | 'integrations' = 'notifications';

  // ── Notifications ──────────────────────────────────────────────
  savingSettings = false;
  settings: { [key: string]: boolean } = {
    email_enabled: true, calendar_enabled: true, sms_enabled: false, reminders_enabled: true
  };

  readonly settingsList = [
    { key: 'email_enabled',     labelKey: 'settings.notifications.email_label',    descKey: 'settings.notifications.email_desc' },
    { key: 'calendar_enabled',  labelKey: 'settings.notifications.calendar_label', descKey: 'settings.notifications.calendar_desc' },
    { key: 'sms_enabled',       labelKey: 'settings.notifications.sms_label',      descKey: 'settings.notifications.sms_desc' },
    { key: 'reminders_enabled', labelKey: 'settings.notifications.reminder_label', descKey: 'settings.notifications.reminder_desc' },
  ];

  // ── Google Calendar ────────────────────────────────────────────
  googleConnected     = false;
  googleEmail: string | null = null;
  googleLoading       = false;
  googleConnecting    = false;
  googleDisconnecting = false;
  googleStatusMsg     = '';
  googleStatusOk      = false;

  constructor(
    private api: ApiService,
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.route.queryParams.subscribe(params => {
      if (params['tab'] === 'integrations') {
        this.activeTab = 'integrations';
      }
      if (params['google'] === 'connected') {
        this.googleStatusMsg = '✓ Google Calendar підключено успішно';
        this.googleStatusOk  = true;
        this.loadGoogleStatus();
      } else if (params['google'] === 'error') {
        this.googleStatusMsg = 'Помилка підключення Google. Спробуйте ще раз.';
        this.googleStatusOk  = false;
      }
      if (params['google'] || params['tab']) {
        this.router.navigate([], { replaceUrl: true, queryParams: {} });
      }
    });

    this.api.getNotificationSettings().subscribe({
      next: (s: any) => { if (s) this.settings = { ...this.settings, ...s }; },
      error: () => {}
    });

    this.loadGoogleStatus();
  }

  setTab(tab: 'notifications' | 'integrations'): void {
    this.activeTab = tab;
  }

  toggleSetting(key: string): void {
    (this.settings as any)[key] = !(this.settings as any)[key];
    this.savingSettings = true;
    this.api.saveNotificationSettings(this.settings).subscribe({
      next: (s: any) => {
        if (s) this.settings = { ...this.settings, ...s };
        this.savingSettings = false;
      },
      error: () => { this.savingSettings = false; }
    });
  }

  private loadGoogleStatus(): void {
    this.googleLoading = true;
    this.api.getGoogleStatus().subscribe({
      next: (res) => {
        this.googleConnected = res.connected;
        this.googleEmail     = res.google_email;
        this.googleLoading   = false;
      },
      error: () => { this.googleLoading = false; }
    });
  }

  connectGoogle(): void {
    this.googleConnecting = true;
    this.api.getGoogleAuthUrl().subscribe({
      next: (res) => {
        this.googleConnecting = false;
        window.location.href = res.url;
      },
      error: (err: any) => {
        this.googleConnecting = false;
        this.googleStatusMsg = err?.error?.error || 'Помилка: Google OAuth не налаштовано';
        this.googleStatusOk  = false;
      }
    });
  }

  disconnectGoogle(): void {
    this.googleDisconnecting = true;
    this.api.disconnectGoogle().subscribe({
      next: () => {
        this.googleConnected     = false;
        this.googleEmail         = null;
        this.googleDisconnecting = false;
        this.googleStatusMsg     = 'Google Calendar відключено';
        this.googleStatusOk      = false;
      },
      error: () => { this.googleDisconnecting = false; }
    });
  }
}
