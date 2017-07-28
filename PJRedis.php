<?php
// check redis extension
extension_loaded ( 'redis' ) or die ( 'PHP Extension redis is not loaded!' );

/**
 * Methods of PHP-Redis
 *
 * @author PHPJungle
 * @since 2015/07/23 周四
 * @see https://github.com/phpredis/phpredis#classes-and-methods
 * @abstract Bug:ZREVRANGEBYSCORE limit(offset,count) 中count识别不了float的bug(eg:limit 0,float(15)) 分页失效，会获取所有记录
 * @abstract Bug:部分方法不能正常执行:pexpire
 */
class PJRedis {
	const REDIS_STRING = Redis::REDIS_STRING;
	const REDIS_SET = Redis::REDIS_SET;
	const REDIS_LIST = Redis::REDIS_LIST;
	const REDIS_ZSET = Redis::REDIS_ZSET;
	const REDIS_HASH = Redis::REDIS_HASH;
	const REDIS_NOT_FOUND = Redis::REDIS_NOT_FOUND;
	const SORT_ASC = 'asc';
	const SORT_DESC = 'desc';

	# 类型映射
	var $_REDIS_TYPE_MEANS = array(
		self::REDIS_STRING=>'REDIS_STRING',
		self::REDIS_SET=>'REDIS_SET',
		self::REDIS_LIST=>'REDIS_LIST',
		self::REDIS_ZSET=>'REDIS_ZSET',
		self::REDIS_HASH=>'REDIS_HASH',
		self::REDIS_NOT_FOUND=>'REDIS_NOT_FOUND'
	);
	
	const OSETS_RANGE_LEFT_INF = '-inf'; # 负无穷大
	const OSETS_RANGE_RIGHT_INF = '+inf';# 正无穷大
	
	var $redis = null;
	
	var $host, $port;
	var $ar_redis_info = null; # informats of redis
	
	public function __construct($host, $port = 6379) {
		$this->host = $host;
		$this->port = $port;
		
		$this->redis = new Redis ();
		
		try {
			$connect = $this->connect ();
			$this->info();
		}catch (Exception $e){
			$msg = $e->getMessage();
			$code = $e->getCode();
			echo PHP_EOL;
			echo "Failed to connect Redis: $msg! (code:$code)  Host:$host:$port";
			exit;
		}
	}
	
	/**
	 * connect
	 */
	private function connect() {
		return $this->redis->connect ( $this->host, $this->port );
	}
	
	/**
	 * Get information and statistics about the server
	 *
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 * @return array
	 */
	public function info() {
		if(!is_null($this->ar_redis_info)){
			return $this->ar_redis_info;
		}
		return $this->ar_redis_info = $this->redis->info ();
	}
	
	/**
	 * Ping server
	 *
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 * @abstract [如果连接失败(connect)，ping会直接报错：]
	 */
	public function ping() {
		return $this->redis->ping ();
	}
	
	/**
	 * Remove all keys from the current database.
	 *
	 * @author PHPJunlge
	 * @since 2015/07/23 周四
	 */
	public function flush_db() {
		return $this->redis->flushDB ();
	}
	
	/**
	 * Remove all keys from all databases.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 */
	public function flush_all() {
		return $this->redis->flushAll();
	}
	
	public function get_slow_log($opt='get',$num=null){
		return $this->redis->slowlog($opt,$num);
	}
	
// 	/**
// 	 * Add one element to a set
// 	 *
// 	 * @author PHPJungle
// 	 * @since 2015/07/23 周四
// 	 * @param string $set        	
// 	 * @param string $value        	
// 	 */
// 	public function set_add($set, $value) {
// 	}
// 	public function set_exists($set) {
// 	}
// 	public function set_value_exist() {
// 	}
	
	/**
	 * Returns the keys that match a certain pattern.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 * @param string $pattern using '*' as a wildcard.(通配符)
	 * @return array
	 * @abstract <pre>
	 * 		keys('*');   // all keys will match this.
	 * 		keys('user*'); // keys with user prefix.
	 */
	public function keys($pattern = '*'){
		return $this->redis->keys($pattern);
	}
	
	/**
	 * Get the value of a key
	 * 
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 * @param string $key
	 * @return string
	 */
	public function get($key){
		$key = trim($key);
		return $this->redis->get($key);
	}
	
	/**
	 * Get the values of all the specified keys. 
	 * If one or more keys dont exist, the array will contain FALSE at the position of the key.
	 * 
	 * @param mixed $key1
	 * @param string $_ [optional]
	 * @return array
	 */
	public function gets($key1,$_ = null){
		$keys = func_get_args();
		if(empty($keys)){
			return array();
		}
		
		if(is_array($key1) AND $key1)
			$keys = $key1;
		
		$values = $this->redis->mGet($keys);
		
		$maps = array();
		foreach($keys as $i=>$k){
			$maps[$k] = $values[$i];
		}
		return $maps;
	}
	
	
	/**
	 * Set the string value in argument as value of the key. 
	 * If you're using Redis >= 2.6.12, you can pass extended options as explained below
	 * 
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 * @param string $key
	 * @param string $value
	 * @param mix $options (if Redis-Version > 2.6.12)
	 * @abstract <pre>
	 * 		set('key', 'value'); # Simple key -> value set
	 * 		set('key','value', 10); # Will redirect, and actually make an SETEX call
	 * 		set('key', 'value', Array('nx', 'ex'=>10)); # Will set the key, if it doesn't exist, with a ttl of 10 seconds
	 * 		set('key', 'value', Array('xx', 'px'=>1000)); #  Will set a key, if it does exist, with a ttl of 1000 miliseconds
	 */
	public function set($key,$value,$options = array()){
		$key = trim($key);
		$ar_redis_info = $this->info();
		if(isset($ar_redis_info['redis_version']) AND version_compare($ar_redis_info['redis_version'], '2.6.12')>=0 AND $options)
			return $this->redis->set($key,$value,$options);
		else
			return $this->redis->set($key,$value);
	}
	
	/**
	 * Sets a value and returns the previous entry at that key.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 * @param string $key
	 * @param string $new_value
	 */
	public function get_set($key,$new_value){
		$key = trim($key);
		return $this->redis->getSet($key,$new_value);
	}
	
