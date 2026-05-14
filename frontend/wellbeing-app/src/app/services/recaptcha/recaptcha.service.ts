import { Injectable } from '@angular/core';

declare const grecaptcha: any;

const SITE_KEY = '6LciOOQsAAAAAMUxk_Ahg7yoWfKmlNG7Zz_tfdtY';

@Injectable({ providedIn: 'root' })
export class RecaptchaService {
  execute(action: string): Promise<string> {
    return new Promise((resolve) => {
      if (typeof grecaptcha === 'undefined') {
        resolve('');
        return;
      }
      grecaptcha.ready(() => {
        grecaptcha.execute(SITE_KEY, { action }).then(
          (token: string) => resolve(token),
          () => resolve('')
        );
      });
    });
  }
}
