# Blindma1den 2025 - Sitio Web del Evento

Este es el repositorio para el sitio web oficial del evento/conferencia Blindma1den 2025. Está construido con Astro y diseñado para ser rápido, moderno y fácil de mantener.

## 🚀 Stack Tecnológico

- **Framework**: [Astro 5.12.9](https://astro.build/)
- **Estilos**: [Tailwind CSS 4.1.11](https://tailwindcss.com/)
- **Lenguaje**: [TypeScript](https://www.typescriptlang.org/) (con configuración estricta)
- **Gestor de Paquetes**: [npm](https://www.npmjs.com/)

## 📂 Estructura del Proyecto

El proyecto sigue la estructura estándar de Astro, optimizada para este sitio de evento:

```
/
├── public/
│   └── ... (recursos estáticos como videos, fuentes e imágenes)
├── src/
│   ├── components/
│   │   ├── Evento.astro
│   │   ├── Footer.astro
│   │   ├── Hero.astro
│   │   └── NavBar.astro
│   ├── data/
│   │   └── charlas2024.json (datos para el contenido dinámico)
│   ├── layouts/
│   │   └── Layout.astro (plantilla principal del sitio)
│   └── pages/
│       └── index.astro (página de inicio)
├── astro.config.mjs
├── package.json
└── tailwind.config.mjs
```

- **`src/components`**: Contiene componentes de Astro reutilizables como la barra de navegación, el pie de página y secciones de contenido específicas.
- **`src/layouts`**: Contiene la plantilla principal `Layout.astro` que define la estructura común de las páginas (cabecera, pie de página, etc.).
- **`src/data`**: Almacena archivos JSON, como `charlas2024.json`, que se utilizan para poblar dinámicamente secciones del sitio, como la lista de ponencias.
- **`src/pages`**: Contiene las rutas del sitio. Cada archivo `.astro` en este directorio se convierte en una página.

## 🛠️ Comandos de Desarrollo

Todos los comandos se ejecutan desde la raíz del proyecto.

### Requisitos Previos

- Node.js (versión `18.20.8` || `^20.3.0` || `>=22.0.0`)
- npm (versión `>= 9.6.5`)

### Instalación

Instala las dependencias del proyecto:
```bash
npm install
```

### Servidor de Desarrollo

Inicia el servidor de desarrollo local en `http://localhost:4321` con HMR (Hot Module Replacement):
```bash
npm run dev
```

### Build de Producción

Genera el sitio estático para producción en el directorio `./dist/`:
```bash
npm run build
```

### Vista Previa de Producción

Lanza un servidor local para previsualizar el build de producción antes de desplegarlo:
```bash
npm run preview
```
