<?php
/**
 * User API
 *
 * PHP Version 7
 *
 * @category DB
 * @package  DumpError
 * @author   Martin Wedepohl <martin.wedepohl@gmail.com>
 * @license  https:// Proprietary
 * @link     https://API/DB/DumpError.php
 */
namespace CAT\Api\Users;

use CAT\Api\Config\Config;
use CAT\Api\DB\MySQLAccess;
use CAT\Api\DBConfig\DBConfig;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Class for user api
 *
 * @category Class
 * @package  Users
 * @author   Martin Wedepohl <martin.wedepohl@gmail.com>
 * @license  https:// Proprietary
 * @link     https://API/DB/Users.php
 */
class Users
{
    private static $_users_table = 'users';
    private        $_dba         = null;

    /**
     * Constructor for users.
     *
     * Instantiates the database connection and sets the timezone of the application.
     * 
     * @throws \Exception if the connection to the database fails.
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
     * Process the API mode.
     *
     * @param array $args Array of arguments.
     *
     * @return array Results of the API call.
     */
    public function processMode($args) {
        $result = [
            'status'  => 406,
            'message' => 'No content available for action requested',
        ];

        $action = isset($args['action']) ? $args['action'] : '';
        
        switch ($action) {
            case 'create':
                $result = $this->create($args);
                break;
            case 'login':
                $result = $this->login($args);
                break;
            case 'validate':
                $result = $this->validate($args);
                break;
            default:
                break;
        }

        return $result;
    }

