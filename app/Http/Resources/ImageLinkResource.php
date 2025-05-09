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
//          'id'         => $this->id,
            'name'       => $this->name,
            'hash'       => $this->hash,
            'album'      => $this->when($this->customAlbum, fn () => [
                'hash'     => $this->when(isset($this->customAlbum['hash'    ]), fn() => $this->customAlbum['hash']),
                'alias'    => $this->when(isset($this->customAlbum['alias'   ]), fn() => $this->customAlbum['alias']),
                'name'     => $this->when(isset($this->customAlbum['name'    ]), fn() => $this->customAlbum['name']),
                'sign'     => $this->when(isset($this->customAlbum['sign'    ]), fn() => $this->customAlbum['sign']),
                'ratingId' => $this->when(isset($this->customAlbum['ratingId']), fn() => $this->customAlbum['ratingId']),
            ]),
            'date'       => $this->date,
            'size'       => $this->size,
            'width'      => $this->width,
            'height'     => $this->height,
            'ratingId'   => $this->when($this->age_rating_id, $this->age_rating_id),
            'tags'       => $this->whenLoaded('tags', fn() =>
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
