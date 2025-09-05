<?php

declare(strict_types=1);

namespace LiveStream\Traits;

use LiveStream\Contracts\RoomInfoInterface;
use LiveStream\Config\RecordingOptions;
use LiveStream\Exceptions\RecordingException;
use LiveStream\Traits\HasRecordingOptions;
/**
 * 路径构建工具
 * 
 * 负责构建录制文件的输出路径
 */
trait HasPathBuilder
{
    /**
     * 确保输出目录存在
     * 
     * @throws RecordingException 当目录创建失败时
     */
    private function ensureOutputDirectoryExists(string $savePath): void
    {
        $directory = dirname($savePath);

        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw RecordingException::permissionDenied($directory, 'create');
        }

        if (!is_writable($directory)) {
            throw RecordingException::permissionDenied($directory, 'write');
        }
    }

    /**
     * 清理文件名中的非法字符
     * 
     * @param string $filename 原始文件名
     * @return string 清理后的文件名
     */
    private function sanitizeFilename(string $filename): string
    {
        // // 移除或替换非法字符
        // $filename = preg_replace('/[^\w\-_\.]/', '_', $filename);

        // // 移除多个连续的下划线
        // $filename = preg_replace('/_+/', '_', $filename);

        // // 移除开头和结尾的下划线
        // return trim($filename, '_');

        return $filename;
    }
}
