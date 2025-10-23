<?php


namespace App\Middleware;

use App\Configuration\Config;

/**
 * PreflightMiddleware
 * 
 * This middleware handles preflight `OPTIONS` requests for CORS (Cross-Origin Resource Sharing). 
 * Preflight requests are sent by browsers before the actual request to verify the CORS policies 
 * on the server. This middleware checks the request origin and, if allowed, sets the appropriate 
 * CORS headers and responds with a `200 OK` status. If the origin is not allowed, it responds 
 * with a `403 Forbidden` status.
 *
 * - **Allowed Origins**: The list of accepted origins is fetched from the environment configuration (`cors.allowed_origins`).
 * - **CORS Headers**: Sets the necessary CORS headers (`Access-Control-Allow-Origin`, `Access-Control-Allow-Headers`, 
 *   `Access-Control-Allow-Methods`, `Access-Control-Allow-Credentials`).
 * - **Preflight Requests**: Handles `OPTIONS` requests by verifying the origin and responding accordingly.
 * - **Logging**: Logs the allowed and denied origins and whether the request was preflight or not.
 * 
 * @param object $request The incoming HTTP request object.
 * @param callable $next  The next middleware or application logic in the chain.
 * 
 * @return mixed The result of the next middleware or application logic.
 */
class PreflightMiddleware
{
    /**
     * Handle an incoming request and manage CORS preflight requests.
     * 
     * This method checks if the request method is `OPTIONS` (a preflight request) and validates 
     * the origin against the list of accepted origins. If the origin is allowed, it sets the 
     * necessary CORS headers, including `Access-Control-Allow-Headers` and `Access-Control-Allow-Methods`. 
     * The response status is set to `200 OK`. If the origin is not allowed, it returns a `403 Forbidden` 
     * response. For non-preflight requests, it simply logs and passes the request to the next middleware.
     * 
     * @param object $request The incoming HTTP request object.
     * @param callable $next  The next middleware or application logic in the chain.
     * 
     * @return mixed The result of the next middleware or application logic.
     */
    public function handle($request, $next)
    {
        // Fetch accepted origins from the environment configuration
        $acceptedOrigins = Config::get('cors.allowed_origins');

        // Check if the request is an OPTIONS (preflight) request
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            // Check if the origin is allowed
            if (isset($_SERVER['HTTP_ORIGIN'])) {
                if (in_array($_SERVER['HTTP_ORIGIN'], $acceptedOrigins)) {
                    // Log the allowed origin
                    error_log('PreflightMiddleware-18: Origin allowed: ' . $_SERVER['HTTP_ORIGIN']);

                    // Set CORS headers for allowed origin
                    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
                    header('Access-Control-Allow-Credentials: true');

                    // Allow specific headers and methods
                    header("Access-Control-Allow-Headers: Content-Type, Authorization, User-Authorization");
                    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

                    // Respond with a 200 OK status
                    header("HTTP/1.1 200 OK");
                    exit;
                } else {
                    print_r('origin not allowed');
                    // Log the denied origin and respond with a 403 status
                    error_log('PreflightMiddleware-27: Origin not allowed: ' . $_SERVER['HTTP_ORIGIN']);
                    header("HTTP/1.1 403 Forbidden Source");
                    exit;
                }
            } else {
                // Log that the request is not a preflight (OPTIONS) request
                writeLog('PreflightMiddleware-32', 'No OPTIONS request');
            }
        }
         // Proceed to the next middleware or application logic
         return $next($request);
    }
}
