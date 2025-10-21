<?php

declare(strict_types=1);

namespace App\Leads;

interface LeadRequestTransformerInterface
{
    /**
     * Transform request data based on application source
     * Maps Homsters-specific field names to standard format
     *
     * @param array $data Raw request data
     * @param string $applicationName Source application (morizon, gratka, homsters)
     * @return array Transformed data in standard format
     */
    public function transformRequestData(array $data, string $applicationName): array;
}































