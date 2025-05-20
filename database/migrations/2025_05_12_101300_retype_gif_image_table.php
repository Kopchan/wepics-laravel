<?php

use App\Console\Commands\StoreIndex;
use App\Models\Album;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Prompts\Output\ConsoleOutput;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\FFProbe;

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
            ->where('images.name', 'like', '%.gif')
            ->where('images.type', 'image')
            ->get();

        $count = $images->count();
        $output->writeln('Selected '. $count .' images');
        $probe = FFProbe::create();

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
                .' '
                .$path
                .' ';

            $output->write($outputStart);

            if (!file_exists($fullPath)) {
                //continue;
                $output->writeln('');
                dd('why not exist?', $fullPath, $image);
            }

            $probeInfo = $probe->streams($fullPath)->videos()->first();

            if (!$probeInfo) {
                $output->write(
                    "\r"
                    .'<fg=red>'
                    . $outputStart
                    .'cannot get ffprobe info'
                    .'</>'
                );
                $output->writeln('');
                continue;
            }

            if ($probeInfo->get('duration_ts') <= 1) {
                $output->write(
                    "\r"
                    .'<fg=yellow>'
                    . $outputStart
                    .'is static image'
                    .'</>'
                );
                $output->writeln('');
                continue;
            }

            $steamContentFields = [
                'type' => 'imageAnimated'
            ];
            $steamContentFields['codec_name'] = $probeInfo->get('codec_name');

            $number = $probeInfo->get('duration');
            if (!str_contains($number, '.')) $number .= '.000';
            [$intPart, $decimalPart] = explode('.', $number, 2);
            $decimalPart = substr($decimalPart . '000', 0, 3);
            $steamContentFields['duration_ms'] = (int)($intPart . $decimalPart);

            $framerate = array_map('intval',
                explode('/', $probeInfo->get('avg_frame_rate'))
            );
            $steamContentFields['avg_frame_rate_num'] = $framerate[0];
            $steamContentFields['avg_frame_rate_den'] = $framerate[1];
            $steamContentFields['frame_count'] = (int)$probeInfo->get('nb_frames');

            $output->write(
                "\r"
                .'<fg=green>'
                . $outputStart
                .'is animated image'
                .'</>'
            );

            DB::table('images')->where('id', $image->id)->update($steamContentFields);
            $output->writeln('');
            //break;
        }
    }

    public function down()
    {
    }
};
