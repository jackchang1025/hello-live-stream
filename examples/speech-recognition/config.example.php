<?php

/**
 * 阿里云智能语音识别配置示例
 */
return [
    // 阿里云访问凭证
    'access_key_id' => env('ALIYUN_ACCESS_KEY_ID', 'your-access-key-id'),
    'access_key_secret' => env('ALIYUN_ACCESS_KEY_SECRET', 'your-access-key-secret'),
    
    // 语音识别应用Key
    'app_key' => env('ALIYUN_ASR_APP_KEY', 'your-app-key'),
    
    // 地域ID
    'region_id' => env('ALIYUN_REGION_ID', 'cn-shanghai'),
    
    // OSS配置（用于上传音频文件）
    'oss' => [
        'bucket' => env('ALIYUN_OSS_BUCKET', 'your-bucket-name'),
        'endpoint' => env('ALIYUN_OSS_ENDPOINT', 'oss-cn-shanghai.aliyuncs.com'),
    ],
    
    // 临时音频文件目录
    'temp_audio_dir' => env('TEMP_AUDIO_DIR', sys_get_temp_dir() . '/speech-recognition'),
    
    // 音频提取默认设置
    'audio_extraction' => [
        'sample_rate' => 16000,  // 16kHz 适合语音识别
        'channels' => 1,         // 单声道
        'format' => 'wav',       // WAV格式
        'bitrate' => 128,        // 128kbps
    ],
    
    // 语音识别默认设置
    'speech_recognition' => [
        'noise_threshold' => 0.9,      // 噪音阈值
        'max_silence' => 800,          // 最大静音时长(ms)
        'enable_words' => true,        // 启用词级别时间戳
        'vocabulary_id' => null,       // 自定义词汇表ID
        'hot_words' => null,           // 热词
    ],
    
    // 批量处理设置
    'batch_processing' => [
        'max_concurrent' => 3,         // 最大并发数
        'cleanup_audio' => true,       // 处理完成后清理音频文件
    ],
    
    // 缓存设置
    'cache' => [
        'enabled' => true,
        'driver' => 'redis',           // redis, file, database
        'ttl' => 86400,               // 缓存时间(秒)
        'prefix' => 'speech_recognition:',
    ],
    
    // 成本优化设置
    'cost_optimization' => [
        'enable_deduplication' => true,  // 启用去重
        'hash_algorithm' => 'md5',       // 文件哈希算法
        'min_duration' => 1.0,           // 最小处理时长(秒)
        'max_file_size' => 500 * 1024 * 1024, // 最大文件大小(字节)
    ],
];
