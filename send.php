<?php
header('Content-Type: application/json');

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

// Получаем данные из формы
$name = trim($_POST['name'] ?? '');
$company = trim($_POST['company'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$format = trim($_POST['format'] ?? '');
$date = trim($_POST['date'] ?? '');
$budget = trim($_POST['budget'] ?? '');
$message = trim($_POST['message'] ?? '');

// Валидация обязательных полей
if (empty($name) || empty($email) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Заполните обязательные поля (имя, email, телефон)']);
    exit;
}

// Валидация email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Некорректный email']);
    exit;
}

// Формируем тему письма
$subject = 'Новая заявка с ART 13';

// Формируем тело письма
$body = "<h2>Новая заявка с сайта art13.art</h2>";
$body .= "<table style='border-collapse: collapse; width: 100%; max-width: 600px;'>";
$body .= "<tr><td style='padding: 10px; border: 1px solid #ddd; font-weight: bold; background: #f5f5f5;'>Имя:</td><td style='padding: 10px; border: 1px solid #ddd;'>$name</td></tr>";

if (!empty($company)) {
    $body .= "<tr><td style='padding: 10px; border: 1px solid #ddd; font-weight: bold; background: #f5f5f5;'>Компания:</td><td style='padding: 10px; border: 1px solid #ddd;'>$company</td></tr>";
}

$body .= "<tr><td style='padding: 10px; border: 1px solid #ddd; font-weight: bold; background: #f5f5f5;'>Email:</td><td style='padding: 10px; border: 1px solid #ddd;'><a href='mailto:$email'>$email</a></td></tr>";
$body .= "<tr><td style='padding: 10px; border: 1px solid #ddd; font-weight: bold; background: #f5f5f5;'>Телефон:</td><td style='padding: 10px; border: 1px solid #ddd;'><a href='tel:$phone'>$phone</a></td></tr>";

if (!empty($format)) {
    $formatLabels = [
        'corporate' => 'Корпоративное мероприятие',
        'stage' => 'Сценический production',
        'open' => 'Открытое мероприятие',
        'other' => 'Другое'
    ];
    $formatText = $formatLabels[$format] ?? $format;
    $body .= "<tr><td style='padding: 10px; border: 1px solid #ddd; font-weight: bold; background: #f5f5f5;'>Формат:</td><td style='padding: 10px; border: 1px solid #ddd;'>$formatText</td></tr>";
}

if (!empty($date)) {
    $body .= "<tr><td style='padding: 10px; border: 1px solid #ddd; font-weight: bold; background: #f5f5f5;'>Дата:</td><td style='padding: 10px; border: 1px solid #ddd;'>$date</td></tr>";
}

if (!empty($budget)) {
    $budgetLabels = [
        'small' => 'до 500 000 ₽',
        'medium' => '500 000 — 1 500 000 ₽',
        'large' => '1 500 000 — 5 000 000 ₽',
        'enterprise' => 'от 5 000 000 ₽'
    ];
    $budgetText = $budgetLabels[$budget] ?? $budget;
    $body .= "<tr><td style='padding: 10px; border: 1px solid #ddd; font-weight: bold; background: #f5f5f5;'>Бюджет:</td><td style='padding: 10px; border: 1px solid #ddd;'>$budgetText</td></tr>";
}

if (!empty($message)) {
    $body .= "<tr><td style='padding: 10px; border: 1px solid #ddd; font-weight: bold; background: #f5f5f5; vertical-align: top;'>Описание:</td><td style='padding: 10px; border: 1px solid #ddd;'>$message</td></tr>";
}

$body .= "</table>";

// Отправляем email
$headers = [];
$headers[] = "From: art13.art <noreply@art13.art>";
$headers[] = "Reply-To: $email";
$headers[] = "MIME-Version: 1.0";
$headers[] = "Content-Type: text/html; charset=UTF-8";

$mailSent = mail('info@art13.art', $subject, $body, implode("\r\n", $headers));

if ($mailSent) {
    echo json_encode(['success' => true, 'message' => 'Заявка отправлена']);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка отправки. Попробуйте позже.']);
}
