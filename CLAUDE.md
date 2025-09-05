# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Development Commands
- **Run tests**: `composer test` or `vendor/bin/pest`
- **Run static analysis**: `composer stan`
- **Check code style**: `composer cs-check`
- **Fix code style**: `composer cs-fix` or `composer fix`
- **Run tests with coverage**: `composer test-coverage`
- **Install dependencies**: `composer install`

### Testing Live Stream Recording
- **Basic recording test**: `php test-recording.php`
- **Realtime split recording**: `php test-realtime-split-recording.php`
- **Smart split recording**: `php test-smart-split-recording.php`
- **Native split recording**: `php test-native-split-recording.php`
- **Elegant split recording**: `php test-elegant-split-recording.php`

## Architecture Overview

This is a PHP library for extracting live streaming data and recording streams from various platforms (Douyin/TikTok, Kuaishou, Bilibili, etc.). The codebase follows a modular, extensible architecture:

### Core Components

1. **Platform Abstraction Layer** (`src/Platforms/`)
   - Each platform (Douyin, TikTok, etc.) has its own implementation
   - Implements `PlatformInterface` for consistency
   - Uses Saloon HTTP client for API requests
   - Handles platform-specific authentication and anti-crawler mechanisms

2. **Recording System** (`src/Recording/`)
   - **PendingRecorder**: Main entry point for recording configuration
   - **RecordingPipeline**: Orchestrates the recording process through pipes
   - **Recorder Drivers**: Different recording implementations (NativeFFmpegRecorder, PhpFFmpegRecorder)
   - **Splitter System**: Handles video splitting strategies (time-based, size-based, hybrid)
     - SmartRealtimeSplitter: Intelligent real-time splitting
     - RealtimeSplitter: Basic real-time splitting
     - VideoSplitter: Post-processing splitting

3. **Configuration System** (`src/Config/`)
   - RecordingOptions: Recording configuration (quality, format, path)
   - StreamConfig: Stream-specific configuration

4. **Value Objects and Contracts**
   - RoomInfo: Abstraction for live room information
   - SegmentInfo: Information about video segments
   - Various interfaces ensuring consistency across implementations

### Key Design Patterns

- **Factory Pattern**: PlatformFactory for creating platform instances
- **Pipeline Pattern**: RecordingPipeline for processing recording steps
- **Strategy Pattern**: Different splitting strategies (TimeSplitStrategy, SizeSplitStrategy, HybridSplitStrategy)
- **Driver Pattern**: Multiple recorder implementations

### Dependencies

- **php-ffmpeg/php-ffmpeg**: FFmpeg PHP wrapper for video processing
- **symfony/process**: Process management for running FFmpeg commands
- **saloonphp/saloon**: HTTP client for API requests
- **monolog/monolog**: Logging
- **pestphp/pest**: Testing framework

### Important Considerations

- The library handles anti-crawler mechanisms (X-Bogus signatures for Douyin)
- Supports proxy configuration for all HTTP requests
- Real-time recording with on-the-fly splitting capabilities
- Extensible architecture allows easy addition of new platforms