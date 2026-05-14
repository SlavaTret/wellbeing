import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { TranslateModule } from '@ngx-translate/core';
import { IconComponent } from '../components/shared/icon/icon.component';
import { LangSwitcherComponent } from '../components/shared/lang-switcher/lang-switcher.component';
import { WysiwygComponent } from '../components/shared/wysiwyg/wysiwyg.component';
import { LegalModalComponent } from '../components/shared/legal-modal/legal-modal.component';

@NgModule({
  declarations: [
    IconComponent,
    LangSwitcherComponent,
    WysiwygComponent,
    LegalModalComponent,
  ],
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    TranslateModule,
  ],
  exports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    TranslateModule,
    IconComponent,
    LangSwitcherComponent,
    WysiwygComponent,
    LegalModalComponent,
  ],
})
export class SharedModule {}
