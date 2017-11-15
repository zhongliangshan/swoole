<?php
namespace Common;

class MysqlHandle
{
    // mysql 配置文件
    private $_conf = [];

    // 数据库连接
    private $_connects = [];

    // 数据库名
    private $_dbname = 'mysql';

    private $pdo = null;
    // 当前连接
    private $now_conn = null;

    public function __construct()
    {

    }

    // 获取连接
    public function connection($name = 'mysql')
    {

        try {
            $conf = config("config.db.{$name}");
            if (empty($conf) || null == $conf) {
                throw new \Exception("pdo config: config.db.{$name} not found");
            }

            $dsn = "mysql:dbname={$conf['dbname']}";
            if (isset($conf['host']) && isset($conf['port'])) {
                $dsn .= ";host={$conf['host']};port={$conf['port']}";
            } else {
                $dsn .= ";unix_socket={$conf['socket']}";
            }

            //create pdo connection
            $this->pdo = new \PDO(
                $dsn,
                $conf['username'],
                $conf['password'],
                $conf['options']
            );

            return $this->pdo;

        } catch (\Exception $e) {
            echo $e->getMessage();
            return false;
        }
    }

    /**
     * 设定数据库连接，可执行链式操作
     * @param  $name
     * @throws \Exception
     * @return $this
     */
    public function getPdo($name)
    {
        $this->_dbname = $name;
        if (!empty($this->_connects[$name])) {
            $this->ping($this->_connects[$name], $name);
            $this->now_conn = $this->_connects[$name];
        } else {
            $this->now_conn = $this->_connects[$name] = $this->connection($name);
        }

        return $this;
    }

    /**
     * 如果mysql go away,连接重启
     * @param  \PDO         $pdo
     * @param  $name
     * @throws \Exception
     * @return bool
     */
    private function ping($pdo, $name)
    {
        //是否重新连接 0 => ping连接正常, 没有重连 1=>ping 连接超时, 重新连接
        $isReconnect = 0;
        if (!is_object($pdo)) {
            $isReconnect            = 1;
            $this->_connects[$name] = $this->connection($name);
            logger('sql', "mysql ping:pdo instance [{$name}] is null, reconnect");
        } else {
            try {
                //warn 此处如果mysql gone away会有一个警告,此处屏蔽继续重连
                @$pdo->query('SELECT 1');
            } catch (\PDOException $e) {
                //WARN 非超时连接错误
                if ($e->getCode() != 'HY000' || !stristr($e->getMessage(), 'server has gone away')) {
                    throw $e;
                }
                //手动重连
                $this->_connects[$name] = $this->connection($name);
                $isReconnect            = 1;
            } finally {
                if ($isReconnect) {
                    logger("mysql ping: reconnect {$name}");
                }
            }
        }

        return $isReconnect;
    }

    /**
     * 执行 原生sql
     * @param  string $sql            [description]
     * @return [type] [description]
     */
    public function query($sql = '')
    {
        logger('sql:' . $sql);
        if ('' == $sql) {
            return false;
        }

        try {
            return $this->now_conn->query($sql);
        } catch (\PDOException $e) {
            logger($e->getMessage() . PHP_EOL . $this->lastQuery());
            return false;
        }
    }

    // 获取一条数据
    public function fetchOne($table, $where = [], $field = '*', $order = 'id DESC')
    {
        if (!is_array($where)) {
            logger('fetchOne where type is not current except array but ' . gettype($where));
            return false;
        }

        if (empty($where)) {
            logger('fetchOne where is not except empty');
            return false;
        }

        $sql = "select {$field} from {$table} where " . implode(' AND ', $where) . " order by {$order}" . ' limit 1';
        $res = false;
        try {
            $sth = $this->now_conn->query($sql);
            if ($sth) {
                $sth->execute();
                if ($rows = $sth->fetch(\PDO::FETCH_ASSOC)) {
                    $res = $rows;
                }
            }
        } catch (\PDOException $e) {
            logger($e->getMessage() . PHP_EOL . $this->lastQuery());
        }

        return $res;
    }

    // 获取多条数据
    public function fetchAll($table, $where = [], $field = '*', $order = 'id DESC')
    {
        if (!is_array($where)) {
            logger('fetchAll where type is not current except array but ' . gettype($where));
            return false;
        }
        $sql = "select {$field} from {$table}" . (empty($where) ? '' : " where " . implode(' AND ', $where)) . " order by {$order}";
        logger('sql' . $sql, 'sql');
        $res = false;
        try {
            $sth = $this->now_conn->prepare($sql);
            if ($sth) {
                $sth->execute();
                if ($rows = $sth->fetchAll(\PDO::FETCH_ASSOC)) {
                    $res = $rows;
                }
            }
        } catch (\PDOException $e) {
            logger($e->getMessage() . PHP_EOL . $this->lastQuery());
        }

        return $res;
    }

