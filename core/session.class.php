<?php

namespace JT;

class session
{
    protected static $instance;
    protected $session_id;

    protected $config = array(
        'cookie_name' => 'remixsid',                    # PHPSESSID or your version
        'cookie_domain' => null,                        # example.com or .example.com default $_SERVER['HTTP_HOST']
        'cookie_path' => '/',                           # Cookie path
        'cookie_secure' => null,                        # Cookie secure
        'cookie_http_only' => true,                     # Cookie http only
        'cookie_session_id_key_len' => 40,              # Cookie session id key len
        'memcache' => false,                            # false or array('host'=>'', 'port'=>'');
    );

    protected function __clone()
    {
    }

    public static function start($config = array())
    {
        if (!isset(self::$instance)) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    private function __construct($config = array())
    {
        $this->config = array_merge($this->config, $config);

        if ($this->config['cookie_domain'] == null)
            $this->config['cookie_domain'] = $_SERVER['HTTP_HOST'];

        if ($this->config['cookie_secure'] === null)
            $this->config['cookie_secure'] = config::$https;

        if ($this->config['memcache']) {
            if (is_array($this->config['memcache']) && !empty($this->config['memcache']['host']) && !empty($this->config['memcache']['port'])) {
                $host = $this->config['memcache']['host'];
                $port = $this->config['memcache']['port'];
            } else {
                $host = 'localhost';
                $port = 11211;
            }

            session_module_name('memcache');
            session_save_path("tcp://{$host}:{$port}?persistent=1&amp;weight=1&amp;timeout=1&amp;retry_interval=15");
        }


        if (isset($_COOKIE[$this->config['cookie_name']]) && preg_match('/^[a-zA-Z0-9]{' . $this->config['cookie_session_id_key_len'] . '}$/', $_COOKIE[$this->config['cookie_name']])) {
            $this->session_id = $_COOKIE[$this->config['cookie_name']];
        } else {
            $this->session_id = utils::get_random_code($this->config['cookie_session_id_key_len']);
            session_id($this->session_id);
        }

        session_name($this->config['cookie_name']);
        session_set_cookie_params(0, $this->config['cookie_path'], $this->config['cookie_domain'], $this->config['cookie_secure'], $this->config['cookie_http_only']);
        session_start();
    }

    public static function get($key = '')
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : false;
    }

    public static function set($key = '', $value = '')
    {
        $_SESSION[$key] = $value;
    }

    public static function getInstance()
    {
        return self::$instance;
    }

    public static function session_id()
    {
        return self::$instance->session_id;
    }

    public static function destroy()
    {
        session_destroy();
    }

}


