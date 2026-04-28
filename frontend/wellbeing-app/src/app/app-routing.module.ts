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
import { LoginComponent } from './components/auth/login/login.component';
import { RegisterComponent } from './components/auth/register/register.component';
import { AuthGuard } from './guards/auth.guard';
import { AdminLoginComponent } from './admin/components/login/admin-login.component';
import { AdminLayoutComponent } from './admin/components/layout/admin-layout.component';
import { AdminDashboardComponent } from './admin/components/dashboard/admin-dashboard.component';
import { AdminCompaniesComponent } from './admin/components/companies/admin-companies.component';
import { AdminUsersComponent } from './admin/components/users/admin-users.component';
import { AdminPaymentsPageComponent } from './admin/components/payments/admin-payments.component';
import { AdminSpecialistsComponent } from './admin/components/specialists/admin-specialists.component';
import { AdminAppointmentsPageComponent } from './admin/components/appointments/admin-appointments.component';
import { AdminCategoriesComponent } from './admin/components/categories/admin-categories.component';
import { AdminSlotsComponent } from './admin/components/slots/admin-slots.component';
import { AdminGuard } from './admin/guards/admin.guard';

const routes: Routes = [
  { path: '', redirectTo: '/dashboard', pathMatch: 'full' },
  { path: 'login', component: LoginComponent },
  { path: 'register', component: RegisterComponent },
  { path: 'dashboard', component: DashboardComponent, canActivate: [AuthGuard] },
  { path: 'profile', component: ProfileComponent, canActivate: [AuthGuard] },
  { path: 'appointments', component: AppointmentsComponent, canActivate: [AuthGuard] },
  { path: 'documents', component: DocumentsComponent, canActivate: [AuthGuard] },
  { path: 'payments', component: PaymentsComponent, canActivate: [AuthGuard] },
  { path: 'notifications', component: NotificationsComponent, canActivate: [AuthGuard] },
  { path: 'questionnaire', component: QuestionnaireComponent, canActivate: [AuthGuard] },
  { path: 'support', component: SupportComponent, canActivate: [AuthGuard] },
  { path: 'admin/login', component: AdminLoginComponent },
  {
    path: 'admin',
    component: AdminLayoutComponent,
    canActivate: [AdminGuard],
    children: [
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
      { path: 'dashboard', component: AdminDashboardComponent },
      { path: 'companies', component: AdminCompaniesComponent },
      { path: 'users', component: AdminUsersComponent },
      { path: 'payments', component: AdminPaymentsPageComponent },
      { path: 'specialists', component: AdminSpecialistsComponent },
      { path: 'appointments', component: AdminAppointmentsPageComponent },
      { path: 'categories', component: AdminCategoriesComponent },
      { path: 'slots', component: AdminSlotsComponent },
    ]
  }
];

@NgModule({
  imports: [RouterModule.forRoot(routes)],
  exports: [RouterModule]
})
export class AppRoutingModule { }
