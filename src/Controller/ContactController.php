<?php

namespace App\Controller;

use App\Entity\Contact;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\ContactFormType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        // Création de l'entité Contact et du formulaire
        $contact = new Contact();
        $form = $this->createForm(ContactFormType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Enregistrement des données en base de données
            $em->persist($contact);
            $em->flush();

            // Création de l'email
            $email = (new Email())
                ->from($contact->getEmail())  // L'adresse e-mail saisie par l'utilisateur
                ->to('admin@votre-site.fr')   // L'adresse de réception
                ->subject('Nouveau message de contact')
                ->text(sprintf(
                    "Nom: %s\nPrénom: %s\nEmail: %s\nTéléphone: %s\nMessage: %s",
                    $contact->getNom(),
                    $contact->getPrenom(),
                    $contact->getEmail(),
                    $contact->getTelephone(),
                    $contact->getDemande()
                ));

            // Envoi de l'e-mail
            $mailer->send($email);

            // Message flash pour informer l'utilisateur que le message a été envoyé
            $this->addFlash('success', 'Votre message a été envoyé avec succès.');

            // Redirection après soumission réussie
            return $this->redirectToRoute('app_contact');
        }

        // Rendu du formulaire si non soumis ou non valide
        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
