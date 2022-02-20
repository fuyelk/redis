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

    // 通过集合删除缓存
    $redis->delBySet('users'));
    
    // 数值自增
    $redis->inc('money', 5));
    
    // 记录自减
    $redis->dec('money', 5));
    
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
    
    // 清理过期锁
    $redis->clearLock());
    ...
```