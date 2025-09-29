<?php
require_once 'config.php';

try {
    // Verificar que sea POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Método no permitido', 405);
    }

    // Aplicar rate limiting
    checkRateLimit();

    // Obtener datos JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        handleError('Datos JSON inválidos');
    }

    // Validar datos
    $errors = validateRegistrationData($data);
    if (!empty($errors)) {
        handleError(implode(', ', $errors));
    }

    // Generar ID temporal único para el registro
    $tempId = uniqid('blindma1den_', true);

    // Crear metadata para Stripe
    $metadata = [
        'temp_id' => $tempId,
        'nombre' => $data['nombre'],
        'apellido' => $data['apellido'],
        'email' => $data['email'],
        'telefono' => $data['telefono'],
        'pais' => $data['pais'],
        'modalidad' => $data['modalidad'],
        'team_name' => $data['team_name'] ?? '',
        'team_members' => $data['team_members'],
        'event' => 'Blindma1den Hack&Code 2025'
    ];

    // Crear sesión de checkout de Stripe
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => CURRENCY,
                'product_data' => [
                    'name' => 'Registro Blindma1den Hack&Code 2025',
                    'description' => 'Participación en ' . $data['modalidad'] . ' - Incluye mentoría',
                ],
                'unit_amount' => PAYMENT_AMOUNT * 100, // Stripe usa centavos
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => SUCCESS_URL . '?session_id={CHECKOUT_SESSION_ID}&temp_id=' . $tempId,
        'cancel_url' => CANCEL_URL . '?cancelled=1',
        'metadata' => $metadata,
        'customer_email' => $data['email'],
        'billing_address_collection' => 'required',
        'payment_intent_data' => [
            'metadata' => $metadata
        ]
    ]);

    // Limpiar archivos temporales antiguos
    cleanOldTempFiles();

    // Guardar datos temporalmente de forma encriptada
    $tempData = [
        'temp_id' => $tempId,
        'session_id' => $session->id,
        'data' => $data,
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'pending'
    ];

    // Encriptar los datos antes de guardarlos
    $encryptedData = encryptData($tempData);

    $tempFile = __DIR__ . '/temp/' . $tempId . '.enc';
    $tempDir = dirname($tempFile);

    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0700, true); // Permisos más restrictivos
    }

    file_put_contents($tempFile, $encryptedData);
    chmod($tempFile, 0600); // Solo lectura/escritura para el propietario

    // Respuesta exitosa
    jsonResponse([
        'success' => true,
        'checkout_url' => $session->url,
        'session_id' => $session->id,
        'temp_id' => $tempId
    ]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('Stripe API Error: ' . $e->getMessage());
    handleError('Error al procesar el pago: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log('General Error: ' . $e->getMessage());
    handleError('Error interno del servidor', 500);
}
?>