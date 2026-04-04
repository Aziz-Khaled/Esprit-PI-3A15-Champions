<?php

namespace App\Controller\frontOffice;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ProjetController extends AbstractController
{
    /**
     * Dashboard FrontOffice
     */
    #[Route('/projet', name: 'app_projet')]
    public function index(): Response
    {
        // On ajoute "front_office/" devant car c'est le dossier parent
        return $this->render('front_office/projet/index.html.twig');
    }

}