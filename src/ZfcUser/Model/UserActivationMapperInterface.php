<?php
namespace ZfcUser\Model;

use ZfcUser\Model\UserActivationInterface;

interface UserActivationMapperInterface
{
    public function persist(UserActivationInterface $userActivation);

    public function findByEmail($email);

    public function findByToken($token);
}