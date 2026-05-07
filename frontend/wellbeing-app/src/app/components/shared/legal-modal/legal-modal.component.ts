import { Component, EventEmitter, Input, Output } from '@angular/core';

@Component({
  selector: 'app-legal-modal',
  templateUrl: './legal-modal.component.html',
  styleUrls: ['./legal-modal.component.css'],
})
export class LegalModalComponent {
  @Input() title = '';
  @Input() content = '';
  @Output() closed = new EventEmitter<void>();

  close(): void { this.closed.emit(); }

  onBackdropClick(event: MouseEvent): void {
    if ((event.target as HTMLElement).classList.contains('legal-modal-backdrop')) {
      this.close();
    }
  }
}
