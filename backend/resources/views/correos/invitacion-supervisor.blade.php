<p>Hola, {{ $nombre }}:</p>
<p>Quedaste registrado(a) como supervisor(a) de práctica de la carrera de Diseño Multimedia de la Universidad de Tarapacá.
En el Sistema de Gestión de Prácticas responderás los informes de los estudiantes que supervisas.</p>
<p>Tu usuario es <strong>{{ $correo }}</strong>. Para crear tu contraseña, abre este enlace:</p>
<p><a href="{{ $enlace }}">{{ $enlace }}</a></p>
<p>El enlace vence en {{ $horas }} horas. Si vence, entra a <a href="{{ $acceso }}">{{ $acceso }}</a> y usa "¿Olvidaste tu contraseña?" con este mismo correo.</p>
<p>Coordinación de Prácticas<br>Diseño Multimedia, Universidad de Tarapacá</p>
