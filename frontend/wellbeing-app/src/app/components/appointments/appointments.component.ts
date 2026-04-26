import { Component } from '@angular/core';

@Component({
  selector: 'app-appointments',
  templateUrl: './appointments.component.html',
  styleUrls: ['./appointments.component.css']
})
export class AppointmentsComponent {
  filter = 'all';
  cancelModal: any = null;
  ratingModal: any = null;
  ratingValue = 0;
  ratingText = '';
  ratings: { [id: number]: number } = {};
  cancelReason = '';

  filters = [
    { id: 'all', label: 'Всі' },
    { id: 'upcoming', label: 'Майбутні' },
    { id: 'completed', label: 'Завершені' },
    { id: 'cancelled', label: 'Скасовані' },
    { id: 'paid', label: 'Оплачені' },
    { id: 'unpaid', label: 'Неоплачені' }
  ];

  allAppts = [
    { id: 1, specialist: 'Марія Іваненко', type: 'Психолог', date: '28 квітня 2025', time: '14:00', status: 'confirmed', paid: true, avatar: 'МІ' },
    { id: 2, specialist: 'Дмитро Сорока', type: 'Коуч', date: '5 травня 2025', time: '10:30', status: 'pending', paid: false, avatar: 'ДС' },
    { id: 3, specialist: 'Марія Іваненко', type: 'Психолог', date: '15 квітня 2025', time: '14:00', status: 'completed', paid: true, avatar: 'МІ' },
    { id: 4, specialist: 'Аліна Бойко', type: 'Психотерапевт', date: '3 квітня 2025', time: '11:00', status: 'cancelled', paid: false, avatar: 'АБ' },
    { id: 5, specialist: 'Марія Іваненко', type: 'Психолог', date: '20 березня 2025', time: '14:00', status: 'completed', paid: true, avatar: 'МІ' }
  ];

  get filtered() {
    return this.allAppts.filter(a => {
      if (this.filter === 'all') return true;
      if (this.filter === 'upcoming') return a.status === 'confirmed' || a.status === 'pending';
      if (this.filter === 'completed') return a.status === 'completed';
      if (this.filter === 'cancelled') return a.status === 'cancelled';
      if (this.filter === 'paid') return a.paid;
      if (this.filter === 'unpaid') return !a.paid;
      return true;
    });
  }

  getStatusLabel(status: string): string {
    const map: any = { confirmed: 'Підтверджено', pending: 'Очікування', completed: 'Завершено', cancelled: 'Скасовано', noshow: 'Не з\'явився' };
    return map[status] || status;
  }

  openCancel(a: any) { this.cancelModal = a; this.cancelReason = ''; }
  closeCancel() { this.cancelModal = null; }
  confirmCancel() { this.cancelModal = null; }

  openRating(a: any) { this.ratingModal = a; this.ratingValue = 0; this.ratingText = ''; }
  closeRating() { this.ratingModal = null; }
  submitRating() {
    if (this.ratingValue && this.ratingModal) {
      this.ratings[this.ratingModal.id] = this.ratingValue;
      this.ratingModal = null;
    }
  }

  stars = [1, 2, 3, 4, 5];
}
