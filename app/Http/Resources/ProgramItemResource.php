<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->day_of_week,
            'order_no' => $this->order_no,
            'exercise' => $this->exercise,
            'sets' => $this->sets,
            'reps' => $this->reps,
            'rest_seconds' => $this->rest_seconds,
            'notes' => $this->notes,
        ];
    }
}
