<?php

namespace Tests\Unit\Loans;

use App\Enums\InterestMethod;
use App\Services\Loans\InterestCalculationService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Reine Rechen-Tests fuer dayCountFactor und interestForPeriod
 * (keine Datenbank). Zeitraumkonvention: [from, to), from inklusiv,
 * to exklusiv. Alle Erwartungswerte sind von Hand vorgerechnet.
 */
class EngineInterestTest extends TestCase
{
    private InterestCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InterestCalculationService;
    }

    public function test_day_count_factor_act_365(): void
    {
        // [01.01.2026, 01.02.2026) = 31 Tage; 31/365 = 0,08493150684... -> Skala 10: 0.0849315068
        $factor = $this->service->dayCountFactor(InterestMethod::Act365, Carbon::parse('2026-01-01'), Carbon::parse('2026-02-01'));
        $this->assertSame('0.0849315068', $factor);
    }

    public function test_day_count_factor_act_360(): void
    {
        // 31 Tage; 31/360 = 0,08611111111... -> 0.0861111111
        $factor = $this->service->dayCountFactor(InterestMethod::Act360, Carbon::parse('2026-01-01'), Carbon::parse('2026-02-01'));
        $this->assertSame('0.0861111111', $factor);
    }

    public function test_day_count_factor_thirty_360_full_month(): void
    {
        // 30/360: [01.01., 01.02.) = 360*0 + 30*1 + (1-1) = 30 Tage; 30/360 = 0.0833333333
        $factor = $this->service->dayCountFactor(InterestMethod::Thirty360, Carbon::parse('2026-01-01'), Carbon::parse('2026-02-01'));
        $this->assertSame('0.0833333333', $factor);
    }

    public function test_day_count_factor_thirty_360_start_day_31(): void
    {
        // US-Regel: Starttag 31 -> 30. [31.01., 28.02.) = 30*1 + (28-30) = 28 Tage; 28/360 = 0.0777777777
        $factor = $this->service->dayCountFactor(InterestMethod::Thirty360, Carbon::parse('2026-01-31'), Carbon::parse('2026-02-28'));
        $this->assertSame('0.0777777777', $factor);
    }

    public function test_day_count_factor_thirty_360_end_day_31_with_start_30(): void
    {
        // Endtag 31 -> 30 nur wenn Starttag 30/31: [30.01., 31.03.) = 30*2 + (30-30) = 60 Tage
        $factor = $this->service->dayCountFactor(InterestMethod::Thirty360, Carbon::parse('2026-01-30'), Carbon::parse('2026-03-31'));
        $this->assertSame('0.1666666666', $factor);
    }

    public function test_day_count_factor_thirty_360_end_day_31_with_mid_month_start(): void
    {
        // Starttag 15: Endtag 31 bleibt 31. [15.01., 31.01.) = 31-15 = 16 Tage; 16/360 = 0.0444444444
        $factor = $this->service->dayCountFactor(InterestMethod::Thirty360, Carbon::parse('2026-01-15'), Carbon::parse('2026-01-31'));
        $this->assertSame('0.0444444444', $factor);
    }

    public function test_day_count_factor_act_act_full_leap_year(): void
    {
        // ACT/ACT (ISDA), Schaltjahr 2024: [01.01.2024, 01.01.2025) = 366/366 = 1
        $factor = $this->service->dayCountFactor(InterestMethod::ActAct, Carbon::parse('2024-01-01'), Carbon::parse('2025-01-01'));
        $this->assertSame('1.0000000000', $factor);
    }

    public function test_day_count_factor_act_act_across_year_boundary(): void
    {
        // [01.12.2024, 01.02.2025): Dez 2024 = 31/366 = 0.0846994535 (Schaltjahr),
        // Jan 2025 = 31/365 = 0.0849315068; Summe = 0.1696309603
        $factor = $this->service->dayCountFactor(InterestMethod::ActAct, Carbon::parse('2024-12-01'), Carbon::parse('2025-02-01'));
        $this->assertSame('0.1696309603', $factor);
    }

    public function test_day_count_factor_empty_or_negative_period_is_zero(): void
    {
        $this->assertSame('0.0000000000', $this->service->dayCountFactor(InterestMethod::Act365, Carbon::parse('2026-01-01'), Carbon::parse('2026-01-01')));
        $this->assertSame('0.0000000000', $this->service->dayCountFactor(InterestMethod::Act365, Carbon::parse('2026-02-01'), Carbon::parse('2026-01-01')));
    }

    public function test_interest_for_period_act_365_vs_thirty_360(): void
    {
        // 100.000,00 EUR, 6 % p. a., Januar 2026 [01.01., 01.02.), 31 Tage:
        // ACT/365: 100000 * 0,06 * 31/365 = 186000/365 = 509,5890410958... -> gerundet 509,59
        $act = $this->service->interestForPeriod('100000.00', '6.000000', InterestMethod::Act365, Carbon::parse('2026-01-01'), Carbon::parse('2026-02-01'));
        $this->assertSame('509.59', Money::round($act, 2));

        // 30/360: 100000 * 0,06 * 30/360 = 500,00 exakt
        $thirty = $this->service->interestForPeriod('100000.00', '6.000000', InterestMethod::Thirty360, Carbon::parse('2026-01-01'), Carbon::parse('2026-02-01'));
        $this->assertSame('500.00', Money::round($thirty, 2));

        // ACT/360: 100000 * 0,06 * 31/360 = 186000/360 = 516,6666... -> 516,67
        $act360 = $this->service->interestForPeriod('100000.00', '6.000000', InterestMethod::Act360, Carbon::parse('2026-01-01'), Carbon::parse('2026-02-01'));
        $this->assertSame('516.67', Money::round($act360, 2));
    }

    public function test_interest_for_period_act_act_leap_year(): void
    {
        // Schaltjahr 2024, ACT/ACT: 100000 * 5 % * 366/366 = 5000,00
        $interest = $this->service->interestForPeriod('100000.00', '5.000000', InterestMethod::ActAct, Carbon::parse('2024-01-01'), Carbon::parse('2025-01-01'));
        $this->assertSame('5000.00', Money::round($interest, 2));
    }

    public function test_interest_for_period_is_unrounded_scale_10(): void
    {
        // Rueckgabe ungerundet mit Skala 10: 6000 * 0.0849315068 = 509.5890408000
        $interest = $this->service->interestForPeriod('100000.00', '6.000000', InterestMethod::Act365, Carbon::parse('2026-01-01'), Carbon::parse('2026-02-01'));
        $this->assertSame('509.5890408000', $interest);
    }

    public function test_zero_rate_yields_zero_interest(): void
    {
        // Zinslos (Rate 0): 0,00
        $interest = $this->service->interestForPeriod('100000.00', '0.000000', InterestMethod::Act365, Carbon::parse('2026-01-01'), Carbon::parse('2026-02-01'));
        $this->assertSame('0.00', Money::round($interest, 2));
    }
}
