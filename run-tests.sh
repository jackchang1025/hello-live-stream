#!/bin/bash

# 运行测试的脚本

echo "🚀 开始运行 live-stream 项目测试"
echo "================================="

# 检查 Docker 是否可用
if ! command -v docker &> /dev/null; then
    echo "❌ Docker 未安装，请先安装 Docker"
    exit 1
fi

# 检查 Docker Compose 是否可用
if ! command -v docker-compose &> /dev/null; then
    echo "⚠️  Docker Compose 未找到，尝试使用 docker compose"
    DOCKER_COMPOSE="docker compose"
else
    DOCKER_COMPOSE="docker-compose"
fi

echo "📦 构建测试环境..."
$DOCKER_COMPOSE -f docker-compose.test.yml build

if [ $? -ne 0 ]; then
    echo "❌ Docker 构建失败"
    exit 1
fi

echo "✅ 测试环境构建完成"

echo "🔧 验证 FFmpeg 安装..."
$DOCKER_COMPOSE -f docker-compose.test.yml run --rm php-test ffmpeg -version

echo "🧪 运行 Composer 安装..."
$DOCKER_COMPOSE -f docker-compose.test.yml run --rm php-test composer install --no-interaction

echo "🧪 运行测试..."
$DOCKER_COMPOSE -f docker-compose.test.yml run --rm php-test ./vendor/bin/pest

echo "🎉 测试完成！"