    /**
     * 更新指定表数据
     * @param  string $table
     * @param  array  $data    绑定参数
     * @param  string $where
     * @return int
     */
    final public function update($table, $data, $where)
    {
        if (!is_array($where)) {
            logger('update where type is not current except array but ' . gettype($where));
            return false;
        }

        if (empty($where)) {
            logger('update where is not except empty');
            return false;
        }
        $rowsCount = 0;
        $where     = implode(' AND ', $where);

        $keys = array_keys($data);
        $cols = [];
        foreach ($keys as $v) {
            $cols[] = "{$v}=:$v";
        }
        $cols = implode(', ', $cols);
        $sql  = <<<SQL
update {$table} set {$cols} where {$where}
SQL;

        logger('sql:' . $sql, 'sql');
        try {

            $sth = $this->now_conn->prepare($sql);
            if (!$sth) {
                if (isset($this->_connects[$this->_dbname])) {
                    $this->ping($this->_connects[$this->_dbname], $this->_dbname);
                    $this->now_conn = $this->_connects[$this->_dbname];
                } else {
                    $this->now_conn = $this->connection($this->_dbname);
                }
                $sth = $this->now_conn->prepare($sql);
            }
            $sth->execute($data);
            $rowsCount = $sth->rowCount();
        } catch (\PDOException $e) {
            logger($e->getMessage() . PHP_EOL);
        }

        return $rowsCount;
    }

    /**
     * 删除指定表数据，返回影响行数
     * @param  string $table 表名称
     * @param  string $where where 条件
     * @return int
     */
    final public function del($table, $where)
    {
        if (!is_array($where)) {
            logger('del where type is not current except array but ' . gettype($where));
            return false;
        }

        if (empty($where)) {
            logger('del where is not except empty');
            return false;
        }
        $rowsCount = 0;
        $where     = implode(' AND ', $where);
        $sql       = <<<SQL
delete from {$table} where $where
SQL;
        logger('sql:' . $sql, 'sql');
        try {
            //sth 返回值取决于pdo连接设置的errorMode,如果设置为exception,则抛出异常
            $sth = $this->now_conn->prepare($sql);
            if (!$sth) {
                if (isset($this->_connects[$this->_dbname])) {
                    $this->ping($this->_connects[$this->_dbname], $this->_dbname);
                    $this->now_conn = $this->_connects[$this->_dbname];
                } else {
                    $this->now_conn = $this->connection($this->_dbname);
                }
                $sth = $this->now_conn->prepare($sql);
            }
            $sth->execute();
            $rowsCount = $sth->rowCount();
        } catch (\PDOException $e) {
            logger($e->getMessage() . PHP_EOL);
        }

        return $rowsCount;
    }
    /**
     * 向表里插入一条数据
     * @param  string       $table
     * @param  array        $data
     * @return int|string
     */
    public function addOne($table, $data, $ignore = false)
    {
        $lastId = 0;
        if (empty($data)) {
            return $lastId;
        }
        $keys   = array_keys($data);
        $cols   = implode('`,`', $keys);
        $params = ':' . implode(',:', $keys);
        $sql    = '';
        if ($ignore) {
            $sql = <<<SQL
insert IGNORE into {$table} (`{$cols}`) values ($params)
SQL;
        } else {
            $sql = <<<SQL
insert into {$table} (`{$cols}`) values ($params)
SQL;
        }
        logger('sql=>' . $sql);
        $bindParams = [];
        foreach ($data as $key => $val) {
            $bindParams[':' . $key] = $val;
        }
        logger('bindParams=>' . var_export($bindParams, true));
        try {
            $sth = $this->now_conn->prepare($sql);
            if (!$sth) {
                if (isset($this->_connects[$this->_dbname])) {
                    $this->ping($this->_connects[$this->_dbname], $this->_dbname);
                    $this->now_conn = $this->_connects[$this->_dbname];
                } else {
                    $this->now_conn = $this->connection($this->_dbname);
                }
                $sth = $this->now_conn->prepare($sql);
            }
            $sth->execute($bindParams);
            $lastId = $this->now_conn->lastInsertId();
        } catch (\PDOException $e) {
            logger($e->getMessage() . PHP_EOL);
        }
        logger($lastId);
        return $lastId;
    }

    /**
     * 生成一个prepare的Statement语句，用于批量插入
     * @param  string       $pdoName  pdo连接名称
     * @param  string       $table    表名称
     * @param  array        $bindCols 插入数据字段数组
     * @throws \Exception
     * @return \Closure
     */
    public function addBatchSth($pdoName, $table, $data)
    {
        if (empty($data)) {
            return 0;
        }

        $keys   = array_keys($data[0]);
        $cols   = implode(',', $keys);
        $params = ':' . implode(',:', $keys);
        $sql    = <<<SQL
insert into {$table} ({$cols}) values ($params)
SQL;
        logger($sql, __FUNCTION__);
        $res = [];
        try {
            $sth = $this->now_conn->prepare($sql);
            if (!$sth) {
                if (isset($this->_connects[$this->_dbname])) {
                    $this->ping($this->_connects[$this->_dbname], $this->_dbname);
                    $this->now_conn = $this->_connects[$this->_dbname];
                } else {
                    $this->now_conn = $this->connection($this->_dbname);
                }
                $sth = $this->now_conn->prepare($sql);
            }
            foreach ($data as $val) {
                $bindParam = [];

                foreach ($keys as $item) {
                    $bindParam[":{$item}"] = $val[$item];
                }
                logger(var_export($bindParam, true), __FUNCTION__);
                $sth->execute($bindParam);
                array_push($res, $this->now_conn->lastInsertId());
            }
        } catch (\PDOException $e) {
            logger($e->getMessage() . PHP_EOL);
        }

        return $res;
    }

    public function close($name = 'new_alarm')
    {
        $this->now_conn = null;
        $this->pdo      = null;
        unset($this->_connects[$name]);
    }

    public function __destruct()
    {
        $this->now_conn = null;
        $this->pdo      = null;
        unset($this->_connects);
    }
}
