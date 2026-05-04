import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class AdminApiService {
  private readonly apiUrl = '/api/v1';
  private readonly TOKEN_KEY = 'admin_access_token';
  private readonly USER_KEY  = 'admin_user';

  constructor(private http: HttpClient) {}

  getAdminToken(): string | null { return localStorage.getItem(this.TOKEN_KEY); }

  setAdminSession(token: string, user: any): void {
    localStorage.setItem(this.TOKEN_KEY, token);
    localStorage.setItem(this.USER_KEY, JSON.stringify(user));
  }

  clearAdminSession(): void {
    localStorage.removeItem(this.TOKEN_KEY);
    localStorage.removeItem(this.USER_KEY);
  }

  isAdminLoggedIn(): boolean { return !!this.getAdminToken(); }

  getAdminUser(): any {
    try { return JSON.parse(localStorage.getItem(this.USER_KEY) || 'null'); } catch { return null; }
  }

  private authHeaders(): HttpHeaders {
    return new HttpHeaders({ Authorization: `Bearer ${this.getAdminToken()}` });
  }

  login(email: string, password: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/user/login`, { email, password });
  }

  getDashboard(): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/dashboard`, { headers: this.authHeaders() });
  }

  uploadLogo(file: File): Observable<any> {
    const fd = new FormData();
    fd.append('logo', file, file.name);
    return this.http.post(`${this.apiUrl}/admin/upload-logo`, fd, { headers: new HttpHeaders({ Authorization: `Bearer ${this.getAdminToken()}` }) });
  }

  getAdminCompanies(): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/companies`, { headers: this.authHeaders() });
  }

  createCompany(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/companies`, data, { headers: this.authHeaders() });
  }

  updateCompany(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/companies/${id}`, data, { headers: this.authHeaders() });
  }

  deleteCompany(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/companies/${id}`, { headers: this.authHeaders() });
  }

  getAdminUsers(params: { search?: string; status?: string; page?: number; per_page?: number } = {}): Observable<any> {
    const parts: string[] = [];
    if (params.search)                  parts.push(`search=${encodeURIComponent(params.search)}`);
    if (params.status)                  parts.push(`status=${params.status}`);
    if (params.page && params.page > 1) parts.push(`page=${params.page}`);
    if (params.per_page)                parts.push(`per_page=${params.per_page}`);
    const query = parts.length ? '?' + parts.join('&') : '';
    return this.http.get(`${this.apiUrl}/admin/users${query}`, { headers: this.authHeaders() });
  }

  createUser(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/users`, data, { headers: this.authHeaders() });
  }

  updateUser(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/users/${id}`, data, { headers: this.authHeaders() });
  }

  deleteUser(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/users/${id}`, { headers: this.authHeaders() });
  }

  getSpecialists(): Observable<any> {
    return this.http.get(`${this.apiUrl}/specialist`, { headers: this.authHeaders() });
  }

  getAdminCategories(search = ''): Observable<any> {
    const q = search ? `?search=${encodeURIComponent(search)}` : '';
    return this.http.get(`${this.apiUrl}/admin/categories${q}`, { headers: this.authHeaders() });
  }

  createCategory(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/categories`, data, { headers: this.authHeaders() });
  }

  updateCategory(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/categories/${id}`, data, { headers: this.authHeaders() });
  }

  deleteCategory(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/categories/${id}`, { headers: this.authHeaders() });
  }

  getAdminPayments(params: { search?: string; status?: string; page?: number } = {}): Observable<any> {
    const parts: string[] = [];
    if (params.search)                  parts.push(`search=${encodeURIComponent(params.search)}`);
    if (params.status && params.status !== 'all') parts.push(`status=${params.status}`);
    if (params.page && params.page > 1) parts.push(`page=${params.page}`);
    const query = parts.length ? '?' + parts.join('&') : '';
    return this.http.get(`${this.apiUrl}/admin/payments${query}`, { headers: this.authHeaders() });
  }

  updateAdminPayment(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/payments/${id}`, data, { headers: this.authHeaders() });
  }

  getAdminAppointments(params: { search?: string; status?: string; page?: number } = {}): Observable<any> {
    const parts: string[] = [];
    if (params.search)                  parts.push(`search=${encodeURIComponent(params.search)}`);
    if (params.status && params.status !== 'all') parts.push(`status=${params.status}`);
    if (params.page && params.page > 1) parts.push(`page=${params.page}`);
    const query = parts.length ? '?' + parts.join('&') : '';
    return this.http.get(`${this.apiUrl}/admin/appointments${query}`, { headers: this.authHeaders() });
  }

  createAdminAppointment(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/appointments`, data, { headers: this.authHeaders() });
  }

  updateAdminAppointment(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/appointments/${id}`, data, { headers: this.authHeaders() });
  }

  deleteAdminAppointment(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/appointments/${id}`, { headers: this.authHeaders() });
  }

  getAdminSpecialists(search = ''): Observable<any> {
    const q = search ? `?search=${encodeURIComponent(search)}` : '';
    return this.http.get(`${this.apiUrl}/admin/specialists${q}`, { headers: this.authHeaders() });
  }

  createSpecialist(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/specialists`, data, { headers: this.authHeaders() });
  }

  updateSpecialist(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/specialists/${id}`, data, { headers: this.authHeaders() });
  }

  deleteSpecialist(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/specialists/${id}`, { headers: this.authHeaders() });
  }

  uploadSpecialistAvatar(id: number, file: File): Observable<any> {
    const fd = new FormData();
    fd.append('avatar', file, file.name);
    return this.http.post(
      `${this.apiUrl}/admin/specialists/${id}/upload-avatar`,
      fd,
      { headers: new HttpHeaders({ Authorization: `Bearer ${this.getAdminToken()}` }) }
    );
  }

  getAdminSpecializations(): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/specializations`, { headers: this.authHeaders() });
  }

  createSpecialization(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/specializations`, data, { headers: this.authHeaders() });
  }

  updateSpecialization(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/specializations/${id}`, data, { headers: this.authHeaders() });
  }

  deleteSpecialization(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/specializations/${id}`, { headers: this.authHeaders() });
  }

  getAdminSpecialistAvailableSlots(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/specialists/${id}/available-slots`, { headers: this.authHeaders() });
  }

  getSpecialistSlots(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/specialists/${id}/slots`, { headers: this.authHeaders() });
  }

  saveSpecialistSlots(id: number, slots: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/specialists/${id}/slots`, { slots }, { headers: this.authHeaders() });
  }

  getSpecialistWeekSchedule(id: number, from?: string): Observable<any> {
    const q = from ? `?from=${from}` : '';
    return this.http.get(`${this.apiUrl}/admin/specialists/${id}/week-schedule${q}`, { headers: this.authHeaders() });
  }

  blockSpecialistDate(id: number, date: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/specialists/${id}/block-date`, { date }, { headers: this.authHeaders() });
  }

  unblockSpecialistDate(id: number, date: string): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/specialists/${id}/block-date`, {
      headers: this.authHeaders(), body: { date }
    });
  }

  // ==================== APP SETTINGS ====================
  getAdminSettings(): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/settings`, { headers: this.authHeaders() });
  }

  saveAdminSettings(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/settings`, data, { headers: this.authHeaders() });
  }

  // ==================== PAYMENT SETTINGS ====================
  getPaymentSettings(): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/payment-settings`, { headers: this.authHeaders() });
  }

  savePaymentSettings(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/payment-settings`, data, { headers: this.authHeaders() });
  }

  checkPaymentStatus(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/payments/${id}/check-status`, {}, { headers: this.authHeaders() });
  }
}
