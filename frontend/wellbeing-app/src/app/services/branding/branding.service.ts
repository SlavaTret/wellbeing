import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

export interface CompanyBranding {
  id: number;
  code: string;
  name: string;
  logo_url: string | null;
  primary_color: string;
  secondary_color: string;
  accent_color: string;
}

const DEFAULT_BRANDING: CompanyBranding = {
  id: 0,
  code: 'default',
  name: 'Wellbeing',
  logo_url: null,
  primary_color: '#2DB928',
  secondary_color: '#1E9020',
  accent_color: '#E8F5E9',
};

const BRANDING_KEY = 'wb_branding';

@Injectable({ providedIn: 'root' })
export class BrandingService {
  private brandingSubject = new BehaviorSubject<CompanyBranding>(this.readCached());
  branding$ = this.brandingSubject.asObservable();

  constructor() {
    // Apply cached CSS variables immediately on startup — before any API call
    this.applyToCss(this.brandingSubject.value);
  }

  get current(): CompanyBranding {
    return this.brandingSubject.value;
  }

  set(branding: CompanyBranding | null): void {
    const next = branding ?? DEFAULT_BRANDING;
    this.brandingSubject.next(next);
    this.applyToCss(next);
    try {
      if (branding) {
        localStorage.setItem(BRANDING_KEY, JSON.stringify(branding));
      } else {
        localStorage.removeItem(BRANDING_KEY);
      }
    } catch {}
  }

  reset(): void {
    this.set(null);
    try { localStorage.removeItem(BRANDING_KEY); } catch {}
  }

  private readCached(): CompanyBranding {
    try {
      const raw = localStorage.getItem(BRANDING_KEY);
      if (raw) {
        const parsed = JSON.parse(raw);
        if (parsed?.primary_color) return parsed as CompanyBranding;
      }
    } catch {}
    return DEFAULT_BRANDING;
  }

  private applyToCss(b: CompanyBranding): void {
    const root = document.documentElement;
    root.style.setProperty('--green',          b.primary_color);
    root.style.setProperty('--green-dark',     b.secondary_color);
    root.style.setProperty('--green-light',    b.accent_color);
    root.style.setProperty('--brand-primary',  b.primary_color);
    root.style.setProperty('--brand-secondary',b.secondary_color);
    root.style.setProperty('--brand-accent',   b.accent_color);
  }
}
