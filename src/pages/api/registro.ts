import type { APIRoute } from 'astro';
import { SecurityUtils } from '../../utils/security';

export const POST: APIRoute = async ({ request }) => {
  try {
    // Get client IP for rate limiting
    const clientIP = SecurityUtils.getClientIP(request);
    
    // Check rate limiting (3 registration attempts per 10 minutes)
    if (!SecurityUtils.checkRateLimit(clientIP, 3, 600000)) {
      return SecurityUtils.createErrorResponse('Demasiados intentos de registro. Intenta de nuevo en 10 minutos.', 429);
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
    const nombre = formData.get('nombre')?.toString() || '';
    const apellido = formData.get('apellido')?.toString() || '';
    const username_x = formData.get('username_x')?.toString() || '';
    const email = formData.get('email')?.toString() || '';
    const modalidad = formData.get('modalidad')?.toString() || '';
    const pais = formData.get('pais')?.toString() || '';
    const nombre_equipo = formData.get('nombre_equipo')?.toString() || '';
    const num_integrantes = formData.get('num_integrantes')?.toString() || '';

    // Validate all fields
    const validationErrors: string[] = [];

    // Validate nombre
    const nombreValidation = SecurityUtils.validateText(nombre, 'Nombre', 2, 50);
    if (!nombreValidation.isValid) {
      validationErrors.push(...nombreValidation.errors);
    }

    // Validate apellido
    const apellidoValidation = SecurityUtils.validateText(apellido, 'Apellido', 2, 50);
    if (!apellidoValidation.isValid) {
      validationErrors.push(...apellidoValidation.errors);
    }

    // Validate username
    const usernameValidation = SecurityUtils.validateUsername(username_x);
    if (!usernameValidation.isValid) {
      validationErrors.push(...usernameValidation.errors);
    }

    // Validate email
    const emailValidation = SecurityUtils.validateEmailField(email);
    if (!emailValidation.isValid) {
      validationErrors.push(...emailValidation.errors);
    }

    // Validate modalidad
    const modalidadValidation = SecurityUtils.validateSelection(
      modalidad, 
      ['CTF', 'Hackathon', 'Ambos'], 
      'Modalidad'
    );
    if (!modalidadValidation.isValid) {
      validationErrors.push(...modalidadValidation.errors);
    }

    // Validate país
    const paisValidation = SecurityUtils.validateCountry(pais);
    if (!paisValidation.isValid) {
      validationErrors.push(...paisValidation.errors);
    }

    // Validate team name (optional)
    const teamNameValidation = SecurityUtils.validateTeamName(nombre_equipo);
    if (!teamNameValidation.isValid) {
      validationErrors.push(...teamNameValidation.errors);
    }

    // Validate number of team members
    const membersValidation = SecurityUtils.validateTeamMembers(num_integrantes);
    if (!membersValidation.isValid) {
      validationErrors.push(...membersValidation.errors);
    }

    // Return validation errors if any
    if (validationErrors.length > 0) {
      return SecurityUtils.createErrorResponse(validationErrors.join(', '));
    }

    // Sanitize data
    const sanitizedData = {
      nombre: SecurityUtils.sanitizeInput(nombre),
      apellido: SecurityUtils.sanitizeInput(apellido),
      username_x: SecurityUtils.sanitizeInput(username_x),
      email: SecurityUtils.sanitizeInput(email),
      modalidad: SecurityUtils.sanitizeInput(modalidad),
      pais: SecurityUtils.sanitizeInput(pais),
      nombre_equipo: SecurityUtils.sanitizeInput(nombre_equipo),
      num_integrantes: parseInt(num_integrantes),
      timestamp: new Date().toISOString(),
      clientIP: clientIP
    };

    // Additional business logic validation
    if (sanitizedData.num_integrantes > 1 && !sanitizedData.nombre_equipo) {
      return SecurityUtils.createErrorResponse('Nombre del equipo es requerido para equipos de más de 1 integrante');
    }

    // Log the registration submission (in production, save to database)
    console.log('Registration form submission:', {
      nombre: sanitizedData.nombre,
      apellido: sanitizedData.apellido,
      email: sanitizedData.email,
      modalidad: sanitizedData.modalidad,
      pais: sanitizedData.pais,
      timestamp: sanitizedData.timestamp,
      clientIP: sanitizedData.clientIP
    });

    // In a real application, you would:
    // 1. Check for duplicate registrations (by email or username)
    // 2. Save to database
    // 3. Send confirmation email
    // 4. Generate payment link
    // 5. Add to event management system
    // 6. Send welcome information

    // For now, simulate processing delay
    await new Promise(resolve => setTimeout(resolve, 1000));

    return SecurityUtils.createSuccessResponse(
      '¡Registro exitoso! Te contactaremos pronto con más información sobre el pago y próximos pasos.',
      { 
        submittedAt: sanitizedData.timestamp,
        registrationId: `REG-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
      }
    );

  } catch (error) {
    console.error('Registration form error:', error);
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