import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { tap } from 'rxjs/operators';

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private apiUrl = 'http://localhost:8000/api/v1'; // Update with your backend URL
  private accessToken: string | null = null;

  constructor(private http: HttpClient) {
    this.accessToken = localStorage.getItem('access_token');
  }

  // ==================== AUTH ====================
  register(email: string, password: string, firstName: string, lastName: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/user/register`, {
      email, password, first_name: firstName, last_name: lastName
    }).pipe(
      tap(response => {
        if (response.access_token) {
          this.setAccessToken(response.access_token);
        }
      })
    );
  }

  login(email: string, password: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/user/login`, { email, password }).pipe(
      tap(response => {
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
  }

  clearAccessToken(): void {
    this.accessToken = null;
    localStorage.removeItem('access_token');
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

  // ==================== DASHBOARD ====================
  getDashboard(): Observable<any> {
    return this.http.get(`${this.apiUrl}/dashboard`);
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

  getDocument(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/document/${id}`);
  }

  uploadDocument(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/document`, data);
  }

  deleteDocument(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/document/${id}`);
  }

  // ==================== PAYMENTS ====================
  getPayments(): Observable<any> {
    return this.http.get(`${this.apiUrl}/payment`);
  }

  getPayment(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/payment/${id}`);
  }

  createPayment(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/payment`, data);
  }

  processPayment(id: number, paymentMethod: string, transactionId?: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/payment/${id}/process`, {
      payment_method: paymentMethod,
      transaction_id: transactionId
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

  getNotification(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/notification/${id}`);
  }

  markNotificationAsRead(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/notification/${id}/mark-as-read`, {});
  }

  markAllNotificationsAsRead(): Observable<any> {
    return this.http.post(`${this.apiUrl}/notification/mark-all-as-read`, {});
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
}
