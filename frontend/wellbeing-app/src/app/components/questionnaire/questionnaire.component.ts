import { Component, OnInit } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { ApiService } from '../../services/api/api.service';

interface MoodOption {
  value: number;
  emoji: string;
  label: string;
}

@Component({
  selector: 'app-questionnaire',
  templateUrl: './questionnaire.component.html',
  styleUrls: ['./questionnaire.component.css']
})
export class QuestionnaireComponent implements OnInit {
  readonly moodEmojis = ['😔', '😕', '😐', '🙂', '😊'];

  get moodOptions(): MoodOption[] {
    const labels: string[] = this.translate.instant('questionnaire.mood_labels');
    return this.moodEmojis.map((emoji, i) => ({
      value: i + 1,
      emoji,
      label: Array.isArray(labels) ? labels[i] : emoji,
    }));
  }

  selectedMood: number | null = null;
  savedMood: number | null = null;
  moodSaving = false;
  moodSaved = false;
  moodError = false;

  private readonly ukMonths = ['січня','лютого','березня','квітня','травня','червня','липня','серпня','вересня','жовтня','листопада','грудня'];
  private readonly enMonths = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  get todayFormatted(): string {
    const d = new Date();
    if (this.translate.currentLang === 'en') {
      return `${this.enMonths[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
    }
    return `${d.getDate()} ${this.ukMonths[d.getMonth()]} ${d.getFullYear()}`;
  }

  survey: any = null;
  alreadyCompleted = false;
  loading = true;
  currentStep = 0;
  answers: { [qId: number]: number } = {};
  submitting = false;
  submitted = false;
  submitError = false;

  constructor(private api: ApiService, private translate: TranslateService) {}

  ngOnInit() {
    this.api.getTodayMood().subscribe({
      next: (row) => {
        if (row && row.mood) {
          this.selectedMood = row.mood;
          this.savedMood = row.mood;
          this.moodSaved = true;
        }
      },
      error: () => {}
    });

    this.api.getActiveSurvey().subscribe({
      next: (s) => {
        if (s?.questions) {
          s.questions = s.questions.map((q: any) => ({
            ...q,
            options: Array.isArray(q.options) ? q.options : (typeof q.options === 'string' ? JSON.parse(q.options) : [])
          }));
        }
        this.survey = s;
        this.api.getSurveyMyStatus().subscribe({
          next: (status) => {
            this.alreadyCompleted = status?.completed ?? false;
            this.loading = false;
          },
          error: () => { this.loading = false; }
        });
      },
      error: () => {
        this.survey = null;
        this.loading = false;
      }
    });
  }

  selectMood(value: number) {
    if (this.moodSaving) return;
    this.selectedMood = value;
    this.moodError = false;
    this.moodSaving = true;
    this.moodSaved = false;

    this.api.saveMood(value).subscribe({
      next: () => {
        this.savedMood = value;
        this.moodSaving = false;
        this.moodSaved = true;
      },
      error: () => {
        this.moodSaving = false;
        this.moodError = true;
      }
    });
  }

  get currentQuestion(): any {
    return this.survey?.questions[this.currentStep];
  }

  get progress(): number {
    return ((this.currentStep + 1) / this.survey.questions.length) * 100;
  }

  get canSubmit(): boolean {
    return Object.keys(this.answers).length === this.survey?.questions?.length;
  }

  selectAnswer(qId: number, optionIndex: number) {
    this.answers[qId] = optionIndex;
    if (this.currentStep < this.survey.questions.length - 1) {
      setTimeout(() => { this.currentStep++; }, 300);
    }
  }

  next() {
    if (this.currentStep < this.survey.questions.length - 1) {
      this.currentStep++;
    }
  }

  prev() {
    if (this.currentStep > 0) {
      this.currentStep--;
    }
  }

  submit() {
    if (!this.canSubmit || this.submitting) return;
    this.submitting = true;
    this.submitError = false;
    this.api.submitSurveyResponse(this.survey.id, this.answers).subscribe({
      next: () => {
        this.submitting = false;
        this.submitted = true;
      },
      error: () => {
        this.submitting = false;
        this.submitError = true;
      }
    });
  }
}
