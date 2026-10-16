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
- Extensiones de PHP: `pdo_mysql`, `gd`, `intl` y `zip`

## Instalación

### Windows (XAMPP)

1. Instalar **XAMPP 8.2.12** (apachefriends.org), **Composer** (getcomposer.org) y **Node.js 22 LTS**
   (nodejs.org). Cuando el instalador de Composer pregunte por PHP, elegir `C:\xampp\php\php.exe`
   y dejar marcada la opción de agregarlo al PATH.
2. Abrir `C:\xampp\php\php.ini` y quitar el `;` del principio de estas líneas:

   ```
   extension=gd
   extension=intl
   extension=zip
   ```

3. En el panel de XAMPP, encender **Apache** y **MySQL**. En http://localhost/phpmyadmin, crear la
   base `practicas` con el cotejamiento `utf8mb4_unicode_ci`.

El usuario `root` de XAMPP no tiene clave, que es lo que espera `.env.example`.

### Linux (Arch / CachyOS)

```bash
sudo pacman -S php composer nodejs npm mariadb php-gd
sudo sed -i 's/^;extension=pdo_mysql/extension=pdo_mysql/; s/^;extension=mysqli/extension=mysqli/; s/^;extension=gd/extension=gd/; s/^;extension=iconv/extension=iconv/' /etc/php/php.ini
sudo mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql
sudo systemctl start mariadb
sudo mariadb -e "ALTER USER root@localhost IDENTIFIED VIA unix_socket OR mysql_native_password USING PASSWORD(''); CREATE DATABASE practicas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

El último comando deja a `root` sin clave, igual que en XAMPP, y crea la base. MariaDB solo
corre cuando se enciende con `sudo systemctl start mariadb`.

## Puesta en marcha

### 1. Backend

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

### 2. Frontend

```bash
cd frontend
npm ci
npm start                     # http://localhost:4200
```

`npm ci` instala exactamente lo que dice `package-lock.json`. Si falla con un error que dice
`edgesOut` (bug de npm 10), usar `npx npm@11 ci`. Las llamadas a `/api` se redirigen al
backend en el puerto 8000.

### Para trabajar

Con la base encendida (MySQL en XAMPP, o `sudo systemctl start mariadb` en Linux), dejar
corriendo `php artisan serve` en `backend` y `npm start` en `frontend`, cada uno en su terminal.
Después de hacer pull de cambios en la base de datos, correr `php artisan migrate` en `backend`.

## Ver la base de datos

- **Windows:** phpMyAdmin, en http://localhost/phpmyadmin (con Apache y MySQL encendidos en XAMPP).
- **Desde VS Code (cualquier sistema):** extensión **MySQL** de *Database Client* (`cweijan.vscode-mysql-client2`).
  Crear una conexión con estos datos:

  | Campo | Valor |
  |---|---|
  | Server Type | MariaDB |
  | Host | `127.0.0.1` |
  | Port | `3306` |
  | Username | `root` |
  | Password | vacío |
  | Database | `practicas` |

Las contraseñas se guardan cifradas en `users.password`. Cada inicio de sesión crea una fila en
`personal_access_tokens` y el cierre de sesión la borra. La base `practicas_test` es de los tests:
se vacía en cada ejecución, así que no conviene guardar nada ahí.

## Tests

Los tests del backend usan una base aparte, `practicas_test`, para no borrar los datos de
desarrollo. Hay que crearla una vez, igual que `practicas` (en phpMyAdmin o por consola), y luego:

```bash
cd backend && php artisan test
cd frontend && npx ng test --watch=false
```

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
