import { Injectable } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';

const LANG_KEY = 'wb_lang';
const SUPPORTED = ['uk', 'en'] as const;
export type Lang = typeof SUPPORTED[number];

@Injectable({ providedIn: 'root' })
export class LangService {
  readonly supported: Lang[] = [...SUPPORTED];

  constructor(private translate: TranslateService) {}

  init(): void {
    const saved = localStorage.getItem(LANG_KEY) as Lang | null;
    const lang: Lang = saved && SUPPORTED.includes(saved as Lang) ? (saved as Lang) : 'uk';
    this.translate.addLangs(this.supported);
    this.translate.setDefaultLang('uk');
    this.translate.use(lang);
  }

  get current(): Lang {
    return (this.translate.currentLang as Lang) ?? 'uk';
  }

  use(lang: Lang): void {
    this.translate.use(lang);
    localStorage.setItem(LANG_KEY, lang);
  }
}
