# 🏗️ 多平台直播录制扩展包 - 新架构设计

## 📋 设计原则

基于**领域驱动设计(DDD)**和**Clean Architecture**模式，遵循以下核心原则：

- **SOLID原则**: 单一职责、开闭、里氏替换、接口隔离、依赖倒置
- **分层架构**: 清晰的职责分离和依赖方向控制
- **类型安全**: 严格的类型提示和验证
- **依赖注入**: 通过容器管理依赖关系
- **面向接口编程**: 依赖抽象而非具体实现

## 🏗️ 分层架构设计

### 1. 表现层 (Presentation)
负责用户交互和外部接口

```
src-new/Presentation/
├── CLI/                    # 命令行接口
│   ├── RecordingCommand.php
│   ├── PlatformCommand.php
│   └── StatusCommand.php
├── HTTP/                   # HTTP接口 (可选)
│   ├── Controllers/
│   └── Middleware/
└── Events/                 # 事件处理
    ├── RecordingStarted.php
    └── RecordingCompleted.php
```

### 2. 应用层 (Application)
协调业务流程，不包含业务逻辑

```
src-new/Application/
├── Services/               # 应用服务
│   ├── RecordingService.php
│   ├── PlatformService.php
│   └── SplitterService.php
├── DTOs/                   # 数据传输对象
│   ├── RecordingRequest.php
│   ├── RecordingResponse.php
│   ├── PlatformInfo.php
│   └── SplitterConfig.php
├── Commands/               # 命令对象
│   ├── StartRecordingCommand.php
│   ├── StopRecordingCommand.php
│   └── GetPlatformInfoCommand.php
└── Handlers/               # 命令处理器
    ├── StartRecordingHandler.php
    ├── StopRecordingHandler.php
    └── GetPlatformInfoHandler.php
```

### 3. 领域层 (Domain)
核心业务逻辑，不依赖外部

```
src-new/Domain/
├── Entities/               # 领域实体
│   ├── Recording.php       # 录制实体
│   ├── Platform.php        # 平台实体
│   ├── Segment.php         # 分段实体
│   └── StreamInfo.php      # 流信息实体
├── ValueObjects/           # 值对象
│   ├── StreamUrl.php       # 流地址
│   ├── Duration.php        # 时长
│   ├── RecordingId.php     # 录制ID
│   ├── Quality.php         # 质量设置
│   └── OutputPath.php      # 输出路径
├── Repositories/           # 仓储接口
│   ├── PlatformRepositoryInterface.php
│   ├── RecordingRepositoryInterface.php
│   └── SegmentRepositoryInterface.php
├── Factories/              # 工厂接口
│   ├── PlatformFactoryInterface.php
│   ├── RecorderFactoryInterface.php
│   └── SplitterFactoryInterface.php
├── Services/               # 领域服务
│   ├── StreamValidationService.php
│   ├── QualitySelectionService.php
│   └── PathGenerationService.php
└── Strategies/             # 策略接口
    ├── SplittingStrategyInterface.php
    ├── QualityStrategyInterface.php
    └── NamingStrategyInterface.php
```

### 4. 基础设施层 (Infrastructure)
外部依赖的具体实现

```
src-new/Infrastructure/
├── Platforms/              # 平台实现
│   ├── DouyinPlatform.php
│   ├── KuaishouPlatform.php
│   ├── BilibiliPlatform.php
│   └── AbstractPlatform.php
├── Recording/              # 录制实现
│   ├── FFmpegRecorder.php
│   ├── PhpFFmpegRecorder.php
│   └── AbstractRecorder.php
├── Splitting/              # 分割实现
│   ├── TimeSplitter.php
│   ├── SizeSplitter.php
│   ├── HybridSplitter.php
│   └── AbstractSplitter.php
├── Repositories/           # 仓储实现
│   ├── FilePlatformRepository.php
│   ├── DatabaseRecordingRepository.php
│   └── MemorySegmentRepository.php
├── Factories/              # 工厂实现
│   ├── ConfigurablePlatformFactory.php
│   ├── StrategyBasedRecorderFactory.php
│   └── PolicyBasedSplitterFactory.php
├── External/               # 外部服务适配器
│   ├── DouyinApiAdapter.php
│   ├── FFmpegProcessAdapter.php
│   └── HttpClientAdapter.php
└── Configuration/          # 配置管理
    ├── ConfigurationManager.php
    ├── EnvironmentLoader.php
    └── ValidationRules.php
```

