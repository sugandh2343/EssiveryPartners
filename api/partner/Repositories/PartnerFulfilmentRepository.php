<?php
declare(strict_types=1);

namespace Essivery\Partner\Repositories;

use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Helpers;
use Essivery\Partner\Domain\PartnerContext;
use PDO;

final class PartnerFulfilmentRepository
{
    private bool $schemaChecked = false;
    public function __construct(private PDO $pdo) {}

    public function get(PartnerContext $context): array
    {
        $this->assertSchema();
        $statement = $this->pdo->prepare("SELECT essivery_delivery,self_delivery,customer_pickup,service_at_customer_location FROM partner_fulfilment_settings WHERE partner_id=:partner AND module_code=:module AND status='active' LIMIT 1");
        $statement->execute(['partner' => $context->businessPartnerId, 'module' => $context->moduleCode]);
        return $statement->fetch() ?: [];
    }

    public function save(PartnerContext $context, array $values): void
    {
        $this->assertSchema();
        $parameters = [
            'public' => Helpers::publicId('PFS'), 'partner' => $context->businessPartnerId, 'module' => $context->moduleCode,
            'essivery' => $values['essiveryDelivery'] ? 1 : 0, 'self' => $values['selfDelivery'] ? 1 : 0,
            'pickup' => $values['customerPickup'] ? 1 : 0, 'customer_location' => $values['serviceAtCustomerLocation'] ? 1 : 0,
        ];
        $sql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "INSERT INTO partner_fulfilment_settings(public_id,partner_id,module_code,essivery_delivery,self_delivery,customer_pickup,service_at_customer_location,status) VALUES(:public,:partner,:module,:essivery,:self,:pickup,:customer_location,'active') ON CONFLICT(partner_id,module_code) DO UPDATE SET essivery_delivery=excluded.essivery_delivery,self_delivery=excluded.self_delivery,customer_pickup=excluded.customer_pickup,service_at_customer_location=excluded.service_at_customer_location,status='active',updated_at=CURRENT_TIMESTAMP"
            : "INSERT INTO partner_fulfilment_settings(public_id,partner_id,module_code,essivery_delivery,self_delivery,customer_pickup,service_at_customer_location,status) VALUES(:public,:partner,:module,:essivery,:self,:pickup,:customer_location,'active') ON DUPLICATE KEY UPDATE essivery_delivery=VALUES(essivery_delivery),self_delivery=VALUES(self_delivery),customer_pickup=VALUES(customer_pickup),service_at_customer_location=VALUES(service_at_customer_location),status='active',updated_at=CURRENT_TIMESTAMP(6)";
        $this->pdo->prepare($sql)->execute($parameters);
    }

    private function assertSchema(): void
    {
        if ($this->schemaChecked || $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') { $this->schemaChecked = true; return; }
        $required = ['public_id','partner_id','module_code','essivery_delivery','self_delivery','customer_pickup','service_at_customer_location','status'];
        $statement = $this->pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='partner_fulfilment_settings'");
        $statement->execute();
        $missing = array_values(array_diff($required, array_column($statement->fetchAll(), 'column_name')));
        if ($missing) throw new ApiException(503, 'PARTNER_OPERATIONS_SCHEMA_UNAVAILABLE', 'The Operations schema is incomplete.', 'Operations is temporarily unavailable.', [], true, ['missingColumns' => $missing]);
        $this->schemaChecked = true;
    }
}
