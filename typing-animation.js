/**
 * Typing Animation — реалистичный набор текста с ошибками и исправлениями
 * 
 * Использование:
 *   const typer = new TypingAnimation({
 *     element: document.getElementById('typing-text'),
 *     phrases: ['Первая фраза', 'Вторая фраза'],
 *     errorChance: 0.08,        // вероятность ошибки (0–1)
 *     baseSpeed: 65,            // базовая скорость набора (мс на символ)
 *     pauseAfterPhrase: 2500,   // пауза после фразы (мс)
 *     pauseBeforeDelete: 400,   // пауза перед стиранием (мс)
 *     deleteSpeed: 40,          // скорость стирания (мс на символ)
 *     maxConsecutiveErrors: 2,  // макс. ошибки подряд
 *   });
 *   typer.start();
 */

class TypingAnimation {
    constructor(options = {}) {
        this.element = options.element;
        this.phrases = options.phrases || [];
        this.errorChance = options.errorChance || 0.08;
        this.baseSpeed = options.baseSpeed || 65;
        this.pauseAfterPhrase = options.pauseAfterPhrase || 2500;
        this.pauseBeforeDelete = options.pauseBeforeDelete || 400;
        this.deleteSpeed = options.deleteSpeed || 40;
        this.maxConsecutiveErrors = options.maxConsecutiveErrors || 2;
        this.typing = false;
        this.currentPhraseIndex = 0;
        this.consecutiveErrors = 0;
        this.abortController = null;
        
        // Создаём курсор
        this.cursor = document.createElement('span');
        this.cursor.className = 'typing-cursor';
        this.element.appendChild(this.cursor);
    }

    /** Получить скорость набора с вариативностью (как человек) */
    getTypingSpeed() {
        const variance = this.baseSpeed * 0.5;
        return this.baseSpeed + (Math.random() * variance * 2 - variance);
    }

    /** Получить случайный "соседний" символ для ошибки */
    getWrongChar(char, allChars) {
        const isSpace = char === ' ';
        const isUpper = char === char.toUpperCase() && char !== ' ';
        const isLetter = /[a-zA-Zа-яА-ЯёЁ]/.test(char);
        
        if (isSpace) {
            // Иногда вместо пробела — повтор предыдущего или табуляция
            const r = Math.random();
            if (r < 0.5) return char; // промах мимо пробела
            if (r < 0.8) return '\t';
            return allChars[Math.floor(Math.random() * allChars.length)];
        }
        
        if (isLetter) {
            const lower = char.toLowerCase();
            const pos = 'abcdefghijklmnopqrstuvwxyzаоиеёэыуклмнпргшщзхъфывджчцбю'.indexOf(lower);
            if (pos === -1) return allChars[Math.floor(Math.random() * allChars.length)];
            
            // Соседняя клавиша на раскладке
            const rows = [
                'abcdefghijklmnopqrstuvwxyz',
                'фыволгждпршщзхъ',
                'ячсмицьб'
            ];
            
            let found = false;
            for (const row of rows) {
                const idx = row.indexOf(lower);
                if (idx !== -1) {
                    const offset = Math.random() < 0.5 ? -1 : 1;
                    const neighbor = row[idx + offset];
                    if (neighbor) {
                        return isUpper ? neighbor.toUpperCase() : neighbor;
                    }
                    found = true;
                    break;
                }
            }
            
            if (!found) return allChars[Math.floor(Math.random() * allChars.length)];
        }
        
        // Для специальных символов — случайный символ
        return allChars[Math.floor(Math.random() * allChars.length)];
    }

    /** Получить все допустимые символы для ошибок */
    getAllChars() {
        return 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZаоиеёэыуклмнпргшщзхъфывджчцбюАОИЕЁЭЫУКЛМНПРГШЩЗХЪФЫВДЖЧЦБЮ1234567890!@#$%^&*()_+-=[]{}|;:,.<>?/ ';
    }