	/**
	 *  Append specified string to the string stored in specified key.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 * @param string $key
	 * @param string $append_string
	 * @return int Size of the value after the append
	 */
	public function append($key,$append_string){
		$key = trim($key);
		return $this->redis->append($key,$append_string);
	}
	
	/**
	 * Verify if the specified key exists.
	 * 
	 * @param string $key
	 * @return bool
	 * @abstract 适用于所有类型（string,hash,list,zlist.etc）
	 */
	public function key_exists($key){
		$key = trim($key);
		return $this->redis->exists($key);
	}
	
	/**
	 * Return a substring of a larger string(索引从0开始)
	 * 
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 * @param string $key
	 * @param int $startindex
	 * @param int $endindex
	 * @return string
	 * @abstract <pre>
	 * 		substr('list_map', -3); # 一直最后
	 * 		substr('list_map', -3,-1); # 
	 * 		substr('list_map', 0,3);
	 */
	public function substr($key,$startindex,$endindex = null){
		$key = trim($key);
		$endindex = $endindex?:-1; # php-redsi::getRange函数必须是三个参数
		return $this->redis->getRange($key,$startindex,$endindex);
	}
	
	/**
	 * Get the length of a string value.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 * @param string $key
	 * @return int
	 */
	public function strlen($key){
		$key = trim($key);
		return $this->redis->strlen($key);
	}
	
	/**
	 *  Adds a value to the hash stored at key. 
	 *  If this value is already in the hash, FALSE is returned.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 * @param string $hash_key
	 * @param string $hash_attr
	 * @param string $attr_value
	 * @return <pre>LONG 
	 * 1 if value didn't exist and was added successfully, 
	 * 0 if the value was already present and was replaced, 
	 * FALSE if there was an error.
	 */
	public function hash_set($hash_key,$hash_attr,$attr_value){
		$hash_key = trim($hash_key);
		$hash_attr = trim($hash_attr);
		
		if('' === $hash_attr){
			return null;
		}
		
		return $this->redis->hSet($hash_key,$hash_attr,$attr_value);
	}
	
	/**
	 * Adds a value to the hash stored at key only if this field <b><u>isn't </u></b>already in the hash.
	 * 
	 * @author PHPJungle 
	 * @since 2015/07/24 周五
	 * @param string $hash_key
	 * @param string $hash_attr
	 * @param string $attr_value
	 * @return bool
	 */
	public function hash_set_only_not_exists($hash_key,$hash_attr,$attr_value){
		$hash_key = trim($hash_key);
		$hash_attr = trim($hash_attr);
		return $this->redis->hSetNx($hash_key,$hash_attr,$attr_value);
	}
	
	/**
	 * Fills in a whole hash. Non-string values are converted to string, 
	 * using the standard (string) cast. 
	 * NULL values are stored as empty strings.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $hash_key
	 * @param array $maps
	 * @return BOOL
	 */
	public function hash_sets($hash_key,$maps){
		$hash_key = trim($hash_key);
		return $this->redis->hMSet($hash_key,$maps);
	}
	
	/**
	 *  Gets a value from the hash stored at key.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 * @param string $hash_key
	 * @param string $hash_attr
	 * @return string | false
	 */
	public function hash_value($hash_key,$hash_attr){
		$hash_key = trim($hash_key);
		$hash_attr = trim($hash_attr);
		return $this->redis->hGet($hash_key,$hash_attr);
	}
	
	/**
	 * Returns the values in a hash, as an array of strings with numberic index.
	 * 
	 * This works like PHP's array_values().
	 * 
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 * @param string $hash_key
	 * @return array
	 */
	public function hash_values($hash_key){
		$hash_key = trim($hash_key);
		return $this->redis->hVals($hash_key);
	}
	
	/**
	 * Returns the whole hash, as an array of strings indexed by strings.
	 *
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 * @param string $hash_key
	 * @return array
	 * @abstract 异常情况返回array(0){}
	 */
	public function hash_maps($hash_key){
		$hash_key = trim($hash_key);
		return $this->redis->hGetAll($hash_key);
	}

	/**
	 * Returns the length of a hash, in number of items
	 * 
	 * @author PHPJungle
	 * @since 2015/07/23 周四
	 * @param string $hash_key
	 * @return LONG or FALSE if the key doesn't exist or isn't a hash.
	 */
	public function hash_field_count($hash_key){
		$hash_key = trim($hash_key);
		return $this->redis->hLen($hash_key);
	}
	
	/**
	 * Verify if the specified member exists in a key.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $hash_key
	 * @param string $attr
	 * @return bool
	 */
	public function hash_field_exists($hash_key,$attr){
		$hash_key = trim($hash_key);
		$attr = trim($attr);
		return $this->redis->hExists($hash_key,$attr);
	}

	/**
	 * Get all the fields in a hash
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $hash_key
	 * @return array
	 */
	public function hash_fields($hash_key){
		$hash_key = trim($hash_key);
		return $this->redis->hKeys($hash_key);
	}
	
	/**
	 *  Increments the value of a member(<u>Integer</u>) from a hash by a given amount.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $hash_key
	 * @param string $attr
	 * @param int $int_incr [default 1]
	 * @return LONG the new value
	 * @abstract 如果$int_incr 等于负数则做减法操作
	 * (<p style="color:red">如果一个键或者属性不存在会自动创建一个)</p>
	 */
	public function hash_val_incr_by($hash_key,$attr,$int_incr = 1){
		$hash_key = trim($hash_key);
		$attr = trim($attr);
		return $this->redis->hIncrBy($hash_key,$attr,$int_incr);
	}
	
	/**
	 *  Increments the value of a member(<u>Float</u>) from a hash by a given amount.
	 *
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $hash_key
	 * @param string $attr
	 * @param float $float_incr
	 * @return FLOAT  the new value
	 */
	public function hash_val_incr_byfloat($hash_key,$attr,$float_incr){
		$hash_key = trim($hash_key);
		$attr = trim($attr);
		return $this->redis->hIncrByFloat($hash_key,$attr,$float_incr);
	}
	
