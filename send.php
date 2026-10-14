<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Content-Security-Policy: frame-ancestors \'none\'');

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

// Защита от ботов — honeypot
if (!empty($_POST['website'] ?? '')) {
    // Если поле заполнено — это бот, молча отвечаем успехом
    echo json_encode(['success' => true, 'message' => 'OK']);
    exit;
}

// Проверяем реферер (защита от прямых POST-запросов)
$allowed_referrers = ['art13.art', 'localhost', '127.0.0.1'];
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$referer_valid = false;
foreach ($allowed_referrers as $referrer) {
    if (stripos($referer, $referrer) !== false) {
        $referer_valid = true;
        break;
    }
}
// Не блокируем, если реферер не определён (может быть отключён браузером)
if (!empty($referer) && !$referer_valid) {
    echo json_encode(['success' => false, 'message' => 'Некорректный источник запроса']);
    exit;
}

// Функция санитизации данных
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Получаем и санитизируем данные
$name = sanitize($_POST['name'] ?? '');
$company = sanitize($_POST['company'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$format = sanitize($_POST['format'] ?? '');
$date = sanitize($_POST['date'] ?? '');
$budget = sanitize($_POST['budget'] ?? '');
$message = sanitize($_POST['message'] ?? '');
$consent = $_POST['consent'] ?? ''; // Чекбокс согласия

// Проверяем согласие на обработку данных
if (empty($consent)) {
    echo json_encode(['success' => false, 'message' => 'Необходимо дать согласие на обработку персональных данных']);
    exit;
}

// Валидация обязательных полей
if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Пожалуйста, введите ваше имя']);
    exit;
}

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Пожалуйста, введите email']);
    exit;
}

if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Пожалуйста, введите номер телефона']);
    exit;
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Пожалуйста, опишите ваш проект']);
    exit;
}

// Валидация email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Введите корректный email адрес']);
    exit;
}

// Валидация телефона (должно быть 11 цифр после санитизации)
$phone_digits = preg_replace('/\D/', '', $phone);
if (strlen($phone_digits) < 11) {
    echo json_encode(['success' => false, 'message' => 'Введите полный номер телефона']);
    exit;
}

// Проверка длины имени (не более 100 символов)
if (strlen($name) > 100) {
    echo json_encode(['success' => false, 'message' => 'Имя слишком длинное']);
    exit;
}

// Проверка длины сообщения (не более 2000 символов)
if (strlen($message) > 2000) {
    echo json_encode(['success' => false, 'message' => 'Описание проекта слишком длинное']);
    exit;
}

// Проверка частоты запросов (rate limiting) — защита от спама
// Используем сессию (если доступна)
session_start();
$rate_limit_key = 'last_form_submit';
$rate_limit_interval = 60; // Минимум 60 секунд между отправками

if (isset($_SESSION[$rate_limit_key]) && (time() - $_SESSION[$rate_limit_key]) < $rate_limit_interval) {
    echo json_encode(['success' => false, 'message' => 'Пожалуйста, подождите перед повторной отправкой']);
    exit;
}

$_SESSION[$rate_limit_key] = time();

// Формируем тему письма
$subject = 'Новая заявка с art13.art — ' . $name;

