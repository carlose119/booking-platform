# Documento de Requisitos del Producto (PRD) - Plataforma de Reservas para Negocios Locales

## 1. Introducción y Visión General
Este documento especifica los requisitos para el desarrollo de una plataforma open-source de reservas en tiempo real dirigida a negocios locales (peluquerías, centros médicos, canchas deportivas, etc.). El sistema está concebido como una solución robusta y flexible bajo un modelo de arquitectura multi-inquilino (Multi-tenant), permitiendo que múltiples negocios operen de manera aislada dentro de la misma infraestructura tecnológica.

El proyecto se distribuirá bajo la licencia de código abierto MIT, promoviendo la colaboración de la comunidad y facilitando la auto-instalación (self-hosting) por parte de desarrolladores o empresas independientes.

## 2. Stack Tecnológico
Para garantizar agilidad en el desarrollo, escalabilidad y un panel administrativo moderno, se define el siguiente stack principal:
- **Framework Backend:** Laravel 13.x (aprovechando sus características nativas de colas, notificaciones, y manejo de bases de datos).
- **Panel de Administración:** FilamentPHP v5 (utilizando su soporte integrado para multi-tenancy, generación rápida de CRUDS y diseño responsivo).
- **Base de Datos:** MySQL 8.0 (con soporte para transacciones seguras e indexación de consultas de disponibilidad).

## 3. Arquitectura Multi-Tenant y Datos
El sistema implementará una arquitectura multi-inquilino de base de datos única con separación lógica mediante llaves foráneas (`tenant_id`). Esto optimiza los costos de infraestructura en entornos SaaS y se alinea de forma nativa con las capacidades de multi-tenancy de FilamentPHP.

Cada negocio (tenant) tendrá control absoluto sobre sus configuraciones, servicios, empleados, horarios y políticas de pago de manera aislada del resto.

## 4. Gestión de Roles y Permisos (RBAC)
El sistema contará con cuatro niveles de acceso bien definidos, configurados a través de políticas y autorizaciones de Filament:

| Rol | Descripción y Alcance | Acceso al Panel (Filament) |
| :--- | :--- | :--- |
| **Super Administrador** | Gestiona la plataforma global, da de alta nuevos negocios (tenants) y supervisa métricas generales del sistema. | Panel de Administración Global |
| **Administrador de Negocio** | Dueño o gerente del local. Configura servicios, precios, horarios comerciales, pasarela de pagos, flujos de reembolso y empleados. | Panel del Tenant (Completo) |
| **Empleado / Profesional** | Prestador del servicio (ej. médico, peluquero). Visualiza únicamente su propia agenda, bloquea sus horas libres y gestiona sus citas asignadas. | Panel del Tenant (Restringido) |
| **Cliente (Registrado / Invitado)** | Usuario final. Puede reservar citas en tiempo real. Si se registra, accede a un historial de citas. Si opera como invitado, solo suministra datos de contacto esenciales. | Frontend público / Área de Cliente |

## 5. Módulos Funcionales y Requisitos Detallados

### 5.1. Configuración de Servicios y Duración Dinámica
- El Administrador del Negocio puede crear catálogos de servicios asignándoles nombre, descripción, precio y duración configurable en minutos.
- El sistema debe calcular dinámicamente los bloques de tiempo libres en el calendario interactivo basándose en la duración específica del servicio seleccionado y la disponibilidad del empleado asignado.

### 5.2. Calendario Interactivo y Flujo de Reserva
- **Interfaz de Usuario:** Calendario moderno y responsivo en la vista pública donde el cliente selecciona servicio, profesional, fecha y hora disponible.
- **Flujo de Invitado (Guest Checkout):** Permitir reservas rápidas solicitando obligatoriamente Nombre, Correo Electrónico y Teléfono, sin requerir una contraseña.
- **Prevención de Duplicados (Double-booking):** El sistema debe bloquear inmediatamente el espacio de tiempo seleccionado al iniciar la transacción de pago para evitar que dos usuarios reserven el mismo bloque simultáneamente.

