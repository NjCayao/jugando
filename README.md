Estructura de Carpetas - sistema de Software
misistema/
├── admin/                          # Dashboard AdminLTE3
│   ├── assets/                     # CSS, JS, imágenes del admin
│   ├── includes/                   # Headers, sidebars del admin
│   ├── pages/                      # Páginas del dashboard
│   │   ├── config/                 # Configuraciones
│   │   │   ├── general.php         # ✅ Configuración general
│   │   │   ├── email.php          # ✅ Configuración de emails
│   │   │   └── payments.php       # ✅ Configuración de pagos
│   │   ├── products/               # Gestión de productos
│   │   ├── users/                  # Gestión de usuarios
│   │   ├── orders/                 # Gestión de órdenes
│   │   ├── donations/              # 🆕 Gestión de donaciones
│   │   │   ├── index.php          # 🆕 Lista de donaciones
│   │   │   ├── view.php           # 🆕 Ver detalle donación
│   │   │   └── reports.php        # 🆕 Reportes de donaciones
│   │   └── reports/                # Reportes y estadísticas
│   ├── login.php                   # Login del admin
│   └── index.php                   # Dashboard principal
│
├── assets/                         # Frontend público
│   ├── css/                        # Estilos del sitio
│   ├── js/                         # JavaScript del sitio
│   ├── images/                     # Imágenes generales
│   ├── uploads/                    # Imágenes subidas
│   │   ├── products/               # Para imágenes de productos
│   │   ├── categories/             # Para imágenes de categorías 
│   │   ├── banners/                # Para imágenes de banners 
│   │   └── logos/                  # Para logos del sitio 
│
├── config/                         # Configuraciones generales
│   ├── database.php                # Conexión a BD
│   ├── constants.php               # Constantes del sistema
│   ├── functions.php               # Funciones globales
│   └── settings.php                # Configuraciones del sitio
│
├── includes/                       # Componentes del frontend
│   ├── header.php                  # Header del sitio
│   ├── footer.php                  # Footer del sitio
│   ├── navbar.php                  # Navegación
│   └── modals.php                  # Modales reutilizables
│
├── pages/                          # Páginas públicas
│   ├── products.php                # ✅ Catálogo de productos
│   ├── product.php                 # ✅ Página de producto individual
│   ├── category.php                # ✅ Página de categoría
│   ├── search.php                  # ✅ Sistema de búsqueda
│   ├── page.php                    # ✅ Páginas dinámicas del CMS
│   ├── donar-cafe.php              # 🆕 Página de donaciones
│   ├── donation-success.php        # 🆕 Página de éxito
│   ├── donation-failed.php         # 🆕 Página de error
│   ├── cart.php                    # 
│   ├── checkout.php                # 
│   ├── login.php                   # 
│   ├── register.php                # 
│   └── dashboard.php               # 
│
├── api/                            # APIs y webhooks
│   ├── payments/                   # Webhooks de pagos
│   ├── downloads/                  # Control de descargas
│   ├── donations/                  # 🆕 API de donaciones
│   │   ├── process.php             # 🆕 Procesador principal
│   │   ├── mercadopago_process.php # 🆕 Procesador MercadoPago
│   │   ├── paypal_process.php      # 🆕 Procesador PayPal
│   │   ├── mercadopago_webhook.php # 🆕 Webhook MercadoPago
│   │   └── paypal_webhook.php      # 🆕 Webhook PayPal
│   └── auth/                       # Autenticación
│
├── downloads/                      # Archivos de productos
│   ├── products/                   # Organizados por producto
│   │   ├── sistema-ventas/
│   │   │   ├── v1.0/
│   │   │   ├── v1.1/
│   │   │   └── v1.2/
│   │   └── otro-sistema/
│   └── .htaccess                   # Protección de acceso directo
│
├── vendor/                         # Librerías externas
│   ├── adminlte/                   # AdminLTE3
│   ├── stripe/                     # SDK de Stripe
│   ├── paypal/                     # SDK de PayPal
│   └── mercadopago/                # SDK de MercadoPago
│
├── logs/                           # Archivos de log
│   ├── errors.log
│   ├── payments.log
│   ├── donations.log               # 🆕 Log específico de donaciones
│   └── downloads.log
│
├── database/                       # 🆕 Scripts de base de datos
│   ├── migrations/                 # 🆕 Migraciones
│   │   └── 001_create_donations_table.sql # 🆕 Tabla donaciones
│   └── seeds/                      # 🆕 Datos de prueba
│       └── sample_donations.sql    # 🆕 Donaciones de ejemplo
│
├── .htaccess                       # URLs amigables (ACTUALIZADO)
├── index.php                       # Página principal
├── robots.txt                      # SEO
└── sitemap.xml                     # Sitemap automático

# progreso del plan de desarollo del word
12/06/25 completado hasta la 2.2
12/06/25 solucion de menus
12/06/25 solucion de subida de imagenes con categorias 
12/06/25  # 2.3 Gestión de Productos actualizado y corregido errores. 
•	CRUD productos: Formularios completos (nombre, descripción, precio, categoría, imagen)
•	Sistema versiones: Modal para agregar v1.0, v1.1, v1.2, etc.
•	Subida archivos: Upload de ZIP/RAR por cada versión
•	Pricing: Campo "precio deseado" → cálculo automático precio final
•	Límites: Configurar re-descargas y meses de actualización

13/06/25 FASE 3: FRONTEND PÚBLICO - terminado 
13/06/25 correcion de header y footer enlaces 
13/06/25 correcion de banner slider 
13/06/25 correcion para traer las imagenes del modulo contenido al index principal

# falta rediseñar - mover los css
reset-password
searh.php
sucess.php
cerify-email.php

