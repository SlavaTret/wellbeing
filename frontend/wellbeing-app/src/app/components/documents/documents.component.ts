import { Component, ElementRef, OnInit, ViewChild } from '@angular/core';
import { ApiService } from '../../services/api/api.service';

@Component({
  selector: 'app-documents',
  templateUrl: './documents.component.html',
  styleUrls: ['./documents.component.css']
})
export class DocumentsComponent implements OnInit {
  @ViewChild('fileInput') fileInput!: ElementRef<HTMLInputElement>;

  loading = true;
  uploading = false;
  dragOver = false;
  error = '';

  docs: any[] = [];

  readonly typeColors: { [key: string]: string } = {
    pdf:  '#C62828',
    jpg:  '#1565C0',
    jpeg: '#1565C0',
    png:  '#6A1B9A',
    doc:  '#1B5E20',
    docx: '#1B5E20',
  };

  constructor(private api: ApiService) {}

  ngOnInit(): void {
    this.loadDocs();
  }

  loadDocs(): void {
    this.loading = true;
    this.api.getDocuments().subscribe({
      next: (res: any) => {
        this.docs = res?.items ?? [];
        this.loading = false;
      },
      error: () => { this.loading = false; }
    });
  }

  typeColor(type: string): string {
    return this.typeColors[type?.toLowerCase()] ?? '#555';
  }

  openFilePicker(): void {
    this.fileInput.nativeElement.click();
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file) this.uploadFile(file);
    input.value = '';
  }

  onDragOver(event: DragEvent): void {
    event.preventDefault();
    this.dragOver = true;
  }

  onDragLeave(): void { this.dragOver = false; }

  onDrop(event: DragEvent): void {
    event.preventDefault();
    this.dragOver = false;
    const file = event.dataTransfer?.files?.[0];
    if (file) this.uploadFile(file);
  }

  uploadFile(file: File): void {
    this.error = '';
    this.uploading = true;
    this.api.uploadDocument(file).subscribe({
      next: (doc: any) => {
        this.uploading = false;
        if (doc?.id) this.docs = [doc, ...this.docs];
      },
      error: (err) => {
        this.uploading = false;
        this.error = err?.error?.error || 'Не вдалось завантажити файл';
      }
    });
  }

  download(d: any): void {
    if (!d?.url) return;
    const a = document.createElement('a');
    a.href = d.url;
    a.download = d.name;
    a.target = '_blank';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  remove(d: any): void {
    if (!confirm(`Видалити "${d.name}"?`)) return;
    this.api.deleteDocument(d.id).subscribe({
      next: () => { this.docs = this.docs.filter(x => x.id !== d.id); }
    });
  }
}
