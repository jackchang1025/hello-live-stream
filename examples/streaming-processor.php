<?php

declare(strict_types=1);

/**
 * PHP-FFMpeg 流媒体处理示例
 * 
 * 本示例展示了如何处理实时流媒体、直播推流等场景
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use FFMpeg\Format\Audio\Mp3;
use FFMpeg\Coordinate\Dimension;

class StreamingProcessor
{
    private FFMpeg $ffmpeg;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'timeout' => 0,  // 无超时限制
            'ffmpeg.threads' => 4,
            'temporary_directory' => '/tmp/streaming'
        ], $config);

        $this->ffmpeg = FFMpeg::create($this->config);
    }

    /**
     * RTMP推流到多个平台
     */
    public function multiPlatformStreaming(string $inputSource, array $platforms): void
    {
        try {
            $inputs = [$inputSource];

            // 如果有水印文件，添加到输入
            if (isset($this->config['watermark'])) {
                $inputs[] = $this->config['watermark'];
            }

            $advancedMedia = $this->ffmpeg->openAdvanced($inputs);

            // 添加水印
            if (isset($this->config['watermark'])) {
                $advancedMedia->filters()
                    ->custom(
                        '[0:v][1:v]',
                        'overlay=W-w-10:H-h-10',  // 右下角
                        '[watermarked]'
                    );
                $videoStream = '[watermarked]';
            } else {
                $videoStream = '0:v';
            }

            foreach ($platforms as $platform) {
                $this->addPlatformOutput($advancedMedia, $videoStream, $platform);
            }

            echo "开始多平台推流...\n";
            foreach ($platforms as $platform) {
                echo "  推流到: {$platform['name']} ({$platform['resolution']})\n";
            }

            $advancedMedia->save();
        } catch (Exception $e) {
            throw new RuntimeException("多平台推流失败: " . $e->getMessage());
        }
    }

    private function addPlatformOutput($advancedMedia, string $videoStream, array $platform): void
    {
        $format = new X264();

        // 根据平台配置格式
        $format->setKiloBitrate($platform['video_bitrate'] ?? 2500)
            ->setAudioKiloBitrate($platform['audio_bitrate'] ?? 128)
            ->setAdditionalParameters([
                '-f',
                'flv',
                '-preset',
                $platform['preset'] ?? 'veryfast',
                '-tune',
                'zerolatency',
                '-g',
                (string)($platform['keyframe_interval'] ?? 60),
                '-sc_threshold',
                '0',
                '-b:v',
                $platform['video_bitrate'] . 'k',
                '-maxrate',
                ($platform['video_bitrate'] * 1.2) . 'k',
                '-bufsize',
                ($platform['video_bitrate'] * 2) . 'k'
            ]);

        // 添加视频缩放滤镜
        $resolution = $platform['resolution'] ?? '1920x1080';
        [$width, $height] = explode('x', $resolution);

        $advancedMedia->filters()
            ->custom($videoStream, "scale={$width}:{$height}", "[{$platform['name']}_scaled]");

        $advancedMedia->map(
            ["0:a", "[{$platform['name']}_scaled]"],
            $format,
            $platform['rtmp_url']
        );
    }

    /**
     * 录制直播流
     */
    public function recordLiveStream(
        string $streamUrl,
        string $outputPath,
        int $durationMinutes = 60,
        array $options = []
    ): void {
        try {
            $video = $this->ffmpeg->open($streamUrl);

            $format = new X264();
            $format->setKiloBitrate($options['video_bitrate'] ?? 4000)
                ->setAudioKiloBitrate($options['audio_bitrate'] ?? 192)
                ->setAdditionalParameters([
                    '-t',
                    (string)($durationMinutes * 60),  // 录制时长
                    '-avoid_negative_ts',
                    'make_zero',
                    '-fflags',
                    '+genpts'
                ]);

            // 添加进度监听
            $format->on('progress', function ($video, $format, $percentage) use ($outputPath) {
                $currentTime = gmdate('H:i:s');
                echo "\r[{$currentTime}] 录制进度: {$percentage}%";
            });

            echo "开始录制直播流: {$streamUrl}\n";
            echo "输出文件: {$outputPath}\n";
            echo "录制时长: {$durationMinutes} 分钟\n\n";

            $video->save($format, $outputPath);

            echo "\n录制完成: {$outputPath}\n";
        } catch (Exception $e) {
            throw new RuntimeException("录制直播流失败: " . $e->getMessage());
        }
    }

    /**
     * HLS切片处理
     */
    public function createHLSStream(string $inputVideo, string $outputDir, array $qualities = []): void
    {
        if (empty($qualities)) {
            $qualities = [
                ['name' => '720p', 'resolution' => '1280x720', 'bitrate' => 2500],
                ['name' => '480p', 'resolution' => '854x480', 'bitrate' => 1500],
                ['name' => '360p', 'resolution' => '640x360', 'bitrate' => 800]
            ];
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        try {
            $video = $this->ffmpeg->open($inputVideo);

            foreach ($qualities as $quality) {
                $qualityDir = $outputDir . '/' . $quality['name'];
                if (!is_dir($qualityDir)) {
                    mkdir($qualityDir, 0755, true);
                }

                $format = new X264();
                $format->setKiloBitrate($quality['bitrate'])
                    ->setAudioKiloBitrate(128);

                // 设置HLS参数
                $format->setAdditionalParameters([
                    '-hls_time',
                    '4',                    // 每段4秒
                    '-hls_list_size',
                    '0',              // 保留所有段
                    '-hls_segment_filename',
                    $qualityDir . '/segment_%03d.ts',
                    '-f',
                    'hls'
                ]);

                // 应用缩放
                [$width, $height] = explode('x', $quality['resolution']);
                $video->filters()->resize(new Dimension((int)$width, (int)$height));

                $playlistPath = $qualityDir . '/playlist.m3u8';

                echo "生成HLS流: {$quality['name']} ({$quality['resolution']})\n";
                $video->save($format, $playlistPath);
            }

            // 创建主播放列表
            $this->createMasterPlaylist($outputDir, $qualities);

            echo "HLS流生成完成: {$outputDir}\n";
        } catch (Exception $e) {
            throw new RuntimeException("创建HLS流失败: " . $e->getMessage());
        }
    }

    private function createMasterPlaylist(string $outputDir, array $qualities): void
    {
        $content = "#EXTM3U\n#EXT-X-VERSION:3\n\n";

        foreach ($qualities as $quality) {
            $bandwidth = $quality['bitrate'] * 1000;
            [$width, $height] = explode('x', $quality['resolution']);

            $content .= "#EXT-X-STREAM-INF:BANDWIDTH={$bandwidth},RESOLUTION={$width}x{$height}\n";
            $content .= "{$quality['name']}/playlist.m3u8\n\n";
        }

        file_put_contents($outputDir . '/master.m3u8', $content);
    }

    /**
     * DASH流处理
     */
    public function createDASHStream(string $inputVideo, string $outputDir): void
    {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        try {
            $video = $this->ffmpeg->open($inputVideo);

            $format = new X264();
            $format->setKiloBitrate(2500)
                ->setAudioKiloBitrate(128)
                ->setAdditionalParameters([
                    '-f',
                    'dash',
                    '-seg_duration',
                    '4',
                    '-adaptation_sets',
                    'id=0,streams=v id=1,streams=a',
                    '-use_template',
                    '1',
                    '-use_timeline',
                    '1'
                ]);

            $manifestPath = $outputDir . '/manifest.mpd';

            echo "生成DASH流...\n";
            $video->save($format, $manifestPath);

            echo "DASH流生成完成: {$outputDir}\n";
        } catch (Exception $e) {
            throw new RuntimeException("创建DASH流失败: " . $e->getMessage());
        }
    }

    /**
     * 直播转码和录制
     */
    public function liveTranscodeAndRecord(
        string $inputStream,
        array $transcodeConfigs,
        string $recordPath = null
    ): void {
        try {
            $inputs = [$inputStream];
            $advancedMedia = $this->ffmpeg->openAdvanced($inputs);

            // 添加转码输出
            foreach ($transcodeConfigs as $config) {
                $format = new X264();
                $format->setKiloBitrate($config['video_bitrate'])
                    ->setAudioKiloBitrate($config['audio_bitrate'])
                    ->setAdditionalParameters([
                        '-f',
                        'flv',
                        '-preset',
                        'veryfast',
                        '-tune',
                        'zerolatency'
                    ]);

                if (isset($config['resolution'])) {
                    [$width, $height] = explode('x', $config['resolution']);
                    $advancedMedia->filters()
                        ->custom('[0:v]', "scale={$width}:{$height}", "[scaled_{$config['name']}]");

                    $advancedMedia->map(
                        ["0:a", "[scaled_{$config['name']}]"],
                        $format,
                        $config['output_url']
                    );
                } else {
                    $advancedMedia->map(['0:a', '0:v'], $format, $config['output_url']);
                }
            }

            // 添加录制输出
            if ($recordPath) {
                $recordFormat = new X264();
                $recordFormat->setKiloBitrate(4000)
                    ->setAudioKiloBitrate(192);

                $advancedMedia->map(['0:a', '0:v'], $recordFormat, $recordPath);
            }

            echo "开始直播转码和录制...\n";
            foreach ($transcodeConfigs as $config) {
                echo "  转码输出: {$config['name']} -> {$config['output_url']}\n";
            }
            if ($recordPath) {
                echo "  录制文件: {$recordPath}\n";
            }

            $advancedMedia->save();
        } catch (Exception $e) {
            throw new RuntimeException("直播转码和录制失败: " . $e->getMessage());
        }
    }

    /**
     * 摄像头捕获和推流
     */
    public function webcamStreaming(string $devicePath, string $outputUrl, array $options = []): void
    {
        try {
            // 设置输入参数
            $inputParams = [
                '-f',
                'v4l2',                        // Linux视频设备
                '-framerate',
                (string)($options['fps'] ?? 30),
                '-video_size',
                $options['resolution'] ?? '1280x720'
            ];

            $format = new X264();
            $format->setInitialParameters($inputParams)
                ->setKiloBitrate($options['video_bitrate'] ?? 2000)
                ->setAudioKiloBitrate($options['audio_bitrate'] ?? 128)
                ->setAdditionalParameters([
                    '-f',
                    'flv',
                    '-preset',
                    'ultrafast',
                    '-tune',
                    'zerolatency',
                    '-g',
                    '60'
                ]);

            $video = $this->ffmpeg->open($devicePath);

            echo "开始摄像头推流...\n";
            echo "设备: {$devicePath}\n";
            echo "输出: {$outputUrl}\n";
            echo "分辨率: {$options['resolution']}\n";
            echo "帧率: {$options['fps']} fps\n\n";

            $video->save($format, $outputUrl);
        } catch (Exception $e) {
            throw new RuntimeException("摄像头推流失败: " . $e->getMessage());
        }
    }
}

