<?php

use App\Console\Commands\StoreIndex;
use App\Models\Album;
use App\Models\Image;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\ConsoleOutput;

return new class extends Migration
{
    public function up()
    {
        $output = new ConsoleOutput();
        $output->writeln('');

        $files = Storage::files('thumbs');

        $filtered = collect($files)->filter(function ($path) {
            return preg_match('/\/([a-f0-9]{16})-(\w+)(\d+)\.webp$/', $path);
        })->values();

        $count = $filtered->count();
        $output->writeln('Selected '. $count .' thumbs');

        foreach ($filtered as $i => $path) {
            preg_match('/thumbsOld\/([a-f0-9]{16})-(\w+)(\d+)\.webp$/', $path, $matches);
            [$full, $hexHash, $orientation, $size] = $matches;
            //dd($path);
            $output->write(
                '['
                . StoreIndex::counter($i + 1, $count)
                .']'
                .' '
                . $path
                .' '
            );

            $binary = hex2bin($hexHash);
            if ($binary === false) {
                //continue;
                dd('why hex2bin errored', $matches);
            }
            $base64url = base64url_encode($binary);
            $output->write(
                '->'
                .' '
                .'<fg=green>'
                . $base64url
                .'</>'
            );

            $newPath = "thumbs/{$orientation}{$size}/{$base64url}.webp";
            Storage::makeDirectory(dirname($newPath));
            Storage::move($path, $newPath);
            //dd($newPath);

            $output->writeln('');
            //break;
        }
    }

    public function down()
    {

    }
};
