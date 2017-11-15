<?php
namespace Common;

class RedisHandle
{
    // redis 连接句柄
    private $_redis_server = null;

    private $_host = '';

    private $_port = '';

    private $_timeout = 0;

    public function __construct()
    {
        $this->connection();
    }

    public function connection()
    {
        $this->_host         = '127.0.0.1';
        $this->_port         = '6379';
        $this->_redis_server = new \Redis();
        $this->_redis_server->connect($this->_host, $this->_port);
    }
    /**
     * 查看redis连接是否断开
     * @return $return bool true:连接未断开 false:连接已断开
     */
    public function ping()
    {
        $return = null;
        try {
            $return = $this->_redis_server->ping();
            return '+PONG' == $return ? true : false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     *   缓存数据到redis 中 ，持久缓存
     */
    public function hset($pri_key, $sec_key, $value)
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else if (is_object($value)) {
            $value = json_encode(o2a($value), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return $this->_redis_server->hset($pri_key, $sec_key, $value);
    }

    // 获取hash 数据
    public function hget($pri_key, $sec_key)
    {
        $value      = $this->_redis_server->hget($pri_key, $sec_key);
        $json_value = json_decode($value, true);
        return is_null($json_value) ? $value : $json_value;
    }

    // 加锁 每个锁默认锁定1分钟
    public function addLock($key, $timeout = 60)
    {
        $expiretime = time() + 60;
        return $this->_redis_server->set($key, $expiretime);
    }

    public function addMLock($data, $timeout = 60)
    {
        $expiretime = time() + 60;

    }

    // 获取锁 获取的是锁的过期时间
    public function getLock($key)
    {
        return $this->_redis_server->get($key);
    }

    // hash 批量缓存数据
    public function hmset($pri_key, $data)
    {
        $return = false;

        if (is_array($data) && !empty($data)) {
            foreach ($data as $key => $val) {
                if (is_array($val)) {
                    $data[$key] = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    continue;
                }
                if (is_object($val)) {
                    $data[$key] = json_encode(o2a($val), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    continue;
                }
            }

            $return = $this->_redis_server->hMset($pri_key, $data);
        }
    }

    public function hmget($pri_key, $sec_keys)
    {
        if (empty($sec_keys)) {
            return [];
        }

        $data = $this->_redis_server->hmget($pri_key, $sec_keys);
        if (empty($data)) {
            return [];
        }

        foreach ($data as $key => $val) {
            $value = json_decode($val, true);
            if (is_null($value)) {
                continue;
            }

            $data[$key] = $value;
        }
        return $data;
    }

    public function hgetall($pri_key)
    {
        $data = $this->_redis_server->hgetall($pri_key);
        foreach ($data as $key => $val) {
            $value = json_decode($val, true);
            if (is_null($value)) {
                continue;
            }

            $data[$key] = $value;
        }
        return $data;
    }

    // 删除单个或者多个key
    public function hdel($pri_key, $sec_key)
    {
        return $this->_redis_server->hdel($pri_key, $sec_key);
    }

    public function lpush($key, $value)
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else if (is_object($value)) {
            $value = json_encode(o2a($value), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        return $this->_redis_server->lpush($key, $value);
    }
    public function lpop($key)
    {
        $value = $this->_redis_server->rpop($key);
        return is_null(json_decode($value, true)) ? $value : json_decode($value, true);
    }

    // 魔术方法 调用一些不常用的redis 方法
    public function __call($method, $params)
    {
        return call_user_func_array([$this->_redis_server, $method], $params);
    }
}
