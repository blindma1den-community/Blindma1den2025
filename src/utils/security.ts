// Security utilities for form validation and protection

// Rate limiting store (in production, use Redis or database)
const rateLimitStore = new Map<string, number[]>();

// CSRF token store (in production, use secure session storage)
const csrfTokenStore = new Map<string, string>();

export interface ValidationResult {
  isValid: boolean;
  errors: string[];
}

export class SecurityUtils {
  
  /**
   * Generate a CSRF token
   */
  static generateCSRFToken(): string {
    return crypto.randomUUID();
  }

  /**
   * Validate CSRF token
   */
  static validateCSRFToken(token: string, sessionId: string): boolean {
    const storedToken = csrfTokenStore.get(sessionId);
    return storedToken === token;
  }

  /**
   * Store CSRF token
   */
  static storeCSRFToken(token: string, sessionId: string): void {
    csrfTokenStore.set(sessionId, token);
  }

  /**
   * Sanitize input string
   */
  static sanitizeInput(input: string): string {
    if (typeof input !== 'string') return '';
    
    return input
      .replace(/[<>]/g, '') // Remove potentially dangerous characters
      .replace(/javascript:/gi, '') // Remove javascript: protocols
      .replace(/on\w+=/gi, '') // Remove event handlers
      .trim()
      .substring(0, 1000); // Limit length
  }

  /**
   * Validate email format
   */
  static validateEmail(email: string): boolean {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email) && email.length <= 254;
  }

  /**
   * Validate required field
   */
  static validateRequired(value: string, fieldName: string): ValidationResult {
    const sanitized = this.sanitizeInput(value);
    if (!sanitized || sanitized.length === 0) {
      return {
        isValid: false,
        errors: [`${fieldName} es requerido`]
      };
    }
    return { isValid: true, errors: [] };
  }

  /**
   * Validate text field with length constraints
   */
  static validateText(value: string, fieldName: string, minLength = 2, maxLength = 100): ValidationResult {
    const requiredCheck = this.validateRequired(value, fieldName);
    if (!requiredCheck.isValid) return requiredCheck;

    const sanitized = this.sanitizeInput(value);
    const errors: string[] = [];

    if (sanitized.length < minLength) {
      errors.push(`${fieldName} debe tener al menos ${minLength} caracteres`);
    }
    if (sanitized.length > maxLength) {
      errors.push(`${fieldName} no puede exceder ${maxLength} caracteres`);
    }

    return {
      isValid: errors.length === 0,
      errors
    };
  }

  /**
   * Validate email field
   */
  static validateEmailField(email: string): ValidationResult {
    const requiredCheck = this.validateRequired(email, 'Email');
    if (!requiredCheck.isValid) return requiredCheck;

    const sanitized = this.sanitizeInput(email);
    if (!this.validateEmail(sanitized)) {
      return {
        isValid: false,
        errors: ['Formato de email inválido']
      };
    }

    return { isValid: true, errors: [] };
  }

  /**
   * Validate username (alphanumeric, underscores, hyphens)
   */
  static validateUsername(username: string): ValidationResult {
    const requiredCheck = this.validateRequired(username, 'Username');
    if (!requiredCheck.isValid) return requiredCheck;

    const sanitized = this.sanitizeInput(username);
    const usernameRegex = /^[a-zA-Z0-9_-]+$/;
    
    if (!usernameRegex.test(sanitized)) {
      return {
        isValid: false,
        errors: ['Username solo puede contener letras, números, guiones y guiones bajos']
      };
    }

    if (sanitized.length < 3 || sanitized.length > 30) {
      return {
        isValid: false,
        errors: ['Username debe tener entre 3 y 30 caracteres']
      };
    }

    return { isValid: true, errors: [] };
  }

  /**
   * Validate country field
   */
  static validateCountry(country: string): ValidationResult {
    return this.validateText(country, 'País', 2, 50);
  }

  /**
   * Validate team name
   */
  static validateTeamName(teamName: string): ValidationResult {
    if (!teamName || teamName.trim() === '') {
      return { isValid: true, errors: [] }; // Optional field
    }
    return this.validateText(teamName, 'Nombre del equipo', 2, 50);
  }

  /**
   * Validate number of team members
   */
  static validateTeamMembers(members: string): ValidationResult {
    const requiredCheck = this.validateRequired(members, 'Número de integrantes');
    if (!requiredCheck.isValid) return requiredCheck;

    const num = parseInt(members);
    if (isNaN(num) || num < 1 || num > 3) {
      return {
        isValid: false,
        errors: ['Número de integrantes debe ser entre 1 y 3']
      };
    }

    return { isValid: true, errors: [] };
  }

  /**
   * Validate selection field (CTF/Hackathon)
   */
  static validateSelection(value: string, options: string[], fieldName: string): ValidationResult {
    const requiredCheck = this.validateRequired(value, fieldName);
    if (!requiredCheck.isValid) return requiredCheck;

    const sanitized = this.sanitizeInput(value);
    if (!options.includes(sanitized)) {
      return {
        isValid: false,
        errors: [`${fieldName} debe ser una opción válida`]
      };
    }

    return { isValid: true, errors: [] };
  }

  /**
   * Rate limiting check
   */
  static checkRateLimit(clientIP: string, maxRequests = 5, windowMs = 60000): boolean {
    const now = Date.now();
    const userRequests = rateLimitStore.get(clientIP) || [];
    
    // Filter requests within the time window
    const recentRequests = userRequests.filter(time => now - time < windowMs);
    
    if (recentRequests.length >= maxRequests) {
      return false; // Rate limit exceeded
    }
    
    // Update the store with the new request
    recentRequests.push(now);
    rateLimitStore.set(clientIP, recentRequests);
    
    return true; // Request allowed
  }

  /**
   * Get client IP from request
   */
  static getClientIP(request: Request): string {
    // In production, consider proxy headers like X-Forwarded-For
    const forwarded = request.headers.get('x-forwarded-for');
    if (forwarded) {
      return forwarded.split(',')[0].trim();
    }
    
    const realIP = request.headers.get('x-real-ip');
    if (realIP) {
      return realIP;
    }
    
    // Fallback (not reliable in production)
    return 'unknown';
  }

  /**
   * Create error response
   */
  static createErrorResponse(message: string, status = 400): Response {
    return new Response(JSON.stringify({
      success: false,
      error: message
    }), {
      status,
      headers: {
        'Content-Type': 'application/json'
      }
    });
  }

  /**
   * Create success response
   */
  static createSuccessResponse(message: string, data?: any): Response {
    return new Response(JSON.stringify({
      success: true,
      message,
      data
    }), {
      status: 200,
      headers: {
        'Content-Type': 'application/json'
      }
    });
  }
}