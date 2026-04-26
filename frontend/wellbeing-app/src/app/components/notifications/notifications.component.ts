import { Component } from '@angular/core';

@Component({
  selector: 'app-notifications',
  templateUrl: './notifications.component.html',
  styleUrls: ['./notifications.component.css']
})
export class NotificationsComponent {
  settings = { email: true, calendar: true, sms: false, reminders: true };

  settingsList = [
    { key: 'email',     label: 'Email сповіщення' },
    { key: 'calendar',  label: 'Google Calendar' },
    { key: 'sms',       label: 'SMS нагадування' },
    { key: 'reminders', label: 'Нагадування за 12 год' }
  ];

  notifs = [
    { title: 'Нагадування про консультацію', body: 'Ваша консультація з Марією Іваненко завтра о 14:00', time: '2 год тому', read: false, icon: 'bell' },
    { title: 'Запис підтверджено', body: 'Дмитро Сорока підтвердив ваш запис на 5 травня о 10:30', time: '1 день тому', read: false, icon: 'check' },
    { title: 'Консультацію завершено', body: 'Залиште відгук про консультацію від 15 квітня', time: '2 дні тому', read: true, icon: 'star' }
  ];

  toggle(key: string) {
    (this.settings as any)[key] = !(this.settings as any)[key];
  }
  getValue(key: string): boolean {
    return (this.settings as any)[key];
  }
}
