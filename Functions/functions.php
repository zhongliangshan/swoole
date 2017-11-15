<?php

/**
 * 支持 config.db.mysql
 * @param  string $file [description]
 * @return [type]       [description]
 */
function config($name = '' , $default = null) {
    static $cached = [];
    // 移除多余的分隔符
    $name = trim($name, '.');
    if (isset($cached[$name])) {
      return null === $cached[$name] ? $default : $cached[$name];
    }

    // 获取配置名及路径
    if (strpos($name, '.') === false) {
      $paths    = [];
      $filename = $name;
    } else {
      $paths    = explode('.', $name);
      $filename = array_shift($paths);
    }

    if (isset($cached[$filename])) {
      $data = $cached[$filename];
    } else {
      // 默认优先查找 php 数组类型的配置

      // 当前配置环境路径
      $path =SWOOLE_PATH . '/Config';
      $file = "$path/$filename.php";
      if (is_file($file)) {
          $data = include $file;
      }
      // 缓存文件数据
      $cached[$filename] = $data;
    }
    // 支持路径方式获取配置，例如：config('file.key.subkey')
    foreach ($paths as $key) {
	  if (is_object($data)) {
     	 $data = isset($data->{$key}) ? $data->{$key}: null ;
	  }else {
		$data = isset($data[$key]) ? $data[$key] : null ;
	  } 
    }
    // 缓存数据
    $cached[$name] = $data;
    return null === $cached[$name] ? $default : $cached[$name];

}
function logger($msg , $filename='common' , $type = 'info') {
    $date = date('Y-m-d');
	$file = SWOOLE_LOG."/{$date}/{$filename}.log";
    if (!file_exists(dirname($file))) {
        mkdir(dirname($file), 0777);
    }

    $message = "{$type} [" . date('Y-m-d H:i:s') . "]: ";

    if (is_array($msg)) {
        error_log($message. PHP_EOL , 3 , $file);
    } else {
        $msg = $message.$msg. PHP_EOL;
    }
    error_log(print_r($msg, 1) , 3 , $file);
}


 /**
  * 加载函数库
  *
  *     load_functions('tag', ...)
  *     load_functions(array('tag', ...))
  *
  * @param string|array $names
  */
function load_functions($names)
{
    static $cached = ['common'];

    if (func_num_args() > 1) {
        $names = func_get_args();
    } elseif (!is_array($names)) {
        $names = [$names];
    }

    $names = array_map('strtolower', $names);

    foreach ($names as $name) {
        if (!isset($cached[$name])) {
             $file = SWOOLE_PATH. "/Functions/{$name}.php";
             if (is_file($file)) {
                 require_once $file;
             }
        }
    }
}

// 二元表达式
function on($first , $second , $return = true) {
	return $first === $second ? $return : false;
}
function on_3($bool , $resTrue , $resFalse =null){
	return $bool ? $resTrue : $resFalse;
}


/**
 * 确保一个可迭代的数据类型, 可用于foreach，避免判断
 * 如果　$iterator　是一个可迭代类型则返回其本身，否则返回一个空数组
 *
 * @param  mixed   $iterator
 * @return mixed
 */
function _i($iterator)
{
    return is_array($iterator) || is_object($iterator)
    ? $iterator
    : [];
}
/**
 * 执行 curl 请求，并返回响应内容
 *
 * @param  string   $url
 * @param  array    $data
 * @param  array    $options
 * @return string
 */
function curl($url, array $data = null, array $options = null)
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_URL            => $url,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_RETURNTRANSFER => 1,
    ]);

    if ($data) {
        $data = http_build_query($data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($data),
        ]);
    }

    if ($options) {
        curl_setopt_array($ch, $options);
    }

    $response = curl_exec($ch);
	// 获取http状态码
    $intReturnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (200 != $intReturnCode) {
        return false;
    }

    curl_close($ch);

    return $response;
}

/**
 * CURL DELETE 请求
 *
 * @param  string   $url
 * @param  array    $postdata
 * @param  array    $curl_opts
 * @return string
 */
function delete($url, $postdata = '', array $curl_opts = null)
{
    $ch = curl_init();
    if ('' !== $postdata && is_array($postdata)) {
        $postdata = http_build_query($postdata);
    }

    write_log('curl_delete', "url:' . $url,parm:" . var_export($postdata, true));
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_URL            => $url,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_POST           => 1,
        CURLOPT_POSTFIELDS     => $postdata,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FAILONERROR    => 1,
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
    ]);

    if (null !== $curl_opts) {
        curl_setopt_array($ch, $curl_opts);
    }

    $result = curl_exec($ch);
    // 获取http状态码
    $intReturnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    write_log('curl_delete', var_export($result, true) . ',status:' . $intReturnCode);
    curl_close($ch);

    return 200 == $intReturnCode;
}

