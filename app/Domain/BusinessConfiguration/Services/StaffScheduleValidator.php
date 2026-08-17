<?php

namespace App\Domain\BusinessConfiguration\Services;

use Illuminate\Validation\ValidationException;

class StaffScheduleValidator
{
    /** @param list<array<string, mixed>> $rules */
    public function validate(array $rules): void
    {
        $capacityRules = array_values(array_filter($rules, fn ($rule) => in_array($rule['kind'] ?? null, ['working', 'temporary_change'], true)));
        foreach ($capacityRules as $index => $left) {
            $this->assertComplete($left, $index);
            foreach (array_slice($capacityRules, $index + 1) as $right) {
                if (! $this->sameRecurrence($left, $right) || ! $this->overlaps($left, $right)) {
                    continue;
                }
                $message = ($left['location_id'] ?? null) !== ($right['location_id'] ?? null)
                    ? 'Staff cannot be scheduled at different locations during overlapping times.'
                    : 'Staff working intervals cannot overlap.';
                throw ValidationException::withMessages(['rules' => $message]);
            }
        }
    }

    /** @param array<string, mixed> $rule */
    private function assertComplete(array $rule, int $index): void
    {
        if (empty($rule['starts_at']) || empty($rule['ends_at']) || $rule['starts_at'] >= $rule['ends_at']) {
            throw ValidationException::withMessages(["rules.{$index}" => 'Working and temporary-change intervals need an end after their start.']);
        }
        if (($rule['kind'] ?? null) === 'working' && empty($rule['day_of_week'])) {
            throw ValidationException::withMessages(["rules.{$index}.day_of_week" => 'Weekly working intervals need a day of week.']);
        }
    }

    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    private function sameRecurrence(array $left, array $right): bool
    {
        if (($left['kind'] ?? null) === 'working' && ($right['kind'] ?? null) === 'working') {
            return ($left['day_of_week'] ?? null) === ($right['day_of_week'] ?? null);
        }
        $leftStart = $left['starts_on'] ?? null;
        $leftEnd = $left['ends_on'] ?? $leftStart;
        $rightStart = $right['starts_on'] ?? null;
        $rightEnd = $right['ends_on'] ?? $rightStart;

        return $leftStart && $rightStart && $leftStart <= $rightEnd && $rightStart <= $leftEnd;
    }

    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    private function overlaps(array $left, array $right): bool
    {
        return $left['starts_at'] < $right['ends_at'] && $right['starts_at'] < $left['ends_at'];
    }
}
