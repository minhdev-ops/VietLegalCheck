<?php

namespace Tests\Feature;

use App\Domain\RuleEngine\RuleSetResolver;
use App\Models\LegalRuleVersion;
use Carbon\Carbon;
use Tests\TestCase;

class DebugRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new \ReflectionClass(\Tests\Feature\AssessmentFlowTest::class));
    }

    public function test_x(): void
    {
        LegalRuleVersion::create([
            'rule_id' => 'x',
            'version' => 1,
        ]);
        fwrite(STDERR, 'count='.LegalRuleVersion::count()."\n");
        $this->assertTrue(true);
    }
}
