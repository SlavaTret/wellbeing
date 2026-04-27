import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { tap } from 'rxjs/operators';
import { ApiService } from '../api/api.service';
import { BrandingService } from '../branding/branding.service';

@Injectable({ providedIn: 'root' })
export class UserService {
  private userSubject = new BehaviorSubject<any>(null);
  user$ = this.userSubject.asObservable();

  get current(): any { return this.userSubject.value; }

  constructor(private api: ApiService, private branding: BrandingService) {}

  load(): Observable<any> {
    return this.api.getProfile().pipe(
      tap(user => {
        this.userSubject.next(user);
        this.branding.set(user?.company_branding ?? null);
      })
    );
  }

  update(data: any): Observable<any> {
    return this.api.updateProfile(data).pipe(
      tap((resp: any) => {
        if (resp?.user) {
          this.userSubject.next(resp.user);
          this.branding.set(resp.user?.company_branding ?? null);
        }
      })
    );
  }

  setUser(user: any): void {
    this.userSubject.next(user);
    this.branding.set(user?.company_branding ?? null);
  }

  clear(): void {
    this.userSubject.next(null);
    this.branding.reset();
  }
}
