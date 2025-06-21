<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code'      => $this->link,
            'expiresAt' => $this->expires_at,
            'joinLimit' => $this->join_limit,
        ];
    }
}
