<?php

declare(strict_types=1);

namespace LiveStream\Application\Services;

use Closure;
use LiveStream\Application\DTOs\RecordingRequest;
use LiveStream\Application\DTOs\RecordingResponse;
use LiveStream\Domain\Entities\Recording;
use LiveStream\Domain\ValueObjects\RecordingId;
use LiveStream\Domain\ValueObjects\StreamUrl;
use LiveStream\Domain\Repositories\RecordingRepositoryInterface;
use LiveStream\Domain\Repositories\PlatformRepositoryInterface;
use LiveStream\Domain\Factories\RecorderFactoryInterface;
use LiveStream\Shared\Exceptions\ApplicationException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * 录制应用服务
 * 
 * 协调录制业务流程，不包含业务逻辑
 */
final class RecordingService
{
    public function __construct(
        private readonly RecordingRepositoryInterface $recordingRepository,
        private readonly PlatformRepositoryInterface $platformRepository,
        private readonly RecorderFactoryInterface $recorderFactory,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * 启动录制
     *
     * @param RecordingRequest $request
     * @param Closure|null $progressCallback
     * @return RecordingResponse
     * @throws RecordingServiceException
     */
    public function startRecording(
        RecordingRequest $request,
        ?Closure $progressCallback = null
    ): RecordingResponse {
        try {
            // 1. 验证请求
            $this->validateRequest($request);

            // 2. 创建录制实体
            $recording = $this->createRecording($request);

            // 3. 获取平台信息
            $platform = $this->getPlatform($recording->getUrl());

            // 4. 启动录制
            $recording->start();

            // 5. 创建录制器并启动
            $recorder = $this->recorderFactory->create($platform, $recording);
            $handle = $recorder->start($recording, $progressCallback);

            // 6. 保存录制记录
            $this->recordingRepository->save($recording);

            // 7. 记录日志
            $this->logger->info('Recording started', [
                'recording_id' => $recording->getId()->getValue(),
                'url' => $recording->getUrl()->getValue(),
                'platform' => $platform->getName(),
            ]);

            // 8. 返回成功响应
            return RecordingResponse::success(
                $recording->getId(),
                $handle,
                'Recording started successfully'
            );

        } catch (Throwable $e) {
            $recordingId = $recording->getId() ?? RecordingId::generate();
            
            // 记录失败日志
            $this->logger->error('Failed to start recording', [
                'recording_id' => $recordingId->getValue(),
                'url' => $request->url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 如果录制实体已创建，标记为失败
            if (isset($recording)) {
                $recording->markAsFailed($e->getMessage());
                $this->recordingRepository->save($recording);
            }

            return RecordingResponse::failure(
                $recordingId,
                $e->getMessage(),
                ['error_type' => get_class($e)]
            );
        }
    }

    /**
     * 停止录制
     *
     * @param RecordingId $recordingId
     * @return bool
     * @throws RecordingServiceException
     */
    public function stopRecording(RecordingId $recordingId): bool
    {
        try {
            $recording = $this->recordingRepository->findById($recordingId);
            
            if ($recording === null) {
                throw new RecordingServiceException("Recording not found: {$recordingId->getValue()}");
            }

            if (!$recording->getStatus()->canStop()) {
                throw new RecordingServiceException(
                    "Cannot stop recording in status: {$recording->getStatus()->value}"
                );
            }

            // 停止录制（这里需要实现录制器的停止逻辑）
            // TODO: 实现录制器管理和停止逻辑

            $recording->complete($recording->getRecordedDuration());
            $this->recordingRepository->save($recording);

            $this->logger->info('Recording stopped', [
                'recording_id' => $recordingId->getValue(),
            ]);

            return true;

        } catch (Throwable $e) {
            $this->logger->error('Failed to stop recording', [
                'recording_id' => $recordingId->getValue(),
                'error' => $e->getMessage(),
            ]);

            throw new RecordingServiceException(
                "Failed to stop recording: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 获取录制状态
     *
     * @param RecordingId $recordingId
     * @return array|null
     */
    public function getRecordingStatus(RecordingId $recordingId): ?array
    {
        $recording = $this->recordingRepository->findById($recordingId);
        
        if ($recording === null) {
            return null;
        }

        return $recording->getStatistics();
    }

    /**
     * 获取所有录制记录
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function listRecordings(int $limit = 20, int $offset = 0): array
    {
        $recordings = $this->recordingRepository->findAll($limit, $offset);
        
        return array_map(
            fn(Recording $recording) => $recording->getStatistics(),
            $recordings
        );
    }

    /**
     * 验证录制请求
     *
     * @param RecordingRequest $request
     * @throws RecordingServiceException
     */
    private function validateRequest(RecordingRequest $request): void
    {
        $url = StreamUrl::fromString($request->url);
        
        if (!$this->platformRepository->supports($url)) {
            throw new RecordingServiceException("Unsupported platform URL: {$request->url}");
        }

        // 验证输出路径
        $outputDir = dirname($request->outputPath);
        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
            throw new RecordingServiceException("Cannot create output directory: {$outputDir}");
        }

        if (!is_writable($outputDir)) {
            throw new RecordingServiceException("Output directory is not writable: {$outputDir}");
        }
    }

    /**
     * 创建录制实体
     *
     * @param RecordingRequest $request
     * @return Recording
     */
    private function createRecording(RecordingRequest $request): Recording
    {
        return Recording::create(
            id: RecordingId::generate(),
            url: StreamUrl::fromString($request->url),
            outputPath: $request->outputPath,
            quality: $request->quality,
            options: $request->toArray()
        );
    }

    /**
     * 获取平台信息
     *
     * @param StreamUrl $url
     * @return \LiveStream\Domain\Entities\Platform
     * @throws RecordingServiceException
     */
    private function getPlatform(StreamUrl $url): \LiveStream\Domain\Entities\Platform
    {
        $platform = $this->platformRepository->findByUrl($url);
        
        if ($platform === null) {
            throw new RecordingServiceException("No platform found for URL: {$url->getValue()}");
        }

        return $platform;
    }
}

/**
 * 录制服务异常
 */
final class RecordingServiceException extends ApplicationException
{
    public function getErrorCode(): string
    {
        return 'RECORDING_SERVICE_ERROR';
    }
}