<?php
namespace LiveStream\Traits;

use LiveStream\Config\RecordingOptions;

trait HasConfig
{
    protected ?RecordingOptions $config = null;

    public function config(): RecordingOptions
    {
        return $this->config ??= RecordingOptions::make(options: $this->defaultOptions());
    }

    protected function defaultConfig(): array
    {
        return [
            
        ];
    }

    public function withConfig(RecordingOptions|array $config): static
    {
        $this->config = is_array($config) ? RecordingOptions::make(options: $config) : $config;
        return $this;
    }
    
}