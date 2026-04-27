import { Component, OnInit } from '@angular/core';
import { ApiService } from '../../services/api/api.service';
import { NotificationService } from '../../services/notification/notification.service';

@Component({
  selector: 'app-notifications',
  templateUrl: './notifications.component.html',
  styleUrls: ['./notifications.component.css']
})
export class NotificationsComponent implements OnInit {
  loading = true;
  savingSettings = false;
  markingAll = false;

  notifs: any[] = [];
  settings: { [key: string]: boolean } = { email_enabled: true, calendar_enabled: true, sms_enabled: false, reminders_enabled: true };

  readonly settingsList = [
    { key: 'email_enabled',     label: 'Email сповіщення',      desc: 'Підтвердження та нагадування на пошту' },
    { key: 'calendar_enabled',  label: 'Google Calendar',        desc: 'Автоматично додавати події в календар' },
    { key: 'sms_enabled',       label: 'SMS нагадування',        desc: 'Нагадування на номер телефону' },
    { key: 'reminders_enabled', label: 'Нагадування за 12 год',  desc: 'Push/email перед кожною консультацією' },
  ];

  constructor(private api: ApiService, private notifService: NotificationService) {}

  ngOnInit(): void {
    this.api.getNotifications().subscribe({
      next: (res: any) => {
        this.notifs = res.items ?? [];
        this.notifService.setCount(res.unread_count ?? 0);
        this.loading = false;
      },
      error: () => { this.loading = false; }
    });

    this.api.getNotificationSettings().subscribe({
      next: (s: any) => { if (s) this.settings = { ...this.settings, ...s }; },
      error: () => {}
    });
  }

  get unreadCount(): number { return this.notifs.filter(n => !n.is_read).length; }

  markRead(n: any): void {
    if (n.is_read) return;
    this.api.markNotificationAsRead(n.id).subscribe({
      next: (res: any) => {
        n.is_read = true;
        this.notifService.setCount(res.unread_count ?? 0);
      }
    });
  }

  markAllRead(): void {
    if (!this.unreadCount) return;
    this.markingAll = true;
    this.api.markAllNotificationsAsRead().subscribe({
      next: () => {
        this.notifs.forEach(n => n.is_read = true);
        this.notifService.setCount(0);
        this.markingAll = false;
      },
      error: () => { this.markingAll = false; }
    });
  }

  toggleSetting(key: string): void {
    (this.settings as any)[key] = !(this.settings as any)[key];
    this.savingSettings = true;
    this.api.saveNotificationSettings(this.settings).subscribe({
      next: (s: any) => { if (s) this.settings = { ...this.settings, ...s }; this.savingSettings = false; },
      error: () => { this.savingSettings = false; }
    });
  }

  iconFor(n: any): string {
    if (n.icon) return n.icon;
    if (n.type === 'appointment_reminder') return 'bell';
    if (n.type === 'payment_reminder')     return 'card';
    return 'check';
  }
}
