import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AuthGuard } from '../guards/auth.guard';
import { DashboardComponent } from '../components/dashboard/dashboard.component';
import { ProfileComponent } from '../components/profile/profile.component';
import { AppointmentsComponent } from '../components/appointments/appointments.component';
import { DocumentsComponent } from '../components/documents/documents.component';
import { PaymentsComponent } from '../components/payments/payments.component';
import { NotificationsComponent } from '../components/notifications/notifications.component';
import { QuestionnaireComponent } from '../components/questionnaire/questionnaire.component';
import { SupportComponent } from '../components/support/support.component';
import { SettingsComponent } from '../components/settings/settings.component';

const routes: Routes = [
  { path: '',              redirectTo: 'dashboard', pathMatch: 'full' },
  { path: 'dashboard',     component: DashboardComponent,     canActivate: [AuthGuard], data: { title: 'Дашборд' } },
  { path: 'profile',       component: ProfileComponent,       canActivate: [AuthGuard], data: { title: 'Профіль' } },
  { path: 'appointments',  component: AppointmentsComponent,  canActivate: [AuthGuard], data: { title: 'Записи' } },
  { path: 'documents',     component: DocumentsComponent,     canActivate: [AuthGuard], data: { title: 'Документи' } },
  { path: 'payments',      component: PaymentsComponent,      canActivate: [AuthGuard], data: { title: 'Оплата' } },
  { path: 'notifications', component: NotificationsComponent, canActivate: [AuthGuard], data: { title: 'Сповіщення' } },
  { path: 'questionnaire', component: QuestionnaireComponent, canActivate: [AuthGuard], data: { title: 'Анкета' } },
  { path: 'support',       component: SupportComponent,       canActivate: [AuthGuard], data: { title: 'Підтримка' } },
  { path: 'settings',      component: SettingsComponent,      canActivate: [AuthGuard], data: { title: 'Налаштування' } },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class UserRoutingModule {}
