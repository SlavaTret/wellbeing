import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable, of } from 'rxjs';
import { tap, catchError } from 'rxjs/operators';
import { ApiService } from '../api/api.service';
import { BrandingService } from '../branding/branding.service';

export interface FreeSessions {
  total: number;
  used: number;
  remaining: number;
  percent: number;
}

const USER_KEY          = 'wb_user';
const FREE_SESSIONS_KEY = 'wb_free_sessions';
const FREE_SESSIONS_TTL = 5 * 60 * 1000; // 5 minutes

@Injectable({ providedIn: 'root' })
export class UserService {
  private userSubject         = new BehaviorSubject<any>(this.readUser());
  private freeSessionsSubject = new BehaviorSubject<FreeSessions | null>(this.readFreeSessions());

  user$         = this.userSubject.asObservable();
  freeSessions$ = this.freeSessionsSubject.asObservable();

  get current(): any { return this.userSubject.value; }
  get freeSessions(): FreeSessions | null { return this.freeSessionsSubject.value; }

  constructor(private api: ApiService, private branding: BrandingService) {}

  load(): Observable<any> {
    return this.api.getProfile().pipe(
      tap(user => this.applyUser(user))
    );
  }

  update(data: any): Observable<any> {
    return this.api.updateProfile(data).pipe(
      tap((resp: any) => {
        if (resp?.user) this.applyUser(resp.user);
      })
    );
  }

  setUser(user: any): void {
    this.applyUser(user);
  }

  setFreeSessions(fs: FreeSessions): void {
    this.freeSessionsSubject.next(fs);
    try {
      localStorage.setItem(FREE_SESSIONS_KEY, JSON.stringify({ ...fs, _at: Date.now() }));
    } catch {}
  }

  invalidateFreeSessions(): void {
    try { localStorage.removeItem(FREE_SESSIONS_KEY); } catch {}
    this.freeSessionsSubject.next(null);
  }

  loadFreeSessions(): Observable<FreeSessions> {
    return this.api.getFreeSessions().pipe(
      tap((fs: FreeSessions) => this.setFreeSessions(fs)),
      catchError(() => of(this.freeSessions as FreeSessions))
    );
  }

  clear(): void {
    this.userSubject.next(null);
    this.freeSessionsSubject.next(null);
    this.branding.reset();
    try {
      localStorage.removeItem(USER_KEY);
      localStorage.removeItem(FREE_SESSIONS_KEY);
    } catch {}
  }

  private applyUser(user: any): void {
    this.userSubject.next(user);
    this.branding.set(user?.company_branding ?? null);
    try {
      localStorage.setItem(USER_KEY, JSON.stringify(user));
    } catch {}
  }

  private readUser(): any {
    try {
      const raw = localStorage.getItem(USER_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch { return null; }
  }

  private readFreeSessions(): FreeSessions | null {
    try {
      const raw = localStorage.getItem(FREE_SESSIONS_KEY);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (Date.now() - (parsed._at ?? 0) > FREE_SESSIONS_TTL) {
        localStorage.removeItem(FREE_SESSIONS_KEY);
        return null;
      }
      const { _at, ...fs } = parsed;
      return fs as FreeSessions;
    } catch { return null; }
  }
}
