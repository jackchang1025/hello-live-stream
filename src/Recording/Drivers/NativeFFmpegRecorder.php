<?php

declare(strict_types=1);

namespace LiveStream\Recording\Drivers;

use LiveStream\Exceptions\RecordingException;
use LiveStream\Recording\Contracts\RecorderInterface;
use LiveStream\Recording\Contracts\ProcessRunnerInterface;
use LiveStream\Recording\PendingRecorder;
use LiveStream\Recording\ValueObjects\RecordHandle;

final class NativeFFmpegRecorder implements RecorderInterface
{
    public function __construct(
        private readonly ProcessRunnerInterface $runner,
        private readonly string $ffmpegBinary = 'ffmpeg',
        private readonly array $env = []
    ) {}

    public function start(PendingRecorder $pendingRecorder, ?callable $progress = null): RecordHandle
    {
        $command = $this->buildCommand();

        // 替换可执行文件为配置的二进制（若不同）
        if (!empty($command) && $command[0] !== $this->ffmpegBinary) {
            $command[0] = $this->ffmpegBinary;
        }

        $process = $this->runner->start($command, $progress, $this->env);

        if (!$process->isRunning()) {
            throw RecordingException::fromException(new \RuntimeException('Failed to start ffmpeg process'), $pendingRecorder->getRecordId());
        }

        return new RecordHandle(
            recordId: $pendingRecorder->getRecordId(),
            outputPath: $pendingRecorder->getOutputPath(),
            command: $command,
            process: $process
        );
    }

    public function stop(RecordHandle $handle): void
    {
        $this->runner->stop($handle->process);
    }

        /**
     * 构建 FFmpeg 命令参数
     * 
     * @return array FFmpeg 命令数组
     */
    public function buildCommand(): array
    {

        $format = $this->options->format->value;

        $this->builder()
            ->setBinary('ffmpeg')
            ->addGlobalOptions(['-y'])
            ->setInput($this->streamConfig->getRecordUrl())
            ->useCopyCodec()
            ->setFormat($format)
            ->applyOverseasOptimization($this->enableOverseasOptimization)
            ->setOutput($this->outputPath);

        $headers = $this->options->customHeaders ?? [];
        if (!empty($headers) && is_array($headers)) {
            $this->builder()->addHeaders($headers);
        }

        $this->builder()->setReferer($this->platform->getReferer());

        if (!empty($this->options->ffmpegOptions) && is_array($this->options->ffmpegOptions)) {
            $this->builder()->addRawOptions($this->options->ffmpegOptions);
        }

        // mp4 友好：避免短时录制无文件可见
        if ($format === 'mp4') {
            $this->builder()->addAudioBitstreamFilter('aac_adtstoasc')
                ->addMovflags(['faststart', 'frag_keyframe', 'empty_moov']);
        }

        return $this->builder()->build();
    }
}