	/**
	 * Removes a value from the hash stored at key. 
	 * If the hash table doesn't exist, or the key doesn't exist, FALSE is returned.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $hash_key
	 * @param string $attr
	 * @return bool
	 */
	public function hash_field_del($hash_key,$attr){
		$hash_key = trim($hash_key);
		$attr = trim($attr);
		return $this->redis->hDel($hash_key,$attr);
	}
	
	
	/**
	 * Removes multiple values from the hash stored at key. 
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $hash_key
	 * @param array $ar_attr
	 * @return int
	 */
	public function hash_fields_del($hash_key,$ar_attr){
		$hash_key = trim($hash_key);
		if(is_array($ar_attr) AND $ar_attr){
			$count = count($ar_attr);
			foreach($ar_attr as $attr){
				$attr = trim($attr);
				$this->hash_field_del($hash_key, $attr);
			}
			return $count;
		}
		return null;
	}
	
	/**
	 * Return and remove the first element of the list.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list
	 * @return string|false
	 */
	public function list_pop_head($list){
		$list = trim($list);
		return $this->redis->lPop($list);
	}
	
	/**
	 * Is a blocking lPop
	 * 
	 * @author PHPJungle
	 * @since 2015/07/25 周六
	 * @param mix $list [array or string]
	 * @param int $timeout
	 * @return ARRAY array('listName', 'element')
	 * @abstract 可以指定多个list，如果所有list的集合不为空，则直接pop,
	 * 	如果整个集合为空，则会阻塞到知道整个集合有元素位置（或者是超过timeout指定的时间）
	 */
	public function list_pop_head_with_block($list,$timeout){
		return $this->redis->blPop($list,$timeout);
	}
	
	/**
	 * Is a blocking rPop
	 *
	 * @author PHPJungle
	 * @since 2015/07/25 周六
	 * @param mix $list [array or string]
	 * @param int $timeout
	 * @return ARRAY array('listName', 'element')
	 * @abstract 可以指定多个list，如果所有list的集合不为空，则直接pop,
	 * 	如果整个集合为空，则会阻塞到知道整个集合有元素位置（或者是超过timeout指定的时间）
	 */
	public function list_pop_tail_with_block($list,$timeout){
		return $this->redis->brPop($list,$timeout);
	}
	
	/**
	 * Return and remove the last element of the list.
	 *
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list
	 * @return string|false
	 */
	public function list_pop_tail($list){
		$list = trim($list);
		return $this->redis->rPop($list);
	}
	
	/**
	 * Adds the string value to the head (left) of the list. 
	 * Creates the list if the key didn't exist. 
	 * If the key exists and is not a list, FALSE is returned.
	 *
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list
	 * @param string $value
	 * @return Long | false
	 */
	public function list_push_head($list,$value){
		$list = trim($list);
		return $this->redis->lPush($list,$value);
	}
	
	/**
	 * Adds the string value to the tail (right) of the list. 
	 * Creates the list if the key didn't exist. 
	 * If the key exists and is not a list, FALSE is returned.
	 *
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list
	 * @param string $value
	 * @return Long | false
	 */
	public function list_push_tail($list,$value){
		$list = trim($list);
		return $this->redis->rPush($list,$value);
	}
	
	/**
	 *  Adds the string value to the head (left) of the list <b>if the list exists</b>.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list
	 * @param string $value
	 * @return LONG false
	 */
	public function list_push_head_if_exist($list,$value){
		$list = trim($list);
		return $this->redis->lPushx($list,$value);
	}
	
	/**
	 *  Adds the string value to the tail (right) of the list <b>if the list exists</b>.
	 *
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list
	 * @param string $value
	 * @return LONG false
	 */
	public function list_push_tail_if_exist($list,$value){
		$list = trim($list);
		return $this->redis->rPushx($list,$value);
	}
	
	/**
	 * Return the specified element of the list stored at the specified key.
	 * 0 the first element, 1 the second ... -1 the last element, -2 the penultimate ...
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list
	 * @param int $index
	 * @return string false
	 */
	public function list_get_by_index($list,$index){
		$list = trim($list);
		return $this->redis->lGet($list,$index);
	}
	
	/**
	 * Returns the specified elements of the list stored at the specified key in the range [start, end].
	 *  start and stop are interpretated as indices: 
	 *  0 the first element, 1 the second ... -1 the last element, -2 the penultimate ...
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list
	 * @param int $start_index
	 * @param int $end_index
	 * @return array 
	 */
	public function list_sub_list($list,$start_index=0,$end_index=-1){
		$list = trim($list);
		return $this->redis->lRange($list,$start_index,$end_index);
	}
	
	/**
	 * Get all the List elements
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list
	 * @return array
	 */
	public function list_get_all($list){
		return $this->list_sub_list($list);
	}
	
	/**
	 * Returns the size of a list identified by Key.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list
	 * @return LONG
	 */
	public function list_count($list){
		$list = trim($list);
		return $this->redis->lLen($list);
	}
	
	
	/**
	 * Removes the first-count occurences of the value element from the list. 
	 * If count is zero, all the matching elements are removed. 
	 * If count is negative, elements are removed from tail to head.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list
	 * @param string $value
	 * @param int $count
	 * @return LONG the number of elements to remove,BOOL FALSE if the value identified by key is not a list.
	 */
	public function list_remove($list,$value,$count){
		$list = trim($list);
		return $this->redis->lRem($list,$value,$count);
	}
	
	/**
	 * Remove all the value '===' $value
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list
	 * @param string $value
	 * @return LONG the number of elements to remove,BOOL FALSE if the value identified by key is not a list.
	 */
	public function list_remove_all($list,$value){
		return $this->list_remove($list,$value,0);
	}
	
	/**
	 * Trims an existing list so that it will contain only a specified range of elements.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list
	 * @param int $start_index
	 * @param int $end_index
	 * @return int　| BOOL return FALSE if the key identify a non-list value.
	 * @abstract @todo:这里面有个Bug：如果只有一个元素，传参($list,-2,-2),则会删除所有元素
	 */
	public function list_trim($list,$start_index,$end_index){
		$list = trim($list);
		return $this->redis->lTrim($list,$start_index,$end_index);
	}
	
	/**
	 * Pops a value from the tail of a list  
	 * and pushes it to the <b><u>front</u></b> of another list. 
	 * Also return this value. (redis >= 1.1)
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list_srckey
	 * @param string $list_dstkey
	 * @return <b>STRING</b> The element that was moved in case of success, <b>FALSE</b> in case of failure.
	 * @abstract 如果$list_dstkey不存在,则会新建一个名为list_dstkey的list
	 */
	public function list_poppush_from_tail_to_head($list_srckey,$list_dstkey){
		$list_pop = trim($list_srckey);
		$list_push = trim($list_dstkey);
		return $this->redis->rpoplpush($list_pop,$list_push);
	}
	
