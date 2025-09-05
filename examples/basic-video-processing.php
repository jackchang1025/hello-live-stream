<?php

declare(strict_types=1);

/**
 * PHP-FFMpeg 基础视频处理示例
 * 
 * 本示例展示了如何使用PHP-FFMpeg进行基础的视频处理操作
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Format\Video\X264;
use FFMpeg\Format\Video\WebM;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Filters\Video\ResizeFilter;

class BasicVideoProcessor
{
    private FFMpeg $ffmpeg;
    private FFProbe $ffprobe;

    public function __construct()
    {
        // 创建FFMpeg和FFProbe实例
        $this->ffmpeg = FFMpeg::create([
            'timeout' => 3600,
            'ffmpeg.threads' => 4,
        ]);

        $this->ffprobe = FFProbe::create();
    }

    /**
     * 获取视频信息
     */
    public function getVideoInfo(string $videoPath): array
    {
        try {
            // 验证文件是否有效
            if (!$this->ffprobe->isValid($videoPath)) {
                throw new InvalidArgumentException("无效的视频文件: {$videoPath}");
            }

            // 获取格式信息
            $format = $this->ffprobe->format($videoPath);

            // 获取流信息
            $streams = $this->ffprobe->streams($videoPath);
            $videoStream = $streams->videos()->first();
            $audioStream = $streams->audios()->first();

            return [
                'duration' => (float)$format->get('duration'),
                'size' => (int)$format->get('size'),
                'bitrate' => (int)$format->get('bit_rate'),
                'format_name' => $format->get('format_name'),
                'video' => $videoStream ? [
                    'codec' => $videoStream->get('codec_name'),
                    'width' => (int)$videoStream->get('width'),
                    'height' => (int)$videoStream->get('height'),
                    'frame_rate' => $videoStream->get('r_frame_rate'),
                    'bitrate' => (int)$videoStream->get('bit_rate', 0),
                ] : null,
                'audio' => $audioStream ? [
                    'codec' => $audioStream->get('codec_name'),
                    'sample_rate' => (int)$audioStream->get('sample_rate'),
                    'channels' => (int)$audioStream->get('channels'),
                    'bitrate' => (int)$audioStream->get('bit_rate', 0),
                ] : null,
            ];
        } catch (Exception $e) {
            throw new RuntimeException("获取视频信息失败: " . $e->getMessage());
        }
    }

    /**
     * 视频转码
     */
    public function transcodeVideo(string $inputPath, string $outputPath, array $options = []): void
    {
        try {
            $video = $this->ffmpeg->open($inputPath);

            // 创建H.264格式
            $format = new X264();

            // 设置基础参数
            $format->setKiloBitrate($options['video_bitrate'] ?? 2000)
                ->setAudioChannels($options['audio_channels'] ?? 2)
                ->setAudioKiloBitrate($options['audio_bitrate'] ?? 128);

            // 添加进度监听
            $format->on('progress', function ($video, $format, $percentage) use ($outputPath) {
                printf("转码进度 [%s]: %d%%\n", basename($outputPath), $percentage);
            });

            // 保存视频
            $video->save($format, $outputPath);

            echo "转码完成: {$outputPath}\n";
        } catch (Exception $e) {
            throw new RuntimeException("视频转码失败: " . $e->getMessage());
        }
    }

    /**
     * 视频缩放
     */
    public function resizeVideo(
        string $inputPath,
        string $outputPath,
        int $width,
        int $height,
        string $mode = ResizeFilter::RESIZEMODE_FIT
    ): void {
        try {
            $video = $this->ffmpeg->open($inputPath);

            // 应用缩放滤镜
            $video->filters()->resize(
                new Dimension($width, $height),
                $mode,
                true
            );

            $format = new X264();
            $format->setKiloBitrate(2000)
                ->setAudioKiloBitrate(128);

            $video->save($format, $outputPath);

            echo "视频缩放完成: {$outputPath} ({$width}x{$height})\n";
        } catch (Exception $e) {
            throw new RuntimeException("视频缩放失败: " . $e->getMessage());
        }
    }

    /**
     * 提取视频帧
     */
    public function extractFrames(string $inputPath, string $outputDir, array $timePoints = []): array
    {
        try {
            $video = $this->ffmpeg->open($inputPath);
            $extractedFrames = [];

            // 如果没有指定时间点，则提取10秒、30秒、60秒的帧
            if (empty($timePoints)) {
                $timePoints = [10, 30, 60];
            }

            foreach ($timePoints as $seconds) {
                $outputPath = $outputDir . '/frame_' . $seconds . 's.jpg';

                $frame = $video->frame(TimeCode::fromSeconds($seconds));
                $frame->save($outputPath);

                $extractedFrames[] = $outputPath;
                echo "提取帧: {$outputPath}\n";
            }

            return $extractedFrames;
        } catch (Exception $e) {
            throw new RuntimeException("提取帧失败: " . $e->getMessage());
        }
    }

    /**
     * 视频剪辑
     */
    public function clipVideo(
        string $inputPath,
        string $outputPath,
        int $startSeconds,
        int $durationSeconds
    ): void {
        try {
            $video = $this->ffmpeg->open($inputPath);

            $clip = $video->clip(
                TimeCode::fromSeconds($startSeconds),
                TimeCode::fromSeconds($durationSeconds)
            );

            $format = new X264();
            $format->setKiloBitrate(2000)
                ->setAudioKiloBitrate(128);

            $clip->save($format, $outputPath);

            echo "视频剪辑完成: {$outputPath} (开始:{$startSeconds}s, 时长:{$durationSeconds}s)\n";
        } catch (Exception $e) {
            throw new RuntimeException("视频剪辑失败: " . $e->getMessage());
        }
    }

    /**
     * 生成缩略图GIF
     */
    public function createThumbnailGif(
        string $inputPath,
        string $outputPath,
        int $startSeconds = 10,
        int $duration = 3,
        int $width = 320,
        int $height = 240
    ): void {
        try {
            $video = $this->ffmpeg->open($inputPath);

            $gif = $video->gif(
                TimeCode::fromSeconds($startSeconds),
                new Dimension($width, $height),
                $duration
            );

            $gif->save($outputPath);

            echo "GIF缩略图创建完成: {$outputPath}\n";
        } catch (Exception $e) {
            throw new RuntimeException("创建GIF失败: " . $e->getMessage());
        }
    }

    /**
     * 批量处理视频
     */
    public function batchProcess(string $inputDir, string $outputDir): void
    {
        $videoFiles = glob($inputDir . '/*.{mp4,avi,mov,mkv}', GLOB_BRACE);

        if (empty($videoFiles)) {
            echo "在 {$inputDir} 中未找到视频文件\n";
            return;
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        foreach ($videoFiles as $videoFile) {
            $fileName = pathinfo($videoFile, PATHINFO_FILENAME);
            $outputPath = $outputDir . '/' . $fileName . '_processed.mp4';

            try {
                echo "处理文件: " . basename($videoFile) . "\n";

                // 获取视频信息
                $info = $this->getVideoInfo($videoFile);
                echo "  原始分辨率: {$info['video']['width']}x{$info['video']['height']}\n";
                echo "  时长: " . gmdate('H:i:s', (int)$info['duration']) . "\n";

                // 标准化处理：转为1080p H.264
                $this->resizeVideo($videoFile, $outputPath, 1920, 1080);

                // 创建缩略图
                $gifPath = $outputDir . '/' . $fileName . '_thumb.gif';
                $this->createThumbnailGif($videoFile, $gifPath);

                // 提取关键帧
                $frameDir = $outputDir . '/frames/' . $fileName;
                if (!is_dir($frameDir)) {
                    mkdir($frameDir, 0755, true);
                }
                $this->extractFrames($videoFile, $frameDir);

                echo "  处理完成: " . basename($outputPath) . "\n\n";
            } catch (Exception $e) {
                echo "  处理失败: " . $e->getMessage() . "\n\n";
            }
        }
    }
}

