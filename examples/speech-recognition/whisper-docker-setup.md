# Whisper 自建语音识别服务部署指南

## 🐳 Docker 部署方案

### 1. Dockerfile 配置

```dockerfile
# Dockerfile.whisper
FROM python:3.9-slim

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    ffmpeg \
    git \
    && rm -rf /var/lib/apt/lists/*

# 安装 Python 依赖
RUN pip install --no-cache-dir \
    openai-whisper \
    flask \
    gunicorn \
    torch \
    torchaudio

# 创建工作目录
WORKDIR /app

# 复制应用代码
COPY whisper-api/ .

# 预下载模型（可选，减少首次启动时间）
RUN python -c "import whisper; whisper.load_model('base')"

# 暴露端口
EXPOSE 5000

# 启动命令
CMD ["gunicorn", "--bind", "0.0.0.0:5000", "--workers", "2", "--timeout", "300", "app:app"]
```

### 2. Flask API 服务

```python
# whisper-api/app.py
from flask import Flask, request, jsonify
import whisper
import tempfile
import os
import logging

app = Flask(__name__)
logging.basicConfig(level=logging.INFO)

# 加载模型（启动时加载，避免每次请求重新加载）
model = whisper.load_model("base")  # 可选: tiny, base, small, medium, large

@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({"status": "healthy"})

@app.route('/transcribe', methods=['POST'])
def transcribe_audio():
    try:
        # 检查文件上传
        if 'audio' not in request.files:
            return jsonify({"error": "No audio file provided"}), 400
        
        audio_file = request.files['audio']
        if audio_file.filename == '':
            return jsonify({"error": "No file selected"}), 400
        
        # 获取参数
        language = request.form.get('language', None)  # 自动检测或指定语言
        task = request.form.get('task', 'transcribe')  # transcribe 或 translate
        
        # 保存临时文件
        with tempfile.NamedTemporaryFile(delete=False, suffix='.wav') as tmp_file:
            audio_file.save(tmp_file.name)
            
            # 执行转录
            result = model.transcribe(
                tmp_file.name,
                language=language,
                task=task,
                verbose=True
            )
            
            # 清理临时文件
            os.unlink(tmp_file.name)
            
            # 格式化返回结果
            response = {
                "text": result["text"],
                "language": result["language"],
                "segments": [
                    {
                        "id": seg["id"],
                        "start": seg["start"],
                        "end": seg["end"],
                        "text": seg["text"],
                        "confidence": seg.get("avg_logprob", 0.0)
                    }
                    for seg in result["segments"]
                ],
                "duration": result.get("duration", 0)
            }
            
            return jsonify(response)
            
    except Exception as e:
        app.logger.error(f"Transcription error: {str(e)}")
        return jsonify({"error": str(e)}), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
```

### 3. Docker Compose 配置

```yaml
# docker-compose.whisper.yml
version: '3.8'

services:
  whisper-api:
    build:
      context: .
      dockerfile: Dockerfile.whisper
    ports:
      - "5000:5000"
    volumes:
      - ./temp:/tmp  # 临时文件目录
    environment:
      - FLASK_ENV=production
    deploy:
      resources:
        limits:
          memory: 4G
        reservations:
          memory: 2G
    restart: unless-stopped
    
  # 可选：添加 Redis 缓存
  redis:
    image: redis:alpine
    ports:
      - "6379:6379"
    restart: unless-stopped
```

## 🚀 部署和使用

### 1. 构建和启动服务

```bash
# 构建镜像
docker-compose -f docker-compose.whisper.yml build

# 启动服务
docker-compose -f docker-compose.whisper.yml up -d

# 查看日志
docker-compose -f docker-compose.whisper.yml logs -f whisper-api
```

### 2. 健康检查

```bash
curl http://localhost:5000/health
```

### 3. 测试转录

```bash
curl -X POST \
  -F "audio=@test.wav" \
  -F "language=zh" \
  http://localhost:5000/transcribe
```

## 📊 性能优化建议

### 1. 硬件要求

| 模型大小 | 内存需求 | GPU需求 | 处理速度 | 准确率 |
|----------|----------|---------|----------|--------|
| tiny     | ~1GB     | 可选    | 32x实时  | 较低   |
| base     | ~1GB     | 可选    | 16x实时  | 中等   |
| small    | ~2GB     | 推荐    | 6x实时   | 良好   |
| medium   | ~5GB     | 推荐    | 2x实时   | 很好   |
| large    | ~10GB    | 必需    | 1x实时   | 最佳   |

### 2. 生产环境优化

```python
# 优化版 app.py 片段
import torch
from concurrent.futures import ThreadPoolExecutor
import redis

# GPU 加速（如果可用）
device = "cuda" if torch.cuda.is_available() else "cpu"
model = whisper.load_model("base", device=device)

# Redis 缓存
redis_client = redis.Redis(host='redis', port=6379, db=0)

# 线程池处理并发请求
executor = ThreadPoolExecutor(max_workers=4)

@app.route('/transcribe', methods=['POST'])
def transcribe_audio():
    # 添加缓存逻辑
    file_hash = hashlib.md5(audio_file.read()).hexdigest()
    audio_file.seek(0)  # 重置文件指针
    
    # 检查缓存
    cached_result = redis_client.get(f"transcribe:{file_hash}")
    if cached_result:
        return jsonify(json.loads(cached_result))
    
    # ... 转录逻辑 ...
    
    # 缓存结果（24小时）
    redis_client.setex(f"transcribe:{file_hash}", 86400, json.dumps(response))
    
    return jsonify(response)
```

## 🔧 监控和维护

### 1. 日志配置

```python
import logging
from logging.handlers import RotatingFileHandler

# 配置日志轮转
handler = RotatingFileHandler('whisper.log', maxBytes=10000000, backupCount=5)
handler.setFormatter(logging.Formatter(
    '%(asctime)s %(levelname)s: %(message)s [in %(pathname)s:%(lineno)d]'
))
app.logger.addHandler(handler)
app.logger.setLevel(logging.INFO)
```

### 2. 健康监控

```bash
# 监控脚本 monitor.sh
#!/bin/bash
while true; do
    if ! curl -f http://localhost:5000/health > /dev/null 2>&1; then
        echo "$(date): Whisper API is down, restarting..."
        docker-compose -f docker-compose.whisper.yml restart whisper-api
    fi
    sleep 30
done
```
