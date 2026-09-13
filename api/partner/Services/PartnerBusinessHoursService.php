<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Api\Core\ApiException;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerBusinessHoursRepository;
use PDO;
use Throwable;

final class PartnerBusinessHoursService
{
    private const DAYS = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

    public function __construct(private PDO $pdo, private PartnerBusinessHoursRepository $hours, private PartnerSetupService $setup) {}

    public function get(PartnerContext $context): array
    {
        $this->applicable($context);
        $grouped = [];
        foreach ($this->hours->rows($context) as $row) {
            $this->assertStoredRow($row);
            $grouped[(int) $row['day_of_week']][] = $row;
        }
        $days = [];
        foreach (self::DAYS as $index => $name) {
            if (!isset($grouped[$index])) continue;
            $rows = $grouped[$index];
            $first = $rows[0];
            $closed = (bool) $first['is_closed'];
            $allDay = (bool) $first['is_24_hours'];
            if (($closed || $allDay) && count($rows) !== 1) $this->malformed();
            $slots = [];
            if (!$closed && !$allDay) foreach ($rows as $row) $slots[] = [
                'opensAt' => substr((string) $row['opens_at'], 0, 5),
                'closesAt' => substr((string) $row['closes_at'], 0, 5),
                'overnight' => (bool) $row['is_overnight'],
            ];
            $days[] = ['day' => $name, 'closed' => $closed, 'open24Hours' => $allDay, 'slots' => $slots];
        }
        $setup = $this->setup->load($context);
        return ['timezone' => 'Asia/Kolkata', 'days' => $days, 'step' => $this->step($setup), 'setup' => $setup];
    }

