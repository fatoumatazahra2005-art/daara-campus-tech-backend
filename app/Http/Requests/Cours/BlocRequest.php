<?php

namespace App\Http\Requests\Cours;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bloc TEXTE (contenu), IMAGE (url_image, alt) ou CODE (code, langage).
 * À la création le type est fourni ; à la modification il est celui du bloc.
 */
class BlocRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creation = $this->isMethod('POST');
        $requis = $creation ? 'required' : 'sometimes';
        $type = $creation ? $this->input('type_bloc') : $this->route('bloc')?->type_bloc;

        $regles = $creation
            ? ['type_bloc' => ['required', Rule::in(['TEXTE', 'IMAGE', 'CODE'])]]
            : [];

        return $regles + match ($type) {
                'TEXTE' => ['contenu' => [$requis, 'string']],
                'IMAGE' => ['url_image' => [$requis, 'string', 'max:500'], 'alt' => ['nullable', 'string', 'max:255']],
                'CODE' => ['code' => [$requis, 'string'], 'langage' => [$requis, 'string', 'max:30']],
                default => [],
            };
    }
}
