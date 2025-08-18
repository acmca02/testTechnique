<?php

namespace App\Controller\Product;

use App\Entity\Product;
use App\Form\PromoType;
use App\Repository\CodePromoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShowProductController extends AbstractController
{
    #[Route('/products/{id}', name: 'product_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function __invoke(Product $product, Request $request, CodePromoRepository $repository): Response
    {
        $promoForm = $this->createForm(PromoType::class);
        $promoForm->handleRequest($request);

        $result = null;
        $discountedPrice = null;

        if ($promoForm->isSubmitted() && $promoForm->isValid()) {
            $code = $promoForm->get('nom')->getData();
            [$result, $discountedPrice] = $this->validateCodePromo($product, $code, $repository);
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'promoForm' => $promoForm->createView(),
            'result' => $result,
            'discountedPrice' => $discountedPrice,
        ]);
    }

    /**
     * Vérifie le code promo et calcule le prix réduit si applicable.
     *
     * @param string $code
     * @param CodePromoRepository $repository
     * @return array [string $message, float|null $discountedPrice]
     */
    private function validateCodePromo(Product $product, string $code, CodePromoRepository $repository): array
    {
        $codePromo = $repository->findOneBy(['nom' => $code]);

        if (!$codePromo) {
            return ["Code promo invalide", null];
        }

        if ($codePromo->getProduct()->getId() !== $product->getId()) {
            return ["Ce code promo n'est pas applicable à ce produit", null];
        }

        $now = new \DateTime();
        $expiration = $codePromo->getExpirationDate();

        if ($expiration && $expiration < $now) {
            return ["Ce code promo a expiré", null];
        }

        $reduction = $codePromo->getPourcentage();
        $originalPrice = $codePromo->getProduct()->getPrice();
        $discountedPrice = $originalPrice - ($originalPrice * $reduction / 100);

        // $discountedPrice = $originalPrice * (1 - $reduction / 100);

        return ["Code promo valide : réduction de $reduction%", $discountedPrice];
    }
}
