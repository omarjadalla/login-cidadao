<?php
/**
 * This file is part of the login-cidadao project or it's bundles.
 *
 * (c) Guilherme Donato <guilhermednt on github>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LoginCidadao\AccountRecoveryBundle\Controller;

use LoginCidadao\AccountRecoveryBundle\Event\AccountRecoveryDataEditEvent;
use LoginCidadao\AccountRecoveryBundle\Event\AccountRecoveryEvents;
use LoginCidadao\AccountRecoveryBundle\Form\AccountRecoveryDataType;
use LoginCidadao\CoreBundle\Model\PersonInterface;
use LoginCidadao\AccountRecoveryBundle\Service\AccountRecoveryService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountRecoveryDataController extends Controller
{

    /**
     * @Route("/account-recovery-data", name="account_recovery_edit")
     * @Template()
     */
    public function editAction(Request $request)
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->get('event_dispatcher');

        /** @var PersonInterface $person */
        $person = $this->getUser();

        /** @var AccountRecoveryService $accountRecoveryService */
        $accountRecoveryService = $this->get('lc.account_recovery');

        $recoveryData = $accountRecoveryService->getAccountRecoveryData($person);
        $event = new AccountRecoveryDataEditEvent($recoveryData);
        $eventDispatcher->dispatch(AccountRecoveryEvents::ACCOUNT_RECOVERY_DATA_EDIT_INITIALIZE, $event);

        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }

        $form = $this->createForm(AccountRecoveryDataType::class, $recoveryData);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $event = new AccountRecoveryDataEditEvent($recoveryData);
            $eventDispatcher->dispatch(AccountRecoveryEvents::ACCOUNT_RECOVERY_DATA_EDIT_SUCCESS, $event);

            $this->getDoctrine()->getManager()->flush();

            if (null === $response = $event->getResponse()) {
                $response = $this->redirectToRoute('account_recovery_edit');
            }

            $event = new AccountRecoveryDataEditEvent($recoveryData, $response);
            $eventDispatcher->dispatch(AccountRecoveryEvents::ACCOUNT_RECOVERY_DATA_EDIT_COMPLETED, $event);

            return $response;
        }

        return [
            'form' => $form->createView(),
        ];
    }
}