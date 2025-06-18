# TOTS - Backend

API RESTful desarrollada con Symfony 6.4 para la gestión de reservas de espacios.

## Requisitos previos

- PHP 8.1 o superior
- Composer
- MySQL/MariaDB
- Symfony CLI (opcional, para desarrollo)

## Instalación

1. Clona el repositorio:

```bash
git clone <url-del-repositorio>
cd tots/back
```

2. Instala las dependencias:

```bash
composer install
```

3. Configura las variables de entorno:

Copia el archivo `.env` a `.env.local` y configura tus parámetros de conexión a la base de datos y demás configuraciones.

```bash
cp .env .env.local
```

Edita `.env.local` y configura la variable `DATABASE_URL` con tus datos de conexión:

```
DATABASE_URL="mysql://usuario:contraseña@127.0.0.1:3306/tots_db?serverVersion=8.0.32&charset=utf8mb4"
```

4. Crea la base de datos y aplica las migraciones:

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

5. (Opcional) Carga datos de prueba:

```bash
php bin/console doctrine:fixtures:load
```

6. Genera las claves JWT:

```bash
php bin/console lexik:jwt:generate-keypair
```

7. Inicia el servidor de desarrollo:

```bash
symfony serve
# o alternativamente
php -S localhost:8000 -t public/
```

## Rutas de API

### Autenticación

- `POST /api/login_check`: Iniciar sesión y obtener token JWT
- `POST /api/register`: Registrar un nuevo usuario

### Espacios

- `GET /api/spaces`: Listar todos los espacios disponibles
- `GET /api/spaces/{id}`: Obtener detalles de un espacio
- `POST /api/spaces`: Crear un nuevo espacio (requiere rol ADMIN)
- `PUT /api/spaces/{id}`: Actualizar un espacio existente (requiere rol ADMIN)
- `DELETE /api/spaces/{id}`: Eliminar un espacio (requiere rol ADMIN)

### Reservas

- `GET /api/reservations`: Listar todas las reservas del usuario actual
- `GET /api/reservations/{id}`: Obtener detalles de una reserva
- `POST /api/reservations`: Crear una nueva reserva
- `PUT /api/reservations/{id}`: Actualizar una reserva existente
- `DELETE /api/reservations/{id}`: Cancelar una reserva
- `GET /api/spaces/{spaceId}/availability`: Verificar disponibilidad de un espacio

### Administración

- `GET /api/admin/users`: Listar todos los usuarios (requiere rol ADMIN)
- `GET /api/admin/reservations`: Listar todas las reservas (requiere rol ADMIN)
- `GET /api/admin/statistics`: Obtener estadísticas del sistema (requiere rol ADMIN)

## Estructura del proyecto

- `src/Controller/`: Controladores de la aplicación
- `src/Entity/`: Entidades de Doctrine (modelos)
- `src/Repository/`: Repositorios para consultas a la base de datos
- `src/Service/`: Servicios de la aplicación
- `src/EventListener/`: Listeners de eventos
- `config/`: Archivos de configuración
- `migrations/`: Migraciones de base de datos

## Librerías principales

- **symfony/framework-bundle**: Framework Symfony base
- **doctrine/orm**: Mapeo objeto-relacional para la base de datos
- **lexik/jwt-authentication-bundle**: Autenticación JWT
- **nelmio/cors-bundle**: Soporte para CORS
- **symfony/serializer**: Serialización de entidades a JSON/XML
- **symfony/validator**: Validación de datos
- **symfony/security-bundle**: Componente de seguridad

## Características principales

1. **Autenticación y autorización**
   - Sistema de login con JWT
   - Roles de usuario (USER, ADMIN)
   - Protección de rutas según roles

2. **API RESTful**
   - Endpoints bien estructurados
   - Respuestas en formato JSON
   - Manejo de errores estandarizado

3. **Gestión de espacios**
   - CRUD completo
   - Campo para descripción y detalles del espacio
   - Imágenes (URLs)

4. **Sistema de reservas**
   - Validación de disponibilidad
   - Verificación de capacidad
   - Estado de la reserva (pendiente, confirmada, cancelada)

5. **Serialización**
   - Grupos de serialización para diferentes contextos
   - Normalización/desnormalización personalizada

## Comandos útiles

```bash
# Limpiar caché
php bin/console cache:clear

# Crear una nueva entidad
php bin/console make:entity

# Crear un nuevo controlador
php bin/console make:controller

# Generar una migración
php bin/console make:migration

# Validar mapeo de entidades
php bin/console doctrine:schema:validate
```

## Pruebas

```bash
# Ejecutar pruebas unitarias
php bin/phpunit

# Ejecutar pruebas con cobertura
php bin/phpunit --coverage-html coverage
```
