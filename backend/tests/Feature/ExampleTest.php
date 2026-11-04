<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Cổng API phản hồi đúng (SRS 6.2): envelope JSON lỗi + request_id.
 */
class ExampleTest extends TestCase
{
    public function test_api_root_responds(): void
    {
        $response = $this->get('/api/v1/legal/documents');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'as_of_date',
            'meta' => ['page', 'last_page', 'total'],
        ]);
    }

    public function test_api_errors_carry_request_id(): void
    {
        $response = $this->get('/api/v1/legal/documents/no-such-id');

        $response->assertNotFound();
        $response->assertJsonStructure(['error' => ['code', 'message', 'details', 'request_id']]);
    }
}
