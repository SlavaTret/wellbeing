import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { DashboardComponent } from './components/dashboard/dashboard.component';
import { ProfileComponent } from './components/profile/profile.component';
import { AppointmentsComponent } from './components/appointments/appointments.component';
import { DocumentsComponent } from './components/documents/documents.component';
import { PaymentsComponent } from './components/payments/payments.component';
import { NotificationsComponent } from './components/notifications/notifications.component';
import { QuestionnaireComponent } from './components/questionnaire/questionnaire.component';
import { SupportComponent } from './components/support/support.component';

const routes: Routes = [
  { path: '', redirectTo: '/dashboard', pathMatch: 'full' },
  { path: 'dashboard', component: DashboardComponent },
  { path: 'profile', component: ProfileComponent },
  { path: 'appointments', component: AppointmentsComponent },
  { path: 'documents', component: DocumentsComponent },
  { path: 'payments', component: PaymentsComponent },
  { path: 'notifications', component: NotificationsComponent },
  { path: 'questionnaire', component: QuestionnaireComponent },
  { path: 'support', component: SupportComponent }
];

@NgModule({
  imports: [RouterModule.forRoot(routes)],
  exports: [RouterModule]
})
export class AppRoutingModule { }
