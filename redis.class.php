<?php

class C_Redis
{
    static private  $_host;
    static private  $_port;
    static private  $_redis;
    static private $_prefix = '';
    static private $_auth = false;
    static private $_autoclose = true;
    static private $_connected = false;
    static private $_pconnect = true;
    static private $_pconnect_timeout = 3;
    static private $_cache = array();

    static public function register($config=array()){
        if (!extension_loaded('redis')) {
            exit('服务器不支持redis扩展');
        }
        self::$_host = $config['host']?$config['host']:'127.0.0.1';
        self::$_port = $config['port']?$config['port']:'6379';
        if (isset($config['prefix'])) self::$_prefix = $config['prefix'];
        if (isset($config['pwd'])) self::$_auth = $config['pwd'];
        if (isset($config['autoclose'])) self::$_autoclose = $config['autoclose'];
    }

    /*
     * 连接函数，执行连接
     * 连接redis与选择数据库，并确认是否可以正常连接，连接不上就返回false
     */
    static public function _connect(){

        if (!self::$_host || !self::$_port) return false;
        if (self::$_connected) return true;
        if (self::$_redis === null) {
            self::$_redis = new Redis;
        }
        if (self::$_pconnect) {
            self::$_connected = self::$_redis->pconnect(self::$_host, self::$_port, self::$_pconnect_timeout );
        } else {
            self::$_connected = self::$_redis->connect(self::$_host, self::$_port);
        }
        if(self::$_auth) self::$_redis->auth(self::$_auth);
        return self::$_connected;
    }

    /*
     * 关闭函数，执行连接
     * 连接redis与选择数据库，并确认是否可以正常连接，连接不上就返回false
     */
    static public function close() {
        if (!self::$_connected) return;
        if (self::$_redis) {
            self::$_redis->close();
        }
        self::$_connected = false;
    }

