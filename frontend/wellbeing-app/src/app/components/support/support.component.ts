import { Component } from '@angular/core';
import { ApiService } from '../../services/api/api.service';

@Component({
  selector: 'app-support',
  templateUrl: './support.component.html',
  styleUrls: ['./support.component.css']
})
export class SupportComponent {
  title = '';
  msg = '';
  contactMethod = 'email';
  sending = false;
  success = false;
  error = '';

  readonly contactMethods = [
    { id: 'email', label: 'Email' },
    { id: 'phone', label: 'Телефон' },
  ];

  constructor(private api: ApiService) {}

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
