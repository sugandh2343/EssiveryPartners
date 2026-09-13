<?php
declare(strict_types=1);

namespace Essivery\Partner\Repositories;

use Essivery\Partner\Domain\PartnerContext;
use PDO;

final class PartnerBusinessHoursRepository
{
    private bool $schemaChecked = false;

    public function __construct(private PDO $pdo) {}

    public function rows(PartnerContext $context): array
    {
        $this->assertSchema();
        $statement = $this->pdo->prepare(
            "SELECT day_of_week,slot_order,is_open,is_closed,is_24_hours,is_overnight,opens_at,closes_at
             FROM partner_business_hours
             WHERE partner_id=:partner AND module_code=:module AND status='active'
             ORDER BY day_of_week,slot_order"
        );
        $statement->execute(['partner' => $context->businessPartnerId, 'module' => $context->moduleCode]);
        return $statement->fetchAll();
    }

    public function replace(PartnerContext $context, array $days): void
    {
        $this->assertSchema();
        $delete = $this->pdo->prepare('DELETE FROM partner_business_hours WHERE partner_id=:partner AND module_code=:module');
        $delete->execute(['partner' => $context->businessPartnerId, 'module' => $context->moduleCode]);
        $insert = $this->pdo->prepare(
            'INSERT INTO partner_business_hours
             (partner_id,module_code,day_of_week,slot_order,is_open,is_closed,is_24_hours,is_overnight,opens_at,closes_at,status)
             VALUES(:partner,:module,:day,:slot,:open,:closed,:all_day,:overnight,:opens,:closes,\'active\')'
        );
        foreach ($days as $day) {
            foreach ($day['rows'] as $slot => $row) {
                $insert->execute([
                    'partner' => $context->businessPartnerId,
                    'module' => $context->moduleCode,
                    'day' => $day['dayOfWeek'],
                    'slot' => $slot + 1,
                    'open' => $row['isOpen'],
                    'closed' => $row['isClosed'],
                    'all_day' => $row['is24Hours'],
                    'overnight' => $row['isOvernight'],
                    'opens' => $row['opensAt'],
                    'closes' => $row['closesAt'],
                ]);
            }
        }
    }

    private function assertSchema(): void
    {
        if ($this->schemaChecked || $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->schemaChecked = true;
            return;
        }
        $required = ['partner_id','module_code','day_of_week','slot_order','is_open','is_closed','is_24_hours','is_overnight','opens_at','closes_at','status'];
        $statement = $this->pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='partner_business_hours'");
        $statement->execute();
        $missing = array_values(array_diff($required, array_column($statement->fetchAll(), 'column_name')));
        if ($missing) {
            throw new \Essivery\Api\Core\ApiException(503, 'PARTNER_HOURS_SCHEMA_UNAVAILABLE', 'The normalized Business Hours schema is incomplete.', 'Business Hours is temporarily unavailable.', [], true, ['missingColumns' => $missing]);
        }
        $this->schemaChecked = true;
    }
}
