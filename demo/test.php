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
    see('set name', $redis->set('name', 'alice'));         // 创建记录 ：1
    see('get name', $redis->get('name', 'default'));     // 读取记录 ：alice
    see('setnx not_exist', $redis->setnx('exist', 'yes'));// 数据不存在则创建 ：1
    see('del age', $redis->del('age'));                         // 删除记录 ：0
    see('get age', $redis->get('age', 20));              // 获取不存在的值 ：20
    see('set money', $redis->set('money', 100));          // 记录数值 ：100
    see('inc money', $redis->inc('money', 5));            // 数值自增 ：105
    see('dec money', $redis->dec('money', 5));            // 记录自减 ：100
    see('get money', $redis->get('money'));                     // 读取记录 ：100
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
    see('unlock foo', $redis->clearLock());                                 // 清理过期锁
} catch (Exception $e) {
    print_r($e->getMessage());
}

echo "\nfinished";