<?php

namespace App\Controller;

use App\Service\DatabaseManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use App\Form\InformationType;
use App\Form\ProduitType;
use Symfony\Component\Security\Core\Annotation\Security;
use App\Form\CoordonneesClientType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use PDOException;
use Throwable;

class HomeController extends AbstractController
{
    private $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }
    #[Route('/', name: 'app_home')]
    public function index(DatabaseManager $databaseManager): Response
    {
        $result = $databaseManager->executeQuery('SELECT * FROM information');
        return $this->render('base.html.twig', [
            'controller_name' => 'HomeController',
            'result' => $result,
        ]);
    }

    #[Route('/liste-produits', name: 'app_produits')]
    public function produits(DatabaseManager $databaseManager): Response
    {
        
        $result = $databaseManager->executeQuery('SELECT * FROM produit');
        return $this->render('/User/produit.html.twig', [
            'controller_name' => 'HomeController',
            'result' => $result,
            
        ]);
    }
    #[Route('/panier', name: 'app_panier')]
    public function panier(Request $request, DatabaseManager $databaseManager, Session $session): Response
    {
        
        $result = $databaseManager->executeQuery('SELECT quantite ,produit.nom AS n, produit.prixAuKg AS prix FROM `lignecommande` JOIN produit ON lignecommande.id_produit = produit.id ');
        $form = $this->createForm(CoordonneesClientType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $coordonnees = $form->getData();
            $idClient = $databaseManager->execute(
                'INSERT INTO coordonnesclient (nom, email, telephone) VALUES (:nom, :email, :telephone)',
                [
                    'nom' => $coordonnees['nom'],
                    'email' => $coordonnees['email'],
                    'telephone' => $coordonnees['telephone'],
                ]
        );
        $idCommande = $session->get('id_commande');
        $databaseManager->execute(
            'UPDATE commande SET client_id = :client_id, id_etat_commande = :id_etat WHERE id = :id_commande',
            ['client_id' => $idClient, 'id_etat' => '2', 'id_commande' => $idCommande]
        );
        $session->getFlashBag()->add('success', 'Votre commande à été envoyé.');
           
        }
        return $this->render('/User/panier.html.twig', [
            'controller_name' => 'HomeController',
            'result' => $result,
            'form' => $form->createView(),
        ]);
    }

    
    #[Security("is_granted('ROLE_ADMIN)")]
    #[Route('/admin/produits', name: 'app_ajout_produit')]
    public function AjoutProduit(Request $request, DatabaseManager $databaseManager, Session $session): Response
    {
        
        $form = $this->createForm(ProduitType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $productData = $form->getData();
            $imageFile = $productData['image'];

            $newFilename = md5(uniqid()) . '.' . $imageFile->guessExtension();
            try {
                $imageFile->move(
                    $this->getParameter('medias_directory'),
                    $newFilename
                );
            } catch (FileException $e) {
            }
           
            $relativeImagePath = 'assets/medias/' . $newFilename;
            $databaseManager->executeQuery('INSERT INTO produit (nom, prixAuKg, image) VALUES (:nom, :prixAuKg, :image)', [
                'nom' => $productData['nom'],
                'prixAuKg' => $productData['prixAuKg'],
                'image' => $relativeImagePath,
            ]);
            $session->getFlashBag()->add('success', 'Le produit à été ajouter avec succès.');
            return $this->redirectToRoute('app_produits');
        }
        return $this->render('Admin/ajout-produit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    #[Security("is_granted('ROLE_ADMIN)")]
    #[Route('/admin/commandes', name: 'app_commandes')]
    public function commande(DatabaseManager $databaseManager): Response
    {
        $result = $databaseManager->executeQuery('SELECT * FROM commande ORDER BY commande.id DESC');
        return $this->render('/Admin/commandes.html.twig', [
            'controller_name' => 'HomeController',
            'result' => $result,
        ]);
    }
    #[Security("is_granted('ROLE_ADMIN)")]
    #[Route('/admin/commandes/{id_commande}', name: 'admin_commande_details')]
    public function commandeDetails(DatabaseManager $databaseManager, $id_commande): Response
    {
        $result = $databaseManager->executeQuery('
        SELECT commande.id, lignecommande.quantite AS q, produit.nom AS n, produit.prixAuKg AS prix 
        FROM `commande`
        JOIN lignecommande ON commande.id = lignecommande.id_commande 
        JOIN produit ON lignecommande.id_produit = produit.id
        WHERE commande.id = :id', ['id' => $id_commande]);

        return $this->render('/Admin/commandes-details.html.twig', [
            'controller_name' => 'HomeController',
            'result' => $result,
        ]);
    }
    #[Security("is_granted('ROLE_ADMIN)")]
    #[Route('/admin/commandes/{id_commande}/changer_etat', name: 'changer_etat_commande', methods: ['POST'])]
    public function changerEtatCommande(DatabaseManager $databaseManager, $id_commande): Response
    {
        $databaseManager->execute(
            'UPDATE commande SET id_etat_commande = :etat WHERE id = :id_commande',
            ['etat' => 3, 'id_commande' => $id_commande]
        );
        $clientInfo = $databaseManager->executeQuery('
        SELECT coordonnesclient.email, coordonnesclient.nom
        FROM coordonnesclient
        JOIN commande ON coordonnesclient.id = commande.client_id
        WHERE commande.id = :id_commande',
        ['id_commande' => $id_commande]
        );
        if ($clientInfo[0] && isset($clientInfo[0]['email'])) {
        $email = (new Email())
            ->from('clicketcollect.contact@gmail.com')
            ->to($clientInfo[0]['email'])
            ->subject('Votre commande est prête')
            ->html('Bonjour ' . $clientInfo[0]['nom'] . ', votre commande est prête.');
    
        $this->mailer->send($email);
        }
        return $this->redirectToRoute('admin_commande_details', ['id_commande' => $id_commande]);
    }
    #[Security("is_granted('ROLE_ADMIN)")]
    #[Route('/admin/commandes/{id_commande}/final_etat', name: 'changer_etat_commande_final', methods: ['POST'])]
    public function finalEtatCommande(DatabaseManager $databaseManager, $id_commande): Response
    {
        $databaseManager->execute(
            'UPDATE commande SET id_etat_commande = :etat WHERE id = :id_commande',
            ['etat' => 4, 'id_commande' => $id_commande]
        );
        $clientInfo = $databaseManager->executeQuery('
        SELECT coordonnesclient.email, coordonnesclient.nom
        FROM coordonnesclient
        JOIN commande ON coordonnesclient.id = commande.client_id
        WHERE commande.id = :id_commande',
        ['id_commande' => $id_commande]
        );
    
        $email = (new Email())
            ->from('clicketcollect.contact@gmail.com')
            ->to($clientInfo['email'])
            ->subject('Votre commande est prête')
            ->html('Bonjour ' . $clientInfo['nom'] . ', votre commande est prête.');
    
        $this->mailer->send($email);
        return $this->redirectToRoute('admin_commande_details', ['id_commande' => $id_commande]);
    }
    
    #[Route('/panier/{id_produit}', name: 'app_ajouter_panier', methods: ['POST'])]
    public function ajouterAuPanier(Request $request, DatabaseManager $databaseManager, $id_produit, Session $session): Response
    {
        $idCommande = $session->get('id_commande');
        $id_etat_commande = 1;
            try {
                $insertionResult = $databaseManager->execute('INSERT INTO commande (id_etat_commande) VALUES (:id_etat_commande)', ['id_etat_commande' => $id_etat_commande]);

                $idCommande = $databaseManager->lastInsertId();
                $session->set('id_commande', $idCommande);
            } catch (PDOException $e) {
                die("Erreur SQL lors de l'insertion dans la table 'commande': " . $e->getMessage());
            }
        

        $quantite = $request->request->get('quantite');

        if ($quantite <= 0) {
            return $this->redirectToRoute('app_produits');
        }

        try {
            $insertionLigneCommande = $databaseManager->executeQuery('INSERT INTO lignecommande (id_produit, id_commande, quantite) VALUES (:id_produit, :id_commande, :quantite)', [
                'id_produit' => $id_produit,
                'id_commande' => $idCommande,
                'quantite' => $quantite,
            ]);

            $session->getFlashBag()->add('success', 'Produit ajouté au panier.');
        } catch (PDOException $e) {
            die("Erreur SQL lors de l'insertion dans la table 'lignecommande': " . $e->getMessage());
        }

        return $this->redirectToRoute('app_produits');
    }
   
}
