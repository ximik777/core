<?php

namespace JT;

class DB {

    protected static $instance;
    protected $handle;
    var $sql;
    var $error;
    var $errno;

    protected $config = array(
        'host' => 'localhost',
        'user' => '',
        'pass' => '',
        'name' => '',
        'port' => '3306',
        'charset' => 'utf8',
        'persistent' => false,
        'autocommit' => true
    );

    protected function __clone(){}

    public static function getInstance($config = array()){
        if (!isset(self::$instance)) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public static function getHandle(){
        return self::$instance->handle;
    }

    private function __construct($config = array()){
        $this->config = array_merge($this->config,$config);

        if($this->config['persistent'] === true){
            $this->config['host'] = 'p:'.$this->config['host'];
        }

        $this->handle = @new mysqli($this->config['host'], $this->config['user'], $this->config['pass'], $this->config['name'], $this->config['port']);

        if ($this->handle->connect_errno) {
            throw new Exception("Database is not connected", E_WARNING);
        }

        if($this->config['autocommit'] === true){
            $this->handle->autocommit(true);
        }

        if($this->config['charset'] !== false){
            $this->handle->query("SET NAMES {$this->config['charset']}");
        }
        return true;
    }

    private static function query_replace($sql, $data_arr = null) {
        if ($data_arr === null || $data_arr == array()) {
            return $sql;
        } else {
            $sql_out = '';
            $start   = 0;
            preg_match_all('/([^\\\\]{1}\\$)/', $sql, $math, PREG_OFFSET_CAPTURE);

            foreach ($math[1] as $key => $val) {
                $sql_out .= substr($sql, $start, $val[1] - $start + 1);

                if (is_array($data_arr)) {
                    $sql_out .= is_null($data_arr[$key]) ? 'NULL' : "'" . addslashes($data_arr[$key]) . "'";
                } elseif ($key == 0) {
                    $sql_out .= is_null($data_arr) ? 'NULL' : "'" . addslashes($data_arr) . "'";
                }
                $start = $val[1] + 2;
            }

            $sql_out .= substr($sql, $start);
            return str_replace('\\$', '$', $sql_out);
        }
    }

    public static function query($sql, $data_arr = null) {
        $sql = self::query_replace($sql, $data_arr);
        self::$instance->sql = $sql;
        if (!$res = self::$instance->handle->query($sql)) {
            self::$instance->errno = self::$instance->handle->errno;
            self::$instance->error = $sql . ' ' . self::$instance->errno . ' ' . self::$instance->handle->error;
            return false;
        }
        return $res;
    }

    public static function query_insert($sql, $data_arr = null){
        self::query($sql, $data_arr);
        return self::$instance->handle->insert_id;
    }

    public static function query_affected_rows($sql, $data_arr = null){
        self::query($sql, $data_arr);
        return self::$instance->handle->affected_rows;
    }

    public static function get_value_query($sql, $data_arr = null){
        if (!$res = self::query($sql, $data_arr)) return false;
        if ($res->num_rows & $res->field_count) {
            $res = $res->fetch_array();
            return $res[0];
        } else {
            return false;
        }
    }

    public static function get_array_list($sql, $data_arr = null){
        if (!$res = self::query($sql, $data_arr)) return false;
        $array = array();
        while ($row = $res->fetch_assoc()) {
            $array[] = $row;
        }
        return $array;
    }

    public static function get_key_val_array($sql, $data_arr = null) {
        if (!$res = self::query($sql, $data_arr)) return false;
        $array = array();
        while ($row = $res->fetch_array()) {
            $array[$row[0]] = $row[1];
        }
        return $array;
    }

    public static function get_affected_rows($sql, $data_arr = null){
        self::query($sql, $data_arr);
        return self::$instance->handle->affected_rows;
    }

    public static function get_one_line_assoc($sql, $data_arr = null){
        if (!$res = self::query($sql, $data_arr))
            return false;
        return $res->fetch_assoc();
    }

    public static function exec_query($query, $transaction = false) {
        $i    = 0;
        $arr  = preg_split('/;[ 	]*(\n|\r)/', trim($query));
        if($transaction) self::transaction_start();
        foreach ($arr as $a) {
            if (!self::query($a)) {
                if($transaction) {
                    self::rollback();
                }
                return 0;
            }
            $i++;
        }
        if($transaction) self::commit();
        return $i;
    }

    public static function get_assoc_column($sql, $data_arr = null){
        if (!$res = self::query($sql, $data_arr)) return false;
        $arr = array();
        while ($row = $res->fetch_array()) {
            $arr[] = $row[0];
        }
        return $arr;
    }

    public static function get_assoc_column_id($sql, $data_arr = null){
        if (!$res = self::query($sql, $data_arr)) return false;
        $arr = array();
        while ($row = $res->fetch_assoc()) {
            $id = array_shift($row);
            $arr[$id] = $row;
        }
        return $arr;
    }

    public static function begin() {
        return self::transaction_start();
    }

    public static function transaction_start() {
        return self::query('START TRANSACTION');
    }

    public static function commit(){
        return self::query('COMMIT');
    }

    public static function rollback() {
        return self::query('ROLLBACK');
    }

    public function __destruct(){
        if(self::$instance->handle)
            self::$instance->handle->close();
    }
}