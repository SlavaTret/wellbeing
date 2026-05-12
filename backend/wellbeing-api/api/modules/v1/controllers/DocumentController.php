<?php

namespace api\modules\v1\controllers;

use common\models\Document;
use common\services\CreatioSyncService;
use Yii;
use yii\rest\Controller;
use yii\web\UploadedFile;

class DocumentController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authentication'] = ['class' => \yii\filters\auth\HttpBearerAuth::class];
        $behaviors['access'] = [
            'class' => \yii\filters\AccessControl::class,
            'rules' => [['allow' => true, 'roles' => ['@']]],
        ];
        return $behaviors;
    }

    /** GET /v1/document — list current user's documents */
    public function actionIndex()
    {
        Yii::$app->response->format = 'json';
        $userId = Yii::$app->user->id;

        $items = Document::find()
            ->where(['user_id' => $userId])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        return [
            'items' => array_map(fn($d) => $this->formatDoc($d), $items),
        ];
    }

    /** POST /v1/document/upload — upload a new document */
    public function actionUpload()
    {
        Yii::$app->response->format = 'json';
        $userId = Yii::$app->user->id;

        $file = UploadedFile::getInstanceByName('file');
        if (!$file) {
            Yii::$app->response->statusCode = 400;
            return ['error' => 'Файл не отримано'];
        }

        $ext = strtolower($file->extension);
        if (!in_array($ext, Document::ALLOWED_TYPES)) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Дозволені формати: PDF, JPG, PNG, DOC, DOCX'];
        }

        if ($file->size > 10 * 1024 * 1024) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Файл занадто великий (макс. 10 МБ)'];
        }

        $dir = Yii::getAlias('@webroot/uploads/documents');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stored = 'doc_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path   = $dir . '/' . $stored;

        if (!$file->saveAs($path)) {
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Не вдалось зберегти файл'];
        }

        $doc = new Document([
            'user_id'       => $userId,
            'document_name' => $file->baseName . '.' . $ext,
            'file_url'      => '/api/uploads/documents/' . $stored,
            'file_type'     => $ext === 'jpeg' ? 'jpg' : $ext,
            'file_size'     => $file->size,
        ]);

        if (!$doc->save()) {
            @unlink($path);
            Yii::$app->response->statusCode = 422;
            return ['errors' => $doc->getErrors()];
        }

        // Sync to Creatio ContactFile asynchronously (best-effort, does not block response)
        try {
            $user = Yii::$app->user->identity;
            (new CreatioSyncService())->syncDocument($doc, $user);
        } catch (\Throwable $e) {}

        Yii::$app->response->statusCode = 201;
        return $this->formatDoc($doc);
    }

    /** DELETE /v1/document/<id> */
    public function actionDelete($id)
    {
        Yii::$app->response->format = 'json';
        $doc = Document::findOne(['id' => $id, 'user_id' => Yii::$app->user->id]);
        if (!$doc) {
            Yii::$app->response->statusCode = 404;
            return ['error' => 'Document not found'];
        }

        // Remove from Creatio (best-effort)
        try {
            (new CreatioSyncService())->deleteDocument($doc);
        } catch (\Throwable $e) {}

        // Remove the local file
        if ($doc->file_url && str_contains($doc->file_url, '/uploads/documents/')) {
            $urlPath   = preg_replace('#^/api#', '', parse_url($doc->file_url, PHP_URL_PATH));
            $localPath = Yii::getAlias('@webroot') . $urlPath;
            if (file_exists($localPath)) {
                @unlink($localPath);
            }
        }

        $doc->delete();
        Yii::$app->response->statusCode = 204;
        return null;
    }

    private function formatDoc(Document $d): array
    {
        return [
            'id'        => $d->id,
            'name'      => $d->document_name,
            'url'       => $d->file_url,
            'type'      => strtolower($d->file_type),
            'size'      => (int)$d->file_size,
            'size_label'=> $this->humanSize((int)$d->file_size),
            'date'      => $this->dateLabel((int)$d->created_at),
        ];
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024) . ' KB';
        return $bytes . ' B';
    }

    private function dateLabel(int $ts): string
    {
        $months = ['', 'січ.', 'лют.', 'бер.', 'квіт.', 'трав.', 'черв.', 'лип.', 'серп.', 'вер.', 'жовт.', 'лист.', 'груд.'];
        return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    }
}
