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
 * @method bool sIsMember(string $key, string|mixed $value) 判断成员元素是否是集合的成员
 * @method array sMembers(string $key) 返回集合中的所有的成员
 * @method int sRem(string $key, string|mixed ...$member1) 移除集合中的一个或多个成员元素
 * @method int zAdd(string $key, float|string|mixed $score1, string|float|mixed $value1 = null, float|string|mixed $score2 = null, string|float|mixed $value2 = null, float|string|mixed $scoreN = null, string|float|mixed $valueN = null) 向有序集合添加一个或多个成员，或者更新已存在成员的分数
 * @method int zRem(string $key, string|mixed $member1, string|mixed ...$otherMembers) 移除有序集合中的一个或多个成员
 * @method int zCount(string $key, string $start, string $end) 计算在有序集合中指定区间分数的成员数量
 * @method int zCard(string $key) 获取有序集合的成员数量
 * @method float|bool zScore(string $key, string|mixed $member) 获取有序集中成员的分数值
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
 * @mixin \Redis
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
     * @param string|null $key [配置名]
     * @return array|mixed
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public static function getRedisConf(string $key = null)
    {
        // 验证配置文件的有效性
        if (is_file(self::$CONFIG_FILE)) {
            $data = file_get_contents(self::$CONFIG_FILE);
            if (!empty($data) and $config = json_decode($data, true)) {
                if (md5(__DIR__) != ($config['prefix_validate'] ?? '')) {
                    $config = self::setConfig($config);
                }
                return $key ? ($config[$key] ?? null) : $config;
            }
        }
        $config = self::setConfig();
        return $key ? ($config[$key] ?? null) : $config;
    }

    /**
     * 创建配置文件
     * @param array $default
     * @return array
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    protected static function setConfig(array $default = []): array
    {
        $config = [
            'host' => $default['host'] ?? '127.0.0.1',
            'port' => $default['port'] ?? 6379,
            'password' => $default['password'] ?? '',       // 密码
            'db' => $default['db'] ?? 0,                    // 数据库标识
            'timeout' => $default['timeout'] ?? 0,          // 连接超时时长
            'expire' => $default['expire'] ?? 0,            // 默认数据有效期（秒）
            'persistent' => $default['persistent'] ?? false,// 持久化
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
     * 建立连接
     * @return void
     * @throws \RedisException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    private function connect()
    {
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
    }

    /**
     * Redis constructor.
     * @param array $options ['host','port','password','db','timeout','expire','persistent','prefix']
     * @throws RedisException
     */
    public function __construct(array $options = [])
    {
        if (!extension_loaded('redis')) {
            throw new RedisException('不支持Redis扩展');
        }

        $this->options = self::getRedisConf();
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        try {
            $this->connect();
        } catch (Exception $e) {
            throw new RedisException('Redis 连接失败');
        }
    }

    /**
     * 获取数据名
     * @access public
     * @param string $name 数据名
     * @return string
     */
    protected function getKeyName(string $name): string
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
        if (is_bool($value) || !is_scalar($value)) {
            $value = 'redis_serialize:' . serialize($value);
        }
        return $value;
    }

    /**
     * 解析数据
     * @param mixed $value redis返回数据
     * @param mixed|null $default [默认值]
     * @return bool|mixed
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    protected function decode($value, $default = null)
    {
        if (is_null($value) && !is_null($default)) {
            return $default;
        }
        if (0 === strpos($value, 'redis_serialize:')) {
            $value = unserialize(substr($value, 16));
        }
        return $value;
    }

    /**
     * 写入数据
     * @param string $name 数据名
     * @param mixed $value 数据
     * @param integer|null $expire 有效时间（秒）
     * @return boolean
     * @throws RedisException
     */
    public function set(string $name, $value, int $expire = null): bool
    {
        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }
        if ($expire) {
            $result = $this->action('setex', [$this->getKeyName($name), $expire, $this->encode($value)]);
        } else {
            $result = $this->action('set', [$this->getKeyName($name), $this->encode($value)]);
        }
        return $result;
    }

    /**
     * 读取数据
     * @param string $name 数据名
     * @param mixed|null $default 默认值
     * @return mixed
     * @throws RedisException
     */
    public function get(string $name, $default = null)
    {
        $value = $this->action('get', [$this->getKeyName($name)]);
        return $this->decode($value, $default);
    }

    /**
     * 数据自增
     * @param string $name 数据名
     * @param int $value 增长值
     * @return false|int
     * @throws RedisException
     */
    public function inc(string $name, int $value = 1)
    {
        return $this->action('incrby', [$this->getKeyName($name), $value]);
    }

    /**
     * 数据自减
     * @param string $name 数据名
     * @param int $value 减少值
     * @return false|int
     * @throws RedisException
     */
    public function dec(string $name, int $value = 1)
    {
        return $this->action('decrby', [$this->getKeyName($name), $value]);
    }

    /**
     * 数据不存在则创建数据
     * @param string $name
     * @param string $value
     * @return bool
     * @throws RedisException
     */
    public function setnx(string $name, string $value): bool
    {
        return $this->action('setnx', [$this->getKeyName($name), $this->encode($value)]);
    }

    /**
     * 删除数据
     * @param string $name 数据名
     * @return int
     * @throws RedisException
     */
    public function del(string $name): int
    {
        return $this->action('del', [$this->getKeyName($name)]);
    }

    /**
     * 向有序集合中追加数据（分数为成员加入时的序号，第一个为0）
     * @param string $key 键名
     * @param string|float $value 值
     * @return void
     * @throws RedisException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function zAppend(string $key, $value)
    {
        $key = $this->getKeyName($key);
        $this->action('zAdd', [$key, $this->action('zCard', [$key]), $value]);
    }

    /**
     * 返回有序集合的全部成员（分数为成员加入时的序号，第一个为0）
     * @param string $key 键名
     * @return array
     * @throws RedisException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function zMembers(string $key): array
    {
        $key = $this->getKeyName($key);
        return $this->action('zRangeByScore', [$key, 0, $this->action('zCard', [$key])]);
    }

    /**
     * 订阅给定的一个或多个频道
     * @param string[] $channels 频道名
     * @param callable $callback
     * 该回调有3个参数：Redis实例，频道名，消息内容
     *
     * @return array|false
     * @throws RedisException
     */
    public function subscribe(array $channels, callable $callback)
    {
        $channels = array_map(function ($channel) {
            return $this->getKeyName($channel);
        }, $channels);
        return $this->action('subscribe', [$channels, $callback]);
    }

    /**
     * 订阅一个或多个符合给定模式的频道
     * @param array $patterns 模式 以 * 作为匹配符，比如 user* 匹配所有以 user 开头的频道( user.register，user.login，user.logout 等)
     * @param callable $callback
     * 该回调有3个参数：Redis实例，模式，频道名，消息内容
     *
     * @return mixed|null
     * @throws RedisException
     */
    public function psubscribe(array $patterns, callable $callback)
    {
        $patterns = array_map(function ($pattern) {
            return $this->getKeyName($pattern);
        }, $patterns);
        return $this->action('psubscribe', [$patterns, $callback]);
    }

    /**
     * 向频道发消息
     * @param string $channel 频道
     * @param string $message 消息内容
     * @return mixed|null
     * @throws RedisException
     */
    public function publish(string $channel, string $message)
    {
        return $this->action('publish', [$this->getKeyName($channel), $message]);
    }

    /**
     * 通过集合删除数据
     * @param string $set_name 集合名
     * @return int 完成数量
     * @throws RedisException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function delBySet(string $set_name = ''): int
    {
        $count = $this->sCard($set_name) ?: 0;
        $list = $this->sMembers($set_name) ?: [];
        foreach ($list as $item) {
            $this->del($item);
            $this->sRem($set_name, $item);
        }
        return $count;
    }

    /**
     * 删除全部数据
     * @param bool $ignore_prefix 忽略前缀
     * @return bool
     * @throws RedisException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function delAll(bool $ignore_prefix = false): bool
    {
        $dataList = $this->keys($ignore_prefix);
        foreach ($dataList as $item) {
            $this->action('del', [$item]);
        }
        return true;
    }

    /**
     * 获取全部数据名
     * @param bool $ignorePrefix 忽略前缀
     * @return array
     * @throws RedisException
     */
    public function keys(bool $ignorePrefix = false): array
    {
        return $this->action('keys', [$ignorePrefix ? '*' : $this->getKeyName('*')]);
    }

    /**
     * 获取全部数据
     * @param bool $ignorePrefix 忽略前缀
     * @return array
     * @throws RedisException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function allData(bool $ignorePrefix = false): array
    {
        $keys = $this->keys($ignorePrefix);
        $ret = [];
        foreach ($keys as $key) {
            $ret[$key] = $this->action('get', [$key]);
        }
        return $ret;
    }

    /**
     * 上锁
     * @param string $name 锁标识
     * @param int $expire 锁过期时间，单位：秒
     * @return bool
     * @throws RedisException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function lock(string $name, int $expire = 5): bool
    {
        $redisKey = 'lock:' . $name;
        $locked = $this->setnx($redisKey, time() + $expire);

        // 上锁成功
        if ($locked) {
            $this->expire($redisKey, $expire);
            return true;
        }

        // 上锁失败，检查旧锁是否过期
        $expireTime = $this->get($redisKey);
        if (is_numeric($expireTime) && $expireTime < time()) {
            $this->del($redisKey);

            $res = $this->setnx($redisKey, time() + $expire);
            $this->expire($redisKey, $expire);
            return $res;
        }

        return false;
    }

    /**
     * 解锁
     * @param string $name 锁标识
     * @return bool
     * @throws RedisException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function unlock(string $name): bool
    {
        $this->del('lock:' . $name);
        return true;
    }

    /**
     * @throws RedisException
     */
    public function __call($method, $args)
    {
        if (key_exists(0, $args) && is_scalar($args[0])) {
            $args[0] = $this->getKeyName($args[0]);
        }
        return $this->action($method, $args);
    }

    /**
     * 执行Redis方法，自动断线重连
     * @param string $method 方法名
     * @param array $args 参数
     * @return mixed
     * @throws RedisException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function action(string $method, array $args)
    {
        try {
            $res = $this->handler->$method(...$args);
        } catch (\RedisException $e) {
            // 连接异常，重新连接并重新执行任务
            if (in_array($e->getMessage(), ['Redis server went away', 'Connection lost'])) {
                $count = 0;

                // 断线重连
                while (true) {
                    try {
                        $this->connect();
                    } catch (Exception $e) {
                        // 记录连接失败次数，超过1000次，等待1秒
                        if (++$count >= 1000) {
                            $count = 0;
                            sleep(1);
                        }
                        continue;
                    }
                    // 连接无异常，则跳出
                    break;
                }

                // 重新执行任务
                return $this->action($method, $args);
            }

            // 其他异常直接抛出
            throw new RedisException($e->getMessage());
        }
        return $res;
    }
}