import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
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
    private router: Router,
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
    const map: Record<string, string> = {
      appointment_confirmed: 'check',
      reminder_12h:          'clock',
      reminder_1h:           'clock',
      refund_processed:      'card',
      review_request:        'star',
      appointment_reminder:  'bell',
      payment_reminder:      'card',
      system:                'info',
    };
    return map[n.type] ?? n.icon ?? 'bell';
  }

  colorFor(n: any): string {
    if (n.is_read) return 'var(--text-muted)';
    const map: Record<string, string> = {
      appointment_confirmed: '#2DB928',
      reminder_12h:          '#1565C0',
      reminder_1h:           '#C77800',
      refund_processed:      '#1565C0',
      review_request:        '#6A1B9A',
      system:                '#6B8879',
    };
    return map[n.type] ?? 'var(--green)';
  }

  bgFor(n: any): string {
    if (n.is_read) return '#F2F7F3';
    const map: Record<string, string> = {
      appointment_confirmed: '#E8F5E9',
      reminder_12h:          '#E3F2FD',
      reminder_1h:           '#FFF8E1',
      refund_processed:      '#E3F2FD',
      review_request:        '#F3E5F5',
      system:                '#F2F7F3',
    };
    return map[n.type] ?? '#E8F5E9';
  }

  goToReview(n: any): void {
    this.markRead(n);
    this.router.navigate(['/appointments']);
  }

  isReviewRequest(n: any): boolean {
    return n.type === 'review_request';
  }
}