    /** Задержка */
    delay(ms) {
        return new Promise((resolve, reject) => {
            const timeout = setTimeout(resolve, ms);
            if (this.abortController) {
                this.abortController.signal.addEventListener('abort', () => {
                    clearTimeout(timeout);
                    reject(new DOMException('Aborted', 'AbortError'));
                }, { once: true });
            }
        });
    }

    /** Набрать одну фразу */
    async typePhrase(phrase) {
        const allChars = this.getAllChars();
        let currentText = '';
        let consecutiveErrors = 0;

        for (let i = 0; i < phrase.length; i++) {
            const targetChar = phrase[i];
            
            // Решаем, будет ли ошибка
            const willError = Math.random() < this.errorChance && consecutiveErrors < this.maxConsecutiveErrors;
            
            if (willError) {
                const wrongChar = this.getWrongChar(targetChar, allChars);
                currentText += wrongChar;
                this.consecutiveErrors++;
                
                // Показываем текст с ошибкой (красный)
                this.element.innerHTML = this.escapeHtml(currentText) + 
                    (this.cursor.outerHTML);
                
                // Пауза — "человек заметил ошибку"
                await this.delay(this.getTypingSpeed() * 4);
                
                // Стираем ТОЛЬКО ошибочный символ
                await this.deleteLastChar();
                currentText = currentText.slice(0, -1);
                consecutiveErrors = 0;
                
                // Пауза перед правильным набором
                await this.delay(this.baseSpeed * 2);
            }
            
            // Печатаем правильный символ
            this.consecutiveErrors = 0;
            currentText += targetChar;
            
            // Показываем набранный текст
            this.element.innerHTML = this.escapeHtml(currentText) + 
                (this.cursor.outerHTML);
            
            await this.delay(this.getTypingSpeed());
        }
    }

    /** Стереть последний символ */
    async deleteLastChar() {
        const text = this.element.textContent.replace(/\s+$/, '');
        if (text.length === 0) return;
        
        this.element.textContent = text.slice(0, -1);
        this.element.appendChild(this.cursor);
        await this.delay(this.deleteSpeed);
    }

    /** Стереть весь текст */
    async deleteToEnd() {
        const text = this.element.textContent.replace(/\s+$/, '');
        let currentLength = text.length;

        while (currentLength > 0) {
            currentLength--;
            this.element.textContent = text.substring(0, currentLength);
            this.element.appendChild(this.cursor);
            await this.delay(this.deleteSpeed);
        }
    }

    /** Пауза между фразами */
    async pauseBetweenPhrases() {
        // Меняем курсор на "думающий"
        this.cursor.classList.add('typing-cursor--pausing');
        await this.delay(this.pauseBeforeDelete);
        await this.deleteToEnd();
        await this.delay(this.pauseAfterPhrase);
        this.cursor.classList.remove('typing-cursor--pausing');
    }

    /** HTML-экранирование */
    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /** Начать анимацию */
    async start() {
        if (this.typing) return;
        this.typing = true;
        this.abortController = new AbortController();

        while (this.typing && !this.abortController.signal.aborted) {
            const phrase = this.phrases[this.currentPhraseIndex % this.phrases.length];
            
            try {
                // Очищаем перед началом
                this.element.textContent = '';
                
                // Набираем фразу
                await this.typePhrase(phrase);
                
                // Пауза — читаем
                await this.pauseBetweenPhrases();
                
                // Переходим к следующей фразе
                this.currentPhraseIndex++;
            } catch (err) {
                if (err.name === 'AbortError') break;
                console.error('TypingAnimation error:', err);
            }
        }
    }

    /** Остановить анимацию */
    stop() {
        this.typing = false;
        if (this.abortController) {
            this.abortController.abort();
        }
    }

    /** Перезапустить */
    restart() {
        this.stop();
        this.currentPhraseIndex = 0;
        this.consecutiveErrors = 0;
        this.start();
    }
}

// Экспорт для использования
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TypingAnimation;
}
