<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WITWAN API</title>

    {{-- Swagger CSS --}}
    <link rel="stylesheet" type="text/css" href="{{ l5_swagger_asset($documentation, 'swagger-ui.css') }}">
	<style>
/* Limpiamos la barra de Swagger y ponemos solo nuestro logo */
.topbar-wrapper .link {
    display: block !important;
    background: none !important;
    width: 571px;      /* ancho de tu logo */
    height: 90px;      /* alto de tu logo */
    padding: 0 !important;
}

/* eliminamos todos los hijos que Swagger pone dentro */
.topbar-wrapper .link > * {
    display: none !important;
}

/* agregamos nuestro logo como pseudo-elemento */
.topbar-wrapper .link::before {
    content: '';
    display: block;
    width: 398px;       /* mismo que .link */
    height: 58px;       /* mismo que .link */
    background-image: url('{{ asset('images/logo.png') }}');
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
}.topbar-wrapper {
    height: 68px !important;
}
</style>


    {{-- Favicon personalizado --}}
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
   

    {{-- Estilos generales --}}
    <style>
    html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
    *, *:before, *:after { box-sizing: inherit; }
    body { margin:0; background: #fafafa; }
    </style>

    

    @if(config('l5-swagger.defaults.ui.display.dark_mode'))
        <style>
        body#dark-mode { background: #1b1b1b; color: #e7e7e7; }
        /* resto de tus estilos dark mode aquí si quieres */
        </style>
    @endif
</head>

<body @if(config('l5-swagger.defaults.ui.display.dark_mode')) id="dark-mode" @endif>
<div id="swagger-ui"></div>

<script src="{{ l5_swagger_asset($documentation, 'swagger-ui-bundle.js') }}"></script>
<script src="{{ l5_swagger_asset($documentation, 'swagger-ui-standalone-preset.js') }}"></script>
<script>
window.onload = function() {
    const urls = [];

    @foreach($urlsToDocs as $title => $url)
        urls.push({name: "{{ $title }}", url: "{{ $url }}"});
    @endforeach

    const ui = SwaggerUIBundle({
        dom_id: '#swagger-ui',
        urls: urls,
        "urls.primaryName": "{{ $documentationTitle }}",
        operationsSorter: {!! isset($operationsSorter) ? '"' . $operationsSorter . '"' : 'null' !!},
        configUrl: {!! isset($configUrl) ? '"' . $configUrl . '"' : 'null' !!},
        validatorUrl: {!! isset($validatorUrl) ? '"' . $validatorUrl . '"' : 'null' !!},
        oauth2RedirectUrl: "{{ route('l5-swagger.'.$documentation.'.oauth2_callback', [], $useAbsolutePath) }}",
        requestInterceptor: function(request) {
            request.headers['X-CSRF-TOKEN'] = '{{ csrf_token() }}';
            return request;
        },
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIStandalonePreset
        ],
        plugins: [
            SwaggerUIBundle.plugins.DownloadUrl
        ],
        layout: "StandaloneLayout",
        docExpansion : "{!! config('l5-swagger.defaults.ui.display.doc_expansion', 'none') !!}",
        deepLinking: true,
        filter: {!! config('l5-swagger.defaults.ui.display.filter') ? 'true' : 'false' !!},
        persistAuthorization: "{!! config('l5-swagger.defaults.ui.authorization.persist_authorization') ? 'true' : 'false' !!}",
    });

    window.ui = ui;

    @if(in_array('oauth2', array_column(config('l5-swagger.defaults.securityDefinitions.securitySchemes'), 'type')))
        ui.initOAuth({
            usePkceWithAuthorizationCodeGrant: "{!! (bool)config('l5-swagger.defaults.ui.authorization.oauth2.use_pkce_with_authorization_code_grant') !!}"
        });
    @endif
}
</script>
</body>
</html>

