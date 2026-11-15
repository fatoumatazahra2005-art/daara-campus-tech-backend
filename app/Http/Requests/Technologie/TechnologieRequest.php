<?php

namespace App\Http\Requests\Technologie;

use App\Models\Technologie;
use Illuminate\Foundation\Http\FormRequest;

class TechnologieRequest extends FormRequest
{
    /** Orthographe officielle des technologies courantes (clé = nom en minuscules sans espaces). */
    private const NOMS_OFFICIELS = [
        'html' => 'HTML', 'html5' => 'HTML',
        'css' => 'CSS', 'css3' => 'CSS',
        'sass' => 'Sass', 'scss' => 'SCSS',
        'javascript' => 'JavaScript', 'js' => 'JavaScript',
        'typescript' => 'TypeScript', 'ts' => 'TypeScript',
        'angular' => 'Angular', 'react' => 'React', 'reactnative' => 'React Native',
        'vue' => 'Vue.js', 'vuejs' => 'Vue.js', 'nextjs' => 'Next.js',
        'node' => 'Node.js', 'nodejs' => 'Node.js', 'express' => 'Express',
        'tailwind' => 'Tailwind CSS', 'tailwindcss' => 'Tailwind CSS', 'bootstrap' => 'Bootstrap',
        'dart' => 'Dart', 'flutter' => 'Flutter', 'kotlin' => 'Kotlin', 'swift' => 'Swift',
        'android' => 'Android', 'java' => 'Java', 'spring' => 'Spring', 'springboot' => 'Spring Boot',
        'php' => 'PHP', 'laravel' => 'Laravel', 'symfony' => 'Symfony',
        'python' => 'Python', 'django' => 'Django', 'flask' => 'Flask',
        'c' => 'C', 'c++' => 'C++', 'cpp' => 'C++', 'c#' => 'C#', 'csharp' => 'C#',
        'dotnet' => '.NET', '.net' => '.NET',
        'go' => 'Go', 'golang' => 'Go', 'rust' => 'Rust', 'ruby' => 'Ruby',
        'sql' => 'SQL', 'mysql' => 'MySQL', 'postgresql' => 'PostgreSQL', 'postgres' => 'PostgreSQL',
        'mongodb' => 'MongoDB', 'firebase' => 'Firebase',
        'git' => 'Git', 'github' => 'GitHub', 'docker' => 'Docker', 'linux' => 'Linux',
        'bash' => 'Bash', 'figma' => 'Figma',
    ];

    public function authorize(): bool
    {
        return true; // La Policy gère les autorisations.
    }

    /** Nettoie et normalise le nom : « flutter » → « Flutter », « svelte » → « Svelte ». */
    protected function prepareForValidation(): void
    {
        if (!$this->has('nom')) {
            return;
        }

        $nom = preg_replace('/\s+/', ' ', trim((string) $this->input('nom')));
        $cle = mb_strtolower(str_replace([' ', '-', '_'], '', $nom));

        if (isset(self::NOMS_OFFICIELS[$cle])) {
            $nom = self::NOMS_OFFICIELS[$cle];
        } elseif (isset(self::NOMS_OFFICIELS[mb_strtolower($nom)])) {
            $nom = self::NOMS_OFFICIELS[mb_strtolower($nom)];
        } elseif ($nom !== '') {
            $nom = mb_strtoupper(mb_substr($nom, 0, 1)) . mb_strtolower(mb_substr($nom, 1));
        }

        $this->merge(['nom' => $nom]);
    }

    public function rules(): array
    {
        $technologie = $this->route('technologie');

        return [
            'nom' => [
                'required',
                'string',
                'max:80',
                // Unicité insensible à la casse : Flutter = flutter = FLUTTER.
                function (string $attribut, mixed $valeur, \Closure $echec) use ($technologie) {
                    $existe = Technologie::query()
                        ->whereRaw('LOWER(TRIM(nom)) = ?', [mb_strtolower(trim((string) $valeur))])
                        ->when($technologie, fn ($q) => $q->whereKeyNot($technologie->id))
                        ->first();

                    if ($existe) {
                        $echec("La technologie « {$existe->nom} » existe déjà.");
                    }
                },
            ],
            'description' => ['nullable', 'string'],
            'icone' => ['nullable', 'string', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire.',
            'nom.max' => 'Le nom ne doit pas dépasser 80 caractères.',
            'icone.max' => "L'URL de l'icône ne doit pas dépasser 255 caractères.",
        ];
    }
}
