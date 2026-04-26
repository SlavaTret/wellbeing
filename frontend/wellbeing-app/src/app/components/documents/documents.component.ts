import { Component } from '@angular/core';

@Component({
  selector: 'app-documents',
  templateUrl: './documents.component.html',
  styleUrls: ['./documents.component.css']
})
export class DocumentsComponent {
  dragOver = false;

  docs = [
    { name: 'Договір про надання послуг.pdf', size: '1.2 MB', date: '15 квіт. 2025', type: 'pdf' },
    { name: 'Направлення від лікаря.jpg',     size: '850 KB', date: '3 квіт. 2025',  type: 'jpg' },
    { name: 'Рекомендації психолога.pdf',     size: '420 KB', date: '20 бер. 2025',  type: 'pdf' }
  ];

  typeColors: any = { pdf: '#C62828', jpg: '#1565C0', png: '#6A1B9A' };
  typeColor(type: string) { return this.typeColors[type] || '#555'; }
}
