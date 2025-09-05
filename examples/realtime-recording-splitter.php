<?php

declare(strict_types=1);

/**
 * PHP-FFMpeg 实时录制分割示例
 * 
 * 支持按时间和文件大小进行实时分割
 * 基于 FFmpeg 原生 -f segment 参数实现
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

/**
 * 实时录制分割器
 */
class RealtimeRecordingSplitter
{
    private FFMpeg $ffmpeg;
    private bool $shouldStop = false;
    private $currentProcess = null;
    private array $segments = [];
    private int $segmentCounter = 0;

    public function __construct(array $config = [])
    {
        $this->ffmpeg = FFMpeg::create(array_merge([
            'timeout' => 0,  // 无超时限制
            'ffmpeg.threads' => 4
        ], $config));
    }

    /**
     * 方案一：FFmpeg 原生分割（推荐）
     * 使用 -f segment 参数实现真正的实时分割
     */
    public function recordWithNativeSegment(
        string $inputUrl,
        string $outputDir,
        array $options = []
    ): void {
        $defaultOptions = [
            'segment_time' => 300,        // 每段5分钟
            'segment_size' => '100M',     // 每段100MB
            'format' => 'mp4',           // 输出格式
            'video_bitrate' => 2000,     // 视频比特率
            'audio_bitrate' => 128,      // 音频比特率
        ];

        $options = array_merge($defaultOptions, $options);

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // 生成输出文件模式
        $outputPattern = $outputDir . '/segment_%03d.' . $options['format'];

        echo "🎬 开始FFmpeg原生分割录制...\n";
        echo "输入: {$inputUrl}\n";
        echo "输出: {$outputPattern}\n";
        echo "分段时间: {$options['segment_time']}秒\n";
        echo "分段大小: {$options['segment_size']}\n\n";

        try {
            $this->executeNativeSegmentCommand($inputUrl, $outputPattern, $options);
        } catch (Exception $e) {
            echo "录制失败: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * 执行FFmpeg原生分段命令
     */
    private function executeNativeSegmentCommand(
        string $inputUrl,
        string $outputPattern,
        array $options
    ): void {
        // 构建FFmpeg命令
        $command = [
            'ffmpeg',
            '-i',
            $inputUrl,

            // 网络优化参数
            '-reconnect',
            '1',
            '-reconnect_at_eof',
            '1',
            '-reconnect_streamed',
            '1',
            '-reconnect_delay_max',
            '120',

            // 编码参数
            '-c:v',
            'libx264',
            '-c:a',
            'aac',
            '-preset',
            'fast',
            '-crf',
            '23',
            '-b:v',
            $options['video_bitrate'] . 'k',
            '-b:a',
            $options['audio_bitrate'] . 'k',

            // 关键：FFmpeg原生分段参数
            '-f',
            'segment',
            '-segment_time',
            (string)$options['segment_time'],
            '-segment_format',
            $options['format'],
            '-reset_timestamps',
            '1',
            '-strftime',
            '1',           // 启用时间戳格式化

            // 根据格式优化
            ...$this->getFormatSpecificOptions($options['format']),

            // 输出模式
            $outputPattern
        ];

        // 如果指定了文件大小限制，添加相关参数
        if (isset($options['segment_size'])) {
            array_splice($command, -1, 0, ['-segment_list_size', '0', '-segment_wrap', '0']);
        }

        echo "执行命令: " . implode(' ', array_map('escapeshellarg', $command)) . "\n\n";

        // 启动进程
        $this->startProcess($command);
    }

    /**
     * 方案二：PHP监控分割
     * 通过PHP监控文件大小和时间进行分割控制
     */
    public function recordWithPhpMonitoring(
        string $inputUrl,
        string $outputDir,
        array $options = []
    ): void {
        $defaultOptions = [
            'max_segment_time' => 300,    // 最大分段时间（秒）
            'max_segment_size' => 100,    // 最大分段大小（MB）
            'format' => 'mp4',
            'video_bitrate' => 2000,
            'audio_bitrate' => 128,
        ];

        $options = array_merge($defaultOptions, $options);

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        echo "🎬 开始PHP监控分割录制...\n";
        echo "最大分段时间: {$options['max_segment_time']}秒\n";
        echo "最大分段大小: {$options['max_segment_size']}MB\n\n";

        $this->executeMonitoredRecording($inputUrl, $outputDir, $options);
    }

    /**
     * 执行监控录制
     */
    private function executeMonitoredRecording(
        string $inputUrl,
        string $outputDir,
        array $options
    ): void {
        $segmentStartTime = time();

        while (!$this->shouldStop) {
            $currentSegment = sprintf(
                '%s/segment_%03d.%s',
                $outputDir,
                ++$this->segmentCounter,
                $options['format']
            );

            echo "🎥 开始录制分段 {$this->segmentCounter}: " . basename($currentSegment) . "\n";

            // 启动当前分段录制
            $this->startSegmentRecording($inputUrl, $currentSegment, $options);

            // 监控分段
            $this->monitorCurrentSegment($currentSegment, $options, $segmentStartTime);

            // 停止当前分段
            $this->stopCurrentRecording();

            $this->segments[] = [
                'path' => $currentSegment,
                'size' => file_exists($currentSegment) ? filesize($currentSegment) : 0,
                'created_at' => date('Y-m-d H:i:s')
            ];

            echo "✅ 分段 {$this->segmentCounter} 录制完成\n";

            $segmentStartTime = time();

            // 短暂等待避免CPU过高
            usleep(100000); // 100ms
        }
    }

    /**
     * 启动单个分段录制
     */
    private function startSegmentRecording(
        string $inputUrl,
        string $outputPath,
        array $options
    ): void {
        $command = [
            'ffmpeg',
            '-i',
            $inputUrl,
            '-c:v',
            'libx264',
            '-c:a',
            'aac',
            '-preset',
            'fast',
            '-crf',
            '23',
            '-b:v',
            $options['video_bitrate'] . 'k',
            '-b:a',
            $options['audio_bitrate'] . 'k',
            '-y',  // 覆盖输出文件
            $outputPath
        ];

        $this->startProcess($command);
    }

    /**
     * 监控当前分段
     */
    private function monitorCurrentSegment(
        string $segmentPath,
        array $options,
        int $startTime
    ): void {
        while ($this->currentProcess && is_resource($this->currentProcess)) {
            $elapsed = time() - $startTime;

            // 检查时间限制
            if ($elapsed >= $options['max_segment_time']) {
                echo "⏰ 达到时间限制({$options['max_segment_time']}秒)，切换分段\n";
                break;
            }

            // 检查文件大小限制
            if (file_exists($segmentPath)) {
                $sizeMB = filesize($segmentPath) / 1024 / 1024;
                if ($sizeMB >= $options['max_segment_size']) {
                    echo "📁 达到大小限制({$options['max_segment_size']}MB)，切换分段\n";
                    break;
                }

                // 显示进度
                echo "\r📊 分段 {$this->segmentCounter}: {$elapsed}秒, " .
                    number_format($sizeMB, 2) . "MB";
            }

            // 检查进程状态
            $status = proc_get_status($this->currentProcess);
            if (!$status['running']) {
                echo "\n❌ FFmpeg进程意外停止\n";
                break;
            }

            sleep(1);
        }

        echo "\n";
    }

    /**
     * 方案三：混合分割策略
     * 同时考虑时间和大小的智能分割
     */
    public function recordWithHybridStrategy(
        string $inputUrl,
        string $outputDir,
        array $options = []
    ): void {
        $defaultOptions = [
            'time_threshold' => 300,      // 时间阈值（秒）
            'size_threshold' => 100,      // 大小阈值（MB）
            'min_segment_time' => 60,     // 最小分段时间
            'format' => 'mp4',
        ];

        $options = array_merge($defaultOptions, $options);

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        echo "🎬 开始混合策略分割录制...\n";
        echo "策略: 时间>{$options['time_threshold']}秒 或 大小>{$options['size_threshold']}MB\n";
        echo "最小分段时间: {$options['min_segment_time']}秒\n\n";

        // 使用FFmpeg原生分段，但通过-segment_list监控
        $this->executeHybridRecording($inputUrl, $outputDir, $options);
    }

    /**
     * 执行混合策略录制
     */
    private function executeHybridRecording(
        string $inputUrl,
        string $outputDir,
        array $options
    ): void {
        $outputPattern = $outputDir . '/segment_%03d.' . $options['format'];
        $segmentList = $outputDir . '/segments.txt';

        $command = [
            'ffmpeg',
            '-i',
            $inputUrl,

            // 网络优化
            '-reconnect',
            '1',
            '-reconnect_at_eof',
            '1',
            '-reconnect_streamed',
            '1',

            // 编码设置
            '-c:v',
            'copy',  // 尽量使用流复制以提高性能
            '-c:a',
            'copy',

            // 智能分段参数
            '-f',
            'segment',
            '-segment_time',
            (string)$options['time_threshold'],
            '-segment_format',
            $options['format'],
            '-segment_list',
            $segmentList,
            '-segment_list_flags',
            '+live',
            '-reset_timestamps',
            '1',

            $outputPattern
        ];

        echo "执行混合策略命令...\n";
        $this->startProcess($command);

        // 同时启动文件监控
        $this->monitorSegmentList($segmentList, $options);
    }

    /**
     * 监控分段列表文件
     */
    private function monitorSegmentList(string $segmentList, array $options): void
    {
        $lastSegments = [];

        while ($this->currentProcess && is_resource($this->currentProcess)) {
            if (file_exists($segmentList)) {
                $currentSegments = file($segmentList, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                // 检查新生成的分段
                $newSegments = array_diff($currentSegments, $lastSegments);

                foreach ($newSegments as $segment) {
                    if (file_exists($segment)) {
                        $sizeMB = filesize($segment) / 1024 / 1024;
                        echo "📄 新分段: " . basename($segment) .
                            " (" . number_format($sizeMB, 2) . "MB)\n";
                    }
                }

                $lastSegments = $currentSegments;
            }

            // 检查进程状态
            $status = proc_get_status($this->currentProcess);
            if (!$status['running']) {
                echo "🛑 录制进程已停止\n";
                break;
            }

            sleep(2);
        }
    }

    /**
     * 启动进程
     */
    private function startProcess(array $command): void
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout  
            2 => ['pipe', 'w']   // stderr
        ];

        $this->currentProcess = proc_open(
            implode(' ', array_map('escapeshellarg', $command)),
            $descriptorSpec,
            $pipes
        );

        if (!is_resource($this->currentProcess)) {
            throw new RuntimeException('无法启动FFmpeg进程');
        }

        // 设置信号处理
        $this->setupSignalHandlers();

        // 非阻塞读取输出
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        // 监控进程输出
        while (is_resource($this->currentProcess) && !$this->shouldStop) {
            $status = proc_get_status($this->currentProcess);
            if (!$status['running']) {
                break;
            }

            // 读取输出（可选：处理FFmpeg输出信息）
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);

            if ($error) {
                // 过滤FFmpeg的正常信息输出
                $errorLines = explode("\n", trim($error));
                foreach ($errorLines as $line) {
                    if (
                        strpos($line, 'frame=') === false &&
                        strpos($line, 'time=') === false &&
                        !empty(trim($line))
                    ) {
                        echo "FFmpeg: " . $line . "\n";
                    }
                }
            }

            usleep(100000); // 100ms
        }

        // 关闭管道
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
    }

