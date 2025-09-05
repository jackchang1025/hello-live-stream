# 直播录制使用指南

## 🎯 录制文件保存位置

### 默认保存路径
- **容器内**: `/app/recordings/` 或 `./recordings/`
- **宿主机**: `./recordings/` (项目根目录下)

### 测试保存路径
- **容器内**: `/app/test-recordings/`
- **宿主机**: `./test-recordings/` (如果设置了 volume 映射)

### 文件命名格式
默认格式：`{主播名}_{房间ID}_{时间戳}.{格式}`
例如：`测试主播_426219276305_2024-01-15_14-30-25.mp4`

## 🚀 运行录制测试

### 方法一：使用测试文件
```bash
# 运行改进的测试（会显示录制信息）
docker-compose -f docker-compose.test.yml run --rm php-test ./vendor/bin/pest tests/Unit/ExampleTest.php --filter="test get live data html"
```

### 方法二：使用独立录制脚本
```bash
# 运行独立的录制测试脚本
docker-compose -f docker-compose.test.yml run --rm php-test php test-recording.php
```

### 方法三：交互式录制
```bash
# 进入容器手动测试
docker-compose -f docker-compose.test.yml run --rm php-test bash

# 在容器内运行
php test-recording.php
```

## 📁 检查录制文件

### 使用检查脚本
```bash
# 运行文件检查脚本
./check-recordings.sh
```

### 手动检查
```bash
# 检查宿主机录制目录
ls -la ./recordings/
ls -la ./test-recordings/

# 检查容器内录制目录
docker-compose -f docker-compose.test.yml run --rm php-test ls -la /app/recordings/
docker-compose -f docker-compose.test.yml run --rm php-test ls -la /app/test-recordings/
```

## 🎬 录制过程监控

### 实时查看录制状态
录制过程中会显示：
- 录制ID
- 房间信息（主播、标题）
- 流地址
- 输出路径
- 录制进度（如果支持）

### 示例输出
```
=== 录制信息 ===
录制ID: phpffmpeg_65a1234567890.123
房间ID: 426219276305
主播: 测试主播
标题: 测试直播
输出路径: /app/test-recordings/测试主播_426219276305_2024-01-15_14-30-25.mp4
流地址: https://pull-hls-spe-l1.douyinliving.com/fantasy/stream-...

=== 开始录制 ===
录制进度: 10%
录制进度: 25%
...

=== 录制完成 ===
录制时长: 120 秒
输出文件: /app/test-recordings/测试主播_426219276305_2024-01-15_14-30-25.mp4
文件大小: 45.67 MB
```

## 🔧 录制参数配置

### 基本配置
```php
$options = new RecordingOptions(
    quality: Quality::HIGH,        // 画质：ORIGINAL, HIGH, MEDIUM, LOW
    format: OutputFormat::MP4,     // 格式：MP4, WEBM, FLV, MKV, MP3, AAC
    savePath: './recordings'       // 保存路径
);
```

### 高级配置
```php
$options = new RecordingOptions(
    quality: Quality::ORIGINAL,
    format: OutputFormat::MP4,
    savePath: './recordings',
    proxy: 'http://proxy:8080',           // 代理服务器
    timeoutSeconds: 600,                  // 超时时间
    maxRetries: 5,                        // 最大重试次数
    customHeaders: [                      // 自定义请求头
        'User-Agent' => 'Custom-Agent'
    ]
);
```

## 🚨 常见问题排查

### 1. 录制文件未找到
```bash
# 检查目录权限
docker-compose -f docker-compose.test.yml run --rm php-test ls -la /app/

# 检查磁盘空间
docker-compose -f docker-compose.test.yml run --rm php-test df -h

# 查找所有录制文件
docker-compose -f docker-compose.test.yml run --rm php-test find /app -name "*.mp4" -o -name "*.flv"
```

### 2. 录制失败
常见原因：
- 直播间未开播
- 流地址失效
- 网络连接问题
- 磁盘空间不足
- FFmpeg 配置问题

### 3. 文件大小为 0
可能原因：
- 录制时间太短
- 流格式不兼容
- 编码器问题

## 📊 录制质量说明

### 画质选项
- `ORIGINAL` (原画): 最高画质，文件最大
- `HIGH` (高清): 高画质，适合大多数场景
- `MEDIUM` (标清): 中等画质，文件较小
- `LOW` (流畅): 最低画质，文件最小

### 格式选项
- `MP4`: 通用格式，兼容性好
- `FLV`: 直播常用格式
- `WEBM`: Web 优化格式
- `MKV`: 高质量容器格式
- `MP3/AAC`: 仅音频格式

## 🎯 最佳实践

### 1. 测试录制
```bash
# 先运行短时间测试
docker-compose -f docker-compose.test.yml run --rm php-test timeout 30s php test-recording.php
```

### 2. 长时间录制
```bash
# 后台运行录制
docker-compose -f docker-compose.test.yml run -d php-test php test-recording.php
```

### 3. 监控录制
```bash
# 实时监控文件大小
watch -n 5 'ls -lh ./recordings/'

# 监控容器内文件
docker-compose -f docker-compose.test.yml run --rm php-test watch -n 5 'ls -lh /app/recordings/'
```

## 📝 录制日志

录制过程中的详细信息会输出到控制台，包括：
- 连接状态
- 流分析结果
- 录制进度
- 错误信息
- 文件信息

保存这些信息有助于问题排查和性能优化。