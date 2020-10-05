<?php

/**
 * Extended MySQLi Parametrized DB Class
 * mysqli_handler.php, a MySQLi database access wrapper
 *
 * @package Database
 * @version 1.0
 * @author
 */
class mysqli_handler
{
    // query to execute
    public $query = null;
    protected $connection = null;
    protected static $configuration = array();
    private static $instance = null;
    private $results = null;
    // transaction check
    private $transaction = false;

    /**
     * Connecting to db, if error throw exeption
     */
    private function __construct()
    {
        $this->connection = new mysqli(self::$configuration['db_host'], self::$configuration['db_user'], self::$configuration['db_password'], self::$configuration['db_name'] );
        if (!empty($this->connection->connect_errno)) {
            $this->loginfo('[ERROR DATABASE]Connect failed, error: ' . $this->connection->connect_error);
            throw new Exception('Connect failed, error: ' . $this->connection->connect_error);
        }
    }

    /**
     * Just nice closing connection to current db
     */
    public function __destruct()
    {
        $this->connection->close();
    }

    /**
     * Creating a singleton of current db connection
     */
    public static function getInstance()
    {
        if (empty(self::$instance[self::$configuration['db_host']][self::$configuration['db_name']])) {
            self::$instance[self::$configuration['db_host']][self::$configuration['db_name']] = new self();
        }
        return self::$instance[self::$configuration['db_host']][self::$configuration['db_name']];
    }

    /**
     * Giving a freedom of doing what you want with mysqli connection
     */
    public function getConnection()
    {
        return self::$connection;
    }

    /**
     * Setting configuration to db
     *
     * @param string $pDbHost
     * @param string $pUser
     * @param string $pPassword
     * @param mixed $pDbName (optional)
     */
    public static function setDbConfiguration($pDbHost, $pUser, $pPassword, $pDbName = null)
    {
        self::$configuration = array(
                            'db_host'		=> $pDbHost,
                            'db_user'		=> $pUser,
                            'db_password'	=> $pPassword,
                            'db_name'		=> $pDbName);
                                }

    /**
     * Executing query on db
     */
    private function execute()
    {
        if (empty($this->query)) {
            $this->loginfo('[ERROR DATABASE]Empty query');
            throw new Exception('Empty query');
        }
        $this->loginfo('[QUERY][=>]'.$this->query);
        $this->results = $this->connection->query($this->query);
        if ($this->connection->errno > 0) {
            $this->loginfo($this->connection->errno . ' - ' . $this->connection->error . "\n" . $this->query);
            throw new Exception($this->connection->errno . ' - ' . $this->connection->error . "\n" . $this->query);
        }
    }

    /**
     * Get a single array or resutls
     *
     * @return & array
     */
    public function &getSingleResult()
    {
        $this->execute();

        $results = array();

        if (!empty($this->results)) {
            $results = $this->results->fetch_assoc();
            $this->results->free_result();
        }
        return $results;
    }

    /**
     * Get a mutlidimensional array or resutls
     *
     * @return & array
     */
    public function &getResults()
    {
        $this->execute();
        $results = array();
        if (!empty($this->results)) {
            while ($data = $this->results->fetch_assoc()) {
                $results[] = $data;
            }
            unset($data);
            $this->results->free_result();
        }
        return $results;
    }

    /**
     * Run insert/update/other on db
     *
     * @return int (affected rows)
     */
    public function runQuery()
    {
        $this->execute();
        return $this->connection->affected_rows;
    }

    /**
     * Set auto commit
     *
     * @param $pSetTo boolen
     * 
     * @return boolen
     */
    private function setAutoCommit($pSetTo)
    {
        if (false === $result = $this->connection->autocommit($pSetTo)) {
            $this->loginfo('[ERROR DATABASE]Cant set autocommit to ' . $pSetTo);
            throw new Exception('Cant set autocommit to ' . $pSetTo);
        }
        return $result;
    }

    /**
     * Starts transaction on db
     *
     * @return void
     */
    public function StartTransaction()
    {
        if (true === $this->transaction) {
            $this->loginfo('[ERROR DATABASE]Transaction already started');
            throw new Exception('Transaction already started');
        } elseif (true === $this->setAutoCommit(false)) {
            $this->transaction = true;
        }
    }

    /**
     * Commit transaction on db
     * 
     * @return void
     */
    public function CommitTransaction()
    {
        if (false === $this->transaction) {
            $this->loginfo('[ERROR DATABASE]No Transaction to commit');
            throw new Exception('No Transaction to commit');
        } elseif (true === $this->connection->commit()) {
            $this->setAutoCommit(true);
            $this->transaction = false;
        } else {
            $this->loginfo('[ERROR DATABASE]Cant commit tranaction');
            throw new Exception('Cant commit tranaction');
        }
    }

    /**
     * Rollback transaction on db
     * 
     * @return void
     */
    public function RollbackTransaction()
    {
        if (false === $this->transaction) {
            $this->loginfo('[ERROR DATABASE]No Transaction to rollback');
            throw new Exception('No Transaction to rollback');
        } elseif (true === $this->connection->rollback()) {
            $this->setAutoCommit(true);
            $this->transaction = false;
        } else {
            $this->loginfo('[ERROR DATABASE]Cant rollback tranaction');
            throw new Exception('Cant rollback tranaction');
        }
    }

    /**
     * Escapes data
     *
     * @param string $pData ss
     * 
     * @return & $data
     */
    public function &Escape($pData)
    {
        $data = 'NULL';
        if (!is_null($pData)) {
            $data = "'" . $this->connection->escape_string($pData) . "'";
        }
        return $data;
    }
    /**
     * Undocumented function
     *
     * @param string $data input texto
     * 
     * @return void
     */
    private function loginfo($data)
    {
        $script		= $_SERVER['PHP_SELF'];
        $path_info	= pathinfo($script);
        $logname	= str_replace(".php", "", $path_info['basename']);
        $today		= date("Ymd");
        list($usec, $sec) = explode(" ", microtime());
        $u			= substr($usec, 1, 5);
        $time		= date("H:i:s").$u;
        file_put_contents("/var/www/ws/logs/".$logname."_".$today.".log", $time." ".$data."\n", FILE_APPEND);
        file_put_contents("/var/www/ws/logs/database_".$today.".log", $time." ".$data."\n", FILE_APPEND);
    }
}
