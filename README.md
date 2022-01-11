## Redis常用方法封装

## 安装
> composer require fuyelk/redis

### 方法
```
    $redis = new \fuyelk\redis\Redis();

    // 创建记录
    $redis->set('name', 'zhangsan'));
    
    // 读取记录
    $redis->get('name', 'default'));
    
    // 数据不存在则创建
    $redis->setnx('exist', 'yes'));
    
    // 删除记录
    $redis->del('age'));
    
    // 数值自增
    $redis->inc('money', 5));
    
    // 记录自减
    $redis->dec('money', 5));
    
    // 列表长度
    $redis->llen('hobby'));
    
    // 左侧加入
    $redis->lpush('hobby', 'Basketball'));
    
    // 左侧弹出
    $redis->lpop('hobby'));
    
    // 右侧加入
    $redis->rpush('hobby', 'football'));
    
    // 右侧弹出
    $redis->rpop('hobby'));
    
    // 获取部分数据 ['golf','ping-pong','coding']
    $redis->lrange('hobby', 2, 100));
    
    // 符合当前前缀全部键名
    $redis->->keys());
    
    // Redis缓存中的全部键名
    $redis->keys(true));
    
    // 符合当前前缀的所有数据
    $redis->allData());
    
    // redis缓存中的所有数据
    $redis->allData(true));
    
    // 锁foo 10秒
    $redis->lock('foo', 10));
    
    // 解锁foo
    $redis->unlock('foo'));

    ...
```