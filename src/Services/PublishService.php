<?php

namespace hexa_app_publish\Services;

use hexa_core\Services\GenericService;

class PublishService
{
    protected GenericService $generic;

    /**
     * @param GenericService $generic Core generic service for shared utilities.
     */
    public function __construct(GenericService $generic)
    {
        $this->generic = $generic;
    }

    /**
     * Get the current app version.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return config('hws-publish.version', '0.0.0');
    }
}
