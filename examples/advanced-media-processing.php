<?php

declare(strict_types=1);

/**
 * PHP-FFMpeg 高级媒体处理示例
 * 
 * 本示例展示了如何使用AdvancedMedia处理多输入输出的复杂场景
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use FFMpeg\Format\Audio\Mp3;
use FFMpeg\Format\Audio\Flac;
use FFMpeg\Filters\AdvancedMedia\XStackFilter;

class AdvancedMediaProcessor
{
    private FFMpeg $ffmpeg;

    public function __construct()
    {
        $this->ffmpeg = FFMpeg::create([
            'timeout' => 7200,  // 2小时超时
            'ffmpeg.threads' => 8,
            'temporary_directory' => '/tmp/ffmpeg-advanced'
        ]);
    }

    /**
     * 创建视频拼贴（多个视频合并为一个画面）
     */
    public function createVideoCollage(array $inputVideos, string $outputPath, string $layout = '2x2'): void
    {
        if (count($inputVideos) !== 4 && $layout === '2x2') {
            throw new InvalidArgumentException("2x2布局需要4个输入视频");
        }

        try {
            $advancedMedia = $this->ffmpeg->openAdvanced($inputVideos);

            // 为每个视频应用不同的滤镜效果
            $advancedMedia->filters()
                // 第一个视频：正常
                ->custom('[0:v]', 'scale=640:480', '[v0]')

                // 第二个视频：黑白效果
                ->custom('[1:v]', 'scale=640:480,hue=s=0', '[v1]')

                // 第三个视频：边缘检测
                ->custom('[2:v]', 'scale=640:480,edgedetect', '[v2]')

                // 第四个视频：模糊效果
                ->custom('[3:v]', 'scale=640:480,boxblur=2:1', '[v3]')

                // 使用xstack滤镜创建2x2布局
                ->xStack('[v0][v1][v2][v3]', XStackFilter::LAYOUT_2X2, 4, '[collage]');

            // 使用第一个视频的音频
            $advancedMedia->map(['0:a', '[collage]'], new X264(), $outputPath);

            $advancedMedia->save();

            echo "视频拼贴创建完成: {$outputPath}\n";
        } catch (Exception $e) {
            throw new RuntimeException("创建视频拼贴失败: " . $e->getMessage());
        }
    }

    /**
     * 多轨道音频混合
     */
    public function mixAudioTracks(array $audioFiles, string $outputPath, array $volumes = []): void
    {
        try {
            $advancedMedia = $this->ffmpeg->openAdvanced($audioFiles);

            $filterChain = '';
            $inputs = '';

            // 构建音频混合滤镜链
            for ($i = 0; $i < count($audioFiles); $i++) {
                $volume = $volumes[$i] ?? 1.0;
                $advancedMedia->filters()
                    ->custom("[{$i}:a]", "volume={$volume}", "[a{$i}]");
                $inputs .= "[a{$i}]";
            }

            // 混合所有音频轨道
            $advancedMedia->filters()
                ->custom($inputs, 'amix=inputs=' . count($audioFiles) . ':duration=longest', '[mixed]');

            $advancedMedia->map(['[mixed]'], new Mp3(), $outputPath);
            $advancedMedia->save();

            echo "音频混合完成: {$outputPath}\n";
        } catch (Exception $e) {
            throw new RuntimeException("音频混合失败: " . $e->getMessage());
        }
    }

    /**
     * 视频画中画效果
     */
    public function createPictureInPicture(
        string $mainVideo,
        string $overlayVideo,
        string $outputPath,
        int $overlayX = 10,
        int $overlayY = 10,
        float $overlayScale = 0.3
    ): void {
        try {
            $advancedMedia = $this->ffmpeg->openAdvanced([$mainVideo, $overlayVideo]);

            // 缩放叠加视频
            $overlayWidth = (int)(1920 * $overlayScale);
            $overlayHeight = (int)(1080 * $overlayScale);

            $advancedMedia->filters()
                // 主视频保持原尺寸
                ->custom('[0:v]', 'scale=1920:1080', '[main]')

                // 叠加视频缩放
                ->custom('[1:v]', "scale={$overlayWidth}:{$overlayHeight}", '[overlay]')

                // 叠加两个视频
                ->custom('[main][overlay]', "overlay={$overlayX}:{$overlayY}", '[output]');

            // 使用主视频的音频
            $advancedMedia->map(['0:a', '[output]'], new X264(), $outputPath);
            $advancedMedia->save();

            echo "画中画效果创建完成: {$outputPath}\n";
        } catch (Exception $e) {
            throw new RuntimeException("创建画中画效果失败: " . $e->getMessage());
        }
    }

    /**
     * 创建分屏对比视频
     */
    public function createSplitScreenComparison(
        string $leftVideo,
        string $rightVideo,
        string $outputPath,
        string $orientation = 'horizontal'
    ): void {
        try {
            $advancedMedia = $this->ffmpeg->openAdvanced([$leftVideo, $rightVideo]);

            if ($orientation === 'horizontal') {
                // 水平分屏
                $advancedMedia->filters()
                    ->custom('[0:v]', 'scale=960:1080,crop=960:1080:0:0', '[left]')
                    ->custom('[1:v]', 'scale=960:1080,crop=960:1080:0:0', '[right]')
                    ->custom('[left][right]', 'hstack=inputs=2', '[output]');
            } else {
                // 垂直分屏
                $advancedMedia->filters()
                    ->custom('[0:v]', 'scale=1920:540,crop=1920:540:0:0', '[top]')
                    ->custom('[1:v]', 'scale=1920:540,crop=1920:540:0:0', '[bottom]')
                    ->custom('[top][bottom]', 'vstack=inputs=2', '[output]');
            }

            // 混合两个视频的音频
            $advancedMedia->filters()
                ->custom('[0:a][1:a]', 'amix=inputs=2:duration=shortest', '[audio]');

            $advancedMedia->map(['[audio]', '[output]'], new X264(), $outputPath);
            $advancedMedia->save();

            echo "分屏对比视频创建完成: {$outputPath}\n";
        } catch (Exception $e) {
            throw new RuntimeException("创建分屏对比视频失败: " . $e->getMessage());
        }
    }

    /**
     * 多格式同时输出
     */
    public function multiFormatOutput(string $inputVideo, string $outputPrefix): array
    {
        try {
            $advancedMedia = $this->ffmpeg->openAdvanced([$inputVideo]);

            // 定义不同的输出格式和分辨率
            $outputs = [
                // 4K H.264
                [
                    'suffix' => '_4k.mp4',
                    'format' => new X264(),
                    'filter' => 'scale=3840:2160',
                    'bitrate' => 8000
                ],
                // 1080p H.264
                [
                    'suffix' => '_1080p.mp4',
                    'format' => new X264(),
                    'filter' => 'scale=1920:1080',
                    'bitrate' => 4000
                ],
                // 720p H.264
                [
                    'suffix' => '_720p.mp4',
                    'format' => new X264(),
                    'filter' => 'scale=1280:720',
                    'bitrate' => 2000
                ],
                // 480p H.264 (移动设备)
                [
                    'suffix' => '_480p.mp4',
                    'format' => new X264(),
                    'filter' => 'scale=854:480',
                    'bitrate' => 1000
                ]
            ];

            $outputFiles = [];

            foreach ($outputs as $i => $output) {
                $outputPath = $outputPrefix . $output['suffix'];
                $outputFiles[] = $outputPath;

                // 为每个输出创建缩放滤镜
                $advancedMedia->filters()
                    ->custom('[0:v]', $output['filter'], "[v{$i}]");

                // 配置格式
                $output['format']->setKiloBitrate($output['bitrate'])
                    ->setAudioKiloBitrate(128);

                // 添加输出映射
                $advancedMedia->map(["0:a", "[v{$i}]"], $output['format'], $outputPath);
            }

            $advancedMedia->save();

            echo "多格式输出完成:\n";
            foreach ($outputFiles as $file) {
                echo "  - {$file}\n";
            }

            return $outputFiles;
        } catch (Exception $e) {
            throw new RuntimeException("多格式输出失败: " . $e->getMessage());
        }
    }

    /**
     * 实时直播推流处理
     */
    public function processLiveStream(
        string $inputStream,
        array $outputs,
        array $overlayImages = []
    ): void {
        try {
            $advancedMedia = $this->ffmpeg->openAdvanced([$inputStream]);

            // 添加水印和叠加图片
            $videoFilter = '[0:v]';

            foreach ($overlayImages as $i => $overlay) {
                $advancedMedia = $this->ffmpeg->openAdvanced([$inputStream, $overlay['path']]);
                $videoFilter = '[overlayed]';

                $advancedMedia->filters()
                    ->custom(
                        '[0:v][1:v]',
                        "overlay={$overlay['x']}:{$overlay['y']}",
                        '[overlayed]'
                    );
            }

            // 为不同平台创建不同的输出流
            foreach ($outputs as $output) {
                $format = new X264();
                $format->setKiloBitrate($output['bitrate'])
                    ->setAudioKiloBitrate($output['audio_bitrate'])
                    ->setAdditionalParameters([
                        '-f',
                        'flv',
                        '-preset',
                        'ultrafast',
                        '-tune',
                        'zerolatency',
                        '-g',
                        '60',
                        '-keyint_min',
                        '60'
                    ]);

                $advancedMedia->filters()
                    ->custom($videoFilter, $output['video_filter'], "[out_{$output['name']}]");

                $advancedMedia->map(
                    ["0:a", "[out_{$output['name']}]"],
                    $format,
                    $output['rtmp_url']
                );
            }

            echo "开始直播推流处理...\n";
            $advancedMedia->save();
        } catch (Exception $e) {
            throw new RuntimeException("直播推流处理失败: " . $e->getMessage());
        }
    }

    /**
     * 批量音频格式转换
     */
    public function batchAudioConversion(string $inputDir, string $outputDir): void
    {
        $audioFiles = glob($inputDir . '/*.{mp3,wav,flac,aac,m4a}', GLOB_BRACE);

        if (empty($audioFiles)) {
            echo "在 {$inputDir} 中未找到音频文件\n";
            return;
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        try {
            // 批量处理所有音频文件
            $advancedMedia = $this->ffmpeg->openAdvanced($audioFiles);

            $formats = [
                'mp3' => new Mp3(),
                'flac' => new Flac()
            ];

            foreach ($audioFiles as $i => $audioFile) {
                $fileName = pathinfo($audioFile, PATHINFO_FILENAME);

                foreach ($formats as $ext => $format) {
                    $outputPath = $outputDir . '/' . $fileName . '.' . $ext;

                    if ($format instanceof Mp3) {
                        $format->setAudioKiloBitrate(192);
                    }

                    $advancedMedia->map(["{$i}:a"], $format, $outputPath);
                }
            }

            $advancedMedia->save();

            echo "批量音频转换完成\n";
        } catch (Exception $e) {
            throw new RuntimeException("批量音频转换失败: " . $e->getMessage());
        }
    }
}

