<?php

namespace App\Controller\backoffice;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AssetController extends AbstractController
{
    #[Route('/backoffice/asset', name: 'app_backoffice_asset')]
    public function index(): Response
    {
        return $this->render('backoffice/asset/index.html.twig', [
            'controller_name' => 'AssetController',
        ]);
    }
}
