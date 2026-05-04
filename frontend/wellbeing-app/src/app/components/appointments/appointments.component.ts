import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { ApiService } from '../../services/api/api.service';
import { UserService } from '../../services/user/user.service';

@Component({
  selector: 'app-appointments',
  templateUrl: './appointments.component.html',
  styleUrls: ['./appointments.component.css']
})
export class AppointmentsComponent implements OnInit {
  filter = 'all';
  loading = true;
  error = '';

  allAppts: any[] = [];
  cancelModal: any = null;
  cancelReason = '';
  cancelling = false;

  ratingModal: any = null;
  ratingValue = 0;
  ratingText = '';
  ratings: { [id: number]: number } = {};
  paying: { [id: number]: boolean } = {};
  paymentToast: 'success' | 'failed' | null = null;
  cancelToast: 'refunded' | 'refund_failed' | 'cancelled' | null = null;

  // ── Booking ─────────────────────────────────────────────────
  bookingOpen = false;
  // Steps: 1=Catalog 2=Specialist 3=Slots 4=Confirm 5=Success
  bookingStep = 1;

  specialists: any[] = [];
  specialistsLoading = false;
  catFilter = 'all';
  selectedSpec: any = null;
  selectedDay = '';
  selectedTime = '';
  paymentVia: 'card' | 'subscription' = 'card';
  bookingSaving = false;
  newAppt: any = null;
  initiatingPayment = false;

  readonly stepLabels = [
    'appointments.booking.steps.catalog',
    'appointments.booking.steps.specialist',
    'appointments.booking.steps.slot',
    'appointments.booking.steps.confirm',
  ];

  categories: { id: string; label: string }[] = [
    { id: 'all', label: 'appointments.booking.step1.all_categories' },
  ];

  readonly filters = [
    { id: 'all',       label: 'appointments.filters.all' },
    { id: 'upcoming',  label: 'appointments.filters.upcoming' },
    { id: 'completed', label: 'appointments.filters.completed' },
    { id: 'cancelled', label: 'appointments.filters.cancelled' },
    { id: 'paid',      label: 'appointments.filters.paid' },
  ];

  readonly stars = [1, 2, 3, 4, 5];

  freeRemaining = 0;

  constructor(
    private api: ApiService,
    private route: ActivatedRoute,
    private router: Router,
    private userService: UserService,
    private translate: TranslateService
  ) {}

  ngOnInit(): void {
    const params = this.route.snapshot.queryParams;
    const paymentResult = params['payment'];
    const orderId = params['order'] || '';

    if (paymentResult === 'success' && orderId) {
      // Clear URL immediately so refresh won't re-trigger toast
      this.router.navigate([], { relativeTo: this.route, queryParams: {}, replaceUrl: true });
      this.paymentToast = 'success';
      setTimeout(() => { this.paymentToast = null; }, 5000);
      // Sync payment status first, then load — avoids showing stale "pending" state
      this.loading = true;
      this.api.syncPaymentByOrder(orderId).subscribe({
        next: () => this.loadAppointments(),
        error: () => this.loadAppointments()
      });
    } else if (paymentResult === 'failed') {
      this.router.navigate([], { relativeTo: this.route, queryParams: {}, replaceUrl: true });
      this.paymentToast = 'failed';
      setTimeout(() => { this.paymentToast = null; }, 5000);
      this.loadAppointments();
    } else {
      this.loadAppointments();
    }
    this.freeRemaining = this.userService.freeSessions?.remaining ?? 0;
    this.userService.freeSessions$.subscribe(fs => {
      this.freeRemaining = fs?.remaining ?? 0;
    });
    this.api.getCategories().subscribe({
      next: (list: any[]) => {
        this.categories = [
          { id: 'all', label: 'appointments.booking.step1.all_categories' },
          ...(list || []).map((c: any) => ({ id: c.name, label: c.name })),
        ];
      },
      error: () => {}
    });
  }

