<?php

class HttpServer
{
    // server
    private $_server = null;

    // 配置信息
    private $_settings = [];

    private $_mode = SWOOLE_SOCK_TCP;

    private $_app = null;

    public static $_task_ids = [];

    public function __construct($host = '', $port = '')
    {
        $config = config('swoole');
        //加载配置文件内容
        if (null == $config) {
            throw new ErrorException("swoole config file:{$swoole} not found");
        }

        if ('' != $host) {
            $config['host'] = $host;
        }

        if ('' != $port) {
            $config['port'] = $port;
        }

        $this->_settings = $config;
    }

    public function loadframework()
    {
        //框架核心文件
        $coreFiles = [
            '/Common/Base.php',
            '/Common/MysqlHandle.php',
            '/Common/RedisHandle.php',
        ];
        foreach ($coreFiles as $v) {
            include_once SWOOLE_PATH . $v;
        }
    }

    /**
     * 设置swoole进程名称
     * @param string $name swoole进程名称
     */
    private function setProcessName($name)
    {
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($name);
        } else {
            if (function_exists('swoole_set_process_name')) {
                swoole_set_process_name($name);
            } else {
                trigger_error(__METHOD__ . " failed. require cli_set_process_title or swoole_set_process_name.");
            }
        }
    }

    public function run()
    {
        // 设置log_file 文件路径
        $date     = date('Y-m-d');
        $log_file = SWOOLE_PATH . "/log/{$date}/swoole.log";
        if (!is_dir(dirname($log_file))) {
            mkdir(dirname($log_file));
        }
        $this->_settings['log_file'] = $log_file;

        $this->_server = new swoole_server($this->_settings['host'], $this->_settings['port'], SWOOLE_PROCESS, $this->_mode);

        $this->loadFramework();
        $this->_server->set($this->_settings);

        //回调函数
        $call = [
            'start',
            'workerStart',
            'managerStart',
            'task',
            'finish',
            'workerStop',
            'shutdown',
            'receive',
        ];
        //事件回调函数绑定
        foreach ($call as $v) {
            $m = 'on' . ucfirst($v);
            if (method_exists($this, $m)) {
                $this->_server->on($v, [$this, $m]);
            }
        }
        $this->_app = \Common\Base::getInstance();
        $this->_app->initAllCtrls(); // 自动注册所有的 控制器类
        $this->_app->initRedis();    // 初始化所有的基本信息 加载到redis中
        $this->_server->start();
    }

    /**
     * swoole-server master start
     * @param $server
     */
    public function onStart($server)
    {
        echo 'Date:' . date('Y-m-d H:i:s') . "\t swoole_http_server master worker start\n";
        $this->setProcessName(SWOOLE_TASK_NAME_PRE . '-master');
        //记录进程id,脚本实现自动重启
        $pid = "{$this->_server->master_pid}\n{$this->_server->manager_pid}";
        file_put_contents(SWOOLE_TASK_PID_PATH, $pid);
    }

    /**
     * manager worker start
     * @param $server
     */
    public function onManagerStart($server)
    {
        echo 'Date:' . date('Y-m-d H:i:s') . "\t swoole_http_server manager worker start\n";
        $this->setProcessName(SWOOLE_TASK_NAME_PRE . '-manager');
    }

    /**
     * swoole-server master shutdown
     */
    public function onShutdown()
    {
        unlink(SWOOLE_TASK_PID_PATH);
        $this->_app->destoryCtrls();
        echo 'Date:' . date('Y-m-d H:i:s') . "\t swoole_http_server shutdown\n";
    }

    /**
     * worker start 加载业务脚本常驻内存
     * @param $server
     * @param $workerId
     */
    public function onWorkerStart($server, $workerId)
    {
        if (0 == $workerId) {
            $server->tick(10000, function ($timer_id) use ($server) {
                $server->task(['class_name' => 'Alarm', 'action_name' => 'addInitAlarm', 'request' => []]);
            });
            $server->tick(1000, function ($timer_id) use ($server) {
                for ($i = 0; $i < 50; $i++) {
                    $server->task(['class_name' => 'Alarm', 'action_name' => 'index', 'request' => []]);
                }
            });
            $server->tick(15001, function ($timer_id) use ($server) {
                $server->task(['class_name' => 'Msg', 'action_name' => 'noticeCustomer', 'request' => []]);
                $server->task(['class_name' => 'Msg', 'action_name' => 'noticeReceiver', 'request' => []]);
            });
            $server->tick(310000, function ($timer_id) use ($server) {
                $server->task(['class_name' => 'Common', 'action_name' => 'updateBaseInfo', 'request' => []]);
            });

            $server->tick(10800000, function ($timer_id) use ($server) {
                $server->task(['class_name' => 'Tool', 'action_name' => 'index', 'request' => []]);
            });
        }
        if ($workerId >= $this->_settings['worker_num']) {
            $this->setProcessName(SWOOLE_TASK_NAME_PRE . '-task');
        } else {
            $this->setProcessName(SWOOLE_TASK_NAME_PRE . '-event');
        }
    }

    /**
     * worker 进程停止
     * @param $server
     * @param $workerId
     */
    public function onWorkerStop($server, $workerId)
    {
        echo 'Date:' . date('Y-m-d H:i:s') . "\t swoole_http_server[{" . SWOOLE_TASK_NAME_PRE . "-worker}  worker:{$workerId} shutdown\n";
    }

    public function onReceive($server, $fd, $fromId, $data)
    {
        echo 'Date:' . date('Y-m-d H:i:s') . "\treceive client data\n";
        $server->task($data);
    }

    /**
     * 任务回调
     */
    public function onTask($server, $taskId, $fromId, $request)
    {
        if (!is_array($request)) {
            $request = json_decode($request, true);
        }
        $ret = $this->_app->run($request, $taskId, $fromId);
        return $ret;
    }

    public function onFinish($server, $taskId, $ret)
    {
        $fromId = $server->worker_id;
        if (empty($ret['errno']) || 200 == $ret['errno']) {
            //任务成功运行不再提示
            echo "\tTask[taskId:{$taskId}] success" . PHP_EOL;
        } else {
            $error = PHP_EOL . var_export($ret, true);
            #echo "\tTask[taskId:$fromId#{$taskId}] failed, Error[$error]" . PHP_EOL;
        }
    }

}
