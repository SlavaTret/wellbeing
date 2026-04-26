import { Component, Input } from '@angular/core';
import { ICON_PATHS } from './icons';

@Component({
  selector: 'app-icon',
  template: `
    <svg
      [attr.width]="size"
      [attr.height]="size"
      viewBox="0 0 24 24"
      [attr.fill]="fill"
      [attr.stroke]="stroke"
      [attr.stroke-width]="strokeWidth"
      stroke-linecap="round"
      stroke-linejoin="round"
      [attr.aria-hidden]="true">
      <path *ngFor="let p of paths" [attr.d]="p"></path>
    </svg>
  `,
  styles: [`:host { display: inline-flex; align-items: center; justify-content: center; line-height: 0; }`]
})
export class IconComponent {
  @Input() name = '';
  @Input() size: number = 20;
  @Input() stroke: string = 'currentColor';
  @Input() fill: string = 'none';
  @Input() strokeWidth: number = 1.6;

  get paths(): string[] {
    const d = ICON_PATHS[this.name];
    if (!d) return [];
    // Some icon paths contain multiple sub-paths separated by " M " — split them so each <path> renders cleanly.
    return d.split(/\s(?=M)/);
  }
}
