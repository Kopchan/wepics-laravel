<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgeRatingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request)
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'color' => $this->color,
            'preset' => $this->preset,
            'description' => $this->description,
        ];
    }
}
