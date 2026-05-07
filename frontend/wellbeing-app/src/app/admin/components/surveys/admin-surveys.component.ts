import { Component, OnInit } from '@angular/core';
import { ApiService } from '../../../services/api/api.service';

@Component({
  selector: 'app-admin-surveys',
  templateUrl: './admin-surveys.component.html',
  styleUrls: ['./admin-surveys.component.css']
})
export class AdminSurveysComponent implements OnInit {
  surveys: any[] = [];
  loading = true;

  showCreate = false;
  createTitle = '';
  createDesc = '';
  creating = false;

  questionsModal: any = null;

  resultsModal: any = null;

  editingQuestion: any = null;
  editForm = { question: '', options: [''] };
  savingEdit = false;

  newQuestion = { question: '', options: ['', '', '', ''] };
  addingQuestion = false;

  constructor(private api: ApiService) {}

  ngOnInit() { this.load(); }

  load() {
    this.loading = true;
    this.api.getAdminSurveys().subscribe({
      next: (s) => { this.surveys = s; this.loading = false; },
      error: () => { this.loading = false; }
    });
  }

  openCreate() { this.showCreate = true; this.createTitle = ''; this.createDesc = ''; }
  closeCreate() { this.showCreate = false; }

  createSurvey() {
    if (!this.createTitle.trim()) return;
    this.creating = true;
    this.api.createAdminSurvey({ title: this.createTitle.trim(), description: this.createDesc.trim() }).subscribe({
      next: () => { this.creating = false; this.showCreate = false; this.load(); },
      error: () => { this.creating = false; }
    });
  }

  activate(id: number) {
    this.api.activateAdminSurvey(id).subscribe({ next: () => this.load() });
  }

  deleteSurvey(id: number) {
    if (!confirm('Видалити опитування?')) return;
    this.api.deleteAdminSurvey(id).subscribe({
      next: () => this.load(),
      error: (e) => alert(e?.error?.error || 'Не можна видалити — є відповіді')
    });
  }

  openQuestions(survey: any) {
    this.questionsModal = { survey, questions: [], loading: true };
    this.newQuestion = { question: '', options: ['', '', '', ''] };
    this.editingQuestion = null;
    this.api.getAdminSurveyQuestions(survey.id).subscribe({
      next: (q) => {
        this.questionsModal.questions = q.map((question: any) => ({
          ...question,
          options: Array.isArray(question.options) ? question.options : (typeof question.options === 'string' ? JSON.parse(question.options) : [])
        }));
        this.questionsModal.loading = false;
      },
      error: () => { this.questionsModal.loading = false; }
    });
  }

  closeQuestions() { this.questionsModal = null; this.editingQuestion = null; }

  editQuestion(q: any) {
    this.editingQuestion = q;
    this.editForm = {
      question: q.question,
      options: [...q.options]
    };
  }

  cancelEdit() { this.editingQuestion = null; }

  addEditOption() { this.editForm.options.push(''); }
  removeEditOption(i: number) {
    if (this.editForm.options.length > 2) this.editForm.options.splice(i, 1);
  }

  saveEditQuestion() {
    const opts = this.editForm.options.filter((o: string) => o.trim());
    if (!this.editForm.question.trim() || opts.length < 2) return;
    this.savingEdit = true;
    this.api.updateAdminSurveyQuestion(this.questionsModal.survey.id, this.editingQuestion.id, {
      question: this.editForm.question.trim(),
      options: opts
    }).subscribe({
      next: () => {
        this.savingEdit = false;
        this.editingQuestion = null;
        this.openQuestions(this.questionsModal.survey);
      },
      error: () => { this.savingEdit = false; }
    });
  }

  addOption() { this.newQuestion.options.push(''); }
  removeOption(i: number) { if (this.newQuestion.options.length > 2) this.newQuestion.options.splice(i, 1); }

  addQuestion() {
    const opts = this.newQuestion.options.filter(o => o.trim());
    if (!this.newQuestion.question.trim() || opts.length < 2) return;
    this.addingQuestion = true;
    this.api.createAdminSurveyQuestion(this.questionsModal.survey.id, {
      question: this.newQuestion.question.trim(),
      options: opts,
      sort_order: this.questionsModal.questions.length
    }).subscribe({
      next: () => {
        this.addingQuestion = false;
        this.newQuestion = { question: '', options: ['', '', '', ''] };
        this.openQuestions(this.questionsModal.survey);
      },
      error: () => { this.addingQuestion = false; }
    });
  }

  moveQuestion(q: any, dir: -1 | 1) {
    const list = this.questionsModal.questions;
    const idx = list.indexOf(q);
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= list.length) return;
    [list[idx], list[newIdx]] = [list[newIdx], list[idx]];
    this.api.updateAdminSurveyQuestion(this.questionsModal.survey.id, q.id, { sort_order: newIdx }).subscribe();
    this.api.updateAdminSurveyQuestion(this.questionsModal.survey.id, list[idx].id, { sort_order: idx }).subscribe();
  }

  deleteQuestion(qid: number) {
    this.api.deleteAdminSurveyQuestion(this.questionsModal.survey.id, qid).subscribe({
      next: () => this.openQuestions(this.questionsModal.survey)
    });
  }

  openResults(survey: any) {
    this.resultsModal = { survey, results: [], loading: true };
    this.api.getAdminSurveyResults(survey.id).subscribe({
      next: (r) => { this.resultsModal.results = r; this.resultsModal.loading = false; },
      error: () => { this.resultsModal.loading = false; }
    });
  }

  closeResults() { this.resultsModal = null; }

  percent(count: number, total: number): number {
    return total ? Math.round((count / total) * 100) : 0;
  }
}
