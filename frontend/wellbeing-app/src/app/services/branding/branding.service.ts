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

/**
 * Holds the current user's company branding and applies it as CSS variables
 * on document.documentElement so any component using var(--brand-*) reflects it.
 *
 * Design system already exposes --green / --green-dark / --green-light. We override
 * those root vars when a branded company is set, so existing components
 * automatically pick up the new colors.
 */
@Injectable({ providedIn: 'root' })
export class BrandingService {
  private brandingSubject = new BehaviorSubject<CompanyBranding>(DEFAULT_BRANDING);
  branding$ = this.brandingSubject.asObservable();

  get current(): CompanyBranding {
    return this.brandingSubject.value;
  }

  set(branding: CompanyBranding | null): void {
    const next = branding ?? DEFAULT_BRANDING;
    this.brandingSubject.next(next);
    this.applyToCss(next);
  }

  reset(): void {
    this.set(null);
  }

  private applyToCss(b: CompanyBranding): void {
    const root = document.documentElement;
    // Override the design system variables that drive primary actions/highlights.
    root.style.setProperty('--green', b.primary_color);
    root.style.setProperty('--green-dark', b.secondary_color);
    root.style.setProperty('--green-light', b.accent_color);
    // Brand-specific aliases (use these in new components for clarity).
    root.style.setProperty('--brand-primary', b.primary_color);
    root.style.setProperty('--brand-secondary', b.secondary_color);
    root.style.setProperty('--brand-accent', b.accent_color);
  }
}
