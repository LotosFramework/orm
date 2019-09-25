<?php

namespace Lotos\ORM;

use PDO;
use PDOException;

class Connector
{
    private $Host;
    private $DBPort;
    private $DBName;
    private $DBUser;
    private $DBPassword;
    private $pdo;
    private $sQuery;
    private $connectionStatus = false;
    private $parameters;
    public $rowCount   = 0;
    public $columnCount   = 0;
    public $querycount = 0;
    private $retryAttempt = 0;
    const AUTO_RECONNECT = true;
    const RETRY_ATTEMPTS = 3;

    public function __construct(array $params)
    {
        $this->type = $params['DB_TYPE'];
        $this->host = $params['DB_HOST'];
        $this->port = $params['DB_PORT'];
        $this->name = $params['DB_NAME'];
        $this->user = $params['DB_USER'];
        $this->pass = $params['DB_PASS'];
        $this->char = $params['DB_CHAR'];
        $this->parameters = array();
    }

    private function connect()
    {
        try {
            $dsn = $this->type .':';
            $dsn .= 'host=' . $this->host . ';';
            $dsn .= 'port=' . $this->port . ';';
            $dsn .= 'dbname=' . $this->name . ';';
            $dsn .= 'charset=' . $this->char . ';';
            $this->pdo = new PDO($dsn,
                $this->user,
                $this->pass,
                array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                )
            );
            $this->connectionStatus = true;

        } catch (PDOException $e) {
            throw new PDOException($e->getMessage());
        }
    }

    private function setFailureFlag()
    {
        $this->pdo = null;
        $this->connectionStatus = false;
    }

    public function closeConnection()
    {
        $this->pdo = null;
    }

    private function init($query, $parameters = null, $driverOptions = array())
    {
        if (!$this->connectionStatus) {
            $this->connect();
        }
        try {
                $this->parameters = $parameters;
                $this->sQuery = $this->pdo->prepare(
                    $this->buildParams($query, $this->parameters),
                    $driverOptions
                );
            if (!empty($this->parameters)) {
                if (array_key_exists(0, $parameters)) {
                    $parametersType = true;
                    array_unshift($this->parameters, "");
                    unset($this->parameters[0]);
                } else {
                    $parametersType = false;
                }
                foreach ($this->parameters as $column => $value) {
                    $this->sQuery->bindParam($parametersType ? intval($column) : ":" . $column, $this->parameters[$column]);
                }
            }
            if (!isset($driverOptions[PDO::ATTR_CURSOR])) {
                $this->sQuery->execute();
            }
            $this->querycount++;
        }
        catch (PDOException $e) {
            throw new PDOException($query.' '. $e->getMessage());
        }

        $this->parameters = array();
    }

    private function buildParams($query, $params = null)
    {
        if (!empty($params)) {
            $array_parameter_found = false;
            foreach ($params as $parameter_key => $parameter) {
                if (is_array($parameter)){
                    $array_parameter_found = true;
                    $in = "(";
                    foreach ($parameter as $key => $value){
                        $name_placeholder = $parameter_key."_".$key;
                            $in .= ":".$name_placeholder.", ";
                        $params[$name_placeholder] = $value;
                    }
                    $in = rtrim($in, ", ") . ")";
                    $query = preg_replace("/:".$parameter_key."/", $in, $query);
                    unset($params[$parameter_key]);
                }
            }
            if ($array_parameter_found){
                $this->parameters = $params;
            }
        }
        return $query;
    }
    /**
     * @return bool
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }
    /**
     * @return bool
     */
    public function commit()
    {
        return $this->pdo->commit();
    }
    /**
     * @return bool
     */
    public function rollBack()
    {
        return $this->pdo->rollBack();
    }
    /**
     * @return bool
     */
    public function inTransaction()
    {
        return $this->pdo->inTransaction();
    }

    public function query($query, $params = null, $fetchMode = PDO::FETCH_ASSOC)
    {
        $query        = trim($query);
        $rawStatement = explode(" ", $query);
        $this->init($query, $params);
        $statement = strtolower($rawStatement[0]);
        if ($statement === 'select' || $statement === 'show') {
            return $this->sQuery->fetchAll($fetchMode);
        } elseif ($statement === 'insert' || $statement === 'update' || $statement === 'delete') {
            return $this->sQuery->rowCount();
        } else {
            return NULL;
        }
    }

    /**
     * @param $tableName
     * @param null $params
     * @return bool|string
     */
    public function insert(string $tableName, array $params = null)
    {
        $keys = array_keys($params);
        $this->query("set sql_mode='';");
        $rowCount = $this->query(
            'INSERT INTO `' . $tableName . '` (`' . implode('`,`', $keys) . '`)
            VALUES (:' . implode(',:', $keys) . ')',
            $params
        );
        if ($rowCount === 0) {
            return false;
        }
        return $this->lastInsertId();
    }

    public function update(string $tableName, array $params, int $pk)
    {
        $sql = 'update ' . $tableName;
        $updates = [];
        foreach ($params as $key => $value) {
            if(count($updates) == 0) {
                $updates[] = ' set ' . $key . '=:' . $key;
            } else {
                $updates[] = $key .'=:'. $key;
            }
        }
        $sql .= implode(', ', $updates);
        $sql .= ' where id='.$pk;

        $this->query($sql, $params);
    }
    /**
     * @return string
     */
    public function lastInsertId()
    {
        $lastId = $this->pdo->lastInsertId();
        return $lastId;
    }
    /**
     * @param $query
     * @param null $params
     * @return array
     */
    public function column($query, $params = null)
    {
        $this->init($query, $params);
        $resultColumn = $this->sQuery->fetchAll(PDO::FETCH_COLUMN);
        $this->rowCount = $this->sQuery->rowCount();
        $this->columnCount = $this->sQuery->columnCount();
        $this->sQuery->closeCursor();
        return $resultColumn;
    }
    /**
     * @param $query
     * @param null $params
     * @param int $fetchmode
     * @return mixed
     */
    public function row($query, $params = null, $fetchmode = PDO::FETCH_ASSOC)
    {
        $this->init($query, $params);
        $resultRow = $this->sQuery->fetch($fetchmode);
        $this->rowCount = $this->sQuery->rowCount();
        $this->columnCount = $this->sQuery->columnCount();
        $this->sQuery->closeCursor();
        return $resultRow;
    }
    /**
     * @param $query
     * @param null $params
     * @return mixed
     */
    public function single($query, $params = null)
    {
        $this->init($query, $params);
        $column = $this->sQuery->fetchColumn();
        return $column;
    }

}
