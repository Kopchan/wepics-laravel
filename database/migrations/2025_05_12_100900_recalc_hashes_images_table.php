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

        $images = DB::table('images')
            ->join('albums', 'images.album_id', '=', 'albums.id')
            ->select(
                'images.id',
                'images.name',
                'albums.path as album_path'
            )
            ->whereRaw('LENGTH(images.hash) = 32')
            ->get();

        $count = $images->count();
        $output->writeln('Selected '. $count .' images');

        foreach ($images as $i => $image) {
            $path = $image->album_path . $image->name;
            $fullPath = Storage::path('images'.$path);

            $outputStart =
                '['
                . StoreIndex::counter($i + 1, $count)
                .']'
                .' '
                .'<fg=gray>#</>'
                . StoreIndex::formatNumber($image->id)
                .' ';

            $output->write($outputStart .'            '. $path);

            if (!file_exists($fullPath)) {
                //continue;
                dd('why not exist?', $fullPath, $image);
            }

            $base64url = base64url_encode(hash_file('xxh3', $fullPath, true));
            $output->write(
                "\r"
                . $outputStart
                .'<fg=green>'
                . $base64url
                .'</>'
                .' '
                . $path
            );

            DB::table('images')->where('id', $image->id)->update(['hash' => $base64url]);
            $output->writeln('');
            //break;
        }
    }

    public function down()
    {

    }
};