    public function update(PartnerContext $context, array $input, ?callable $beforeCommit = null): array
    {
        $this->applicable($context);
        $days = $this->validate($input['days'] ?? null);
        $this->pdo->beginTransaction();
        try {
            $this->hours->replace($context, $days);
            $summary = $this->setup->markHoursComplete($context);
            if ($beforeCommit) $beforeCommit($this->auditMetadata($days));
            $this->pdo->commit();
            $result = $this->get($context);
            $result['setup'] = $summary;
            $result['step'] = $this->step($summary);
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    private function validate(mixed $payload): array
    {
        if (!is_array($payload) || count($payload) !== 7) return $this->invalid(['days' => 'Configure all seven weekdays.']);
        $normalized = [];
        $seen = [];
        $openDays = 0;
        $fields = [];
        foreach ($payload as $position => $day) {
            if (!is_array($day)) { $fields["days.$position"] = 'Each day must be an object.'; continue; }
            $unknown = array_diff(array_keys($day), ['day','closed','open24Hours','slots']);
            $name = strtolower(trim((string) ($day['day'] ?? '')));
            if ($unknown) $fields["days.$position"] = 'Unsupported day fields: ' . implode(', ', $unknown) . '.';
            if (!in_array($name, self::DAYS, true)) { $fields["days.$position.day"] = 'Choose a valid weekday.'; continue; }
            if (isset($seen[$name])) { $fields["days.$position.day"] = 'Each weekday may appear only once.'; continue; }
            $seen[$name] = true;
            if (!is_bool($day['closed'] ?? null) || !is_bool($day['open24Hours'] ?? null) || !is_array($day['slots'] ?? null)) {
                $fields["days.$position"] = 'Closed, Open 24 Hours, and slots must use the documented types.';
                continue;
            }
            $closed = $day['closed']; $allDay = $day['open24Hours']; $slots = $day['slots'];
            if ($closed && ($allDay || $slots)) $fields["days.$position"] = 'A closed day cannot be open 24 hours or include time slots.';
            if ($allDay && $slots) $fields["days.$position"] = '24-hour operation cannot include time slots.';
            if (!$closed && !$allDay && count($slots) === 0) $fields["days.$position.slots"] = "Add at least one opening time for " . ucfirst($name) . '.';
            if (count($slots) > 8) $fields["days.$position.slots"] = 'A day may contain at most eight time slots.';
            $rows = [];
            $intervals = [];
            if ($closed) $rows[] = $this->stateRow(false, true, false, false, null, null);
            elseif ($allDay) { $openDays++; $rows[] = $this->stateRow(true, false, true, false, null, null); }
            else {
                $openDays++;
                foreach ($slots as $slotPosition => $slot) {
                    $path = "days.$position.slots.$slotPosition";
                    if (!is_array($slot) || array_diff(array_keys($slot), ['opensAt','closesAt','overnight'])) { $fields[$path] = 'Each slot may contain only opening, closing, and overnight values.'; continue; }
                    $opens = (string) ($slot['opensAt'] ?? ''); $closes = (string) ($slot['closesAt'] ?? ''); $overnight = $slot['overnight'] ?? null;
                    if (!$this->time($opens) || !$this->time($closes)) { $fields[$path] = 'Use strict 24-hour HH:MM times.'; continue; }
                    if (!is_bool($overnight)) { $fields["$path.overnight"] = 'Overnight must be true or false.'; continue; }
                    $start = $this->minutes($opens); $end = $this->minutes($closes);
                    if (!$overnight && $end <= $start) { $fields[$path] = 'Closing time must be later than opening time.'; continue; }
                    if ($overnight && $end >= $start) { $fields[$path] = 'This overnight schedule is invalid.'; continue; }
                    $endNormalized = $overnight ? $end + 1440 : $end;
                    foreach ($intervals as [$otherStart, $otherEnd]) if (max($start, $otherStart) < min($endNormalized, $otherEnd)) $fields[$path] = 'These time slots overlap.';
                    $intervals[] = [$start, $endNormalized];
                    $rows[] = $this->stateRow(true, false, false, $overnight, $opens, $closes);
                }
            }
            $normalized[] = ['day' => $name, 'dayOfWeek' => array_search($name, self::DAYS, true), 'rows' => $rows];
        }
        foreach (array_diff(self::DAYS, array_keys($seen)) as $missing) $fields['days.' . $missing] = ucfirst($missing) . ' is required.';
        if ($openDays === 0) $fields['days'] = 'Choose at least one open day.';
        if ($fields) return $this->invalid($fields);
        usort($normalized, fn(array $a, array $b): int => $a['dayOfWeek'] <=> $b['dayOfWeek']);
        return $normalized;
    }

    private function stateRow(bool $open, bool $closed, bool $allDay, bool $overnight, ?string $opens, ?string $closes): array
    {
        return ['isOpen' => $open ? 1 : 0, 'isClosed' => $closed ? 1 : 0, 'is24Hours' => $allDay ? 1 : 0, 'isOvernight' => $overnight ? 1 : 0, 'opensAt' => $opens, 'closesAt' => $closes];
    }
    private function time(string $value): bool { return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1; }
    private function minutes(string $value): int { [$hours, $minutes] = array_map('intval', explode(':', $value)); return $hours * 60 + $minutes; }
    private function applicable(PartnerContext $context): void { if ($context->deliveryPartnerId !== null || !in_array($context->moduleCode, ['RETAIL','RESTAURANT','HOME_SERVICE'], true)) throw new ApiException(409, 'PARTNER_SETUP_NOT_APPLICABLE', 'Business Hours does not apply to this Partner identity.', 'Business Hours is not required for this Partner type.'); }
    private function invalid(array $fields): never { throw new ApiException(422, 'VALIDATION_ERROR', 'Business Hours validation failed.', 'Review the highlighted Business Hours fields.', $fields); }
    private function step(array $setup): array { return array_values(array_filter($setup['steps'], fn(array $step): bool => $step['code'] === 'hours'))[0] ?? ['code' => 'hours', 'status' => 'notStarted']; }
    private function auditMetadata(array $days): array { return ['changedWeekdays' => array_column($days, 'day'), 'activeSlotCount' => array_sum(array_map(fn(array $day): int => count(array_filter($day['rows'], fn(array $row): bool => $row['isOpen'] === 1)), $days))]; }
    private function assertStoredRow(array $row): void
    {
        $day = (int) $row['day_of_week']; $open = (bool) $row['is_open']; $closed = (bool) $row['is_closed']; $allDay = (bool) $row['is_24_hours']; $overnight = (bool) $row['is_overnight'];
        if ($day < 0 || $day > 6 || ($closed && ($open || $allDay || $overnight || $row['opens_at'] !== null || $row['closes_at'] !== null)) || ($allDay && (!$open || $closed || $overnight || $row['opens_at'] !== null || $row['closes_at'] !== null)) || (!$closed && !$allDay && (!$open || !$this->time(substr((string) $row['opens_at'], 0, 5)) || !$this->time(substr((string) $row['closes_at'], 0, 5))))) $this->malformed();
    }
    private function malformed(): never { throw new ApiException(409, 'PARTNER_HOURS_DATA_INVALID', 'An existing Business Hours row has ambiguous or inconsistent semantics.', 'Some saved Business Hours need support review before they can be edited.'); }
}
