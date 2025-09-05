<?php

declare(strict_types=1);

namespace LiveStream\Platforms\Douyin\Http\Connector;

use Saloon\Http\BaseResource;
use LiveStream\Contracts\RoomInfoInterface;
use LiveStream\Platforms\Douyin\Http\Requests\DouYinStreamRequest;
use LiveStream\Platforms\Douyin\Http\Requests\DouYinAppStreamRequest;
use LiveStream\Platforms\Douyin\RoomInfo\DouyinRoomInfo;
use LiveStream\Exceptions\PlatformException;

class Resource extends BaseResource
{
    public function getDouyinStreamResponse(string $url): RoomInfoInterface
    {
        try {
            $response = $this->connector->send(new DouYinStreamRequest($url));
            $html = $response->body();
            $room = $this->parseWebRoomFromHtml($html);
            return new DouyinRoomInfo($room);
        } catch (\Throwable $e) {
            // 与 Python 一致：Web 解析失败则回退到 App 接口
            return $this->getDouyinAppStreamResponse($url);
        }
    }

    public function getDouyinAppStreamResponse(string $url): RoomInfoInterface
    {
        $response = $this->connector->send(new DouYinAppStreamRequest($url));
        $json = $response->json();
        $room = $this->parseAppRoomFromJson($json);
        return new DouyinRoomInfo($room);
    }

    public function getDouYinRoomInfo(string $url): RoomInfoInterface
    {
        $useApp = str_contains($url, 'v.douyin.com') || str_contains($url, '/user/');
        if ($useApp) {
            return $this->getDouyinAppStreamResponse($url);
        }

        return $this->getDouyinStreamResponse($url);
    }

