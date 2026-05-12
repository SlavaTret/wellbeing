<?php

namespace console\controllers;

use common\services\CreatioSyncService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Creatio sync commands.
 *
 * Usage:
 *   php yii creatio/sync-contracts          # sync all companies
 *   php yii creatio/sync-contracts <guid>   # sync one company by creatio_account_id
 */
class CreatioController extends Controller
{
    /**
     * Sync WelContract records from Creatio for all active companies (or a specific one).
     *
     * @param string|null $accountId  Creatio Account GUID to sync a single company.
     */
    public function actionSyncContracts(?string $accountId = null): int
    {
        $this->stdout('Syncing contracts' . ($accountId ? ' for account ' . $accountId : ' for all companies') . '...' . PHP_EOL);

        $service = new CreatioSyncService();
        $service->syncContracts($accountId);

        $this->stdout('Done.' . PHP_EOL);
        return ExitCode::OK;
    }
}