    /**
     * Create a new user in the database.
     *
     * The arguments are:
     *   data['username'] - The username to create.
     *   data['password'] - The users password.
     *   data['name']     - The users name.
     *
     * @param array $args Array of arguments.
     *
     * @return array Containing the users token.
     */
    public function create($args) {
        try {
            $table    = self::$_users_table;

            $data     = isset($args['data']) ? $args['data'] : null;
            $username = isset($data['username']) ? trim($data['username']) : null;
            $password = isset($data['password']) ? trim($data['password']) : null;
            $name     = isset($data['name']) ? trim($data['name']) : null;

            // Validate input.
            $error = '';
            if (null === $username || '' === $username) {
                $error .= "Username is required\r\n";
            }
            if (null === $password || '' === $password) {
                $error .= "Password is required\r\n";
            }
            if (null === $name || '' === $name) {
                $error .= "Name is required\r\n";
            }

            if ('' !== $error) {
                throw new \Exception($error);
            }

            // Check that the user isn't already in the database.
            $sql    = "SELECT * FROM {$table} WHERE username=:username";
            $params = ['username' => $username];
            $stmt   = $this->_dba->query($sql, $params);
            if (false !== $row = $stmt->fetch()) {
                throw new \Exception("User {$username} already exists");
            }

            // Salt and modification are the current date/time.
            $now = date('Y-m-d H:i:s');

            // Create a hashed password from the salt, password and pepper.
            $sql = "SELECT SHA2(CONCAT(:salt, :password, :pepper), 512) AS hash";
            $params = [
                'salt'     => $now,
                'password' => $password,
                'pepper'   => DBConfig::DB_PEPPER,
            ];
            $stmt   = $this->_dba->query($sql, $params);
            if (false === $row = $stmt->fetch()) {
                throw new \Exception('Unable to create password hash');
            }
            $hash = $row['hash'];

            $fields = [
                'modification',
                'username',
                'hash',
            ];

            $fdata = [
                'modification' => $now,
                'username'     => $username,
                'hash'         => $hash,
            ];

            $insert_id = $this->_dba->insert($table, $fields, $fdata);
            if (0 === $insert_id) {
                throw new \Exception('Unable to insert new user into the database');
            }

            // Create a token from the salt, username and pepper.
            $sql = "SELECT SHA2(CONCAT(:salt, :username, :pepper), 512) AS token";
            $params = [
                'salt'     => $now,
                'username' => $username,
                'pepper'   => DBConfig::DB_PEPPER,
            ];
            $stmt   = $this->_dba->query($sql, $params);
            if (false === $row = $stmt->fetch()) {
                throw new \Exception('Unable to create login token');
            }
            $token = $row['token'];

            $results['token'] = $token;

            return $results;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Login a user to the system.
     *
     * This will recreate the password hash and login token.
     *
     * The arguments are:
     *   data['username'] - The username to create.
     *   data['password'] - The users password.
     *
     * @param array $args Array of arguments.
     *
     * @return array Containing the login token.
     */
    public function login($args) {
        try {
            $table    = self::$_users_table;

            $data     = isset($args['data']) ? $args['data'] : null;
            $username = isset($data['username']) ? trim($data['username']) : null;
            $password = isset($data['password']) ? trim($data['password']) : null;

            // Validate input.
            $error = '';
            if (null === $username || '' === $username) {
                $error .= "Username is required\r\n";
            }
            if (null === $password || '' === $password) {
                $error .= "Password is required\r\n";
            }

            if ('' !== $error) {
                throw new \Exception($error);
            }

            $sql    = "SELECT `id` FROM {$table} WHERE username=:username AND hash=SHA2(CONCAT(modification, :password, :pepper), 512)";
            $params = [
                'username' => $username,
                'password' => $password,
                'pepper'   => DBConfig::DB_PEPPER,
            ];
            $stmt   = $this->_dba->query($sql, $params);
            if (false === $row = $stmt->fetch()) {
                throw new \Exception('Invalid credentials');
            }

            // Update password hash since we are logging in anew.

            // Salt and modification are the current date/time.
            $id  = $row['id'];
            $now = date('Y-m-d H:i:s');

            // Create a hashed password from the salt, password and pepper.
            $sql = "SELECT SHA2(CONCAT(:salt, :password, :pepper), 512) AS hash";
            $params = [
                'salt'     => $now,
                'password' => $password,
                'pepper'   => DBConfig::DB_PEPPER,
            ];
            $stmt   = $this->_dba->query($sql, $params);
            if (false === $row = $stmt->fetch()) {
                throw new \Exception('Unable to create password hash');
            }
            $hash = $row['hash'];

            $fields = [
                'modification',
                'username',
                'hash',
            ];

            $fdata = [
                'modification' => $now,
                'username'     => $username,
                'hash'         => $hash,
                'id'           => $id,
            ];

            $num_updated = $this->_dba->update($table, 'id', $fields, $fdata);
            if (0 === $num_updated) {
                throw new \Exception('Unable to update user in the database');
            }

            // Create a token from the salt, username and pepper.
            $sql = "SELECT SHA2(CONCAT(:salt, :username, :pepper), 512) AS token";
            $params = [
                'salt'     => $now,
                'username' => $username,
                'pepper'   => DBConfig::DB_PEPPER,
            ];
            $stmt   = $this->_dba->query($sql, $params);
            if (false === $row = $stmt->fetch()) {
                throw new \Exception('Unable to create login token');
            }
            $token = $row['token'];

            $results['token'] = $row['token'];

            return $results;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Validate a user token in the system.
     *
     * The arguments are:
     *   data['username'] - The username to create.
     *   data['token']    - The users token.
     *
     * @param array $args Array of arguments.
     *
     * @return array Successful or not.
     */
    public function validate($args) {
        try {
            $table    = self::$_users_table;

            $data     = isset($args['data']) ? $args['data'] : null;
            $username = isset($data['username']) ? trim($data['username']) : null;
            $token    = isset($data['token']) ? trim($data['token']) : null;

            // Validate input.
            $error = '';
            if (null === $username || '' === $username) {
                $error .= "Username is required\r\n";
            }
            if (null === $token || '' === $token) {
                $error .= "Token is required\r\n";
            }

            if ('' !== $error) {
                throw new \Exception($error);
            }

            $sql    = "SELECT `modification` FROM {$table} WHERE username=:username";
            $params = [
                'username' => $username,
            ];
            $stmt   = $this->_dba->query($sql, $params);
            if (false === $row = $stmt->fetch()) {
                throw new \Exception('Invalid credentials');
            }

            $salt = $row['modification'];

            // Create a token from the salt, username and pepper.
            $sql = "SELECT SHA2(CONCAT(:salt, :username, :pepper), 512) AS token";
            $params = [
                'salt'     => $salt,
                'username' => $username,
                'pepper'   => DBConfig::DB_PEPPER,
            ];
            $stmt   = $this->_dba->query($sql, $params);
            if (false === $row = $stmt->fetch()) {
                throw new \Exception('Unable to create login token');
            }
            $validated = $token === $row['token'] ? true : false;

            if (false === $validated) {
                throw new \Exception('Invalid credentials');
            }

            $results['validated'] = $validated;

            return $results;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

}
