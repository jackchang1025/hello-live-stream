<?php

declare(strict_types=1);

namespace LiveStream\Enum;

/**
 * 录制画质枚举
 * 
 * 定义支持的录制画质级别
 */
enum Quality: string
{
    case ORIGINAL = 'original';
    case ULTRA_HIGH = 'ultra_high';
    case HIGH = 'high';
    case STANDARD = 'standard';
    case LOW = 'low';

    /**
     * 获取画质的中文描述
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::ORIGINAL => '原画',
            self::ULTRA_HIGH => '超清',
            self::HIGH => '高清',
            self::STANDARD => '标清',
            self::LOW => '流畅',
        };
    }

    /**
     * 从中文描述创建枚举
     */
    public static function fromDisplayName(string $name): ?self
    {
        return match($name) {
            '原画' => self::ORIGINAL,
            '超清' => self::ULTRA_HIGH,
            '高清' => self::HIGH,
            '标清' => self::STANDARD,
            '流畅' => self::LOW,
            default => null,
        };
    }

    /**
     * 获取所有支持的画质选项
     * 
     * @return array<string, string>
     */
    public static function getOptions(): array
    {
        return [
            self::ORIGINAL->value => self::ORIGINAL->getDisplayName(),
            self::ULTRA_HIGH->value => self::ULTRA_HIGH->getDisplayName(),
            self::HIGH->value => self::HIGH->getDisplayName(),
            self::STANDARD->value => self::STANDARD->getDisplayName(),
            self::LOW->value => self::LOW->getDisplayName(),
        ];
    }
}