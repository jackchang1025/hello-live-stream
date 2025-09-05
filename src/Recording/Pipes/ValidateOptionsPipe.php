<?php

namespace LiveStream\Recording\Pipes;

use LiveStream\Exceptions\RecordingException;
use LiveStream\Recording\PendingRecorder;

class ValidateOptionsPipe
{
    public function handle(PendingRecorder $pendingRecorder,\Closure $next): mixed
    {

        //判断是否开播
        if (!$pendingRecorder->getRoomInfo()->isLive()) {
            throw RecordingException::streamNotLive(
                anchorName: $pendingRecorder->getRoomInfo()->getAnchorName()
            );
        }

        return $next($pendingRecorder);
    }
}