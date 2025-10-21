<?php

// Define the base path for tests
$path = '/api_mylanguage/';
$testDirectory = 'C:/ampp82/htdocs/api_mylanguage/scripts/';

// Function to map filename to route path
function generateRoute($file) {
    global $path;
    $route = str_replace(['canGet', '.php'], '', $file);
    $route = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $route)); // Converts CamelCase to kebab-case
    return $path . 'test/' . $route;
}

// Scan the directory and generate routes
$routes = [];
foreach (scandir($testDirectory) as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $route = generateRoute($file);
        $routes[$route] = $testDirectory . $file; // Full path for including the file
    }
}



// The current path being accessed
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Check if the request matches a route
if (array_key_exists($requestUri, $routes)) {
    include $routes[$requestUri];
} else {
    echo "404 - Test not found for $requestUri";
}
?>

<h1>Import TWIG Index</h1>
<ol>
    <?php foreach ($routes as $route => $file): ?>
        <li><a href="<?php echo htmlspecialchars($route); ?>"><?php echo basename($file, '.php'); ?></a></li>
    <?php endforeach; ?>
</ol>
