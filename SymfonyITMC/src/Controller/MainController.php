<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Entity\User;
use App\Entity\Book;
use App\Entity\Kind;

use App\Service\Recaptcha;

class MainController extends AbstractController
{

    /**
     * @Route("/", name="home")
     * Page d'accueil du site
     */
    public function home()
    {
        return $this->render('home.html.twig');
    }

    /**
     * @Route("/inscrivez-vous/", name="register")
     * Page d'inscription
     */
    public function register()
    {

        return $this->render('register.html.twig');
    }

    /**
     * @Route("/page-administration/", name="admin")
     * Page de l'administrateur
     */
    public function admin()
    {
        
        dump(1);

        return $this->render('admin.html.twig');
    }

}