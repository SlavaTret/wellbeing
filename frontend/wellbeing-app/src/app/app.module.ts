import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { HttpClientModule, HTTP_INTERCEPTORS } from '@angular/common/http';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';

import { AppRoutingModule } from './app-routing.module';
import { AppComponent } from './app.component';
import { LoginComponent } from './components/auth/login/login.component';
import { RegisterComponent } from './components/auth/register/register.component';
import { AuthInterceptor } from './interceptors/auth.interceptor';
import { DashboardComponent } from './components/dashboard/dashboard.component';
import { ProfileComponent } from './components/profile/profile.component';
import { AppointmentsComponent } from './components/appointments/appointments.component';
import { DocumentsComponent } from './components/documents/documents.component';
import { PaymentsComponent } from './components/payments/payments.component';
import { NotificationsComponent } from './components/notifications/notifications.component';
import { QuestionnaireComponent } from './components/questionnaire/questionnaire.component';
import { SupportComponent } from './components/support/support.component';
import { IconComponent } from './components/shared/icon/icon.component';
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
import { AdminSettingsComponent } from './admin/components/settings/admin-settings.component';
import { SettingsComponent } from './components/settings/settings.component';

@NgModule({
  declarations: [
    AppComponent,
    IconComponent,
    LoginComponent,
    RegisterComponent,
    DashboardComponent,
    ProfileComponent,
    AppointmentsComponent,
    DocumentsComponent,
    PaymentsComponent,
    NotificationsComponent,
    QuestionnaireComponent,
    SupportComponent,
    AdminLoginComponent,
    AdminLayoutComponent,
    AdminDashboardComponent,
    AdminCompaniesComponent,
    AdminUsersComponent,
    AdminPaymentsPageComponent,
    AdminSpecialistsComponent,
    AdminAppointmentsPageComponent,
    AdminCategoriesComponent,
    AdminSlotsComponent,
    AdminSettingsComponent,
    SettingsComponent,
  ],
  imports: [
    BrowserModule,
    AppRoutingModule,
    HttpClientModule,
    FormsModule,
    ReactiveFormsModule
  ],
  providers: [
    {
      provide: HTTP_INTERCEPTORS,
      useClass: AuthInterceptor,
      multi: true
    }
  ],
  bootstrap: [AppComponent]
})
export class AppModule { }
