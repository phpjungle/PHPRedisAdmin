<?php
require_once __DIR__.'/PJRedis.php';
require_once __DIR__.'/includes/config.sample.inc.php';

// echo_json($config);  # config['db'];

function echo_json($mixed)
{
    is_array($mixed) or $mixed =[$mixed];
    $json = json_encode($mixed);
    die($json);
}

function fmt_json($json, $append_array = [])
{
    
    $ret = $append_array + ['redis_data'=>$json];
    $info = json_decode($json, true);
    if (!is_array($info)) {
        $info = json_decode($info, true);
        if (!is_array($info)) {
            $info = json_decode($info, true);
            if (!is_array($info)) {
                $ret = json_encode($ret, JSON_HEX_TAG);
                return $ret;
            }
        }
    }

    $ret['redis_data'] = $info;
    $ret = json_encode($ret, JSON_HEX_TAG);
    return $ret;
}

$gets = $_GET;

// echo_json($gets);
// 
if (!isset($gets['s'], $gets['d'], $gets['key'])) {
    echo_json(['msg'=>'Illegal Params']);
} else {
    $server_index = $gets['s'];
    $db_index = $gets['d'];
    $key = $gets['key'];

    $host = $config['servers'][$server_index]['host'];
    $port = $config['servers'][$server_index]['port'];

    $redis = new PJRedis($host, $port);
    $redis->select($db_index);
    $type = $redis->type($key);

    $type_mean = $redis->_REDIS_TYPE_MEANS[$type];
    $append = [];
    $append['redis_server'] = ['host'=>$host.':'.$port,'db'=>$db_index,'redis_type'=>$type_mean, 'redis_key'=>$key];

    $output = '';
    $json = '';
    switch ($type) {
        case PJRedis::REDIS_STRING:
            $json = $redis->get($key);
            break;
        case PJRedis::REDIS_HASH:
            $attr = trim($gets['hkey']);
            $append['redis_server']['hash_attr'] = $attr;
            $json = $redis->hash_value($key, $attr);
            break;
        case Redis::REDIS_NOT_FOUND:
        	$json = 'Redis::REDIS_NOT_FOUND';
        default:
            $json = 'Please waiting for this function! ';
            break;
    }
    $output = fmt_json($json, $append);

    echo $output;
}
