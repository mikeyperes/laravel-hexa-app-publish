<?php

namespace hexa_app_publish\Publishing\Pipeline\Jobs;

use hexa_app_publish\Publishing\Pipeline\Services\PipelineOperationExecutor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PreparePipelineOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $operationId,
        public array $payload
    ) {}

    public function handle(PipelineOperationExecutor $executor): void
    {
        $executor->runPrepare($this->operationId, $this->payload);
    }
}