    /*
     * 判断key是否存在
     * $key 键值
     */
    static public function exists($key){
        if (!self::_connect()) return false;
        $ret= self::$_redis->exists(self::$_prefix.$key);
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 判断key剩余有效时间，单位秒
     * $key 键值
     */
    static public function ttl($key){
        if (!self::_connect()) return false;
        $ret= self::$_redis->ttl(self::$_prefix.$key);
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 获取字符串对象
     * $key 键值
     */
    static public function get($key){
        if (!self::_connect()) return false;
        $ret= json_decode(self::$_redis->get(self::$_prefix.$key),true);
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 设置字符串，带生存时间
     * $key 键值
     */
    static public function set($key,$value, $time=0){
        if (!self::_connect()) return false;
        $str=json_encode($value);
        if($time==0){
            $ret=self::$_redis->set(self::$_prefix.$key,$str);
        }
        else{
            $ret=self::$_redis->setex(self::$_prefix.$key,$time,$str);
        }
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 设置锁
     * $key 键值
     * $str， 字符串
     */
    static public function setnx($key,$value){
        if (!self::_connect()) return false;
        $ret= self::$_redis->setnx(self::$_prefix.$key,$value);

        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 删除key
     * $key 键值
     */
    static public function  delete($key){
        if (!self::_connect()) return false;
        if(!is_array($key)){
            $ret= self::$_redis->delete(self::$_prefix.$key);
        }else{
            foreach ($key as $k){
                $array[]=self::$_prefix.$k;
            }
            $ret= self::$_redis->delete($array);
        }
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 链表增加多个元素
     * $key 键值
     */
    static public function  push($key,$array,$direction='left'){
        if (!self::_connect()) return false;
        if(!is_array($array)){
            $array=array($array);
        }
        foreach($array as $val){
            $ret= ($direction == 'left') ? self::$_redis->lPush(self::$_prefix.$key, Common::json_encode_all($val)) : self::$_redis->rPush(self::$_prefix.$key, Common::json_encode_all($val));
        }
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 链表增加多个元素 中文字符不编码
     * $key 键值
     */
    static public function  push_uncoded($key,$array,$direction='left'){
        if (!self::_connect()) return false;
        if(!is_array($array)){
            $array=array($array);
        }
        foreach($array as $val){
            $ret= ($direction == 'left') ? self::$_redis->lPush(self::$_prefix.$key, $val) : self::$_redis->rPush(self::$_prefix.$key, $val);
        }
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 链表弹出多个元素
     * $key 键值
     * 返回数组
     */
    static public function  pop($key,$num=1,$direction='right') {
        if (!self::_connect()) return false;
        for($i=0;$i<$num;$i++){
            $value = ($direction == 'right') ? self::$_redis->rPop(self::$_prefix.$key) : self::$_redis->lPop(self::$_prefix.$key);
            $data[]=json_decode($value,true);
        }
        if (self::$_autoclose) self::close();
        return 1==$num?$data[0]:$data;
    }


    /**
     *
     * llen
     * @param string $key
     */
    static public function  list_count($key){
        if (!self::_connect()) return false;
        $ret= self::$_redis->lLen(self::$_prefix.$key);
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /**
     * 获取链表元素
     * lrange
     * @param string $key
     */

    static public function  list_get($key,$start,$stop){
        if (!self::_connect()) return false;
        $ret= self::$_redis->lRANGE(self::$_prefix.$key, $start, $stop);
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 哈希表新增或修改元素
     * $key 键值
     * $array 关联数组
     */
    static public function  hash_set($key,$array){
        if (!self::_connect()) return false;
        if(is_array($array)){
            foreach ($array as &$v){
                $v = Common::json_encode_all($v);
            }
            $ret= self::$_redis->hMset(self::$_prefix.$key,$array);
        }
        else{
            return false;
        }
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 哈希表读取元素
     * $key 键值
     */
    static public function  hash_get($key,$array){
        if (!self::_connect()) return false;
        if(!is_array($array)){
            return json_decode(self::$_redis->hGet(self::$_prefix.$key,$array),true);
        }
        $ret= self::$_redis->hmGet(self::$_prefix.$key,$array);
        foreach ($ret as &$v){
            $v=json_decode($v,true);
        }
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /**
     * 哈希读取 HGET add sam
     */
    static public function h_get($key,$hash_key) {
        if (!self::_connect()) return false;
        $ret= self::$_redis->hGet(self::$_prefix.$key,$hash_key);
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 哈希表元素加减法
     * $key 键值
     * 给哈希键为key的field字段加上数字value，value为负数，就是减法
     */
    static public function hash_incr($key,$field,$value=1){
        if (!self::_connect()) return false;
        $ret= self::$_redis->hIncrBy(self::$_prefix.$key,$field,$value);
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 哈希表判断某字段是否存在
     * $key 键值
     */
    static public function  hash_exists($key,$field){
        if (!self::_connect()) return false;
        $ret= self::$_redis->hExists(self::$_prefix.$key,$field);
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 哈希表删除某字段
     * $key 键值
     */
    static public function  hash_delete($key,$field){
        if (!self::_connect()) return false;
        $ret= self::$_redis->hDel(self::$_prefix.$key,$field);
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 获取哈希表所有字段以及值
     * $key 键值
     */
    static public function  hash_getall($key){
        if (!self::_connect()) return false;
        $ret= self::$_redis->hGetAll(self::$_prefix.$key);
        if (self::$_autoclose) self::close();
        return $ret;
    }
    /**
     *
     * hlen
     * @param string $key
     */
    static public function  hash_count($key){
        if (!self::_connect()) return false;
        $ret= self::$_redis->hLen(self::$_prefix.$key);
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /*
     * 自加一，默认加一
     * $key 键值
     */
    static public function  incr($key,$value=1){
        if (!self::_connect()) return false;
        $ret=self::$_redis->incrby(self::$_prefix.$key,$value);
        if (self::$_autoclose) self::close();
        return $ret;

    }

    /*
     * 自减一，默认减一
     * $key 键值
     */
    static public function decr($key,$value=1){
        if (!self::_connect()) return false;
        $ret=self::$_redis->decrby(self::$_prefix.$key,$value);
        if (self::$_autoclose) self::close();
        return $ret;

    }

    /*
     * 清空当前db
     * $key 键值
     */
    static public function clean(){
        if (!self::_connect()) return false;
        $ret=self::$_redis->flushdb();
        if (self::$_autoclose) self::close();
        return $ret;
    }
    /**
     *
     * sAdd
     * @param string $key
     * @param string $field
     */
    static public function set_set($key,$field){
        if(!$key||!$field) return false;
        if (!self::_connect()) return false;
        $ret=self::$_redis->sAdd(self::$_prefix.$key , $field);
        if (self::$_autoclose) self::close();
        return $ret;
    }
    /**
     *
     *sMembers
     * @param string $key
     */
    static public function set_get($key){
        if(!$key) return false;
        if (!self::_connect()) return false;
        $ret=self::$_redis->sMembers(self::$_prefix.$key);
        if (self::$_autoclose) self::close();
        return $ret;
    }
    /**
     *
     * sIsmember
     * @param string $key
     * @param string $field
     */
    static public function set_exists($key,$field){
        if(!$key||!$field) return false;
        if (!self::_connect()) return false;
        $ret=self::$_redis->sIsMember(self::$_prefix.$key,$field);
        if (self::$_autoclose) self::close();
        return $ret;
    }
    /**
     *
     * sRem
     * @param string $key
     * @param string $field
     */
    static public function set_delete($key,$field){
        if(!$key||!$field) return false;
        if (!self::_connect()) return false;
        $ret=self::$_redis->sRem(self::$_prefix.$key,$field);
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /**
     * SPOP
     * @param string $key
     */
    static public function set_spop($key) {
        if(!$key) return false;
        if (!self::_connect()) return false;
        $ret=self::$_redis->sPop(self::$_prefix.$key);
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /**
     * SRANDMEMBER
     * @param string $key
     * @return boolean|unknown
     */
    static public function set_srand($key) {
        if(!$key) return false;
        if (!self::_connect()) return false;
        $ret=self::$_redis->sRandmember(self::$_prefix.$key);
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /**
     *
     * sCard
     * @param string $key
     */
    static public function set_count($key){
        if(!$key) return false;
        if (!self::_connect()) return false;
        $ret=self::$_redis->sCard(self::$_prefix.$key);
        if (self::$_autoclose) self::close();
        return $ret;
    }

    /**
     * 在未定义方法时可以命名用原生Redis
     */
    static public function redis(){
        if (!self::_connect()) return false;
        return self::$_redis;
    }
    /**
     * 获取数据类型
     * @param unknown_type $key
     */
    static public function type($key){
        if(!$key) return false;
        if (!self::_connect()) return false;
        $ret=self::$_redis->type(self::$_prefix.$key);
        if (self::$_autoclose) self::close();
        return $ret;
    }
    /**
     * 之所构造getx函数，是因为在不知道数据类型时，无法直接用get
     * @param unknown_type $key
     */
    static public function getx($key){
        if(!$key) return false;
        if (!self::_connect()) return false;
        $type=self::$_redis->type(self::$_prefix.$key);
        switch ($type){
            case Redis::REDIS_NOT_FOUND:
                $ret=null;
                break;
            case Redis::REDIS_STRING:
                $ret=self::get($key);
                break;
            case Redis::REDIS_SET:
                $ret=self::set_get($key);
                break;
            case Redis::REDIS_LIST:
                $len=self::list_count($key);
                $ret=self::pop($key,$len);
                self::push($key, $ret);//保持原内容不变
                break;
            case Redis::REDIS_ZSET:
                $ret=null;//暂不支持
                break;
            case Redis::REDIS_HASH:
                $ret=self::hash_getall($key);
                break;
        }
        if (self::$_autoclose) self::close();
        return $ret;
    }
    static public function info(){
        return array('host'=>self::$_host,'port'=>self::$_port,'prefix'=>self::$_prefix);
    }
}