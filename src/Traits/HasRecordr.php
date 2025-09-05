<?php
namespace LiveStream\Traits;

use LiveStream\Recording\Contracts\RecorderInterface;
use LiveStream\Recording\Drivers\PhpFFMpegRecorder;

trait HasRecordr
{
    protected ?RecorderInterface $recordr = null;

    public function recordr(): RecorderInterface
    {
        return $this->recordr ??= new PhpFFMpegRecorder();
    }

    public function withRecordr(RecorderInterface $recordr): static
    {
        $this->recordr = $recordr;
        return $this;
    }
}