# 发布到 Packagist 指南

## 准备工作

### 1. 更新包信息

在发布之前，请更新以下文件中的信息：

#### composer.json
```json
{
    "name": "your-vendor/live-stream",
    "description": "A PHP library for live streaming platform data extraction and stream URL parsing",
    "authors": [
        {
            "name": "Your Name",
            "email": "your.email@example.com",
            "homepage": "https://github.com/your-username"
        }
    ]
}
```

#### README.md
- 更新安装命令中的包名
- 更新GitHub链接
- 更新作者信息

### 2. 版本号管理

使用语义化版本号：
- `1.0.0` - 初始版本
- `1.0.1` - 补丁版本（bug修复）
- `1.1.0` - 次要版本（新功能）
- `2.0.0` - 主要版本（破坏性变更）

## 发布步骤

### 1. 创建 GitHub 仓库

```bash
# 初始化 Git 仓库
git init
git add .
git commit -m "Initial commit"

# 创建 GitHub 仓库并推送
git remote add origin https://github.com/your-username/live-stream.git
git branch -M main
git push -u origin main
```

### 2. 创建 Git 标签

```bash
# 创建版本标签
git tag v1.0.0
git push origin v1.0.0
```

### 3. 注册 Packagist 账户

1. 访问 [Packagist.org](https://packagist.org)
2. 使用 GitHub 账户登录
3. 点击 "Submit Package"

### 4. 提交包到 Packagist

1. 在 Packagist 提交页面输入 GitHub 仓库 URL
2. 点击 "Check" 验证包信息
3. 点击 "Submit" 提交包

### 5. 配置自动更新

1. 在 Packagist 包页面点击 "Settings"
2. 启用 "Auto-update"
3. 配置 GitHub Webhook（可选）

## 本地开发测试

### 1. 安装依赖

```bash
composer install
```

### 2. 运行测试

```bash
# 运行单元测试
composer test

# 运行代码质量检查
composer cs-check
composer stan
```

### 3. 本地测试

```bash
# 运行本地测试脚本
php test-local.php
```

## 持续集成

### GitHub Actions 配置

创建 `.github/workflows/ci.yml`：

```yaml
name: CI

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, xml, ctype, iconv, intl, pcre, session, dom, filter, gd, iconv, json, mbstring, pdo, phar, reflection, simplexml, spl, standard, tokenizer, xml, xmlreader, xmlwriter, zip, zlib
        coverage: xdebug
        
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
        
    - name: Run tests
      run: composer test
        
    - name: Run code quality checks
      run: |
        composer cs-check
        composer stan
```

## 发布检查清单

- [ ] 更新 `composer.json` 中的包信息
- [ ] 更新 `README.md` 中的文档
- [ ] 运行所有测试并确保通过
- [ ] 检查代码质量（PHPStan, PHP CS Fixer）
- [ ] 创建 Git 标签
- [ ] 推送到 GitHub
- [ ] 在 Packagist 提交包
- [ ] 配置自动更新

## 维护指南

### 更新版本

1. 更新 `composer.json` 中的版本号
2. 更新 `README.md` 中的更新日志
3. 创建新的 Git 标签
4. 推送到 GitHub

### 处理 Issues

1. 及时回复用户反馈
2. 修复 bug 并发布补丁版本
3. 考虑用户的功能请求

### 安全更新

1. 定期检查依赖的安全漏洞
2. 及时更新依赖版本
3. 发布安全补丁

## 常见问题

### Q: 包提交失败怎么办？
A: 检查 composer.json 格式是否正确，确保所有必需字段都已填写。

### Q: 如何更新已发布的包？
A: 创建新的 Git 标签并推送到 GitHub，Packagist 会自动检测并更新。

### Q: 如何删除包？
A: 在 Packagist 包页面的设置中可以删除包，但建议先标记为废弃。

## 相关资源

- [Packagist 文档](https://packagist.org/about)
- [Composer 文档](https://getcomposer.org/doc/)
- [语义化版本](https://semver.org/)
- [PHP-FIG PSR 标准](https://www.php-fig.org/psr/) 