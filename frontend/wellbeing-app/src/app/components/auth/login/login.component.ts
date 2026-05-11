import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from '../../../services/api/api.service';
import { UserService } from '../../../services/user/user.service';
import { LangService, Lang } from '../../../services/lang/lang.service';
import { RecaptchaService } from '../../../services/recaptcha/recaptcha.service';

@Component({
  selector: 'app-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.css']
})
export class LoginComponent implements OnInit, OnDestroy {
  email = '';
  password = '';
  showPassword = false;
  loading = false;
  error = '';

  constructor(
    private api: ApiService,
    private userService: UserService,
    private router: Router,
    private langService: LangService,
    private recaptcha: RecaptchaService
  ) {}

  ngOnInit(): void {
    document.body.classList.add('show-recaptcha');
    const saved = localStorage.getItem('wb_lang');
    if (!saved) {
      const browser = navigator.language || '';
      let detected: Lang = 'uk';
      if (browser.startsWith('ru')) {
        detected = 'ru';
      } else if (browser.startsWith('en')) {
        detected = 'en';
      }
      this.langService.use(detected);
    }
  }

  ngOnDestroy(): void {
    document.body.classList.remove('show-recaptcha');
  }

  async submit(): Promise<void> {
    if (!this.email || !this.password) {
      this.error = 'Заповніть усі поля';
      return;
    }
    this.loading = true;
    this.error = '';

    const recaptchaToken = await this.recaptcha.execute('login');

    this.api.login(this.email, this.password, recaptchaToken).subscribe({
      next: () => {
        this.userService.load().subscribe({
          next: (user) => {
            if (user?.role === 'specialist') {
              this.api.clearAccessToken();
              this.userService.clear();
              this.loading = false;
              this.error = 'Для входу в кабінет консультанта використовуйте окрему панель.';
              return;
            }
            this.router.navigate(['/dashboard']);
          },
          error: () => this.router.navigate(['/dashboard'])
        });
      },
      error: (err) => {
        this.loading = false;
        this.error = err?.error?.error || err?.error?.message || 'Невірний email або пароль';
      }
    });
  }
}
