<?php
namespace Common;

use \Common\MysqlHandle as Mysql;
use \Common\RedisHandle;

class Base
{
    /**
     * 控制器的名称
     * @var string
     */
    public $_ctrl = '';

    /**
     * 方法名称
     */
    public $_action = '';

    /**
     * class name
     */

    public $_class = '';

    /**
     * 带上命名空间的控制器
     */
    public $_ctrls = [];

    // 初始化
    public static $_instance = null;

    const ALARM_STRATEGY    = 'alarm_strategy';
    const ALARM_RULE_BASE   = 'alarm_rule_base';
    const ALARM_SHIELD      = 'alarm_shield';
    const ALARM_CONVERGENCE = 'alarm_convergence';
    const ALARM_APPNAME     = 'alarm_appname';
    const ALARM_KEY         = 'alarm_key';
    const ALARM_CUSTOMER    = 'alarm_customer';
    const ALARM_MODULE      = 'alarm_module';
    const ALARM_OBJECT      = 'alarm_object';
    const ALARM_MODULE_INFO = 'alarm_module_info';

    // 单例模式防止 __clone
    private function __clone()
    {}

    public static function getInstance()
    {
        if (null !== self::$_instance) {
            return self::$_instance;
        }

        return new self;
    }

    public function run($req, $taskId, $fromId)
    {
        $class_name = ucfirst($req['class_name']) . 'Controller';
        if (!isset($this->_ctrls[$class_name])) {
            logger($class_name . ' not exists in systems', 'swoole_server', 'error');
            return ['errno' => 1000, 'msg' => "{$class_name} not find"];
        }
        try {
            $this->_class  = new $this->_ctrls[$class_name];
            $this->_action = $req['action_name'] . 'Action';
            if (!method_exists($this->_class, $this->_action)) {
                logger('this class : ' . $this->_ctrl . ' is not find Action ' . $this->_action . '----> error', 'swoole_server', 'error');
                return ['errno' => 1002, 'msg' => $class_name . ' class ->>' . $this->_action . ' 方法未找到'];
            }

            $res = $this->_class->{$this->_action}(isset($req['request']) ? $req['request'] : []);
            return ['errno' => isset($res['errno']) ? $res['errno'] : 200, 'msg' => isset($res['msg']) ? $res['msg'] : 'SUCCESS'];
        } catch (\Exception $e) {
            return ['errno' => 1001, 'msg' => $class_name . '类未定义' . $e->getMessage()];
        } finally {
            unset($this->_class, $this->_action);
        }
    }

    public function destoryCtrls()
    {
        $this->_ctrls  = null;
        $this->_ctrl   = null;
        $this->_class  = null;
        $this->_action = null;
        return true;
    }

