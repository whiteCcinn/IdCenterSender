<?php

/**
 * 64 bits 的长整型
 *           0          |000 0000 0000 0000 0000 0000 0000 0000 0000 0000 00 | 00 0000 0000 00  | 0000 0000 00
 * |       1个bit       |                    41个bits                        |      12bits       |   10个bits  |
 * | 0代表正数，1代表负数 |        总共的时间为2^42 - 1,相当于139年              | 支持2^13 -1 个节点 | 支持2 ^11 -1 个进程内自增PID |
 * |  最高位舍弃，符号位  |------------------ 毫秒级时间戳 ---------------------|------- 节点ID -----|-- 进程(毫秒)内自增ID --|
 *
 * 类snowflake算法
 * ID发号器有效期可以延续从发布开始的139年
 * 分布式支持8191台机器
 * 单进程调用的情况下，并发每秒支持200万个ID生成
 */

/**********************************************************\
 *                                                        *
 * cckeyid/IdCenterSender.php                             *
 *                                                        *
 * IdCenterSender class for at least php 5.4+             *
 *                                                        *
 * Author: Cai wenhui <471113744@qq.com>                  *
 *                                                        *
\**********************************************************/

namespace cckeyid;

class IdCenterSender
{

  /**
   * 实例化对象
   *
   * @var null
   */
  private static $instance = null;

  /**
   * 文件句柄
   *
   * @var null
   */
  private $fileHandler = null;

  /**
   * 文件锁
   *
   * @var null
   */
  private $lock = null;

  /**
   * 文件大小
   *
   * @var int
   */
  private $fileSize = 1024;

  /**
   * 生成的id
   *
   * @var array
   */
  private $ids = [];

  /**
   * 指定分布式ID发布器启动的时间
   *
   * @var int
   */
  private $epoch = 0;

  /**
   * 指定分布式机器的节点ID
   *
   * @var int
   */
  private $node_id = 1;

  /**
   * 日志位置
   * @var string
   */
  private static $log_path = '';

  /**
   * 本地缓存文件
   */
  const DATAFILE = '.cache';

  // 毫秒级时间戳42个bits
  const TIMESTAMP_BITS = 41;

  // 节点ID12个bits
  const NODE_ID_BITS = 12;

  // 毫秒内自增ID
  const SEQUENCE_BITS = 9;

  // 毫秒时间戳统计偏移量
  const TIMESTAMP_LEFT_SHIFT = 21;

  // 节点统计偏移量
  const NODE_ID_LEFT_SHIFT = 9;

  /**
   * 私有化构造方法
   */
  private function __construct(){}

  /**
   * 私有化克隆方法
   */
  private function __clone(){}

  /**
   * 饿汉单例设计模式获取对象
   * @param bool $new 是否生成一个新的实例
   *
   * @return IdCenterSender|null
   */
  public static function getInstance($new = false)
  {
    if (!(self::$instance instanceof self) || $new)
    {
      self::$log_path = __DIR__ . DIRECTORY_SEPARATOR;
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * 文件锁
   */
  private function _lock_file()
  {

    if (!file_exists(self::$log_path . self::DATAFILE))
    {
      touch(self::$log_path . self::DATAFILE);
    }

    $this->fileHandler = @fopen(self::$log_path . self::DATAFILE, 'r+');

    // 非阻塞排它锁
    $this->lock = @flock($this->fileHandler, LOCK_EX | LOCK_NB);
  }

  private function _unlock_file()
  {
    @flock($this->fileHandler, LOCK_UN);

    @fclose($this->fileHandler);

    $this->lock = null;

    $this->fileHandler = null;
  }

  /**
   * 获取当前毫秒时间戳
   *
   * @return int
   */
  public function get_curr_timestamp_ms()
  {
    return (int)(microtime(true) * 1000);
  }

  /**
   * 暂停一毫秒
   *
   * @return int
   */
  private function wait_until_next_ms()
  {
    usleep(1000);

    return $this->get_curr_timestamp_ms();
  }

  /**
   * 获取id
   *
   * @param int $num
   *
   * @return array|int
   */
  public function ck_get_new_id($num = 1)
  {
    $now = $this->get_curr_timestamp_ms();

    // 抢夺资源锁
    while (!$this->lock)
    {
      $this->_lock_file();
    }

    $data = ['last_timestamp' => 0, 'sequence' => 0];

    $serializeData = @fread($this->fileHandler, $this->fileSize);

    if (!empty($serializeData))
    {
      $data = unserialize($serializeData);
    }

    if ($data["last_timestamp"] == 0 || $data["last_timestamp"] > $now)
    {
      $last_timestamp = $now;
      $sequence       = rand(0, 10) % 2;
    } else
    {
      $last_timestamp = $data["last_timestamp"];
      $sequence       = $data["sequence"];
    }
    if ($now == $last_timestamp)
    {
      $sequence = ($sequence + 1) & ((-1 ^ (-1 << self::SEQUENCE_BITS)));

      // 如果一毫秒内并发2047个ID都不够分配的话，那么阻塞等待下一秒再分配
      if ($sequence == 0)
      {
        $now = $this->wait_until_next_ms();
      }
    } else
    {
      $sequence = 0;
    }

    @fseek($this->fileHandler, 0);
    $length = @fwrite($this->fileHandler, serialize(["last_timestamp" => $now, "sequence" => $sequence]));

    $id = (($now - ($this->epoch * 1000) & ((-1 ^ (-1 << self::TIMESTAMP_BITS)) ^ (1 << self::TIMESTAMP_BITS))) << self::TIMESTAMP_LEFT_SHIFT)
        | (($this->node_id & (-1 ^ (-1 >> self::NODE_ID_BITS))) << self::NODE_ID_LEFT_SHIFT)
        | ($sequence);

    $this->_unlock_file();

    $this->ids[] = $id;

    if ($num > 1)
    {
      $this->ck_get_new_id($num - 1);
    }

    return count($this->ids) > 1 ? $this->ids : $id;
  }
}