// Формируем тело письма для администратора
$body = "<!DOCTYPE html><html lang='ru'><head><meta charset='UTF-8'><style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
.container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.header { background: linear-gradient(135deg, #FF6B35, #ff8555); color: #fff; padding: 24px; }
.header h2 { margin: 0; font-size: 20px; }
.header p { margin: 4px 0 0; opacity: 0.9; font-size: 13px; }
.content { padding: 24px; }
.table-row { border-bottom: 1px solid #f0f0f0; }
.table-row:last-child { border-bottom: none; }
.table-label { color: #888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 0; }
.table-value { font-size: 15px; color: #333; padding: 10px 0; word-break: break-word; }
.footer { background: #f9f9f9; padding: 16px 24px; text-align: center; font-size: 12px; color: #888; }
</style></head><body>";

$body .= "<div class='container'>";
$body .= "<div class='header'>";
$body .= "<h2>🎪 Новая заявка с сайта</h2>";
$body .= "<p>art13.art — Event-продакшен полного цикла</p>";
$body .= "</div>";

$body .= "<div class='content'>";
$body .= "<table style='width:100%;'>";
$body .= "<tr class='table-row'><td class='table-label'>Имя</td><td class='table-value'>" . ($name ?: '—') . "</td></tr>";

if (!empty($company)) {
    $body .= "<tr class='table-row'><td class='table-label'>Компания</td><td class='table-value'>" . $company . "</td></tr>";
}

$body .= "<tr class='table-row'><td class='table-label'>Email</td><td class='table-value'><a href='mailto:" . $email . "'>" . $email . "</a></td></tr>";
$body .= "<tr class='table-row'><td class='table-label'>Телефон</td><td class='table-value'><a href='tel:" . $phone . "'>" . $phone . "</a></td></tr>";

if (!empty($format)) {
    $formatLabels = [
        'corporate' => 'Корпоративное мероприятие',
        'stage' => 'Сценический production',
        'open' => 'Открытое мероприятие',
        'other' => 'Другое'
    ];
    $formatText = $formatLabels[$format] ?? $format;
    $body .= "<tr class='table-row'><td class='table-label'>Формат</td><td class='table-value'>" . $formatText . "</td></tr>";
}

if (!empty($date)) {
    $formatted_date = date('d.m.Y', strtotime($date));
    $body .= "<tr class='table-row'><td class='table-label'>Дата мероприятия</td><td class='table-value'>" . $formatted_date . "</td></tr>";
}

if (!empty($budget)) {
    $budgetLabels = [
        'small' => 'до 500 000 ₽',
        'medium' => '500 000 — 1 500 000 ₽',
        'large' => '1 500 000 — 5 000 000 ₽',
        'enterprise' => 'от 5 000 000 ₽'
    ];
    $budgetText = $budgetLabels[$budget] ?? $budget;
    $body .= "<tr class='table-row'><td class='table-label'>Бюджет</td><td class='table-value'>" . $budgetText . "</td></tr>";
}

if (!empty($message)) {
    $body .= "<tr class='table-row'><td class='table-label' style='vertical-align: top;'>Описание проекта</td><td class='table-value' style='white-space: pre-wrap;'>" . nl2br($message) . "</td></tr>";
}

$body .= "</table>";
$body .= "</div>";

$body .= "<div class='footer'>";
$body .= "<p>Заявка отправлена: " . date('d.m.Y H:i:s') . " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "</p>";
$body .= "</div>";

$body .= "</div></body></html>";

// Отправляем email администратору
$mail_headers = [];
$mail_headers[] = "From: art13.art <noreply@art13.art>";
$mail_headers[] = "Reply-To: " . $email;
$mail_headers[] = "MIME-Version: 1.0";
$mail_headers[] = "Content-Type: text/html; charset=UTF-8";

$mail_sent = mail('info@art13.art', $subject, $body, implode("\r\n", $mail_headers));

// Отправляем ответное письмо клиенту
$client_subject = 'Спасибо за заявку — ART 13';
$client_body = "<!DOCTYPE html><html lang='ru'><head><meta charset='UTF-8'><style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
.container { max-width: 550px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.header { background: linear-gradient(135deg, #FF6B35, #ff8555); color: #fff; padding: 32px 24px; text-align: center; }
.header h2 { margin: 0 0 8px; font-size: 22px; }
.header p { margin: 0; opacity: 0.95; font-size: 14px; }
.content { padding: 32px 24px; }
.content p { color: #555; font-size: 14px; line-height: 1.7; margin-bottom: 16px; }
.content strong { color: #333; }
.contact-box { background: #FFF5F0; border: 1px solid rgba(255,107,53,0.2); border-radius: 8px; padding: 20px; margin: 24px 0; }
.contact-box p { margin: 8px 0; color: #333; font-size: 14px; }
.contact-box a { color: #FF6B35; text-decoration: none; font-weight: 600; }
.contact-box a:hover { text-decoration: underline; }
.footer { background: #f9f9f9; padding: 20px 24px; text-align: center; font-size: 12px; color: #888; }
</style></head><body>";

$client_body .= "<div class='container'>";
$client_body .= "<div class='header'>";
$client_body .= "<h2>✓ Заявка принята!</h2>";
$client_body .= "<p>Спасибо, " . htmlspecialchars($name) . "!</p>";
$client_body .= "</div>";

$client_body .= "<div class='content'>";
$client_body .= "<p>Мы получили вашу заявку и свяжемся с вами в течение <strong>24 часов</strong> для обсуждения деталей.</p>";

$client_body .= "<div class='contact-box'>";
$client_body .= "<p><strong>Пока можете связаться с нами:</strong></p>";
$client_body .= "<p>📞 <a href='tel:+79169897398'>+7 (916) 989-73-98</a></p>";
$client_body .= "<p>✉️ <a href='mailto:info@art13.art'>info@art13.art</a></p>";
$client_body .= "</div>";

$client_body .= "<p>Наша команда изучит ваш проект и подготовит индивидуальное коммерческое предложение.</p>";
$client_body .= "<p>С уважением,<br><strong>Компания ART 13</strong></p>";
$client_body .= "</div>";

$client_body .= "<div class='footer'>";
$client_body .= "<p>© 2026 ART 13. Event-продакшен полного цикла.</p>";
$client_body .= "</div>";

$client_body .= "</div></body></html>";

$client_headers = [];
$client_headers[] = "From: ART 13 <noreply@art13.art>";
$client_headers[] = "MIME-Version: 1.0";
$client_headers[] = "Content-Type: text/html; charset=UTF-8";

$mail_sent_client = mail($email, $client_subject, $client_body, implode("\r\n", $client_headers));

if ($mail_sent) {
    echo json_encode([
        'success' => true, 
        'message' => 'Заявка отправлена',
        'email_sent' => $mail_sent_client
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка отправки. Пожалуйста, свяжитесь с нами по телефону или email.']);
}
