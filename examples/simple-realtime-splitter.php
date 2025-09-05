<?php

declare(strict_types=1);

/**
 * 简化版实时录制分割示例
 * 
 * 直接使用 php-ffmpeg 包实现基于时间和大小的实时分割
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

class SimpleRealtimeSplitter
{
    private FFMpeg $ffmpeg;
    private bool $recording = false;
    private int $segmentCounter = 0;
    private array $segments = [];

    public function __construct()
    {
        $this->ffmpeg = FFMpeg::create([
            'timeout' => 0,  // 无超时限制
            'ffmpeg.threads' => 4
        ]);
    }

    /**
     * 按时间分割录制
     */
    public function recordByTime(
        string $inputUrl,
        string $outputDir,
        int $segmentDurationSeconds = 300
    ): void {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $this->recording = true;

        echo "🎬 开始按时间分割录制...\n";
        echo "分段时长: {$segmentDurationSeconds}秒\n";
        echo "按 Ctrl+C 停止录制\n\n";

        // 设置信号处理
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->recording = false;
                echo "\n🛑 正在停止录制...\n";
            });
        }

        while ($this->recording) {
            $segmentPath = sprintf(
                '%s/segment_%03d_%s.mp4',
                $outputDir,
                ++$this->segmentCounter,
                date('YmdHis')
            );

            echo "🎥 录制分段 {$this->segmentCounter}: " . basename($segmentPath) . "\n";

            try {
                $this->recordSegment($inputUrl, $segmentPath, $segmentDurationSeconds);

                if (file_exists($segmentPath)) {
                    $sizeMB = filesize($segmentPath) / 1024 / 1024;
                    $this->segments[] = [
                        'path' => $segmentPath,
                        'size' => filesize($segmentPath),
                        'duration' => $segmentDurationSeconds
                    ];

                    echo "✅ 分段完成: " . number_format($sizeMB, 2) . " MB\n\n";
                } else {
                    echo "❌ 分段文件未生成\n\n";
                }
            } catch (Exception $e) {
                echo "❌ 录制分段失败: " . $e->getMessage() . "\n";
                break;
            }

            // 检查信号
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        $this->printSummary();
    }

    /**
     * 按文件大小分割录制
     */
    public function recordBySize(
        string $inputUrl,
        string $outputDir,
        int $maxSizeMB = 100
    ): void {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $this->recording = true;

        echo "🎬 开始按大小分割录制...\n";
        echo "最大分段大小: {$maxSizeMB}MB\n";
        echo "按 Ctrl+C 停止录制\n\n";

        // 信号处理
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->recording = false;
                echo "\n🛑 正在停止录制...\n";
            });
        }

        while ($this->recording) {
            $segmentPath = sprintf(
                '%s/segment_%03d_%s.mp4',
                $outputDir,
                ++$this->segmentCounter,
                date('YmdHis')
            );

            echo "🎥 录制分段 {$this->segmentCounter}: " . basename($segmentPath) . "\n";

            $startTime = time();

            try {
                $this->recordSegmentBySize($inputUrl, $segmentPath, $maxSizeMB);

                $duration = time() - $startTime;

                if (file_exists($segmentPath)) {
                    $sizeMB = filesize($segmentPath) / 1024 / 1024;
                    $this->segments[] = [
                        'path' => $segmentPath,
                        'size' => filesize($segmentPath),
                        'duration' => $duration
                    ];

                    echo "✅ 分段完成: " . number_format($sizeMB, 2) . " MB, {$duration}秒\n\n";
                }
            } catch (Exception $e) {
                echo "❌ 录制分段失败: " . $e->getMessage() . "\n";
                break;
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        $this->printSummary();
    }

    /**
     * 智能分割：同时考虑时间和大小
     */
    public function recordSmart(
        string $inputUrl,
        string $outputDir,
        int $maxDurationSeconds = 300,
        int $maxSizeMB = 100,
        int $minDurationSeconds = 60
    ): void {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $this->recording = true;

        echo "🎬 开始智能分割录制...\n";
        echo "最大时长: {$maxDurationSeconds}秒\n";
        echo "最大大小: {$maxSizeMB}MB\n";
        echo "最小时长: {$minDurationSeconds}秒\n";
        echo "按 Ctrl+C 停止录制\n\n";

        // 信号处理
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->recording = false;
                echo "\n🛑 正在停止录制...\n";
            });
        }

        while ($this->recording) {
            $segmentPath = sprintf(
                '%s/segment_%03d_%s.mp4',
                $outputDir,
                ++$this->segmentCounter,
                date('YmdHis')
            );

            echo "🎥 录制分段 {$this->segmentCounter}: " . basename($segmentPath) . "\n";

            $startTime = time();

            try {
                $this->recordSegmentSmart(
                    $inputUrl,
                    $segmentPath,
                    $maxDurationSeconds,
                    $maxSizeMB,
                    $minDurationSeconds
                );

                $duration = time() - $startTime;

                if (file_exists($segmentPath)) {
                    $sizeMB = filesize($segmentPath) / 1024 / 1024;
                    $this->segments[] = [
                        'path' => $segmentPath,
                        'size' => filesize($segmentPath),
                        'duration' => $duration
                    ];

                    echo "✅ 分段完成: " . number_format($sizeMB, 2) . " MB, {$duration}秒\n\n";
                }
            } catch (Exception $e) {
                echo "❌ 录制分段失败: " . $e->getMessage() . "\n";
                break;
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        $this->printSummary();
    }

    /**
     * 录制单个时间分段
     */
    private function recordSegment(string $inputUrl, string $outputPath, int $duration): void
    {
        // 使用临时文件避免权限问题
        $tempPath = $outputPath . '.tmp';

        $video = $this->ffmpeg->open($inputUrl);

        $format = new X264();
        $format->setKiloBitrate(2500)
            ->setAudioKiloBitrate(128)
            ->setAdditionalParameters([
                '-t',
                (string)$duration,  // 限制录制时长
                '-avoid_negative_ts',
                'make_zero',
                '-fflags',
                '+genpts'
            ]);

        $video->save($format, $tempPath);

        // 移动到最终位置
        if (file_exists($tempPath)) {
            rename($tempPath, $outputPath);
        }
    }

    /**
     * 按大小录制分段
     */
    private function recordSegmentBySize(string $inputUrl, string $outputPath, int $maxSizeMB): void
    {
        $tempPath = $outputPath . '.tmp';

        // 启动后台录制进程
        $command = sprintf(
            'ffmpeg -i %s -c:v libx264 -c:a aac -preset fast -crf 23 -y %s > /dev/null 2>&1 & echo $!',
            escapeshellarg($inputUrl),
            escapeshellarg($tempPath)
        );

        $pid = (int)shell_exec($command);

        if (!$pid) {
            throw new RuntimeException('无法启动录制进程');
        }

        // 监控文件大小
        while ($this->recording) {
            if (file_exists($tempPath)) {
                $sizeMB = filesize($tempPath) / 1024 / 1024;

                echo "\r📊 当前大小: " . number_format($sizeMB, 2) . " MB";

                if ($sizeMB >= $maxSizeMB) {
                    echo "\n📁 达到大小限制，停止录制\n";
                    break;
                }
            }

            // 检查进程是否仍在运行
            $running = shell_exec("ps -p {$pid} -o pid= 2>/dev/null");
            if (!trim($running)) {
                echo "\n❌ 录制进程已停止\n";
                break;
            }

            sleep(1);
        }

        // 停止录制进程
        shell_exec("kill {$pid} 2>/dev/null");

        // 等待进程结束
        sleep(2);

        // 移动文件
        if (file_exists($tempPath)) {
            rename($tempPath, $outputPath);
        }
    }

    /**
     * 智能录制分段
     */
    private function recordSegmentSmart(
        string $inputUrl,
        string $outputPath,
        int $maxDuration,
        int $maxSizeMB,
        int $minDuration
    ): void {
        $tempPath = $outputPath . '.tmp';
        $startTime = time();

        // 启动录制进程
        $command = sprintf(
            'ffmpeg -i %s -c:v libx264 -c:a aac -preset fast -crf 23 -y %s > /dev/null 2>&1 & echo $!',
            escapeshellarg($inputUrl),
            escapeshellarg($tempPath)
        );

        $pid = (int)shell_exec($command);

        if (!$pid) {
            throw new RuntimeException('无法启动录制进程');
        }

        while ($this->recording) {
            $elapsed = time() - $startTime;
            $shouldStop = false;
            $reason = '';

            // 检查最小时长
            if ($elapsed >= $minDuration) {
                if (file_exists($tempPath)) {
                    $sizeMB = filesize($tempPath) / 1024 / 1024;

                    // 检查大小限制
                    if ($sizeMB >= $maxSizeMB) {
                        $shouldStop = true;
                        $reason = "达到大小限制({$maxSizeMB}MB)";
                    }
                    // 检查时间限制
                    elseif ($elapsed >= $maxDuration) {
                        $shouldStop = true;
                        $reason = "达到时间限制({$maxDuration}秒)";
                    }

                    echo "\r📊 时长: {$elapsed}s, 大小: " . number_format($sizeMB, 2) . " MB";
                }
            }

            if ($shouldStop) {
                echo "\n🎯 {$reason}，停止录制\n";
                break;
            }

            // 检查进程状态
            $running = shell_exec("ps -p {$pid} -o pid= 2>/dev/null");
            if (!trim($running)) {
                echo "\n❌ 录制进程已停止\n";
                break;
            }

            sleep(1);
        }

        // 停止录制
        shell_exec("kill {$pid} 2>/dev/null");
        sleep(2);

        if (file_exists($tempPath)) {
            rename($tempPath, $outputPath);
        }
    }

    /**
     * 打印录制总结
     */
    private function printSummary(): void
    {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "📊 录制总结\n";
        echo str_repeat("=", 50) . "\n";

        $totalSize = array_sum(array_column($this->segments, 'size'));
        $totalDuration = array_sum(array_column($this->segments, 'duration'));

        echo "总分段数: " . count($this->segments) . "\n";
        echo "总大小: " . number_format($totalSize / 1024 / 1024, 2) . " MB\n";
        echo "总时长: " . gmdate('H:i:s', $totalDuration) . "\n";

        if (!empty($this->segments)) {
            echo "\n📁 分段详情:\n";
            foreach ($this->segments as $i => $segment) {
                $sizeMB = $segment['size'] / 1024 / 1024;
                $duration = gmdate('H:i:s', $segment['duration']);
                echo sprintf(
                    "%2d. %s (%s, %s)\n",
                    $i + 1,
                    basename($segment['path']),
                    number_format($sizeMB, 2) . " MB",
                    $duration
                );
            }
        }

        echo str_repeat("=", 50) . "\n";
    }

    /**
     * 停止录制
     */
    public function stop(): void
    {
        $this->recording = false;
    }
}

