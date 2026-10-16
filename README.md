# Sistema de Gestión de Prácticas – Diseño Multimedia

Sistema web para gestionar las prácticas laborales y profesionales de la carrera de
Diseño Multimedia de la Universidad de Tarapacá: habilitación de estudiantes, solicitudes,
asignación de centros, asistencia diaria, visitas técnicas, evaluación y actas.

Proyecto III, Ingeniería Civil en Computación e Informática, UTA, 2º semestre 2026.

## Stack

| Capa | Tecnología |
|---|---|
| Frontend | Angular 21 + Angular Material |
| Backend | Laravel 12 (API REST, autenticación con Sanctum) |
| Base de datos | MariaDB 10.4 o superior |

## Estructura

```
backend/    API en Laravel
frontend/   Aplicación en Angular
```

## Requisitos

- PHP 8.2 o superior y Composer 2
- Node.js 20.19 o superior (o 22.12 o superior)
- MariaDB 10.4 o superior (XAMPP 8.2.12 ya trae PHP 8.2 y MariaDB)

## Puesta en marcha

### 1. Base de datos

Crear una base vacía llamada `practicas` (desde phpMyAdmin o por consola):

```sql
CREATE DATABASE practicas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Backend

```bash
cd backend
composer install
cp .env.example .env          # en Windows: copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve             # http://localhost:8000
```

Los correos se envían en cola y las alertas se calculan con el scheduler. En desarrollo,
cada uno en su propia terminal:

```bash
php artisan queue:work
php artisan schedule:work
```

### 3. Frontend

```bash
cd frontend
npm ci
npm start                     # http://localhost:4200
```

`npm ci` instala exactamente lo que dice `package-lock.json`. Las llamadas a `/api` se
redirigen al backend en el puerto 8000.

## Usuarios de prueba

Los crea `php artisan migrate --seed`. Contraseña de todos: `password`.

| Rol | Correo |
|---|---|
| Secretaria de Jefatura | secretaria@uta.cl |
| Coordinadora de Prácticas | coordinadora@uta.cl |
| Jefe de Carrera | jefe@uta.cl |
| Estudiante | estudiante@alumnos.uta.cl |

## Forma de trabajo

- `main` siempre funciona; no se sube nada directo a ella.
- Una rama por historia de usuario: `feature/HU-01-iniciar-sesion`.
- Cada rama entra a `main` con un pull request revisado por otro integrante.
- Los commits empiezan con la HU: `HU-01: valida credenciales y redirige por rol`.

## Equipo

Ignacio Garrido, Bairon Núñez, Rafael Nakata, Ana Gutiérrez y Felipe Flores.
Profesor: Andrés Colque Castro.