    // 初始化所有的控制器
    public function initAllCtrls()
    {
        $dir_path = SWOOLE_PATH . '/Ctrl/';
        $files    = glob($dir_path . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $file_list                  = explode('.', $file);
                $ctrls                      = explode('/', $file_list[0]);
                $this->_ctrl                = $ctrls[count($ctrls) - 1];
                $this->_ctrls[$this->_ctrl] = '\Ctrl\\' . $ctrls[count($ctrls) - 1];
                $this->sql_auto_register_class($file);
                continue;
            }

            if (is_dir($file)) {
                $sec_files = glob($file . '/*');
                foreach ($sec_files as $sec_file) {
                    if (is_file($sec_file)) {
                        $file_list                  = explode('.', $sec_file);
                        $ctrls                      = explode('/', $file_list[0]);
                        $this->_ctrl                = $ctrls[count($ctrls) - 1];
                        $this->_ctrls[$this->_ctrl] = '\Ctrl\\' . $ctrls[count($ctrls) - 2] . '\\' . $ctrls[count($ctrls) - 1];
                        $this->sql_auto_register_class($sec_file);
                        continue;
                    }
                }
            }

        }
    }

    private function sql_auto_register_class($file = '')
    {
        if ('' == $file) {
            return false;
        }

        spl_autoload_register(function () use ($file) {
            if (!file_exists($file)) {
                logger($this->_ctrl . ' is not find----> error', 'swoole_server', 'error');
                throw new \Exception($this->_ctrl . ' is not find----> error');
            }

            if (!class_exists($this->_ctrl, false)) {
                logger('include class ' . $file, 'swoole_server');
                include_once $file;
            }

            return true;
        });
        return true;
    }

    // 初始化 基本redis数据
    public function initRedis()
    {
        $redis = new RedisHandle();
        $mysql = new Mysql();
        $alarm = $mysql->getPdo('new_alarm');
        // 初始化策略
        $strategy = $alarm->fetchAll('alarm_strategy');
        $redis->hmset(self::ALARM_STRATEGY, array_using_key($strategy, 'id'));
        // 初始化规则
        $alarm_rule = $alarm->fetchAll('alarm_rule');
        $redis->hmset(self::ALARM_RULE_BASE, array_using_key($alarm_rule, 'id'));

        // 初始化屏蔽规则
        $alarm_shield = $alarm->fetchAll('alarm_shield');
        $redis->del(self::ALARM_SHIELD);
        $redis->hmset(self::ALARM_SHIELD, array_using_key($alarm_shield, 'id'));
        // 初始化规则
        $alarm_convergence = $alarm->fetchAll('alarm_convergence_rule');
        $redis->hmset(self::ALARM_CONVERGENCE, array_using_key($alarm_convergence, 'id'));
        // 初始化appname
        $alarm_appname = $alarm->fetchAll('alarm_appinfo');
        $redis->hmset(self::ALARM_APPNAME, array_using_key($alarm_appname, 'id'));
        $redis->hmset(self::ALARM_APPNAME, array_using_key($alarm_appname, 'appname'));
        // 初始化key
        $alarm_key = $alarm->fetchAll('alarm_keyinfo');
        $redis->hmset(self::ALARM_KEY, array_using_key($alarm_key, 'key'));
        $redis->hmset(self::ALARM_KEY, array_using_key($alarm_key, 'id'));
        // 初始化售后规则
        $alarm_customer = $alarm->fetchAll('alarm_customer_rule', ['status' => 1]);
        $redis->del(self::ALARM_CUSTOMER);
        $redis->hmset(self::ALARM_CUSTOMER, array_using_key($alarm_customer, 'id'));
        // 初始化object表
        $alarm_object = $alarm->fetchAll('alarm_object');
        if (!empty($alarm_object)) {
            $data = [];
            foreach ($alarm_object as $object) {
                if (isset($data[$object['fid']])) {
                    array_push($data[$object['fid']], $object);
                } else {
                    $data[$object['fid']][] = $object;
                }

            }
            $redis->hmset(self::ALARM_OBJECT, $data);
        }
        // 初始化object表
        $alarm_module = $alarm->fetchAll('alarm_module');
        if (!empty($alarm_module)) {
            $data = [];
            foreach ($alarm_module as $module) {
                if (isset($data[$module['fid']])) {
                    array_push($data[$module['fid']], $module);
                } else {
                    $data[$module['fid']][] = $module;
                }

            }
            $redis->hmset(self::ALARM_MODULE, $data);
        }

        // 缓存模块信息
        $module_info = json_decode(curl('http://statweb.xunleioa.com/api/cmdb/get_all_model_info'), true);
        $data        = [];
        if ($module_info) {
            foreach ($module_info['data'] as $item) {
                if ('' == $item['hostname']) {
                    continue;
                }

                if (isset($data[$item['hostname']])) {
                    array_push($data[$item['hostname']], $item['mod_id']);
                } else {
                    $data[$item['hostname']][] = $item['mod_id'];
                }
            }
        }
        $redis->hmset(self::ALARM_MODULE_INFO, $data);
    }
}
