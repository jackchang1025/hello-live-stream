<?php

declare(strict_types=1);

namespace LiveStream\Recording;

use Closure;
use LiveStream\Traits\HasMiddleware;
use LiveStream\Traits\HasRecordr;
use LiveStream\Traits\HasConfig;
use LiveStream\Contracts\PlatformInterface;
use LiveStream\Recording\Pipes\ValidateOptionsPipe;

class RecordrConnector
{
    use HasMiddleware;
    use HasRecordr;
    use HasConfig;
    
    public function handle(PlatformInterface $platform, ?Closure $progress = null): mixed
    {
        $pending = new PendingRecorder($this, $platform);

        $this->middleware()->pipe(new ValidateOptionsPipe());

        $result = $this->middleware()
            ->send($pending)
            ->then(function (PendingRecorder $pendingRecorder) use ($progress) {
                return $pendingRecorder->recordrConnector()->recordr()->start(pendingRecorder: $pendingRecorder, progress: $progress);
            });

        return $result;
    }
}
