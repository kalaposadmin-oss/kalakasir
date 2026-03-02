<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ShiftController;
use PHPUnit\Framework\TestCase;

class ShiftControllerFindingsTest extends TestCase
{
    private function callBuildFindings(float $difference, string $note, int $salesCount, array $payments): array
    {
        $ctrl = new ShiftController();
        $ref = new \ReflectionMethod(ShiftController::class, 'buildCloseFindings');
        $ref->setAccessible(true);

        return $ref->invokeArgs($ctrl, [$difference, $note, $salesCount, $payments]);
    }

    public function test_returns_no_sales_when_no_sales()
    {
        $rows = $this->callBuildFindings(0.0, '', 0, []);
        $codes = array_column($rows, 'code');

        $this->assertContains('NO_SALES', $codes);
    }

    public function test_returns_difference_and_missing_note_when_difference_without_note()
    {
        $rows = $this->callBuildFindings(15000.0, '', 2, []);
        $codes = array_column($rows, 'code');

        $this->assertContains('CASH_DIFFERENCE', $codes);
        $this->assertContains('DIFFERENCE_NOTE_MISSING', $codes);
    }

    public function test_returns_cash_payment_present_when_cash_payments_exist()
    {
        $payments = [
            ['method' => 'cash', 'total' => 12000],
        ];

        $rows = $this->callBuildFindings(0.0, '', 3, $payments);
        $codes = array_column($rows, 'code');

        $this->assertContains('CASH_PAYMENT_PRESENT', $codes);
    }

    public function test_returns_no_issue_when_everything_ok()
    {
        $rows = $this->callBuildFindings(0.0, '', 1, []);
        $codes = array_column($rows, 'code');

        $this->assertContains('NO_ISSUE', $codes);
    }
}
