<?php

namespace App\Console\Commands;

use App\Jobs\ChunkOrderCSVJob;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Throwable;

class FileImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:file-import {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command is for import a large scale of orders data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->argument('file');

        $handleFile = fopen($path, 'r');

        if (! $handleFile) { $this->error("Cannot open $path"); return 1; }

        // Skip header if exists
        $header = fgetcsv($handleFile);

        $chunkSize = 200;
        $buffer = [];
        $jobs = [];

        while (($row = fgetcsv($handleFile)) !== false) {
            $buffer[] = $row;
            if (count($buffer) >= $chunkSize) {
                $jobs[] = new ChunkOrderCSVJob($buffer);
                $buffer = [];
            }
        }
        if (count($buffer)) {
            $jobs[] = new ChunkOrderCSVJob($buffer);
        }
        fclose($handleFile);

        $batch = Bus::batch($jobs)
            ->then(function (Batch $batch) {
                info("All import chunks finished. Batch ID: {$batch->id}");
            })
            ->catch(function (Batch $batch, Throwable $e) {
                info("Import failed in batch {$batch->id}: {$e->getMessage()}");
            })
            ->dispatch();

        $this->info("Started batch import: {$batch->id}");

        $this->info('Dispatched '.count($jobs)." jobs to queue.");
    }
}
