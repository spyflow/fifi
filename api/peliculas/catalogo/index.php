<?php
header("Access-Control-Allow-Origin: *"); // Permite solicitudes desde cualquier dominio
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Métodos permitidos
header("Access-Control-Allow-Headers: Content-Type"); // Encabezados permitidos

error_reporting(0); // Desactiva la notificación de errores
ini_set('display_errors', 0); // Evita que se muestren en pantalla

// Include database utilities
require_once __DIR__ . '/../utils/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit();
}

function getWebContent($url) {
    // Obtener la API Key desde las variables de entorno
    $apiKey = getenv('JEY_API_KEY');
    $scraperApiUrl = 'https://api.scraperapi.com/';

    // Verificar si la API Key está configurada correctamente
    if (!$apiKey) {
        die("API Key no encontrada en las variables de entorno.");
    }

    // Construir la URL con parámetros para ScraperAPI
    $params = [
        'api_key' => $apiKey,
        'url' => $url
    ];
    $fullUrl = $scraperApiUrl . '?' . http_build_query($params);

    // Realizar la solicitud con file_get_contents
    $response = file_get_contents($fullUrl);

    if ($response === false) {
        die("Error al realizar la solicitud.");
    }

    return $response;
}

function scrapePelisplus($query, $debug = false) {
    // Check cache first
    $cacheKey = 'catalog';
    $cachedData = getCachedData($cacheKey, 'catalogo');
    
    if ($cachedData !== null) {
        if ($debug) {
            header('Content-Type: application/json');
            echo json_encode(['source' => 'cache', 'data' => json_decode($cachedData, true)], 
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            return;
        } else {
            header('Content-Type: application/json');
            echo $cachedData;
            return;
        }
    }
    
    $baseUrl = "https://www18.pelisplushd.to";
    $html = getWebContent($baseUrl);

    if ($debug) {
        // Mostrar el HTML en crudo si debug está activo
        echo "<pre>";
        echo htmlspecialchars($html);
        echo "</pre>";
        return;
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $movies = [];
    $series = [];

    // Busca todos los contenedores de resultados
    $results = $xpath->query("//a[@class='Posters-link']");

    foreach ($results as $result) {
        $title = $xpath->query(".//p", $result)->item(0)->nodeValue;
        $link = $result->getAttribute('href');
        $softName = str_replace(['/pelicula/', '/serie/'], '', $link); // Eliminar el prefijo de la URL

        // Extraer la URL de la imagen de la carátula
        $img = $xpath->query(".//img", $result)->item(0);
        $imgUrl = $img ? "https://www18.pelisplushd.to" . $img->getAttribute('src') : ''; // Usar la URL original con el prefijo

        $item = [
            'title' => trim($title),
            'soft-name' => $softName,
            'poster' => $imgUrl, // Incluir la URL modificada de la carátula
        ];

        // Clasificación de resultados
        if (stripos($link, '/pelicula/') !== false) {
            $movies[] = $item;
        } else if (stripos($link, '/serie/') !== false) {
            $series[] = $item;
        }
    }

    $result = json_encode([
        'movies' => $movies,
        'series' => $series
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    // Cache the result for 30 days
    setCachedData($cacheKey, $result, 'catalogo', 30);

    header('Content-Type: application/json');
    echo $result;
}

// Obtener el parámetro de búsqueda 'q' de la URL
$query = "";
$debug = isset($_GET['debug']) ? filter_var($_GET['debug'], FILTER_VALIDATE_BOOLEAN) : false; // Control de debug

scrapePelisplus($query, $debug);
?>

