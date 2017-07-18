<h1 align="center">IdCenterSender ---PHP实现-64位分布式自增发号器</h1>

### C语言实现的PHP扩展的形式

- [IdCenterSender.so - PHP扩展版本](https://github.com/whiteCcinn/IdCenterSender-so)

PHP实现64位分布式ID发号器

## 原理

参考Snowflake算法,根据自身设计情况扩展了其中的细节。具体组成如下图：
	
![bits_struct.jpg](https://raw.githubusercontent.com/whiteCcinn/IdCenterSender/master/pic/bits_struct.png)

> 如图所示，64bits 分成了4个部分。

> 0. 最高位舍弃
> 1. 毫秒级的时间戳,有41个bit.能够使用139年，当然这些是可以扩展的,可以通知指定起始时间来延长这个日期长度。也就是说服务启动开始之后就可以持续使用139年
> 2. 自定义分布式机器节点id,占位12个bit,能够支持8191个节点。部署的时候可以配置好服务器id,也就是代码里面的node_id变量，每一台机器都需要用不同的node_id来标志，就像mysql的server_id一样
> 3. 进程（毫秒）自增序号。占位10bit,一毫秒能产生2047个id。

## 总结特点：
- 类snowflake算法
- ID发号器有效期可以延续从发布开始的139年
- 分布式支持8191台机器
- 单进程调用的情况下，并发每秒支持200万个ID生成

## 唯一性保证
> 同一毫秒内自增变量保证并发的唯一性(采用文件锁的方式对cache文件进行锁定)。

## 使用

```
include_once '../cckeyid/IdCenterSender.php';

echo \cckeyid\IdCenterSender::getInstance()->ck_get_new_id(1);

echo PHP_EOL;

print_r(\cckeyid\IdCenterSender::getInstance(true)->ck_get_new_id(4));

```
