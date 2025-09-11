import { defineMiddleware } from 'astro:middleware';

export const onRequest = defineMiddleware(async (context, next) => {
  const { pathname } = context.url;
  
  // Bloquear temporalmente la ruta de registro
  if (pathname === '/registro') {
    return new Response(`
      <!DOCTYPE html>
      <html lang="es">
      <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registro Temporalmente Deshabilitado</title>
        <style>
          body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #242467 0%, #2E0967 30%, #3C1E5E 70%, #1B0123 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
          }
          .container {
            text-align: center;
            max-width: 600px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
          }
          h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #753BE8, #A412BA);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
          }
          p {
            font-size: 1.2rem;
            line-height: 1.6;
            margin-bottom: 30px;
            color: rgba(255, 255, 255, 0.9);
          }
          .back-btn {
            display: inline-block;
            padding: 12px 30px;
            background: #753BE8;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
          }
          .back-btn:hover {
            background: #8B5CF6;
            transform: translateY(-2px);
          }
          .icon {
            font-size: 4rem;
            margin-bottom: 20px;
          }
        </style>
      </head>
      <body>
        <div class="container">
          <div class="icon">🚧</div>
          <h1>Registro Temporalmente Deshabilitado</h1>
          <p>
            El sistema de registro está temporalmente fuera de servicio mientras preparamos mejoras para ofrecerte la mejor experiencia.
          </p>
          <p>
            <strong>¡Vuelve pronto!</strong> Estaremos listos muy pronto.
          </p>
          <a href="/" class="back-btn">← Volver al Inicio</a>
        </div>
      </body>
      </html>
    `, {
      status: 503, // Service Unavailable
      headers: {
        'Content-Type': 'text/html; charset=utf-8',
        'Retry-After': '3600' // Sugerir reintentar en 1 hora
      }
    });
  }
  
  return next();
});