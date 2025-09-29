<?php
require_once 'config.php';

try {
    // Verificar que sea GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        handleError('Método no permitido', 405);
    }

    // Aplicar rate limiting
    checkRateLimit();

    $sessionId = $_GET['session_id'] ?? null;
    $tempId = $_GET['temp_id'] ?? null;

    if (!$sessionId || !$tempId) {
        handleError('Parámetros requeridos faltantes');
    }

    // Verificar sesión en Stripe
    try {
        $session = \Stripe\Checkout\Session::retrieve($sessionId);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        handleError('Sesión no encontrada', 404);
    }

    // Verificar si el pago fue completado
    if ($session->payment_status === 'paid') {
        // Buscar el registro
        $registrationFile = __DIR__ . '/registrations/' . $tempId . '.json';

        if (file_exists($registrationFile)) {
            $registrationData = json_decode(file_get_contents($registrationFile), true);

            jsonResponse([
                'success' => true,
                'status' => 'completed',
                'registration_id' => $tempId,
                'amount_paid' => $session->amount_total / 100,
                'participant' => [
                    'nombre' => $registrationData['data']['nombre'],
                    'apellido' => $registrationData['data']['apellido'],
                    'email' => $registrationData['data']['email'],
                    'modalidad' => $registrationData['data']['modalidad']
                ]
            ]);
        } else {
            // El webhook aún no ha procesado el pago, pero está pagado
            jsonResponse([
                'success' => true,
                'status' => 'processing',
                'message' => 'Pago confirmado, procesando registro...'
            ]);
        }
    } else {
        // Verificar archivo temporal encriptado
        $tempFile = __DIR__ . '/temp/' . $tempId . '.enc';

        if (file_exists($tempFile)) {
            $encryptedData = file_get_contents($tempFile);
            $tempData = decryptData($encryptedData);

            if (!$tempData) {
                handleError('Error al leer datos temporales', 500);
            }

            jsonResponse([
                'success' => false,
                'status' => $tempData['status'] ?? 'pending',
                'message' => 'Pago aún no completado'
            ]);
        } else {
            handleError('Registro no encontrado', 404);
        }
    }

} catch (Exception $e) {
    error_log('Error en verify_payment: ' . $e->getMessage());
    handleError('Error interno del servidor', 500);
}
?>