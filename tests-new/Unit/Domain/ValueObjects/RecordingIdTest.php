<?php

declare(strict_types=1);

use LiveStream\Domain\ValueObjects\RecordingId;
use LiveStream\Domain\ValueObjects\InvalidRecordingIdException;

describe('RecordingId Value Object', function () {
    
    it('can generate new ID', function () {
        $id = RecordingId::generate();
        
        expect($id->getValue())->toBeRecordingId();
        expect($id->isGenerated())->toBeTrue();
    });

    it('generates unique IDs', function () {
        $id1 = RecordingId::generate();
        $id2 = RecordingId::generate();
        
        expect($id1->getValue())->not->toBe($id2->getValue());
        expect($id1->equals($id2))->toBeFalse();
    });

    it('can be created from string', function () {
        $customId = 'custom_recording_123';
        $id = RecordingId::fromString($customId);
        
        expect($id->getValue())->toBe($customId);
        expect($id->isGenerated())->toBeFalse();
    });

    it('validates ID format', function () {
        expect(fn() => RecordingId::fromString(''))
            ->toThrow(InvalidRecordingIdException::class, 'Recording ID cannot be empty');
            
        expect(fn() => RecordingId::fromString('invalid@id#'))
            ->toThrow(InvalidRecordingIdException::class, 'Recording ID contains invalid characters');
            
        expect(fn() => RecordingId::fromString(str_repeat('a', 101)))
            ->toThrow(InvalidRecordingIdException::class, 'Recording ID too long');
    });

    it('accepts valid characters', function () {
        $validIds = [
            'rec_123',
            'recording-456',
            'test_recording_789',
            'ABC123def456',
        ];
        
        foreach ($validIds as $validId) {
            $id = RecordingId::fromString($validId);
            expect($id->getValue())->toBe($validId);
        }
    });

    it('extracts timestamp from generated ID', function () {
        $id = RecordingId::generate();
        $timestamp = $id->getTimestamp();
        
        expect($timestamp)->not->toBeNull();
        expect($timestamp)->toMatch('/^\d{14}$/');
        
        // 验证时间戳是最近的
        $now = date('YmdHis');
        $timeDiff = abs((int)$now - (int)$timestamp);
        expect($timeDiff)->toBeLessThan(10); // 应该在10秒内
    });

    it('extracts random part from generated ID', function () {
        $id = RecordingId::generate();
        $randomPart = $id->getRandomPart();
        
        expect($randomPart)->not->toBeNull();
        expect($randomPart)->toMatch('/^[a-f0-9]{8}$/');
    });

    it('returns null for non-generated ID parts', function () {
        $id = RecordingId::fromString('custom_id');
        
        expect($id->getTimestamp())->toBeNull();
        expect($id->getRandomPart())->toBeNull();
    });

    it('supports equality comparison', function () {
        $id1 = RecordingId::fromString('test_123');
        $id2 = RecordingId::fromString('test_123');
        $id3 = RecordingId::fromString('test_456');
        
        expect($id1->equals($id2))->toBeTrue();
        expect($id1->equals($id3))->toBeFalse();
    });

    it('can be converted to string', function () {
        $originalId = 'test_recording_123';
        $id = RecordingId::fromString($originalId);
        
        expect((string) $id)->toBe($originalId);
        expect($id->__toString())->toBe($originalId);
    });

});

describe('RecordingId Edge Cases', function () {
    
    it('handles minimum length ID', function () {
        $id = RecordingId::fromString('a');
        expect($id->getValue())->toBe('a');
    });

    it('handles maximum length ID', function () {
        $maxId = str_repeat('a', 100);
        $id = RecordingId::fromString($maxId);
        expect($id->getValue())->toBe($maxId);
    });

    it('handles underscores and hyphens', function () {
        $id = RecordingId::fromString('test_recording-123_final');
        expect($id->getValue())->toBe('test_recording-123_final');
    });

});