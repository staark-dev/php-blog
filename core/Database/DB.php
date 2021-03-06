<?php
declare(strict_types=1);
namespace Core\Database;

use Exception;
use PDO;
use Core\Exceptions\DatabaseException;

class DB {
    private static $instance = null;

    private $query;
    public $results = [];
    public $count = 0;
    private $error = false;

    private $query_string = "";
    private $bindValues = [];
    private $lastId;
    public $smtp;

    public function __construct() {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
            PDO::ATTR_ORACLE_NULLS => PDO::NULL_EMPTY_STRING
        ];
        
        try {
            // dbname=". env('DB_NAME') .";
            $this->smtp = new PDO("mysql:host=". env('DB_HOST') .";port=" . env('DB_PORT'), env('DB_USER'), env('DB_PASS'), $options);
            //$this->smtp->query('USE ' . env('DB_NAME'));
        } catch (\PDOException $e) {
            throw new DatabaseException('Failed to connect to MySQL Server - ' . $e->getMessage());
        }

        return $this->smtp;
    }

    public static function getInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new DB();
        }

        return self::$instance;
    }

    public function query(string $sql, array $parameters = []) {
        $this->error = false;
        $this->smtp->query('USE ' . env('DB_NAME'));

        // Prepare sql statement
        if ($this->query = $this->smtp->prepare($sql)) {
            $i = 1;
            if(is_array($parameters) && !is_null($parameters)) {
                foreach ($parameters as $param) {
                    $this->query->bindValue($i, $param);
                    $i++;
                }
            }

            if ($this->query->execute()) {
                // You can PDO::FETCH_OBJ instad of assoc, or whatever you like
                $this->results = $this->query->fetchAll(PDO::FETCH_ASSOC);
                $this->count = $this->query->rowCount();
                $this->lastId = $this->smtp->lastInsertId();
            } else {
                $this->error = true;
                throw new DatabaseException("Failed to execute this query");
            }
        }

        return $this;
    }

    // Insert / Update
    /**
     * Insert statement of query
     */
    public function insert(string $table_name = '*', array $parameters = []) {
        $this->error = false;
        $this->smtp->query('USE ' . env('DB_NAME'));

        $keys = array_keys($parameters);
        $values = array_values($parameters);
        $action = "INSERT INTO {$table_name}";

        if(is_null($table_name) || $table_name === "*") return ;

        /**
         * Extract all parameters of array
         * implode and foreach
         */
        $values = preg_replace("/^(.*?)$/i", "'$1'", $values);
        
        if(is_array($parameters)) {
            for ($i = 0; $i < count($keys); $i++) {
                if ($i < count($keys))
                $this->bindValues[] = $parameters[$keys[$i]];
            }

            $action .= "(". implode(", ", $keys) .")";
        }

        $action .= " VALUES (". implode(',', $values) .")";
        
        if ($this->query = $this->smtp->prepare($action)) {
            
            if ($this->query->execute()) {
                $this->error = false;
                $this->lastId = $this->smtp->lastInsertId();
                var_dump($this->lastId);
            } else {
                $this->error = true;
                var_dump($this->smtp->errorInfo());
            }
        }

        return $this;
    }

    public function select($fields = "*") {
        $action = "";
        $this->query_string = "";
        $this->smtp->query('USE ' . env('DB_NAME'));

        if (is_array($fields)) {
            $action = "SELECT ";
            for ($i = 0; $i < count($fields); $i++) {
                $action .= $fields[$i];
                if ($i != count($fields) - 1)
                    $action .= ', ';
            }
        } else {
            $action = "SELECT * ";
        }

        $this->query_string .= $action;

        return $this;
    }

    public function from(string $table) {
        $this->query_string .= " FROM {$table} ";

        return $this;
    }

    public function where(array $where = []) {
        $keys = array_keys($where);
        
        $action = "WHERE ";

        for ($i = 0; $i < count($keys); $i++) {
            $action .= $keys[$i] . ' = ?';
            if ($i < count($keys) - 1)
                $action .= ' AND ';
            $this->bindValues[] = $where[$keys[$i]];
        }

        $this->query_string .= $action;

        return $this;
    }

    public function get() {
        $this->smtp->query('USE ' . env('DB_NAME'));
        if(is_null($this->query_string)) return;
        $sql = $this->query_string;

        if ($this->query = $this->smtp->prepare($sql)) {
            /**
             * bug fixed on posts
             */
            $item = [end($this->bindValues)];
            if(count($this->bindValues) >= 1) {
                if ($this->query->execute($item)) {
                    $this->results = $this->query->fetchAll(PDO::FETCH_OBJ);
                    $this->count = $this->query->rowCount();
                } else {
                    throw new DatabaseException("Failed to execute this query");
                    $this->error = true;
                }
            }
            /**
             * Default query execute
             */
            if ($this->query->execute()) {
                $this->results = $this->query->fetchAll(PDO::FETCH_OBJ);
                $this->count = $this->query->rowCount();
            } else {
                throw new DatabaseException("Failed to execute this query");
                $this->error = true;
            }
        }

        return (object)$this;
    }

    public function results() {
        return $this->get()->results;
    }

    public function rows() {
        return $this->count;
    }
}