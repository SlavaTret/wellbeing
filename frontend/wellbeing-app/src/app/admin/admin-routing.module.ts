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
import { SpecialistGuard } from './guards/specialist.guard';
import { AdminOnlyGuard } from './guards/admin-only.guard';
import { SpecialistDashboardComponent } from './components/specialist-dashboard/specialist-dashboard.component';
import { SpecialistAppointmentsComponent } from './components/specialist-appointments/specialist-appointments.component';
import { SpecialistSlotsComponent } from './components/specialist-slots/specialist-slots.component';

const routes: Routes = [
  { path: 'login', component: AdminLoginComponent, data: { title: 'Вхід в адмінпанель' } },
  {
    path: '',
    component: AdminLayoutComponent,
    canActivate: [AdminGuard],
    children: [
      { path: '',               redirectTo: 'dashboard', pathMatch: 'full' },
      { path: 'dashboard',      component: AdminDashboardComponent,        canActivate: [AdminOnlyGuard], data: { title: 'Дашборд' } },
      { path: 'companies',      component: AdminCompaniesComponent,        canActivate: [AdminOnlyGuard], data: { title: 'Компанії' } },
      { path: 'users',          component: AdminUsersComponent,            canActivate: [AdminOnlyGuard], data: { title: 'Користувачі' } },
      { path: 'payments',       component: AdminPaymentsPageComponent,     canActivate: [AdminOnlyGuard], data: { title: 'Платежі' } },
      { path: 'specialists',    component: AdminSpecialistsComponent,      canActivate: [AdminOnlyGuard], data: { title: 'Спеціалісти' } },
      { path: 'appointments',   component: AdminAppointmentsPageComponent, canActivate: [AdminOnlyGuard], data: { title: 'Записи' } },
      { path: 'categories',     component: AdminCategoriesComponent,       canActivate: [AdminOnlyGuard], data: { title: 'Категорії' } },
      { path: 'specializations',component: AdminSpecializationsComponent,  canActivate: [AdminOnlyGuard], data: { title: 'Спеціалізації' } },
      { path: 'slots',          component: AdminSlotsComponent,            canActivate: [AdminOnlyGuard], data: { title: 'Розклад' } },
      { path: 'settings',       component: AdminSettingsComponent,         canActivate: [AdminOnlyGuard], data: { title: 'Налаштування' } },
      { path: 'surveys',        component: AdminSurveysComponent,          canActivate: [AdminOnlyGuard], data: { title: 'Опитування' } },
      {
        path: 'specialist',
        canActivate: [SpecialistGuard],
        children: [
          { path: '',             redirectTo: 'dashboard', pathMatch: 'full' },
          { path: 'dashboard',    component: SpecialistDashboardComponent,    data: { title: 'Дашборд' } },
          { path: 'appointments', component: SpecialistAppointmentsComponent, data: { title: 'Мої записи' } },
          { path: 'slots',        component: SpecialistSlotsComponent,        data: { title: 'Мій розклад' } },
        ],
      },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class AdminRoutingModule {}
