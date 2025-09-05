# Live Stream PHP Library

一个用于获取直播平台数据的PHP库，支持抖音、TikTok、快手、B站等多个直播平台。

## 功能特性

- 🎯 **多平台支持**：支持抖音、TikTok、快手、B站等主流直播平台
- 🔄 **异步处理**：支持异步HTTP请求，提高性能
- 🛡️ **反爬虫对抗**：内置X-Bogus签名生成等反爬虫机制
- 🔧 **模块化设计**：易于扩展新平台
- 📝 **完整日志**：详细的日志记录和错误处理
- 🚀 **高性能**：基于Guzzle HTTP客户端，性能优秀

## 安装

### 通过Composer安装

```bash
composer require your-vendor/live-stream
```

### 手动安装

```bash
git clone https://github.com/your-username/live-stream.git
cd live-stream
composer install
```

## 快速开始

### 基本使用

```php
<?php

require_once 'vendor/autoload.php';

use LiveStream\LiveStream;

// 创建实例
$liveStream = new LiveStream();

// 获取直播数据
try {
    $liveData = $liveStream->getLiveData('https://live.douyin.com/123456789');
    echo "主播: " . $liveData['anchor_name'] . "\n";
    echo "标题: " . $liveData['title'] . "\n";
    echo "是否直播: " . ($liveData['status'] == 2 ? '是' : '否') . "\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
```

### 获取流地址

```php
<?php

use LiveStream\LiveStream;

$liveStream = new LiveStream();

try {
    $streamData = $liveStream->getStreamUrl(
        'https://live.douyin.com/123456789',
        '原画', // 画质：原画|超清|高清|标清|流畅
        'http://127.0.0.1:7890' // 代理地址（可选）
    );
    
    if ($streamData['is_live']) {
        echo "M3U8地址: " . $streamData['m3u8_url'] . "\n";
        echo "FLV地址: " . $streamData['flv_url'] . "\n";
        echo "录制地址: " . $streamData['record_url'] . "\n";
    }
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
```

### 检查直播状态

```php
<?php

use LiveStream\LiveStream;

$liveStream = new LiveStream();

// 检查单个直播间
$isLive = $liveStream->isLive('https://live.douyin.com/123456789');
echo $isLive ? "正在直播" : "未在直播";

// 批量检查
$urls = [
    'https://live.douyin.com/123456789',
    'https://live.douyin.com/987654321'
];

$results = $liveStream->batchCheckLiveStatus($urls);
foreach ($results as $url => $result) {
    echo $url . ": " . ($result['is_live'] ? '直播中' : '未直播') . "\n";
}
```

### 获取主播信息

```php
<?php

use LiveStream\LiveStream;

$liveStream = new LiveStream();

$anchorInfo = $liveStream->getAnchorInfo('https://live.douyin.com/123456789');
echo "平台: " . $anchorInfo['platform'] . "\n";
echo "主播: " . $anchorInfo['anchor_name'] . "\n";
echo "标题: " . $anchorInfo['title'] . "\n";
echo "状态: " . ($anchorInfo['is_live'] ? '直播中' : '未直播') . "\n";
```

## 配置

### 使用代理

```php
<?php

use LiveStream\LiveStream;

$liveStream = new LiveStream();

// 使用HTTP代理
$streamData = $liveStream->getStreamUrl(
    'https://live.douyin.com/123456789',
    '原画',
    'http://127.0.0.1:7890'
);

// 使用SOCKS5代理
$streamData = $liveStream->getStreamUrl(
    'https://live.douyin.com/123456789',
    '原画',
    'socks5://127.0.0.1:1080'
);
```

### 使用Cookie

```php
<?php

use LiveStream\LiveStream;

$liveStream = new LiveStream();

$cookies = 'ttwid=xxx; __ac_nonce=xxx; __ac_signature=xxx;';

$liveData = $liveStream->getLiveData(
    'https://live.douyin.com/123456789',
    null, // 代理
    $cookies
);
```

### 配置日志

