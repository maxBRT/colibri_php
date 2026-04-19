<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;
use RuntimeException;

class SourceSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = database_path('seeders/data/sources.csv');

        if (! file_exists($csvPath)) {
            throw new RuntimeException("Sources CSV not found: {$csvPath}");
        }

        $handle = fopen($csvPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open sources CSV: {$csvPath}");
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            throw new RuntimeException('Sources CSV is empty.');
        }

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || count($row) !== count($header)) {
                continue;
            }

            $entry = array_combine($header, $row);

            if ($entry === false) {
                continue;
            }

            $id = trim((string) ($entry['id'] ?? ''));
            $name = trim((string) ($entry['name'] ?? ''));
            $url = trim((string) ($entry['url'] ?? ''));
            $category = trim((string) ($entry['category'] ?? ''));

            if ($id === '' || $name === '' || $url === '' || $category === '') {
                continue;
            }

            Source::query()->updateOrCreate(
                ['id' => $id],
                [
                    'name' => $name,
                    'url' => $url,
                    'category' => $category,
                ]
            );
        }

        fclose($handle);
    }
}
