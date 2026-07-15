<?php

namespace Modules\Workspace\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Workspace\Support\CallPayload;

class CallResource extends JsonResource
{
    private ?array $connection = null;

    public function withConnection(?array $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return CallPayload::make($this->resource, $this->connection);
    }
}
