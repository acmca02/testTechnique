<?php

namespace App\Controller\CodePromo;

use App\Entity\CodePromo;
use App\Form\PromoType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CodePromoController extends AbstractController
{
    #[Route('/code/promo', name: 'code_promo')]
    public function index(): Response
    {

        return $this->render('code_promo/index.html.twig', [
            'controller_name' => 'CodePromoController',
        ]);
    }
}
