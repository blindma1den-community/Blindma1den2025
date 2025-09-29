# Sistema de Pagos - Blindma1den Hack&Code 2025

Este directorio contiene el sistema de pagos integrado con Stripe para el registro al evento.

## Configuración

### 1. Variables de Entorno
Editar el archivo `.env` con tus credenciales reales:

```env
# Stripe Keys (Reemplaza con tus claves reales de Stripe)
STRIPE_SECRET_KEY=sk_test_your_stripe_secret_key_here
STRIPE_PUBLISHABLE_KEY=pk_test_your_stripe_publishable_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here

# URLs de la aplicación
APP_URL=https://www.blindma1den.com
SUCCESS_URL=https://www.blindma1den.com/success
CANCEL_URL=https://www.blindma1den.com/registro
```

### 2. Configurar Webhooks en Stripe

1. Ve a tu dashboard de Stripe → Webhooks
2. Crear nuevo endpoint: `https://tudominio.com/php/webhook.php`
3. Eventos a escuchar:
   - `checkout.session.completed`
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
4. Copiar el secreto del webhook y agregarlo a `.env`

### 3. Estructura de Archivos

```
php/
├── config.php              # Configuración general y funciones
├── create_payment.php       # Crear sesión de pago Stripe
├── webhook.php             # Procesar webhooks de Stripe
├── verify_payment.php      # Verificar estado del pago
├── .env                    # Variables de entorno (NO subir a git)
├── composer.json           # Dependencias PHP
├── temp/                   # Archivos temporales de registro
├── registrations/          # Registros completados
└── vendor/                 # Dependencias de Composer
```

## API Endpoints

### POST `/php/create_payment.php`
Crear una nueva sesión de pago.

**Request:**
```json
{
  "nombre": "Juan",
  "apellido": "Pérez",
  "email": "juan@example.com",
  "telefono": "@juanperez",
  "pais": "Ecuador",
  "modalidad": "CTF",
  "team_name": "Team Alpha",
  "team_members": 2
}
```

**Response:**
```json
{
  "success": true,
  "checkout_url": "https://checkout.stripe.com/...",
  "session_id": "cs_...",
  "temp_id": "blindma1den_..."
}
```

### GET `/php/verify_payment.php?session_id=xxx&temp_id=xxx`
Verificar el estado de un pago.

**Response:**
```json
{
  "success": true,
  "status": "completed",
  "registration_id": "blindma1den_...",
  "participant": {
    "nombre": "Juan",
    "apellido": "Pérez",
    "email": "juan@example.com",
    "modalidad": "CTF"
  }
}
```

### POST `/php/webhook.php`
Endpoint para webhooks de Stripe (configurado automáticamente).

## Flujo de Registro

1. **Formulario**: Usuario llena formulario en `/registro`
2. **Pago**: Se crea sesión Stripe y redirige a checkout
3. **Webhook**: Stripe notifica pago exitoso
4. **Confirmación**: Usuario regresa a `/success` con confirmación
5. **Email**: Sistema envía email de confirmación

## Seguridad

- Headers CORS configurados en `.htaccess`
- Validación de webhook signature
- Archivos sensibles protegidos
- Datos temporales en directorios protegidos

## Desarrollo Local

Para probar localmente:

1. Instalar PHP >= 7.4
2. Instalar Composer
3. Ejecutar: `composer install`
4. Configurar servidor local que sirva archivos PHP
5. Usar ngrok para webhooks: `ngrok http localhost:8000`

## Producción

1. Subir archivos PHP al servidor
2. Configurar variables de entorno
3. Asegurar que `.htaccess` esté configurado
4. Configurar webhook URL en Stripe
5. Probar flujo completo con pagos de prueba

## Notas Importantes

- **NO** subir el archivo `.env` al repositorio
- Usar claves de prueba durante desarrollo
- Configurar claves de producción al hacer deploy
- El costo está fijo en $25 USD (modificar en `config.php`)
- Los emails se envían usando la función `mail()` nativa (considerar servicio más robusto para producción)