	/**
	 * A blocking version of <b>list_poppush_from_tail_to_head</b>, with an integral timeout in the third parameter.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/24 周五
	 * @param string $list_srckey
	 * @param string $list_dstkey
	 * @param int $timeout
	 * @return <b>STRING</b> The element that was moved in case of success, <b>FALSE</b> in case of failure.
	 */
	public function list_poppush_from_tail_to_head_via_block($list_srckey,$list_dstkey,$timeout){
		$list_pop = trim($list_srckey);
		$list_push = trim($list_dstkey);
		return $this->redis->brpoplpush($list_pop,$list_push,$timeout);
	}
	
	/**
	 * Remove specified keys.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/25 周六
	 * @param array $key
	 * @param string $_
	 * @return Long Number of keys deleted.
	 */
	public function delete($key,$_=null){
		$ar = array();
		$args = func_get_args();
		if(empty($args)){
			return 0;
		}
		
		foreach($args as $k){
			if(is_array($k)){
				$ar = array_merge($k);
			}else{
				$ar[] = $k;
			}
		}
		if(empty($ar)){
			return 0;
		}
		return $this->redis->delete($ar);
	}
	
	/**
	 * @todo complish this code!!!
	 * 
	 * @author PHPJungle
	 */
	public function list_insert(){
		die('add your code to function:'.__FUNCTION__);
	}
	
	/**
	 * Adds a(or Multiple )value to the set value stored at key.
	 * If this value is already in the set, FALSE is returned.
	 *
	 * @author PHPJungle
	 * @since 2015/07/25 周六
	 * @param string $key        	
	 * @param mix $value        	
	 * @param mix $_        	
	 * @return LONG the number of elements added to the set.(or FALSE when
	 *         failed)
	 */
	public function sets_add($key, $value, $_=null) {
		$ar_ele = array ();
		$args = func_get_args ();
		if (count ( $args ) >= 2) {
			unset ( $args [0] ); // remove key
			foreach ( $args as $e ) {
				if (is_array ( $e )) {
					$ar_ele = array_merge ( $ar_ele,$e );
				} else {
					$ar_ele [] = $e;
				}
			}
			$num = 0;
			if($ar_ele){
				$key = trim($key);
				foreach($ar_ele as $e){
					! $this->redis->sAdd($key,$e) or $num++;
				}
				return $num;
			}
			# return call_user_func(array($this->redis,'sAdd'),$ar_ele); # @todo：PHPRedis>=2.4才能使用
		}
		return false;
	}
	
	
	/**
	 * Returns the contents of a set.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/25 周六
	 * @param string $key
	 * @return array
	 */
	public function sets_get_all($key){
		$key = trim($key);
		return $this->redis->sMembers($key);
	}
	
	/**
	 * get the size fo a set
	 * 
	 * @author PHPJungle
	 * @since 2015/07/25 周六
	 * @param string $key
	 * @return LONG
	 */
	public function sets_count($key){
		$key = trim($key);
		return $this->redis->sCard($key);
	}
	
	/**
	 * Performs the difference between N sets and returns it.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/25 周六
	 * @param string $set1
	 * @param string $set2
	 * @param string $_
	 * @return array | false
	 */
	public function sets_diff($set1,$set2,$_=null){
		$argv = func_get_args();
		if(count($argv) >= 2){
			return call_user_func(array($this->redis,'sDiff'),$argv);
		}
		return false;
	}
	
	/**
	 * Performs the difference between N sets and <b><u>Stores</u></b> the result in the first key
	 *
	 * @author PHPJungle
	 * @since 2015/07/27 周一
	 * @param string $set1
	 * @param string $set2
	 * @param string $_
	 * @return array | false
	 */
	public function sets_diff_then_store($set1,$set2,$_=null){
		$argv = func_get_args();
		if(count($argv) >= 2){
			return call_user_func(array($this->redis,'sDiffStore'),$argv);
		}
		return false;
	}
	
	/**
	 * Returns the members of a set resulting from the intersection of all the sets held at the specified keys.
	 * 
	 * [warning]: If one of the keys is missing, FALSE is returned.
	 *
	 * @author PHPJungle
	 * @since 2015/07/27 周一
	 * @param string $set1
	 * @param string $set2
	 * @param string $_
	 * @return array | false
	 */
	public function sets_intersect($set1,$set2,$_=null){
		$argv = func_get_args();
		if(count($argv) >= 2){
			return call_user_func(array($this->redis,'sInter'),$argv);
		}
		return false;
	}
	
	/**
	 * Performs the intersections between N sets and <b><u>Stores</u></b> the result in the first key
	 *
	 * @author PHPJungle
	 * @since 2015/07/27 周一
	 * @param string $set1
	 * @param string $set2
	 * @param string $_
	 * @return array | false
	 */
	public function sets_intersect_then_store($set1,$set2,$_=null){
		$argv = func_get_args();
		if(count($argv) >= 2){
			return call_user_func(array($this->redis,'sInterStore'),$argv);
		}
		return false;
	}
	
	/**
	 * Checks if value is a member of the set stored at the key
	 * 
	 * @author PHPJungle
	 * @since 2015/07/27 周一
	 * @param string $set
	 * @param string $value
	 * @return bool
	 */
	public function sets_value_exist($set,$value){
		$set = trim($set);
		return $this->redis->sIsMember($set,$value);
	}
	
	/**
	 * Moves the specified member from the set at srcKey to the set at dstKey
	 * 
	 * @author PHPJungle
	 * @since 2015/07/27 周一
	 * @param string $set1
	 * @param string $set2
	 * @param string $value
	 * @return bool
	 */
	public function sets_move_value($set1,$set2,$value){
		$set1 = trim($set1);
		$set2 = trim($set2);
		return $this->redis->sMove($set1,$set2,$value);
	}
	
	/**
	 * <b>Removes</b> and <b>returns a random element</b> from the set value at Key.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/27 周一
	 * @param string $set
	 * @return <p><b>String</b> "popped" value</p>
	 * <p>Bool FALSE if set identified by key is empty or doesn't exist.</p>
	 */
	public function sets_random_pop($set){
		$set = trim($set);
		return $this->redis->sPop($set);
	}
	
