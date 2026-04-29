import { Component, OnInit } from '@angular/core';
import { ApiService } from '../../services/api/api.service';
import { NotificationService } from '../../services/notification/notification.service';

@Component({
  selector: 'app-notifications',
  templateUrl: './notifications.component.html',
  styleUrls: ['./notifications.component.css']
})
export class NotificationsComponent implements OnInit {
  loading    = true;
  markingAll = false;
  notifs: any[] = [];

  constructor(
    private api: ApiService,
    private notifService: NotificationService,
  ) {}

  ngOnInit(): void {
    this.api.getNotifications().subscribe({
      next: (res: any) => {
        this.notifs = res.items ?? [];
        this.notifService.setCount(res.unread_count ?? 0);
        this.loading = false;
      },
      error: () => { this.loading = false; }
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

  iconFor(n: any): string {
    if (n.icon) return n.icon;
    if (n.type === 'appointment_reminder') return 'bell';
    if (n.type === 'payment_reminder')     return 'card';
    return 'check';
  }
}