```php
<?php

use LiveStream\LiveStream;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// 创建日志实例
$logger = new Logger('live-stream');
$logger->pushHandler(new StreamHandler('logs/live-stream.log', Logger::DEBUG));

// 使用自定义日志
$liveStream = new LiveStream($logger);
```

## API文档

### LiveStream类

#### 构造函数

```php
public function __construct(?LoggerInterface $logger = null)
```

#### 主要方法

##### getLiveData()

获取直播数据

```php
public function getLiveData(string $url, ?string $proxy = null, ?string $cookies = null): array
```

**参数：**
- `$url` (string): 直播间URL
- `$proxy` (string|null): 代理地址
- `$cookies` (string|null): Cookie字符串

**返回：**
- `array`: 直播数据数组

##### getStreamUrl()

获取流地址

```php
public function getStreamUrl(string $url, string $quality = '原画', ?string $proxy = null, ?string $cookies = null): array
```

**参数：**
- `$url` (string): 直播间URL
- `$quality` (string): 画质（原画|超清|高清|标清|流畅）
- `$proxy` (string|null): 代理地址
- `$cookies` (string|null): Cookie字符串

**返回：**
- `array`: 流地址信息

##### isLive()

检查是否正在直播

```php
public function isLive(string $url, ?string $proxy = null, ?string $cookies = null): bool
```

##### getAnchorInfo()

获取主播信息

```php
public function getAnchorInfo(string $url, ?string $proxy = null, ?string $cookies = null): array
```

##### batchCheckLiveStatus()

批量检查直播状态

```php
public function batchCheckLiveStatus(array $urls, ?string $proxy = null, ?string $cookies = null): array
```

## 支持的平台

| 平台 | 状态 | 支持URL格式 |
|------|------|-------------|
| 抖音 | ✅ | `live.douyin.com/*`, `v.douyin.com/*` |
| TikTok | 🚧 | `tiktok.com/*` |
| 快手 | 🚧 | `live.kuaishou.com/*` |
| B站 | 🚧 | `live.bilibili.com/*` |
| 虎牙 | 🚧 | `huya.com/*` |
| 斗鱼 | 🚧 | `douyu.com/*` |

## 扩展新平台

### 1. 创建平台类

```php
<?php

namespace LiveStream\Platforms;

use LiveStream\Platforms\AbstractPlatform;

class MyPlatform extends AbstractPlatform
{
    protected array $supportedPatterns = [
        '/myplatform\.com/',
    ];
    
    public function getPlatformName(): string
    {
        return 'MyPlatform';
    }
    
    public function getLiveData(string $url, ?string $proxy = null, ?string $cookies = null): array
    {
        // 实现获取直播数据的逻辑
    }
    
    public function getStreamUrl(array $data, string $quality = '原画', ?string $proxy = null): array
    {
        // 实现获取流地址的逻辑
    }
}
```

### 2. 注册平台

```php
<?php

use LiveStream\LiveStream;
use LiveStream\Platforms\MyPlatform;

$liveStream = new LiveStream();
$liveStream->registerPlatform(new MyPlatform());
```

## 错误处理

库使用自定义异常类来处理错误：

```php
<?php

use LiveStream\Exceptions\PlatformException;

try {
    $liveData = $liveStream->getLiveData('https://example.com/live');
} catch (PlatformException $e) {
    echo "平台错误: " . $e->getMessage() . "\n";
    echo "平台: " . $e->getPlatform() . "\n";
    echo "URL: " . $e->getUrl() . "\n";
} catch (Exception $e) {
    echo "其他错误: " . $e->getMessage() . "\n";
}
```

## 测试

运行测试：

```bash
composer test
```

运行代码质量检查：

```bash
composer cs-check
composer stan
```

## 贡献

欢迎提交Issue和Pull Request！

### 开发环境设置

```bash
git clone https://github.com/your-username/live-stream.git
cd live-stream
composer install
composer test
```

## 许可证

MIT License

## 更新日志

### v1.0.0
- 初始版本
- 支持抖音直播平台
- 基础API接口
- 代理和Cookie支持

## 相关项目

- [DouyinLiveRecorder](https://github.com/ihmily/DouyinLiveRecorder) - Python版本的直播录制工具 