    public function parseWebRoomFromHtml(string $html): array
    {
        // 按照 spider.py get_douyin_stream_data 的逻辑实现，已优化性能

        // 步骤1：提取嵌入的 JSON 数据 (与 Python 第174-177行一致)
        $matchJsonStr = null;
        if (preg_match('/(\{\\"state\\":.*?)]\\n"]\)/', $html, $matches)) {
            $matchJsonStr = $matches[1];
        } elseif (preg_match('/(\{\\"common\\":.*?)]\\n"]\)<\/script><div hidden/', $html, $matches)) {
            $matchJsonStr = $matches[1];
        }

        // 方法2：新的 pace_f.push 格式 - 寻找所有 roomStore 实例 (基于实际 HTML 分析)
        if (!$matchJsonStr) {
            // 在新版抖音页面中，可能有多个 roomStore，寻找包含有效房间数据的那个
            $offset = 0;
            while (($roomStorePos = strpos($html, '\\"roomStore\\":', $offset)) !== false) {
                $colonPos = $roomStorePos + 14; // 跳过 \"roomStore\":
                $startPos = strpos($html, '{', $colonPos);

                if ($startPos !== false) {
                    // 使用括号计数来找到完整的 roomStore 对象
                    $content = substr($html, $startPos);
                    $braceCount = 0;
                    $endPos = 0;

                    for ($i = 0; $i < strlen($content); $i++) {
                        $char = $content[$i];
                        if ($char === '{') {
                            $braceCount++;
                        } elseif ($char === '}') {
                            $braceCount--;
                            if ($braceCount === 0) {
                                $endPos = $i;
                                break;
                            }
                        }
                    }

                    if ($endPos > 0) {
                        $roomStoreContent = substr($content, 0, $endPos + 1);

                        // 检查这个 roomStore 是否包含有效的房间数据
                        if (
                            strpos($roomStoreContent, '\\"room\\":{') !== false &&
                            strpos($roomStoreContent, '\\"id_str\\"') !== false
                        ) {
                            // 构造一个简化的 JSON，只包含 roomStore
                            $matchJsonStr = '{"roomStore":' . $roomStoreContent . '}';
                            break; // 找到有效数据，停止搜索
                        }
                    }
                }

                $offset = $roomStorePos + 14; // 继续搜索下一个 roomStore
            }
        }

        // 方法3：直接从 HTML 中查找 roomStore 数据
        if (!$matchJsonStr) {
            // 查找 roomStore 的位置
            $roomStorePos = strpos($html, '"roomStore"');
            if ($roomStorePos !== false) {
                // 向前找到包含 roomStore 的完整 JSON 块的开始
                $searchStart = max(0, $roomStorePos - 10000); // 向前搜索 10k 字符
                $searchString = substr($html, $searchStart, 20000); // 提取 20k 字符用于搜索

                // 尝试匹配包含 roomStore 的 JSON 结构
                if (preg_match('/"roomStore":\{[^}]*"roomInfo":\{[^}]*"room":\{[^}]*"id_str"[^}]*\}/', $searchString, $matches)) {
                    // 找到了完整的 roomStore 结构，现在构造一个简化的 JSON
                    $matchJsonStr = '{"roomStore":' . $this->extractRoomStoreJson($searchString) . '}';
                }
            }
        }

        if (!$matchJsonStr) {
            throw new PlatformException('Failed to extract embedded JSON from Douyin HTML.');
        }

        // 步骤2：清理 JSON 字符串 (第178行)
        // 使用 PHP 的 stripcslashes 正确处理转义字符
        $cleanedString = stripcslashes($matchJsonStr);

        // 步骤3：解析 JSON 并提取 roomStore
        $jsonData = json_decode($cleanedString, true);
        if (!$jsonData) {
            // 调试信息
            $error = json_last_error_msg();
            $preview = substr($cleanedString, 0, 200);
            throw new PlatformException("Failed to parse JSON from Douyin HTML. Error: $error. Preview: $preview");
        }

        // 处理不同的 JSON 结构
        $roomStore = null;
        $anchorName = '';

        // 原始格式：直接包含 roomStore
        if (isset($jsonData['roomStore'])) {
            $roomStore = $jsonData['roomStore'];
        }
        // 可能的嵌套格式
        elseif (isset($jsonData['state']['roomStore'])) {
            $roomStore = $jsonData['state']['roomStore'];
        }
        // 如果还是没找到，尝试用原始的字符串匹配方法
        else {
            if (!preg_match('/"roomStore":(.*?),"linkmicStore"/', $cleanedString, $roomMatch)) {
                throw new PlatformException('Failed to locate roomStore in Douyin HTML JSON.');
            }
            $roomStoreStr = $roomMatch[1];
            $roomStoreStr = explode(',"has_commerce_goods"', $roomStoreStr)[0] . '}}}';
            $roomStoreData = json_decode($roomStoreStr, true);
            if (isset($roomStoreData['roomInfo']['room'])) {
                $room = $roomStoreData['roomInfo']['room'];
            }
        }

        // 从 roomStore 中提取房间信息
        if ($roomStore && isset($roomStore['roomInfo']['room'])) {
            $room = $roomStore['roomInfo']['room'];
        } elseif ($roomStore && !empty($roomStore['roomInfo']) && is_array($roomStore['roomInfo'])) {
            // 有时 roomInfo 本身就是房间数据
            $room = $roomStore['roomInfo'];
        }

        if (!isset($room) || empty($room)) {
            // 调试信息：显示实际的数据结构
            $debugInfo = [
                'jsonData_keys' => array_keys($jsonData),
                'roomStore_keys' => isset($jsonData['roomStore']) ? array_keys($jsonData['roomStore']) : 'roomStore not found',
                'roomInfo_keys' => isset($jsonData['roomStore']['roomInfo']) ? array_keys($jsonData['roomStore']['roomInfo']) : 'roomInfo not found'
            ];
            throw new PlatformException('Failed to extract room data from roomStore. Debug: ' . json_encode($debugInfo));
        }

        // 步骤4：提取主播名称
        if (preg_match('/"nickname":"([^"]*)"/', $cleanedString, $nameMatch)) {
            $anchorName = $nameMatch[1];
        }

        // 构建完整的房间数据（对应 Python 第182-187行）
        $roomData = [
            'id_str' => $room['id_str'] ?? $room['id'] ?? '',
            'status' => (int)($room['status'] ?? 4),
            'title' => $room['title'] ?? '',
            'anchor_name' => $anchorName ?: ($room['owner']['nickname'] ?? ''),
            'stream_url' => $room['stream_url'] ?? [],
            'user_count_str' => $room['user_count_str'] ?? '0',
        ];

        // 检查直播状态（对应 Python 第188-189行）
        if ($roomData['status'] === 4) {
            return $roomData; // 未直播，直接返回
        }


        // 步骤5：Origin 流注入（对应 Python 第190-203行）
        $this->injectOriginFromSdk($roomData);

        // 步骤6：修复 URL 编码问题 - 这是导致 403 错误的关键原因
        $this->fixUrlEncoding($roomData);

        return $roomData;
    }

