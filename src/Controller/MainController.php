<?php

namespace App\Controller;

use App\Form\UserSignUpType;
use App\Entity\{ User, Post, Action };

use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\{ Response, JsonResponse, Request };
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\{ AuthenticationUtils };

class MainController extends Controller 
{
   /**
    * @Route("/signup", name="signup", methods={ "GET", "POST" })
    */
   public function signup(Request $request, UserPasswordEncoderInterface $passwordEncoder)
   {
      $user = new User();
      $form = $this->createForm(UserSignUpType::class, $user, [
            'action' => $this->generateUrl('signup')
      ]);

      $form->handleRequest($request);
      if ($form->isSubmitted() && $form->isValid()) {

            $password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
            $user->setPassword($password);
            $user->setPermalink(explode('@', $user->getEmail())[0] . rand(0, 100));
            
            // Save user to the database
            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            return $this->redirectToRoute('index');
      }

      return $this->render('main/signup.html.twig', [
            'signup' => $form->createView()
      ]);
   }

   /**
    * @Route("/login", name="login", methods={ "GET", "POST" })
    */
   public function login(Request $request, AuthenticationUtils $au)
   {
      $lastUsername = $au->getLastUsername();
      $error = $au->getLastAuthenticationError();
      
      return $this->render('main/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error
      ]);
   }

   /**
    * @Route("/logout", name="logout")
    */
   public function logout() {}

   /**
    * @Route("/", name="index", methods={ "GET" })
    */
   public function index()
   {
      // Get all posts and users from the database
      $posts = $this->getDoctrine()
                  ->getRepository(Post::class)
                  ->findBy([], [ 'date_of_upload' => 'DESC' ]);
      $uploaders = $this->getDoctrine()
                  ->getRepository(User::class)
                  ->findAll();

      // Set posts for template
      foreach ($posts as $i => $post) {

          $upvoteCount = 0;
          $comments = [];

          // Find the actions of this post
          $actions = $this->getDoctrine()
              ->getRepository(Action::class)
              ->findBy([ 'entity_id' => $post->getPostId() ]);

          if (isset($actions)) {
              foreach ($actions as $k => $action) {
                  if ($action->getActionType() === 'comment') {
                      $comments[] = $actions[$k];
                  }
                  if ($action->getActionType() === 'upvote') {
                      $upvoteCount++;
                      if ($action->getUserId() === $this->getUser()->getUserId()) {
                          $post->setUpvotedByUser(true);
                      }
                  }
              }
          }

          // Find the uploader of this post
          $postUploader = null;
          foreach ($uploaders as $u) {
              if ($post->getUserId() === $u->getUserId()) {
                  $postUploader = $u;
              }

              // Also find uploaders of comments
              // on this post and set them for the template
              foreach ($comments as $j => $comment) {
                  if ($comments[$j]->getUserId() === $u->getUserId()) {
                      $comments[$j]->setCommenterLink($u->getPermalink());
                      $comments[$j]->setCommenterProfile($u->getProfilePic());
                      $comments[$j]->setCommenter($u->getFirstName() . ' ' . $u->getLastName());
                  }
              }
          }

          // Set up post for template
          $posts[$i]->setUploader($postUploader->getFirstName() . ' ' . $postUploader->getLastName());
          $posts[$i]->setUploaderProfilePic($postUploader->getProfilePic());
          $posts[$i]->setUploaderLink($postUploader->getPermalink());
          $posts[$i]->setComments($comments);
          $posts[$i]->setUpvotes($upvoteCount);
      }

      return $this->render('main/index.html.twig', [
         'posts' => $posts
      ]);
   }
}
