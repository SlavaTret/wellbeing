import { Component, OnInit } from '@angular/core';
import { ApiService } from '../../services/api/api.service';

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
  bookVia: 'online' | 'phone' = 'online';
  bookingSaving = false;

  readonly stepLabels = ['Каталог', 'Спеціаліст', 'Слот', 'Підтвердження'];

  readonly categories = [
    { id: 'all',              label: 'Всі категорії' },
    { id: 'Тривога та стрес', label: 'Тривога та стрес' },
    { id: 'Депресія',         label: 'Депресія' },
    { id: 'Травма та ПТСР',   label: 'Травма та ПТСР' },
    { id: 'Розвиток та цілі', label: 'Розвиток та цілі' },
    { id: 'Відносини',        label: 'Відносини' },
    { id: 'Самооцінка',       label: 'Самооцінка' },
  ];

  readonly filters = [
    { id: 'all',       label: 'Всі' },
    { id: 'upcoming',  label: 'Майбутні' },
    { id: 'completed', label: 'Завершені' },
    { id: 'cancelled', label: 'Скасовані' },
    { id: 'paid',      label: 'Оплачені' },
    { id: 'unpaid',    label: 'Неоплачені' },
  ];

  readonly stars = [1, 2, 3, 4, 5];

  freeRemaining = 0;

  constructor(private api: ApiService) {}

  ngOnInit(): void {
    this.loadAppointments();
    this.api.getDashboard().subscribe({
      next: (data: any) => { this.freeRemaining = data?.free_sessions?.remaining ?? 0; },
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
    const map: any = {
      confirmed: 'Підтверджено',
      pending:   'Очікування',
      completed: 'Завершено',
      cancelled: 'Скасовано',
      noshow:    "Не з'явився"
    };
    return map[status] || status;
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
      },
      error: () => { this.cancelling = false; }
    });
  }

  // ── Pay ─────────────────────────────────────────────────────
  payAppointment(a: any): void {
    if (this.paying[a.id]) return;
    this.paying[a.id] = true;

    // Find the pending payment for this appointment via the payments API,
    // then process it. We identify the payment by searching for pending
    // payments linked to this appointment.
    this.api.getPayments().subscribe({
      next: (res: any) => {
        const pending = res?.pending;
        const match = pending?.appointment_id === a.id
          ? pending
          : (res?.items ?? []).find((p: any) => p.appointment_id === a.id && p.status === 'pending');

        if (!match) {
          this.paying[a.id] = false;
          return;
        }

        this.api.processPayment(match.id).subscribe({
          next: () => {
            const idx = this.allAppts.findIndex(ap => ap.id === a.id);
            if (idx !== -1) {
              this.allAppts[idx] = { ...this.allAppts[idx], paid: true, payment_status: 'paid' };
            }
            this.paying[a.id] = false;
          },
          error: () => { this.paying[a.id] = false; }
        });
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
    this.bookVia = 'online';

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
    if (this.bookingStep === 2) return !!this.bookVia;
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
    }).subscribe({
      next: (appt: any) => {
        this.allAppts.unshift(appt);
        this.bookingStep = 5;
        this.bookingSaving = false;
      },
      error: () => { this.bookingSaving = false; }
    });
  }

  formatDateLabel(dateStr: string): string {
    if (!dateStr) return '';
    const ukMonths = ['','січня','лютого','березня','квітня','травня','червня','липня','серпня','вересня','жовтня','листопада','грудня'];
    const parts = dateStr.split('-');
    return `${parseInt(parts[2])} ${ukMonths[parseInt(parts[1])]} ${parts[0]}`;
  }
}
