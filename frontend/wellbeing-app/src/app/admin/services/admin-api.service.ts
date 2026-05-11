import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class AdminApiService {
  private readonly apiUrl   = '/api/v1';
  private readonly TOKEN_KEY = 'admin_access_token';
  private readonly USER_KEY  = 'admin_user';

  constructor(private http: HttpClient) {}

  getAdminToken(): string | null { return localStorage.getItem(this.TOKEN_KEY); }

  setAdminSession(token: string, user: any): void {
    localStorage.setItem(this.TOKEN_KEY, token);
    localStorage.setItem(this.USER_KEY, JSON.stringify({
      id: user.id, email: user.email, role: user.role ?? 'user', is_admin: !!user.is_admin,
      first_name: user.first_name, last_name: user.last_name
    }));
  }

  clearAdminSession(): void {
    [this.TOKEN_KEY, this.USER_KEY].forEach(k => localStorage.removeItem(k));
  }

  isAdminLoggedIn(): boolean { return !!this.getAdminToken(); }

  isSpecialist(): boolean { return this.getAdminUser()?.role === 'specialist'; }
  isAdmin(): boolean { return !!this.getAdminUser()?.is_admin; }

  getAdminUser(): any {
    try { return JSON.parse(localStorage.getItem(this.USER_KEY) || 'null'); } catch { return null; }
  }

  login(email: string, password: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/user/login`, { email, password });
  }

  // ==================== DASHBOARD ====================
  getDashboard(): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/dashboard`);
  }

  // ==================== COMPANIES ====================
  uploadLogo(file: File): Observable<any> {
    const fd = new FormData();
    fd.append('logo', file, file.name);
    return this.http.post(`${this.apiUrl}/admin/upload-logo`, fd);
  }

  getAdminCompanies(): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/companies`);
  }

  createCompany(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/companies`, data);
  }

  updateCompany(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/companies/${id}`, data);
  }

  deleteCompany(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/companies/${id}`);
  }

  // ==================== USERS ====================
  getAdminUsers(params: { search?: string; status?: string; page?: number; per_page?: number } = {}): Observable<any> {
    const parts: string[] = [];
    if (params.search)                  parts.push(`search=${encodeURIComponent(params.search)}`);
    if (params.status)                  parts.push(`status=${params.status}`);
    if (params.page && params.page > 1) parts.push(`page=${params.page}`);
    if (params.per_page)                parts.push(`per_page=${params.per_page}`);
    const query = parts.length ? '?' + parts.join('&') : '';
    return this.http.get(`${this.apiUrl}/admin/users${query}`);
  }

  createUser(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/users`, data);
  }

  updateUser(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/users/${id}`, data);
  }

  deleteUser(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/users/${id}`);
  }

  // ==================== CATEGORIES ====================
  getAdminCategories(search = ''): Observable<any> {
    const q = search ? `?search=${encodeURIComponent(search)}` : '';
    return this.http.get(`${this.apiUrl}/admin/categories${q}`);
  }

  createCategory(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/categories`, data);
  }

  updateCategory(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/categories/${id}`, data);
  }

  deleteCategory(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/categories/${id}`);
  }

  // ==================== PAYMENTS ====================
  getAdminPayments(params: { search?: string; status?: string; page?: number } = {}): Observable<any> {
    const parts: string[] = [];
    if (params.search)                  parts.push(`search=${encodeURIComponent(params.search)}`);
    if (params.status && params.status !== 'all') parts.push(`status=${params.status}`);
    if (params.page && params.page > 1) parts.push(`page=${params.page}`);
    const query = parts.length ? '?' + parts.join('&') : '';
    return this.http.get(`${this.apiUrl}/admin/payments${query}`);
  }

  updateAdminPayment(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/payments/${id}`, data);
  }

  checkPaymentStatus(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/payments/${id}/check-status`, {});
  }

  // ==================== APPOINTMENTS ====================
  getAdminAppointments(params: { search?: string; status?: string; page?: number } = {}): Observable<any> {
    const parts: string[] = [];
    if (params.search)                  parts.push(`search=${encodeURIComponent(params.search)}`);
    if (params.status && params.status !== 'all') parts.push(`status=${params.status}`);
    if (params.page && params.page > 1) parts.push(`page=${params.page}`);
    const query = parts.length ? '?' + parts.join('&') : '';
    return this.http.get(`${this.apiUrl}/admin/appointments${query}`);
  }

  createAdminAppointment(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/appointments`, data);
  }

  updateAdminAppointment(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/appointments/${id}`, data);
  }

  deleteAdminAppointment(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/appointments/${id}`);
  }

  // ==================== SPECIALISTS ====================
  getAdminSpecialists(search = ''): Observable<any> {
    const q = search ? `?search=${encodeURIComponent(search)}` : '';
    return this.http.get(`${this.apiUrl}/admin/specialists${q}`);
  }

  createSpecialist(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/specialists`, data);
  }

  updateSpecialist(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/specialists/${id}`, data);
  }

  deleteSpecialist(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/specialists/${id}`);
  }

  uploadSpecialistAvatar(id: number, file: File): Observable<any> {
    const fd = new FormData();
    fd.append('avatar', file, file.name);
    return this.http.post(`${this.apiUrl}/admin/specialists/${id}/upload-avatar`, fd);
  }

  getAdminSpecialistAvailableSlots(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/specialists/${id}/available-slots`);
  }

  getSpecialistSlots(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/specialists/${id}/slots`);
  }

  saveSpecialistSlots(id: number, slots: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/specialists/${id}/slots`, { slots });
  }

  getSpecialistWeekSchedule(id: number, from?: string): Observable<any> {
    const q = from ? `?from=${from}` : '';
    return this.http.get(`${this.apiUrl}/admin/specialists/${id}/week-schedule${q}`);
  }

  blockSpecialistDate(id: number, date: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/specialists/${id}/block-date`, { date });
  }

  unblockSpecialistDate(id: number, date: string): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/specialists/${id}/block-date`, { body: { date } });
  }

  // ==================== SPECIALIZATIONS ====================
  getAdminSpecializations(): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/specializations`);
  }

  createSpecialization(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/specializations`, data);
  }

  updateSpecialization(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/specializations/${id}`, data);
  }

  deleteSpecialization(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/specializations/${id}`);
  }

  // ==================== APP SETTINGS ====================
  getAdminSettings(): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/settings`);
  }

  saveAdminSettings(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/settings`, data);
  }

  uploadFavicon(file: File): Observable<any> {
    const fd = new FormData();
    fd.append('favicon', file, file.name);
    return this.http.post(`${this.apiUrl}/admin/settings/upload-favicon`, fd);
  }

  // ==================== PAYMENT SETTINGS ====================
  getPaymentSettings(): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/payment-settings`);
  }

  savePaymentSettings(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/payment-settings`, data);
  }

  // ==================== SPECIALISTS (public read via admin) ====================
  getSpecialists(): Observable<any> {
    return this.http.get(`${this.apiUrl}/specialist`);
  }

  linkSpecialistUser(id: number, data: { email: string; password: string }): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/specialists/${id}/link-user`, data);
  }

  unlinkSpecialistUser(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/specialists/${id}/link-user`);
  }

  // ==================== SPECIALIST PANEL ====================
  getSpecialistDashboard(): Observable<any> {
    return this.http.get(`${this.apiUrl}/specialist-panel/dashboard`);
  }

  getMyAppointments(params: { search?: string; status?: string; page?: number } = {}): Observable<any> {
    const parts: string[] = [];
    if (params.search)                  parts.push(`search=${encodeURIComponent(params.search)}`);
    if (params.status && params.status !== 'all') parts.push(`status=${params.status}`);
    if (params.page && params.page > 1) parts.push(`page=${params.page}`);
    const q = parts.length ? '?' + parts.join('&') : '';
    return this.http.get(`${this.apiUrl}/specialist-panel/appointments${q}`);
  }

  updateMyAppointment(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/specialist-panel/appointments/${id}`, data);
  }

  getMySlots(): Observable<any> {
    return this.http.get(`${this.apiUrl}/specialist-panel/my-slots`);
  }

  saveMySlots(slots: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/specialist-panel/my-slots`, { slots });
  }

  getMyWeekSchedule(from?: string): Observable<any> {
    const q = from ? `?from=${from}` : '';
    return this.http.get(`${this.apiUrl}/specialist-panel/my-week-schedule${q}`);
  }

  blockMyDate(date: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/specialist-panel/block-date`, { date });
  }

  unblockMyDate(date: string): Observable<any> {
    return this.http.delete(`${this.apiUrl}/specialist-panel/block-date`, { body: { date } });
  }
}