// 使用示例
if ($argc < 2) {
    echo "使用方法:\n";
    echo "  php streaming-processor.php multi-stream <input> <config.json>    - 多平台推流\n";
    echo "  php streaming-processor.php record <stream_url> <output> [minutes] - 录制直播\n";
    echo "  php streaming-processor.php hls <input> <output_dir>               - 创建HLS流\n";
    echo "  php streaming-processor.php dash <input> <output_dir>              - 创建DASH流\n";
    echo "  php streaming-processor.php webcam <device> <rtmp_url>             - 摄像头推流\n";
    exit(1);
}

$command = $argv[1];

try {
    switch ($command) {
        case 'multi-stream':
            if ($argc < 4) {
                throw new InvalidArgumentException("需要输入源和配置文件");
            }

            $configFile = $argv[3];
            if (!file_exists($configFile)) {
                throw new InvalidArgumentException("配置文件不存在: {$configFile}");
            }

            $config = json_decode(file_get_contents($configFile), true);
            $processor = new StreamingProcessor($config['ffmpeg'] ?? []);

            $processor->multiPlatformStreaming($argv[2], $config['platforms']);
            break;

        case 'record':
            if ($argc < 4) {
                throw new InvalidArgumentException("需要流URL和输出路径");
            }

            $duration = $argc > 4 ? (int)$argv[4] : 60;
            $processor = new StreamingProcessor();
            $processor->recordLiveStream($argv[2], $argv[3], $duration);
            break;

        case 'hls':
            if ($argc < 4) {
                throw new InvalidArgumentException("需要输入视频和输出目录");
            }

            $processor = new StreamingProcessor();
            $processor->createHLSStream($argv[2], $argv[3]);
            break;

        case 'dash':
            if ($argc < 4) {
                throw new InvalidArgumentException("需要输入视频和输出目录");
            }

            $processor = new StreamingProcessor();
            $processor->createDASHStream($argv[2], $argv[3]);
            break;

        case 'webcam':
            if ($argc < 4) {
                throw new InvalidArgumentException("需要设备路径和RTMP URL");
            }

            $processor = new StreamingProcessor();
            $processor->webcamStreaming($argv[2], $argv[3], [
                'resolution' => '1280x720',
                'fps' => 30,
                'video_bitrate' => 2000,
                'audio_bitrate' => 128
            ]);
            break;

        default:
            throw new InvalidArgumentException("未知命令: {$command}");
    }
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}