// 使用示例
if ($argc < 2) {
    echo "使用方法:\n";
    echo "  php advanced-media-processing.php collage <video1> <video2> <video3> <video4> <output> - 创建视频拼贴\n";
    echo "  php advanced-media-processing.php mix-audio <audio1> <audio2> [audio3...] <output> - 混合音频\n";
    echo "  php advanced-media-processing.php pip <main_video> <overlay_video> <output> - 画中画\n";
    echo "  php advanced-media-processing.php split <video1> <video2> <output> [h|v] - 分屏对比\n";
    echo "  php advanced-media-processing.php multi-output <input> <output_prefix> - 多格式输出\n";
    echo "  php advanced-media-processing.php batch-audio <input_dir> <output_dir> - 批量音频转换\n";
    exit(1);
}

$processor = new AdvancedMediaProcessor();
$command = $argv[1];

try {
    switch ($command) {
        case 'collage':
            if ($argc < 7) {
                throw new InvalidArgumentException("需要4个输入视频和1个输出路径");
            }
            $inputs = array_slice($argv, 2, 4);
            $output = $argv[6];
            $processor->createVideoCollage($inputs, $output);
            break;

        case 'mix-audio':
            if ($argc < 4) {
                throw new InvalidArgumentException("至少需要2个音频文件和1个输出路径");
            }
            $inputs = array_slice($argv, 2, -1);
            $output = end($argv);
            $processor->mixAudioTracks($inputs, $output);
            break;

        case 'pip':
            if ($argc < 5) {
                throw new InvalidArgumentException("需要主视频、叠加视频和输出路径");
            }
            $processor->createPictureInPicture($argv[2], $argv[3], $argv[4]);
            break;

        case 'split':
            if ($argc < 5) {
                throw new InvalidArgumentException("需要2个视频文件和输出路径");
            }
            $orientation = $argc > 5 ? ($argv[5] === 'v' ? 'vertical' : 'horizontal') : 'horizontal';
            $processor->createSplitScreenComparison($argv[2], $argv[3], $argv[4], $orientation);
            break;

        case 'multi-output':
            if ($argc < 4) {
                throw new InvalidArgumentException("需要输入视频和输出前缀");
            }
            $processor->multiFormatOutput($argv[2], $argv[3]);
            break;

        case 'batch-audio':
            if ($argc < 4) {
                throw new InvalidArgumentException("需要输入目录和输出目录");
            }
            $processor->batchAudioConversion($argv[2], $argv[3]);
            break;

        default:
            throw new InvalidArgumentException("未知命令: {$command}");
    }
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}