  loadAppointments(): void {
    this.loading = true;
    this.api.getAppointments().subscribe({
      next: (res: any) => {
        this.allAppts = res.items || res || [];
        // Restore review ratings from backend so they survive page refresh
        this.allAppts.forEach(a => {
          if (a.review_rating && !this.ratings[a.id]) {
            this.ratings[a.id] = a.review_rating;
          }
        });
        this.loading = false;
      },
      error: () => {
        this.error = 'Не вдалось завантажити записи';
        this.loading = false;
      }
    });
  }

  get filtered() {
    return this.allAppts.filter(a => {
      if (this.filter === 'all')       return true;
      if (this.filter === 'upcoming')  return a.status === 'confirmed' || a.status === 'pending';
      if (this.filter === 'completed') return a.status === 'completed';
      if (this.filter === 'cancelled') return a.status === 'cancelled';
      if (this.filter === 'paid')      return a.paid;
      if (this.filter === 'unpaid')    return !a.paid;
      return true;
    });
  }

  getStatusLabel(status: string): string {
    return this.translate.instant('appointments.status.' + status) || status;
  }

  // ── Cancel ──────────────────────────────────────────────────
  openCancel(a: any)  { this.cancelModal = a; this.cancelReason = ''; }
  closeCancel()        { this.cancelModal = null; }

  confirmCancel(): void {
    if (!this.cancelModal) return;
    this.cancelling = true;
    this.api.cancelAppointment(this.cancelModal.id).subscribe({
      next: (res: any) => {
        const updated = res.appointment;
        const idx = this.allAppts.findIndex(a => a.id === updated.id);
        if (idx !== -1) this.allAppts[idx] = updated;
        this.cancelModal = null;
        this.cancelling = false;
        this.userService.invalidateFreeSessions();
        if (res.cancel_type === 'gateway_paid') {
          this.cancelToast = res.refund_success ? 'refunded' : 'refund_failed';
        } else {
          this.cancelToast = 'cancelled';
        }
        setTimeout(() => { this.cancelToast = null; }, 6000);
      },
      error: () => { this.cancelling = false; }
    });
  }

  // ── Pay ─────────────────────────────────────────────────────
  payAppointment(a: any): void {
    if (this.paying[a.id]) return;
    this.paying[a.id] = true;

    this.api.initiatePayment(a.id).subscribe({
      next: (res: any) => {
        this.paying[a.id] = false;
        if (res?.checkout_url) {
          window.location.href = res.checkout_url;
        }
      },
      error: () => { this.paying[a.id] = false; }
    });
  }

  // ── Rating ──────────────────────────────────────────────────
  openRating(a: any)  { this.ratingModal = a; this.ratingValue = 0; this.ratingText = ''; }
  closeRating()        { this.ratingModal = null; }

  submitRating(): void {
    if (!this.ratingValue || !this.ratingModal) return;
    const appt = this.ratingModal;
    this.api.reviewSpecialist(
      appt.specialist_id,
      this.ratingValue,
      this.ratingText,
      appt.id
    ).subscribe({
      next: () => {
        this.ratings[appt.id] = this.ratingValue;
        this.ratingModal = null;
      },
      error: (err) => {
        // if duplicate — still mark locally so button disappears
        if (err?.status === 409) {
          this.ratings[appt.id] = this.ratingValue;
        }
        this.ratingModal = null;
      }
    });
  }

  // ── Booking ─────────────────────────────────────────────────
  openBooking(): void {
    this.bookingOpen = true;
    this.bookingStep = 1;
    this.catFilter = 'all';
    this.selectedSpec = null;
    this.selectedDay = '';
    this.selectedTime = '';
    this.paymentVia = this.freeRemaining > 0 ? 'subscription' : 'card';

    // Always refresh free sessions count from server so payment method step is accurate
    this.userService.loadFreeSessions().subscribe(fs => {
      this.freeRemaining = fs?.remaining ?? 0;
      this.paymentVia = this.freeRemaining > 0 ? 'subscription' : 'card';
    });

    if (!this.specialists.length) {
      this.specialistsLoading = true;
      this.api.getSpecialists().subscribe({
        next: (list: any) => {
          this.specialists = list || [];
          this.specialistsLoading = false;
        },
        error: () => { this.specialistsLoading = false; }
      });
    }
  }

