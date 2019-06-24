<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use App\Service\Recaptcha;
use App\Service\TokenGenerator;
use App\Entity\User;
use App\Entity\Book;
use App\Entity\Comment;
use App\Entity\Kind;
use App\Repository\UserRepository;

use \Datetime;
use \Swift_Mailer;
use \Swift_Message;


class MainController extends AbstractController
{

    /**
     * @Route("/", name="home")
     * Page d'accueil du site
     */
    public function home()
    {
        $bookRepo = $this->getDoctrine()->getRepository(Book::class);
        $book = $bookRepo->findOneById(1);

        return $this->render('home.html.twig', array(
            'book'=>$book
        ));
    }

    /**
     * @Route("/inscrivez-vous/", name="register")
     * Page d'inscription
     */
    public function register(Request $request, Recaptcha $recaptcha, TokenGenerator $tg, Swift_Mailer $mailer)
    {
        //recuperation de la session
        $session = $this->get('session');
        //si account existe en session, alors l'utilisateur est déjà connecté donc on le redirige vers la page d'accueil
        if ($session->has('account')) {
            return $this->redirectToRoute('home');
        }

        //si le formulaire a bien été cliqué
        if ($request->isMethod('POST')) {
            //On récupère les données POST du formulaire
            $firstname = $request->request->get('firstname');
            $lastname = $request->request->get('lastname');
            $pseudo = $request->request->get('pseudo');
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirmPassword');
            $recaptchaCode = $request->request->get('g-recaptcha-response');

            //bloc de verifs
            //regex a utiliser [a-zA-Z]+(([\',. -][a-zA-Z ])?[a-zA-Z]*){1,120}
            if (!preg_match('#^[a-zA-Z]{1,120}$#', $firstname)) {
                $errors['invalidFirstname'] = true;
            }
            if (!preg_match('#^[a-zA-Z]{1,120}$#', $lastname)) {
                $errors['invalidLastname'] = true;
            }
            if (!preg_match('#^[a-zA-Z]{1,120}$#', $pseudo)) {
                $errors['invalidPseudo'] = true;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['invalidEmail'] = true;
            }
            //Validation du MdP : Au moins 8 caractères, dont une majuscule, une minuscule et un chiffre
            if (!preg_match('#^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d]{8,}$#', $password)) {
                $errors['invalidPassword'] = true;
            }
            if ($password != $confirmPassword) {
                $errors['invalidPasswordConfirm'] = true;
            }
            if (!$recaptcha->isValid($recaptchaCode, $request->server->get('REMOTE_ADDR'))) {
                $errors['captchaInvalid'] = true;
            }

            //si aucune erreur
            if (!isset($errors)) {
                // Verif si existe pas
                $userRepo = $this->getDoctrine()->getRepository(User::class);

                $userIfExist = $userRepo->findOneByEmail($email);

                if (empty($userIfExist)) {

                    $newUser = new User();
                    $newUser
                        ->setFirstname($firstname)
                        ->setLastname($lastname)
                        ->setPseudo($pseudo)
                        ->setEmail($email)
                        ->setPassword(password_hash($password, PASSWORD_BCRYPT))
                        ->setAdmin(false)
                        ->setRegisterDate(new DateTime())
                        ->setActive(false)
                        ->setRegisterToken($tg->generate());

                    //On recupère le manager des entités
                    $em = $this->getDoctrine()->getManager();
                    $em->persist($newUser);
                    $em->flush();

                    $message = (new Swift_Message('Activation de votre compte'))
                        ->setFrom('expediteur@monsite.com')
                        ->setTo($newUser->getEmail())
                        ->setBody(
                            $this->renderView('emails/activation.html.twig', ['user' => $newUser]),
                            'text/html'
                        )
                        ->addPart(
                            $this->renderView('emails/activation.txt.twig', ['user' => $newUser]),
                            'text/plain'
                        )
                    ;

                    $mailer->send($message);

                    return $this->render('register.html.twig', array('success' => true));
                } else {
                    $errors['emailAlreadyUsed'] = true;
                }
            }
        }
        // Si il y a des erreurs, on appel la vue en lui donnant ces erreurs
        if (isset($errors)) {
            return $this->render('register.html.twig', array('errorsList' => $errors));
        }

        return $this->render('register.html.twig');
    }

