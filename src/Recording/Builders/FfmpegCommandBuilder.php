<?php

declare(strict_types=1);

namespace LiveStream\Recording\Builders;

use LiveStream\Exceptions\RecordingException;
use LiveStream\Recording\Contracts\CommandBuilderInterface;

/**
 * FFmpeg 命令建造者（链式）
 */
final class FfmpegCommandBuilder implements CommandBuilderInterface
{
    private string $binary = 'ffmpeg';
    private array $globalOptions = ['-y'];
    private ?string $input = null;
    private bool $copyCodec = true;
    private ?string $format = 'mp4';
    private bool $overseasOptimization = false;
    private ?string $output = null;
    /** @var array<string,string> */
    private array $headers = [];
    private ?string $userAgent = "Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36";
    private ?string $referer = null;
    /** @var string[] */
    private array $audioBsfs = [];
    /** @var string[] */
    private array $movflags = [];
    /** @var string[] */
    private array $rawOptions = [];

    public function setBinary(string $binary): self
    {
        $this->binary = $binary;
        return $this;
    }

    public function addGlobalOptions(array $options): self
    {
        $this->globalOptions = array_values(array_unique(array_merge($this->globalOptions, $options)));
        return $this;
    }

    public function setInput(string $inputUrl): self
    {
        $this->input = $inputUrl;
        return $this;
    }

    public function useCopyCodec(): self
    {
        $this->copyCodec = true;
        return $this;
    }

    public function setFormat(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function applyOverseasOptimization(bool $enabled = true): self
    {
        $this->overseasOptimization = $enabled;
        return $this;
    }

    public function setOutput(string $outputPath): self
    {
        $this->output = $outputPath;
        return $this;
    }

    public function addHeaders(array $headers): self
    {
        foreach ($headers as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $this->headers[$k] = $v;
            }
        }
        return $this;
    }

    public function setUserAgent(?string $ua): self
    {
        $this->userAgent = $ua ? trim($ua) : null;
        return $this;
    }

    public function setReferer(?string $referer): self
    {
        $this->referer = $referer ? trim($referer) : null;
        return $this;
    }

    public function addAudioBitstreamFilter(string $filter): self
    {
        $filter = trim($filter);
        if ($filter !== '') {
            $this->audioBsfs[] = $filter;
        }
        return $this;
    }

    public function addMovflags(string|array $flags): self
    {
        $flags = is_array($flags) ? $flags : [$flags];
        foreach ($flags as $flag) {
            $flag = trim($flag);
            if ($flag !== '') {
                $this->movflags[] = $flag;
            }
        }
        return $this;
    }

    public function addRawOptions(array $options): self
    {
        $this->rawOptions = array_values(array_filter(array_map('strval', $options), static fn($v) => $v !== ''));
        return $this;
    }

    public function build(): array
    {
        $this->assertValid();

        $command = [$this->binary];

        if (!empty($this->globalOptions)) {
            $command = array_merge($command, $this->globalOptions);
        }

        // 添加网络和连接参数（参考 main.py 的成功配置）
        $command = array_merge($command, [
            '-rw_timeout',
            '15000000',
            '-analyzeduration',
            '20000000',
            '-probesize',
            '10000000',
            '-protocol_whitelist',
            'rtmp,crypto,file,http,https,tcp,tls,udp,rtp,httpproxy',
            '-thread_queue_size',
            '1024',
            '-fflags',
            '+discardcorrupt',
        ]);

        // Headers & UA & Referer（必须在 -i 之前，对 HLS/CDN 鉴权很关键）
        if (!empty($this->headers)) {
            $headerLines = [];
            foreach ($this->headers as $k => $v) {
                $headerLines[] = sprintf('%s: %s', $k, $v);
            }
            $command[] = '-headers';
            $command[] = implode('\r\n', $headerLines);
        }
        if ($this->userAgent) {
            $command[] = '-user_agent';
            $command[] = $this->userAgent;
        }
        if ($this->referer) {
            $command[] = '-referer';
            $command[] = $this->referer;
        }

        if ($this->input !== null) {
            $command[] = '-i';
            $command[] = $this->input;
        }

        // 添加输入后的网络参数（参考 main.py 的成功配置）
        $command = array_merge($command, [
            '-bufsize',
            '8000k',
            '-sn',
            '-dn',  // 禁用字幕和数据流
            '-reconnect_delay_max',
            '60',
            '-reconnect_streamed',
            '-reconnect_at_eof',
            '-max_muxing_queue_size',
            '1024',
            '-correct_ts_overflow',
            '1',
            '-avoid_negative_ts',
            '1',
        ]);

        if ($this->copyCodec) {
            $command[] = '-c';
            $command[] = 'copy';
        }

        if ($this->format !== null) {
            [$ffFormat, $audioOnly] = $this->normalizeFormat($this->format);
            $command[] = '-f';
            $command[] = $ffFormat;
            if ($audioOnly) {
                $command[] = '-vn';
            }
        }

        // 音频比特流滤镜（mp4常见：aac_adtstoasc）
        foreach ($this->audioBsfs as $bsf) {
            $command[] = '-bsf:a';
            $command[] = $bsf;
        }

        // movflags（faststart/fragmented mp4）
        if (!empty($this->movflags)) {
            $command[] = '-movflags';
            // 过滤掉空字符串和重复的 + 号
            $flags = array_filter($this->movflags, fn($flag) => !empty(trim($flag, '+')));
            $command[] = implode('+', $flags);
        }

        if ($this->overseasOptimization) {
            // 海外优化：增加超时时间和缓冲区大小
            $command = array_merge($command, [
                '-timeout',
                '50000000',
                '-reconnect',
                '1',
            ]);
            // 将 bufsize 从 8000k 增加到 15000k
            foreach ($command as $i => $value) {
                if ($value === '8000k' && $i > 0 && $command[$i - 1] === '-bufsize') {
                    $command[$i] = '15000k';
                    break;
                }
            }
        }

        if ($this->output !== null) {
            if (!empty($this->rawOptions)) {
                $command = array_merge($command, $this->rawOptions);
            }
            $command[] = $this->output;
        }

        return $command;
    }

    /**
     * 校验必要参数和取值合法性
     */
    private function assertValid(): void
    {
        if (trim($this->binary) === '') {
            throw new \InvalidArgumentException('ffmpeg binary cannot be empty');
        }

        if ($this->input === null || trim($this->input) === '') {
            throw new \InvalidArgumentException('input URL is required');
        }

        if ($this->output === null || trim($this->output) === '') {
            throw new \InvalidArgumentException('output path is required');
        }

        if ($this->format !== null) {
            try {
                $this->normalizeFormat($this->format);
            } catch (RecordingException $e) {
                throw new \InvalidArgumentException($e->getMessage());
            }
        }

    }

    /**
     * 规范化格式并返回 (ffmpeg内置格式名, 是否仅音频)
     */
    private function normalizeFormat(string $format): array
    {
        $format = strtolower($format);

        return match ($format) {
            'mp4' => ['mp4', false],
            'webm' => ['webm', false],
            'mkv', 'matroska' => ['matroska', false],
            'flv' => ['flv', false],
            'mp3' => ['mp3', true],
            'aac' => ['aac', true],
            default => throw RecordingException::unsupportedFormat($format, ['mp4', 'webm', 'mkv', 'flv', 'mp3', 'aac']),
        };
    }
}
