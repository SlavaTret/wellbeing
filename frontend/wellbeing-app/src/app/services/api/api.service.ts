import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { tap } from 'rxjs/operators';

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private apiUrl = '/api/v1';
  private accessToken: string | null = null;

  constructor(private http: HttpClient) {
    this.accessToken = this.readTokenWithTTL();
  }

  private readTokenWithTTL(): string | null {
    const token  = localStorage.getItem('access_token');
    const savedAt = +(localStorage.getItem('access_token_at') || 0);
    if (!token) return null;
    // Токен старший 24 годин — очищаємо без запиту до сервера
    if (savedAt && Date.now() - savedAt > 86_400_000) {
      this.clearAccessToken();
      return null;
    }
    return token;
  }

  // ==================== AUTH ====================
  register(payload: {
    email: string; password: string; firstName: string; lastName: string; companyId?: number | null; acceptedTerms?: boolean; recaptchaToken?: string;
  }): Observable<any> {
    return this.http.post(`${this.apiUrl}/user/register`, {
      email: payload.email,
      password: payload.password,
      first_name: payload.firstName,
      last_name: payload.lastName,
      company_id: payload.companyId ?? null,
      accepted_terms: payload.acceptedTerms ?? false,
      recaptcha_token: payload.recaptchaToken ?? ''
    }).pipe(
      tap((response: any) => {
        if (response.access_token) {
          this.setAccessToken(response.access_token);
        }
      })
    );
  }

  // ==================== PORTAL SETTINGS ====================
  getPortalSettings(): Observable<any> {
    return this.http.get(`${this.apiUrl}/portal-settings`);
  }

  // ==================== COMPANIES ====================
  getCompanies(): Observable<any> {
    return this.http.get(`${this.apiUrl}/company`);
  }

  login(email: string, password: string, recaptchaToken = ''): Observable<any> {
    return this.http.post(`${this.apiUrl}/user/login`, { email, password, recaptcha_token: recaptchaToken }).pipe(
      tap((response: any) => {
        if (response.access_token) {
          this.setAccessToken(response.access_token);
        }
      })
    );
  }

  logout(): Observable<any> {
    return this.http.post(`${this.apiUrl}/user/logout`, {}).pipe(
      tap(() => {
        this.clearAccessToken();
      })
    );
  }

  setAccessToken(token: string): void {
    this.accessToken = token;
    localStorage.setItem('access_token', token);
    localStorage.setItem('access_token_at', Date.now().toString());
  }

  clearAccessToken(): void {
    this.accessToken = null;
    localStorage.removeItem('access_token');
    localStorage.removeItem('access_token_at');
  }

  getAccessToken(): string | null {
    return this.accessToken;
  }

  isLoggedIn(): boolean {
    return !!this.accessToken;
  }

  // ==================== USER / PROFILE ====================
  getProfile(): Observable<any> {
    return this.http.get(`${this.apiUrl}/user/profile`);
  }

  updateProfile(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/user/update-profile`, data);
  }

  uploadAvatar(file: File): Observable<any> {
    const fd = new FormData();
    fd.append('avatar', file);
    return this.http.post(`${this.apiUrl}/user/upload-avatar`, fd);
  }

  // ==================== DASHBOARD ====================
  getDashboard(): Observable<any> {
    return this.http.get(`${this.apiUrl}/dashboard`);
  }

  getFreeSessions(): Observable<any> {
    return this.http.get(`${this.apiUrl}/dashboard/free-sessions`);
  }

  // ==================== SPECIALISTS ====================
  getSpecialists(): Observable<any> {
    return this.http.get(`${this.apiUrl}/specialist`);
  }

  getCategories(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/categories`);
  }

  reviewSpecialist(specialistId: number, rating: number, comment: string, appointmentId?: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/specialist/${specialistId}/review`, {
      rating,
      comment,
      appointment_id: appointmentId ?? null,
    });
  }

  // ==================== APPOINTMENTS ====================
  getAppointments(status?: string): Observable<any> {
    let params = new HttpParams();
    if (status) {
      params = params.set('status', status);
    }
    return this.http.get(`${this.apiUrl}/appointment`, { params });
  }

  getAppointment(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/appointment/${id}`);
  }

  createAppointment(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/appointment`, data);
  }

  updateAppointment(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/appointment/${id}`, data);
  }

  cancelAppointment(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/appointment/${id}/cancel`, {});
  }

  leaveReview(id: number, notes: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/appointment/${id}/review`, { notes });
  }

  deleteAppointment(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/appointment/${id}`);
  }

  // ==================== DOCUMENTS ====================
  getDocuments(): Observable<any> {
    return this.http.get(`${this.apiUrl}/document`);
  }

  uploadDocument(file: File): Observable<any> {
    const fd = new FormData();
    fd.append('file', file);
    return this.http.post(`${this.apiUrl}/document/upload`, fd);
  }

  deleteDocument(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/document/${id}`);
  }

  // ==================== PAYMENTS ====================
  getPayments(): Observable<any> {
    return this.http.get(`${this.apiUrl}/payment`);
  }

  initiatePayment(appointmentId: number): Observable<{ checkout_url: string; payment_id: number }> {
    return this.http.post<any>(`${this.apiUrl}/payment/${appointmentId}/initiate`, {});
  }

  syncPaymentStatus(paymentId: number): Observable<{ status: string; payment_id: number }> {
    return this.http.post<any>(`${this.apiUrl}/payment/${paymentId}/sync`, {});
  }

  syncPaymentByOrder(orderId: string): Observable<{ status: string }> {
    return this.http.post<any>(`${this.apiUrl}/payment/sync-by-order`, { order_id: orderId });
  }

  processPayment(id: number, paymentMethod: string = 'card'): Observable<any> {
    return this.http.post(`${this.apiUrl}/payment/${id}/process`, {
      payment_method: paymentMethod,
    });
  }

  // ==================== NOTIFICATIONS ====================
  getNotifications(isRead?: boolean): Observable<any> {
    let params = new HttpParams();
    if (isRead !== undefined) {
      params = params.set('is_read', isRead ? '1' : '0');
    }
    return this.http.get(`${this.apiUrl}/notification`, { params });
  }

  getUnreadNotificationCount(): Observable<any> {
    return this.http.get(`${this.apiUrl}/notification/unread-count`);
  }

  markNotificationAsRead(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/notification/${id}/read`, {});
  }

  markAllNotificationsAsRead(): Observable<any> {
    return this.http.post(`${this.apiUrl}/notification/read-all`, {});
  }

  getNotificationSettings(): Observable<any> {
    return this.http.get(`${this.apiUrl}/notification/settings`);
  }

  saveNotificationSettings(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/notification/save-settings`, data);
  }

  deleteNotification(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/notification/${id}`);
  }

  // ==================== QUESTIONNAIRE ====================
  getQuestionnaires(): Observable<any> {
    return this.http.get(`${this.apiUrl}/questionnaire`);
  }

  getLatestQuestionnaire(): Observable<any> {
    return this.http.get(`${this.apiUrl}/questionnaire/latest`);
  }

  getQuestionnaire(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/questionnaire/${id}`);
  }

  submitQuestionnaire(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/questionnaire`, data);
  }

  updateQuestionnaire(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/questionnaire/${id}`, data);
  }

  // ==================== SUPPORT ====================
  getSupportTickets(status?: string): Observable<any> {
    let params = new HttpParams();
    if (status) {
      params = params.set('status', status);
    }
    return this.http.get(`${this.apiUrl}/support-ticket`, { params });
  }

  getSupportTicket(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/support-ticket/${id}`);
  }

  createSupportTicket(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/support-ticket`, data);
  }

  updateSupportTicket(id: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/support-ticket/${id}`, data);
  }

  replySupportTicket(id: number, responseMessage: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/support-ticket/${id}/reply`, {
      response_message: responseMessage
    });
  }

  closeSupportTicket(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/support-ticket/${id}/close`, {});
  }

  // ==================== GOOGLE CALENDAR ====================
  getGoogleAuthUrl(): Observable<{ url: string }> {
    return this.http.get<{ url: string }>(`${this.apiUrl}/google/auth-url`);
  }

  getGoogleStatus(): Observable<{ connected: boolean; google_email: string | null }> {
    return this.http.get<{ connected: boolean; google_email: string | null }>(`${this.apiUrl}/google/status`);
  }

  disconnectGoogle(): Observable<any> {
    return this.http.post(`${this.apiUrl}/google/disconnect`, {});
  }

  getGoogleUpcomingEvents(): Observable<{ connected: boolean; events: any[]; error?: string }> {
    return this.http.get<any>(`${this.apiUrl}/google/upcoming-events`);
  }

  // ==================== MOOD ====================
  saveMood(mood: number, note?: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/mood`, { mood, note });
  }

  getTodayMood(): Observable<{ mood: number; note: string | null; logged_at: string } | null> {
    return this.http.get<any>(`${this.apiUrl}/mood/today`);
  }

  getMoodHistory(days = 30): Observable<{ mood: number; note: string | null; logged_at: string }[]> {
    return this.http.get<any[]>(`${this.apiUrl}/mood/history?days=${days}`);
  }

  // ==================== SURVEYS ====================
  getActiveSurvey(): Observable<any> {
    return this.http.get(`${this.apiUrl}/survey/active`);
  }

  getSurveyMyStatus(): Observable<{ completed: boolean; survey_id: number | null }> {
    return this.http.get<any>(`${this.apiUrl}/survey/my-status`);
  }

  submitSurveyResponse(surveyId: number, answers: { [qId: number]: number }): Observable<any> {
    return this.http.post(`${this.apiUrl}/survey/respond`, { survey_id: surveyId, answers });
  }

  // Admin surveys
  getAdminSurveys(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/admin/survey`);
  }

  createAdminSurvey(data: { title: string; description?: string }): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/survey`, data);
  }

  updateAdminSurvey(id: number, data: { title?: string; description?: string }): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/survey/${id}`, data);
  }

  deleteAdminSurvey(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/survey/${id}`);
  }

  activateAdminSurvey(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/survey/${id}/activate`, {});
  }

  getAdminSurveyQuestions(id: number): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/admin/survey/${id}/questions`);
  }

  createAdminSurveyQuestion(surveyId: number, data: { question: string; options: string[]; sort_order?: number }): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/survey/${surveyId}/questions`, data);
  }

  updateAdminSurveyQuestion(surveyId: number, qid: number, data: { question?: string; options?: string[]; sort_order?: number }): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/survey/${surveyId}/questions/${qid}`, data);
  }

  deleteAdminSurveyQuestion(surveyId: number, qid: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/survey/${surveyId}/questions/${qid}`);
  }

  getAdminSurveyResults(id: number): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/admin/survey/${id}/results`);
  }
}