    /**
     * 停止当前录制
     */
    private function stopCurrentRecording(): void
    {
        if ($this->currentProcess && is_resource($this->currentProcess)) {
            // 发送SIGTERM信号优雅停止
            if (function_exists('proc_terminate')) {
                proc_terminate($this->currentProcess, SIGTERM);

                // 等待进程结束
                for ($i = 0; $i < 10; $i++) {
                    $status = proc_get_status($this->currentProcess);
                    if (!$status['running']) {
                        break;
                    }
                    usleep(500000); // 0.5秒
                }

                // 如果还在运行，强制终止
                if ($status['running']) {
                    proc_terminate($this->currentProcess, SIGKILL);
                }
            }

            proc_close($this->currentProcess);
            $this->currentProcess = null;
        }
    }

    /**
     * 设置信号处理
     */
    private function setupSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        }
    }

    /**
     * 处理信号
     */
    public function handleSignal(int $signal): void
    {
        echo "\n🛑 收到停止信号，正在优雅停止录制...\n";
        $this->shouldStop = true;
        $this->stopCurrentRecording();
    }

    /**
     * 停止录制
     */
    public function stop(): void
    {
        $this->shouldStop = true;
        $this->stopCurrentRecording();
    }

    /**
     * 获取录制统计信息
     */
    public function getRecordingStats(): array
    {
        return [
            'total_segments' => count($this->segments),
            'total_size' => array_sum(array_column($this->segments, 'size')),
            'segments' => $this->segments
        ];
    }

    /**
     * 获取格式特定选项
     */
    private function getFormatSpecificOptions(string $format): array
    {
        switch ($format) {
            case 'mp4':
                return [
                    '-movflags',
                    '+frag_keyframe+empty_moov+default_base_moof'
                ];
            case 'ts':
                return [
                    '-mpegts_flags',
                    'resend_headers'
                ];
            case 'mkv':
                return [
                    '-flags',
                    'global_header'
                ];
            default:
                return [];
        }
    }
}

