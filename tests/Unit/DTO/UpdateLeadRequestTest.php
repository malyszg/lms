<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\UpdateLeadRequest;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UpdateLeadRequest
 */
class UpdateLeadRequestTest extends TestCase
{
    public function testUpdateLeadRequestConstructor(): void
    {
        $request = new UpdateLeadRequest(status: 'contacted');
        
        $this->assertEquals('contacted', $request->status);
    }

    public function testUpdateLeadRequestWithDifferentStatuses(): void
    {
        $statuses = ['new', 'contacted', 'qualified', 'converted', 'rejected'];
        
        foreach ($statuses as $status) {
            $request = new UpdateLeadRequest(status: $status);
            
            $this->assertEquals($status, $request->status);
        }
    }

    public function testUpdateLeadRequestWithEmptyStatus(): void
    {
        $request = new UpdateLeadRequest(status: '');
        
        $this->assertEquals('', $request->status);
    }

    public function testUpdateLeadRequestWithLongStatus(): void
    {
        $longStatus = 'very_long_status_name_that_should_still_work';
        
        $request = new UpdateLeadRequest(status: $longStatus);
        
        $this->assertEquals($longStatus, $request->status);
    }

    public function testUpdateLeadRequestWithSpecialCharacters(): void
    {
        $specialStatus = 'status-with-special_chars.and+symbols';
        
        $request = new UpdateLeadRequest(status: $specialStatus);
        
        $this->assertEquals($specialStatus, $request->status);
    }

    public function testUpdateLeadRequestWithNumericStatus(): void
    {
        $numericStatus = '123';
        
        $request = new UpdateLeadRequest(status: $numericStatus);
        
        $this->assertEquals($numericStatus, $request->status);
    }

    public function testUpdateLeadRequestWithMixedCaseStatus(): void
    {
        $mixedCaseStatus = 'CoNtAcTeD';
        
        $request = new UpdateLeadRequest(status: $mixedCaseStatus);
        
        $this->assertEquals($mixedCaseStatus, $request->status);
    }
}
