# Docker 测试环境使用指南

## 🎯 概述

本项目已配置完整的 Docker 测试环境，包含了 PHP 8.3、FFmpeg 和所有必要的依赖，可以在容器中运行测试。

## 📦 环境组成

- **PHP 8.3 CLI**：最新的 PHP 运行环境
- **FFmpeg 5.1.6**：完整的 FFmpeg 多媒体处理工具
- **Composer**：PHP 依赖管理工具
- **所有项目依赖**：自动安装所有 composer 依赖

## 🚀 快速开始

### 1. 构建测试环境

```bash
docker-compose -f docker-compose.test.yml build
```

### 2. 运行测试

```bash
# 运行所有测试
docker-compose -f docker-compose.test.yml run --rm php-test ./vendor/bin/pest

# 运行特定测试文件
docker-compose -f docker-compose.test.yml run --rm php-test ./vendor/bin/pest tests/Unit/PendingRecorderTest.php

# 运行特定测试
docker-compose -f docker-compose.test.yml run --rm php-test ./vendor/bin/pest --filter="PendingRecorder 配置封装测试"
```

### 3. 进入容器调试

```bash
docker-compose -f docker-compose.test.yml run --rm php-test bash
```

## 📋 测试结果

### ✅ 成功的测试

1. **PendingRecorderTest.php**
   - ✅ PendingRecorder 配置封装测试
   - ✅ PhpFFmpegRecorder 执行测试（模拟）
   - ✅ 海外优化配置测试

2. **Feature/ExampleTest.php**
   - ✅ example 测试

### ❌ 需要注意的测试

1. **ExampleTest.php**
   - ❌ test get live data：依赖真实直播间，直播间未开播时会失败
   - ❌ test get live data html：同样依赖真实直播间

## 🔧 环境验证命令

在容器中可以使用以下命令验证环境：

```bash
# PHP 版本
php -v

# FFmpeg 版本和功能
ffmpeg -version

# Composer 版本
composer --version

# 查看已安装的 PHP 扩展
php -m

# 检查项目依赖
composer show
```

## 📁 项目结构

```
live-stream/
├── Dockerfile.test          # 测试环境 Dockerfile
├── docker-compose.test.yml  # Docker Compose 配置
├── run-tests.sh            # 一键测试脚本
├── tests/
│   ├── Feature/            # 功能测试
│   └── Unit/               # 单元测试
│       ├── ExampleTest.php         # 原始测试（依赖真实直播）
│       └── PendingRecorderTest.php # 模拟测试（推荐）
└── src/
    ├── Recording/
    │   ├── PendingRecorder.php     # 配置封装类
    │   └── Advanced/
    │       └── PhpFFmpegRecorder.php # 执行器类
    └── Utils/
        └── PathBuilder.php         # 路径构建工具
```

## 💡 测试策略

### 推荐的测试方式

1. **使用模拟测试**：`PendingRecorderTest.php`
   - 不依赖真实直播间
   - 测试核心逻辑和配置
   - 运行快速稳定

2. **避免依赖外部服务**：
   - 真实直播间状态不可控
   - 网络请求可能失败
   - 测试结果不稳定

### 测试覆盖范围

- ✅ **配置管理**：PendingRecorder 配置封装
- ✅ **路径构建**：输出路径生成和验证
- ✅ **命令构建**：FFmpeg 命令参数生成
- ✅ **海外优化**：海外平台特殊参数
- ✅ **格式支持**：支持的输出格式验证
- ⚠️  **实际执行**：需要真实流地址（可选）

## 🐛 常见问题

### 1. 容器构建失败

```bash
# 清理 Docker 缓存
docker system prune -a

# 重新构建
docker-compose -f docker-compose.test.yml build --no-cache
```

### 2. 依赖安装失败

```bash
# 进入容器手动安装
docker-compose -f docker-compose.test.yml run --rm php-test bash
composer install --no-interaction
```

### 3. FFmpeg 相关错误

```bash
# 验证 FFmpeg 安装
docker-compose -f docker-compose.test.yml run --rm php-test ffmpeg -version

# 检查 php-ffmpeg 扩展
docker-compose -f docker-compose.test.yml run --rm php-test composer show php-ffmpeg/php-ffmpeg
```

## 🎯 最佳实践

### 开发时

1. **优先编写模拟测试**：避免依赖外部服务
2. **测试配置逻辑**：重点测试业务逻辑而非执行
3. **使用依赖注入**：便于测试时注入模拟对象

### CI/CD 集成

```yaml
# GitHub Actions 示例
- name: Run Tests
  run: |
    docker-compose -f docker-compose.test.yml build
    docker-compose -f docker-compose.test.yml run --rm php-test ./vendor/bin/pest
```

## 📊 性能指标

- **环境构建时间**：~7-8 分钟（首次）
- **测试运行时间**：~0.03 秒（模拟测试）
- **容器启动时间**：~2-3 秒
- **内存占用**：~200MB

## 🎉 总结

Docker 测试环境已经完全配置好，包含：

1. ✅ **完整的 PHP 8.3 环境**
2. ✅ **FFmpeg 多媒体处理能力**
3. ✅ **所有项目依赖**
4. ✅ **模拟测试用例**
5. ✅ **一键运行脚本**

推荐使用 `PendingRecorderTest.php` 进行日常开发测试，它不依赖外部服务，运行稳定快速！

## 📝 使用示例

```bash
# 1. 构建环境（仅需一次）
docker-compose -f docker-compose.test.yml build

# 2. 运行推荐的测试
docker-compose -f docker-compose.test.yml run --rm php-test ./vendor/bin/pest tests/Unit/PendingRecorderTest.php

# 3. 查看测试结果
# PASS  Tests\Unit\PendingRecorderTest
# ✓ PendingRecorder 配置封装测试
# ✓ PhpFFmpegRecorder 执行测试（模拟）
# ✓ 海外优化配置测试
# Tests:    3 passed (22 assertions)
```

现在您可以放心地在 Docker 容器中测试 `live-stream` 项目了！🚀