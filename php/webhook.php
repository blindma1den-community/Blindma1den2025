<?php
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    // Obtener el payload y firma del webhook
    $payload = file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $webhook_secret = $_ENV['STRIPE_WEBHOOK_SECRET'];

    if (empty($webhook_secret)) {
        handleError('Webhook secret no configurado', 500);
    }

    // Verificar la firma del webhook
    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
    } catch (\UnexpectedValueException $e) {
        handleError('Payload inválido', 400);
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        handleError('Firma inválida', 400);
    }

    // Log del evento recibido
    error_log('Webhook recibido: ' . $event->type);

    // Manejar diferentes tipos de eventos
    switch ($event->type) {
        case 'checkout.session.completed':
            handleCheckoutCompleted($event->data->object);
            break;

        case 'payment_intent.succeeded':
            handlePaymentSucceeded($event->data->object);
            break;

        case 'payment_intent.payment_failed':
            handlePaymentFailed($event->data->object);
            break;

        default:
            error_log('Evento no manejado: ' . $event->type);
    }

    // Responder a Stripe que el webhook fue procesado
    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    error_log('Error en webhook: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleCheckoutCompleted($session) {
    error_log('Checkout completado para sesión: ' . $session->id);

    // Obtener metadata del usuario
    $metadata = $session->metadata;
    $tempId = $metadata['temp_id'] ?? null;

    if (!$tempId) {
        error_log('No se encontró temp_id en metadata');
        return;
    }

    // Buscar el archivo temporal encriptado
    $tempFile = __DIR__ . '/temp/' . $tempId . '.enc';

    if (file_exists($tempFile)) {
        $encryptedData = file_get_contents($tempFile);
        $tempData = decryptData($encryptedData);

        if (!$tempData) {
            error_log('Error al desencriptar datos temporales para temp_id: ' . $tempId);
            return;
        }

        // Actualizar status
        $tempData['status'] = 'completed';
        $tempData['completed_at'] = date('Y-m-d H:i:s');
        $tempData['stripe_session_id'] = $session->id;
        $tempData['amount_paid'] = $session->amount_total / 100; // Convertir de centavos

        // Guardar en base de datos
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $stmt = $pdo->prepare("INSERT INTO registrations
                (temp_id, stripe_session_id, nombre, apellido, email, telefono, pais, modalidad, team_name, team_members, amount_paid, payment_status, completed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())");

            $data = $tempData['data'];
            $stmt->execute([
                $tempId,
                $session->id,
                $data['nombre'],
                $data['apellido'],
                $data['email'],
                $data['telefono'],
                $data['pais'],
                $data['modalidad'],
                $data['team_name'] ?? null,
                $data['team_members'],
                $tempData['amount_paid']
            ]);

            error_log('Registro guardado en BD para: ' . $data['email']);

        } catch (PDOException $e) {
            error_log('Error guardando registro en BD: ' . $e->getMessage());
        }

        // Guardar archivo de respaldo
        $registrationFile = __DIR__ . '/registrations/' . $tempId . '.json';
        @mkdir(dirname($registrationFile), 0755, true);
        file_put_contents($registrationFile, json_encode($tempData, JSON_PRETTY_PRINT));

        // Enviar email de confirmación
        sendConfirmationEmail($tempData);

        // Limpiar archivo temporal
        unlink($tempFile);

        error_log('Registro completado para: ' . $metadata['email']);
    } else {
        error_log('No se encontró archivo temporal: ' . $tempFile);
    }
}

function handlePaymentSucceeded($paymentIntent) {
    error_log('Pago exitoso: ' . $paymentIntent->id);
    // Lógica adicional si es necesaria
}

function handlePaymentFailed($paymentIntent) {
    error_log('Pago falló: ' . $paymentIntent->id);

    $metadata = $paymentIntent->metadata;
    $tempId = $metadata['temp_id'] ?? null;

    if ($tempId) {
        $tempFile = __DIR__ . '/temp/' . $tempId . '.enc';
        if (file_exists($tempFile)) {
            $encryptedData = file_get_contents($tempFile);
            $tempData = decryptData($encryptedData);

            if ($tempData) {
                $tempData['status'] = 'failed';
                $tempData['failed_at'] = date('Y-m-d H:i:s');

                $updatedEncryptedData = encryptData($tempData);
                file_put_contents($tempFile, $updatedEncryptedData);
                chmod($tempFile, 0600);
            }
        }
    }
}

function sendConfirmationEmail($registrationData) {

    try {
        $mail = new PHPMailer(true);

        // Configuración del servidor
        $isProduction = strpos($_SERVER['HTTP_HOST'], 'blindma1den.com') !== false;
        if (!$isProduction) {
            $mail->SMTPDebug = 1;
            $mail->Debugoutput = 'error_log';
        }

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Port = SMTP_PORT;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->CharSet = 'UTF-8';

        // Encryption para producción
        if ($isProduction && SMTP_PORT == 587) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($isProduction && SMTP_PORT == 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        // Email configuration
        $data = $registrationData['data'];
        $mail->setFrom('noreply@blindma1den.com', 'Blindma1den');
        $mail->addAddress($data['email'], $data['nombre'] . ' ' . $data['apellido']);

        $mail->isHTML(true);
        $mail->Subject = 'Registro Confirmado - Blindma1den Hack&Code 2025';

        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2 style='color: #753BE8;'>¡Registro Exitoso!</h2>
            <p>Hola {$data['nombre']},</p>
            <p>Tu registro para <strong>Blindma1den Hack&Code 2025</strong> ha sido confirmado exitosamente.</p>

            <h3>Detalles de tu registro:</h3>
            <ul>
                <li><strong>Modalidad:</strong> {$data['modalidad']}</li>
                <li><strong>País:</strong> {$data['pais']}</li>
                <li><strong>Equipo:</strong> " . ($data['team_name'] ?? 'Individual') . "</li>
                <li><strong>Integrantes:</strong> {$data['team_members']}</li>
            </ul>

            <p>¡Te esperamos en el evento!</p>

            <p>Saludos,<br><strong>Equipo Blindma1den</strong></p>
        </body>
        </html>";

        $mail->send();
        error_log('Email de confirmación enviado a: ' . $data['email']);

    } catch (Exception $e) {
        error_log('Error enviando email de confirmación: ' . $e->getMessage());
    }
}
?>