<?php

declare(strict_types=1);

use LiveStream\Domain\ValueObjects\StreamUrl;
use LiveStream\Domain\ValueObjects\InvalidStreamUrlException;

describe('StreamUrl Value Object', function () {
    
    it('can be created from valid URL', function () {
        $url = StreamUrl::fromString('https://live.douyin.com/123456');
        
        expect($url->getValue())->toBe('https://live.douyin.com/123456');
        expect($url->getDomain())->toBe('live.douyin.com');
        expect($url->getScheme())->toBe('https');
        expect($url->isSecure())->toBeTrue();
    });

    it('validates URL format', function () {
        expect(fn() => StreamUrl::fromString('invalid-url'))
            ->toThrow(InvalidStreamUrlException::class, 'Invalid URL format');
    });

    it('rejects empty URL', function () {
        expect(fn() => StreamUrl::fromString(''))
            ->toThrow(InvalidStreamUrlException::class, 'Stream URL cannot be empty');
    });

    it('rejects unsupported schemes', function () {
        expect(fn() => StreamUrl::fromString('ftp://example.com'))
            ->toThrow(InvalidStreamUrlException::class, 'Unsupported URL scheme');
    });

    it('can match domain patterns', function () {
        $url = StreamUrl::fromString('https://live.douyin.com/123456');
        
        expect($url->matchesDomain('/douyin\.com/'))->toBeTrue();
        expect($url->matchesDomain('/bilibili\.com/'))->toBeFalse();
    });

    it('provides path information', function () {
        $url = StreamUrl::fromString('https://live.douyin.com/room/123456');
        
        expect($url->getPath())->toBe('/room/123456');
    });

    it('supports HTTP URLs', function () {
        $url = StreamUrl::fromString('http://live.douyin.com/123456');
        
        expect($url->getScheme())->toBe('http');
        expect($url->isSecure())->toBeFalse();
    });

    it('can be converted to string', function () {
        $originalUrl = 'https://live.douyin.com/123456';
        $url = StreamUrl::fromString($originalUrl);
        
        expect((string) $url)->toBe($originalUrl);
        expect($url->__toString())->toBe($originalUrl);
    });

    it('supports equality comparison', function () {
        $url1 = StreamUrl::fromString('https://live.douyin.com/123456');
        $url2 = StreamUrl::fromString('https://live.douyin.com/123456');
        $url3 = StreamUrl::fromString('https://live.douyin.com/654321');
        
        expect($url1->equals($url2))->toBeTrue();
        expect($url1->equals($url3))->toBeFalse();
    });

    it('handles URLs without domain gracefully', function () {
        // 这个测试确保我们正确处理edge case
        expect(fn() => StreamUrl::fromString('https://'))
            ->toThrow(InvalidStreamUrlException::class, 'URL must have a valid domain');
    });

});

describe('StreamUrl Edge Cases', function () {
    
    it('handles international domains', function () {
        $url = StreamUrl::fromString('https://直播.中国/123456');
        
        expect($url->getDomain())->toBe('直播.中国');
    });

    it('handles URLs with query parameters', function () {
        $url = StreamUrl::fromString('https://live.douyin.com/123456?quality=high&format=mp4');
        
        expect($url->getValue())->toContain('quality=high');
        expect($url->getDomain())->toBe('live.douyin.com');
    });

    it('handles URLs with fragments', function () {
        $url = StreamUrl::fromString('https://live.douyin.com/123456#section');
        
        expect($url->getValue())->toContain('#section');
    });

});