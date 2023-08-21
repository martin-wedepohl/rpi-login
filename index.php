<?php
/**
 * Process the API calls which are sent in a JSON string
 *
 * PHP Version 7
 *
 * @category Api
 * @package  Code
 * @author   Martin Wedepohl <martin@orchardrecovery.com>
 * @license  https://ox.o-connect.ca Proprietary
 * @link     https://ox.o-connect.ca/Api/process-api.php
 */
namespace CAT;

session_start();

use CAT\Api\DB\ErrorLog;
use CAT\Api\Users\Users;
use CAT\Api\Config\Config;
use CAT\Api\DB\MySQLAccess;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * PHP can't read json data directly
 */
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
$args        = [];

if ($contentType === "application/json") {
    // Receive the RAW post data.
    $content = trim(file_get_contents("php://input"));

    // Decode the JSON string into a named array.
    $args = json_decode($content, true);
}

/**
 * Ensure we have valid data returning a 406 Not Acceptable
 * error if invalid
 */
try {
    // Default for invalid data
    $result = [
        'status'  => 406,
        'message' => 'No content available for mode requested',
    ];

    // Check the API mode
    if (isset($args['mode'])) {
        switch ($args['mode']) {
            case 'error':
                if (isset($args['action'])) {
                    $result = handleError($args);
                }
                break;
            case 'users':
                if (isset($args['action'])) {
                    $result = handleUsers($args);
                }
                break;
        default:
        }
    }
} catch(\Exception $e) {
    // Some other exception usually a database error
    $result = [
        'message' => $e->getMessage(),
    ];
    $loginRequired = 'Invalid credentials' === $result['message'];
    if ($loginRequired) {
        $result['status'] = 401;
    } else {
        $result['status'] = 500;
    }
}

// Check if we have an error and set the appropriate header
if (isset($result['status'])) {
    switch ($result['status']) {
    case 200:
        header('HTTP/1.1 200 Okay');
        break;
    case 401:
        header('HTTP/1.1 401 Unauthorized');
        break;
    case 406:
        header('HTTP/1.1 406 Not Acceptable');
        break;
    case 500:
        header('HTTP/1.1 500 Internal Server Error');
        break;
    default:
    }
}

// headers for not caching the results
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// headers to tell that result is JSON
header('Content-type: application/json');

exit(json_encode($result));

/**
 * Handle any error API calls.
 *
 * @param array $args Arguments sent to via the POST request
 *
 * @return array Array of data requested
 */
function handleError(array $args): array
{
    $error  = new ErrorLog();
    $result = $error->processMode($args);
    
    return $result;
}

/**
 * Handle any user API calls.
 *
 * @param array $args Arguments sent to via the POST request
 *
 * @return array Array of data requested
 */
function handleUsers(array $args): array
{
    $users  = new Users();
    $result = $users->processMode($args);
    
    return $result;
}

