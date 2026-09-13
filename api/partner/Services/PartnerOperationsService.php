<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Api\Core\ApiException;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerFulfilmentRepository;
use PDO;
use Throwable;

final class PartnerOperationsService
{
    public function __construct(private PDO $pdo, private PartnerFulfilmentRepository $fulfilment, private PartnerSetupService $setup) {}

    public function get(PartnerContext $context): array
    {
        $this->applicable($context);
        $row = $this->fulfilment->get($context);
        $values = $this->defaults();
        if ($row) $values = [
            'essiveryDelivery' => (bool) $row['essivery_delivery'], 'selfDelivery' => (bool) $row['self_delivery'],
            'customerPickup' => (bool) $row['customer_pickup'], 'serviceAtCustomerLocation' => (bool) $row['service_at_customer_location'],
        ];
        $setup = $this->setup->load($context);
        return ['type' => $this->type($context), 'fulfilment' => $this->project($context, $values), 'step' => $this->step($setup), 'setup' => $setup];
    }

    public function update(PartnerContext $context, array $input, ?callable $beforeCommit = null): array
    {
        $this->applicable($context);
        $values = $this->validate($context, $input);
        $this->pdo->beginTransaction();
        try {
            $this->fulfilment->save($context, $values);
            $summary = $this->setup->markOperationsComplete($context);
            if ($beforeCommit) $beforeCommit(['enabledModes' => array_keys(array_filter($this->project($context, $values)))]);
            $this->pdo->commit();
            $result = $this->get($context); $result['setup'] = $summary; $result['step'] = $this->step($summary); return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    private function validate(PartnerContext $context, array $input): array
    {
        $home = $context->moduleCode === 'HOME_SERVICE';
        $allowed = $home ? ['serviceAtCustomerLocation'] : ['essiveryDelivery','selfDelivery','customerPickup'];
        $unknown = array_values(array_diff(array_keys($input), $allowed));
        if ($unknown) $this->invalid(['body' => 'Unsupported fields: ' . implode(', ', $unknown)]);
        $fields = [];
        foreach ($allowed as $field) if (!array_key_exists($field, $input) || !is_bool($input[$field])) $fields[$field] = 'Choose yes or no.';
        if ($fields) $this->invalid($fields);
        if ($home && !$input['serviceAtCustomerLocation']) $this->invalid(['serviceAtCustomerLocation' => 'Enable Service at Customer Location to continue.']);
        if (!$home && !$input['essiveryDelivery'] && !$input['selfDelivery'] && !$input['customerPickup']) $this->invalid(['fulfilment' => 'Choose at least one fulfilment mode.']);
        return array_replace($this->defaults(), $input);
    }

    private function applicable(PartnerContext $context): void { if ($context->deliveryPartnerId !== null || !in_array($context->moduleCode, ['RETAIL','RESTAURANT','HOME_SERVICE'], true)) throw new ApiException(409, 'PARTNER_SETUP_NOT_APPLICABLE', 'Operations does not apply to this Partner identity.', 'Operations is not required for this Partner type.'); }
    private function defaults(): array { return ['essiveryDelivery'=>false,'selfDelivery'=>false,'customerPickup'=>false,'serviceAtCustomerLocation'=>false]; }
    private function project(PartnerContext $context, array $values): array { return $context->moduleCode === 'HOME_SERVICE' ? ['serviceAtCustomerLocation'=>$values['serviceAtCustomerLocation']] : ['essiveryDelivery'=>$values['essiveryDelivery'],'selfDelivery'=>$values['selfDelivery'],'customerPickup'=>$values['customerPickup']]; }
    private function type(PartnerContext $context): string { return strtolower($context->moduleCode); }
    private function step(array $setup): array { return array_values(array_filter($setup['steps'], fn(array $step): bool => $step['code'] === 'operations'))[0] ?? ['code'=>'operations','status'=>'notStarted']; }
    private function invalid(array $fields): never { throw new ApiException(422, 'VALIDATION_ERROR', 'Operations validation failed.', 'Review your Operations selections.', $fields); }
}
