import { Component } from '@angular/core';
import { LangService, Lang } from '../../../services/lang/lang.service';

@Component({
  selector: 'app-lang-switcher',
  templateUrl: './lang-switcher.component.html',
  styleUrls: ['./lang-switcher.component.css'],
})
export class LangSwitcherComponent {
  constructor(public lang: LangService) {}

  switch(l: Lang): void {
    this.lang.use(l);
  }
}
