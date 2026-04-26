import { Component } from '@angular/core';

@Component({
  selector: 'app-support',
  templateUrl: './support.component.html',
  styleUrls: ['./support.component.css']
})
export class SupportComponent {
  msg = '';
  get canSend() { return this.msg.trim().length > 0; }
  send() { if (this.canSend) { this.msg = ''; } }
}
