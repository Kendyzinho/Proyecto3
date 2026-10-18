<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Carta de presentación</title>
    @include('pdf._estilos')
</head>
<body>
    <div class="membrete">
        <img src="{{ $logo }}" alt="Universidad de Tarapacá">
        <p>{{ $contenido['unidad'] }}</p>
    </div>

    <p class="derecha">{{ $contenido['lugar_fecha'] }}</p>

    <div class="bloque">
        @foreach ($contenido['destinatario'] as $linea)
            <p>{{ $linea }}</p>
        @endforeach
    </div>

    @foreach ($contenido['parrafos'] as $parrafo)
        <p>{{ $parrafo }}</p>
    @endforeach

    <div class="firma">
        <p class="nombre">{{ $contenido['firma'][0] }}</p>
        <p class="cargo">{{ $contenido['firma'][1] }}</p>
    </div>
</body>
</html>
