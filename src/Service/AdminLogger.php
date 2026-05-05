<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class AdminLogger
{
    public function __construct(private Connection $connection) {}

    public function log(string $action, string $entity, string $performedBy, ?string $details = null): void
    {
        $this->connection->insert('admin_log', [
            'action'       => $action,
            'entity'       => $entity,
            'details'      => $details,
            'performed_by' => $performedBy,
            'created_at'   => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByDateRange(?string $from, ?string $to): array
    {
        $sql    = 'SELECT * FROM admin_log WHERE 1=1';
        $params = [];
        $types  = [];

        if ($from) {
            $sql .= ' AND created_at >= :from';
            $params['from'] = $from . ' 00:00:00';
            $types['from']  = ParameterType::STRING;
        }

        if ($to) {
            $sql .= ' AND created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
            $types['to']  = ParameterType::STRING;
        }

        $sql .= ' ORDER BY created_at DESC';

        return $this->connection->fetchAllAssociative($sql, $params, $types);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM admin_log ORDER BY created_at DESC LIMIT :limit',
            ['limit' => $limit],
            ['limit' => ParameterType::INTEGER]
        );
    }
}