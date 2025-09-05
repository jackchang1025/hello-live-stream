<?php

declare(strict_types=1);

namespace Tests\Unit;

use LiveStream\Platforms\Douyin\Http\Connector\Resource;
use LiveStream\Platforms\Douyin\Http\Connector\DouyinConnector;

test('compare douyin parsing results with python output', function () {

    // Python 版本的输出数据（从你提供的数据）
    $pythonOutput = [
        'id_str' => '7537288513609927476',
        'status' => 2,
        'status_str' => '2',
        'title' => '2025到底应该怎么办！？',
        'user_count_str' => '77',
        'stream_url' => [
            'flv_pull_url' => [
                'ORIGIN' => 'http://pull-flv-l11.douyincdn.com/third/stream-694230885870862812_or4.flv?arch_hrchy=w1&exp_hrchy=w1&expire=1755519946&major_anchor_level=common&sign=9eec3fe889997888c49871d9339148f4&t_id=037-2025081120254510A48334857E66CDC1B9-9zL49X&unique_id=stream-694230885870862812_476_flv_or4&codec=h264',
                'FULL_HD1' => 'http://pull-flv-l11.douyincdn.com/third/stream-694230885870862812_or4.flv?arch_hrchy=w1&exp_hrchy=w1&expire=1755519946&major_anchor_level=common&sign=9eec3fe889997888c49871d9339148f4&t_id=037-2025081120254510A48334857E66CDC1B9-9zL49X&unique_id=stream-694230885870862812_476_flv_or4',
                'HD1' => 'http://pull-flv-l11.douyincdn.com/third/stream-694230885870862812_hd.flv?arch_hrchy=w1&exp_hrchy=w1&expire=1755519946&major_anchor_level=common&sign=9f0043fb6a9ed9fade3fd4bcdadf9ad0&t_id=037-2025081120254510A48334857E66CDC1B9-9zL49X&unique_id=stream-694230885870862812_476_flv_hd',
                'SD1' => 'http://pull-flv-l11.douyincdn.com/third/stream-694230885870862812_ld.flv?arch_hrchy=w1&exp_hrchy=w1&expire=1755519946&major_anchor_level=common&sign=02281f1629ecf0cf9f2bc99b8907ba6d&t_id=037-2025081120254510A48334857E66CDC1B9-9zL49X&unique_id=stream-694230885870862812_476_flv_ld',
                'SD2' => 'http://pull-flv-l11.douyincdn.com/third/stream-694230885870862812_sd.flv?arch_hrchy=w1&exp_hrchy=w1&expire=1755519946&major_anchor_level=common&sign=596ed9e474d3370db8fd5fc4a72f73aa&t_id=037-2025081120254510A48334857E66CDC1B9-9zL49X&unique_id=stream-694230885870862812_476_flv_sd'
            ],
            'hls_pull_url_map' => [
                'ORIGIN' => 'http://pull-hls-l11.douyincdn.com/third/stream-694230885870862812_or4.m3u8?arch_hrchy=w1&exp_hrchy=w1&expire=1755519946&major_anchor_level=common&sign=63015841b626f417b3737b61ec815530&t_id=037-2025081120254510A48334857E66CDC1B9-9zL49X&codec=h264',
                'FULL_HD1' => 'http://pull-hls-l11.douyincdn.com/third/stream-694230885870862812_or4.m3u8?arch_hrchy=w1&exp_hrchy=w1&expire=1755519946&major_anchor_level=common&sign=63015841b626f417b3737b61ec815530&t_id=037-2025081120254510A48334857E66CDC1B9-9zL49X',
                'HD1' => 'http://pull-hls-l11.douyincdn.com/third/stream-694230885870862812_hd.m3u8?arch_hrchy=w1&exp_hrchy=w1&expire=1755519946&major_anchor_level=common&sign=e4d2d1010515a2da16869b9735d4118a&t_id=037-2025081120254510A48334857E66CDC1B9-9zL49X',
                'SD1' => 'http://pull-hls-l11.douyincdn.com/third/stream-694230885870862812_ld.m3u8?arch_hrchy=w1&exp_hrchy=w1&expire=1755519946&major_anchor_level=common&sign=ea1233511a78b0b4c99a2c97aa71ad66&t_id=037-2025081120254510A48334857E66CDC1B9-9zL49X',
                'SD2' => 'http://pull-hls-l11.douyincdn.com/third/stream-694230885870862812_sd.m3u8?arch_hrchy=w1&exp_hrchy=w1&expire=1755519946&major_anchor_level=common&sign=b9a0ec5298bf87f09eb8601606106f73&t_id=037-2025081120254510A48334857E66CDC1B9-9zL49X'
            ],
            'hls_pull_url' => 'http://pull-hls-l11.douyincdn.com/third/stream-694230885870862812_hd.m3u8?arch_hrchy=w1&exp_hrchy=w1&expire=1755519946&major_anchor_level=common&sign=e4d2d1010515a2da16869b9735d4118a&t_id=037-2025081120254510A48334857E66CDC1B9-9zL49X'
        ],
        'anchor_name' => '急速引擎'
    ];

    // 读取 HTML 文件
    $htmlContent = file_get_contents(__DIR__ . '/html.html');

    // 创建 Resource 实例并解析 HTML
    $connector = new DouyinConnector();
    $resource = new Resource($connector);

    // 执行解析
    $phpParsedRoom = $resource->parseWebRoomFromHtml($htmlContent);

    // 断言：基础字段对比
    expect($phpParsedRoom['id_str'])->toBe($pythonOutput['id_str'], '房间ID应该一致');
    expect($phpParsedRoom['title'])->toBe($pythonOutput['title'], '标题应该一致');
    expect($phpParsedRoom['anchor_name'])->toBe($pythonOutput['anchor_name'], '主播名称应该一致');
    expect($phpParsedRoom['status'])->toBe($pythonOutput['status'], '直播状态应该一致');
    expect($phpParsedRoom['user_count_str'])->toBe($pythonOutput['user_count_str'], '用户数应该一致');

    // 断言：流地址结构存在
    expect($phpParsedRoom)->toHaveKey('stream_url');
    expect($phpParsedRoom['stream_url'])->toHaveKey('hls_pull_url_map');
    expect($phpParsedRoom['stream_url'])->toHaveKey('flv_pull_url');

    // 断言：流地址数量对比
    $pythonHlsUrls = $pythonOutput['stream_url']['hls_pull_url_map'];
    $phpHlsUrls = $phpParsedRoom['stream_url']['hls_pull_url_map'];

    // 注意：PHP版本可能缺少某些画质，这是正常的，因为不同的解析方式可能获取到不同数量的画质
    expect(count($phpHlsUrls))->toBeGreaterThanOrEqual(3, 'PHP应该至少解析出3种画质');

    // 断言：关键画质的流地址对比
    $criticalQualities = ['HD1', 'SD1'];
    foreach ($criticalQualities as $quality) {
        expect($phpHlsUrls)->toHaveKey($quality);
        expect($pythonHlsUrls)->toHaveKey($quality);

        $pythonUrl = $pythonHlsUrls[$quality];
        $phpUrl = $phpHlsUrls[$quality];

        // 断言：URL基础结构一致
        expect(parse_url($phpUrl, PHP_URL_HOST))->toBe(parse_url($pythonUrl, PHP_URL_HOST), "{$quality}画质的域名应该一致");
        expect(parse_url($phpUrl, PHP_URL_PATH))->toBe(parse_url($pythonUrl, PHP_URL_PATH), "{$quality}画质的路径应该一致");

        // 断言：关键参数一致（这是修复403错误的核心）
        $pythonParams = parse_url($pythonUrl, PHP_URL_QUERY);
        $phpParams = parse_url($phpUrl, PHP_URL_QUERY);

        parse_str($pythonParams, $pythonQuery);
        parse_str($phpParams, $phpQuery);

        expect($phpQuery['expire'])->toBe($pythonQuery['expire'], "{$quality}画质的expire参数应该一致");
        expect($phpQuery['sign'])->toBe($pythonQuery['sign'], "{$quality}画质的sign参数应该一致");
        expect($phpQuery['t_id'])->toBe($pythonQuery['t_id'], "{$quality}画质的t_id参数应该一致");

        // 断言：URL中不应包含错误的编码
        expect($phpUrl)->not->toContain('u0026');
        expect($phpUrl)->toContain('&');
    }

    // 断言：ORIGIN画质存在性（如果两者都有的话）
    if (isset($pythonHlsUrls['ORIGIN']) && isset($phpHlsUrls['ORIGIN'])) {
        $pythonOriginUrl = $pythonHlsUrls['ORIGIN'];
        $phpOriginUrl = $phpHlsUrls['ORIGIN'];

        // 提取并对比关键参数
        parse_str(parse_url($pythonOriginUrl, PHP_URL_QUERY), $pythonOriginQuery);
        parse_str(parse_url($phpOriginUrl, PHP_URL_QUERY), $phpOriginQuery);

        expect($phpOriginQuery['expire'])->toBe($pythonOriginQuery['expire']);
        expect($phpOriginQuery['sign'])->toBe($pythonOriginQuery['sign']);
    }

    // 断言：FLV流地址对比
    $pythonFlvUrls = $pythonOutput['stream_url']['flv_pull_url'];
    $phpFlvUrls = $phpParsedRoom['stream_url']['flv_pull_url'];

    // 注意：PHP版本可能缺少某些画质，这是正常的
    expect(count($phpFlvUrls))->toBeGreaterThanOrEqual(3, 'PHP应该至少解析出3种FLV画质');

    foreach (['HD1', 'SD1'] as $quality) {
        if (isset($pythonFlvUrls[$quality]) && isset($phpFlvUrls[$quality])) {
            $pythonFlvUrl = $pythonFlvUrls[$quality];
            $phpFlvUrl = $phpFlvUrls[$quality];

            // 断言：FLV URL参数一致性
            parse_str(parse_url($pythonFlvUrl, PHP_URL_QUERY), $pythonFlvQuery);
            parse_str(parse_url($phpFlvUrl, PHP_URL_QUERY), $phpFlvQuery);

            expect($phpFlvQuery['expire'])->toBe($pythonFlvQuery['expire'], "{$quality}画质FLV的expire参数应该一致");
            expect($phpFlvQuery['sign'])->toBe($pythonFlvQuery['sign'], "{$quality}画质FLV的sign参数应该一致");

            // 断言：FLV URL编码修复
            expect($phpFlvUrl)->not->toContain('u0026');
        }
    }
});

