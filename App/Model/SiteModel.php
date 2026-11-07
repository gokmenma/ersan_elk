<?php

namespace App\Model;

use App\Core\SiteDb;

class SiteModel extends SiteDb
{
    protected $table;

    public function __construct(?string $table = null)
    {
        parent::__construct();
        if ($table !== null) {
            $this->table = $table;
        }
    }

    protected function sayac(string $sql, array $bind = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bind);
        return (int) $stmt->fetchColumn();
    }
}
