<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Security\UsersAuthenticator;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AdminController extends AbstractController
{
    use TargetPathTrait;
    
    #[Route("/admin", name:"admin_login")]
    public function login(AuthenticationUtils $authenticationUtils, Security $security, Request $request)
    {

        
        $user = $security->getUser();

        if ($user && $security->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_home');
         }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

     
        return $this->render('Admin/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);


    }
    #[Route(path: '/logout', name: 'admin_logout')]
    public function logout()
    {
   
    }
    
}
