# Demostración de HU-17: ficha individual

El seeder `Hu17FichaDemoSeeder` agrega datos ficticios y repetibles para abrir una ficha con contenido. No forma parte de `DatabaseSeeder`; se ejecuta explícitamente y solo permite `APP_ENV=local` junto a la base `practicas` o `practicas_test`.

## Preparar una base local

Recomendado: configurar `backend/.env` para usar `DB_DATABASE=practicas_test`, así la demostración queda aislada de la base `practicas`.

Desde `backend`, si la base está vacía o aún no tiene las tablas, crear el esquema:

```powershell
php artisan migrate
```

Si la base ya tiene las tablas, omitir el comando anterior. No hace falta correr `db:seed`: el seeder HU-17 prepara los datos mínimos que necesita y agrega el caso de demostración:

```powershell
php artisan db:seed --class=Hu17FichaDemoSeeder
```

El seeder no borra datos generales. Usa cuentas y registros con identificadores de demostración para poder ejecutarse más de una vez. Genera la carta y el seguro mediante el mismo servicio de HU-10.

## Iniciar la aplicación

En una terminal desde `backend`:

```powershell
php artisan serve
```

En otra terminal desde `frontend`:

```powershell
npm start
```

Iniciar sesión como Coordinación:

- Correo: `demo.hu17.coordinadora@uta.cl`
- Contraseña: `DemoHU2026!`

Abrir **Centros de práctica** (`/coordinacion/centros`), seleccionar **Centro de Innovación Audiovisual** y pulsar **Ver ficha** en la fila de Valentina Soto Riquelme. También se puede entrar a **Asignación de prácticas** (`/coordinacion/asignaciones`) y abrir la ficha desde la tabla de prácticas con fechas y horarios.

La ficha de muestra presenta una práctica en curso con cuatro jornadas confirmadas (22 de 64 horas), una jornada rechazada que no suma al avance, una visita virtual, carta y seguro descargables, y evaluación pendiente. El correo del supervisor usa `example.test`; todos los datos son ficticios.

## Prueba de aceptación

`FichaEstudianteTest` crea una práctica sin registros diarios, visitas, documentos ni dictamen y verifica que el endpoint responda sin error, muestre avance cero y evaluación pendiente. También verifica que un estudiante no pueda consultar el endpoint reservado a Coordinación y Jefatura. La prueba apunta a `practicas`, valida el nombre real de la base y usa una transacción con rollback para no reiniciar ni dejar cambios en sus tablas.

Para ejecutarla desde `backend`:

```powershell
php artisan test --filter='BitacoraTest|FichaEstudianteTest'
```

Ese filtro ejecuta los tests de aceptación HU-13 y HU-17. Aunque `phpunit.xml` conserva `practicas_test` para el resto de las pruebas del repositorio, estos dos tests cambian y verifican la conexión hacia `practicas`; sus fixtures se revierten al terminar.