### 5. 共享内核 (Shared)
通用组件和工具

```
src-new/Shared/
├── Contracts/              # 通用接口
│   ├── ContainerInterface.php
│   ├── ConfigurableInterface.php
│   ├── ValidatableInterface.php
│   └── LoggableInterface.php
├── Exceptions/             # 异常定义
│   ├── DomainException.php
│   ├── ApplicationException.php
│   ├── InfrastructureException.php
│   └── ValidationException.php
├── Utils/                  # 工具类
│   ├── Pipeline.php        # 修复后的管道
│   ├── SimpleContainer.php # 简单DI容器
│   ├── Validator.php       # 验证器
│   └── Logger.php          # 日志器
└── Traits/                 # 通用特性
    ├── Configurable.php
    ├── Validatable.php
    ├── Loggable.php
    └── Makeable.php
```

## 🎨 设计模式应用

### 1. 工厂模式 (Factory Pattern)
```php
interface PlatformFactoryInterface
{
    public function createPlatform(StreamUrl $url): Platform;
    public function register(string $pattern, callable $creator): void;
    public function getSupportedPlatforms(): array;
}

class ConfigurablePlatformFactory implements PlatformFactoryInterface
{
    private array $creators = [];
    
    public function register(string $pattern, callable $creator): void
    {
        $this->creators[$pattern] = $creator;
    }
    
    public function createPlatform(StreamUrl $url): Platform
    {
        foreach ($this->creators as $pattern => $creator) {
            if (preg_match($pattern, $url->getValue())) {
                return $creator($url);
            }
        }
        
        throw new UnsupportedPlatformException($url);
    }
}
```

### 2. 仓储模式 (Repository Pattern)
```php
interface RecordingRepositoryInterface
{
    public function save(Recording $recording): void;
    public function findById(RecordingId $id): ?Recording;
    public function findByStatus(RecordingStatus $status): array;
    public function delete(RecordingId $id): void;
}

class DatabaseRecordingRepository implements RecordingRepositoryInterface
{
    public function __construct(
        private readonly DatabaseConnection $connection
    ) {}
    
    public function save(Recording $recording): void
    {
        // 实现数据库保存逻辑
    }
}
```

### 3. 策略模式 (Strategy Pattern)
```php
interface SplittingStrategyInterface
{
    public function shouldSplit(
        Duration $currentDuration, 
        FileSize $currentSize
    ): bool;
    
    public function getNextSegmentName(
        OutputPath $basePath, 
        int $segmentIndex
    ): string;
}

class TimeSplittingStrategy implements SplittingStrategyInterface
{
    public function __construct(
        private readonly Duration $maxDuration
    ) {}
    
    public function shouldSplit(
        Duration $currentDuration, 
        FileSize $currentSize
    ): bool {
        return $currentDuration->isGreaterThan($this->maxDuration);
    }
}
```

### 4. 值对象模式 (Value Object Pattern)
```php
final readonly class StreamUrl
{
    public function __construct(
        private string $value
    ) {
        $this->validate();
    }
    
    public static function fromString(string $url): self
    {
        return new self($url);
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
    
    public function getDomain(): string
    {
        return parse_url($this->value, PHP_URL_HOST) ?? '';
    }
    
    private function validate(): void
    {
        if (!filter_var($this->value, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException($this->value);
        }
    }
}
```

