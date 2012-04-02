<?php

namespace ZfcUser\Service;

use Zend\Authentication\AuthenticationService,
    Zend\Form\Form,
    Zend\EventManager\ListenerAggregate,
    Zend\Mail\Transport,
    Zend\Mail\Message,
    DateTime,
    ZfcUser\Util\Password,
    ZfcUser\Model\UserActivationMapperInterface,
    ZfcUser\Model\UserInterface,
    ZfcUser\Model\UserMapperInterface,
    ZfcUser\Model\UserMetaMapperInterface,
    ZfcUser\Module as ZfcUser,
    ZfcBase\EventManager\EventProvider,
    ZfcBase\Util;

class User extends EventProvider
{
    /**
     * @var UserMapperInterface
     */
    protected $userMapper;

    /**
     * @var UserMetaMapperInterface
     */
    protected $userMetaMapper;

    /**
     * @var UserActivationMapperInterface
     */
    protected $userActivationMapper;

    /**
     * @var mixed
     */
    protected $resolvedIdentity;

    /**
     * @var authService
     */
    protected $authService;

    /**
     * @var \Zend\Di\Locator
     */
    protected $locator;

    /**
     * @var \Zend\Mail\Transport
     */
    protected $mailTransport;

    /**
     * @var \Zend\Mail\Message
     */
    protected $message;

    public function updateMeta($key, $value)
    {
        $user = $this->getAuthService()->getIdentity();
        if (!$userMeta = $this->userMetaMapper->get($user->getUserId(), $key)) {
            $class = ZfcUser::getOption('usermeta_model_class');
            $userMeta = new $class;
            $userMeta->setUser($user);
            $userMeta->setMetaKey($key);
            $userMeta->setMeta($value);
            $this->userMetaMapper->add($userMeta);
        }
        if (!$userMeta->getUser()) {
            $userMeta->setUser($user);
        }
        $userMeta->setMeta($value);
        $this->userMetaMapper->update($userMeta);
    }

    /**
     * createFromForm
     *
     * @param Form $form
     * @return ZfcUser\Model\User
     */
    public function createFromForm(Form $form)
    {
        $class = ZfcUser::getOption('user_model_class');
        $user = new $class;
        $user->setEmail($form->getValue('email'))
             ->setPassword(Password::hash($form->getValue('password')))
             ->setRegisterIp($_SERVER['REMOTE_ADDR'])
             ->setRegisterTime(new DateTime('now'))
             ->setEnabled(true);
        if (ZfcUser::getOption('require_activation')) {
            $user->setActive(false);
        } else {
            $user->setActive(true);
        }
        if (ZfcUser::getOption('enable_username')) {
            $user->setUsername($form->getValue('username'));
        }
        if (ZfcUser::getOption('enable_display_name')) {
            $user->setDisplayName($form->getValue('display_name'));
        }
        $this->events()->trigger(__FUNCTION__, $this, array('user' => $user, 'form' => $form));
        $this->userMapper->persist($user);
        return $user;
    }

    /**
     * @param $userId
     * @param $code
     * @return bool
     */
    public function activateUser($userId, $code)
    {
        if (!ZfcUser::getOption('require_activation')) {
            return false;
        }

        $user = $this->userMapper->findById($userId);
        if (!$user) {
            return false;
        }

        $expectedCode = $this->generateActivationCode($user->getEmail());
        if ($expectedCode != $code) {
            return false;
        }

        $user->setActive(true);
        $this->userMapper->persist($user);

        return true;
    }

    /**
     * @param \ZfcUser\Model\UserInterface $user
     * @return bool
     */
    public function sendConfirmation(UserInterface $user)
    {
        $template = ZfcUser::getOption('email_activation_body');
        $renderer = $this->getLocator()->get('Zend\View\Renderer\PhpRenderer');
        $viewParams = array('code' => Util\String::getRandomBytes(16),
                            'user' => $user->getUserId());
        $body = $renderer->render($template, $viewParams);

        $message = $this->message->setTo($user->getEmail())->setBody($body);
        try {
            $this->mailTransport->send($message);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Get a user entity by their username
     *
     * @param string $username
     * @return ZfcUser\Model\User
     */
    public function getByUsername($username)
    {
        return $this->userMapper->findByUsername($username);
    }

    /**
     * setUserMapper
     *
     * @param UserMapperInterface $userMapper
     * @return User
     */
    public function setUserMapper(UserMapperInterface $userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * setUserMetaMapper
     *
     * @param UserMetaMapperInterface $userMetaMapper
     * @return User
     */
    public function setUserMetaMapper(UserMetaMapperInterface $userMetaMapper)
    {
        $this->userMetaMapper = $userMetaMapper;
        return $this;
    }

    /**
     * setUserActivationMapper
     *
     * @param UserActivationMapperInterface $userActivationMapper
     * @return User
     */
    public function setUserActivationMapper(UserActivationMapperInterface $userActivationMapper)
    {
        $this->userActivationMapper = $userActivationMapper;
        return $this;
    }

    /**
     * getAuthService
     *
     * @return AuthenticationService
     */
    public function getAuthService()
    {
        if (null === $this->authService) {
            $this->authService = new AuthenticationService;
        }
        return $this->authService;
    }

    /**
     * setAuthenticationService
     *
     * @param AuthenticationService $authService
     * @return User
     */
    public function setAuthService(AuthenticationService $authService)
    {
        $this->authService = $authService;
        return $this;
    }

    /**
     * Retrieve locator instance
     *
     * @return \Zend\Di\Locator
     */
    public function getLocator()
    {
        return $this->locator;
    }

    /**
     * Set locator instance
     *
     * @param  \Zend\Di\Locator $locator
     * @return User
     */
    public function setLocator(\Zend\Di\Locator $locator)
    {
        $this->locator = $locator;
        return $this;
    }

    /**
     * Set mail transport instance
     *
     * @param \Zend\Mail\Transport $mailTransport
     * @return User
     */
    public function setMailTransport(Transport $mailTransport)
    {
        $this->mailTransport = $mailTransport;
        return $this;
    }

    /**
     * Set mail message instance
     *
     * @param \Zend\Mail\Message $message
     * @return User
     */
    public function setMessage(\Zend\Mail\Message $message)
    {
        $this->message = $message;
        return $this;
    }
}
