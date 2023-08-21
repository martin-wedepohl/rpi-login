<?php
/**
 * Database access
 *
 * PHP Version 7
 *
 * @category DB
 * @package  MySQLAccess
 * @author   Martin Wedepohl <martin.wedepohl@gmail.com>
 * @license  https:// Proprietary
 * @link     https://Api/DB/MySQLAccess.php
 */
namespace CAT\Api\DB;

use CAT\Api\DB\DumpError;
use CAT\Api\DBConfig\DBConfig;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Class for database access
 *
 * @category Class
 * @package  MySQLAccess
 * @author   Martin Wedepohl <martin.wedepohl@gmail.com>
 * @license  https:// Proprietary
 * @link     https://Api/DB/MySQLAccess.php
 */
class MySQLAccess
{
    private static $_pdo;

    /**
     * Build the update string in the format X=:X with the input_parameters[X] = Xdata
     * 
     * @param array $fields All the input data fields
     * @param array $data   Corresponding data for the input
     * 
     * @return string SQL update string
     */
    private function _buildUpdateString(array $fields, array $data): string
    {
        $upd = '';

        // Build the update string
        foreach ($fields as $f) {
            if (!isset($data[$f]) || is_null($data[$f])) {
                $v = 'NULL';      // Null element
            } else {
                $v = ":$f";
            }
            $upd .= ", $f=$v";   // Set element
        }

        // Remove the leading ', '
        $update_vars = substr($upd, 2);

        return $update_vars;
    }

    /**
     * Create an error string based on optional input parameters
     *
     * @param array $params Optional input parameters
     * 
     * @return string Error string
     */
    private function _createErrorStr(array $params): string
    {
        $errorStr = '';

        if (isset($params)) {
            foreach ($params as $key => $value) {
                $errorStr .= "Arg: $key Value: $value<br>";
            }
        }
        return $errorStr;
    }

    /**
     * Constructor for the MySQL object. Connects to the database based on the
     * parameters in the database configuration file.
     * 
     * @throws \Exception if the connection to the database fails
     */
    public function __construct()
    {
        try {
            $dsn = 'mysql:host=' . DBConfig::DB_HOST . ';port=' . DBConfig::DB_PORT . ';dbname=' . DBConfig::DB_NAME;
            $pdoAttrs = [
                \PDO::ATTR_EMULATE_PREPARES => true,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAME utf8',
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET CHARACTER SET utf8'
            ];
            self::$_pdo = new \PDO($dsn, DBConfig::DB_USERNAME, DBConfig::DB_PASSWORD, $pdoAttrs);
            self::$_pdo->exec('set session sql_mode = traditional');
            // Check to see if the database exists
            $stmt = self::$_pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE schema_name='" . DBConfig::DB_NAME . "'");
            $stmt->execute();
            $validConnection = false;
            if ($row = $stmt->fetch()) {
                $validConnection = true;
            }
        } catch (\PDOException $e) {
            // Fatal error since we cannot connect to the database
            // Set the error and go back to the main index page
            $_SESSION['ERROR'] = 'PDO ERROR in MySQLAccess constructor<br>';
            header("refresh: 0; url=/index.php");
            die();
        }

        if (!$validConnection) {
            // Fatal error since we cannot connect to the database
            // Set the error and go back to the main index page
            $_SESSION['ERROR'] = 'PDO ERROR in MySQLAccess constructor<br>';
            header("refresh: 0; url=/index.php");
            die();
        }
    }

    /**
     * Start a PDO tranaction and turn off the auto commit
     *
     * @return null
     *
     * @throws \Exception
     */
    function startTransaction()
    {
        try {
            self::$_pdo->beginTransaction();
        } catch (\Exception $e) {
            $errorstr = 'ERROR: MySQLAccess startTransaction: <br>' . $e->getMessage() . '<br';
            DumpError::logError(__FILE__, __LINE__, $errorstr);
            throw new \Exception('ERROR: MySQLAccess startTransaction');
        }
    }

    /**
     * Commit any pending transactions and turn on auto commit
     *
     * @return null
     *
     * @throws \Exception
     */
    function commitTransaction()
    {
        try {
            self::$_pdo->commit();
        } catch (\Exception $e) {
            $errorstr = 'ERROR: MySQLAccess commit: <br>' . $e->getMessage() . '<br';
            DumpError::logError(__FILE__, __LINE__, $errorstr);
            throw new \Exception('ERROR: MySQLAccess commit');
        }
    }

    /**
     * Rollback any pending transactions and turn on auto commit
     *
     * @return null
     *
     * @throws \Exception
     */
    function rollBackTransaction()
    {
        try {
            self::$_pdo->rollBack();
        } catch (\Exception $e) {
            $errorstr = 'ERROR: MySQLAccess rollBack: <br>' . $e->getMessage() . '<br';
            DumpError::logError(__FILE__, __LINE__, $errorstr);
            throw new \Exception('ERROR: MySQLAccess rollBack');
        }
    }

    /**
     * Delete rows from the database
     * 
     * @param string $table The table to update
     * @param string $key   The search key
     * @param string $data  The data to search for
     * 
     * @return int Number of rows deleted
     * 
     * @throws \Exception if the query fails
     */
    function delete(string $table, string $key, string $data): int
    {
        $sql = null;
        try {
            $sql = "DELETE FROM $table WHERE $key=:$key";
            $params = array($key => $data);
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount();
        } catch (\Exception $e) {
            $errorstr = 'ERROR: MySQLAccess delete: <br>' . $e->getMessage() . '<br>SQL: ' . $sql . '<br>';
            $errorstr .= "Arg: $key Value: $data";
            DumpError::logError(__FILE__, __LINE__, $errorstr);
            throw new \Exception('ERROR: MySQLAccess delete');
        }
    }

