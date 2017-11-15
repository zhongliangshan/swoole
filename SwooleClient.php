<?php

class SwooleClient
{
    private $_client;
    /**
     * 连接 host
     * @var string
     */
    private $_host = '0.0.0.0';

    /**
     * 端口
     * @var string
     */
    private $_port = '9501';

    /**
     * $flag
     */
    private $_flag = 1;

    /**
     * 连接模式
     */
    private $_mode = SWOOLE_SOCK_TCP;

    /**
     * 是否同步阻塞
     */
    private $_block = false;

    /**
     * 是否开启长度检查
     * @var integer
     */
    private $open_eof_check = 1;

    private $package_eof = '\r\n\r\n';
    /**
     * package_max_length
     */
    private $package_max_length = 2097152; // 协议最大长度

    /**
     * open_length_check 开启长度检查
     * @var integer
     */
    private $open_length_check = 1;

    private $package_length_type = 'N';

    /**
     * 第N个字节是包长度的值
     * @var integer
     */
    private $package_length_offset = 0;

    /**
     * 第几个字节开始计算长度
     * @var integer
     */
    private $package_body_offset = 4;

    /**
     * socket_buffer_size
     */
    private $socket_buffer_size = 2097152; // 2M

    /**
     * open_tcp_nodelay
     * 关闭Nagle合并算法
     */
    private $open_tcp_nodelay = true;

    public function __set($name, $value)
    {
        $this->$name = $value;
    }

    public function __construct($end_check = false, $length_check = false)
    {
        $this->_client = ($this->_block) ? new swoole_client($this->_mode, $this->_block) : new swoole_client($this->_mode);
        $this->_client->set(array(
            'socket_buffer_size' => $this->socket_buffer_size,
        ));
        if ($end_check) {
            $this->_client->set([
                'open_eof_check'     => $this->open_eof_check,
                'package_eof'        => $this->package_eof,
                'package_max_length' => $this->package_max_length,
            ]);
        }

        if ($length_check) {
            $this->_client->set([
                'open_length_check'     => $this->open_length_check,
                'package_length_type'   => $this->package_length_type,
                'package_length_offset' => $this->package_length_offset,
                'package_body_offset'   => $this->package_body_offset,
                'package_max_length'    => $this->package_max_length,
            ]);
        }
    }

    public function connect()
    {
        if (!$this->_client->connect($this->_host, $this->_port)) {
            throw new \Exception( "Error: [{$this->_client->errCode}]\n");
			return false;
        }
    }

    public function onConnect($client)
    {
        echo 'Date:' . date('Y-m-d H:i:s') . "\t connect success\n";
    }

    public function send($data = '')
    {
        return $this->_client->send($data);
    }

    public function close()
    {
        return $this->_client->close();
    }
}

