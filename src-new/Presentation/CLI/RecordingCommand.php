<?php

declare(strict_types=1);

namespace LiveStream\Presentation\CLI;

use LiveStream\Application\Services\RecordingService;
use LiveStream\Application\DTOs\RecordingRequest;
use LiveStream\Domain\ValueObjects\RecordingId;
use LiveStream\Shared\Contracts\ContainerInterface;
use Throwable;

/**
 * 录制命令行接口
 * 
 * 提供完整的录制管理CLI操作
 */
final class RecordingCommand
{
    private RecordingService $recordingService;

    public function __construct(ContainerInterface $container)
    {
        $this->recordingService = $container->make(RecordingService::class);
    }

    /**
     * 启动录制
     *
     * @param array $args 命令行参数
     * @return int 退出码
     */
    public function start(array $args): int
    {
        try {
            $request = $this->parseStartArguments($args);
            
            echo "正在启动录制...\n";
            echo "URL: {$request->url}\n";
            echo "输出: {$request->outputPath}\n";
            echo "质量: {$request->quality}\n";
            
            if ($request->isSplittingEnabled()) {
                echo "分割: 启用\n";
                if ($request->splitDuration) {
                    echo "  - 时长分割: {$request->splitDuration}秒\n";
                }
                if ($request->splitSize) {
                    echo "  - 大小分割: {$request->splitSize}MB\n";
                }
            }
            
            echo "\n";

            $progressCallback = function (string $type, string $buffer) {
                // 简单的进度显示
                if ($type === 'stderr' && strpos($buffer, 'time=') !== false) {
                    if (preg_match('/time=(\d+:\d+:\d+\.\d+)/', $buffer, $matches)) {
                        echo "\r录制时长: {$matches[1]}";
                    }
                }
            };

            $response = $this->recordingService->startRecording($request, $progressCallback);

            if ($response->isSuccessful()) {
                echo "\n✅ 录制启动成功!\n";
                echo "录制ID: {$response->id->getValue()}\n";
                echo "句柄ID: {$response->getHandleId()}\n";
                echo "\n使用以下命令查看状态:\n";
                echo "php recording.php status {$response->id->getValue()}\n";
                return 0;
            } else {
                echo "\n❌ 录制启动失败: {$response->message}\n";
                return 1;
            }

        } catch (Throwable $e) {
            echo "\n❌ 错误: {$e->getMessage()}\n";
            if (isset($_ENV['DEBUG']) && $_ENV['DEBUG']) {
                echo "\n调试信息:\n{$e->getTraceAsString()}\n";
            }
            return 1;
        }
    }

    /**
     * 停止录制
     *
     * @param array $args
     * @return int
     */
    public function stop(array $args): int
    {
        try {
            if (empty($args['id'])) {
                echo "❌ 错误: 请提供录制ID\n";
                echo "用法: php recording.php stop <recording_id>\n";
                return 1;
            }

            $recordingId = RecordingId::fromString($args['id']);
            
            echo "正在停止录制 {$recordingId->getValue()}...\n";
            
            $success = $this->recordingService->stopRecording($recordingId);
            
            if ($success) {
                echo "✅ 录制已停止\n";
                return 0;
            } else {
                echo "❌ 停止录制失败\n";
                return 1;
            }

        } catch (Throwable $e) {
            echo "❌ 错误: {$e->getMessage()}\n";
            return 1;
        }
    }

    /**
     * 查看录制状态
     *
     * @param array $args
     * @return int
     */
    public function status(array $args): int
    {
        try {
            if (empty($args['id'])) {
                echo "❌ 错误: 请提供录制ID\n";
                echo "用法: php recording.php status <recording_id>\n";
                return 1;
            }

            $recordingId = RecordingId::fromString($args['id']);
            $status = $this->recordingService->getRecordingStatus($recordingId);

            if ($status === null) {
                echo "❌ 录制记录不存在: {$recordingId->getValue()}\n";
                return 1;
            }

            $this->displayRecordingStatus($status);
            return 0;

        } catch (Throwable $e) {
            echo "❌ 错误: {$e->getMessage()}\n";
            return 1;
        }
    }