### 5. 依赖注入模式 (Dependency Injection)
```php
interface ContainerInterface
{
    public function bind(string $abstract, callable|string $concrete): void;
    public function singleton(string $abstract, callable|string $concrete): void;
    public function make(string $abstract): object;
    public function instance(string $abstract, object $instance): void;
}

class SimpleContainer implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];
    
    public function make(string $abstract): object
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        
        if (!isset($this->bindings[$abstract])) {
            return $this->resolveClass($abstract);
        }
        
        $concrete = $this->bindings[$abstract];
        
        if (is_callable($concrete)) {
            $object = $concrete($this);
        } else {
            $object = $this->resolveClass($concrete);
        }
        
        if ($this->isSingleton($abstract)) {
            $this->instances[$abstract] = $object;
        }
        
        return $object;
    }
}
```

## 🔧 核心组件设计

### 1. 应用服务层
```php
final class RecordingService
{
    public function __construct(
        private readonly RecordingRepositoryInterface $recordingRepository,
        private readonly PlatformRepositoryInterface $platformRepository,
        private readonly RecorderFactoryInterface $recorderFactory,
        private readonly SplitterFactoryInterface $splitterFactory,
        private readonly LoggerInterface $logger
    ) {}
    
    public function startRecording(
        RecordingRequest $request,
        ?Closure $progressCallback = null
    ): RecordingResponse {
        // 1. 验证请求
        $this->validateRequest($request);
        
        // 2. 创建录制实体
        $recording = Recording::create(
            id: RecordingId::generate(),
            url: StreamUrl::fromString($request->url),
            outputPath: OutputPath::fromString($request->outputPath),
            quality: Quality::fromString($request->quality),
            options: RecordingOptions::fromArray($request->options)
        );
        
        // 3. 获取平台信息
        $platform = $this->platformRepository->findByUrl($recording->getUrl());
        
        // 4. 创建录制器
        $recorder = $this->recorderFactory->create($platform, $recording);
        
        // 5. 启动录制
        $handle = $recorder->start($recording, $progressCallback);
        
        // 6. 保存录制记录
        $this->recordingRepository->save($recording);
        
        // 7. 返回响应
        return new RecordingResponse(
            id: $recording->getId(),
            status: $recording->getStatus(),
            handle: $handle
        );
    }
}
```

### 2. 领域实体
```php
final class Recording
{
    private RecordingStatus $status;
    private ?DateTime $startedAt = null;
    private ?DateTime $completedAt = null;
    private array $segments = [];
    
    private function __construct(
        private readonly RecordingId $id,
        private readonly StreamUrl $url,
        private readonly OutputPath $outputPath,
        private readonly Quality $quality,
        private readonly RecordingOptions $options
    ) {
        $this->status = RecordingStatus::PENDING;
    }
    
    public static function create(
        RecordingId $id,
        StreamUrl $url,
        OutputPath $outputPath,
        Quality $quality,
        RecordingOptions $options
    ): self {
        return new self($id, $url, $outputPath, $quality, $options);
    }
    
    public function start(): void
    {
        if (!$this->status->isPending()) {
            throw new InvalidRecordingStateException(
                'Cannot start recording in state: ' . $this->status->value
            );
        }
        
        $this->status = RecordingStatus::RECORDING;
        $this->startedAt = new DateTime();
    }
    
    public function complete(): void
    {
        if (!$this->status->isRecording()) {
            throw new InvalidRecordingStateException(
                'Cannot complete recording in state: ' . $this->status->value
            );
        }
        
        $this->status = RecordingStatus::COMPLETED;
        $this->completedAt = new DateTime();
    }
    
    public function addSegment(Segment $segment): void
    {
        $this->segments[] = $segment;
    }
    
    // Getters...
    public function getId(): RecordingId { return $this->id; }
    public function getUrl(): StreamUrl { return $this->url; }
    public function getStatus(): RecordingStatus { return $this->status; }
    // ...
}
```