  closeBooking(): void { this.bookingOpen = false; }

  get filteredSpecialists() {
    if (this.catFilter === 'all') return this.specialists;
    return this.specialists.filter(s =>
      s.categories && s.categories.some((c: string) => c === this.catFilter)
    );
  }

  selectSpec(s: any): void {
    this.selectedSpec = s;
    this.selectedDay = '';
    this.selectedTime = '';
    this.bookingStep = 2;
  }

  // Available dates for selected specialist
  get availableDates(): { date: string; label: string; slots: string[] }[] {
    if (!this.selectedSpec?.available_slots) return [];
    const ukMonths = ['','січ','лют','бер','квіт','трав','черв','лип','серп','вер','жовт','лист','груд'];
    return this.selectedSpec.available_slots.map((d: any) => {
      const parts = d.date.split('-');
      const label = `${parseInt(parts[2])} ${ukMonths[parseInt(parts[1])]}`;
      return { date: d.date, label, slots: d.slots };
    });
  }

  get selectedDaySlots(): string[] {
    const found = this.selectedSpec?.available_slots?.find((d: any) => d.date === this.selectedDay);
    return found ? found.slots : [];
  }

  get canGoNext(): boolean {
    if (this.bookingStep === 1) return !!this.selectedSpec;
    if (this.bookingStep === 2) return !!this.paymentVia;
    if (this.bookingStep === 3) return !!this.selectedTime;
    return true;
  }

  stepBack(): void {
    if (this.bookingStep > 1) this.bookingStep--;
    else this.closeBooking();
  }

  stepNext(): void {
    if (this.bookingStep === 4) {
      this.confirmBooking();
    } else if (this.canGoNext) {
      this.bookingStep++;
    }
  }

  confirmBooking(): void {
    if (!this.selectedSpec || !this.selectedDay || !this.selectedTime) return;
    this.bookingSaving = true;
    this.api.createAppointment({
      specialist_id:    this.selectedSpec.id,
      specialist_name:  this.selectedSpec.name,
      specialist_type:  this.selectedSpec.type,
      appointment_date: this.selectedDay,
      appointment_time: this.selectedTime,
      payment_via:      this.paymentVia,
    }).subscribe({
      next: (appt: any) => {
        this.newAppt = appt;
        this.allAppts.unshift(appt);
        this.bookingSaving = false;
        this.userService.invalidateFreeSessions();

        if (this.paymentVia === 'card' && appt.payment_status === 'pending') {
          this.initiatingPayment = true;
          this.api.initiatePayment(appt.id).subscribe({
            next: (res: any) => {
              this.initiatingPayment = false;
              if (res?.checkout_url) {
                window.location.href = res.checkout_url;
              } else {
                this.bookingStep = 5;
              }
            },
            error: () => {
              this.initiatingPayment = false;
              this.bookingStep = 5;
            }
          });
        } else {
          this.bookingStep = 5;
        }
      },
      error: () => { this.bookingSaving = false; }
    });
  }

  payNewAppt(): void {
    if (!this.newAppt || this.initiatingPayment) return;
    this.initiatingPayment = true;
    this.api.initiatePayment(this.newAppt.id).subscribe({
      next: (res: any) => {
        this.initiatingPayment = false;
        if (res?.checkout_url) {
          window.location.href = res.checkout_url;
        }
      },
      error: () => { this.initiatingPayment = false; }
    });
  }

  formatDateLabel(dateStr: string): string {
    if (!dateStr) return '';
    const ukMonths = ['','січня','лютого','березня','квітня','травня','червня','липня','серпня','вересня','жовтня','листопада','грудня'];
    const parts = dateStr.split('-');
    return `${parseInt(parts[2])} ${ukMonths[parseInt(parts[1])]} ${parts[0]}`;
  }

  typeName(type: string, typeName?: string): string {
    if (typeName) return typeName;
    const key = 'specialist.type.' + type;
    const translated = this.translate.instant(key);
    return translated !== key ? translated : type;
  }
}
