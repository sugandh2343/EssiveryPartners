<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Helpers;
use Essivery\Partner\Domain\PartnerContext;
use PDO;
use PDOException;
use Throwable;

final class PartnerSetupService
{
    private bool $schemaChecked = false;

    public function __construct(private PDO $pdo, private array $definitions)
    {
    }

    public function load(PartnerContext $context): array
    {
        try {
            $this->assertSchema();
            $application = $this->current($context, false);
            if (!$application) {
                throw new ApiException(409, 'PARTNER_SETUP_NOT_INITIALIZED', 'Partner setup application is not initialized.', 'Business Setup is being prepared.', [], true);
            }
            return $this->summary($application, $context);
        } catch (PDOException $error) {
            throw $this->databaseError($error);
        }
    }

    public function assertMutable(PartnerContext $context,string $stepCode):void
    {
        $application=$this->current($context,false);if(!$application)throw new ApiException(409,'PARTNER_SETUP_NOT_INITIALIZED','Partner setup application is not initialized.','Business Setup is being prepared.',[],true);$status=(string)$application['application_status'];if(in_array($status,['submitted','under_review','approved','rejected'],true))throw new ApiException(409,'APPLICATION_READ_ONLY','The submitted application is read-only.','This setup cannot be edited in its current review state.');if($status==='correction_required'){$q=$this->pdo->prepare("SELECT step_status FROM partner_onboarding_steps WHERE application_id=:application AND step_code=:step AND status='active' LIMIT 1");$q->execute(['application'=>$application['id'],'step'=>$stepCode]);if($q->fetchColumn()!=='needs_correction')throw new ApiException(409,'APPLICATION_SECTION_READ_ONLY','This section was not requested for correction.','Only sections that need correction can be edited.');}
    }

