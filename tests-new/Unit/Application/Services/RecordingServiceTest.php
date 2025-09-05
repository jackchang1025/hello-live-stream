<?php

declare(strict_types=1);

use LiveStream\Application\Services\RecordingService;
use LiveStream\Application\DTOs\RecordingRequest;
use LiveStream\Domain\ValueObjects\RecordingId;
use LiveStream\Domain\ValueObjects\StreamUrl;
use LiveStream\Domain\Repositories\RecordingRepositoryInterface;
use LiveStream\Domain\Repositories\PlatformRepositoryInterface;
use LiveStream\Domain\Factories\RecorderFactoryInterface;
use LiveStream\Domain\Entities\Platform;
use Psr\Log\LoggerInterface;
use Mockery;

describe('RecordingService', function () {
    
    beforeEach(function () {
        $this->recordingRepository = Mockery::mock(RecordingRepositoryInterface::class);
        $this->platformRepository = Mockery::mock(PlatformRepositoryInterface::class);
        $this->recorderFactory = Mockery::mock(RecorderFactoryInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        
        $this->service = new RecordingService(
            $this->recordingRepository,
            $this->platformRepository,
            $this->recorderFactory,
            $this->logger
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    it('can start recording successfully', function () {
        $request = createTestRecordingRequest();
        
        // Mock platform repository
        $platform = Mockery::mock(Platform::class);
        $platform->shouldReceive('getName')->andReturn('douyin');
        
        $this->platformRepository
            ->shouldReceive('supports')
            ->with(Mockery::type(StreamUrl::class))
            ->andReturn(true);
            
        $this->platformRepository
            ->shouldReceive('findByUrl')
            ->with(Mockery::type(StreamUrl::class))
            ->andReturn($platform);
        
        // Mock recorder factory
        $recorder = Mockery::mock(\LiveStream\Domain\Factories\RecorderInterface::class);
        $handle = Mockery::mock(\LiveStream\Domain\Factories\RecordHandle::class);
        $handle->shouldReceive('getId')->andReturn('handle_123');
        
        $recorder->shouldReceive('start')->andReturn($handle);
        
        $this->recorderFactory
            ->shouldReceive('create')
            ->andReturn($recorder);
        
        // Mock repository save
        $this->recordingRepository
            ->shouldReceive('save')
            ->once();
        
        // Mock logger
        $this->logger
            ->shouldReceive('info')
            ->once();
        
        // Execute
        $response = $this->service->startRecording($request);
        
        // Assert
        expect($response->isSuccessful())->toBeTrue();
        expect($response->getHandleId())->toBe('handle_123');
    });

    it('handles unsupported platform gracefully', function () {
        $request = createTestRecordingRequest(['url' => 'https://unsupported.com/123']);
        
        $this->platformRepository
            ->shouldReceive('supports')
            ->andReturn(false);
        
        $this->logger
            ->shouldReceive('error')
            ->once();
        
        $response = $this->service->startRecording($request);
        
        expect($response->isSuccessful())->toBeFalse();
        expect($response->message)->toContain('Unsupported platform URL');
    });

    it('validates output directory', function () {
        $request = createTestRecordingRequest(['outputPath' => '/invalid/path/output.mp4']);
        
        $this->platformRepository
            ->shouldReceive('supports')
            ->andReturn(true);
        
        $this->logger
            ->shouldReceive('error')
            ->once();
        
        $response = $this->service->startRecording($request);
        
        expect($response->isSuccessful())->toBeFalse();
        expect($response->message)->toContain('Cannot create output directory');
    });

    it('can stop recording', function () {
        $recordingId = RecordingId::generate();
        $recording = createTestRecording();
        $recording->start(); // 确保可以停止
        
        $this->recordingRepository
            ->shouldReceive('findById')
            ->with($recordingId)
            ->andReturn($recording);
        
        $this->recordingRepository
            ->shouldReceive('save')
            ->once();
        
        $this->logger
            ->shouldReceive('info')
            ->once();
        
        $result = $this->service->stopRecording($recordingId);
        
        expect($result)->toBeTrue();
    });

    it('handles stop recording for non-existent recording', function () {
        $recordingId = RecordingId::generate();
        
        $this->recordingRepository
            ->shouldReceive('findById')
            ->andReturn(null);
        
        $this->logger
            ->shouldReceive('error')
            ->once();
        
        expect(fn() => $this->service->stopRecording($recordingId))
            ->toThrow(\LiveStream\Application\Services\RecordingServiceException::class, 'Recording not found');
    });

    it('can get recording status', function () {
        $recordingId = RecordingId::generate();
        $recording = createTestRecording();
        
        $this->recordingRepository
            ->shouldReceive('findById')
            ->with($recordingId)
            ->andReturn($recording);
        
        $status = $this->service->getRecordingStatus($recordingId);
        
        expect($status)->not->toBeNull();
        expect($status)->toHaveKey('id');
        expect($status)->toHaveKey('status');
    });

    it('returns null for non-existent recording status', function () {
        $recordingId = RecordingId::generate();
        
        $this->recordingRepository
            ->shouldReceive('findById')
            ->andReturn(null);
        
        $status = $this->service->getRecordingStatus($recordingId);
        
        expect($status)->toBeNull();
    });

    it('can list recordings', function () {
        $recordings = [createTestRecording(), createTestRecording()];
        
        $this->recordingRepository
            ->shouldReceive('findAll')
            ->with(20, 0)
            ->andReturn($recordings);
        
        $result = $this->service->listRecordings();
        
        expect($result)->toHaveCount(2);
        expect($result[0])->toHaveKey('id');
        expect($result[0])->toHaveKey('status');
    });

});

describe('RecordingService Error Handling', function () {
    
    beforeEach(function () {
        $this->recordingRepository = Mockery::mock(RecordingRepositoryInterface::class);
        $this->platformRepository = Mockery::mock(PlatformRepositoryInterface::class);
        $this->recorderFactory = Mockery::mock(RecorderFactoryInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        
        $this->service = new RecordingService(
            $this->recordingRepository,
            $this->platformRepository,
            $this->recorderFactory,
            $this->logger
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    it('handles recorder creation failure', function () {
        $request = createTestRecordingRequest();
        
        $platform = Mockery::mock(Platform::class);
        $platform->shouldReceive('getName')->andReturn('douyin');
        
        $this->platformRepository
            ->shouldReceive('supports')
            ->andReturn(true);
            
        $this->platformRepository
            ->shouldReceive('findByUrl')
            ->andReturn($platform);
        
        $this->recorderFactory
            ->shouldReceive('create')
            ->andThrow(new Exception('Recorder creation failed'));
        
        $this->recordingRepository
            ->shouldReceive('save')
            ->once(); // 保存失败的录制记录
        
        $this->logger
            ->shouldReceive('error')
            ->once();
        
        $response = $this->service->startRecording($request);
        
        expect($response->isSuccessful())->toBeFalse();
        expect($response->message)->toBe('Recorder creation failed');
    });

    it('handles platform not found gracefully', function () {
        $request = createTestRecordingRequest();
        
        $this->platformRepository
            ->shouldReceive('supports')
            ->andReturn(true);
            
        $this->platformRepository
            ->shouldReceive('findByUrl')
            ->andReturn(null);
        
        $this->logger
            ->shouldReceive('error')
            ->once();
        
        $response = $this->service->startRecording($request);
        
        expect($response->isSuccessful())->toBeFalse();
        expect($response->message)->toContain('No platform found for URL');
    });

});