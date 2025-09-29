<?php
// contact_secure.php - Versión con máxima seguridad para producción
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Headers de seguridad
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS específico para tu dominio + desarrollo local
$allowed_origins = [
    'https://blindma1den.com',
    'https://www.blindma1den.com',
    'http://localhost:4321',  // Desarrollo Astro
    'http://127.0.0.1:4321'   // Alternativa local
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // En desarrollo, permitir localhost
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
        header('Access-Control-Allow-Origin: http://localhost:4321');
    } else {
        header('Access-Control-Allow-Origin: https://blindma1den.com');
    }
}

header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 3600');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Verificar User-Agent (bloquear bots básicos)
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$blocked_agents = ['curl', 'wget', 'python', 'bot', 'crawler'];

foreach ($blocked_agents as $agent) {
    if (stripos($user_agent, $agent) !== false) {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso denegado']);
        exit;
    }
}

// Usar el sistema de rate limiting unificado
$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$fingerprint = $client_ip . '_' . $user_agent;
checkRateLimit($fingerprint);

// Validar Content-Type
if (!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {
    http_response_code(400);
    echo json_encode(['error' => 'Content-Type debe ser application/json']);
    exit;
}

// Parse y validar JSON
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

// Validación exhaustiva
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$nombre = trim($input['nombre'] ?? '');
$mensaje = trim($input['mensaje'] ?? '');

// Sanitización
$nombre = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
$mensaje = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');

$errors = [];

// Validar email
if (!$email) {
    $errors[] = 'Email inválido';
} else {
    // Verificar dominios temporales/desechables
    $temp_domains = ['10minutemail.com', 'guerrillamail.com', 'tempmail.org'];
    $email_domain = substr(strrchr($email, "@"), 1);
    if (in_array($email_domain, $temp_domains)) {
        $errors[] = 'No se permiten emails temporales';
    }
}

// Validar nombre
if (empty($nombre)) {
    $errors[] = 'Nombre es obligatorio';
} elseif (strlen($nombre) < 2) {
    $errors[] = 'Nombre debe tener al menos 2 caracteres';
} elseif (strlen($nombre) > 100) {
    $errors[] = 'Nombre demasiado largo';
} elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $nombre)) {
    $errors[] = 'Nombre contiene caracteres no válidos';
}

// Validar mensaje
if (empty($mensaje)) {
    $errors[] = 'Mensaje es obligatorio';
} elseif (strlen($mensaje) < 10) {
    $errors[] = 'Mensaje debe tener al menos 10 caracteres';
} elseif (strlen($mensaje) > 2000) {
    $errors[] = 'Mensaje demasiado largo';
}

// Anti-spam avanzado
$spam_words = [
    'viagra', 'casino', 'lottery', 'winner', 'congratulations', 'free money',
    'click here', 'make money', 'weight loss', 'get rich', 'nigeria',
    'inheritance', 'million dollars', 'urgent', 'confidential'
];

$content_check = strtolower($nombre . ' ' . $mensaje);
foreach ($spam_words as $spam_word) {
    if (strpos($content_check, $spam_word) !== false) {
        $errors[] = 'Contenido no permitido detectado';
        break;
    }
}

// Verificar exceso de enlaces
$link_count = preg_match_all('/(http|www\.|\.com|\.org|\.net)/i', $mensaje);
if ($link_count > 2) {
    $errors[] = 'Demasiados enlaces en el mensaje';
}

// Verificar caracteres repetidos (spam)
if (preg_match('/(.)\1{10,}/', $mensaje)) {
    $errors[] = 'Mensaje contiene caracteres repetidos excesivos';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => implode(', ', $errors)]);
    exit;
}

// Guardar en BD con prepared statement
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$nombre, $email, $mensaje]);

    $message_id = $pdo->lastInsertId();

} catch (PDOException $e) {
    error_log('Error BD contact_secure: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
    exit;
}

// Enviar email usando PHPMailer
$email_sent = false;

try {
    $mail = new PHPMailer(true);

    // Debug mode solo en desarrollo
    $isProduction = strpos($_SERVER['HTTP_HOST'], 'blindma1den.com') !== false;
    if (!$isProduction) {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = 'error_log';
    }

    // Configuración del servidor según ambiente
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Port       = SMTP_PORT;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->CharSet    = 'UTF-8';

    // Configurar encryption solo para producción (cPanel SMTP)
    $isProduction = strpos($_SERVER['HTTP_HOST'], 'blindma1den.com') !== false;
    if ($isProduction && SMTP_PORT == 587) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($isProduction && SMTP_PORT == 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    }
    // Mailtrap (desarrollo) no necesita encryption

    // Configuración del mensaje
    $mail->setFrom('noreply@blindma1den.com', 'Blindma1den Contact Form');
    $mail->addAddress(ADMIN_EMAIL);
    $mail->addReplyTo($email, $nombre);

    $mail->Subject = "Nuevo contacto #$message_id de: $nombre";

    // Cuerpo del mensaje
    $body = "Nuevo mensaje de contacto desde Blindma1den:\n\n";
    $body .= "ID: $message_id\n";
    $body .= "Nombre: $nombre\n";
    $body .= "Email: $email\n";
    $body .= "Fecha: " . date('Y-m-d H:i:s') . "\n\n";
    $body .= "Mensaje:\n" . wordwrap($mensaje, 70) . "\n\n";
    $body .= "---\n";
    $body .= "Este mensaje fue enviado desde el formulario de contacto de blindma1den.com";

    $mail->Body = $body;

    $mail->send();
    $email_sent = true;

} catch (Exception $e) {
    error_log("Error PHPMailer: " . (isset($mail) ? $mail->ErrorInfo : 'N/A'));
    error_log("Exception: " . $e->getMessage());
}

// Log del evento para monitoreo
error_log("Contact form - ID: $message_id, Email: $email");

// Respuesta exitosa
echo json_encode([
    'success' => true,
    'message' => '¡Mensaje enviado correctamente! Te contactaremos pronto.',
    'data' => [
        'message_id' => $message_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'email_sent' => $email_sent
    ]
]);
?>