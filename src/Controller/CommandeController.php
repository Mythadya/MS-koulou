<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Detail;
use App\Form\CommandeType;
use App\Repository\PlatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class CommandeController extends AbstractController
{
    private $platRepo;

    public function __construct(PlatRepository $platRepo)
    {
        $this->platRepo = $platRepo;
    }

    #[Route('/commande', name: 'app_commande')]    
    public function index(Request $request, EntityManagerInterface $em, SessionInterface $session, MailerInterface $mailer): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
    
        /** @var \App\Entity\Utilisateur $user */
        $user = $this->getUser();

        $form = $this->createForm(CommandeType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()){

            // Récupération du panier de la session
            $panier = $session->get('panier', []);

            if (empty($panier)) {
                // Si le panier est vide, rediriger l'utilisateur avec un message
                $this->addFlash('error', 'Votre panier est vide.');
                return $this->redirectToRoute('app_panier');
            }

            $total = 0;
            $plats = $this->platRepo->findBy(['id' => array_keys($panier)]); // Récupération des plats en une seule requête

            // Création de la commande
            $commande = new Commande();
            $commande->setDateCommande(new \DateTimeImmutable());
            $commande->setEtat(0);
            $commande->setUtilisateurs($user);

            // Persistance de la commande
            $em->persist($commande);

            foreach ($plats as $plat) {
                $quantite = $panier[$plat->getId()];
                $total += $plat->getPrix() * $quantite;

                // Créer les détails de la commande
                $detail = new Detail();
                $detail->setQuantite($quantite);
                $detail->setCommandes($commande);
                $detail->setPlats($plat);

                // Persister le détail de la commande
                $em->persist($detail);
            }

            // Mise à jour du total de la commande
            $commande->setTotal($total);

            // Exécuter les changements en une seule fois
            $em->flush();

            // Envoyer l'e-mail de confirmation
            $email = (new Email())
                ->from('noreply@example.com')  // L'adresse d'envoi
                ->to($user->getEmail())        // L'e-mail du client
                ->subject('Confirmation de votre commande')
                ->text(sprintf("Bonjour %s,\n\nVotre commande d'un montant de %.2f € a été bien reçue.\nMerci de votre achat !", $user->getNom(), $total));

            $mailer->send($email);  // Envoi de l'e-mail

            // Vider le panier après la validation
            $session->remove('panier');

            // Ajouter un flash message de confirmation
            $this->addFlash('success', 'Votre commande a bien été validée et un e-mail de confirmation vous a été envoyé.');

            // Redirection après validation
            return $this->redirectToRoute('app_index');
        }

        // Si le formulaire n'est pas soumis ou n'est pas valide, afficher la page du formulaire
        return $this->render('commande/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
