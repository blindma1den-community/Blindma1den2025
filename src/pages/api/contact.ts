import type { APIRoute } from 'astro';
import { SecurityUtils } from '../../utils/security';

export const POST: APIRoute = async ({ request }) => {
  try {
    // Get client IP for rate limiting
    const clientIP = SecurityUtils.getClientIP(request);
    
    // Check rate limiting (5 requests per minute)
    if (!SecurityUtils.checkRateLimit(clientIP, 5, 60000)) {
      return SecurityUtils.createErrorResponse('Demasiadas solicitudes. Intenta de nuevo en un minuto.', 429);
    }

    // Parse form data
    const formData = await request.formData();
    
    // Extract and validate CSRF token
    const csrfToken = formData.get('csrf_token')?.toString() || '';
    const sessionId = formData.get('session_id')?.toString() || '';
    
    if (!SecurityUtils.validateCSRFToken(csrfToken, sessionId)) {
      return SecurityUtils.createErrorResponse('Token de seguridad inválido', 403);
    }

    // Extract form fields
    const email = formData.get('email')?.toString() || '';
    const nombre = formData.get('nombre')?.toString() || '';
    const mensaje = formData.get('mensaje')?.toString() || '';

    // Validate all fields
    const validationErrors: string[] = [];

    // Validate email
    const emailValidation = SecurityUtils.validateEmailField(email);
    if (!emailValidation.isValid) {
      validationErrors.push(...emailValidation.errors);
    }

    // Validate name
    const nameValidation = SecurityUtils.validateText(nombre, 'Nombre', 2, 100);
    if (!nameValidation.isValid) {
      validationErrors.push(...nameValidation.errors);
    }

    // Validate message
    const messageValidation = SecurityUtils.validateText(mensaje, 'Mensaje', 10, 1000);
    if (!messageValidation.isValid) {
      validationErrors.push(...messageValidation.errors);
    }

    // Return validation errors if any
    if (validationErrors.length > 0) {
      return SecurityUtils.createErrorResponse(validationErrors.join(', '));
    }

    // Sanitize data
    const sanitizedData = {
      email: SecurityUtils.sanitizeInput(email),
      nombre: SecurityUtils.sanitizeInput(nombre),
      mensaje: SecurityUtils.sanitizeInput(mensaje),
      timestamp: new Date().toISOString(),
      clientIP: clientIP
    };

    // Log the contact submission (in production, save to database)
    console.log('Contact form submission:', {
      email: sanitizedData.email,
      nombre: sanitizedData.nombre,
      timestamp: sanitizedData.timestamp,
      clientIP: sanitizedData.clientIP
    });

    // In a real application, you would:
    // 1. Save to database
    // 2. Send notification email
    // 3. Add to CRM system
    // 4. Send auto-reply to user

    // For now, simulate processing delay
    await new Promise(resolve => setTimeout(resolve, 500));

    return SecurityUtils.createSuccessResponse(
      '¡Mensaje enviado correctamente! Te contactaremos pronto.',
      { submittedAt: sanitizedData.timestamp }
    );

  } catch (error) {
    console.error('Contact form error:', error);
    return SecurityUtils.createErrorResponse('Error interno del servidor. Intenta de nuevo más tarde.', 500);
  }
};

// Handle other HTTP methods
export const GET: APIRoute = () => {
  return SecurityUtils.createErrorResponse('Método no permitido', 405);
};

export const PUT: APIRoute = () => {
  return SecurityUtils.createErrorResponse('Método no permitido', 405);
};

export const DELETE: APIRoute = () => {
  return SecurityUtils.createErrorResponse('Método no permitido', 405);
};