    /**
     * 列出所有录制记录
     *
     * @param array $args
     * @return int
     */
    public function list(array $args): int
    {
        try {
            $limit = (int)($args['limit'] ?? 20);
            $offset = (int)($args['offset'] ?? 0);

            $recordings = $this->recordingService->listRecordings($limit, $offset);

            if (empty($recordings)) {
                echo "📝 暂无录制记录\n";
                return 0;
            }

            echo "📋 录制记录列表:\n\n";
            
            foreach ($recordings as $recording) {
                $this->displayRecordingSummary($recording);
                echo "\n";
            }

            return 0;

        } catch (Throwable $e) {
            echo "❌ 错误: {$e->getMessage()}\n";
            return 1;
        }
    }

    /**
     * 显示帮助信息
     *
     * @return int
     */
    public function help(): int
    {
        echo "🎥 多平台直播录制工具\n\n";
        echo "用法:\n";
        echo "  php recording.php <command> [options]\n\n";
        echo "命令:\n";
        echo "  start <url> <output>     启动录制\n";
        echo "  stop <recording_id>      停止录制\n";
        echo "  status <recording_id>    查看录制状态\n";
        echo "  list                     列出所有录制记录\n";
        echo "  help                     显示帮助信息\n\n";
        echo "启动录制选项:\n";
        echo "  --quality <quality>      视频质量 (origin|high|medium|low)\n";
        echo "  --format <format>        输出格式 (mp4|flv|mkv|ts)\n";
        echo "  --split-duration <sec>   按时长分割 (秒)\n";
        echo "  --split-size <mb>        按大小分割 (MB)\n";
        echo "  --enable-splitting       启用分割\n\n";
        echo "示例:\n";
        echo "  php recording.php start https://live.douyin.com/123 ./output.mp4\n";
        echo "  php recording.php start https://live.douyin.com/123 ./output.mp4 --quality high --split-duration 300\n";
        echo "  php recording.php status rec_20241208_123456_abcd1234\n";
        echo "  php recording.php list --limit 10\n";
        
        return 0;
    }

    /**
     * 解析启动录制的参数
     *
     * @param array $args
     * @return RecordingRequest
     */
    private function parseStartArguments(array $args): RecordingRequest
    {
        if (empty($args['url'])) {
            throw new \InvalidArgumentException('请提供直播URL');
        }

        if (empty($args['output'])) {
            throw new \InvalidArgumentException('请提供输出文件路径');
        }

        return RecordingRequest::fromArray([
            'url' => $args['url'],
            'output_path' => $args['output'],
            'quality' => $args['quality'] ?? 'origin',
            'format' => $args['format'] ?? 'mp4',
            'enable_splitting' => isset($args['enable_splitting']) || isset($args['split_duration']) || isset($args['split_size']),
            'split_duration' => isset($args['split_duration']) ? (int)$args['split_duration'] : null,
            'split_size' => isset($args['split_size']) ? (int)$args['split_size'] : null,
        ]);
    }

    /**
     * 显示录制状态详情
     *
     * @param array $status
     */
    private function displayRecordingStatus(array $status): void
    {
        echo "📊 录制状态详情:\n";
        echo "ID: {$status['id']}\n";
        echo "状态: {$status['status_display']} ({$status['status']})\n";
        echo "URL: {$status['url']}\n";
        echo "输出: {$status['output_path']}\n";
        echo "质量: {$status['quality']}\n";
        
        if ($status['started_at']) {
            echo "开始时间: {$status['started_at']}\n";
        }
        
        if ($status['completed_at']) {
            echo "结束时间: {$status['completed_at']}\n";
        }
        
        echo "录制时长: {$status['recorded_duration_human']}\n";
        echo "分段数量: {$status['total_segments']}\n";
        echo "错误数量: {$status['total_errors']}\n";
        
        if ($status['is_active']) {
            echo "🟢 录制进行中\n";
        } elseif ($status['is_successful']) {
            echo "✅ 录制成功完成\n";
        } elseif ($status['is_finished']) {
            echo "🔴 录制已结束\n";
        }
    }

    /**
     * 显示录制记录摘要
     *
     * @param array $recording
     */
    private function displayRecordingSummary(array $recording): void
    {
        $statusIcon = match ($recording['status']) {
            'recording' => '🔴',
            'completed' => '✅',
            'failed' => '❌',
            'cancelled' => '⏹️',
            default => '⏸️'
        };

        echo "{$statusIcon} {$recording['id']} - {$recording['status_display']}\n";
        echo "   URL: {$recording['url']}\n";
        echo "   时长: {$recording['recorded_duration_human']}\n";
        
        if ($recording['started_at']) {
            echo "   时间: {$recording['started_at']}";
            if ($recording['completed_at']) {
                echo " -> {$recording['completed_at']}";
            }
            echo "\n";
        }
    }
}