# 📊 DouyinLiveRecorder 录制和分割实现分析

## 🎯 项目概述

`DouyinLiveRecorder` 是一个 Python 实现的多平台直播录制工具，支持抖音、B站、虎牙等多个直播平台的录制和分割功能。通过分析其 `main.py` 文件，我们可以了解其录制和分割的核心实现原理。

## 🔧 核心技术架构

### 1. **录制流程架构**

```python
def start_record(url_data: tuple, count_variable: int = -1) -> None:
    # 1. 获取直播流信息
    # 2. 构建 FFmpeg 录制命令
    # 3. 执行录制进程
    # 4. 监控录制状态
    # 5. 后处理（转码、分割）
```

**核心特点**：
- 🔄 **循环监控**：持续监控直播状态，自动重启录制
- 🌐 **多平台支持**：支持 20+ 直播平台
- 🎯 **命令行驱动**：完全基于 FFmpeg 命令行工具

## 🎬 录制实现原理

### 1. **FFmpeg 命令构建**

```python
ffmpeg_command = [
    'ffmpeg', "-y",
    "-v", "verbose",
    "-rw_timeout", rw_timeout,
    "-loglevel", "error",
    "-hide_banner",
    "-user_agent", user_agent,
    "-protocol_whitelist", "rtmp,crypto,file,http,https,tcp,tls,udp,rtp,httpproxy",
    "-thread_queue_size", "1024",
    "-analyzeduration", analyzeduration,
    "-probesize", probesize,
    "-fflags", "+discardcorrupt",
    "-re", "-i", real_url,  # 输入流地址
    "-bufsize", bufsize,
    "-sn", "-dn",
    "-reconnect_delay_max", "60",
    "-reconnect_streamed", "-reconnect_at_eof",
    "-max_muxing_queue_size", max_muxing_queue_size,
    "-correct_ts_overflow", "1",
    "-avoid_negative_ts", "1"
]
```

**关键参数解析**：
- `-re`：以原始帧率读取输入（实时录制）
- `-reconnect_streamed`：流中断时自动重连
- `-fflags +discardcorrupt`：丢弃损坏的数据包
- `-avoid_negative_ts`：避免负时间戳问题

### 2. **录制进程管理**

```python
def check_subprocess(record_name: str, record_url: str, ffmpeg_command: list, save_type: str,
                     script_command: str | None = None) -> bool:
    # 启动 FFmpeg 进程
    process = subprocess.Popen(
        ffmpeg_command, stdin=subprocess.PIPE, stderr=subprocess.STDOUT, 
        startupinfo=get_startup_info(os_type)
    )
    
    # 监控进程状态
    while process.poll() is None:
        if record_url in url_comments or exit_recording:
            # 优雅停止录制
            if os.name == 'nt':
                process.stdin.write(b'q')  # Windows
            else:
                process.send_signal(signal.SIGINT)  # Linux/Mac
            process.wait()
            return True
        time.sleep(1)
    
    return process.returncode == 0
```

**进程管理特点**：
- 🔄 **实时监控**：每秒检查进程状态
- 🛑 **优雅停止**：使用 `q` 命令或 `SIGINT` 信号
- 📊 **状态跟踪**：记录录制开始/结束时间

## ✂️ 分割录制实现

### 1. **实时分割策略**

DouyinLiveRecorder 采用 **FFmpeg 原生分割** 的方式，在录制过程中直接分割：

#### **MP4 格式分割**

```python
if split_video_by_time:
    save_file_path = f"{full_path}/{anchor_name}_{title_in_name}{now}_%03d.mp4"
    command = [
        "-c:v", "copy",           # 视频流复制（不重新编码）
        "-c:a", "aac",            # 音频编码为 AAC
        "-map", "0",              # 映射所有流
        "-f", "segment",          # 使用分段格式
        "-segment_time", split_time,     # 分段时间
        "-segment_format", "mp4",        # 分段格式
        "-reset_timestamps", "1",        # 重置时间戳
        "-movflags", "+frag_keyframe+empty_moov",  # MP4 优化
        save_file_path,
    ]
```

