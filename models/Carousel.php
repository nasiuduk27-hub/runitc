<?php
class Carousel {
    private $pdo;
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    public function getActiveCarousels() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM bis_media WHERE is_active = 1 ORDER BY sort_order ASC");
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('Carousel query failed: ' . $e->getMessage());
            return [];
        }
    }
}
