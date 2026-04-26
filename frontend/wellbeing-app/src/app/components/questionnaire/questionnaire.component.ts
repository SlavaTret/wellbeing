import { Component } from '@angular/core';

@Component({
  selector: 'app-questionnaire',
  templateUrl: './questionnaire.component.html',
  styleUrls: ['./questionnaire.component.css']
})
export class QuestionnaireComponent {
  mood: number | null = null;
  step = 0;
  answers: { [i: number]: string } = {};
  done = false;
  moods = ['😔', '😕', '😐', '🙂', '😊'];

  questions = [
    { q: 'Як часто за останні 2 тижні ви відчували пригніченість або відчай?', options: ['Жодного разу', 'Кілька днів', 'Більше половини днів', 'Майже щодня'] },
    { q: 'Як часто вам було важко зосередитись на виконанні завдань?',          options: ['Жодного разу', 'Кілька днів', 'Більше половини днів', 'Майже щодня'] },
    { q: 'Що є основною метою вашого звернення?',                               options: ['Зменшення тривоги та стресу', 'Підтримка в особистих труднощах', 'Розвиток та самопізнання', 'Допомога після травматичних подій'] },
    { q: 'Як ви оцінюєте свій поточний емоційний стан?',                        options: ['Дуже добре', 'Добре', 'Задовільно', 'Погано', 'Дуже погано'] }
  ];

  get progress() { return (this.step / this.questions.length) * 100; }

  answer(opt: string) {
    this.answers[this.step] = opt;
    if (this.step < this.questions.length - 1) { this.step++; }
    else { this.done = true; }
  }

  restart() { this.step = 0; this.answers = {}; this.done = false; }
}
