import { NgModule } from '@angular/core';
import { SharedModule } from '../shared/shared.module';
import { UserRoutingModule } from './user-routing.module';
import { DashboardComponent } from '../components/dashboard/dashboard.component';
import { ProfileComponent } from '../components/profile/profile.component';
import { AppointmentsComponent } from '../components/appointments/appointments.component';
import { DocumentsComponent } from '../components/documents/documents.component';
import { PaymentsComponent } from '../components/payments/payments.component';
import { NotificationsComponent } from '../components/notifications/notifications.component';
import { QuestionnaireComponent } from '../components/questionnaire/questionnaire.component';
import { SupportComponent } from '../components/support/support.component';
import { SettingsComponent } from '../components/settings/settings.component';

@NgModule({
  declarations: [
    DashboardComponent,
    ProfileComponent,
    AppointmentsComponent,
    DocumentsComponent,
    PaymentsComponent,
    NotificationsComponent,
    QuestionnaireComponent,
    SupportComponent,
    SettingsComponent,
  ],
  imports: [
    SharedModule,
    UserRoutingModule,
  ],
})
export class UserModule {}
