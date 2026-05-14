import {
  Component, ElementRef, forwardRef, OnDestroy, ViewChild, ChangeDetectionStrategy
} from '@angular/core';
import { ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';

@Component({
  selector: 'app-wysiwyg',
  templateUrl: './wysiwyg.component.html',
  styleUrls: ['./wysiwyg.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [{ provide: NG_VALUE_ACCESSOR, useExisting: forwardRef(() => WysiwygComponent), multi: true }],
})
export class WysiwygComponent implements ControlValueAccessor, OnDestroy {
  @ViewChild('editor', { static: true }) editorRef!: ElementRef<HTMLDivElement>;

  private onChange: (v: string) => void = () => {};
  private onTouched: () => void = () => {};
  disabled = false;

  exec(cmd: string, value: string | undefined = undefined): void {
    this.editorRef.nativeElement.focus();
    document.execCommand(cmd, false, value);
    this.emitChange();
  }

  insertLink(): void {
    const url = prompt('URL посилання:', 'https://');
    if (url) this.exec('createLink', url);
  }

  onInput(): void {
    this.emitChange();
  }

  onBlur(): void {
    this.onTouched();
  }

  private emitChange(): void {
    this.onChange(this.editorRef.nativeElement.innerHTML);
  }

  writeValue(html: string): void {
    if (this.editorRef?.nativeElement) {
      this.editorRef.nativeElement.innerHTML = html ?? '';
    }
  }

  registerOnChange(fn: (v: string) => void): void { this.onChange = fn; }
  registerOnTouched(fn: () => void): void { this.onTouched = fn; }
  setDisabledState(isDisabled: boolean): void { this.disabled = isDisabled; }

  ngOnDestroy(): void {}
}
