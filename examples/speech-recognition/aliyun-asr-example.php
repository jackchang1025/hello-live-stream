<?php

declare(strict_types=1);

namespace LiveStream\Examples\SpeechRecognition;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * 阿里云智能语音识别示例
 * 
 * 支持实时语音识别和录音文件识别
 */
class AliyunASRExample
{
    private Client $client;
    private string $accessKeyId;
    private string $accessKeySecret;
    private string $appKey;
    
    public function __construct(string $accessKeyId, string $accessKeySecret, string $appKey)
    {
        $this->accessKeyId = $accessKeyId;
        $this->accessKeySecret = $accessKeySecret;
        $this->appKey = $appKey;
        
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false
        ]);
    }
    
    /**
     * 录音文件识别（推荐用于直播录制文件）
     */
    public function recognizeFile(string $audioFilePath): array
    {
        // 1. 提交录音文件识别请求
        $taskId = $this->submitFileRecognition($audioFilePath);
        
        // 2. 轮询获取识别结果
        return $this->pollRecognitionResult($taskId);
    }
    
    /**
     * 提交文件识别任务
     */
    private function submitFileRecognition(string $audioFilePath): string
    {
        $url = 'https://filetrans.cn-shanghai.aliyuncs.com/';
        
        // 上传音频文件到OSS或使用本地文件URL
        $audioUrl = $this->uploadAudioFile($audioFilePath);
        
        $params = [
            'Action' => 'SubmitTask',
            'Version' => '2018-08-17',
            'RegionId' => 'cn-shanghai',
            'AppKey' => $this->appKey,
            'FileLink' => $audioUrl,
            'EnableWords' => true, // 返回词级别时间戳
            'EnableSentenceTimeStamp' => true, // 返回句子级别时间戳
        ];
        
        $signature = $this->generateSignature('POST', $params);
        
        try {
            $response = $this->client->post($url, [
                'form_params' => $params,
                'headers' => [
                    'Authorization' => $signature,
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            if (isset($result['TaskId'])) {
                return $result['TaskId'];
            }
            
            throw new \Exception('提交识别任务失败: ' . json_encode($result));
            
        } catch (RequestException $e) {
            throw new \Exception('API请求失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 轮询获取识别结果
     */
    private function pollRecognitionResult(string $taskId): array
    {
        $url = 'https://filetrans.cn-shanghai.aliyuncs.com/';
        $maxAttempts = 60; // 最多等待5分钟
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            $params = [
                'Action' => 'GetTaskResult',
                'Version' => '2018-08-17',
                'RegionId' => 'cn-shanghai',
                'TaskId' => $taskId,
            ];
            
            $signature = $this->generateSignature('POST', $params);
            
            try {
                $response = $this->client->post($url, [
                    'form_params' => $params,
                    'headers' => [
                        'Authorization' => $signature,
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ]
                ]);
                
                $result = json_decode($response->getBody()->getContents(), true);
                
                if ($result['StatusText'] === 'SUCCESS') {
                    return $this->parseRecognitionResult($result['Result']);
                } elseif ($result['StatusText'] === 'RUNNING') {
                    sleep(5); // 等待5秒后重试
                    $attempt++;
                    continue;
                } else {
                    throw new \Exception('识别失败: ' . $result['StatusText']);
                }
                
            } catch (RequestException $e) {
                throw new \Exception('获取结果失败: ' . $e->getMessage());
            }
        }
        
        throw new \Exception('识别超时');
    }
    
    /**
     * 解析识别结果
     */
    private function parseRecognitionResult(string $resultJson): array
    {
        $result = json_decode($resultJson, true);
        $sentences = $result['Sentences'] ?? [];
        
        $transcription = [
            'full_text' => '',
            'sentences' => [],
            'words' => [],
            'duration' => 0
        ];
        
        foreach ($sentences as $sentence) {
            $transcription['sentences'][] = [
                'text' => $sentence['Text'],
                'start_time' => $sentence['BeginTime'] / 1000, // 转换为秒
                'end_time' => $sentence['EndTime'] / 1000,
                'confidence' => $sentence['SilenceConfidence'] ?? 0.9
            ];
            
            $transcription['full_text'] .= $sentence['Text'];
            
            // 解析词级别信息
            if (isset($sentence['Words'])) {
                foreach ($sentence['Words'] as $word) {
                    $transcription['words'][] = [
                        'word' => $word['Word'],
                        'start_time' => $word['BeginTime'] / 1000,
                        'end_time' => $word['EndTime'] / 1000,
                        'confidence' => $word['Confidence'] ?? 0.9
                    ];
                }
            }
        }
        
        if (!empty($sentences)) {
            $transcription['duration'] = end($sentences)['EndTime'] / 1000;
        }
        
        return $transcription;
    }
    
    /**
     * 生成阿里云API签名
     */
    private function generateSignature(string $method, array $params): string
    {
        // 实现阿里云API签名算法
        // 这里简化处理，实际使用时需要完整的签名实现
        ksort($params);
        $queryString = http_build_query($params);
        
        $stringToSign = $method . '&' . rawurlencode('/') . '&' . rawurlencode($queryString);
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret . '&', true));
        
        return 'acs ' . $this->accessKeyId . ':' . $signature;
    }
    
    /**
     * 上传音频文件（示例实现）
     */
    private function uploadAudioFile(string $filePath): string
    {
        // 实际实现中需要上传到OSS或其他云存储
        // 这里返回示例URL
        return 'https://your-oss-bucket.oss-cn-shanghai.aliyuncs.com/audio/' . basename($filePath);
    }
}

// 使用示例
try {
    $asr = new AliyunASRExample(
        'your-access-key-id',
        'your-access-key-secret', 
        'your-app-key'
    );
    
    $result = $asr->recognizeFile('/path/to/audio/file.wav');
    
    echo "识别结果:\n";
    echo "完整文本: " . $result['full_text'] . "\n";
    echo "总时长: " . $result['duration'] . "秒\n";
    echo "句子数量: " . count($result['sentences']) . "\n";
    
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
