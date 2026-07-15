<?php

namespace Modules\Workspace\AiTools;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

abstract class AbstractAiTool implements ToolContract
{
    public function declaration(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => $this->parametersSchema(),
        ];
    }

    /** @return array{type: string, properties: array, required?: array} */
    abstract protected function parametersSchema(): array;

    /** @return array<string, mixed> */
    public function validate(array $arguments): array
    {
        $validator = Validator::make($arguments, $this->rules());

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $validator->validated();
    }

    protected function taskStatusEnum(): array
    {
        return ['complete', 'to_do', 'in_progress', 'blocked'];
    }
}
