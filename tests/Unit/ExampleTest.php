<?php

use LiveStream\LiveStream;
use LiveStream\PlatformFactory;
use LiveStream\Platforms\Douyin\DouyinPlatform;
use LiveStream\Platforms\Douyin\RoomInfo\DouyinRoomInfo;
use LiveStream\Recording\PendingRecorder;
use LiveStream\Config\RecordingOptions;
use LiveStream\Recording\Advanced\PhpFFmpegRecorder;
use LiveStream\Platforms\PlatformManager;
use LiveStream\Recording\Drivers\NativeFFmpegRecorder;
use LiveStream\Recording\Runners\SymfonyProcessRunner;
use LiveStream\Recording\RecordingPipeline;
use LiveStream\Recording\ValueObjects\RecordHandle;

beforeEach(function () {
    $this->platformManager = new PlatformManager(new PlatformFactory());
});

test('test get live data', function () {

    $driver = $this->platformManager->driver('https://live.douyin.com/474894543910');

    expect($driver)->toBeInstanceOf(DouyinPlatform::class);
    expect($driver->getRoomInfo())->toBeInstanceOf(DouyinRoomInfo::class)->dump();

    // 测试新的 getStreamConfig 方法
    $streamConfig = $driver->getRoomInfo()->getStreamConfig();
    expect($streamConfig)->toBeInstanceOf(\LiveStream\Config\StreamConfig::class);
    expect($streamConfig->getRecordUrl())->toBeString();
});



test('example', function () {
    expect(true)->toBeTrue();
});
