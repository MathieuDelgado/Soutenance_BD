<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use \Datetime;
use App\Entity\User;
use App\Entity\Book;
use App\Entity\Kind;
use App\Repository\UserRepository;
use App\Service\Recaptcha;

/**
 * @Route("/admin")
 */
class AdminController extends AbstractController
{

    /**
     * @Route("/", name="admin")
     * Page de l'administrateur
     */
    public function admin()
    {
        // on utilise la session 
        $session = $this->get('session');

        if(!$session->has('account') || !$session->get('account')->getAdmin()){
            throw new AccessDeniedHttpException();

        } else {
            $adminStatus = $session->get('account')->getAdmin();
            dump($adminStatus);
            dump($session);
            
            
            return $this->render('admin.html.twig');
        }
        return $this->redirectToRoute('home');
        
    }

}