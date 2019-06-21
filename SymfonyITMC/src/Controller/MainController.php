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
     * @Route("/detail-de-la-BD/{idBook}/", requirements={"name"="[1-9][0-9]{0,10}"}, name="displayOneBD")
     * Page détail d'une seule BD
     */
    public function displayOneBD($idBook)
    {
        //via le repository des Book, on récupère la BD qui correspond à book_id dans l'url
        $bookRepo = $this->getDoctrine()->getRepository(Book::class);
        $book = $bookRepo->findOneById($idBook);

             return $this->render('displayOneBD.html.twig', array(
            'book'=>$book
             ));            
    }
}