    /**
     * @Route("/activer-compte/{userId}/{userToken}/", name="activate", requirements = {"userId" = "[1-9][0-9]{0,10}", "userToken" = "[0-9a-fA-F]{32}"})
     * Page permettant d'activer un compte en bdd
     */
    public function activate($userId, $userToken)
    {
        // Récupération de l'utilisateur correspondant à l'id passé dans l'url
        $userRepo = $this->getDoctrine()->getRepository(User::class);
        $user = $userRepo->findOneById($userId);

        // Si un compte a bien été trouvé, on continue, sinon erreur 404
        if($user){

            // Si le token du compte est bien le même token que passé en dans l'url on continue, sinon erreur 404
            if($user->getRegisterToken() == $userToken){

                // Si le compte n'est pas déjà activé on continue, sinon erreur 404
                if(!$user->getActive()){

                    // On passe active de false à true sur le compte et on sauvegarde en BDD
                    $user->setActive(true);
                    $em = $this->getDoctrine()->getManager();
                    $em->flush();

                    // Affichage de la vue de la page qui affichera un message confirmant la bonne activation du compte
                    return $this->render('activate.html.twig');

                } else {

                    throw new NotFoundHttpException('Déja activé');
                }

            } else {

                throw new NotFoundHttpException('Token pas bon');
            }

        } else {
            throw new NotFoundHttpException('Compte pas trouvé');
        }

    }

    /**
     * @Route("/connexion/", name="login")
     * Page de connexion
     */
    public function login(Request $request)
    {
        // Récupération de la session
        $session = $this->get('session');
        // Si account existe en session, alors l'utilisateur est déjà connecté à un compte donc on le redirige sur la page d'accueil
        if($session->has('account')){
            return $this->redirectToRoute('home');
        }

        // Si le formulaire a été cliqué
        if($request->isMethod('POST')){

            // Recupération des données du formulaire avec l'objet $request
            $email = $request->request->get('email');
            $password = $request->request->get('password');

            // bloc des verifs
            if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
                $errors['invalidEmail'] = true;
            }

            if(!preg_match('#^.{8,300}$#', $password)){
                $errors['invalidPassword'] = true;
            }

            // Si pas d'erreurs
            if(!isset($errors)){

                // Via le repository des utilisateurs, on récupère l'utilisateur ayant déjà l'adresse email entrée dans le formulaire
                $userRepo = $this->getDoctrine()->getRepository(User::class);
                $user = $userRepo->findOneByEmail($email);

                // Si l'utilisateur a été trouvé, tout va bien c'est que le compte existe
                if(!empty($user)){

                    // Vérification que le mot de passe est bien le bon
                    if(password_verify($password, $user->getPassword())){

                        if($user->getActive()){

                            // Connexion de l'utilisateur
                            $session->set('account', $user);

                            return $this->render('login.html.twig', array('success' => true));
                        } else {
                            $errors['notActive'] = true;
                        }


                    } else {
                        $errors['badPassword'] = true;
                    }

                } else {
                    $errors['notExist'] = true;
                }
            }
        }

        // Si il y a des erreurs, on charge la vue en lui envoyant ces erreurs en parametre
        if(isset($errors)){
            return $this->render('login.html.twig', array('errorsList' => $errors));
        }

        // Chargement de la vue par défaut (si pas d'erreurs mais pas de succès non plus)
        return $this->render('login.html.twig');
    }


    /**
     * @Route("/deconnexion/", name="logout")
     * Page de déconnexion
     */
    public function logout(){

        // Si la personne n'est pas connectée, on la redirige vers la page de connexion
        $session = $this->get('session');
        if(!$session->has('account')){
            return $this->redirectToRoute('login');
        }

        // On supprime la variable account en session (ce qui provoque une deconnexion)
        $session->remove('account');

        // Appel de la vue deconnexion pour afficher un message indiquant la reussite de la deconnexion
        return $this->render('logout.html.twig');

    }

    /**
     * @Route("/detail-de-la-BD/{idBook}/", requirements={"name"="[1-9][0-9]{0,10}"}, name="displayOneBD")
     * Page détail d'une seule BD
     */
    public function displayOneBD($idBook)
    {
        //via le repository des Book, on récupère la BD qui correspond à book_id dans l'url
        $bookRepo = $this->getDoctrine()->getRepository(Book::class);
        $commentRepo = $this->getDoctrine()->getRepository(Comment::class);
        $book = $bookRepo->findOneById($idBook);
        $comments = $commentRepo->findAll();

        return $this->render('displayOneBD.html.twig', array(
            'book'=>$book,
            'comments' => $comments
        )); 

    }
}
