import { NgModule } from '@angular/core';
import { SharedModule } from '../shared/shared.module';
import { AdminRoutingModule } from './admin-routing.module';
import { AdminLoginComponent } from './components/login/admin-login.component';
import { AdminLayoutComponent } from './components/layout/admin-layout.component';
import { AdminDashboardComponent } from './components/dashboard/admin-dashboard.component';
import { AdminCompaniesComponent } from './components/companies/admin-companies.component';
import { AdminUsersComponent } from './components/users/admin-users.component';
import { AdminPaymentsPageComponent } from './components/payments/admin-payments.component';
import { AdminSpecialistsComponent } from './components/specialists/admin-specialists.component';
import { AdminAppointmentsPageComponent } from './components/appointments/admin-appointments.component';
import { AdminCategoriesComponent } from './components/categories/admin-categories.component';
import { AdminSpecializationsComponent } from './components/specializations/admin-specializations.component';
import { AdminSlotsComponent } from './components/slots/admin-slots.component';
import { AdminSettingsComponent } from './components/settings/admin-settings.component';
import { AdminSurveysComponent } from './components/surveys/admin-surveys.component';

@NgModule({
  declarations: [
    AdminLoginComponent,
    AdminLayoutComponent,
    AdminDashboardComponent,
    AdminCompaniesComponent,
    AdminUsersComponent,
    AdminPaymentsPageComponent,
    AdminSpecialistsComponent,
    AdminAppointmentsPageComponent,
    AdminCategoriesComponent,
    AdminSpecializationsComponent,
    AdminSlotsComponent,
    AdminSettingsComponent,
    AdminSurveysComponent,
  ],
  imports: [
    SharedModule,
    AdminRoutingModule,
  ],
})
export class AdminModule {}
