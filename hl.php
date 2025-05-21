<?php
date_default_timezone_set('Asia/Shanghai');
header('Content-Type: application/json');

// Redis 配置
$redisHost = '127.0.0.1';
$redisPort = 6379;
$redisKeyPrefix = 'almanac_';
$ymd = date('Ymd');
$redisKey = $redisKeyPrefix . $ymd;

try {
    // 连接 Redis
    $redis = new Redis();
    $redis->connect($redisHost, $redisPort);

    // 如果有缓存，直接返回并标记为缓存数据
    if ($redis->exists($redisKey)) {
        $cached = json_decode($redis->get($redisKey), true);
        $cached['r'] = '1';
        echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
} catch (Exception $e) {
    error_log('Redis连接失败: ' . $e->getMessage());
}

// 无缓存，请求接口
$appid = '';
$app_security = '';
$timestamp = round(microtime(true) * 1000);
$sign = md5("{$appid}&{$timestamp}&{$app_security}");

$postData = http_build_query([
    'appid'     => $appid,
    'timestamp' => $timestamp,
    'sign'      => $sign,
    'ymd'       => $ymd
]);

$ch = curl_init("https://api.shumaidata.com/v10/almanac/calendar");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $data = json_decode($response, true);
    $data['r'] = '0';

    // 写入缓存 24 小时
    try {
        if (isset($redis)) {
            $redis->setex($redisKey, 86400, json_encode($data, JSON_UNESCAPED_UNICODE));
        }
    } catch (Exception $e) {
        error_log('Redis写入失败: ' . $e->getMessage());
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'code' => 500,
        'msg' => '黄历接口请求失败',
        'r' => 'error',
        'http_code' => $http_code,
        'debug' => [
            'appid'     => $appid,
            'timestamp' => $timestamp,
            'sign'      => $sign,
            'ymd'       => $ymd
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
