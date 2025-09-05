# PHP-FFMpeg 扩展包详细使用指南

## 目录
1. [项目架构分析](#项目架构分析)
2. [安装与配置](#安装与配置)
3. [基础使用](#基础使用)
4. [视频处理](#视频处理)
5. [音频处理](#音频处理)
6. [滤镜系统](#滤镜系统)
7. [格式系统](#格式系统)
8. [高级功能](#高级功能)
9. [API参考](#api参考)
10. [最佳实践](#最佳实践)

## 项目架构分析

### 整体架构
PHP-FFMpeg是一个面向对象的PHP库，用于与FFMpeg/AVConv进行交互，实现视频和音频文件的处理。整个架构采用分层设计：

```
┌─────────────────────────────────────────────────┐
│                应用层                           │
├─────────────────────────────────────────────────┤
│        媒体处理层 (Media Layer)                 │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ │
│  │ Video   │ │ Audio   │ │ Frame   │ │ Gif     │ │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘ │
├─────────────────────────────────────────────────┤
│        滤镜层 (Filters Layer)                   │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ │
│  │ Video   │ │ Audio   │ │ Frame   │ │Waveform │ │
│  │Filters  │ │Filters  │ │Filters  │ │Filters  │ │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘ │
├─────────────────────────────────────────────────┤
│        格式层 (Format Layer)                    │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ │
│  │ X264    │ │ WebM    │ │ MP3     │ │ FLAC    │ │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘ │
├─────────────────────────────────────────────────┤
│        核心层 (Core Layer)                      │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ │
│  │ FFMpeg  │ │ FFProbe │ │ Driver  │ │Coordinate│ │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘ │
├─────────────────────────────────────────────────┤
│     二进制驱动层 (Binary Driver Layer)          │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ │
│  │Abstract │ │Process  │ │Config   │ │Listener │ │
│  │Binary   │ │Builder  │ │uration  │ │Manager  │ │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘ │
└─────────────────────────────────────────────────┘
```

### 核心组件

#### 1. 二进制驱动层 (Alchemy\BinaryDriver)
负责与系统底层FFMpeg二进制程序的交互：
- `AbstractBinary`：抽象二进制驱动程序基类
- `ProcessBuilderFactory`：进程构建工厂
- `Configuration`：配置管理
- `Listeners`：事件监听器管理

#### 2. 核心层 (FFMpeg)
提供主要的API接口：
- `FFMpeg`：主要入口类，用于打开和处理媒体文件
- `FFProbe`：媒体信息探测工具
- `Driver`：FFMpeg和FFProbe的驱动程序

#### 3. 媒体层 (Media)
处理不同类型的媒体对象：
- `Video`：视频文件处理
- `Audio`：音频文件处理
- `Frame`：单帧图像处理
- `Gif`：GIF动画生成
- `AdvancedMedia`：高级多输入输出处理

#### 4. 滤镜层 (Filters)
提供各种媒体处理滤镜：
- `VideoFilters`：视频滤镜（缩放、旋转、裁剪等）
- `AudioFilters`：音频滤镜（重采样、元数据等）
- `FrameFilters`：帧滤镜
- `WaveformFilters`：波形滤镜

#### 5. 格式层 (Format)
定义输出格式：
- `VideoInterface`：视频格式接口
- `AudioInterface`：音频格式接口
- 具体格式实现：X264、WebM、MP3、FLAC等

#### 6. 坐标系统 (Coordinate)
处理时间和空间坐标：
- `TimeCode`：时间码
- `Dimension`：尺寸
- `Point`：点坐标
- `FrameRate`：帧率
- `AspectRatio`：宽高比

## 安装与配置

### 系统要求
- PHP 8.0 或更高版本
- FFMpeg 和 FFProbe 二进制程序
- Composer 包管理器

### 安装FFMpeg二进制程序

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install ffmpeg
```

**CentOS/RHEL:**
```bash
sudo yum install epel-release
sudo yum install ffmpeg ffmpeg-devel
```

**macOS:**
```bash
brew install ffmpeg
```

**Windows:**
下载FFMpeg二进制包：https://ffmpeg.org/download.html

### 安装PHP-FFMpeg包

```bash
composer require php-ffmpeg/php-ffmpeg
```

### 基础配置

```php
<?php
declare(strict_types=1);

require_once 'vendor/autoload.php';

use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;

// 基础配置
$ffmpeg = FFMpeg::create();

// 自定义配置
$ffmpeg = FFMpeg::create([
    'ffmpeg.binaries'  => '/usr/local/bin/ffmpeg',    // FFMpeg二进制路径
    'ffprobe.binaries' => '/usr/local/bin/ffprobe',   // FFProbe二进制路径
    'timeout'          => 3600,                       // 超时时间（秒）
    'ffmpeg.threads'   => 12,                         // 线程数
    'temporary_directory' => '/tmp/ffmpeg',            // 临时文件目录
]);

// 添加日志
$logger = new \Monolog\Logger('ffmpeg');
$ffmpeg = FFMpeg::create($configuration, $logger);
```

## 基础使用

### 打开媒体文件

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;

$ffmpeg = FFMpeg::create();

// 打开本地文件
$video = $ffmpeg->open('video.mp4');
$audio = $ffmpeg->open('audio.mp3');

// 打开远程文件
$video = $ffmpeg->open('https://example.com/video.mp4');

// 打开流媒体
$video = $ffmpeg->open('rtmp://stream.example.com/live/stream');
```

### 获取媒体信息

```php
<?php
declare(strict_types=1);

use FFMpeg\FFProbe;

$ffprobe = FFProbe::create();

// 获取格式信息
$format = $ffprobe->format('video.mp4');
echo '时长: ' . $format->get('duration') . '秒' . PHP_EOL;
echo '比特率: ' . $format->get('bit_rate') . PHP_EOL;

// 获取流信息
$streams = $ffprobe->streams('video.mp4');

// 获取视频流信息
$videoStream = $streams->videos()->first();
if ($videoStream) {
    echo '视频编码: ' . $videoStream->get('codec_name') . PHP_EOL;
    echo '分辨率: ' . $videoStream->get('width') . 'x' . $videoStream->get('height') . PHP_EOL;
    echo '帧率: ' . $videoStream->get('r_frame_rate') . PHP_EOL;
}

// 获取音频流信息
$audioStream = $streams->audios()->first();
if ($audioStream) {
    echo '音频编码: ' . $audioStream->get('codec_name') . PHP_EOL;
    echo '采样率: ' . $audioStream->get('sample_rate') . 'Hz' . PHP_EOL;
    echo '声道数: ' . $audioStream->get('channels') . PHP_EOL;
}
```

### 验证媒体文件

```php
<?php
declare(strict_types=1);

use FFMpeg\FFProbe;

$ffprobe = FFProbe::create();

// 验证文件是否为有效的媒体文件
if ($ffprobe->isValid('video.mp4')) {
    echo '文件有效' . PHP_EOL;
} else {
    echo '文件无效或损坏' . PHP_EOL;
}
```

## 视频处理

### 视频转码

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use FFMpeg\Format\Video\WebM;
use FFMpeg\Format\Video\WMV;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('input.mp4');

// 转换为H.264格式
$format = new X264();
$format->setKiloBitrate(1000)          // 视频比特率
       ->setAudioChannels(2)           // 音频声道
       ->setAudioKiloBitrate(128);     // 音频比特率

$video->save($format, 'output.mp4');

// 批量转码
$video->save(new X264(), 'output-x264.mp4')
      ->save(new WebM(), 'output-webm.webv')
      ->save(new WMV(), 'output-wmv.wmv');
```

### 进度监控

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('input.mp4');

$format = new X264();

// 添加进度监听器
$format->on('progress', function ($video, $format, $percentage): void {
    echo "转码进度: {$percentage}%" . PHP_EOL;
    
    // 可以在这里实现进度条更新、数据库记录等
    if ($percentage % 10 === 0) {
        file_put_contents('progress.txt', "转码进度: {$percentage}%\n", FILE_APPEND);
    }
});

$video->save($format, 'output.mp4');
```

### 提取视频帧

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('video.mp4');

// 提取指定时间点的帧
$frame = $video->frame(TimeCode::fromSeconds(10));
$frame->save('frame-10s.jpg');

// 提取多个时间点的帧
$timePoints = [5, 10, 15, 20, 25];
foreach ($timePoints as $second) {
    $frame = $video->frame(TimeCode::fromSeconds($second));
    $frame->save("frame-{$second}s.jpg");
}

// 高精度帧提取（更慢但更准确）
$frame = $video->frame(TimeCode::fromSeconds(10));
$frame->save('accurate-frame.jpg', true);

// 提取为Base64字符串
$base64Data = $frame->save('frame.jpg', false, true);
echo $base64Data;
```

### 批量提取帧

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Filters\Video\ExtractMultipleFramesFilter;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('video.mp4');

// 每10秒提取一帧
$video->filters()
      ->extractMultipleFrames(
          ExtractMultipleFramesFilter::FRAMERATE_EVERY_10SEC,
          '/path/to/destination/folder/'
      )
      ->synchronize();

// 自定义帧文件类型
$filter = new ExtractMultipleFramesFilter(
    ExtractMultipleFramesFilter::FRAMERATE_EVERY_2SEC,
    '/path/to/destination/folder/'
);
$filter->setFrameFileType('png');
$video->addFilter($filter);

$video->save(new X264(), '/path/to/new/file.mp4');
```

### 视频剪辑

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('input.mp4');

// 剪辑：从30秒开始，持续15秒
$clip = $video->clip(
    TimeCode::fromSeconds(30),    // 开始时间
    TimeCode::fromSeconds(15)     // 持续时间
);

$clip->save(new X264(), 'clip.mp4');

// 可以对剪辑应用其他滤镜
$clip->filters()
     ->resize(new \FFMpeg\Coordinate\Dimension(720, 480))
     ->synchronize();

$clip->save(new X264(), 'resized-clip.mp4');
```

### 生成GIF动画

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Coordinate\Dimension;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('video.mp4');

// 从第2秒开始，生成640x480的GIF，持续3秒
$gif = $video->gif(
    TimeCode::fromSeconds(2),        // 开始时间
    new Dimension(640, 480),         // 尺寸
    3                                // 持续时间（秒）
);

$gif->save('animation.gif');

// 生成静态GIF（单帧）
$gif = $video->gif(
    TimeCode::fromSeconds(5),
    new Dimension(320, 240)
    // 不指定持续时间，生成单帧GIF
);

$gif->save('static.gif');
```

## 音频处理

### 音频转码

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use FFMpeg\Format\Audio\Flac;
use FFMpeg\Format\Audio\Wav;
use FFMpeg\Format\Audio\Aac;

$ffmpeg = FFMpeg::create();

// 从视频中提取音频
$video = $ffmpeg->open('video.mp4');
$audioFormat = new Mp3();
$audioFormat->setAudioChannels(2)
            ->setAudioKiloBitrate(192);

$video->save($audioFormat, 'extracted-audio.mp3');

// 音频格式转换
$audio = $ffmpeg->open('input.wav');

// 转换为多种格式
$audio->save(new Mp3(), 'output.mp3')
      ->save(new Flac(), 'output.flac')
      ->save(new Wav(), 'output.wav')
      ->save(new Aac(), 'output.aac');
```

### 音频剪辑

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Audio\Mp3;

$ffmpeg = FFMpeg::create();
$audio = $ffmpeg->open('input.mp3');

// 音频剪辑：从30秒开始，持续60秒
$audio->filters()
      ->clip(
          TimeCode::fromSeconds(30),
          TimeCode::fromSeconds(60)
      );

$audio->save(new Mp3(), 'clip.mp3');
```

### 音频重采样

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;

$ffmpeg = FFMpeg::create();
$audio = $ffmpeg->open('input.mp3');

// 重采样到44.1kHz
$audio->filters()->resample(44100);

$audio->save(new Mp3(), 'resampled.mp3');
```

### 音频元数据处理

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;

$ffmpeg = FFMpeg::create();
$audio = $ffmpeg->open('input.mp3');

// 添加元数据
$metadata = [
    'title' => '歌曲标题',
    'artist' => '艺术家',
    'album' => '专辑名称',
    'track' => 1,
    'year' => 2024,
    'description' => '歌曲描述',
    'artwork' => '/path/to/cover.jpg'  // 添加封面图片
];

$audio->filters()->addMetadata($metadata);
$audio->save(new Mp3(), 'output-with-metadata.mp3');

// 移除所有元数据
$audio->filters()->addMetadata();
$audio->save(new Mp3(), 'output-no-metadata.mp3');
```

### 生成音频波形图

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;

$ffmpeg = FFMpeg::create();
$audio = $ffmpeg->open('input.mp3');

// 生成波形图（必须保存为PNG格式）
$waveform = $audio->waveform(
    640,                    // 宽度
    120,                    // 高度
    ['#00FF00']            // 颜色数组（十六进制）
);

$waveform->save('waveform.png');

// 从视频生成音频波形图
$video = $ffmpeg->open('video.mp4');
$video->save(new \FFMpeg\Format\Audio\Mp3(), 'temp-audio.mp3');

$audio = $ffmpeg->open('temp-audio.mp3');
$waveform = $audio->waveform();
$waveform->save('video-waveform.png');

// 清理临时文件
unlink('temp-audio.mp3');
```

## 滤镜系统

### 视频滤镜

#### 缩放滤镜

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('input.mp4');

// 适配缩放（保持宽高比）
$video->filters()->resize(
    new Dimension(1280, 720),
    ResizeFilter::RESIZEMODE_FIT,
    true  // 强制使用标准宽高比
);

// 拉伸缩放（可能改变宽高比）
$video->filters()->resize(
    new Dimension(1920, 1080),
    ResizeFilter::RESIZEMODE_STRETCH_ASPECT
);

// 内嵌缩放（添加黑边保持宽高比）
$video->filters()->resize(
    new Dimension(1920, 1080),
    ResizeFilter::RESIZEMODE_INSET
);

$video->save(new X264(), 'resized.mp4');
```

#### 填充滤镜

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('input.mp4');

// 添加黑边使视频达到指定尺寸
$video->filters()->pad(new Dimension(1920, 1080));

$video->save(new X264(), 'padded.mp4');
```

#### 旋转滤镜

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Filters\Video\RotateFilter;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('input.mp4');

// 旋转90度顺时针
$video->filters()->rotate(RotateFilter::ROTATE_90);

// 旋转180度
$video->filters()->rotate(RotateFilter::ROTATE_180);

// 旋转90度逆时针
$video->filters()->rotate(RotateFilter::ROTATE_270);

$video->save(new X264(), 'rotated.mp4');
```

#### 裁剪滤镜

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\Point;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('input.mp4');

// 裁剪：从(100,50)开始，裁剪800x600的区域
$video->filters()->crop(
    new Point(100, 50),          // 起始点
    new Dimension(800, 600)      // 裁剪尺寸
);

// 动态裁剪（支持表达式）
$video->filters()->crop(
    new Point("t*100", 0, true), // 动态X坐标
    new Dimension(200, 600)
);

$video->save(new X264(), 'cropped.mp4');
```

#### 水印滤镜

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('input.mp4');

// 相对位置水印
$video->filters()->watermark(
    '/path/to/watermark.png',
    [
        'position' => 'relative',
        'bottom' => 50,          // 距离底部50像素
        'right' => 50            // 距离右边50像素
    ]
);

// 绝对位置水印
$video->filters()->watermark(
    '/path/to/watermark.png',
    [
        'position' => 'absolute',
        'x' => 100,              // X坐标
        'y' => 100               // Y坐标
    ]
);

$video->save(new X264(), 'watermarked.mp4');
```

#### 帧率滤镜

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\FrameRate;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('input.mp4');

// 修改帧率为30fps，GOP值为15
$video->filters()->framerate(
    new FrameRate(30),
    15  // GOP (Group of Pictures) 值
);

$video->save(new X264(), 'framerate-30.mp4');
```

#### 同步滤镜

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('input.mp4');

// 同步音视频（解决某些容器的音视频不同步问题）
$video->filters()->synchronize();

$video->save(new X264(), 'synchronized.mp4');
```

### 滤镜链式调用

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\Point;
use FFMpeg\Coordinate\FrameRate;
use FFMpeg\Filters\Video\RotateFilter;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('input.mp4');

// 链式应用多个滤镜
$video->filters()
      ->resize(new Dimension(1280, 720))
      ->rotate(RotateFilter::ROTATE_90)
      ->crop(new Point(100, 100), new Dimension(640, 480))
      ->framerate(new FrameRate(25), 12)
      ->watermark('/path/to/logo.png', [
          'position' => 'relative',
          'top' => 10,
          'right' => 10
      ])
      ->synchronize();

$video->save(new X264(), 'processed.mp4');
```

### 自定义滤镜

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('input.mp4');

// 添加自定义FFMpeg参数
$video->filters()->custom([
    '-vf', 'eq=brightness=0.1:contrast=1.2',  // 调整亮度和对比度
    '-af', 'volume=1.5'                       // 调整音量
]);

// 更复杂的自定义滤镜
$video->filters()->custom([
    '-vf', 'scale=1920:1080,fps=30,format=yuv420p',
    '-af', 'aformat=sample_rates=48000:channel_layouts=stereo',
    '-c:v', 'libx264',
    '-preset', 'medium',
    '-crf', '23'
]);

$video->save(new X264(), 'custom-filtered.mp4');
```

## 格式系统

### 视频格式

#### H.264 (X264) 格式

```php
<?php
declare(strict_types=1);

use FFMpeg\Format\Video\X264;

$format = new X264();

// 基础配置
$format->setKiloBitrate(2000)              // 视频比特率 2Mbps
       ->setAudioChannels(2)               // 立体声
       ->setAudioKiloBitrate(128)          // 音频比特率 128kbps
       ->setAudioCodec('aac');             // 音频编码

// 高级配置
$format->setAdditionalParameters([
    '-preset', 'medium',                   // 编码预设
    '-crf', '23',                         // 恒定质量因子
    '-profile:v', 'high',                 // H.264配置文件
    '-level', '4.0',                      // H.264级别
    '-pix_fmt', 'yuv420p'                 // 像素格式
]);

// 设置初始参数
$format->setInitialParameters([
    '-hwaccel', 'auto'                    // 硬件加速
]);

// 多通道编码
$format->setPasses(2);                    // 2-pass编码
```

#### WebM 格式

```php
<?php
declare(strict_types=1);

use FFMpeg\Format\Video\WebM;

$format = new WebM();
$format->setKiloBitrate(1500)
       ->setAudioChannels(2)
       ->setAudioKiloBitrate(128)
       ->setVideoCodec('libvpx-vp9')      // VP9编码
       ->setAudioCodec('libvorbis');      // Vorbis音频

$format->setAdditionalParameters([
    '-quality', 'good',
    '-cpu-used', '0',
    '-crf', '30',
    '-b:v', '0'                           // VBR模式
]);
```

### 音频格式

#### MP3 格式

```php
<?php
declare(strict_types=1);

use FFMpeg\Format\Audio\Mp3;

$format = new Mp3();
$format->setAudioChannels(2)              // 立体声
       ->setAudioKiloBitrate(192)         // 192kbps
       ->setAudioCodec('libmp3lame');     // LAME编码器

$format->setAdditionalParameters([
    '-q:a', '2'                           // VBR质量等级
]);
```

#### FLAC 格式

```php
<?php
declare(strict_types=1);

use FFMpeg\Format\Audio\Flac;

$format = new Flac();
$format->setAudioChannels(2)
       ->setAudioKiloBitrate(0)           // FLAC是无损格式，比特率设为0
       ->setAudioCodec('flac');

$format->setAdditionalParameters([
    '-compression_level', '8'             // 压缩级别（0-12）
]);
```

### 自定义格式

```php
<?php
declare(strict_types=1);

use FFMpeg\Format\Video\DefaultVideo;

class CustomH265Format extends DefaultVideo
{
    public function __construct(string $audioCodec = 'aac', string $videoCodec = 'libx265')
    {
        $this->setAudioCodec($audioCodec)
             ->setVideoCodec($videoCodec);
    }

    public function supportBFrames(): bool
    {
        return true;
    }

    public function getAvailableAudioCodecs(): array
    {
        return ['aac', 'mp3', 'libfdk_aac'];
    }

    public function getAvailableVideoCodecs(): array
    {
        return ['libx265', 'hevc_nvenc'];
    }

    public function getExtraParams(): array
    {
        return [
            '-preset', 'medium',
            '-crf', '28',
            '-pix_fmt', 'yuv420p'
        ];
    }
}

// 使用自定义格式
$format = new CustomH265Format();
$format->setKiloBitrate(1500)
       ->setAudioKiloBitrate(128);

$video->save($format, 'output-h265.mp4');
```

## 高级功能

### 多输入输出处理 (AdvancedMedia)

#### 基础用法

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create();

// 打开多个输入文件
$advancedMedia = $ffmpeg->openAdvanced([
    'video1.mp4',
    'video2.mp4',
    'audio.mp3'
]);

// 水平拼接两个视频
$advancedMedia->filters()
              ->custom('[0:v][1:v]', 'hstack', '[v]');

// 映射输出
$advancedMedia->map(
    ['0:a', '[v]'],                      // 输入流映射
    new X264(),                          // 输出格式
    'output.mp4'                         // 输出文件
)->save();
```

#### 复杂处理示例

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use FFMpeg\Format\Audio\Mp3;
use FFMpeg\Filters\AdvancedMedia\XStackFilter;

$ffmpeg = FFMpeg::create();

// 4个视频输入
$inputs = [
    'video1.mp4',
    'video2.mp4', 
    'video3.mp4',
    'video4.mp4'
];

$advancedMedia = $ffmpeg->openAdvanced($inputs);

// 对每个视频应用不同的滤镜
$advancedMedia->filters()
              ->custom('[0:v]', 'negate', '[v0negate]')           // 反色
              ->custom('[1:v]', 'edgedetect', '[v1edgedetect]')   // 边缘检测
              ->custom('[2:v]', 'hflip', '[v2hflip]')             // 水平翻转
              ->custom('[3:v]', 'vflip', '[v3vflip]')             // 垂直翻转
              ->xStack(
                  '[v0negate][v1edgedetect][v2hflip][v3vflip]',
                  XStackFilter::LAYOUT_2X2,
                  4,
                  '[resultv]'
              );

// 多路输出
$advancedMedia->map(['0:a'], new Mp3(), 'audio1.mp3')
              ->map(['1:a'], new Mp3(), 'audio2.mp3')
              ->map(['[resultv]'], new X264(), 'collage.mp4')
              ->save();
```

### 视频拼接

#### 相同编码拼接

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('video1.mp4');

// 拼接相同编码的视频（高性能）
$video->concat([
    'video1.mp4',
    'video2.mp4',
    'video3.mp4'
])->saveFromSameCodecs('concatenated.mp4', true);  // true启用复制模式
```

#### 不同编码拼接

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('video1.mp4');

$format = new X264();
$format->setAudioCodec('libmp3lame');

// 拼接不同编码的视频（需要重新编码）
$video->concat([
    'video1.avi',
    'video2.mov',
    'video3.mkv'
])->saveFromDifferentCodecs($format, 'concatenated.mp4');
```

### 实时流处理

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create([
    'timeout' => 0  // 无超时限制
]);

// 处理RTMP流
$stream = $ffmpeg->open('rtmp://live.example.com/stream/key');

$format = new X264();
$format->setAdditionalParameters([
    '-f', 'flv',              // 输出格式
    '-tune', 'zerolatency',   // 零延迟调优
    '-preset', 'ultrafast'    // 最快编码预设
]);

// 推流到另一个RTMP地址
$stream->save($format, 'rtmp://output.example.com/live/stream');
```

### 硬件加速

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

$ffmpeg = FFMpeg::create();
$video = $ffmpeg->open('input.mp4');

$format = new X264();

// NVIDIA GPU加速
$format->setInitialParameters([
    '-hwaccel', 'cuda',
    '-hwaccel_output_format', 'cuda'
]);

$format->setVideoCodec('h264_nvenc')
       ->setAdditionalParameters([
           '-preset', 'p4',         // NVENC预设
           '-rc', 'vbr',           // 码率控制
           '-cq', '23',            // 质量参数
           '-b:v', '5M',           // 目标比特率
           '-maxrate', '10M',      // 最大比特率
           '-bufsize', '20M'       // 缓冲区大小
       ]);

// Intel Quick Sync加速
$format->setInitialParameters([
    '-hwaccel', 'qsv'
]);
$format->setVideoCodec('h264_qsv');

$video->save($format, 'output-hw-accelerated.mp4');
```

### 批量处理

```php
<?php
declare(strict_types=1);

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use FFMpeg\Coordinate\Dimension;

class BatchProcessor
{
    private FFMpeg $ffmpeg;
    
    public function __construct()
    {
        $this->ffmpeg = FFMpeg::create([
            'timeout' => 3600,
            'ffmpeg.threads' => 4
        ]);
    }
    
    public function processDirectory(string $inputDir, string $outputDir): void
    {
        $files = glob($inputDir . '/*.{mp4,avi,mov,mkv}', GLOB_BRACE);
        
        foreach ($files as $file) {
            $this->processFile($file, $outputDir);
        }
    }
    
    private function processFile(string $inputFile, string $outputDir): void
    {
        try {
            $video = $this->ffmpeg->open($inputFile);
            
            // 应用标准处理
            $video->filters()
                  ->resize(new Dimension(1920, 1080))
                  ->synchronize();
            
            $format = new X264();
            $format->setKiloBitrate(2000)
                   ->setAudioKiloBitrate(128)
                   ->on('progress', function ($video, $format, $percentage) use ($inputFile) {
                       echo basename($inputFile) . ": {$percentage}%" . PHP_EOL;
                   });
            
            $outputFile = $outputDir . '/' . basename($inputFile, '.') . '_processed.mp4';
            $video->save($format, $outputFile);
            
            echo "处理完成: " . basename($inputFile) . PHP_EOL;
            
        } catch (\Exception $e) {
            echo "处理失败: " . basename($inputFile) . " - " . $e->getMessage() . PHP_EOL;
        }
    }
}

// 使用批量处理器
$processor = new BatchProcessor();
$processor->processDirectory('/input/videos', '/output/videos');
```

## API参考

### 核心类

#### FFMpeg 类
```php
// 创建实例
public static function create(array $configuration = [], ?LoggerInterface $logger = null): FFMpeg

// 打开媒体文件
public function open(string $pathfile): Audio|Video

// 打开高级媒体（多输入）
public function openAdvanced(array $inputs): AdvancedMedia

// 设置FFProbe实例
public function setFFProbe(FFProbe $ffprobe): FFMpeg

// 获取FFProbe实例
public function getFFProbe(): FFProbe
```

#### FFProbe 类
```php
// 创建实例
public static function create(array $configuration = [], ?LoggerInterface $logger = null): FFProbe

// 获取格式信息
public function format(string $pathfile): Format

// 获取流信息
public function streams(string $pathfile): StreamCollection

// 验证媒体文件
public function isValid(string $pathfile): bool
```

### 媒体类

#### Video 类
```php
// 获取滤镜管理器
public function filters(): VideoFilters

// 保存视频
public function save(FormatInterface $format, string $outputPathfile): Video

// 提取帧
public function frame(TimeCode $at): Frame

// 生成GIF
public function gif(TimeCode $at, Dimension $dimension, ?int $duration = null): Gif

// 剪辑
public function clip(TimeCode $start, ?TimeCode $duration = null): Clip

// 拼接
public function concat(array $sources): Concat
```

#### Audio 类
```php
// 获取滤镜管理器
public function filters(): AudioFilters

// 保存音频
public function save(FormatInterface $format, string $outputPathfile): Audio

// 生成波形图
public function waveform(int $width = 640, int $height = 120, array $colors = []): Waveform

// 拼接
public function concat(array $sources): Concat
```

### 坐标类

#### TimeCode 类
```php
// 从秒数创建
public static function fromSeconds(int|float $seconds): TimeCode

// 从时间字符串创建
public static function fromString(string $timecode): TimeCode

// 转换为秒数
public function toSeconds(): float

// 字符串表示
public function __toString(): string
```

#### Dimension 类
```php
public function __construct(int $width, int $height)
public function getWidth(): int
public function getHeight(): int
public function getRatio(bool $forceStandards = true): AspectRatio
```

#### Point 类
```php
public function __construct(int|string $x, int|string $y, bool $dynamic = false)
public function getX(): int|string
public function getY(): int|string
```

## 最佳实践

### 1. 性能优化

#### 使用合适的预设
```php
<?php
declare(strict_types=1);

// 快速编码（文件大，质量一般）
$format->setAdditionalParameters(['-preset', 'ultrafast']);

// 平衡（推荐）
$format->setAdditionalParameters(['-preset', 'medium']);

// 高质量（慢）
$format->setAdditionalParameters(['-preset', 'veryslow']);
```

#### 合理设置线程数
```php
<?php
declare(strict_types=1);

$ffmpeg = FFMpeg::create([
    'ffmpeg.threads' => min(8, (int)shell_exec('nproc') ?: 4)
]);
```

#### 使用硬件加速
```php
<?php
declare(strict_types=1);

$format->setInitialParameters(['-hwaccel', 'auto']);
```

### 2. 错误处理

```php
<?php
declare(strict_types=1);

use FFMpeg\Exception\RuntimeException;
use FFMpeg\Exception\ExecutableNotFoundException;

try {
    $ffmpeg = FFMpeg::create();
    $video = $ffmpeg->open('input.mp4');
    $video->save(new X264(), 'output.mp4');
    
} catch (ExecutableNotFoundException $e) {
    echo "FFMpeg未找到: " . $e->getMessage() . PHP_EOL;
    
} catch (RuntimeException $e) {
    echo "处理错误: " . $e->getMessage() . PHP_EOL;
    
} catch (\Exception $e) {
    echo "未知错误: " . $e->getMessage() . PHP_EOL;
}
```

### 3. 内存管理

```php
<?php
declare(strict_types=1);

class VideoProcessor
{
    private FFMpeg $ffmpeg;
    
    public function __construct()
    {
        $this->ffmpeg = FFMpeg::create([
            'temporary_directory' => '/tmp/ffmpeg'
        ]);
    }
    
    public function processLargeFile(string $inputFile): void
    {
        // 处理大文件时限制内存使用
        ini_set('memory_limit', '512M');
        
        $video = $this->ffmpeg->open($inputFile);
        
        // 分段处理而不是一次性处理
        $duration = $this->getDuration($inputFile);
        $segments = ceil($duration / 300); // 每段5分钟
        
        for ($i = 0; $i < $segments; $i++) {
            $start = $i * 300;
            $clip = $video->clip(
                TimeCode::fromSeconds($start),
                TimeCode::fromSeconds(min(300, $duration - $start))
            );
            
            $clip->save(new X264(), "segment_{$i}.mp4");
            
            // 释放内存
            unset($clip);
            gc_collect_cycles();
        }
    }
}
```

### 4. 日志记录

```php
<?php
declare(strict_types=1);

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

$logger = new Logger('ffmpeg');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
$logger->pushHandler(new RotatingFileHandler('/var/log/ffmpeg.log', 7, Logger::DEBUG));

$ffmpeg = FFMpeg::create([], $logger);
```

### 5. 配置管理

```php
<?php
declare(strict_types=1);

class FFMpegConfig
{
    public static function getConfiguration(): array
    {
        return [
            'ffmpeg.binaries' => $_ENV['FFMPEG_BINARY'] ?? 'ffmpeg',
            'ffprobe.binaries' => $_ENV['FFPROBE_BINARY'] ?? 'ffprobe',
            'timeout' => (int)($_ENV['FFMPEG_TIMEOUT'] ?? 3600),
            'ffmpeg.threads' => (int)($_ENV['FFMPEG_THREADS'] ?? 4),
            'temporary_directory' => $_ENV['FFMPEG_TEMP_DIR'] ?? sys_get_temp_dir(),
        ];
    }
    
    public static function getProductionFormat(): X264
    {
        $format = new X264();
        $format->setKiloBitrate(2000)
               ->setAudioKiloBitrate(128)
               ->setAdditionalParameters([
                   '-preset', 'medium',
                   '-crf', '23',
                   '-pix_fmt', 'yuv420p'
               ]);
               
        return $format;
    }
}

// 使用配置
$ffmpeg = FFMpeg::create(FFMpegConfig::getConfiguration());
$format = FFMpegConfig::getProductionFormat();
```

### 6. 进度监控和通知

```php
<?php
declare(strict_types=1);

class ProgressTracker
{
    private string $jobId;
    private ?\Redis $redis;
    
    public function __construct(string $jobId)
    {
        $this->jobId = $jobId;
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }
    
    public function createProgressListener(): callable
    {
        return function ($video, $format, $percentage): void {
            // 更新Redis中的进度
            $this->redis->setex(
                "ffmpeg_progress:{$this->jobId}",
                3600,
                json_encode([
                    'percentage' => $percentage,
                    'timestamp' => time(),
                    'status' => $percentage >= 100 ? 'completed' : 'processing'
                ])
            );
            
            // 发送WebSocket通知（示例）
            if ($percentage % 5 === 0) {
                $this->notifyProgress($percentage);
            }
        };
    }
    
    private function notifyProgress(int $percentage): void
    {
        // 实现WebSocket通知逻辑
        // 或者发送HTTP回调
        file_get_contents("http://callback.example.com/progress?job={$this->jobId}&progress={$percentage}");
    }
}

// 使用进度跟踪
$tracker = new ProgressTracker('job-' . uniqid());
$format = new X264();
$format->on('progress', $tracker->createProgressListener());

$video->save($format, 'output.mp4');
```

这份详细的使用指南涵盖了php-ffmpeg包的架构分析、安装配置、基础和高级功能使用、API参考以及最佳实践。希望能帮助您更好地使用这个强大的多媒体处理库。