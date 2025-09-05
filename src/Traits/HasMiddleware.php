<?php 
namespace LiveStream\Traits;

use LiveStream\Pipeline;

trait HasMiddleware
{
    protected Pipeline $middlewarePipeline;

        /**
     * Access the middleware pipeline
     */
    public function middleware(): Pipeline
    {
        return $this->middlewarePipeline ??= new Pipeline;
    }
}