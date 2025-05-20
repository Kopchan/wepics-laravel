<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;
        return [
//          'id'        => $this->id,
            'name'      => $this->name,
            'type'      => $this->type,
            'hash'      => $this->hash,
            'album'     => $this->when($this->customAlbum, fn () => [
                'hash'     => $this->when(isset($this->customAlbum['hash'    ]), fn() => $this->customAlbum['hash']),
                'alias'    => $this->when(isset($this->customAlbum['alias'   ]), fn() => $this->customAlbum['alias']),
                'name'     => $this->when(isset($this->customAlbum['name'    ]), fn() => $this->customAlbum['name']),
                'sign'     => $this->when(isset($this->customAlbum['sign'    ]), fn() => $this->customAlbum['sign']),
                'ratingId' => $this->when(isset($this->customAlbum['ratingId']), fn() => $this->customAlbum['ratingId']),
            ]),
            'date'      => $this->date,
            'size'      => $this->size,
            'width'     => $this->width,
            'height'    => $this->height,
            'frames'    => $this->when($this->frames,             fn() => $this->frames),
            'duration'  => $this->when($this->duration_ms,        fn() => $this->duration_ms / 1000),
            'bitrate'   => $this->when($this->duration_ms,        fn() => round($this->size * 8 / $this->duration_ms * 1000)),
            'framerate' => $this->when($this->avg_frame_rate_den, fn() => round($this->avg_frame_rate_num / $this->avg_frame_rate_den, 2)),
            'ratingId'  => $this->when($this->age_rating_id, $this->age_rating_id),
            'tags'      => $this->whenLoaded('tags', fn() =>
                $this->when($this->tags->isNotEmpty(), fn () => TagResource::collection($this->tags))
            ),
            'reactions' => $this->whenLoaded('reactions', fn() => $this->when($this->reactions->isNotEmpty(),
                fn () => $this->reactions->groupBy('value')->map(function ($group) use ($userId) {
                    $reactionParams = [];
                    $reactionParams['count']  = $group->count();

                    $youSet = $group->contains(fn ($reaction) =>
                        isset($userId) && $reaction->pivot?->user_id === $userId
                    );
                    if ($youSet)
                        $reactionParams['isYouSet'] = true;

                    return $reactionParams;
                })
            )),
        ];
    }
}
