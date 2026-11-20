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

    <table class="datos">
        @foreach ($contenido['datos'] as $rotulo => $valor)
            <tr><td class="rotulo">{{ $rotulo }}</td><td>{{ $valor }}</td></tr>
        @endforeach
    </table>

    @foreach ($contenido['secciones'] as $seccion)
        <p class="seccion">{{ $seccion['titulo'] }}</p>
        @isset($seccion['texto'])
            <p class="sangria">{{ $seccion['texto'] }}</p>
        @endisset
        @isset($seccion['pasos'])
            <ul>
                @foreach ($seccion['pasos'] as $paso)
                    <li>{{ $paso }}</li>
                @endforeach
            </ul>
        @endisset
    @endforeach
</body>
</html>
