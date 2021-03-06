<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-12-19
 * Time: 上午11:55
 */

namespace Server\Components\CatCache;

use Server\Components\Event\EventDispatcher;

/**
 * 緩存的RPC代理
 * Class CatCacheRpcProxy
 * @package Server\Components\CatCache
 */
class CatCacheRpcProxy implements \ArrayAccess
{
    private static $rpc;
    /**
     * @var CatCacheHash
     */
    protected $map;
    protected $time = 0;
    protected $time_count = 0;

    public function setMap(&$map)
    {
        $this->map = $map;
    }

    public function start()
    {
        \swoole_timer_tick(1000, function () {
            $timer_back = $this->map['timer_back'] ?? [];
            ksort($timer_back);
            $time = time() * 1000;
            foreach ($timer_back as $key => $value) {
                if ($key > $time) break;
                $value['param_arr'][] = $key;
                EventDispatcher::getInstance()->randomDispatch(TimerCallBack::KEY, $value);
            }
        });
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this, $name], $arguments);
    }

    /**
     * 完成回调
     * @param $key
     */
    public function ackTimerCallBack($key)
    {
        unset($this->map["timer_back.$key"]);
    }

    /**
     * @param $time
     * @param $data
     * @return string
     */
    public function setTimerCallBack($time, $data)
    {
        if ($time != $this->time) {
            $this->time = $time;
            $this->time_count = 0;
        }
        $this->time_count++;
        $time = $time * 1000 + $this->time_count;
        $key = "timer_back.$time";
        $this->map[$key] = $data;
        return $key;
    }

    /**
     * @return array
     */
    public function getAll()
    {
        return $this->map->getContainer();
    }

    /**
     * @param $offset
     * @return mixed
     */
    public function offsetExists($offset)
    {
        return $this->map->offsetExists($offset);
    }

    /**
     * @param $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->map->offsetGet($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @oneWay
     */
    public function offsetSet($offset, $value)
    {
        $this->map->offsetSet($offset, $value);
    }

    /**
     * @param mixed $offset
     * @oneWay
     */
    public function offsetUnset($offset)
    {
        $this->map->offsetUnset($offset);
    }

    /**
     * @return CatCacheRpcProxy
     */
    public static function getRpc()
    {
        if (self::$rpc == null) {
            self::$rpc = new CatCacheRpc();
        }
        return self::$rpc;
    }
}
