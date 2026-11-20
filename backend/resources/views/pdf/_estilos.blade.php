{{-- Formato oficial de la Carrera (acuerdos de colaboración): hoja carta, Calibri 11 (Carlito, mismas medidas), texto justificado --}}
<style>
    @font-face { font-family: 'Carlito'; font-weight: normal; src: url('{{ resource_path('fonts/Carlito-Regular.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: bold; src: url('{{ resource_path('fonts/Carlito-Bold.ttf') }}') format('truetype'); }
    @page { margin: 3.2cm 3cm 2.5cm; }
    body { font-family: 'Carlito', sans-serif; font-size: 11pt; line-height: 1.25; color: #000; }

    .encabezado { position: fixed; top: -2.2cm; left: 0; right: 0; height: 1.5cm; }
    .encabezado .uta { position: absolute; left: -1.2cm; top: 0; width: 6.1cm; }
    .encabezado .dm { position: absolute; right: -1cm; top: 0.1cm; width: 3.4cm; }
    .pie { position: fixed; bottom: -1.3cm; left: 0; right: 0; text-align: center; font-size: 8pt; font-weight: bold; }

    p { margin: 0 0 10pt; text-align: justify; }
    .fecha { text-align: right; margin-bottom: 22pt; }
    h1 { margin: 0 0 16pt; font-size: 11pt; text-align: center; text-transform: uppercase; }
    .sangria { text-indent: 1.25cm; }
    .bloque { margin: 0 0 16pt; }
    .bloque p { margin: 0; text-align: left; }
    .seccion { margin: 14pt 0 6pt; font-weight: bold; }
    ul { margin: 0 0 8pt; padding-left: 0.9cm; }
    li { margin-bottom: 4pt; text-align: justify; }

    table.datos { width: 100%; border-collapse: collapse; margin-bottom: 6pt; }
    table.datos td { padding: 2pt 0; vertical-align: top; }
    table.datos td.rotulo { width: 34%; font-weight: bold; }

    .firma { margin: 70pt auto 0; width: 8cm; text-align: center; }
    .firma p { margin: 0; text-align: center; }
    .firma .linea { border-top: 1px solid #000; margin-bottom: 2pt; }
    .firma .nombre { text-transform: uppercase; }
</style>
