#!/bin/bash

echo "🎥 检查录制文件状态"
echo "===================="

# 在容器中检查录制文件
docker-compose -f docker-compose.test.yml run --rm php-test bash -c '
echo "📁 检查录制目录..."

# 检查默认录制目录
if [ -d "./recordings" ]; then
    echo "✅ 默认录制目录存在: ./recordings"
    ls -la ./recordings/
else
    echo "❌ 默认录制目录不存在: ./recordings"
fi

echo ""

# 检查测试录制目录
if [ -d "/app/test-recordings" ]; then
    echo "✅ 测试录制目录存在: /app/test-recordings"
    ls -la /app/test-recordings/
else
    echo "❌ 测试录制目录不存在: /app/test-recordings"
fi

echo ""

# 查找所有可能的录制文件
echo "🔍 查找所有录制文件..."
find /app -name "*.mp4" -o -name "*.flv" -o -name "*.webm" -o -name "*.mkv" 2>/dev/null | head -20

echo ""

# 检查磁盘空间
echo "💾 磁盘空间使用情况:"
df -h /app

echo ""

# 检查最近的文件
echo "📅 最近创建的文件:"
find /app -type f -mtime -1 2>/dev/null | head -10
'

echo ""
echo "🏠 检查宿主机录制文件..."

# 检查宿主机的录制目录
if [ -d "./recordings" ]; then
    echo "✅ 宿主机录制目录存在: ./recordings"
    ls -la ./recordings/
else
    echo "❌ 宿主机录制目录不存在: ./recordings"
fi

if [ -d "./test-recordings" ]; then
    echo "✅ 宿主机测试目录存在: ./test-recordings"
    ls -la ./test-recordings/
else
    echo "❌ 宿主机测试目录不存在: ./test-recordings"
fi