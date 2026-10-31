/**
 * Typing Animation — реалистичный набор текста с одной ошибкой и исправлением
 * 
 * Использование:
 *   const typer = new TypingAnimation({
 *     element: document.getElementById('typing-text'),
 *     phrase: 'Фраза для набора',
 *     errorChance: 0.08,        // вероятность ошибки (0–1)
 *     baseSpeed: 65,            // базовая скорость набора (мс на символ)
 *   });
 *   typer.start();
 */

class TypingAnimation {
    constructor(options = {}) {
        this.element = options.element;
        this.phrase = options.phrase || '';
        this.errorChance = options.errorChance || 0.08;
        this.baseSpeed = options.baseSpeed || 65;
        this.typing = false;
        this.abortController = null;
        
        // Создаём курсор
        this.cursor = document.createElement('span');
        this.cursor.className = 'typing-cursor';
    }

    /** Получить скорость набора с вариативностью (как человек) */
    getTypingSpeed() {
        const variance = this.baseSpeed * 0.5;
        return this.baseSpeed + (Math.random() * variance * 2 - variance);
    }

    /** Получить соседний символ на клавиатуре (реалистичная ошибка) */
    getNeighborChar(char) {
        const isUpper = char === char.toUpperCase() && char !== ' ';
        const lower = char.toLowerCase();

        // Раскладки клавиатуры (QWERTY + ЙЦУКЕН)
        const rows = [
            'abcdefghijklmnopqrstuvwxyz',   // QWERTY
            'фыволгждпршщзхъ',             // ЙЦУКЕН основная
            'ячсмицьб'                      // ЙЦУКЕН нижняя
        ];

        // Ищем символ в раскладке
        for (const row of rows) {
            const idx = row.indexOf(lower);
            if (idx !== -1) {
                // Случайно выбираем левого или правого соседа
                const offset = Math.random() < 0.5 ? -1 : 1;
                const neighbor = row[idx + offset];
                if (neighbor) {
                    return isUpper ? neighbor.toUpperCase() : neighbor;
                }
                // Если соседа нет с одной стороны, пробуем с другой
                const otherOffset = offset === -1 ? 1 : -1;
                const otherNeighbor = row[idx + otherOffset];
                if (otherNeighbor) {
                    return isUpper ? otherNeighbor.toUpperCase() : otherNeighbor;
                }
            }
        }

        // Для пробела — случайный символ из строки цифр или буква
        if (char === ' ') {
            // Человек может пропустить пробел или нажать соседнюю букву
            const r = Math.random();
            if (r < 0.4) {
                // Пропуск — не набираем ничего (возвращаем пустую строку)
                return '';
            } else {
                // Случайная буква из середины раскладки (типичная ошибка "размазывание")
                const randomLetters = 'аеиоуыклтснрп';
                return randomLetters[Math.floor(Math.random() * randomLetters.length)];
            }
        }

        // Для цифр и спецсимволов — соседняя клавиша
        if (/[\d]/.test(char)) {
            const numPos = '1234567890'.indexOf(char);
            if (numPos !== -1) {
                const neighbors = [];
                if (numPos > 0) neighbors.push('1234567890'[numPos - 1]);
                if (numPos < 9) neighbors.push('1234567890'[numPos + 1]);
                return neighbors[Math.floor(Math.random() * neighbors.length)];
            }
        }

        // Фоллбэк — случайная буква (крайний случай)
        const fallback = 'аоиеёэыуклмнпргшщзхъфывджчцбю';
        return fallback[Math.floor(Math.random() * fallback.length)];
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

    /** Показать текст с курсором */
    showText(text) {
        this.element.textContent = text;
        this.element.appendChild(this.cursor);
    }

    /** Стереть последний символ */
    async deleteLastChar() {
        const text = this.element.textContent.replace(/\s+$/, '');
        if (text.length === 0) return;
        
        this.element.textContent = text.slice(0, -1);
        this.element.appendChild(this.cursor);
        await this.delay(this.deleteSpeed || 40);
    }

    /** Набрать фразу с одной ошибкой и исправлением */
    async typePhrase() {
        let currentText = '';
        let errorOccurred = false;

        for (let i = 0; i < this.phrase.length; i++) {
            const targetChar = this.phrase[i];
            
            // Решаем, будет ли ошибка (только одна за весь цикл, не на пробеле)
            const willError = !errorOccurred && Math.random() < this.errorChance && targetChar !== ' ';
            
            if (willError) {
                const wrongChar = this.getNeighborChar(targetChar);
                
                // Показываем текст с ошибкой
                this.showText(currentText + wrongChar);
                
                // Пауза — "человек заметил ошибку"
                await this.delay(this.getTypingSpeed() * 4);
                
                // Стираем ТОЛЬКО ошибочный символ
                await this.deleteLastChar();
                
                // Пауза перед правильным набором
                await this.delay(this.baseSpeed * 2);
                
                errorOccurred = true;
            }
            
            // Печатаем правильный символ
            currentText += targetChar;
            
            // Показываем набранный текст
            this.showText(currentText);
            
            await this.delay(this.getTypingSpeed());
        }
    }

    /** Начать анимацию (один раз, без повторений) */
    async start() {
        if (this.typing) return;
        this.typing = true;
        this.abortController = new AbortController();

        try {
            // Очищаем перед началом
            this.element.textContent = '';
            this.element.appendChild(this.cursor);
            
            // Набираем фразу
            await this.typePhrase();
            
            // Убираем курсор после завершения набора
            if (this.cursor.parentNode) {
                this.cursor.parentNode.removeChild(this.cursor);
            }
        } catch (err) {
            if (err.name === 'AbortError') return;
            console.error('TypingAnimation error:', err);
        } finally {
            this.typing = false;
        }
    }

    /** Остановить анимацию */
    stop() {
        this.typing = false;
        if (this.abortController) {
            this.abortController.abort();
        }
    }
}

// Экспорт для использования
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TypingAnimation;
}
