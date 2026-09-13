<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Api\Core\ApiException;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerContextRepository;
use PDO;

final class PartnerBootstrapService
{
    private PDO $pdo;
    private PartnerContextRepository $repository;
    private PartnerContextService $contexts;
    private ReferralAutoClaimInterface $referrals;

    public function __construct(
        PDO $pdo,
        PartnerContextRepository $repository,
        PartnerContextService $contexts,
        ReferralAutoClaimInterface $referrals
    ) {
        $this->pdo = $pdo;
        $this->repository = $repository;
        $this->contexts = $contexts;
        $this->referrals = $referrals;
    }

    public function bootstrap(array $auth): PartnerContext
    {
        $uninitialized = $this->contexts->resolve($auth, true);
        if ($uninitialized instanceof PartnerContext) {
            $this->referrals->afterBootstrap($uninitialized);
            return $uninitialized;
        }
        $identity = $uninitialized['identity'];
        $module = $uninitialized['module'];
        $delivery = $identity['identity_type'] === 'delivery_partner';
        $delivery ? $this->repository->assertDeliverySchema() : $this->repository->assertBusinessSchema();

        $this->pdo->beginTransaction();
        try {
            $core = $delivery ? $this->repository->createDelivery($identity) : $this->repository->createBusiness($identity);
            $context = $this->contexts->context($identity, $module, $delivery ? null : $core, $delivery ? $core : null);
            $this->pdo->commit();
        } catch (ApiException $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new ApiException(409, 'PARTNER_BOOTSTRAP_CONFLICT', 'Partner bootstrap could not be completed safely.', 'Please retry Partner setup.', [], true);
        }
        $this->referrals->afterBootstrap($context);
        return $context;
    }
}
