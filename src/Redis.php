<?php

namespace fuyelk\redis;

use Exception;

/**
 * Class Redis
 * @package fuyelk\redis
 * @method int|bool exists(string|string[] $key) 验证数据是否存在
 * @method int|bool lPush(string $key, string|mixed ...$value1) 从左侧加入列表
 * @method mixed|bool lPop(string $key) 从左侧弹出数据
 * @method int|bool rPush(string $key, string|mixed ...$value1) 从右侧加入列表
 * @method mixed|bool rPop(string $key) 从右侧弹出数据
 * @method int|bool lLen(string $key) 查询列表长度
 * @method array lRange(string $key, int $start, int $end) 获取列表指定部分数据
 * @method int|bool sAdd(string $key, string|mixed ...$value1) 将一个或多个成员元素加入到集合中
 * @method int sCard(string $key) 返回集合中元素的数量
 * @method int sIsMember(string $key, string|mixed $value) 判断成员元素是否是集合的成员
 * @method array sMembers(string $key) 返回集合中的所有的成员
 * @method int sRem(string $key, string|mixed ...$member1) 移除集合中的一个或多个成员元素
 * @method int|bool hSet(string $key, string $hashKey, string $value) 将哈希表 key 中的字段 field 的值设为 value
 * @method bool hSetNx(string $key, string $hashKey, string $value) 只有在字段 field 不存在时，设置哈希表字段的值
 * @method bool hMSet(string $key, array $hashKeys) 只同时将多个 field->value 键值对设置到哈希表 key 中
 * @method string|false hGet(string $key, string $hashKey) 获取存储在哈希表 key 中指定字段的值
 * @method array hMGet(string $key, array $hashKey) 获取哈希表 key 中所有给定的字段的值
 * @method int|false hDel(string $key, string $hashKey1, string ...$otherHashKeys) 从哈希表 key 中删除一个或多个字段
 * @method int|false hLen(string $key) 获取哈希表 key 中字段的数量
 * @method array hKeys(string $key) 获取所有哈希表 key 中的字段
 * @method array hVals(string $key) 获取哈希表 key 中所有值
 * @method array hGetAll(string $key) 获取在哈希表 key 中所有字段和值
 * @method bool hExists(string $key, string $hashKey) 获查看哈希表 key 中指定的字段是否存在
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
     * @param bool|string $key [配置名]
     * @return array|mixed
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public static function getRedisConf($key = false)
    {
        // 验证配置文件的有效性
        if (is_file(self::$CONFIG_FILE)) {
            $data = file_get_contents(self::$CONFIG_FILE);
            if (!empty($data) and $config = json_decode($data, true) and md5(__DIR__) == ($config['prefix_validate'] ?? '')) {
                return $key ? ($config[$key] ?? null) : $config;
            }
        }
        $config = self::setConfig();
        return $key ? ($config[$key] ?? null) : $config;
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
            'db' => 0,              // 数据库标识
            'timeout' => 0,         // 连接超时时长
            'expire' => 0,          // 默认数据有效期（秒）
            'persistent' => false,  // 持久化
            'prefix' => substr(md5(microtime() . mt_rand(1000, 9999)), 0, 6) . ':', // 键前缀
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
     * @param array $options ['host','port','password','db','timeout','expire','persistent','prefix']
     * @throws RedisException
     */
    public function __construct($options = [])
    {
        if (!extension_loaded('redis')) {
            throw new RedisException('不支持Redis扩展');
        }

        $this->options = self::getRedisConf();
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        try {
            $this->handler = new \Redis;
            if ($this->options['persistent']) {
                $this->handler->pconnect($this->options['host'], $this->options['port'], $this->options['timeout'], 'persistent_id_' . $this->options['db']);
            } else {
                $this->handler->connect($this->options['host'], $this->options['port'], $this->options['timeout']);
            }

            if ('' != $this->options['password']) {
                $this->handler->auth($this->options['password']);
            }

            if (0 != $this->options['db']) {
                $this->handler->select($this->options['db']);
            }

            // 测试Redis连接
            $this->handler->get('redis:test');
        } catch (Exception $e) {
            throw new RedisException('Redis 连接失败');
        }

        // 清理锁
        if (!$this->exists(md5('lock:lock_cleared'))) {
            $this->set(md5('lock:lock_cleared'), 'This is the sign that fuyelk/redis lock has been cleared', 600); // 定时10分钟
            $lockList = $this->sMembers('lock:all_locks') ?: [];
            foreach ($lockList as $item) {
                // 清理已过期超过1分钟的锁
                if ($expireTime = $this->get($item) and is_numeric($expireTime) and $expireTime < strtotime('-1 minute')) {
                    $this->del($item);
                    $this->sRem('lock:all_locks', $item);
                }
            }
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
     * @return bool|float|int|string
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    protected function encode($value)
    {
        return is_scalar($value) ? $value : 'redis_serialize:' . serialize($value);
    }

    /**
     * 解析数据
     * @param mixed $value redis返回数据
     * @param bool|mixed $default [默认值]
     * @return bool|mixed
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    protected function decode($value, $default = false)
    {
        if (is_null($value) || false === $value) {
            return $default;
        }

        try {
            $result = 0 === strpos($value, 'redis_serialize:') ? unserialize(substr($value, 16)) : $value;
        } catch (Exception $e) {
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
     * 通过集合删除数据
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
     * 删除全部数据
     * @param bool $ignorePrefix 忽略前缀
     * @return bool
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function delAll($ignorePrefix = false)
    {
        $dataList = $this->keys($ignorePrefix);
        foreach ($dataList as $item) {
            $this->handler->del($item);
        }
        return true;
    }

    /**
     * 获取全部数据名
     * @param bool $ignorePrefix 忽略前缀
     * @return array
     */
    public function keys($ignorePrefix = false)
    {
        return $this->handler->keys($ignorePrefix ? '*' : $this->getKeyName('*'));
    }

    /**
     * 获取全部数据
     * @param bool $ignorePrefix 忽略前缀
     * @return array
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function allData($ignorePrefix = false)
    {
        $keys = $this->keys($ignorePrefix);
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
        $name = 'lock:' . $name;
        $locked = $this->setnx($name, time() + $expire);

        // 获取锁成功
        if ($locked) {
            $this->sAdd('lock:all_locks', $name);
            return true;
        }

        // 锁已过期则删除锁，重新获取
        if (is_numeric($this->get($name)) && $this->get($name) < time()) {
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
        $this->del('lock:' . $name);
        return true;
    }

    public function __call($method, $args)
    {
        if (key_exists(0, $args) && is_scalar($args[0])) {
            $args[0] = $this->getKeyName($args[0]);
        }
        return call_user_func_array([$this->handler, $method], $args);
    }
}