### 5.3. Configuración de Pagos y Reembolsos Automáticos (Stripe)
Cada negocio definirá su política financiera directamente en su panel administrativo:

| Modalidad de Pago | Comportamiento de la Plataforma |
| :--- | :--- |
| **100% Adelantado** | La reserva se guarda en estado "Pendiente" y solo se confirma al recibir el webhook exitoso de Stripe. |
| **Fracción / Depósito** | Se configura un porcentaje fijo (ej. 20%). El cliente paga este monto en línea y el saldo restante queda registrado para pago en el local. |
| **Sin Pago Obligatorio** | La reserva se confirma de inmediato sin pasar por la pasarela de pagos. |

- **Política de Reembolsos:** El administrador define un límite de tiempo (ej. 24 horas antes de la cita). Si el cliente cancela de forma autónoma antes de esa ventana temporal, el sistema invocará de manera automática la API de Stripe para realizar un reembolso completo o parcial según las reglas configuradas.

### 5.4. Preferencias y Canales de Notificación Automatizada
El motor de notificaciones de Laravel se integrará con servicios de correo electrónico (SMTP/Mailgun) y Twilio para SMS. Los clientes decidirán su canal preferido durante el checkout ("Solo SMS", "Solo Email", o "Ambos"). El sistema procesará estas notificaciones de forma asíncrona mediante colas de Laravel (Queues) ante los siguientes eventos clave:
- **Confirmación de Reserva:** Enviada inmediatamente al procesarse el pago o la reserva de manera exitosa. Incluye un desglose del servicio y un enlace de cancelación/reprogramación.
- **Recordatorio Preventivo (24 Horas Antes):** Tarea programada en segundo plano (Laravel Scheduler) que notifica automáticamente un día antes del evento para reducir inasistencias.
- **Cancelación por parte del Negocio:** Se notifica al cliente si el negocio debe rechazar la cita, activando el reembolso inmediato si aplica.
- **Reprogramación de la Cita:** Notificación con la nueva fecha y hora cuando se modifica la reserva desde cualquiera de las partes.

## 6. Requisitos Técnicos de Contribución y Auto-Hospedaje (Self-Hosting)
Para cumplir con los estándares de la comunidad de código abierto y garantizar una documentación de calidad para el auto-hospedaje, el repositorio del proyecto deberá estructurarse bajo las siguientes directrices:

### 6.1. Requisitos para Desarrolladores y Contribuciones
- **Estilo de Código:** Cumplimiento estricto de PSR-12. El uso de herramientas de formateo automatizado como Laravel Pint es mandatorio antes de enviar Pull Requests.
- **Entorno de Desarrollo Local:** Inclusión obligatoria de una configuración basada en Docker utilizando Laravel Sail para un despliegue inmediato con comandos básicos (`./vendor/bin/sail up -d`).
- **Pruebas Automatizadas:** El código funcional crítico (cálculo de disponibilidad de horarios, webhooks de Stripe y colas de notificación) debe contar con pruebas unitarias y de integración utilizando Pest PHP o PHPUnit, manteniendo una cobertura mínima del 80%.

### 6.2. Guía de Despliegue para Self-Hosting
El archivo `README.md` principal del repositorio debe documentar con precisión los siguientes pasos secuenciales de instalación en servidores limpios:
1. Clonación del repositorio y aprovisionamiento del archivo de entorno `.env`.
2. Instalación de dependencias de backend mediante Composer (`composer install --no-dev --optimize-autoloader`).
3. Ejecución de migraciones y llenado de datos iniciales esenciales del sistema (`php artisan migrate --seed`).
4. Configuración del Supervisor de procesos para mantener activo el comando de procesamiento de colas (`php artisan queue:work`) imprescindible para las notificaciones asíncronas y webhooks.
5. Configuración del Cron Job del sistema para ejecutar las tareas programadas de Laravel (`* * * * * cd /ruta-al-proyecto && php artisan schedule:run >> /dev/null 2>&1`).
