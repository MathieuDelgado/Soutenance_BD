<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use \Datetime;
use App\Entity\User;
use App\Entity\Book;
use App\Entity\Kind;

use App\Service\Recaptcha;

class MainController extends AbstractController
{

    /**
     * @Route("/Accueil/", name="home")
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
     public function register(Request $request)
     {
        //recuperation de la session
        $session = $this->get('session');
        //si account existe en session, alors l'utilisateur est déjà connecté donc on le redirige vers la page d'accueil
        if($session->has('account')){
            return $this->redirectToRoute('home');
        }

        //si le formulaire a bien été cliqué
        if($request->isMethod('POST')){
            //On récupère les données POST du formulaire
            $firstname = $request->request->get('firstname');
            $lastname = $request->request->get('lastname');
            $pseudo = $request->request->get('pseudo');
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirmPassword');
            //$recaptchaCode = $request->request->get('g-recaptcha-response');

            //bloc de verifs
            //regex a utiliser [a-zA-Z]+(([\',. -][a-zA-Z ])?[a-zA-Z]*){1,120}
            if(!preg_match('#^[a-zA-Z]{1,120}$#', $firstname)){
                $errors['invalidFirstname'] = true;
            }
            if(!preg_match('#^[a-zA-Z]{1,120}$#', $lastname)){
                $errors['invalidLastname'] = true;
            }
            if(!preg_match('#^[a-zA-Z]{1,120}$#', $pseudo)){
                $errors['invalidPseudo'] = true;
            }
            if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
                $errors['invalidEmail'] = true;
            }
            //Validation du MdP : Au moins 8 caractères, dont une majuscule, une minuscule et un chiffre
            if(!preg_match('#^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d]{8,}$#', $password)){
                $errors['invalidPassword'] = true;
            }
            if($password != $confirmPassword){
                $errors['invalidPasswordConfirm'] = true;
            }
            // if(!$recaptcha->isValid($recaptchaCode, $request->server->get('REMOTE_ADDR'))){
            //     $errors['captchaInvalid'] = true;
            // }

            //si aucune erreur
            if(!isset($errors)){
                // Verif si existe pas
                $userRepo = $this->getDoctrine()->getRepository(User::class);

                $userIfExist = $userRepo->findOneByEmail($email);

                if(empty($userIfExist)){

                    $newUser = new User();
                    $newUser
                        ->setFirstname($firstname)
                        ->setLastname($lastname)
                        ->setPseudo($pseudo)
                        ->setEmail($email)
                        ->setPassword(password_hash($password, PASSWORD_BCRYPT))
                        ->setAdmin(false)
                        ->setRegisterDate(new DateTime())
                    ;

                    //On recupère le manageer des entités
                    $em = $this->getDoctrine()->getManager();
                    $em->persist($newUser);
                    $em->flush();

                    //TODO ajouter le swiftMailer

                    return $this->render('register.html.twig', array('success'=>true));
                } else {
                    $errors['emailAlreadyUsed'] = true;
                }
            }
        }
        // Si il y a des erreurs, on appel la vue en lui donnant ces erreurs
        if(isset($errors)){
            return $this->render('register.html.twig', array('errorsList' => $errors));
        }

        return $this->render('register.html.twig');
     }

}