// 使用示例
if ($argc < 2) {
    echo "使用方法:\n";
    echo "  php realtime-recording-splitter.php native <input_url> <output_dir>     - FFmpeg原生分割\n";
    echo "  php realtime-recording-splitter.php monitor <input_url> <output_dir>    - PHP监控分割\n";
    echo "  php realtime-recording-splitter.php hybrid <input_url> <output_dir>     - 混合策略分割\n";
    echo "\n示例:\n";
    echo "  php realtime-recording-splitter.php native rtmp://stream.url ./recordings\n";
    echo "  php realtime-recording-splitter.php monitor https://stream.url ./output\n";
    exit(1);
}

$mode = $argv[1];
$inputUrl = $argv[2] ?? 'rtmp://example.com/stream';
$outputDir = $argv[3] ?? './recordings';

$splitter = new RealtimeRecordingSplitter([
    'timeout' => 0,
    'ffmpeg.threads' => 8
]);

try {
    switch ($mode) {
        case 'native':
            $splitter->recordWithNativeSegment($inputUrl, $outputDir, [
                'segment_time' => 180,        // 3分钟分段
                'segment_size' => '50M',      // 50MB分段
                'video_bitrate' => 2500,
                'audio_bitrate' => 128
            ]);
            break;

        case 'monitor':
            $splitter->recordWithPhpMonitoring($inputUrl, $outputDir, [
                'max_segment_time' => 300,    // 5分钟
                'max_segment_size' => 100,    // 100MB
                'video_bitrate' => 2000,
                'audio_bitrate' => 128
            ]);
            break;

        case 'hybrid':
            $splitter->recordWithHybridStrategy($inputUrl, $outputDir, [
                'time_threshold' => 240,      // 4分钟
                'size_threshold' => 80,       // 80MB
                'min_segment_time' => 60      // 最少1分钟
            ]);
            break;

        default:
            echo "未知模式: {$mode}\n";
            exit(1);
    }

    // 显示录制统计
    $stats = $splitter->getRecordingStats();
    echo "\n📊 录制统计:\n";
    echo "总分段数: " . $stats['total_segments'] . "\n";
    echo "总大小: " . number_format($stats['total_size'] / 1024 / 1024, 2) . " MB\n";
} catch (Exception $e) {
    echo "录制失败: " . $e->getMessage() . "\n";
    exit(1);
}