// 使用示例
if ($argc < 2) {
    echo "简化版实时录制分割工具\n";
    echo "使用方法:\n";
    echo "  php simple-realtime-splitter.php time <input_url> [output_dir] [duration_seconds]\n";
    echo "  php simple-realtime-splitter.php size <input_url> [output_dir] [max_size_mb]\n";
    echo "  php simple-realtime-splitter.php smart <input_url> [output_dir]\n";
    echo "\n示例:\n";
    echo "  php simple-realtime-splitter.php time rtmp://stream.url ./recordings 180\n";
    echo "  php simple-realtime-splitter.php size https://stream.url ./output 50\n";
    echo "  php simple-realtime-splitter.php smart rtmp://stream.url ./smart\n";
    exit(1);
}

$mode = $argv[1];
$inputUrl = $argv[2] ?? 'rtmp://example.com/stream';
$outputDir = $argv[3] ?? './recordings';

$splitter = new SimpleRealtimeSplitter();

try {
    switch ($mode) {
        case 'time':
            $duration = (int)($argv[4] ?? 300);  // 默认5分钟
            $splitter->recordByTime($inputUrl, $outputDir, $duration);
            break;

        case 'size':
            $maxSize = (int)($argv[4] ?? 100);   // 默认100MB
            $splitter->recordBySize($inputUrl, $outputDir, $maxSize);
            break;

        case 'smart':
            $splitter->recordSmart($inputUrl, $outputDir);
            break;

        default:
            echo "未知模式: {$mode}\n";
            echo "支持的模式: time, size, smart\n";
            exit(1);
    }
} catch (Exception $e) {
    echo "录制失败: " . $e->getMessage() . "\n";
    exit(1);
}
