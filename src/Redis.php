<?php

namespace fuyelk\redis;

/**
 * Class Redis
 * @package fuyelk\redis
 * @method int|bool sAdd(string $key, string|mixed ...$value1) 将一个或多个成员元素加入到集合中
 * @method int sCard(string $key) 返回集合中元素的数量
 * @method int sIsMember(string $key, string|mixed $value) 判断成员元素是否是集合的成员
 * @method int sMembers(string $key) 返回集合中的所有的成员
 * @method int sRem(string $key, string|mixed ...$member1) 移除集合中的一个或多个成员元素
 * @author fuyelk <fuyelk@fuyelk.com>
 */
class Redis
{
    /**
     * @var \Redis|null
     */
    protected $handler = null;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var string
     */
    private static $CONFIG_FILE = __DIR__ . '/config.json';

    /**
     * 获取redis配置
     * @return array|mixed
     */
    protected function getRedisConf()
    {
        if (is_file(self::$CONFIG_FILE)) {
            $data = file_get_contents(self::$CONFIG_FILE);
            if (!empty($data) && $config = json_decode($data, true)) {
                if (md5(__DIR__) == ($config['prefix_validate'] ?? '')) {
                    return $config;
                }
            }
        }
        return self::setConfig();
    }

    /**
     * 创建配置文件
     * @return array
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    protected static function setConfig()
    {
        $config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '',       // 密码
            'select' => 0,          // 数据库标识
            'timeout' => 0,         // 连接超时时长
            'expire' => 0,          // 默认数据有效期（秒）
            'persistent' => false,  // 持久化
            'prefix' => substr(md5(microtime() . mt_rand(1000, 9999)), 0, 6) . '_', // 键前缀
            'prefix_validate' => md5(__DIR__),// 通过项目路径识别是否需要重置配置
        ];

        if (!is_dir(dirname(self::$CONFIG_FILE))) {
            mkdir(dirname(self::$CONFIG_FILE), 0755, true);
        }

        $fp = fopen(self::$CONFIG_FILE, 'w');
        fwrite($fp, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fclose($fp);
        return $config;
    }

    /**
     * Redis constructor.
     * @param array $options ['host','port','password','select','timeout','expire','persistent','prefix']
     * @throws RedisException
     */
    public function __construct($options = [])
    {
        if (!extension_loaded('redis')) {
            throw new RedisException('not support: redis');
        }
        $this->options = $this->getRedisConf();
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        try {
            $this->handler = new \Redis;
            if ($this->options['persistent']) {
                $this->handler->pconnect($this->options['host'], $this->options['port'], $this->options['timeout'], 'persistent_id_' . $this->options['select']);
            } else {
                $this->handler->connect($this->options['host'], $this->options['port'], $this->options['timeout']);
            }

            if ('' != $this->options['password']) {
                $this->handler->auth($this->options['password']);
            }

            if (0 != $this->options['select']) {
                $this->handler->select($this->options['select']);
            }
        } catch (\Exception $e) {
            throw new RedisException('Redis 连接失败');
        }
    }

    /**
     * 获取数据名
     * @access public
     * @param string $name 数据名
     * @return string
     */
    protected function getKeyName($name)
    {
        return $this->options['prefix'] . $name;
    }

    /**
     * 转为可存数据
     * @param mixed $value 待存储的数据
     */
    protected function encode($value)
    {
        return is_scalar($value) ? $value : 'redis_serialize:' . serialize($value);
    }

    /**
     * 解析数据
     * @param mixed $value redis返回数据
     * @param string $default 默认值
     */
    protected function decode($value, $default = false)
    {
        if (is_null($value) || false === $value) {
            return $default;
        }

        try {
            $result = 0 === strpos($value, 'redis_serialize:') ? unserialize(substr($value, 16)) : $value;
        } catch (\Exception $e) {
            return $default;
        }

        return $result;
    }

