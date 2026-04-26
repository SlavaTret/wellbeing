import { Component } from '@angular/core';
import { Router } from '@angular/router';

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent {
  stats = [
    { label: 'Заплановані консультації', value: 2, icon: 'calendar', color: '#2DB928', bg: '#E8F5E9' },
    { label: 'Завершені консультації', value: 7, icon: 'check', color: '#1565C0', bg: '#E3F2FD' },
    { label: 'Залишок безкоштовних', value: 3, icon: 'shield', color: '#6A1B9A', bg: '#F3E5F5' },
    { label: 'Скасовані', value: 1, icon: 'x', color: '#C62828', bg: '#FFEBEE' }
  ];

  upcoming = [
    { specialist: 'Марія Іваненко', type: 'Психолог', date: '28 квітня', time: '14:00', status: 'confirmed', avatar: 'МІ' },
    { specialist: 'Дмитро Сорока', type: 'Коуч', date: '5 травня', time: '10:30', status: 'pending', avatar: 'ДС' }
  ];

  freeSessionsUsed = 3;
  freeSessionsTotal = 6;
  calendarConnected = false;

  get freePercent(): number {
    return Math.round((this.freeSessionsUsed / this.freeSessionsTotal) * 100);
  }

  getStatusLabel(status: string): string {
    const map: any = { confirmed: 'Підтверджено', pending: 'Очікування', completed: 'Завершено', cancelled: 'Скасовано' };
    return map[status] || status;
  }

  getStatusClass(status: string): string {
    return `badge-${status}`;
  }

  constructor(private router: Router) {}

  goToAppointments() {
    this.router.navigate(['/appointments']);
  }
}
