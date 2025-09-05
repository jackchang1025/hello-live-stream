<?php

declare(strict_types=1);

use LiveStream\Domain\Entities\Recording;
use LiveStream\Domain\ValueObjects\RecordingId;
use LiveStream\Domain\ValueObjects\StreamUrl;
use LiveStream\Domain\ValueObjects\Duration;
use LiveStream\Domain\ValueObjects\RecordingStatus;
use LiveStream\Domain\Entities\InvalidRecordingStateException;

describe('Recording Entity', function () {
    
    it('can be created with required parameters', function () {
        $recording = Recording::create(
            id: RecordingId::generate(),
            url: StreamUrl::fromString('https://live.douyin.com/123456'),
            outputPath: './test-output.mp4',
            quality: 'origin'
        );
        
        expect($recording)->toBeRecording();
        expect($recording->getStatus())->toBe(RecordingStatus::PENDING);
        expect($recording->getQuality())->toBe('origin');
        expect($recording->getRecordedDuration()->isZero())->toBeTrue();
    });

    it('starts with pending status', function () {
        $recording = createTestRecording();
        
        expect($recording->getStatus())->toBe(RecordingStatus::PENDING);
        expect($recording->getStartedAt())->toBeNull();
        expect($recording->getCompletedAt())->toBeNull();
    });

    it('can start recording', function () {
        $recording = createTestRecording();
        
        $recording->start();
        
        expect($recording->getStatus())->toBe(RecordingStatus::RECORDING);
        expect($recording->getStartedAt())->not->toBeNull();
        expect($recording->getStartedAt())->toBeInstanceOf(DateTime::class);
    });

    it('cannot start recording from non-pending status', function () {
        $recording = createTestRecording();
        $recording->start();
        
        expect(fn() => $recording->start())
            ->toThrow(InvalidRecordingStateException::class, 'Cannot start recording in status: recording');
    });

    it('can pause recording', function () {
        $recording = createTestRecording();
        $recording->start();
        
        $recording->pause();
        
        expect($recording->getStatus())->toBe(RecordingStatus::PAUSED);
        expect($recording->getPausedAt())->not->toBeNull();
    });

    it('cannot pause recording from non-recording status', function () {
        $recording = createTestRecording();
        
        expect(fn() => $recording->pause())
            ->toThrow(InvalidRecordingStateException::class, 'Cannot pause recording in status: pending');
    });

    it('can resume recording', function () {
        $recording = createTestRecording();
        $recording->start();
        $recording->pause();
        
        $recording->resume();
        
        expect($recording->getStatus())->toBe(RecordingStatus::RECORDING);
        expect($recording->getPausedAt())->toBeNull();
    });

    it('cannot resume recording from non-paused status', function () {
        $recording = createTestRecording();
        
        expect(fn() => $recording->resume())
            ->toThrow(InvalidRecordingStateException::class, 'Cannot resume recording in status: pending');
    });

    it('can complete recording', function () {
        $recording = createTestRecording();
        $recording->start();
        
        $duration = Duration::fromMinutes(30);
        $recording->complete($duration);
        
        expect($recording->getStatus())->toBe(RecordingStatus::COMPLETED);
        expect($recording->getCompletedAt())->not->toBeNull();
        expect($recording->getRecordedDuration())->toBe($duration);
    });

    it('cannot complete recording from invalid status', function () {
        $recording = createTestRecording();
        
        expect(fn() => $recording->complete(Duration::fromMinutes(30)))
            ->toThrow(InvalidRecordingStateException::class, 'Cannot complete recording in status: pending');
    });

    it('can mark recording as failed', function () {
        $recording = createTestRecording();
        $recording->start();
        
        $recording->markAsFailed('Connection lost');
        
        expect($recording->getStatus())->toBe(RecordingStatus::FAILED);
        expect($recording->getCompletedAt())->not->toBeNull();
        expect($recording->getErrors())->toHaveCount(1);
        expect($recording->getErrors()[0]['message'])->toBe('Connection lost');
    });

    it('can cancel recording', function () {
        $recording = createTestRecording();
        $recording->start();
        
        $recording->cancel();
        
        expect($recording->getStatus())->toBe(RecordingStatus::CANCELLED);
        expect($recording->getCompletedAt())->not->toBeNull();
    });

    it('cannot cancel already finished recording', function () {
        $recording = createTestRecording();
        $recording->start();
        $recording->complete(Duration::fromMinutes(30));
        
        // 取消已完成的录制应该无效果
        $recording->cancel();
        expect($recording->getStatus())->toBe(RecordingStatus::COMPLETED);
    });

    it('can update duration during recording', function () {
        $recording = createTestRecording();
        $recording->start();
        
        $newDuration = Duration::fromMinutes(15);
        $recording->updateDuration($newDuration);
        
        expect($recording->getRecordedDuration())->toBe($newDuration);
    });

    it('cannot update duration when not recording', function () {
        $recording = createTestRecording();
        
        $recording->updateDuration(Duration::fromMinutes(15));
        
        // 非录制状态下更新时长应该无效果
        expect($recording->getRecordedDuration()->isZero())->toBeTrue();
    });

    it('can add errors', function () {
        $recording = createTestRecording();
        
        $recording->addError('First error');
        $recording->addError('Second error');
        
        $errors = $recording->getErrors();
        expect($errors)->toHaveCount(2);
        expect($errors[0]['message'])->toBe('First error');
        expect($errors[1]['message'])->toBe('Second error');
    });

    it('provides comprehensive statistics', function () {
        $recording = createTestRecording();
        $recording->start();
        $recording->updateDuration(Duration::fromMinutes(30));
        $recording->addError('Test error');
        
        $stats = $recording->getStatistics();
        
        expect($stats)->toHaveKeys([
            'id', 'status', 'status_display', 'url', 'output_path',
            'quality', 'started_at', 'completed_at', 'recorded_duration',
            'recorded_duration_human', 'actual_duration_seconds',
            'total_segments', 'total_errors', 'is_active', 'is_finished', 'is_successful'
        ]);
        
        expect($stats['status'])->toBe('recording');
        expect($stats['status_display'])->toBe('录制中');
        expect($stats['total_errors'])->toBe(1);
        expect($stats['is_active'])->toBeTrue();
        expect($stats['is_finished'])->toBeFalse();
    });

});

describe('Recording Entity State Transitions', function () {
    
    it('follows valid state transition flow', function () {
        $recording = createTestRecording();
        
        // PENDING -> RECORDING
        expect($recording->getStatus()->canStart())->toBeTrue();
        $recording->start();
        
        // RECORDING -> PAUSED
        expect($recording->getStatus()->canPause())->toBeTrue();
        $recording->pause();
        
        // PAUSED -> RECORDING
        expect($recording->getStatus()->canResume())->toBeTrue();
        $recording->resume();
        
        // RECORDING -> COMPLETED
        expect($recording->getStatus()->canStop())->toBeTrue();
        $recording->complete(Duration::fromMinutes(30));
        
        expect($recording->getStatus()->isFinished())->toBeTrue();
        expect($recording->getStatus()->isSuccessful())->toBeTrue();
    });

    it('handles failure state correctly', function () {
        $recording = createTestRecording();
        $recording->start();
        
        $recording->markAsFailed('Network error');
        
        expect($recording->getStatus())->toBe(RecordingStatus::FAILED);
        expect($recording->getStatus()->isFinished())->toBeTrue();
        expect($recording->getStatus()->isSuccessful())->toBeFalse();
    });

});