// 使用示例
if ($argc < 2) {
    echo "使用方法:\n";
    echo "  php basic-video-processing.php info <video_file>           - 获取视频信息\n";
    echo "  php basic-video-processing.php transcode <input> <output>  - 转码视频\n";
    echo "  php basic-video-processing.php resize <input> <output> <width> <height> - 缩放视频\n";
    echo "  php basic-video-processing.php clip <input> <output> <start> <duration> - 剪辑视频\n";
    echo "  php basic-video-processing.php gif <input> <output>        - 创建GIF\n";
    echo "  php basic-video-processing.php batch <input_dir> <output_dir> - 批量处理\n";
    exit(1);
}

$processor = new BasicVideoProcessor();
$command = $argv[1];

try {
    switch ($command) {
        case 'info':
            if ($argc < 3) {
                throw new InvalidArgumentException("请指定视频文件路径");
            }

            $info = $processor->getVideoInfo($argv[2]);

            echo "视频信息:\n";
            echo "  文件大小: " . number_format($info['size'] / 1024 / 1024, 2) . " MB\n";
            echo "  时长: " . gmdate('H:i:s', (int)$info['duration']) . "\n";
            echo "  格式: {$info['format_name']}\n";
            echo "  比特率: " . number_format($info['bitrate'] / 1000, 0) . " kbps\n";

            if ($info['video']) {
                echo "  视频编码: {$info['video']['codec']}\n";
                echo "  分辨率: {$info['video']['width']}x{$info['video']['height']}\n";
                echo "  帧率: {$info['video']['frame_rate']}\n";
            }

            if ($info['audio']) {
                echo "  音频编码: {$info['audio']['codec']}\n";
                echo "  采样率: {$info['audio']['sample_rate']} Hz\n";
                echo "  声道数: {$info['audio']['channels']}\n";
            }
            break;

        case 'transcode':
            if ($argc < 4) {
                throw new InvalidArgumentException("请指定输入和输出文件路径");
            }
            $processor->transcodeVideo($argv[2], $argv[3]);
            break;

        case 'resize':
            if ($argc < 6) {
                throw new InvalidArgumentException("请指定输入文件、输出文件、宽度和高度");
            }
            $processor->resizeVideo($argv[2], $argv[3], (int)$argv[4], (int)$argv[5]);
            break;

        case 'clip':
            if ($argc < 6) {
                throw new InvalidArgumentException("请指定输入文件、输出文件、开始时间和持续时间");
            }
            $processor->clipVideo($argv[2], $argv[3], (int)$argv[4], (int)$argv[5]);
            break;

        case 'gif':
            if ($argc < 4) {
                throw new InvalidArgumentException("请指定输入和输出文件路径");
            }
            $processor->createThumbnailGif($argv[2], $argv[3]);
            break;

        case 'batch':
            if ($argc < 4) {
                throw new InvalidArgumentException("请指定输入目录和输出目录");
            }
            $processor->batchProcess($argv[2], $argv[3]);
            break;

        default:
            throw new InvalidArgumentException("未知命令: {$command}");
    }
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}
