import type { APIRoute } from 'astro';
import { SecurityUtils } from '../../utils/security';

export const GET: APIRoute = async ({ request }) => {
  try {
    // Generate session ID and CSRF token
    const sessionId = crypto.randomUUID();
    const csrfToken = SecurityUtils.generateCSRFToken();
    
    // Store the token
    SecurityUtils.storeCSRFToken(csrfToken, sessionId);
    
    return SecurityUtils.createSuccessResponse('Token generado', {
      csrf_token: csrfToken,
      session_id: sessionId
    });
    
  } catch (error) {
    console.error('CSRF token generation error:', error);
    return SecurityUtils.createErrorResponse('Error generando token de seguridad', 500);
  }
};

// Handle other HTTP methods
export const POST: APIRoute = () => {
  return SecurityUtils.createErrorResponse('Método no permitido', 405);
};

export const PUT: APIRoute = () => {
  return SecurityUtils.createErrorResponse('Método no permitido', 405);
};

export const DELETE: APIRoute = () => {
  return SecurityUtils.createErrorResponse('Método no permitido', 405);
};