	/**
	 * Returns a(or <b><u>more</b></u>) <b><u>random element</u></b> from the set value at Key, <b><u>without removing</b></u> it.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/27 周一
	 * @param string $set
	 * @param int $num [optional] 
	 * @return string|array|false
	 * @abstract <pre>
	 * 		1.未指定$num=>随机返回一个string-value;
	 * 		2.正整数:多个随机数构成的数组(如果count>sum，则返回整个set集合)
	 * 		3.-100：返回一个含有100个元素(可重复)的list列表,这100个元素全部来源于set集合</pre>
	 */
	public function sets_random($set,$num = 1){
		$set = trim($set);
		return $this->redis->sRandMember($set,$num);
	}
	
	/**
	 * Removes the specified <b><u>one or more members</b></u> from the set value stored at key.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/27 周一
	 * @param string $set
	 * @param string $value1
	 * @param string $_ [optional]
	 * @return long  The number of elements removed from the set
	 * @abstract @FIXME:PHP Redis Bug:Warning:  Wrong parameter count for Redis::srem()
	 */
	public function sets_remove($set,$value1,$_=null){
		$argvs = func_get_args();
// 		var_dump($argvs);
// 		if(count($argvs)>=2){
// 			# 构造新参数
// 			$params = array();
// 			foreach($argvs as $e){
// 				if(is_array($e) AND $e){
// 					$params = array_merge($params,$e);
// 				}else{
// 					$params[] = $e;
// 				}
// 			}
// 			var_dump($params);
// 			return call_user_func(array($this->redis,'sRem'),$params);
// 		}
		return $this->redis->sRem($set,$value1);
		return 0;
	}
	
	/**
	 * Performs the union between N sets and returns it.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/27 周一
	 * @param mix $set1
	 * @param mix $set2
	 * @param mix $_ [optional]
	 * @return array|false
	 * @abstract Array of strings: The union of all these sets.
	 */
	public function sets_union($set1,$set2 = null,$_=null){
		$argvs = func_get_args();
		if(count($argvs)>=1){
			# 构造新参数
			$params = array();
			foreach($argvs as $e){
				if(is_array($e) AND $e){
					$params = array_merge($params,$e);
				}else{
					$params[] = $e;
				}
			}
			return call_user_func(array($this->redis,'sUnion'),$params);
		}
		return false;
	}
	
	/**
	 * Performs the union between N sets and stores the result in the first key
	 *
	 * @author PHPJungle
	 * @since 2015/07/27 周一
	 * @param mix $set1
	 * @param mix $set2
	 * @param mix $_ [optional]
	 * @return int|false
	 */
	public function sets_union_then_store($set1,$set2 = null,$_=null){
		$argvs = func_get_args();
		if(count($argvs)>=1){
			# 构造新参数
			$params = array();
			foreach($argvs as $e){
				if(is_array($e) AND $e){
					$params = array_merge($params,$e);
				}else{
					$params[] = $e;
				}
			}
			return call_user_func(array($this->redis,'sUnionStore'),$params);
		}
		return false;
	}
	
	/**
	 *  A command allowing you to get information on the Redis pub/sub system.
	 * 
	 * @author PHPJungle
	 * @since 2015/07/27 周一
	 */
	public function channel_info($cmd = null){
		
	}
	
	/**
	 * Get All channels
	 * 
	 * @author PHPJungle
	 * @since 2015/07/27 周一
	 * @return array
	 */
	public function channel_get_all(){
		return $this->redis->pubsub('channel');
	}
	
	/**
	 * Publish messages to channels. 
	 * <b><u>Warning</u></b>: this function will probably change in the future.
	 * 
	 * @author PHPJungle
	 * @since since 2015/07/27 周一
	 * @param string $channel
	 * @param string $msg
	 * @return int
	 */
	public function channel_pub_message($channel,$msg = 'Hello,PHPJungle!'){
		$channel = trim($channel);
		return $this->redis->publish($channel,$msg);
	}
	
	/**
	 * 
	 * 
	 * @author PHPJungle
	 * @since since 2015/07/27 周一
	 * @param string $channel
	 */
	public function channel_subscribe($ar_channel,$callback){
		if(is_array($ar_channel) AND $ar_channel){
			return $this->redis->subscribe($ar_channel,$callback);
		}
		return false;
	}
	
	# 以下是有序结合的相关方法
	/**
	 * Add one member to a sorted set or update its score if it already exists
	 * 
	 * @since 2015/08/18 周二
	 * @param string $key
	 * @param string $ele
	 * @param double $score
	 * @return Long 1 if the element is added. 0 otherwise.
	 */
	public function oSets_add($key,$ele, $score){
		$key = trim($key);
		if($key AND is_numeric($score) AND !is_null($ele)){
			return $this->redis->zAdd($key,$score,$ele);
		}
		return 0;
	}
	
	/**
	 * Add multiple members to a sorted set or update their score if some of them already exist
	 * 
	 * @since 2015/08/18 周二
	 * @param string $key
	 * @param array $ar_maps
	 * @return Long
	 */
	public function oSets_adds($key,$ar_maps){
		$key = trim($key);
		if($key AND is_array($ar_maps) AND $ar_maps){
			$num = 0;
			foreach($ar_maps  as $ele => $score){
				!$this->oSets_add($key, $ele, $score) or $num++;
			}
			return $num;
			# return call_user_func(array($this->redis,'zAdd'),$input); 版本不支持
		}
		return 0;
	}
	
	/**
	 * Get the number of members in a sorted set
	 * 
	 * @since 2015/08/18 周二 
	 * @param string $key
	 * @param double $start
	 * @param double $end
	 * @return long 
	 * @abstract 返回名称为key的zset中score >= star且score <= end的所有元素的个数
	 */
	public function oSets_count($key,$start = null , $end = null){
		$key = trim($key);
		if($key){
			if(!is_null($start) AND !is_null($end)){
				return $this->redis->zCount($key,$start,$end);
			}
			return $this->redis->zSize($key);
		}
		return 0;
	}
	
