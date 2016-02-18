<?php

namespace LoginCidadao\CoreBundle\EventListener;

use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\SecurityContextInterface;
use LoginCidadao\CoreBundle\Exception\RedirectResponseException;
use LoginCidadao\CoreBundle\Model\PersonInterface;
use Doctrine\ORM\EntityManager;

class LoggedInUserListener
{
    /** @var SecurityContextInterface */
    private $context;

    /** @var RouterInterface */
    private $router;

    /** @var Session */
    private $session;

    /** @var TranslatorInterface */
    private $translator;

    /** @var EntityManager */
    private $em;

    /** @var string */
    private $defaultPasswordEncoder;

    public function __construct(SecurityContextInterface $context,
                                RouterInterface $router, Session $session,
                                TranslatorInterface $translator,
                                EntityManager $em, $defaultPasswordEncoder)
    {
        $this->context    = $context;
        $this->router     = $router;
        $this->session    = $session;
        $this->translator = $translator;
        $this->em         = $em;

        $this->defaultPasswordEncoder = $defaultPasswordEncoder;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (HttpKernel::MASTER_REQUEST != $event->getRequestType()) {
            // don't do anything if it's not the master request
            return;
        }
        $token = $this->context->getToken();

        if (is_null($token) ||
            $this->context->isGranted('IS_AUTHENTICATED_REMEMBERED') === false) {
            return;
        }
        if (!($token->getUser() instanceof PersonInterface)) {
            // We don't have a PersonInterface... Nothing to do here.
            return;
        }

        try {
            $this->handleTargetPath($event);
            $this->passwordEncoderMigration($event);
            $this->checkUnconfirmedEmail();
        } catch (RedirectResponseException $e) {
            $event->setResponse($e->getResponse());
        }
    }

    private function handleTargetPath(GetResponseEvent $event)
    {
        $route = $event->getRequest()->get('_route');
        if ($route !== 'lc_home' && $route !== 'fos_user_security_login') {
            return;
        }
        $key = '_security.main.target_path'; #where "main" is your firewall name
        //check if the referer session key has been set
        if ($this->session->has($key)) {
            //set the url based on the link they were trying to access before being authenticated
            $url = $this->session->get($key);

            //remove the session key
            $this->session->remove($key);
        } else {
            $url = $this->router->generate('lc_dashboard');
        }
        throw new RedirectResponseException(new RedirectResponse($url));
    }

    protected function checkUnconfirmedEmail()
    {
        $token = $this->context->getToken();
        $user  = $token->getUser();
        if (is_null($user->getEmailConfirmedAt())) {
            $params = array('%url%' => $this->router->generate('lc_resend_confirmation_email'));
            $title  = $this->translator->trans('notification.unconfirmed.email.title');
            $text   = $this->translator->trans('notification.unconfirmed.email.shortText',
                $params);
            $alert  = sprintf("<strong>%s</strong> %s", $title, $text);

            $this->session->getFlashBag()->add('alert.unconfirmed.email', $alert);
        }
    }

    private function passwordEncoderMigration(GetResponseEvent $event)
    {
        $person = $this->context->getToken()->getUser();
        $route  = $event->getRequest()->get('_route');

        if ($person->getEncoderName() === $this->defaultPasswordEncoder) {
            return;
        }
        $this->session->set('force_password_change', true);

        if ($route === 'fos_user_change_password') {
            return;
        }

        $url = $this->router->generate('fos_user_change_password');
        throw new RedirectResponseException(new RedirectResponse($url));
    }
}
