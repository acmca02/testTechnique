<?php

namespace App\DataFixtures;

use App\Entity\CodePromo;
use App\Repository\ProductRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class Promo extends Fixture
{
    private ProductRepository $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function load(ObjectManager $manager): void
    {
        // $faker = Factory::create();

        // // Récupérer tous les produits existants
        // $products = $this->productRepository->findAll();

        // foreach ($products as $product) {
        //     $nbCodes = rand(1, 2);

        //     for ($i = 0; $i < $nbCodes; $i++) {
        //         $codePromo = new CodePromo();
        //         $codePromo->setNom(strtoupper($faker->word) . rand(5, 50));
        //         $codePromo->setPourcentage(rand(5, 50));
        //         $codePromo->setProduct($product);

        //         // Générer une date d'expiration avant ou après aujourd'hui
        //         if (rand(0, 1)) {
        //             // Code déjà expiré : date dans le passé
        //             $codePromo->setExpirationDate($faker->dateTimeBetween('-1 year', 'now'));
        //         } else {
        //             // Code encore valide : date dans le futur
        //             $codePromo->setExpirationDate($faker->dateTimeBetween('now', '+1 year'));
        //         }

        //         $manager->persist($codePromo);
        //     }

        $manager->flush();
    }
}
