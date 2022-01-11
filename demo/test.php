<?php

require_once "../src/Redis.php";
require_once "../src/RedisException.php";

function see($name = '', $data = null)
{
    if (empty($name)) {
        echo "\n";
        return;
    }

    echo $name . ':';
    print_r($data);
    echo "\n";
}

try {
    $redis = new \fuyelk\redis\Redis();

    // 字符串
    see(' -- :字符串', ' -- ');
    see('set name', $redis->set('name', 'zhangsan'));     // 创建记录 ：1
    see('get name', $redis->get('name', 'default'));     // 读取记录 ：zhangsan
    see('setnx not_exist', $redis->setnx('exist', 'yes'));// 数据不存在则创建 ：1
    see('del age', $redis->del('age'));                         // 删除记录 ：0
    see('get age', $redis->get('age', 20));              // 获取不存在的值 ：20
    see('set money', $redis->set('money', 100));          // 记录数值 ：100
    see('inc money', $redis->inc('money', 5));            // 数值自增 ：105
    see('dec money', $redis->dec('money', 5));            // 记录自减 ：100
    see('get money', $redis->get('money'));                     // 读取记录 ：100
    see();

    // 队列
    see(' -- :列表', ' -- ');
    $redis->del('hobby');
    see('llen hobby', $redis->llen('hobby'));                        // 列表长度 ：0
    see('lpush hobby', $redis->lpush('hobby', 'Basketball'));  // 左侧加入：1
    see('llen hobby', $redis->llen('hobby'));                        // 列表长度：1
    see('lpop hobby', $redis->lpop('hobby'));                        // 左侧弹出：Basketball
    see('llen hobby', $redis->llen('hobby'));                        // 列表长度：0
    see('rpush hobby', $redis->rpush('hobby', 'football'));    // 右侧加入：1
    see('rpop hobby', $redis->rpop('hobby'));                        // 右侧弹出：football
    see('llen hobby', $redis->llen('hobby'));                        // 列表长度：0
    see();

    see(' -- :列表数据选择', ' -- ');
    $redis->del('hobby');
    see('rpush hobby', $redis->rpush('hobby', 'Basketball'));   // 左侧加入：1
    see('rpush hobby', $redis->rpush('hobby', 'football'));     // 左侧加入：2
    see('rpush hobby', $redis->rpush('hobby', 'golf'));         // 左侧加入：3
    see('rpush hobby', $redis->rpush('hobby', 'ping-pong'));    // 左侧加入：4
    see('rpush hobby', $redis->rpush('hobby', 'coding'));       // 左侧加入：5
    see('rrange hobby', $redis->lrange('hobby', 2, 100));   // 获取部分数据['golf','ping-pong','coding']
    see();


    see(' -- :数据管理', ' -- ');
    see('keys', $redis->keys());                                            // 符合当前前缀全部键名
    see('keys all', $redis->keys(true));                                // Redis缓存中的全部键名
    see('allData', $redis->allData());                                      // 符合当前前缀的所有数据
    see('allData true', $redis->allData(true));                         // redis缓存中的所有数据
    see('lock foo', $redis->lock('foo', 10));                  // 锁foo 10秒 ：1
    see('lock foo', $redis->lock('foo', 10));                  // 锁foo 10秒 ：0
    see('unlock foo', $redis->unlock('foo'));                        // 解锁foo ：1
    see('lock foo', $redis->lock('foo', 10));                  //  锁foo 10秒 ：1
} catch (\Exception $e) {
    print_r($e->getMessage());
}

echo "\nfinished";