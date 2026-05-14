import { Injectable } from '@angular/core';
import { HttpRequest, HttpHandler, HttpEvent, HttpInterceptor } from '@angular/common/http';
import { Observable } from 'rxjs';

const ADMIN_TOKEN_KEY = 'admin_access_token';

@Injectable()
export class AdminAuthInterceptor implements HttpInterceptor {
  intercept(req: HttpRequest<unknown>, next: HttpHandler): Observable<HttpEvent<unknown>> {
    // Додаємо токен тільки до запитів адмінки і лише якщо хедер ще не встановлено
    if ((!req.url.includes('/admin/') && !req.url.includes('/specialist-panel/')) || req.headers.has('Authorization')) {
      return next.handle(req);
    }

    const token = localStorage.getItem(ADMIN_TOKEN_KEY);
    if (!token) return next.handle(req);

    return next.handle(
      req.clone({ setHeaders: { Authorization: `Bearer ${token}` } })
    );
  }
}