#### **TS 格式分割**

```python
if split_video_by_time:
    save_file_path = f"{full_path}/{anchor_name}_{title_in_name}{now}_%03d.ts"
    command = [
        "-c:v", "copy",
        "-c:a", "copy",
        "-map", "0",
        "-f", "segment",
        "-segment_time", split_time,
        "-segment_format", 'mpegts',     # MPEG-TS 格式
        "-reset_timestamps", "1",
        save_file_path,
    ]
```

#### **MKV 格式分割**

```python
if split_video_by_time:
    save_file_path = f"{full_path}/{anchor_name}_{title_in_name}{now}_%03d.mkv"
    command = [
        "-flags", "global_header",
        "-c:v", "copy",
        "-c:a", "aac",
        "-map", "0",
        "-f", "segment",
        "-segment_time", split_time,
        "-segment_format", "matroska",   # Matroska 容器
        "-reset_timestamps", "1",
        save_file_path,
    ]
```

### 2. **后处理分割策略**

对于不支持实时分割的格式（如 FLV），采用后处理分割：

```python
def segment_video(converts_file_path: str, segment_save_file_path: str, 
                  segment_format: str, segment_time: str,
                  is_original_delete: bool = True) -> None:
    if os.path.exists(converts_file_path) and os.path.getsize(converts_file_path) > 0:
        ffmpeg_command = [
            "ffmpeg",
            "-i", converts_file_path,     # 输入完整文件
            "-c:v", "copy",               # 视频流复制
            "-c:a", "copy",               # 音频流复制
            "-map", "0",                  # 映射所有流
            "-f", "segment",              # 分段格式
            "-segment_time", segment_time,    # 分段时间
            "-segment_format", segment_format, # 分段格式
            "-reset_timestamps", "1",         # 重置时间戳
            "-movflags", "+frag_keyframe+empty_moov",  # 优化选项
            segment_save_file_path,
        ]
        subprocess.check_output(ffmpeg_command, stderr=subprocess.STDOUT)
```

## 🔍 关键技术对比

### **实时分割 vs 后处理分割**

| 特性 | 实时分割 | 后处理分割 |
|------|---------|-----------|
| **实现方式** | FFmpeg `-f segment` | 录制完成后分割 |
| **内存占用** | ✅ 低 | ❌ 高（需要完整文件） |
| **磁盘空间** | ✅ 节省 | ❌ 需要双倍空间 |
| **实时性** | ✅ 即时生成分段 | ❌ 需要等待录制完成 |
| **容错性** | ✅ 单段损坏不影响其他 | ❌ 整个文件损坏则全部丢失 |
| **格式支持** | 🔧 部分格式 | ✅ 所有格式 |

### **与我们 PHP 实现的对比**

| 方面 | DouyinLiveRecorder | 我们的 PHP 实现 |
|------|-------------------|----------------|
| **核心技术** | FFmpeg 命令行 | php-ffmpeg + 智能分割 |
| **分割方式** | 原生 `-f segment` | 预设时长 + 监控 |
| **进程管理** | `subprocess.Popen` | `php-ffmpeg` 封装 |
| **错误处理** | 返回码检查 | 异常捕获 |
| **架构风格** | 过程式 | 面向对象 |
| **可测试性** | 🔧 困难 | ✅ 容易 |
| **可维护性** | 🔧 中等 | ✅ 优秀 |

## 💡 核心技术亮点

### 1. **FFmpeg 参数优化**

```python
# 网络优化
"-rw_timeout", rw_timeout,
"-reconnect_delay_max", "60",
"-reconnect_streamed", "-reconnect_at_eof",

# 缓冲优化  
"-bufsize", bufsize,
"-max_muxing_queue_size", max_muxing_queue_size,

# 时间戳处理
"-correct_ts_overflow", "1",
"-avoid_negative_ts", "1",
"-reset_timestamps", "1",
```