    private function parseAppRoomFromJson(array $json): array
    {
        // 解析移动端 App 接口的 JSON 数据
        $room = [];

        // webcast.amemv.com/webcast/room/reflow/info 格式
        if (isset($json['data']['room'])) {
            $roomData = $json['data']['room'];
            $owner = $roomData['owner'] ?? [];

            $room = [
                'id_str' => $roomData['id_str'] ?? '',
                'status' => (int)($roomData['status'] ?? 4),
                'title' => $roomData['title'] ?? '',
                'anchor_name' => $owner['nickname'] ?? '',
                'stream_url' => $roomData['stream_url'] ?? [],
                'user_count_str' => $roomData['user_count_str'] ?? '0',
            ];
        }

        return $room;
    }

    private function injectOriginFromSdk(array &$room): void
    {
        // Origin 流注入逻辑（对应 Python 第190-203行）
        try {
            // 简化的 origin 流处理
            if (isset($room['stream_url']) && is_array($room['stream_url'])) {
                // 这里可以实现 origin 流的注入逻辑
                // 暂时保持原有逻辑不变
            }
        } catch (\Throwable) {
        }
    }

    /**
     * 修复 URL 编码问题 - 这是导致 403 错误的关键原因
     * 
     * 问题：从 HTML JSON 解析出来的 URL 中，& 被编码为 u0026
     * 解决：将 u0026 替换回正确的 &，确保 CDN 服务器能正确解析 URL 参数
     */
    private function fixUrlEncoding(array &$room): void
    {
        if (!isset($room['stream_url']) || !is_array($room['stream_url'])) {
            return;
        }

        $streamUrl = &$room['stream_url'];

        // 修复所有流地址中的编码问题
        $urlFields = ['flv_pull_url', 'hls_pull_url_map'];

        foreach ($urlFields as $field) {
            if (isset($streamUrl[$field]) && is_array($streamUrl[$field])) {
                foreach ($streamUrl[$field] as $quality => $url) {
                    if (is_string($url)) {
                        // 将 u0026 替换为 &，修复 URL 编码问题
                        $streamUrl[$field][$quality] = str_replace('u0026', '&', $url);
                    }
                }
            }
        }

        // 修复单个 URL 字段
        $singleUrlFields = ['hls_pull_url'];
        foreach ($singleUrlFields as $field) {
            if (isset($streamUrl[$field]) && is_string($streamUrl[$field])) {
                $streamUrl[$field] = str_replace('u0026', '&', $streamUrl[$field]);
            }
        }
    }

    /**
     * 从搜索字符串中提取 roomStore JSON
     */
    private function extractRoomStoreJson(string $searchString): string
    {
        $roomStorePos = strpos($searchString, '"roomStore"');
        if ($roomStorePos === false) {
            return '{}';
        }

        // 找到 "roomStore": 后面的 {
        $colonPos = strpos($searchString, ':', $roomStorePos);
        $openBracePos = strpos($searchString, '{', $colonPos);

        if ($openBracePos === false) {
            return '{}';
        }

        // 使用简单的括号匹配来找到完整的 JSON 对象
        $braceCount = 0;
        $startPos = $openBracePos;
        $endPos = $startPos;

        for ($i = $startPos; $i < strlen($searchString); $i++) {
            $char = $searchString[$i];
            if ($char === '{') {
                $braceCount++;
            } elseif ($char === '}') {
                $braceCount--;
                if ($braceCount === 0) {
                    $endPos = $i;
                    break;
                }
            }
        }

        if ($braceCount === 0 && $endPos > $startPos) {
            return substr($searchString, $startPos, $endPos - $startPos + 1);
        }

        return '{}';
    }
}
