import { Component, OnInit } from '@angular/core';
import { AdminApiService } from '../../services/admin-api.service';

interface AdminPayment {
  id: number;
  user_id: number;
  appointment_id: number | null;
  amount: number;
  currency: string;
  status: string;
  payment_method: string;
  payment_method_label: string;
  transaction_id: string;
  gateway: string;
  gateway_order_id: string;
  paid_at: number | null;
  refund_status: string | null;
  refund_amount: number | null;
  refunded_at: number | null;
  notes: string;
  created_at: number;
  user_name: string;
  user_email: string;
  company_name: string;
  specialist_name: string;
  appointment_date: string;
  appointment_time: string;
}

@Component({
  selector: 'app-admin-payments',
  templateUrl: './admin-payments.component.html',
  styleUrls: ['./admin-payments.component.css']
})
export class AdminPaymentsPageComponent implements OnInit {
  payments: AdminPayment[] = [];
  loading     = true;
  listLoading = false;
  error       = '';
  search      = '';
  searchTimer: any;

  statusFilter = 'all';
  statusTabs = [
    { value: 'all',       label: 'Всі' },
    { value: 'completed', label: 'Оплачено' },
    { value: 'pending',   label: 'Очікує' },
    { value: 'failed',    label: 'Помилка' },
    { value: 'refunded',  label: 'Повернено' },
  ];

  total       = 0;
  page        = 1;
  pages       = 1;
  paidAmount  = 0;
  pendingAmount = 0;
  totalCount  = 0;

  showModal   = false;
  modalPay: AdminPayment | null = null;
  quickStatus = '';
  saving      = false;
  modalError  = '';

  constructor(private adminApi: AdminApiService) {}

  ngOnInit(): void { this.load(); }

  load(soft = false): void {
    if (soft) { this.listLoading = true; }
    else      { this.loading = true; this.error = ''; }

    this.adminApi.getAdminPayments({
      search: this.search,
      status: this.statusFilter,
      page:   this.page,
    }).subscribe({
      next: (res: any) => {
        this.payments       = res.payments || [];
        this.total          = res.total || 0;
        this.page           = res.page  || 1;
        this.pages          = res.pages || 1;
        this.paidAmount     = res.paid_amount || 0;
        this.pendingAmount  = res.pending_amount || 0;
        this.totalCount     = res.total_count || 0;
        this.loading        = false;
        this.listLoading    = false;
      },
      error: (err: any) => {
        if (!soft) this.error = err?.error?.error || 'Помилка завантаження';
        this.loading     = false;
        this.listLoading = false;
      }
    });
  }

  onSearchInput(): void {
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => { this.page = 1; this.load(true); }, 350);
  }

  setStatus(s: string): void {
    if (this.statusFilter === s) return;
    this.statusFilter = s;
    this.page = 1;
    this.load(true);
  }

  goPage(p: number): void {
    if (p < 1 || p > this.pages || p === this.page) return;
    this.page = p;
    this.load(true);
  }

  openView(p: AdminPayment): void {
    this.modalPay    = p;
    this.quickStatus = p.status;
    this.modalError  = '';
    this.saving      = false;
    this.showModal   = true;
  }

  closeModal(): void { this.showModal = false; this.modalPay = null; }

  saveStatus(): void {
    if (!this.modalPay || this.quickStatus === this.modalPay.status) {
      this.closeModal();
      return;
    }
    this.saving     = true;
    this.modalError = '';
    this.adminApi.updateAdminPayment(this.modalPay.id, { status: this.quickStatus }).subscribe({
      next: (res: any) => {
        const idx = this.payments.findIndex(p => p.id === this.modalPay!.id);
        if (idx !== -1) this.payments[idx] = res.payment;
        this.saving = false;
        this.closeModal();
      },
      error: (err: any) => {
        this.modalError = err?.error?.error || 'Помилка збереження';
        this.saving = false;
      }
    });
  }

  formatDate(ts: number): string {
    if (!ts) return '—';
    const d = new Date(ts * 1000);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  formatAmount(amount: number, currency: string): string {
    const sym: any = { UAH: '₴', USD: '$', EUR: '€' };
    return `${sym[currency] || currency}${amount.toLocaleString('uk-UA')}`;
  }

  statusLabel(s: string): string {
    const m: any = { pending: 'Очікує', completed: 'Оплачено', failed: 'Помилка', refunded: 'Повернено' };
    return m[s] || s;
  }

  payId(id: number): string {
    return '#' + id;
  }

  get pageNumbers(): number[] {
    const arr: number[] = [];
    for (let i = 1; i <= this.pages; i++) arr.push(i);
    return arr;
  }
}
