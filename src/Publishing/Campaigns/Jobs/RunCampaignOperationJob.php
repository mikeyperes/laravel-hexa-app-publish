<?php

namespace hexa_app_publish\Publishing\Campaigns\Jobs;

use hexa_app_publish\Publishing\Campaigns\Services\CampaignRunOperationExecutor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunCampaignOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $operationId,
        public array $payload
    ) {}

    public function handle(CampaignRunOperationExecutor $executor): void
    {
        $executor->run($this->operationId, $this->payload);
    }
}
