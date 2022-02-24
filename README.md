## Redis常用方法封装

## 安装
> composer require fuyelk/redis

## 特性
1. 自动为每个项目创建独立的键名前缀，解决了同一台服务器上Redis数据混淆的问题
2. 自动将非标量数据转换为序列化的字符串来存储，并在读取时自动解析
3. 相较于PHP原生库，增加了以下几个方法：
    - 简单高效的锁：`lock/unlock`
    - 通过集合删除数据：`delBySet`
    - 删除全部数据：`delAll`
    - 获取全部数据名：`keys`
    - 获取全部数据：`allData`

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
    
    // 删除全部数据
    $redis->delAll());
    
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
    ...
```