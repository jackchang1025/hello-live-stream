# PHP-FFMpeg API 参考文档

## 目录
1. [核心类](#核心类)
2. [媒体类](#媒体类)
3. [格式类](#格式类)
4. [滤镜类](#滤镜类)
5. [坐标类](#坐标类)
6. [异常类](#异常类)
7. [事件系统](#事件系统)

## 核心类

### FFMpeg 类

**命名空间**: `FFMpeg\FFMpeg`

#### 静态方法

```php
public static function create(array $configuration = [], ?LoggerInterface $logger = null): FFMpeg
```
创建FFMpeg实例。

**参数**:
- `$configuration`: 配置数组
  - `ffmpeg.binaries`: FFMpeg二进制路径
  - `ffprobe.binaries`: FFProbe二进制路径
  - `timeout`: 超时时间（秒）
  - `ffmpeg.threads`: 线程数
  - `temporary_directory`: 临时目录
- `$logger`: PSR-3 日志记录器实例

**返回**: `FFMpeg` 实例

**示例**:
```php
$ffmpeg = FFMpeg::create([
    'ffmpeg.binaries' => '/usr/bin/ffmpeg',
    'timeout' => 3600,
    'ffmpeg.threads' => 8
]);
```

#### 实例方法

```php
public function open(string $pathfile): Audio|Video
```
打开媒体文件。

**参数**:
- `$pathfile`: 文件路径或URL

**返回**: `Audio` 或 `Video` 对象

**异常**: 
- `RuntimeException`: 文件无法打开
- `InvalidArgumentException`: 无效的文件路径

```php
public function openAdvanced(array $inputs): AdvancedMedia
```
打开高级媒体处理（多输入）。

**参数**:
- `$inputs`: 输入文件路径数组

**返回**: `AdvancedMedia` 对象

```php
public function getFFProbe(): FFProbe
public function setFFProbe(FFProbe $ffprobe): FFMpeg
```
获取/设置FFProbe实例。

---

### FFProbe 类

**命名空间**: `FFMpeg\FFProbe`

#### 静态方法

```php
public static function create(array $configuration = [], ?LoggerInterface $logger = null): FFProbe
```
创建FFProbe实例。

#### 实例方法

```php
public function format(string $pathfile): Format
```
获取媒体格式信息。

**返回**: `Format` 对象，包含：
- `duration`: 时长（秒）
- `size`: 文件大小（字节）
- `bit_rate`: 比特率
- `format_name`: 格式名称

```php
public function streams(string $pathfile): StreamCollection
```
获取媒体流信息。

**返回**: `StreamCollection` 对象

```php
public function isValid(string $pathfile): bool
```
验证媒体文件是否有效。

---

## 媒体类

### Video 类

**命名空间**: `FFMpeg\Media\Video`

**继承**: `AbstractVideo` → `Audio` → `AbstractStreamableMedia` → `AbstractMediaType`

#### 方法

```php
public function filters(): VideoFilters
```
获取视频滤镜管理器。

```php
public function save(FormatInterface $format, string $outputPathfile): Video
```
保存视频文件。

**参数**:
- `$format`: 输出格式对象
- `$outputPathfile`: 输出文件路径

```php
public function frame(TimeCode $at): Frame
```
提取指定时间点的帧。

**参数**:
- `$at`: 时间码对象

**返回**: `Frame` 对象

```php
public function gif(TimeCode $at, Dimension $dimension, ?int $duration = null): Gif
```
生成GIF动画。

**参数**:
- `$at`: 开始时间码
- `$dimension`: 尺寸
- `$duration`: 持续时间（秒，可选）

```php
public function clip(TimeCode $start, ?TimeCode $duration = null): Clip
```
剪辑视频。

**参数**:
- `$start`: 开始时间码
- `$duration`: 持续时间（可选）

```php
public function concat(array $sources): Concat
```
拼接视频。

**参数**:
- `$sources`: 源文件路径数组

---

### Audio 类

**命名空间**: `FFMpeg\Media\Audio`

#### 方法

```php
public function filters(): AudioFilters
```
获取音频滤镜管理器。

```php
public function save(FormatInterface $format, string $outputPathfile): Audio
```
保存音频文件。

```php
public function waveform(int $width = 640, int $height = 120, array $colors = []): Waveform
```
生成音频波形图。

**参数**:
- `$width`: 宽度
- `$height`: 高度
- `$colors`: 颜色数组（十六进制）

```php
public function concat(array $sources): Concat
```
拼接音频。

---

### Frame 类

**命名空间**: `FFMpeg\Media\Frame`

#### 方法

```php
public function save(string $pathfile, bool $accurate = false, bool $returnBase64 = false): string|null
```
保存帧为图片。

**参数**:
- `$pathfile`: 输出路径
- `$accurate`: 是否精确提取（慢但准确）
- `$returnBase64`: 是否返回Base64编码

```php
public function filters(): FrameFilters
```
获取帧滤镜管理器。

---

### AdvancedMedia 类

**命名空间**: `FFMpeg\Media\AdvancedMedia`

#### 方法

```php
public function filters(): ComplexFilters
```
获取复杂滤镜管理器。

```php
public function map(array $outs, FormatInterface $format, string $outputFilename, bool $forceDisableAudio = false, bool $forceDisableVideo = false): AdvancedMedia
```
映射输出流。

**参数**:
- `$outs`: 输出流标识符数组
- `$format`: 输出格式
- `$outputFilename`: 输出文件名
- `$forceDisableAudio`: 强制禁用音频
- `$forceDisableVideo`: 强制禁用视频

```php
public function save(): void
```
执行处理并保存所有输出。

---

## 格式类

### VideoInterface

**命名空间**: `FFMpeg\Format\VideoInterface`

#### 方法

```php
public function getKiloBitrate(): int
public function setKiloBitrate(int $kiloBitrate): self
```
获取/设置视频比特率（kbps）。

```php
public function getVideoCodec(): string
public function setVideoCodec(string $codec): self
```
获取/设置视频编码器。

```php
public function getModulus(): int
```
获取模数（用于分辨率计算）。

```php
public function supportBFrames(): bool
```
是否支持B帧。

```php
public function getAvailableVideoCodecs(): array
```
获取可用的视频编码器列表。

```php
public function getAdditionalParameters(): array
public function setAdditionalParameters(array $parameters): self
```
获取/设置额外参数。

```php
public function getInitialParameters(): array
public function setInitialParameters(array $parameters): self
```
获取/设置初始参数。

### AudioInterface

**命名空间**: `FFMpeg\Format\AudioInterface`

#### 方法

```php
public function getAudioKiloBitrate(): int
public function setAudioKiloBitrate(int $kiloBitrate): self
```
获取/设置音频比特率（kbps）。

```php
public function getAudioChannels(): int
public function setAudioChannels(int $channels): self
```
获取/设置音频声道数。

```php
public function getAudioCodec(): string
public function setAudioCodec(string $codec): self
```
获取/设置音频编码器。

```php
public function getAvailableAudioCodecs(): array
```
获取可用的音频编码器列表。

### 具体格式类

#### X264 类

**命名空间**: `FFMpeg\Format\Video\X264`

**用途**: H.264/AVC 视频格式

**特性**:
- 支持硬件加速
- 支持B帧
- 可配置编码预设
- 支持多通道编码

**示例**:
```php
$format = new X264();
$format->setKiloBitrate(2000)
       ->setAudioChannels(2)
       ->setAudioKiloBitrate(128)
       ->setAdditionalParameters([
           '-preset', 'medium',
           '-crf', '23'
       ]);
```

#### WebM 类

**命名空间**: `FFMpeg\Format\Video\WebM`

**特性**:
- VP8/VP9 编码
- Vorbis/Opus 音频
- Web优化

#### Mp3 类

**命名空间**: `FFMpeg\Format\Audio\Mp3`

**特性**:
- LAME编码器
- VBR/CBR支持
- 元数据支持

---

## 滤镜类

### VideoFilters 类

**命名空间**: `FFMpeg\Filters\Video\VideoFilters`

#### 方法

```php
public function resize(Dimension $dimension, string $mode = ResizeFilter::RESIZEMODE_FIT, bool $forceStandards = true): VideoFilters
```
缩放滤镜。

**模式常量**:
- `ResizeFilter::RESIZEMODE_FIT`: 适配（保持宽高比）
- `ResizeFilter::RESIZEMODE_STRETCH_ASPECT`: 拉伸
- `ResizeFilter::RESIZEMODE_INSET`: 内嵌（加黑边）

```php
public function rotate(int $angle): VideoFilters
```
旋转滤镜。

**角度常量**:
- `RotateFilter::ROTATE_90`: 90°顺时针
- `RotateFilter::ROTATE_180`: 180°
- `RotateFilter::ROTATE_270`: 90°逆时针

```php
public function crop(Point $point, Dimension $dimension): VideoFilters
```
裁剪滤镜。

```php
public function watermark(string $imagePath, array $coordinates = []): VideoFilters
```
水印滤镜。

**坐标参数**:
- `position`: 'relative' 或 'absolute'
- 相对定位: `top`, `bottom`, `left`, `right`
- 绝对定位: `x`, `y`

```php
public function framerate(FrameRate $framerate, int $gop): VideoFilters
```
帧率滤镜。

```php
public function synchronize(): VideoFilters
```
同步滤镜（音视频同步）。

```php
public function pad(Dimension $dimension): VideoFilters
```
填充滤镜（添加黑边）。

```php
public function clip(TimeCode $start, ?TimeCode $duration = null): VideoFilters
```
剪辑滤镜。

```php
public function custom(array $parameters): VideoFilters
```
自定义滤镜。

### AudioFilters 类

**命名空间**: `FFMpeg\Filters\Audio\AudioFilters`

#### 方法

```php
public function resample(int $rate): AudioFilters
```
重采样滤镜。

```php
public function addMetadata(array $metadata = null): AudioFilters
```
元数据滤镜。

**支持的元数据**:
- `title`: 标题
- `artist`: 艺术家
- `album`: 专辑
- `track`: 曲目号
- `year`: 年份
- `description`: 描述
- `artwork`: 封面图片路径

```php
public function clip(TimeCode $start, ?TimeCode $duration = null): AudioFilters
```
音频剪辑滤镜。

---

## 坐标类

### TimeCode 类

**命名空间**: `FFMpeg\Coordinate\TimeCode`

#### 静态方法

```php
public static function fromSeconds(int|float $seconds): TimeCode
```
从秒数创建时间码。

```php
public static function fromString(string $timecode): TimeCode
```
从字符串创建时间码。

**格式**: `HH:MM:SS.mmm` 或 `HH:MM:SS:FF`

#### 实例方法

```php
public function toSeconds(): float
```
转换为秒数。

```php
public function __toString(): string
```
转换为字符串格式。

```php
public function isAfter(TimeCode $timecode): bool
```
比较时间码大小。

### Dimension 类

**命名空间**: `FFMpeg\Coordinate\Dimension`

#### 构造方法

```php
public function __construct(int $width, int $height)
```

#### 方法

```php
public function getWidth(): int
public function getHeight(): int
public function getRatio(bool $forceStandards = true): AspectRatio
```

### Point 类

**命名空间**: `FFMpeg\Coordinate\Point`

#### 构造方法

```php
public function __construct(int|string $x, int|string $y, bool $dynamic = false)
```

**参数**:
- `$x`, `$y`: 坐标值（支持表达式，如 "t*100"）
- `$dynamic`: 是否为动态坐标

#### 方法

```php
public function getX(): int|string
public function getY(): int|string
```

### FrameRate 类

**命名空间**: `FFMpeg\Coordinate\FrameRate`

#### 构造方法

```php
public function __construct(float $value)
```

#### 方法

```php
public function getValue(): float
```

### AspectRatio 类

**命名空间**: `FFMpeg\Coordinate\AspectRatio`

#### 静态方法

```php
public static function create(Dimension $dimension, bool $forceStandards = true): AspectRatio
```

#### 方法

```php
public function getValue(): float
public function calculateWidth(int $height, int $modulus = 1): int
public function calculateHeight(int $width, int $modulus = 1): int
```

---

## 异常类

### 基础异常接口

**命名空间**: `FFMpeg\Exception\ExceptionInterface`

所有PHP-FFMpeg异常都实现此接口。

### 具体异常类

#### RuntimeException

**命名空间**: `FFMpeg\Exception\RuntimeException`

**用途**: 运行时错误，如文件处理失败、编码错误等。

#### InvalidArgumentException

**命名空间**: `FFMpeg\Exception\InvalidArgumentException`

**用途**: 无效参数错误。

#### ExecutableNotFoundException

**命名空间**: `FFMpeg\Exception\ExecutableNotFoundException`

**用途**: 找不到FFMpeg或FFProbe可执行文件。

### 二进制驱动异常

#### ExecutionFailureException

**命名空间**: `Alchemy\BinaryDriver\Exception\ExecutionFailureException`

**用途**: 二进制程序执行失败。

**方法**:
```php
public function getCommand(): string
public function getErrorOutput(): string
```

---

## 事件系统

### ProgressableInterface

**命名空间**: `FFMpeg\Format\ProgressableInterface`

实现此接口的格式类支持进度监听。

#### 方法

```php
public function createProgressListener(MediaTypeInterface $media, FFProbe $ffprobe, int $pass, int $total, int $duration = 0): ListenerInterface[]
```

### 事件监听

所有格式类都继承自 `EventEmitter`，支持事件监听。

#### 进度事件

```php
$format->on('progress', function ($media, $format, $percentage) {
    echo "进度: {$percentage}%\n";
});
```

**回调参数**:
- `$media`: 媒体对象
- `$format`: 格式对象
- `$percentage`: 进度百分比（0-100）

### 自定义事件监听器

#### ListenerInterface

**命名空间**: `Alchemy\BinaryDriver\Listeners\ListenerInterface`

```php
public function handle(string $type, string $data): void
public function forwardedEvents(): array
```

#### DebugListener

**命名空间**: `Alchemy\BinaryDriver\Listeners\DebugListener`

内置的调试监听器，用于捕获FFMpeg的标准输出和错误输出。

**示例**:
```php
use Alchemy\BinaryDriver\Listeners\DebugListener;

$ffmpeg = FFMpeg::create();
$debugListener = new DebugListener();

$ffmpeg->getFFMpegDriver()->listen($debugListener);

$debugListener->on('debug', function ($line) {
    echo "FFMpeg输出: {$line}\n";
});
```

---

## 配置选项详解

### FFMpeg配置

```php
$configuration = [
    // 二进制文件路径
    'ffmpeg.binaries'  => '/usr/local/bin/ffmpeg',
    'ffprobe.binaries' => '/usr/local/bin/ffprobe',
    
    // 超时设置
    'timeout' => 3600,  // 秒，0表示无限制
    
    // 线程设置
    'ffmpeg.threads' => 8,
    
    // 临时目录
    'temporary_directory' => '/tmp/ffmpeg',
    
    // 传递给FFProbe的额外参数
    'ffprobe.timeout' => 60,
];
```

### 环境变量

可以通过环境变量配置：

```bash
export FFMPEG_BINARY=/usr/local/bin/ffmpeg
export FFPROBE_BINARY=/usr/local/bin/ffprobe
export FFMPEG_THREADS=8
export FFMPEG_TIMEOUT=3600
```

### 日志配置

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('ffmpeg');
$logger->pushHandler(new StreamHandler('/var/log/ffmpeg.log', Logger::DEBUG));
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$ffmpeg = FFMpeg::create($configuration, $logger);
```

---

## 性能调优

### 硬件加速

#### NVIDIA GPU (NVENC)

```php
$format = new X264();
$format->setInitialParameters(['-hwaccel', 'cuda'])
       ->setVideoCodec('h264_nvenc')
       ->setAdditionalParameters([
           '-preset', 'p4',
           '-rc', 'vbr',
           '-cq', '23'
       ]);
```

#### Intel Quick Sync

```php
$format->setInitialParameters(['-hwaccel', 'qsv'])
       ->setVideoCodec('h264_qsv');
```

#### AMD GPU (AMF)

```php
$format->setInitialParameters(['-hwaccel', 'd3d11va'])
       ->setVideoCodec('h264_amf');
```

### 多线程优化

```php
$ffmpeg = FFMpeg::create([
    'ffmpeg.threads' => min(8, (int)shell_exec('nproc') ?: 4)
]);
```

### 内存优化

```php
// 限制内存使用
ini_set('memory_limit', '512M');

// 处理大文件时分段处理
$segmentDuration = 300; // 5分钟
$totalDuration = $ffprobe->format($inputFile)->get('duration');
$segments = ceil($totalDuration / $segmentDuration);

for ($i = 0; $i < $segments; $i++) {
    $start = $i * $segmentDuration;
    $duration = min($segmentDuration, $totalDuration - $start);
    
    $clip = $video->clip(
        TimeCode::fromSeconds($start),
        TimeCode::fromSeconds($duration)
    );
    
    $clip->save($format, "segment_{$i}.mp4");
    
    unset($clip);
    gc_collect_cycles();
}
```

这份API参考文档提供了PHP-FFMpeg包的完整接口说明，包括所有主要类的方法、参数、返回值和使用示例。结合使用指南和示例代码，可以帮助开发者充分利用这个强大的多媒体处理库。