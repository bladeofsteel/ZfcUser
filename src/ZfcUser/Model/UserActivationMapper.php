<?php

namespace ZfcUser\Model\Mapper;

use ZfcBase\Mapper\DbMapperAbstract,
    ZfcUser\Module as ZfcUser,
    ZfcUser\Model\UserActivationInterface,
    ZfcUser\Model\UserActivationMapperInterface,
    ArrayObject;

class UserActivationMapper extends DbMapperAbstract implements UserActivationMapperInterface
{
    protected $tableName  = 'user_activation';
    protected $emailField = 'email';
    protected $tokenField = 'token';
    protected $idField    = 'id';

    public function persist(UserActivationInterface $userActivation)
    {
        $data = new ArrayObject($userActivation->toArray()); // or perhaps pass it by reference?
        $this->events()->trigger(__FUNCTION__ . '.pre', $this,
                                 array('data' => $data, 'userActivation' => $userActivation));
        $db = $this->getWriteAdapter();
        if ($userActivation->getId() > 0) {
            $db->update($this->getTableName(), (array)$data,
                        $db->quoteInto($this->idField . ' = ?', $userActivation->getId())
            );
        } else {
            $db->insert($this->getTableName(), (array)$data);
            $userActivationId = $db->lastInsertId();
            $userActivation->setId($userActivationId);
        }
        return $userActivation;
    }

    public function findByEmail($email)
    {
        $db = $this->getReadAdapter();
        $sql = $db->select()
                    ->from($this->getTableName())
                    ->where($this->emailField . ' = ?', $email);
        $this->events()->trigger(__FUNCTION__ . '.pre', $this, array('query' => $sql));
        $row = $db->fetchRow($sql);
        $userActivationModelClass = ZfcUser::getOption('user_activation_model_class');
        $userActivation = $userActivationModelClass::fromArray($row);
        $this->events()->trigger(__FUNCTION__ . '.post', $this,
                                 array('userActivation' => $userActivation, 'row' => $row));
        return $userActivation;
    }

    public function findByToken($token)
    {
        $db = $this->getReadAdapter();
        $sql = $db->select()
                    ->from($this->getTableName())
                    ->where($this->tokenField . ' = ?', $token);
        $this->events()->trigger(__FUNCTION__ . '.pre', $this, array('query' => $sql));
        $row = $db->fetchRow($sql);
        $userActivationModelClass = ZfcUser::getOption('user_activation_model_class');
        $userActivation = $userActivationModelClass::fromArray($row);
        $this->events()->trigger(__FUNCTION__ . '.post', $this,
                                 array('userActivation' => $userActivation, 'row' => $row));
        return $userActivation;
    }
}