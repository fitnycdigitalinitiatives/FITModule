<?php

namespace FITModule\Controller\Site;

use Doctrine\ORM\EntityManager;
use Omeka\Form\LoginForm;
use Omeka\Mvc\Exception\NotFoundException;
use Laminas\Authentication\AuthenticationService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Session\Container;
use Laminas\View\Model\ViewModel;

class SiteLoginController extends AbstractActionController
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var AuthenticationService
     */
    protected $auth;

    /**
     * @param EntityManager $entityManager
     * @param AuthenticationService $auth
     */
    public function __construct(EntityManager $entityManager, AuthenticationService $auth)
    {
        $this->entityManager = $entityManager;
        $this->auth = $auth;
    }
    public function loginAction()
    {
        $siteSettings = $this->siteSettings();
        if ($siteSettings->get('fit_module_loginpage', false) || $siteSettings->get('fit_module_restrictedsites', false)) {
            if ($this->auth->hasIdentity()) {
                return $this->redirect()->toRoute('top');
            }
            // Check if single sign-on is activated, else use standard form
            if (class_exists(\SingleSignOn\Controller\SsoController::class)) {
                $view = new ViewModel;
                // By default redirect to the site homepage
                $redirect_url = $this->currentSite()->siteUrl();
                $query = $this->params()->fromQuery();
                if ($query && array_key_exists('redirect_url', $query) && $query['redirect_url']) {
                    $redirect_url = $query['redirect_url'];
                } else {
                    $sessionManager = Container::getDefaultManager();
                    $session = $sessionManager->getStorage();
                    if ($session->offsetGet('redirect_url')) {
                        $redirect_url = $session->offsetGet('redirect_url');
                    }
                }
                $view->setVariable('redirect_url', $redirect_url);
                $view->setTemplate('fit-module/site/site-login/sso-login');
                return $view;
            } else {
                $form = $this->getForm(LoginForm::class);

                if ($this->getRequest()->isPost()) {
                    $data = $this->getRequest()->getPost();
                    $form->setData($data);
                    if ($form->isValid()) {
                        $sessionManager = Container::getDefaultManager();
                        $sessionManager->regenerateId();
                        $validatedData = $form->getData();
                        $adapter = $this->auth->getAdapter();
                        $adapter->setIdentity($validatedData['email']);
                        $adapter->setCredential($validatedData['password']);
                        $result = $this->auth->authenticate();
                        if ($result->isValid()) {
                            $this->messenger()->addSuccess('Successfully logged in'); // @translate
                            $eventManager = $this->getEventManager();
                            $eventManager->trigger('user.login', $this->auth->getIdentity());
                            $session = $sessionManager->getStorage();
                            if ($redirectUrl = $session->offsetGet('redirect_url')) {
                                return $this->redirect()->toUrl($redirectUrl);
                            }
                            return $this->redirect()->toRoute('top');
                        } else {
                            $this->messenger()->addError('Email or password is invalid'); // @translate
                        }
                    } else {
                        $this->messenger()->addFormErrors($form);
                    }
                }

                $view = new ViewModel;
                $view->setVariable('form', $form);
                return $view;
            }
        } else {
            throw new NotFoundException("Invalid Page");
        }
    }

    public function logoutAction()
    {
        $this->auth->clearIdentity();

        $sessionManager = Container::getDefaultManager();

        $eventManager = $this->getEventManager();
        $eventManager->trigger('user.logout');

        $sessionManager->destroy();

        $this->messenger()->addSuccess('Successfully logged out'); // @translate
        // Restricted sites should return to login page, else go to homepage
        if ($this->siteSettings()->get('fit_module_restrictedsites', false)) {
            return $this->redirect()->toRoute('site/site-login', ['site-slug' => $this->currentSite()->slug()]);
        } else {
            return $this->redirect()->toRoute('site', ['site-slug' => $this->currentSite()->slug()]);
        }
    }
}
