<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $contenido['titulo'][0] }}</title>
    @include('pdf._estilos')
</head>
<body class="acuerdo">
    @include('pdf._membrete')

    <p class="fecha">{{ $contenido['lugar_fecha'] }}</p>

    <h1>
        @foreach ($contenido['titulo'] as $linea)
            {{ $linea }}@if (! $loop->last)<br>@endif
        @endforeach
    </h1>

    @foreach ($contenido['parrafos'] as $parrafo)
        <p class="sangria">{{ $parrafo }}</p>
    @endforeach

    @foreach ($contenido['compromisos'] as $parte)
        <p class="parte">{{ $parte['titulo'] }}</p>
        <ul class="compromisos">
            @foreach ($parte['items'] as $item)
                <li>
                    {{ $item['texto'] }}
                    {{-- Sin lista anidada: dompdf manda una lista dentro de otra a la página siguiente aunque quepa --}}
                    @isset($item['detalle'])
                        @foreach ($item['detalle'] as $detalle)
                            <span class="detalle">o&nbsp;&nbsp;&nbsp;{{ $detalle }}</span>
                        @endforeach
                    @endisset
                </li>
            @endforeach
        </ul>
    @endforeach

    <div class="cierre">
        <p class="sangria">{{ $contenido['vigencia'] }}</p>

        <table class="firmas">
            <tr>
                @foreach ($contenido['firmas'] as $firma)
                    <td>
                        <p class="linea"></p>
                        <p class="nombre">{{ $firma[0] }}</p>
                        @foreach (array_slice($firma, 1) as $linea)
                            <p>{{ $linea }}</p>
                        @endforeach
                    </td>
                @endforeach
            </tr>
        </table>
    </div>
</body>
</html>