    /**
     * Delete all rows from the database
     *
     * @param string $table The table to delete
     *
     * @return null
     *
     * @throws \Exception if the query fails
     */
    function deleteAll(string $table)
    {
        $sql = null;
        try {
            $sql = "TRUNCATE TABLE $table";
            $this->query($sql);
        } catch (\Exception $e) {
            $errorstr = 'ERROR: MySQLAccess deleteAll: ' . $e->getFile() . '(' . $e->getLine() . ')<br>' . $e->getMessage() . '<br>SQL: ' . $sql;
            DumpError::logError(__FILE__, __LINE__, $errorstr);
            throw new \Exception('ERROR: MySQLAccess deleteAll');
        }
    }

    /**
     * Insert a row in the database
     *
     * @param string $table     The table to update
     * @param array  $fields    The data fields that need to be modified
     * @param array  $data      The new data for the fields
     * @param bool   $dumperror True if should make call to dumpError
     *
     * @return int Id of the insert
     *
     * @throws \Exception if the query fails
     */
    function insert(string $table, array $fields, array $data, bool $dumperror = true): int
    {
        $sql = null;
        try {
            $update_vars = $this->_buildUpdateString($fields, $data);
            $sql = "INSERT INTO $table SET $update_vars";
            $this->query($sql, $data);
            return self::$_pdo->lastInsertId();
        } catch (\Exception $e) {
            if ($dumperror) {
                $errorstr = 'ERROR: MySQLAccess insert: ' . $e->getFile() . '(' . $e->getLine() . ')<br>' . $e->getMessage() . '<br>SQL: ' . $sql . '<br>';
                $errorstr .= $this->_createErrorStr($data);
                DumpError::logError(__FILE__, __LINE__, $errorstr);
            }
            throw new \Exception('ERROR: MySQLAccess insert');
        }
    }
    
    /**
     * Return the last insert ID from an SQL query
     * 
     * @return int - Last Insert ID
     */
    public function lastInputId(): int
    {
        try {
            return self::$_pdo->lastInsertId();
        } catch( \PDOException $e ) {
            $errorstr = 'PDO ERROR: MySQLAccess lastInputId: ' . __FILE__ . '(' . __LINE__ . ')<br>' . $e->getMessage();
            dumpError(__FILE__, __LINE__, $errorstr);
        }
    }

    /**
     * Query a database with optional input parameters
     *
     * @param string  $sql       MySQL string with or without input parameters
     * @param array   $params    optional input parameters required for the string (key => data)
     * @param boolean $dumperror True if the error dumped
     * 
     * @return \PDOStatement
     * 
     * @throws \Exception if the prepare or execute fails
     */
    public function query(string $sql, array $params = [], bool $dumperror = true): \PDOStatement
    {
        try {
            $stmt = self::$_pdo->prepare($sql);
            if (!$stmt->execute($params)) {
                $errorstr = 'PDO execute ERROR: MySQLAccess query: ' . __FILE__ . '(' . __LINE__ . ')<br>SQL: ' . $sql . '<br>';
                $errorstr .= $this->_createErrorStr($params);
                DumpError::logError(__FILE__, __LINE__, $errorstr);
                throw new \Exception('PDO execute ERROR in MySQLAccess query');
            }

            return $stmt;
        } catch (\PDOException $e) {
            if ($dumperror) {
                $errorstr = 'PDO ERROR: MySQLAccess query: ' . __FILE__ . '(' . __LINE__ . ')<br>' . $e->getMessage() . '<br>SQL: ' . $sql . '<br>';
                $errorstr .= $this->_createErrorStr($params);
                DumpError::logError(__FILE__, __LINE__, $errorstr);
            }
            throw new \Exception('PDO ERROR in MySQLAccess query');
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Update a row in the database
     * 
     * @param string $table   The table to update
     * @param string $pkfield The key to search on
     * @param array  $fields  The data fields that need to be modified
     * @param array  $data    The new data for the fields
     * 
     * @return int Number of rows changed
     * 
     * @throws \Exception if the query fails
     */
    function update(string $table, string $pkfield, array $fields, array $data): int
    {
        $sql = null;
        try {
            $vars = $this->_buildUpdateString($fields, $data);
            $sql  = "UPDATE $table SET $vars WHERE $pkfield=:$pkfield";
            $stmt = $this->query($sql, $data);
            return $stmt->rowCount();
        } catch (\Exception $e) {
            $errorstr = 'ERROR: MySQLAccess update: ' . $e->getFile() . '(' . $e->getLine() . ')<br>' . $e->getMessage() . '<br>SQL: ' . $sql . '<br>';
            $errorstr .= 'Pkfield: ' . $pkfield . '<br>';
            foreach ($data as $key => $value) {
                $errorstr .= 'Arg: ' . $key . ' Value: ' . $value . '<br>';
            }
            DumpError::logError(__FILE__, __LINE__, $errorstr);
            throw new \Exception('ERROR: MySQLAccess update');
        }
    }

    /**
     * Return the length of the varchar fields in a table
     * 
     * @param string $table The table to process
     * 
     * @return array Array of table rows and lengths
     */
    function varcharLengths(string $table): array
    {
        $sql = "DESCRIBE $table";
        $stmt = $this->query($sql);
        $tablerows = [];
        while ($row = $stmt->fetch()) {
            // Get the varchar(x) fields
            if (0 === strncmp('varchar', $row['Type'], 7)) {
                $tablerows[$row['Field']] = substr($row['Type'], 8, -1);
            }
            if (0 === strncmp('varbinary', $row['Type'], 9)) {
                $tablerows[substr($row['Field'], 1)] = substr($row['Type'], 10, -1) - 1;
            }
        }

        return $tablerows;
    }

}
