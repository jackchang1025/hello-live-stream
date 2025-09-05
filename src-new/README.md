# 🎥 多平台直播录制系统 - 新架构版本

基于**领域驱动设计(DDD)**和**Clean Architecture**模式的现代化PHP直播录制系统。

## ✨ 特性

- 🏗️ **现代架构**: 基于DDD和Clean Architecture的分层设计
- 🔒 **类型安全**: 完整的PHP 8.1+类型提示和严格模式
- 🧪 **测试友好**: 依赖注入让单元测试变得简单
- 📦 **高内聚低耦合**: 清晰的职责分离和依赖管理
- 🔌 **易扩展**: 通过工厂模式轻松添加新平台支持
- 🎯 **SOLID原则**: 遵循面向对象设计的最佳实践

## 📋 支持平台

- ✅ 抖音直播 (live.douyin.com)
- ✅ 快手直播 (live.kuaishou.com) 
- ✅ B站直播 (live.bilibili.com)
- ✅ 虎牙直播 (www.huya.com)
- ✅ 斗鱼直播 (www.douyu.com)
- 🔧 更多平台可通过扩展轻松添加

## 🚀 快速开始

### 安装依赖

```bash
composer install
```

### 基础使用

```php
<?php
use LiveStream\Application\Services\RecordingService;
use LiveStream\Application\DTOs\RecordingRequest;

// 1. 初始化容器
$container = require 'bootstrap.php';

// 2. 获取录制服务
$service = $container->make(RecordingService::class);

// 3. 创建录制请求
$request = new RecordingRequest(
    url: 'https://live.douyin.com/123456',
    outputPath: './recordings/test.mp4',
    quality: 'origin',
    format: 'mp4',
    enableSplitting: true,
    splitDuration: 300
);

// 4. 启动录制
$response = $service->startRecording($request, $progressCallback);

if ($response->isSuccessful()) {
    echo "录制启动成功! ID: " . $response->id->getValue();
} else {
    echo "录制失败: " . $response->message;
}
```

### 命令行使用

```bash
# 启动录制
php cli.php start https://live.douyin.com/123456 ./output.mp4 --quality high

# 查看状态
php cli.php status rec_20241208_123456_abcd1234

# 停止录制
php cli.php stop rec_20241208_123456_abcd1234

# 列出所有录制
php cli.php list

# 查看帮助
php cli.php help
```

## 🏗️ 架构设计

### 分层架构

```
┌─────────────────────────────────────┐
│        表现层 (Presentation)        │  ← CLI/HTTP接口
├─────────────────────────────────────┤
│         应用层 (Application)        │  ← 业务流程协调
├─────────────────────────────────────┤
│          领域层 (Domain)            │  ← 核心业务逻辑
├─────────────────────────────────────┤
│       基础设施层 (Infrastructure)   │  ← 外部依赖实现
├─────────────────────────────────────┤
│         共享内核 (Shared)           │  ← 通用组件
└─────────────────────────────────────┘
```

### 核心组件

- **值对象**: `StreamUrl`, `Duration`, `RecordingId` - 封装业务概念
- **实体**: `Recording`, `Platform`, `Segment` - 业务对象
- **应用服务**: `RecordingService` - 业务流程协调
- **仓储**: 数据访问抽象层
- **工厂**: 对象创建和依赖管理

## 🎨 设计模式

### 1. 工厂模式
```php
// 平台工厂 - 根据URL创建对应平台
$platform = $platformFactory->createPlatform($url);

// 录制器工厂 - 根据平台和配置创建录制器
$recorder = $recorderFactory->create($platform, $recording);
```

### 2. 仓储模式
```php
// 录制数据访问
$recording = $recordingRepository->findById($id);
$recordingRepository->save($recording);

// 平台数据访问
$platform = $platformRepository->findByUrl($url);
```

### 3. 值对象模式
```php
// 类型安全的值对象
$url = StreamUrl::fromString('https://live.douyin.com/123');
$duration = Duration::fromMinutes(30);
$id = RecordingId::generate();
```

## 🧪 测试

### 运行测试
```bash
# 运行所有测试
composer test

# 运行特定测试组
./vendor/bin/pest --group=unit
./vendor/bin/pest --group=integration

# 生成覆盖率报告
composer test-coverage
```

### 测试结构
```
tests-new/
├── Unit/              # 单元测试
│   ├── Domain/        # 领域层测试
│   ├── Application/   # 应用层测试
│   └── Shared/        # 共享组件测试
├── Integration/       # 集成测试
└── Feature/           # 功能测试
```

## 🔧 扩展指南

### 添加新平台支持

```php
// 1. 创建平台实现
class TikTokPlatform implements PlatformInterface
{
    public function getName(): string { return 'tiktok'; }
    public function supports(string $url): bool { /* 实现逻辑 */ }
    // ... 其他方法
}

// 2. 注册到工厂
$platformFactory->register(
    '/tiktok\.com/',
    fn($url) => new TikTokPlatform($url)
);
```

### 添加新录制器

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
$recorderFactory->register('custom', fn($platform, $recording) => 
    new CustomRecorder($platform, $recording)
);
```

## 📊 代码质量

### 静态分析
```bash
# PHPStan 分析
composer stan

# 代码风格检查
composer cs-check

# 自动修复代码风格
composer cs-fix

# 运行所有质量检查
composer quality
```

### 质量指标
- ✅ PSR-12 代码风格标准
- ✅ PHPStan Level 8 静态分析
- ✅ 100% 类型提示覆盖
- ✅ 完整的单元测试覆盖

## 📚 文档

### API文档
所有公共API都有完整的PHPDoc注释，包括：
- 参数类型和说明
- 返回值类型
- 异常说明
- 使用示例

### 架构文档
- [ARCHITECTURE-DESIGN.md](ARCHITECTURE-DESIGN.md) - 详细的架构设计文档
- [REFACTORING-SUMMARY.md](../REFACTORING-SUMMARY.md) - 重构总结

## 🤝 贡献指南

1. Fork 项目
2. 创建功能分支 (`git checkout -b feature/new-platform`)
3. 编写测试并确保通过
4. 提交更改 (`git commit -am 'Add new platform support'`)
5. 推送分支 (`git push origin feature/new-platform`)
6. 创建 Pull Request

### 开发规范
- 遵循 PSR-12 代码风格
- 为新功能编写测试
- 更新相关文档
- 使用语义化提交信息

## 📄 许可证

MIT License - 查看 [LICENSE](LICENSE) 文件了解详情。

## 🙏 致谢

感谢所有贡献者和开源社区的支持。

---

**注意**: 这是重构后的新架构版本，具有更好的可维护性和扩展性。原版本代码保留在 `src/` 目录中作为参考。