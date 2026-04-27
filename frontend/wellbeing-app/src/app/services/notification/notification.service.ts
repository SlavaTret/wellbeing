import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';
import { ApiService } from '../api/api.service';

@Injectable({ providedIn: 'root' })
export class NotificationService {
  private countSubject = new BehaviorSubject<number>(0);
  count$ = this.countSubject.asObservable();

  get count(): number { return this.countSubject.value; }

  constructor(private api: ApiService) {}

  load(): void {
    this.api.getUnreadNotificationCount().subscribe({
      next: (res: any) => this.countSubject.next(res?.count ?? 0),
      error: () => {}
    });
  }

  setCount(n: number): void {
    this.countSubject.next(n);
  }
}
