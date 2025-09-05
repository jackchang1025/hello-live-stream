<?php

declare(strict_types=1);

namespace LiveStream\Recording\Contracts;

/**
 * 命令建造者契约
 */
interface CommandBuilderInterface
{
    public function setBinary(string $binary): self;

    public function addGlobalOptions(array $options): self;

    public function setInput(string $inputUrl): self;

    public function useCopyCodec(): self;

    public function setFormat(string $format): self;

    public function applyOverseasOptimization(bool $enabled = true): self;

    public function addHeaders(array $headers): self;

    public function setUserAgent(?string $ua): self;

    public function setReferer(?string $referer): self;

    /** 添加音频比特流滤镜，例如 aac_adtstoasc */
    public function addAudioBitstreamFilter(string $filter): self;

    /** 添加 movflags 选项（mp4 片段化、faststart 等） */
    public function addMovflags(string|array $flags): self;

    /** 透传原始 ffmpeg 选项 */
    public function addRawOptions(array $options): self;

    public function setOutput(string $outputPath): self;

    /**
     * 返回最终命令数组
     */
    public function build(): array;
}