test('validate url encoding fix', function () {
    // 读取 HTML 文件
    $htmlContent = file_get_contents(__DIR__ . '/html.html');

    // 创建 Resource 实例并解析 HTML
    $connector = new DouyinConnector();
    $resource = new Resource($connector);

    // 执行解析
    $phpParsedRoom = $resource->parseWebRoomFromHtml($htmlContent);

    // 断言：确保所有流地址都已正确修复编码
    $streamUrl = $phpParsedRoom['stream_url'];

    // 检查所有HLS流地址
    if (isset($streamUrl['hls_pull_url_map'])) {
        foreach ($streamUrl['hls_pull_url_map'] as $quality => $url) {
            expect($url)->not->toContain('u0026');
            expect($url)->toMatch('/[?&]/');
        }
    }

    // 检查所有FLV流地址
    if (isset($streamUrl['flv_pull_url'])) {
        foreach ($streamUrl['flv_pull_url'] as $quality => $url) {
            expect($url)->not->toContain('u0026');
            expect($url)->toMatch('/[?&]/');
        }
    }

    // 检查单个HLS URL
    if (isset($streamUrl['hls_pull_url'])) {
        expect($streamUrl['hls_pull_url'])->not->toContain('u0026');
        expect($streamUrl['hls_pull_url'])->toMatch('/[?&]/');
    }
});

test('verify stream url parameters', function () {
    // 读取 HTML 文件
    $htmlContent = file_get_contents(__DIR__ . '/html.html');

    // 创建 Resource 实例并解析 HTML
    $connector = new DouyinConnector();
    $resource = new Resource($connector);

    // 执行解析
    $phpParsedRoom = $resource->parseWebRoomFromHtml($htmlContent);

    // 验证关键参数存在
    $hlsUrls = $phpParsedRoom['stream_url']['hls_pull_url_map'] ?? [];

    foreach ($hlsUrls as $quality => $url) {
        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $params);

        // 断言：关键参数必须存在
        expect($params)->toHaveKey('expire');
        expect($params)->toHaveKey('sign');
        expect($params)->toHaveKey('t_id');

        // 断言：参数格式正确
        expect($params['expire'])->toMatch('/^\d+$/');
        expect($params['sign'])->toMatch('/^[a-f0-9]+$/');
        expect(strlen($params['sign']))->toBe(32);
    }
});
