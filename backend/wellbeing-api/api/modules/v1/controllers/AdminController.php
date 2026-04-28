<?php

namespace api\modules\v1\controllers;

use common\models\Company;
use Yii;
use yii\rest\Controller;
use yii\web\NotFoundHttpException;

class AdminController extends Controller
{
    public $modelClass = 'common\models\User';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authentication'] = [
            'class' => \yii\filters\auth\HttpBearerAuth::class,
        ];
        $behaviors['access'] = [
            'class' => \yii\filters\AccessControl::class,
            'rules' => [['allow' => true, 'roles' => ['@']]],
        ];
        return $behaviors;
    }

    private function requireAdmin(): void
    {
        $user = Yii::$app->user->identity;
        if (!$user || !$user->is_admin) {
            throw new \yii\web\ForbiddenHttpException('Admin access required');
        }
    }

    // ── Dashboard ────────────────────────────────────────────────────

    public function actionDashboard()
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $db = Yii::$app->db;

        $userStats = $db->createCommand(
            "SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE status = 10) AS active FROM \"user\""
        )->queryOne();

        $apptStats = $db->createCommand(
            "SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE status IN ('confirmed','pending')) AS upcoming FROM appointment"
        )->queryOne();

        $payStats = $db->createCommand(
            "SELECT
                COUNT(*)                                                              AS total,
                COUNT(*)                FILTER (WHERE status = 'pending')              AS pending_count,
                COALESCE(SUM(amount)    FILTER (WHERE status = 'pending'),   0)        AS pending_amount,
                COALESCE(SUM(amount)    FILTER (WHERE status = 'completed'), 0)        AS paid_amount
             FROM payment"
        )->queryOne();

        $specCount = (int)$db->createCommand("SELECT COUNT(*) FROM specialist")->queryScalar();

        $stats = [
            'total_users'           => (int)$userStats['total'],
            'active_users'          => (int)$userStats['active'],
            'total_appointments'    => (int)$apptStats['total'],
            'upcoming_appointments' => (int)$apptStats['upcoming'],
            'pending_payments'      => (int)$payStats['pending_count'],
            'pending_amount'        => (float)$payStats['pending_amount'],
            'paid_amount'           => (float)$payStats['paid_amount'],
            'total_payments'        => (int)$payStats['total'],
            'total_specialists'     => $specCount,
        ];

        $recentRows = $db->createCommand("
            SELECT
                a.id,
                TRIM(COALESCE(u.first_name,'') || ' ' || COALESCE(u.last_name,'')) AS user_name,
                u.avatar_url,
                a.specialist_name,
                a.appointment_date,
                a.appointment_time,
                a.status,
                a.payment_status
            FROM appointment a
            LEFT JOIN \"user\" u ON u.id = a.user_id
            ORDER BY a.created_at DESC
            LIMIT 5
        ")->queryAll();

        $pendingRows = $db->createCommand("
            SELECT
                p.id,
                TRIM(COALESCE(u.first_name,'') || ' ' || COALESCE(u.last_name,'')) AS user_name,
                a.specialist_name,
                p.amount,
                p.created_at,
                p.status AS payment_status
            FROM payment p
            LEFT JOIN \"user\" u ON u.id = p.user_id
            LEFT JOIN appointment a ON a.id = p.appointment_id
            WHERE p.status = 'pending'
            ORDER BY p.created_at DESC
            LIMIT 5
        ")->queryAll();

        $companyRows = $db->createCommand("
            SELECT
                c.name,
                COUNT(DISTINCT u.id)  AS total_users,
                COUNT(DISTINCT a.id)  AS total_appointments
            FROM company c
            LEFT JOIN \"user\" u ON u.company_id = c.id
            LEFT JOIN appointment a ON a.user_id = u.id
            WHERE c.is_active = TRUE
            GROUP BY c.id, c.name
            ORDER BY total_users DESC
            LIMIT 4
        ")->queryAll();

        return [
            'stats' => [
                'total_users'           => (int)$stats['total_users'],
                'active_users'          => (int)$stats['active_users'],
                'total_appointments'    => (int)$stats['total_appointments'],
                'upcoming_appointments' => (int)$stats['upcoming_appointments'],
                'pending_payments'      => (int)$stats['pending_payments'],
                'pending_amount'        => (float)$stats['pending_amount'],
                'paid_amount'           => (float)$stats['paid_amount'],
                'total_payments'        => (int)$stats['total_payments'],
                'total_specialists'     => (int)$stats['total_specialists'],
            ],
            'recent_appointments' => array_map(fn($r) => [
                'id'               => $r['id'],
                'user_name'        => $r['user_name'] ?: 'Невідомий',
                'avatar_url'       => $r['avatar_url'] ?: null,
                'specialist_name'  => $r['specialist_name'] ?: '—',
                'appointment_date' => $r['appointment_date'],
                'appointment_time' => $r['appointment_time'],
                'status'           => $r['status'],
                'payment_status'   => $r['payment_status'],
            ], $recentRows),
            'pending_payments' => array_map(fn($r) => [
                'id'             => $r['id'],
                'user_name'      => $r['user_name'] ?: 'Невідомий',
                'specialist_name'=> $r['specialist_name'] ?: '—',
                'amount'         => (float)$r['amount'],
                'created_at'     => $r['created_at'],
                'payment_status' => $r['payment_status'],
            ], $pendingRows),
            'company_stats' => array_map(fn($r) => [
                'name'               => $r['name'],
                'total_users'        => (int)$r['total_users'],
                'total_appointments' => (int)$r['total_appointments'],
            ], $companyRows),
        ];
    }

    // ── Companies ────────────────────────────────────────────────────

    public function actionCompanies()
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $rows = Yii::$app->db->createCommand("
            SELECT
                c.id, c.code, c.name, c.logo_url,
                c.primary_color, c.secondary_color, c.accent_color,
                c.free_sessions_per_user, c.is_active,
                c.created_at,
                COUNT(DISTINCT u.id) AS total_users,
                COUNT(DISTINCT a.id) AS total_appointments,
                (
                    SELECT COALESCE(SUM(p.amount), 0)
                    FROM payment p
                    JOIN \"user\" pu ON pu.id = p.user_id
                    WHERE pu.company_id = c.id AND p.status = 'completed'
                ) AS total_revenue
            FROM company c
            LEFT JOIN \"user\" u ON u.company_id = c.id
            LEFT JOIN appointment a ON a.user_id = u.id
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ")->queryAll();

        return array_map(fn($r) => [
            'id'                   => (int)$r['id'],
            'code'                 => $r['code'],
            'name'                 => $r['name'],
            'logo_url'             => $r['logo_url'],
            'primary_color'        => $r['primary_color'],
            'secondary_color'      => $r['secondary_color'],
            'accent_color'         => $r['accent_color'],
            'free_sessions_per_user' => (int)$r['free_sessions_per_user'],
            'is_active'            => (bool)$r['is_active'],
            'created_at'           => $r['created_at'],
            'total_users'          => (int)$r['total_users'],
            'total_appointments'   => (int)$r['total_appointments'],
            'total_revenue'        => (float)$r['total_revenue'],
        ], $rows);
    }

    public function actionCreateCompany()
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $data = Yii::$app->request->post();

        $company = new Company();
        $company->name                  = $data['name'] ?? '';
        $company->code                  = $data['code'] ?? '';
        $company->logo_url              = $data['logo_url'] ?? null;
        $company->primary_color         = $data['primary_color'] ?? '#2DB928';
        $company->secondary_color       = $data['secondary_color'] ?? '#1C2B20';
        $company->accent_color          = $data['accent_color'] ?? '#E8F5E9';
        $company->free_sessions_per_user = (int)($data['free_sessions_per_user'] ?? 5);
        $company->is_active             = isset($data['is_active']) ? (bool)$data['is_active'] : true;

        if (!$company->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $company->getErrors()];
        }

        if (!$company->save()) {
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Не вдалося зберегти компанію'];
        }

        return ['success' => true, 'company' => $this->companyRow($company)];
    }

    public function actionUpdateCompany($id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $company = Company::findOne((int)$id);
        if (!$company) throw new NotFoundHttpException('Компанію не знайдено');

        $data = Yii::$app->request->post();

        if (isset($data['name']))                   $company->name                   = $data['name'];
        if (isset($data['code']))                   $company->code                   = $data['code'];
        if (array_key_exists('logo_url', $data))    $company->logo_url               = $data['logo_url'];
        if (isset($data['primary_color']))          $company->primary_color          = $data['primary_color'];
        if (isset($data['secondary_color']))        $company->secondary_color        = $data['secondary_color'];
        if (isset($data['accent_color']))           $company->accent_color           = $data['accent_color'];
        if (isset($data['free_sessions_per_user'])) $company->free_sessions_per_user = (int)$data['free_sessions_per_user'];
        if (isset($data['is_active']))              $company->is_active              = (bool)$data['is_active'];

        if (!$company->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $company->getErrors()];
        }

        if (!$company->save()) {
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Не вдалося оновити компанію'];
        }

        return ['success' => true, 'company' => $this->companyRow($company)];
    }

    public function actionDeleteCompany($id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $company = Company::findOne((int)$id);
        if (!$company) throw new NotFoundHttpException('Компанію не знайдено');

        // Detach users from company before delete
        Yii::$app->db->createCommand()
            ->update('"user"', ['company_id' => null], ['company_id' => $company->id])
            ->execute();

        $company->delete();

        return ['success' => true];
    }

    public function actionUploadLogo()
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $file = \yii\web\UploadedFile::getInstanceByName('logo');
        if (!$file) {
            Yii::$app->response->statusCode = 400;
            return ['error' => 'Файл не отримано'];
        }

        $ext = strtolower($file->extension);
        if (!in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'webp'])) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Дозволені формати: SVG, PNG, JPG, WEBP'];
        }

        if ($file->size > 2 * 1024 * 1024) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Файл завеликий (макс. 2 МБ)'];
        }

        $dir = Yii::getAlias('@webroot/uploads/logos');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = 'logo_' . time() . '_' . Yii::$app->security->generateRandomString(8) . '.' . $ext;
        $path = $dir . '/' . $filename;

        if (!$file->saveAs($path)) {
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Не вдалося зберегти файл'];
        }

        $baseUrl = Yii::$app->request->hostInfo;

        return ['url' => $baseUrl . '/uploads/logos/' . $filename];
    }

    // ── Users ─────────────────────────────────────────────────────────

    public function actionUsers()
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $db = Yii::$app->db;
        $search  = Yii::$app->request->get('search', '');
        $status  = Yii::$app->request->get('status', '');
        $page    = max(1, (int)Yii::$app->request->get('page', 1));
        $limit   = 8;
        $offset  = ($page - 1) * $limit;

        $where = ['u.status != 40'];
        $params = [];

        if ($search !== '') {
            $where[] = "(u.first_name ILIKE :s OR u.last_name ILIKE :s OR u.email ILIKE :s OR c.name ILIKE :s)";
            $params[':s'] = '%' . $search . '%';
        }
        if ($status === 'active') {
            $where[] = 'u.status = 10';
        } elseif ($status === 'inactive') {
            $where[] = 'u.status != 10';
        }

        $whereStr = implode(' AND ', $where);

        // Two counts in one query
        $counts = $db->createCommand(
            "SELECT COUNT(*) FILTER (WHERE status != 40) AS total_all, COUNT(*) FILTER (WHERE status = 10) AS active_all FROM \"user\""
        )->queryOne();
        $totalAll  = (int)$counts['total_all'];
        $activeAll = (int)$counts['active_all'];

        $total = (int)$db->createCommand(
            "SELECT COUNT(*) FROM \"user\" u LEFT JOIN company c ON c.id = u.company_id WHERE $whereStr",
            $params
        )->queryScalar();

        $rows = $db->createCommand("
            SELECT
                u.id, u.first_name, u.last_name, u.email, u.phone,
                u.avatar_url, u.status, u.is_admin, u.created_at,
                c.id   AS company_id,
                c.name AS company_name,
                COALESCE(c.free_sessions_per_user, 0) AS free_sessions_per_user,
                COALESCE(ua.total_appointments, 0)    AS total_appointments,
                COALESCE(ua.free_used, 0)             AS free_used
            FROM \"user\" u
            LEFT JOIN company c ON c.id = u.company_id
            LEFT JOIN (
                SELECT user_id,
                       COUNT(*) AS total_appointments,
                       COUNT(*) FILTER (WHERE payment_status = 'free' AND status NOT IN ('cancelled')) AS free_used
                FROM appointment
                GROUP BY user_id
            ) ua ON ua.user_id = u.id
            WHERE $whereStr
            ORDER BY u.created_at DESC
            LIMIT $limit OFFSET $offset
        ", $params)->queryAll();

        // Compute sessions_left in PHP (avoids extra subquery)
        foreach ($rows as &$row) {
            $row['sessions_left'] = max(0, (int)$row['free_sessions_per_user'] - (int)$row['free_used']);
        }
        unset($row);

        return [
            'users'        => array_map(fn($r) => $this->userRow($r), $rows),
            'total'        => $total,
            'total_all'    => $totalAll,
            'active_all'   => $activeAll,
            'page'         => $page,
            'pages'        => (int)ceil($total / $limit),
            'per_page'     => $limit,
        ];
    }

    public function actionCreateUser()
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $data = Yii::$app->request->post();

        $user = new \common\models\User();
        $user->first_name = trim($data['first_name'] ?? '');
        $user->last_name  = trim($data['last_name']  ?? '');
        $user->email      = trim($data['email']      ?? '');
        $user->username   = trim($data['email']      ?? '');
        $user->phone      = $data['phone'] ?? '';
        $user->company_id = isset($data['company_id']) && $data['company_id'] !== '' ? (int)$data['company_id'] : null;
        $user->is_admin   = (bool)($data['is_admin'] ?? false);
        $user->status     = \common\models\User::STATUS_ACTIVE;

        $password = $data['password'] ?? Yii::$app->security->generateRandomString(10);
        $user->setPassword($password);
        $user->generateAuthKey();

        if (!$user->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $user->getErrors()];
        }

        if (!$user->save()) {
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Не вдалося створити користувача'];
        }

        $row = Yii::$app->db->createCommand("
            SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
                   u.avatar_url, u.status, u.is_admin, u.created_at,
                   c.id AS company_id, c.name AS company_name,
                   0 AS total_appointments, 0 AS sessions_left
            FROM \"user\" u LEFT JOIN company c ON c.id = u.company_id
            WHERE u.id = :id
        ", [':id' => $user->id])->queryOne();

        return ['success' => true, 'user' => $this->userRow($row)];
    }

    public function actionUpdateUser($id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $user = \common\models\User::findOne((int)$id);
        if (!$user) throw new NotFoundHttpException('Користувача не знайдено');

        $data = Yii::$app->request->post();

        if (isset($data['first_name']))  $user->first_name = $data['first_name'];
        if (isset($data['last_name']))   $user->last_name  = $data['last_name'];
        if (isset($data['email']))       $user->email      = $data['email'];
        if (isset($data['phone']))       $user->phone      = $data['phone'];
        if (isset($data['status']))      $user->status     = (int)$data['status'];
        if (isset($data['company_id']))  $user->company_id = $data['company_id'] ? (int)$data['company_id'] : null;
        if (isset($data['is_admin']))    $user->is_admin   = (bool)$data['is_admin'];

        if (!$user->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $user->getErrors()];
        }

        if (!$user->save()) {
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Не вдалося зберегти користувача'];
        }

        $db = Yii::$app->db;
        $row = $db->createCommand("
            SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
                   u.avatar_url, u.status, u.is_admin, u.created_at,
                   c.id AS company_id, c.name AS company_name,
                   (SELECT COUNT(*) FROM appointment a WHERE a.user_id = u.id) AS total_appointments,
                   (SELECT COALESCE(free_sessions_per_user, 0) FROM company WHERE id = u.company_id)
                   - (SELECT COUNT(*) FROM appointment a WHERE a.user_id = u.id AND a.payment_status = 'free' AND a.status NOT IN ('cancelled')) AS sessions_left
            FROM \"user\" u LEFT JOIN company c ON c.id = u.company_id
            WHERE u.id = :id
        ", [':id' => $user->id])->queryOne();

        return ['success' => true, 'user' => $this->userRow($row)];
    }

    public function actionDeleteUser($id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $user = \common\models\User::findOne((int)$id);
        if (!$user) throw new NotFoundHttpException('Користувача не знайдено');

        $user->status = \common\models\User::STATUS_DELETED;
        $user->save(false);

        return ['success' => true];
    }

    // ── Specialists ──────────────────────────────────────────────────────

    public function actionAdminSpecialists()
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $search = Yii::$app->request->get('search', '');
        $where  = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = "(s.name ILIKE :s OR s.type ILIKE :s OR s.categories ILIKE :s OR s.bio ILIKE :s)";
            $params[':s'] = '%' . $search . '%';
        }
        $whereStr = implode(' AND ', $where);

        $rows = Yii::$app->db->createCommand("
            SELECT
                s.id, s.name, s.type, s.bio, s.experience_years,
                s.categories, s.avatar_initials, s.price,
                s.is_active, s.created_at,
                COALESCE(rv.avg_rating, 0)  AS computed_rating,
                COALESCE(rv.cnt, 0)         AS reviews_count,
                COALESCE(ac.cnt, 0)         AS sessions_count
            FROM specialist s
            LEFT JOIN (
                SELECT specialist_id,
                       ROUND(AVG(rating)::numeric, 1) AS avg_rating,
                       COUNT(*) AS cnt
                FROM specialist_review
                GROUP BY specialist_id
            ) rv ON rv.specialist_id = s.id
            LEFT JOIN (
                SELECT specialist_name, COUNT(*) AS cnt
                FROM appointment
                GROUP BY specialist_name
            ) ac ON ac.specialist_name = s.name
            WHERE $whereStr
            ORDER BY s.is_active DESC, s.name ASC
        ", $params)->queryAll();

        return array_map(fn($r) => $this->specialistRow($r), $rows);
    }

    public function actionCreateSpecialist()
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $data = Yii::$app->request->post();

        $s = new \common\models\Specialist();
        $s->name             = trim($data['name'] ?? '');
        $s->type             = $data['type'] ?? 'psychologist';
        $s->bio              = $data['bio'] ?? '';
        $s->experience_years = (int)($data['experience_years'] ?? 0);
        $s->price            = (float)($data['price'] ?? 0);
        $s->categories       = $data['categories'] ?? '';
        $s->is_active        = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        $s->rating           = 0;

        $parts = preg_split('/\s+/', trim($s->name));
        $s->avatar_initials = mb_strtoupper(
            mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1)
        );

        if (!$s->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $s->getErrors()];
        }
        if (!$s->save()) {
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Не вдалося створити спеціаліста'];
        }

        if (!empty($data['slots'])) {
            $this->saveSpecialistSlots($s->id, $data['slots']);
        }

        return ['success' => true, 'specialist' => $this->specialistRowById($s->id)];
    }

    public function actionUpdateSpecialist($id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $s = \common\models\Specialist::findOne((int)$id);
        if (!$s) throw new NotFoundHttpException('Спеціаліста не знайдено');

        $data = Yii::$app->request->post();

        if (isset($data['name']))             $s->name             = trim($data['name']);
        if (isset($data['type']))             $s->type             = $data['type'];
        if (isset($data['bio']))              $s->bio              = $data['bio'];
        if (isset($data['experience_years'])) $s->experience_years = (int)$data['experience_years'];
        if (isset($data['price']))            $s->price            = (float)$data['price'];
        if (isset($data['categories']))       $s->categories       = $data['categories'];
        if (isset($data['is_active']))        $s->is_active        = (bool)$data['is_active'];

        $parts = preg_split('/\s+/', trim($s->name));
        $s->avatar_initials = mb_strtoupper(
            mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1)
        );

        if (!$s->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $s->getErrors()];
        }
        if (!$s->save()) {
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Не вдалося оновити спеціаліста'];
        }

        if (array_key_exists('slots', $data)) {
            $this->saveSpecialistSlots($s->id, $data['slots'] ?? []);
        }

        return ['success' => true, 'specialist' => $this->specialistRowById($s->id)];
    }

    public function actionDeleteSpecialist($id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $s = \common\models\Specialist::findOne((int)$id);
        if (!$s) throw new NotFoundHttpException('Спеціаліста не знайдено');

        $s->is_active = false;
        $s->save(false);

        return ['success' => true];
    }

    public function actionSpecialistSlots($id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $slots = \common\models\SpecialistSchedule::find()
            ->where(['specialist_id' => (int)$id])
            ->orderBy(['day_of_week' => SORT_ASC, 'time_slot' => SORT_ASC])
            ->all();

        $byDay = [];
        foreach ($slots as $slot) {
            $byDay[(string)$slot->day_of_week][] = $slot->time_slot;
        }
        return $byDay;
    }

    public function actionSaveSpecialistSlots($id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $s = \common\models\Specialist::findOne((int)$id);
        if (!$s) throw new NotFoundHttpException('Спеціаліста не знайдено');

        $data  = Yii::$app->request->post();
        $slots = $data['slots'] ?? [];
        $this->saveSpecialistSlots($s->id, $slots);

        return ['success' => true];
    }

    public function actionSpecialistWeekSchedule(int $id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $s = \common\models\Specialist::findOne($id);
        if (!$s) throw new NotFoundHttpException('Спеціаліста не знайдено');

        // Weekly recurring template
        $schedRows = Yii::$app->db->createCommand(
            'SELECT day_of_week, time_slot FROM specialist_schedule WHERE specialist_id = :id ORDER BY day_of_week, time_slot',
            [':id' => $id]
        )->queryAll();

        $template = []; // dow => [time, ...]
        foreach ($schedRows as $r) {
            $template[(int)$r['day_of_week']][] = $r['time_slot'];
        }

        // Week to show (from=YYYY-MM-DD, default = current week Monday)
        $fromStr = Yii::$app->request->get('from', '');
        if ($fromStr && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromStr)) {
            $monday = new \DateTime($fromStr);
        } else {
            $monday = new \DateTime();
            $dow = (int)$monday->format('N'); // 1=Mon
            $monday->modify('-' . ($dow - 1) . ' days');
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = clone $monday;
            $d->modify("+{$i} days");
            $dateStr = $d->format('Y-m-d');
            $dowNum  = (int)$d->format('N'); // 1-7 Mon-Sun → we store 1-7, 0=Sun fix below
            $dowKey  = $dowNum === 7 ? 0 : $dowNum; // 0=Sun like JS
            $days[] = ['date' => $dateStr, 'dow' => $dowKey];
        }

        // Blocked dates in this week
        $dates    = array_column($days, 'date');
        $placeholders = implode(',', array_map(fn($d) => "'$d'", $dates));
        $blocked  = Yii::$app->db->createCommand(
            "SELECT block_date FROM specialist_day_block WHERE specialist_id = :id AND block_date IN ($placeholders)",
            [':id' => $id]
        )->queryColumn();

        // Booked slots in this week
        $bookedRows = Yii::$app->db->createCommand(
            "SELECT appointment_date, appointment_time FROM appointment
             WHERE specialist_id = :id AND appointment_date IN ($placeholders)
             AND status NOT IN ('cancelled')",
            [':id' => $id]
        )->queryAll();
        $bookedSet = [];
        foreach ($bookedRows as $b) {
            $bookedSet[$b['appointment_date'] . '_' . $b['appointment_time']] = true;
        }

        $result = [];
        foreach ($days as $day) {
            $date    = $day['date'];
            $dow     = $day['dow'];
            $isBlocked = in_array($date, $blocked);
            $times   = $template[$dow] ?? [];

            $slots = [];
            foreach ($times as $t) {
                $key = $date . '_' . $t;
                $slots[] = [
                    'time'   => $t,
                    'status' => $isBlocked ? 'blocked' : (isset($bookedSet[$key]) ? 'booked' : 'available'),
                ];
            }

            $result[] = [
                'date'    => $date,
                'dow'     => $dow,
                'blocked' => $isBlocked,
                'slots'   => $slots,
            ];
        }

        return [
            'week'     => $result,
            'template' => $template,
            'blocked_dates' => $blocked,
        ];
    }

    public function actionBlockSpecialistDate(int $id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $s = \common\models\Specialist::findOne($id);
        if (!$s) throw new NotFoundHttpException('Спеціаліста не знайдено');

        $date = Yii::$app->request->post('date', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Невірний формат дати'];
        }

        Yii::$app->db->createCommand()->upsert('specialist_day_block', [
            'specialist_id' => $id,
            'block_date'    => $date,
        ], false)->execute();

        return ['success' => true, 'date' => $date];
    }

    public function actionUnblockSpecialistDate(int $id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $date = Yii::$app->request->post('date', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Невірний формат дати'];
        }

        Yii::$app->db->createCommand()->delete('specialist_day_block', [
            'specialist_id' => $id,
            'block_date'    => $date,
        ])->execute();

        return ['success' => true, 'date' => $date];
    }

    private function saveSpecialistSlots(int $specialistId, array $slots): void
    {
        $db = Yii::$app->db;
        $db->createCommand()->delete('specialist_schedule', ['specialist_id' => $specialistId])->execute();

        foreach ($slots as $dayOfWeek => $times) {
            foreach ((array)$times as $time) {
                $time = trim((string)$time);
                if ($time !== '') {
                    $db->createCommand()->insert('specialist_schedule', [
                        'specialist_id' => $specialistId,
                        'day_of_week'   => (int)$dayOfWeek,
                        'time_slot'     => $time,
                    ])->execute();
                }
            }
        }
    }

    private function specialistRow(array $r): array
    {
        $cats   = $r['categories'] ? array_filter(array_map('trim', explode(',', $r['categories']))) : [];
        $parts  = preg_split('/\s+/', trim($r['name']));
        $initials = mb_strtoupper(
            mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1)
        );

        $validTypes = ['psychologist', 'therapist', 'coach'];
        $type = in_array($r['type'], $validTypes) ? $r['type'] : 'psychologist';

        return [
            'id'               => (int)$r['id'],
            'name'             => $r['name'],
            'type'             => $type,
            'type_name'        => $this->specialistTypeName($type),
            'bio'              => $r['bio'] ?? '',
            'experience_years' => (int)$r['experience_years'],
            'rating'           => $r['computed_rating'] ? round((float)$r['computed_rating'], 1) : 0.0,
            'reviews_count'    => (int)$r['reviews_count'],
            'sessions_count'   => (int)$r['sessions_count'],
            'categories'       => array_values($cats),
            'categories_str'   => $r['categories'] ?? '',
            'avatar_initials'  => $r['avatar_initials'] ?: $initials,
            'price'            => (float)$r['price'],
            'is_active'        => (bool)$r['is_active'],
            'created_at'       => $r['created_at'],
        ];
    }

    private function specialistRowById(int $id): array
    {
        $row = Yii::$app->db->createCommand("
            SELECT s.id, s.name, s.type, s.bio, s.experience_years,
                   s.categories, s.avatar_initials, s.price,
                   s.is_active, s.created_at,
                   (SELECT ROUND(AVG(r.rating)::numeric, 1) FROM specialist_review r WHERE r.specialist_id = s.id) AS computed_rating,
                   (SELECT COUNT(*) FROM specialist_review r WHERE r.specialist_id = s.id) AS reviews_count,
                   (SELECT COUNT(*) FROM appointment a WHERE a.specialist_name = s.name) AS sessions_count
            FROM specialist s WHERE s.id = :id
        ", [':id' => $id])->queryOne();
        return $this->specialistRow($row);
    }

    private function specialistTypeName(string $type): string
    {
        return match($type) {
            'psychologist' => 'Психолог',
            'therapist'    => 'Психотерапевт',
            'coach'        => 'Коуч',
            default        => $type,
        };
    }

    private function userRow(array $r): array
    {
        $sessions = (int)$r['sessions_left'];
        return [
            'id'                 => (int)$r['id'],
            'first_name'         => $r['first_name'] ?? '',
            'last_name'          => $r['last_name'] ?? '',
            'name'               => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
            'email'              => $r['email'] ?? '',
            'phone'              => $r['phone'] ?? '',
            'avatar_url'         => $r['avatar_url'] ?? null,
            'status'             => (int)$r['status'],
            'is_active'          => (int)$r['status'] === 10,
            'is_admin'           => (bool)$r['is_admin'],
            'company_id'         => $r['company_id'] ? (int)$r['company_id'] : null,
            'company_name'       => $r['company_name'] ?? null,
            'total_appointments' => (int)$r['total_appointments'],
            'sessions_left'      => max(0, $sessions),
            'created_at'         => $r['created_at'],
        ];
    }

    private function companyRow(Company $c): array
    {
        return [
            'id'                     => $c->id,
            'code'                   => $c->code,
            'name'                   => $c->name,
            'logo_url'               => $c->logo_url,
            'primary_color'          => $c->primary_color,
            'secondary_color'        => $c->secondary_color,
            'accent_color'           => $c->accent_color,
            'free_sessions_per_user' => (int)$c->free_sessions_per_user,
            'is_active'              => (bool)$c->is_active,
            'created_at'             => $c->created_at,
            'total_users'            => 0,
            'total_appointments'     => 0,
            'total_revenue'          => 0.0,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // Admin Appointments
    // ══════════════════════════════════════════════════════════════

    public function actionAdminAppointments()
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $request = Yii::$app->request;
        $search  = $request->get('search', '');
        $status  = $request->get('status', '');
        $page    = max(1, (int)$request->get('page', 1));
        $perPage = 15;
        $offset  = ($page - 1) * $perPage;

        $where  = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = "(u.first_name ILIKE :s OR u.last_name ILIKE :s OR u.email ILIKE :s OR a.specialist_name ILIKE :s)";
            $params[':s'] = '%' . $search . '%';
        }
        if ($status !== '' && $status !== 'all') {
            $where[] = "a.status = :status";
            $params[':status'] = $status;
        }

        $whereStr = implode(' AND ', $where);

        $total = (int)Yii::$app->db->createCommand("
            SELECT COUNT(*) FROM appointment a
            LEFT JOIN \"user\" u ON u.id = a.user_id
            LEFT JOIN company c ON c.id = u.company_id
            WHERE $whereStr
        ", $params)->queryScalar();

        $rows = Yii::$app->db->createCommand("
            SELECT
                a.id, a.user_id, a.specialist_name, a.specialist_type,
                a.appointment_date, a.appointment_time,
                a.status, a.payment_status, a.notes, a.price, a.created_at,
                COALESCE(u.first_name || ' ' || u.last_name, 'Видалений') AS user_name,
                u.email AS user_email,
                COALESCE(c.name, '') AS company_name
            FROM appointment a
            LEFT JOIN \"user\" u ON u.id = a.user_id
            LEFT JOIN company c ON c.id = u.company_id
            WHERE $whereStr
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT $perPage OFFSET $offset
        ", $params)->queryAll();

        return [
            'items'    => array_map(fn($r) => $this->appointmentRow($r), $rows),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int)ceil($total / $perPage),
        ];
    }

    public function actionCreateAdminAppointment()
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $data = Yii::$app->request->post();

        $a = new \common\models\Appointment();
        $a->user_id          = (int)($data['user_id'] ?? 0);
        $a->specialist_name  = trim($data['specialist_name'] ?? '');
        $a->specialist_type  = $data['specialist_type'] ?? 'psychologist';
        $a->appointment_date = $data['appointment_date'] ?? '';
        $a->appointment_time = $data['appointment_time'] ?? '';
        $a->status           = $data['status'] ?? \common\models\Appointment::STATUS_PENDING;
        $a->payment_status   = $data['payment_status'] ?? \common\models\Appointment::PAYMENT_UNPAID;
        $a->notes            = $data['notes'] ?? '';
        $a->price            = (float)($data['price'] ?? 0);

        if (isset($data['specialist_id'])) {
            $a->specialist_id = (int)$data['specialist_id'];
        }

        if (!$a->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $a->getErrors()];
        }
        if (!$a->save()) {
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Не вдалося створити запис'];
        }

        return ['success' => true, 'appointment' => $this->appointmentRowById($a->id)];
    }

    public function actionUpdateAdminAppointment($id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $a = \common\models\Appointment::findOne((int)$id);
        if (!$a) throw new \yii\web\NotFoundHttpException('Запис не знайдено');

        $data = Yii::$app->request->post();

        if (isset($data['status']))         $a->status         = $data['status'];
        if (isset($data['payment_status'])) $a->payment_status = $data['payment_status'];
        if (isset($data['notes']))          $a->notes          = $data['notes'];
        if (isset($data['appointment_date'])) $a->appointment_date = $data['appointment_date'];
        if (isset($data['appointment_time'])) $a->appointment_time = $data['appointment_time'];
        if (isset($data['price']))          $a->price          = (float)$data['price'];

        if (!$a->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $a->getErrors()];
        }
        if (!$a->save()) {
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Не вдалося оновити запис'];
        }

        return ['success' => true, 'appointment' => $this->appointmentRowById($a->id)];
    }

    public function actionDeleteAdminAppointment($id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $a = \common\models\Appointment::findOne((int)$id);
        if (!$a) throw new \yii\web\NotFoundHttpException('Запис не знайдено');

        $a->delete();
        return ['success' => true];
    }

    private function appointmentRow(array $r): array
    {
        return [
            'id'               => (int)$r['id'],
            'user_id'          => (int)$r['user_id'],
            'user_name'        => $r['user_name'] ?? '',
            'user_email'       => $r['user_email'] ?? '',
            'company_name'     => $r['company_name'] ?? '',
            'specialist_name'  => $r['specialist_name'] ?? '',
            'specialist_type'  => $r['specialist_type'] ?? '',
            'appointment_date' => $r['appointment_date'] ?? '',
            'appointment_time' => $r['appointment_time'] ?? '',
            'status'           => $r['status'] ?? 'pending',
            'payment_status'   => $r['payment_status'] ?? 'unpaid',
            'notes'            => $r['notes'] ?? '',
            'price'            => (float)$r['price'],
            'created_at'       => $r['created_at'],
        ];
    }

    private function appointmentRowById(int $id): array
    {
        $row = Yii::$app->db->createCommand("
            SELECT
                a.id, a.user_id, a.specialist_name, a.specialist_type,
                a.appointment_date, a.appointment_time,
                a.status, a.payment_status, a.notes, a.price, a.created_at,
                COALESCE(u.first_name || ' ' || u.last_name, 'Видалений') AS user_name,
                u.email AS user_email,
                COALESCE(c.name, '') AS company_name
            FROM appointment a
            LEFT JOIN \"user\" u ON u.id = a.user_id
            LEFT JOIN company c ON c.id = u.company_id
            WHERE a.id = :id
        ", [':id' => $id])->queryOne();
        return $this->appointmentRow($row);
    }

    // ══════════════════════════════════════════════════════════════
    // Admin Categories
    // ══════════════════════════════════════════════════════════════

    public function actionAdminCategories()
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $search = Yii::$app->request->get('search', '');
        $where  = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = "c.name ILIKE :s";
            $params[':s'] = '%' . $search . '%';
        }
        $whereStr = implode(' AND ', $where);

        $rows = Yii::$app->db->createCommand('
            WITH spec_cat AS (
                SELECT s.id   AS specialist_id,
                       s.name AS specialist_name,
                       TRIM(unnest(string_to_array(s.categories, \',\'))) AS cat_name
                FROM specialist s
                WHERE s.categories IS NOT NULL AND s.categories <> \'\'
            )
            SELECT
                c.id, c.name, c.status, c.created_at,
                COALESCE(sc.cnt, 0) AS specialist_count,
                COALESCE(ac.cnt, 0) AS sessions_count
            FROM "category" c
            LEFT JOIN (
                SELECT cat_name, COUNT(*) AS cnt FROM spec_cat GROUP BY cat_name
            ) sc ON sc.cat_name = c.name
            LEFT JOIN (
                SELECT sc.cat_name, COUNT(*) AS cnt
                FROM spec_cat sc
                JOIN appointment a ON a.specialist_name = sc.specialist_name
                GROUP BY sc.cat_name
            ) ac ON ac.cat_name = c.name
            WHERE ' . $whereStr . '
            ORDER BY c.status ASC, c.name ASC
        ', $params)->queryAll();

        return array_map(fn($r) => $this->categoryRow($r), $rows);
    }

    public function actionCreateCategory()
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $data = Yii::$app->request->post();
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            Yii::$app->response->statusCode = 422;
            return ['errors' => ['name' => ['Назва обов\'язкова']]];
        }

        $cat = new \common\models\Category();
        $cat->name   = $name;
        $cat->status = $data['status'] ?? 'active';

        if (!$cat->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $cat->getErrors()];
        }
        if (!$cat->save()) {
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Не вдалося створити категорію'];
        }

        return ['success' => true, 'category' => $this->categoryRowById($cat->id)];
    }

    public function actionUpdateCategory($id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $cat = \common\models\Category::findOne((int)$id);
        if (!$cat) throw new \yii\web\NotFoundHttpException('Категорію не знайдено');

        $data    = Yii::$app->request->post();
        $oldName = $cat->name;

        if (isset($data['name']))   $cat->name   = trim($data['name']);
        if (isset($data['status'])) $cat->status = $data['status'];

        if (!$cat->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $cat->getErrors()];
        }
        if (!$cat->save()) {
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Не вдалося оновити категорію'];
        }

        // Rename in specialist.categories CSV if name changed
        if ($oldName !== $cat->name) {
            $this->renameCategoryInSpecialists($oldName, $cat->name);
        }

        return ['success' => true, 'category' => $this->categoryRowById($cat->id)];
    }

    public function actionDeleteCategory($id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $cat = \common\models\Category::findOne((int)$id);
        if (!$cat) throw new \yii\web\NotFoundHttpException('Категорію не знайдено');

        $name = $cat->name;
        $cat->delete();

        // Remove from specialist.categories CSV
        $this->removeCategoryFromSpecialists($name);

        return ['success' => true];
    }

    private function renameCategoryInSpecialists(string $oldName, string $newName): void
    {
        $specialists = Yii::$app->db->createCommand(
            "SELECT id, categories FROM specialist WHERE categories ILIKE :p",
            [':p' => '%' . $oldName . '%']
        )->queryAll();

        foreach ($specialists as $s) {
            $cats = array_map('trim', explode(',', $s['categories']));
            $cats = array_map(fn($c) => $c === $oldName ? $newName : $c, $cats);
            Yii::$app->db->createCommand()->update(
                'specialist',
                ['categories' => implode(', ', $cats)],
                ['id' => $s['id']]
            )->execute();
        }
    }

    private function removeCategoryFromSpecialists(string $name): void
    {
        $specialists = Yii::$app->db->createCommand(
            "SELECT id, categories FROM specialist WHERE categories ILIKE :p",
            [':p' => '%' . $name . '%']
        )->queryAll();

        foreach ($specialists as $s) {
            $cats = array_filter(
                array_map('trim', explode(',', $s['categories'])),
                fn($c) => $c !== $name
            );
            Yii::$app->db->createCommand()->update(
                'specialist',
                ['categories' => implode(', ', $cats)],
                ['id' => $s['id']]
            )->execute();
        }
    }

    private function categoryRow(array $r): array
    {
        return [
            'id'               => (int)$r['id'],
            'name'             => $r['name'],
            'status'           => $r['status'],
            'specialist_count' => (int)$r['specialist_count'],
            'sessions_count'   => (int)$r['sessions_count'],
            'created_at'       => $r['created_at'],
        ];
    }

    private function categoryRowById(int $id): array
    {
        $row = Yii::$app->db->createCommand('
            SELECT
                c.id, c.name, c.status, c.created_at,
                (SELECT COUNT(*) FROM specialist s
                 WHERE s.categories ILIKE \'%\' || c.name || \'%\') AS specialist_count,
                (SELECT COUNT(*) FROM appointment a
                 JOIN specialist s ON a.specialist_name = s.name
                 WHERE s.categories ILIKE \'%\' || c.name || \'%\') AS sessions_count
            FROM "category" c WHERE c.id = :id
        ', [':id' => $id])->queryOne();
        return $this->categoryRow($row);
    }

    /* ═══════════════════════════════════════════
       PAYMENTS
    ═══════════════════════════════════════════ */

    public function actionAdminPayments()
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $search = Yii::$app->request->get('search', '');
        $status = Yii::$app->request->get('status', '');
        $page   = max(1, (int)Yii::$app->request->get('page', 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[]      = "((u.first_name || ' ' || u.last_name) ILIKE :s OR u.email ILIKE :s OR p.transaction_id ILIKE :s)";
            $params[':s'] = '%' . $search . '%';
        }
        if ($status !== '' && $status !== 'all') {
            $where[]       = 'p.status = :st';
            $params[':st'] = $status;
        }
        $whereStr = implode(' AND ', $where);

        $stats = Yii::$app->db->createCommand(
            'SELECT
                COUNT(*)                                                           AS total_count,
                COALESCE(SUM(CASE WHEN p.status = \'completed\' THEN p.amount ELSE 0 END), 0) AS paid_amount,
                COALESCE(SUM(CASE WHEN p.status = \'pending\'   THEN p.amount ELSE 0 END), 0) AS pending_amount
            FROM payment p'
        )->queryOne();

        $total = (int)Yii::$app->db->createCommand(
            'SELECT COUNT(*) FROM payment p
             LEFT JOIN "user" u ON u.id = p.user_id
             WHERE ' . $whereStr, $params
        )->queryScalar();

        $rows = Yii::$app->db->createCommand(
            'SELECT
                p.id, p.user_id, p.appointment_id, p.amount, p.currency,
                p.status, p.payment_method, p.transaction_id, p.notes,
                p.created_at, p.updated_at,
                (u.first_name || \' \' || u.last_name) AS user_name,
                u.email AS user_email,
                COALESCE(u.company, c.name, \'\') AS company_name,
                a.specialist_name, a.appointment_date, a.appointment_time
            FROM payment p
            LEFT JOIN "user" u ON u.id = p.user_id
            LEFT JOIN company c ON c.id = u.company_id
            LEFT JOIN appointment a ON a.id = p.appointment_id
            WHERE ' . $whereStr . '
            ORDER BY p.created_at DESC
            LIMIT :lim OFFSET :off',
            array_merge($params, [':lim' => $limit, ':off' => $offset])
        )->queryAll();

        return [
            'payments'      => array_map(fn($r) => $this->paymentRow($r), $rows),
            'total'         => $total,
            'page'          => $page,
            'pages'         => max(1, (int)ceil($total / $limit)),
            'paid_amount'   => (float)$stats['paid_amount'],
            'pending_amount'=> (float)$stats['pending_amount'],
            'total_count'   => (int)$stats['total_count'],
        ];
    }

    public function actionUpdateAdminPayment(int $id)
    {
        Yii::$app->response->format = 'json';
        $this->requireAdmin();

        $payment = \common\models\Payment::findOne($id);
        if (!$payment) throw new NotFoundHttpException('Payment not found');

        $data = Yii::$app->request->post();
        $validStatuses = ['pending', 'completed', 'failed', 'refunded'];

        if (isset($data['status']) && in_array($data['status'], $validStatuses)) {
            $payment->status = $data['status'];
        }
        if (isset($data['notes'])) {
            $payment->notes = $data['notes'];
        }

        if (!$payment->save()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $payment->errors];
        }

        $row = Yii::$app->db->createCommand(
            'SELECT
                p.id, p.user_id, p.appointment_id, p.amount, p.currency,
                p.status, p.payment_method, p.transaction_id, p.notes,
                p.created_at, p.updated_at,
                (u.first_name || \' \' || u.last_name) AS user_name,
                u.email AS user_email,
                COALESCE(u.company, c.name, \'\') AS company_name,
                a.specialist_name, a.appointment_date, a.appointment_time
            FROM payment p
            LEFT JOIN "user" u ON u.id = p.user_id
            LEFT JOIN company c ON c.id = u.company_id
            LEFT JOIN appointment a ON a.id = p.appointment_id
            WHERE p.id = :id',
            [':id' => $id]
        )->queryOne();

        return ['payment' => $this->paymentRow($row)];
    }

    private function paymentRow(?array $r): array
    {
        if (!$r) return [];
        $methodMap = [
            'card'          => 'Картка',
            'ua_pay'        => 'UA Pay',
            'bank_transfer' => 'Банк. переказ',
        ];
        return [
            'id'               => (int)$r['id'],
            'user_id'          => (int)$r['user_id'],
            'appointment_id'   => $r['appointment_id'] ? (int)$r['appointment_id'] : null,
            'amount'           => (float)$r['amount'],
            'currency'         => $r['currency'] ?: 'UAH',
            'status'           => $r['status'],
            'payment_method'   => $r['payment_method'] ?: '',
            'payment_method_label' => $methodMap[$r['payment_method']] ?? $r['payment_method'],
            'transaction_id'   => $r['transaction_id'] ?: '',
            'notes'            => $r['notes'] ?: '',
            'created_at'       => (int)$r['created_at'],
            'user_name'        => $r['user_name'] ?: '',
            'user_email'       => $r['user_email'] ?: '',
            'company_name'     => $r['company_name'] ?: '',
            'specialist_name'  => $r['specialist_name'] ?: '',
            'appointment_date' => $r['appointment_date'] ?: '',
            'appointment_time' => $r['appointment_time'] ?: '',
        ];
    }
}
