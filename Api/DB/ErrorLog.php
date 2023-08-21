<?php
/**
 * Error Log Utilities
 *
 * PHP Version 7
 *
 * @category DB
 * @package  ErrorLog
 * @author   Martin Wedepohl <martin.wedepohl@gmail.com>
 * @license  https:// Proprietary
 * @link     https:///Api/DB/ErrorLog.php
 */
namespace CAT\Api\DB;

use CAT\Api\Config\Config;
use CAT\Api\DB\MySQLAccess;
use CAT\Api\DB\CheckAccess;
use CAT\Api\Config\Constants;
use CAT\Api\DBConfig\DBConfig;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * ErrorLog class
 *
 * @category Class
 * @package  ErrorLog
 * @author   Martin Wedepohl <martin.wedepohl@gmail.com>
 * @license  https:// Proprietary
 * @link     https:///Api/DB/ErrorLog.php
 */
class ErrorLog
{
    private static $_errorlog_table  = 'errorlog';

    private $_dba = null;

    /**
     * Class Constructor
     *
     * If checkSessionAccess fails the application will be exited
     *
     * @throws \Exception if unable to create a connection to the database
     */
    public function __construct()
    {
        try {
            $this->_dba = new MySQLAccess();
            \date_default_timezone_set(Config::TIMEZONE);


        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Process Error Log API calls.
     *
     * The function must be passed an action and an array of data
     * pairs.
     *
     * $args['action']
     * $args['data'] - Array of data for the action
     *
     * @param array $args Arguments sent to via the POST request
     *
     * @return array Results of the API call
     */
    function processMode(array $args): array
    {
        $action = isset($args['action']) ? $args['action'] : '';
        $data   = isset($args['data']) ? $args['data'] : [];
        
        switch ($action) {
        case 'delete_all_error_log':
            $result = $this->deleteAllErrorLog($data);
            break;
        case 'delete_error_log':
            $result = $this->deleteErrorLog($data);
            break;
        case 'view_error_log':
            $result = $this->viewErrorLog($data);
            break;
        default:
            $result = [
                'status'  => 406,
                'message' => 'No content available for mode requested',
            ];
        }
        return $result;
    }

    /**
     * Delete All Error Logs.
     *
     * @param array $data Array of pairs of data
     *
     * @return array An array of change log results
     *
     * @throws \Exception if there is a database error
     */
    public function deleteAllErrorLog($data): array
    {
        try {
            $table   = self::$_errorlog_table;
            $results = [];
            $logs    = [];

            $num_deletes = $this->_dba->deleteAll($table);

            $results['deleted'] = true;

            return $results;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Delete an Error Log.
     *
     * @param array $data Array of pairs of data
     *
     * @return array An array of change log results
     *
     * @throws \Exception if there is a database error
     */
    public function deleteErrorLog($data): array
    {
        try {
            $table   = self::$_errorlog_table;
            $results = [];
            $logs    = [];

            // Get a chunk of log results.
            $id = isset($data['id']) ? $data['id'] : null;
            if (null === $id) {
                throw new \Exception('Trying to delete a log without an id');
            }

            $num_deletes = $this->_dba->delete($table, 'id', $id);

            $results['deleted'] = $num_deletes;

            return $results;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * View Error Log.
     *
     * @param array $data Array of pairs of data
     *
     * @return array An array of change log results
     *
     * @throws \Exception if there is a database error
     */
    public function viewErrorLog($data): array
    {
        try {
            $table   = self::$_errorlog_table;
            $results = [];
            $logs    = [];

            // Get a chunk of log results.
            $start    = isset($data['start'])       ? $data['start']       : 0;
            $elements = isset($data['numElements']) ? $data['numElements'] : 40;

            $sql = "
                SELECT `id`, `filename`, `line`, `date`, `error`
                FROM {$table}
                ORDER BY date DESC
                LIMIT {$start}, {$elements}
            ";

            $stmt = $this->_dba->query($sql);
            while ($row = $stmt->fetch()) {
                $logs[] = $row;
            }
            $results['endOfData'] = false;
            $results['entries']   = $logs;
            // Check for end of data.
            if (count($logs) < $elements) {
                $results['endOfData'] = true;
            }

            return $results;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}

