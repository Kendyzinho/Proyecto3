<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $contenido['titulo'] }}</title>
    @include('pdf._estilos')
</head>
<body>
    @include('pdf._membrete')

    <p class="fecha">{{ $contenido['lugar_fecha'] }}</p>

    <h1>{{ $contenido['titulo'] }}</h1>

    <div class="bloque">
        @foreach ($contenido['destinatario'] as $linea)
            <p>{{ $linea }}</p>
        @endforeach
    </div>

    @foreach ($contenido['parrafos'] as $parrafo)
        <p class="sangria">{{ $parrafo }}</p>
    @endforeach

    <div class="firma">
        <p class="linea"></p>
        <p class="nombre">{{ $contenido['firma'][0] }}</p>
        @foreach (array_slice($contenido['firma'], 1) as $linea)
            <p>{{ $linea }}</p>
        @endforeach
    </div>
</body>
</html>
