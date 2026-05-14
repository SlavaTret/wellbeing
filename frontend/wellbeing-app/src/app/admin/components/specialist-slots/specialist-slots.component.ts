import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { AdminApiService } from '../../services/admin-api.service';

interface SlotInfo { time: string; status: 'available' | 'booked' | 'blocked'; }
interface DaySchedule { date: string; dow: number; blocked: boolean; slots: SlotInfo[]; }

const DOW_LABELS = ['Нд', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
const DOW_FULL   = ['Неділя', 'Понеділок', 'Вівторок', 'Середа', 'Четвер', 'П\'ятниця', 'Субота'];
const ALL_HOURS  = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00'];

@Component({
  selector: 'app-specialist-slots',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './specialist-slots.component.html',
  styleUrls: ['./specialist-slots.component.css'],
})
export class SpecialistSlotsComponent implements OnInit {
  activeTab: 'calendar' | 'schedule' = 'calendar';

  loadingWeek     = false;
  loadingTemplate = false;
  saving          = false;
  blocking: string | null = null;

  weekDays: DaySchedule[] = [];
  currentMonday = '';

  template: { [dow: number]: string[] } = {};
  readonly allHours  = ALL_HOURS;
  readonly dowLabels = DOW_FULL;
  readonly dows      = [1, 2, 3, 4, 5, 6, 0];

  constructor(private adminApi: AdminApiService, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.currentMonday = this.getMonday(new Date());
    this.loadWeek();
    this.loadTemplate();
  }

  loadWeek(): void {
    this.loadingWeek = true;
    this.adminApi.getMyWeekSchedule(this.currentMonday).subscribe({
      next: (res: any) => {
        this.weekDays    = res.week || [];
        this.loadingWeek = false;
        this.cdr.markForCheck();
      },
      error: () => { this.loadingWeek = false; this.cdr.markForCheck(); },
    });
  }

  loadTemplate(): void {
    this.loadingTemplate = true;
    this.adminApi.getMySlots().subscribe({
      next: (data: any) => {
        this.template = {};
        this.dows.forEach(d => {
          const key = data[d] || data[String(d)];
          this.template[d] = key ? [...key] : [];
        });
        this.loadingTemplate = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.dows.forEach(d => { this.template[d] = []; });
        this.loadingTemplate = false;
        this.cdr.markForCheck();
      },
    });
  }

  prevWeek(): void {
    const d = new Date(this.currentMonday);
    d.setDate(d.getDate() - 7);
    this.currentMonday = this.getMonday(d);
    this.loadWeek();
  }

  nextWeek(): void {
    const d = new Date(this.currentMonday);
    d.setDate(d.getDate() + 7);
    this.currentMonday = this.getMonday(d);
    this.loadWeek();
  }

  toggleBlock(day: DaySchedule): void {
    if (this.blocking) return;
    this.blocking = day.date;

    const obs = day.blocked
      ? this.adminApi.unblockMyDate(day.date)
      : this.adminApi.blockMyDate(day.date);

    obs.subscribe({
      next: () => {
        day.blocked = !day.blocked;
        day.slots   = day.slots.map(s => ({
          ...s,
          status: day.blocked ? 'blocked' : (s.status === 'blocked' ? 'available' : s.status),
        }));
        this.blocking = null;
        this.cdr.markForCheck();
      },
      error: () => { this.blocking = null; this.cdr.markForCheck(); },
    });
  }

  hasHour(dow: number, h: string): boolean { return (this.template[dow] || []).includes(h); }

  toggleHour(dow: number, h: string): void {
    if (!this.template[dow]) this.template[dow] = [];
    if (this.template[dow].includes(h)) {
      this.template[dow] = this.template[dow].filter(t => t !== h);
    } else {
      this.template[dow] = [...this.template[dow], h].sort();
    }
  }

  saveTemplate(): void {
    if (this.saving) return;
    this.saving = true;
    this.adminApi.saveMySlots(this.template).subscribe({
      next: () => {
        this.saving = false;
        this.loadWeek();
        this.cdr.markForCheck();
      },
      error: () => { this.saving = false; this.cdr.markForCheck(); },
    });
  }

  getMonday(d: Date): string {
    const day  = d.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    const m    = new Date(d);
    m.setDate(d.getDate() + diff);
    return m.toISOString().slice(0, 10);
  }

  formatDate(s: string): string {
    return new Date(s + 'T00:00:00').toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit' });
  }

  weekRangeLabel(): string {
    if (!this.weekDays.length) return '';
    const first = new Date(this.weekDays[0].date + 'T00:00:00');
    const last  = new Date(this.weekDays[6].date + 'T00:00:00');
    const opts: Intl.DateTimeFormatOptions = { day: '2-digit', month: 'long' };
    return `${first.toLocaleDateString('uk-UA', opts)} — ${last.toLocaleDateString('uk-UA', opts)}`;
  }

  dowLabel(dow: number): string { return DOW_LABELS[dow]; }

  slotCount(day: DaySchedule, status: string): number { return day.slots.filter(s => s.status === status).length; }

  isPast(s: string): boolean { return new Date(s + 'T23:59:59') < new Date(); }
}
