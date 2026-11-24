{{-- Encabezado y pie del formato oficial; se repiten en cada página --}}
<div class="encabezado">
    <img class="uta" src="{{ public_path('logos/membrete-uta.png') }}" alt="Universidad de Tarapacá">
    <img class="dm" src="{{ public_path('logos/membrete-dm.png') }}" alt="Diseño Multimedia">
</div>
<div class="pie">{{ $contenido['pie'] }}</div>
