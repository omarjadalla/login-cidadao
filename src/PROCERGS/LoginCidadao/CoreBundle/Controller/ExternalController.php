<?php

namespace PROCERGS\LoginCidadao\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ExternalController extends Controller
{

    /**
     * @Route("/external/navbar.js", name="lc_navbar_external")
     * @Template()
     */
    public function navbarAction()
    {
        $referer = $this->getRequest()->headers->get('referer');
        $domain = preg_replace('/https?:\/\/([^\/]+)\/.*/i', '\1', $referer);
        $navbar = $this->renderView('PROCERGSLoginCidadaoCoreBundle:External:navbar.html.twig', compact('domain'));
        $html = json_encode(array('navbar' => $navbar));
        $response = $this->render('PROCERGSLoginCidadaoCoreBundle:External:navbar.js.twig', compact('html'));
        $response->headers->set('Content-Type', 'application/javascript');
        return $response;
    }

}