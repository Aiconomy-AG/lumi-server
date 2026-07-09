<?php


namespace Modules\Workspace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules():array
    {
        return [
            'type'=>['required', Rule::in(['direct', 'group'])],
            'name'=>['required_if:type,group', 'nullable', 'string', 'max:255'],
            'participants_employee_ids'=>['required','array','min:1'],
            'participants_employee_ids.*'=>['integer','exists:users,id'],
        ];
    }

}