    public function bootstrap(PartnerContext $context): array
    {
        $this->assertSchema();
        $definition = $this->definition($context->moduleCode);
        $created = false;
        $initializedSteps = 0;
        $this->pdo->beginTransaction();
        try {
            $application = $this->current($context, true);
            if (!$application) {
                try {
                    $statement = $this->pdo->prepare(
                        'INSERT INTO partner_onboarding_applications
                         (public_id,business_partner_id,delivery_partner_id,identity_type,module_code,application_status,last_saved_at)
                         VALUES(:public,:business,:delivery,:identity,:module,\'in_progress\',CURRENT_TIMESTAMP)'
                    );
                    $statement->execute([
                        'public' => Helpers::publicId('PSA'),
                        'business' => $context->businessPartnerId,
                        'delivery' => $context->deliveryPartnerId,
                        'identity' => $context->identityType,
                        'module' => $context->moduleCode,
                    ]);
                    $created = true;
                } catch (PDOException $error) {
                    if ((int) ($error->errorInfo[1] ?? 0) !== 1062) throw $error;
                }
                $application = $this->current($context, true);
                if (!$application) throw new ApiException(409, 'PARTNER_SETUP_CONFLICT', 'Partner setup ownership could not be resolved.', 'Business Setup could not be prepared safely.', [], true);
            }

            if (!$created && in_array((string) $application['application_status'], ['submitted','under_review','correction_required','approved','rejected'], true)) {
                $this->pdo->commit();
                return ['created' => false, 'initializedSteps' => 0, 'summary' => $this->summary($application, $context)];
            }

            foreach ($definition as $step) {
                $existingStep = $this->pdo->prepare('SELECT 1 FROM partner_onboarding_steps WHERE application_id=:application AND step_code=:code LIMIT 1');
                $existingStep->execute(['application' => $application['id'], 'code' => $step['code']]);
                if ($existingStep->fetchColumn()) continue;
                try {
                    $mobile = $step['code'] === 'mobile_verification';
                    $statement = $this->pdo->prepare(
                        'INSERT INTO partner_onboarding_steps
                         (public_id,application_id,step_code,requirement_type,is_required,step_status,completed_at,last_saved_at)
                         VALUES(:public,:application,:code,:requirement,:required,:step_status,:completed,:saved)'
                    );
                    $statement->execute([
                        'public' => Helpers::publicId('PSS'),
                        'application' => $application['id'],
                        'code' => $step['code'],
                        'requirement' => $step['requirement'],
                        'required' => $step['requirement'] === 'required' ? 1 : 0,
                        'step_status' => $mobile ? 'complete' : 'not_started',
                        'completed' => $mobile ? gmdate('Y-m-d H:i:s') : null,
                        'saved' => $mobile ? gmdate('Y-m-d H:i:s') : null,
                    ]);
                    $initializedSteps++;
                } catch (PDOException $error) {
                    if ((int) ($error->errorInfo[1] ?? 0) !== 1062) throw $error;
                }
            }

            $this->refreshProgress((int) $application['id']);
            $application = $this->applicationById((int) $application['id']);
            $this->pdo->commit();
            return ['created' => $created, 'initializedSteps' => $initializedSteps, 'summary' => $this->summary($application, $context)];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($error instanceof PDOException) throw $this->databaseError($error);
            throw $error;
        }
    }

    public function markPersonalComplete(PartnerContext $context): array
    {
        $application = $this->current($context, true);
        if (!$application) throw new ApiException(409, 'PARTNER_SETUP_NOT_INITIALIZED', 'Partner setup application is not initialized.', 'Business Setup is being prepared.', [], true);
        $statement = $this->pdo->prepare("UPDATE partner_onboarding_steps SET step_status='complete',completed_at=COALESCE(completed_at,CURRENT_TIMESTAMP),last_saved_at=CURRENT_TIMESTAMP,revision_no=revision_no+1 WHERE application_id=:application AND step_code='personal' AND status='active'");
        $statement->execute(['application' => $application['id']]);
        $this->refreshProgress((int) $application['id']);
        return $this->summary($this->applicationById((int) $application['id']), $context);
    }

    public function markBusinessComplete(PartnerContext $context): array
    {
        if ($context->deliveryPartnerId !== null) throw new ApiException(409, 'PARTNER_SETUP_NOT_APPLICABLE', 'Business Details does not apply to Delivery Partners.', 'Continue to Location setup.');
        $application = $this->current($context, true);
        if (!$application) throw new ApiException(409, 'PARTNER_SETUP_NOT_INITIALIZED', 'Partner setup application is not initialized.', 'Business Setup is being prepared.', [], true);
        $statement=$this->pdo->prepare("UPDATE partner_onboarding_steps SET step_status='complete',completed_at=COALESCE(completed_at,CURRENT_TIMESTAMP),last_saved_at=CURRENT_TIMESTAMP,revision_no=revision_no+1 WHERE application_id=:application AND step_code='business' AND status='active'");
        $statement->execute(['application'=>$application['id']]);
        $this->refreshProgress((int)$application['id']);
        return$this->summary($this->applicationById((int)$application['id']),$context);
    }

    public function markLocationComplete(PartnerContext $context): array
    {
        $application=$this->current($context,true);if(!$application)throw new ApiException(409,'PARTNER_SETUP_NOT_INITIALIZED','Partner setup application is not initialized.','Business Setup is being prepared.',[],true);$statement=$this->pdo->prepare("UPDATE partner_onboarding_steps SET step_status='complete',completed_at=COALESCE(completed_at,CURRENT_TIMESTAMP),last_saved_at=CURRENT_TIMESTAMP,revision_no=revision_no+1 WHERE application_id=:application AND step_code='location' AND status='active'");$statement->execute(['application'=>$application['id']]);$this->refreshProgress((int)$application['id']);return$this->summary($this->applicationById((int)$application['id']),$context);
    }

    public function markHoursComplete(PartnerContext $context): array
    {
        if ($context->deliveryPartnerId !== null) throw new ApiException(409, 'PARTNER_SETUP_NOT_APPLICABLE', 'Business Hours does not apply to Delivery Partners.', 'Business Hours is not required for this Partner type.');
        $application = $this->current($context, true);
        if (!$application) throw new ApiException(409, 'PARTNER_SETUP_NOT_INITIALIZED', 'Partner setup application is not initialized.', 'Business Setup is being prepared.', [], true);
        $statement = $this->pdo->prepare("UPDATE partner_onboarding_steps SET step_status='complete',completed_at=COALESCE(completed_at,CURRENT_TIMESTAMP),last_saved_at=CURRENT_TIMESTAMP,revision_no=revision_no+1 WHERE application_id=:application AND step_code='hours' AND status='active'");
        $statement->execute(['application' => $application['id']]);
        if ($statement->rowCount() !== 1) throw new ApiException(409, 'PARTNER_SETUP_NOT_APPLICABLE', 'Business Hours is not present in this setup definition.', 'Business Hours is not required for this Partner type.');
        $this->refreshProgress((int) $application['id']);
        return $this->summary($this->applicationById((int) $application['id']), $context);
    }

    public function markOperationsComplete(PartnerContext $context): array
    {
        if ($context->deliveryPartnerId !== null) throw new ApiException(409, 'PARTNER_SETUP_NOT_APPLICABLE', 'Operations does not apply to Delivery Partners.', 'Operations is not required for this Partner type.');
        $application = $this->current($context, true);
        if (!$application) throw new ApiException(409, 'PARTNER_SETUP_NOT_INITIALIZED', 'Partner setup application is not initialized.', 'Business Setup is being prepared.', [], true);
        $statement = $this->pdo->prepare("UPDATE partner_onboarding_steps SET step_status='complete',completed_at=COALESCE(completed_at,CURRENT_TIMESTAMP),last_saved_at=CURRENT_TIMESTAMP,revision_no=revision_no+1 WHERE application_id=:application AND step_code='operations' AND status='active'");
        $statement->execute(['application'=>$application['id']]);
        if ($statement->rowCount() !== 1) throw new ApiException(409, 'PARTNER_SETUP_NOT_APPLICABLE', 'Operations is not present in this setup definition.', 'Operations is not required for this Partner type.');
        $this->refreshProgress((int)$application['id']);
        return $this->summary($this->applicationById((int)$application['id']),$context);
    }

    public function syncDocumentsState(PartnerContext $context,bool $complete,bool $correction):array
    {
        $application=$this->current($context,false);if(!$application)throw new ApiException(409,'PARTNER_SETUP_NOT_INITIALIZED','Partner setup application is not initialized.','Business Setup is being prepared.',[],true);$desired=$complete?'complete':($correction?'needs_correction':'in_progress');$statement=$this->pdo->prepare("UPDATE partner_onboarding_steps SET step_status=:status,completed_at=CASE WHEN :complete=1 THEN COALESCE(completed_at,CURRENT_TIMESTAMP) ELSE NULL END,last_saved_at=CURRENT_TIMESTAMP,revision_no=revision_no+1 WHERE application_id=:application AND step_code='documents' AND status='active' AND step_status<>:status_compare");$statement->execute(['status'=>$desired,'complete'=>$complete?1:0,'application'=>$application['id'],'status_compare'=>$desired]);if($statement->rowCount()>0)$this->refreshProgress((int)$application['id']);return$this->summary($this->applicationById((int)$application['id']),$context);
    }

    public function syncBankState(PartnerContext $context,bool $complete,bool $correction):array
    {
        $application=$this->current($context,false);if(!$application)throw new ApiException(409,'PARTNER_SETUP_NOT_INITIALIZED','Partner setup application is not initialized.','Business Setup is being prepared.',[],true);$desired=$complete?'complete':($correction?'needs_correction':'in_progress');$statement=$this->pdo->prepare("UPDATE partner_onboarding_steps SET step_status=:status,completed_at=CASE WHEN :complete=1 THEN COALESCE(completed_at,CURRENT_TIMESTAMP) ELSE NULL END,last_saved_at=CURRENT_TIMESTAMP,revision_no=revision_no+1 WHERE application_id=:application AND step_code='bank' AND status='active' AND step_status<>:status_compare");$statement->execute(['status'=>$desired,'complete'=>$complete?1:0,'application'=>$application['id'],'status_compare'=>$desired]);if($statement->rowCount()>0)$this->refreshProgress((int)$application['id']);return$this->summary($this->applicationById((int)$application['id']),$context);
    }

    public function markRetailComplete(PartnerContext $context):array
    {
        if($context->deliveryPartnerId!==null||$context->moduleCode!=='RETAIL')throw new ApiException(409,'PARTNER_SETUP_NOT_APPLICABLE','Retail Setup does not apply to this Partner identity.','Retail Setup is not required for this Partner type.');$application=$this->current($context,true);if(!$application)throw new ApiException(409,'PARTNER_SETUP_NOT_INITIALIZED','Partner setup application is not initialized.','Business Setup is being prepared.',[],true);$q=$this->pdo->prepare("UPDATE partner_onboarding_steps SET step_status='complete',completed_at=COALESCE(completed_at,CURRENT_TIMESTAMP),last_saved_at=CURRENT_TIMESTAMP,revision_no=revision_no+1 WHERE application_id=:application AND step_code='retail_setup' AND status='active'");$q->execute(['application'=>$application['id']]);if($q->rowCount()!==1)throw new ApiException(409,'PARTNER_SETUP_NOT_APPLICABLE','Retail Setup is absent from this setup definition.','Retail Setup is not required for this Partner type.');$this->refreshProgress((int)$application['id']);return$this->summary($this->applicationById((int)$application['id']),$context);
    }
    public function markRestaurantComplete(PartnerContext $context):array
    {
        if($context->deliveryPartnerId!==null||$context->moduleCode!=='RESTAURANT')throw new ApiException(409,'PARTNER_SETUP_NOT_APPLICABLE','Restaurant Setup does not apply to this Partner identity.','Restaurant Setup is not required for this Partner type.');$a=$this->current($context,true);if(!$a)throw new ApiException(409,'PARTNER_SETUP_NOT_INITIALIZED','Partner setup application is not initialized.','Business Setup is being prepared.',[],true);$q=$this->pdo->prepare("UPDATE partner_onboarding_steps SET step_status='complete',completed_at=COALESCE(completed_at,CURRENT_TIMESTAMP),last_saved_at=CURRENT_TIMESTAMP,revision_no=revision_no+1 WHERE application_id=:application AND step_code='restaurant_setup' AND status='active'");$q->execute(['application'=>$a['id']]);if($q->rowCount()!==1)throw new ApiException(409,'PARTNER_SETUP_NOT_APPLICABLE','Restaurant Setup is absent from this setup definition.','Restaurant Setup is not required for this Partner type.');$this->refreshProgress((int)$a['id']);return$this->summary($this->applicationById((int)$a['id']),$context);
    }

    public function markHomeServiceComplete(PartnerContext $context):array
    {
        if($context->deliveryPartnerId!==null||$context->moduleCode!=='HOME_SERVICE')throw new ApiException(409,'PARTNER_SETUP_NOT_APPLICABLE','Home Service Setup does not apply to this Partner identity.','Home Service Setup is not required for this Partner type.');$application=$this->current($context,true);if(!$application)throw new ApiException(409,'PARTNER_SETUP_NOT_INITIALIZED','Partner setup application is not initialized.','Business Setup is being prepared.',[],true);$q=$this->pdo->prepare("UPDATE partner_onboarding_steps SET step_status='complete',completed_at=COALESCE(completed_at,CURRENT_TIMESTAMP),last_saved_at=CURRENT_TIMESTAMP,revision_no=revision_no+1 WHERE application_id=:application AND step_code='home_service_setup' AND status='active'");$q->execute(['application'=>$application['id']]);if($q->rowCount()!==1)throw new ApiException(409,'PARTNER_SETUP_NOT_APPLICABLE','Home Service Setup is absent from this setup definition.','Home Service Setup is not required for this Partner type.');$this->refreshProgress((int)$application['id']);return$this->summary($this->applicationById((int)$application['id']),$context);
    }

    public function syncDeliveryState(PartnerContext $context,bool $complete,bool $correction):array
    {
        if($context->deliveryPartnerId===null||$context->moduleCode!=='DELIVERY')throw new ApiException(409,'PARTNER_SETUP_NOT_APPLICABLE','Delivery Partner Setup does not apply to this identity.','Delivery Partner Setup is not required for this Partner type.');$application=$this->current($context,true);if(!$application)throw new ApiException(409,'PARTNER_SETUP_NOT_INITIALIZED','Partner setup application is not initialized.','Business Setup is being prepared.',[],true);$status=$complete?'complete':($correction?'needs_correction':'in_progress');$q=$this->pdo->prepare("UPDATE partner_onboarding_steps SET step_status=:status,completed_at=CASE WHEN :complete=1 THEN COALESCE(completed_at,CURRENT_TIMESTAMP) ELSE NULL END,last_saved_at=CURRENT_TIMESTAMP,revision_no=revision_no+1 WHERE application_id=:application AND step_code='delivery_setup' AND status='active' AND step_status<>:compare");$q->execute(['status'=>$status,'complete'=>$complete?1:0,'application'=>$application['id'],'compare'=>$status]);if($q->rowCount()>0)$this->refreshProgress((int)$application['id']);return$this->summary($this->applicationById((int)$application['id']),$context);
    }

    private function assertSchema(): void
    {
        if ($this->schemaChecked) return;
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') { $this->schemaChecked = true; return; }
        $required = [
            'partner_onboarding_applications' => ['id','public_id','business_partner_id','delivery_partner_id','identity_type','module_code','version_no','application_status','current_step','completion_percentage','submitted_at','is_current','status'],
            'partner_onboarding_steps' => ['id','public_id','application_id','step_code','requirement_type','is_required','step_status','completed_at','status'],
        ];
        $missing = [];
        foreach ($required as $table => $columns) {
            $statement = $this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table');
            $statement->execute(['table' => $table]);
            $actual = array_column($statement->fetchAll(), 'column_name');
            foreach (array_diff($columns, $actual) as $column) $missing[] = $table . '.' . $column;
        }
        if ($missing) {
            throw new ApiException(503, 'PARTNER_SETUP_SCHEMA_UNAVAILABLE', 'Partner setup schema is incomplete in the active application database.', 'Business Setup is temporarily unavailable.', [], true, ['missingColumns' => $missing]);
        }
        $this->schemaChecked = true;
    }

    private function databaseError(PDOException $error): ApiException
    {
        return new ApiException(503, 'PARTNER_SETUP_DATABASE_ERROR', 'Partner setup database operation failed.', 'Business Setup is temporarily unavailable.', [], true, [
            'sqlState' => (string) ($error->errorInfo[0] ?? $error->getCode()),
            'driverCode' => (int) ($error->errorInfo[1] ?? 0),
        ]);
    }

    private function refreshProgress(int $applicationId): void
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) total,SUM(step_status='complete') completed
             FROM partner_onboarding_steps WHERE application_id=:application AND is_required=1 AND status='active'"
        );
        $statement->execute(['application' => $applicationId]);
        $counts = $statement->fetch() ?: ['total' => 0, 'completed' => 0];
        $total = (int) $counts['total'];
        $percentage = $total > 0 ? (int) round(((int) $counts['completed'] * 100) / $total) : 0;
        $next = $this->pdo->prepare(
            "SELECT step_code FROM partner_onboarding_steps
             WHERE application_id=:application AND is_required=1 AND status='active' AND step_status<>'complete'
             ORDER BY id LIMIT 1"
        );
        $next->execute(['application' => $applicationId]);
        $current = $next->fetchColumn() ?: null;
        $update = $this->pdo->prepare(
            'UPDATE partner_onboarding_applications
             SET completion_percentage=:percentage,current_step=:current,last_saved_at=CURRENT_TIMESTAMP
             WHERE id=:id'
        );
        $update->execute(['percentage' => $percentage, 'current' => $current, 'id' => $applicationId]);
    }

    private function summary(array $application, PartnerContext $context): array
    {
        $definition = $this->definition($context->moduleCode);
        $statement = $this->pdo->prepare('SELECT step_code,requirement_type,is_required,step_status,completed_at FROM partner_onboarding_steps WHERE application_id=:application AND status=\'active\' ORDER BY id');
        $statement->execute(['application' => $application['id']]);
        $rows = [];
        foreach ($statement->fetchAll() as $row) $rows[$row['step_code']] = $row;
        $steps = [];
        foreach ($definition as $item) {
            $row = $rows[$item['code']] ?? null;
            if (!$row) continue;
            $steps[] = [
                'code' => $this->camel($item['code']),
                'routeCode' => $this->routeCode($item['code']),
                'title' => $item['title'],
                'description' => $item['description'],
                'requirement' => $row['requirement_type'],
                'required' => (bool) $row['is_required'],
                'status' => $this->camel($row['step_status']),
                'completedAt' => $row['completed_at'] ? gmdate('c', strtotime($row['completed_at'])) : null,
            ];
        }
        $current = $application['current_step'] ? $this->camel($application['current_step']) : null;
        $status = $this->camel($application['application_status']);
        return [
            'status' => $status,
            'completionPercentage' => (int) $application['completion_percentage'],
            'currentStep' => $current,
            'nextRecommendedStep' => $current,
            'canSubmit' => (int) $application['completion_percentage'] === 100 && in_array($application['application_status'], ['draft','in_progress','correction_required'], true),
            'steps' => $steps,
            'submittedAt' => $application['submitted_at'] ? gmdate('c', strtotime($application['submitted_at'])) : null,
            'version' => (int) $application['version_no'],
        ];
    }

    private function current(PartnerContext $context, bool $lock): ?array
    {
        $column = $context->deliveryPartnerId !== null ? 'delivery_partner_id' : 'business_partner_id';
        $owner = $context->deliveryPartnerId ?? $context->businessPartnerId;
        $lockClause = $lock && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : '';
        $statement = $this->pdo->prepare("SELECT * FROM partner_onboarding_applications WHERE $column=:owner AND is_current=1 AND status='active' LIMIT 1" . $lockClause);
        $statement->execute(['owner' => $owner]);
        return $statement->fetch() ?: null;
    }

    private function applicationById(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM partner_onboarding_applications WHERE id=:id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    private function definition(string $module): array
    {
        if (!isset($this->definitions[$module])) throw new ApiException(409, 'PARTNER_SETUP_NOT_APPLICABLE', 'No setup definition exists for this Partner module.', 'Business Setup is not available for this Partner type.');
        return $this->definitions[$module];
    }

    private function camel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }

    private function routeCode(string $value): string
    {
        return ['retail_setup' => 'retail', 'restaurant_setup' => 'restaurant', 'home_service_setup' => 'home-service', 'delivery_setup' => 'delivery'][$value] ?? str_replace('_', '-', $value);
    }
}
