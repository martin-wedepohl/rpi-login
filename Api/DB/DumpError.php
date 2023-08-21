<?php
/**
 * Database error logger
 *
 * PHP Version 7
 *
 * @category DB
 * @package  DumpError
 * @author   Martin Wedepohl <martin.wedepohl@gmail.com>
 * @license  https:// Proprietary
 * @link     https://API/DB/DumpError.php
 */
namespace CAT\Api\DB;

use CAT\Api\Config\Config;
use CAT\Api\DB\MySQLAccess;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Class for database error logger
 *
 * @category Class
 * @package  DumpError
 * @author   Martin Wedepohl <martin.wedepohl@gmail.com>
 * @license  https:// Proprietary
 * @link     https://API/DB/DumpError.php
 */
class DumpError
{
    private static $_error_table = 'errorlog';

    /**
     * Log to database error log table.
     *
     * If an error is caught from the database call just ignore since already
     * in the error logger.
     *
     * @param string $file    The file name where the call was made from.
     * @param int    $line    The line number where the call was made.
     * @param string $message The message to log in the database.
     */
    public static function logError(string $file, int $line, string $message)
    {
        try {
            \date_default_timezone_set(Config::TIMEZONE);
            $now        = date('Y-m-d H:i:s');
            $db         = new MySQLAccess();
            $table      = self::$_error_table;
            $table_rows = $db->varcharLengths($table);
            $fields     = [
                'filename',
                'line',
                'error',
            ];
            $data       = [
                'filename' => substr($file, 0, $table_rows['filename']),
                'line'     => $line,
                'error'    => substr($message, 0, $table_rows['error']),
            ];

            if (!$db->insert($table, $fields, $data, false) ) {
                throw new \Exception('Unable to insert into database error logger');
            }
        } catch (\Exception $e) {
            echo '<br>ERROR in database error logger: ' . $e->getFile() . '(' . $e->getLine() . '): ' . $e->getMessage() . '<br>';
        }
    }

}
