<?php

require_once BASE_PATH.'/models/SystemSettings.php';

class SystemSettingsController
{
    private SystemSettings $model;

    public function __construct(PDO $pdoRun)
    {
        $this->model = new SystemSettings($pdoRun);
    }

    public function getModel(): SystemSettings
    {
        return $this->model;
    }

    public function handleApi(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        Csrf::verify();

        $action = $_POST['action'] ?? '';

        try {
            $result = match ($action) {
                'update_setting' => $this->updateSetting(),
                'reset_setting' => $this->resetSetting(),
                default => ['error' => 'Unknown action'],
            };
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function updateSetting(): array
    {
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '';
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $error = $this->model->validate($key, $value);
        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $this->model->set($key, $value, $userId);

        return ['success' => true, 'message' => 'Setting berhasil disimpan'];
    }

    private function resetSetting(): array
    {
        $key = $_POST['key'] ?? '';
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $this->model->resetToDefault($key, $userId);
        $default = $this->model->getDefaultValue($key);

        return ['success' => true, 'message' => 'Setting dikembalikan ke default', 'default_value' => $default];
    }
}
