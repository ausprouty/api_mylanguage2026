<?php
namespace App\Repositories;

use App\Services\Database\DatabaseService;

class JVideoContentRepository {
    private $database;

    public function __construct(DatabaseService $database) {
        $this->database = $database;
    }

    public function getAllJesusVideoSegments() {
        $sql = "SELECT * FROM jesus_video_segments";
        return $this->database->fetchAll($sql);
    }
}
