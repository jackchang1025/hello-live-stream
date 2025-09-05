<?php

declare(strict_types=1);

use LiveStream\Shared\Utils\SimpleContainer;
use LiveStream\Application\Services\RecordingService;
use LiveStream\Domain\Repositories\RecordingRepositoryInterface;
use Psr\Log\LoggerInterface;

describe('Container Integration', function () {
    
    it('can resolve dependencies correctly', function () {
        $container = createMockContainer();
        
        // 绑定基础依赖
        $container->singleton(LoggerInterface::class, function () {
            return new \Monolog\Logger('test');
        });
        
        $container->bind(RecordingRepositoryInterface::class, function () {
            return new class implements RecordingRepositoryInterface {
                public function save(\LiveStream\Domain\Entities\Recording $recording): void {}
                public function findById(\LiveStream\Domain\ValueObjects\RecordingId $id): ?\LiveStream\Domain\Entities\Recording { return null; }
                public function findByStatus(\LiveStream\Domain\ValueObjects\RecordingStatus $status): array { return []; }
                public function findActive(): array { return []; }
                public function delete(\LiveStream\Domain\ValueObjects\RecordingId $id): void {}
                public function findAll(int $limit = 100, int $offset = 0): array { return []; }
                public function count(?\LiveStream\Domain\ValueObjects\RecordingStatus $status = null): int { return 0; }
            };
        });
        
        $container->bind(\LiveStream\Domain\Repositories\PlatformRepositoryInterface::class, function () {
            return new class implements \LiveStream\Domain\Repositories\PlatformRepositoryInterface {
                public function findByUrl(\LiveStream\Domain\ValueObjects\StreamUrl $url): ?\LiveStream\Domain\Entities\Platform { return null; }
                public function findByName(string $name): ?\LiveStream\Domain\Entities\Platform { return null; }
                public function findAll(): array { return []; }
                public function save(\LiveStream\Domain\Entities\Platform $platform): void {}
                public function supports(\LiveStream\Domain\ValueObjects\StreamUrl $url): bool { return true; }
            };
        });
        
        $container->bind(\LiveStream\Domain\Factories\RecorderFactoryInterface::class, function () {
            return new class implements \LiveStream\Domain\Factories\RecorderFactoryInterface {
                public function create(\LiveStream\Domain\Entities\Platform $platform, \LiveStream\Domain\Entities\Recording $recording): \LiveStream\Domain\Factories\RecorderInterface {
                    throw new Exception('Not implemented in test');
                }
                public function register(string $type, callable $creator): void {}
                public function getSupportedTypes(): array { return []; }
                public function supports(string $type): bool { return false; }
            };
        });
        
        // 解析服务
        $service = $container->make(RecordingService::class);
        
        expect($service)->toBeInstanceOf(RecordingService::class);
    });

    it('supports singleton binding', function () {
        $container = new SimpleContainer();
        
        $container->singleton('test.service', function () {
            return new stdClass();
        });
        
        $instance1 = $container->make('test.service');
        $instance2 = $container->make('test.service');
        
        expect($instance1)->toBe($instance2);
    });

    it('supports regular binding', function () {
        $container = new SimpleContainer();
        
        $container->bind('test.service', function () {
            return new stdClass();
        });
        
        $instance1 = $container->make('test.service');
        $instance2 = $container->make('test.service');
        
        expect($instance1)->not->toBe($instance2);
    });

    it('can auto-resolve constructor dependencies', function () {
        $container = new SimpleContainer();
        
        // 定义测试类
        $testClass = new class {
            public function __construct(
                public readonly stdClass $dependency
            ) {}
        };
        
        $container->bind(stdClass::class, fn() => new stdClass());
        
        $instance = $container->make($testClass::class);
        
        expect($instance->dependency)->toBeInstanceOf(stdClass::class);
    });

});

describe('Pipeline Integration', function () {
    
    it('can process pipes with container', function () {
        $container = new SimpleContainer();
        $pipeline = new \LiveStream\Shared\Utils\Pipeline($container);
        
        // 定义测试管道
        $testPipe = new class {
            public function handle($passable, $next) {
                $passable['processed'] = true;
                return $next($passable);
            }
        };
        
        $result = $pipeline
            ->send(['data' => 'test'])
            ->through([$testPipe])
            ->then(fn($passable) => $passable);
        
        expect($result['processed'])->toBeTrue();
        expect($result['data'])->toBe('test');
    });

    it('can handle callable pipes', function () {
        $pipeline = new \LiveStream\Shared\Utils\Pipeline();
        
        $result = $pipeline
            ->send('initial')
            ->through([
                fn($passable, $next) => $next($passable . '_pipe1'),
                fn($passable, $next) => $next($passable . '_pipe2'),
            ])
            ->then(fn($passable) => $passable . '_final');
        
        expect($result)->toBe('initial_pipe1_pipe2_final');
    });

});