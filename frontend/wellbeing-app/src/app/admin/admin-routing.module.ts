import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdminGuard } from './guards/admin.guard';
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

const routes: Routes = [
  { path: 'login', component: AdminLoginComponent, data: { title: 'Вхід в адмінпанель' } },
  {
    path: '',
    component: AdminLayoutComponent,
    canActivate: [AdminGuard],
    children: [
      { path: '',               redirectTo: 'dashboard', pathMatch: 'full' },
      { path: 'dashboard',      component: AdminDashboardComponent,        data: { title: 'Дашборд' } },
      { path: 'companies',      component: AdminCompaniesComponent,        data: { title: 'Компанії' } },
      { path: 'users',          component: AdminUsersComponent,            data: { title: 'Користувачі' } },
      { path: 'payments',       component: AdminPaymentsPageComponent,     data: { title: 'Платежі' } },
      { path: 'specialists',    component: AdminSpecialistsComponent,      data: { title: 'Спеціалісти' } },
      { path: 'appointments',   component: AdminAppointmentsPageComponent, data: { title: 'Записи' } },
      { path: 'categories',     component: AdminCategoriesComponent,       data: { title: 'Категорії' } },
      { path: 'specializations',component: AdminSpecializationsComponent,  data: { title: 'Спеціалізації' } },
      { path: 'slots',          component: AdminSlotsComponent,            data: { title: 'Розклад' } },
      { path: 'settings',       component: AdminSettingsComponent,         data: { title: 'Налаштування' } },
      { path: 'surveys',        component: AdminSurveysComponent,          data: { title: 'Опитування' } },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class AdminRoutingModule {}
