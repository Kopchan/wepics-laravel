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
            ->select('id', 'name', 'hash')
            ->whereRaw('LENGTH(hash) = 16')
            ->get();

        $count = $images->count();
        $output->writeln('Selected '. $count .' images');

        foreach ($images as $i => $image) {
            $output->write(
                '['
                . StoreIndex::counter($i + 1, $count)
                .']'
                .' '
                .'<fg=gray>#</>'
                . StoreIndex::formatNumber($image->id)
                .' '
                . $image->hash
                .' '
            );

            $binary = hex2bin($image->hash);
            if ($binary === false) {
                //continue;
                dd('why hex2bin errored', $image->hash, $image);
            }
            $base64url = base64url_encode($binary);
            $output->write(
                '->'
                .' '
                .'<fg=green>'
                . $base64url
                .'</>'
            );

            try {
                DB::table('images')->where('id', $image->id)->update(['hash' => $base64url]);
            }
            catch (\Exception $e) {
                $image->newHash = $base64url;
                $output->write(' <fg=red>failed update, delete</>');
                DB::table('images')->where('id', $image->id)->delete();
            }

            $output->writeln('');
            //break;
        }
    }

    public function down()
    {

    }
};
