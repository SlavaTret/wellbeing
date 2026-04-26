import { Component } from '@angular/core';

@Component({
  selector: 'app-profile',
  templateUrl: './profile.component.html',
  styleUrls: ['./profile.component.css']
})
export class ProfileComponent {
  editing = false;
  saved = false;

  form = {
    firstName: 'Олена',
    lastName: 'Бондар',
    patronymic: 'Василівна',
    phone: '+38 050 123 45 67',
    email: 'o.kovalenko@epam.com',
    company: 'EPAM Ukraine',
    acceptedTerms: true
  };

  startEdit() { this.editing = true; this.saved = false; }
  cancelEdit() { this.editing = false; }
  save() {
    this.editing = false;
    this.saved = true;
    setTimeout(() => this.saved = false, 2000);
  }
}
