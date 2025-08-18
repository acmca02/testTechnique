<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Writer;

/**
 * Service responsible for exporting product data to CSV files and generating statistics.
 */
class ProductExporter
{
    private EntityManagerInterface $entityManager;

    /**
     * @param EntityManagerInterface $entityManager The Doctrine entity manager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Exports product data and statistics to a CSV file for direct HTTP download.
     *
     * @param string|null $filename The desired filename, or null for auto-generation
     * @return void This method streams the file and terminates the script
     * @throws \Exception If the stream or file operations fail
     */
    public function exportToCsv(?string $filename = null): void
    {
        $filename = $this->generateFilename($filename);
        $products = $this->fetchProducts();
        $stats = $this->generateStatistics($products);

        $this->setHttpHeadersForDownload($filename);
        $this->writeCsvToStream($filename, $products);
        $this->saveStatisticsToFile($stats, $filename);

        exit();
    }

    /**
     * Generates a default filename if none provided.
     *
     * @param string|null $filename The custom filename or null
     * @return string The generated filename
     */
    private function generateFilename(?string $filename): string
    {
        return $filename ?? 'products_export_' . date('Y_m_d_H_i_s') . '.csv';
    }

    /**
     * Ensures the export directory exists, creating it if necessary.
     *
     * @return string The export directory path
     */
    private function ensureExportDirectoryExists(): string
    {
        $exportDir = __DIR__ . '/../../public/';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        return $exportDir;
    }

    /**
     * Fetches all products from the database.
     *
     * @return array<Product> List of all product entities
     */
    private function fetchProducts(): array
    {
        return $this->entityManager->getRepository('App\Entity\Product')->findAll();
    }

    /**
     * Sets HTTP headers for CSV file download.
     *
     * @param string $filename The filename for the download
     */
    private function setHttpHeadersForDownload(string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    /**
     * Writes product data to a CSV stream for download.
     *
     * @param string $filename The filename of the CSV
     * @param array<Product> $products List of products to export
     * @throws \Exception If the stream cannot be created
     */
    private function writeCsvToStream(string $filename, array $products): void
    {
        $csv = Writer::createFromStream(fopen('php://output', 'w'));
        $csv->setDelimiter(';');
        $csv->setOutputBOM(Writer::BOM_UTF8);

        $this->writeProductHeaders($csv);
        $this->writeProductRows($csv, $products);
    }

    /**
     * Writes the CSV headers for product data.
     *
     * @param Writer $csv The CSV writer instance
     */
    private function writeProductHeaders(Writer $csv): void
    {
        $headers = array_map('mb_convert_encoding', ['ID', 'Nom', 'Description', 'Prix', 'Stock', 'Statut Stock'], array_fill(0, 6, 'UTF-8'));
        $csv->insertOne($headers);
    }

    /**
     * Writes the product data rows to the CSV.
     *
     * @param Writer $csv The CSV writer instance
     * @param array<Product> $products List of products to export
     */
    private function writeProductRows(Writer $csv, array $products): void
    {
        if (empty($products)) {
            $csv->insertOne(['Aucun produit à exporter']);
            return;
        }

        foreach ($products as $product) {
            $row = $this->formatProductRow($product);
            $row = array_map('mb_convert_encoding', $row, array_fill(0, count($row), 'UTF-8'));
            $csv->insertOne($row);
        }
    }

    /**
     * Formats a product into a CSV row.
     *
     * @param object $product The product entity
     * @return array The formatted row data
     */
    private function formatProductRow(object $product): array
    {
        $stockStatus = $this->determineStockStatus($product->getStock());
        $formattedPrice = number_format($product->getPrice(), 2, ',', ' ') . ' €';
        $description = $this->formatDescription($product->getDescription());

        return [
            (string)$product->getId(),
            $product->getName(),
            $description,
            $formattedPrice,
            (string)$product->getStock(),
            $stockStatus,
        ];
    }

    /**
     * Determines the stock status based on the quantity.
     *
     * @param int $stock The stock quantity
     * @return string The stock status
     */
    private function determineStockStatus(int $stock): string
    {
        return match (true) {
            $stock == 0 => 'Rupture',
            $stock <= 5 => 'Stock faible',
            $stock <= 10 => 'Stock moyen',
            default => 'Stock élevé',
        };
    }

    /**
     * Formats the product description for CSV export.
     *
     * @param string|null $description The product description
     * @return string The formatted description
     */
    private function formatDescription(?string $description): string
    {
        if (!$description) {
            return 'Aucune description';
        }

        $description = str_replace(["\n", "\r", "\t"], ' ', $description);
        $description = trim($description);
        return strlen($description) > 100 ? substr($description, 0, 97) . '...' : $description;
    }

    /**
     * Generates statistics based on product data.
     *
     * @param array<Product> $products List of products
     * @return array<string, mixed> The calculated statistics
     */
    private function generateStatistics(array $products): array
    {
        $totalProducts = count($products);
        $totalValue = 0;
        $outOfStock = 0;
        $lowStock = 0;

        foreach ($products as $product) {
            $totalValue += $product->getPrice() * $product->getStock();
            if ($product->getStock() == 0) {
                $outOfStock++;
            } elseif ($product->getStock() <= 5) {
                $lowStock++;
            }
        }

        return [
            'totalProducts' => $totalProducts,
            'totalValue' => $totalValue,
            'outOfStock' => $outOfStock,
            'lowStock' => $lowStock,
        ];
    }

    /**
     * Saves statistics to a file on the server.
     *
     * @param array<string, mixed> $stats The statistics data
     * @param string $filename The filename of the exported CSV
     * @throws \Exception If the file cannot be written
     */
    private function saveStatisticsToFile(array $stats, string $filename): void
    {
        $exportDir = $this->ensureExportDirectoryExists();
        $statsFile = $exportDir . 'stats_' . basename($this->generateFilename(null));
        $statsHandle = fopen($statsFile, 'w');

        if ($statsHandle === false) {
            throw new \Exception('Unable to create statistics file: ' . $statsFile);
        }

        fprintf($statsHandle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fwrite($statsHandle, "=== STATISTIQUES EXPORT PRODUITS ===\n");
        fwrite($statsHandle, "Date d'export: " . date('d/m/Y H:i:s') . "\n");
        fwrite($statsHandle, "Nombre total de produits: " . $stats['totalProducts'] . "\n");
        fwrite($statsHandle, "Valeur totale du stock: " . number_format($stats['totalValue'], 2, ',', ' ') . " €\n");
        fwrite($statsHandle, "Produits en rupture: " . $stats['outOfStock'] . "\n");
        fwrite($statsHandle, "Produits en stock faible: " . $stats['lowStock'] . "\n");
        fwrite($statsHandle, "Fichier CSV généré: " . $filename . "\n");
        fclose($statsHandle);
    }

    /**
     * Retrieves a list of existing export files (stats files only).
     *
     * @return array<array{name: string, size: int, date: string}> List of export file details
     */
    public function getExportsList(): array
    {
        $exportDir = $this->ensureExportDirectoryExists();
        $files = [];

        if (is_dir($exportDir)) {
            $handle = opendir($exportDir);
            while (($file = readdir($handle)) !== false) {
                if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'csv') {
                    $filepath = $exportDir . $file;
                    $files[] = [
                        'name' => $file,
                        'size' => filesize($filepath),
                        'date' => date('d/m/Y H:i:s', filemtime($filepath)),
                    ];
                }
            }
            closedir($handle);
        }

        return $files;
    }
}