    /**
     * 写入数据
     * @param string $name 数据名
     * @param mixed $value 数据
     * @param integer $expire 有效时间（秒）
     * @return boolean
     */
    public function set($name, $value, $expire = null)
    {
        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }
        if ($expire) {
            $result = $this->handler->setex($this->getKeyName($name), $expire, $this->encode($value));
        } else {
            $result = $this->handler->set($this->getKeyName($name), $this->encode($value));
        }
        return $result;
    }

    /**
     * 读取数据
     * @param string $name 数据名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get($name, $default = false)
    {
        $value = $this->handler->get($this->getKeyName($name));
        return $this->decode($value, $default);
    }

    /**
     * 数据自增
     * @param string $name 数据名
     * @param int $value 增长值
     * @return false|int
     */
    public function inc($name, $value = 1)
    {
        return $this->handler->incrby($this->getKeyName($name), $value);
    }

    /**
     * 数据自减
     * @param string $name 数据名
     * @param int $value 减少值
     * @return false|int
     */
    public function dec($name, $value = 1)
    {
        return $this->handler->decrby($this->getKeyName($name), $value);
    }

    /**
     * 从左侧加入列表
     * @param string $name 数据名
     * @param mixed $value 数据
     * @return bool|int
     */
    public function lpush($name, $value)
    {
        return $this->handler->lPush($this->getKeyName($name), $this->encode($value));
    }

    /**
     * 从左侧弹出数据
     * @param string $name 数据名
     * @param mixed $value 数据
     * @return mixed
     */
    public function lpop($name)
    {
        $value = $this->handler->lPop($this->getKeyName($name));
        return $this->decode($value);
    }

    /**
     * 从右侧加入列表
     * @param string $name 数据名
     * @param mixed $value 数据
     * @return bool|int
     */
    public function rpush($name, $value)
    {
        return $this->handler->rPush($this->getKeyName($name), $this->encode($value));
    }

    /**
     * 从右侧弹出数据
     * @param string $name 数据名
     * @param mixed $value 数据
     * @return mixed
     */
    public function rpop($name)
    {
        $value = $this->handler->rPop($this->getKeyName($name));
        return $this->decode($value);
    }

    /**
     * 查询列表长度
     * @param string $name 数据名
     * @return bool|int
     */
    public function llen($name)
    {
        return $this->handler->llen($this->getKeyName($name));
    }

    /**
     * @return array
     */
    /**
     * 获取列表指定部分数据
     * @param string $name 数据名
     * @param int $start
     * @param int $end
     * @return array
     */
    public function lrange($name, $start, $end)
    {
        $list = $this->handler->lRange($this->getKeyName($name), $start, $end);
        $result = [];
        foreach ($list as $item) {
            $result[] = $this->decode($item);
        }
        return $result;
    }

    /**
     * 数据不存在则创建数据
     * @param string $name
     * @param string $value
     * @return bool
     */
    public function setnx($name, $value)
    {
        return $this->handler->setnx($this->getKeyName($name), $this->encode($value));
    }

    /**
     * 删除数据
     * @param string $name 数据名
     * @return int
     */
    public function del($name)
    {
        return $this->handler->del($this->getKeyName($name));
    }

    /**
     * 通过集合删除缓存
     * @param string $setName 集合名
     * @return int 完成数量
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function delBySet($setName = '')
    {
        $count = $this->sCard($setName) ?: 0;
        $list = $this->sMembers($setName) ?: [];
        foreach ($list as $item) {
            $this->del($item);
            $this->sRem($setName, $item);
        }
        return $count;
    }

    /**
     * 获取全部键
     * @param bool $all 是否查询全部键（不限制前缀前缀）
     * @return array
     */
    public function keys($all = false)
    {
        return $this->handler->keys($all ? '*' : $this->getKeyName('*'));
    }

    /**
     * 获取全部数据
     * @param bool $all 是否查询全部键（不限制前缀前缀）
     * @return array
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function allData($all = false)
    {
        $keys = $this->keys($all);
        $ret = [];
        foreach ($keys as $key) {
            $ret[$key] = $this->handler->get($key);
        }
        return $ret;
    }

    /**
     * 获取锁
     * @param string $name 锁标识
     * @param int $expire 锁过期时间
     * @return bool
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function lock($name, $expire = 5)
    {
        $locked = $this->setnx($name, time() + $expire);

        // 获取锁成功
        if ($locked) {
            $this->sAdd('lock_list', $name);
            return true;
        }

        // 锁已过期则删除锁，重新获取
        if (time() > $this->get($name)) {
            $this->del($name);
            return $this->setnx($name, time() + $expire);
        }

        return false;
    }

    /**
     * 释放锁
     * @param string $name 锁标识
     * @return bool
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function unlock($name)
    {
        $this->del($name);
        return true;
    }

    /**
     * 清理过期锁
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function clearLock()
    {
        $lockList = $this->sMembers('lock_list') ?: [];
        foreach ($lockList as $item) {
            if ($expireTime = $this->get($item) and is_numeric($expireTime) and $expireTime < strtotime('-1 minute')) {
                $this->del($item);
                $this->sRem('lock_list', $item);
            }
        }
    }

    public function __call($method, $args)
    {
        if (key_exists(0, $args) && is_scalar($args[0])) {
            $args[0] = $this->getKeyName($args[0]);
        }
        return call_user_func_array([$this->handler, $method], $args);
    }
}