import { Component, OnInit } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { ApiService } from '../../services/api/api.service';

@Component({
  selector: 'app-payments',
  templateUrl: './payments.component.html',
  styleUrls: ['./payments.component.css']
})
export class PaymentsComponent implements OnInit {
  loading = true;
  paying = false;

  payments: any[] = [];
  pending: any = null;

  constructor(private api: ApiService, private translate: TranslateService) {}

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading = true;
    this.api.getPayments().subscribe({
      next: (res: any) => {
        this.payments = res?.items ?? [];
        this.pending  = res?.pending ?? null;
        this.loading  = false;
      },
      error: () => { this.loading = false; }
    });
  }

  pay(): void {
    if (!this.pending || this.paying) return;
    this.paying = true;
    this.api.initiatePayment(this.pending.appointment_id).subscribe({
      next: (res: any) => {
        this.paying = false;
        if (res?.checkout_url) {
          window.location.href = res.checkout_url;
        }
      },
      error: () => { this.paying = false; }
    });
  }

  isPaid(p: any): boolean { return p.status === 'completed'; }
  isRefunded(p: any): boolean { return p.status === 'refunded'; }

  statusLabel(p: any): string {
    if (p.status === 'completed') return this.translate.instant('payments.status.paid');
    if (p.status === 'refunded')  return this.translate.instant('payments.status.refunded');
    return this.translate.instant('payments.status.unpaid');
  }
}
