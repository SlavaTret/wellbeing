import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from '../../../services/api/api.service';
import { CompanyBranding } from '../../../services/branding/branding.service';

@Component({
  selector: 'app-register',
  templateUrl: './register.component.html',
  styleUrls: ['./register.component.css']
})
export class RegisterComponent implements OnInit {
  firstName = '';
  lastName = '';
  email = '';
  password = '';
  companyId: number | null = null;
  companies: CompanyBranding[] = [];
  loading = false;
  loadingCompanies = true;
  error = '';
  showPassword = false;

  constructor(private api: ApiService, private router: Router) {}

  ngOnInit(): void {
    this.api.getCompanies().subscribe({
      next: (list: CompanyBranding[]) => {
        this.companies = list || [];
        this.loadingCompanies = false;
      },
      error: () => {
        this.loadingCompanies = false;
      }
    });
  }

  generatePassword(): void {
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const lower = 'abcdefghijkmnpqrstuvwxyz';
    const digits = '23456789';
    const symbols = '!@#$%&*?';
    const all = upper + lower + digits + symbols;
    const pick = (set: string) => set[Math.floor(Math.random() * set.length)];

    let pwd = pick(upper) + pick(lower) + pick(digits) + pick(symbols);
    for (let i = 0; i < 8; i++) pwd += pick(all);
    // Shuffle
    pwd = pwd.split('').sort(() => Math.random() - 0.5).join('');

    this.password = pwd;
    this.showPassword = true;
  }

  togglePasswordVisibility(): void {
    this.showPassword = !this.showPassword;
  }

  submit(): void {
    if (!this.firstName || !this.lastName || !this.email || !this.password) {
      this.error = 'Заповніть усі поля';
      return;
    }
    if (!this.companyId) {
      this.error = 'Оберіть компанію';
      return;
    }
    if (this.password.length < 8) {
      this.error = 'Пароль має бути не менше 8 символів';
      return;
    }
    this.loading = true;
    this.error = '';
    this.api.register({
      email: this.email,
      password: this.password,
      firstName: this.firstName,
      lastName: this.lastName,
      companyId: this.companyId
    }).subscribe({
      next: () => this.router.navigate(['/dashboard']),
      error: (err) => {
        this.loading = false;
        this.error = err?.error?.message || err?.error?.error || 'Помилка реєстрації. Спробуйте ще раз.';
      }
    });
  }
}
