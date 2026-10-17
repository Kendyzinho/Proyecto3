<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $contenido['titulo'] }}</title>
    @include('pdf._estilos')
</head>
<body>
    <div class="membrete">
        <img src="{{ $logo }}" alt="Universidad de Tarapacá">
        <p>{{ $contenido['unidad'] }}</p>
    </div>

    <h1>{{ $contenido['titulo'] }}</h1>

    <table class="datos">
        @foreach ($contenido['datos'] as $rotulo => $valor)
            <tr><td class="rotulo">{{ $rotulo }}</td><td>{{ $valor }}</td></tr>
        @endforeach
    </table>

    @foreach ($contenido['secciones'] as $seccion)
        <h2>{{ $seccion['titulo'] }}</h2>
        @isset($seccion['texto'])
            <p>{{ $seccion['texto'] }}</p>
        @endisset
        @isset($seccion['pasos'])
            <ol>
                @foreach ($seccion['pasos'] as $paso)
                    <li>{{ $paso }}</li>
                @endforeach
            </ol>
        @endisset
    @endforeach
</body>
</html>