	/**
	 * Increments the score of a member from a sorted set by a given amount.
	 * 
	 * @since 2015/08/18 周二
	 * @param string $key
	 * @param string $ele
	 * @param double $amount [<font color = red>default = 1</font>]
	 * @return DOUBLE the new value or null when fail
	 */
	public function oSets_inc_by($key,$ele,$amount = 1){
		$key = trim($key);
		if($key and is_numeric($amount)){
			return $this->redis->zIncrBy($key,$amount,$ele);
		}
		return null;
	}
	
	/**
	 * Returns a range of elements from the ordered set stored at the specified key, 
	 * 
	 * with values in the range [start, end](<font color=red>
	 * 
	 * 这个范围是：整型值,0 the first element, 1 the second ... -1 the last element, -2 the penultimate ...</font>).
	 * 
	 * @since 2015/08/18 周二
	 * @param string $key
	 * @param long $start
	 * @param long $end
	 * @param bool $withscores [是否显示score]
	 * @return array
	 */
	public function oSets_range($key,$start,$end = -1, $withscores = false){
		$key = trim($key);
		if($key and is_numeric($start) and is_numeric($end)){
			return $this->redis->zRange($key,$start,$end,$withscores);
		}
		return array();
	}
	/**
	 * 获取最大元素(即：最后一个元素)
	 * 
	 * @since 2015/08/21 周五
	 * @param string $key
	 * @param bool $withscores [optional：是否显示分数]
	 * @return string|array|null
	 * @abstract 默认返回string，如果$withscores=true ,则返回数组array(member=>score);
	 */
	public function oSets_max($key,$withscores = false){
		$key = trim($key);
		if($key AND $member = $this->oSets_range($key, -1,-1,$withscores)){
			return $withscores?$member:current($member);
		}
		return null;
	}
	
	/**
	 * Alias of <b><u>oSets_max</u></b>
	 * 
	 * @since 2015/08/21 周五
	 * @param string $key
	 * @param bool $withscores [optional：是否显示分数]
	 * @return string|array|null
	 * @abstract 默认返回string，如果$withscores=true ,则返回数组array(member=>score);
	 */
	public function oSets_right($key,$withscores = false){
		return $this->oSets_max($key,$withscores);
	}
	
	/**
	 * Pop the max(right) member
	 * 
	 * @since 2015/08/21 周五
	 * @param string $key
	 * @param bool $withscores [optional：是否显示分数]
	 * @return string|array|null
	 * @abstract @todo <font color=red>即便存在并发删除也不要紧，程序会判断是否删除成功，如果发现删除失败会返回null</font>
	 */
	public function oSets_pop_max($key,$withscores = false){
		$key = trim($key);
		if($key and $member = $this->oSets_max($key,$withscores)){
			$v = $withscores?key($member):$member;
			if($this->oSets_del($key, $v)){ # 如果移除成功就返回member
				return $member;
			}
		}
		return null;
	}
	
	/**
	 * Pop the min(left) member
	 * 
	 * @since 2015/08/21 周五
	 * @param string $key
	 * @param bool $withscores
	 * @return string|array|null
	 */
	public function oSets_pop_min($key,$withscores = false){
		$key = trim($key);
		if($key and $member = $this->oSets_min($key,$withscores)){
			$v = $withscores?key($member):$member;
			if($this->oSets_del($key, $v)){ # 如果移除成功就返回member
				return $member;
			}
		}
		return null;
	}
	
	/**
	 * 获取最大元素(即：最后第一个元素)
	 * 
	 * @since 2015/08/21 周五
	 * @param string $key
	 * @param bool $withscores
	 * @return string|array|null
	 * @abstract 默认返回string，如果$withscores=true ,则返回数组array(member=>score);
	 */
	public function oSets_min($key,$withscores = false){
		$key = trim($key);
		if($key AND $member = $this->oSets_range($key, 0,0,$withscores)){
			return $withscores?$member:current($member);
		}
		return null;
	}
	
	/**
	 * Alias of <b><u>oSets_min</u></b>
	 * 
	 * @since 2015/08/21 周五
	 * @param string $key
	 * @param bool $withscores
	 * @return 默认返回string，如果$withscores=true ,则返回数组array(member=>score);
	 */
	public function oSets_left($key,$withscores = false){
		return $this->oSets_min($key,$withscores);
	}
	
	/**
	 * Get All oSets members with specified key[可以自定义排序和筛选字段]
	 * 
	 * @since 2015/08/19 周三
	 * @param string $key
	 * @param bool $desc [optional:默认true->返回从大到小的排序]
	 * @param bool $withscores [optional:是否显示score,默认:false]
	 * @return array
	 */
	public function oSets_get_all($key,$desc = true,$withscores = false){
		$key = trim($key);
		if($key){
			if($desc){
				return $this->oSets_range_reverse_by_score($key,self::OSETS_RANGE_LEFT_INF, self::OSETS_RANGE_RIGHT_INF,array('withscores'=>$withscores));
			}else{
				return $this->oSets_range($key, 0,-1,$withscores);
			}
		}
		return array();
	}
	
	/**
	 * Returns the elements of the sorted set stored at the specified key <font color=red >which have scoresin the range [start,end].</font> 
	 * 
	 *  Adding a parenthesis before start or end excludes it from the range. +inf and -inf are also valid limits. 
	 * 
	 * @since 2015/08/18 周二
	 * @param string $key
	 * @param double $score_start
	 * @param double $score_end
	 * @param array $opt <font color=red>[Two options are available: withscores => TRUE, and limit => array($offset, $count)]</font>
	 * @return array
	 * @abstract demo:<br>
	 * 		oSets_range_by_score($key, 0.0,1000,array('withscores' => true,'limit'=>array(0,3)));
	 */
	public function oSets_range_by_score($key,$score_start,$score_end,$opt = array()){
		if($key and (is_numeric($score_start) or $score_start === self::OSETS_RANGE_LEFT_INF) 
				and (is_numeric($score_end) or $score_end === self::OSETS_RANGE_RIGHT_INF)){
			
			# limit to int !important
			if(isset($opt['limit']['0'],$opt['limit']['1'])){
				$opt['limit']['0'] = (int)$opt['limit']['0'];
				$opt['limit']['1'] = (int)$opt['limit']['1'];
			} 
			
			return $this->redis->zRangeByScore($key,$score_start,$score_end,$opt);
		}
		return array();
	}
	