if (!function_exists('http_build_url')) {
    define('HTTP_URL_REPLACE', 1);          // Replace every part of the first URL when there's one of the second URL
    define('HTTP_URL_JOIN_PATH', 2);        // Join relative paths
    define('HTTP_URL_JOIN_QUERY', 4);       // Join query strings
    define('HTTP_URL_STRIP_USER', 8);       // Strip any user authentication information
    define('HTTP_URL_STRIP_PASS', 16);      // Strip any password authentication information
    define('HTTP_URL_STRIP_AUTH', 32);      // Strip any authentication information
    define('HTTP_URL_STRIP_PORT', 64);      // Strip explicit port numbers
    define('HTTP_URL_STRIP_PATH', 128);     // Strip complete path
    define('HTTP_URL_STRIP_QUERY', 256);    // Strip query string
    define('HTTP_URL_STRIP_FRAGMENT', 512); // Strip any fragments (#identifier)
    define('HTTP_URL_STRIP_ALL', 1024);     // Strip anything but scheme and host

    /**
     * Build an URL
     * The parts of the second URL will be merged into the first according to the flags argument.
     *
     * @param mixed   (Part(s) of) an URL in form of a string or associative array like parse_url() returns
     * @param mixed   Same     as the first argument
     * @param integer A        bitmask of binary or'ed HTTP_URL constants (Optional)HTTP_URL_REPLACE is the default
     * @param array   If       set, it will be filled with the parts of the composed url like parse_url() would return
     */
    function http_build_url($url, $parts = [], $flags = HTTP_URL_REPLACE, &$new_url = false)
    {
        $keys = ['user', 'pass', 'port', 'path', 'query', 'fragment'];

        // HTTP_URL_STRIP_ALL becomes all the HTTP_URL_STRIP_Xs
        if ($flags & HTTP_URL_STRIP_ALL) {
            $flags |= HTTP_URL_STRIP_USER;
            $flags |= HTTP_URL_STRIP_PASS;
            $flags |= HTTP_URL_STRIP_PORT;
            $flags |= HTTP_URL_STRIP_PATH;
            $flags |= HTTP_URL_STRIP_QUERY;
            $flags |= HTTP_URL_STRIP_FRAGMENT;
        }
        // HTTP_URL_STRIP_AUTH becomes HTTP_URL_STRIP_USER and HTTP_URL_STRIP_PASS
        elseif ($flags & HTTP_URL_STRIP_AUTH) {
            $flags |= HTTP_URL_STRIP_USER;
            $flags |= HTTP_URL_STRIP_PASS;
        }

        // Parse the original URL
        $parse_url = parse_url($url);

        // Scheme and Host are always replaced
        if (isset($parts['scheme'])) {
            $parse_url['scheme'] = $parts['scheme'];
        }

        if (isset($parts['host'])) {
            $parse_url['host'] = $parts['host'];
        }

        // (If applicable) Replace the original URL with it's new parts
        if ($flags & HTTP_URL_REPLACE) {
            foreach ($keys as $key) {
                if (isset($parts[$key])) {
                    $parse_url[$key] = $parts[$key];
                }

            }
        } else {
            // Join the original URL path with the new path
            if (isset($parts['path']) && ($flags & HTTP_URL_JOIN_PATH)) {
                if (isset($parse_url['path'])) {
                    $parse_url['path'] = rtrim(str_replace(basename($parse_url['path']), '', $parse_url['path']), '/') . '/' . ltrim($parts['path'], '/');
                } else {
                    $parse_url['path'] = $parts['path'];
                }

            }

            // Join the original query string with the new query string
            if (isset($parts['query']) && ($flags & HTTP_URL_JOIN_QUERY)) {
                if (isset($parse_url['query'])) {
                    $parse_url['query'] .= '&' . $parts['query'];
                } else {
                    $parse_url['query'] = $parts['query'];
                }

            }
        }

        // Strips all the applicable sections of the URL
        // Note: Scheme and Host are never stripped
        foreach ($keys as $key) {
            if ($flags & (int) constant('HTTP_URL_STRIP_' . strtoupper($key))) {
                unset($parse_url[$key]);
            }

        }

        $new_url = $parse_url;

        return
            ((isset($parse_url['scheme'])) ? $parse_url['scheme'] . '://' : '') .
            ((isset($parse_url['user'])) ? $parse_url['user'] . ((isset($parse_url['pass'])) ? ':' . $parse_url['pass'] : '') . '@' : '') .
            ((isset($parse_url['host'])) ? $parse_url['host'] : '') .
            ((isset($parse_url['port'])) ? ':' . $parse_url['port'] : '') .
            ((isset($parse_url['path'])) ? $parse_url['path'] : '') .
            ((isset($parse_url['query'])) ? '?' . $parse_url['query'] : '') .
            ((isset($parse_url['fragment'])) ? '#' . $parse_url['fragment'] : '')
        ;
    }
}