### 2. **格式特定优化**

```python
# MP4 优化
"-movflags", "+frag_keyframe+empty_moov"

# 音频处理
"-c:a", "aac"           # MP4/MKV 使用 AAC
"-c:a", "copy"          # TS 直接复制

# 视频处理
"-c:v", "copy"          # 所有格式都复制视频流（避免重编码）
```

### 3. **文件命名策略**

```python
# 分段文件命名模式
save_file_path = f"{full_path}/{anchor_name}_{title_in_name}{now}_%03d.{extension}"

# 示例输出：
# 主播名_2025-01-10_12-30-45_001.mp4
# 主播名_2025-01-10_12-30-45_002.mp4  
# 主播名_2025-01-10_12-30-45_003.mp4
```

## 🎯 最佳实践总结

### **DouyinLiveRecorder 的优势**

1. **✅ 原生分割**：使用 FFmpeg 的 `-f segment` 实现真正的实时分割
2. **✅ 格式丰富**：支持 MP4、TS、MKV、FLV 等多种格式
3. **✅ 网络优化**：完善的重连和容错机制
4. **✅ 资源高效**：分割过程不需要额外内存和存储

### **可借鉴的技术点**

1. **FFmpeg 参数优化**：
   ```bash
   -reconnect_streamed -reconnect_at_eof  # 自动重连
   -avoid_negative_ts 1                   # 时间戳处理
   -reset_timestamps 1                    # 重置分段时间戳
   ```

2. **分段文件命名**：
   ```bash
   output_%03d.mp4  # 自动编号：001, 002, 003...
   ```

3. **进程监控机制**：
   - 实时检查进程状态
   - 优雅的停止机制
   - 返回码验证

### **我们 PHP 实现的优势**

1. **✅ 架构优雅**：面向对象设计，符合 SOLID 原则
2. **✅ 可测试性**：完善的单元测试支持
3. **✅ 错误处理**：结构化异常处理
4. **✅ 扩展性**：策略模式，易于扩展新的分割策略
5. **✅ 统一接口**：基于 php-ffmpeg 的一致性 API

## 🚀 技术演进建议

基于 DouyinLiveRecorder 的成功实践，我们可以考虑以下改进：

### 1. **混合分割策略**

```php
// 结合原生分割和智能监控
if ($this->supportsNativeSegmentation($format)) {
    return $this->useNativeFFmpegSegmentation($pendingRecorder);
} else {
    return $this->useSmartRealtimeSplitter($pendingRecorder);
}
```

### 2. **网络优化参数**

```php
$ffmpegOptions = [
    'reconnect_streamed' => true,
    'reconnect_at_eof' => true, 
    'rw_timeout' => '30000000',
    'avoid_negative_ts' => 'make_zero',
];
```

### 3. **进程监控增强**

```php
// 添加进程健康检查
private function monitorFFmpegProcess(): void
{
    while ($this->process->isRunning()) {
        if ($this->shouldStop || !$this->isStreamStillLive()) {
            $this->gracefulStop();
            break;
        }
        usleep(1000000); // 1秒检查一次
    }
}
```

## 📊 总结

DouyinLiveRecorder 的实现为我们提供了宝贵的参考：

1. **🎯 原生分割是最优解**：FFmpeg 的 `-f segment` 是最高效的分割方式
2. **🔄 实时监控很重要**：持续监控进程状态和直播状态
3. **🛠️ 参数优化是关键**：合适的 FFmpeg 参数能显著提升稳定性
4. **📁 文件管理要规范**：清晰的命名规则和目录结构

我们的 PHP 实现在保持技术先进性的同时，更注重代码的可维护性和扩展性，这是一个很好的平衡！🚀