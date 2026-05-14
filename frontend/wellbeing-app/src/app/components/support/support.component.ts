import { Component, OnInit } from '@angular/core';
import { ApiService } from '../../services/api/api.service';

@Component({
  selector: 'app-support',
  templateUrl: './support.component.html',
  styleUrls: ['./support.component.css']
})
export class SupportComponent implements OnInit {
  title = '';
  msg = '';
  contactMethod = 'email';
  sending = false;
  success = false;
  error = '';

  supportPhone    = '';
  supportTgUrl    = '';
  supportViberUrl = '';

  readonly contactMethods = [
    { id: 'email', label: 'Email' },
    { id: 'phone', label: 'Телефон' },
  ];

  constructor(private api: ApiService) {}

  ngOnInit(): void {
    this.api.getPortalSettings().subscribe({
      next: (s: any) => {
        this.supportPhone    = s.support_phone     ?? '';
        this.supportTgUrl    = s.support_tg_url    ?? '';
        this.supportViberUrl = s.support_viber_url ?? '';
      }
    });
  }

  get canSend(): boolean {
    return this.title.trim().length > 0 && this.msg.trim().length > 0 && !this.sending;
  }

  send(): void {
    if (!this.canSend) return;
    this.sending = true;
    this.error = '';
    this.api.createSupportTicket({
      title:          this.title.trim(),
      message:        this.msg.trim(),
      contact_method: this.contactMethod,
    }).subscribe({
      next: () => {
        this.sending = false;
        this.success = true;
      },
      error: () => {
        this.sending = false;
        this.error = 'Не вдалось надіслати звернення. Спробуйте ще раз.';
      }
    });
  }

  reset(): void {
    this.title = '';
    this.msg = '';
    this.contactMethod = 'email';
    this.success = false;
    this.error = '';
  }
}
