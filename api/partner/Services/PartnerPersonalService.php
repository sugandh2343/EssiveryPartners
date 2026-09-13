<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Api\Core\ApiException;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerPersonalRepository;
use PDO;
use Throwable;

final class PartnerPersonalService
{
    public function __construct(private PDO $pdo, private PartnerPersonalRepository $personal, private PartnerSetupService $setup) {}

    public function get(PartnerContext $context): array
    {
        $row = $this->personal->get($context);
        if (!$row) throw new ApiException(404, 'PARTNER_PROFILE_NOT_FOUND', 'Owned Partner profile was not found.', 'Your Partner profile could not be loaded.');
        $name = trim((string) $row['owner_name']);
        if ($this->placeholder($name)) $name = '';
        return ['ownerName' => $name, 'verifiedMobile' => $row['mobile'], 'email' => $row['email'] ?: null, 'alternateMobile' => null, 'profilePhoto' => null, 'step' => ['code' => 'personal', 'status' => $name === '' ? 'notStarted' : 'complete']];
    }

    public function update(PartnerContext $context, array $input): array
    {
        $name = preg_replace('/\s+/u', ' ', trim(strip_tags((string) ($input['ownerName'] ?? '')))) ?? '';
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $fields = [];
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 150) $fields['ownerName'] = 'Owner name must be between 2 and 150 characters.';
        if ($email !== '' && (mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) $fields['email'] = 'Enter a valid email address.';
        if ($fields) throw new ApiException(422, 'VALIDATION_ERROR', 'Personal Details validation failed.', 'Review the highlighted Personal Details fields.', $fields);
        $this->pdo->beginTransaction();
        try {
            $this->personal->update($context, $name, $email === '' ? null : $email);
            $summary = $this->setup->markPersonalComplete($context);
            $this->pdo->commit();
            return $this->get($context) + ['setup' => $summary];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    private function placeholder(string $name): bool
    {
        return in_array(mb_strtolower($name), ['', 'partner', 'owner', 'delivery partner'], true);
    }
}
