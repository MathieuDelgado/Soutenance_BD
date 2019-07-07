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
use Doctrine\ORM\EntityManager;
use \Datetime;
use \Swift_Mailer;
use \Swift_Message;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class MainController extends AbstractController
{

    /**
     * @Route("/", name="home")
     * Page d'accueil du site
     */
    public function home()
    {
        // utilisation du repository pour afficher les infos des BD en page d'accueil
        $booksRepo = $this->getDoctrine()->getRepository(Book::class);
        $books = $booksRepo->findAll();

        return $this->render('home.html.twig', array(
            'books' => $books
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
                        );

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
        if ($user) {

            // Si le token du compte est bien le même token que passé en dans l'url on continue, sinon erreur 404
            if ($user->getRegisterToken() == $userToken) {

                // Si le compte n'est pas déjà activé on continue, sinon erreur 404
                if (!$user->getActive()) {

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
        if ($session->has('account')) {
            return $this->redirectToRoute('home');
        }

        // Si le formulaire a été cliqué
        if ($request->isMethod('POST')) {

            // Recupération des données du formulaire avec l'objet $request
            $email = $request->request->get('email');
            $password = $request->request->get('password');

            // bloc des verifs
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['invalidEmail'] = true;
            }

            if (!preg_match('#^.{8,300}$#', $password)) {
                $errors['invalidPassword'] = true;
            }

            // Si pas d'erreurs
            if (!isset($errors)) {

                // Via le repository des utilisateurs, on récupère l'utilisateur ayant déjà l'adresse email entrée dans le formulaire
                $userRepo = $this->getDoctrine()->getRepository(User::class);
                $user = $userRepo->findOneByEmail($email);

                // Si l'utilisateur a été trouvé, tout va bien c'est que le compte existe
                if (!empty($user)) {

                    // Vérification que le mot de passe est bien le bon
                    if (password_verify($password, $user->getPassword())) {

                        if ($user->getActive()) {

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
        if (isset($errors)) {
            return $this->render('login.html.twig', array('errorsList' => $errors));
        }

        // Chargement de la vue par défaut (si pas d'erreurs mais pas de succès non plus)
        return $this->render('login.html.twig');
    }


    /**
     * @Route("/deconnexion/", name="logout")
     * Page de déconnexion
     */
    public function logout()
    {

        // Si la personne n'est pas connectée, on la redirige vers la page de connexion
        $session = $this->get('session');
        if (!$session->has('account')) {
            return $this->redirectToRoute('login');
        }

        // On supprime la variable account en session (ce qui provoque une deconnexion)
        $session->remove('account');

        // Appel de la vue deconnexion pour afficher un message indiquant la reussite de la deconnexion
        return $this->render('logout.html.twig');
    }

    /**
     * @Route("/detail-de-la-bd/{titleBook}/", name="displayOneBD")
     * Page détail d'une seule BD
     */
    public function displayOneBD(Request $request, $titleBook)
    {
        // Récupération de la session
        $session = $this->get('session');
        //via le repository des Book, on récupère la BD qui correspond à book_id dans l'url
        $bookRepo = $this->getDoctrine()->getRepository(Book::class);
        $commentRepo = $this->getDoctrine()->getRepository(Comment::class);
        $userRepo = $this->getDoctrine()->getRepository(User::class);
        $book = $bookRepo->findOneByTitle($titleBook);
        $user = $this->getUser();
        //si formulaire cliqué
        if ($request->isMethod('POST')){
            //TODO remettre le STR_REPLACE
            //$content = str_replace(array("\n", "\r"), ' ', nl2br($request->request->get('inputComment')));
            $content= $request->request->get('inputComment');
            // Bloc des vérifs
            if(!preg_match('#^[a-zÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿA-Z0-9\.\,\'\:\"\(\)\?\!;\-\r\n ]{1,2000}$#', $content)){
                $errors['invalidContent'] = true;  
                dump('contenu invalide'); 
            }  
            // Si pas d'erreurs
            if(!isset($errors)){
                $entityManager = $this->getDoctrine()->getManager();
                $sessionUser= $session->get('account');
                $user = $userRepo->find($sessionUser->getId());
                //$book = $bookRepo->find($bookUser->getId());

                // On crée le nouveau commentaire, puis on l'hydrate avec les données adéquates
                $comment = new Comment();
                $comment ->setContent($content); // On donne le contenu venant du formulaire
                $comment->setDate(new DateTime);
                $comment->setUser($user);
                $comment->setBook($book);   
                $entityManager->persist($comment);   
                $entityManager->flush();
            }
        }
        if(isset($errors)){
            $comments = $book->getComments();
            return $this->render('displayOneBD.html.twig', array(
                'book' => $book,
                'comments' => $comments,
                'errors' => $errors
            ));
        }else{
            //AFFICHER LES COMMs
            $comments = $book->getComments();
            return $this->render('displayOneBD.html.twig', array(
                'book' => $book,
                'comments' => $comments,
            ));
        }
    }

    /**
     * @Route("/bdtheque-par-titre/", name = "bdbddByTitle")
     * page de la bibliothèque général trié par titre
     */
    public function displayAllBDByTitle()
    {
        $em = $this->getDoctrine()->getManager();
        //$query contient les livres de la bdd avec le nom de du titre croissant.
        $query = $em->createQuery('SELECT b FROM App\Entity\Book b ORDER BY b.title ASC');
        $books = $query->getResult();
        return $this->render('bdbddByTitle.html.twig', array(
            'books' => $books
        ));
    }

    /**
     * @Route("/bdtheque-par-auteur/", name = "bdbddByAuthor")
     * page de la bibliothèque général trié par Auteur
     */
    public function displayAllBDByAuthor()
    {
        $em = $this->getDoctrine()->getManager();
        $query = $em->createQuery('SELECT b FROM App\Entity\Book b ORDER BY b.author ASC');
        $books = $query->getResult();
        return $this->render('bdbddByAuthor.html.twig', array(
            'books' => $books
        ));
    }

    /**
     * @Route("/bdtheque-par-editeur/", name = "bdbddByEditor")
     */
    public function displayAllBDByEditor()
    {
        $em = $this->getDoctrine()->getManager();
        $query = $em->createQuery('SELECT b FROM App\Entity\Book b ORDER BY b.editor ASC');
        $books = $query->getResult();
        return $this->render('bdbddByEditor.html.twig', array(
            'books' => $books
        ));
    }

    /**
     * @Route("/bdtheque-dernier-ajout/", name = "bdbddByLast")
     */
    public function displayAllBDByLast()
    {
        $bookRepo = $this->getDoctrine()->getRepository(Book::class);
        //$books contient les 30 derniers livres insérés rangé par id décroissants
        $books = $bookRepo->findBy(array(), array('id' => 'DESC'), 30, 0);
        return $this->render('bdbddByLast.html.twig', array(
            'books' => $books
        ));
    }

    
    /**
     * @Route("/bdtheque-recherche/", name = "bdbddSearch")
     */
    public function displaySearchBar(Request $request)
    {
        $title = $request->query->get('s');
        if(!empty($title)){
            $bookRepo = $this->getDoctrine()->getRepository(Book::class);
            $books = $bookRepo->searchByPartialTitle($title);

            return $this->render('bdbddSearch.html.twig', array(
                'books' => $books
            ));
        }
        return $this->render('bdbddSearch.html.twig');
    }


    /**
     * @Route("/contactez-nous/", name="contact")
     * Page de contact
     */
    public function contact(Request $request, Swift_Mailer $mailer)
    {
        // Si le formulaire a bien été cliqué 
        if ($request->isMethod('POST')) {
            // On récupère les champs de formulaire 
            $email = $request->request->get('email');
            $subject = $request->request->get('subject');
            $content = $request->request->get('content');

            // Bloc des vérifs
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['invalidEmail'] = true;
            }

            if (!preg_match('#^[a-z A-Z 0-9 áàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ._-\s\,\;\:\!\?\@]{2,100}$#', $subject)) {
                $errors['invalidSubject'] = true;
            }

            if (!preg_match('#^[a-z A-Z 0-9 áàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ._-\s\,\;\:\!\?\@]{2,500}$#', $content)) {
                $errors['invalidContent'] = true;
            }

            if (!isset($errors)) {
                $message = (new Swift_Message('Email de contact'))
                    ->setSubject($subject)
                    ->setFrom($email)
                    ->setTo("boissey265@gmail.com")
                    ->setBody(
                        $this->renderView('emails/contactEmail.html.twig', array(
                            "content" => $content,
                            "subject" => $subject,

                        )),
                        'text/html'
                    )
                    ->addPart(
                        $this->renderView('emails/contactEmail.txt.twig', array(
                            "content" => $content,
                            "subject" => $subject,
                        )),
                        'text/plain'
                    );
                $status = $mailer->send($message);
                if ($status) {
                    return $this->render('contact.html.twig', array('success' => true));
                } else {
                    $errors['errorMail'] = true;
                }
            }
        }

        if (isset($errors)) {
            return $this->render('contact.html.twig', array('errorsList' => $errors));
        }

        return $this->render('contact.html.twig');
    }

    /**
     * @Route("/qui-sommes-nous/", name="about_us")
     * Page d'information sur qui nous sommes
     */
    public function about_us()
    {
        return $this->render('about_us.html.twig');
    }

    /**
     * @Route("/plan-du-site/", name="sitemap")
     * Page de plan du site 
     */
    public function sitemap()
    {
        return $this->render('sitemap.html.twig');
    }

    /**
     * @Route("/mentions-legales/", name="mentions_legales")
     * Page de mentions légales
     */
    public function mentions_legales()
    {
        return $this->render('mentions_legales.html.twig');
    }

    /**
     * @Route("/ajouter-une-bd/", name="addComic")
     * page de creation d'une bd
     */
    public function addComic(Request $request)
    {


        //on verifie que le formulaire a été cliqué
        if ($request->isMethod('POST')) {

            //recuperation des données
            $googleId = $request->request->get('book');
            dump($googleId);
            //bloc des verifs
            if (!preg_match('#^.{12}$#', $googleId)) {
                $errors['googleIdInvalid'] = true;
            }

            if (!isset($errors)) {

                $result = file_get_contents('https://www.googleapis.com/books/v1/volumes?q=+id:' . $googleId);
                $parsedResult = json_decode($result);
                $items = $parsedResult->items;


                $title = isset($items[0]->volumeInfo->title) ? $items[0]->volumeInfo->title : 'inconnu';
                $author = isset($items[0]->volumeInfo->authors[0]) ? $items[0]->volumeInfo->authors[0] : 'inconnu';
                $illustrator = isset($items[0]->volumeInfo->authors[1]) ? $items[0]->volumeInfo->authors[1] : 'Inconnu';
                $publisher = isset($items[0]->volumeInfo->publisher) ? $items[0]->volumeInfo->publisher : 'inconnu';
                $isbn = isset($items[0]->volumeInfo->industryIdentifiers[1]->identifier) ? $items[0]->volumeInfo->industryIdentifiers[1]->identifier : 'inconnu';
                $synopsis = isset($items[0]->volumeInfo->description) ? $items[0]->volumeInfo->description : 'inconnu';
                $imgUrl = isset($items[0]->volumeInfo->imageLinks->thumbnail) ? $items[0]->volumeInfo->imageLinks->thumbnail : 'inconnu';

                // Verif si existe pas
                $bookRepo = $this->getDoctrine()->getRepository(Book::class);

                $bookIfExist = $bookRepo->findOneByIsbn($isbn);

                if (empty($bookIfExist)) {

                    $newBook = new Book();
                    $newBook
                        ->setTitle($title)
                        ->setAuthor($author)
                        ->setIllustrator($illustrator)
                        ->setEditor($publisher)
                        ->setIsbn($isbn)
                        ->setSynopsis($synopsis)
                        ->setImgUrl($imgUrl)
                        ->setGoogleIdent($googleId);
                    //On recupère le manager des entités
                    $em = $this->getDoctrine()->getManager();
                    $em->persist($newBook);
                    $em->flush();

                    return $this->render('addComic.html.twig', ['addComicSuccess' => true]);
                } else {
                    return $this->render('addComic.html.twig', ['errors' => $errors]);
                }
            }
        }
        return $this->render('addComic.html.twig');
    }

    /**
     * @Route("/ma-page-de-profil/", name="profil")
     * Page d'affichage du profil utilisateur
     */
    public function userProfil(Request $request)
    {
        //recuperation de la session
        $session = $this->get('session');

        //si account n'existe pas en session, alors l'utilisateur est redirigé vers la page d'accueil

        if (!$session->has('account')) {
            return $this->redirectToRoute('home');
        }

        // On recupère tout les livres de l'utilisateur
        $currentUser = $session->get('account');

        $userRepo = $this->getDoctrine()->getRepository(User::class);

        $userInfos = $userRepo->findOneById($currentUser->getId());

        $books = $currentUser->getBooks();
        dump($session);
        dump($userInfos);
        dump($books);

        return $this->render('userProfil.html.twig', array(
            'user' => $currentUser,
            'books' => $books
        ));

        return $this->render('userProfil.html.twig');
    }

    /**
     * @Route("modifier-mon-profil", name="editProfil")
     * Page de modifications des informations des données utilisateurs
     */
    public function editProfil(Request $request)
    {
        //recuperation de la session
        $session = $this->get('session');

        $email = $session->get('account')->getEmail();

        //si account n'existe pas en session, alors l'utilisateur est redirigé vers la page d'accueil

        // if (!$session->has('account')) {
        //     return $this->redirectToRoute('home');
        // }

        //si le formulaire a bien été cliqué
        if ($request->isMethod('POST')) {
        //On récupère les données POST du formulaire
            $updatePseudo = $request->request->get('pseudo');
            $updateEmail = $request->request->get('email');
            $updateConfirmEmail = $request->request->get('confirmEmail');
            $oldPassword = $request->request->get('oldPassword');
            $updatePassword = $request->request->get('newPassword');
            $updateConfirmPassword = $request->request->get('confirmNewPassword');

            $account = $session->get('account');
            //bloc de verifs
            //regex a utiliser [a-zA-Z]+(([\',. -][a-zA-Z ])?[a-zA-Z]*){1,120}
            if(!preg_match('#^[a-zA-Z]{1,120}$#', $updatePseudo)) {
                $errors['invalidPseudo'] = true;
            }

            if(!filter_var($updateEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['invalidEmail'] = true;
            }
            if($updateEmail != $updateConfirmEmail){
                $errors['invalidEmailConfirm'] = true;
            }
            if(!empty($oldPassword)){
                // le mot de passe ne correspond pas au mdp présent en bdd
                if(!password_verify($oldPassword, $session->get('account')->getPassword())){
                    $errors['invalidOldPassword'] = true;
                }
                //Validation du MdP : Au moins 8 caractères, dont une majuscule, une minuscule et un chiffre
                if(!preg_match('#^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d]{8,}$#', $updatePassword)) {
                    $errors['invalidPassword'] = true;
                }
                // l'ancien mdp saisie est comparé au nouveau mdp
                if($oldPassword == $updatePassword){
                    $errors['sameUpdatePassword'] = true;
                }
                // l'ancien mdp stocké est comparé au nouveau mdp
                if($account->getPassword() == $updatePassword){
                    $errors['samePassword'] = true;
                }
                // le nouveau mdp est comparé à la confirmation
                if($updatePassword != $updateConfirmPassword) {
                    $errors['invalidPasswordConfirm'] = true;
                }
            }
            //si aucune erreur
            if(!isset($errors)) {
                    
                //On recupère le manager des entités
                $em = $this->getDoctrine()->getManager();
            
                $updateUser = $session->get('account');

                $updateUser = $em->merge($updateUser);
                dump($updateUser);

                $updateUser
                    ->setPseudo($updatePseudo)
                    ->setEmail($updateEmail);

                if(!empty($oldPassword)){
                    $updateUser
                        ->setPassword(password_hash($updatePassword, PASSWORD_BCRYPT));
                    }

                $em->flush();

                $session->set('account', $updateUser);

                return $this->render('editProfil.html.twig', ['success' => true]);
                    
            } else {
                return $this->render('editProfil.html.twig', ['errors' => $errors]);

            }
        }    
        return $this->render('editProfil.html.twig');
    }
}
