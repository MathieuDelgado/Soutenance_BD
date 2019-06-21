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
        
        // À virer quand on pourra utiliser la page de connexion
        //////////////////////////////////
        $userRepo = $this->getDoctrine()->getRepository(User::class);
        
        $session = $this->get('session');

        $session->set('account', $userRepo->findOneById(1));

        //////////////////////////////////

        if(!$session->has('account') || !$session->get('account')->getAdmin()){
            throw new AccessDeniedHttpException();

        } else {
            $adminStatus = $session->get('account')->getAdmin();
            dump($adminStatus); 
            //////////////////////////////
            // utiliser app.session.has('account') and app.session.get('session').user.admin
            // pour la base.html.twig pour l'affichage du bouton dans la barre de menu
            //////////////////////////////
            
            return $this->render('admin.html.twig');
        }
        return $this->redirectToRoute('home');
        
    }

}