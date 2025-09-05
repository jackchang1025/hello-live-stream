<?php

declare(strict_types=1);

use LiveStream\Domain\ValueObjects\Duration;
use LiveStream\Domain\ValueObjects\InvalidDurationException;

describe('Duration Value Object', function () {
    
    it('can be created from seconds', function () {
        $duration = Duration::fromSeconds(3661); // 1:01:01
        
        expect($duration->getSeconds())->toBe(3661);
        expect($duration->getMinutes())->toBe(61);
        expect($duration->getHours())->toBe(1);
    });

    it('can be created from minutes', function () {
        $duration = Duration::fromMinutes(90); // 1.5 hours
        
        expect($duration->getSeconds())->toBe(5400);
        expect($duration->getMinutes())->toBe(90);
        expect($duration->getHours())->toBe(1);
    });

    it('can be created from hours', function () {
        $duration = Duration::fromHours(2);
        
        expect($duration->getSeconds())->toBe(7200);
        expect($duration->getHours())->toBe(2);
    });

    it('can be created from string format', function () {
        $duration = Duration::fromString('01:30:45');
        
        expect($duration->getSeconds())->toBe(5445); // 1*3600 + 30*60 + 45
        expect($duration->format())->toBe('01:30:45');
    });

    it('validates string format', function () {
        expect(fn() => Duration::fromString('invalid'))
            ->toThrow(InvalidDurationException::class, 'Invalid time format');
            
        expect(fn() => Duration::fromString('25:00:00'))
            ->toThrow(InvalidDurationException::class, 'Invalid time values');
            
        expect(fn() => Duration::fromString('01:60:00'))
            ->toThrow(InvalidDurationException::class, 'Invalid time values');
    });

    it('rejects negative durations', function () {
        expect(fn() => Duration::fromSeconds(-1))
            ->toThrow(InvalidDurationException::class, 'Duration cannot be negative');
    });

    it('formats correctly', function () {
        expect(Duration::fromSeconds(0)->format())->toBe('00:00:00');
        expect(Duration::fromSeconds(61)->format())->toBe('00:01:01');
        expect(Duration::fromSeconds(3661)->format())->toBe('01:01:01');
        expect(Duration::fromSeconds(90061)->format())->toBe('25:01:01');
    });

    it('provides human readable format', function () {
        expect(Duration::fromSeconds(30)->toHuman())->toBe('30秒');
        expect(Duration::fromSeconds(90)->toHuman())->toBe('1分30秒');
        expect(Duration::fromSeconds(120)->toHuman())->toBe('2分');
        expect(Duration::fromSeconds(3600)->toHuman())->toBe('1小时');
        expect(Duration::fromSeconds(3661)->toHuman())->toBe('1小时1分1秒');
        expect(Duration::fromSeconds(7200)->toHuman())->toBe('2小时');
    });

    it('supports arithmetic operations', function () {
        $duration1 = Duration::fromMinutes(30);
        $duration2 = Duration::fromMinutes(45);
        
        $sum = $duration1->add($duration2);
        expect($sum->getMinutes())->toBe(75);
        
        $diff = $duration2->subtract($duration1);
        expect($diff->getMinutes())->toBe(15);
        
        // 测试减法不会产生负数
        $safeDiff = $duration1->subtract($duration2);
        expect($safeDiff->getSeconds())->toBe(0);
    });

    it('supports comparison operations', function () {
        $short = Duration::fromMinutes(30);
        $long = Duration::fromMinutes(60);
        $equal = Duration::fromMinutes(30);
        
        expect($long->isGreaterThan($short))->toBeTrue();
        expect($short->isLessThan($long))->toBeTrue();
        expect($short->equals($equal))->toBeTrue();
        expect($short->equals($long))->toBeFalse();
    });

    it('can create zero duration', function () {
        $zero = Duration::zero();
        
        expect($zero->getSeconds())->toBe(0);
        expect($zero->isZero())->toBeTrue();
        expect($zero->format())->toBe('00:00:00');
        expect($zero->toHuman())->toBe('0秒');
    });

    it('can be converted to string', function () {
        $duration = Duration::fromString('02:30:45');
        
        expect((string) $duration)->toBe('02:30:45');
        expect($duration->__toString())->toBe('02:30:45');
    });

});

describe('Duration Edge Cases', function () {
    
    it('handles very large durations', function () {
        $duration = Duration::fromHours(100);
        
        expect($duration->format())->toBe('100:00:00');
        expect($duration->toHuman())->toBe('100小时');
    });

    it('handles single digit inputs correctly', function () {
        $duration = Duration::fromString('1:05:09');
        
        expect($duration->getHours())->toBe(1);
        expect($duration->format())->toBe('01:05:09');
    });

});