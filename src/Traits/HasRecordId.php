<?php

namespace LiveStream\Traits;

trait HasRecordId
{
    protected ?string $recordId = null;
    
    public function recordId(): string
    {
        return $this->recordId ??= uniqid('phpffmpeg_', true);
    }

    public function withRecordId(string $recordId): static
    {
        $this->recordId = $recordId;
        return $this;
    }
}