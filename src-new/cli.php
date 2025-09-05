<?php

declare(strict_types=1);

/**
 * 命令行入口文件
 * 
 * 提供完整的CLI操作界面
 */

require_once __DIR__ . '/../vendor/autoload.php';

use LiveStream\Presentation\CLI\RecordingCommand;
use LiveStream\Shared\Contracts\ContainerInterface;

// 解析命令行参数
function parseArgs(array $argv): array
{
    $args = [];
    $currentKey = null;

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        if (str_starts_with($arg, '--')) {
            $currentKey = substr($arg, 2);
            $args[$currentKey] = true; // 默认为true，如果有值会被覆盖
        } elseif (str_starts_with($arg, '-')) {
            $currentKey = substr($arg, 1);
            $args[$currentKey] = true;
        } else {
            if ($currentKey !== null) {
                $args[$currentKey] = $arg;
                $currentKey = null;
            } else {
                // 位置参数
                if (!isset($args['command'])) {
                    $args['command'] = $arg;
                } elseif (!isset($args['url']) && $args['command'] === 'start') {
                    $args['url'] = $arg;
                } elseif (!isset($args['output']) && $args['command'] === 'start') {
                    $args['output'] = $arg;
                } elseif (!isset($args['id']) && in_array($args['command'], ['stop', 'status'])) {
                    $args['id'] = $arg;
                }
            }
        }
    }

    return $args;
}

try {
    $args = parseArgs($argv);
    $command = $args['command'] ?? 'help';

    // 初始化容器
    /** @var ContainerInterface $container */
    $container = require __DIR__ . '/bootstrap.php';

    // 创建命令处理器
    $cli = new RecordingCommand($container);

    // 执行命令
    $exitCode = match ($command) {
        'start' => $cli->start($args),
        'stop' => $cli->stop($args),
        'status' => $cli->status($args),
        'list' => $cli->list($args),
        'help' => $cli->help(),
        default => function() use ($command) {
            echo "❌ 未知命令: {$command}\n";
            echo "使用 'php cli.php help' 查看帮助信息\n";
            return 1;
        }()
    };

    exit($exitCode);

} catch (Throwable $e) {
    echo "\n💥 CLI异常: {$e->getMessage()}\n";
    echo "文件: {$e->getFile()}:{$e->getLine()}\n";
    
    if (isset($_ENV['DEBUG']) && $_ENV['DEBUG']) {
        echo "\n🐛 调试信息:\n";
        echo $e->getTraceAsString() . "\n";
    }
    
    exit(1);
}