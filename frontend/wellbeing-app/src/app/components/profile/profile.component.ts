import { Component, OnInit } from '@angular/core';
import { UserService } from '../../services/user/user.service';
import { ApiService } from '../../services/api/api.service';

@Component({
  selector: 'app-profile',
  templateUrl: './profile.component.html',
  styleUrls: ['./profile.component.css']
})
export class ProfileComponent implements OnInit {
  editing = false;
  saving = false;
  saved = false;
  loading = true;
  error = '';

  avatarUploading = false;
  avatarError = '';

  companyName = '';

  form = {
    firstName: '',
    lastName: '',
    phone: '',
    email: '',
    acceptedTerms: false
  };

  constructor(public userService: UserService, private api: ApiService) {}

  ngOnInit(): void {
    this.userService.user$.subscribe(user => {
      if (user) {
        this.loading = false;
        this.companyName = user.company_name ?? user.company_branding?.name ?? user.company ?? '';
        this.form = {
          firstName:    user.first_name   ?? '',
          lastName:     user.last_name    ?? '',
          phone:        user.phone        ?? '',
          email:        user.email        ?? '',
          acceptedTerms: !!user.accepted_terms
        };
      }
    });
  }

  get avatarLetter(): string {
    return (this.form.firstName || this.userService.current?.email || 'U').charAt(0).toUpperCase();
  }

  get userId(): string {
    return this.userService.current?.id ?? '—';
  }

  startEdit(): void { this.editing = true; this.saved = false; this.error = ''; }

  cancelEdit(): void {
    this.editing = false;
    const user = this.userService.current;
    if (user) {
      this.form = {
        firstName:    user.first_name   ?? '',
        lastName:     user.last_name    ?? '',
        phone:        user.phone        ?? '',
        email:        user.email        ?? '',
        acceptedTerms: !!user.accepted_terms
      };
    }
  }

  save(): void {
    this.saving = true;
    this.error = '';
    this.userService.update({
      first_name:     this.form.firstName,
      last_name:      this.form.lastName,
      phone:          this.form.phone,
      accepted_terms: this.form.acceptedTerms
    }).subscribe({
      next: () => {
        this.saving = false;
        this.editing = false;
        this.saved = true;
        setTimeout(() => this.saved = false, 3000);
      },
      error: (err) => {
        this.saving = false;
        this.error = err?.error?.message || 'Помилка збереження. Спробуйте ще раз.';
      }
    });
  }

  onAvatarFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    input.value = '';

    this.avatarUploading = true;
    this.avatarError = '';
    this.api.uploadAvatar(file).subscribe({
      next: (res: any) => {
        this.avatarUploading = false;
        if (res?.user) {
          this.userService.setUser(res.user);
        }
      },
      error: (err) => {
        this.avatarUploading = false;
        this.avatarError = err?.error?.error || 'Помилка завантаження фото';
      }
    });
  }
}
