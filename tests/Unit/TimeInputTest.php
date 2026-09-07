<?php

namespace Tests\Unit;

use App\Support\TimeInput;
use PHPUnit\Framework\TestCase;

class TimeInputTest extends TestCase
{
    public function test_normalizes_am_pm_and_short_forms(): void
    {
        $this->assertSame('17:30', TimeInput::normalize('5:30 PM'));
        $this->assertSame('17:30', TimeInput::normalize('05:30pm'));
        $this->assertSame('08:00', TimeInput::normalize('8:00 AM'));
        $this->assertSame('08:00', TimeInput::normalize('8:00'));
        $this->assertSame('00:15', TimeInput::normalize('12:15 AM'));
        $this->assertSame('12:00', TimeInput::normalize('12:00 PM'));
    }

    public function test_passes_through_empty_and_unparseable(): void
    {
        $this->assertNull(TimeInput::normalize(null));
        $this->assertSame('', TimeInput::normalize('  '));
        $this->assertSame('25:99', TimeInput::normalize('25:99'));
        $this->assertSame('bukan jam', TimeInput::normalize('bukan jam'));
    }
}