	/**
	 *  returns the same items(like oSets_range_by_score ) but in reverse order
	 * 
	 * @since since 2015/08/18 周二
	 * @param string $key
	 * @param double $score_start
	 * @param double $score_end
	 * @param array $opt <font color=red>[Two options are available: withscores => TRUE, and limit => array($offset, $count)]</font>
	 * @return array
	 */
	public function oSets_range_reverse_by_score($key,$score_start,$score_end,$opt = array()){
		if($key and (is_numeric($score_start) or $score_start === self::OSETS_RANGE_LEFT_INF ) 
				and (is_numeric($score_end) or $score_end === self::OSETS_RANGE_RIGHT_INF)){
			
			# limit to int !important
			if(isset($opt['limit']['0'],$opt['limit']['1'])){
				$opt['limit']['0'] = (int)$opt['limit']['0'];
				$opt['limit']['1'] = (int)$opt['limit']['1'];
			}
			return $this->redis->zRevRangeByScore($key,$score_end,$score_start,$opt);
		}
		return array();
	}
	
	/**
	 * get top N members(从大到小)
	 * 
	 * @since since 2015/08/18 周二
	 * @param string $key
	 * @param int $topN [defalut:3]
	 * @param bool $withscores [default:false 是否显示score]
	 * @return array
	 */
	public function oSets_top_N_desc($key,$topN = 3,$withscores = false){
		if($key and is_numeric($topN)){
			$opt = array('withscores'=>$withscores,'limit'=>array(0,(int)$topN));
			return $this->oSets_range_reverse_by_score($key, self::OSETS_RANGE_LEFT_INF, self::OSETS_RANGE_RIGHT_INF,$opt);
		}
		return array();
	}
	
	/**
	 * get top N members(从小到大)
	 *
	 * @since 2015/08/18 周二
	 * @param string $key
	 * @param int $topN
	 * @param bool $withscores [是否显示score]
	 * @return array
	 */
	public function oSets_top_N_asc($key,$topN = 3,$withscores = false){
		if($key and is_numeric($topN)){
			$opt = array('withscores'=>$withscores,'limit'=>array(0,(int)$topN));
			return $this->oSets_range_by_score($key, self::OSETS_RANGE_LEFT_INF, self::OSETS_RANGE_RIGHT_INF,$opt);
		}
		return array();
	}
	
	/**
	 * 未实现的方法:@see <a href="https://github.com/phpredis/phpredis#zrangebyscore-zrevrangebyscore">点我啊</a>
	 */
	public function oSets_range_by_lex(){
		die('未实现的方法：@see https://github.com/phpredis/phpredis#zrangebyscore-zrevrangebyscore');
	}
	
	/**
	 * 【查排名:值0 Rank最小】
	 * 
	 * Returns the rank of a given member in the specified sorted set,
	 * 
	 * starting at 0 for the item with the smallest score. 
	 * 
	 * @since 2015/08/18 周二
	 * @param string $key
	 * @param string $ele
	 * @return long(0 最小，-1 表示参数不合法，false表示元素不存在)
	 */
	public function oSets_rank_asc($key,$ele){
		if($key){
			return $this->redis->zRank($key,$ele);
		}
		return -1;
	}
	
	/**
	 * 【查排名:值0 Rank最大】
	 *
	 * Returns the rank of a given member in the specified sorted set,
	 *
	 * starting at 0 for the item with the smallest score.
	 *
	 * @since 2015/08/18 周二
	 * @param string $key
	 * @param string $ele
	 * @return long(0 最大，-1 表示参数不合法，false表示元素不存在)
	 */
	public function oSets_rank_desc($key,$ele){
		if($key){
			return $this->redis->zRevRank($key,$ele);
		}
		return -1;
	}
	
	/**
	 * Deletes a specified member from the ordered set.
	 * 
	 * @since 2015/08/19 周三
	 * @param string $key
	 * @param string $ele
	 * @return LONG 1 on success, 0 on failure.
	 */
	public function oSets_del($key,$ele){
		$key = trim($key);
		if($key){
			return $this->redis->zDelete($key,$ele);
		}
		return 0;
	}
	
	/**
	 * Deletes multiple members from the ordered set.
	 * 
	 * @since 2015/08/23 周日
	 * @param string $key
	 * @param array $ar_members
	 * @param long
	 */
	public function oSets_dels($key,$ar_members){
		$key = trim($key);
		$n = 0;
		if($key && is_array($ar_members) && $ar_members){
			foreach($ar_members as $member){
				!$this->oSets_del($key, $member) or $n++;
			}
		}
		return $n;
	}
	
	/**
	 * Deletes the elements of the sorted set stored at the specified key <font color=red>which have rank in the range [start,end]</font>.
	 * 
	 * @since 2015/08/19 周三
	 * @param string $key
	 * @param long $rank_start
	 * @param long $rank_end
	 * @return LONG The number of values deleted from the sorted se
	 */
	public function oSets_dels_by_rank($key,$rank_start,$rank_end){
		$key = trim($key);
		if($key){
			return $this->redis->zDeleteRangeByRank($key,$rank_start,$rank_end);
		}
		return 0;
	}
	
	/**
	 * Deletes the elements of the sorted set stored at the specified key <font color=red>which have scores in the range [start,end]</font>.
	 * 
	 * @since 2015/08/19 周三
	 * @param string $key
	 * @param double $score_start [value:double or 负无穷]
	 * @param double $score_end [value:doule or 负无穷]
	 * @return LONG The number of values deleted from the sorted set
	 */
	public function oSets_dels_by_score($key,$score_start,$score_end){
		$key = trim($key);
		if($key){
			return $this->redis->zRemRangeByScore($key,$score_start,$score_end);
		}
		return 0;
	}
	
	/**
	 * Returns the score of a given member in the specified sorted set.
	 * 
	 * @since  2015/08/19 周三
	 * @param string $key
	 * @param string $ele
	 * @return Double|false
	 */
	public function oSets_score($key,$ele){
		$key = trim($key);
		if($key){
			return $this->redis->zScore($key,$ele);
		}
		return false;
	}
	
	/**
	 * Check whether ele is osets's member 
	 * 
	 * @since 2015/08/23 周日
	 * @param string $key
	 * @param string $ele
	 * @return bool 
	 */
	public function oSets_is_member($key,$ele){
		$key = trim($key);
		return false === $this->oSets_score($key, $ele)?false:true;
	}
	
