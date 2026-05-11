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
    const primaryRgb   = this.hexToRgb(b.primary_color);
    const secondaryRgb = this.hexToRgb(b.secondary_color);

    root.style.setProperty('--green',               b.primary_color);
    root.style.setProperty('--green-dark',          b.secondary_color);
    root.style.setProperty('--green-light',         b.accent_color);
    root.style.setProperty('--green-hover',         this.tint(b.primary_color, 0.92));
    root.style.setProperty('--green-hover-border',  this.tint(b.primary_color, 0.62));
    root.style.setProperty('--success',             this.darken(b.primary_color, 0.52));
    root.style.setProperty('--primary',             b.primary_color);
    root.style.setProperty('--brand-primary',       b.primary_color);
    root.style.setProperty('--brand-secondary',     b.secondary_color);
    root.style.setProperty('--brand-accent',        b.accent_color);
    root.style.setProperty('--brand-primary-rgb',   primaryRgb);
    root.style.setProperty('--brand-secondary-rgb', secondaryRgb);
    root.style.setProperty('--brand-gradient',
      `linear-gradient(135deg, ${b.primary_color}, ${b.secondary_color})`);
  }

  private hexToRgb(hex: string): string {
    const clean = hex.replace('#', '');
    const full  = clean.length === 3
      ? clean.split('').map(c => c + c).join('')
      : clean;
    const r = parseInt(full.substring(0, 2), 16);
    const g = parseInt(full.substring(2, 4), 16);
    const b = parseInt(full.substring(4, 6), 16);
    return isNaN(r) ? '45, 185, 40' : `${r}, ${g}, ${b}`;
  }

  private tint(hex: string, weight: number): string {
    const [r, g, b] = this.parseHex(hex);
    const tr = Math.round(r + (255 - r) * weight);
    const tg = Math.round(g + (255 - g) * weight);
    const tb = Math.round(b + (255 - b) * weight);
    return `#${this.toHex2(tr)}${this.toHex2(tg)}${this.toHex2(tb)}`;
  }

  private darken(hex: string, amount: number): string {
    const [r, g, b] = this.parseHex(hex);
    const dr = Math.round(r * (1 - amount));
    const dg = Math.round(g * (1 - amount));
    const db = Math.round(b * (1 - amount));
    return `#${this.toHex2(dr)}${this.toHex2(dg)}${this.toHex2(db)}`;
  }

  private parseHex(hex: string): [number, number, number] {
    const clean = hex.replace('#', '');
    const full  = clean.length === 3
      ? clean.split('').map(c => c + c).join('')
      : clean;
    return [
      parseInt(full.substring(0, 2), 16) || 0,
      parseInt(full.substring(2, 4), 16) || 0,
      parseInt(full.substring(4, 6), 16) || 0,
    ];
  }

  private toHex2(n: number): string {
    return Math.min(255, Math.max(0, n)).toString(16).padStart(2, '0');
  }
}
