<?php

namespace Tests\Unit;

use App\Domain\Enums\SystemRole;
use PHPUnit\Framework\TestCase;

/**
 * Enum hệ thống ánh xạ đúng vai trò (SRS 5.2).
 */
class ExampleTest extends TestCase
{
    public function test_system_roles_have_distinct_values(): void
    {
        $values = array_map(fn (SystemRole $role) => $role->value, SystemRole::cases());

        $this->assertSame(count($values), count(array_unique($values)));
        $this->assertContains('SYS_ADMIN', $values);
        $this->assertContains('LEGAL_ADMIN', $values);
        $this->assertContains('LEGAL_EXPERT', $values);
        $this->assertContains('USER', $values);
    }
}
