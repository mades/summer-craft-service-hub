<?php

namespace SummerCraft\Service\Email\Storage;

use SummerCraft\Service\Database\Config\RelationalStorageConfig;
use SummerCraft\Service\Database\RelationalStorage;
use SummerCraft\Service\Email\MailerConfig;
use SummerCraft\Core\ComponentManaging\RequestScope;

/**
 * @extends RelationalStorage<MailerRecord>
 */
class MailerRepository extends RelationalStorage
{
    public function __construct(RequestScope $scope, MailerConfig $mailerConfig)
    {
        $config = new RelationalStorageConfig(
            MailerRecord::class,
            $mailerConfig->databaseHandlerServiceName,
            'mailer',
            'id',
            ['id']
        );
        parent::__construct($scope, $config);
    }

    public function newInstance(): MailerRecord
    {
        return MailerRecord::emptyRecord();
    }

    /**
     * @return MailerRecord[]
     */
    public function findWithId(int $id): array
    {
        return $this->find(
            $id ? ['id' => $id] : [],
            [
                'limit' => ['count' => 1],
            ]
        );
    }

    /**
     * @return MailerRecord[]
     */
    public function findNext(int $count): array
    {
        return $this->find(
            [],
            [
                'order' => ['updated_at' => 'asc'],
                'limit' => ['count' => $count],
            ]
        );
    }

    public function findOneLastToEmail(string $email): ?MailerRecord
    {
        return $this->findOne(
            ['email' => $email],
            ['order' => ['id' => 'desc']]
        );
    }
}