/**
 * CURL GET 请求
 *
 * @param  string   $url
 * @param  array    $curl_opts
 * @return string
 */
function get($url, array $curl_opts = null)
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_URL            => $url,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_RETURNTRANSFER => 1,
    ]);

    if (null !== $curl_opts) {
        curl_setopt_array($ch, $curl_opts);
    }

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

/**
 * CURL POST 请求
 *
 * @param  string   $url
 * @param  array    $postdata
 * @param  array    $curl_opts
 * @return string
 */
function post($url, $postdata = '', array $curl_opts = null)
{
    $ch = curl_init();
    if ('' !== $postdata && is_array($postdata)) {
        $postdata = http_build_query($postdata);
    }

    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_URL            => $url,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_POST           => 1,
        CURLOPT_POSTFIELDS     => $postdata,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FAILONERROR    => 1,
    ]);

    if (null !== $curl_opts) {
        curl_setopt_array($ch, $curl_opts);
    }

    $result = curl_exec($ch);
    // 获取http状态码
    $intReturnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'result'    => $result,
        'http_code' => $intReturnCode,
    ];
}

/**
 * post md5 加密数据
 */
function post_sign($url, $postdata, array $curl_opts = null)
{
    if (!isset($postdata['key'])) {
        return false;
    }
    $key             = $postdata['key'];
    $sign            = sign($postdata, $key);
    $postdata['key'] = $sign;
    $ch              = curl_init();
    if ('' !== $postdata && is_array($postdata)) {
        $postdata = http_build_query($postdata);
    }

    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_URL            => $url,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_POST           => 1,
        CURLOPT_POSTFIELDS     => $postdata,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FAILONERROR    => 1,
    ]);

    if (null !== $curl_opts) {
        curl_setopt_array($ch, $curl_opts);
    }

    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function sign($data, $key)
{
    $unset = [
        'key',
        '_url',
    ];
    foreach ($unset as $k) {
        if (isset($data[$k])) {
            unset($data[$k]);
        }

    }
    ksort($data);
    $query = http_build_query($data);
    return md5($query . $key);
}

function create_uuid($prefix = "")
{
    return  md5(uniqid(mt_rand(), true));
}

function create_str($length = 1)
{
    // 密码字符集，可任意添加你需要的字符
    $chars = 'zUcQPaoXpZAYYfj8VYmqhbnm76UufYzTwoukpWizUtzaLJTFtmisywCgalhdSbVJCvyhJL4WF8STXc0RIsnrthT5chrtTouxbaCcoLwczTYkltuzthAzhuxwwsbcJ5SXq0sVywsRl77fiYrTTTmiph';
    $str   = '';
    for ($i = 0; $i < $length; $i++) {
        // 这里提供两种字符获取方式
        // 第一种是使用 substr 截取$chars中的任意一位字符；
        // 第二种是取字符数组 $chars 的任意元素
        // $password .= substr($chars, mt_rand(0, strlen($chars) – 1), 1);
        $str .= $chars[mt_rand(0, strlen($chars) - 1)];
    }

    return $str;
}

/**
 * 判断两个浮点数是否相等
 * @param  float  $num1
 * @param  float  $num2
 * @param  float  $diff
 * @return bool
 */
function float_eq($num1, $num2, $diff = 0.000001)
{
    return abs($num1 - $num2) < $diff;
}
function get_current_ip()
{
    if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
    } elseif (isset($_SERVER["HTTP_CLIENT_IP"])) {
        $ip = $_SERVER["HTTP_CLIENT_IP"];
    } elseif (isset($_SERVER["REMOTE_ADDR"])) {
        $ip = $_SERVER["REMOTE_ADDR"];
    } elseif (getenv("HTTP_X_FORWARDED_FOR")) {
        $ip = getenv("HTTP_X_FORWARDED_FOR");
    } elseif (getenv("HTTP_CLIENT_IP")) {
        $ip = getenv("HTTP_CLIENT_IP");
    } elseif (getenv("REMOTE_ADDR")) {
        $ip = getenv("REMOTE_ADDR");
    } else {
        $ip = Yii::$app->request->userIP;
    }

    return $ip;
}

function getSendApiToken($apiname) {
     $arr = explode('_' , $apiname);
     return md5($arr[0].date('Y-m-d:H'));
}

