<?php
/**
 * Short description for file
 *
 * Long description for file (if any)...
 *
 * PHP version 5
 *
 * LICENSE: This source file is subject to version 3.01 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_01.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category  Handler
 * @package   Comunicaciones/ws
 * @author    felipe castro <felipe.castro@zgroup.cl>
 * @copyright 2017 zgroup 08-2017
 * @license   http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link      https://gist.github.com/danferth/9512172
 */
class Database
{
    private $_host;
    private $_dbname;
    private $_user;
    private $_pass;
    private $_dbh;
    private $_qError;
    private $_stmt;
    private $_bConnected = false;
    protected $link;

    /**
     * Undocumented function
     *
     * @param string $host     ipdb
     * @param string $username user db
     * @param string $password pas fb
     * @param string $dbname   nombre db
     */
    public function __construct($host, $username, $password, $dbname)
    {
        $this->_host   = $host;
        $this->_dbname = $dbname;
        $this->_user   = $username;
        $this->_pass   = $password;
        $this->_connect();
    }
    /**
     * Undocumented function
     *
     * @return void
     */
    private function _connect()
    {
        // Set DSN
        $dsn = 'mysql:host=' . $this->_host . ';dbname=' . $this->_dbname;
        // Set options
        $options = array(
            PDO::ATTR_PERSISTENT               => false,
            PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND       => "SET NAMES utf8",
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        );
        // Create a new PDO instanace
        try {
            $this->_dbh        = new PDO($dsn, $this->_user, $this->_pass, $options);
            $this->_bConnected = true;
        } catch (PDOException $e) {
            // Write into log
            //loginfo(print_r($e,true), 'pdo_handler');
            echo $this->_exceptionLog($e->getMessage());
        }
    }
    /**
     * Undocumented function
     */
    public function __destruct()
    {
        //$this->disconnect;
        $this->_dbh = null;
    }

    /**
     * Undocumented function
     *
     * @param string $query Description.
     *
     * @return void Description.
     */
    public function query($query)
    {
        $this->_stmt = $this->_dbh->prepare($query);
    }

    /**
     * Undocumented function
     *
     * @param string $param Description.
     * @param string $value Description.
     * @param string $type  Description.
     *
     * @return void
     */
    public function bind($param, $value, $type = null)
    {
        try {
            if (is_null($type)) {
                switch (true) {
                    case is_int($value):
                        $type = PDO::PARAM_INT;
                        break;
                    case is_bool($value):
                        $type = PDO::PARAM_BOOL;
                        break;
                    case is_null($value):
                        $type = PDO::PARAM_NULL;
                        break;
                    default:
                        $type = PDO::PARAM_STR;
                }
            }
            $this->_stmt->bindValue($param, $value, $type);
        } catch (PDOException $e) {
            //Write into log and display Exception
            echo $this->_exceptionLog($e->getMessage(), '');
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function execute()
    {
        return $this->_stmt->execute();

        $this->_qError = $this->_dbh->errorInfo();
        if (!is_null($this->_qError[2])) {
            echo $this->_qError[2];
        }
        echo 'done with query';
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function resultset()
    {
        $this->execute();
        return $this->_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function resultsetNum()
    {
        $this->execute();
        return $this->_stmt->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function resultsetObj()
    {
        $this->execute();
        return $this->_stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function single()
    {
        $this->execute();
        return $this->_stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function singleObj()
    {
        $this->execute();
        return $this->_stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function rowCount()
    {
        return $this->_stmt->rowCount();
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function lastInsertId()
    {
        return $this->_dbh->lastInsertId();
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function beginTransaction()
    {
        return $this->_dbh->beginTransaction();
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function endTransaction()
    {
        return $this->_dbh->commit();
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function cancelTransaction()
    {
        return $this->_dbh->rollBack();
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function debugDumpParams()
    {
        return $this->_stmt->debugDumpParams();
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function queryError()
    {
        $this->_qError = $this->_dbh->errorInfo();
        if (!is_null($this->_qError[2])) {
            echo $this->_qError[2];
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function disconnect()
    {
        // Set the PDO object to null to close the connection
        // http://www.php.net/manual/en/pdo.connections.php
        $this->_dbh = null;
    }

    /**
     * Writes the log and returns the exception
     *
     * @param string $message Description.
     * @param string $sql     Description.
     *
     * @return string
     */
    private function _exceptionLog($message, $sql = "")
    {
        $exception = 'Unhandled Exception. <br />';
        $exception .= $message;
        $exception .= "<br /> You can find the error back in the log.";

        if (!empty($sql)) {
            // Add the Raw SQL to the Log
            $message .= "\r\nRaw SQL : " . $sql;
        }
        // Write into log
        //$this->log->write($message);

        return $exception;
    }
}
