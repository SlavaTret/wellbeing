import { Component, OnInit } from '@angular/core';
import { AdminApiService } from '../../services/admin-api.service';

interface SlotInfo {
  time: string;
  status: 'available' | 'booked' | 'blocked';
}

interface DaySchedule {
  date: string;
  dow: number;
  blocked: boolean;
  slots: SlotInfo[];
}

interface SpecialistItem {
  id: number;
  name: string;
  type_name: string;
  is_active: boolean;
  avatar_initials: string;
}

const DOW_LABELS = ['Нд', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
const DOW_FULL   = ['Неділя', 'Понеділок', 'Вівторок', 'Середа', 'Четвер', 'П\'ятниця', 'Субота'];
const ALL_HOURS  = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00'];

@Component({
  selector: 'app-admin-slots',
  templateUrl: './admin-slots.component.html',
  styleUrls: ['./admin-slots.component.css']
})
export class AdminSlotsComponent implements OnInit {
  specialists: SpecialistItem[] = [];
  selectedId: number | null = null;

  loadingSpec = true;
  loadingWeek = false;
  loadingTemplate = false;
  saving = false;
  blocking: string | null = null;

  // Tab: 'schedule' | 'calendar'
  activeTab: 'schedule' | 'calendar' = 'calendar';

  // Calendar view
  weekDays: DaySchedule[] = [];
  currentMonday = '';

  // Schedule template editor
  template: { [dow: number]: string[] } = {};
  readonly allHours = ALL_HOURS;
  readonly dowLabels = DOW_FULL;
  readonly dows = [1,2,3,4,5,6,0];

  constructor(private adminApi: AdminApiService) {}

  ngOnInit(): void {
    this.adminApi.getAdminSpecialists().subscribe({
      next: (list: any[]) => {
        this.specialists = (list || []).map((s: any) => ({
          id: s.id,
          name: s.name,
          type_name: s.type_name,
          is_active: s.is_active,
          avatar_initials: s.avatar_initials || this.initials(s.name),
        }));
        this.loadingSpec = false;
        if (this.specialists.length) this.selectSpecialist(this.specialists[0].id);
      },
      error: () => { this.loadingSpec = false; }
    });

    this.currentMonday = this.getMonday(new Date());
  }

  selectSpecialist(id: number): void {
    if (this.selectedId === id) return;
    this.selectedId = id;
    this.loadWeek();
    this.loadTemplate();
  }

  // ── Calendar ──────────────────────────────────────────────────────

  loadWeek(): void {
    if (!this.selectedId) return;
    this.loadingWeek = true;
    this.adminApi.getSpecialistWeekSchedule(this.selectedId, this.currentMonday).subscribe({
      next: (res: any) => {
        this.weekDays    = res.week || [];
        this.loadingWeek = false;
      },
      error: () => { this.loadingWeek = false; }
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
    if (!this.selectedId || this.blocking) return;
    this.blocking = day.date;

    const obs = day.blocked
      ? this.adminApi.unblockSpecialistDate(this.selectedId, day.date)
      : this.adminApi.blockSpecialistDate(this.selectedId, day.date);

    obs.subscribe({
      next: () => {
        day.blocked = !day.blocked;
        day.slots   = day.slots.map(s => ({
          ...s,
          status: day.blocked ? 'blocked' : (s.status === 'blocked' ? 'available' : s.status)
        }));
        this.blocking = null;
      },
      error: () => { this.blocking = null; }
    });
  }

  // ── Schedule template ─────────────────────────────────────────────

  loadTemplate(): void {
    if (!this.selectedId) return;
    this.loadingTemplate = true;
    this.adminApi.getSpecialistSlots(this.selectedId).subscribe({
      next: (data: any) => {
        this.template = {};
        this.dows.forEach(d => {
          const key = data[d] || data[String(d)];
          this.template[d] = key ? [...key] : [];
        });
        this.loadingTemplate = false;
      },
      error: () => {
        this.dows.forEach(d => { this.template[d] = []; });
        this.loadingTemplate = false;
      }
    });
  }

  hasHour(dow: number, h: string): boolean {
    return (this.template[dow] || []).includes(h);
  }

  toggleHour(dow: number, h: string): void {
    if (!this.template[dow]) this.template[dow] = [];
    if (this.template[dow].includes(h)) {
      this.template[dow] = this.template[dow].filter(t => t !== h);
    } else {
      this.template[dow] = [...this.template[dow], h].sort();
    }
  }

  saveTemplate(): void {
    if (!this.selectedId || this.saving) return;
    this.saving = true;
    this.adminApi.saveSpecialistSlots(this.selectedId, this.template).subscribe({
      next: () => {
        this.saving = false;
        this.loadWeek();
      },
      error: () => { this.saving = false; }
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────

  getMonday(d: Date): string {
    const day = d.getDay(); // 0=Sun
    const diff = day === 0 ? -6 : 1 - day;
    const m = new Date(d);
    m.setDate(d.getDate() + diff);
    return m.toISOString().slice(0, 10);
  }

  formatDate(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit' });
  }

  dowLabel(dow: number): string {
    return DOW_LABELS[dow];
  }

  weekRangeLabel(): string {
    if (!this.weekDays.length) return '';
    const first = new Date(this.weekDays[0].date + 'T00:00:00');
    const last  = new Date(this.weekDays[6].date + 'T00:00:00');
    const opts: Intl.DateTimeFormatOptions = { day: '2-digit', month: 'long' };
    return `${first.toLocaleDateString('uk-UA', opts)} — ${last.toLocaleDateString('uk-UA', opts)}`;
  }

  slotCount(day: DaySchedule, status: string): number {
    return day.slots.filter(s => s.status === status).length;
  }

  initials(name: string): string {
    return (name || '').trim().split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('');
  }

  get selectedSpecialist(): SpecialistItem | null {
    return this.specialists.find(s => s.id === this.selectedId) || null;
  }

  isPast(dateStr: string): boolean {
    return new Date(dateStr + 'T23:59:59') < new Date();
  }
}
