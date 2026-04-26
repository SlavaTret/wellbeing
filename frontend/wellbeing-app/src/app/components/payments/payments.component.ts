import { Component } from '@angular/core';

@Component({
  selector: 'app-payments',
  templateUrl: './payments.component.html',
  styleUrls: ['./payments.component.css']
})
export class PaymentsComponent {
  payments = [
    { specialist: 'Марія Іваненко', date: '15 квіт. 2025', amount: 1200, status: 'paid' },
    { specialist: 'Марія Іваненко', date: '20 бер. 2025',  amount: 1200, status: 'paid' },
    { specialist: 'Ірина Василенко', date: '10 бер. 2025', amount: 900,  status: 'paid' },
    { specialist: 'Дмитро Сорока',  date: '5 трав. 2025',  amount: 1100, status: 'unpaid' }
  ];
}