## 🎯 命名规范

### 1. 类命名
- **实体**: `Recording`, `Platform`, `Segment`
- **值对象**: `StreamUrl`, `Duration`, `RecordingId`
- **服务**: `RecordingService`, `PlatformService`
- **接口**: `RecorderInterface`, `PlatformRepositoryInterface`
- **异常**: `DomainException`, `ValidationException`
- **DTO**: `RecordingRequest`, `RecordingResponse`

### 2. 方法命名
- **动作方法**: `startRecording()`, `stopRecording()`, `createPlatform()`
- **查询方法**: `findById()`, `getStatus()`, `isRecording()`
- **验证方法**: `validate()`, `isValid()`, `supports()`
- **工厂方法**: `create()`, `fromString()`, `generate()`

### 3. 文件组织
- **一个类一个文件**
- **文件名与类名一致**
- **目录结构反映命名空间**
- **接口以Interface后缀**

## 🚀 扩展性设计

### 1. 添加新平台支持
```php
// 1. 创建平台实体
class TikTokPlatform extends AbstractPlatform
{
    public function getName(): string { return 'tiktok'; }
    public function getDisplayName(): string { return 'TikTok Live'; }
    public function getSupportedDomains(): array { return ['*.tiktok.com']; }
    public function getReferer(): string { return 'https://www.tiktok.com/'; }
}

// 2. 注册到工厂
$platformFactory->register(
    '/tiktok\.com/',
    fn(StreamUrl $url) => new TikTokPlatform($url)
);
```

### 2. 添加新录制器
```php
// 1. 实现录制器接口
class CustomRecorder implements RecorderInterface
{
    public function start(Recording $recording, ?callable $progress = null): RecordHandle
    {
        // 自定义录制逻辑
    }
}

// 2. 注册到工厂
$recorderFactory->register(
    'custom',
    fn(Platform $platform, Recording $recording) => new CustomRecorder($platform, $recording)
);
```

### 3. 添加新分割策略
```php
// 1. 实现策略接口
class SmartSplittingStrategy implements SplittingStrategyInterface
{
    public function shouldSplit(Duration $duration, FileSize $size): bool
    {
        // 智能分割逻辑：基于内容分析
        return $this->detectSceneChange($duration) || $size->isGreaterThan($this->maxSize);
    }
}

// 2. 注册到工厂
$splitterFactory->register(
    'smart',
    fn(array $config) => new SmartSplittingStrategy($config)
);
```

## 📊 依赖关系

### 依赖方向控制
```
Presentation → Application → Domain ← Infrastructure
                              ↑
                            Shared
```

### 核心原则
- **高层模块不依赖低层模块，都依赖抽象**
- **抽象不依赖具体，具体依赖抽象**
- **依赖注入解决依赖关系**
- **接口定义在使用方，实现在提供方**

## 🧪 测试友好设计

### 1. 依赖注入便于Mock
```php
// 测试中可以轻松Mock依赖
$mockRepository = $this->createMock(RecordingRepositoryInterface::class);
$mockFactory = $this->createMock(RecorderFactoryInterface::class);

$service = new RecordingService($mockRepository, $platformRepo, $mockFactory);
```

### 2. 值对象便于断言
```php
// 值对象的不变性使测试更可靠
$url = StreamUrl::fromString('https://live.douyin.com/123');
$this->assertEquals('live.douyin.com', $url->getDomain());
$this->assertTrue($url->isValid());
```

### 3. 纯函数便于测试
```php
// 领域服务中的纯函数易于单元测试
$isValid = StreamValidationService::validateUrl($url);
$quality = QualitySelectionService::selectBestQuality($availableQualities, $preference);
```

这个新架构设计解决了现有项目的所有问题，提供了清晰的分层、统一的命名、完整的类型安全和良好的扩展性。