	/**
	 * Get Multiple Scores
	 * 
	 * @since 2015/08/19 周三
	 * @param string $key
	 * @param string $ar_ele
	 * @param bool $withMemberName [optional:是否获取memberName]
	 * @return array
	 */
	public function oSets_scores($key,$ar_ele,$withMemberName = false){
		$key = trim($key);
		if($key AND is_array($ar_ele) AND $ar_ele){
			# Unique_Array
			$ar_ele = array_unique($ar_ele);
			$data = array();
			foreach($ar_ele as $member){
				if($withMemberName)
					$data[$member] = $this->oSets_score($key, $member);
				else
					$data[] = $this->oSets_score($key, $member);
			}
			return $data;
		}
		return array();
	}
	
	/**
	 * 未实现的方法:@see <a href="https://github.com/phpredis/phpredis#zinter">点我啊</a>
	 */
	public function oSets_intersect(){
		die('未实现的方法：@see https://github.com/phpredis/phpredis#zinter');
	}
	
	/**
	 * 未实现的方法:@see <a href="https://github.com/phpredis/phpredis#zunion">点我啊</a>
	 */
	public function oSets_union(){
		die('未实现的方法：@see https://github.com/phpredis/phpredis#zunion');
	}
	
	/**
	 * Set a key's time to live in seconds
	 * 
	 * Description: Sets an expiration date (a timeout) on an item. pexpire requires a TTL in milliseconds.
	 * 
	 * @since 2015/12/12 周六
	 * @param string $key The key that will disappear.
	 * @param int $ttl The key's remaining Time To Live, in seconds.
	 * @return BOOL: TRUE in case of success, FALSE in case of failure.
	 */ 
    public function expire($key, $ttl_second = 60){
        $key = trim($key);
        return $this->redis->expire($key,$ttl_second);
    }
    
    /**
     * Set a key's time to live in milliseconds
     *
     * Description: Sets an expiration date (a timeout) on an item. pexpire requires a TTL in milliseconds.
     *
     * @since 2015/12/12 周六
     * @param string $key The key that will disappear.
     * @param int $ttl_millisecond The key's remaining Time To Live, in milliseconds.
     * @return BOOL: TRUE in case of success, FALSE in case of failure.
     */
    public function expire_in_millisecond($key, $ttl_millisecond = 1000){
        $key = trim($key);
        return $this->redis->pexpire($key,$ttl_millisecond);
    }
    
    
    
    /**
     * Returns the timestamp of the last disk save.
     * 
     * @since 2015/12/14 周一
     * @return INT: timestamp.
     */
    public function last_save(){
        return $this->redis->lastSave();
    }
    
    /**
     * Get a key's TTL in second
     * 
     * @since 2015/12/14 周一
     * @param string $key
     * @return LONG: The time to live in seconds. If the key has no ttl, -1 will be returned, and -2 if the key doesn't exist.
     */
    public function ttl($key){
        $key = trim($key);
        return $this->redis->ttl($key);
    }
    
    /**
     * Get a key's TTL in millisecond
     * 
     * @since 2015/12/14 周一
     * @param string $key
     * @return LONG: The time to live in seconds. If the key has no ttl, -1 will be returned, and -2 if the key doesn't exist.
     */
    public function ttl_in_millisecond($key){
        $key = trim($key);
        return $this->redis->pttl($key);
    }
    
    /**
     * Change the selected database for the current connection.
     * 
     * @since 01/20/2016 Wed
     * @param int $dbIndex [dbindex, the database number to switch to.]
     * @return bool
     */
    public function select($dbIndex){
        $dbIndex = (int)$dbIndex;
        return $this->redis->select($dbIndex);
    }
    
    /**
     * Get DB size of current dbindex
     * 
     * @since 02/14/2016 Sun
     * @return int
     */
    public function db_size(){
        return $this->redis->dbSize();
    }
    
    /**
     * Returns the type of data pointed by a given key.
     * 
     * @param string $key
     * @return int
     * @abstract <pre>
     *      Depending on the type of the data pointed by the key, this method will return the following value: 
     *      string: Redis::REDIS_STRING 
     *      set: Redis::REDIS_SET 
     *      list: Redis::REDIS_LIST 
     *      zset: Redis::REDIS_ZSET 
     *      hash: Redis::REDIS_HASH 
     *      other: Redis::REDIS_NOT_FOUND
     */
    public function type($key){
        return $this->redis->type($key);
    }
    
    /**
     * 测试阶段-执行一段redis脚本命令
     * 
     * @since 02/14/2016 Sun
     * @param string $redis_command
     * @return boolean
     */
    private function run($redis_command){
        $redis_command = trim($redis_command);
        if($redis_command){
            
        }
        return false;
    }

    /**
     * Get osets pager list
     * 
     * [获取有序集合分页数据]
     * 
     * @since 2016-09-22 Thu 11:48:39
     * @author PHPJungle
     * @param string $key
     * @param int $page_index [default:1]
     * @param int $page_size  [default:10]
     * @param string $with_scores [true:显示score,false不显示score,default:true]
     * @param int $score_start [double | self::OSETS_RANGE_LEFT_INF]
     * @param int $score_end [double | self::OSETS_RANGE_RIGHT_INF]
     * @param const $score_sort [升序self::SORT:ASC,降序self::SORT_DESC(default)]
     * @return array
     * @abstract <pre>
     *      
     */
    public function oSets_list($key, $page_index = 1, $page_size = 10, $with_scores = true, $score_start = self::OSETS_RANGE_LEFT_INF, $score_end = self::OSETS_RANGE_RIGHT_INF, $score_sort = self::SORT_DESC)
    {
        $with_scores = (bool) $with_scores;
        ($page_index = (int) $page_index) >= 1 or $page_index = 1;
        ($page_size = (int) $page_size) >= 1 or $page_size = 10;
        
        $offset = ($page_index - 1) * $page_size;
        
        $opt['withscores'] = $with_scores;
        $opt['limit'] = array($offset , $page_size);
        
        $key = trim($key);
        $list = array();
        
        switch ($score_sort) {
            case self::SORT_ASC:
                $list = $this->oSets_range_by_score($key, $score_start, $score_end, $opt);
                break;
            case self::SORT_DESC:
                $list = $list = $this->oSets_range_reverse_by_score($key, $score_start, $score_end, $opt);
            default:
        }
        return $list;
    }
}